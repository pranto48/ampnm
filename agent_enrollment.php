<?php
require_once 'includes/auth_check.php';
require_once 'header.php';

$user_role = $_SESSION['user_role'] ?? 'viewer';

if ($user_role !== 'admin') {
    echo "<div class='container mx-auto px-4 py-8'><div class='bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-lg'>Access Denied: Administrator role required.</div></div>";
    require_once 'footer.php'; // Wait, does footer.php exist? Let's check or just end the file without footer if not.
    exit;
}

$pdo = getDbConnection();
$success_message = '';
$error_message = '';
$generated_token = '';

// Handle Onboarding Token Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_token') {
    // Validate CSRF
    $csrf = $_POST['csrf_token'] ?? '';
    if (!isValidCsrfToken($csrf)) {
        $error_message = 'Invalid CSRF token.';
    } else {
        $name = trim($_POST['token_name'] ?? '');
        $expiry = trim($_POST['expires_at'] ?? '');
        
        if (empty($name)) {
            $error_message = 'Token name is required.';
        } else {
            try {
                // Generate secure random enrollment token
                $plaintext_token = 'ampnm_' . bin2hex(random_bytes(16));
                $token_hash = hash('sha256', $plaintext_token);
                
                $expires_at = !empty($expiry) ? date('Y-m-d H:i:s', strtotime($expiry)) : null;
                $created_by = $_SESSION['user_id'] ?? null;
                
                $stmt = $pdo->prepare("INSERT INTO agent_enrollment_tokens (token_hash, name, expires_at, created_by, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$token_hash, $name, $expires_at, $created_by]);
                
                $success_message = 'Enrollment token created successfully!';
                $generated_token = $plaintext_token; // Saved to display exactly once
            } catch (Exception $e) {
                $error_message = 'Error creating token: ' . $e->getMessage();
            }
        }
    }
}

// Handle Token Status Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['toggle_active', 'delete_token'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!isValidCsrfToken($csrf)) {
        $error_message = 'Invalid CSRF token.';
    } else {
        $token_id = (int)($_POST['token_id'] ?? 0);
        if ($token_id > 0) {
            try {
                if ($_POST['action'] === 'toggle_active') {
                    $stmt = $pdo->prepare("UPDATE agent_enrollment_tokens SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$token_id]);
                    $success_message = 'Token status updated.';
                } elseif ($_POST['action'] === 'delete_token') {
                    $stmt = $pdo->prepare("DELETE FROM agent_enrollment_tokens WHERE id = ?");
                    $stmt->execute([$token_id]);
                    $success_message = 'Token deleted.';
                }
            } catch (Exception $e) {
                $error_message = 'Error updating token: ' . $e->getMessage();
            }
        }
    }
}

