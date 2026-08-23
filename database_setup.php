<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
// Database configuration using environment variables for Docker compatibility
$servername = getenv('DB_HOST') ?: '127.0.0.1'; // Use environment variable if present
$username = 'root'; // Setup script needs root privileges to create DB and tables
$password = getenv('MYSQL_ROOT_PASSWORD') ?: ''; // Get root password from Docker env
$dbname = getenv('DB_NAME') ?: 'network_monitor';

function message($text, $is_error = false) {
    $safeText = htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    $typeClass = $is_error ? 'error' : 'success';
    echo "<div class='setup-log {$typeClass}' data-setup-log='1'><span class='dot'></span><span>{$safeText}</span></div>";
}

// Function to generate a UUID (Universally Unique Identifier)
function generateUuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord(ord($data[8]) & 0x3f | 0x80)); // set bits 6-7 to 10
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup</title>
    <style>
        :root {
            --bg: #020617;
            --panel: #0f172a;
            --panel-border: #334155;
            --text: #cbd5e1;
            --muted: #94a3b8;
            --accent: #22d3ee;
            --accent-2: #6366f1;
            --ok: #22c55e;
            --err: #ef4444;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at 20% 10%, rgba(34, 211, 238, 0.15), transparent 30%),
                radial-gradient(circle at 80% 0%, rgba(99, 102, 241, 0.14), transparent 28%),
                var(--bg);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
            padding: 24px;
        }
        .setup-shell { max-width: 920px; margin: 0 auto; }
        .setup-card {
            background: linear-gradient(180deg, rgba(15,23,42,0.95), rgba(15,23,42,0.88));
            border: 1px solid var(--panel-border);
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 24px 70px rgba(0,0,0,0.45);
        }
        .setup-title { margin: 0 0 8px; color: #f8fafc; font-size: clamp(24px, 3vw, 34px); }
        .setup-subtitle { margin: 0 0 16px; color: var(--muted); }
        .progress-wrap { margin-bottom: 16px; }
        .progress-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; color: var(--muted); font-size: 13px; }
        .progress-rail {
            position: relative;
            overflow: hidden;
            width: 100%;
            height: 14px;
            border-radius: 999px;
            border: 1px solid #334155;
            background: rgba(15, 23, 42, 0.75);
        }
        .progress-fill {
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 8%;
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            box-shadow: 0 0 16px rgba(34, 211, 238, 0.45);
            border-radius: inherit;
            transition: width 420ms cubic-bezier(.22,.61,.36,1);
        }
        .progress-glow {
            position: absolute; inset: 0;
            background: linear-gradient(120deg, transparent 35%, rgba(255,255,255,0.22) 50%, transparent 65%);
            transform: translateX(-120%);
            animation: pulseSweep 2.5s linear infinite;
            pointer-events: none;
        }
        .log-box {
            margin-top: 14px;
            border: 1px solid #334155;
            border-radius: 12px;
            background: rgba(2,6,23,0.55);
            padding: 12px;
            max-height: 50vh;
            overflow: auto;
        }
        .setup-log {
            display: flex;
            gap: 8px;
            align-items: center;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            margin: 5px 0;
            color: #d1fae5;
            opacity: 0;
            transform: translateY(4px);
            animation: reveal .32s ease forwards;
        }
        .setup-log.error { color: #fecaca; }
        .setup-log .dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--ok);
            box-shadow: 0 0 10px rgba(34,197,94,.55);
            flex: 0 0 auto;
        }
        .setup-log.error .dot { background: var(--err); box-shadow: 0 0 10px rgba(239,68,68,.6); }
        .loader { border: 3px solid #334155; border-top: 3px solid var(--accent); border-radius: 50%; width: 18px; height: 18px; animation: spin 1s linear infinite; display: inline-block; margin-right: 8px; vertical-align: middle; }
        .actions { margin-top: 14px; }
        .actions a { color: var(--accent); text-decoration: none; font-size: 1rem; }
        .badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(15,23,42,.8);
            border: 1px solid #334155;
            color: #67e8f9;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            margin-bottom: 10px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes pulseSweep { to { transform: translateX(120%); } }
        @keyframes reveal { to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
<div class="setup-shell">
    <div class="setup-card">
        <div class="badge"><span class="loader"></span><span>Running setup workflow</span></div>
        <h1 class="setup-title">Docker AMPNM Installation Progress</h1>
        <p class="setup-subtitle">Preparing database, tables, migrations, defaults, and indexes. Please wait…</p>
        <div class="progress-wrap">
            <div class="progress-meta">
                <span id="setupProgressLabel">Starting setup…</span>
                <span id="setupProgressPct">0%</span>
            </div>
            <div class="progress-rail">
                <div id="setupProgressFill" class="progress-fill"></div>
                <div class="progress-glow"></div>
            </div>
        </div>
        <div id="setupLogBox" class="log-box">
<?php
try {
    // Connect to MySQL server (without selecting a database)
    $pdo = new PDO("mysql:host=$servername", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    message("Database '$dbname' checked/created successfully.");

    // Reconnect, this time selecting the new database
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Step 1: Ensure users table exists first
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('admin', 'viewer') DEFAULT 'admin', /* NEW: Add role column */
        `user_group` VARCHAR(50) NOT NULL DEFAULT 'default_group',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    message("Table 'users' checked/created successfully.");

    // Migration: Add role column if it doesn't exist
    function columnExists($pdo, $db, $table, $column) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$db, $table, $column]);
        return $stmt->fetchColumn() > 0;
    }

    if (!columnExists($pdo, $dbname, 'users', 'role')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `role` ENUM('admin', 'viewer') DEFAULT 'admin' AFTER `password`;");
        message("Migrated 'users' table: added 'role' column.");
        // Set existing users to 'admin' role
        $pdo->exec("UPDATE `users` SET `role` = 'admin' WHERE `role` IS NULL;");
        message("Migrated existing users to 'admin' role.");
    }

    if (!columnExists($pdo, $dbname, 'users', 'user_group')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `user_group` VARCHAR(50) NOT NULL DEFAULT 'default_group' AFTER `role`;");
        message("Migrated 'users' table: added 'user_group' column.");
    }


    // Step 2: Ensure admin user exists and set password from environment variable
    $admin_user = 'admin';
    $admin_password = getenv('ADMIN_PASSWORD') ?: 'password';
    $is_default_password = ($admin_password === 'password');

    $stmt = $pdo->prepare("SELECT id, password FROM `users` WHERE username = ?");
    $stmt->execute([$admin_user]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_data) {
        $admin_pass_hash = password_hash($admin_password, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO `users` (username, password, role) VALUES (?, ?, 'admin')")->execute([$admin_user, $admin_pass_hash]);
        $admin_id = $pdo->lastInsertId();
        message("Created default user 'admin'.");
        if ($is_default_password) {
            message("WARNING: Admin password is set to the default 'password'. Please change the ADMIN_PASSWORD in docker-compose.yml for security.", true);
        } else {
            message("Admin password set securely from environment variable.");
        }
    } else {
        $admin_id = $admin_data['id'];
        // Update password if it's changed in the env var and doesn't match the current one
        if (!password_verify($admin_password, $admin_data['password'])) {
            $new_hash = password_hash($admin_password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE `users` SET password = ? WHERE id = ?");
            $updateStmt->execute([$new_hash, $admin_id]);
            message("Updated admin password from environment variable.");
        }
    }

    // Step 3: Create the rest of the tables
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
            `offline_delay_seconds` INT(6) NOT NULL DEFAULT 5,
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
            `name_text_color` VARCHAR(20) DEFAULT '#ffffff',
            `name_text_bold` TINYINT(1) DEFAULT 0,
            `name_text_italic` TINYINT(1) DEFAULT 0,
            `icon_url` VARCHAR(255) NULL,
            `router_api_username` VARCHAR(100) NULL,
            `router_api_password` TEXT NULL,
            `router_api_port` INT(5) NULL,
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
            `connection_type` VARCHAR(50) DEFAULT 'cat6',
            `thickness` INT DEFAULT 2,
            `color` VARCHAR(50) DEFAULT NULL,
            `line_style` VARCHAR(20) DEFAULT 'solid',
            `arrows` VARCHAR(20) DEFAULT 'none',
            `label` VARCHAR(100) DEFAULT NULL,
            `animated` TINYINT(1) DEFAULT 1,
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

        // TABLE FOR LOG BACKUP SCHEDULES
        "CREATE TABLE IF NOT EXISTS `log_backup_schedules` (
            `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `target_type` ENUM('ftp', 'smb', 'email') NOT NULL,
            `target_config` TEXT NULL,
            `period_scope` ENUM('day', 'month', 'year') NOT NULL DEFAULT 'day',
            `schedule_type` ENUM('daily', 'weekly', 'monthly') NOT NULL DEFAULT 'daily',
            `schedule_time` TIME NOT NULL DEFAULT '00:15:00',
            `day_of_week` TINYINT UNSIGNED NULL,
            `day_of_month` TINYINT UNSIGNED NULL,
            `enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `last_run_at` TIMESTAMP NULL,
            `next_run_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLE FOR LOG BACKUP RUN HISTORY
        "CREATE TABLE IF NOT EXISTS `log_backup_runs` (
            `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `schedule_id` INT(10) UNSIGNED NULL,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `status` ENUM('success', 'failed') NOT NULL,
            `target_type` ENUM('ftp', 'smb', 'email') NOT NULL,
            `period_scope` ENUM('day', 'month', 'year') NOT NULL,
            `file_name` VARCHAR(255) NULL,
            `file_size_bytes` BIGINT UNSIGNED NULL,
            `error_message` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`schedule_id`) REFERENCES `log_backup_schedules`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `network_graphs` (
            `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `category` VARCHAR(100) NULL,
            `base_url` VARCHAR(500) NOT NULL,
            `param_name` VARCHAR(50) NOT NULL DEFAULT 'range',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX `idx_network_graphs_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // New table for SMTP settings
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
            `bind_ip` VARCHAR(45) NULL,
            `reply_to_email` VARCHAR(255) NULL,
            `subject_prefix` VARCHAR(120) DEFAULT '[AMPNM]',
            `connection_timeout_seconds` INT(5) UNSIGNED DEFAULT 20,
            `max_emails_per_hour` INT(6) UNSIGNED DEFAULT 240,
            `allow_invalid_certs` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `user_id_unique` (`user_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // New table for device email subscriptions
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
        
        // New table for SMS settings
        "CREATE TABLE IF NOT EXISTS `sms_settings` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `username` VARCHAR(255) NOT NULL,
            `api_key` VARCHAR(255) NOT NULL,
            `sender_id` VARCHAR(255) NULL,
            `enabled` TINYINT(1) DEFAULT 1,
            `cooldown_minutes` INT(11) DEFAULT 30,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `user_id_unique` (`user_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // New table for device SMS subscriptions
        "CREATE TABLE IF NOT EXISTS `device_sms_subscriptions` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `device_id` INT(6) UNSIGNED NOT NULL,
            `recipient_phone` VARCHAR(30) NOT NULL,
            `notify_on_online` BOOLEAN DEFAULT TRUE,
            `notify_on_offline` BOOLEAN DEFAULT TRUE,
            `notify_on_warning` BOOLEAN DEFAULT TRUE,
            `notify_on_critical` BOOLEAN DEFAULT TRUE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `device_phone_unique` (`device_id`, `recipient_phone`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // New table for Telegram settings
        "CREATE TABLE IF NOT EXISTS `telegram_settings` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `bot_token` VARCHAR(255) NOT NULL,
            `enabled` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `user_id_unique` (`user_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // New table for device Telegram subscriptions
        "CREATE TABLE IF NOT EXISTS `device_telegram_subscriptions` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `device_id` INT(6) UNSIGNED NOT NULL,
            `chat_id` VARCHAR(50) NOT NULL,
            `notify_on_online` BOOLEAN DEFAULT TRUE,
            `notify_on_offline` BOOLEAN DEFAULT TRUE,
            `notify_on_warning` BOOLEAN DEFAULT TRUE,
            `notify_on_critical` BOOLEAN DEFAULT TRUE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `device_chat_unique` (`device_id`, `chat_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // New table for WhatsApp settings
        "CREATE TABLE IF NOT EXISTS `whatsapp_settings` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `provider` VARCHAR(50) NOT NULL DEFAULT 'twilio',
            `api_url` VARCHAR(255) NULL,
            `token` VARCHAR(255) NOT NULL,
            `phone_number` VARCHAR(50) NOT NULL,
            `enabled` TINYINT(1) DEFAULT 1,
            `cooldown_minutes` INT(11) DEFAULT 30,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `user_id_unique` (`user_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // New table for device WhatsApp subscriptions
        "CREATE TABLE IF NOT EXISTS `device_whatsapp_subscriptions` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `device_id` INT(6) UNSIGNED NOT NULL,
            `recipient_phone` VARCHAR(30) NOT NULL,
            `notify_on_online` BOOLEAN DEFAULT TRUE,
            `notify_on_offline` BOOLEAN DEFAULT TRUE,
            `notify_on_warning` BOOLEAN DEFAULT TRUE,
            `notify_on_critical` BOOLEAN DEFAULT TRUE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `device_phone_whatsapp_unique` (`device_id`, `recipient_phone`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        // NEW TABLE FOR APPLICATION SETTINGS (LICENSE KEY, INSTALLATION ID)
        "CREATE TABLE IF NOT EXISTS `app_settings` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(100) NOT NULL UNIQUE,
            `setting_value` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `menu_items` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `parent_id` INT(6) UNSIGNED NULL,
            `title` VARCHAR(100) NOT NULL,
            `url` VARCHAR(255) NOT NULL,
            `icon` VARCHAR(100) NULL,
            `sort_order` INT(6) DEFAULT 0,
            `role_required` ENUM('admin', 'viewer') DEFAULT 'viewer',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`parent_id`) REFERENCES `menu_items`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLE FOR AGENT TOKENS (Windows Agent authentication)
        "CREATE TABLE IF NOT EXISTS `agent_tokens` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `token` VARCHAR(255) NOT NULL UNIQUE,
            `enabled` BOOLEAN DEFAULT TRUE,
            `last_used_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLES FOR ASYNC METRICS INGEST
        "CREATE TABLE IF NOT EXISTS `metrics_ingest_queue` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `idempotency_key` VARCHAR(255) NOT NULL,
            `message_type` VARCHAR(50) NOT NULL,
            `payload_json` LONGTEXT NOT NULL,
            `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
            `last_error` TEXT NULL,
            `queued_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `processed_at` TIMESTAMP NULL,
            UNIQUE KEY `uniq_metrics_ingest_queue_idem` (`idempotency_key`),
            KEY `idx_metrics_ingest_queue_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `metrics_ingest_dead_letter` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `idempotency_key` VARCHAR(255) NOT NULL,
            `message_type` VARCHAR(50) NOT NULL,
            `payload_json` LONGTEXT NOT NULL,
            `error_reason` TEXT NULL,
            `failed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_metrics_ingest_dead_letter_idem` (`idempotency_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `metrics_ingest_dedup` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `idempotency_key` VARCHAR(255) NOT NULL,
            `message_type` VARCHAR(50) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'processing',
            `last_error` TEXT NULL,
            `processed_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_metrics_ingest_dedup_idem` (`idempotency_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLE FOR HOST METRICS (Windows Agent telemetry)
        "CREATE TABLE IF NOT EXISTS `host_metrics` (
            `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `hostname` VARCHAR(255) NOT NULL,
            `ip_address` VARCHAR(45) NULL,
            `os_version` VARCHAR(255) NULL,
            `cpu_usage` DECIMAL(5,2) NULL,
            `memory_usage` DECIMAL(5,2) NULL,
            `memory_total` DECIMAL(12,2) NULL,
            `disk_usage` DECIMAL(5,2) NULL,
            `disk_total` DECIMAL(12,2) NULL,
            `gpu_usage` DECIMAL(5,2) NULL,
            `network_in` BIGINT NULL,
            `network_out` BIGINT NULL,
            `uptime_seconds` BIGINT NULL,
            `boot_time` TIMESTAMP NULL,
            `status` VARCHAR(20) DEFAULT 'online',
            `agent_token_id` INT(6) UNSIGNED NULL,
            `first_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `last_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `hostname_unique` (`hostname`),
            FOREIGN KEY (`agent_token_id`) REFERENCES `agent_tokens`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLE FOR HOST METRICS HISTORY
        "CREATE TABLE IF NOT EXISTS `host_metrics_history` (
            `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `hostname` VARCHAR(255) NOT NULL,
            `cpu_usage` DECIMAL(5,2) NULL,
            `memory_usage` DECIMAL(5,2) NULL,
            `memory_total` DECIMAL(12,2) NULL,
            `disk_usage` DECIMAL(5,2) NULL,
            `disk_total` DECIMAL(12,2) NULL,
            `gpu_usage` DECIMAL(5,2) NULL,
            `network_in` BIGINT NULL,
            `network_out` BIGINT NULL,
            `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_hostname_recorded` (`hostname`, `recorded_at` DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLE FOR HOST PROCESSES
        "CREATE TABLE IF NOT EXISTS `host_processes` (
            `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `hostname` VARCHAR(255) NOT NULL,
            `process_name` VARCHAR(255) NOT NULL,
            `process_type` ENUM('process','service') DEFAULT 'process',
            `pid` INT(10) NULL,
            `cpu_percent` DECIMAL(5,2) NULL,
            `memory_mb` DECIMAL(10,2) NULL,
            `status` VARCHAR(50) NULL,
            `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_hostname_recorded` (`hostname`, `recorded_at` DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLE FOR HOST ALERT SETTINGS (global thresholds per user)
        "CREATE TABLE IF NOT EXISTS `host_alert_settings` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `enabled` BOOLEAN DEFAULT TRUE,
            `cpu_warning_threshold` DECIMAL(5,2) DEFAULT 80.00,
            `cpu_critical_threshold` DECIMAL(5,2) DEFAULT 95.00,
            `memory_warning_threshold` DECIMAL(5,2) DEFAULT 80.00,
            `memory_critical_threshold` DECIMAL(5,2) DEFAULT 95.00,
            `disk_warning_threshold` DECIMAL(5,2) DEFAULT 80.00,
            `disk_critical_threshold` DECIMAL(5,2) DEFAULT 95.00,
            `gpu_warning_threshold` DECIMAL(5,2) DEFAULT 80.00,
            `gpu_critical_threshold` DECIMAL(5,2) DEFAULT 95.00,
            `cooldown_minutes` INT(11) DEFAULT 30,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `user_id_unique` (`user_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLE FOR PER-HOST ALERT OVERRIDES
        "CREATE TABLE IF NOT EXISTS `host_alert_overrides` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `hostname` VARCHAR(255) NULL,
            `host_ip` VARCHAR(45) NULL,
            `enabled` BOOLEAN DEFAULT TRUE,
            `cpu_warning` DECIMAL(5,2) NULL,
            `cpu_critical` DECIMAL(5,2) NULL,
            `memory_warning` DECIMAL(5,2) NULL,
            `memory_critical` DECIMAL(5,2) NULL,
            `disk_warning` DECIMAL(5,2) NULL,
            `disk_critical` DECIMAL(5,2) NULL,
            `gpu_warning` DECIMAL(5,2) NULL,
            `gpu_critical` DECIMAL(5,2) NULL,
            `status_delay_seconds` INT(11) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `hostname_unique` (`hostname`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLE FOR FLOOR PLANS
        "CREATE TABLE IF NOT EXISTS `floor_plans` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `name` VARCHAR(255) NOT NULL DEFAULT 'Floor Plan',
            `image_url` TEXT NULL,
            `width` INT DEFAULT 1200,
            `height` INT DEFAULT 800,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLE FOR RACK LOCATIONS
        "CREATE TABLE IF NOT EXISTS `rack_locations` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `floor_plan_id` INT(6) UNSIGNED NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `x` DECIMAL(10,2) DEFAULT 0,
            `y` DECIMAL(10,2) DEFAULT 0,
            `rack_units` INT DEFAULT 42,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`floor_plan_id`) REFERENCES `floor_plans`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLE FOR PATCH PANELS
        "CREATE TABLE IF NOT EXISTS `patch_panels` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `rack_id` INT(6) UNSIGNED NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `port_count` INT DEFAULT 24,
            `rack_position` INT DEFAULT 1,
            `panel_type` VARCHAR(50) DEFAULT 'rj45',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`rack_id`) REFERENCES `rack_locations`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLE FOR SWITCH PORTS
        "CREATE TABLE IF NOT EXISTS `switch_ports` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `device_id` INT(6) UNSIGNED NOT NULL,
            `port_number` INT NOT NULL,
            `port_label` VARCHAR(50) NULL,
            `status` VARCHAR(50) DEFAULT 'inactive',
            `speed` VARCHAR(20) DEFAULT '1G',
            `vlan` VARCHAR(50) NULL,
            `connected_device` VARCHAR(255) NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLE FOR CABLE RUNS
        "CREATE TABLE IF NOT EXISTS `cable_runs` (
            `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `floor_plan_id` INT(6) UNSIGNED NULL,
            `cable_type` VARCHAR(50) DEFAULT 'cat6',
            `cable_color` VARCHAR(50) DEFAULT 'blue',
            `cable_length` VARCHAR(50) NULL,
            `label` VARCHAR(255) NULL,
            `source_type` VARCHAR(50) NOT NULL DEFAULT 'patch_panel',
            `source_id` INT(6) UNSIGNED NOT NULL,
            `source_port` INT NOT NULL,
            `dest_type` VARCHAR(50) NOT NULL DEFAULT 'switch',
            `dest_id` INT(6) UNSIGNED NOT NULL,
            `dest_port` INT NOT NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`floor_plan_id`) REFERENCES `floor_plans`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLE FOR AGENT ENROLLMENT TOKENS
        "CREATE TABLE IF NOT EXISTS `agent_enrollment_tokens` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `token_hash` VARCHAR(64) UNIQUE NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `expires_at` DATETIME NULL,
            `created_by` BIGINT UNSIGNED NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `last_used_at` DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLE FOR AGENT DEVICES
        "CREATE TABLE IF NOT EXISTS `agent_devices` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `agent_uuid` VARCHAR(64) UNIQUE NOT NULL,
            `agent_name` VARCHAR(255) NULL,
            `hostname` VARCHAR(255) NOT NULL,
            `device_name` VARCHAR(255) NULL,
            `os_name` VARCHAR(128) NOT NULL,
            `os_version` VARCHAR(128) NOT NULL,
            `architecture` VARCHAR(32) NOT NULL,
            `username` VARCHAR(255) NULL,
            `domain` VARCHAR(255) NULL,
            `local_ip` VARCHAR(64) NULL,
            `public_ip` VARCHAR(64) NULL,
            `mac_address` VARCHAR(64) NULL,
            `cpu_model` VARCHAR(255) NULL,
            `cpu_cores` INT UNSIGNED DEFAULT 1,
            `total_memory_mb` INT UNSIGNED DEFAULT 0,
            `total_disk_gb` INT UNSIGNED DEFAULT 0,
            `app_version` VARCHAR(32) NOT NULL,
            `server_address` VARCHAR(1024) NOT NULL,
            `heartbeat_interval_seconds` INT UNSIGNED DEFAULT 5,
            `status` ENUM('online', 'warning', 'offline') DEFAULT 'offline',
            `last_seen_at` DATETIME NULL,
            `registered_at` DATETIME NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `is_active` TINYINT(1) DEFAULT 1,
            INDEX `idx_agent_devices_uuid` (`agent_uuid`),
            INDEX `idx_agent_devices_hostname` (`hostname`),
            INDEX `idx_agent_devices_last_seen` (`last_seen_at`),
            INDEX `idx_agent_devices_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLE FOR AGENT DEVICE SECRETS
        "CREATE TABLE IF NOT EXISTS `agent_device_secrets` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `agent_device_id` BIGINT UNSIGNED NOT NULL,
            `secret_hash` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `rotated_at` DATETIME NULL,
            `revoked_at` DATETIME NULL,
            INDEX `idx_agent_device_secrets_device` (`agent_device_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLE FOR AGENT HEARTBEATS
        "CREATE TABLE IF NOT EXISTS `agent_heartbeats` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `agent_device_id` BIGINT UNSIGNED NOT NULL,
            `cpu_usage_percent` DECIMAL(5,2) DEFAULT 0.00,
            `memory_used_mb` INT UNSIGNED DEFAULT 0,
            `memory_total_mb` INT UNSIGNED DEFAULT 0,
            `memory_usage_percent` DECIMAL(5,2) DEFAULT 0.00,
            `disk_used_gb` INT UNSIGNED DEFAULT 0,
            `disk_total_gb` INT UNSIGNED DEFAULT 0,
            `disk_usage_percent` DECIMAL(5,2) DEFAULT 0.00,
            `network_rx_bytes` BIGINT UNSIGNED DEFAULT 0,
            `network_tx_bytes` BIGINT UNSIGNED DEFAULT 0,
            `uptime_seconds` BIGINT UNSIGNED DEFAULT 0,
            `battery_percent` TINYINT UNSIGNED NULL,
            `battery_status` VARCHAR(32) DEFAULT 'unknown',
            `active_user` VARCHAR(255) NULL,
            `current_ip` VARCHAR(64) NULL,
            `process_count` INT UNSIGNED DEFAULT 0,
            `service_count` INT UNSIGNED DEFAULT 0,
            `agent_version` VARCHAR(32) NOT NULL,
            `collected_at` DATETIME NOT NULL,
            `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `raw_payload_json` LONGTEXT NULL,
            INDEX `idx_agent_heartbeats_device` (`agent_device_id`),
            INDEX `idx_agent_heartbeats_collected` (`collected_at`),
            INDEX `idx_agent_heartbeats_received` (`received_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // TABLE FOR AGENT EVENTS
        "CREATE TABLE IF NOT EXISTS `agent_events` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `agent_device_id` BIGINT UNSIGNED NOT NULL,
            `event_type` VARCHAR(64) NOT NULL,
            `severity` ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
            `message` TEXT NOT NULL,
            `metadata_json` LONGTEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_agent_events_device` (`agent_device_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        // SYSTEM BACKUP TABLES (FTP / NAS)
        "CREATE TABLE IF NOT EXISTS `system_backup_schedules` (
            `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `target_type` ENUM('ftp', 'nas') NOT NULL,
            `target_config` TEXT NULL,
            `schedule_type` ENUM('daily', 'weekly', 'monthly') NOT NULL DEFAULT 'daily',
            `schedule_time` TIME NOT NULL DEFAULT '00:15:00',
            `day_of_week` TINYINT UNSIGNED NULL,
            `day_of_month` TINYINT UNSIGNED NULL,
            `enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `last_run_at` TIMESTAMP NULL,
            `next_run_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `system_backup_runs` (
            `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `schedule_id` INT(10) UNSIGNED NULL,
            `user_id` INT(6) UNSIGNED NOT NULL,
            `status` ENUM('success', 'failed') NOT NULL,
            `target_type` ENUM('ftp', 'nas') NOT NULL,
            `file_name` VARCHAR(255) NULL,
            `file_size_bytes` BIGINT UNSIGNED NULL,
            `error_message` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`schedule_id`) REFERENCES `system_backup_schedules`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];

    foreach ($tables as $sql) {
        $pdo->exec($sql);
        preg_match('/CREATE TABLE IF NOT EXISTS `(\w+)`/', $sql, $matches);
        $tableName = $matches[1] ?? 'unknown';
        message("Table '$tableName' checked/created successfully.");
    }

    // Step 4: Schema migration section to handle upgrades
    // columnExists function is defined above
    
    if (!columnExists($pdo, $dbname, 'maps', 'user_id')) {
        $pdo->exec("ALTER TABLE `maps` ADD COLUMN `user_id` INT(6) UNSIGNED;");
        $updateStmt = $pdo->prepare("UPDATE `maps` SET user_id = ?");
        $updateStmt->execute([$admin_id]);
        $pdo->exec("ALTER TABLE `maps` MODIFY COLUMN `user_id` INT(6) UNSIGNED NOT NULL;");
        $pdo->exec("ALTER TABLE `maps` ADD CONSTRAINT `fk_maps_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;");
        message("Upgraded 'maps' table: assigned existing maps to admin.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'user_id')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `user_id` INT(6) UNSIGNED;");
        $updateStmt = $pdo->prepare("UPDATE `devices` SET user_id = ?");
        $updateStmt->execute([$admin_id]);
        $pdo->exec("ALTER TABLE `devices` MODIFY COLUMN `user_id` INT(6) UNSIGNED NOT NULL;");
        $pdo->exec("ALTER TABLE `devices` ADD CONSTRAINT `fk_devices_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;");
        message("Upgraded 'devices' table: assigned existing devices to admin.");
    }
    if (!columnExists($pdo, $dbname, 'device_edges', 'user_id')) {
        $pdo->exec("ALTER TABLE `device_edges` ADD COLUMN `user_id` INT(6) UNSIGNED;");
        $updateStmt = $pdo->prepare("UPDATE `device_edges` SET user_id = ?");
        $updateStmt->execute([$admin_id]);
        $pdo->exec("ALTER TABLE `device_edges` MODIFY COLUMN `user_id` INT(6) UNSIGNED NOT NULL;");
        $pdo->exec("ALTER TABLE `device_edges` ADD CONSTRAINT `fk_device_edges_user_id` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;");
        message("Upgraded 'device_edges' table: assigned existing edges to admin.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'check_port')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `check_port` INT(5) NULL AFTER `ip`;");
        message("Upgraded 'devices' table: added 'check_port' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'monitor_method')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `monitor_method` ENUM('ping','port') DEFAULT 'ping' AFTER `check_port`;");
        message("Upgraded 'devices' table: added 'monitor_method' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'icon_url')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `icon_url` VARCHAR(255) NULL AFTER `name_text_size`;");
        message("Upgraded 'devices' table: added 'icon_url' column for custom icons.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'name_text_color')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `name_text_color` VARCHAR(20) DEFAULT '#ffffff' AFTER `name_text_size`;");
        message("Upgraded 'devices' table: added 'name_text_color' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'name_text_bold')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `name_text_bold` TINYINT(1) DEFAULT 0 AFTER `name_text_color`;");
        message("Upgraded 'devices' table: added 'name_text_bold' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'name_text_italic')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `name_text_italic` TINYINT(1) DEFAULT 0 AFTER `name_text_bold`;");
        message("Upgraded 'devices' table: added 'name_text_italic' column.");
    }
    // NEW MIGRATION: Add subchoice column for icon variants
    if (!columnExists($pdo, $dbname, 'devices', 'subchoice')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `subchoice` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `type`;");
        message("Migrated 'devices' table: added 'subchoice' column for icon variants.");
    }
    if (!columnExists($pdo, $dbname, 'maps', 'background_color')) {
        $pdo->exec("ALTER TABLE `maps` ADD COLUMN `background_color` VARCHAR(20) NULL AFTER `description`;");
        message("Upgraded 'maps' table: added 'background_color' column.");
    }
    if (!columnExists($pdo, $dbname, 'maps', 'background_image_url')) {
        $pdo->exec("ALTER TABLE `maps` ADD COLUMN `background_image_url` VARCHAR(255) NULL AFTER `background_color`;");
        message("Upgraded 'maps' table: added 'background_image_url' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'description')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `description` TEXT NULL AFTER `type`;");
        message("Upgraded 'devices' table: added 'description' column.");
    }
    // NEW MIGRATION: Add public_view_enabled to maps table
    if (!columnExists($pdo, $dbname, 'maps', 'public_view_enabled')) {
        $pdo->exec("ALTER TABLE `maps` ADD COLUMN `public_view_enabled` BOOLEAN DEFAULT FALSE AFTER `is_default`;");
        message("Migrated `maps` table: added `public_view_enabled` column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'router_api_username')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `router_api_username` VARCHAR(100) NULL AFTER `icon_url`;");
        message("Upgraded 'devices' table: added 'router_api_username' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'router_api_password')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `router_api_password` TEXT NULL AFTER `router_api_username`;");
        message("Upgraded 'devices' table: added 'router_api_password' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'router_api_port')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `router_api_port` INT(5) NULL AFTER `router_api_password`;");
        message("Upgraded 'devices' table: added 'router_api_port' column.");
    }
    // NEW MIGRATION: Add port labels to device_edges for Cisco Packet Tracer-style port mapping
    if (!columnExists($pdo, $dbname, 'device_edges', 'source_port_label')) {
        $pdo->exec("ALTER TABLE `device_edges` ADD COLUMN `source_port_label` VARCHAR(50) NULL AFTER `connection_type`;");
        message("Upgraded 'device_edges' table: added 'source_port_label' column.");
    }
    if (!columnExists($pdo, $dbname, 'device_edges', 'target_port_label')) {
        $pdo->exec("ALTER TABLE `device_edges` ADD COLUMN `target_port_label` VARCHAR(50) NULL AFTER `source_port_label`;");
        message("Upgraded 'device_edges' table: added 'target_port_label' column.");
    }
    // NEW MIGRATION: Add port_config column for custom port type/count definitions per device
    if (!columnExists($pdo, $dbname, 'devices', 'port_config')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `port_config` TEXT NULL AFTER `subchoice`;");
        message("Migrated 'devices' table: added 'port_config' column for custom port layouts.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'name_text_color')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `name_text_color` VARCHAR(20) DEFAULT '#ffffff' AFTER `name_text_size`;");
        message("Upgraded 'devices' table: added 'name_text_color' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'name_text_bold')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `name_text_bold` TINYINT(1) DEFAULT 0 AFTER `name_text_color`;");
        message("Upgraded 'devices' table: added 'name_text_bold' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'name_text_italic')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `name_text_italic` TINYINT(1) DEFAULT 0 AFTER `name_text_bold`;");
        message("Upgraded 'devices' table: added 'name_text_italic' column.");
    }
    // v1.19 MIGRATION: Sub-Maps & 19" Rack Support
    if (!columnExists($pdo, $dbname, 'maps', 'parent_map_id')) {
        $pdo->exec("ALTER TABLE `maps` ADD COLUMN `parent_map_id` INT NULL DEFAULT NULL AFTER `id`;");
        message("Upgraded 'maps' table: added 'parent_map_id' column for drill-down sub-maps.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'target_map_id')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `target_map_id` INT NULL DEFAULT NULL AFTER `name_text_italic`;");
        message("Upgraded 'devices' table: added 'target_map_id' column for sub-map links.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'is_rack')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `is_rack` TINYINT(1) DEFAULT 0 AFTER `target_map_id`;");
        message("Upgraded 'devices' table: added 'is_rack' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'rack_units')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `rack_units` INT DEFAULT 42 AFTER `is_rack`;");
        message("Upgraded 'devices' table: added 'rack_units' column.");
    }
    if (!columnExists($pdo, $dbname, 'devices', 'rack_position')) {
        $pdo->exec("ALTER TABLE `devices` ADD COLUMN `rack_position` INT NULL AFTER `rack_units`;");
        message("Upgraded 'devices' table: added 'rack_position' column.");
    }

    // v1.19 MIGRATION: Bandwidth Flow & Link Traffic Analytics
    if (!columnExists($pdo, $dbname, 'device_edges', 'bandwidth_speed_mbps')) {
        $pdo->exec("ALTER TABLE `device_edges` ADD COLUMN `bandwidth_speed_mbps` INT DEFAULT 1000 AFTER `target_port_label`;");
        message("Upgraded 'device_edges' table: added 'bandwidth_speed_mbps' column.");
    }
    if (!columnExists($pdo, $dbname, 'device_edges', 'utilization_percent')) {
        $pdo->exec("ALTER TABLE `device_edges` ADD COLUMN `utilization_percent` FLOAT DEFAULT 0 AFTER `bandwidth_speed_mbps`;");
        message("Upgraded 'device_edges' table: added 'utilization_percent' column.");
    }
    if (!columnExists($pdo, $dbname, 'device_edges', 'rx_bytes')) {
        $pdo->exec("ALTER TABLE `device_edges` ADD COLUMN `rx_bytes` BIGINT DEFAULT 0 AFTER `utilization_percent`;");
        message("Upgraded 'device_edges' table: added 'rx_bytes' column.");
    }
    if (!columnExists($pdo, $dbname, 'device_edges', 'tx_bytes')) {
        $pdo->exec("ALTER TABLE `device_edges` ADD COLUMN `tx_bytes` BIGINT DEFAULT 0 AFTER `rx_bytes`;");
        message("Upgraded 'device_edges' table: added 'tx_bytes' column.");
    }

    // v1.19 MIGRATION: Audit Logs Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_logs` (
        `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT(11) NULL,
        `username` VARCHAR(100) NOT NULL,
        `action` VARCHAR(100) NOT NULL,
        `entity_type` VARCHAR(50) NOT NULL,
        `entity_id` INT(11) NULL,
        `details` TEXT NULL,
        `ip_address` VARCHAR(45) NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    message("Created or verified 'audit_logs' table.");

    // v1.19 MIGRATION: Map Permissions Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `map_permissions` (
        `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `map_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `permission` ENUM('read', 'write', 'admin') DEFAULT 'read',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `map_user_unique` (`map_id`, `user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    message("Created or verified 'map_permissions' table.");

    // MIGRATION: Rename cat5 to cat6 in device_edges
    $pdo->exec("UPDATE `device_edges` SET `connection_type` = 'cat6' WHERE `connection_type` = 'cat5'");
    message("Migrated 'device_edges': renamed 'cat5' connections to 'cat6'.");

    // MIGRATION: Add canvas-related columns to rack_locations
    if (!columnExists($pdo, $dbname, 'rack_locations', 'rotation')) {
        $pdo->exec("ALTER TABLE `rack_locations` ADD COLUMN `rotation` INT DEFAULT 0 AFTER `rack_units`;");
        message("Upgraded 'rack_locations' table: added 'rotation' column.");
    }
    if (!columnExists($pdo, $dbname, 'rack_locations', 'label_visible')) {
        $pdo->exec("ALTER TABLE `rack_locations` ADD COLUMN `label_visible` TINYINT(1) DEFAULT 1 AFTER `rotation`;");
        message("Upgraded 'rack_locations' table: added 'label_visible' column.");
    }

    // MIGRATION: Add floor_plan_devices table for placing devices on canvas
    $pdo->exec("CREATE TABLE IF NOT EXISTS `floor_plan_devices` (
        `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `floor_plan_id` INT(6) UNSIGNED NOT NULL,
        `device_id` INT(6) UNSIGNED NOT NULL,
        `x` DECIMAL(10,2) DEFAULT 0,
        `y` DECIMAL(10,2) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`floor_plan_id`) REFERENCES `floor_plans`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_plan_device` (`floor_plan_id`, `device_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    message("Table 'floor_plan_devices' checked/created successfully.");

    // MIGRATION: Add floor_plan_annotations table for labels and zones on canvas
    $pdo->exec("CREATE TABLE IF NOT EXISTS `floor_plan_annotations` (
        `id` INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `floor_plan_id` INT(6) UNSIGNED NOT NULL,
        `x` DECIMAL(10,2) DEFAULT 0,
        `y` DECIMAL(10,2) DEFAULT 0,
        `text` VARCHAR(500) DEFAULT 'Label',
        `font_size` INT DEFAULT 14,
        `color` VARCHAR(50) DEFAULT '#94a3b8',
        `type` VARCHAR(20) DEFAULT 'label',
        `width` INT NULL,
        `height` INT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`floor_plan_id`) REFERENCES `floor_plans`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    message("Table 'floor_plan_annotations' checked/created successfully.");

    // MIGRATION: Add width/height to floor_plans for canvas dimensions
    if (!columnExists($pdo, $dbname, 'floor_plans', 'width')) {
        $pdo->exec("ALTER TABLE `floor_plans` ADD COLUMN `width` INT DEFAULT 2000 AFTER `image_url`;");
        message("Upgraded 'floor_plans' table: added 'width' column.");
    }
    if (!columnExists($pdo, $dbname, 'floor_plans', 'height')) {
        $pdo->exec("ALTER TABLE `floor_plans` ADD COLUMN `height` INT DEFAULT 1500 AFTER `width`;");
        message("Upgraded 'floor_plans' table: added 'height' column.");
    }

    if (!columnExists($pdo, $dbname, 'host_metrics', 'platform')) {
        $pdo->exec("ALTER TABLE `host_metrics` ADD COLUMN `platform` VARCHAR(20) NULL AFTER `agent_token_id`;");
        message("Upgraded 'host_metrics' table: added 'platform' column.");
    }
    if (!columnExists($pdo, $dbname, 'host_metrics', 'load_average')) {
        $pdo->exec("ALTER TABLE `host_metrics` ADD COLUMN `load_average` DECIMAL(10,2) NULL AFTER `platform`;");
        message("Upgraded 'host_metrics' table: added 'load_average' column.");
    }
    if (!columnExists($pdo, $dbname, 'host_metrics', 'temperature_celsius')) {
        $pdo->exec("ALTER TABLE `host_metrics` ADD COLUMN `temperature_celsius` DECIMAL(6,2) NULL AFTER `load_average`;");
        message("Upgraded 'host_metrics' table: added 'temperature_celsius' column.");
    }

    // MIGRATION: Add extended SMTP customization options
    if (!columnExists($pdo, $dbname, 'smtp_settings', 'reply_to_email')) {
        $pdo->exec("ALTER TABLE `smtp_settings` ADD COLUMN `reply_to_email` VARCHAR(255) NULL AFTER `from_name`;");
        message("Upgraded 'smtp_settings' table: added 'reply_to_email' column.");
    }
    if (!columnExists($pdo, $dbname, 'smtp_settings', 'bind_ip')) {
        $pdo->exec("ALTER TABLE `smtp_settings` ADD COLUMN `bind_ip` VARCHAR(45) NULL AFTER `from_name`;");
        message("Upgraded 'smtp_settings' table: added 'bind_ip' column.");
    }
    if (!columnExists($pdo, $dbname, 'smtp_settings', 'subject_prefix')) {
        $pdo->exec("ALTER TABLE `smtp_settings` ADD COLUMN `subject_prefix` VARCHAR(120) DEFAULT '[AMPNM]' AFTER `reply_to_email`;");
        message("Upgraded 'smtp_settings' table: added 'subject_prefix' column.");
    }
    if (!columnExists($pdo, $dbname, 'smtp_settings', 'connection_timeout_seconds')) {
        $pdo->exec("ALTER TABLE `smtp_settings` ADD COLUMN `connection_timeout_seconds` INT(5) UNSIGNED DEFAULT 20 AFTER `subject_prefix`;");
        message("Upgraded 'smtp_settings' table: added 'connection_timeout_seconds' column.");
    }

    // ==========================================
    // v1.20 MIGRATION: SNMP Deep Router & Switch Monitoring
    // ==========================================
    $snmpCols = [
        'snmp_enabled' => "TINYINT(1) DEFAULT 0",
        'snmp_version' => "ENUM('v1', 'v2c', 'v3') DEFAULT 'v2c'",
        'snmp_community' => "VARCHAR(128) DEFAULT 'public'",
        'snmp_port' => "INT DEFAULT 161",
        'snmp_v3_user' => "VARCHAR(128) NULL",
        'snmp_v3_auth_proto' => "VARCHAR(32) DEFAULT 'SHA'",
        'snmp_v3_auth_pass' => "VARCHAR(128) NULL",
        'snmp_v3_priv_proto' => "VARCHAR(32) DEFAULT 'AES'",
        'snmp_v3_priv_pass' => "VARCHAR(128) NULL",
        'snmp_v3_sec_level' => "VARCHAR(32) DEFAULT 'authPriv'",
        'snmp_last_poll' => "DATETIME NULL",
        'snmp_sys_descr' => "VARCHAR(500) NULL",
        'snmp_sys_uptime' => "VARCHAR(120) NULL"
    ];

    foreach ($snmpCols as $col => $typeDef) {
        if (!columnExists($pdo, $dbname, 'devices', $col)) {
            $pdo->exec("ALTER TABLE `devices` ADD COLUMN `$col` $typeDef;");
            message("Upgraded 'devices' table: added '$col' column for SNMP.");
        }
    }

    // SNMP Interface Table for Switches/Routers
    $pdo->exec("CREATE TABLE IF NOT EXISTS `device_snmp_interfaces` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `device_id` INT(6) UNSIGNED NOT NULL,
        `if_index` INT NOT NULL,
        `if_descr` VARCHAR(255) NOT NULL,
        `if_alias` VARCHAR(255) NULL,
        `if_type` VARCHAR(64) DEFAULT 'ethernet',
        `if_speed` BIGINT UNSIGNED DEFAULT 0,
        `if_mac` VARCHAR(32) NULL,
        `if_admin_status` VARCHAR(20) DEFAULT 'up',
        `if_oper_status` VARCHAR(20) DEFAULT 'up',
        `if_in_octets` BIGINT UNSIGNED DEFAULT 0,
        `if_out_octets` BIGINT UNSIGNED DEFAULT 0,
        `if_in_errors` INT UNSIGNED DEFAULT 0,
        `if_out_errors` INT UNSIGNED DEFAULT 0,
        `in_rate_bps` BIGINT UNSIGNED DEFAULT 0,
        `out_rate_bps` BIGINT UNSIGNED DEFAULT 0,
        `last_poll_time` DATETIME NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `uk_dev_if` (`device_id`, `if_index`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    message("Created or verified 'device_snmp_interfaces' table.");

    // SNMP Timeseries History for Traffic & Hardware Graphs
    $pdo->exec("CREATE TABLE IF NOT EXISTS `device_snmp_history` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `device_id` INT(6) UNSIGNED NOT NULL,
        `if_index` INT NOT NULL,
        `in_rate_bps` BIGINT UNSIGNED DEFAULT 0,
        `out_rate_bps` BIGINT UNSIGNED DEFAULT 0,
        `cpu_usage` FLOAT NULL,
        `memory_usage` FLOAT NULL,
        `temperature` FLOAT NULL,
        `uptime_seconds` BIGINT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE CASCADE,
        INDEX `idx_dev_if_time` (`device_id`, `if_index`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    message("Created or verified 'device_snmp_history' table.");

    // Tie map connection edges to specific SNMP switch port interface
    if (!columnExists($pdo, $dbname, 'device_edges', 'snmp_source_if_index')) {
        $pdo->exec("ALTER TABLE `device_edges` ADD COLUMN `snmp_source_if_index` INT NULL AFTER `tx_bytes`;");
        message("Upgraded 'device_edges' table: added 'snmp_source_if_index' column.");
    }
    if (!columnExists($pdo, $dbname, 'device_edges', 'snmp_target_if_index')) {
        $pdo->exec("ALTER TABLE `device_edges` ADD COLUMN `snmp_target_if_index` INT NULL AFTER `snmp_source_if_index`;");
        message("Upgraded 'device_edges' table: added 'snmp_target_if_index' column.");
    }
    if (!columnExists($pdo, $dbname, 'smtp_settings', 'max_emails_per_hour')) {
        $pdo->exec("ALTER TABLE `smtp_settings` ADD COLUMN `max_emails_per_hour` INT(6) UNSIGNED DEFAULT 240 AFTER `connection_timeout_seconds`;");
        message("Upgraded 'smtp_settings' table: added 'max_emails_per_hour' column.");
    }
    if (!columnExists($pdo, $dbname, 'smtp_settings', 'allow_invalid_certs')) {
        $pdo->exec("ALTER TABLE `smtp_settings` ADD COLUMN `allow_invalid_certs` TINYINT(1) DEFAULT 0 AFTER `max_emails_per_hour`;");
        message("Upgraded 'smtp_settings' table: added 'allow_invalid_certs' column.");
    }

    // ==========================================
    // v1.20 MIGRATION: Agent Secure Remote Commanding
    // ==========================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `agent_commands` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `agent_token_id` VARCHAR(128) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `command_type` VARCHAR(50) NOT NULL,
        `command_payload` TEXT NULL,
        `status` ENUM('pending', 'running', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
        `result_output` LONGTEXT NULL,
        `exit_code` INT NULL,
        `execution_time_ms` INT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `executed_at` DATETIME NULL,
        `completed_at` DATETIME NULL,
        INDEX `idx_token_status` (`agent_token_id`, `status`),
        INDEX `idx_user_time` (`user_id`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    message("Created or verified 'agent_commands' table.");

    // Step 5: Check if the admin user has any maps
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `maps` WHERE user_id = ?");
    $stmt->execute([$admin_id]);
    if ($stmt->fetchColumn() == 0) {
        $pdo->prepare("INSERT INTO `maps` (user_id, name, type, is_default) VALUES (?, 'Default LAN Map', 'lan', TRUE)")->execute([$admin_id]);
        message("Created a default map for the admin user.");
    }

    // Step 6: Indexing for Performance
    function indexExists($pdo, $db, $table, $index) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?");
        $stmt->execute([$db, $table, $index]);
        return $stmt->fetchColumn() > 0;
    }

    message("Applying database indexes for performance...");
    $indexes = [
        'ping_results' => ['idx_host_created_at' => '(`host`, `created_at` DESC)'],
        'devices' => [
            'idx_ip' => '(`ip`)',
            'idx_map_id' => '(`map_id`)',
            'idx_user_id' => '(`user_id`)'
        ],
        'device_status_logs' => ['idx_device_created' => '(`device_id`, `created_at` DESC)']
    ];

    foreach ($indexes as $table => $indexList) {
        foreach ($indexList as $indexName => $columns) {
            if (!indexExists($pdo, $dbname, $table, $indexName)) {
                $pdo->exec("CREATE INDEX `$indexName` ON `$table` $columns;");
                message("Created index '$indexName' on table '$table'.");
            } else {
                message("Index '$indexName' on table '$table' already exists.");
            }
        }
    }

    // Initialize app_settings for license management
    $settings_to_init = [
        'installation_id' => generateUuid(),
        'app_license_key' => '' // Initially empty, user will fill this
    ];

    foreach ($settings_to_init as $key => $value) {
        $stmt = $pdo->prepare("SELECT setting_value FROM `app_settings` WHERE setting_key = ?");
        $stmt->execute([$key]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO `app_settings` (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
            message("Initialized app setting: '$key'.");
        }
    }

    // Seed default agent token for local development and Windows Agent compatibility
    $default_agent_token = 'ampnm_1dc3c51eb6872b8eabcd2717e0b7bcf3';
    $stmt = $pdo->prepare("SELECT id FROM `agent_tokens` WHERE token = ?");
    $stmt->execute([$default_agent_token]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO `agent_tokens` (user_id, token, name, enabled) VALUES (?, ?, ?, 1)");
        $stmt->execute([$admin_id, $default_agent_token, 'Default Windows Agent']);
        message("Initialized default Windows agent token.");
    }

    // Seed default menu items if the table is empty
    $check_menu = $pdo->query("SELECT COUNT(*) FROM `menu_items`")->fetchColumn();
    if ($check_menu == 0) {
        message("Initializing default main menu items...");
        
        // Define default main menu tree
        // Format: [parent_index_or_null, title, url, icon, sort_order, role_required]
        $default_menus = [
            // Parents (index 0 to 5)
            [null, 'Dashboard', 'index.php', 'fas fa-tachometer-alt', 1, 'viewer'], // index 0
            [null, 'Network', '#', 'fas fa-network-wired', 2, 'viewer'],           // index 1
            [null, 'Monitoring', '#', 'fas fa-heartbeat', 3, 'viewer'],           // index 2
            [null, 'Administration', '#', 'fas fa-cogs', 4, 'admin'],             // index 3
            [null, 'Help', 'documentation.php', 'fas fa-book', 5, 'viewer'],      // index 4
            [null, 'Logout', 'logout.php', 'fas fa-sign-out-alt', 6, 'viewer'],     // index 5
            
            // Network submenu (parent_index = 1)
            [1, 'Map', 'map.php', 'fas fa-project-diagram', 1, 'viewer'],
            [1, 'Floor Plan', 'floor_plan.php', 'fas fa-building', 2, 'viewer'],
            [1, 'Network Graphs', 'network_graphs.php', 'fas fa-chart-line', 3, 'viewer'],
            
            // Monitoring submenu (parent_index = 2)
            [2, 'Host Metrics', 'host_metrics.php', 'fas fa-microchip', 1, 'viewer'],
            [2, 'Windows Agents', 'agent_devices.php', 'fas fa-desktop', 2, 'viewer'],
            [2, 'Agent Enrollment', 'agent_enrollment.php', 'fas fa-key', 3, 'viewer'],
            [2, 'Agent Settings', 'agent_settings.php', 'fas fa-sliders', 4, 'viewer'],
            [2, 'Agent Logs', 'agent_logs.php', 'fas fa-file-lines', 5, 'viewer'],
            [2, 'Alert Settings', 'alert_settings.php', 'fas fa-bell', 6, 'viewer'],
            [2, 'Agent Onboarding', 'windows_agent.php', 'fas fa-person-chalkboard', 7, 'viewer'],
            [2, 'Download Agents', 'download-agent.php', 'fas fa-download', 8, 'viewer'],
            [2, 'Windows Agent Guide', 'documentation.php#windows-agent', 'fas fa-book-open', 9, 'viewer'],
            [2, 'Agent API Health', 'api/agent/windows-metrics/health', 'fas fa-plug-circle-check', 10, 'viewer'],
            
            // Administration submenu (parent_index = 3)
            [3, 'Devices', 'devices.php', 'fas fa-server', 1, 'admin'],
            [3, 'History', 'history.php', 'fas fa-history', 2, 'admin'],
            [3, 'Status Logs', 'status_logs.php', 'fas fa-clipboard-list', 3, 'admin'],
            [3, 'System Backup', 'system_backup.php', 'fas fa-database', 4, 'admin'],
            [3, 'Email Notifications', 'email_notifications.php', 'fas fa-envelope', 5, 'admin'],
            [3, 'SMS Notifications', 'sms_notifications.php', 'fas fa-sms', 6, 'admin'],
            [3, 'Telegram Notifications', 'telegram_notifications.php', 'fab fa-telegram', 7, 'admin'],
            [3, 'WhatsApp Notifications', 'whatsapp_notifications.php', 'fab fa-whatsapp', 8, 'admin'],
            [3, 'Update Status', 'update_status.php', 'fas fa-cloud-download-alt', 9, 'admin'],
            [3, 'Users', 'users.php', 'fas fa-users-cog', 10, 'admin'],
            [3, 'Menu & Themes', 'menu_settings.php', 'fas fa-palette', 11, 'admin'],
            [3, 'License', 'license_management.php', 'fas fa-id-card', 12, 'admin']
        ];
        
        $inserted_ids = [];
        
        // Insert parent nodes first
        foreach ($default_menus as $idx => $m) {
            if ($m[0] === null) {
                $stmt = $pdo->prepare("INSERT INTO `menu_items` (parent_id, title, url, icon, sort_order, role_required) VALUES (NULL, ?, ?, ?, ?, ?)");
                $stmt->execute([$m[1], $m[2], $m[3], $m[4], $m[5]]);
                $inserted_ids[$idx] = $pdo->lastInsertId();
            }
        }
        
        // Insert submenus
        foreach ($default_menus as $m) {
            if ($m[0] !== null) {
                $parent_id = $inserted_ids[$m[0]];
                $stmt = $pdo->prepare("INSERT INTO `menu_items` (parent_id, title, url, icon, sort_order, role_required) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$parent_id, $m[1], $m[2], $m[3], $m[4], $m[5]]);
            }
        }
        
        message("Default menu items successfully seeded.");
    }



    echo "<h2 style='color: #06b6d4; margin-top: 14px;'>Database setup completed successfully!</h2>";
    echo "<p style='color: #94a3b8;'><span class='loader'></span>Redirecting to the application in 3 seconds...</p>";
    echo '<meta http-equiv="refresh" content="3;url=index.php">';

} catch (PDOException $e) {
    message("Database setup failed: " . $e->getMessage(), true);
    exit(1);
}
?>
        </div>
        <div class="actions">
            <a href="index.php">&larr; Go to Dashboard</a>
        </div>
    </div>
</div>
<script>
(() => {
    const logs = Array.from(document.querySelectorAll('[data-setup-log]'));
    const fill = document.getElementById('setupProgressFill');
    const pct = document.getElementById('setupProgressPct');
    const label = document.getElementById('setupProgressLabel');
    const logBox = document.getElementById('setupLogBox');
    if (!fill || !pct || !label || !logBox) return;

    const total = Math.max(1, logs.length);
    const hasError = logs.some(row => row.classList.contains('error'));
    logs.forEach((row, idx) => { row.style.animationDelay = `${idx * 65}ms`; });

    const progressValue = hasError ? Math.min(96, Math.round((total / (total + 2)) * 100)) : 100;
    fill.style.width = progressValue + '%';
    pct.textContent = progressValue + '%';
    label.textContent = hasError
        ? `Completed with warnings (${total} steps logged)`
        : `Completed successfully (${total} steps)`;
    logBox.scrollTop = logBox.scrollHeight;
})();
</script>
</body>
</html>
