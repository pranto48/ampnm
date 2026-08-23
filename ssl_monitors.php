<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * SSL / TLS Certificate Expiry Monitoring Dashboard
 */
require_once 'includes/auth_check.php';
require_once 'header.php';
require_once 'includes/ssl_checker.php';

$pdo = getDbConnection();
$userId = $_SESSION['user_id'] ?? 1;

// Fetch all monitored SSL domains
$stmt = $pdo->prepare("SELECT * FROM domain_ssl_monitors WHERE user_id = ? ORDER BY days_remaining ASC, id DESC");
$stmt->execute([$userId]);
$sslMonitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalCount = count($sslMonitors);
$validCount = 0;
$expiringCount = 0;
$criticalCount = 0;

foreach ($sslMonitors as $m) {
    $days = $m['days_remaining'] ?? 999;
    if ($days < 0 || $m['status'] === 'expired' || $m['status'] === 'error') {
        $criticalCount++;
    } elseif ($days <= 30) {
        $expiringCount++;
    } else {
        $validCount++;
    }
}
?>

<div class="container mx-auto px-4 py-8 max-w-7xl">
    <!-- Page Header & Metrics Summary -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold text-white flex items-center gap-3">
                <i class="fas fa-lock text-cyan-400"></i>
                SSL / TLS Certificate Expiry Tracker
            </h1>
            <p class="text-sm text-slate-400 mt-1">
                Automated SSL certificate health, renewal countdowns, and proactive expiration alerts.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" id="btnCheckAllSSL" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-xs font-semibold flex items-center gap-2 transition shadow">
                <i class="fas fa-sync-alt text-cyan-400" id="refreshIcon"></i>
                <span>Check All Certificates</span>
            </button>
            <button type="button" onclick="document.getElementById('modalAddSSL').classList.remove('hidden')" class="px-4 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white rounded-xl text-xs font-bold flex items-center gap-2 transition shadow-lg shadow-cyan-500/20">
                <i class="fas fa-plus"></i>
                <span>Add Domain Monitor</span>
            </button>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <div class="bg-slate-800/80 border border-slate-700 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400">Total Monitored</span>
                <div class="w-10 h-10 rounded-xl bg-slate-700/50 flex items-center justify-center text-slate-300">
                    <i class="fas fa-globe"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-white mt-2"><?= $totalCount ?></p>
            <p class="text-xs text-slate-500 mt-1">Active domains & endpoints</p>
        </div>

        <div class="bg-slate-800/80 border border-slate-700 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400">Valid & Healthy</span>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                    <i class="fas fa-shield-alt"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-emerald-400 mt-2"><?= $validCount ?></p>
            <p class="text-xs text-slate-500 mt-1">> 30 days remaining</p>
        </div>

        <div class="bg-slate-800/80 border border-slate-700 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400">Expiring Soon</span>
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-400">
                    <i class="fas fa-hourglass-half"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-amber-400 mt-2"><?= $expiringCount ?></p>
            <p class="text-xs text-slate-500 mt-1"><= 30 days left</p>
        </div>

        <div class="bg-slate-800/80 border border-slate-700 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400">Critical / Expired</span>
                <div class="w-10 h-10 rounded-xl bg-rose-500/10 flex items-center justify-center text-rose-400">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-rose-400 mt-2"><?= $criticalCount ?></p>
            <p class="text-xs text-slate-500 mt-1"><= 7 days or expired</p>
        </div>
    </div>

    <!-- SSL Monitors Table -->
    <div class="bg-slate-800/90 border border-slate-700 rounded-2xl shadow-xl overflow-hidden">
        <div class="p-5 border-b border-slate-700 flex justify-between items-center">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fas fa-list text-cyan-400"></i>
                Monitored SSL Endpoints
            </h3>
            <span class="text-xs text-slate-400"><?= $totalCount ?> endpoints monitored</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-900/60 border-b border-slate-700/80 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 px-5">Domain / Endpoint</th>
                        <th class="py-3.5 px-5">Common Name (CN)</th>
                        <th class="py-3.5 px-5">Issuer</th>
                        <th class="py-3.5 px-5">Valid Until</th>
                        <th class="py-3.5 px-5">Days Left</th>
                        <th class="py-3.5 px-5">Status</th>
                        <th class="py-3.5 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/60 text-slate-200">
                    <?php if (empty($sslMonitors)): ?>
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500">
                                <i class="fas fa-lock-open text-4xl mb-3 block text-slate-600"></i>
                                No SSL certificate monitors configured yet.<br>
                                Click <strong>"Add Domain Monitor"</strong> to start tracking certificate expirations.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sslMonitors as $m): 
                            $days = $m['days_remaining'] !== null ? (int)$m['days_remaining'] : null;
                            $status = $m['status'];
                            $badgeClass = 'bg-slate-700 text-slate-300';
                            $badgeText = 'Pending';

                            if ($status === 'valid') {
                                $badgeClass = 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30';
                                $badgeText = 'Valid';
                            } elseif ($status === 'expiring_soon') {
                                $badgeClass = 'bg-amber-500/10 text-amber-400 border border-amber-500/30';
                                $badgeText = 'Expiring Soon';
                            } elseif ($status === 'critical') {
                                $badgeClass = 'bg-rose-500/10 text-rose-400 border border-rose-500/30 font-bold';
                                $badgeText = 'Critical';
                            } elseif ($status === 'expired') {
                                $badgeClass = 'bg-red-600 text-white font-bold';
                                $badgeText = 'Expired';
                            } elseif ($status === 'error') {
                                $badgeClass = 'bg-rose-900/50 text-rose-300 border border-rose-700';
                                $badgeText = 'Unreachable';
                            }
                        ?>
                            <tr class="hover:bg-slate-700/30 transition">
                                <td class="py-4 px-5 font-mono font-semibold text-white">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-lock text-xs text-slate-400"></i>
                                        <span><?= htmlspecialchars($m['domain']) ?></span>
                                        <?php if ($m['port'] != 443): ?>
                                            <span class="text-xs text-slate-500">:<?= (int)$m['port'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-4 px-5 text-slate-300 font-mono text-xs">
                                    <?= htmlspecialchars($m['common_name'] ?: '—') ?>
                                </td>
                                <td class="py-4 px-5 text-slate-300 text-xs">
                                    <?= htmlspecialchars($m['issuer'] ?: '—') ?>
                                </td>
                                <td class="py-4 px-5 text-xs text-slate-300">
                                    <?= $m['valid_to'] ? date('M d, Y', strtotime($m['valid_to'])) : '—' ?>
                                </td>
                                <td class="py-4 px-5 font-semibold">
                                    <?php if ($days !== null): ?>
                                        <span class="<?= $days <= 7 ? 'text-rose-400 font-bold' : ($days <= 30 ? 'text-amber-400' : 'text-emerald-400') ?>">
                                            <?= $days ?> days
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-500">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-5">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $badgeClass ?>">
                                        <?= $badgeText ?>
                                    </span>
                                </td>
                                <td class="py-4 px-5 text-right space-x-2">
                                    <button type="button" onclick="checkSingleSSL(<?= (int)$m['id'] ?>)" class="p-2 text-cyan-400 hover:text-cyan-300 hover:bg-slate-700/60 rounded-lg transition" title="Re-check Now">
                                        <i class="fas fa-sync-alt text-xs"></i>
                                    </button>
                                    <button type="button" onclick="deleteSSL(<?= (int)$m['id'] ?>, '<?= htmlspecialchars(addslashes($m['domain'])) ?>')" class="p-2 text-rose-400 hover:text-rose-300 hover:bg-slate-700/60 rounded-lg transition" title="Delete Monitor">
                                        <i class="fas fa-trash-alt text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add SSL Monitor -->
<div id="modalAddSSL" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
    <div class="bg-slate-900 border border-slate-700 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex justify-between items-center pb-3 border-b border-slate-700 mb-5">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-plus-circle text-cyan-400"></i>
                Add Domain SSL Monitor
            </h3>
            <button type="button" onclick="document.getElementById('modalAddSSL').classList.add('hidden')" class="text-slate-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="formAddSSL" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase mb-1.5">Domain or Host</label>
                <input type="text" id="addDomain" required placeholder="e.g. google.com or myhost.net" class="w-full bg-slate-800 border border-slate-700 text-white rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-cyan-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase mb-1.5">Port</label>
                <input type="number" id="addPort" value="443" min="1" max="65535" class="w-full bg-slate-800 border border-slate-700 text-white rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-cyan-500 focus:outline-none">
            </div>

            <div class="flex justify-end gap-3 pt-3 border-t border-slate-700">
                <button type="button" onclick="document.getElementById('modalAddSSL').classList.add('hidden')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl text-xs font-bold transition shadow-lg shadow-cyan-500/20">
                    Save & Check
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('formAddSSL').addEventListener('submit', async function(e) {
    e.preventDefault();
    const domain = document.getElementById('addDomain').value.trim();
    const port = parseInt(document.getElementById('addPort').value.trim(), 10) || 443;

    if (!domain) return;

    try {
        const res = await fetch('api.php?action=create_ssl_monitor', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ domain, port })
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Failed to add SSL monitor');
        }
    } catch (err) {
        alert('Network error: ' + err.message);
    }
});

async function checkSingleSSL(id) {
    try {
        const res = await fetch('api.php?action=check_ssl_monitor', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Failed to re-check certificate');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

async function deleteSSL(id, domain) {
    if (!confirm(`Are you sure you want to remove SSL monitor for "${domain}"?`)) return;

    try {
        const res = await fetch('api.php?action=delete_ssl_monitor', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Failed to delete monitor');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

document.getElementById('btnCheckAllSSL').addEventListener('click', async function() {
    const icon = document.getElementById('refreshIcon');
    icon.classList.add('fa-spin');
    try {
        const res = await fetch('api.php?action=check_all_ssl_monitors', { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Failed to check all SSL certificates');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    } finally {
        icon.classList.remove('fa-spin');
    }
});
</script>

<?php if (file_exists('footer.php')) require_once 'footer.php'; else echo '</body></html>'; ?>
