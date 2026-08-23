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
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-layer-group text-cyan-400"></i> Multi-Tier Escalation & Webhook Integration
            </h1>
            <p class="text-slate-400 text-sm mt-1">Configure automated shift escalations, delayed incident routing, and live Slack / Discord / MS Teams / PagerDuty webhooks.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="alert_settings.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 rounded-lg text-sm transition-colors flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Alert Settings
            </a>
            <button type="button" id="save-all-btn" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-sm font-semibold shadow-lg shadow-cyan-600/30 transition-all flex items-center gap-2">
                <i class="fas fa-save"></i> Save All Policies
            </button>
        </div>
    </div>

    <!-- Alert Toast Notification Area -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-2 pointer-events-none"></div>

    <!-- Webhook Integration Card -->
    <div class="bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 p-6 mb-6 shadow-xl">
        <div class="flex items-center justify-between mb-4 border-b border-slate-700/60 pb-3">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                <i class="fas fa-plug text-cyan-400"></i> Webhook Channels (Slack, Discord, MS Teams, PagerDuty)
            </h2>
            <span class="text-xs text-slate-400 bg-slate-900/80 border border-slate-700 px-2.5 py-1 rounded-full">Real-time Webhook Dispatcher</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Slack -->
            <div class="bg-slate-900/70 border border-slate-700/70 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-slate-200 text-sm font-semibold flex items-center gap-2">
                        <i class="fab fa-slack text-amber-400 text-base"></i> Slack Incoming Webhook
                    </label>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="slack-enabled" class="sr-only peer" checked>
                        <div class="w-9 h-5 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-cyan-600"></div>
                    </label>
                </div>
                <div class="flex gap-2">
                    <input type="url" id="slack-url" placeholder="https://hooks.slack.com/services/..." class="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-xs font-mono focus:border-cyan-500 focus:outline-none">
                    <button type="button" onclick="testWebhook('slack')" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-cyan-400 border border-slate-600 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fas fa-paper-plane"></i> Test
                    </button>
                </div>
            </div>

            <!-- Discord -->
            <div class="bg-slate-900/70 border border-slate-700/70 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-slate-200 text-sm font-semibold flex items-center gap-2">
                        <i class="fab fa-discord text-indigo-400 text-base"></i> Discord Webhook
                    </label>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="discord-enabled" class="sr-only peer" checked>
                        <div class="w-9 h-5 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-cyan-600"></div>
                    </label>
                </div>
                <div class="flex gap-2">
                    <input type="url" id="discord-url" placeholder="https://discord.com/api/webhooks/..." class="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-xs font-mono focus:border-cyan-500 focus:outline-none">
                    <button type="button" onclick="testWebhook('discord')" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-cyan-400 border border-slate-600 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fas fa-paper-plane"></i> Test
                    </button>
                </div>
            </div>

            <!-- Microsoft Teams -->
            <div class="bg-slate-900/70 border border-slate-700/70 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-slate-200 text-sm font-semibold flex items-center gap-2">
                        <i class="fab fa-microsoft text-blue-400 text-base"></i> Microsoft Teams Connector
                    </label>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="msteams-enabled" class="sr-only peer" checked>
                        <div class="w-9 h-5 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-cyan-600"></div>
                    </label>
                </div>
                <div class="flex gap-2">
                    <input type="url" id="msteams-url" placeholder="https://outlook.office.com/webhook/..." class="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-xs font-mono focus:border-cyan-500 focus:outline-none">
                    <button type="button" onclick="testWebhook('msteams')" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-cyan-400 border border-slate-600 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fas fa-paper-plane"></i> Test
                    </button>
                </div>
            </div>

            <!-- PagerDuty -->
            <div class="bg-slate-900/70 border border-slate-700/70 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-slate-200 text-sm font-semibold flex items-center gap-2">
                        <i class="fas fa-pager text-emerald-400 text-base"></i> PagerDuty Events Integration
                    </label>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="pagerduty-enabled" class="sr-only peer" checked>
                        <div class="w-9 h-5 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-cyan-600"></div>
                    </label>
                </div>
                <div class="flex gap-2">
                    <input type="text" id="pagerduty-key" placeholder="PAGERDUTY_ROUTING_KEY (32-char hex)" class="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-xs font-mono focus:border-cyan-500 focus:outline-none">
                    <button type="button" onclick="testWebhook('pagerduty')" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-cyan-400 border border-slate-600 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fas fa-paper-plane"></i> Test
                    </button>
                </div>
            </div>

            <!-- Custom JSON Webhook -->
            <div class="md:col-span-2 bg-slate-900/70 border border-slate-700/70 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-slate-200 text-sm font-semibold flex items-center gap-2">
                        <i class="fas fa-code text-cyan-400 text-base"></i> Custom JSON Endpoint Webhook
                    </label>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="custom-enabled" class="sr-only peer">
                        <div class="w-9 h-5 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-cyan-600"></div>
                    </label>
                </div>
                <div class="flex gap-2">
                    <input type="url" id="custom-url" placeholder="https://api.yourdomain.com/v1/network/alerts" class="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-xs font-mono focus:border-cyan-500 focus:outline-none">
                    <button type="button" onclick="testWebhook('custom')" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-cyan-400 border border-slate-600 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fas fa-paper-plane"></i> Test
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- On-Call Escalation Rules Card -->
    <div class="bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 p-6 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 border-b border-slate-700/60 pb-3">
            <div>
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-user-clock text-cyan-400"></i> Multi-Level Incident Escalation Policy
                </h2>
                <p class="text-slate-400 text-xs mt-0.5">Alerts are routed to higher tiers if unacknowledged or unrecovered within the specified delay time.</p>
            </div>
            <button type="button" onclick="addEscalationTier()" class="px-3.5 py-1.5 bg-slate-700 hover:bg-slate-600 text-cyan-400 border border-slate-600 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 self-start">
                <i class="fas fa-plus"></i> Add Tier
            </button>
        </div>

        <div id="escalation-tiers-container" class="space-y-4">
            <!-- Dynamically populated via JS -->
            <div class="text-center py-8 text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading escalation policies...</div>
        </div>
    </div>