// Retrieve active tokens list
$stmt = $pdo->query("SELECT aet.*, u.username as creator_name FROM agent_enrollment_tokens aet 
                     LEFT JOIN users u ON aet.created_by = u.id 
                     ORDER BY aet.created_at DESC");
$tokens = $stmt->fetchAll();

$csrf_token = ensureCsrfTokenInSession();
?>

<div class="container mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-white mb-1">
            <i class="fas fa-key text-cyan-400 mr-2"></i>Agent Enrollment
        </h1>
        <p class="text-slate-400 text-sm">Manage enrollment tokens used to authorize new Windows Usage Agents.</p>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 p-4 rounded-lg mb-6 flex items-center justify-between">
            <div>
                <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_message) ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-lg mb-6 flex items-center justify-between">
            <div>
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error_message) ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($generated_token)): ?>
        <div class="bg-amber-500/10 border-2 border-amber-500/40 rounded-xl p-6 mb-6 shadow-xl relative overflow-hidden">
            <div class="absolute right-0 top-0 opacity-10 transform translate-x-8 -translate-y-8 pointer-events-none">
                <i class="fas fa-key text-[150px] text-amber-400"></i>
            </div>
            <h3 class="text-amber-400 font-bold text-lg mb-2 flex items-center">
                <i class="fas fa-exclamation-triangle mr-2 text-xl animate-pulse"></i>Copy Token Immediately
            </h3>
            <p class="text-slate-300 text-sm mb-4">
                This token is cryptographically hashed and <strong>cannot</strong> be retrieved later. Copy it now to configure your client agents.
            </p>
            <div class="flex items-center gap-3 bg-slate-900 border border-slate-700 rounded-lg p-3">
                <code class="text-green-400 text-sm select-all font-mono break-all flex-1" id="rawTokenCode"><?= htmlspecialchars($generated_token) ?></code>
                <button onclick="copyRawToken()" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-semibold transition-colors flex items-center gap-2">
                    <i class="fas fa-copy"></i>Copy
                </button>
            </div>
        </div>
        <script>
            function copyRawToken() {
                const text = document.getElementById('rawTokenCode').textContent;
                navigator.clipboard.writeText(text).then(() => {
                    alert('Token copied to clipboard!');
                }).catch(() => {
                    const el = document.createElement('textarea');
                    el.value = text;
                    document.body.appendChild(el);
                    el.select();
                    document.execCommand('copy');
                    document.body.removeChild(el);
                    alert('Token copied to clipboard!');
                });
            }
        </script>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Token Creation Form -->
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 shadow-lg h-fit">
            <h3 class="text-white font-bold text-lg mb-4 flex items-center gap-2">
                <i class="fas fa-plus text-cyan-400"></i>Create Onboarding Token
            </h3>
            <form action="agent_enrollment.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="create_token">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div>
                    <label class="block text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">Token Name / Description</label>
                    <input type="text" name="token_name" placeholder="e.g. Finance Dept Laptops" required
                           class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                </div>

                <div>
                    <label class="block text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">Expiration Date (Optional)</label>
                    <input type="date" name="expires_at"
                           class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                    <p class="text-slate-500 text-xs mt-1">Leave empty for a non-expiring token.</p>
                </div>

                <button type="submit" class="w-full py-2 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 text-white rounded-lg text-sm font-semibold transition-all shadow-lg flex items-center justify-center gap-2">
                    <i class="fas fa-plus-circle"></i>Generate Token
                </button>
            </form>
        </div>

        <!-- Token Listing Table -->
        <div class="lg:col-span-2 bg-slate-800/50 border border-slate-700 rounded-xl p-6 shadow-lg">
            <h3 class="text-white font-bold text-lg mb-4 flex items-center gap-2">
                <i class="fas fa-list-ul text-cyan-400"></i>Active Enrollment Tokens
            </h3>
            
            <div class="overflow-x-auto">
                <table class="min-w-full text-slate-300 text-sm">
                    <thead>
                        <tr class="border-b border-slate-700 text-slate-400 text-xs uppercase tracking-wider bg-slate-900/40">
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Creator</th>
                            <th class="px-4 py-3 text-center">Expires</th>
                            <th class="px-4 py-3 text-center">Last Used</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php if (empty($tokens)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">No enrollment tokens found. Generate one on the left to start onboarding.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tokens as $token): 
                                $is_expired = $token['expires_at'] !== null && strtotime($token['expires_at']) < time();
                                $status_text = 'Active';
                                $status_class = 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
                                
                                if (!$token['is_active']) {
                                    $status_text = 'Revoked';
                                    $status_class = 'bg-red-500/10 text-red-400 border border-red-500/20';
                                } elseif ($is_expired) {
                                    $status_text = 'Expired';
                                    $status_class = 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
                                }
                            ?>
                                <tr class="hover:bg-slate-800/30 transition-colors">
                                    <td class="px-4 py-3 font-semibold text-white"><?= htmlspecialchars($token['name']) ?></td>
                                    <td class="px-4 py-3 text-slate-400 text-xs"><?= htmlspecialchars($token['creator_name'] ?? 'System') ?></td>
                                    <td class="px-4 py-3 text-center text-xs text-slate-400">
                                        <?= $token['expires_at'] ? date('M d, Y', strtotime($token['expires_at'])) : '<span class="text-slate-600">—</span>' ?>
                                    </td>
                                    <td class="px-4 py-3 text-center text-xs text-slate-400">
                                        <?= $token['last_used_at'] ? date('M d, H:i', strtotime($token['last_used_at'])) : '<span class="text-slate-600">Never</span>' ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $status_class ?>">
                                            <?= $status_text ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <!-- Toggle Status Form -->
                                            <form action="agent_enrollment.php" method="POST" onsubmit="return confirm('Toggle status of this token?');">
                                                <input type="hidden" name="action" value="toggle_active">
                                                <input type="hidden" name="token_id" value="<?= $token['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <button type="submit" class="p-1 px-2 text-xs bg-slate-700/60 hover:bg-slate-600 rounded text-slate-200 border border-slate-600 transition-colors">
                                                    <?= $token['is_active'] ? 'Revoke' : 'Activate' ?>
                                                </button>
                                            </form>

                                            <!-- Delete Form -->
                                            <form action="agent_enrollment.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this token permanently?');">
                                                <input type="hidden" name="action" value="delete_token">
                                                <input type="hidden" name="token_id" value="<?= $token['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <button type="submit" class="p-1 px-2 text-xs bg-red-950/40 hover:bg-red-900/60 border border-red-800 text-red-400 rounded transition-colors">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// Footers are handled inside header.php usually, or we close body and html if no footer.php exists.
// Let's check if footer.php exists before requiring it.
if (file_exists('footer.php')) {
    require_once 'footer.php';
} else {
    echo '</body></html>';
}
?>
