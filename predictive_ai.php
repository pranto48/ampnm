<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM AIOps Predictive Capacity & Anomaly Forecast Engine
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
                <i class="fas fa-chart-area text-cyan-400"></i> Predictive Capacity &amp; Anomaly AI
            </h1>
            <p class="text-slate-400 text-sm mt-1">Linear trend regression forecasting, storage exhaustion countdowns, and dynamic anomaly detection.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="loadForecasts()" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-sm font-semibold shadow-lg shadow-cyan-600/30 transition-all flex items-center gap-2">
                <i class="fas fa-sync-alt"></i> Refresh Forecasts
            </button>
        </div>
    </div>

    <!-- Predictive KPI Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400 text-xl">
                <i class="fas fa-server"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Analyzed Endpoints</span>
                <h3 class="text-xl font-bold text-white" id="pred-stat-total">0</h3>
            </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 text-xl">
                <i class="fas fa-hourglass-end"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Critical Capacity Risk</span>
                <h3 class="text-xl font-bold text-white" id="pred-stat-critical">0</h3>
            </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 text-xl">
                <i class="fas fa-wave-square"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Dynamic Anomalies</span>
                <h3 class="text-xl font-bold text-white" id="pred-stat-anomalies">0</h3>
            </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-xl">
                <i class="fas fa-tachometer-alt"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Avg Storage Free</span>
                <h3 class="text-xl font-bold text-white font-mono" id="pred-stat-free">0 GB</h3>
            </div>
        </div>
    </div>

    <!-- Capacity Forecasting & Prescriptive Table -->
    <div class="bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 overflow-hidden shadow-xl">
        <div class="px-6 py-4 border-b border-slate-700/60 flex items-center justify-between">
            <h2 class="text-base font-semibold text-white flex items-center gap-2">
                <i class="fas fa-hourglass-half text-cyan-400"></i> Storage Exhaustion Forecasts &amp; Anomaly Signals
            </h2>
            <span class="text-xs text-slate-400 font-mono" id="pred-table-count">Loading predictions...</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-900/60 text-xs text-slate-400 uppercase border-b border-slate-700/60 font-semibold font-mono">
                    <tr>
                        <th class="px-6 py-3.5">Endpoint</th>
                        <th class="px-6 py-3.5">Storage Utilization</th>
                        <th class="px-6 py-3.5">Growth Rate</th>
                        <th class="px-6 py-3.5">Days Until Full</th>
                        <th class="px-6 py-3.5">Anomaly</th>
                        <th class="px-6 py-3.5">Prescriptive AI Recommendation</th>
                        <th class="px-6 py-3.5 text-right">Risk</th>
                    </tr>
                </thead>
                <tbody id="forecast-table-body" class="divide-y divide-slate-700/40">
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Computing linear trend regressions...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
async function loadForecasts() {
    try {
        const resp = await fetch('api.php?action=get_predictive_forecasts');
        const data = await resp.json();
        if (!data.success) return;

        const forecasts = data.forecasts || [];
        document.getElementById('pred-stat-total').textContent = forecasts.length;
        document.getElementById('pred-table-count').textContent = `${forecasts.length} endpoint(s) modeled`;

        let critCount = 0;
        let anomalyCount = 0;
        let totalFreeGb = 0;

        forecasts.forEach(f => {
            if (f.risk_level === 'critical') critCount++;
            if (f.anomaly_detected) anomalyCount++;
            totalFreeGb += parseFloat(f.disk_free_gb || 0);
        });

        document.getElementById('pred-stat-critical').textContent = critCount;
        document.getElementById('pred-stat-anomalies').textContent = anomalyCount;
        document.getElementById('pred-stat-free').textContent = forecasts.length > 0 ? (totalFreeGb / forecasts.length).toFixed(1) + ' GB' : '0 GB';

        renderForecastTable(forecasts);
    } catch (e) {
        console.error('Error loading forecasts', e);
    }
}

function renderForecastTable(forecasts) {
    const tbody = document.getElementById('forecast-table-body');
    if (forecasts.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-slate-500">No host metrics available for predictive regression. Ensure agents are reporting metrics.</td></tr>`;
        return;
    }

    tbody.innerHTML = forecasts.map(f => {
        let riskBadge = '<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">LOW</span>';
        if (f.risk_level === 'critical') riskBadge = '<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-500/20 text-red-400 border border-red-500/30 animate-pulse">CRITICAL</span>';
        else if (f.risk_level === 'warning') riskBadge = '<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-400 border border-amber-500/30">WARNING</span>';

        const anomalyBadge = f.anomaly_detected 
            ? '<span class="text-rose-400 font-mono text-xs font-bold flex items-center gap-1"><i class="fas fa-bolt"></i> Spike</span>'
            : '<span class="text-slate-500 font-mono text-xs">Normal</span>';

        const daysLabel = f.days_until_disk_full > 365 ? '> 1 Year' : `${f.days_until_disk_full} Days`;

        return `
            <tr class="hover:bg-slate-700/30 transition-colors">
                <td class="px-6 py-4">
                    <div class="font-bold text-white text-sm">${f.hostname}</div>
                    <div class="text-xs text-slate-400 font-mono">${f.ip_address || 'N/A'} • ${f.os || 'OS'}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="font-mono text-xs font-bold text-white">${f.current_disk_pct}% (${f.disk_free_gb} GB free)</div>
                    <div class="w-28 bg-slate-700 rounded-full h-1.5 mt-1.5 overflow-hidden">
                        <div class="${f.current_disk_pct >= 85 ? 'bg-red-500' : 'bg-cyan-500'} h-full" style="width: ${f.current_disk_pct}%"></div>
                    </div>
                </td>
                <td class="px-6 py-4 font-mono text-xs text-slate-300">~${f.est_growth_gb_day} GB/day</td>
                <td class="px-6 py-4 font-mono font-bold ${f.days_until_disk_full <= 7 ? 'text-red-400' : 'text-slate-300'} text-xs">${daysLabel}</td>
                <td class="px-6 py-4">${anomalyBadge}</td>
                <td class="px-6 py-4 text-xs text-slate-300 font-mono">${f.recommendation}</td>
                <td class="px-6 py-4 text-right">${riskBadge}</td>
            </tr>
        `;
    }).join('');
}

document.addEventListener('DOMContentLoaded', loadForecasts);
</script>

<?php include 'footer.php'; ?>
