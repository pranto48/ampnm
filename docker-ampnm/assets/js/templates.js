function initTemplates() {
    const API_URL = 'api.php';
    const tbody = document.getElementById('templatesTableBody');
    const newBtn = document.getElementById('newTemplateBtn');
    const exportBtn = document.getElementById('exportTemplatesBtn');
    const importBtn = document.getElementById('importTemplatesBtn');
    const importFile = document.getElementById('importTemplatesFile');

    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json()),
        post: (action, body = {}) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
    };

    const load = async () => {
        const res = await api.get('get_templates');
        const templates = res.templates || [];
        tbody.innerHTML = templates.map(t => `
            <tr class="border-b border-slate-700" data-id="${t.id}">
                <td class="px-3 py-2 text-white">${t.name}</td>
                <td class="px-3 py-2 text-slate-300">${t.description || ''}</td>
                <td class="px-3 py-2 text-slate-300 text-xs">W:${t.triggers.warning_latency_threshold ?? '-'}ms / C:${t.triggers.critical_latency_threshold ?? '-'}ms<br>WL:${t.triggers.warning_packetloss_threshold ?? '-'}% / CL:${t.triggers.critical_packetloss_threshold ?? '-'}%</td>
                <td class="px-3 py-2">
                    <button class="edit-template text-cyan-400 mr-3">Edit</button>
                    <button class="delete-template text-red-400">Delete</button>
                </td>
            </tr>
        `).join('');
    };

    const promptTemplate = (existing = null) => {
        const name = prompt('Template name', existing?.name || '');
        if (!name) return null;
        const description = prompt('Description', existing?.description || '') || '';
        const warningLatency = prompt('Warning latency threshold (ms)', existing?.triggers?.warning_latency_threshold ?? '');
        const criticalLatency = prompt('Critical latency threshold (ms)', existing?.triggers?.critical_latency_threshold ?? '');
        const warningLoss = prompt('Warning packet loss threshold (%)', existing?.triggers?.warning_packetloss_threshold ?? '');
        const criticalLoss = prompt('Critical packet loss threshold (%)', existing?.triggers?.critical_packetloss_threshold ?? '');
        return {
            name,
            description,
            enabled: true,
            items: [],
            triggers: {
                warning_latency_threshold: warningLatency === '' ? null : Number(warningLatency),
                critical_latency_threshold: criticalLatency === '' ? null : Number(criticalLatency),
                warning_packetloss_threshold: warningLoss === '' ? null : Number(warningLoss),
                critical_packetloss_threshold: criticalLoss === '' ? null : Number(criticalLoss),
            }
        };
    };

    newBtn?.addEventListener('click', async () => {
        const payload = promptTemplate();
        if (!payload) return;
        const res = await api.post('create_template', payload);
        if (res.success) { window.notyf.success('Template created'); load(); }
        else window.notyf.error(res.error || 'Failed to create template');
    });

    tbody.addEventListener('click', async (e) => {
        const row = e.target.closest('tr');
        if (!row) return;
        const id = Number(row.dataset.id);
        const templates = (await api.get('get_templates')).templates || [];
        const existing = templates.find(t => Number(t.id) === id);

        if (e.target.classList.contains('edit-template')) {
            const payload = promptTemplate(existing);
            if (!payload) return;
            const res = await api.post('update_template', { id, ...payload });
            if (res.success) { window.notyf.success('Template updated'); load(); }
            else window.notyf.error(res.error || 'Failed to update template');
        }
        if (e.target.classList.contains('delete-template')) {
            if (!confirm('Delete this template?')) return;
            const res = await api.post('delete_template', { id });
            if (res.success) { window.notyf.success('Template deleted'); load(); }
            else window.notyf.error(res.error || 'Failed to delete template');
        }
    });

    exportBtn?.addEventListener('click', async () => {
        const format = confirm('Export as YAML? Cancel for JSON.') ? 'yaml' : 'json';
        const res = await api.get('export_templates', { format });
        const content = res.content || '';
        const blob = new Blob([content], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `templates_export.${format === 'yaml' ? 'yaml' : 'json'}`;
        a.click();
        URL.revokeObjectURL(url);
    });

    importBtn?.addEventListener('click', () => importFile?.click());
    importFile?.addEventListener('change', () => {
        const file = importFile.files?.[0];
        if (!file) return;
        const ext = file.name.toLowerCase().endsWith('.yaml') || file.name.toLowerCase().endsWith('.yml') ? 'yaml' : 'json';
        const reader = new FileReader();
        reader.onload = async () => {
            const res = await api.post('import_templates', { format: ext, content: String(reader.result || '') });
            if (res.success) { window.notyf.success(`Imported ${res.templates_processed} template(s)`); load(); }
            else window.notyf.error(res.error || 'Import failed');
        };
        reader.readAsText(file);
    });

    load();
}
