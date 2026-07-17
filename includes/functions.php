<?php
require_once __DIR__ . '/../config.php';

function ensureCsrfTokenInSession(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function isValidCsrfToken(?string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    $provided = (string) ($token ?? '');
    if ($provided === '') {
        return false;
    }

    return hash_equals($sessionToken, $provided);
}

// Function to check a TCP port on a host
function checkPortStatus($host, $port, $timeout = 1) {
    $startTime = microtime(true);
    // The '@' suppresses warnings on connection failure, which we handle ourselves.
    $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $endTime = microtime(true);

    if ($socket) {
        fclose($socket);
        return [
            'success' => true,
            'time' => round(($endTime - $startTime) * 1000, 2), // time in ms
            'output' => "Successfully connected to $host on port $port."
        ];
    } else {
        return [
            'success' => false,
            'time' => 0,
            'output' => "Connection failed: $errstr (Error no: $errno)"
        ];
    }
}

// Function to execute ping command more efficiently
function executePing($host, $count = 4) {
    // Basic validation and sanitization for the host
    if (empty($host) || !preg_match('/^[a-zA-Z0-9\.\-]+$/', $host)) {
        return ['output' => 'Invalid host provided.', 'return_code' => -1, 'success' => false];
    }
    
    // Escape the host to prevent command injection
    $escaped_host = escapeshellarg($host);
    
    // Determine the correct ping command based on the OS, with timeouts
    if (stristr(PHP_OS, 'WIN')) {
        // Windows: -n for count, -w for timeout in ms
        $command = "ping -n $count -w 1000 $escaped_host";
    } else {
        // Linux/Mac: -c for count, -W for timeout in seconds
        $command = "ping -c $count -W 1 $escaped_host";
    }
    
    $output_array = [];
    $return_code = -1;
    
    // Use exec to get both output and return code in one call
    @exec($command . ' 2>&1', $output_array, $return_code);
    
    $output = implode("\n", $output_array);
    
    // Determine success more reliably. Return code 0 is good, but we also check for 100% packet loss.
    $success = ($return_code === 0 && strpos($output, '100% packet loss') === false && strpos($output, 'Lost = ' . $count) === false);

    return [
        'output' => $output,
        'return_code' => $return_code,
        'success' => $success
    ];
}

// Function to parse ping output from different OS
function parsePingOutput($output) {
    $packetLoss = 100;
    $avgTime = 0;
    $minTime = 0;
    $maxTime = 0;
    $ttl = null;
    
    // Regex for Windows
    if (preg_match('/Lost = \d+ \((\d+)% loss\)/', $output, $matches)) {
        $packetLoss = (int)$matches[1];
    }
    if (preg_match('/Minimum = (\d+)ms, Maximum = (\d+)ms, Average = (\d+)ms/', $output, $matches)) {
        $minTime = (float)$matches[1];
        $maxTime = (float)$matches[2];
        $avgTime = (float)$matches[3];
    }
    if (preg_match('/TTL=(\d+)/', $output, $matches)) {
        $ttl = (int)$matches[1];
    }
    
    // Regex for Linux/Mac
    if (preg_match('/(\d+)% packet loss/', $output, $matches)) {
        $packetLoss = (int)$matches[1];
    }
    if (preg_match('/rtt min\/avg\/max\/mdev = ([\d.]+)\/([\d.]+)\/([\d.]+)\/([\d.]+) ms/', $output, $matches)) {
        $minTime = (float)$matches[1];
        $avgTime = (float)$matches[2];
        $maxTime = (float)$matches[3];
    }
    if (preg_match('/ttl=(\d+)/', $output, $matches)) {
        $ttl = (int)$matches[1];
    }
    
    return [
        'packet_loss' => $packetLoss,
        'avg_time' => $avgTime,
        'min_time' => $minTime,
        'max_time' => $maxTime,
        'ttl' => $ttl
    ];
}

// Function to save a ping result to the database
function savePingResult($pdo, $host, $pingResult) {
    $pingOutput = isset($pingResult['output']) ? $pingResult['output'] : '';
    $parsed = parsePingOutput($pingOutput);

    // Normalize values to satisfy NOT NULL schema constraints and avoid type warnings
    $packetLoss = isset($parsed['packet_loss']) ? (int)$parsed['packet_loss'] : 100;
    $avgTime = isset($parsed['avg_time']) ? (float)$parsed['avg_time'] : 0;
    $minTime = isset($parsed['min_time']) ? (float)$parsed['min_time'] : 0;
    $maxTime = isset($parsed['max_time']) ? (float)$parsed['max_time'] : 0;
    $successFlag = !empty($pingResult['success']) ? 1 : 0;

    $sql = "INSERT INTO ping_results (host, packet_loss, avg_time, min_time, max_time, success, output) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $host,
        $packetLoss,
        $avgTime,
        $minTime,
        $maxTime,
        $successFlag,
        $pingOutput
    ]);
}

