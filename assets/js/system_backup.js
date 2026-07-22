/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
document.addEventListener("DOMContentLoaded", () => {
    const API_URL = 'api.php';
    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json()),
        post: (action, body = {}) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
    };
    
    const els = {
        form: document.getElementById('systemBackupForm'),
        scheduleId: document.getElementById('scheduleId'),
        backupName: document.getElementById('backupName'),
        targetType: document.getElementById('targetType'),
        nasConfigGroup: document.getElementById('nasConfigGroup'),
        nasMountPath: document.getElementById('nasMountPath'),
        ftpConfigGroup: document.getElementById('ftpConfigGroup'),
        ftpHost: document.getElementById('ftpHost'),
        ftpPort: document.getElementById('ftpPort'),
        ftpUser: document.getElementById('ftpUser'),
        ftpPass: document.getElementById('ftpPass'),
        ftpPath: document.getElementById('ftpPath'),
        scheduleType: document.getElementById('scheduleType'),
        scheduleTime: document.getElementById('scheduleTime'),
        weeklyDayGroup: document.getElementById('weeklyDayGroup'),
        dayOfWeek: document.getElementById('dayOfWeek'),
        monthlyDayGroup: document.getElementById('monthlyDayGroup'),
        dayOfMonth: document.getElementById('dayOfMonth'),
        enabled: document.getElementById('enabled'),
        resetBtn: document.getElementById('resetBtn'),
        schedulesTableBody: document.getElementById('schedulesTableBody'),
        runsTableBody: document.getElementById('runsTableBody'),
        runManualBackupBtn: document.getElementById('runManualBackupBtn'),
        formTitle: document.getElementById('formTitle')
    };

    // Toggle target type configurations
    els.targetType.addEventListener('change', () => {
        if (els.targetType.value === 'ftp') {
            els.ftpConfigGroup.classList.remove('hidden');
            els.nasConfigGroup.classList.add('hidden');
        } else if (els.targetType.value === 'nas') {
            els.ftpConfigGroup.classList.add('hidden');
            els.nasConfigGroup.classList.remove('hidden');
        } else {
            els.ftpConfigGroup.classList.add('hidden');
            els.nasConfigGroup.classList.add('hidden');
        }
    });

    // Toggle schedule trigger values
    els.scheduleType.addEventListener('change', () => {
        const val = els.scheduleType.value;
        if (val === 'weekly') {
            els.weeklyDayGroup.classList.remove('hidden');
            els.monthlyDayGroup.classList.add('hidden');
        } else if (val === 'monthly') {
            els.weeklyDayGroup.classList.add('hidden');
            els.monthlyDayGroup.classList.remove('hidden');
        } else {
            els.weeklyDayGroup.classList.add('hidden');
            els.monthlyDayGroup.classList.add('hidden');
        }
    });

    // Format file sizes
    const formatBytes = (bytes, decimals = 2) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    };

    const loadSchedules = async () => {
        try {
            const schedules = await api.get('get_system_backup_schedules');
            els.schedulesTableBody.innerHTML = schedules.length
                ? schedules.map(s => {
                    const cfg = JSON.parse(s.target_config || '{}');
                    const destination = s.target_type === 'ftp' ? `FTP: ${cfg.host}` : `NAS: ${cfg.mount_path || '/backups'}`;
                    const trigger = s.schedule_type === 'daily' ? `Daily @ ${s.schedule_time}` : 
                                    s.schedule_type === 'weekly' ? `Weekly (Day ${s.day_of_week}) @ ${s.schedule_time}` : 
                                    `Monthly (Day ${s.day_of_month}) @ ${s.schedule_time}`;
                    const enabledBadge = s.enabled ? '<span class="px-2 py-0.5 bg-emerald-500/20 text-emerald-400 text-xs rounded-full">Active</span>' : '<span class="px-2 py-0.5 bg-rose-500/20 text-rose-400 text-xs rounded-full">Disabled</span>';
                    
                    return `
                        <tr class="border-b border-slate-750 hover:bg-slate-750/30 transition-colors">
                            <td class="py-3 px-2 font-semibold text-white">${s.name} ${enabledBadge}</td>
                            <td class="py-3 px-2 text-slate-300 font-mono text-xs">${destination}</td>
                            <td class="py-3 px-2 text-slate-400 text-xs">${trigger}</td>
                            <td class="py-3 px-2 text-slate-400 font-mono text-xs">${s.next_run_at || 'Pending calculation'}</td>
                            <td class="py-3 px-2 text-right space-x-3">
                                <button class="btn-edit text-cyan-400 hover:text-cyan-300 hover:underline text-xs font-bold" data-id="${s.id}"><i class="fas fa-edit"></i> Edit</button>
                                <button class="btn-run text-emerald-400 hover:text-emerald-300 hover:underline text-xs font-bold" data-id="${s.id}"><i class="fas fa-play"></i> Run</button>
                                <button class="btn-delete text-rose-400 hover:text-rose-300 hover:underline text-xs font-bold" data-id="${s.id}"><i class="fas fa-trash-can"></i> Delete</button>
                            </td>
                        </tr>
                    `;
                }).join('')
                : '<tr><td colspan="5" class="py-6 text-center text-slate-500 italic">No backup schedules defined yet.</td></tr>';

            // Add actions listeners
            els.schedulesTableBody.querySelectorAll('.btn-run').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.dataset.id;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner animate-spin"></i> Running...';
                    window.notyf.open({ type: 'info', message: 'System backup triggered...' });
                    
                    const res = await api.post('run_system_backup_now', { id });
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-play"></i> Run';
                    
                    if (res.success) {
                        window.notyf.success('Backup finished and uploaded!');
                    } else {
                        window.notyf.error(res.error || 'Backup upload failed');
                    }
                    loadSchedules();
                    loadHistory();
                });
            });

            els.schedulesTableBody.querySelectorAll('.btn-delete').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (!confirm("Are you sure you want to delete this backup schedule?")) return;
                    const id = btn.dataset.id;
                    const res = await api.post('delete_system_backup_schedule', { id });
                    if (res.success) {
                        window.notyf.success('Schedule deleted');
                        loadSchedules();
                    } else {
                        window.notyf.error(res.error || 'Delete failed');
                    }
                });
            });

            els.schedulesTableBody.querySelectorAll('.btn-edit').forEach(btn => {
                btn.addEventListener('click', () => {
                    const s = schedules.find(x => String(x.id) === String(btn.dataset.id));
                    if (!s) return;
                    
                    els.scheduleId.value = s.id;
                    els.backupName.value = s.name;
                    els.targetType.value = s.target_type;
                    els.targetType.dispatchEvent(new Event('change'));
                    
                    const cfg = JSON.parse(s.target_config || '{}');
                    if (s.target_type === 'ftp') {
                        els.ftpHost.value = cfg.host || '';
                        els.ftpPort.value = cfg.port || 21;
                        els.ftpUser.value = cfg.username || '';
                        els.ftpPass.value = cfg.password || '';
                        els.ftpPath.value = cfg.remote_path || '/';
                    } else {
                        els.nasMountPath.value = cfg.mount_path || '';
                    }

                    els.scheduleType.value = s.schedule_type;
                    els.scheduleType.dispatchEvent(new Event('change'));
                    els.scheduleTime.value = s.schedule_time;
                    els.dayOfWeek.value = s.day_of_week || 1;
                    els.dayOfMonth.value = s.day_of_month || 1;
                    els.enabled.checked = !!s.enabled;

                    els.formTitle.innerText = "Edit Backup Schedule";
                });
            });
        } catch (e) {
            console.error(e);
        }
    };

    const loadHistory = async () => {
        try {
            const runs = await api.get('get_system_backup_runs');
            els.runsTableBody.innerHTML = runs.length
                ? runs.map(r => {
                    const statusClass = r.status === 'success' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30';
                    const fileText = r.file_name ? `<span class="font-mono text-xs block text-white font-semibold">${r.file_name}</span><span class="text-slate-400 text-xs font-mono">${formatBytes(r.file_size_bytes)}</span>` : '<span class="text-rose-400 text-xs">Failed upload</span>';
                    const downloadLink = r.status === 'success' && r.file_name ? `<a href="uploads/backups/${r.file_name}" download class="text-cyan-400 hover:text-cyan-300 hover:underline font-bold text-xs"><i class="fas fa-download"></i> Download</a>` : '';
                    
                    return `
                        <tr class="border-b border-slate-750 hover:bg-slate-750/30 transition-colors">
                            <td class="py-3 px-2 text-slate-350 font-mono text-xs">${r.created_at}</td>
                            <td class="py-3 px-2">${fileText}</td>
                            <td class="py-3 px-2 uppercase font-semibold text-xs">${r.target_type}</td>
                            <td class="py-3 px-2">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold ${statusClass}">${r.status}</span>
                                ${r.error_message ? `<p class="text-rose-400/80 text-[10px] font-semibold mt-1 bg-rose-500/5 p-1.5 border border-rose-500/10 rounded-lg max-w-xs break-all">${r.error_message}</p>` : ''}
                            </td>
                            <td class="py-3 px-2 text-right space-x-3">
                                ${downloadLink}
                                <button class="btn-delete-run text-rose-400 hover:text-rose-300 text-xs font-bold" data-id="${r.id}"><i class="fas fa-trash-can"></i> Delete</button>
                            </td>
                        </tr>
                    `;
                }).join('')
                : '<tr><td colspan="5" class="py-6 text-center text-slate-500 italic">No backup execution logs found.</td></tr>';

            els.runsTableBody.querySelectorAll('.btn-delete-run').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (!confirm("Are you sure you want to delete this backup run log and its local archive file?")) return;
                    const id = btn.dataset.id;
                    const res = await api.post('delete_system_backup_run', { id });
                    if (res.success) {
                        window.notyf.success('Log deleted');
                        loadHistory();
                    } else {
                        window.notyf.error(res.error || 'Delete failed');
                    }
                });
            });
        } catch (e) {
            console.error(e);
        }
    };

    els.resetBtn.addEventListener('click', () => {
        els.scheduleId.value = '';
        els.form.reset();
        els.targetType.dispatchEvent(new Event('change'));
        els.scheduleType.dispatchEvent(new Event('change'));
        els.formTitle.innerText = "Create Backup Schedule";
    });

    els.form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const scheduleId = els.scheduleId.value;
        const config = {};
        
        if (els.targetType.value === 'ftp') {
            config.host = els.ftpHost.value;
            config.port = Number(els.ftpPort.value);
            config.username = els.ftpUser.value;
            config.password = els.ftpPass.value;
            config.remote_path = els.ftpPath.value;
        } else {
            config.mount_path = els.nasMountPath.value;
        }

        const data = {
            id: scheduleId ? Number(scheduleId) : undefined,
            name: els.backupName.value,
            target_type: els.targetType.value,
            target_config: config,
            schedule_type: els.scheduleType.value,
            schedule_time: els.scheduleTime.value,
            day_of_week: els.scheduleType.value === 'weekly' ? Number(els.dayOfWeek.value) : undefined,
            day_of_month: els.scheduleType.value === 'monthly' ? Number(els.dayOfMonth.value) : undefined,
            enabled: els.enabled.checked ? 1 : 0
        };

        const res = await api.post('save_system_backup_schedule', data);
        if (res.success) {
            window.notyf.success('Schedule saved successfully!');
            els.resetBtn.click();
            loadSchedules();
        } else {
            window.notyf.error(res.error || 'Failed to save schedule');
        }
    });

    els.runManualBackupBtn.addEventListener('click', async () => {
        els.runManualBackupBtn.disabled = true;
        els.runManualBackupBtn.innerHTML = '<i class="fas fa-spinner animate-spin"></i> Execution in progress...';
        window.notyf.open({ type: 'info', message: 'Manual backup started...' });

        const res = await api.post('run_system_backup_now', { id: 0 });
        els.runManualBackupBtn.disabled = false;
        els.runManualBackupBtn.innerHTML = '<i class="fas fa-play"></i> Run Full Backup Now';

        if (res.success) {
            window.notyf.success('Backup completed successfully!');
            loadHistory();
        } else {
            window.notyf.error(res.error || 'Backup execution failed');
        }
    });

    loadSchedules();
    loadHistory();
});