</div>

<script>
let escalationRules = [];

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    const bg = type === 'success' ? 'bg-emerald-600 border-emerald-500' : 'bg-red-600 border-red-500';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
    toast.className = `${bg} text-white px-4 py-3 rounded-lg border shadow-xl flex items-center gap-3 text-sm transition-all duration-300 pointer-events-auto transform translate-y-2 opacity-0`;
    toast.innerHTML = `<i class="fas ${icon}"></i> <span>${message}</span>`;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-y-2', 'opacity-0');
    }, 10);
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

async function loadSettings() {
    try {
        const resp = await fetch('api.php?action=get_escalation_settings');
        const data = await resp.json();
        if (!data.success) return;

        // Populate Webhooks
        data.webhooks.forEach(w => {
            if (w.type === 'slack') {
                document.getElementById('slack-url').value = w.url || '';
                document.getElementById('slack-enabled').checked = w.is_enabled == 1;
            } else if (w.type === 'discord') {
                document.getElementById('discord-url').value = w.url || '';
                document.getElementById('discord-enabled').checked = w.is_enabled == 1;
            } else if (w.type === 'msteams') {
                document.getElementById('msteams-url').value = w.url || '';
                document.getElementById('msteams-enabled').checked = w.is_enabled == 1;
            } else if (w.type === 'pagerduty') {
                document.getElementById('pagerduty-key').value = w.routing_key || w.url || '';
                document.getElementById('pagerduty-enabled').checked = w.is_enabled == 1;
            } else if (w.type === 'custom') {
                document.getElementById('custom-url').value = w.url || '';
                document.getElementById('custom-enabled').checked = w.is_enabled == 1;
            }
        });

        // Populate Rules
        escalationRules = data.rules && data.rules.length > 0 ? data.rules : [
            { id: '1', level: 1, delay_minutes: 0, channels: ['telegram', 'sms'], recipients: 'On-Duty NOC Engineer', is_enabled: 1 },
            { id: '2', level: 2, delay_minutes: 15, channels: ['discord', 'email', 'slack'], recipients: 'NOC Lead / SysAdmin', is_enabled: 1 },
            { id: '3', level: 3, delay_minutes: 30, channels: ['pagerduty', 'msteams', 'sms'], recipients: 'IT Operations Manager', is_enabled: 1 }
        ];

        renderEscalationTiers();
    } catch (e) {
        console.error('Failed to load escalation settings', e);
    }
}

