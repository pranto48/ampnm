<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * Advanced Multi-Threaded Subnet & IP Discovery Engine
 */

class AdvancedScanner {
    // Top Hardware OUI Vendor Database Prefixes
    private static array $ouiDatabase = [
        '00:0C:29' => 'VMware', '00:50:56' => 'VMware', '00:05:69' => 'VMware',
        'B8:27:EB' => 'Raspberry Pi', 'DC:A6:32' => 'Raspberry Pi', 'E4:5F:01' => 'Raspberry Pi',
        '00:15:5D' => 'Microsoft Hyper-V',
        '00:1A:E8' => 'MikroTik', '2C:C8:1B' => 'MikroTik', '48:8F:5A' => 'MikroTik', '64:D1:54' => 'MikroTik', 'CC:2D:E0' => 'MikroTik', 'D4:CA:6D' => 'MikroTik', 'E4:8D:8C' => 'MikroTik',
        '00:27:22' => 'Ubiquiti', '24:A4:3C' => 'Ubiquiti', '78:8A:20' => 'Ubiquiti', 'F0:9F:C2' => 'Ubiquiti', 'AC:8B:A9' => 'Ubiquiti',
        '00:1E:13' => 'Cisco', '00:26:0B' => 'Cisco', '00:27:0D' => 'Cisco', '40:55:39' => 'Cisco', '70:69:79' => 'Cisco', 'F4:4E:05' => 'Cisco',
        '00:0E:8E' => 'TP-Link', '14:CC:20' => 'TP-Link', '50:C7:BF' => 'TP-Link', 'E8:48:B8' => 'TP-Link', 'F4:F2:6D' => 'TP-Link',
        '00:11:32' => 'Synology', '00:11:33' => 'Synology', '90:09:D0' => 'Synology',
        '00:08:9B' => 'QNAP', '24:5E:BE' => 'QNAP',
        '00:18:AE' => 'Dahua IP Cam', '3C:EF:8C' => 'Dahua IP Cam', 'E0:50:8B' => 'Dahua IP Cam',
        '00:12:12' => 'Hikvision', '44:19:B6' => 'Hikvision', 'BC:5E:58' => 'Hikvision', 'C4:2F:90' => 'Hikvision',
        '00:1E:C9' => 'Dell', '18:03:73' => 'Dell', '70:B5:E8' => 'Dell', 'B8:2A:72' => 'Dell',
        '00:25:B3' => 'HP', '3C:D9:2B' => 'HP', '70:5A:0F' => 'HP', '9C:B6:54' => 'HP',
        '00:04:F2' => 'Polycom', '00:15:65' => 'Yealink',
        '00:17:88' => 'Philips Hue',
        '00:1D:BA' => 'Apple', '70:DE:E2' => 'Apple', 'AC:DE:48' => 'Apple', 'F4:5C:89' => 'Apple'
    ];

    /**
     * Parse CIDR or IP Range into array of IPs
     */
    public static function parseTargets(string $input): array {
        $input = trim($input);
        $ips = [];

        if (str_contains($input, '/')) {
            // CIDR notation (e.g. 192.168.1.0/24)
            [$net, $mask] = explode('/', $input, 2);
            $mask = (int)$mask;
            if ($mask < 16) $mask = 16; // Limit to maximum 65536 IPs for safety
            if ($mask > 32) $mask = 32;

            $ipLong = ip2long($net);
            $maskLong = -1 << (32 - $mask);
            $netLong = $ipLong & $maskLong;
            $broadcastLong = $netLong | (~$maskLong);

            // Skip network & broadcast addresses for /24 and smaller
            $start = ($mask >= 31) ? $netLong : $netLong + 1;
            $end = ($mask >= 31) ? $broadcastLong : $broadcastLong - 1;

            $count = 0;
            for ($i = $start; $i <= $end && $count < 512; $i++, $count++) {
                $ips[] = long2ip($i);
            }
        } elseif (str_contains($input, '-')) {
            // Range notation (e.g. 192.168.1.1-192.168.1.50 or 192.168.1.1-50)
            [$startStr, $endStr] = explode('-', $input, 2);
            $startStr = trim($startStr);
            $endStr = trim($endStr);

            if (!str_contains($endStr, '.')) {
                // Short format: 192.168.1.1-50
                $parts = explode('.', $startStr);
                array_pop($parts);
                $endStr = implode('.', $parts) . '.' . $endStr;
            }

            $startLong = ip2long($startStr);
            $endLong = ip2long($endStr);

            if ($startLong && $endLong && $startLong <= $endLong) {
                $count = 0;
                for ($i = $startLong; $i <= $endLong && $count < 512; $i++, $count++) {
                    $ips[] = long2ip($i);
                }
            }
        } else {
            // Single IP
            if (filter_var($input, FILTER_VALIDATE_IP)) {
                $ips[] = $input;
            }
        }

        return $ips;
    }

