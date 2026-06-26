<?php
// Core database setup logic - can be included by setup.php or run standalone
// Assumes $pdo is already connected to the database

if (!isset($pdo)) {
    die("Error: Database connection not established");
}

function generateUuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function columnExists($pdo, $db, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$db, $table, $column]);
    return $stmt->fetchColumn() > 0;
}

// Get database name
$db_name = $pdo->query("SELECT DATABASE()")->fetchColumn();

// Step 1: Create users table
$pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'viewer') DEFAULT 'admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Migration: Add role column if it doesn't exist
if (!columnExists($pdo, $db_name, 'users', 'role')) {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `role` ENUM('admin', 'viewer') DEFAULT 'admin' AFTER `password`;");
    $pdo->exec("UPDATE `users` SET `role` = 'admin' WHERE `role` IS NULL;");
}

// Step 2: Create admin user
$admin_user = 'admin';
$admin_password = 'admin123'; // Default for script version

$stmt = $pdo->prepare("SELECT id, password FROM `users` WHERE username = ?");
$stmt->execute([$admin_user]);
$admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin_data) {
    $admin_pass_hash = password_hash($admin_password, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO `users` (username, password, role) VALUES (?, ?, 'admin')")->execute([$admin_user, $admin_pass_hash]);
    $admin_id = $pdo->lastInsertId();
} else {
    $admin_id = $admin_data['id'];
}

// Step 3: Create all other tables
$tables = [
    "CREATE TABLE IF NOT EXISTS `ping_results` (
        `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `host` VARCHAR(100) NOT NULL,
        `packet_loss` INT(3) NOT NULL,
        `avg_time` DECIMAL(10,2) NOT NULL,
        `min_time` DECIMAL(10,2) NOT NULL,
        `max_time` DECIMAL(10,2) NOT NULL,
        `success` BOOLEAN NOT NULL,
        `output` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `maps` (
        `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT(6) UNSIGNED NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `type` VARCHAR(50) NOT NULL,
        `description` TEXT,
        `background_color` VARCHAR(20) NULL,
        `background_image_url` VARCHAR(255) NULL,
        `is_default` BOOLEAN DEFAULT FALSE,
        `public_view_enabled` BOOLEAN DEFAULT FALSE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `devices` (
        `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT(6) UNSIGNED NOT NULL,
        `ip` VARCHAR(15) NULL,
        `check_port` INT(5) NULL,
        `monitor_method` ENUM('ping','port') DEFAULT 'ping',
        `name` VARCHAR(100) NOT NULL,
        `status` ENUM('online', 'offline', 'unknown', 'warning', 'critical') DEFAULT 'unknown',
        `last_seen` TIMESTAMP NULL,
        `type` VARCHAR(50) NOT NULL DEFAULT 'server',
        `subchoice` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `description` TEXT,
        `enabled` BOOLEAN DEFAULT TRUE,
        `x` DECIMAL(10, 4) NULL,
        `y` DECIMAL(10, 4) NULL,
        `map_id` INT(6) UNSIGNED,
        `ping_interval` INT(11) NULL,
        `icon_size` INT(11) DEFAULT 50,
        `name_text_size` INT(11) DEFAULT 14,
        `icon_url` VARCHAR(255) NULL,
        `warning_latency_threshold` INT(11) NULL,
        `warning_packetloss_threshold` INT(11) NULL,
        `critical_latency_threshold` INT(11) NULL,
        `critical_packetloss_threshold` INT(11) NULL,
        `last_avg_time` DECIMAL(10, 2) NULL,
        `last_ttl` INT(11) NULL,
        `show_live_ping` BOOLEAN DEFAULT FALSE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`map_id`) REFERENCES `maps`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `device_edges` (
        `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT(6) UNSIGNED NOT NULL,
        `source_id` INT(6) UNSIGNED NOT NULL,
        `target_id` INT(6) UNSIGNED NOT NULL,
        `map_id` INT(6) UNSIGNED NOT NULL,
        `connection_type` VARCHAR(50) DEFAULT 'cat5',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`source_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`target_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`map_id`) REFERENCES `maps`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `device_status_logs` (
        `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `device_id` INT(6) UNSIGNED NOT NULL,
        `status` ENUM('online', 'offline', 'unknown', 'warning', 'critical') NOT NULL,
        `details` VARCHAR(255) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `smtp_settings` (
        `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT(6) UNSIGNED NOT NULL,
        `host` VARCHAR(255) NOT NULL,
        `port` INT(5) NOT NULL,
        `username` VARCHAR(255) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `encryption` ENUM('none', 'ssl', 'tls') DEFAULT 'tls',
        `from_email` VARCHAR(255) NOT NULL,
        `from_name` VARCHAR(255) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `user_id_unique` (`user_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `device_email_subscriptions` (
        `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT(6) UNSIGNED NOT NULL,
        `device_id` INT(6) UNSIGNED NOT NULL,
        `recipient_email` VARCHAR(255) NOT NULL,
        `notify_on_online` BOOLEAN DEFAULT TRUE,
        `notify_on_offline` BOOLEAN DEFAULT TRUE,
        `notify_on_warning` BOOLEAN DEFAULT TRUE,
        `notify_on_critical` BOOLEAN DEFAULT TRUE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `device_recipient_unique` (`device_id`, `recipient_email`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    "CREATE TABLE IF NOT EXISTS `app_settings` (
        `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(100) NOT NULL UNIQUE,
        `setting_value` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($tables as $sql) {
    $pdo->exec($sql);
}

// Schema migrations
if (!columnExists($pdo, $db_name, 'maps', 'user_id')) {
    $pdo->exec("ALTER TABLE `maps` ADD COLUMN `user_id` INT(6) UNSIGNED;");
    $updateStmt = $pdo->prepare("UPDATE `maps` SET user_id = ?");
    $updateStmt->execute([$admin_id]);
    $pdo->exec("ALTER TABLE `maps` MODIFY COLUMN `user_id` INT(6) UNSIGNED NOT NULL;");
    $pdo->exec("ALTER TABLE `maps` ADD CONSTRAINT `fk_maps_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;");
}

if (!columnExists($pdo, $db_name, 'device_edges', 'user_id')) {
    $pdo->exec("ALTER TABLE `device_edges` ADD COLUMN `user_id` INT(6) UNSIGNED;");
    $updateStmt = $pdo->prepare("UPDATE `device_edges` SET user_id = ?");
    $updateStmt->execute([$admin_id]);
    $pdo->exec("ALTER TABLE `device_edges` MODIFY COLUMN `user_id` INT(6) UNSIGNED NOT NULL;");
    $pdo->exec("ALTER TABLE `device_edges` ADD CONSTRAINT `fk_device_edges_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;");
}

if (!columnExists($pdo, $db_name, 'maps', 'public_view_enabled')) {
    $pdo->exec("ALTER TABLE `maps` ADD COLUMN `public_view_enabled` BOOLEAN DEFAULT FALSE AFTER `is_default`;");
}

// NEW MIGRATION: Add subchoice column for icon variants
if (!columnExists($pdo, $db_name, 'devices', 'subchoice')) {
    $pdo->exec("ALTER TABLE `devices` ADD COLUMN `subchoice` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `type`;");
}

// Initialize installation ID if not set
$stmt = $pdo->prepare("SELECT setting_value FROM `app_settings` WHERE setting_key = 'installation_id'");
$stmt->execute();
if (!$stmt->fetchColumn()) {
    $installation_id = generateUuid();
    $pdo->prepare("INSERT INTO `app_settings` (setting_key, setting_value) VALUES ('installation_id', ?)")->execute([$installation_id]);
}

return true;
