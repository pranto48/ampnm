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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_email') {
    $recipient_type = $_POST['recipient_type'] ?? 'all';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $email_type = $_POST['email_type'] ?? 'announcement';

    if (empty($subject) || empty($message)) {
        $error_message = 'Subject and message are required.';
    } else {
        try {
            $pdo = get_db_connection();
            
            // Get recipients based on type
            $recipients = [];
            if ($recipient_type === 'all') {
                $stmt = $pdo->query("SELECT email, full_name FROM portal_customers WHERE email IS NOT NULL");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($recipient_type === 'active_licenses') {
                $stmt = $pdo->query("SELECT DISTINCT c.email, c.full_name FROM portal_customers c 
                                    INNER JOIN portal_licenses l ON c.id = l.customer_id 
                                    WHERE l.status = 'active' AND c.email IS NOT NULL");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($recipient_type === 'expired_licenses') {
                $stmt = $pdo->query("SELECT DISTINCT c.email, c.full_name FROM portal_customers c 
                                    INNER JOIN portal_licenses l ON c.id = l.customer_id 
                                    WHERE l.status = 'expired' AND c.email IS NOT NULL");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            if (empty($recipients)) {
                $error_message = 'No recipients found for the selected category.';
            } else {
                require_once '../includes/functions.php';
                $sent_count = 0;
                $failed_count = 0;

                foreach ($recipients as $recipient) {
                    $personalized_message = str_replace('{name}', $recipient['full_name'], $message);
                    if (send_portal_email($recipient['email'], $subject, $personalized_message)) {
                        $sent_count++;
                    } else {
                        $failed_count++;
                    }
                    
                    // Small delay to avoid overwhelming SMTP server
                    usleep(100000); // 0.1 second
                }

                $success_message = "Email sent successfully to {$sent_count} recipient(s).";
                if ($failed_count > 0) {
                    $success_message .= " Failed to send to {$failed_count} recipient(s).";
                }

                // Log the notification
                $stmt = $pdo->prepare("INSERT INTO portal_email_logs (email_type, subject, recipient_count, sent_at, sent_by) VALUES (?, ?, ?, NOW(), ?)");
                $stmt->execute([$email_type, $subject, $sent_count, $_SESSION['admin_username'] ?? 'admin']);
            }
        } catch (Exception $e) {
            $error_message = 'Error sending emails: ' . $e->getMessage();
        }
    }
}

// Get email statistics
$total_sent = 0;
$recent_emails = [];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT COUNT(*) FROM portal_email_logs");
    $total_sent = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT * FROM portal_email_logs ORDER BY sent_at DESC LIMIT 10");
    $recent_emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist yet
}

include 'header.php';
?>

<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3"><i class="fas fa-bullhorn text-primary me-2"></i>Send Email Notifications</h1>
                <a href="smtp_settings.php" class="btn btn-outline-primary">
                    <i class="fas fa-cog me-2"></i>SMTP Settings
                </a>
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
                            <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Compose Email</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="send_email">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email Type <span class="text-danger">*</span></label>
                                        <select name="email_type" class="form-select" required>
                                            <option value="announcement">📢 Announcement</option>
                                            <option value="product_launch">🚀 Product Launch</option>
                                            <option value="version_update">🔄 Version Update</option>
                                            <option value="license_renewal">🔑 License Renewal</option>
                                            <option value="news">📰 News</option>
                                            <option value="maintenance">🔧 Maintenance Notice</option>
                                            <option value="promotion">🎁 Promotion</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Recipients <span class="text-danger">*</span></label>
                                        <select name="recipient_type" class="form-select" required>
                                            <option value="all">👥 All Customers</option>
                                            <option value="active_licenses">✅ Active License Holders</option>
                                            <option value="expired_licenses">⏰ Expired License Holders</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Subject <span class="text-danger">*</span></label>
                                    <input type="text" name="subject" class="form-control" 
                                           placeholder="Enter email subject" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Message <span class="text-danger">*</span></label>
                                    <textarea name="message" class="form-control" rows="10" 
                                              placeholder="Enter your message here..." required></textarea>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-lightbulb text-warning me-1"></i>
                                        Tip: Use {name} to personalize with customer's name
                                    </small>
                                </div>

                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Warning:</strong> This will send emails to multiple recipients. Please review your message carefully before sending.
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Send Email Notification
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Email Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <h2 class="display-4 text-success"><?= $total_sent ?></h2>
                                <p class="text-muted">Total Emails Sent</p>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-mail-bulk me-2"></i>Email Templates</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">Quick templates you can use:</p>
                            <div class="d-grid gap-2">
                                <button class="btn btn-sm btn-outline-primary template-btn" data-template="product">
                                    🚀 Product Launch
                                </button>
                                <button class="btn btn-sm btn-outline-primary template-btn" data-template="update">
                                    🔄 Version Update
                                </button>
                                <button class="btn btn-sm btn-outline-primary template-btn" data-template="renewal">
                                    🔑 License Renewal
                                </button>
                                <button class="btn btn-sm btn-outline-primary template-btn" data-template="maintenance">
                                    🔧 Maintenance
                                </button>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($recent_emails)): ?>
                    <div class="card shadow-sm">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Emails</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($recent_emails, 0, 5) as $email): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1 small"><?= htmlspecialchars($email['subject']) ?></h6>
                                        <small><?= date('M j', strtotime($email['sent_at'])) ?></small>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-users me-1"></i><?= $email['recipient_count'] ?> recipients
                                    </small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Email templates
const templates = {
    product: {
        subject: "🚀 Introducing New Product: [Product Name]",
        message: "Hello {name},\n\nWe're excited to announce the launch of our new product [Product Name]!\n\n[Brief description of the product and its benefits]\n\nKey Features:\n- Feature 1\n- Feature 2\n- Feature 3\n\nVisit our website to learn more: https://portal.itsupport.com.bd\n\nBest regards,\nIT Support BD Team"
    },
    update: {
        subject: "🔄 AMPNM Version [X.X.X] Released",
        message: "Hello {name},\n\nWe're pleased to announce the release of AMPNM Version [X.X.X]!\n\nWhat's New:\n- Enhancement 1\n- Bug fix 2\n- New feature 3\n\nUpdate Instructions:\n1. Pull the latest Docker image\n2. Restart your containers\n3. Verify the version\n\nFor detailed release notes, visit our documentation.\n\nBest regards,\nIT Support BD Team"
    },
    renewal: {
        subject: "🔑 License Renewal Reminder",
        message: "Hello {name},\n\nThis is a reminder that your AMPNM license will expire soon.\n\nLicense Details:\n- Expires: [Date]\n- Devices: [Count]\n\nRenew Now to avoid service interruption:\nhttps://portal.itsupport.com.bd/products.php\n\nNeed help? Contact our support team.\n\nBest regards,\nIT Support BD Team"
    },
    maintenance: {
        subject: "🔧 Scheduled Maintenance Notice",
        message: "Hello {name},\n\nWe will be performing scheduled maintenance on our portal.\n\nSchedule:\n- Date: [Date]\n- Time: [Time]\n- Duration: [Duration]\n\nDuring this time, the portal may be temporarily unavailable.\n\nWe apologize for any inconvenience.\n\nBest regards,\nIT Support BD Team"
    }
};

document.querySelectorAll('.template-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const template = templates[this.dataset.template];
        if (template) {
            document.querySelector('input[name="subject"]').value = template.subject;
            document.querySelector('textarea[name="message"]').value = template.message;
        }
    });
});
</script>

<?php include 'footer.php'; ?>
