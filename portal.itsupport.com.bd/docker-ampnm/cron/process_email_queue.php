<?php
/**
 * Email Queue Cron Processor
 * Run this script via cron every 1-5 minutes to process pending emails
 * 
 * Example crontab entry:
 * * / 2 * * * * php /var/www/html/cron/process_email_queue.php >> /var/log/email_queue.log 2>&1
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Define working directory
chdir(dirname(__DIR__));

// Include configuration
require_once 'includes/config.php';

// Get database connection
$pdo = getDbConnection();

// Include email queue
require_once 'includes/email_queue.php';

try {
    $emailQueue = new EmailQueue($pdo);
    
    // Process up to 20 emails per run
    $result = $emailQueue->processQueue(20);
    
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] Email Queue Processed: {$result['processed']} emails, {$result['success']} sent, {$result['failed']} failed\n";
    
    // Cleanup old logs (run occasionally - every 100th execution or so)
    if (rand(1, 100) === 1) {
        $cleanupResult = $emailQueue->cleanupOldLogs();
        echo "[{$timestamp}] Cleanup: Deleted {$cleanupResult['deleted_logs']} logs, {$cleanupResult['deleted_queue']} queue items\n";
    }
    
} catch (Exception $e) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] Email Queue Error: " . $e->getMessage() . "\n";
    error_log("Email Queue Cron Error: " . $e->getMessage());
    exit(1);
}
