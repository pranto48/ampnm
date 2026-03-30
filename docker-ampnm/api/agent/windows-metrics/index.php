<?php
// REST-style endpoint for Windows monitoring agent
// - POST   /api/agent/windows-metrics
// - GET    /api/agent/windows-metrics/health
// - GET    /api/agent/windows-metrics/recent?limit=50
// - GET    /api/agent/windows-metrics/<HOSTNAME>/latest
// - GET    /api/agent/windows-metrics/device-by-ip?host_ip=1.2.3.4&host_name=HOST

require_once __DIR__ . '/../../../includes/functions.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

// Base path of this endpoint (works under /docker-ampnm/ as well)
$endpointBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
$suffix = '';
if ($endpointBase !== '' && str_starts_with($requestPath, $endpointBase)) {
    $suffix = trim(substr($requestPath, strlen($endpointBase)), '/');
}

// Route
try {
    if ($method === 'POST' && ($suffix === '' || $suffix === 'ingest')) {
        // Reuse the existing handler logic
        $_GET['action'] = 'submit_metrics';
        require __DIR__ . '/../../handlers/metrics_handler.php';
        exit;
    }

    if ($method === 'GET' && $suffix === 'device-by-ip') {
        $_GET['action'] = 'pull_device_by_ip';
        require __DIR__ . '/../../handlers/metrics_handler.php';
        exit;
    }

    $pdo = getDbConnection();

    if ($method === 'GET' && $suffix === 'health') {
        // Lightweight check: confirm DB connectivity.
        $stmt = $pdo->query('SELECT 1');
        $stmt->fetch();

        echo json_encode([
            'status' => 'ok',
            'timestamp' => date('c'),
        ]);
        exit;
    }

    if ($method === 'GET' && $suffix === 'recent') {
        $limit = (int)($_GET['limit'] ?? 50);
        if ($limit < 1) $limit = 1;
        if ($limit > 200) $limit = 200;

        $stmt = $pdo->prepare('SELECT * FROM host_metrics ORDER BY created_at DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode(['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // <HOSTNAME>/latest
    if ($method === 'GET' && preg_match('#^([^/]+)/latest$#', $suffix, $m)) {
        $hostName = urldecode($m[1]);
        $columns = [];
        $colStmt = $pdo->query("SHOW COLUMNS FROM `host_metrics`");
        while ($col = $colStmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $col['Field'];
        }
        $hostNameColumn = in_array('host_name', $columns, true) ? 'host_name' : 'hostname';
        $sortColumn = in_array('last_seen', $columns, true) ? 'last_seen' : 'created_at';
        $stmt = $pdo->prepare('
            SELECT *
            FROM host_metrics
            WHERE `' . $hostNameColumn . '` = ?
            ORDER BY `' . $sortColumn . '` DESC, id DESC
            LIMIT 1
        ');
        $stmt->execute([$hostName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row ?: ['error' => 'No metrics found']);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
} catch (Exception $e) {
    error_log('windows-metrics endpoint error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
}
