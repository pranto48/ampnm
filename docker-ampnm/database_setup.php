<?php
// Database configuration using environment variables for Docker compatibility
$servername = '127.0.0.1'; // Forcing 127.0.0.1 to resolve connection issues in Docker
$username = 'root'; // Setup script needs root privileges to create DB and tables
$password = getenv('MYSQL_ROOT_PASSWORD') ?: ''; // Get root password from Docker env
$dbname = getenv('DB_NAME') ?: 'network_monitor';

function message($text, $is_error = false) {
    $color = $is_error ? '#ef4444' : '#22c55e';
    echo "<p style='color: $color; margin: 4px 0; font-family: monospace;'>$text</p>";
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
        body { background-color: #0f172a; color: #cbd5e1; font-family: sans-serif; padding: 2rem; }
        .loader { border: 4px solid #334155; border-top: 4px solid #22d3ee; border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; display: inline-block; margin-right: 10px; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
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
            `public_view_enabled` BOOLEAN DEFAULT FALSE, /* NEW COLUMN */
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
        
        // NEW TABLE FOR APPLICATION SETTINGS (LICENSE KEY, INSTALLATION ID)
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


    echo "<h2 style='color: #06b6d4; font-family: sans-serif;'>Database setup completed successfully!</h2>";
    echo "<p style='color: #94a3b8;'><span class='loader'></span>Redirecting to the application in 3 seconds...</p>";
    echo '<meta http-equiv="refresh" content="3;url=index.php">';

} catch (PDOException $e) {
    message("Database setup failed: " . $e->getMessage(), true);
    exit(1);
}
?>
    <a href="index.php" style="color: #22d3ee; text-decoration: none; font-size: 1.2rem;">&larr; Go to Dashboard</a>
</body>
</html>