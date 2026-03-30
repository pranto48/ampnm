<?php
require_once __DIR__ . '/../config.php';

function securityEnsureSchema(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `security_audit_log` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `event_type` VARCHAR(100) NOT NULL,
        `severity` ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
        `actor_type` VARCHAR(50) NULL,
        `actor_id` VARCHAR(128) NULL,
        `source_ip` VARCHAR(64) NULL,
        `request_path` VARCHAR(255) NULL,
        `details_json` LONGTEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_security_event_type` (`event_type`),
        INDEX `idx_security_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `auth_attempts` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `identity` VARCHAR(255) NOT NULL,
        `source_ip` VARCHAR(64) NOT NULL,
        `attempt_type` VARCHAR(50) NOT NULL,
        `was_success` BOOLEAN NOT NULL DEFAULT FALSE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_auth_attempt_identity` (`identity`,`created_at`),
        INDEX `idx_auth_attempt_ip` (`source_ip`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("ALTER TABLE `users`
        ADD COLUMN IF NOT EXISTS `mfa_secret` VARCHAR(128) NULL,
        ADD COLUMN IF NOT EXISTS `mfa_enabled` BOOLEAN NOT NULL DEFAULT FALSE");

    $pdo->exec("ALTER TABLE `agent_tokens`
        ADD COLUMN IF NOT EXISTS `auth_mode` ENUM('token','psk','mtls','hybrid') NOT NULL DEFAULT 'token',
        ADD COLUMN IF NOT EXISTS `expires_at` TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS `scope_site_pattern` VARCHAR(255) NULL,
        ADD COLUMN IF NOT EXISTS `scope_group_pattern` VARCHAR(255) NULL,
        ADD COLUMN IF NOT EXISTS `scope_device_pattern` VARCHAR(255) NULL,
        ADD COLUMN IF NOT EXISTS `rate_limit_per_minute` INT NOT NULL DEFAULT 120,
        ADD COLUMN IF NOT EXISTS `psk_hash` VARCHAR(255) NULL,
        ADD COLUMN IF NOT EXISTS `prev_psk_hash` VARCHAR(255) NULL,
        ADD COLUMN IF NOT EXISTS `prev_psk_grace_until` TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS `last_rotated_at` TIMESTAMP NULL");

    $done = true;
}

function securityAuditLog(PDO $pdo, string $eventType, string $severity, string $actorType = 'system', ?string $actorId = null, array $details = []): void {
    try {
        securityEnsureSchema($pdo);
        $stmt = $pdo->prepare('INSERT INTO security_audit_log (event_type, severity, actor_type, actor_id, source_ip, request_path, details_json) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $eventType,
            $severity,
            $actorType,
            $actorId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['REQUEST_URI'] ?? null,
            json_encode($details, JSON_UNESCAPED_SLASHES),
        ]);
    } catch (Throwable $e) {
        error_log('securityAuditLog error: ' . $e->getMessage());
    }
}

function securityIsHttpsRequest(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    $proto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $proto === 'https';
}

function enforceTlsForSensitiveRoutes(PDO $pdo): void {
    $mode = strtolower((string)(getenv('TLS_ENFORCEMENT_MODE') ?: 'warn'));
    if ($mode === 'off') {
        return;
    }

    $path = (string)($_SERVER['PHP_SELF'] ?? '');
    $isSensitive = str_contains($path, 'login.php') || str_contains($path, 'api.php');
    if (!$isSensitive || securityIsHttpsRequest()) {
        return;
    }

    securityAuditLog($pdo, 'tls.unencrypted_request', 'warning', 'anonymous', null, ['path' => $path]);
    if ($mode === 'strict') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'TLS is required for this endpoint']);
        exit;
    }
}

function recordAuthAttempt(PDO $pdo, string $identity, string $type, bool $success): void {
    securityEnsureSchema($pdo);
    $stmt = $pdo->prepare('INSERT INTO auth_attempts (identity, source_ip, attempt_type, was_success) VALUES (?, ?, ?, ?)');
    $stmt->execute([$identity, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $type, $success ? 1 : 0]);
}

function isLoginThrottled(PDO $pdo, string $identity): bool {
    securityEnsureSchema($pdo);
    $window = (int)(getenv('LOGIN_THROTTLE_WINDOW_SECONDS') ?: 900);
    $maxAttempts = (int)(getenv('LOGIN_THROTTLE_MAX_ATTEMPTS') ?: 8);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM auth_attempts WHERE identity = ? AND attempt_type = ? AND was_success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)');
    $stmt->execute([$identity, 'login', $window]);
    return ((int)$stmt->fetchColumn()) >= $maxAttempts;
}

function verifyTotpCode(string $secret, string $code): bool {
    $secret = strtoupper(preg_replace('/\s+/', '', $secret));
    if ($secret === '' || !preg_match('/^\d{6}$/', $code)) {
        return false;
    }

    $base32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    $buffer = 0;
    $bitsLeft = 0;
    foreach (str_split($secret) as $char) {
        $val = strpos($base32, $char);
        if ($val === false) continue;
        $buffer = ($buffer << 5) | $val;
        $bitsLeft += 5;
        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $binary .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }

    $timeStep = 30;
    $counter = floor(time() / $timeStep);
    foreach ([-1, 0, 1] as $drift) {
        $ctr = pack('N*', 0) . pack('N*', $counter + $drift);
        $hash = hash_hmac('sha1', $ctr, $binary, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
        $expected = str_pad((string)($truncated % 1000000), 6, '0', STR_PAD_LEFT);
        if (hash_equals($expected, $code)) {
            return true;
        }
    }

    return false;
}
