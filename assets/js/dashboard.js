/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
function initDashboard() {
    const API_URL = 'api.php';
    const dashboardLoader = document.getElementById('dashboardLoader');
    const dashboardWidgets = document.getElementById('dashboard-widgets');

    const statusChartCanvas = document.getElementById('statusChart');
    const totalDevicesText = document.getElementById('totalDevicesText');
    const onlineCountEl = document.getElementById('onlineCount');
    const warningCountEl = document.getElementById('warningCount');
    const criticalCountEl = document.getElementById('criticalCount');
    const offlineCountEl = document.getElementById('offlineCount');
    const recentActivityListEl = document.getElementById('recentActivityList');
    const noRecentActivityMessage = document.getElementById('noRecentActivityMessage');
    const deviceInfoContainer = document.getElementById('deviceInfoContainer');
    const noDeviceInfoMessage = document.getElementById('noDeviceInfoMessage');
    const deviceInfoStatusFilter = document.getElementById('deviceInfoStatusFilter');
    const deviceInfoGridBtn = document.getElementById('deviceInfoGridBtn');
    const deviceInfoListBtn = document.getElementById('deviceInfoListBtn');
    const deviceInfoAnimateToggle = document.getElementById('deviceInfoAnimateToggle');
    // const manageDevicesLink = document.getElementById('manageDevicesLink'); // This element is not in index.php anymore, but keeping for consistency if it's added back.
    let statusChart = null;
    let latestDeviceRows = [];
    let deviceViewMode = 'grid';
    let isExplorerExpanded = false;
    const deviceInfoMoreContainer = document.getElementById('deviceInfoMoreContainer');
    const deviceInfoMoreBtn = document.getElementById('deviceInfoMoreBtn');

    const pingForm = document.getElementById('pingForm');
    const pingHostInput = document.getElementById('pingHostInput');
    const pingButton = document.getElementById('pingButton');
    const pingResultContainer = document.getElementById('pingResultContainer');
    const pingResultPre = document.getElementById('pingResultPre');
    const updateStatusWidget = document.getElementById('update-status-widget');
    const updateStatusPill = document.getElementById('update-status-pill');
    const updateLastChecked = document.getElementById('update-last-checked');
    const updateNowBtn = document.getElementById('update-now-btn');

    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json()),
        post: (action, body) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
    };

    const statusColorMap = {
        online: 'text-green-400',
        warning: 'text-yellow-400',
        critical: 'text-red-400',
        offline: 'text-slate-400',
        unknown: 'text-slate-500'
    };

    const statusBadgeClassMap = {
        online: 'bg-green-500/20 text-green-300 border-green-500/30',
        warning: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30',
        critical: 'bg-red-500/20 text-red-300 border-red-500/30',
        offline: 'bg-slate-500/20 text-slate-300 border-slate-500/30',
        unknown: 'bg-slate-600/20 text-slate-300 border-slate-500/30'
    };

    const formatLastSeen = (value) => {
        if (!value) return 'Never';
        const parsed = new Date(value);
        return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleString();
    };

    const formatUpdateCheckedAt = (value) => {
        if (!value) return 'Last checked: never';
        const parsed = new Date(value);
        if (Number.isNaN(parsed.getTime())) return `Last checked: ${value}`;
        return `Last checked: ${parsed.toLocaleString()}`;
    };

    const loadUpdateStatus = async () => {
        if (!updateStatusWidget || window.userRole !== 'admin') return;

        try {
            const response = await fetch('api/update_status.php');
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Failed to load update status.');
            }

            updateStatusWidget.classList.remove('hidden');
            const behindCount = Number(data.behind_count || 0);
            const available = !!data.update_available || behindCount > 0;
            updateStatusPill.textContent = available ? 'Update available' : 'Up to date';
            updateStatusPill.className = `inline-flex px-2 py-0.5 rounded-full border text-[11px] ${available
                ? 'bg-amber-500/20 text-amber-300 border-amber-500/40'
                : 'bg-green-500/20 text-green-300 border-green-500/40'}`;
            updateLastChecked.textContent = formatUpdateCheckedAt(data.last_checked);

            if (available) {
                updateNowBtn.classList.remove('hidden');
                updateNowBtn.disabled = false;
                updateNowBtn.textContent = behindCount > 0 ? `Update now (${behindCount})` : 'Update now';
            } else {
                updateNowBtn.classList.add('hidden');
            }
        } catch (error) {
            console.error(error);
        }
    };

    updateNowBtn?.addEventListener('click', async () => {
        const csrfToken = updateStatusWidget?.dataset?.csrfToken || '';
        if (!csrfToken) {
            window.notyf?.error('Missing CSRF token. Refresh and try again.');
            return;
        }

        updateNowBtn.disabled = true;
        updateNowBtn.textContent = 'Updating...';

        try {
            const body = new URLSearchParams({ csrf_token: csrfToken });
            const response = await fetch('api/run_update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                const logHint = data.log_path ? ` Log: ${data.log_path}` : '';
                throw new Error((data.error || 'Update failed.') + logHint);
            }

            window.notyf?.success(data.message || 'Update completed successfully.');
            await loadUpdateStatus();
        } catch (error) {
            window.notyf?.error(error.message || 'Update failed.');
        } finally {
            updateNowBtn.disabled = false;
            await loadUpdateStatus();
        }
    });

    const applyDeviceViewModeClasses = () => {
        if (!deviceInfoContainer) return;
        if (deviceViewMode === 'list') {
            deviceInfoContainer.className = 'space-y-2';
            deviceInfoGridBtn?.classList.replace('bg-cyan-600', 'bg-slate-700');
            deviceInfoGridBtn?.classList.replace('text-white', 'text-slate-200');
            deviceInfoListBtn?.classList.replace('bg-slate-700', 'bg-cyan-600');
            deviceInfoListBtn?.classList.replace('text-slate-200', 'text-white');
        } else {
            deviceInfoContainer.className = 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3';
            deviceInfoListBtn?.classList.replace('bg-cyan-600', 'bg-slate-700');
            deviceInfoListBtn?.classList.replace('text-white', 'text-slate-200');
            deviceInfoGridBtn?.classList.replace('bg-slate-700', 'bg-cyan-600');
            deviceInfoGridBtn?.classList.replace('text-slate-200', 'text-white');
        }
    };

    const renderDeviceInfo = () => {
        if (!deviceInfoContainer) return;
        const selectedStatus = deviceInfoStatusFilter?.value || 'all';
        const shouldAnimate = !!deviceInfoAnimateToggle?.checked;
        const devices = latestDeviceRows.filter((d) => selectedStatus === 'all' ? true : d.status === selectedStatus);

        applyDeviceViewModeClasses();

        if (!devices.length) {
            deviceInfoContainer.innerHTML = '';
            noDeviceInfoMessage?.classList.remove('hidden');
            deviceInfoMoreContainer?.classList.add('hidden');
            return;
        }
        noDeviceInfoMessage?.classList.add('hidden');

        // Toggle Show More button container and set label
        if (devices.length > 6) {
            deviceInfoMoreContainer?.classList.remove('hidden');
            if (isExplorerExpanded) {
                if (deviceInfoMoreBtn) {
                    deviceInfoMoreBtn.querySelector('span').textContent = 'Show Less';
                    deviceInfoMoreBtn.querySelector('i').className = 'fas fa-chevron-up';
                }
            } else {
                if (deviceInfoMoreBtn) {
                    deviceInfoMoreBtn.querySelector('span').textContent = 'Show More';
                    deviceInfoMoreBtn.querySelector('i').className = 'fas fa-chevron-down';
                }
            }
        } else {
            deviceInfoMoreContainer?.classList.add('hidden');
        }

        const visibleDevices = isExplorerExpanded ? devices : devices.slice(0, 6);

        deviceInfoContainer.innerHTML = visibleDevices.map((device, index) => {
            const status = device.status || 'unknown';
            const statusClass = statusBadgeClassMap[status] || statusBadgeClassMap.unknown;
            const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
            const animClass = shouldAnimate ? 'dashboard-device-enter' : '';
            const delay = shouldAnimate ? `style="animation-delay:${Math.min(index * 45, 400)}ms"` : '';
            const safeDesc = (device.description || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            if (deviceViewMode === 'list') {
                return `
                    <div class="bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-3 flex items-center justify-between gap-3 ${animClass}" ${delay}>
                        <div class="min-w-0">
                            <div class="text-white font-medium truncate">${device.name}</div>
                            <div class="text-xs text-slate-400 font-mono truncate">${device.ip || 'No IP'} • ${device.type || 'device'} • ${device.monitor_method || 'ping'}</div>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="inline-flex items-center px-2 py-1 rounded-full border text-xs ${statusClass}">${statusLabel}</span>
                            <div class="text-[11px] text-slate-500 mt-1">Seen: ${formatLastSeen(device.last_seen)}</div>
                        </div>
                    </div>
                `;
            }
            return `
                <div class="bg-slate-900/60 border border-slate-700 rounded-lg p-4 ${animClass}" ${delay}>
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <h4 class="text-white font-semibold truncate">${device.name}</h4>
                        <span class="inline-flex items-center px-2 py-1 rounded-full border text-xs ${statusClass}">${statusLabel}</span>
                    </div>
                    <div class="space-y-1 text-xs text-slate-300">
                        <div><span class="text-slate-500">IP:</span> <span class="font-mono">${device.ip || 'No IP'}</span></div>
                        <div><span class="text-slate-500">Type:</span> ${device.type || 'device'}</div>
                        <div><span class="text-slate-500">Monitor:</span> ${device.monitor_method || 'ping'} | ${device.ping_interval || '-'}s</div>
                        <div><span class="text-slate-500">Last Seen:</span> ${formatLastSeen(device.last_seen)}</div>
                        ${safeDesc ? `<div class="text-slate-400 italic">${safeDesc}</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
    };

    const loadDashboardData = async (mapId) => {
        if (!mapId) {
            dashboardLoader.classList.add('hidden');
            return;
        }
        dashboardLoader.classList.remove('hidden');
        dashboardWidgets.classList.add('hidden');
        // manageDevicesLink.href = `devices.php?map_id=${mapId}`; // This link is not present in the current index.php

        try {
            const data = await api.get('get_dashboard_data', { map_id: mapId });
            
            // Update total devices text with global count
            totalDevicesText.querySelector('span:first-child').textContent = data.global_total_devices;

            // Use map_stats for the breakdown and chart
            onlineCountEl.textContent = data.map_stats.online;
            warningCountEl.textContent = data.map_stats.warning;
            criticalCountEl.textContent = data.map_stats.critical;
            offlineCountEl.textContent = data.map_stats.offline;

            if (statusChart) {
                statusChart.destroy();
            }
            const chartData = {
                labels: ['Online', 'Warning', 'Critical', 'Offline'],
                datasets: [{
                    data: [data.map_stats.online, data.map_stats.warning, data.map_stats.critical, data.map_stats.offline],
                    backgroundColor: ['#22c55e', '#f59e0b', '#ef4444', '#64748b'],
                    borderColor: '#1e293b',
                    borderWidth: 4,
                }]
            };
            statusChart = new Chart(statusChartCanvas, {
                type: 'doughnut',
                data: chartData,
                options: {
                    responsive: true,
                    cutout: '75%',
                    plugins: { legend: { display: false }, tooltip: { enabled: true } }
                }
            });

            // Render recent activity
            if (data.recent_activity && data.recent_activity.length > 0) {
                recentActivityListEl.innerHTML = data.recent_activity.map(activity => `
                    <div class="border border-slate-700 rounded-lg p-3 flex items-center justify-between">
                        <div>
                            <div class="font-medium text-white">${activity.device_name} <span class="text-sm text-slate-500 font-mono">(${activity.device_ip || 'N/A'})</span></div>
                            <div class="text-sm ${statusColorMap[activity.status] || statusColorMap.unknown}">${activity.status.charAt(0).toUpperCase() + activity.status.slice(1)}: ${activity.details}</div>
                        </div>
                        <div class="text-xs text-slate-500">${new Date(activity.created_at).toLocaleTimeString()}</div>
                    </div>
                `).join('');
                noRecentActivityMessage.classList.add('hidden');
            } else {
                recentActivityListEl.innerHTML = '';
                noRecentActivityMessage.classList.remove('hidden');
            }

            latestDeviceRows = Array.isArray(data.devices) ? data.devices : [];
            renderDeviceInfo();

        } catch (error) {
            console.error("Failed to load dashboard data:", error);
        } finally {
            dashboardLoader.classList.add('hidden');
            dashboardWidgets.classList.remove('hidden');
        }
    };

    createMapSelector('map-selector-container', loadDashboardData).then(selector => {
        if (selector) {
            loadDashboardData(selector.value);
        } else {
            dashboardLoader.classList.add('hidden');
        }
    });

    loadUpdateStatus();

    deviceInfoGridBtn?.addEventListener('click', () => {
        deviceViewMode = 'grid';
        renderDeviceInfo();
    });
    deviceInfoListBtn?.addEventListener('click', () => {
        deviceViewMode = 'list';
        renderDeviceInfo();
    });
    deviceInfoStatusFilter?.addEventListener('change', renderDeviceInfo);
    deviceInfoAnimateToggle?.addEventListener('change', renderDeviceInfo);
    deviceInfoMoreBtn?.addEventListener('click', () => {
        isExplorerExpanded = !isExplorerExpanded;
        renderDeviceInfo();
    });

    // Disable ping form for viewer role
    if (window.userRole === 'viewer') {
        if (pingForm) {
            pingForm.querySelectorAll('input, button').forEach(el => el.disabled = true);
            pingForm.insertAdjacentHTML('afterend', '<p class="text-red-400 text-sm mt-2">You do not have permission to perform ping tests.</p>');
        }
    } else {
        pingForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const host = pingHostInput.value.trim();
            if (!host) return;

            pingButton.disabled = true;
            pingButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Pinging...';
            pingResultContainer.classList.remove('hidden');
            pingResultPre.textContent = `Pinging ${host}...`;

            try {
                const result = await api.post('manual_ping', { host });
                pingResultPre.textContent = result.output || `Error: ${result.error || 'Unknown error'}`;
            } catch (error) {
                pingResultPre.textContent = `Failed to perform ping. Check API connection.`;
            } finally {
                pingButton.disabled = false;
                pingButton.innerHTML = '<i class="fas fa-bolt mr-2"></i>Ping';
            }
        });
    }

    // --- CUSTOM DASHBOARD WIDGETS CUSTOMIZER ---
    const customizeWidgetsBtn = document.getElementById('customize-widgets-btn');
    const customizeWidgetsModal = document.getElementById('customize-widgets-modal');
    const closeCustomizeBtn = document.getElementById('close-customize-btn');
    const closeCustomizeBtnOk = document.getElementById('close-customize-btn-ok');

    const openCustomizeModal = () => {
        if (customizeWidgetsModal) {
            customizeWidgetsModal.classList.remove('hidden');
            customizeWidgetsModal.classList.add('flex');
        }
    };

    const closeCustomizeModal = () => {
        if (customizeWidgetsModal) {
            customizeWidgetsModal.classList.add('hidden');
            customizeWidgetsModal.classList.remove('flex');
        }
    };

    customizeWidgetsBtn?.addEventListener('click', openCustomizeModal);
    closeCustomizeBtn?.addEventListener('click', closeCustomizeModal);
    closeCustomizeBtnOk?.addEventListener('click', closeCustomizeModal);

    const widgetsList = [
        { key: 'server-metrics', checkboxId: 'chk-widget-server-metrics', containerId: 'widget-server-metrics' },
        { key: 'device-overview', checkboxId: 'chk-widget-device-overview', containerId: 'widget-device-overview' },
        { key: 'ping-test', checkboxId: 'chk-widget-ping-test', containerId: 'widget-ping-test' },
        { key: 'recent-activity', checkboxId: 'chk-widget-recent-activity', containerId: 'widget-recent-activity' },
        { key: 'device-explorer', checkboxId: 'chk-widget-device-explorer', containerId: 'widget-device-explorer' }
    ];

    const updateGridMiddleClass = () => {
        const middleGrid = document.getElementById('widget-grid-middle');
        if (!middleGrid) return;

        const pingVisible = document.getElementById('chk-widget-ping-test')?.checked !== false;
        const recentVisible = document.getElementById('chk-widget-recent-activity')?.checked !== false;

        if (pingVisible && recentVisible) {
            middleGrid.className = 'grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8';
            middleGrid.classList.remove('hidden');
        } else if (!pingVisible && !recentVisible) {
            middleGrid.classList.add('hidden');
        } else {
            middleGrid.className = 'grid grid-cols-1 gap-8 mb-8';
            middleGrid.classList.remove('hidden');
        }
    };

    const loadWidgetPreferences = () => {
        let prefs = {};
        try {
            prefs = JSON.parse(localStorage.getItem('ampnm_dashboard_widgets')) || {};
        } catch (e) {
            prefs = {};
        }

        widgetsList.forEach(w => {
            const chk = document.getElementById(w.checkboxId);
            const container = document.getElementById(w.containerId);
            if (!chk) return;

            const visible = prefs[w.key] !== false;
            chk.checked = visible;
            
            if (container) {
                if (visible) {
                    container.classList.remove('hidden');
                } else {
                    container.classList.add('hidden');
                }
            }
        });
        
        updateGridMiddleClass();
    };

    const saveWidgetPreferences = () => {
        const prefs = {};
        widgetsList.forEach(w => {
            const chk = document.getElementById(w.checkboxId);
            if (chk) {
                prefs[w.key] = chk.checked;
            }
        });
        localStorage.setItem('ampnm_dashboard_widgets', JSON.stringify(prefs));
    };

    widgetsList.forEach(w => {
        const chk = document.getElementById(w.checkboxId);
        chk?.addEventListener('change', () => {
            const container = document.getElementById(w.containerId);
            if (container) {
                if (chk.checked) {
                    container.classList.remove('hidden');
                } else {
                    container.classList.add('hidden');
                }
            }
            saveWidgetPreferences();
            updateGridMiddleClass();
            
            if (w.key === 'server-metrics' && chk.checked) {
                pollServerMetrics();
            }
        });
    });

    // --- REALTIME DOCKER HOST SERVER STATUS METRICS ---
    let serverNetChart = null;
    const maxNetworkDataPoints = 10;
    const netChartLabels = [];
    const netChartDataIn = [];
    const netChartDataOut = [];
    let serverMetricsInterval = null;

    const initServerNetChart = () => {
        const ctx = document.getElementById('serverNetChart');
        if (!ctx) return;

        netChartLabels.length = 0;
        netChartDataIn.length = 0;
        netChartDataOut.length = 0;
        for (let i = 0; i < maxNetworkDataPoints; i++) {
            netChartLabels.push('');
            netChartDataIn.push(0);
            netChartDataOut.push(0);
        }

        serverNetChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: netChartLabels,
                datasets: [
                    {
                        label: 'Incoming (In)',
                        data: netChartDataIn,
                        borderColor: '#10b981', // emerald-500
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 2,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'Outgoing (Out)',
                        data: netChartDataOut,
                        borderColor: '#3b82f6', // blue-500
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 2,
                        pointHoverRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { display: false }
                    },
                    y: {
                        grid: { color: 'rgba(71, 85, 105, 0.2)' },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 10 },
                            callback: function(value) {
                                return value + ' Mbps';
                            }
                        }
                    }
                }
            }
        });
    };

    const updateServerMetricsUi = (data) => {
        if (!data) return;

        const hostnameEl = document.getElementById('srv-hostname');
        const osEl = document.getElementById('srv-os');
        if (hostnameEl) hostnameEl.textContent = data.hostname || '--';
        if (osEl) osEl.textContent = data.os_version || '--';

        const cpuValEl = document.getElementById('srv-cpu-val');
        const cpuBarEl = document.getElementById('srv-cpu-bar');
        if (cpuValEl) cpuValEl.textContent = data.cpu + '%';
        if (cpuBarEl) cpuBarEl.style.width = data.cpu + '%';

        if (data.ram) {
            const ramValEl = document.getElementById('srv-ram-val');
            const ramBarEl = document.getElementById('srv-ram-bar');
            const ramUsedEl = document.getElementById('srv-ram-used');
            const ramTotalEl = document.getElementById('srv-ram-total');

            if (ramValEl) ramValEl.textContent = data.ram.percent + '%';
            if (ramBarEl) ramBarEl.style.width = data.ram.percent + '%';
            if (ramUsedEl) ramUsedEl.textContent = parseFloat(data.ram.used).toFixed(2);
            if (ramTotalEl) ramTotalEl.textContent = parseFloat(data.ram.total).toFixed(2);
        }

        if (data.disk) {
            const diskValEl = document.getElementById('srv-disk-val');
            const diskBarEl = document.getElementById('srv-disk-bar');
            const diskUsedEl = document.getElementById('srv-disk-used');
            const diskTotalEl = document.getElementById('srv-disk-total');

            if (diskValEl) diskValEl.textContent = data.disk.percent + '%';
            if (diskBarEl) diskBarEl.style.width = data.disk.percent + '%';
            if (diskUsedEl) diskUsedEl.textContent = parseFloat(data.disk.used).toFixed(1);
            if (diskTotalEl) diskTotalEl.textContent = parseFloat(data.disk.total).toFixed(1);
        }

        if (data.network) {
            const netInEl = document.getElementById('srv-net-in');
            const netOutEl = document.getElementById('srv-net-out');

            const inSpeed = parseFloat(data.network.in_mbps).toFixed(3);
            const outSpeed = parseFloat(data.network.out_mbps).toFixed(3);

            if (netInEl) netInEl.textContent = inSpeed;
            if (netOutEl) netOutEl.textContent = outSpeed;

            if (serverNetChart) {
                const now = new Date();
                const timeLabel = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

                netChartLabels.push(timeLabel);
                netChartDataIn.push(parseFloat(inSpeed));
                netChartDataOut.push(parseFloat(outSpeed));

                if (netChartLabels.length > maxNetworkDataPoints) {
                    netChartLabels.shift();
                    netChartDataIn.shift();
                    netChartDataOut.shift();
                }

                serverNetChart.update('none');
            }
        }
    };

    const pollServerMetrics = async () => {
        const chk = document.getElementById('chk-widget-server-metrics');
        if (chk && !chk.checked) return;

        try {
            const data = await api.get('get_server_metrics');
            if (data && data.success) {
                updateServerMetricsUi(data);
            }
        } catch (e) {
            console.error('Failed to poll server metrics:', e);
        }
    };

    const startServerMetricsPolling = () => {
        initServerNetChart();
        pollServerMetrics();
        if (serverMetricsInterval) clearInterval(serverMetricsInterval);
        serverMetricsInterval = setInterval(pollServerMetrics, 3000);
    };

    loadWidgetPreferences();
    startServerMetricsPolling();
}
