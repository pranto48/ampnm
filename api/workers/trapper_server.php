#!/usr/bin/env php
<?php

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/agent_metrics_compat.php';

// Prevent execution timeouts for this background script
set_time_limit(0);

$server = stream_socket_server("tcp://0.0.0.0:10051", $errno, $errstr);
if (!$server) {
    error_log("Trapper Server Error: $errstr ($errno)");
    exit(1);
}

echo "Trapper server listening on tcp://0.0.0.0:10051...\n";

while ($conn = stream_socket_accept($server, -1)) {
    // Set 5 seconds read/write timeout
    stream_set_timeout($conn, 5);

    try {
        $pdo = getDbConnection();

        // 1. Read header (5 bytes)
        $header = fread($conn, 5);
        if ($header !== "ZBXD\x01") {
            // Invalid protocol header
            fwrite($conn, "ERROR: Invalid protocol header\n");
            fclose($conn);
            continue;
        }

        // 2. Read length (8 bytes little-endian uint64)
        $lenBytes = fread($conn, 8);
        if (strlen($lenBytes) < 8) {
            fwrite($conn, "ERROR: Failed to read packet length\n");
            fclose($conn);
            continue;
        }

        $unpack = unpack('P', $lenBytes);
        $dataLength = $unpack[1] ?? 0;

        if ($dataLength <= 0 || $dataLength > 10 * 1024 * 1024) { // limit payload size to 10MB to avoid OOM
            fwrite($conn, "ERROR: Invalid packet length\n");
            fclose($conn);
            continue;
        }

        // 3. Read body data
        $data = '';
        $remaining = $dataLength;
        $startTime = time();
        while ($remaining > 0 && (time() - $startTime) < 5) {
            $chunk = fread($conn, min($remaining, 8192));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        if ($remaining > 0) {
            fwrite($conn, "ERROR: Timeout or incomplete payload\n");
            fclose($conn);
            continue;
        }

        // 4. Decode JSON
        $payload = json_decode($data, true);
        if (!is_array($payload)) {
            fwrite($conn, "ERROR: Invalid JSON payload\n");
            fclose($conn);
            continue;
        }

        $token = trim((string)($payload['agent_token'] ?? ''));
        $tokenInfo = agentCompatValidateToken($pdo, $token);
        if (!$tokenInfo) {
            fwrite($conn, "ERROR: Invalid or missing agent token\n");
            fclose($conn);
            continue;
        }

        $metrics = $payload['metrics'] ?? [];
        if (!is_array($metrics)) {
            fwrite($conn, "ERROR: Invalid metrics object\n");
            fclose($conn);
            continue;
        }

        $saved = agentCompatSaveMetrics($pdo, $metrics, (int)$tokenInfo['user_id']);
        if (empty($saved['ok'])) {
            $err = $saved['error'] ?? 'Unknown save error';
            fwrite($conn, "ERROR: $err\n");
            fclose($conn);
            continue;
        }

        // Send a Zabbix-like JSON response packet
        $response = json_encode(['response' => 'success', 'info' => 'Processed successfully']);
        $respLen = strlen($response);
        $respPacket = "ZBXD\x01" . pack('P', $respLen) . $response;
        fwrite($conn, $respPacket);

    } catch (Throwable $e) {
        error_log("Trapper Server client processing error: " . $e->getMessage());
        @fwrite($conn, "ERROR: Internal server error\n");
    }

    fclose($conn);
}
