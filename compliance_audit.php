<?php
/**
 * AMPNM Network Configuration Compliance & Security Audit Dashboard
 * Copyright (c) IT Support BD. All rights reserved.
 */

require_once 'includes/auth_check.php';
require_once 'includes/compliance_engine.php';
include 'header.php';

$engine = new ComplianceEngine();
$overview = $engine->getGlobalComplianceOverview();
$rules = $engine->getRules();

// Calculate aggregate stats
$totalAudited = count($overview);
$avgScore = 0;
$criticalViolations = 0;
$cleanDevices = 0;

if ($totalAudited > 0) {
    $sumScore = 0;
    foreach ($overview as $row) {
        $sumScore += (float)$row['compliance_score'];
        if ((float)$row['compliance_score'] >= 100) {
            $cleanDevices++;
        }
        $violations = json_decode($row['violations_json'] ?? '[]', true) ?: [];
        foreach ($violations as $v) {
            if (($v['severity'] ?? '') === 'critical') {
                $criticalViolations++;
            }
        }
    }
    $avgScore = round($sumScore / $totalAudited, 1);
}
?>

<div class="container-fluid px-4 py-4 max-w-7xl mx-auto">
    <!-- Header Title & Action Buttons -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-shield-check text-emerald-400"></i>
                <span>Configuration Compliance & Golden Standard Audit</span>
            </h1>
            <p class="text-slate-400 text-sm mt-1">
                Automated CIS/NIST compliance auditing, security rule enforcement, and one-click remediation diffs.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="openRuleManagerModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-lg text-sm font-medium transition flex items-center gap-2">
                <i class="fas fa-sliders-h text-cyan-400"></i> Manage Rules (<?= count($rules) ?>)
            </button>
            <button onclick="runGlobalAudit()" id="btnRunAudit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold shadow-lg shadow-emerald-900/30 transition flex items-center gap-2">
                <i class="fas fa-play"></i> Run Full Audit
            </button>
        </div>
    </div>

    <!-- Overview Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-850 border border-slate-800 rounded-xl p-4 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400 text-xl font-bold">
                <i class="fas fa-server"></i>
            </div>
            <div>
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Audited Devices</p>
                <h3 class="text-2xl font-bold text-white mt-0.5"><?= $totalAudited ?></h3>
            </div>
        </div>

        <div class="bg-slate-850 border border-slate-800 rounded-xl p-4 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl <?= $avgScore >= 80 ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-amber-500/10 border-amber-500/30 text-amber-400' ?> border flex items-center justify-center text-xl font-bold">
                <?= $avgScore ?>%
            </div>
            <div>
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Average Compliance</p>
                <h3 class="text-xl font-bold text-white mt-0.5"><?= $avgScore >= 90 ? 'High Hardening' : ($avgScore >= 70 ? 'Moderate Risk' : 'High Risk') ?></h3>
            </div>
        </div>

        <div class="bg-slate-850 border border-slate-800 rounded-xl p-4 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-xl font-bold">
                <i class="fas fa-check-double"></i>
            </div>
            <div>
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">100% Compliant</p>
                <h3 class="text-2xl font-bold text-emerald-400 mt-0.5"><?= $cleanDevices ?></h3>
            </div>
        </div>

        <div class="bg-slate-850 border border-slate-800 rounded-xl p-4 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-red-500/10 border border-red-500/30 flex items-center justify-center text-red-400 text-xl font-bold">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Critical Violations</p>
                <h3 class="text-2xl font-bold text-red-400 mt-0.5"><?= $criticalViolations ?></h3>
            </div>
        </div>
    </div>

    <!-- Device Compliance Table -->
    <div class="bg-slate-850 border border-slate-800 rounded-xl overflow-hidden shadow-xl mb-8">
        <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
            <h3 class="font-semibold text-white flex items-center gap-2">
                <i class="fas fa-list-check text-cyan-400"></i> Device Security Posture & Compliance Status
            </h3>
            <span class="text-xs text-slate-400">Real-time Policy Evaluation</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-900/60 text-slate-400 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Device</th>
                        <th class="py-3 px-4">IP Address</th>
                        <th class="py-3 px-4">Score</th>
                        <th class="py-3 px-4">Rules Status</th>
                        <th class="py-3 px-4">Violations</th>
                        <th class="py-3 px-4">Last Audited</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <?php if (empty($overview)): ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500">
                                <i class="fas fa-clipboard-check text-4xl mb-2 block"></i>
                                No device audit records found. Click "Run Full Audit" to perform your first compliance scan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($overview as $dev): 
                            $score = (float)$dev['compliance_score'];
                            $violations = json_decode($dev['violations_json'] ?? '[]', true) ?: [];
                        ?>
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="py-3.5 px-4 font-semibold text-white">
                                <?= htmlspecialchars($dev['device_name']) ?>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-xs text-cyan-400">
                                <?= htmlspecialchars($dev['ip_address']) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-16 bg-slate-900 rounded-full h-2 overflow-hidden">
                                        <div class="h-2 rounded-full <?= $score >= 90 ? 'bg-emerald-500' : ($score >= 70 ? 'bg-amber-500' : 'bg-red-500') ?>" style="width: <?= $score ?>%"></div>
                                    </div>
                                    <span class="font-bold text-xs <?= $score >= 90 ? 'text-emerald-400' : ($score >= 70 ? 'text-amber-400' : 'text-red-400') ?>">
                                        <?= $score ?>%
                                    </span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                <span class="text-emerald-400 font-semibold"><?= $dev['passed_rules'] ?> Passed</span> / 
                                <span class="text-red-400 font-semibold"><?= $dev['failed_rules'] ?> Failed</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <?php if (empty($violations)): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <i class="fas fa-check-circle"></i> Clean
                                    </span>
                                <?php else: ?>
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach (array_slice($violations, 0, 2) as $v): ?>
                                            <span class="px-2 py-0.5 rounded text-2xs <?= ($v['severity'] ?? '') === 'critical' ? 'bg-red-500/20 text-red-300 border border-red-500/40' : 'bg-amber-500/20 text-amber-300 border border-amber-500/40' ?>">
                                                <?= htmlspecialchars($v['rule_name']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($violations) > 2): ?>
                                            <span class="text-xs text-slate-400">+<?= count($violations) - 2 ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-400">
                                <?= date('M d, H:i', strtotime($dev['audited_at'])) ?>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <button onclick='showViolationModal(<?= json_encode($dev) ?>)' class="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-cyan-400 border border-slate-700 rounded text-xs transition">
                                    <i class="fas fa-eye"></i> Details & Diff
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

