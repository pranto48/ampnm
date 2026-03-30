<?php

require_once __DIR__ . '/metrics_ingest_queue.php';
require_once __DIR__ . '/telemetry.php';

class MetricsIngestService
{
    public static function normalizeMetricsPayload(array $data): array
    {
        $hostName = trim((string)($data['host_name'] ?? $data['hostname'] ?? ''));
        $hostIp = trim((string)($data['host_ip'] ?? $data['ip_address'] ?? ''));
        $platform = self::normalizePlatform($data);

        $memoryTotal = $data['memory_total_gb'] ?? $data['memory_total'] ?? null;
        $memoryFree = $data['memory_free_gb'] ?? $data['memory_available_gb'] ?? null;
        $diskTotal = $data['disk_total_gb'] ?? $data['disk_total'] ?? null;
        $diskFree = $data['disk_free_gb'] ?? null;
        $networkInMbps = $data['network_in_mbps'] ?? null;
        $networkOutMbps = $data['network_out_mbps'] ?? null;

        if ($networkInMbps === null && isset($data['network_in']) && is_numeric($data['network_in'])) {
            $networkInMbps = round((((float)$data['network_in']) * 8) / 1000000, 2);
        }
        if ($networkOutMbps === null && isset($data['network_out']) && is_numeric($data['network_out'])) {
            $networkOutMbps = round((((float)$data['network_out']) * 8) / 1000000, 2);
        }

        $diskPercent = $data['disk_percent'] ?? $data['disk_usage'] ?? null;
        if ($diskPercent === null && is_numeric($diskTotal) && is_numeric($diskFree) && (float)$diskTotal > 0) {
            $diskPercent = round((((float)$diskTotal - (float)$diskFree) / (float)$diskTotal) * 100, 2);
        }

        return [
            'host_name' => $hostName !== '' ? $hostName : ($hostIp !== '' ? $hostIp : 'Unknown'),
            'host_ip' => $hostIp !== '' ? $hostIp : null,
            'hostname' => $hostName !== '' ? $hostName : ($hostIp !== '' ? $hostIp : 'Unknown'),
            'ip_address' => $hostIp !== '' ? $hostIp : null,
            'cpu_percent' => $data['cpu_percent'] ?? $data['cpu_usage'] ?? $data['cpu'] ?? null,
            'memory_percent' => $data['memory_percent'] ?? $data['memory_usage'] ?? null,
            'memory_total_gb' => $memoryTotal,
            'memory_free_gb' => $memoryFree,
            'disk_percent' => $diskPercent,
            'disk_total_gb' => $diskTotal,
            'disk_free_gb' => $diskFree,
            'network_in_mbps' => $networkInMbps,
            'network_out_mbps' => $networkOutMbps,
            'gpu_percent' => $data['gpu_percent'] ?? $data['gpu_usage'] ?? null,
            'uptime_seconds' => $data['uptime_seconds'] ?? null,
            'boot_time' => $data['boot_time'] ?? null,
            'os_version' => $data['os_version'] ?? null,
            'platform' => $platform,
            'services' => $data['services'] ?? null,
            'top_processes' => $data['top_processes'] ?? $data['processes'] ?? null,
            'raw_payload' => $data,
            'timestamp' => $data['timestamp'] ?? null,
            'sequence' => $data['sequence'] ?? null,
            'agent_id' => $data['agent_id'] ?? null,
        ];
    }

    public static function buildIdempotencyKey(array $payload, ?int $tokenUserId = null): string
    {
        $agentId = trim((string)($payload['agent_id'] ?? ''));
        if ($agentId === '') {
            $agentId = trim((string)($payload['host_ip'] ?? $payload['ip_address'] ?? $payload['host_name'] ?? 'unknown-agent'));
        }

        $timestamp = trim((string)($payload['timestamp'] ?? $payload['collected_at'] ?? ''));
        if ($timestamp === '') {
            $timestamp = gmdate('c');
        }

        $sequence = trim((string)($payload['sequence'] ?? $payload['seq'] ?? $payload['sample_sequence'] ?? '0'));
        $userPart = $tokenUserId ? (string)$tokenUserId : '0';

        return strtolower(hash('sha256', implode('|', [$userPart, $agentId, $timestamp, $sequence])));
    }

