<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM AIOps Root Cause Analysis (RCA) & Alert Storm Suppressor
 */
require_once 'includes/bootstrap.php';
require_once 'includes/auth_check.php';
require_once 'includes/db.php';

require_once 'header.php';
?>

<div class="container mx-auto px-4 py-6 max-w-7xl">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-brain text-cyan-400"></i> AIOps Root Cause Analysis (RCA)
            </h1>
            <p class="text-slate-400 text-sm mt-1">Autonomous topology graph reasoning, parent-dependency failure correlation, and alert storm suppression.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="runRcaScan()" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-sm font-semibold shadow-lg shadow-cyan-600/30 transition-all flex items-center gap-2">
                <i class="fas fa-microchip"></i> Re-Analyze Outage Graph
            </button>
        </div>
    </div>

    <!-- RCA Summary KPI Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-red-500/10 border border-red-500/20 flex items-center justify-center text-red-400 text-xl">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Total Down Nodes</span>
                <h3 class="text-xl font-bold text-white" id="rca-stat-down">0</h3>
            </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 text-xl">
                <i class="fas fa-crosshairs"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Root Causes Found</span>
                <h3 class="text-xl font-bold text-white" id="rca-stat-roots">0</h3>
            </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400 text-xl">
                <i class="fas fa-bell-slash"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Suppressed Alerts</span>
                <h3 class="text-xl font-bold text-white" id="rca-stat-suppressed">0</h3>
            </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-xl">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">AI Noise Reduction</span>
                <h3 class="text-xl font-bold text-white" id="rca-stat-reduction">0%</h3>
            </div>
        </div>
    </div>

    <!-- Active Root Cause Incidents Stream -->
    <div class="bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 p-6 shadow-xl space-y-6">
        <div class="flex items-center justify-between border-b border-slate-700/60 pb-4">
            <div>
                <h2 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fas fa-project-diagram text-cyan-400"></i> AI Correlated Root Cause Incidents
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Topological analysis groups downstream cascade failures under single root components.</p>
            </div>
            <span class="text-xs font-mono text-cyan-400" id="rca-last-scan-time"></span>
        </div>

        <div id="rca-incidents-container" class="space-y-4">
            <div class="text-center py-12 text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Analyzing outage graph...</div>
        </div>
    </div>
</div>

<script>
async function runRcaScan() {
    const container = document.getElementById('rca-incidents-container');
    container.innerHTML = `<div class="text-center py-12 text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Analyzing network topology graph...</div>`;

    try {
        const resp = await fetch('api.php?action=get_rca_analysis');
        const data = await resp.json();
        if (!data.success) return;

        const analysis = data.analysis;
        document.getElementById('rca-last-scan-time').textContent = 'Analyzed: ' + new Date().toLocaleTimeString();
        document.getElementById('rca-stat-down').textContent = analysis.total_down_devices || 0;
        document.getElementById('rca-stat-roots').textContent = analysis.root_cause_count || 0;
        document.getElementById('rca-stat-suppressed').textContent = analysis.suppressed_count || 0;

        const totalDown = analysis.total_down_devices || 0;
        const suppressed = analysis.suppressed_count || 0;
        const reductionPct = totalDown > 0 ? Math.round((suppressed / totalDown) * 100) : 0;
        document.getElementById('rca-stat-reduction').textContent = `${reductionPct}%`;

        renderRcaIncidents(analysis);
    } catch (e) {
        container.innerHTML = `<div class="text-center py-12 text-red-400">Error analyzing topology: ${e.message}</div>`;
    }
}

function renderRcaIncidents(analysis) {
    const container = document.getElementById('rca-incidents-container');
    if (!analysis.has_outage || analysis.root_causes.length === 0) {
        container.innerHTML = `
            <div class="text-center py-16 text-slate-400">
                <div class="w-16 h-16 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center text-3xl mx-auto mb-3 shadow-lg">
                    <i class="fas fa-check-double"></i>
                </div>
                <h3 class="text-base font-bold text-white">No Active Root Cause Failures</h3>
                <p class="text-xs text-slate-500 mt-1">All monitored core routers, switches, and dependent nodes are online and operating normally.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = analysis.root_causes.map((rc, idx) => `
        <div class="bg-slate-900/80 border border-slate-700/80 rounded-xl p-5 shadow-lg space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-800 pb-3">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-red-500/20 text-red-400 border border-red-500/30 flex items-center justify-center text-lg font-bold">
                        <i class="fas fa-biohazard"></i>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="font-bold text-white text-base">${rc.root_device_name}</h4>
                            <span class="px-2.5 py-0.5 rounded bg-red-500/20 text-red-400 border border-red-500/30 text-xs font-mono font-bold">ROOT FAILURE</span>
                        </div>
                        <span class="text-xs text-slate-400 font-mono">${rc.root_device_ip || 'No IP'} • ${rc.root_device_type ? rc.root_device_type.toUpperCase() : 'DEVICE'}</span>
                    </div>
                </div>

                <div class="flex items-center gap-4 text-xs font-mono">
                    <div class="text-right">
                        <span class="text-slate-400">Impact Blast Radius:</span>
                        <div class="text-rose-400 font-bold">${rc.impact_count} Dependent Nodes</div>
                    </div>
                    <div class="text-right border-l border-slate-800 pl-4">
                        <span class="text-slate-400">AI Confidence:</span>
                        <div class="text-emerald-400 font-bold">${rc.confidence_percent}%</div>
                    </div>
                </div>
            </div>

            <p class="text-xs text-slate-300 bg-slate-950/60 p-3 rounded-lg border border-slate-800 font-mono">
                <i class="fas fa-robot text-cyan-400 mr-1.5"></i> ${rc.summary}
            </p>

            ${rc.suppressed_device_ids && rc.suppressed_device_ids.length > 0 ? `
                <div class="space-y-1.5">
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider font-mono">
                        <i class="fas fa-bell-slash text-amber-400 mr-1"></i> Suppressed Downstream Leaf Alerts (${rc.suppressed_device_ids.length})
                    </span>
                    <div class="flex flex-wrap gap-2 pt-1">
                        ${rc.suppressed_device_ids.map(id => `
                            <span class="px-2.5 py-1 bg-slate-950 border border-slate-800 rounded text-slate-400 text-xs font-mono">
                                Node #${id.substring(0, 8)}... (Suppressed)
                            </span>
                        `).join('')}
                    </div>
                </div>
            ` : ''}
        </div>
    `).join('');
}

document.addEventListener('DOMContentLoaded', runRcaScan);
</script>

<?php include 'footer.php'; ?>
