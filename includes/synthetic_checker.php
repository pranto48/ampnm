<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM Synthetic Performance Monitoring & Microsecond Waterfall Timing Engine
 */

class AMPNM_SyntheticChecker {

    public static function executeHttpCheck($url, $method = 'GET', $headers = [], $payload = '', $expectedCode = 200, $bodyAssertion = '', $timeout = 10) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AMPNM-SyntheticMonitor/1.0');

        if (!empty($headers)) {
            $formattedHeaders = is_array($headers) ? $headers : explode("\n", $headers);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_filter($formattedHeaders));
        }

        if (!empty($payload) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $start = microtime(true);
        $response = curl_exec($ch);
        $totalExec = (microtime(true) - $start) * 1000;

        $info = curl_getinfo($ch);
        $err = curl_error($ch);
        curl_close($ch);

        $dnsTime = ($info['namelookup_time'] ?? 0) * 1000;
        $tcpTime = max(0, (($info['connect_time'] ?? 0) - ($info['namelookup_time'] ?? 0)) * 1000);
        $tlsTime = max(0, (($info['appconnect_time'] ?? 0) - ($info['connect_time'] ?? 0)) * 1000);
        $ttfbTime = max(0, (($info['starttransfer_time'] ?? 0) - ($info['appconnect_time'] ?: ($info['connect_time'] ?? 0))) * 1000);
        $totalTime = ($info['total_time'] ?? 0) * 1000;
        $statusCode = (int)($info['http_code'] ?? 0);

        $passed = true;
        $errMsg = null;

        if ($err) {
            $passed = false;
            $errMsg = "cURL error: " . $err;
        } else if ($expectedCode > 0 && $statusCode !== $expectedCode) {
            $passed = false;
            $errMsg = "HTTP Status Code mismatch: expected {$expectedCode}, got {$statusCode}";
        } else if (!empty($bodyAssertion) && strpos($response, $bodyAssertion) === false) {
            $passed = false;
            $errMsg = "Body assertion failed: response did not contain '{$bodyAssertion}'";
        }

        return [
            'status' => $passed ? 'pass' : 'fail',
            'status_code' => $statusCode,
            'dns_time_ms' => round($dnsTime, 2),
            'tcp_time_ms' => round($tcpTime, 2),
            'tls_time_ms' => round($tlsTime, 2),
            'ttfb_time_ms' => round($ttfbTime, 2),
            'total_time_ms' => round($totalTime ?: $totalExec, 2),
            'error_message' => $errMsg,
            'response_snippet' => substr($response ?: '', 0, 500)
        ];
    }

    public static function executeDnsCheck($domain, $dnsServer = '1.1.1.1', $timeout = 5) {
        $start = microtime(true);
        $ip = @gethostbyname($domain);
        $durationMs = (microtime(true) - $start) * 1000;

        $passed = ($ip !== $domain && filter_var($ip, FILTER_VALIDATE_IP));
        return [
            'status' => $passed ? 'pass' : 'fail',
            'status_code' => $passed ? 200 : 0,
            'dns_time_ms' => round($durationMs, 2),
            'tcp_time_ms' => 0,
            'tls_time_ms' => 0,
            'ttfb_time_ms' => 0,
            'total_time_ms' => round($durationMs, 2),
            'error_message' => $passed ? null : "DNS Resolution failed for host '{$domain}'",
            'resolved_ip' => $ip
        ];
    }

    public static function executeTcpCheck($host, $port = 80, $timeout = 5) {
        $start = microtime(true);
        $fp = @fsockopen($host, (int)$port, $errno, $errstr, $timeout);
        $durationMs = (microtime(true) - $start) * 1000;

        $passed = is_resource($fp);
        if ($passed) {
            fclose($fp);
        }

        return [
            'status' => $passed ? 'pass' : 'fail',
            'status_code' => $passed ? 200 : 0,
            'dns_time_ms' => 0,
            'tcp_time_ms' => round($durationMs, 2),
            'tls_time_ms' => 0,
            'ttfb_time_ms' => 0,
            'total_time_ms' => round($durationMs, 2),
            'error_message' => $passed ? null : "TCP Socket connection to {$host}:{$port} failed: {$errstr} ({$errno})"
        ];
    }

    public static function runMonitor($pdo, $monitorId) {
        $stmt = $pdo->prepare("SELECT * FROM synthetic_monitors WHERE id = ?");
        $stmt->execute([$monitorId]);
        $mon = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mon) return ['success' => false, 'message' => 'Monitor not found'];

        $res = null;
        switch ($mon['type']) {
            case 'http_api':
                $res = self::executeHttpCheck(
                    $mon['target_url'],
                    $mon['http_method'],
                    $mon['headers'],
                    $mon['body_payload'],
                    (int)$mon['expected_status_code'],
                    $mon['body_assertion'],
                    (int)$mon['timeout_seconds']
                );
                break;
            case 'dns_query':
                $res = self::executeDnsCheck($mon['target_url'], '1.1.1.1', (int)$mon['timeout_seconds']);
                break;
            case 'tcp_port':
                $res = self::executeTcpCheck($mon['target_url'], $mon['port'] ?: 80, (int)$mon['timeout_seconds']);
                break;
            default:
                $res = self::executeHttpCheck($mon['target_url']);
                break;
        }

        // Record Run in synthetic_monitor_runs
        $runId = generateUuid();
        $stmtRun = $pdo->prepare("INSERT INTO synthetic_monitor_runs (id, monitor_id, status, status_code, dns_time_ms, tcp_time_ms, tls_time_ms, ttfb_time_ms, total_time_ms, error_message) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtRun->execute([
            $runId,
            $monitorId,
            $res['status'],
            $res['status_code'],
            $res['dns_time_ms'],
            $res['tcp_time_ms'],
            $res['tls_time_ms'],
            $res['ttfb_time_ms'],
            $res['total_time_ms'],
            $res['error_message']
        ]);

        // Update monitor status
        $statusState = $res['status'] === 'pass' ? 'operational' : 'failing';
        $stmtUpd = $pdo->prepare("UPDATE synthetic_monitors SET status = ?, last_response_time_ms = ?, last_checked_at = NOW() WHERE id = ?");
        $stmtUpd->execute([$statusState, $res['total_time_ms'], $monitorId]);

        return array_merge(['success' => true], $res);
    }
}
