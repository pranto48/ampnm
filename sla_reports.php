<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM SLA Compliance & Executive Reporting Engine
 */
require_once 'includes/bootstrap.php';
require_once 'includes/auth_check.php';
require_once 'includes/db.php';

require_once 'header.php';
?>

<div class="container mx-auto px-4 py-6 max-w-7xl">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 print:hidden">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-file-contract text-cyan-400"></i> SLA Compliance &amp; Executive Reports
            </h1>
            <p class="text-slate-400 text-sm mt-1">Service Level Agreement compliance tracking, MTTR, MTBF, and executive downtime reporting.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="exportCsv()" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5">
                <i class="fas fa-file-csv text-emerald-400"></i> Export CSV
            </button>
            <button type="button" onclick="window.print()" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-xs font-semibold shadow-lg shadow-cyan-600/30 transition-all flex items-center gap-2">
                <i class="fas fa-print"></i> Print Executive Report
            </button>
        </div>
    </div>

    <!-- Filter & Configuration Bar -->
    <div class="bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 p-4 mb-6 shadow-xl print:hidden">
        <div class="flex flex-wrap items-center justify-between gap-4 text-xs">
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-slate-400 font-medium">Reporting Window:</label>
                    <select id="sel-window-days" onchange="loadSlaReport()" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-white focus:border-cyan-500 focus:outline-none">
                        <option value="7">Last 7 Days (Weekly)</option>
                        <option value="30" selected>Last 30 Days (Monthly)</option>
                        <option value="90">Last 90 Days (Quarterly)</option>
                        <option value="365">Last 365 Days (Annual)</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-slate-400 font-medium">Target SLA Threshold:</label>
                    <select id="sel-target-sla" onchange="loadSlaReport()" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-white focus:border-cyan-500 focus:outline-none">
                        <option value="99.99">99.99% (Gold / Mission Critical)</option>
                        <option value="99.90" selected>99.90% (Standard Enterprise)</option>
                        <option value="99.50">99.50% (Standard Business)</option>
                        <option value="99.00">99.00% (Basic SLA)</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-2 text-slate-400 font-mono text-[11px]" id="report-generated-time">
                <!-- Timestamp populated via JS -->
            </div>
        </div>
    </div>

    <!-- Executive KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400 text-xl">
                <i class="fas fa-chart-line"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Overall SLA</span>
                <h3 class="text-xl font-bold text-white font-mono" id="stat-overall-sla">100.0%</h3>
            </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-xl">
                <i class="fas fa-server"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Monitored Units</span>
                <h3 class="text-xl font-bold text-white" id="stat-total-devices">0</h3>
            </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 text-xl">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">SLA Breaches</span>
                <h3 class="text-xl font-bold text-white" id="stat-breach-count">0</h3>
            </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 text-xl">
                <i class="fas fa-wrench"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Mean MTTR</span>
                <h3 class="text-xl font-bold text-white font-mono" id="stat-mean-mttr">0.0 m</h3>
            </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 text-xl">
                <i class="fas fa-shield-virus"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Mean MTBF</span>
                <h3 class="text-xl font-bold text-white font-mono" id="stat-mean-mtbf">720.0 h</h3>
            </div>
        </div>
    </div>

    <!-- Executive SLA Table -->
    <div class="bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 overflow-hidden shadow-xl">
        <div class="px-6 py-4 border-b border-slate-700/60 flex items-center justify-between">
            <h2 class="text-base font-semibold text-white flex items-center gap-2">
                <i class="fas fa-table text-cyan-400"></i> Detailed Infrastructure Compliance Breakdown
            </h2>
            <span class="text-xs text-slate-400 font-mono" id="table-count-label">Loading data...</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300" id="sla-table">
                <thead class="bg-slate-900/60 text-xs text-slate-400 uppercase border-b border-slate-700/60 font-semibold font-mono">
                    <tr>
                        <th class="px-6 py-3.5">Device</th>
                        <th class="px-6 py-3.5">Actual SLA</th>
                        <th class="px-6 py-3.5">Target SLA</th>
                        <th class="px-6 py-3.5">Total Downtime</th>
                        <th class="px-6 py-3.5">Outages</th>
                        <th class="px-6 py-3.5">MTTR / MTBF</th>
                        <th class="px-6 py-3.5 text-right">Compliance</th>
                    </tr>
                </thead>
                <tbody id="sla-table-body" class="divide-y divide-slate-700/40">
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Computing SLA compliance metrics...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let currentReportData = null;

