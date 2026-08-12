<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * SNMP (v1 / v2c / v3) Deep Network Monitoring Engine for Switches, Routers, NAS & UPS devices.
 */

if (!defined('AMPNM_SNMP_LOADED')) {
    define('AMPNM_SNMP_LOADED', true);
}

class SNMPMonitor {
    private string $host;
    private int $port;
    private string $version; // '1', '2c', '3'
    private string $community;
    private array $v3Params;
    private int $timeout;
    private int $retries;

    // Standard OIDs
    public const OID_SYS_DESCR  = '1.3.6.1.2.1.1.1.0';
    public const OID_SYS_UPTIME = '1.3.6.1.2.1.1.3.0';
    public const OID_SYS_NAME   = '1.3.6.1.2.1.1.5.0';
    public const OID_SYS_LOCATION = '1.3.6.1.2.1.1.6.0';
    
    // CPU & Memory OIDs
    public const OID_HOST_CPU   = '1.3.6.1.2.1.25.3.3.1.2';
    public const OID_CISCO_CPU  = '1.3.6.1.4.1.9.9.109.1.1.1.1.5.1';
    public const OID_MIKROTIK_CPU = '1.3.6.1.4.1.14988.1.1.1.3.1.0';

    // Interface OIDs
    public const OID_IF_DESCR     = '1.3.6.1.2.1.2.2.1.2';
    public const OID_IF_TYPE      = '1.3.6.1.2.1.2.2.1.3';
    public const OID_IF_SPEED     = '1.3.6.1.2.1.2.2.1.5';
    public const OID_IF_OPER_STATUS = '1.3.6.1.2.1.2.2.1.8';
    public const OID_IF_IN_OCTETS  = '1.3.6.1.2.1.2.2.1.10';
    public const OID_IF_OUT_OCTETS = '1.3.6.1.2.1.2.2.1.16';

    public function __construct(string $host, string $community = 'public', string $version = '2c', int $port = 161, array $v3Params = [], int $timeout = 2, int $retries = 1) {
        $this->host = $host;
        $this->community = $community;
        $this->version = strtolower($version);
        $this->port = $port;
        $this->v3Params = $v3Params;
        $this->timeout = $timeout * 1000000; // microseconds for PHP snmp functions
        $this->retries = $retries;
    }

    /**
     * Executes single OID query using PHP native snmpget or CLI fallback
     */
    public function get(string $oid): ?string {
        if ($this->version === '3') {
            return $this->getV3($oid);
        }

        // Native PHP SNMP check
        $functionName = ($this->version === '1') ? 'snmpget' : 'snmp2_get';
        if (function_exists($functionName)) {
            $result = @$functionName($this->host . ':' . $this->port, $this->community, $oid, $this->timeout, $this->retries);
            if ($result !== false) {
                return $this->cleanValue($result);
            }
        }

        // CLI fallback using snmpget tool
        return $this->cliGet($oid);
    }

    /**
     * SNMP v3 Get Query
     */
    private function getV3(string $oid): ?string {
        $secName = $this->v3Params['sec_name'] ?? 'initial';
        $secLevel = $this->v3Params['sec_level'] ?? 'authPriv'; // noAuthNoPriv, authNoPriv, authPriv
        $authProtocol = $this->v3Params['auth_protocol'] ?? 'SHA';
        $authPassphrase = $this->v3Params['auth_passphrase'] ?? '';
        $privProtocol = $this->v3Params['priv_protocol'] ?? 'AES';
        $privPassphrase = $this->v3Params['priv_passphrase'] ?? '';

        if (function_exists('snmp3_get')) {
            $result = @snmp3_get(
                $this->host . ':' . $this->port,
                $secName,
                $secLevel,
                $authProtocol,
                $authPassphrase,
                $privProtocol,
                $privPassphrase,
                $oid,
                $this->timeout,
                $this->retries
            );
            if ($result !== false) {
                return $this->cleanValue($result);
            }
        }

        return null;
    }

