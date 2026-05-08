<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/update_state.php';

header('Content-Type: application/json');

if (($_SESSION['user_role'] ?? 'viewer') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: Only admin can view update status.']);
    exit;
}

$state = readUpdateStateFile();
$behindCount = isset($state['behind_count']) ? (int)$state['behind_count'] : 0;
$updateAvailable = !empty($state['update_available']) || $behindCount > 0;

$response = [
    'success' => true,
    'status' => $updateAvailable ? 'Update available' : 'Up to date',
    'update_available' => $updateAvailable,
    'behind_count' => $behindCount,
    'last_checked' => isset($state['checked_at']) ? (string)$state['checked_at'] : null,
    'state_path' => getUpdateStatePath(),
];

echo json_encode($response);