function renderEscalationTiers() {
    const container = document.getElementById('escalation-tiers-container');
    container.innerHTML = '';

    escalationRules.forEach((rule, index) => {
        const channels = Array.isArray(rule.channels) ? rule.channels : (typeof rule.channels === 'string' ? JSON.parse(rule.channels || '[]') : []);
        const delayBadge = rule.delay_minutes == 0 ? 'Immediate (0 Mins)' : `${rule.delay_minutes} Mins Unacknowledged`;
        const tierCard = document.createElement('div');
        tierCard.className = 'bg-slate-900/80 border border-slate-700/80 rounded-xl p-5 shadow-md';
        tierCard.innerHTML = `
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-800">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-full bg-cyan-600/20 text-cyan-400 border border-cyan-500/30 flex items-center justify-center font-bold text-sm">#${index + 1}</span>
                    <div>
                        <h4 class="text-white font-semibold text-sm">Tier ${index + 1} Policy</h4>
                        <span class="text-xs text-cyan-400 font-mono">${delayBadge}</span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer">
                        <input type="checkbox" onchange="toggleTier(${index}, this.checked)" ${rule.is_enabled == 1 ? 'checked' : ''} class="rounded border-slate-600 bg-slate-800 text-cyan-500">
                        <span>Active</span>
                    </label>
                    <button type="button" onclick="removeEscalationTier(${index})" class="text-slate-500 hover:text-red-400 transition-colors p-1.5" title="Remove Tier">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <div>
                    <label class="block text-slate-400 font-medium mb-1">Delay Before Trigger</label>
                    <select onchange="updateTierField(${index}, 'delay_minutes', parseInt(this.value))" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                        <option value="0" ${rule.delay_minutes == 0 ? 'selected' : ''}>0 Mins (Immediate on down)</option>
                        <option value="5" ${rule.delay_minutes == 5 ? 'selected' : ''}>5 Mins Unacknowledged</option>
                        <option value="10" ${rule.delay_minutes == 10 ? 'selected' : ''}>10 Mins Unacknowledged</option>
                        <option value="15" ${rule.delay_minutes == 15 ? 'selected' : ''}>15 Mins Unacknowledged</option>
                        <option value="30" ${rule.delay_minutes == 30 ? 'selected' : ''}>30 Mins Unacknowledged</option>
                        <option value="60" ${rule.delay_minutes == 60 ? 'selected' : ''}>60 Mins Unacknowledged</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-400 font-medium mb-1">Assigned Role / Recipient</label>
                    <input type="text" value="${rule.recipients || ''}" onchange="updateTierField(${index}, 'recipients', this.value)" placeholder="e.g. NOC Shift Lead" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-slate-400 font-medium mb-1">Dispatch Channels</label>
                    <div class="flex flex-wrap gap-2 pt-1">
                        ${['telegram', 'whatsapp', 'sms', 'email', 'slack', 'discord', 'msteams', 'pagerduty'].map(ch => `
                            <label class="inline-flex items-center gap-1.5 px-2 py-1 bg-slate-800 border ${channels.includes(ch) ? 'border-cyan-500 text-cyan-300' : 'border-slate-700 text-slate-400'} rounded text-xs cursor-pointer select-none">
                                <input type="checkbox" onchange="toggleChannel(${index}, '${ch}', this.checked)" ${channels.includes(ch) ? 'checked' : ''} class="hidden">
                                <span>${ch.toUpperCase()}</span>
                            </label>
                        `).join('')}
                    </div>
                </div>
            </div>
        `;
        container.appendChild(tierCard);
    });
}

