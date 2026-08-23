<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * SNMP v2c/v3 Deep Router/Switch Interface & Metric Monitoring Engine
 */

class SNMPMonitor {
    private string $host;
    private int $port;
    private string $version; // 'v1', 'v2c', 'v3'
    private string $community;
    private int $timeout;
    private int $retries;
    
    // SNMP v3 credentials
    private ?string $v3User;
    private ?string $v3AuthProto; // MD5, SHA, SHA256
    private ?string $v3AuthPass;
    private ?string $v3PrivProto; // DES, AES, AES128
    private ?string $v3PrivPass;
    private ?string $v3SecLevel;  // noAuthNoPriv, authNoPriv, authPriv

    // Standard OID definitions
    public const OID_SYS_DESCR     = '.1.3.6.1.2.1.1.1.0';
    public const OID_SYS_UPTIME    = '.1.3.6.1.2.1.1.3.0';
    public const OID_SYS_CONTACT   = '.1.3.6.1.2.1.1.4.0';
    public const OID_SYS_NAME      = '.1.3.6.1.2.1.1.5.0';
    public const OID_SYS_LOCATION  = '.1.3.6.1.2.1.1.6.0';
    
    // IF-MIB Standard Table OIDs
    public const OID_IF_INDEX        = '.1.3.6.1.2.1.2.2.1.1';
    public const OID_IF_DESCR        = '.1.3.6.1.2.1.2.2.1.2';
    public const OID_IF_TYPE         = '.1.3.6.1.2.1.2.2.1.3';
    public const OID_IF_SPEED        = '.1.3.6.1.2.1.2.2.1.5';
    public const OID_IF_PHYS_ADDRESS = '.1.3.6.1.2.1.2.2.1.6';
    public const OID_IF_ADMIN_STATUS = '.1.3.6.1.2.1.2.2.1.7';
    public const OID_IF_OPER_STATUS  = '.1.3.6.1.2.1.2.2.1.8';
    public const OID_IF_IN_OCTETS    = '.1.3.6.1.2.1.2.2.1.10';
    public const OID_IF_IN_ERRORS    = '.1.3.6.1.2.1.2.2.1.14';
    public const OID_IF_OUT_OCTETS   = '.1.3.6.1.2.1.2.2.1.16';
    public const OID_IF_OUT_ERRORS   = '.1.3.6.1.2.1.2.2.1.20';
    
    // IF-MIB 64-bit High Capacity (HC) OIDs (for 1G/10G/100G interfaces)
    public const OID_IF_NAME         = '.1.3.6.1.2.1.31.1.1.1.1';
    public const OID_IF_HC_IN_OCTETS = '.1.3.6.1.2.1.31.1.1.1.6';
    public const OID_IF_HC_OUT_OCTETS= '.1.3.6.1.2.1.31.1.1.1.10';
    public const OID_IF_HIGH_SPEED   = '.1.3.6.1.2.1.31.1.1.1.15'; // Speed in Mbps
    public const OID_IF_ALIAS        = '.1.3.6.1.2.1.31.1.1.1.18'; // Custom Port Label/Description

    // Vendor Specific CPU/RAM OIDs
    public const OID_MIKROTIK_CPU    = '.1.3.6.1.4.1.14988.1.1.1.2.1.1.0';
    public const OID_MIKROTIK_TEMP   = '.1.3.6.1.4.1.14988.1.1.3.10.0';
    public const OID_CISCO_CPU_5M    = '.1.3.6.1.4.1.9.9.109.1.1.1.1.5.1';
    public const OID_HOST_CPU        = '.1.3.6.1.2.1.25.3.3.1.2.1';