    /**
     * Discover live hosts using fast non-blocking concurrent socket sweep
     */
    public static function sweep(array $ips, int $timeoutMs = 300): array {
        if (empty($ips)) return [];

        $arpMap = self::getArpTable();
        $liveHosts = [];
        $probePorts = [80, 443, 22, 3389, 445, 8291, 554, 8000, 161, 53];

        // Process in chunks of 40 IPs concurrently
        $chunks = array_chunk($ips, 40);

        foreach ($chunks as $chunk) {
            $sockets = [];
            $meta = [];

            foreach ($chunk as $ip) {
                // Open non-blocking socket to common port 80 / 443 / 22 / 445
                foreach ([80, 22, 445, 443] as $port) {
                    $sock = @stream_socket_client("tcp://{$ip}:{$port}", $errno, $errstr, 0.05, STREAM_CLIENT_ASYNC_CONNECT);
                    if ($sock) {
                        stream_set_blocking($sock, false);
                        $sockets[] = $sock;
                        $meta[(int)$sock] = ['ip' => $ip, 'port' => $port];
                    }
                }
            }

            // Wait for connections to establish
            if (!empty($sockets)) {
                $write = $sockets;
                $read = $except = null;
                $sec = 0;
                $usec = $timeoutMs * 1000;

                if (@stream_select($read, $write, $except, $sec, $usec) > 0) {
                    foreach ($write as $sock) {
                        $info = $meta[(int)$sock] ?? null;
                        if ($info && !in_array($info['ip'], $liveHosts)) {
                            $liveHosts[] = $info['ip'];
                        }
                    }
                }

                foreach ($sockets as $s) {
                    @fclose($s);
                }
            }

            // Fast fallback: If host is present in local ARP table, consider it live
            foreach ($chunk as $ip) {
                if (!in_array($ip, $liveHosts) && isset($arpMap[$ip])) {
                    $liveHosts[] = $ip;
                }
            }
        }

        // If shell ICMP/ping is available and very few hosts were found, do a quick ping sweep fallback
        if (count($liveHosts) < 2 && count($ips) <= 64) {
            foreach ($ips as $ip) {
                if (!in_array($ip, $liveHosts) && self::quickPing($ip)) {
                    $liveHosts[] = $ip;
                }
            }
        }

        // Deep inspect each live host (detect open ports, hostname, MAC, vendor, device type)
        $results = [];
        foreach ($liveHosts as $ip) {
            $mac = $arpMap[$ip]['mac'] ?? null;
            $vendor = $mac ? self::resolveVendor($mac) : null;
            $openPorts = self::probePorts($ip, $probePorts);
            $hostname = self::resolveHostname($ip);
            $devType = self::classifyDevice($openPorts, $vendor, $hostname);

            $results[] = [
                'ip' => $ip,
                'hostname' => $hostname,
                'mac' => $mac,
                'vendor' => $vendor,
                'open_ports' => $openPorts,
                'device_type' => $devType['type'],
                'device_name' => $hostname ?: ($vendor ? "{$vendor} Device" : "Host {$ip}"),
                'icon' => $devType['icon'],
                'alive' => true
            ];
        }

        return $results;
    }

    /**
     * Fast single host ping
     */
    private static function quickPing(string $ip): bool {
        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $cmd = $isWin ? "ping -n 1 -w 150 " . escapeshellarg($ip) : "ping -c 1 -W 1 " . escapeshellarg($ip) . " 2>&1";
        $out = @shell_exec($cmd);
        return $out && (str_contains($out, 'TTL=') || str_contains($out, 'ttl=') || str_contains($out, 'bytes from'));
    }

