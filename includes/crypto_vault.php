<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * AES-256-GCM Zero-Trust Credential Crypto Vault
 */

class CryptoVault
{
    private static ?string $masterKey = null;

    /**
     * Derive or fetch the application master encryption key
     */
    private static function getMasterKey(): string
    {
        if (self::$masterKey !== null) {
            return self::$masterKey;
        }

        $envKey = getenv('AMPNM_VAULT_MASTER_KEY') ?: getenv('APP_LICENSE_KEY');
        if (!empty($envKey)) {
            self::$masterKey = hash('sha256', $envKey, true);
            return self::$masterKey;
        }

        // Fallback to machine-derived consistent salt if no key provided
        $fallbackSeed = php_uname() . (getenv('DB_PASSWORD') ?: 'ampnm_secure_vault_seed_2026');
        self::$masterKey = hash('sha256', $fallbackSeed, true);
        return self::$masterKey;
    }

    /**
     * Encrypt sensitive data using AES-256-GCM
     *
     * @param string $plaintext
     * @return string Base64 encoded payload: [IV:12B][TAG:16B][CIPHERTEXT]
     */
    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }

        $key = self::getMasterKey();
        $cipher = 'aes-256-gcm';
        $ivLen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivLen);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException('CryptoVault encryption failed.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt AES-256-GCM payload
     *
     * @param string $encryptedBase64
     * @return string Decrypted plaintext or original if not encrypted
     */
    public static function decrypt(string $encryptedBase64): string
    {
        if ($encryptedBase64 === '') {
            return '';
        }

        $raw = base64_decode($encryptedBase64, true);
        if ($raw === false || strlen($raw) < 28) {
            // Not a valid vault payload, return as plaintext for backward compatibility
            return $encryptedBase64;
        }

        $cipher = 'aes-256-gcm';
        $ivLen = 12;
        $tagLen = 16;

        $iv = substr($raw, 0, $ivLen);
        $tag = substr($raw, $ivLen, $tagLen);
        $ciphertext = substr($raw, $ivLen + $tagLen);

        $key = self::getMasterKey();
        $plaintext = openssl_decrypt(
            $ciphertext,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            // Decryption failed or was plain text
            return $encryptedBase64;
        }

        return $plaintext;
    }

    /**
     * Safely mask secrets for UI presentation (e.g., '••••••••ab12')
     */
    public static function mask(string $secret, int $visibleTail = 4): string
    {
        $len = strlen($secret);
        if ($len <= $visibleTail) {
            return str_repeat('•', max(4, $len));
        }
        return str_repeat('•', max(4, $len - $visibleTail)) . substr($secret, -$visibleTail);
    }
}