// Function to ping a single device and return structured data
function pingDevice($ip) {
    $pingResult = executePing($ip, 1); // Ping once for speed
    $parsedResult = parsePingOutput($pingResult['output']);
    $alive = $pingResult['success'];

    return [
        'ip' => $ip,
        'alive' => $alive,
        'time' => $alive ? $parsedResult['avg_time'] : null,
        'timestamp' => date('c'), // ISO 8601 format
        'error' => !$alive ? 'Host unreachable or timed out' : null
    ];
}

// Function to scan the network for devices using nmap
function scanNetwork($subnet) {
    // NOTE: This function requires 'nmap' to be installed on the server.
    // The web server user (e.g., www-data) may need permissions to run it.
    if (empty($subnet) || !preg_match('/^[a-zA-Z0-9\.\/]+$/', $subnet)) {
        // Default to a common local subnet if none is provided or if input is invalid
        $subnet = '192.168.1.0/24';
    }

    // Escape the subnet to prevent command injection
    $escaped_subnet = escapeshellarg($subnet);
    
    // Use nmap for a discovery scan (-sn: ping scan, -oG -: greppable output)
    $command = "nmap -sn $escaped_subnet -oG -";
    $output = @shell_exec($command);

    if (empty($output)) {
        return []; // nmap might not be installed or failed to run
    }

    $results = [];
    $lines = explode("\n", $output);
    foreach ($lines as $line) {
        if (strpos($line, 'Host:') === 0 && strpos($line, 'Status: Up') !== false) {
            $parts = preg_split('/\s+/', $line);
            $ip = $parts[1];
            $hostname = (isset($parts[2]) && $parts[2] !== '') ? trim($parts[2], '()') : null;
            
            $results[] = [
                'ip' => $ip,
                'hostname' => $hostname,
                'mac' => null, // nmap -sn doesn't always provide MAC, a privileged scan is needed
                'vendor' => null,
                'alive' => true
            ];
        }
    }
    return $results;
}

// Function to check if host is reachable via HTTP
function checkHttpConnectivity($host) {
    if (empty($host) || filter_var($host, FILTER_VALIDATE_IP) === false) {
        return ['success' => false, 'http_code' => 0, 'error' => 'Invalid IP address'];
    }
    $url = "http://$host";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Reduced timeout for faster checks
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 400),
        'http_code' => $httpCode,
        'error' => $error
    ];
}

