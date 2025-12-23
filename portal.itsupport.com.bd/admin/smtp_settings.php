<?php
session_start();
require_once '../config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'save_smtp') {
            $smtp_host = trim($_POST['smtp_host'] ?? '');
            $smtp_port = (int)($_POST['smtp_port'] ?? 587);
            $smtp_username = trim($_POST['smtp_username'] ?? '');
            $smtp_password = $_POST['smtp_password'] ?? '';
            $smtp_from_email = trim($_POST['smtp_from_email'] ?? '');
            $smtp_from_name = trim($_POST['smtp_from_name'] ?? 'IT Support BD');
            $smtp_encryption = $_POST['smtp_encryption'] ?? 'tls';

            try {
                $pdo = get_db_connection();
                
                // Check if settings exist
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM portal_settings WHERE setting_key = 'smtp_host'");
                $stmt->execute();
                $exists = $stmt->fetchColumn() > 0;

                if ($exists) {
                    // Update existing settings
                    $settings = [
                        'smtp_host' => $smtp_host,
                        'smtp_port' => $smtp_port,
                        'smtp_username' => $smtp_username,
                        'smtp_password' => $smtp_password,
                        'smtp_from_email' => $smtp_from_email,
                        'smtp_from_name' => $smtp_from_name,
                        'smtp_encryption' => $smtp_encryption
                    ];

                    foreach ($settings as $key => $value) {
                        $stmt = $pdo->prepare("INSERT INTO portal_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                        $stmt->execute([$key, $value, $value]);
                    }
                } else {
                    // Insert new settings
                    $settings = [
                        'smtp_host' => $smtp_host,
                        'smtp_port' => $smtp_port,
                        'smtp_username' => $smtp_username,
                        'smtp_password' => $smtp_password,
                        'smtp_from_email' => $smtp_from_email,
                        'smtp_from_name' => $smtp_from_name,
                        'smtp_encryption' => $smtp_encryption
                    ];

                    foreach ($settings as $key => $value) {
                        $stmt = $pdo->prepare("INSERT INTO portal_settings (setting_key, setting_value) VALUES (?, ?)");
                        $stmt->execute([$key, $value]);
                    }
                }

                $success_message = 'SMTP settings saved successfully!';
            } catch (Exception $e) {
                $error_message = 'Error saving SMTP settings: ' . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'test_smtp') {
            $test_email = trim($_POST['test_email'] ?? '');
            
            if (empty($test_email)) {
                $error_message = 'Please enter a test email address.';
            } else {
                // Send test email
                require_once '../includes/functions.php';
                if (send_portal_email($test_email, 'SMTP Test Email', 'This is a test email from IT Support BD Portal. If you received this, your SMTP configuration is working correctly!')) {
                    $success_message = 'Test email sent successfully to ' . htmlspecialchars($test_email);
                } else {
                    $error_message = 'Failed to send test email. Please check your SMTP settings.';
                }
            }
        }
    }
}

// Load current SMTP settings
$smtp_settings = [];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM portal_settings WHERE setting_key LIKE 'smtp_%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $smtp_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $error_message = 'Error loading SMTP settings: ' . $e->getMessage();
}

include 'header.php';
?>

<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3"><i class="fas fa-envelope-open-text text-primary me-2"></i>SMTP Email Settings</h1>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-cog me-2"></i>SMTP Configuration</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="save_smtp">
                                
                                <div class="mb-3">
                                    <label class="form-label">SMTP Host <span class="text-danger">*</span></label>
                                    <input type="text" name="smtp_host" class="form-control" 
                                           value="<?= htmlspecialchars($smtp_settings['smtp_host'] ?? '') ?>" 
                                           placeholder="smtp.gmail.com" required>
                                    <small class="form-text text-muted">SMTP server hostname</small>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">SMTP Port <span class="text-danger">*</span></label>
                                        <input type="number" name="smtp_port" class="form-control" 
                                               value="<?= htmlspecialchars($smtp_settings['smtp_port'] ?? '587') ?>" 
                                               placeholder="587" required>
                                        <small class="form-text text-muted">Usually 587 (TLS) or 465 (SSL)</small>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Encryption <span class="text-danger">*</span></label>
                                        <select name="smtp_encryption" class="form-select" required>
                                            <option value="tls" <?= ($smtp_settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                            <option value="ssl" <?= ($smtp_settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                            <option value="none" <?= ($smtp_settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">SMTP Username <span class="text-danger">*</span></label>
                                    <input type="text" name="smtp_username" class="form-control" 
                                           value="<?= htmlspecialchars($smtp_settings['smtp_username'] ?? '') ?>" 
                                           placeholder="your-email@gmail.com" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">SMTP Password <span class="text-danger">*</span></label>
                                    <input type="password" name="smtp_password" class="form-control" 
                                           value="<?= htmlspecialchars($smtp_settings['smtp_password'] ?? '') ?>" 
                                           placeholder="Enter SMTP password" required>
                                    <small class="form-text text-muted">For Gmail, use App Password if 2FA is enabled</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">From Email <span class="text-danger">*</span></label>
                                    <input type="email" name="smtp_from_email" class="form-control" 
                                           value="<?= htmlspecialchars($smtp_settings['smtp_from_email'] ?? '') ?>" 
                                           placeholder="noreply@itsupport.com.bd" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">From Name</label>
                                    <input type="text" name="smtp_from_name" class="form-control" 
                                           value="<?= htmlspecialchars($smtp_settings['smtp_from_name'] ?? 'IT Support BD') ?>" 
                                           placeholder="IT Support BD">
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save SMTP Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Test Configuration</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Send a test email to verify your SMTP configuration is working.</p>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="test_smtp">
                                <div class="mb-3">
                                    <label class="form-label">Test Email Address</label>
                                    <input type="email" name="test_email" class="form-control" 
                                           placeholder="test@example.com" required>
                                </div>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-paper-plane me-2"></i>Send Test Email
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Email Usage</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">Emails will be sent for:</p>
                            <ul class="small mb-0">
                                <li>New product launches</li>
                                <li>AMPNM version updates</li>
                                <li>License renewals</li>
                                <li>Registration confirmations</li>
                                <li>Support ticket updates</li>
                                <li>Custom announcements</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
