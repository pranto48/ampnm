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

    // Add optional MFA-related columns safely for existing installs.
    if (!securityColumnExists($pdo, 'users', 'mfa_secret')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `mfa_secret` VARCHAR(128) NULL");
    }
    if (!securityColumnExists($pdo, 'users', 'mfa_enabled')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `mfa_enabled` TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!securityColumnExists($pdo, 'users', 'mfa_backup_codes')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `mfa_backup_codes` TEXT NULL");
    }
}

function getClientIpAddress(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? substr($ip, 0, 64) : '';
}

function isLoginThrottled(PDO $pdo, string $username, int $windowMinutes = 15, int $maxAttempts = 8): bool {
    securityEnsureSchema($pdo);

    $ip = getClientIpAddress();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM `login_attempts`
        WHERE (`username` = ? OR `ip_address` = ?)
          AND `attempted_at` >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $stmt->execute([$username, $ip, $windowMinutes]);
    return (int)$stmt->fetchColumn() >= $maxAttempts;
}

function recordFailedLoginAttempt(PDO $pdo, string $username): void {
    securityEnsureSchema($pdo);
    $ip = getClientIpAddress();
    $stmt = $pdo->prepare("INSERT INTO `login_attempts` (`username`, `ip_address`) VALUES (?, ?)");
    $stmt->execute([$username, $ip]);
}

function clearFailedLoginAttempts(PDO $pdo, string $username): void {
    securityEnsureSchema($pdo);
    $ip = getClientIpAddress();
    $stmt = $pdo->prepare("DELETE FROM `login_attempts` WHERE `username` = ? OR `ip_address` = ?");
    $stmt->execute([$username, $ip]);
}

