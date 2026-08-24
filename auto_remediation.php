<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM Autonomous Self-Healing Auto-Remediation Manager
 */
require_once 'includes/bootstrap.php';
require_once 'includes/auth_check.php';
require_once 'includes/db.php';

if (($_SESSION['user_role'] ?? 'viewer') !== 'admin') {
    header('Location: index.php');
    exit;
}

$pdo = getDbConnection();
$devices = $pdo->query("SELECT id, name, ip FROM devices ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once 'header.php';
?>

<div class="container mx-auto px-4 py-6 max-w-7xl">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-magic text-cyan-400"></i> Autonomous Auto-Remediation Runbooks
            </h1>
            <p class="text-slate-400 text-sm mt-1">Configure event-triggered self-healing workflows, automatic service restarts, and log purge scripts.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="openNewRuleModal()" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-sm font-semibold shadow-lg shadow-cyan-600/30 transition-all flex items-center gap-2">
                <i class="fas fa-plus"></i> New Remediation Rule
            </button>
        </div>
    </div>

    <!-- Active Runbooks & Execution Logs Split Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Runbook Rules (5 Cols) -->
        <div class="lg:col-span-5 bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 p-5 shadow-xl flex flex-col">
            <div class="flex items-center justify-between mb-4 border-b border-slate-700/60 pb-3">
                <h2 class="text-sm font-semibold text-white uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-scroll text-cyan-400"></i> Active Runbook Policies
                </h2>
                <span class="text-xs text-slate-400" id="rules-count-badge">0 Rules</span>
            </div>

            <div id="rules-list-container" class="space-y-3 flex-1 overflow-y-auto max-h-[600px] pr-1">
                <div class="text-center py-8 text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading remediation rules...</div>
            </div>
        </div>

        <!-- Execution Logs (7 Cols) -->
        <div class="lg:col-span-7 bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 p-5 shadow-xl flex flex-col">
            <div class="flex items-center justify-between mb-4 border-b border-slate-700/60 pb-3">
                <h2 class="text-sm font-semibold text-white uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-history text-cyan-400"></i> Self-Healing Execution Audit Stream
                </h2>
                <span class="text-xs text-slate-400 font-mono">Live Audit Trail</span>
            </div>

            <div id="logs-list-container" class="space-y-3 flex-1 overflow-y-auto max-h-[600px] pr-1">
                <div class="text-center py-8 text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading execution audit stream...</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Create / Edit Rule -->
<div id="rule-modal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-800 border border-slate-700 rounded-2xl max-w-lg w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-4 border-b border-slate-700 pb-3">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fas fa-magic text-cyan-400"></i> Configure Auto-Remediation Rule
            </h3>
            <button type="button" onclick="closeRuleModal()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form id="rule-form" onsubmit="submitRule(event)" class="space-y-3.5 text-xs">
            <input type="hidden" id="rule-id">
            <div>
                <label class="block text-slate-300 font-medium mb-1">Rule Name</label>
                <input type="text" id="rule-name" required placeholder="e.g. Auto-Restart Print Spooler on Crash" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Trigger Condition</label>
                    <select id="rule-trigger" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                        <option value="service_down">Service Stopped / Down</option>
                        <option value="device_down">Device Unreachable (Down)</option>
                        <option value="high_disk">High Disk Usage (>90%)</option>
                        <option value="high_cpu">Sustained High CPU (>85%)</option>
                        <option value="port_down">Switch Port Down</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Action Type</label>
                    <select id="rule-action-type" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                        <option value="agent_service_restart">Agent: Restart Service</option>
                        <option value="agent_command">Agent: Run PowerShell Script</option>
                        <option value="snmp_port_restart">SNMP: Reset Interface Port</option>
                        <option value="custom_script">Custom Shell Action</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-slate-300 font-medium mb-1">Action Payload / Service Name / Command</label>
                <input type="text" id="rule-payload" required placeholder="e.g. Spooler or Clear-RecycleBin -Force" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:border-cyan-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Target Device (Optional)</label>
                    <select id="rule-device-id" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                        <option value="">Any Matching Host</option>
                        <?php foreach ($devices as $d): ?>
                            <option value="<?= htmlspecialchars($d['id']) ?>"><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['ip'] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-300 font-medium mb-1">Cooldown (Minutes)</label>
                    <input type="number" id="rule-cooldown" value="10" min="1" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                </div>
            </div>

            <div class="pt-2 flex items-center justify-between border-t border-slate-700">
                <label class="flex items-center gap-2 text-slate-300 cursor-pointer">
                    <input type="checkbox" id="rule-enabled" checked class="rounded border-slate-600 bg-slate-800 text-cyan-500">
                    <span>Enable Rule</span>
                </label>
                <div class="flex gap-2">
                    <button type="button" onclick="closeRuleModal()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg shadow-lg shadow-cyan-600/30">Save Runbook</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let remediationData = null;

async function loadRemediationData() {
    try {
        const resp = await fetch('api.php?action=get_remediation_rules');
        const data = await resp.json();
        if (!data.success) return;

        remediationData = data;
        document.getElementById('rules-count-badge').textContent = `${(data.rules || []).length} Rules`;
        renderRules(data.rules || []);
        renderLogs(data.logs || []);
    } catch (e) {
        console.error('Error loading remediation rules', e);
    }
}

function renderRules(rules) {
    const list = document.getElementById('rules-list-container');
    if (rules.length === 0) {
        list.innerHTML = `<div class="text-center py-8 text-slate-500">No remediation runbooks configured.</div>`;
        return;
    }

    list.innerHTML = rules.map(r => `
        <div class="p-4 bg-slate-900/80 border border-slate-700/80 rounded-xl space-y-3 text-xs">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full ${r.is_enabled == 1 ? 'bg-emerald-400 animate-pulse' : 'bg-slate-600'}"></span>
                    <h4 class="font-bold text-white">${r.name}</h4>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="triggerManualRun('${r.id}')" class="px-2.5 py-1 bg-cyan-600/20 text-cyan-400 border border-cyan-500/30 hover:bg-cyan-600/40 rounded text-[11px] font-semibold transition" title="Trigger Self-Healing Test">
                        <i class="fas fa-play mr-1"></i>Run
                    </button>
                    <button type="button" onclick="deleteRule('${r.id}')" class="p-1 text-slate-400 hover:text-red-400">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 text-[11px] font-mono text-slate-400">
                <div>Trigger: <span class="text-amber-400">${r.trigger_condition}</span></div>
                <div>Action: <span class="text-cyan-300">${r.action_type}</span></div>
                <div>Payload: <span class="text-slate-300">${r.action_payload}</span></div>
                <div>Cooldown: <span class="text-slate-300">${r.cooldown_minutes}m</span></div>
            </div>
        </div>
    `).join('');
}

function renderLogs(logs) {
    const list = document.getElementById('logs-list-container');
    if (logs.length === 0) {
        list.innerHTML = `<div class="text-center py-8 text-slate-500">No autonomous executions logged yet.</div>`;
        return;
    }

    list.innerHTML = logs.map(l => {
        let statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">SUCCESS</span>';
        if (l.status === 'failed') statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-500/20 text-red-400 border border-red-500/30">FAILED</span>';
        else if (l.status === 'skipped_cooldown') statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">COOLDOWN</span>';

        return `
            <div class="p-3.5 bg-slate-900/80 border border-slate-700/80 rounded-xl space-y-2 text-xs">
                <div class="flex items-center justify-between">
                    <div class="font-bold text-white">${l.rule_name || 'Remediation Rule'}</div>
                    <div class="flex items-center gap-2">
                        ${statusBadge}
                        <span class="text-slate-500 font-mono text-[11px]">${l.created_at}</span>
                    </div>
                </div>
                <div class="text-[11px] font-mono text-slate-400 bg-slate-950 p-2 rounded border border-slate-800">
                    <div class="text-cyan-400 font-semibold mb-1">> ${l.action_executed}</div>
                    <div class="text-slate-300 font-sans">${l.output || '(No output)'}</div>
                </div>
            </div>
        `;
    }).join('');
}

function openNewRuleModal() {
    document.getElementById('rule-id').value = '';
    document.getElementById('rule-name').value = '';
    document.getElementById('rule-trigger').value = 'service_down';
    document.getElementById('rule-action-type').value = 'agent_service_restart';
    document.getElementById('rule-payload').value = '';
    document.getElementById('rule-device-id').value = '';
    document.getElementById('rule-cooldown').value = '10';
    document.getElementById('rule-enabled').checked = true;
    document.getElementById('rule-modal').classList.remove('hidden');
}

function closeRuleModal() {
    document.getElementById('rule-modal').classList.add('hidden');
}

async function submitRule(e) {
    e.preventDefault();
    const id = document.getElementById('rule-id').value;
    const name = document.getElementById('rule-name').value;
    const trigger_condition = document.getElementById('rule-trigger').value;
    const action_type = document.getElementById('rule-action-type').value;
    const action_payload = document.getElementById('rule-payload').value;
    const target_device_id = document.getElementById('rule-device-id').value;
    const cooldown_minutes = document.getElementById('rule-cooldown').value;
    const is_enabled = document.getElementById('rule-enabled').checked ? 1 : 0;

    try {
        const resp = await fetch('api.php?action=save_remediation_rule', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, name, trigger_condition, action_type, action_payload, target_device_id, cooldown_minutes, is_enabled })
        });
        const res = await resp.json();
        alert(res.message || 'Saved!');
        closeRuleModal();
        loadRemediationData();
    } catch (err) {
        alert('Error saving rule: ' + err.message);
    }
}

async function triggerManualRun(ruleId) {
    try {
        const resp = await fetch('api.php?action=trigger_remediation_manual', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ rule_id: ruleId })
        });
        const res = await resp.json();
        alert(res.message || 'Run complete!');
        loadRemediationData();
    } catch (err) {
        alert('Execution failed: ' + err.message);
    }
}

async function deleteRule(id) {
    if (!confirm('Delete this remediation runbook rule?')) return;
    try {
        await fetch('api.php?action=delete_remediation_rule', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        loadRemediationData();
    } catch (err) {
        alert('Error deleting rule: ' + err.message);
    }
}

document.addEventListener('DOMContentLoaded', loadRemediationData);
</script>

<?php include 'footer.php'; ?>
