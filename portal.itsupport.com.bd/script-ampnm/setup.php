<?php
session_start();

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['db_config'])) {
        $_SESSION['db_host'] = $_POST['db_host'];
        $_SESSION['db_user'] = $_POST['db_user'];
        $_SESSION['db_pass'] = $_POST['db_pass'];
        $_SESSION['db_name'] = $_POST['db_name'];
        
        try {
            $pdo = new PDO("mysql:host={$_POST['db_host']}", $_POST['db_user'], $_POST['db_pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $_SESSION['db_test'] = 'success';
            header('Location: setup.php?step=2');
            exit;
        } catch (PDOException $e) {
            $_SESSION['db_error'] = $e->getMessage();
            header('Location: setup.php?step=1&error=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMPNM Setup Wizard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container { 
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 700px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 { color: #1a202c; margin-bottom: 10px; font-size: 32px; }
        .subtitle { color: #718096; margin-bottom: 30px; font-size: 14px; }
        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #718096;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: bold;
            position: relative;
            z-index: 2;
        }
        .step.active .step-number { background: #667eea; color: white; }
        .step.completed .step-number { background: #48bb78; color: white; }
        .step-label { font-size: 12px; color: #718096; }
        .step.active .step-label { color: #667eea; font-weight: 600; }
        .step-line {
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
            font-size: 14px;
        }
        input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .btn {
            display: inline-block;
            padding: 12px 32px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
        }
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary { background: #e2e8f0; color: #4a5568; }
        .btn-secondary:hover { background: #cbd5e0; }
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .check-item {
            display: flex;
            align-items: center;
            padding: 12px;
            margin: 8px 0;
            border-radius: 8px;
            background: #f7fafc;
        }
        .check-ok { border-left: 4px solid #48bb78; }
        .check-error { border-left: 4px solid #f56565; }
        .check-icon { width: 24px; margin-right: 12px; }
        .progress-text {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #4a5568;
            padding: 8px;
            background: #f7fafc;
            border-radius: 6px;
            margin: 4px 0;
        }
        .btn-group { display: flex; gap: 10px; margin-top: 30px; }
        .help-text { font-size: 12px; color: #718096; margin-top: 4px; }
        .code {
            background: #2d3748;
            color: #48bb78;
            padding: 16px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin: 16px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 AMPNM Setup Wizard</h1>
        <p class="subtitle">Script Version - Easy Installation for XAMPP/LAMP</p>

        <div class="steps">
            <div class="step-line"></div>
            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                <div class="step-number">1</div>
                <div class="step-label">Database Config</div>
            </div>
            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                <div class="step-number">2</div>
                <div class="step-label">Installation</div>
            </div>
            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">
                <div class="step-number">3</div>
                <div class="step-label">Complete</div>
            </div>
        </div>

        <?php if ($step === 1): ?>
            <h2 style="margin-bottom: 20px; color: #2d3748;">Database Configuration</h2>

            <?php if (isset($_GET['error']) && isset($_SESSION['db_error'])): ?>
                <div class="alert alert-error">
                    <strong>Connection Failed:</strong><br>
                    <?php echo htmlspecialchars($_SESSION['db_error']); unset($_SESSION['db_error']); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="alert alert-info">
                    Enter your MySQL/MariaDB credentials. The database will be created automatically.
                </div>

                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" name="db_host" value="localhost" required>
                    <span class="help-text">Usually "localhost" or "127.0.0.1" for XAMPP</span>
                </div>

                <div class="form-group">
                    <label>Database Username</label>
                    <input type="text" name="db_user" value="root" required>
                    <span class="help-text">Default is "root" for XAMPP</span>
                </div>

                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" name="db_pass" value="">
                    <span class="help-text">Leave empty for default XAMPP installation</span>
                </div>

                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" name="db_name" value="network_monitor" required>
                    <span class="help-text">Will be created automatically if it doesn't exist</span>
                </div>

                <div class="btn-group">
                    <button type="submit" name="db_config" class="btn">Test & Continue →</button>
                </div>
            </form>

        <?php elseif ($step === 2): ?>
            <h2 style="margin-bottom: 20px; color: #2d3748;">Installing Database</h2>

            <div class="progress">
                <?php
                $db_host = $_SESSION['db_host'] ?? 'localhost';
                $db_user = $_SESSION['db_user'] ?? 'root';
                $db_pass = $_SESSION['db_pass'] ?? '';
                $db_name = $_SESSION['db_name'] ?? 'network_monitor';

                try {
                    echo "<div class='progress-text'>→ Connecting to MySQL...</div>";
                    $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    echo "<div class='progress-text' style='color: #48bb78;'>✓ Connected!</div>";

                    echo "<div class='progress-text'>→ Creating database '$db_name'...</div>";
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    echo "<div class='progress-text' style='color: #48bb78;'>✓ Database created!</div>";

                    echo "<div class='progress-text'>→ Connecting to database...</div>";
                    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    echo "<div class='progress-text'>→ Creating tables...</div>";
                    include('database_setup_core.php');
                    echo "<div class='progress-text' style='color: #48bb78;'>✓ All tables created!</div>";

                    echo "<div class='progress-text'>→ Saving configuration...</div>";
                    $config_template = file_get_contents('config.php.template');
                    $config_content = "<?php\n";
                    $config_content .= "// Database configuration for XAMPP/LAMP\n";
                    $config_content .= "define('DB_SERVER', '$db_host');\n";
                    $config_content .= "define('DB_USERNAME', '$db_user');\n";
                    $config_content .= "define('DB_PASSWORD', '$db_pass');\n";
                    $config_content .= "define('DB_NAME', '$db_name');\n\n";
                    $config_content .= "// License System Configuration\n";
                    $config_content .= "define('LICENSE_API_URL', 'https://portal.itsupport.com.bd/verify_license.php');\n";
                    $config_content .= "define('APP_LICENSE_KEY_ENV', '');\n";
                    $config_content .= "define('LICENSE_DATA_KEY', '');\n";
                    $config_content .= $config_template;

                    file_put_contents('config.php', $config_content);
                    echo "<div class='progress-text' style='color: #48bb78;'>✓ Configuration saved!</div>";

                    echo "<div class='alert alert-success' style='margin-top: 20px;'>";
                    echo "<strong>🎉 Installation Complete!</strong><br>";
                    echo "Database and all tables created successfully.";
                    echo "</div>";

                    echo "<div class='alert alert-info'>";
                    echo "<strong>Default Login:</strong><br>";
                    echo "Username: <strong>admin</strong><br>";
                    echo "Password: <strong>admin123</strong><br>";
                    echo "<small>⚠️ Change password after first login!</small>";
                    echo "</div>";

                    echo "<div class='btn-group'>";
                    echo "<a href='index.php' class='btn'>Go to Dashboard →</a>";
                    echo "</div>";

                } catch (PDOException $e) {
                    echo "<div class='alert alert-error'>";
                    echo "<strong>Error:</strong><br>";
                    echo htmlspecialchars($e->getMessage());
                    echo "</div>";
                    echo "<div class='btn-group'>";
                    echo "<a href='setup.php?step=1' class='btn'>← Try Again</a>";
                    echo "</div>";
                }
                ?>
            </div>
        <?php endif; ?>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e2e8f0; text-align: center; color: #718096; font-size: 13px;">
            AMPNM Script Version | <a href="https://portal.itsupport.com.bd" style="color: #667eea;">portal.itsupport.com.bd</a>
        </div>
    </div>
</body>
</html>
