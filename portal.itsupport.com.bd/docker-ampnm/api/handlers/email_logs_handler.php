<?php
/**
 * Email Logs Handler - API for email queue, logs, and settings management
 */

require_once __DIR__ . '/../../includes/email_queue.php';

$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'viewer';

// Only admins can access email logs
if ($user_role !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin privileges required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$emailQueue = new EmailQueue($pdo);

switch ($action) {
    case 'get_email_stats':
        $stats = $emailQueue->getStats();
        echo json_encode($stats);
        break;
        
    case 'get_email_logs':
        $page = (int)($_GET['page'] ?? 1);
        $perPage = min((int)($_GET['per_page'] ?? 50), 100);
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;
        
        $result = $emailQueue->getLogs($page, $perPage, $status, $search);
        echo json_encode($result);
        break;
        
    case 'get_email_queue':
        $status = $_GET['status'] ?? null;
        $page = (int)($_GET['page'] ?? 1);
        $perPage = min((int)($_GET['per_page'] ?? 50), 100);
        $offset = ($page - 1) * $perPage;
        
        $where = "1=1";
        $params = [];
        
        if ($status) {
            $where .= " AND status = ?";
            $params[] = $status;
        }
        
        // Get total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM email_queue WHERE $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        
        // Get queue items
        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $pdo->prepare("
            SELECT id, recipient, subject, priority, status, attempts, max_attempts, 
                   error_message, scheduled_at, sent_at, created_at
            FROM email_queue 
            WHERE $where
            ORDER BY 
                FIELD(status, 'pending', 'processing', 'failed', 'sent', 'cancelled'),
                FIELD(priority, 'high', 'normal', 'low'),
                created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'queue' => $queue,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ]);
        break;
        
    case 'retry_email':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Email ID required']);
                exit;
            }
            
            $result = $emailQueue->retryEmail($id);
            echo json_encode(['success' => $result, 'message' => $result ? 'Email queued for retry' : 'Failed to retry email']);
        }
        break;
        
    case 'cancel_email':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Email ID required']);
                exit;
            }
            
            $result = $emailQueue->cancelEmail($id);
            echo json_encode(['success' => $result, 'message' => $result ? 'Email cancelled' : 'Failed to cancel email']);
        }
        break;
        
    case 'process_email_queue':
        // Manually trigger queue processing
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $limit = min((int)($input['limit'] ?? 10), 50);
            $result = $emailQueue->processQueue($limit);
            echo json_encode(['success' => true, 'result' => $result]);
        }
        break;
        
    case 'cleanup_email_logs':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $emailQueue->cleanupOldLogs();
            echo json_encode(['success' => true, 'result' => $result]);
        }
        break;
        
    case 'get_system_settings':
        $stmt = $pdo->query("SELECT setting_key, setting_value, setting_type, description FROM system_settings ORDER BY setting_key");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = [
                'value' => $row['setting_value'],
                'type' => $row['setting_type'],
                'description' => $row['description']
            ];
        }
        echo json_encode($settings);
        break;
        
    case 'save_system_settings':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $updated = 0;
            foreach ($input as $key => $value) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
                $updated += $stmt->rowCount();
            }
            echo json_encode(['success' => true, 'updated' => $updated]);
        }
        break;
        
    case 'get_alert_logs':
        $page = (int)($_GET['page'] ?? 1);
        $perPage = min((int)($_GET['per_page'] ?? 50), 100);
        $offset = ($page - 1) * $perPage;
        $hostIp = $_GET['host_ip'] ?? null;
        $level = $_GET['level'] ?? null;
        
        $where = "1=1";
        $params = [];
        
        if ($hostIp) {
            $where .= " AND host_ip = ?";
            $params[] = $hostIp;
        }
        if ($level) {
            $where .= " AND alert_level = ?";
            $params[] = $level;
        }
        
        // Get total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM host_alert_log WHERE $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        
        // Get logs
        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $pdo->prepare("
            SELECT * FROM host_alert_log 
            WHERE $where
            ORDER BY sent_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ]);
        break;
        
    case 'cleanup_alert_logs':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Get retention days from settings
            $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'alert_log_retention_days'");
            $retentionDays = (int)($stmt->fetchColumn() ?: 30);
            
            $stmt = $pdo->prepare("DELETE FROM host_alert_log WHERE sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$retentionDays]);
            $deleted = $stmt->rowCount();
            
            echo json_encode(['success' => true, 'deleted' => $deleted, 'retention_days' => $retentionDays]);
        }
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Invalid email logs action']);
}
