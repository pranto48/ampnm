<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
// This is the new central bootstrap file.
// It handles basic setup like loading functions and checking database integrity.

require_once __DIR__ . '/functions.php';

// This script should not run on the setup page itself to avoid a redirect loop.
if (basename($_SERVER['PHP_SELF']) !== 'database_setup.php') {
    try {
        $pdo = getDbConnection();
        // A simple query to check if the main 'users' table exists.
        // If this fails, we assume the database has not been initialized.
        $pdo->query("SELECT 1 FROM `users` LIMIT 1");
        
        // Auto-create system backup tables if missing
        try {
            $pdo->query("SELECT 1 FROM `system_backup_schedules` LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `system_backup_schedules` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        try {
            $pdo->query("SELECT 1 FROM `system_backup_runs` LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `system_backup_runs` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        try {
            $pdo->query("SELECT `thickness`, `color`, `line_style`, `arrows`, `label`, `animated` FROM `device_edges` LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `device_edges` 
                    ADD COLUMN `thickness` INT DEFAULT 2,
                    ADD COLUMN `color` VARCHAR(50) DEFAULT NULL,
                    ADD COLUMN `line_style` VARCHAR(20) DEFAULT 'solid',
                    ADD COLUMN `arrows` VARCHAR(20) DEFAULT 'none',
                    ADD COLUMN `label` VARCHAR(100) DEFAULT NULL,
                    ADD COLUMN `animated` TINYINT(1) DEFAULT 1");
            } catch (Exception $e2) {
                // Ignore if already run or lock
            }
        }

        // Auto-migrate devices for label styles and position gap
        try {
            $pdo->query("SELECT `name_text_color` FROM `devices` LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `devices` 
                    ADD COLUMN `name_text_color` VARCHAR(20) DEFAULT '#ffffff',
                    ADD COLUMN `name_text_bold` TINYINT(1) DEFAULT 0,
                    ADD COLUMN `name_text_italic` TINYINT(1) DEFAULT 0");
            } catch (Exception $e2) {}
        }
        try {
            $pdo->query("SELECT `name_text_vadjust` FROM `devices` LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `devices` ADD COLUMN `name_text_vadjust` INT DEFAULT 0 COMMENT 'Label vertical offset in pixels'");
            } catch (Exception $e2) {}
        }

        // Auto-migrate users for group isolation
        try {
            $pdo->query("SELECT `user_group` FROM `users` LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `user_group` VARCHAR(50) NOT NULL DEFAULT 'default_group'");
            } catch (Exception $e2) {}
        }
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
    session_start();
}