<!-- Modal: Violation Details & Remediation Diff -->
<div id="violationModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto shadow-2xl p-6">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4 mb-4">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-shield-alt text-cyan-400"></i> <span id="modalDeviceTitle">Device Compliance Report</span>
            </h3>
            <button onclick="closeViolationModal()" class="text-slate-400 hover:text-white text-lg">&times;</button>
        </div>

        <div id="modalViolationsList" class="space-y-4 mb-6"></div>

        <div class="mb-4">
            <h4 class="text-sm font-semibold text-slate-300 mb-2 flex items-center gap-2">
                <i class="fas fa-terminal text-amber-400"></i> Remediation CLI Script
            </h4>
            <pre id="modalRemediationDiff" class="bg-slate-950 p-4 rounded-xl border border-slate-800 font-mono text-xs text-emerald-400 overflow-x-auto select-all max-h-48"></pre>
        </div>

        <div class="flex justify-end gap-3">
            <button onclick="closeViolationModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-sm">Close</button>
        </div>
    </div>
</div>

<!-- Modal: Rule Manager -->
<div id="ruleModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto shadow-2xl p-6">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4 mb-4">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-sliders-h text-cyan-400"></i> Security Compliance Rules
            </h3>
            <button onclick="closeRuleManagerModal()" class="text-slate-400 hover:text-white text-lg">&times;</button>
        </div>
        <div class="space-y-3">
            <?php foreach ($rules as $r): ?>
                <div class="bg-slate-850 p-3 rounded-xl border border-slate-800 flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-white text-sm"><?= htmlspecialchars($r['name']) ?></span>
                            <span class="px-2 py-0.5 rounded text-2xs uppercase tracking-wider font-bold <?= $r['severity'] === 'critical' ? 'bg-red-500/20 text-red-300' : 'bg-amber-500/20 text-amber-300' ?>">
                                <?= $r['severity'] ?>
                            </span>
                            <span class="text-xs text-slate-400 capitalize">(<?= $r['vendor'] ?>)</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($r['description']) ?></p>
                        <code class="text-2xs font-mono text-cyan-400 mt-1 block">Pattern: <?= htmlspecialchars($r['pattern_expression']) ?></code>
                    </div>
                    <span class="text-xs text-emerald-400 font-semibold px-2 py-1 bg-emerald-500/10 rounded">Active</span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button onclick="closeRuleManagerModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-sm">Close</button>
        </div>
    </div>
</div>

<script>
function showViolationModal(data) {
    document.getElementById('modalDeviceTitle').textContent = `${data.device_name} (${data.ip_address}) - Compliance: ${data.compliance_score}%`;
    const violations = JSON.parse(data.violations_json || '[]');
    const list = document.getElementById('modalViolationsList');
    list.innerHTML = '';

    if (violations.length === 0) {
        list.innerHTML = '<div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm"><i class="fas fa-check-circle mr-2"></i> Device is fully compliant with all security baseline rules.</div>';
    } else {
        violations.forEach(v => {
            const card = document.createElement('div');
            card.className = 'p-3.5 rounded-xl bg-slate-850 border border-slate-800';
            card.innerHTML = `
                <div class="flex items-center justify-between mb-1">
                    <span class="font-semibold text-white text-sm">${v.rule_name}</span>
                    <span class="px-2 py-0.5 rounded text-2xs uppercase tracking-wider font-bold ${v.severity === 'critical' ? 'bg-red-500/20 text-red-300' : 'bg-amber-500/20 text-amber-300'}">${v.severity}</span>
                </div>
                <p class="text-xs text-slate-400 mb-2">${v.description || ''}</p>
                <div class="bg-slate-950 p-2 rounded text-xs font-mono text-slate-300">
                    <span class="text-slate-500">Requirement:</span> ${v.expected}
                </div>
            `;
            list.appendChild(card);
        });
    }

    document.getElementById('modalRemediationDiff').textContent = data.remediation_diff || '! No remediation needed. Configuration is compliant.';
    const modal = document.getElementById('violationModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeViolationModal() {
    const modal = document.getElementById('violationModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function openRuleManagerModal() {
    const modal = document.getElementById('ruleModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeRuleManagerModal() {
    const modal = document.getElementById('ruleModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function runGlobalAudit() {
    const btn = document.getElementById('btnRunAudit');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Auditing...';

    try {
        const res = await fetch('api.php?action=run_compliance_audit', { method: 'POST' });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            alert('Audit failed: ' + (json.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Network error while running audit');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play"></i> Run Full Audit';
    }
}
</script>

<?php include 'footer.php'; ?>
