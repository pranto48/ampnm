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
        backupScheduleId:   document.getElementById('backupScheduleId'),
        backupName:         document.getElementById('backupName'),
        backupTargetType:   document.getElementById('backupTargetType'),
        backupPeriodScope:  document.getElementById('backupPeriodScope'),
        backupScheduleType: document.getElementById('backupScheduleType'),
        backupScheduleTime: document.getElementById('backupScheduleTime'),
        backupDayOfWeek:    document.getElementById('backupDayOfWeek'),
        backupDayOfMonth:   document.getElementById('backupDayOfMonth'),
        backupEnabled:      document.getElementById('backupEnabled'),
        backupSchedulesTable: document.getElementById('backupSchedulesTable'),
        backupScheduleReset:  document.getElementById('backupScheduleReset'),
        backupFormPanel:    document.getElementById('backupFormPanel'),
        backupFormTitle:    document.getElementById('backupFormTitle'),
        showBackupFormBtn:  document.getElementById('showBackupFormBtn'),
        // NAS fields
        lbNasGroup:       document.getElementById('lbNasGroup'),
        lbNasIp:          document.getElementById('lbNasIp'),
        lbNasPort:        document.getElementById('lbNasPort'),
        lbNasUsername:    document.getElementById('lbNasUsername'),
        lbNasPassword:    document.getElementById('lbNasPassword'),
        lbNasMountPath:   document.getElementById('lbNasMountPath'),
        lbNasTestBtn:     document.getElementById('lbNasTestBtn'),
        lbNasTestResults: document.getElementById('lbNasTestResults'),
        // FTP fields
        lbFtpGroup:  document.getElementById('lbFtpGroup'),
        lbFtpHost:   document.getElementById('lbFtpHost'),
        lbFtpPort:   document.getElementById('lbFtpPort'),
        lbFtpUser:   document.getElementById('lbFtpUser'),
        lbFtpPass:   document.getElementById('lbFtpPass'),
        lbFtpPath:   document.getElementById('lbFtpPath'),
        // Email fields
        lbEmailGroup:    document.getElementById('lbEmailGroup'),
        lbEmailRecipient:document.getElementById('lbEmailRecipient'),
        // Schedule day groups
        lbWeeklyGroup:  document.getElementById('lbWeeklyGroup'),
        lbMonthlyGroup: document.getElementById('lbMonthlyGroup'),
    };

    const state = { currentMapId: null, currentDeviceId: '', currentPeriod: '24h' };
    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json()),
        post: (action, body = {}) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
    };

    // ── Target type group toggling ──────────────────────────────────────────
    const showTargetGroup = (type) => {
        const groups = { nas: els.lbNasGroup, ftp: els.lbFtpGroup, email: els.lbEmailGroup };
        Object.entries(groups).forEach(([t, el]) => {
            if (el) el.classList.toggle('hidden', t !== type);
        });
    };
    els.backupTargetType?.addEventListener('change', () => showTargetGroup(els.backupTargetType.value));

    // ── Schedule type (weekly/monthly day pickers) ──────────────────────────
    const syncScheduleFields = () => {
        const t = els.backupScheduleType?.value;
        if (els.lbWeeklyGroup)  els.lbWeeklyGroup.classList.toggle('hidden', t !== 'weekly');
        if (els.lbMonthlyGroup) els.lbMonthlyGroup.classList.toggle('hidden', t !== 'monthly');
    };
    els.backupScheduleType?.addEventListener('change', syncScheduleFields);

    // ── Form panel show/hide ────────────────────────────────────────────────
    const showForm = (title = 'Create Backup Schedule') => {
        if (els.backupFormPanel)  els.backupFormPanel.classList.remove('hidden');
        if (els.backupFormTitle)  els.backupFormTitle.textContent = title;
        if (els.showBackupFormBtn) els.showBackupFormBtn.classList.add('hidden');
        els.backupFormPanel?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };
    const hideForm = () => {
        if (els.backupFormPanel)  els.backupFormPanel.classList.add('hidden');
        if (els.showBackupFormBtn) els.showBackupFormBtn.classList.remove('hidden');
    };
    els.showBackupFormBtn?.addEventListener('click', () => { resetScheduleForm(); showForm(); });

    // ── Reset form ──────────────────────────────────────────────────────────
    const resetScheduleForm = () => {
        els.backupScheduleId.value = '';
        els.backupName.value = '';
        els.backupTargetType.value = 'nas';
        els.backupPeriodScope.value = 'day';
        els.backupScheduleType.value = 'daily';
        els.backupScheduleTime.value = '00:15';
        if (els.backupDayOfWeek) els.backupDayOfWeek.value = '1';
        if (els.backupDayOfMonth) els.backupDayOfMonth.value = '1';
        els.backupEnabled.checked = true;
        // NAS
        if (els.lbNasIp) els.lbNasIp.value = '';
        if (els.lbNasPort) els.lbNasPort.value = '';
        if (els.lbNasUsername) els.lbNasUsername.value = '';
        if (els.lbNasPassword) els.lbNasPassword.value = '';
        if (els.lbNasMountPath) els.lbNasMountPath.value = '';
        if (els.lbNasTestResults) { els.lbNasTestResults.innerHTML = ''; els.lbNasTestResults.classList.add('hidden'); }
        // FTP
        if (els.lbFtpHost) els.lbFtpHost.value = '';
        if (els.lbFtpPort) els.lbFtpPort.value = '21';
        if (els.lbFtpUser) els.lbFtpUser.value = '';
        if (els.lbFtpPass) els.lbFtpPass.value = '';
        if (els.lbFtpPath) els.lbFtpPath.value = '/backups/logs';
        // Email
        if (els.lbEmailRecipient) els.lbEmailRecipient.value = '';
        showTargetGroup('nas');
        syncScheduleFields();
    };

    // ── Build target_config from structured fields ───────────────────────────
    const buildTargetConfig = () => {
        const type = els.backupTargetType.value;
        if (type === 'nas') {
            return {
                mount_path:   (els.lbNasMountPath?.value || '').trim(),
                nas_ip:       (els.lbNasIp?.value || '').trim(),
                nas_port:     els.lbNasPort?.value ? parseInt(els.lbNasPort.value) : null,
                nas_username: (els.lbNasUsername?.value || '').trim(),
                nas_password: els.lbNasPassword?.value || '',
            };
        }
        if (type === 'ftp') {
            return {
                host:        (els.lbFtpHost?.value || '').trim(),
                port:        parseInt(els.lbFtpPort?.value || '21'),
                username:    (els.lbFtpUser?.value || '').trim(),
                password:    els.lbFtpPass?.value || '',
                remote_path: (els.lbFtpPath?.value || '/backups/logs').trim(),
            };
        }
        if (type === 'email') {
            return { recipient_email: (els.lbEmailRecipient?.value || '').trim() };
        }
        return {};
    };

    // ── Populate structured fields from stored target_config ────────────────
    const populateTargetFields = (type, cfg) => {
        els.backupTargetType.value = type;
        showTargetGroup(type);
        if (type === 'nas') {
            if (els.lbNasMountPath) els.lbNasMountPath.value = cfg.mount_path || '';
            if (els.lbNasIp)       els.lbNasIp.value       = cfg.nas_ip || '';
            if (els.lbNasPort)     els.lbNasPort.value     = cfg.nas_port || '';
            if (els.lbNasUsername) els.lbNasUsername.value = cfg.nas_username || '';
            if (els.lbNasPassword) els.lbNasPassword.value = cfg.nas_password || '';
        } else if (type === 'ftp') {
            if (els.lbFtpHost) els.lbFtpHost.value = cfg.host || '';
            if (els.lbFtpPort) els.lbFtpPort.value = cfg.port || '21';
            if (els.lbFtpUser) els.lbFtpUser.value = cfg.username || '';
            if (els.lbFtpPass) els.lbFtpPass.value = cfg.password || '';
            if (els.lbFtpPath) els.lbFtpPath.value = cfg.remote_path || '/backups/logs';
        } else if (type === 'email') {
            if (els.lbEmailRecipient) els.lbEmailRecipient.value = cfg.recipient_email || '';
        }
    };

    // ── NAS Connection Test ─────────────────────────────────────────────────
    els.lbNasTestBtn?.addEventListener('click', async () => {
        const btn = els.lbNasTestBtn;
        const resultsEl = els.lbNasTestResults;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing…';
        resultsEl.classList.remove('hidden');
        resultsEl.innerHTML = '<div class="px-4 py-3 text-slate-400 text-sm">Running connection tests…</div>';

        try {
            const result = await api.post('nas_test_connection', {
                nas_ip:       (els.lbNasIp?.value || '').trim(),
                nas_port:     els.lbNasPort?.value ? parseInt(els.lbNasPort.value) : 0,
                nas_username: (els.lbNasUsername?.value || '').trim(),
                nas_password: els.lbNasPassword?.value || '',
                nas_path:     (els.lbNasMountPath?.value || '').trim(),
                protocol:     'smb',
            });
            const steps = result.results || [];
            resultsEl.innerHTML = steps.map(s => `
                <div class="flex items-start gap-3 px-4 py-2.5 ${s.ok ? 'bg-green-900/20' : 'bg-red-900/20'} border-b border-slate-700/50 last:border-0">
                    <i class="fas ${s.ok ? 'fa-check-circle text-green-400' : 'fa-times-circle text-red-400'} mt-0.5 shrink-0"></i>
                    <div>
                        <span class="text-xs font-semibold ${s.ok ? 'text-green-300' : 'text-red-300'}">${s.step}</span>
                        <p class="text-xs text-slate-400 mt-0.5">${s.msg}</p>
                    </div>
                </div>`).join('');
            if (result.success) {
                window.notyf.success('NAS connection test passed!');
            } else {
                window.notyf.error('NAS test failed — see results below.');
            }
        } catch (err) {
            resultsEl.innerHTML = '<div class="px-4 py-3 text-red-400 text-sm"><i class="fas fa-exclamation-circle mr-2"></i>Test request failed. Check console.</div>';
            console.error(err);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plug"></i> Test Connection';
        }
    });

    // ── Map/Device selection ────────────────────────────────────────────────
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

    // ── Chart ────────────────────────────────────────────────────────────────
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
        if (data.length === 0) { els.noDataMessage.classList.remove('hidden'); return; }
        els.chartContainer.classList.remove('hidden');
        const labels = data.map(d => d.time_group);
        const datasets = [
            { label: 'Critical', data: data.map(d => d.critical_count), backgroundColor: '#ef4444' },
            { label: 'Warning',  data: data.map(d => d.warning_count),  backgroundColor: '#f59e0b' },
            { label: 'Offline',  data: data.map(d => d.offline_count),  backgroundColor: '#64748b' },
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
            api.get('get_offline_logs',     { map_id: state.currentMapId, device_id: state.currentDeviceId, scope })
        ]);

        els.downtimeSummaryTable.innerHTML = summary.length
            ? summary.map(row => `<tr class="border-b border-slate-700"><td class="px-3 py-2 text-slate-300">${row.bucket}</td><td class="px-3 py-2 text-white">${row.device_name}</td><td class="px-3 py-2 text-red-300">${row.offline_events}</td></tr>`).join('')
            : '<tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">No downtime data.</td></tr>';

        els.offlineLogsTable.innerHTML = offline.length
            ? offline.map(row => `<tr class="border-b border-slate-700"><td class="px-3 py-2 text-slate-300">${row.created_at}</td><td class="px-3 py-2 text-white">${row.device_name}</td><td class="px-3 py-2 text-slate-400">${row.details || '-'}</td></tr>`).join('')
            : '<tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">No offline events.</td></tr>';
    };

    // ── Backup Schedules Table ───────────────────────────────────────────────
    const targetIcon = { nas: 'fa-server', ftp: 'fa-upload', email: 'fa-envelope', smb: 'fa-server' };
    const targetColor = { nas: 'text-cyan-400', ftp: 'text-indigo-400', email: 'text-emerald-400', smb: 'text-cyan-400' };

    const loadBackupSchedules = async () => {
        const schedules = await api.get('get_log_backup_schedules');
        if (!schedules.length) {
            els.backupSchedulesTable.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-slate-500 italic">No log backup schedules configured. Click "New Schedule" to create one.</td></tr>';
            return;
        }
        els.backupSchedulesTable.innerHTML = schedules.map(s => {
            const cfg = (() => { try { return JSON.parse(s.target_config || '{}'); } catch { return {}; } })();
            const dest = s.target_type === 'nas' ? (cfg.mount_path || '—')
                       : s.target_type === 'ftp' ? (cfg.host || '—')
                       : s.target_type === 'email' ? (cfg.recipient_email || '—')
                       : (cfg.mount_path || '—');
            const icon = targetIcon[s.target_type] || 'fa-database';
            const color = targetColor[s.target_type] || 'text-slate-400';
            const enabledBadge = Number(s.enabled)
                ? '<span class="px-1.5 py-0.5 rounded-full text-xs bg-green-900/40 text-green-300 border border-green-700/50">Active</span>'
                : '<span class="px-1.5 py-0.5 rounded-full text-xs bg-slate-700 text-slate-400 border border-slate-600">Paused</span>';
            return `
                <tr class="border-b border-slate-700/50 hover:bg-slate-700/20 transition-colors">
                    <td class="px-4 py-3 text-white font-medium">${s.name}</td>
                    <td class="px-4 py-3">
                        <span class="flex items-center gap-1.5 ${color}">
                            <i class="fas ${icon} text-xs"></i>
                            <span class="text-xs font-semibold uppercase">${s.target_type}</span>
                        </span>
                        <span class="text-xs text-slate-500 font-mono block mt-0.5 truncate max-w-[140px]" title="${dest}">${dest}</span>
                    </td>
                    <td class="px-4 py-3 text-slate-300 text-xs capitalize">${s.period_scope}</td>
                    <td class="px-4 py-3 text-slate-300 text-xs">${s.schedule_type} @ ${String(s.schedule_time || '').slice(0,5)}</td>
                    <td class="px-4 py-3 text-slate-400 text-xs">${s.last_run_at ? new Date(s.last_run_at).toLocaleString() : '—'}</td>
                    <td class="px-4 py-3">${enabledBadge}</td>
                    <td class="px-4 py-3 text-right space-x-1 whitespace-nowrap">
                        <button class="backup-edit inline-flex items-center gap-1 px-2 py-1 text-xs rounded bg-slate-700 hover:bg-slate-600 text-slate-200 transition" data-id="${s.id}">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="backup-run inline-flex items-center gap-1 px-2 py-1 text-xs rounded bg-emerald-900/50 hover:bg-emerald-800/60 text-emerald-300 border border-emerald-700/40 transition" data-id="${s.id}">
                            <i class="fas fa-play"></i> Run
                        </button>
                        <button class="backup-delete inline-flex items-center gap-1 px-2 py-1 text-xs rounded bg-red-900/30 hover:bg-red-900/50 text-red-400 border border-red-800/30 transition" data-id="${s.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
        }).join('');

        els.backupSchedulesTable.querySelectorAll('.backup-run').forEach(btn => btn.addEventListener('click', async () => {
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            const result = await api.post('run_log_backup_now', { id: Number(btn.dataset.id) });
            if (result.success) window.notyf.success(result.message || 'Backup completed!');
            else window.notyf.error(result.error || 'Backup failed');
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-play"></i> Run';
            loadBackupSchedules();
        }));

        els.backupSchedulesTable.querySelectorAll('.backup-delete').forEach(btn => btn.addEventListener('click', async () => {
            if (!confirm('Delete this backup schedule?')) return;
            const result = await api.post('delete_log_backup_schedule', { id: Number(btn.dataset.id) });
            if (result.success) window.notyf.success('Schedule deleted');
            else window.notyf.error(result.error || 'Delete failed');
            loadBackupSchedules();
        }));

        els.backupSchedulesTable.querySelectorAll('.backup-edit').forEach(btn => btn.addEventListener('click', async () => {
            const all = await api.get('get_log_backup_schedules');
            const s = all.find(x => String(x.id) === String(btn.dataset.id));
            if (!s) return;
            resetScheduleForm();
            els.backupScheduleId.value = s.id;
            els.backupName.value       = s.name || '';
            els.backupPeriodScope.value  = s.period_scope || 'day';
            els.backupScheduleType.value = s.schedule_type || 'daily';
            els.backupScheduleTime.value = String(s.schedule_time || '00:15:00').slice(0, 5);
            if (els.backupDayOfWeek)  els.backupDayOfWeek.value  = s.day_of_week  || '1';
            if (els.backupDayOfMonth) els.backupDayOfMonth.value = s.day_of_month || '1';
            els.backupEnabled.checked = Number(s.enabled || 0) === 1;
            syncScheduleFields();
            const cfg = (() => { try { return JSON.parse(s.target_config || '{}'); } catch { return {}; } })();
            populateTargetFields(s.target_type || 'nas', cfg);
            showForm('Edit Backup Schedule');
        }));
    };

    // ── Form submission ─────────────────────────────────────────────────────
    els.backupScheduleForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const type = els.backupTargetType.value;
        const cfg  = buildTargetConfig();

        // Validate required fields
        if (type === 'nas' && !cfg.mount_path) { window.notyf.error('NAS destination path is required.'); return; }
        if (type === 'ftp' && (!cfg.host || !cfg.username)) { window.notyf.error('FTP host and username are required.'); return; }
        if (type === 'email' && !cfg.recipient_email) { window.notyf.error('Email recipient is required.'); return; }

        const payload = {
            id:            els.backupScheduleId.value ? Number(els.backupScheduleId.value) : null,
            name:          els.backupName.value.trim(),
            target_type:   type,
            period_scope:  els.backupPeriodScope.value,
            schedule_type: els.backupScheduleType.value,
            schedule_time: `${els.backupScheduleTime.value || '00:15'}:00`,
            day_of_week:   els.backupScheduleType.value === 'weekly'  ? Number(els.backupDayOfWeek.value)  : null,
            day_of_month:  els.backupScheduleType.value === 'monthly' ? Number(els.backupDayOfMonth.value) : null,
            enabled:       els.backupEnabled.checked,
            target_config: cfg,
        };

        const btn = els.backupScheduleForm.querySelector('button[type="submit"]');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving…';
        const result = await api.post('save_log_backup_schedule', payload);
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Schedule';

        if (result.success) {
            window.notyf.success('Schedule saved successfully.');
            resetScheduleForm();
            hideForm();
            loadBackupSchedules();
        } else {
            window.notyf.error(result.error || 'Failed to save schedule.');
        }
    });
    els.backupScheduleReset?.addEventListener('click', () => { resetScheduleForm(); hideForm(); });

    if (window.userRole === 'viewer' && els.backupScheduleForm) {
        els.backupScheduleForm.querySelectorAll('input,select,textarea,button').forEach(el => el.disabled = true);
        if (els.showBackupFormBtn) els.showBackupFormBtn.disabled = true;
    }

    // ── Event listeners ─────────────────────────────────────────────────────
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

    // ── Init ────────────────────────────────────────────────────────────────
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
            showTargetGroup('nas');
            syncScheduleFields();
        } catch (error) {
            console.error(error);
            window.notyf.error('Failed to load status log modules.');
        }
    })();
}
