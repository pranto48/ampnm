<?php

function ensureProxySchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `proxies` (
        `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT(6) UNSIGNED NOT NULL,
        `name` VARCHAR(120) NOT NULL,
        `token` VARCHAR(255) NOT NULL UNIQUE,
        `site` VARCHAR(120) NULL,
        `status` ENUM('online','offline','degraded','unknown') DEFAULT 'unknown',
        `last_seen` TIMESTAMP NULL,
        `capabilities` JSON NULL,
        `version` VARCHAR(60) NULL,
        `last_latency_ms` DECIMAL(10,2) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_proxy_user` (`user_id`),
        INDEX `idx_proxy_site` (`site`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `proxy_checks` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `proxy_id` INT(10) UNSIGNED NOT NULL,
        `device_id` INT(6) UNSIGNED NOT NULL,
        `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `next_due_at` TIMESTAMP NULL,
        `last_dispatched_at` TIMESTAMP NULL,
        `last_result_at` TIMESTAMP NULL,
        UNIQUE KEY `uniq_proxy_device` (`proxy_id`,`device_id`),
        INDEX `idx_proxy_due` (`proxy_id`,`next_due_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `proxy_result_receipts` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `proxy_id` INT(10) UNSIGNED NOT NULL,
        `idempotency_key` VARCHAR(191) NOT NULL,
        `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_proxy_idem` (`proxy_id`,`idempotency_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($dbName) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$dbName, 'devices', 'proxy_id']);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `devices` ADD COLUMN `proxy_id` INT(10) UNSIGNED NULL AFTER `map_id`");
            $pdo->exec("ALTER TABLE `devices` ADD INDEX `idx_devices_proxy` (`proxy_id`)");
        }
    }
}

function generateProxyToken(): string {
    try {
        return bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        return hash('sha256', uniqid((string)mt_rand(), true) . microtime(true));
    }
}

function getBearerToken(): string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $header, $m)) {
        return trim($m[1]);
    }
    return trim((string)($_GET['token'] ?? ''));
}

function validateProxyToken(PDO $pdo, string $token): ?array {
    if ($token === '') return null;
    ensureProxySchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM proxies WHERE token = ? LIMIT 1");
    $stmt->execute([$token]);
    $proxy = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$proxy) return null;

    $touch = $pdo->prepare("UPDATE proxies SET last_seen = NOW(), status = 'online' WHERE id = ?");
    $touch->execute([$proxy['id']]);
    $proxy['status'] = 'online';
    $proxy['last_seen'] = date('Y-m-d H:i:s');
    return $proxy;
}
