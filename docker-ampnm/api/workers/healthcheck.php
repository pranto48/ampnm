#!/usr/bin/env php
<?php

require_once __DIR__ . '/../../includes/functions.php';

$role = $argv[1] ?? 'worker';

try {
    $pdo = getDbConnection();
    $pdo->query('SELECT 1');
} catch (Throwable $e) {
    fwrite(STDERR, "db unavailable: {$e->getMessage()}\n");
    exit(1);
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
        $redis->ping();
    } catch (Throwable $e) {
        fwrite(STDERR, "redis unavailable: {$e->getMessage()}\n");
        exit(1);
    }
}

$heartbeatFile = '/tmp/ampnm-' . preg_replace('/[^a-z0-9_-]/i', '', $role) . '-heartbeat';
if ($role === 'scheduler') {
    if (!file_exists($heartbeatFile)) {
        fwrite(STDERR, "missing scheduler heartbeat\n");
        exit(1);
    }

    $age = time() - (int)filemtime($heartbeatFile);
    if ($age > 120) {
        fwrite(STDERR, "stale scheduler heartbeat\n");
        exit(1);
    }
}

echo "ok\n";
