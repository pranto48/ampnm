<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
require_once 'includes/auth_check.php';
require_once 'header.php';

$user_role = $_SESSION['user_role'] ?? 'viewer';

if ($user_role !== 'admin') {
    echo "<div class='container mx-auto px-4 py-8'><div class='bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-lg'>Access Denied: Administrator role required.</div></div>";
    if (file_exists('footer.php')) require_once 'footer.php'; else echo '</body></html>';
    exit;
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!isValidCsrfToken($csrf)) {
        $error_message = 'Invalid CSRF token.';
    } else {
        $interval = (int)($_POST['heartbeat_interval_seconds'] ?? 5);
        if ($interval < 1) $interval = 1;
        
        $collect_username = isset($_POST['collect_username']) ? '1' : '0';
        $collect_mac_address = isset($_POST['collect_mac_address']) ? '1' : '0';
        $collect_public_ip = isset($_POST['collect_public_ip']) ? '1' : '0';
        
        $warn_cpu = (float)($_POST['warn_threshold_cpu'] ?? 90.0);
        $warn_mem = (float)($_POST['warn_threshold_mem'] ?? 90.0);
        $warn_disk = (float)($_POST['warn_threshold_disk'] ?? 90.0);
        
        $offline_mult = (int)($_POST['offline_threshold_multiplier'] ?? 3);
        if ($offline_mult < 2) $offline_mult = 2;
        
        $retention_days = (int)($_POST['log_retention_days'] ?? 30);
        if ($retention_days < 1) $retention_days = 1;
        
        // Save settings to DB
        $ok1 = updateAppSetting('agent_heartbeat_interval_seconds', (string)$interval);
        $ok2 = updateAppSetting('agent_collect_username', $collect_username);
        $ok3 = updateAppSetting('agent_collect_mac_address', $collect_mac_address);
        $ok4 = updateAppSetting('agent_collect_public_ip', $collect_public_ip);
        $ok5 = updateAppSetting('agent_warn_threshold_cpu', (string)$warn_cpu);
        $ok6 = updateAppSetting('agent_warn_threshold_mem', (string)$warn_mem);
        $ok7 = updateAppSetting('agent_warn_threshold_disk', (string)$warn_disk);
        $ok8 = updateAppSetting('agent_offline_threshold_multiplier', (string)$offline_mult);
        $ok9 = updateAppSetting('agent_log_retention_days', (string)$retention_days);
        
        if ($ok1 && $ok2 && $ok3 && $ok4 && $ok5 && $ok6 && $ok7 && $ok8 && $ok9) {
            $success_message = 'Agent preferences and settings updated successfully!';
        } else {
            $error_message = 'Some settings failed to save. Please try again.';
        }
    }
}

// Load current preferences
$interval = (int)(getAppSetting('agent_heartbeat_interval_seconds') ?? 5);
$collect_username = (getAppSetting('agent_collect_username') ?? '1') === '1';
$collect_mac_address = (getAppSetting('agent_collect_mac_address') ?? '1') === '1';
$collect_public_ip = (getAppSetting('agent_collect_public_ip') ?? '1') === '1';
$warn_cpu = (float)(getAppSetting('agent_warn_threshold_cpu') ?? 90.0);
$warn_mem = (float)(getAppSetting('agent_warn_threshold_mem') ?? 90.0);
$warn_disk = (float)(getAppSetting('agent_warn_threshold_disk') ?? 90.0);
$offline_mult = (int)(getAppSetting('agent_offline_threshold_multiplier') ?? 3);
$retention_days = (int)(getAppSetting('agent_log_retention_days') ?? 30);

$csrf_token = ensureCsrfTokenInSession();
?>

