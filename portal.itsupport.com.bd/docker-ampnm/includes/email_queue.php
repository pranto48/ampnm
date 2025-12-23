<?php
/**
 * Email Queue System for AMPNM
 * Provides reliable email delivery with retry logic and status tracking
 */

require_once __DIR__ . '/mailer.php';

class EmailQueue {
    private $pdo;
    private $settings;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }
    
    /**
     * Load system settings
     */
    private function loadSettings() {
        $this->settings = [
            'max_retries' => 3,
            'retry_delay' => 5,
            'queue_enabled' => true,
            'log_retention_days' => 7
        ];
        
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('email_queue_max_retries', 'email_queue_retry_delay', 'email_queue_enabled', 'log_retention_days')");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            switch ($row['setting_key']) {
                case 'email_queue_max_retries':
                    $this->settings['max_retries'] = (int)$row['setting_value'];
                    break;
                case 'email_queue_retry_delay':
                    $this->settings['retry_delay'] = (int)$row['setting_value'];
                    break;
                case 'email_queue_enabled':
                    $this->settings['queue_enabled'] = $row['setting_value'] === 'true';
                    break;
                case 'log_retention_days':
                    $this->settings['log_retention_days'] = (int)$row['setting_value'];
                    break;
            }
        }
    }
    
    /**
     * Add email to queue
     */
    public function queue($recipient, $subject, $body, $priority = 'normal', $scheduledAt = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO email_queue (recipient, subject, body, priority, max_attempts, scheduled_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $recipient,
            $subject,
            $body,
            $priority,
            $this->settings['max_retries'],
            $scheduledAt
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Process pending emails in queue
     */
    public function processQueue($limit = 10) {
        // Get SMTP settings
        $stmt = $this->pdo->query("
            SELECT ss.* FROM smtp_settings ss
            JOIN users u ON ss.user_id = u.id
            WHERE u.role = 'admin'
            LIMIT 1
        ");
        $smtpSettings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$smtpSettings) {
            error_log("EmailQueue: No SMTP settings found");
            return ['processed' => 0, 'success' => 0, 'failed' => 0, 'error' => 'No SMTP settings configured'];
        }
        
        $mailer = new AMPNMMailer($smtpSettings);
        
        // Get pending emails ready to be sent
        $stmt = $this->pdo->prepare("
            SELECT * FROM email_queue 
            WHERE status IN ('pending', 'processing')
            AND attempts < max_attempts
            AND (scheduled_at IS NULL OR scheduled_at <= NOW())
            ORDER BY 
                FIELD(priority, 'high', 'normal', 'low'),
                created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = ['processed' => 0, 'success' => 0, 'failed' => 0];
        
        foreach ($emails as $email) {
            $results['processed']++;
            
            // Mark as processing
            $this->pdo->prepare("UPDATE email_queue SET status = 'processing', attempts = attempts + 1, updated_at = NOW() WHERE id = ?")->execute([$email['id']]);
            
            // Attempt to send
            $sendResult = $mailer->send($email['recipient'], $email['subject'], $email['body']);
            
            if ($sendResult) {
                // Success
                $this->pdo->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW(), updated_at = NOW() WHERE id = ?")->execute([$email['id']]);
                $this->logDelivery($email['id'], $email['recipient'], $email['subject'], 'sent', null, null, $email['attempts'] + 1);
                $results['success']++;
            } else {
                // Failed
                $errorMsg = $mailer->getLastError();
                $newAttempts = $email['attempts'] + 1;
                
                if ($newAttempts >= $email['max_attempts']) {
                    // Max retries reached
                    $this->pdo->prepare("UPDATE email_queue SET status = 'failed', error_message = ?, updated_at = NOW() WHERE id = ?")->execute([$errorMsg, $email['id']]);
                    $this->logDelivery($email['id'], $email['recipient'], $email['subject'], 'failed', null, $errorMsg, $newAttempts);
                } else {
                    // Schedule retry
                    $retryAt = date('Y-m-d H:i:s', strtotime("+{$this->settings['retry_delay']} minutes"));
                    $this->pdo->prepare("UPDATE email_queue SET status = 'pending', error_message = ?, scheduled_at = ?, updated_at = NOW() WHERE id = ?")->execute([$errorMsg, $retryAt, $email['id']]);
                }
                $results['failed']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Log email delivery attempt
     */
    private function logDelivery($queueId, $recipient, $subject, $status, $smtpResponse = null, $errorMsg = null, $attempts = 1) {
        $stmt = $this->pdo->prepare("
            INSERT INTO email_logs (queue_id, recipient, subject, status, smtp_response, error_message, attempts)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$queueId, $recipient, $subject, $status, $smtpResponse, $errorMsg, $attempts]);
    }
    
    /**
     * Get queue statistics
     */
    public function getStats() {
        $stats = [];
        
        // Count by status
        $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM email_queue GROUP BY status");
        $stats['queue'] = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats['queue'][$row['status']] = (int)$row['count'];
        }
        
        // Recent delivery stats (last 24 hours)
        $stmt = $this->pdo->query("
            SELECT status, COUNT(*) as count 
            FROM email_logs 
            WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY status
        ");
        $stats['delivery_24h'] = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats['delivery_24h'][$row['status']] = (int)$row['count'];
        }
        
        // Total emails sent (all time)
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'sent'");
        $stats['total_sent'] = (int)$stmt->fetchColumn();
        
        // Total failed
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'failed'");
        $stats['total_failed'] = (int)$stmt->fetchColumn();
        
        return $stats;
    }
    
    /**
     * Get email logs with pagination
     */
    public function getLogs($page = 1, $perPage = 50, $status = null, $search = null) {
        $offset = ($page - 1) * $perPage;
        $params = [];
        
        $where = "1=1";
        if ($status) {
            $where .= " AND status = ?";
            $params[] = $status;
        }
        if ($search) {
            $where .= " AND (recipient LIKE ? OR subject LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        // Get total count
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM email_logs WHERE $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        
        // Get logs
        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $this->pdo->prepare("
            SELECT * FROM email_logs 
            WHERE $where
            ORDER BY sent_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
    
    /**
     * Cleanup old logs based on retention setting
     */
    public function cleanupOldLogs() {
        $retentionDays = $this->settings['log_retention_days'];
        
        // Delete old email logs
        $stmt = $this->pdo->prepare("DELETE FROM email_logs WHERE sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$retentionDays]);
        $deletedLogs = $stmt->rowCount();
        
        // Delete old sent/failed queue items
        $stmt = $this->pdo->prepare("DELETE FROM email_queue WHERE status IN ('sent', 'failed', 'cancelled') AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$retentionDays]);
        $deletedQueue = $stmt->rowCount();
        
        error_log("EmailQueue cleanup: Deleted $deletedLogs logs and $deletedQueue queue items older than $retentionDays days");
        
        return ['deleted_logs' => $deletedLogs, 'deleted_queue' => $deletedQueue];
    }
    
    /**
     * Cancel a queued email
     */
    public function cancelEmail($id) {
        $stmt = $this->pdo->prepare("UPDATE email_queue SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Retry a failed email
     */
    public function retryEmail($id) {
        $stmt = $this->pdo->prepare("UPDATE email_queue SET status = 'pending', attempts = 0, error_message = NULL, scheduled_at = NULL, updated_at = NOW() WHERE id = ? AND status = 'failed'");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Check if queue system is enabled
     */
    public function isEnabled() {
        return $this->settings['queue_enabled'];
    }
}
