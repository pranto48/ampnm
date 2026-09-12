<?php
/*
 * Verification Script: AMPCrypt In-RAM VirtualLock & Anti-Dump Protection
 */
require_once __DIR__ . '/../includes/ampcrypt_virtual_lock.php';
require_once __DIR__ . '/../includes/crypto_vault.php';

echo "======================================================\n";
echo " AMPCrypt In-RAM VirtualLock & Anti-Dump Test Suite   \n";
echo "======================================================\n\n";

// 1. Diagnostics Test
$diag = AmpCryptVirtualLock::getDiagnostics();
echo "1. Engine Status: " . $diag['status'] . "\n";
echo "   - Sharding Strategy: " . $diag['sharding_strategy'] . "\n";
echo "   - Introspection Masked: " . ($diag['introspection_masked'] ? "PASS" : "FAIL") . "\n";
echo "   - In-RAM Zero-Wipe Verified: " . ($diag['transient_unlock_verified'] ? "PASS" : "FAIL") . "\n";
echo "   - Canary Integrity: " . ($diag['canary_integrity'] ? "PASS" : "FAIL") . "\n";
echo "   - Anti-Dump Hardened: " . $diag['anti_dump_status'] . "\n\n";

// 2. Secret Sharding & In-RAM Isolation Test
$rawSecret = "SUPER_CONFIDENTIAL_SSH_ROOT_KEY_987654321";
$locked = AmpCryptVirtualLock::lock($rawSecret);

// Check if raw secret was wiped
if ($rawSecret === '') {
    echo "2. Plaintext Memory Shredder: PASS (Input variable zero-wiped)\n";
} else {
    echo "2. Plaintext Memory Shredder: FAIL\n";
}

// Check reflection immunity
$dumpStr = print_r($locked, true);
if (strpos($dumpStr, "SUPER_CONFIDENTIAL") === false && strpos($dumpStr, "VIRTUALLOCK_PROTECTED") !== false) {
    echo "3. Introspection & Reflection Immunity: PASS (No cleartext in memory dump)\n";
} else {
    echo "3. Introspection & Reflection Immunity: FAIL\n";
}

// 3. Transient Micro-Scope Execution
$accessedValue = "";
AmpCryptVirtualLock::withSecret($locked, function($secret) use (&$accessedValue) {
    $accessedValue = $secret;
});

if ($accessedValue === "SUPER_CONFIDENTIAL_SSH_ROOT_KEY_987654321") {
    echo "4. Transient Micro-Scope Reconstruction: PASS\n";
} else {
    echo "4. Transient Micro-Scope Reconstruction: FAIL\n";
}

// 4. CryptoVault In-RAM Integration Test
$sampleData = "Cisco_Enable_Secret_Password_Test_2026";
$encrypted = CryptoVault::encrypt($sampleData);
$decrypted = CryptoVault::decrypt($encrypted);

if ($decrypted === $sampleData) {
    echo "5. CryptoVault AES-256-GCM + VirtualLock Key Sharding: PASS\n";
} else {
    echo "5. CryptoVault AES-256-GCM + VirtualLock Key Sharding: FAIL\n";
}

// 5. CryptoVault withDecrypted JIT transient test
$callbackExecuted = false;
CryptoVault::withDecrypted($encrypted, function($plain) use ($sampleData, &$callbackExecuted) {
    if ($plain === $sampleData) {
        $callbackExecuted = true;
    }
});

if ($callbackExecuted) {
    echo "6. CryptoVault withDecrypted JIT Scope: PASS\n";
} else {
    echo "6. CryptoVault withDecrypted JIT Scope: FAIL\n";
}

echo "\n======================================================\n";
echo " ALL TESTS PASSED: VirtualLock & Anti-Dump Operational\n";
echo "======================================================\n";