<div class="container mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-white mb-1">
            <i class="fas fa-sliders text-cyan-400 mr-2"></i>Agent Settings
        </h1>
        <p class="text-slate-400 text-sm">Configure global settings, thresholds, telemetry gathering options, and data retention rules.</p>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 p-4 rounded-lg mb-6">
            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-lg mb-6">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <form action="agent_settings.php" method="POST" class="space-y-6 max-w-4xl">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Polling Configurations -->
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 shadow-lg space-y-4">
                <h3 class="text-white font-bold text-lg border-b border-slate-700 pb-2 flex items-center gap-2">
                    <i class="fas fa-clock text-cyan-400"></i>Polling Parameters
                </h3>
                
                <div>
                    <label class="block text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">Heartbeat Interval (Seconds)</label>
                    <input type="number" name="heartbeat_interval_seconds" min="1" max="3600" value="<?= $interval ?>" required
                           class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                    <p class="text-slate-500 text-xs mt-1">How often agents will send metrics payloads. Lower values use more bandwidth. Default: 5.</p>
                </div>

                <div>
                    <label class="block text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">Offline Warning Multiplier</label>
                    <input type="number" name="offline_threshold_multiplier" min="2" max="10" value="<?= $offline_mult ?>" required
                           class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                    <p class="text-slate-500 text-xs mt-1">Determines when a host is considered offline. Calculated as: <code>Multiplier * Heartbeat Interval</code>. Default: 3.</p>
                </div>
            </div>

            <!-- Warning Threshold Ratios -->
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 shadow-lg space-y-4">
                <h3 class="text-white font-bold text-lg border-b border-slate-700 pb-2 flex items-center gap-2">
                    <i class="fas fa-exclamation-triangle text-amber-500"></i>Health Alarm Thresholds
                </h3>

                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">CPU (%)</label>
                        <input type="number" name="warn_threshold_cpu" min="10" max="100" step="0.5" value="<?= $warn_cpu ?>" required
                               class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                    </div>
                    <div>
                        <label class="block text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">RAM (%)</label>
                        <input type="number" name="warn_threshold_mem" min="10" max="100" step="0.5" value="<?= $warn_mem ?>" required
                               class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                    </div>
                    <div>
                        <label class="block text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">Disk (%)</label>
                        <input type="number" name="warn_threshold_disk" min="10" max="100" step="0.5" value="<?= $warn_disk ?>" required
                               class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                    </div>
                </div>
                <p class="text-slate-500 text-xs mt-1">If a device exceeds these values, its health status changes from "online" to "warning".</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Privacy & Consent Policies -->
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 shadow-lg space-y-4">
                <h3 class="text-white font-bold text-lg border-b border-slate-700 pb-2 flex items-center gap-2">
                    <i class="fas fa-user-shield text-emerald-400"></i>Privacy & Data Compliance
                </h3>
                
                <div class="space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="collect_username" value="1" <?= $collect_username ? 'checked' : '' ?>
                               class="rounded border-slate-600 bg-slate-900 text-cyan-500 focus:ring-cyan-500 h-4 w-4">
                        <div>
                            <span class="text-slate-200 text-sm font-semibold">Collect Active OS Username</span>
                            <p class="text-slate-500 text-xs">Transmits the currently logged-in user name to the server.</p>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="collect_mac_address" value="1" <?= $collect_mac_address ? 'checked' : '' ?>
                               class="rounded border-slate-600 bg-slate-900 text-cyan-500 focus:ring-cyan-500 h-4 w-4">
                        <div>
                            <span class="text-slate-200 text-sm font-semibold">Collect MAC Address</span>
                            <p class="text-slate-500 text-xs">Includes the network hardware MAC address for identification.</p>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="collect_public_ip" value="1" <?= $collect_public_ip ? 'checked' : '' ?>
                               class="rounded border-slate-600 bg-slate-900 text-cyan-500 focus:ring-cyan-500 h-4 w-4">
                        <div>
                            <span class="text-slate-200 text-sm font-semibold">Resolve & Store Public IP Address</span>
                            <p class="text-slate-500 text-xs">Saves the remote client's source public IP address in heartbeats.</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Data Retention and Pruning -->
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 shadow-lg space-y-4">
                <h3 class="text-white font-bold text-lg border-b border-slate-700 pb-2 flex items-center gap-2">
                    <i class="fas fa-trash-alt text-purple-400"></i>Data Retention
                </h3>
                
                <div>
                    <label class="block text-slate-400 text-xs font-semibold uppercase tracking-wider mb-2">Delete Heartbeat History After (Days)</label>
                    <input type="number" name="log_retention_days" min="1" max="365" value="<?= $retention_days ?>" required
                           class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500">
                    <p class="text-slate-500 text-xs mt-1">Cleans up historical database rows to control storage growth. Default: 30.</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 text-white font-semibold rounded-lg shadow-lg transition-all flex items-center gap-2">
                <i class="fas fa-save"></i>Save Agent Policies
            </button>
        </div>
    </form>
</div>

<?php
if (file_exists('footer.php')) {
    require_once 'footer.php';
} else {
    echo '</body></html>';
}
?>
