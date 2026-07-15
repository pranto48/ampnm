<?php
// One-time admin script to insert an AmPOS license into the cPanel MySQL database.
// DELETE THIS FILE after use for security!

require_once __DIR__ . '/config.php';

$adminToken = $_GET['token'] ?? '';
if ($adminToken !== 'ITSupportBD-AdminToken-2026') {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized. Provide ?token=ITSupportBD-AdminToken-2026']));
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'insert';

try {
    $pdo = getLicenseDbConnection();

    if ($action === 'check_table') {
        // Check if licenses table exists and show its columns
        $stmt = $pdo->query("DESCRIBE `licenses`");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['table' => 'licenses', 'columns' => $cols]);
        exit;
    }

    if ($action === 'list') {
        $stmt = $pdo->query("SELECT id, license_key, status, expires_at, customer_id, product_id FROM `licenses` LIMIT 50");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['licenses' => $rows, 'count' => count($rows)]);
        exit;
    }

    // Default: insert the AmPOS license key
    $licenseKey = 'AMP256-B713A3E37B5FE53C-6F38AE70F5CC0DF6-EBB7A8AEFFB261B0-9401F60994D97223';

    // Check if it already exists
    $check = $pdo->prepare("SELECT id FROM `licenses` WHERE license_key = ?");
    $check->execute([$licenseKey]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo json_encode([
            'status' => 'already_exists',
            'message' => 'License key already exists in database.',
            'id' => $existing['id'],
            'key' => $licenseKey
        ]);
        exit;
    }

    // Insert with customer_id = NULL (admin license, no customer)
    $stmt = $pdo->prepare(
        "INSERT INTO `licenses` 
            (customer_id, product_id, license_key, status, issued_at, expires_at, max_devices, current_devices, bound_installation_id) 
         VALUES 
            (NULL, NULL, ?, 'active', NOW(), '2027-12-31 23:59:59', 1, 0, NULL)"
    );
    $stmt->execute([$licenseKey]);
    $newId = $pdo->lastInsertId();

    echo json_encode([
        'status' => 'created',
        'message' => 'License key inserted successfully!',
        'id' => $newId,
        'key' => $licenseKey,
        'expires_at' => '2027-12-31',
        'max_devices' => 1
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
