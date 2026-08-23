<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM Public-Facing System Status & Live Incident Board
 */
require_once 'includes/bootstrap.php';
require_once 'includes/db.php';

$pdo = getDbConnection();
$settings = $pdo->query("SELECT * FROM status_page_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$settings || empty($settings['is_public_enabled'])) {
    http_response_code(403);
    die("<div style='font-family:sans-serif; text-align:center; padding:50px; background:#0f172a; color:#cbd5e1; height:100vh;'>
        <h1 style='color:#f87171;'>Status Page Offline</h1>
        <p>The public status page is currently disabled by administrator.</p>
    </div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($settings['title'] ?? 'AMPNM System Status') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="bg-slate-950 text-slate-200 min-h-screen flex flex-col">

    <!-- Header Navigation -->
    <header class="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur sticky top-0 z-40">
        <div class="container mx-auto px-4 py-4 max-w-5xl flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400 text-lg shadow-lg shadow-cyan-500/10">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-white"><?= htmlspecialchars($settings['company_name'] ?? 'AMPNM Global Network') ?></h1>
                    <span class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($settings['title'] ?? 'System Status Board') ?></span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-400 font-mono flex items-center gap-1.5 bg-slate-800/60 border border-slate-700/60 px-3 py-1.5 rounded-full">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> Live Monitoring
                </span>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 max-w-5xl flex-1 space-y-8">
        <!-- Main Overall Status Hero Banner -->
        <div id="overall-status-banner" class="rounded-2xl p-6 border shadow-2xl transition-all duration-500 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-emerald-950/40 border-emerald-500/40">
            <div class="flex items-center gap-4">
                <div id="overall-status-icon" class="w-14 h-14 rounded-2xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-2xl shadow-lg">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-white" id="overall-status-text">All Systems Operational</h2>
                    <p class="text-slate-400 text-xs mt-1" id="overall-status-subtext"><?= htmlspecialchars($settings['header_message'] ?? 'All services and network nodes are operating within normal parameters.') ?></p>
                </div>
            </div>
            <div class="text-right sm:self-center">
                <span class="text-[11px] text-slate-400 font-mono" id="last-updated-time">Checking status...</span>
            </div>
        </div>

        <!-- Scheduled Maintenance Alert (if active) -->
        <div id="maintenance-alert-box" class="hidden bg-amber-950/40 border border-amber-500/40 rounded-2xl p-5 shadow-lg">
            <div class="flex items-center gap-3 mb-2 text-amber-400 font-semibold text-sm">
                <i class="fas fa-tools"></i> Scheduled System Maintenance
            </div>
            <p class="text-slate-300 text-xs" id="maintenance-alert-text"></p>
        </div>

        <!-- Active Incidents Stream -->
        <div id="active-incidents-container" class="space-y-4 hidden">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-exclamation-triangle text-amber-400"></i> Active Incidents
            </h3>
            <div id="active-incidents-list" class="space-y-4"></div>
        </div>

        <!-- Grouped Infrastructure Services & Components -->
        <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-6 shadow-xl space-y-6">
            <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fas fa-layer-group text-cyan-400"></i> Service &amp; Infrastructure Components
                </h3>
                <span class="text-xs text-slate-400 font-mono">90-Day Operational History</span>
            </div>

            <div id="components-group-list" class="space-y-6">
                <div class="text-center py-12 text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading live components...</div>
            </div>
        </div>

        <!-- Past Incident Postmortems (Last 7 Days) -->
        <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-6 shadow-xl space-y-4">
            <h3 class="text-base font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
                <i class="fas fa-history text-cyan-400"></i> Past Incidents &amp; Postmortems
            </h3>
            <div id="past-incidents-list" class="space-y-4">
                <div class="text-xs text-slate-500 italic py-4">No major incidents reported in the past 7 days.</div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800/80 bg-slate-900/40 py-6 text-center text-xs text-slate-500">
        <div class="container mx-auto px-4 max-w-5xl flex flex-col sm:flex-row items-center justify-between gap-2">
            <span>Powered by <strong class="text-cyan-400">AMPNM Network Monitor</strong></span>
            <span>Auto-refreshing every 60s</span>
        </div>
    </footer>

<script>
async function fetchStatusPageData() {
    try {
        const resp = await fetch('api.php?action=get_public_status_page');
        const data = await resp.json();
        if (!data.success) return;

        document.getElementById('last-updated-time').textContent = 'Updated: ' + new Date().toLocaleTimeString();

        // 1. Evaluate Overall Status
        evaluateOverallStatus(data.components, data.incidents, data.maintenance_windows);

        // 2. Render Maintenance Windows
        renderMaintenanceNotices(data.maintenance_windows);

        // 3. Render Components
        renderComponents(data.components);

        // 4. Render Active & Past Incidents
        renderIncidents(data.incidents);

    } catch (e) {
        console.error('Failed to load public status page data', e);
    }
}

function evaluateOverallStatus(components, incidents, maintenances) {
    const banner = document.getElementById('overall-status-banner');
    const icon = document.getElementById('overall-status-icon');
    const title = document.getElementById('overall-status-text');

    const hasOutage = components.some(c => c.status === 'outage') || incidents.some(i => i.status !== 'resolved' && (i.impact === 'major' || i.impact === 'critical'));
    const hasDegraded = components.some(c => c.status === 'degraded') || incidents.some(i => i.status !== 'resolved');
    const hasMaintenance = maintenances && maintenances.length > 0;

    if (hasOutage) {
        banner.className = 'rounded-2xl p-6 border shadow-2xl transition-all duration-500 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-red-950/50 border-red-500/50';
        icon.className = 'w-14 h-14 rounded-2xl bg-red-500/20 border border-red-500/30 flex items-center justify-center text-red-400 text-2xl shadow-lg';
        icon.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
        title.textContent = 'Major Service Outage Reported';
    } else if (hasDegraded) {
        banner.className = 'rounded-2xl p-6 border shadow-2xl transition-all duration-500 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-amber-950/50 border-amber-500/50';
        icon.className = 'w-14 h-14 rounded-2xl bg-amber-500/20 border border-amber-500/30 flex items-center justify-center text-amber-400 text-2xl shadow-lg';
        icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
        title.textContent = 'Degraded Performance';
    } else if (hasMaintenance) {
        banner.className = 'rounded-2xl p-6 border shadow-2xl transition-all duration-500 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-blue-950/50 border-blue-500/50';
        icon.className = 'w-14 h-14 rounded-2xl bg-blue-500/20 border border-blue-500/30 flex items-center justify-center text-blue-400 text-2xl shadow-lg';
        icon.innerHTML = '<i class="fas fa-tools"></i>';
        title.textContent = 'Scheduled System Maintenance In Progress';
    } else {
        banner.className = 'rounded-2xl p-6 border shadow-2xl transition-all duration-500 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-emerald-950/40 border-emerald-500/40';
        icon.className = 'w-14 h-14 rounded-2xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-2xl shadow-lg';
        icon.innerHTML = '<i class="fas fa-check-circle"></i>';
        title.textContent = 'All Systems Operational';
    }
}

function renderMaintenanceNotices(maintenances) {
    const box = document.getElementById('maintenance-alert-box');
    if (!maintenances || maintenances.length === 0) {
        box.classList.add('hidden');
        return;
    }

    const activeM = maintenances[0];
    document.getElementById('maintenance-alert-text').textContent = `${activeM.title} scheduled from ${activeM.start_time} to ${activeM.end_time}. ${activeM.notes || ''}`;
    box.classList.remove('hidden');
}

function renderComponents(components) {
    const container = document.getElementById('components-group-list');
    if (components.length === 0) {
        container.innerHTML = `<div class="text-center py-6 text-slate-500">No components registered.</div>`;
        return;
    }

    // Group components
    const groups = {};
    components.forEach(c => {
        const gName = c.group_name || 'Core Services';
        if (!groups[gName]) groups[gName] = [];
        groups[gName].push(c);
    });

    container.innerHTML = Object.keys(groups).map(gName => {
        const compList = groups[gName];
        return `
            <div class="space-y-3">
                <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider font-mono">${gName}</h4>
                <div class="space-y-2">
                    ${compList.map(c => {
                        let badge = '<span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">Operational</span>';
                        if (c.status === 'degraded') badge = '<span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-500/20 text-amber-400 border border-amber-500/30">Degraded</span>';
                        else if (c.status === 'outage') badge = '<span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-red-500/20 text-red-400 border border-red-500/30">Major Outage</span>';
                        else if (c.status === 'maintenance') badge = '<span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-500/20 text-blue-400 border border-blue-500/30">Maintenance</span>';

                        return `
                            <div class="bg-slate-950/60 border border-slate-800/80 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:border-slate-700 transition-colors">
                                <div>
                                    <div class="font-semibold text-white text-sm">${c.name}</div>
                                    ${c.linked_device_name ? `<div class="text-[11px] text-slate-500 font-mono">Linked: ${c.linked_device_name}</div>` : ''}
                                </div>
                                <div class="flex items-center gap-4">
                                    <!-- 90-day bar mockup -->
                                    <div class="hidden md:flex items-center gap-1 opacity-75">
                                        ${Array.from({length: 20}).map(() => `<span class="w-1 h-5 rounded-full bg-emerald-500/80"></span>`).join('')}
                                    </div>
                                    ${badge}
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }).join('');
}

function renderIncidents(incidents) {
    const activeCont = document.getElementById('active-incidents-container');
    const activeList = document.getElementById('active-incidents-list');
    const pastList = document.getElementById('past-incidents-list');

    const active = incidents.filter(i => i.status !== 'resolved');
    const past = incidents.filter(i => i.status === 'resolved');

    if (active.length > 0) {
        activeCont.classList.remove('hidden');
        activeList.innerHTML = active.map(i => renderIncidentCard(i, true)).join('');
    } else {
        activeCont.classList.add('hidden');
    }

    if (past.length > 0) {
        pastList.innerHTML = past.map(i => renderIncidentCard(i, false)).join('');
    } else {
        pastList.innerHTML = `<div class="text-xs text-slate-500 italic py-4">No major incidents reported in the past 7 days.</div>`;
    }
}

function renderIncidentCard(inc, isActive) {
    const badgeColor = isActive ? 'bg-amber-500/20 text-amber-400 border-amber-500/30' : 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30';
    return `
        <div class="bg-slate-950/70 border ${isActive ? 'border-amber-500/40' : 'border-slate-800'} rounded-xl p-5 shadow-lg space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase ${badgeColor}">${inc.status}</span>
                    <h4 class="font-bold text-white text-sm">${inc.title}</h4>
                </div>
                <span class="text-xs text-slate-400 font-mono">${inc.created_at}</span>
            </div>

            <!-- Incident Updates Stream -->
            <div class="space-y-2.5 pl-2 border-l-2 border-slate-800 font-mono text-xs">
                ${(inc.updates || []).map(u => `
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-cyan-400 font-semibold">
                            <span class="capitalize">${u.status_state}</span>
                            <span class="text-slate-500 text-[10px]">- ${u.created_at}</span>
                        </div>
                        <p class="text-slate-300 font-sans text-xs">${u.message}</p>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

document.addEventListener('DOMContentLoaded', () => {
    fetchStatusPageData();
    setInterval(fetchStatusPageData, 60000);
});
</script>
</body>
</html>