    public function __construct(array $config) {
        $this->host = trim($config['ip'] ?? $config['host'] ?? '127.0.0.1');
        $this->port = (int)($config['snmp_port'] ?? 161);
        $this->version = strtolower($config['snmp_version'] ?? 'v2c');
        $this->community = trim($config['snmp_community'] ?? 'public');
        $this->timeout = (int)($config['timeout'] ?? 1500000); // 1.5s
        $this->retries = (int)($config['retries'] ?? 2);

        $this->v3User = $config['snmp_v3_user'] ?? null;
        $this->v3AuthProto = $config['snmp_v3_auth_proto'] ?? 'SHA';
        $this->v3AuthPass = $config['snmp_v3_auth_pass'] ?? null;
        $this->v3PrivProto = $config['snmp_v3_priv_proto'] ?? 'AES';
        $this->v3PrivPass = $config['snmp_v3_priv_pass'] ?? null;
        $this->v3SecLevel = $config['snmp_v3_sec_level'] ?? 'authPriv';
    }

    /**
     * Checks if PHP native SNMP extension is available.
     */
    public static function hasNativeExtension(): bool {
        return extension_loaded('snmp') && function_exists('snmp2_get');
    }

    /**
     * Execute SNMP GET query
     */
    public function get(string $oid): ?string {
        $hostWithPort = $this->port === 161 ? $this->host : "{$this->host}:{$this->port}";

        if (self::hasNativeExtension()) {
            try {
                if ($this->version === 'v3') {
                    $val = @snmp3_get(
                        $hostWithPort,
                        $this->v3SecLevel,
                        $this->v3AuthProto,
                        $this->v3AuthPass,
                        $this->v3PrivProto,
                        $this->v3PrivPass,
                        $oid,
                        $this->timeout,
                        $this->retries
                    );
                } elseif ($this->version === 'v1') {
                    $val = @snmpget($hostWithPort, $this->community, $oid, $this->timeout, $this->retries);
                } else {
                    $val = @snmp2_get($hostWithPort, $this->community, $oid, $this->timeout, $this->retries);
                }
                if ($val !== false) {
                    return self::cleanSnmpValue($val);
                }
            } catch (Throwable $e) {
                // Fallback to shell execution
            }
        }

        return $this->shellGet($oid);
    }

    /**
     * Execute SNMP WALK query (returns array key => value)
     */
    public function walk(string $oid): array {
        $hostWithPort = $this->port === 161 ? $this->host : "{$this->host}:{$this->port}";
        $results = [];

        if (self::hasNativeExtension()) {
            try {
                if ($this->version === 'v3') {
                    $raw = @snmp3_real_walk(
                        $hostWithPort,
                        $this->v3SecLevel,
                        $this->v3AuthProto,
                        $this->v3AuthPass,
                        $this->v3PrivProto,
                        $this->v3PrivPass,
                        $oid,
                        $this->timeout,
                        $this->retries
                    );
                } elseif ($this->version === 'v1') {
                    $raw = @snmprealwalk($hostWithPort, $this->community, $oid, $this->timeout, $this->retries);
                } else {
                    $raw = @snmp2_real_walk($hostWithPort, $this->community, $oid, $this->timeout, $this->retries);
                }

                if (is_array($raw)) {
                    foreach ($raw as $k => $v) {
                        $idx = substr(strrchr($k, '.'), 1);
                        if ($idx !== false) {
                            $results[$idx] = self::cleanSnmpValue($v);
                        }
                    }
                    return $results;
                }
            } catch (Throwable $e) {
                // Fallback to shell
            }
        }

        return $this->shellWalk($oid);
    }

    /**
     * Shell fallback for snmpget
     */
    private function shellGet(string $oid): ?string {
        if (!shell_exec('which snmpget 2>/dev/null')) {
            return null;
        }

        $cmd = $this->buildShellCommand('snmpget', $oid);
        $output = trim(shell_exec($cmd) ?? '');
        if (empty($output) || str_contains($output, 'No Such') || str_contains($output, 'Timeout')) {
            return null;
        }

        return self::cleanSnmpValue($output);
    }

