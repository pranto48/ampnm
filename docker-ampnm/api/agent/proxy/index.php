<?php
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/proxy_service.php';

header('Content-Type: application/json');

$pdo = getDbConnection();
ensureProxySchema($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$endpointBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
$suffix = '';
if ($endpointBase !== '' && str_starts_with($requestPath, $endpointBase)) {
    $suffix = trim(substr($requestPath, strlen($endpointBase)), '/');
}
$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    if ($method === 'POST' && $suffix === 'auth-token') {
        require_once __DIR__ . '/../../../includes/auth_check.php';
        if (($_SESSION['user_role'] ?? 'viewer') !== 'admin') {
            http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
        }
        $name = trim((string)($input['name'] ?? 'Proxy'));
        $site = trim((string)($input['site'] ?? ''));
        $token = generateProxyToken();
        $stmt = $pdo->prepare("INSERT INTO proxies (user_id, name, token, site, status, capabilities, version) VALUES (?, ?, ?, ?, 'unknown', NULL, NULL)");
        $stmt->execute([$_SESSION['user_id'], $name, $token, $site !== '' ? $site : null]);
        echo json_encode(['success' => true, 'proxy_id' => (int)$pdo->lastInsertId(), 'token' => $token]);
        exit;
    }

    if ($method === 'POST' && $suffix === 'register') {
        $proxy = validateProxyToken($pdo, getBearerToken());
        if (!$proxy) { http_response_code(401); echo json_encode(['error' => 'Invalid token']); exit; }

        $name = trim((string)($input['name'] ?? $proxy['name'] ?? 'Proxy'));
        $site = trim((string)($input['site'] ?? $proxy['site'] ?? ''));
        $capabilities = $input['capabilities'] ?? [];
        $version = trim((string)($input['version'] ?? ''));

        $stmt = $pdo->prepare("UPDATE proxies SET name=?, site=?, capabilities=?, version=?, status='online', last_seen=NOW() WHERE id=?");
        $stmt->execute([
            $name,
            $site !== '' ? $site : null,
            json_encode($capabilities),
            $version !== '' ? $version : null,
            $proxy['id']
        ]);

        echo json_encode(['success' => true, 'proxy_id' => (int)$proxy['id']]);
        exit;
    }

    if ($method === 'POST' && $suffix === 'pull') {
        $proxy = validateProxyToken($pdo, getBearerToken());
        if (!$proxy) { http_response_code(401); echo json_encode(['error' => 'Invalid token']); exit; }

        $limit = max(1, min(200, (int)($input['limit'] ?? 50)));
        $site = trim((string)($proxy['site'] ?? ''));

        $sql = "
            SELECT d.id, d.name, d.ip, d.check_port, d.monitor_method, d.ping_interval, d.proxy_id,
                   COALESCE(pc.next_due_at, NOW()) AS next_due_at
            FROM devices d
            LEFT JOIN maps m ON m.id = d.map_id
            LEFT JOIN proxy_checks pc ON pc.proxy_id = ? AND pc.device_id = d.id
            WHERE d.enabled = 1 AND d.ip IS NOT NULL AND d.ip != ''
              AND (d.proxy_id = ? " . ($site !== '' ? " OR m.name = ? " : '') . ")
              AND COALESCE(pc.next_due_at, NOW()) <= NOW()
            ORDER BY COALESCE(pc.next_due_at, NOW()) ASC
            LIMIT {$limit}
        ";
        $params = [$proxy['id'], $proxy['id']];
        if ($site !== '') $params[] = $site;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $checks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $upsert = $pdo->prepare("INSERT INTO proxy_checks (proxy_id, device_id, next_due_at, last_dispatched_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW()) ON DUPLICATE KEY UPDATE last_dispatched_at=VALUES(last_dispatched_at), next_due_at=VALUES(next_due_at)");
        foreach ($checks as $row) {
            $interval = max(10, (int)($row['ping_interval'] ?? 60));
            $upsert->execute([$proxy['id'], $row['id'], $interval]);
        }

        $queueLagStmt = $pdo->prepare("SELECT COUNT(*) FROM devices d LEFT JOIN proxy_checks pc ON pc.proxy_id = ? AND pc.device_id = d.id WHERE d.proxy_id = ? AND COALESCE(pc.next_due_at, NOW()) <= NOW()");
        $queueLagStmt->execute([$proxy['id'], $proxy['id']]);

        echo json_encode([
            'success' => true,
            'proxy_id' => (int)$proxy['id'],
            'checks' => $checks,
            'queue_lag' => (int)$queueLagStmt->fetchColumn(),
            'server_time' => gmdate('c'),
        ]);
        exit;
    }

    if ($method === 'POST' && $suffix === 'results-batch') {
        $proxy = validateProxyToken($pdo, getBearerToken());
        if (!$proxy) { http_response_code(401); echo json_encode(['error' => 'Invalid token']); exit; }

        $results = is_array($input['results'] ?? null) ? $input['results'] : [];
        $accepted = 0;
        $duplicates = 0;
        $now = date('Y-m-d H:i:s');

        $receiptStmt = $pdo->prepare("INSERT INTO proxy_result_receipts (proxy_id, idempotency_key) VALUES (?, ?)");
        $updateDevice = $pdo->prepare("UPDATE devices SET status=?, last_seen=?, last_avg_time=?, updated_at=NOW() WHERE id=? AND (proxy_id = ? OR proxy_id IS NULL)");
        $updateCheck = $pdo->prepare("UPDATE proxy_checks SET last_result_at = NOW(), next_due_at = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE proxy_id = ? AND device_id = ?");
        $insertPing = $pdo->prepare("INSERT INTO ping_results (host, packet_loss, avg_time, min_time, max_time, success, output) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($results as $result) {
            $idem = trim((string)($result['idempotency_key'] ?? ''));
            if ($idem === '') $idem = hash('sha256', json_encode([$proxy['id'], $result['device_id'] ?? null, $result['checked_at'] ?? $now, $result['status'] ?? 'unknown']));

            try {
                $receiptStmt->execute([$proxy['id'], $idem]);
            } catch (Throwable $e) {
                $duplicates++;
                continue;
            }

            $deviceId = (int)($result['device_id'] ?? 0);
            if ($deviceId <= 0) continue;
            $status = in_array(($result['status'] ?? ''), ['online','offline','warning','critical','unknown'], true) ? $result['status'] : 'unknown';
            $latency = isset($result['latency_ms']) ? (float)$result['latency_ms'] : null;
            $packetLoss = isset($result['packet_loss']) ? (int)$result['packet_loss'] : ($status === 'offline' ? 100 : 0);
            $success = $status !== 'offline' ? 1 : 0;
            $checkedAt = !empty($result['checked_at']) ? date('Y-m-d H:i:s', strtotime($result['checked_at'])) : $now;

            $updateDevice->execute([$status, $checkedAt, $latency, $deviceId, $proxy['id']]);
            $updateCheck->execute([max(10, (int)($result['next_interval'] ?? 60)), $proxy['id'], $deviceId]);
            $insertPing->execute([$result['ip'] ?? 'proxy-check', $packetLoss, $latency ?? 0, $latency ?? 0, $latency ?? 0, $success, substr((string)($result['output'] ?? 'proxy_result'), 0, 65000)]);
            $accepted++;
        }

        $lagMs = isset($input['latency_ms']) ? (float)$input['latency_ms'] : null;
        if ($lagMs !== null) {
            $stmt = $pdo->prepare("UPDATE proxies SET last_latency_ms = ?, last_seen = NOW(), status = 'online' WHERE id = ?");
            $stmt->execute([$lagMs, $proxy['id']]);
        }

        echo json_encode([
            'success' => true,
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'offline_buffering' => [
                'mode' => 'store-and-forward',
                'idempotency' => 'required',
                'acknowledged_at' => gmdate('c')
            ]
        ]);
        exit;
    }

    if ($method === 'GET' && $suffix === 'health') {
        $st = $pdo->query('SELECT 1');
        $st->fetch();
        echo json_encode(['status' => 'ok', 'service' => 'proxy', 'timestamp' => date('c')]);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
} catch (Throwable $e) {
    error_log('proxy endpoint error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
}
