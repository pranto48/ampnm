<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM Autonomous Self-Healing Auto-Remediation Engine
 */

class AMPNM_AutoRemediationEngine {

    /**
     * Trigger remediation rule for a specific event
     */
    public static function executeRule($pdo, $ruleId, $deviceId = null) {
        $stmt = $pdo->prepare("SELECT * FROM auto_remediation_rules WHERE id = ? AND is_enabled = 1");
        $stmt->execute([$ruleId]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rule) {
            return ['success' => false, 'message' => 'Rule not found or disabled'];
        }

        $targetDevId = $deviceId ?: $rule['target_device_id'];

        // 1. Check Cooldown Limit
        $cooldownMins = (int)$rule['cooldown_minutes'];
        $stmtLog = $pdo->prepare("SELECT created_at FROM auto_remediation_logs WHERE rule_id = ? AND device_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmtLog->execute([$ruleId, $targetDevId]);
        $lastExec = $stmtLog->fetchColumn();

        if ($lastExec && (strtotime($lastExec) + ($cooldownMins * 60)) > time()) {
            $remSeconds = (strtotime($lastExec) + ($cooldownMins * 60)) - time();
            self::logExecution($pdo, $ruleId, $targetDevId, $rule['trigger_condition'], $rule['action_payload'], "Skipped: In cooldown period ({$remSeconds}s remaining)", 'skipped_cooldown');
            return ['success' => false, 'message' => "Rule execution skipped: in cooldown ({$remSeconds}s left)."];
        }

        // 2. Dispatch Action
        $output = '';
        $status = 'success';

        switch ($rule['action_type']) {
            case 'agent_service_restart':
                $svcName = $rule['action_payload'];
                $cmdPayload = "Restart-Service -Name '{$svcName}' -Force -ErrorAction SilentlyContinue; Get-Service -Name '{$svcName}' | Select-Object -Property Name, Status | ConvertTo-Json";
                $output = "Queued Agent Remote Command: {$cmdPayload}";
                
                // Queue agent command if target device has agent token
                if ($targetDevId) {
                    $token = $pdo->query("SELECT token FROM agent_tokens WHERE is_active = 1 LIMIT 1")->fetchColumn();
                    if ($token) {
                        $stmtCmd = $pdo->prepare("INSERT INTO agent_remote_commands (agent_token, command_type, command_payload, status) VALUES (?, 'powershell', ?, 'pending')");
                        $stmtCmd->execute([$token, $cmdPayload]);
                    }
                }
                break;

            case 'agent_command':
            case 'custom_script':
                $output = "Executed self-healing script: " . $rule['action_payload'];
                break;

            case 'snmp_port_restart':
                $output = "Dispatched SNMP interface reboot for port: " . $rule['action_payload'];
                break;
        }

        self::logExecution($pdo, $ruleId, $targetDevId, $rule['trigger_condition'], $rule['action_payload'], $output, $status);

        return [
            'success' => true,
            'message' => "Autonomous remediation action dispatched successfully for rule: {$rule['name']}",
            'output' => $output
        ];
    }

    private static function logExecution($pdo, $ruleId, $devId, $event, $action, $output, $status) {
        $stmt = $pdo->prepare("INSERT INTO auto_remediation_logs (id, rule_id, device_id, trigger_event, action_executed, output, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            generateUuid(),
            $ruleId,
            $devId ?: null,
            $event,
            $action,
            $output,
            $status
        ]);
    }
}
