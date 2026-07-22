<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */

require_once __DIR__ . '/functions.php';

class MetricsIngestQueue
{
    public const DEFAULT_STREAM = 'ampnm:metrics:ingest';
    public const DEFAULT_DLQ_STREAM = 'ampnm:metrics:dead-letter';

    public static function ensureQueueTables(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `metrics_ingest_queue` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `idempotency_key` VARCHAR(255) NOT NULL,
            `message_type` VARCHAR(50) NOT NULL,
            `payload_json` LONGTEXT NOT NULL,
            `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
            `last_error` TEXT NULL,
            `queued_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `processed_at` TIMESTAMP NULL,
            UNIQUE KEY `uniq_metrics_ingest_queue_idem` (`idempotency_key`),
            KEY `idx_metrics_ingest_queue_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `metrics_ingest_dead_letter` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `idempotency_key` VARCHAR(255) NOT NULL,
            `message_type` VARCHAR(50) NOT NULL,
            `payload_json` LONGTEXT NOT NULL,
            `error_reason` TEXT NULL,
            `failed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_metrics_ingest_dead_letter_idem` (`idempotency_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `metrics_ingest_dedup` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `idempotency_key` VARCHAR(255) NOT NULL,
            `message_type` VARCHAR(50) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'processing',
            `last_error` TEXT NULL,
            `processed_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_metrics_ingest_dedup_idem` (`idempotency_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function enqueue(PDO $pdo, array $message): array
    {
        self::ensureQueueTables($pdo);

        $idempotencyKey = (string)($message['idempotency_key'] ?? '');
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('idempotency_key is required for ingest enqueue');
        }

        $streamName = getenv('METRICS_INGEST_STREAM') ?: self::DEFAULT_STREAM;
        $transport = 'database';
        $messageId = null;

        if (class_exists('Redis')) {
            try {
                $redis = new Redis();
                $redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
                $redisPort = (int)(getenv('REDIS_PORT') ?: 6379);
                $redisTimeout = (float)(getenv('REDIS_TIMEOUT') ?: 1.5);
                $redis->connect($redisHost, $redisPort, $redisTimeout);

                if (($password = getenv('REDIS_PASSWORD')) !== false && $password !== '') {
                    $redis->auth($password);
                }
                if (($db = getenv('REDIS_DB')) !== false && $db !== '') {
                    $redis->select((int)$db);
                }

                $messageId = $redis->xAdd($streamName, '*', ['payload' => json_encode($message)]);
                $transport = 'redis_stream';
            } catch (Throwable $e) {
                error_log('Metrics queue redis enqueue failed, falling back to db queue: ' . $e->getMessage());
            }
        }

        if ($transport === 'database') {
            $stmt = $pdo->prepare("INSERT INTO metrics_ingest_queue (idempotency_key, message_type, payload_json, status) VALUES (?, ?, ?, 'pending') ON DUPLICATE KEY UPDATE queued_at = CURRENT_TIMESTAMP");
            $stmt->execute([
                $idempotencyKey,
                (string)($message['message_type'] ?? 'metrics_submit'),
                json_encode($message),
            ]);
            $messageId = (string)$pdo->lastInsertId();
        }

        return [
            'transport' => $transport,
            'message_id' => $messageId,
            'stream' => $streamName,
        ];
    }
}