    /**
     * Read and parse local ARP cache
     */
    public static function getArpTable(): array {
        $arpMap = [];
        $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($isWin) {
            $output = @shell_exec("arp -a");
            if ($output) {
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s+([0-9a-fA-F\-]{17})/i', $line, $m)) {
                        $ip = $m[1];
                        $mac = strtoupper(str_replace('-', ':', $m[2]));
                        if ($mac !== 'FF:FF:FF:FF:FF:FF') {
                            $arpMap[$ip] = ['mac' => $mac];
                        }
                    }
                }
            }
        } else {
            // Linux /proc/net/arp
            if (file_exists('/proc/net/arp')) {
                $content = @file_get_contents('/proc/net/arp');
                if ($content) {
                    $lines = explode("\n", $content);
                    foreach ($lines as $line) {
                        $parts = preg_split('/\s+/', trim($line));
                        if (count($parts) >= 4 && filter_var($parts[0], FILTER_VALIDATE_IP)) {
                            $ip = $parts[0];
                            $mac = strtoupper($parts[3]);
                            if ($mac !== '00:00:00:00:00:00') {
                                $arpMap[$ip] = ['mac' => $mac];
                            }
                        }
                    }
                }
            } else {
                $output = @shell_exec("arp -n");
                if ($output) {
                    $lines = explode("\n", $output);
                    foreach ($lines as $line) {
                        if (preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s+\S+\s+([0-9a-fA-F:]{17})/i', $line, $m)) {
                            $arpMap[$m[1]] = ['mac' => strtoupper($m[2])];
                        }
                    }
                }
            }
        }

        return $arpMap;
    }

    /**
     * Resolve MAC prefix to Vendor name
     */
    public static function resolveVendor(string $mac): string {
        $prefix = strtoupper(substr($mac, 0, 8));
        return self::$ouiDatabase[$prefix] ?? 'Standard Network Interface';
    }

    /**
     * Probe specific TCP ports on a live IP
     */
    public static function probePorts(string $ip, array $ports): array {
        $open = [];
        foreach ($ports as $port) {
            $conn = @fsockopen($ip, $port, $errno, $errstr, 0.08);
            if ($conn) {
                $open[] = $port;
                fclose($conn);
            }
        }
        return $open;
    }

    /**
     * Reverse DNS lookup
     */
    public static function resolveHostname(string $ip): ?string {
        $host = @gethostbyaddr($ip);
        return ($host && $host !== $ip) ? $host : null;
    }

    /**
     * Auto-classify device type based on open ports & vendor
     */
    public static function classifyDevice(array $openPorts, ?string $vendor, ?string $hostname): array {
        $v = strtolower((string)$vendor);
        $h = strtolower((string)$hostname);

        if (in_array(8291, $openPorts) || str_contains($v, 'mikrotik') || str_contains($h, 'mikrotik') || str_contains($h, 'router')) {
            return ['type' => 'router', 'icon' => 'router'];
        }
        if (in_array(161, $openPorts) || str_contains($v, 'cisco') || str_contains($h, 'switch') || str_contains($h, 'sw-')) {
            return ['type' => 'switch', 'icon' => 'switch'];
        }
        if (in_array(554, $openPorts) || in_array(8000, $openPorts) || str_contains($v, 'dahua') || str_contains($v, 'hikvision') || str_contains($h, 'cam') || str_contains($h, 'nvr')) {
            return ['type' => 'camera', 'icon' => 'camera'];
        }
        if (in_array(9100, $openPorts) || in_array(515, $openPorts) || in_array(631, $openPorts) || str_contains($h, 'print') || str_contains($v, 'epson') || str_contains($v, 'canon')) {
            return ['type' => 'printer', 'icon' => 'printer'];
        }
        if (in_array(5000, $openPorts) || in_array(5001, $openPorts) || str_contains($v, 'synology') || str_contains($v, 'qnap') || str_contains($h, 'nas')) {
            return ['type' => 'nas', 'icon' => 'server'];
        }
        if (str_contains($v, 'ubiquiti') || str_contains($h, 'ap-') || str_contains($h, 'unifi')) {
            return ['type' => 'access_point', 'icon' => 'wifi'];
        }
        if (in_array(3389, $openPorts) || in_array(445, $openPorts)) {
            return ['type' => 'server', 'icon' => 'server'];
        }
        if (in_array(22, $openPorts)) {
            return ['type' => 'server', 'icon' => 'linux'];
        }

        return ['type' => 'generic', 'icon' => 'desktop'];
    }
}
