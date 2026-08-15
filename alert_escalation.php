<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM Multi-Tier Incident Alert & Escalation Management
 */
require_once 'includes/bootstrap.php';
require_once 'includes/auth_check.php';

if (($_SESSION['user_role'] ?? 'viewer') !== 'admin') {
    header('Location: index.php');
    exit;
}

require_once 'header.php';
?>

<div class="container mx-auto px-4 py-6 max-w-6xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-layer-group text-cyan-400"></i> Multi-Tier Escalation & Webhook Integration
            </h1>
            <p class="text-slate-400 text-sm mt-1">Manage shift schedules, multi-level alert escalations, and Slack / Discord / PagerDuty webhooks.</p>
        </div>
        <a href="alert_settings.php" class="text-cyan-400 hover:text-cyan-300 text-sm flex items-center gap-1">
            <i class="fas fa-arrow-left"></i> Alert Settings
        </a>
    </div>

    <!-- Webhook Integration Card -->
    <div class="bg-slate-800/50 rounded-xl border border-slate-700/50 p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
            <i class="fas fa-plug text-cyan-400"></i> Webhook Channels (Slack, Discord, MS Teams, PagerDuty)
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-slate-300 text-sm font-medium mb-1">Slack Webhook URL</label>
                <input type="url" id="slack-webhook" placeholder="https://hooks.slack.com/services/..." class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:border-cyan-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-slate-300 text-sm font-medium mb-1">Discord Webhook URL</label>
                <input type="url" id="discord-webhook" placeholder="https://discord.com/api/webhooks/..." class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:border-cyan-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-slate-300 text-sm font-medium mb-1">PagerDuty Integration Key</label>
                <input type="text" id="pagerduty-key" placeholder="PAGERDUTY_ROUTING_KEY" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:border-cyan-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-slate-300 text-sm font-medium mb-1">Custom JSON Webhook</label>
                <input type="url" id="custom-webhook" placeholder="https://api.yourcompany.com/alerts" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:border-cyan-500 focus:outline-none">
            </div>
        </div>
    </div>

    <!-- On-Call Escalation Rules Card -->
    <div class="bg-slate-800/50 rounded-xl border border-slate-700/50 p-6">
        <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
            <i class="fas fa-user-clock text-cyan-400"></i> Multi-Level Incident Escalation Policy
        </h2>
        <div class="space-y-4">
            <div class="bg-slate-900/60 border border-slate-700/60 rounded-lg p-4 flex items-center justify-between">
                <div>
                    <span class="text-cyan-400 font-semibold text-sm">Tier 1: Immediate Alert (0 Min)</span>
                    <p class="text-slate-400 text-xs mt-1">Send Instant Email & Telegram notifications to On-Call Engineer.</p>
                </div>
                <span class="px-3 py-1 bg-green-500/20 text-green-400 border border-green-500/30 rounded-full text-xs font-semibold">Active</span>
            </div>
            <div class="bg-slate-900/60 border border-slate-700/60 rounded-lg p-4 flex items-center justify-between">
                <div>
                    <span class="text-amber-400 font-semibold text-sm">Tier 2: Escalation (5 Mins Unacknowledged)</span>
                    <p class="text-slate-400 text-xs mt-1">Send High-Priority SMS & WhatsApp message to Shift Lead.</p>
                </div>
                <span class="px-3 py-1 bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-full text-xs font-semibold">Active</span>
            </div>
            <div class="bg-slate-900/60 border border-slate-700/60 rounded-lg p-4 flex items-center justify-between">
                <div>
                    <span class="text-red-400 font-semibold text-sm">Tier 3: Executive Escalation (15 Mins Unacknowledged)</span>
                    <p class="text-slate-400 text-xs mt-1">Trigger PagerDuty incident & dispatch Alert to IT Operations Manager.</p>
                </div>
                <span class="px-3 py-1 bg-red-500/20 text-red-400 border border-red-500/30 rounded-full text-xs font-semibold">Active</span>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