    /**
     * Executes snmpwalk/table query for interfaces
     */
    public function getInterfaceSummary(): array {
        $interfaces = [];
        $descrs = $this->walk(self::OID_IF_DESCR);
        $statuses = $this->walk(self::OID_IF_OPER_STATUS);
        $inOctets = $this->walk(self::OID_IF_IN_OCTETS);
        $outOctets = $this->walk(self::OID_IF_OUT_OCTETS);

        if (empty($descrs)) {
            return [];
        }

        foreach ($descrs as $index => $descr) {
            $status = isset($statuses[$index]) ? (int)$statuses[$index] : 2; // 1 = up, 2 = down
            $interfaces[] = [
                'index' => $index,
                'name'  => $descr,
                'status' => ($status === 1) ? 'up' : 'down',
                'in_octets' => isset($inOctets[$index]) ? (float)$inOctets[$index] : 0,
                'out_octets' => isset($outOctets[$index]) ? (float)$outOctets[$index] : 0,
            ];
        }

        return $interfaces;
    }

    /**
     * SNMP Walk implementation
     */
    public function walk(string $oid): array {
        $results = [];
        $functionName = ($this->version === '1') ? 'snmpwalk' : 'snmp2_walk';

        if (function_exists($functionName) && $this->version !== '3') {
            $raw = @$functionName($this->host . ':' . $this->port, $this->community, $oid, $this->timeout, $this->retries);
            if (is_array($raw)) {
                foreach ($raw as $k => $val) {
                    $results[$k] = $this->cleanValue($val);
                }
            }
        }

        return $results;
    }

    /**
     * Complete Metrics Audit for Switch/Router
     */
    public function getMetrics(): array {
        $sysName = $this->get(self::OID_SYS_NAME) ?? $this->host;
        $sysDescr = $this->get(self::OID_SYS_DESCR) ?? 'Unknown Device';
        $sysUptime = $this->get(self::OID_SYS_UPTIME) ?? '0';

        // CPU Usage Check
        $cpu = $this->get(self::OID_CISCO_CPU);
        if ($cpu === null) {
            $cpu = $this->get(self::OID_MIKROTIK_CPU);
        }
        if ($cpu === null) {
            $cpu = $this->get(self::OID_HOST_CPU);
        }

        $interfaces = $this->getInterfaceSummary();

        return [
            'status' => 'online',
            'host' => $this->host,
            'sys_name' => $sysName,
            'sys_descr' => $sysDescr,
            'uptime_raw' => $sysUptime,
            'cpu_percent' => ($cpu !== null) ? (float)$cpu : null,
            'interface_count' => count($interfaces),
            'interfaces' => array_slice($interfaces, 0, 16), // top 16 interfaces
            'last_poll' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * CLI Fallback using snmpget executable
     */
    private function cliGet(string $oid): ?string {
        if (!function_exists('exec')) {
            return null;
        }

        $vFlag = ($this->version === '1') ? '-v1' : '-v2c';
        $cmd = sprintf("snmpget %s -c %s -t 2 %s %s 2>&1",
            escapeshellarg($vFlag),
            escapeshellarg($this->community),
            escapeshellarg($this->host . ':' . $this->port),
            escapeshellarg($oid)
        );

        $output = [];
        $returnVar = 0;
        @exec($cmd, $output, $returnVar);

        if ($returnVar === 0 && !empty($output[0])) {
            return $this->cleanValue(implode(' ', $output));
        }

        return null;
    }

    /**
     * Strips SNMP type prefixes (STRING:, Gauge32:, INTEGER:, Timeticks:)
     */
    private function cleanValue(string $raw): string {
        $clean = preg_replace('/^(STRING|INTEGER|Gauge32|Counter32|Counter64|Timeticks|OID):\s*/i', '', trim($raw));
        $clean = trim($clean, '"');
        return $clean;
    }
}
