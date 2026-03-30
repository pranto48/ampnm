<?php

/**
 * Security hardening helpers for login protection and incremental schema setup.
 * Uses compatibility checks instead of `ADD COLUMN IF NOT EXISTS` because many
 * MySQL/MariaDB versions in the wild do not support that syntax.
 */

function securityColumnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function securityEnsureSchema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(255) NOT NULL,
            `ip_address` VARCHAR(64) NOT NULL,
            `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user_time` (`username`, `attempted_at`),
            INDEX `idx_ip_time` (`ip_address`, `attempted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Intentionally no MFA schema mutations here.
    // Local-LAN deployments may not want or need 2FA/MFA columns.
}

function getClientIpAddress(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? substr($ip, 0, 64) : '';
}

function isLoginThrottled(PDO $pdo, string $username, int $windowMinutes = 15, int $maxAttempts = 8): bool {
    try {
        securityEnsureSchema($pdo);
    } catch (Throwable $e) {
        error_log('Security schema setup failed in isLoginThrottled: ' . $e->getMessage());
        return false; // fail open to avoid login hard-crash
    }
    $windowMinutes = max(1, (int)$windowMinutes);

    $ip = getClientIpAddress();
    $cutoff = date('Y-m-d H:i:s', time() - ($windowMinutes * 60));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM `login_attempts`
        WHERE (`username` = ? OR `ip_address` = ?)
          AND `attempted_at` >= ?
    ");
    $stmt->execute([$username, $ip, $cutoff]);
    return (int)$stmt->fetchColumn() >= $maxAttempts;
}

function recordFailedLoginAttempt(PDO $pdo, string $username): void {
    try {
        securityEnsureSchema($pdo);
    } catch (Throwable $e) {
        error_log('Security schema setup failed in recordFailedLoginAttempt: ' . $e->getMessage());
        return;
    }
    $ip = getClientIpAddress();
    $stmt = $pdo->prepare("INSERT INTO `login_attempts` (`username`, `ip_address`) VALUES (?, ?)");
    $stmt->execute([$username, $ip]);
}

function clearFailedLoginAttempts(PDO $pdo, string $username): void {
    try {
        securityEnsureSchema($pdo);
    } catch (Throwable $e) {
        error_log('Security schema setup failed in clearFailedLoginAttempts: ' . $e->getMessage());
        return;
    }
    $ip = getClientIpAddress();
    $stmt = $pdo->prepare("DELETE FROM `login_attempts` WHERE `username` = ? OR `ip_address` = ?");
    $stmt->execute([$username, $ip]);
}

/**
 * Backward-compatible helper used by some login.php variants.
 * `$success = true` clears attempts, `$success = false` records a failed attempt.
 */
function recordAuthAttempt(PDO $pdo, string $username, bool $success): void {
    if ($success) {
        clearFailedLoginAttempts($pdo, $username);
        return;
    }
    recordFailedLoginAttempt($pdo, $username);
}