function updateTierField(index, field, value) {
    escalationRules[index][field] = value;
}

function toggleTier(index, isEnabled) {
    escalationRules[index].is_enabled = isEnabled ? 1 : 0;
}

function toggleChannel(index, channel, isChecked) {
    let channels = Array.isArray(escalationRules[index].channels) 
        ? escalationRules[index].channels 
        : JSON.parse(escalationRules[index].channels || '[]');
    
    if (isChecked) {
        if (!channels.includes(channel)) channels.push(channel);
    } else {
        channels = channels.filter(c => c !== channel);
    }
    escalationRules[index].channels = channels;
    renderEscalationTiers();
}

function addEscalationTier() {
    const nextLevel = escalationRules.length + 1;
    escalationRules.push({
        id: 'tier_' + Date.now(),
        level: nextLevel,
        delay_minutes: nextLevel * 15,
        channels: ['email', 'slack'],
        recipients: 'Tier ' + nextLevel + ' Support Team',
        is_enabled: 1
    });
    renderEscalationTiers();
}

function removeEscalationTier(index) {
    if (escalationRules.length <= 1) {
        showToast('At least one escalation tier must remain.', 'error');
        return;
    }
    escalationRules.splice(index, 1);
    escalationRules.forEach((r, i) => r.level = i + 1);
    renderEscalationTiers();
}

async function testWebhook(type) {
    let url = '';
    let routingKey = '';

    if (type === 'slack') url = document.getElementById('slack-url').value;
    else if (type === 'discord') url = document.getElementById('discord-url').value;
    else if (type === 'msteams') url = document.getElementById('msteams-url').value;
    else if (type === 'pagerduty') routingKey = document.getElementById('pagerduty-key').value;
    else if (type === 'custom') url = document.getElementById('custom-url').value;

    if (!url && !routingKey) {
        showToast(`Please enter a valid URL or Key for ${type} before testing.`, 'error');
        return;
    }

    showToast(`Sending test notification to ${type}...`, 'success');

    try {
        const resp = await fetch('api.php?action=test_webhook_endpoint', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type, url, routing_key: routingKey })
        });
        const res = await resp.json();
        showToast(res.message, res.success ? 'success' : 'error');
    } catch (e) {
        showToast(`Test failed: ${e.message}`, 'error');
    }
}

document.getElementById('save-all-btn').addEventListener('click', async () => {
    const webhooks = [
        { name: 'Slack', type: 'slack', url: document.getElementById('slack-url').value, is_enabled: document.getElementById('slack-enabled').checked ? 1 : 0 },
        { name: 'Discord', type: 'discord', url: document.getElementById('discord-url').value, is_enabled: document.getElementById('discord-enabled').checked ? 1 : 0 },
        { name: 'MS Teams', type: 'msteams', url: document.getElementById('msteams-url').value, is_enabled: document.getElementById('msteams-enabled').checked ? 1 : 0 },
        { name: 'PagerDuty', type: 'pagerduty', routing_key: document.getElementById('pagerduty-key').value, is_enabled: document.getElementById('pagerduty-enabled').checked ? 1 : 0 },
        { name: 'Custom', type: 'custom', url: document.getElementById('custom-url').value, is_enabled: document.getElementById('custom-enabled').checked ? 1 : 0 }
    ];

    try {
        const resp = await fetch('api.php?action=save_escalation_settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ webhooks, rules: escalationRules })
        });
        const res = await resp.json();
        showToast(res.message || 'Saved successfully!', res.success ? 'success' : 'error');
    } catch (e) {
        showToast(`Save failed: ${e.message}`, 'error');
    }
});

document.addEventListener('DOMContentLoaded', loadSettings);
</script>

<?php include 'footer.php'; ?>
