#!/usr/bin/env php
<?php

require_once __DIR__ . '/../../includes/metrics_ingest_service.php';
require_once __DIR__ . '/../../includes/telemetry.php';

$once = in_array('--once', $argv, true);
$maxAttempts = (int)(getenv('METRICS_INGEST_MAX_ATTEMPTS') ?: 5);
if ($maxAttempts < 1) {
    $maxAttempts = 5;
}

$pdo = getDbConnection();
MetricsIngestQueue::ensureQueueTables($pdo);
ensureTelemetrySchema($pdo);

$stream = getenv('METRICS_INGEST_STREAM') ?: MetricsIngestQueue::DEFAULT_STREAM;
$dlqStream = getenv('METRICS_INGEST_DLQ_STREAM') ?: MetricsIngestQueue::DEFAULT_DLQ_STREAM;
$group = getenv('METRICS_INGEST_GROUP') ?: 'metrics_workers';
$consumer = getenv('METRICS_INGEST_CONSUMER') ?: ('worker-' . getmypid());
$supportsRedis = class_exists('Redis');

if ($supportsRedis) {
    try {
        $redis = new Redis();
        $redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int)(getenv('REDIS_PORT') ?: 6379), (float)(getenv('REDIS_TIMEOUT') ?: 1.5));
        if (($password = getenv('REDIS_PASSWORD')) !== false && $password !== '') {
            $redis->auth($password);
        }
        if (($db = getenv('REDIS_DB')) !== false && $db !== '') {
            $redis->select((int)$db);
        }
        try {
            $redis->xGroup('CREATE', $stream, $group, '0', true);
        } catch (Throwable $e) {
        }
    } catch (Throwable $e) {
        $supportsRedis = false;
        error_log('metrics worker redis connect failed, using db queue: ' . $e->getMessage());
    }
}

function failToDeadLetter(PDO $pdo, array $message, string $error): void
{
    $stmt = $pdo->prepare("INSERT INTO metrics_ingest_dead_letter (idempotency_key, message_type, payload_json, error_reason) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE error_reason = VALUES(error_reason), failed_at = CURRENT_TIMESTAMP");
    $stmt->execute([
        (string)($message['idempotency_key'] ?? ''),
        (string)($message['message_type'] ?? 'metrics_submit'),
        json_encode($message),
        $error,
    ]);
}

function processWithRetry(PDO $pdo, array $message, int $maxAttempts): array
{
    $attempt = (int)($message['attempt'] ?? 0) + 1;
    $message['attempt'] = $attempt;
    $correlationId = (string)($message['correlation_id'] ?? telemetryCorrelationId());

    try {
        telemetryLog('worker.message.start', ['correlation_id' => $correlationId, 'attempt' => $attempt, 'message_type' => $message['message_type'] ?? 'metrics_submit']);
        $result = MetricsIngestService::processMessage($pdo, $message);
        telemetryLog('worker.message.done', ['correlation_id' => $correlationId, 'status' => 'ok']);
        return ['status' => 'ok', 'result' => $result, 'message' => $message];
    } catch (InvalidArgumentException $e) {
        failToDeadLetter($pdo, $message, $e->getMessage());
        telemetryEmitAlert($pdo, 'ingest_failed_job', 'warning', 'workers', 'Message moved to dead letter queue', ['error' => $e->getMessage()], $correlationId);
        return ['status' => 'dead_letter', 'error' => $e->getMessage(), 'message' => $message];
    } catch (Throwable $e) {
        if ($attempt >= $maxAttempts) {
            failToDeadLetter($pdo, $message, $e->getMessage());
            telemetryEmitAlert($pdo, 'ingest_failed_job', 'critical', 'workers', 'Message exceeded max attempts', ['error' => $e->getMessage(), 'attempt' => $attempt], $correlationId);
            return ['status' => 'dead_letter', 'error' => $e->getMessage(), 'message' => $message];
        }

        return ['status' => 'retry', 'error' => $e->getMessage(), 'message' => $message];
    }
}

while (true) {
    telemetryMarkHeartbeat('metrics-worker');
    $processedAny = false;

    if ($supportsRedis) {
        $entries = $redis->xReadGroup($group, $consumer, [$stream => '>'], 20, 2000);
        $rows = $entries[$stream] ?? [];

        foreach ($rows as $entryId => $fields) {
            $processedAny = true;
            $message = json_decode($fields['payload'] ?? '{}', true);
            $outcome = processWithRetry($pdo, $message, $maxAttempts);

            if ($outcome['status'] === 'retry') {
                $redis->xAdd($stream, '*', ['payload' => json_encode($outcome['message'])]);
            } elseif ($outcome['status'] === 'dead_letter') {
                $redis->xAdd($dlqStream, '*', [
                    'payload' => json_encode($outcome['message']),
                    'error' => $outcome['error'],
                ]);
            }

            $redis->xAck($stream, $group, [$entryId]);
        }
    } else {
        $stmt = $pdo->query("SELECT * FROM metrics_ingest_queue WHERE status = 'pending' ORDER BY id ASC LIMIT 20");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $processedAny = true;
            $message = json_decode($row['payload_json'], true) ?: [];
            $message['attempt'] = (int)$row['attempt_count'];
            $outcome = processWithRetry($pdo, $message, $maxAttempts);

            if ($outcome['status'] === 'ok') {
                $update = $pdo->prepare("UPDATE metrics_ingest_queue SET status = 'processed', attempt_count = ?, processed_at = NOW(), last_error = NULL WHERE id = ?");
                $update->execute([(int)$outcome['message']['attempt'], (int)$row['id']]);
            } elseif ($outcome['status'] === 'retry') {
                $update = $pdo->prepare("UPDATE metrics_ingest_queue SET attempt_count = ?, last_error = ? WHERE id = ?");
                $update->execute([(int)$outcome['message']['attempt'], $outcome['error'], (int)$row['id']]);
            } else {
                $update = $pdo->prepare("UPDATE metrics_ingest_queue SET status = 'dead_letter', attempt_count = ?, last_error = ?, processed_at = NOW() WHERE id = ?");
                $update->execute([(int)$outcome['message']['attempt'], $outcome['error'], (int)$row['id']]);
            }
        }
    }

    if ($once) {
        break;
    }

    if (!$processedAny) {
        usleep(250000);
    }
}