    /**
     * Shell fallback for snmpwalk
     */
    private function shellWalk(string $oid): array {
        if (!shell_exec('which snmpwalk 2>/dev/null')) {
            return [];
        }

        $cmd = $this->buildShellCommand('snmpwalk', $oid);
        $output = trim(shell_exec($cmd) ?? '');
        if (empty($output) || str_contains($output, 'No Such') || str_contains($output, 'Timeout')) {
            return [];
        }

        $results = [];
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            // Format: iso.3.6.1.2.1.2.2.1.2.1 = STRING: ether1
            if (preg_match('/\.(\d+)\s*=\s*(?:[A-Za-z0-9\-]+:\s*)?(.*)$/', $line, $matches)) {
                $results[$matches[1]] = self::cleanSnmpValue($matches[2]);
            }
        }

        return $results;
    }

    private function buildShellCommand(string $binary, string $oid): string {
        $hostWithPort = escapeshellarg($this->port === 161 ? $this->host : "{$this->host}:{$this->port}");
        $escapedOid = escapeshellarg($oid);

        if ($this->version === 'v3') {
            return sprintf(
                '%s -v3 -l %s -u %s -a %s -A %s -x %s -X %s -r %d -t %d %s %s 2>&1',
                $binary,
                escapeshellarg($this->v3SecLevel),
                escapeshellarg((string)$this->v3User),
                escapeshellarg((string)$this->v3AuthProto),
                escapeshellarg((string)$this->v3AuthPass),
                escapeshellarg((string)$this->v3PrivProto),
                escapeshellarg((string)$this->v3PrivPass),
                $this->retries,
                (int)($this->timeout / 1000000),
                $hostWithPort,
                $escapedOid
            );
        }

        $verFlag = $this->version === 'v1' ? '-v1' : '-v2c';
        return sprintf(
            '%s %s -c %s -r %d -t %d %s %s 2>&1',
            $binary,
            $verFlag,
            escapeshellarg($this->community),
            $this->retries,
            max(1, (int)($this->timeout / 1000000)),
            $hostWithPort,
            $escapedOid
        );
    }

    /**
     * Cleans raw SNMP string output (removes types like STRING:, Counter64:, INTEGER:, Timeticks:, etc.)
     */
    public static function cleanSnmpValue(string $raw): string {
        $raw = trim($raw);
        $cleaned = preg_replace('/^(?:STRING|Counter32|Counter64|Gauge32|INTEGER|Timeticks|Hex-STRING|OID|IpAddress|Network Address):\s*/i', '', $raw);
        $cleaned = trim($cleaned, "\" \t\n\r\0\x0B");
        if (preg_match('/\(\d+\)\s*(.+)/', $cleaned, $matches)) {
            $cleaned = $matches[1];
        }
        return $cleaned;
    }

    /**
     * Polls basic device overview: SysName, SysDescr, Uptime, CPU, Temp
     */
    public function getSystemOverview(): array {
        $sysDescr = $this->get(self::OID_SYS_DESCR);
        if ($sysDescr === null) {
            return ['online' => false, 'error' => 'SNMP host unreachable or timeout'];
        }

        $sysName = $this->get(self::OID_SYS_NAME);
        $sysUptime = $this->get(self::OID_SYS_UPTIME);
        $sysContact = $this->get(self::OID_SYS_CONTACT);
        $sysLocation = $this->get(self::OID_SYS_LOCATION);

        // Attempt CPU detection
        $cpu = $this->get(self::OID_MIKROTIK_CPU) 
            ?? $this->get(self::OID_CISCO_CPU_5M) 
            ?? $this->get(self::OID_HOST_CPU);

        $temp = $this->get(self::OID_MIKROTIK_TEMP);
        if ($temp !== null && is_numeric($temp) && $temp > 100) {
            $temp = round($temp / 10, 1);
        }

        return [
            'online' => true,
            'sys_name' => $sysName ?: $this->host,
            'sys_descr' => $sysDescr,
            'sys_uptime' => $sysUptime,
            'sys_contact' => $sysContact,
            'sys_location' => $sysLocation,
            'cpu_percent' => ($cpu !== null && is_numeric($cpu)) ? (float)$cpu : null,
            'temperature' => $temp
        ];
    }

    /**
     * Discovers and retrieves all physical and logical interfaces with current traffic counters
     */
    public function getInterfaces(): array {
        $descr = $this->walk(self::OID_IF_DESCR);
        if (empty($descr)) {
            $descr = $this->walk(self::OID_IF_NAME);
        }
        if (empty($descr)) {
            return [];
        }

        $types = $this->walk(self::OID_IF_TYPE);
        $speeds = $this->walk(self::OID_IF_SPEED);
        $highSpeeds = $this->walk(self::OID_IF_HIGH_SPEED);
        $adminStatus = $this->walk(self::OID_IF_ADMIN_STATUS);
        $operStatus = $this->walk(self::OID_IF_OPER_STATUS);
        $macs = $this->walk(self::OID_IF_PHYS_ADDRESS);
        $aliases = $this->walk(self::OID_IF_ALIAS);
        
        $inOctets = $this->walk(self::OID_IF_HC_IN_OCTETS);
        if (empty($inOctets)) $inOctets = $this->walk(self::OID_IF_IN_OCTETS);
        
        $outOctets = $this->walk(self::OID_IF_HC_OUT_OCTETS);
        if (empty($outOctets)) $outOctets = $this->walk(self::OID_IF_OUT_OCTETS);

        $inErrors = $this->walk(self::OID_IF_IN_ERRORS);
        $outErrors = $this->walk(self::OID_IF_OUT_ERRORS);

        $interfaces = [];
        foreach ($descr as $idx => $name) {
            $speedBps = isset($speeds[$idx]) && is_numeric($speeds[$idx]) ? (int)$speeds[$idx] : 0;
            if (isset($highSpeeds[$idx]) && is_numeric($highSpeeds[$idx]) && (int)$highSpeeds[$idx] > 0) {
                $speedBps = (int)$highSpeeds[$idx] * 1000000;
            }

            $admin = isset($adminStatus[$idx]) ? (str_contains($adminStatus[$idx], '1') || strtolower($adminStatus[$idx]) === 'up' ? 'up' : 'down') : 'unknown';
            $oper = isset($operStatus[$idx]) ? (str_contains($operStatus[$idx], '1') || strtolower($operStatus[$idx]) === 'up' ? 'up' : 'down') : 'unknown';

            $interfaces[] = [
                'if_index' => (int)$idx,
                'if_descr' => $name,
                'if_alias' => $aliases[$idx] ?? '',
                'if_type' => $types[$idx] ?? 'ethernet',
                'if_speed' => $speedBps,
                'if_mac' => !empty($macs[$idx]) ? self::formatMac($macs[$idx]) : null,
                'if_admin_status' => $admin,
                'if_oper_status' => $oper,
                'in_octets' => isset($inOctets[$idx]) && is_numeric($inOctets[$idx]) ? (float)$inOctets[$idx] : 0,
                'out_octets' => isset($outOctets[$idx]) && is_numeric($outOctets[$idx]) ? (float)$outOctets[$idx] : 0,
                'in_errors' => isset($inErrors[$idx]) && is_numeric($inErrors[$idx]) ? (int)$inErrors[$idx] : 0,
                'out_errors' => isset($outErrors[$idx]) && is_numeric($outErrors[$idx]) ? (int)$outErrors[$idx] : 0,
            ];
        }

        return $interfaces;
    }

    private static function formatMac(string $raw): string {
        $clean = preg_replace('/[^0-9a-fA-F]/', '', $raw);
        if (strlen($clean) === 12) {
            return strtoupper(implode(':', str_split($clean, 2)));
        }
        return $raw;
    }

    public static function formatBitrate(float $bps): string {
        if ($bps >= 1000000000) {
            return round($bps / 1000000000, 2) . ' Gbps';
        }
        if ($bps >= 1000000) {
            return round($bps / 1000000, 2) . ' Mbps';
        }
        if ($bps >= 1000) {
            return round($bps / 1000, 1) . ' Kbps';
        }
        return round($bps) . ' bps';
    }
}
