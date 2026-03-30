<?php

require_once __DIR__ . '/functions.php';

function ensureTelemetrySchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `telemetry_db_query` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `component` VARCHAR(40) NOT NULL,
        `operation` VARCHAR(120) NOT NULL,
        `latency_ms` DECIMAL(12,3) NOT NULL,
        `correlation_id` VARCHAR(80) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_telemetry_db_query_component_time` (`component`,`created_at`),
        INDEX `idx_telemetry_db_query_time` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `telemetry_alert_events` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `alert_type` VARCHAR(80) NOT NULL,
        `severity` ENUM('warning','critical') NOT NULL,
        `status` ENUM('active','resolved') NOT NULL DEFAULT 'active',
        `component` VARCHAR(40) NOT NULL,
        `message` VARCHAR(255) NOT NULL,
        `details_json` JSON NULL,
        `correlation_id` VARCHAR(80) NULL,
        `triggered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `resolved_at` TIMESTAMP NULL,
        UNIQUE KEY `uniq_alert_open` (`alert_type`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `telemetry_alert_emissions` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `alert_type` VARCHAR(80) NOT NULL,
        `severity` ENUM('warning','critical') NOT NULL,
        `component` VARCHAR(40) NOT NULL,
        `correlation_id` VARCHAR(80) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_alert_emissions_time` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function telemetryCorrelationId(?string $fallback = null): string
{
    $header = trim((string)($_SERVER['HTTP_X_CORRELATION_ID'] ?? ''));
    if ($header !== '') {
        return substr(preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $header) ?: $header, 0, 80);
    }

    if ($fallback !== null && $fallback !== '') {
        return substr($fallback, 0, 80);
    }

    return 'corr-' . bin2hex(random_bytes(8));
}

function telemetryLog(string $event, array $fields = []): void
{
    $payload = array_merge([
        'ts' => gmdate('c'),
        'event' => $event,
        'correlation_id' => $fields['correlation_id'] ?? null,
    ], $fields);

    error_log(json_encode($payload, JSON_UNESCAPED_SLASHES));
}

function telemetryObserveDbQuery(PDO $pdo, string $component, string $operation, float $latencyMs, ?string $correlationId = null): void
{
    ensureTelemetrySchema($pdo);
    $stmt = $pdo->prepare('INSERT INTO telemetry_db_query (component, operation, latency_ms, correlation_id) VALUES (?, ?, ?, ?)');
    $stmt->execute([$component, $operation, round($latencyMs, 3), $correlationId]);
}

function telemetryTimedExec(PDO $pdo, string $component, string $operation, callable $fn, ?string $correlationId = null)
{
    $start = microtime(true);
    $result = $fn();
    $elapsed = (microtime(true) - $start) * 1000;
    telemetryObserveDbQuery($pdo, $component, $operation, $elapsed, $correlationId);
    return $result;
}

function telemetryMarkHeartbeat(string $name): void
{
    @touch('/tmp/ampnm-' . preg_replace('/[^a-z0-9\-]/', '-', strtolower($name)) . '-heartbeat');
}

function telemetryEmitAlert(PDO $pdo, string $alertType, string $severity, string $component, string $message, array $details = [], ?string $correlationId = null): void
{
    ensureTelemetrySchema($pdo);

    $upsert = $pdo->prepare("INSERT INTO telemetry_alert_events (alert_type, severity, status, component, message, details_json, correlation_id)
        VALUES (?, ?, 'active', ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE severity = VALUES(severity), component = VALUES(component), message = VALUES(message), details_json = VALUES(details_json), correlation_id = VALUES(correlation_id), triggered_at = CURRENT_TIMESTAMP, resolved_at = NULL");
    $upsert->execute([$alertType, $severity, $component, $message, json_encode($details), $correlationId]);

    $emit = $pdo->prepare('INSERT INTO telemetry_alert_emissions (alert_type, severity, component, correlation_id) VALUES (?, ?, ?, ?)');
    $emit->execute([$alertType, $severity, $component, $correlationId]);

    telemetryLog('alert.emitted', [
        'correlation_id' => $correlationId,
        'alert_type' => $alertType,
        'severity' => $severity,
        'component' => $component,
        'message' => $message,
        'details' => $details,
    ]);
}

function telemetryResolveAlert(PDO $pdo, string $alertType, ?string $correlationId = null): void
{
    ensureTelemetrySchema($pdo);
    $stmt = $pdo->prepare("UPDATE telemetry_alert_events SET status = 'resolved', resolved_at = NOW(), correlation_id = COALESCE(?, correlation_id) WHERE alert_type = ? AND status = 'active'");
    $stmt->execute([$correlationId, $alertType]);
}

function telemetryCollectHealth(PDO $pdo): array
{
    ensureTelemetrySchema($pdo);

    $queueDepth = (int)$pdo->query("SELECT COUNT(*) FROM metrics_ingest_queue WHERE status = 'pending'")->fetchColumn();
    $processingLatencyMs = (float)$pdo->query("SELECT COALESCE(AVG(TIMESTAMPDIFF(MICROSECOND, queued_at, processed_at))/1000, 0) FROM metrics_ingest_queue WHERE processed_at IS NOT NULL AND processed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)")->fetchColumn();
    $failedJobs = (int)$pdo->query("SELECT COUNT(*) FROM metrics_ingest_dead_letter WHERE failed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)")->fetchColumn();
    $dbLatencyMs = (float)$pdo->query("SELECT COALESCE(AVG(latency_ms), 0) FROM telemetry_db_query WHERE created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)")->fetchColumn();
    $alertThroughput = (float)$pdo->query("SELECT COUNT(*)/5.0 FROM telemetry_alert_emissions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();

    $oldestPendingSec = (int)$pdo->query("SELECT COALESCE(MAX(TIMESTAMPDIFF(SECOND, queued_at, NOW())), 0) FROM metrics_ingest_queue WHERE status='pending'")->fetchColumn();
    $staleWorkers = [];
    foreach (['scheduler', 'metrics-worker'] as $worker) {
        $file = '/tmp/ampnm-' . $worker . '-heartbeat';
        $age = file_exists($file) ? (time() - (int)@filemtime($file)) : 999999;
        if ($age > 120) $staleWorkers[$worker] = $age;
    }
    $proxyDisconnectCount = (int)$pdo->query("SELECT COUNT(*) FROM proxies WHERE last_seen IS NULL OR last_seen < DATE_SUB(NOW(), INTERVAL 2 MINUTE)")->fetchColumn();

    return [
        'metrics' => [
            'queue_depth' => $queueDepth,
            'processing_latency_ms' => round($processingLatencyMs, 2),
            'failed_jobs_last_hour' => $failedJobs,
            'db_query_latency_ms' => round($dbLatencyMs, 2),
            'alert_throughput_per_min' => round($alertThroughput, 2),
        ],
        'signals' => [
            'oldest_pending_seconds' => $oldestPendingSec,
            'stale_workers' => $staleWorkers,
            'proxy_disconnect_count' => $proxyDisconnectCount,
        ],
    ];
}

function telemetryEvaluateAlerts(PDO $pdo, ?string $correlationId = null): array
{
    $health = telemetryCollectHealth($pdo);
    $m = $health['metrics'];
    $s = $health['signals'];

    $active = [];

    if ($s['oldest_pending_seconds'] > 120) {
        telemetryEmitAlert($pdo, 'ingestion_lag', 'critical', 'workers', 'Metrics ingestion queue is lagging', ['oldest_pending_seconds' => $s['oldest_pending_seconds']], $correlationId);
        $active[] = 'ingestion_lag';
    } else {
        telemetryResolveAlert($pdo, 'ingestion_lag', $correlationId);
    }

    if (!empty($s['stale_workers'])) {
        telemetryEmitAlert($pdo, 'stale_workers', 'critical', 'scheduler', 'Worker heartbeat is stale', ['stale_workers' => $s['stale_workers']], $correlationId);
        $active[] = 'stale_workers';
    } else {
        telemetryResolveAlert($pdo, 'stale_workers', $correlationId);
    }

    if ($m['db_query_latency_ms'] > 250) {
        telemetryEmitAlert($pdo, 'db_slow_queries', 'warning', 'api', 'DB query latency exceeded threshold', ['db_query_latency_ms' => $m['db_query_latency_ms']], $correlationId);
        $active[] = 'db_slow_queries';
    } else {
        telemetryResolveAlert($pdo, 'db_slow_queries', $correlationId);
    }

    if ($s['proxy_disconnect_count'] > 0) {
        telemetryEmitAlert($pdo, 'proxy_disconnects', 'warning', 'proxy', 'One or more proxies are disconnected', ['proxy_disconnect_count' => $s['proxy_disconnect_count']], $correlationId);
        $active[] = 'proxy_disconnects';
    } else {
        telemetryResolveAlert($pdo, 'proxy_disconnects', $correlationId);
    }

    $alertRows = $pdo->query("SELECT alert_type, severity, component, message, details_json, triggered_at FROM telemetry_alert_events WHERE status='active' ORDER BY triggered_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    return ['health' => $health, 'active_alerts' => $alertRows, 'active_keys' => $active];
}

function telemetrySloStatus(float $value, float $greenMax, float $yellowMax): string
{
    if ($value <= $greenMax) return 'green';
    if ($value <= $yellowMax) return 'yellow';
    return 'red';
}

function telemetryPrometheus(PDO $pdo): string
{
    $evaluated = telemetryEvaluateAlerts($pdo);
    $m = $evaluated['health']['metrics'];
    $s = $evaluated['health']['signals'];

    $lines = [];
    $lines[] = '# HELP ampnm_queue_depth Pending ingest queue depth';
    $lines[] = '# TYPE ampnm_queue_depth gauge';
    $lines[] = 'ampnm_queue_depth{service="workers"} ' . $m['queue_depth'];
    $lines[] = '# HELP ampnm_processing_latency_ms Ingest processing latency in milliseconds';
    $lines[] = '# TYPE ampnm_processing_latency_ms gauge';
    $lines[] = 'ampnm_processing_latency_ms{service="workers"} ' . $m['processing_latency_ms'];
    $lines[] = '# HELP ampnm_failed_jobs_last_hour Failed jobs in the last hour';
    $lines[] = '# TYPE ampnm_failed_jobs_last_hour gauge';
    $lines[] = 'ampnm_failed_jobs_last_hour{service="workers"} ' . $m['failed_jobs_last_hour'];
    $lines[] = '# HELP ampnm_db_query_latency_ms Average database query latency in milliseconds';
    $lines[] = '# TYPE ampnm_db_query_latency_ms gauge';
    $lines[] = 'ampnm_db_query_latency_ms{service="api"} ' . $m['db_query_latency_ms'];
    $lines[] = '# HELP ampnm_alert_throughput_per_min Alert emissions per minute (5m average)';
    $lines[] = '# TYPE ampnm_alert_throughput_per_min gauge';
    $lines[] = 'ampnm_alert_throughput_per_min{service="scheduler"} ' . $m['alert_throughput_per_min'];
    $lines[] = '# HELP ampnm_oldest_pending_seconds Age of oldest pending ingestion message';
    $lines[] = '# TYPE ampnm_oldest_pending_seconds gauge';
    $lines[] = 'ampnm_oldest_pending_seconds{service="workers"} ' . $s['oldest_pending_seconds'];
    $lines[] = '# HELP ampnm_proxy_disconnects Number of disconnected proxies';
    $lines[] = '# TYPE ampnm_proxy_disconnects gauge';
    $lines[] = 'ampnm_proxy_disconnects{service="proxy"} ' . $s['proxy_disconnect_count'];
    $lines[] = '# HELP ampnm_active_alerts Number of active internal alerts';
    $lines[] = '# TYPE ampnm_active_alerts gauge';
    $lines[] = 'ampnm_active_alerts{service="scheduler"} ' . count($evaluated['active_alerts']);

    return implode("\n", $lines) . "\n";
}
