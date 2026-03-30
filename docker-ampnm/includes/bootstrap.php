<?php
// This is the new central bootstrap file.
// It handles basic setup like loading functions and checking database integrity.

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security_hardening.php';

// This script should not run on the setup page itself to avoid a redirect loop.
if (basename($_SERVER['PHP_SELF']) !== 'database_setup.php') {
    try {
        $pdo = getDbConnection();
        // A simple query to check if the main 'users' table exists.
        // If this fails, we assume the database has not been initialized.
        $pdo->query("SELECT 1 FROM `users` LIMIT 1");
    } catch (PDOException $e) {
        // Check for the specific "table not found" error.
        if (strpos($e->getMessage(), 'Base table or view not found') !== false) {
            // The database is connected, but tables are missing. Redirect to setup.
            header('Location: database_setup.php');
            exit;
        } else {
            // A different, more serious database error occurred.
            die("A critical database error occurred: " . $e->getMessage());
        }
    }
}

// Start session management after DB check.
// This ensures sessions are available on all pages that include this bootstrap.
if (session_status() === PHP_SESSION_NONE) {
    $sessionDriver = strtolower((string)(getenv('SESSION_DRIVER') ?: 'files'));
    if ($sessionDriver === 'redis' && class_exists('Redis')) {
        $redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
        $redisPort = (int)(getenv('REDIS_PORT') ?: 6379);
        $redisDb = (int)(getenv('REDIS_DB') ?: 0);
        $redisPassword = getenv('REDIS_PASSWORD') ?: '';

        $authPart = $redisPassword !== '' ? '?auth=' . rawurlencode($redisPassword) . '&database=' . $redisDb : '?database=' . $redisDb;
        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', "tcp://{$redisHost}:{$redisPort}{$authPart}");
    }

    session_start();
}

try {
    $pdo = getDbConnection();
    securityEnsureSchema($pdo);
    enforceTlsForSensitiveRoutes($pdo);
} catch (Throwable $e) {
    error_log('Bootstrap security hardening warning: ' . $e->getMessage());
}
