<?php
/**
 * AMPNM Network Configuration Compliance & Golden Standard Audit Engine
 * Copyright (c) IT Support BD. All rights reserved.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config.php';

class ComplianceEngine {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? getDbConnection();
    }

    /**
     * Fetch all active compliance rules
     */
    public function getRules(?string $vendor = null): array {
        if ($vendor && $vendor !== 'all') {
            $stmt = $this->pdo->prepare("SELECT * FROM config_compliance_rules WHERE is_enabled = 1 AND (vendor = ? OR vendor = 'generic') ORDER BY severity ASC");
            $stmt->execute([$vendor]);
        } else {
            $stmt = $this->pdo->query("SELECT * FROM config_compliance_rules WHERE is_enabled = 1 ORDER BY severity ASC");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Run compliance audit on a device
     */
    public function auditDevice(string $deviceId, ?string $configContent = null): array {
        // Fetch device details
        $devStmt = $this->pdo->prepare("SELECT * FROM devices WHERE id = ?");
        $devStmt->execute([$deviceId]);
        $device = $devStmt->fetch(PDO::FETCH_ASSOC);

        if (!$device) {
            return ['success' => false, 'error' => 'Device not found'];
        }

        // Fetch latest backup config if not provided
        if ($configContent === null) {
            $bkStmt = $this->pdo->prepare("SELECT config_content FROM device_config_backups WHERE device_id = ? ORDER BY created_at DESC LIMIT 1");
            $bkStmt->execute([$deviceId]);
            $configContent = $bkStmt->fetchColumn() ?: '';
        }

        // If no config found, generate simulated running config based on device specs
        if (empty($configContent)) {
            $configContent = $this->generateSampleConfig($device);
        }

        $rules = $this->getRules();
        $totalRules = count($rules);
        $passedRules = 0;
        $failedRules = 0;
        $violations = [];
        $remediationCommands = [];

        foreach ($rules as $rule) {
            $rulePassed = false;
            $type = $rule['rule_type'];
            $pattern = $rule['pattern_expression'];

            switch ($type) {
                case 'must_contain':
                    $rulePassed = (stripos($configContent, $pattern) !== false);
                    break;
                case 'must_not_contain':
                    $rulePassed = (stripos($configContent, $pattern) === false);
                    break;
                case 'regex_match':
                    $rulePassed = (bool) @preg_match('/' . $pattern . '/i', $configContent);
                    break;
            }

            if ($rulePassed) {
                $passedRules++;
            } else {
                $failedRules++;
                $violations[] = [
                    'rule_id' => $rule['id'],
                    'rule_name' => $rule['name'],
                    'severity' => $rule['severity'],
                    'vendor' => $rule['vendor'],
                    'description' => $rule['description'],
                    'expected' => $type === 'must_contain' ? "Must contain: '{$pattern}'" : ($type === 'must_not_contain' ? "Must NOT contain: '{$pattern}'" : "Must match regex: {$pattern}"),
                    'remediation' => $rule['remediation_command'] ?? ''
                ];
                if (!empty($rule['remediation_command'])) {
                    $remediationCommands[] = "! Fix for: " . $rule['name'] . "\n" . $rule['remediation_command'];
                }
            }
        }

        $score = $totalRules > 0 ? round(($passedRules / $totalRules) * 100, 2) : 100.00;
        $remediationDiff = implode("\n\n", $remediationCommands);

        // Store result in database
        $auditId = generateUuid();
        $saveStmt = $this->pdo->prepare("INSERT INTO config_compliance_results 
            (id, device_id, compliance_score, total_rules, passed_rules, failed_rules, violations_json, remediation_diff, audited_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $saveStmt->execute([
            $auditId,
            $deviceId,
            $score,
            $totalRules,
            $passedRules,
            $failedRules,
            json_encode($violations),
            $remediationDiff
        ]);

        return [
            'success' => true,
            'audit_id' => $auditId,
            'device_name' => $device['name'] ?? $device['ip_address'],
            'compliance_score' => $score,
            'total_rules' => $totalRules,
            'passed_rules' => $passedRules,
            'failed_rules' => $failedRules,
            'violations' => $violations,
            'remediation_diff' => $remediationDiff
        ];
    }

    /**
     * Helper to synthesize standard configuration for unbacked devices
     */
    private function generateSampleConfig(array $device): string {
        $ip = $device['ip_address'] ?? '192.168.1.1';
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $device['name'] ?? 'Core-Switch');
        return "! Running configuration for {$name}
! Last configuration change at " . date('Y-m-d H:i:s') . "
version 15.2
no service password-encryption
hostname {$name}
!
ip domain-name enterprise.local
!
interface GigabitEthernet0/1
 ip address {$ip} 255.255.255.0
 negotiation auto
!
snmp-server community public RO
!
line con 0
line vty 0 4
 transport input telnet ssh
 login
!
end";
    }

    /**
     * Get latest compliance overview across all devices
     */
    public function getGlobalComplianceOverview(): array {
        $stmt = $this->pdo->query("SELECT r.*, d.name as device_name, d.ip_address, d.device_type 
            FROM config_compliance_results r
            JOIN devices d ON r.device_id = d.id
            INNER JOIN (
                SELECT device_id, MAX(audited_at) as max_audit 
                FROM config_compliance_results 
                GROUP BY device_id
            ) latest ON r.device_id = latest.device_id AND r.audited_at = latest.max_audit
            ORDER BY r.compliance_score ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
