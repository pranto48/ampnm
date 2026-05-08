function initStatusLogs() {
    const API_URL = 'api.php';
    let statusLogChart = null;
    let liveInterval = null;

    const els = {
        mapSelector: document.getElementById('mapSelector'),
        deviceSelector: document.getElementById('deviceSelector'),
        periodSelector: document.getElementById('periodSelector'),
        chartTitle: document.getElementById('chartTitle'),
        chartLoader: document.getElementById('chartLoader'),
        chartContainer: document.getElementById('chartContainer'),
        statusLogChartCanvas: document.getElementById('statusLogChart'),
        noDataMessage: document.getElementById('noDataMessage'),
        downtimeScope: document.getElementById('downtimeScope'),
        downtimeSummaryTable: document.getElementById('downtimeSummaryTable'),
        offlineLogsTable: document.getElementById('offlineLogsTable'),
        backupScheduleForm: document.getElementById('backupScheduleForm'),
        backupScheduleId: document.getElementById('backupScheduleId'),
        backupName: document.getElementById('backupName'),
        backupTargetType: document.getElementById('backupTargetType'),
        backupPeriodScope: document.getElementById('backupPeriodScope'),
        backupScheduleType: document.getElementById('backupScheduleType'),
        backupScheduleTime: document.getElementById('backupScheduleTime'),
        backupDayOfWeek: document.getElementById('backupDayOfWeek'),
        backupDayOfMonth: document.getElementById('backupDayOfMonth'),
        backupEnabled: document.getElementById('backupEnabled'),
        backupTargetConfig: document.getElementById('backupTargetConfig'),
        backupSchedulesTable: document.getElementById('backupSchedulesTable'),
        backupScheduleReset: document.getElementById('backupScheduleReset'),
    };

    const state = { currentMapId: null, currentDeviceId: '', currentPeriod: '24h' };
    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json()),
        post: (action, body = {}) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
    };

    const resetScheduleForm = () => {
        els.backupScheduleId.value = '';
        els.backupName.value = '';
        els.backupTargetType.value = 'ftp';
        els.backupPeriodScope.value = 'day';
        els.backupScheduleType.value = 'daily';
        els.backupScheduleTime.value = '00:15';
        els.backupDayOfWeek.value = '';
        els.backupDayOfMonth.value = '';
        els.backupEnabled.checked = true;
        els.backupTargetConfig.value = '';
    };

    const populateMapSelector = async () => {
        const maps = await api.get('get_maps');
        if (maps.length > 0) {
            els.mapSelector.innerHTML = maps.map(map => `<option value="${map.id}">${map.name}</option>`).join('');
            state.currentMapId = maps[0].id;
        } else {
            els.mapSelector.innerHTML = '<option>No maps found</option>';
        }
    };

    const populateDeviceSelector = async () => {
        if (!state.currentMapId) return;
        const devices = await api.get('get_devices', { map_id: state.currentMapId });
        els.deviceSelector.innerHTML = '<option value="">All Devices</option>' +
            devices.devices.map(d => `<option value="${d.id}">${d.name} (${d.ip || 'No IP'})</option>`).join('');
    };

    const loadChartData = async () => {
        if (liveInterval) clearInterval(liveInterval);
        els.chartLoader.classList.remove('hidden');
        els.chartContainer.classList.add('hidden');
        els.noDataMessage.classList.add('hidden');
        if (statusLogChart) statusLogChart.destroy();

        const data = await api.get('get_status_logs', {
            map_id: state.currentMapId,
            device_id: state.currentDeviceId,
            period: state.currentPeriod
        });

        els.chartLoader.classList.add('hidden');
        if (data.length === 0) {
            els.noDataMessage.classList.remove('hidden');
            return;
        }
        els.chartContainer.classList.remove('hidden');
        const labels = data.map(d => d.time_group);
        const datasets = [
            { label: 'Critical', data: data.map(d => d.critical_count), backgroundColor: '#ef4444' },
            { label: 'Warning', data: data.map(d => d.warning_count), backgroundColor: '#f59e0b' },
            { label: 'Offline', data: data.map(d => d.offline_count), backgroundColor: '#64748b' },
        ];
        statusLogChart = new Chart(els.statusLogChartCanvas, {
            type: 'bar',
            data: { labels, datasets },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#cbd5e1' } } },
                scales: {
                    x: { type: 'time', time: { unit: state.currentPeriod === '24h' || state.currentPeriod === 'live' ? 'hour' : 'day' }, ticks: { color: '#94a3b8' }, grid: { color: '#334155' } },
                    y: { stacked: true, beginAtZero: true, ticks: { color: '#94a3b8', stepSize: 1 }, grid: { color: '#334155' } }
                }
            }
        });
        if (state.currentPeriod === 'live' && window.userRole === 'admin') {
            liveInterval = setInterval(loadChartData, 30000);
        }
    };

    const loadDowntimeAndOffline = async () => {
        const scope = els.downtimeScope.value || 'day';
        const [summary, offline] = await Promise.all([
            api.get('get_downtime_summary', { map_id: state.currentMapId, device_id: state.currentDeviceId, scope }),
            api.get('get_offline_logs', { map_id: state.currentMapId, device_id: state.currentDeviceId, scope })
        ]);

        els.downtimeSummaryTable.innerHTML = summary.length
            ? summary.map(row => `<tr class="border-b border-slate-700"><td class="px-3 py-2 text-slate-300">${row.bucket}</td><td class="px-3 py-2 text-white">${row.device_name}</td><td class="px-3 py-2 text-red-300">${row.offline_events}</td></tr>`).join('')
            : '<tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">No downtime data.</td></tr>';

        els.offlineLogsTable.innerHTML = offline.length
            ? offline.map(row => `<tr class="border-b border-slate-700"><td class="px-3 py-2 text-slate-300">${row.created_at}</td><td class="px-3 py-2 text-white">${row.device_name}</td><td class="px-3 py-2 text-slate-400">${row.details || '-'}</td></tr>`).join('')
            : '<tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">No offline events.</td></tr>';
    };

    const loadBackupSchedules = async () => {
        const schedules = await api.get('get_log_backup_schedules');
        els.backupSchedulesTable.innerHTML = schedules.length
            ? schedules.map(s => `
                <tr class="border-b border-slate-700">
                    <td class="px-3 py-2 text-white">${s.name}</td>
                    <td class="px-3 py-2 text-slate-300 uppercase">${s.target_type}</td>
                    <td class="px-3 py-2 text-slate-300">${s.period_scope}</td>
                    <td class="px-3 py-2 text-slate-300">${s.schedule_type} @ ${s.schedule_time}</td>
                    <td class="px-3 py-2 text-slate-400">${s.last_run_at || '-'}</td>
                    <td class="px-3 py-2 space-x-2">
                        <button class="backup-edit text-cyan-300" data-id="${s.id}">Edit</button>
                        <button class="backup-run text-emerald-300" data-id="${s.id}">Run</button>
                        <button class="backup-delete text-red-300" data-id="${s.id}">Delete</button>
                    </td>
                </tr>
            `).join('')
            : '<tr><td colspan="6" class="px-3 py-4 text-center text-slate-500">No schedules configured.</td></tr>';

        els.backupSchedulesTable.querySelectorAll('.backup-run').forEach(btn => btn.addEventListener('click', async () => {
            const id = Number(btn.dataset.id);
            const result = await api.post('run_log_backup_now', { id });
            if (result.success) window.notyf.success(result.message || 'Backup done');
            else window.notyf.error(result.error || 'Backup failed');
            loadBackupSchedules();
        }));
        els.backupSchedulesTable.querySelectorAll('.backup-delete').forEach(btn => btn.addEventListener('click', async () => {
            const id = Number(btn.dataset.id);
            if (!confirm('Delete backup schedule?')) return;
            const result = await api.post('delete_log_backup_schedule', { id });
            if (result.success) window.notyf.success('Deleted');
            else window.notyf.error(result.error || 'Delete failed');
            loadBackupSchedules();
        }));
        els.backupSchedulesTable.querySelectorAll('.backup-edit').forEach(btn => btn.addEventListener('click', async () => {
            const schedulesFresh = await api.get('get_log_backup_schedules');
            const s = schedulesFresh.find(x => String(x.id) === String(btn.dataset.id));
            if (!s) return;
            els.backupScheduleId.value = s.id;
            els.backupName.value = s.name || '';
            els.backupTargetType.value = s.target_type || 'ftp';
            els.backupPeriodScope.value = s.period_scope || 'day';
            els.backupScheduleType.value = s.schedule_type || 'daily';
            els.backupScheduleTime.value = String(s.schedule_time || '00:15:00').slice(0, 5);
            els.backupDayOfWeek.value = s.day_of_week || '';
            els.backupDayOfMonth.value = s.day_of_month || '';
            els.backupEnabled.checked = Number(s.enabled || 0) === 1;
            els.backupTargetConfig.value = s.target_config || '';
        }));
    };

    els.mapSelector.addEventListener('change', async () => {
        state.currentMapId = els.mapSelector.value;
        await populateDeviceSelector();
        state.currentDeviceId = '';
        await loadChartData();
        await loadDowntimeAndOffline();
    });
    els.deviceSelector.addEventListener('change', async () => {
        state.currentDeviceId = els.deviceSelector.value;
        await loadChartData();
        await loadDowntimeAndOffline();
    });
    els.downtimeScope?.addEventListener('change', loadDowntimeAndOffline);

    els.periodSelector.addEventListener('click', (e) => {
        if (e.target.tagName !== 'BUTTON') return;
        const newPeriod = e.target.dataset.period;
        if (newPeriod === 'live' && window.userRole === 'viewer') {
            window.notyf.error('You do not have permission to view live status logs.');
            return;
        }
        state.currentPeriod = newPeriod;
        els.periodSelector.querySelectorAll('button').forEach(btn => btn.classList.remove('bg-slate-700', 'text-white'));
        e.target.classList.add('bg-slate-700', 'text-white');
        els.chartTitle.textContent = newPeriod === 'live' ? 'Live Status Events (Last 1 Hour)' : `Status Events in the Last ${e.target.textContent}`;
        loadChartData();
    });

    els.backupScheduleForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const targetConfigParsed = els.backupTargetConfig.value.trim() ? JSON.parse(els.backupTargetConfig.value.trim()) : {};
            const payload = {
                id: els.backupScheduleId.value ? Number(els.backupScheduleId.value) : null,
                name: els.backupName.value.trim(),
                target_type: els.backupTargetType.value,
                period_scope: els.backupPeriodScope.value,
                schedule_type: els.backupScheduleType.value,
                schedule_time: `${els.backupScheduleTime.value || '00:15'}:00`,
                day_of_week: els.backupDayOfWeek.value ? Number(els.backupDayOfWeek.value) : null,
                day_of_month: els.backupDayOfMonth.value ? Number(els.backupDayOfMonth.value) : null,
                enabled: els.backupEnabled.checked,
                target_config: targetConfigParsed
            };
            const result = await api.post('save_log_backup_schedule', payload);
            if (result.success) {
                window.notyf.success('Schedule saved');
                resetScheduleForm();
                loadBackupSchedules();
            } else {
                window.notyf.error(result.error || 'Failed to save schedule');
            }
        } catch (err) {
            window.notyf.error('Target config must be valid JSON');
        }
    });
    els.backupScheduleReset?.addEventListener('click', resetScheduleForm);

    if (window.userRole === 'viewer' && els.backupScheduleForm) {
        els.backupScheduleForm.querySelectorAll('input,select,textarea,button').forEach(el => el.disabled = true);
    }

    (async () => {
        try {
            if (window.userRole === 'admin') {
                await api.post('run_due_log_backups', {});
            }
            await populateMapSelector();
            await populateDeviceSelector();
            await loadChartData();
            await loadDowntimeAndOffline();
            await loadBackupSchedules();
            resetScheduleForm();
        } catch (error) {
            console.error(error);
            window.notyf.error('Failed to load status log modules.');
        }
    })();
}
