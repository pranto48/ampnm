<?php
/**
 * Cloud Monitor Bridge
 * 
 * This script runs as a continuous loop inside the Docker container.
 * It polls the Supabase cloud for devices that need monitoring,
 * performs real ICMP ping or TCP port checks locally, then pushes
 * the results back to the cloud.
 * 
 * Usage:
 *   php cloud_monitor.php
 * 
 * Environment variables (set in docker-compose.yml or .env):
 *   CLOUD_SYNC_URL    - The Supabase edge function URL for docker-sync
 *   CLOUD_ANON_KEY    - The Supabase anon key for authentication
 *   CLOUD_POLL_INTERVAL - How often to check for pending pings (default: 15 seconds)
 * 
 * This script uses the same ICMP ping and TCP port check functions
 * as the Docker AMPNM app, providing true network-level monitoring
 * for LAN devices that the cloud cannot reach directly.
 */

require_once __DIR__ . '/includes/functions.php';

// --- Configuration ---
$syncUrl = getenv('CLOUD_SYNC_URL');
$anonKey = getenv('CLOUD_ANON_KEY');
$pollInterval = (int)(getenv('CLOUD_POLL_INTERVAL') ?: 15);

if (!$syncUrl || !$anonKey) {
    echo "[Cloud Monitor] ERROR: CLOUD_SYNC_URL and CLOUD_ANON_KEY environment variables are required.\n";
    echo "[Cloud Monitor] Set them in your docker-compose.yml:\n";
    echo "  environment:\n";
    echo "    CLOUD_SYNC_URL: https://<project-ref>.supabase.co/functions/v1/docker-sync\n";
    echo "    CLOUD_ANON_KEY: <your-anon-key>\n";
    exit(1);
}

echo "[Cloud Monitor] Starting cloud monitoring bridge...\n";
echo "[Cloud Monitor] Sync URL: {$syncUrl}\n";
echo "[Cloud Monitor] Poll interval: {$pollInterval}s\n";
echo "---\n";

// --- Helper: Make HTTP request to cloud ---
function cloudRequest(string $url, string $anonKey, string $method = 'GET', ?array $body = null): ?array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $anonKey,
        'Authorization: Bearer ' . $anonKey,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "[Cloud Monitor] HTTP error: {$error}\n";
        return null;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        echo "[Cloud Monitor] HTTP {$httpCode}: {$response}\n";
        return null;
    }

    return json_decode($response, true);
}

// --- Helper: Determine status based on thresholds ---
function determineStatus(array $device, bool $success, float $latencyMs, float $packetLoss): string {
    if (!$success) return 'offline';

    $warnLatency = $device['warning_latency_threshold'] ?? null;
    $critLatency = $device['critical_latency_threshold'] ?? null;
    $warnLoss = $device['warning_packetloss_threshold'] ?? null;
    $critLoss = $device['critical_packetloss_threshold'] ?? null;

    if ($critLatency && $latencyMs >= $critLatency) return 'critical';
    if ($critLoss && $packetLoss >= $critLoss) return 'critical';
    if ($warnLatency && $latencyMs >= $warnLatency) return 'warning';
    if ($warnLoss && $packetLoss >= $warnLoss) return 'warning';

    return 'online';
}

// --- Main monitoring loop ---
while (true) {
    try {
        // 1. Pull devices that need pinging
        $pullUrl = $syncUrl . '?action=pull_pending_pings';
        $response = cloudRequest($pullUrl, $anonKey);

        if (!$response || !$response['success']) {
            echo "[Cloud Monitor] Failed to pull pending pings. Retrying in {$pollInterval}s...\n";
            sleep($pollInterval);
            continue;
        }

        $devices = $response['devices'] ?? [];

        if (empty($devices)) {
            // No devices need checking right now
            sleep($pollInterval);
            continue;
        }

        echo "[Cloud Monitor] " . count($devices) . " device(s) due for check...\n";

        $statusUpdates = [];
        $pingResults = [];

        foreach ($devices as $device) {
            $ip = $device['ip'] ?? null;
            if (!$ip) continue;

            $monitorMethod = $device['monitor_method'] ?? 'ping';
            $checkPort = $device['check_port'] ?? null;
            $latencyMs = 0;
            $packetLoss = 100;
            $success = false;

            // 2. Perform the actual check
            if ($monitorMethod === 'port' && $checkPort) {
                // TCP port check
                $portResult = checkPortStatus($ip, $checkPort);
                $success = $portResult['success'];
                $latencyMs = $portResult['time'] ?? 0;
                $packetLoss = $success ? 0 : 100;
                echo "  [{$device['name']}] Port {$checkPort} on {$ip}: " . ($success ? "OPEN ({$latencyMs}ms)" : "CLOSED") . "\n";
            } else {
                // ICMP ping
                $pingResult = executePing($ip, 2); // 2 pings for accuracy
                $parsed = parsePingOutput($pingResult['output']);
                $success = $pingResult['success'];
                $latencyMs = $parsed['avg_time'] ?? 0;
                $packetLoss = $parsed['packet_loss'] ?? 100;
                echo "  [{$device['name']}] Ping {$ip}: " . ($success ? "OK ({$latencyMs}ms, {$packetLoss}% loss)" : "FAIL") . "\n";
            }

            // 3. Determine status based on thresholds
            $status = determineStatus($device, $success, $latencyMs, $packetLoss);

            $statusUpdates[] = [
                'device_id' => $device['id'],
                'status' => $status,
                'latency_ms' => $success ? round($latencyMs, 2) : null,
                'packet_loss' => $packetLoss,
            ];

            $pingResults[] = [
                'device_id' => $device['id'],
                'status' => $status,
                'latency_ms' => $success ? round($latencyMs, 2) : null,
                'packet_loss' => $packetLoss,
            ];
        }

        // 4. Push status updates back to cloud
        if (!empty($statusUpdates)) {
            $pushStatusUrl = $syncUrl . '?action=push_status';
            $pushResult = cloudRequest($pushStatusUrl, $anonKey, 'POST', ['updates' => $statusUpdates]);
            if ($pushResult && $pushResult['success']) {
                echo "[Cloud Monitor] Pushed " . count($statusUpdates) . " status update(s).\n";
            } else {
                echo "[Cloud Monitor] Failed to push status updates.\n";
            }
        }

        // 5. Push ping results for history
        if (!empty($pingResults)) {
            $pushPingUrl = $syncUrl . '?action=push_ping_results';
            $pushResult = cloudRequest($pushPingUrl, $anonKey, 'POST', ['results' => $pingResults]);
            if ($pushResult && $pushResult['success']) {
                echo "[Cloud Monitor] Pushed " . count($pingResults) . " ping result(s).\n";
            } else {
                echo "[Cloud Monitor] Failed to push ping results.\n";
            }
        }

        echo "---\n";

    } catch (Exception $e) {
        echo "[Cloud Monitor] Error: " . $e->getMessage() . "\n";
    }

    sleep($pollInterval);
}
