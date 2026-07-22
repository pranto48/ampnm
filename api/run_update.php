<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (($_SESSION['user_role'] ?? 'viewer') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: Only admin can run updates.']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!isValidCsrfToken(is_string($csrfToken) ? $csrfToken : '')) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Security check failed. Please refresh and try again.']);
    exit;
}

$repoPath = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$scriptPath = realpath($repoPath . '/scripts/update.sh') ?: ($repoPath . '/scripts/update.sh');
$logPath = $repoPath . '/storage/update_manual.log';

$command = 'cd ' . escapeshellarg($repoPath)
    . ' && bash ' . escapeshellarg($scriptPath)
    . ' >> ' . escapeshellarg($logPath)
    . ' 2>&1';

exec($command, $output, $exitCode);

if ($exitCode === 0) {
    echo json_encode([
        'success' => true,
        'message' => 'Update completed successfully.',
    ]);
    exit;
}

http_response_code(500);
echo json_encode([
    'success' => false,
    'error' => 'Update failed. Please check the update log for details.',
    'log_path' => $logPath,
]);