// Function to execute multiple pings concurrently
function pingMultiple(array $ips, $count = 2, $timeout = 1) {
    $processes = [];
    $results = [];

    // Initialize all results as offline
    foreach ($ips as $ip) {
        if (empty($ip)) continue;
        $results[$ip] = [
            'success' => false,
            'output' => 'Ping timed out or failed.',
            'avg_time' => 0,
            'packet_loss' => 100,
            'ttl' => null
        ];
    }

    // Launch processes in parallel
    foreach ($ips as $ip) {
        if (empty($ip) || !preg_match('/^[a-zA-Z0-9\.\:-]+$/', $ip)) {
            continue;
        }
        $escaped_host = escapeshellarg($ip);
        if (stristr(PHP_OS, 'WIN')) {
            $cmd = "ping -n $count -w " . ($timeout * 1000) . " $escaped_host";
        } else {
            $cmd = "ping -c $count -W $timeout $escaped_host";
        }

        $descriptorspec = [
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"]   // stderr
        ];

        $proc = @proc_open($cmd, $descriptorspec, $proc_pipes);
        if (is_resource($proc)) {
            @stream_set_blocking($proc_pipes[1], false);
            @stream_set_blocking($proc_pipes[2], false);
            $processes[$ip] = [
                'proc' => $proc,
                'pipes' => $proc_pipes,
                'output' => '',
                'start_time' => microtime(true)
            ];
        }
    }

    // Monitor processes with a timeout loop
    $startTime = microtime(true);
    $running = true;
    while ($running && (microtime(true) - $startTime) < ($timeout + 1.5)) {
        $running = false;
        foreach ($processes as $ip => &$procInfo) {
            $status = proc_get_status($procInfo['proc']);
            
            // Read output
            $out = fread($procInfo['pipes'][1], 8192);
            if ($out !== false && $out !== '') {
                $procInfo['output'] .= $out;
            }
            $err = fread($procInfo['pipes'][2], 8192);
            if ($err !== false && $err !== '') {
                $procInfo['output'] .= $err;
            }

            if ($status['running']) {
                $running = true;
            }
        }
        usleep(15000); // Sleep 15ms
    }

    // Clean up and parse results
    foreach ($processes as $ip => &$procInfo) {
        // Read any remaining output
        $out = @stream_get_contents($procInfo['pipes'][1]);
        if ($out !== false && $out !== '') {
            $procInfo['output'] .= $out;
        }
        $err = @stream_get_contents($procInfo['pipes'][2]);
        if ($err !== false && $err !== '') {
            $procInfo['output'] .= $err;
        }

        @fclose($procInfo['pipes'][1]);
        @fclose($procInfo['pipes'][2]);
        @proc_close($procInfo['proc']);

        // Parse result
        $output = $procInfo['output'];
        $parsed = parsePingOutput($output);
        
        // Success condition: packet loss < 100
        $loss = isset($parsed['packet_loss']) ? $parsed['packet_loss'] : 100;
        $success = ($loss < 100);

        $results[$ip] = [
            'success' => $success,
            'output' => $output,
            'avg_time' => $parsed['avg_time'] ?? 0,
            'packet_loss' => $loss,
            'ttl' => $parsed['ttl'] ?? null
        ];
    }

    return $results;
}

/**
 * Generates a Font Awesome icon as an SVG data URL.
 * This version assumes Font Awesome CSS is already loaded in the browser.
 *
 * @param string $iconCode The Font Awesome Unicode character (e.g., '\uf233' for server).
 * @param int $size The desired size of the icon in pixels.
 * @param string $color The color of the icon (e.g., '#ffffff').
 * @return string The SVG data URL.
 */
function generateFaSvgDataUrl($iconCode, $size, $color) {
    // Ensure the icon code is properly escaped for XML
    $escapedIconCode = htmlspecialchars($iconCode);

    // Font Awesome 6 Free Solid font family
    $fontFamily = 'Font Awesome 6 Free';
    $fontWeight = '900'; // Solid icons

    // Create SVG content, assuming the font is already loaded by the browser's CSS
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}">
    <text x="50%" y="50%" style="font-family: '{$fontFamily}'; font-weight: {$fontWeight}; font-size: {$size}px; fill: {$color}; text-anchor: middle; dominant-baseline: central;">{$escapedIconCode}</text>
</svg>
SVG;

    // Encode SVG for data URL
    $encodedSvg = rawurlencode($svg);
    return "data:image/svg+xml;charset=utf-8,{$encodedSvg}";
}
