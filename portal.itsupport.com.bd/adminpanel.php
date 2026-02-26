<?php
require_once 'includes/functions.php';

// Redirect if already logged in
if (isAdminLoggedIn()) {
    header('Location: admin/index.php'); // Redirect to actual admin dashboard
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Username and password are required.';
    } else {
        if (authenticateAdmin($username, $password)) {
            header('Location: admin/index.php'); // Redirect to actual admin dashboard
            exit;
        } else {
            $error_message = 'Invalid username or password.';
        }
    }
}

admin_header("Admin Login");
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 admin-body relative">
    <div class="animated-grid"></div>
    <div class="admin-circuit"></div>
    <div class="max-w-6xl w-full grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="admin-card p-10 form-fade-in tilt-card admin-login-card">
            <div class="tilt-inner space-y-6">
                <div class="flex items-center justify-between mb-1">
                    <span class="accent-badge"><i class="fas fa-user-shield"></i>Admin Console</span>
                    <span class="admin-chip"><i class="fas fa-satellite-dish"></i> NOC Only</span>
                </div>
                <div class="space-y-2">
                    <h1 class="text-3xl font-bold text-gray-100">Authenticate operations</h1>
                    <p class="text-gray-300">Access license orchestration, billing controls, and ticket triage behind a hardened UI.</p>
                </div>

                <?php if ($error_message): ?>
                    <div class="alert-admin-error mb-2">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <form action="adminpanel.php" method="POST" class="mt-4 space-y-5">
                    <div>
                        <label for="username" class="block text-sm text-gray-300 mb-2">Username</label>
                        <input id="username" name="username" type="text" autocomplete="username" required
                               class="form-admin-input"
                               placeholder="admin" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="password" class="block text-sm text-gray-300 mb-2">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                               class="form-admin-input"
                               placeholder="••••••••">
                    </div>

                    <div class="flex items-center justify-between text-xs text-gray-400">
                        <span class="inline-flex items-center gap-2"><span class="status-dot"></span> Session audit enabled</span>
                        <span class="inline-flex items-center gap-2"><i class="fas fa-fingerprint"></i> MFA recommended</span>
                    </div>

                    <button type="submit" class="btn-admin-primary w-full flex justify-center items-center">
                        <i class="fas fa-sign-in-alt mr-2"></i>Enter Admin Panel
                    </button>
                </form>
            </div>
        </div>

        <div class="admin-card p-10 tilt-card admin-briefing">
            <div class="tilt-inner space-y-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-semibold text-white">Operations brief</h2>
                    <span class="admin-chip"><i class="fas fa-bolt"></i> Live</span>
                </div>
                <p class="text-gray-300">Distinct neon cues, darker contrast, and circuit overlays keep the admin surface visually separate from the customer portal.</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-gray-200">
                    <div class="intel-card">
                        <p class="text-sm uppercase text-blue-200">Licenses</p>
                        <p class="text-2xl font-semibold">Renewals & provisioning</p>
                    </div>
                    <div class="intel-card">
                        <p class="text-sm uppercase text-blue-200">Payments</p>
                        <p class="text-2xl font-semibold">Billing reconciliations</p>
                    </div>
                    <div class="intel-card">
                        <p class="text-sm uppercase text-blue-200">Support</p>
                        <p class="text-2xl font-semibold">Ticket escalations</p>
                    </div>
                </div>
                <div class="admin-ribbon">
                    <i class="fas fa-lock mr-2"></i> Backend endpoints are isolated; use admin credentials only.
                </div>
            </div>
        </div>
    </div>
</div>

<?php admin_footer(); ?>
