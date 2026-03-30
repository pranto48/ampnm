<?php
require_once __DIR__ . '/../../includes/proxy_service.php';

$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'viewer';
ensureProxySchema($pdo);

switch ($action) {
    case 'get_proxies':
        $stmt = $pdo->prepare("SELECT id, name, site, status, last_seen, capabilities, version, last_latency_ms FROM proxies WHERE user_id = ? ORDER BY name ASC");
        $stmt->execute([$current_user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['proxies' => $rows]);
        break;

    case 'create_proxy_token':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $name = trim((string)($input['name'] ?? 'Proxy'));
        $site = trim((string)($input['site'] ?? ''));
        $token = generateProxyToken();
        $stmt = $pdo->prepare("INSERT INTO proxies (user_id, name, token, site, status) VALUES (?, ?, ?, ?, 'unknown')");
        $stmt->execute([$current_user_id, $name, $token, $site !== '' ? $site : null]);
        echo json_encode(['success' => true, 'proxy_id' => (int)$pdo->lastInsertId(), 'token' => $token]);
        break;

    case 'assign_device_proxy':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $deviceId = (int)($input['device_id'] ?? 0);
        $proxyId = isset($input['proxy_id']) && $input['proxy_id'] !== '' ? (int)$input['proxy_id'] : null;
        if ($deviceId <= 0) { http_response_code(400); echo json_encode(['error' => 'device_id is required']); exit; }

        if ($proxyId !== null) {
            $chk = $pdo->prepare("SELECT id FROM proxies WHERE id = ? AND user_id = ?");
            $chk->execute([$proxyId, $current_user_id]);
            if (!$chk->fetchColumn()) { http_response_code(404); echo json_encode(['error' => 'Proxy not found']); exit; }
        }

        $stmt = $pdo->prepare("UPDATE devices SET proxy_id = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$proxyId, $deviceId, $current_user_id]);
        echo json_encode(['success' => true]);
        break;

    case 'assign_map_proxy':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $mapId = (int)($input['map_id'] ?? 0);
        $proxyId = isset($input['proxy_id']) && $input['proxy_id'] !== '' ? (int)$input['proxy_id'] : null;
        if ($mapId <= 0) { http_response_code(400); echo json_encode(['error' => 'map_id is required']); exit; }
        $stmt = $pdo->prepare("UPDATE devices SET proxy_id = ? WHERE map_id = ? AND user_id = ?");
        $stmt->execute([$proxyId, $mapId, $current_user_id]);
        echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
        break;

    case 'get_proxy_health':
        $stmt = $pdo->prepare("SELECT p.id, p.name, p.site, p.status, p.version, p.last_seen, p.last_latency_ms,
            SUM(CASE WHEN pc.next_due_at IS NOT NULL AND pc.next_due_at <= NOW() THEN 1 ELSE 0 END) AS queue_lag,
            SUM(CASE WHEN p.last_seen IS NULL OR p.last_seen < DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END) AS stale
            FROM proxies p
            LEFT JOIN proxy_checks pc ON pc.proxy_id = p.id
            WHERE p.user_id = ?
            GROUP BY p.id
            ORDER BY p.name ASC");
        $stmt->execute([$current_user_id]);
        echo json_encode(['proxies' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;
}