    public static function processMessage(PDO $pdo, array $message): array
    {
        MetricsIngestQueue::ensureQueueTables($pdo);
        ensureTelemetrySchema($pdo);

        $payload = $message['payload'] ?? [];
        $type = (string)($message['message_type'] ?? 'metrics_submit');
        $idempotencyKey = (string)($message['idempotency_key'] ?? '');
        $correlationId = (string)($message['correlation_id'] ?? telemetryCorrelationId());

        if ($idempotencyKey === '') {
            throw new RuntimeException('Missing idempotency key');
        }

        if (!self::beginDedup($pdo, $idempotencyKey, $type)) {
            return ['status' => 'duplicate_skipped', 'idempotency_key' => $idempotencyKey];
        }

        try {
            if ($type === 'pull_device_by_ip') {
                $result = self::processPullDeviceByIp($pdo, $payload);
            } else {
                $result = self::processMetricsSubmit($pdo, $payload);
            }

            self::markDedup($pdo, $idempotencyKey, 'done', null);
            telemetryLog('ingest.message.processed', ['correlation_id' => $correlationId, 'idempotency_key' => $idempotencyKey, 'message_type' => $type]);
            return array_merge(['status' => 'processed', 'idempotency_key' => $idempotencyKey], $result);
        } catch (Throwable $e) {
            self::markDedup($pdo, $idempotencyKey, 'failed', $e->getMessage());
            telemetryLog('ingest.message.failed', ['correlation_id' => $correlationId, 'idempotency_key' => $idempotencyKey, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private static function processMetricsSubmit(PDO $pdo, array $payload): array
    {
        $normalized = self::normalizeMetricsPayload($payload);
        if (empty($normalized['host_ip'])) {
            throw new InvalidArgumentException('host_ip is required');
        }

        $tokenUserId = isset($payload['token_user_id']) ? (int)$payload['token_user_id'] : null;
        $device = self::findDeviceMatch($pdo, (string)($normalized['host_name'] ?? ''), (string)($normalized['host_ip'] ?? ''), $tokenUserId ?: null);
        $autoCreatedDevice = false;

        if (!$device && $tokenUserId) {
            $created = self::createDeviceForHost($pdo, $tokenUserId, (string)($normalized['host_name'] ?? ''), $normalized['host_ip'] ?? null, (string)($normalized['platform'] ?? 'unknown'));
            if ($created) {
                $device = $created;
                $autoCreatedDevice = true;
            }
        }

        $deviceId = $device ? (int)$device['id'] : null;
        if ($deviceId) {
            self::touchDeviceFromMetrics($pdo, $deviceId, (string)($normalized['host_ip'] ?? ''), 'online');
        }

        $metricsId = self::saveHostMetrics($pdo, $normalized, $deviceId);

        try {
            require_once __DIR__ . '/host_alerts.php';
            $alertSystem = new HostAlertSystem($pdo);
            $alertSystem->checkAndAlert($normalized['host_ip'], $normalized['host_name'] ?? $normalized['host_ip'], $normalized);
        } catch (Exception $e) {
            error_log('Host Alert Error: ' . $e->getMessage());
        }

        return [
            'metrics_id' => $metricsId,
            'device_id' => $deviceId,
            'auto_device_created' => $autoCreatedDevice,
        ];
    }

    private static function processPullDeviceByIp(PDO $pdo, array $payload): array
    {
        $requestedIp = trim((string)($payload['host_ip'] ?? ''));
        $requestedHostName = trim((string)($payload['host_name'] ?? ''));
        if ($requestedIp === '' && $requestedHostName === '') {
            throw new InvalidArgumentException('host_ip or host_name is required');
        }

        $tokenUserId = isset($payload['token_user_id']) ? (int)$payload['token_user_id'] : null;
        $device = self::findDeviceMatch($pdo, $requestedHostName, $requestedIp, $tokenUserId ?: null);
        $autoCreated = false;
        if (!$device && $tokenUserId) {
            $created = self::createDeviceForHost($pdo, $tokenUserId, $requestedHostName, $requestedIp ?: null, 'windows');
            if ($created) {
                $device = $created;
                $autoCreated = true;
            }
        }

        if ($device && !empty($device['id'])) {
            self::touchDeviceFromMetrics($pdo, (int)$device['id'], $requestedIp, 'online');
        }

        return ['device_found' => (bool)$device, 'auto_device_created' => $autoCreated];
    }

    private static function beginDedup(PDO $pdo, string $idempotencyKey, string $type): bool
    {
        $stmt = $pdo->prepare("INSERT INTO metrics_ingest_dedup (idempotency_key, message_type, status) VALUES (?, ?, 'processing') ON DUPLICATE KEY UPDATE idempotency_key = idempotency_key");
        $stmt->execute([$idempotencyKey, $type]);
        return $stmt->rowCount() > 0;
    }

    private static function markDedup(PDO $pdo, string $idempotencyKey, string $status, ?string $error): void
    {
        $stmt = $pdo->prepare("UPDATE metrics_ingest_dedup SET status = ?, last_error = ?, processed_at = CASE WHEN ? = 'done' THEN NOW() ELSE processed_at END WHERE idempotency_key = ?");
        $stmt->execute([$status, $error, $status, $idempotencyKey]);
    }

    private static function normalizePlatform(array $payload): string
    {
        $platform = strtolower(trim((string)($payload['platform'] ?? $payload['agent_platform'] ?? '')));
        if ($platform === '') {
            $runtimeCollector = strtolower((string)($payload['agent_runtime']['collector'] ?? ''));
            $osVersion = strtolower((string)($payload['os_version'] ?? ''));
            if (str_contains($runtimeCollector, 'linux') || str_contains($osVersion, 'ubuntu') || str_contains($osVersion, 'debian') || str_contains($osVersion, 'rhel') || str_contains($osVersion, 'rocky') || str_contains($osVersion, 'alma') || str_contains($osVersion, 'fedora')) {
                return 'linux';
            }
            if (str_contains($osVersion, 'windows')) {
                return 'windows';
            }
        }
        return in_array($platform, ['windows', 'linux'], true) ? $platform : 'unknown';
    }

    private static function getTableColumns(PDO $pdo, string $table): array
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }

        return $cache[$table] = $columns;
    }

    private static function hasColumn(array $columns, string $column): bool
    {
        return in_array($column, $columns, true);
    }

    private static function firstAvailableColumn(array $columns, array $preferred): ?string
    {
        foreach ($preferred as $column) {
            if (self::hasColumn($columns, $column)) {
                return $column;
            }
        }
        return null;
    }

    private static function findDeviceMatch(PDO $pdo, string $hostname, string $ip, ?int $userId = null)
    {
        $userClause = $userId ? ' AND user_id = ?' : '';
        $userParams = $userId ? [$userId] : [];

        if (!empty($ip)) {
            $stmt = $pdo->prepare("SELECT id, name, ip, ping_interval FROM devices WHERE ip = ?{$userClause} LIMIT 1");
            $stmt->execute(array_merge([$ip], $userParams));
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($device) return $device;
        }

        if (!empty($hostname)) {
            $stmt = $pdo->prepare("SELECT id, name, ip, ping_interval FROM devices WHERE name = ?{$userClause} LIMIT 1");
            $stmt->execute(array_merge([$hostname], $userParams));
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($device) return $device;
        }

        return false;
    }

    private static function touchDeviceFromMetrics(PDO $pdo, int $deviceId, string $hostIp = '', string $status = 'online'): void
    {
        $columns = self::getTableColumns($pdo, 'devices');
        $updates = [];
        $values = [];
        $now = date('Y-m-d H:i:s');

        if (self::hasColumn($columns, 'status')) {
            $updates[] = '`status` = ?';
            $values[] = $status;
        }
        if (self::hasColumn($columns, 'last_seen')) {
            $updates[] = '`last_seen` = ?';
            $values[] = $now;
        }
        if (self::hasColumn($columns, 'last_check')) {
            $updates[] = '`last_check` = ?';
            $values[] = $now;
        }
        if ($hostIp !== '' && self::hasColumn($columns, 'ip')) {
            $updates[] = '`ip` = COALESCE(NULLIF(`ip`, \'\'), ?)';
            $values[] = $hostIp;
        }

        if (empty($updates)) {
            return;
        }

        $values[] = $deviceId;
        $stmt = $pdo->prepare('UPDATE devices SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $stmt->execute($values);
    }

    private static function createDeviceForHost(PDO $pdo, int $userId, string $hostName, ?string $hostIp, string $platform = 'unknown'): ?array
    {
        $safeName = trim($hostName) !== '' ? trim($hostName) : ($hostIp ?: 'Host ' . date('YmdHis'));
        $deviceType = in_array($platform, ['windows', 'linux'], true) ? 'server' : 'server';

        if (!empty($hostIp)) {
            $stmt = $pdo->prepare("SELECT id, name, ip FROM devices WHERE user_id = ? AND ip = ? LIMIT 1");
            $stmt->execute([$userId, $hostIp]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) return $existing;
        }

        $stmt = $pdo->prepare("SELECT id, name, ip FROM devices WHERE user_id = ? AND name = ? LIMIT 1");
        $stmt->execute([$userId, $safeName]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) return $existing;

        $insert = $pdo->prepare("INSERT INTO devices (user_id, name, ip, monitor_method, type, status, ping_interval, show_live_ping) VALUES (?, ?, ?, 'ping', ?, 'online', 60, 1)");
        $insert->execute([$userId, $safeName, $hostIp, $deviceType]);
        $newId = (int)$pdo->lastInsertId();

        $fetch = $pdo->prepare("SELECT id, name, ip FROM devices WHERE id = ? LIMIT 1");
        $fetch->execute([$newId]);
        return $fetch->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function saveHostMetrics(PDO $pdo, array $data, ?int $deviceId = null): int
    {
        $columns = self::getTableColumns($pdo, 'host_metrics');

        $current = [
            'device_id' => $deviceId,
            'host_name' => $data['host_name'],
            'hostname' => $data['host_name'],
            'host_ip' => $data['host_ip'],
            'ip_address' => $data['host_ip'],
            'cpu_percent' => $data['cpu_percent'],
            'cpu_usage' => $data['cpu_percent'],
            'memory_percent' => $data['memory_percent'],
            'memory_usage' => $data['memory_percent'],
            'memory_total_gb' => $data['memory_total_gb'],
            'memory_total' => $data['memory_total_gb'],
            'memory_free_gb' => $data['memory_free_gb'],
            'disk_percent' => $data['disk_percent'],
            'disk_usage' => $data['disk_percent'],
            'disk_total_gb' => $data['disk_total_gb'],
            'disk_total' => $data['disk_total_gb'],
            'disk_free_gb' => $data['disk_free_gb'],
            'network_in_mbps' => $data['network_in_mbps'],
            'network_out_mbps' => $data['network_out_mbps'],
            'network_in' => $data['network_in_mbps'],
            'network_out' => $data['network_out_mbps'],
            'gpu_percent' => $data['gpu_percent'],
            'gpu_usage' => $data['gpu_percent'],
            'uptime_seconds' => $data['uptime_seconds'],
            'boot_time' => $data['boot_time'],
            'os_version' => $data['os_version'],
            'status' => 'online',
            'created_at' => date('Y-m-d H:i:s'),
            'last_seen' => date('Y-m-d H:i:s'),
        ];

        $identifierColumn = self::firstAvailableColumn($columns, ['host_ip', 'ip_address', 'host_name', 'hostname']);
        $identifierValue = $identifierColumn && array_key_exists($identifierColumn, $current) ? $current[$identifierColumn] : null;
        $existingId = null;

        if ($identifierColumn && !empty($identifierValue)) {
            $stmt = $pdo->prepare("SELECT id FROM host_metrics WHERE `{$identifierColumn}` = ? LIMIT 1");
            $stmt->execute([$identifierValue]);
            $existingId = $stmt->fetchColumn() ?: null;
        }

        $persistColumns = [];
        $persistValues = [];
        foreach ($current as $column => $value) {
            if (self::hasColumn($columns, $column)) {
                $persistColumns[] = $column;
                $persistValues[] = $value;
            }
        }

        if ($existingId) {
            $set = implode(', ', array_map(fn($column) => "`{$column}` = ?", $persistColumns));
            $stmt = $pdo->prepare("UPDATE host_metrics SET {$set} WHERE id = ?");
            $stmt->execute(array_merge($persistValues, [$existingId]));
            $metricsId = (int)$existingId;
        } else {
            $columnList = implode(', ', array_map(fn($column) => "`{$column}`", $persistColumns));
            $placeholders = implode(', ', array_fill(0, count($persistColumns), '?'));
            $stmt = $pdo->prepare("INSERT INTO host_metrics ({$columnList}) VALUES ({$placeholders})");
            $stmt->execute($persistValues);
            $metricsId = (int)$pdo->lastInsertId();
        }

        $historyColumns = self::getTableColumns($pdo, 'host_metrics_history');
        $history = [
            'hostname' => $data['host_name'],
            'host_name' => $data['host_name'],
            'host_ip' => $data['host_ip'],
            'ip_address' => $data['host_ip'],
            'cpu_percent' => $data['cpu_percent'],
            'cpu_usage' => $data['cpu_percent'],
            'memory_percent' => $data['memory_percent'],
            'memory_usage' => $data['memory_percent'],
            'memory_total_gb' => $data['memory_total_gb'],
            'memory_total' => $data['memory_total_gb'],
            'memory_free_gb' => $data['memory_free_gb'],
            'disk_percent' => $data['disk_percent'],
            'disk_usage' => $data['disk_percent'],
            'disk_total_gb' => $data['disk_total_gb'],
            'disk_total' => $data['disk_total_gb'],
            'disk_free_gb' => $data['disk_free_gb'],
            'network_in_mbps' => $data['network_in_mbps'],
            'network_out_mbps' => $data['network_out_mbps'],
            'network_in' => $data['network_in_mbps'],
            'network_out' => $data['network_out_mbps'],
            'gpu_percent' => $data['gpu_percent'],
            'gpu_usage' => $data['gpu_percent'],
            'recorded_at' => date('Y-m-d H:i:s'),
        ];

        $historyPersistColumns = [];
        $historyPersistValues = [];
        foreach ($history as $column => $value) {
            if (self::hasColumn($historyColumns, $column)) {
                $historyPersistColumns[] = $column;
                $historyPersistValues[] = $value;
            }
        }
        if (!empty($historyPersistColumns)) {
            $columnList = implode(', ', array_map(fn($column) => "`{$column}`", $historyPersistColumns));
            $placeholders = implode(', ', array_fill(0, count($historyPersistColumns), '?'));
            $stmt = $pdo->prepare("INSERT INTO host_metrics_history ({$columnList}) VALUES ({$placeholders})");
            $stmt->execute($historyPersistValues);
        }

        return $metricsId;
    }
}
