<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../api/handlers/device_handler.php';

$pdo = getDbConnection();

// Test Device Simulation
$mockDevice = [
    'id' => 999999,
    'name' => 'Test Simulation Device',
    'ip' => '192.0.2.1', // Unroutable TEST-NET IP for simulated failure
    'ping_interval' => 20,
    'first_failed_at' => null,
    'warning_latency_threshold' => 200,
    'warning_packetloss_threshold' => 25,
    'critical_latency_threshold' => 450,
    'critical_packetloss_threshold' => 80
];

echo "=== Testing Threshold Logic ===\n";

// Test 1: First Failure (Simulated 20s interval failure)
$details = '';
$first_failed_at = null;
$fakePingFail = ['output' => '100% packet loss', 'success' => false, 'return_code' => 1];
$parsedResult = ['packet_loss' => 100, 'avg_time' => 0, 'ttl' => null];

$status1 = getStatusFromPingResult($mockDevice, $fakePingFail, $parsedResult, $details, $first_failed_at);
echo "1. First failure test:\n";
echo "   Status: {$status1} (Expected: critical)\n";
echo "   Details: {$details}\n";
echo "   First Failed At recorded: {$first_failed_at}\n";

// Test 2: Failure continuing past 30 seconds
$mockDevice['first_failed_at'] = date('Y-m-d H:i:s', time() - 35); // 35 seconds ago
$status2 = getStatusFromPingResult($mockDevice, $fakePingFail, $parsedResult, $details, $first_failed_at);
echo "2. 35-second failure test:\n";
echo "   Status: {$status2} (Expected: offline)\n";
echo "   Details: {$details}\n";

// Test 3: Recovery
$fakePingSuccess = ['output' => 'bytes=32 time=15ms TTL=64', 'success' => true, 'return_code' => 0];
$parsedSuccess = ['packet_loss' => 0, 'avg_time' => 15, 'ttl' => 64];
$status3 = getStatusFromPingResult($mockDevice, $fakePingSuccess, $parsedSuccess, $details, $first_failed_at);
echo "3. Recovery test:\n";
echo "   Status: {$status3} (Expected: online)\n";
echo "   Details: {$details}\n";
echo "   First Failed At: " . var_export($first_failed_at, true) . " (Expected: NULL)\n";

$pass = ($status1 === 'critical' && $status2 === 'offline' && $status3 === 'online' && $first_failed_at === null);
echo "=== Overall Result: " . ($pass ? "ALL TESTS PASSED ✅" : "TESTS FAILED ❌") . " ===\n";
