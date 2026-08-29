<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * Security Guard: Brute-force Jail, IP Rate-Limiting, and Zero-Trust Request Shield
 */

class SecurityGuard
{
    private static int $maxFailures = 5;
    private static int $jailDurationSeconds = 900; // 15 minutes jail
    private static int $rateLimitPerMinute = 120; // 120 reqs/min per IP

    /**
     * Get real client IP addressing reverse proxy headers
     */
    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Check if client IP is jailed
     */
    public static function checkJail(PDO $pdo): bool
    {
        $ip = self::getClientIp();

        // Whitelist local loopback
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return false;
        }

        $stmt = $pdo->prepare("SELECT id, reason, jail_until FROM security_ip_jail WHERE ip_address = ? AND (jail_until > NOW() OR is_permanent = 1) LIMIT 1");
        $stmt->execute([$ip]);
        $jailed = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($jailed) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'Access Denied: Your IP has been jailed by AMPNM Security Shield.',
                'ip' => $ip,
                'reason' => $jailed['reason'],
                'jail_until' => $jailed['jail_until']
            ]);
            exit;
        }

        return false;
    }

    /**
     * Record a failed authentication attempt and auto-jail if threshold exceeded
     */
    public static function recordFailedAttempt(PDO $pdo, string $targetType, string $identifier): void
    {
        $ip = self::getClientIp();
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

        // 1. Log failed attempt
        $stmt = $pdo->prepare("INSERT INTO security_audit_logs (ip_address, event_type, target_type, target_identifier, details, user_agent) VALUES (?, 'auth_failed', ?, ?, 'Failed login/auth verification', ?)");
        $stmt->execute([$ip, $targetType, $identifier, $userAgent]);

        // 2. Count failures in last 10 minutes
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM security_audit_logs WHERE ip_address = ? AND event_type = 'auth_failed' AND created_at >= (NOW() - INTERVAL 10 MINUTE)");
        $stmt->execute([$ip]);
        $count = (int)$stmt->fetchColumn();

        if ($count >= self::$maxFailures) {
            self::jailIp($pdo, $ip, "Exceeded {$count} failed authentication attempts within 10 minutes", self::$jailDurationSeconds);
        }
    }

    /**
     * Put an IP into jail
     */
    public static function jailIp(PDO $pdo, string $ip, string $reason, int $durationSeconds = 900, bool $permanent = false): void
    {
        $stmt = $pdo->prepare("
            INSERT INTO security_ip_jail (ip_address, reason, jail_until, is_permanent)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?)
            ON DUPLICATE KEY UPDATE 
                reason = VALUES(reason),
                jail_until = DATE_ADD(NOW(), INTERVAL ? SECOND),
                is_permanent = VALUES(is_permanent),
                attempt_count = attempt_count + 1
        ");
        $stmt->execute([$ip, $reason, $durationSeconds, $permanent ? 1 : 0, $durationSeconds]);

        // Log jail event
        $stmt = $pdo->prepare("INSERT INTO security_audit_logs (ip_address, event_type, target_type, target_identifier, details) VALUES (?, 'ip_jailed', 'system', 'security_guard', ?)");
        $stmt->execute([$ip, "IP Jailed: {$reason} (Duration: {$durationSeconds}s)"]);
    }

    /**
     * Release an IP from jail
     */
    public static function unjailIp(PDO $pdo, string $ip): bool
    {
        $stmt = $pdo->prepare("DELETE FROM security_ip_jail WHERE ip_address = ?");
        return $stmt->execute([$ip]);
    }
}
