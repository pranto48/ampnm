<?php
/**
 * Host Alert System - Checks thresholds and sends email alerts
 * Uses the existing SMTP settings from the notification system
 */

class HostAlertSystem {
    private $pdo;
    private $settings;
    private $smtpSettings;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
        $this->loadSmtpSettings();
    }
    
    /**
     * Load alert settings (uses first admin's settings)
     */
    private function loadSettings() {
        $stmt = $this->pdo->query("
            SELECT has.* FROM host_alert_settings has
            JOIN users u ON has.user_id = u.id
            WHERE u.role = 'admin' AND has.enabled = TRUE
            LIMIT 1
        ");
        $this->settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Use defaults if no settings exist
        if (!$this->settings) {
            $this->settings = [
                'cpu_warning_threshold' => 80,
                'cpu_critical_threshold' => 95,
                'memory_warning_threshold' => 80,
                'memory_critical_threshold' => 95,
                'disk_warning_threshold' => 80,
                'disk_critical_threshold' => 95,
                'enabled' => true,
                'cooldown_minutes' => 30
            ];
        }
    }
    
    /**
     * Load SMTP settings for sending emails
     */
    private function loadSmtpSettings() {
        $stmt = $this->pdo->query("
            SELECT ss.* FROM smtp_settings ss
            JOIN users u ON ss.user_id = u.id
            WHERE u.role = 'admin'
            LIMIT 1
        ");
        $this->smtpSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check metrics against thresholds and send alerts if needed
     */
    public function checkAndAlert($hostIp, $hostName, $metrics) {
        if (!$this->settings['enabled'] || !$this->smtpSettings) {
            return; // Alerts disabled or no SMTP configured
        }
        
        // Check for per-host override
        $thresholds = $this->getThresholdsForHost($hostIp);
        
        $alerts = [];
        
        // Check CPU
        if ($metrics['cpu_percent'] !== null) {
            $cpu = floatval($metrics['cpu_percent']);
            if ($cpu >= $thresholds['cpu_critical']) {
                $alerts[] = ['type' => 'cpu', 'level' => 'critical', 'value' => $cpu, 'threshold' => $thresholds['cpu_critical']];
            } elseif ($cpu >= $thresholds['cpu_warning']) {
                $alerts[] = ['type' => 'cpu', 'level' => 'warning', 'value' => $cpu, 'threshold' => $thresholds['cpu_warning']];
            }
        }
        
        // Check Memory
        if ($metrics['memory_percent'] !== null) {
            $mem = floatval($metrics['memory_percent']);
            if ($mem >= $thresholds['memory_critical']) {
                $alerts[] = ['type' => 'memory', 'level' => 'critical', 'value' => $mem, 'threshold' => $thresholds['memory_critical']];
            } elseif ($mem >= $thresholds['memory_warning']) {
                $alerts[] = ['type' => 'memory', 'level' => 'warning', 'value' => $mem, 'threshold' => $thresholds['memory_warning']];
            }
        }
        
        // Check Disk
        $diskPercent = null;
        if ($metrics['disk_total_gb'] && $metrics['disk_total_gb'] > 0) {
            $diskUsed = $metrics['disk_total_gb'] - ($metrics['disk_free_gb'] ?? 0);
            $diskPercent = ($diskUsed / $metrics['disk_total_gb']) * 100;
        }
        
        if ($diskPercent !== null) {
            if ($diskPercent >= $thresholds['disk_critical']) {
                $alerts[] = ['type' => 'disk', 'level' => 'critical', 'value' => round($diskPercent, 2), 'threshold' => $thresholds['disk_critical']];
            } elseif ($diskPercent >= $thresholds['disk_warning']) {
                $alerts[] = ['type' => 'disk', 'level' => 'warning', 'value' => round($diskPercent, 2), 'threshold' => $thresholds['disk_warning']];
            }
        }
        
        // Check GPU
        if (isset($metrics['gpu_percent']) && $metrics['gpu_percent'] !== null) {
            $gpu = floatval($metrics['gpu_percent']);
            if ($gpu >= $thresholds['gpu_critical']) {
                $alerts[] = ['type' => 'gpu', 'level' => 'critical', 'value' => $gpu, 'threshold' => $thresholds['gpu_critical']];
            } elseif ($gpu >= $thresholds['gpu_warning']) {
                $alerts[] = ['type' => 'gpu', 'level' => 'warning', 'value' => $gpu, 'threshold' => $thresholds['gpu_warning']];
            }
        }
        
        // Process alerts
        foreach ($alerts as $alert) {
            if (!$this->isInCooldown($hostIp, $alert['type'], $alert['level'])) {
                $this->sendAlert($hostIp, $hostName, $alert, $metrics);
                $this->logAlert($hostIp, $hostName, $alert);
            }
        }
    }
    
    /**
     * Get thresholds for a specific host (override or global)
     */
    private function getThresholdsForHost($hostIp) {
        // Check for per-host override
        $stmt = $this->pdo->prepare("SELECT * FROM host_alert_overrides WHERE host_ip = ? AND enabled = TRUE");
        $stmt->execute([$hostIp]);
        $override = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($override) {
            return [
                'cpu_warning' => $override['cpu_warning'] ?? 80,
                'cpu_critical' => $override['cpu_critical'] ?? 95,
                'memory_warning' => $override['memory_warning'] ?? 80,
                'memory_critical' => $override['memory_critical'] ?? 95,
                'disk_warning' => $override['disk_warning'] ?? 85,
                'disk_critical' => $override['disk_critical'] ?? 95,
                'gpu_warning' => $override['gpu_warning'] ?? 80,
                'gpu_critical' => $override['gpu_critical'] ?? 95
            ];
        }
        
        // Use global settings
        return [
            'cpu_warning' => $this->settings['cpu_warning_threshold'] ?? 80,
            'cpu_critical' => $this->settings['cpu_critical_threshold'] ?? 95,
            'memory_warning' => $this->settings['memory_warning_threshold'] ?? 80,
            'memory_critical' => $this->settings['memory_critical_threshold'] ?? 95,
            'disk_warning' => $this->settings['disk_warning_threshold'] ?? 85,
            'disk_critical' => $this->settings['disk_critical_threshold'] ?? 95,
            'gpu_warning' => 80,
            'gpu_critical' => 95
        ];
    }
    
    /**
     * Check if an alert was recently sent (cooldown period)
     */
    private function isInCooldown($hostIp, $type, $level) {
        $cooldown = $this->settings['cooldown_minutes'] ?? 30;
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM host_alert_log 
            WHERE host_ip = ? AND alert_type = ? AND alert_level = ?
            AND sent_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$hostIp, $type, $level, $cooldown]);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Log the alert to prevent spam
     */
    private function logAlert($hostIp, $hostName, $alert) {
        $stmt = $this->pdo->prepare("
            INSERT INTO host_alert_log (host_ip, host_name, alert_type, alert_level, value, threshold)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$hostIp, $hostName, $alert['type'], $alert['level'], $alert['value'], $alert['threshold']]);
    }
    
    /**
     * Send email alert
     */
    private function sendAlert($hostIp, $hostName, $alert, $metrics) {
        $smtp = $this->smtpSettings;
        
        // Get admin email recipients (all device subscriptions for now, or use SMTP from_email)
        $recipients = [$smtp['from_email']];
        
        $levelEmoji = $alert['level'] === 'critical' ? '🔴' : '🟡';
        $typeLabel = ucfirst($alert['type']);
        
        $subject = "{$levelEmoji} [{$alert['level']}] {$typeLabel} Alert: {$hostName}";
        
        $body = $this->buildEmailBody($hostIp, $hostName, $alert, $metrics);
        
        // Use PHP mail or SMTP library
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . ($smtp['from_name'] ? "{$smtp['from_name']} <{$smtp['from_email']}>" : $smtp['from_email']),
            'Reply-To: ' . $smtp['from_email']
        ];
        
        foreach ($recipients as $to) {
            // Try to use mail() function
            @mail($to, $subject, $body, implode("\r\n", $headers));
        }
        
        // Log for debugging
        error_log("Host Alert Sent: {$alert['level']} {$alert['type']} for {$hostName} ({$hostIp}) - {$alert['value']}%");
    }
    
    /**
     * Build HTML email body
     */
    private function buildEmailBody($hostIp, $hostName, $alert, $metrics) {
        $levelColor = $alert['level'] === 'critical' ? '#ef4444' : '#eab308';
        $typeLabel = ucfirst($alert['type']);
        $time = date('Y-m-d H:i:s');
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: #1e293b; border-radius: 12px; overflow: hidden; }
                .header { background: {$levelColor}; color: white; padding: 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 24px; }
                .metric { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #334155; }
                .metric:last-child { border-bottom: none; }
                .label { color: #94a3b8; }
                .value { font-weight: bold; color: #fff; }
                .value.high { color: {$levelColor}; }
                .footer { padding: 16px 24px; background: #0f172a; text-align: center; font-size: 12px; color: #64748b; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . strtoupper($alert['level']) . " {$typeLabel} ALERT</h1>
                </div>
                <div class='content'>
                    <div class='metric'>
                        <span class='label'>Host</span>
                        <span class='value'>{$hostName}</span>
                    </div>
                    <div class='metric'>
                        <span class='label'>IP Address</span>
                        <span class='value'>{$hostIp}</span>
                    </div>
                    <div class='metric'>
                        <span class='label'>{$typeLabel} Usage</span>
                        <span class='value high'>{$alert['value']}%</span>
                    </div>
                    <div class='metric'>
                        <span class='label'>Threshold</span>
                        <span class='value'>{$alert['threshold']}%</span>
                    </div>
                    <div class='metric'>
                        <span class='label'>CPU</span>
                        <span class='value'>" . ($metrics['cpu_percent'] ?? 'N/A') . "%</span>
                    </div>
                    <div class='metric'>
                        <span class='label'>Memory</span>
                        <span class='value'>" . ($metrics['memory_percent'] ?? 'N/A') . "%</span>
                    </div>
                    <div class='metric'>
                        <span class='label'>Disk Free</span>
                        <span class='value'>" . ($metrics['disk_free_gb'] ?? 'N/A') . " GB</span>
                    </div>
                    <div class='metric'>
                        <span class='label'>Time</span>
                        <span class='value'>{$time}</span>
                    </div>
                </div>
                <div class='footer'>
                    Sent by AMPNM Host Monitoring System
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
