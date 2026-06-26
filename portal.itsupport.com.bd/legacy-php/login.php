<?php
require_once 'includes/functions.php';

// Redirect if already logged in
if (isCustomerLoggedIn()) {
    redirectToDashboard();
}

$error_message = '';

// Generate captcha if not exists in session
if (!isset($_SESSION['captcha_answer'])) {
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $_SESSION['captcha_num1'] = $num1;
    $_SESSION['captcha_num2'] = $num2;
    $_SESSION['captcha_answer'] = $num1 + $num2;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha_response = trim($_POST['captcha'] ?? '');

    if (empty($email) || empty($password)) {
        $error_message = 'Email and password are required.';
    } elseif (empty($captcha_response)) {
        $error_message = 'Please solve the math problem.';
    } elseif ((int)$captcha_response !== (int)$_SESSION['captcha_answer']) {
        $error_message = 'Incorrect answer to math problem. Please try again.';
        // Regenerate captcha
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $_SESSION['captcha_num1'] = $num1;
        $_SESSION['captcha_num2'] = $num2;
        $_SESSION['captcha_answer'] = $num1 + $num2;
    } else {
        if (authenticateCustomer($email, $password)) {
            // Clear captcha on successful login
            unset($_SESSION['captcha_answer'], $_SESSION['captcha_num1'], $_SESSION['captcha_num2']);
            redirectToDashboard();
        } else {
            $error_message = 'Invalid email or password.';
            // Regenerate captcha on failed login
            $num1 = rand(1, 10);
            $num2 = rand(1, 10);
            $_SESSION['captcha_num1'] = $num1;
            $_SESSION['captcha_num2'] = $num2;
            $_SESSION['captcha_answer'] = $num1 + $num2;
        }
    }
}

portal_header("Login - IT Support BD Portal");
?>

<div class="min-h-screen grid grid-cols-1 lg:grid-cols-2 gap-6 py-12 px-4 sm:px-6 lg:px-8 relative">
    <div class="animated-grid"></div>
    <div class="glass-card p-10 space-y-8 form-fade-in tilt-card user-login-card">
        <div class="tilt-inner space-y-6">
            <div class="flex items-center justify-between">
                <span class="accent-badge"><i class="fas fa-lock"></i>Customer Login</span>
                <a href="registration.php" class="text-sm text-blue-200 hover:underline">Need an account?</a>
            </div>
            <div class="space-y-2">
                <h1 class="text-3xl font-bold text-white">Welcome back, operator</h1>
                <p class="text-gray-300">Sign in to track licenses, devices, and support tickets with a dashboard tuned for uptime.</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert-glass-error mb-2">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="space-y-5">
                <div>
                    <label for="email" class="block text-sm text-gray-300 mb-2">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="email" required
                           class="form-glass-input"
                           placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div>
                    <label for="password" class="block text-sm text-gray-300 mb-2">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="form-glass-input"
                           placeholder="••••••••">
                </div>

                <div class="bg-gradient-to-r from-blue-900/30 to-cyan-900/30 border border-blue-500/30 rounded-lg p-4">
                    <label for="captcha" class="block text-sm text-gray-300 mb-2 flex items-center">
                        <i class="fas fa-robot mr-2 text-cyan-400"></i>Security Check
                    </label>
                    <div class="flex items-center gap-3">
                        <div class="bg-slate-800 px-4 py-2 rounded-lg border border-slate-600 font-mono text-xl text-white">
                            <?= $_SESSION['captcha_num1'] ?> + <?= $_SESSION['captcha_num2'] ?> = ?
                        </div>
                        <input id="captcha" name="captcha" type="number" required
                               class="form-glass-input w-24"
                               placeholder="?" min="0" max="20">
                    </div>
                    <p class="text-xs text-gray-400 mt-2"><i class="fas fa-shield-alt mr-1"></i>This prevents automated bot attacks</p>
                </div>

                <button type="submit" class="btn-glass-primary w-full flex justify-center items-center">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login to Portal
                </button>
            </form>

            <div class="login-meta grid grid-cols-1 sm:grid-cols-3 gap-4 text-gray-200">
                <div class="meta-pill"><i class="fas fa-mobile-alt"></i> Mobile-first UI</div>
                <div class="meta-pill"><i class="fas fa-bell"></i> Renewal alerts</div>
                <div class="meta-pill"><i class="fas fa-hands-helping"></i> Human support</div>
            </div>
        </div>
    </div>

    <div class="glass-card p-10 space-y-5 tilt-card user-login-aside">
        <div class="tilt-inner space-y-5">
            <h2 class="text-2xl font-semibold text-white">Stay synced on the move</h2>
            <p class="text-gray-300">Thumb-friendly spacing, adaptive cards, and focused contrast keep AMPNM usable in server rooms and on mobile devices.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-gray-200">
                <div class="glass-chip">
                    <p class="text-sm uppercase text-blue-200">3D Motion</p>
                    <p class="text-lg font-semibold">Subtle parallax, focus-ready forms.</p>
                </div>
                <div class="glass-chip">
                    <p class="text-sm uppercase text-blue-200">Support</p>
                    <p class="text-lg font-semibold">Direct access to our engineers.</p>
                </div>
            </div>
            <ul class="text-gray-200 space-y-2 list-disc list-inside">
                <li>Faster navigation for license renewals.</li>
                <li>Save time with prefilled account data.</li>
                <li>Stay synced with your AMPNM Docker nodes.</li>
            </ul>
            <div class="inline-flex items-center gap-3 text-blue-200 text-sm">
                <span class="status-dot"></span> Optimized for low-light NOC environments
            </div>
        </div>
    </div>
</div>

<?php portal_footer(); ?>
