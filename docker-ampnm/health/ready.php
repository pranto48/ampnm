<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$checks = [
    'database' => false,
    'redis' => false,
];
$errors = [];

try {
    $pdo = getDbConnection();
    $pdo->query('SELECT 1');
    $checks['database'] = true;
} catch (Throwable $e) {
    $errors['database'] = $e->getMessage();
}

if (class_exists('Redis')) {
    try {
        $redis = new Redis();
        $redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int)(getenv('REDIS_PORT') ?: 6379), (float)(getenv('REDIS_TIMEOUT') ?: 1.5));
        if (($password = getenv('REDIS_PASSWORD')) !== false && $password !== '') {
            $redis->auth($password);
        }
        if (($db = getenv('REDIS_DB')) !== false && $db !== '') {
            $redis->select((int)$db);
        }
        $checks['redis'] = $redis->ping() !== false;
    } catch (Throwable $e) {
        $errors['redis'] = $e->getMessage();
    }
} else {
    $errors['redis'] = 'phpredis extension is not loaded';
}

$ready = !in_array(false, $checks, true);
http_response_code($ready ? 200 : 503);

echo json_encode([
    'status' => $ready ? 'ready' : 'not_ready',
    'checks' => $checks,
    'errors' => $errors,
    'time' => gmdate('c'),
]);