async function loadSlaReport() {
    const days = document.getElementById('sel-window-days').value;
    const targetSla = document.getElementById('sel-target-sla').value;

    try {
        const resp = await fetch(`api.php?action=get_sla_report_data&days=${days}&target_sla=${targetSla}`);
        const data = await resp.json();
        if (!data.success) return;

        currentReportData = data;
        document.getElementById('report-generated-time').textContent = 'Generated: ' + new Date().toLocaleString();
        document.getElementById('stat-overall-sla').textContent = data.overall_sla_percent.toFixed(3) + '%';
        document.getElementById('stat-total-devices').textContent = data.total_devices;
        document.getElementById('stat-breach-count').textContent = data.breach_count;

        // Calculate average MTTR and MTBF
        let totalMttr = 0, totalMtbf = 0, devCount = data.device_reports.length;
        data.device_reports.forEach(r => {
            totalMttr += parseFloat(r.mttr_minutes || 0);
            totalMtbf += parseFloat(r.mtbf_hours || 0);
        });

        document.getElementById('stat-mean-mttr').textContent = devCount > 0 ? (totalMttr / devCount).toFixed(1) + ' m' : '0 m';
        document.getElementById('stat-mean-mtbf').textContent = devCount > 0 ? (totalMtbf / devCount).toFixed(1) + ' h' : '0 h';
        document.getElementById('table-count-label').textContent = `${devCount} devices analyzed`;

        renderSlaTable(data.device_reports, parseFloat(targetSla));
    } catch (e) {
        console.error('Failed to load SLA report', e);
    }
}

function renderSlaTable(reports, targetSla) {
    const tbody = document.getElementById('sla-table-body');
    if (reports.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-slate-500">No monitored devices found.</td></tr>`;
        return;
    }

    tbody.innerHTML = reports.map(r => {
        const isPass = r.is_compliant;
        const slaColor = isPass ? 'text-emerald-400' : 'text-rose-400';
        const badge = isPass 
            ? '<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">COMPLIANT</span>'
            : '<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/20 text-rose-400 border border-rose-500/30 animate-pulse">SLA BREACH</span>';

        const pct = Math.min(100, Math.max(0, r.sla_percent));

        return `
            <tr class="hover:bg-slate-700/30 transition-colors">
                <td class="px-6 py-4">
                    <div class="font-bold text-white text-sm">${r.name}</div>
                    <div class="text-xs text-slate-400 font-mono">${r.ip || 'No IP'} • ${r.type.toUpperCase()}</div>
                </td>
                <td class="px-6 py-4 font-mono font-bold ${slaColor} text-sm">
                    <div>${r.sla_percent.toFixed(3)}%</div>
                    <div class="w-24 bg-slate-700 rounded-full h-1 mt-1 overflow-hidden">
                        <div class="${isPass ? 'bg-emerald-500' : 'bg-rose-500'} h-full" style="width: ${pct}%"></div>
                    </div>
                </td>
                <td class="px-6 py-4 font-mono text-xs text-slate-400">${targetSla.toFixed(2)}%</td>
                <td class="px-6 py-4 font-mono text-xs text-slate-300">${r.downtime_minutes} mins</td>
                <td class="px-6 py-4 font-mono text-xs text-slate-300">${r.outage_count} events</td>
                <td class="px-6 py-4 font-mono text-xs text-slate-400">
                    <div>MTTR: ${r.mttr_minutes}m</div>
                    <div>MTBF: ${r.mtbf_hours}h</div>
                </td>
                <td class="px-6 py-4 text-right">${badge}</td>
            </tr>
        `;
    }).join('');
}

function exportCsv() {
    if (!currentReportData || !currentReportData.device_reports) return;
    const rows = [
        ['Device Name', 'IP Address', 'Type', 'Actual SLA %', 'Target SLA %', 'Downtime (Mins)', 'Outage Count', 'MTTR (Mins)', 'MTBF (Hours)', 'Compliance Status']
    ];

    currentReportData.device_reports.forEach(r => {
        rows.push([
            `"${r.name}"`,
            `"${r.ip}"`,
            `"${r.type}"`,
            r.sla_percent.toFixed(3),
            currentReportData.target_sla.toFixed(2),
            r.downtime_minutes,
            r.outage_count,
            r.mttr_minutes,
            r.mtbf_hours,
            r.is_compliant ? 'COMPLIANT' : 'BREACH'
        ]);
    });

    const csvContent = 'data:text/csv;charset=utf-8,' + rows.map(e => e.join(',')).join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `AMPNM_SLA_Report_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

document.addEventListener('DOMContentLoaded', loadSlaReport);
</script>

<?php include 'footer.php'; ?>
