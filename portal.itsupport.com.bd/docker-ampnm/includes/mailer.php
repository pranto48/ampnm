<?php
/**
 * PHPMailer Helper Class for AMPNM
 * Provides reliable SMTP email delivery with proper authentication
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer (assuming it's installed via composer or manually)
require_once __DIR__ . '/../vendor/autoload.php';

class AMPNMMailer {
    private $smtp;
    private $lastError = '';
    
    public function __construct($smtpSettings) {
        $this->smtp = $smtpSettings;
    }
    
    /**
     * Get the last error message
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Send an email using PHPMailer with SMTP
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $htmlBody HTML email body
     * @param string $textBody Plain text alternative (optional)
     * @return bool Success status
     */
    public function send($to, $subject, $htmlBody, $textBody = null) {
        if (empty($this->smtp)) {
            $this->lastError = 'SMTP settings not configured';
            return false;
        }
        
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtp['host'];
            $mail->Port = (int)$this->smtp['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp['username'];
            $mail->Password = $this->smtp['password'];
            
            // Encryption
            $encryption = strtolower($this->smtp['encryption'] ?? 'tls');
            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            
            // Timeout settings
            $mail->Timeout = 30;
            $mail->SMTPKeepAlive = false;
            
            // Debug level (0 = off, 1 = client, 2 = client and server)
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
            
            // Recipients
            $fromEmail = $this->smtp['from_email'];
            $fromName = $this->smtp['from_name'] ?? 'AMPNM Monitoring';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->addReplyTo($fromEmail, $fromName);
            
            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            
            if ($textBody) {
                $mail->AltBody = $textBody;
            } else {
                // Generate plain text from HTML
                $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
            }
            
            $mail->send();
            error_log("Email sent successfully to {$to} via SMTP ({$this->smtp['host']}:{$this->smtp['port']})");
            return true;
            
        } catch (Exception $e) {
            $this->lastError = $mail->ErrorInfo;
            error_log("PHPMailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }
    
    /**
     * Send a test email to verify SMTP configuration
     * 
     * @param string $to Test recipient email
     * @return array Result with success status and message
     */
    public function sendTest($to) {
        $time = date('Y-m-d H:i:s');
        
        $subject = "✅ AMPNM SMTP Test - Configuration Verified";
        
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; padding: 20px; margin: 0; }
                .container { max-width: 600px; margin: 0 auto; background: #1e293b; border-radius: 12px; overflow: hidden; }
                .header { background: linear-gradient(135deg, #22c55e, #16a34a); color: white; padding: 24px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
                .header p { margin: 8px 0 0; opacity: 0.9; }
                .content { padding: 24px; }
                .success-icon { font-size: 48px; margin-bottom: 16px; }
                .metric { display: flex; justify-content: space-between; padding: 12px 16px; background: #0f172a; border-radius: 8px; margin-bottom: 8px; }
                .metric:last-child { margin-bottom: 0; }
                .label { color: #94a3b8; font-size: 14px; }
                .value { font-weight: 600; color: #fff; font-size: 14px; }
                .footer { padding: 16px 24px; background: #0f172a; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #334155; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='success-icon'>✅</div>
                    <h1>SMTP Configuration Verified</h1>
                    <p>Your email settings are working correctly!</p>
                </div>
                <div class='content'>
                    <div class='metric'>
                        <span class='label'>SMTP Server</span>
                        <span class='value'>{$this->smtp['host']}</span>
                    </div>
                    <div class='metric'>
                        <span class='label'>Port</span>
                        <span class='value'>{$this->smtp['port']}</span>
                    </div>
                    <div class='metric'>
                        <span class='label'>Encryption</span>
                        <span class='value'>" . strtoupper($this->smtp['encryption'] ?? 'TLS') . "</span>
                    </div>
                    <div class='metric'>
                        <span class='label'>Username</span>
                        <span class='value'>{$this->smtp['username']}</span>
                    </div>
                    <div class='metric'>
                        <span class='label'>From Address</span>
                        <span class='value'>{$this->smtp['from_email']}</span>
                    </div>
                    <div class='metric'>
                        <span class='label'>Test Sent At</span>
                        <span class='value'>{$time}</span>
                    </div>
                </div>
                <div class='footer'>
                    Sent by AMPNM Network Monitoring System using PHPMailer
                </div>
            </div>
        </body>
        </html>
        ";
        
        $result = $this->send($to, $subject, $htmlBody);
        
        return [
            'success' => $result,
            'message' => $result 
                ? "Test email sent successfully to {$to}" 
                : "Failed to send test email: " . $this->getLastError()
        ];
    }
}
