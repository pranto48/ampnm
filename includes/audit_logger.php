<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 */

if (!function_exists('log_audit')) {
    function log_audit($pdo, $action, $entity_type, $entity_id = null, $details = null) {
        try {
            $user_id = $_SESSION['user_id'] ?? null;
            $username = $_SESSION['username'] ?? 'system';
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            if (is_array($details) || is_object($details)) {
                $details = json_encode($details);
            }

            $stmt = $pdo->prepare("INSERT INTO `audit_logs` (`user_id`, `username`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $username, $action, $entity_type, $entity_id, $details, $ip_address]);
            return true;
        } catch (Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
            return false;
        }
    }
}
