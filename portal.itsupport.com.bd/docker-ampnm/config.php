<?php
// Database configuration using environment variables for Docker compatibility
// Forcing 127.0.0.1 as the host to resolve connection issues in the Docker environment.
define('DB_SERVER', '127.0.0.1');
define('DB_USERNAME', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'network_monitor');

// License System Configuration
// IMPORTANT: Changed fallback to the correct public HTTPS URL.
define('LICENSE_API_URL', getenv('LICENSE_API_URL') ?: 'https://portal.itsupport.com.bd/verify_license.php');
define('APP_LICENSE_KEY_ENV', getenv('APP_LICENSE_KEY') ?: ''); // This is the key from docker-compose.yml, might be empty
define('LICENSE_DATA_KEY', getenv('LICENSE_DATA_KEY') ?: ''); // Optional stronger key for encrypting license data at rest

// Create database connection
function getDbConnection() {
    static $pdo = null;

    // If a connection exists, check if it's still alive.
    if ($pdo !== null) {
        try {
            $pdo->query("SELECT 1");
        } catch (PDOException $e) {
            // Error code 2006 is "MySQL server has gone away".
            // If that's the case, nullify the connection to force a reconnect.
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 2006) {
                $pdo = null;
            } else {
                // For other errors, we can re-throw them.
                throw $e;
            }
        }
    }

    // If no connection exists (or it was lost), create a new one.
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
        } catch(PDOException $e) {
            // For a real application, you would log this error and show a generic message.
            // For this local tool, dying is acceptable to immediately see the problem.
            die("ERROR: Could not connect to the database. " . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Retrieves a setting from the app_settings table.
 * @param string $key The key of the setting to retrieve.
 * @return string|null The setting value, or null if not found.
 */
function getAppSetting($key) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM `app_settings` WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : null;
    } catch (PDOException $e) {
        error_log("Error getting app setting '$key': " . $e->getMessage());
        return null;
    }
}

/**
 * Builds a 32-byte encryption key for at-rest secrets. Uses LICENSE_DATA_KEY when provided
 * and falls back to a hashed combination of the transport encryption key and host identity.
 */
function getLicenseDataSecretKey() {
    // Prefer an explicitly-provided strong key from the environment
    $raw_key = LICENSE_DATA_KEY;

    if (empty($raw_key)) {
        // Derive a host-specific key to avoid a single static secret living in source code
        $pepper = getenv('LICENSE_DATA_PEPPER') ?: 'ampnm-license-data-pepper';
        $raw_key = hash('sha256', $pepper . '|' . php_uname('n'), true);
    } elseif (strlen($raw_key) < 32) {
        // Normalize shorter secrets to 32 bytes
        $raw_key = hash('sha256', $raw_key, true);
    }

    return substr($raw_key, 0, 32);
}

/**
 * Encrypts sensitive values for storage using AES-256-GCM with a random IV.
 */
function encryptSensitiveValue($plain_text) {
    if ($plain_text === null || $plain_text === '') {
        return $plain_text;
    }

    $key = getLicenseDataSecretKey();
    $iv_length = openssl_cipher_iv_length('aes-256-gcm');
    $iv = random_bytes($iv_length);
    $tag = '';
    $ciphertext = openssl_encrypt($plain_text, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    return base64_encode(json_encode([
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
        'ciphertext' => base64_encode($ciphertext),
        'v' => 1,
    ]));
}

/**
 * Decrypts values produced by encryptSensitiveValue. Falls back to returning the raw value
 * when it is not an encrypted payload so existing installs keep working.
 */
function decryptSensitiveValue($stored_value) {
    if ($stored_value === null || $stored_value === '') {
        return $stored_value;
    }

    $decoded = base64_decode($stored_value, true);
    if ($decoded === false) {
        return $stored_value; // Not encoded via encryptSensitiveValue
    }

    $payload = json_decode($decoded, true);
    if (!is_array($payload) || ($payload['v'] ?? null) !== 1) {
        return $stored_value; // Unknown format; treat as legacy plaintext
    }

    $key = getLicenseDataSecretKey();
    $iv = base64_decode($payload['iv'] ?? '', true);
    $tag = base64_decode($payload['tag'] ?? '', true);
    $cipher = base64_decode($payload['ciphertext'] ?? '', true);

    if ($iv === false || $tag === false || $cipher === false) {
        return $stored_value; // Malformed payload
    }

    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    return $plain === false ? $stored_value : $plain;
}

/**
 * Updates or inserts a setting into the app_settings table.
 * @param string $key The key of the setting.
 * @param string $value The new value for the setting.
 * @return bool True on success, false on failure.
 */
function updateAppSetting($key, $value) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("INSERT INTO `app_settings` (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP");
        $stmt->execute([$key, $value, $value]);
        return true;
    } catch (PDOException $e) {
        error_log("Error updating app setting '$key': " . $e->getMessage());
        return false;
    }
}

/**
 * Helper function to get the application's license key.
 * @return string|null The license key, or null if not set.
 */
function getAppLicenseKey() {
    $value = getAppSetting('app_license_key');
    return decryptSensitiveValue($value);
}

/**
 * Helper function to set the application's license key.
 * @param string $key The license key to set.
 * @return bool True on success, false on failure.
 */
function setAppLicenseKey($key) {
    $encrypted = encryptSensitiveValue($key);
    return updateAppSetting('app_license_key', $encrypted);
}

/**
 * Helper function to get the application's unique installation ID.
 * @return string|null The installation ID, or null if not set.
 */
function getInstallationId() {
    return getAppSetting('installation_id');
}