<?php
/**
 * Metrics Handler - Receives metrics from Windows monitoring agents
 * Supports both authenticated (token) and IP-based device matching
 */

// This handler can be called directly for agent metrics (no session required)
$action = $_GET['action'] ?? '';
$pdo = getDbConnection();

require_once __DIR__ . '/../../includes/metrics_ingest_service.php';

/**
 * Validate agent token
 */
function validateAgentToken($pdo, $token) {
    if (empty($token)) return false;
    ensureAgentTokenTable($pdo);

    $stmt = $pdo->prepare("SELECT id, name, user_id FROM agent_tokens WHERE token = ? AND enabled = TRUE");
    $stmt->execute([$token]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $updateStmt = $pdo->prepare("UPDATE agent_tokens SET last_used_at = NOW() WHERE id = ?");
        $updateStmt->execute([$result['id']]);
        return $result;
    }
    return false;
}

/**
 * Ensure agent_tokens table exists for legacy installs that skipped setup migrations.
 */
function ensureAgentTokenTable($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `agent_tokens` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `token` VARCHAR(255) NOT NULL UNIQUE,
            `name` VARCHAR(100) NOT NULL,
            `enabled` BOOLEAN DEFAULT TRUE,
            `last_used_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_token` (`token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Generate secure token with compatibility fallback for restricted PHP environments.
 */
function generateAgentToken() {
    try {
        return bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $strong = false;
            $bytes = openssl_random_pseudo_bytes(32, $strong);
            if ($bytes !== false) {
                return bin2hex($bytes);
            }
        }
        return hash('sha256', uniqid((string)mt_rand(), true) . microtime(true));
    }
}

/**
 * Find device by IP address or hostname
 */
function findDeviceMatch($pdo, $hostname, $ip, ?int $userId = null) {
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

function touchDeviceFromMetrics(PDO $pdo, int $deviceId, string $hostIp = '', string $status = 'online'): void {
    $columns = getTableColumns($pdo, 'devices');
    $updates = [];
    $values = [];
    $now = date('Y-m-d H:i:s');

    if (hasColumn($columns, 'status')) {
        $updates[] = '`status` = ?';
        $values[] = $status;
    }
    if (hasColumn($columns, 'last_seen')) {
        $updates[] = '`last_seen` = ?';
        $values[] = $now;
    }
    if (hasColumn($columns, 'last_check')) {
        $updates[] = '`last_check` = ?';
        $values[] = $now;
    }
    if ($hostIp !== '' && hasColumn($columns, 'ip')) {
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

function createDeviceForHost(PDO $pdo, int $userId, string $hostName, ?string $hostIp, string $platform = 'unknown'): ?array {
    $safeName = trim($hostName) !== '' ? trim($hostName) : ($hostIp ?: 'Host ' . date('YmdHis'));
    $deviceType = in_array($platform, ['windows', 'linux'], true) ? 'server' : 'server';

    // Re-check to avoid duplicates by IP or name for this user
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

    $insert = $pdo->prepare("
        INSERT INTO devices (user_id, name, ip, monitor_method, type, status, ping_interval, show_live_ping)
        VALUES (?, ?, ?, 'ping', ?, 'online', 60, 1)
    ");
    $insert->execute([$userId, $safeName, $hostIp, $deviceType]);
    $newId = (int)$pdo->lastInsertId();

    $fetch = $pdo->prepare("SELECT id, name, ip FROM devices WHERE id = ? LIMIT 1");
    $fetch->execute([$newId]);
    return $fetch->fetch(PDO::FETCH_ASSOC) ?: null;
}

function normalizePlatform($payload) {
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

function getTableColumns(PDO $pdo, string $table): array {
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

function hasColumn(array $columns, string $column): bool {
    return in_array($column, $columns, true);
}

function firstAvailableColumn(array $columns, array $preferred): ?string {
    foreach ($preferred as $column) {
        if (hasColumn($columns, $column)) {
            return $column;
        }
    }
    return null;
}

function columnExpr(array $columns, array $preferred, ?string $fallback = 'NULL'): string {
    foreach ($preferred as $column) {
        if (hasColumn($columns, $column)) {
            return "hm.`{$column}`";
        }
    }
    return $fallback ?? 'NULL';
}

function ensureHostOverrideSchema(PDO $pdo): void {
    $columns = getTableColumns($pdo, 'host_alert_overrides');
    if (!in_array('host_name', $columns, true)) {
        $pdo->exec("ALTER TABLE `host_alert_overrides` ADD COLUMN `host_name` VARCHAR(255) NULL AFTER `host_ip`");
        if (in_array('hostname', $columns, true)) {
            $pdo->exec("UPDATE `host_alert_overrides` SET `host_name` = `hostname` WHERE (`host_name` IS NULL OR `host_name` = '') AND `hostname` IS NOT NULL");
        }
    }
}

function normalizeMetricsPayload(array $data): array {
    $hostName = trim((string)($data['host_name'] ?? $data['hostname'] ?? ''));
    $hostIp = trim((string)($data['host_ip'] ?? $data['ip_address'] ?? ''));
    $platform = normalizePlatform($data);

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
    ];
}

/**
 * Save current host snapshot and matching history row
 */
function saveHostMetrics($pdo, $data, $deviceId = null) {
    $data = normalizeMetricsPayload($data);
    $columns = getTableColumns($pdo, 'host_metrics');

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

    $identifierColumn = firstAvailableColumn($columns, ['host_ip', 'ip_address', 'host_name', 'hostname']);
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
        if (hasColumn($columns, $column)) {
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

    $historyColumns = getTableColumns($pdo, 'host_metrics_history');
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
        if (hasColumn($historyColumns, $column)) {
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

    $processColumns = getTableColumns($pdo, 'host_processes');
    if (!empty($data['top_processes']) && hasColumn($processColumns, 'hostname')) {
        $deleteStmt = $pdo->prepare("DELETE FROM host_processes WHERE hostname = ?");
        $deleteStmt->execute([$data['host_name']]);

        $insertableColumns = array_values(array_filter([
            hasColumn($processColumns, 'hostname') ? 'hostname' : null,
            hasColumn($processColumns, 'process_name') ? 'process_name' : null,
            hasColumn($processColumns, 'process_type') ? 'process_type' : null,
            hasColumn($processColumns, 'pid') ? 'pid' : null,
            hasColumn($processColumns, 'cpu_percent') ? 'cpu_percent' : null,
            hasColumn($processColumns, 'memory_mb') ? 'memory_mb' : null,
            hasColumn($processColumns, 'status') ? 'status' : null,
        ]));

        if (!empty($insertableColumns)) {
            $columnList = implode(', ', array_map(fn($column) => "`{$column}`", $insertableColumns));
            $placeholders = implode(', ', array_fill(0, count($insertableColumns), '?'));
            $insertStmt = $pdo->prepare("INSERT INTO host_processes ({$columnList}) VALUES ({$placeholders})");

            foreach (array_slice($data['top_processes'], 0, 50) as $process) {
                $row = [
                    'hostname' => $data['host_name'],
                    'process_name' => $process['name'] ?? $process['process_name'] ?? 'unknown',
                    'process_type' => 'process',
                    'pid' => $process['pid'] ?? null,
                    'cpu_percent' => $process['cpu_percent'] ?? null,
                    'memory_mb' => $process['memory_mb'] ?? null,
                    'status' => $process['state'] ?? $process['status'] ?? null,
                ];
                $values = [];
                foreach ($insertableColumns as $column) {
                    $values[] = $row[$column] ?? null;
                }
                $insertStmt->execute($values);
            }
        }
    }

    return $metricsId;
}

function saveHostProcesses($pdo, $hostname, $processes, $services) {
    if (empty($hostname)) return;

    $deleteStmt = $pdo->prepare("DELETE FROM host_processes WHERE hostname = ?");
    $deleteStmt->execute([$hostname]);

    $insertStmt = $pdo->prepare("INSERT INTO host_processes (hostname, process_name, process_type, pid, cpu_percent, memory_mb, status) VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($processes as $process) {
        if (!is_array($process)) continue;
        $insertStmt->execute([
            $hostname,
            $process['name'] ?? $process['process_name'] ?? 'process',
            'process',
            isset($process['pid']) && is_numeric($process['pid']) ? (int)$process['pid'] : null,
            isset($process['cpu_percent']) && is_numeric($process['cpu_percent']) ? round((float)$process['cpu_percent'], 2) : null,
            isset($process['memory_mb']) && is_numeric($process['memory_mb']) ? round((float)$process['memory_mb'], 2) : null,
            $process['status'] ?? null,
        ]);
    }

    foreach ($services as $service) {
        if (!is_array($service)) continue;
        $insertStmt->execute([
            $hostname,
            $service['name'] ?? $service['service_name'] ?? 'service',
            'service',
            null,
            null,
            null,
            $service['status'] ?? $service['state'] ?? $service['sub_state'] ?? null,
        ]);
    }
}

/**
 * Clean up old metrics (keep last 7 days)
 */
function cleanupOldMetrics($pdo, $daysToKeep = 7) {
    $stmt = $pdo->prepare("DELETE FROM host_metrics WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$daysToKeep]);
    return $stmt->rowCount();
}

// Parse input early for all actions that need it
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Handle different actions
switch ($action) {
    case 'submit_metrics':
        $token = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? '';
        $tokenInfo = validateAgentToken($pdo, $token);
        if (!$tokenInfo) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or missing agent token']);
            exit;
        }

        $normalizedInput = MetricsIngestService::normalizeMetricsPayload($input);
        if (empty($normalizedInput['host_ip'])) {
            http_response_code(400);
            echo json_encode(['error' => 'host_ip is required']);
            exit;
        }

        $tokenUserId = !empty($tokenInfo['user_id']) ? (int)$tokenInfo['user_id'] : null;
        $idempotencyKey = MetricsIngestService::buildIdempotencyKey($input, $tokenUserId);

        $message = [
            'message_type' => 'metrics_submit',
            'idempotency_key' => $idempotencyKey,
            'payload' => array_merge($normalizedInput, [
                'token_user_id' => $tokenUserId,
                'token_id' => $tokenInfo['id'] ?? null,
            ]),
            'enqueued_at' => gmdate('c'),
        ];

        $enqueue = MetricsIngestQueue::enqueue($pdo, $message);
        $processedInline = false;
        if ((getenv('METRICS_INGEST_INLINE_FALLBACK') ?: '1') === '1') {
            try {
                MetricsIngestService::processMessage($pdo, $message);
                $processedInline = true;
            } catch (Throwable $e) {
                error_log('Metrics inline fallback failed: ' . $e->getMessage());
            }
        }

        echo json_encode([
            'success' => true,
            'status' => 'accepted',
            'queued' => true,
            'processed_inline' => $processedInline,
            'queue_transport' => $enqueue['transport'],
            'queue_message_id' => $enqueue['message_id'],
            'idempotency_key' => $idempotencyKey,
            'hostname' => $normalizedInput['host_name'] ?? null,
            'ip_address' => $normalizedInput['host_ip'] ?? null,
        ]);
        break;

    case 'pull_device_by_ip':
        $token = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? '';
        $tokenInfo = validateAgentToken($pdo, $token);
        if (!$tokenInfo) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or missing agent token']);
            exit;
        }

        $requestedIp = trim((string)($_GET['host_ip'] ?? $input['host_ip'] ?? ''));
        $requestedHostName = trim((string)($_GET['host_name'] ?? $input['host_name'] ?? ''));
        if ($requestedIp === '' && $requestedHostName === '') {
            http_response_code(400);
            echo json_encode(['error' => 'host_ip or host_name is required']);
            exit;
        }

        $tokenUserId = !empty($tokenInfo['user_id']) ? (int)$tokenInfo['user_id'] : null;
        $idempotencyKey = MetricsIngestService::buildIdempotencyKey([
            'agent_id' => $requestedHostName !== '' ? $requestedHostName : $requestedIp,
            'timestamp' => gmdate('c'),
            'sequence' => 'pull-device-by-ip',
        ], $tokenUserId);

        $enqueue = MetricsIngestQueue::enqueue($pdo, [
            'message_type' => 'pull_device_by_ip',
            'idempotency_key' => $idempotencyKey,
            'payload' => [
                'host_ip' => $requestedIp ?: null,
                'host_name' => $requestedHostName ?: null,
                'token_user_id' => $tokenUserId,
                'token_id' => $tokenInfo['id'] ?? null,
            ],
            'enqueued_at' => gmdate('c'),
        ]);
        $processedInline = false;
        if ((getenv('METRICS_INGEST_INLINE_FALLBACK') ?: '1') === '1') {
            try {
                MetricsIngestService::processMessage($pdo, [
                    'message_type' => 'pull_device_by_ip',
                    'idempotency_key' => $idempotencyKey,
                    'payload' => [
                        'host_ip' => $requestedIp ?: null,
                        'host_name' => $requestedHostName ?: null,
                        'token_user_id' => $tokenUserId,
                        'token_id' => $tokenInfo['id'] ?? null,
                    ],
                    'enqueued_at' => gmdate('c'),
                ]);
                $processedInline = true;
            } catch (Throwable $e) {
                error_log('Pull-device inline fallback failed: ' . $e->getMessage());
            }
        }

        echo json_encode([
            'success' => true,
            'status' => 'accepted',
            'queued' => true,
            'processed_inline' => $processedInline,
            'queue_transport' => $enqueue['transport'],
            'queue_message_id' => $enqueue['message_id'],
            'idempotency_key' => $idempotencyKey,
            'host_ip' => $requestedIp ?: null,
            'host_name' => $requestedHostName ?: null,
        ]);
        break;
        
    case 'get_latest_metrics':
        // Get latest metrics for a specific device
        $deviceId = $_GET['device_id'] ?? null;
        $hostIp = $_GET['host_ip'] ?? null;
        $hostColumns = getTableColumns($pdo, 'host_metrics');
        $hostIpExpr = columnExpr($hostColumns, ['host_ip', 'ip_address']);
        $hostNameExpr = columnExpr($hostColumns, ['host_name', 'hostname']);
        $cpuExpr = columnExpr($hostColumns, ['cpu_percent', 'cpu_usage']);
        $memoryExpr = columnExpr($hostColumns, ['memory_percent', 'memory_usage']);
        $diskExpr = columnExpr($hostColumns, ['disk_percent', 'disk_usage']);
        $netInExpr = columnExpr($hostColumns, ['network_in_mbps', 'network_in']);
        $netOutExpr = columnExpr($hostColumns, ['network_out_mbps', 'network_out']);
        $gpuExpr = columnExpr($hostColumns, ['gpu_percent', 'gpu_usage']);
        $createdExpr = columnExpr($hostColumns, ['last_seen', 'created_at']);
        
        if (!$deviceId && !$hostIp) {
            http_response_code(400);
            echo json_encode(['error' => 'device_id or host_ip required']);
            exit;
        }
        
        if ($deviceId && hasColumn($hostColumns, 'device_id')) {
            $stmt = $pdo->prepare("
                SELECT hm.*, {$hostNameExpr} AS host_name, {$hostIpExpr} AS host_ip,
                       {$cpuExpr} AS cpu_percent, {$memoryExpr} AS memory_percent, {$diskExpr} AS disk_percent,
                       {$netInExpr} AS network_in_mbps, {$netOutExpr} AS network_out_mbps, {$gpuExpr} AS gpu_percent,
                       {$createdExpr} AS created_at
                FROM host_metrics hm
                WHERE hm.device_id = ?
                ORDER BY hm.id DESC
                LIMIT 1
            ");
            $stmt->execute([$deviceId]);
        } else {
            $hostIpColumn = firstAvailableColumn($hostColumns, ['host_ip', 'ip_address']);
            $stmt = $pdo->prepare("
                SELECT hm.*, {$hostNameExpr} AS host_name, {$hostIpExpr} AS host_ip,
                       {$cpuExpr} AS cpu_percent, {$memoryExpr} AS memory_percent, {$diskExpr} AS disk_percent,
                       {$netInExpr} AS network_in_mbps, {$netOutExpr} AS network_out_mbps, {$gpuExpr} AS gpu_percent,
                       {$createdExpr} AS created_at
                FROM host_metrics hm
                WHERE hm.`{$hostIpColumn}` = ?
                ORDER BY hm.id DESC
                LIMIT 1
            ");
            $stmt->execute([$hostIp]);
        }
        
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($metrics ?: ['error' => 'No metrics found']);
        break;
        
    case 'get_metrics_history':
        // Get historical metrics for charts
        $deviceId = $_GET['device_id'] ?? null;
        $hostIp = $_GET['host_ip'] ?? null;
        $hours = min((int)($_GET['hours'] ?? 24), 168); // Max 7 days
        
        if (!$deviceId && !$hostIp) {
            http_response_code(400);
            echo json_encode(['error' => 'device_id or host_ip required']);
            exit;
        }
        
        $historyColumns = getTableColumns($pdo, 'host_metrics_history');
        $hostNameExpr = hasColumn($historyColumns, 'host_name') ? '`host_name`' : '`hostname`';
        $hostIpExpr = hasColumn($historyColumns, 'host_ip') ? '`host_ip`' : '`ip_address`';
        $recordedExpr = hasColumn($historyColumns, 'recorded_at') ? '`recorded_at`' : '`created_at`';
        $cpuExpr = hasColumn($historyColumns, 'cpu_percent') ? '`cpu_percent`' : '`cpu_usage`';
        $memoryExpr = hasColumn($historyColumns, 'memory_percent') ? '`memory_percent`' : '`memory_usage`';
        $diskExpr = hasColumn($historyColumns, 'disk_percent') ? '`disk_percent`' : '`disk_usage`';
        $netInExpr = hasColumn($historyColumns, 'network_in_mbps') ? '`network_in_mbps`' : '`network_in`';
        $netOutExpr = hasColumn($historyColumns, 'network_out_mbps') ? '`network_out_mbps`' : '`network_out`';
        $gpuExpr = hasColumn($historyColumns, 'gpu_percent') ? '`gpu_percent`' : '`gpu_usage`';

        if ($deviceId) {
            $currentColumns = getTableColumns($pdo, 'host_metrics');
            $hostNameColumn = firstAvailableColumn($currentColumns, ['host_name', 'hostname']);
            if (hasColumn($currentColumns, 'device_id') && $hostNameColumn) {
                $stmt = $pdo->prepare("SELECT `{$hostNameColumn}` FROM host_metrics WHERE device_id = ? LIMIT 1");
                $stmt->execute([$deviceId]);
                $hostIdentifier = $stmt->fetchColumn();
                $stmt = $pdo->prepare("
                    SELECT id, {$cpuExpr} AS cpu_percent, {$memoryExpr} AS memory_percent, {$diskExpr} AS disk_percent,
                           {$netInExpr} AS network_in_mbps, {$netOutExpr} AS network_out_mbps, {$gpuExpr} AS gpu_percent, {$recordedExpr} AS created_at
                    FROM host_metrics_history
                    WHERE {$hostNameExpr} = ? AND {$recordedExpr} >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                    ORDER BY {$recordedExpr} ASC
                ");
                $stmt->execute([$hostIdentifier, $hours]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'device_id lookup not supported by current schema; use host_ip']);
                exit;
            }
        } else {
            $stmt = $pdo->prepare("
                SELECT id, {$cpuExpr} AS cpu_percent, {$memoryExpr} AS memory_percent, {$diskExpr} AS disk_percent,
                       {$netInExpr} AS network_in_mbps, {$netOutExpr} AS network_out_mbps, {$gpuExpr} AS gpu_percent, {$recordedExpr} AS created_at
                FROM host_metrics_history
                WHERE {$hostIpExpr} = ? AND {$recordedExpr} >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                ORDER BY {$recordedExpr} ASC
            ");
            $stmt->execute([$hostIp, $hours]);
        }
        
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($history);
        break;
        
    case 'get_all_hosts':
        // Get list of all monitored hosts with latest metrics, first registration time, and any per-host overrides
        $hostColumns = getTableColumns($pdo, 'host_metrics');
        $hostIdentityExpr = firstAvailableColumn($hostColumns, ['host_ip', 'ip_address', 'host_name', 'hostname']);
        $hostIpExpr = columnExpr($hostColumns, ['host_ip', 'ip_address']);
        $hostNameExpr = columnExpr($hostColumns, ['host_name', 'hostname']);
        $cpuExpr = columnExpr($hostColumns, ['cpu_percent', 'cpu_usage']);
        $memoryExpr = columnExpr($hostColumns, ['memory_percent', 'memory_usage']);
        $memoryTotalExpr = columnExpr($hostColumns, ['memory_total_gb', 'memory_total']);
        $memoryFreeExpr = columnExpr($hostColumns, ['memory_free_gb']);
        $diskExpr = columnExpr($hostColumns, ['disk_percent', 'disk_usage']);
        $diskTotalExpr = columnExpr($hostColumns, ['disk_total_gb', 'disk_total']);
        $diskFreeExpr = columnExpr($hostColumns, ['disk_free_gb']);
        $netInExpr = columnExpr($hostColumns, ['network_in_mbps', 'network_in']);
        $netOutExpr = columnExpr($hostColumns, ['network_out_mbps', 'network_out']);
        $gpuExpr = columnExpr($hostColumns, ['gpu_percent', 'gpu_usage']);
        $createdExpr = columnExpr($hostColumns, ['last_seen', 'created_at']);
        $firstSeenExpr = columnExpr($hostColumns, ['first_seen', 'created_at']);
        $deviceJoin = hasColumn($hostColumns, 'device_id')
            ? 'LEFT JOIN devices d ON hm.device_id = d.id'
            : "LEFT JOIN devices d ON d.ip = COALESCE({$hostIpExpr}, {$hostNameExpr})";

        $stmt = $pdo->query("
            SELECT hm.id,
                   {$hostNameExpr} AS host_name,
                   {$hostIpExpr} AS host_ip,
                   {$cpuExpr} AS cpu_percent,
                   {$memoryExpr} AS memory_percent,
                   {$memoryTotalExpr} AS memory_total_gb,
                   {$memoryFreeExpr} AS memory_free_gb,
                   {$diskExpr} AS disk_percent,
                   {$diskTotalExpr} AS disk_total_gb,
                   {$diskFreeExpr} AS disk_free_gb,
                   {$netInExpr} AS network_in_mbps,
                   {$netOutExpr} AS network_out_mbps,
                   {$gpuExpr} AS gpu_percent,
                   {$createdExpr} AS created_at,
                   {$firstSeenExpr} AS first_seen_at,
                   d.name as device_name,
                   d.id as linked_device_id,
                   CASE WHEN hao.id IS NOT NULL AND hao.enabled = 1 THEN 1 ELSE 0 END as has_override,
                   hao.status_delay_seconds
            FROM host_metrics hm
            {$deviceJoin}
            LEFT JOIN host_alert_overrides hao ON hao.host_ip = {$hostIpExpr}
            INNER JOIN (
                SELECT MAX(id) AS max_id
                FROM host_metrics
                GROUP BY `{$hostIdentityExpr}`
            ) latest ON latest.max_id = hm.id
            ORDER BY host_name
        ");
        $hosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($hosts);
        break;
        
    case 'get_agent_tokens':
        // List all agent tokens (admin only)
        ensureAgentTokenTable($pdo);
        $stmt = $pdo->query("SELECT id, name, token, enabled, last_used_at, created_at FROM agent_tokens ORDER BY created_at DESC");
        $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($tokens);
        break;
        
    case 'create_agent_token':
        // Create a new agent token
        ensureAgentTokenTable($pdo);
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only admin can create agent tokens']);
            exit;
        }
        $name = $input['name'] ?? 'Windows Agent ' . date('Y-m-d H:i');
        $token = generateAgentToken();
        
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) { http_response_code(401); echo json_encode(['error' => 'Not authenticated']); exit; }
        $stmt = $pdo->prepare("INSERT INTO agent_tokens (user_id, token, name) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $token, $name]);
        
        echo json_encode([
            'success' => true,
            'id' => $pdo->lastInsertId(),
            'token' => $token,
            'name' => $name
        ]);
        break;

    case 'create_device_from_host':
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only admin can add host devices']);
            exit;
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }
        $hostIp = trim((string)($input['host_ip'] ?? ''));
        $hostName = trim((string)($input['host_name'] ?? ''));
        $platform = trim((string)($input['platform'] ?? 'unknown'));
        if ($hostIp === '' && $hostName === '') {
            http_response_code(400);
            echo json_encode(['error' => 'host_ip or host_name is required']);
            exit;
        }
        $device = createDeviceForHost($pdo, (int)$userId, $hostName, $hostIp ?: null, $platform);
        echo json_encode(['success' => true, 'device' => $device]);
        break;

    case 'register_host_ip':
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only admin can register hosts']);
            exit;
        }
        $hostIp = trim((string)($input['host_ip'] ?? ''));
        $hostName = trim((string)($input['host_name'] ?? ''));
        $autoCreateDevice = !isset($input['create_device']) || !empty($input['create_device']);
        if ($hostIp === '' || !filter_var($hostIp, FILTER_VALIDATE_IP)) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid host_ip is required']);
            exit;
        }
        $userId = $_SESSION['user_id'] ?? null;
        $payload = normalizeMetricsPayload([
            'host_ip' => $hostIp,
            'host_name' => $hostName !== '' ? $hostName : $hostIp,
            'cpu_percent' => null,
            'memory_percent' => null,
            'disk_percent' => null,
            'network_in_mbps' => null,
            'network_out_mbps' => null,
            'gpu_percent' => null,
            'platform' => 'unknown'
        ]);
        $deviceId = null;
        if ($autoCreateDevice && $userId) {
            $device = createDeviceForHost($pdo, (int)$userId, $payload['host_name'], $payload['host_ip'], 'unknown');
            $deviceId = $device['id'] ?? null;
        }
        $metricsId = saveHostMetrics($pdo, $payload, $deviceId);
        echo json_encode(['success' => true, 'metrics_id' => $metricsId, 'host_ip' => $hostIp, 'host_name' => $payload['host_name']]);
        break;
        
    case 'delete_agent_token':
        ensureAgentTokenTable($pdo);
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only admin can delete agent tokens']);
            exit;
        }
        $tokenId = $input['id'] ?? null;
        if (!$tokenId) {
            http_response_code(400);
            echo json_encode(['error' => 'Token ID required']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM agent_tokens WHERE id = ?");
        $stmt->execute([$tokenId]);
        
        echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
        break;
        
    case 'toggle_agent_token':
        ensureAgentTokenTable($pdo);
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Only admin can manage agent tokens']);
            exit;
        }
        $tokenId = $input['id'] ?? null;
        $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : true;
        
        if (!$tokenId) {
            http_response_code(400);
            echo json_encode(['error' => 'Token ID required']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE agent_tokens SET enabled = ? WHERE id = ?");
        $stmt->execute([$enabled, $tokenId]);
        
        echo json_encode(['success' => true]);
        break;
        
    case 'get_alert_settings':
        $userId = $_SESSION['user_id'] ?? null;
        $stmt = $pdo->prepare("SELECT * FROM host_alert_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($settings ?: [
            'cpu_warning_threshold' => 80,
            'cpu_critical_threshold' => 95,
            'memory_warning_threshold' => 80,
            'memory_critical_threshold' => 95,
            'disk_warning_threshold' => 80,
            'disk_critical_threshold' => 95,
            'enabled' => true,
            'cooldown_minutes' => 30
        ]);
        break;
        
    case 'save_alert_settings':
        $userId = $_SESSION['user_id'] ?? null;
        $stmt = $pdo->prepare("SELECT id FROM host_alert_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->fetch()) {
            $sql = "UPDATE host_alert_settings SET cpu_warning_threshold=?, cpu_critical_threshold=?, memory_warning_threshold=?, memory_critical_threshold=?, disk_warning_threshold=?, disk_critical_threshold=?, enabled=?, cooldown_minutes=? WHERE user_id=?";
            $pdo->prepare($sql)->execute([
                $input['cpu_warning_threshold'] ?? 80, $input['cpu_critical_threshold'] ?? 95,
                $input['memory_warning_threshold'] ?? 80, $input['memory_critical_threshold'] ?? 95,
                $input['disk_warning_threshold'] ?? 80, $input['disk_critical_threshold'] ?? 95,
                $input['enabled'] ?? true, $input['cooldown_minutes'] ?? 30, $userId
            ]);
        } else {
            $sql = "INSERT INTO host_alert_settings (user_id, cpu_warning_threshold, cpu_critical_threshold, memory_warning_threshold, memory_critical_threshold, disk_warning_threshold, disk_critical_threshold, enabled, cooldown_minutes) VALUES (?,?,?,?,?,?,?,?,?)";
            $pdo->prepare($sql)->execute([
                $userId, $input['cpu_warning_threshold'] ?? 80, $input['cpu_critical_threshold'] ?? 95,
                $input['memory_warning_threshold'] ?? 80, $input['memory_critical_threshold'] ?? 95,
                $input['disk_warning_threshold'] ?? 80, $input['disk_critical_threshold'] ?? 95,
                $input['enabled'] ?? true, $input['cooldown_minutes'] ?? 30
            ]);
        }
        echo json_encode(['success' => true]);
        break;
    
    case 'get_host_override':
        ensureHostOverrideSchema($pdo);
        $hostIp = $_GET['host_ip'] ?? null;
        if (!$hostIp) {
            echo json_encode(['error' => 'host_ip required']);
            break;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM host_alert_overrides WHERE host_ip = ?");
        $stmt->execute([$hostIp]);
        $override = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($override ?: []);
        break;
        
    case 'save_host_override':
        ensureHostOverrideSchema($pdo);
        $hostIp = $input['host_ip'] ?? null;
        if (!$hostIp) {
            echo json_encode(['error' => 'host_ip required']);
            break;
        }
        
        // Check if override exists
        $stmt = $pdo->prepare("SELECT id FROM host_alert_overrides WHERE host_ip = ?");
        $stmt->execute([$hostIp]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $sql = "UPDATE host_alert_overrides SET 
                    host_name = ?, enabled = ?,
                    cpu_warning = ?, cpu_critical = ?,
                    memory_warning = ?, memory_critical = ?,
                    disk_warning = ?, disk_critical = ?,
                    gpu_warning = ?, gpu_critical = ?,
                    status_delay_seconds = ?,
                    updated_at = NOW()
                    WHERE host_ip = ?";
            $pdo->prepare($sql)->execute([
                $input['host_name'] ?? $hostIp,
                !empty($input['enabled']) ? 1 : 0,
                $input['cpu_warning'] ?? 80, $input['cpu_critical'] ?? 95,
                $input['memory_warning'] ?? 80, $input['memory_critical'] ?? 95,
                $input['disk_warning'] ?? 85, $input['disk_critical'] ?? 95,
                $input['gpu_warning'] ?? 80, $input['gpu_critical'] ?? 95,
                isset($input['status_delay_seconds']) ? (int)$input['status_delay_seconds'] : null,
                $hostIp
            ]);
        } else {
            $sql = "INSERT INTO host_alert_overrides 
                    (host_ip, host_name, enabled, cpu_warning, cpu_critical, memory_warning, memory_critical, disk_warning, disk_critical, gpu_warning, gpu_critical, status_delay_seconds)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([
                $hostIp,
                $input['host_name'] ?? $hostIp,
                !empty($input['enabled']) ? 1 : 0,
                $input['cpu_warning'] ?? 80, $input['cpu_critical'] ?? 95,
                $input['memory_warning'] ?? 80, $input['memory_critical'] ?? 95,
                $input['disk_warning'] ?? 85, $input['disk_critical'] ?? 95,
                $input['gpu_warning'] ?? 80, $input['gpu_critical'] ?? 95,
                isset($input['status_delay_seconds']) ? (int)$input['status_delay_seconds'] : null
            ]);
        }
        echo json_encode(['success' => true]);
        break;
        
    case 'delete_host_override':
        ensureHostOverrideSchema($pdo);
        $hostIp = $input['host_ip'] ?? null;
        if (!$hostIp) {
            echo json_encode(['error' => 'host_ip required']);
            break;
        }
        
        $stmt = $pdo->prepare("DELETE FROM host_alert_overrides WHERE host_ip = ?");
        $stmt->execute([$hostIp]);
        echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
        break;
        
    case 'get_all_host_overrides':
        ensureHostOverrideSchema($pdo);
        $stmt = $pdo->query("SELECT * FROM host_alert_overrides ORDER BY host_ip");
        $overrides = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($overrides);
        break;

    case 'export_host_overrides':
        ensureHostOverrideSchema($pdo);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="host_alert_overrides.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'host_ip', 'host_name', 'enabled',
            'cpu_warning', 'cpu_critical',
            'memory_warning', 'memory_critical',
            'disk_warning', 'disk_critical',
            'gpu_warning', 'gpu_critical',
            'status_delay_seconds'
        ]);

        $stmt = $pdo->query("SELECT host_ip, host_name, enabled, cpu_warning, cpu_critical, memory_warning, memory_critical, disk_warning, disk_critical, gpu_warning, gpu_critical, status_delay_seconds FROM host_alert_overrides ORDER BY host_ip");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;

    case 'import_host_overrides':
        ensureHostOverrideSchema($pdo);
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => 'CSV file upload failed']);
            break;
        }

        $filePath = $_FILES['file']['tmp_name'];
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            echo json_encode(['error' => 'Unable to read uploaded CSV']);
            break;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            echo json_encode(['error' => 'Empty CSV file']);
            break;
        }

        $map = [];
        foreach ($header as $index => $name) {
            $normalized = strtolower(trim($name));
            $map[$normalized] = $index;
        }

        $requiredColumns = ['host_ip'];
        foreach ($requiredColumns as $col) {
            if (!isset($map[$col])) {
                fclose($handle);
                echo json_encode(['error' => "Missing required column: {$col}"]);
                break 2;
            }
        }

        $imported = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $hostIp = trim($row[$map['host_ip']] ?? '');
            if ($hostIp === '') {
                continue;
            }

            $hostName = $map['host_name'] ?? null;
            $enabledCol = $map['enabled'] ?? null;

            $values = [
                'host_ip' => $hostIp,
                'host_name' => $hostName !== null ? trim($row[$hostName] ?? '') : $hostIp,
                'enabled' => $enabledCol !== null ? (int)in_array(strtolower(trim($row[$enabledCol] ?? '1')), ['1', 'true', 'yes', 'y']) : 1,
                'cpu_warning' => (int)($row[$map['cpu_warning']] ?? 80),
                'cpu_critical' => (int)($row[$map['cpu_critical']] ?? 95),
                'memory_warning' => (int)($row[$map['memory_warning']] ?? 80),
                'memory_critical' => (int)($row[$map['memory_critical']] ?? 95),
                'disk_warning' => (int)($row[$map['disk_warning']] ?? 85),
                'disk_critical' => (int)($row[$map['disk_critical']] ?? 95),
                'gpu_warning' => (int)($row[$map['gpu_warning']] ?? 80),
                'gpu_critical' => (int)($row[$map['gpu_critical']] ?? 95),
                'status_delay_seconds' => isset($map['status_delay_seconds']) && $row[$map['status_delay_seconds']] !== ''
                    ? (int)$row[$map['status_delay_seconds']]
                    : null,
            ];

            // Upsert similar to save_host_override
            $stmt = $pdo->prepare("SELECT id FROM host_alert_overrides WHERE host_ip = ?");
            $stmt->execute([$values['host_ip']]);
            $existing = $stmt->fetch();

            if ($existing) {
                $sql = "UPDATE host_alert_overrides SET 
                        host_name = ?, enabled = ?,
                        cpu_warning = ?, cpu_critical = ?,
                        memory_warning = ?, memory_critical = ?,
                        disk_warning = ?, disk_critical = ?,
                        gpu_warning = ?, gpu_critical = ?,
                        status_delay_seconds = ?,
                        updated_at = NOW()
                        WHERE host_ip = ?";
                $pdo->prepare($sql)->execute([
                    $values['host_name'] ?: $values['host_ip'],
                    $values['enabled'],
                    $values['cpu_warning'], $values['cpu_critical'],
                    $values['memory_warning'], $values['memory_critical'],
                    $values['disk_warning'], $values['disk_critical'],
                    $values['gpu_warning'], $values['gpu_critical'],
                    $values['status_delay_seconds'],
                    $values['host_ip'],
                ]);
            } else {
                $sql = "INSERT INTO host_alert_overrides 
                        (host_ip, host_name, enabled, cpu_warning, cpu_critical, memory_warning, memory_critical, disk_warning, disk_critical, gpu_warning, gpu_critical, status_delay_seconds)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([
                    $values['host_ip'],
                    $values['host_name'] ?: $values['host_ip'],
                    $values['enabled'],
                    $values['cpu_warning'], $values['cpu_critical'],
                    $values['memory_warning'], $values['memory_critical'],
                    $values['disk_warning'], $values['disk_critical'],
                    $values['gpu_warning'], $values['gpu_critical'],
                    $values['status_delay_seconds'],
                ]);
            }

            $imported++;
        }

        fclose($handle);
        echo json_encode(['success' => true, 'imported' => $imported]);
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Invalid metrics action']);
}
