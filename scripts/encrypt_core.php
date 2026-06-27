<?php
$key = 'ITSupportBD_CoreShield_2026';
$cipher = 'aes-256-cbc';

$original_file = __DIR__ . '/../includes/auth_check.php';
$encrypted_file = __DIR__ . '/../includes/auth_check.enc';

if (!file_exists($original_file)) {
    die("Error: Original file not found at {$original_file}\n");
}

$code = file_get_contents($original_file);

// Remove the opening <?php tag from the code if present to allow clean eval
if (strpos($code, '<?php') === 0) {
    $code = substr($code, 5);
}

$iv_len = openssl_cipher_iv_length($cipher);
$iv = openssl_random_pseudo_bytes($iv_len);

$key_buf = hash('sha256', $key, true);
$ciphertext = openssl_encrypt($code, $cipher, $key_buf, OPENSSL_RAW_DATA, $iv);

if ($ciphertext === false) {
    die("Error: Encryption failed.\n");
}

$output = base64_encode($iv . $ciphertext);
file_put_contents($encrypted_file, $output);

echo "Success: Encrypted auth_check.php to auth_check.enc\n";
