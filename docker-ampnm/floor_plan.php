<?php
require_once 'includes/bootstrap.php';
require_once 'includes/auth_check.php';
include 'header.php';

$user_role = $_SESSION['user_role'] ?? 'viewer';
$is_admin = ($user_role === 'admin');
?>
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <i class="fas fa-building text-2xl text-cyan-400"></i>
            <h1 class="text-2xl font-bold text-white">Floor Plan & Cable Management</h1>
        </div>
        <?php if ($is_admin): ?>
        <button onclick="openPlanDialog()" class="bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-2 rounded-lg text-sm font-medium"><i class="fas fa-plus mr-2"></i>New Floor Plan</button>
        <?php endif; ?>
    </div>

    <!-- Floor Plan Selector -->
    <div id="plan-selector" class="flex gap-2 flex-wrap mb-6"></div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-4 border-b border-slate-700 pb-2">
        <button onclick="switchTab('overview')" class="tab-btn active px-4 py-2 rounded-t-lg text-sm font-medium" data-tab="overview"><i class="fas fa-th-large mr-2"></i>Overview</button>
        <button onclick="switchTab('canvas')" class="tab-btn px-4 py-2 rounded-t-lg text-sm font-medium" data-tab="canvas"><i class="fas fa-drafting-compass mr-2"></i>Canvas</button>
        <button onclick="switchTab('racks')" class="tab-btn px-4 py-2 rounded-t-lg text-sm font-medium" data-tab="racks"><i class="fas fa-server mr-2"></i>Racks & Panels</button>
        <button onclick="switchTab('ports')" class="tab-btn px-4 py-2 rounded-t-lg text-sm font-medium" data-tab="ports"><i class="fas fa-th mr-2"></i>Switch Ports</button>
        <button onclick="switchTab('cables')" class="tab-btn px-4 py-2 rounded-t-lg text-sm font-medium" data-tab="cables"><i class="fas fa-ethernet mr-2"></i>Cable Runs</button>
    </div>

    <div id="tab-overview" class="tab-content"></div>
    <div id="tab-canvas" class="tab-content hidden">
        <!-- Canvas Toolbar -->
        <div class="flex items-center gap-2 mb-3 flex-wrap">
            <div class="flex gap-1 bg-slate-800 rounded-lg p-1 border border-slate-700">
                <button onclick="FPCanvas.setTool('select')" class="canvas-tool-btn bg-cyan-600 text-white px-3 py-1.5 rounded text-sm" data-tool="select" title="Select & Move"><i class="fas fa-mouse-pointer"></i></button>
                <?php if ($is_admin): ?>
                <button onclick="FPCanvas.setTool('add-rack')" class="canvas-tool-btn bg-slate-700 text-slate-300 px-3 py-1.5 rounded text-sm" data-tool="add-rack" title="Place Rack"><i class="fas fa-server"></i></button>
                <button onclick="FPCanvas.setTool('add-device')" class="canvas-tool-btn bg-slate-700 text-slate-300 px-3 py-1.5 rounded text-sm" data-tool="add-device" title="Place Device"><i class="fas fa-desktop"></i></button>
                <button onclick="FPCanvas.setTool('add-label')" class="canvas-tool-btn bg-slate-700 text-slate-300 px-3 py-1.5 rounded text-sm" data-tool="add-label" title="Add Label"><i class="fas fa-font"></i></button>
                <button onclick="FPCanvas.setTool('draw-cable')" class="canvas-tool-btn bg-slate-700 text-slate-300 px-3 py-1.5 rounded text-sm" data-tool="draw-cable" title="Draw Cable"><i class="fas fa-ethernet"></i></button>
                <?php endif; ?>
            </div>
            <div class="flex gap-1">
                <label class="flex items-center gap-1.5 text-xs text-slate-400 bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 cursor-pointer">
                    <input type="checkbox" checked onchange="FPCanvas.snapToGrid=this.checked;FPCanvas.render()" class="accent-cyan-500"> Grid
                </label>
                <button onclick="FPCanvas.fitToView()" class="px-3 py-1.5 bg-slate-700 text-slate-300 rounded-lg text-sm hover:bg-slate-600" title="Fit to View"><i class="fas fa-expand"></i></button>
            </div>
            <?php if ($is_admin): ?>
            <div class="flex gap-1 ml-auto">
                <button onclick="FPCanvas.deleteSelected()" class="px-3 py-1.5 bg-red-900/50 text-red-300 rounded-lg text-sm hover:bg-red-900/70 border border-red-800/50" title="Delete Selected"><i class="fas fa-trash mr-1"></i>Delete</button>
            </div>
            <?php endif; ?>
            <div class="flex gap-1 border-l border-slate-700 pl-2">
                <button onclick="FPCanvas.exportPNG()" class="px-3 py-1.5 bg-slate-700 text-slate-300 rounded-lg text-sm hover:bg-slate-600" title="Export PNG"><i class="fas fa-image mr-1"></i>PNG</button>
                <button onclick="FPCanvas.exportSVG()" class="px-3 py-1.5 bg-slate-700 text-slate-300 rounded-lg text-sm hover:bg-slate-600" title="Export SVG"><i class="fas fa-file-code mr-1"></i>SVG</button>
                <button onclick="FPCanvas.exportPDF()" class="px-3 py-1.5 bg-slate-700 text-slate-300 rounded-lg text-sm hover:bg-slate-600" title="Export PDF"><i class="fas fa-file-pdf mr-1"></i>PDF</button>
            </div>
        </div>
        <!-- Canvas Container -->
        <div id="fp-canvas-container" class="bg-slate-900 rounded-xl border border-slate-700 overflow-hidden" style="height:600px;"></div>
        <!-- Properties panel -->
        <div id="canvas-properties" class="mt-3 bg-slate-800/50 border border-slate-700 rounded-xl p-4 hidden">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-bold text-white" id="canvas-prop-title">Properties</span>
            </div>
            <div id="canvas-prop-content" class="text-sm text-slate-400"></div>
        </div>
    </div>
    <div id="tab-racks" class="tab-content hidden"></div>
    <div id="tab-ports" class="tab-content hidden"></div>
    <div id="tab-cables" class="tab-content hidden"></div>
</div>

<!-- Device Picker Dialog for Canvas -->
<div id="canvas-device-picker-dialog" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-md">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-white">Place Device on Canvas</h3>
            <button onclick="document.getElementById('canvas-device-picker-dialog').classList.add('hidden');FPCanvas.activeTool='select';FPCanvas.updateToolButtons()" class="text-slate-400 hover:text-white text-xl">&times;</button>
        </div>
        <div id="canvas-device-picker"></div>
    </div>
</div>

<!-- Dialogs -->
<div id="plan-dialog" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold text-white mb-4" id="plan-dialog-title">New Floor Plan</h3>
        <input type="hidden" id="plan-edit-id">
        <div class="space-y-3">
            <div><label class="text-sm text-slate-400">Name</label><input id="plan-name" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1"></div>
            <div><label class="text-sm text-slate-400">Background Image URL</label><input id="plan-image" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1" placeholder="https://..."></div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
            <button onclick="closePlanDialog()" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm">Cancel</button>
            <button onclick="savePlan()" class="px-4 py-2 bg-cyan-600 text-white rounded-lg text-sm font-bold">Save</button>
        </div>
    </div>
</div>

<div id="rack-dialog" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold text-white mb-4" id="rack-dialog-title">Add Rack</h3>
        <input type="hidden" id="rack-edit-id">
        <div class="space-y-3">
            <div><label class="text-sm text-slate-400">Name</label><input id="rack-name" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1"></div>
            <div><label class="text-sm text-slate-400">Rack Units</label><input type="number" id="rack-units" value="42" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1"></div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
            <button onclick="closeDialog('rack-dialog')" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm">Cancel</button>
            <button onclick="saveRack()" class="px-4 py-2 bg-cyan-600 text-white rounded-lg text-sm font-bold">Save</button>
        </div>
    </div>
</div>

<div id="panel-dialog" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold text-white mb-4" id="panel-dialog-title">Add Patch Panel</h3>
        <input type="hidden" id="panel-edit-id">
        <div class="space-y-3">
            <div><label class="text-sm text-slate-400">Rack</label><select id="panel-rack" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1"></select></div>
            <div><label class="text-sm text-slate-400">Name</label><input id="panel-name" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1"></div>
            <div class="grid grid-cols-3 gap-2">
                <div><label class="text-sm text-slate-400">Ports</label><input type="number" id="panel-ports" value="24" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1"></div>
                <div><label class="text-sm text-slate-400">Rack U</label><input type="number" id="panel-position" value="1" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1"></div>
                <div><label class="text-sm text-slate-400">Type</label><select id="panel-type" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1"><option value="rj45">RJ45</option><option value="fiber-lc">Fiber LC</option><option value="fiber-sc">Fiber SC</option><option value="coax">Coax</option></select></div>
            </div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
            <button onclick="closeDialog('panel-dialog')" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm">Cancel</button>
            <button onclick="savePanel()" class="px-4 py-2 bg-cyan-600 text-white rounded-lg text-sm font-bold">Save</button>
        </div>
    </div>
</div>

<div id="port-dialog" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold text-white mb-4" id="port-dialog-title">Add Switch Port</h3>
        <input type="hidden" id="port-edit-id">
        <div class="space-y-3">
            <div id="port-device-row"><label class="text-sm text-slate-400">Device</label><select id="port-device" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1"></select></div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="text-sm text-slate-400">Port #</label><input type="number" id="port-number" value="1" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1"></div>
                <div><label class="text-sm text-slate-400">Label</label><input id="port-label" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1" placeholder="Gi0/1"></div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="text-sm text-slate-400">Status</label><select id="port-status" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1"><option value="active">Active</option><option value="inactive" selected>Inactive</option><option value="error">Error</option><option value="reserved">Reserved</option></select></div>
                <div><label class="text-sm text-slate-400">Speed</label><select id="port-speed" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1"><option value="100M">100M</option><option value="1G" selected>1G</option><option value="2.5G">2.5G</option><option value="5G">5G</option><option value="10G">10G</option><option value="25G">25G</option><option value="40G">40G</option><option value="100G">100G</option></select></div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="text-sm text-slate-400">VLAN</label><input id="port-vlan" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1" placeholder="100"></div>
                <div><label class="text-sm text-slate-400">Connected To</label><input id="port-connected" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1" placeholder="PC-101"></div>
            </div>
            <div><label class="text-sm text-slate-400">Notes</label><input id="port-notes" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1"></div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
            <button onclick="closeDialog('port-dialog')" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm">Cancel</button>
            <button onclick="savePort()" class="px-4 py-2 bg-cyan-600 text-white rounded-lg text-sm font-bold">Save</button>
        </div>
    </div>
</div>

<div id="cable-dialog" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-lg">
        <h3 class="text-lg font-bold text-white mb-4" id="cable-dialog-title">Add Cable Run</h3>
        <input type="hidden" id="cable-edit-id">
        <div class="space-y-3">
            <div class="grid grid-cols-3 gap-2">
                <div><label class="text-sm text-slate-400">Type</label><select id="cable-type" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1"><option value="cat5">CAT5</option><option value="cat5e">CAT5e</option><option value="cat6" selected>CAT6</option><option value="cat6a">CAT6a</option><option value="cat7">CAT7</option><option value="fiber-sm">Fiber SM</option><option value="fiber-mm">Fiber MM</option><option value="coax">Coax</option><option value="dac">DAC</option></select></div>
                <div><label class="text-sm text-slate-400">Color</label><select id="cable-color" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1"><option value="blue">Blue</option><option value="red">Red</option><option value="green">Green</option><option value="yellow">Yellow</option><option value="orange">Orange</option><option value="white">White</option><option value="gray">Gray</option><option value="purple">Purple</option><option value="black">Black</option></select></div>
                <div><label class="text-sm text-slate-400">Length</label><input id="cable-length" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1" placeholder="3m"></div>
            </div>
            <div><label class="text-sm text-slate-400">Label</label><input id="cable-label" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1" placeholder="Desk 12 → SW1-P3"></div>
            <div class="border border-slate-600 rounded-lg p-3 space-y-2">
                <span class="text-xs font-medium text-slate-500 uppercase">Source</span>
                <div class="grid grid-cols-3 gap-2">
                    <select id="cable-src-type" class="bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2"><option value="patch_panel">Panel</option><option value="switch">Switch</option></select>
                    <select id="cable-src-id" class="bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2"></select>
                    <input type="number" id="cable-src-port" value="1" class="bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2" placeholder="Port">
                </div>
            </div>
            <div class="border border-slate-600 rounded-lg p-3 space-y-2">
                <span class="text-xs font-medium text-slate-500 uppercase">Destination</span>
                <div class="grid grid-cols-3 gap-2">
                    <select id="cable-dst-type" class="bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2"><option value="switch">Switch</option><option value="patch_panel">Panel</option></select>
                    <select id="cable-dst-id" class="bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2"></select>
                    <input type="number" id="cable-dst-port" value="1" class="bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2" placeholder="Port">
                </div>
            </div>
            <div><label class="text-sm text-slate-400">Notes</label><input id="cable-notes" class="w-full bg-slate-900 border border-slate-600 text-white rounded-lg px-3 py-2 mt-1"></div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
            <button onclick="closeDialog('cable-dialog')" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm">Cancel</button>
            <button onclick="saveCable()" class="px-4 py-2 bg-cyan-600 text-white rounded-lg text-sm font-bold">Save</button>
        </div>
    </div>
</div>

<script src="assets/js/floor-plan-canvas.js"></script>
<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
<script>
const notyf = new Notyf({ duration: 3000, position: { x: 'right', y: 'top' } });
const isAdmin = <?= $is_admin ? 'true' : 'false' ?>;
let floorPlans = [], selectedPlanId = null, racks = [], panels = [], switchPorts = [], cables = [], devices = [], planDevices = [], annotations = [];

const CABLE_COLOR_MAP = { blue:'#3b82f6', red:'#ef4444', green:'#22c55e', yellow:'#eab308', orange:'#f97316', white:'#e2e8f0', gray:'#64748b', purple:'#a855f7', black:'#1e293b' };

async function api(action, data = {}) {
    const res = await fetch('api.php?action=' + action + '&handler=metrics', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
    return res.json();
}

// For floor plan we'll use a simpler direct DB approach via a dedicated handler
async function fpApi(action, data = {}) {
    const res = await fetch('api.php?action=' + action + '&handler=floor_plan', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
    return res.json();
}

async function loadAll() {
    const [fp, dev] = await Promise.all([fpApi('get_floor_plans'), fpApi('get_devices')]);
    floorPlans = fp.data || [];
    devices = dev.data || [];
    renderPlanSelector();
    if (floorPlans.length && !selectedPlanId) selectPlan(floorPlans[0].id);
}

function selectPlan(id) {
    selectedPlanId = id;
    renderPlanSelector();
    loadPlanData();
}

async function loadPlanData() {
    if (!selectedPlanId) return;
    const [r, c, sp, pd, ann] = await Promise.all([
        fpApi('get_racks', { floor_plan_id: selectedPlanId }),
        fpApi('get_cables', { floor_plan_id: selectedPlanId }),
        fpApi('get_switch_ports'),
        fpApi('get_floor_plan_devices', { floor_plan_id: selectedPlanId }),
        fpApi('get_annotations', { floor_plan_id: selectedPlanId })
    ]);
    racks = r.data || [];
    cables = c.data || [];
    switchPorts = sp.data || [];
    planDevices = pd.data || [];
    annotations = ann.data || [];
    // Load panels for racks
    if (racks.length) {
        const p = await fpApi('get_panels', { rack_ids: racks.map(r => r.id) });
        panels = p.data || [];
    } else { panels = []; }
    renderCurrentTab();
}

function renderPlanSelector() {
    const el = document.getElementById('plan-selector');
    el.innerHTML = floorPlans.map(fp => `
        <button onclick="selectPlan('${fp.id}')" class="px-3 py-1.5 rounded-lg text-sm font-medium ${fp.id == selectedPlanId ? 'bg-cyan-600 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'}">
            <i class="fas fa-map-marker-alt mr-1"></i>${fp.name}
            ${isAdmin ? `<i class="fas fa-edit ml-2 opacity-60 hover:opacity-100" onclick="event.stopPropagation();editPlan('${fp.id}')"></i><i class="fas fa-trash ml-1 opacity-60 hover:opacity-100 text-red-400" onclick="event.stopPropagation();deletePlan('${fp.id}')"></i>` : ''}
        </button>
    `).join('');
}

function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(e => e.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(e => e.classList.remove('active', 'bg-slate-700', 'text-white'));
    document.getElementById('tab-' + tab).classList.remove('hidden');
    document.querySelector(`.tab-btn[data-tab="${tab}"]`).classList.add('active', 'bg-slate-700', 'text-white');
    renderTab(tab);
}

function renderCurrentTab() {
    const active = document.querySelector('.tab-btn.active');
    renderTab(active ? active.dataset.tab : 'overview');
}

function renderTab(tab) {
    if (tab === 'overview') renderOverview();
    else if (tab === 'canvas') renderCanvas();
    else if (tab === 'racks') renderRacks();
    else if (tab === 'ports') renderPorts();
    else if (tab === 'cables') renderCables();
}

function renderCanvas() {
    const plan = floorPlans.find(f => f.id == selectedPlanId);
    FPCanvas.racks = racks.map(r => ({ ...r, x: parseFloat(r.x) || 100, y: parseFloat(r.y) || 100 }));
    FPCanvas.planDevices = planDevices.map(d => ({ ...d, x: parseFloat(d.x) || 200, y: parseFloat(d.y) || 200 }));
    FPCanvas.cables = cables;
    FPCanvas.annotations = annotations.map(a => ({ ...a, x: parseFloat(a.x) || 0, y: parseFloat(a.y) || 0 }));
    FPCanvas.init('fp-canvas-container', { plan });

    // Properties panel callback
    window.onCanvasSelect = (item, kind) => {
        const panel = document.getElementById('canvas-properties');
        const title = document.getElementById('canvas-prop-title');
        const content = document.getElementById('canvas-prop-content');
        if (!item || !kind) { panel.classList.add('hidden'); return; }
        panel.classList.remove('hidden');
        title.textContent = kind.charAt(0).toUpperCase() + kind.slice(1) + ' Properties';
        let html = '';
        if (kind === 'rack') html = `<div><strong>Name:</strong> ${item.name}</div><div><strong>Units:</strong> ${item.rack_units || 42}U</div><div><strong>Position:</strong> ${Math.round(item.x)}, ${Math.round(item.y)}</div>`;
        else if (kind === 'device') html = `<div><strong>Name:</strong> ${item.name}</div><div><strong>Type:</strong> ${item.type || 'device'}</div>${item.ip ? `<div><strong>IP:</strong> ${item.ip}</div>` : ''}<div><strong>Status:</strong> ${item.status || 'unknown'}</div>`;
        else if (kind === 'cable') html = `<div><strong>Type:</strong> ${item.cable_type}</div><div><strong>Color:</strong> ${item.cable_color}</div>${item.label ? `<div><strong>Label:</strong> ${item.label}</div>` : ''}`;
        else if (kind === 'annotation') html = `<div><strong>Text:</strong> ${item.text}</div><div><strong>Type:</strong> ${item.type}</div>`;
        content.innerHTML = html;
    };
    setTimeout(() => FPCanvas.fitToView(), 100);
}

function renderOverview() {
    const plan = floorPlans.find(f => f.id === selectedPlanId);
    document.getElementById('tab-overview').innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><div class="text-sm text-slate-400">Racks</div><div class="text-3xl font-bold text-white">${racks.length}</div></div>
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><div class="text-sm text-slate-400">Patch Panels</div><div class="text-3xl font-bold text-white">${panels.length}</div></div>
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4"><div class="text-sm text-slate-400">Cable Runs</div><div class="text-3xl font-bold text-white">${cables.length}</div></div>
        </div>
        ${plan && plan.image_url ? `<div class="bg-slate-800/50 border border-slate-700 rounded-xl p-2"><img src="${plan.image_url}" alt="${plan.name}" class="w-full rounded-lg max-h-[500px] object-contain"></div>` : ''}
    `;
}

function renderRacks() {
    let html = isAdmin ? `<button onclick="openRackDialog()" class="mb-4 px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm"><i class="fas fa-plus mr-1"></i>Add Rack</button>` : '';
    racks.forEach(rack => {
        const rackPanels = panels.filter(p => p.rack_id === rack.id).sort((a,b) => a.rack_position - b.rack_position);
        html += `<div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4 mb-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2"><i class="fas fa-server text-cyan-400"></i><span class="font-bold text-white">${rack.name}</span><span class="text-xs bg-slate-700 px-2 py-0.5 rounded text-slate-300">${rack.rack_units}U</span></div>
                ${isAdmin ? `<div class="flex gap-1">
                    <button onclick="editRack('${rack.id}')" class="px-2 py-1 bg-slate-700 hover:bg-slate-600 rounded text-xs text-slate-300"><i class="fas fa-edit"></i></button>
                    <button onclick="deleteRack('${rack.id}')" class="px-2 py-1 bg-slate-700 hover:bg-slate-600 rounded text-xs text-red-400"><i class="fas fa-trash"></i></button>
                    <button onclick="openPanelDialog('${rack.id}')" class="px-2 py-1 bg-cyan-600/30 hover:bg-cyan-600/50 rounded text-xs text-cyan-300"><i class="fas fa-plus mr-1"></i>Panel</button>
                </div>` : ''}
            </div>`;
        if (!rackPanels.length) { html += '<p class="text-sm text-slate-500">No panels.</p>'; }
        rackPanels.forEach(panel => {
            const portGrid = Array.from({length: Math.min(panel.port_count, 48)}, (_, i) => {
                const cable = cables.find(c => (c.source_type === 'patch_panel' && c.source_id === panel.id && c.source_port == i+1) || (c.dest_type === 'patch_panel' && c.dest_id === panel.id && c.dest_port == i+1));
                const bg = cable ? CABLE_COLOR_MAP[cable.cable_color] || '#64748b' : 'rgba(100,116,139,0.3)';
                const title = `Port ${i+1}${cable ? ' - ' + (cable.label || cable.cable_type) : ''}`;
                return `<div class="w-3 h-3 rounded-sm border border-slate-600" style="background:${bg}" title="${title}"></div>`;
            }).join('');
            html += `<div class="flex items-center justify-between p-3 rounded-lg bg-slate-900/50 border border-slate-700 mb-2">
                <div class="flex items-center gap-3"><i class="fas fa-plug text-slate-500"></i><span class="font-medium text-white">${panel.name}</span>
                <span class="text-xs bg-slate-700 px-2 py-0.5 rounded text-slate-300">${panel.port_count} ports</span>
                <span class="text-xs bg-slate-700 px-2 py-0.5 rounded text-slate-300">U${panel.rack_position}</span>
                <span class="text-xs bg-cyan-900/50 px-2 py-0.5 rounded text-cyan-300">${panel.panel_type.toUpperCase()}</span></div>
                ${isAdmin ? `<div class="flex gap-1"><button onclick="editPanel('${panel.id}')" class="px-2 py-1 bg-slate-700 hover:bg-slate-600 rounded text-xs text-slate-300"><i class="fas fa-edit"></i></button><button onclick="deletePanel('${panel.id}')" class="px-2 py-1 bg-slate-700 hover:bg-slate-600 rounded text-xs text-red-400"><i class="fas fa-trash"></i></button></div>` : ''}
                <div class="flex gap-0.5 flex-wrap max-w-[200px]">${portGrid}</div>
            </div>`;
        });
        html += '</div>';
    });
    if (!racks.length) html += '<div class="bg-slate-800/50 border border-slate-700 rounded-xl p-8 text-center text-slate-500">No racks yet.</div>';
    document.getElementById('tab-racks').innerHTML = html;
}

function renderPorts() {
    let html = isAdmin ? `<button onclick="openPortDialog()" class="mb-4 px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm"><i class="fas fa-plus mr-1"></i>Add Port</button>` : '';
    const deviceIds = [...new Set(switchPorts.map(p => p.device_id))];
    deviceIds.forEach(did => {
        const dev = devices.find(d => d.id == did);
        const ports = switchPorts.filter(p => p.device_id == did).sort((a,b) => a.port_number - b.port_number);
        const statusColor = s => s === 'active' ? 'text-emerald-400' : s === 'error' ? 'text-red-400' : s === 'reserved' ? 'text-amber-400' : 'text-slate-500';
        html += `<div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4 mb-4">
            <div class="flex items-center gap-2 mb-3"><i class="fas fa-th text-cyan-400"></i><span class="font-bold text-white">${dev ? dev.name : 'Unknown Device'}</span><span class="text-xs bg-slate-700 px-2 py-0.5 rounded text-slate-300">${ports.length} ports</span></div>
            <div class="grid grid-cols-6 sm:grid-cols-8 md:grid-cols-12 gap-2">
                ${ports.map(p => `<div class="flex flex-col items-center p-2 rounded-lg border border-slate-700 cursor-pointer hover:border-cyan-500 transition-colors group relative" ${isAdmin ? `onclick="editPort('${p.id}')"` : ''}>
                    <i class="fas fa-circle ${statusColor(p.status)} text-sm"></i>
                    <span class="text-xs font-mono mt-1 text-white">${p.port_label || p.port_number}</span>
                    <span class="text-[10px] text-slate-500">${p.speed}</span>
                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-10 bg-slate-800 border border-slate-600 rounded-lg p-2 shadow-lg min-w-[160px] text-xs pointer-events-none">
                        <div class="font-medium text-white">Port ${p.port_number}</div>
                        ${p.port_label ? `<div class="text-slate-400">Label: ${p.port_label}</div>` : ''}
                        <div class="text-slate-400">Status: <span class="${statusColor(p.status)}">${p.status}</span></div>
                        <div class="text-slate-400">Speed: ${p.speed}</div>
                        ${p.vlan ? `<div class="text-slate-400">VLAN: ${p.vlan}</div>` : ''}
                        ${p.connected_device ? `<div class="text-slate-400">→ ${p.connected_device}</div>` : ''}
                        ${p.notes ? `<div class="text-slate-400 mt-1 italic">${p.notes}</div>` : ''}
                    </div>
                </div>`).join('')}
            </div>
        </div>`;
    });
    if (!switchPorts.length) html += '<div class="bg-slate-800/50 border border-slate-700 rounded-xl p-8 text-center text-slate-500">No switch ports defined.</div>';
    document.getElementById('tab-ports').innerHTML = html;
}

function renderCables() {
    let html = isAdmin ? `<button onclick="openCableDialog()" class="mb-4 px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm"><i class="fas fa-plus mr-1"></i>Add Cable Run</button>` : '';
    cables.forEach(c => {
        html += `<div class="flex items-center gap-3 p-3 rounded-lg bg-slate-800/50 border border-slate-700 mb-2">
            <div class="w-4 h-4 rounded-full border-2 border-slate-600" style="background:${CABLE_COLOR_MAP[c.cable_color]||'#64748b'}"></div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap"><span class="font-medium text-white">${c.label || 'Cable #' + c.id.substring(0,6)}</span>
                <span class="text-xs bg-slate-700 px-2 py-0.5 rounded text-slate-300">${c.cable_type.toUpperCase()}</span>
                ${c.cable_length ? `<span class="text-xs bg-cyan-900/50 px-2 py-0.5 rounded text-cyan-300">${c.cable_length}</span>` : ''}</div>
                <div class="text-xs text-slate-500 mt-1">${c.source_type} port ${c.source_port} → ${c.dest_type} port ${c.dest_port}</div>
            </div>
            ${isAdmin ? `<div class="flex gap-1"><button onclick="editCable('${c.id}')" class="px-2 py-1 bg-slate-700 hover:bg-slate-600 rounded text-xs text-slate-300"><i class="fas fa-edit"></i></button><button onclick="deleteCable('${c.id}')" class="px-2 py-1 bg-slate-700 hover:bg-slate-600 rounded text-xs text-red-400"><i class="fas fa-trash"></i></button></div>` : ''}
        </div>`;
    });
    if (!cables.length) html += '<div class="bg-slate-800/50 border border-slate-700 rounded-xl p-8 text-center text-slate-500">No cable runs defined.</div>';
    document.getElementById('tab-cables').innerHTML = html;
}

// Dialog helpers
function closeAllDialogs() { ['plan-dialog','rack-dialog','panel-dialog','port-dialog','cable-dialog'].forEach(id => document.getElementById(id).classList.add('hidden')); }
function closeDialog(id) { document.getElementById(id).classList.add('hidden'); }
function openPlanDialog() { closeAllDialogs(); document.getElementById('plan-edit-id').value = ''; document.getElementById('plan-name').value = ''; document.getElementById('plan-image').value = ''; document.getElementById('plan-dialog-title').textContent = 'New Floor Plan'; document.getElementById('plan-dialog').classList.remove('hidden'); }
function closePlanDialog() { closeDialog('plan-dialog'); }
function editPlan(id) { closeAllDialogs(); const p = floorPlans.find(f => f.id === id); if (!p) return; document.getElementById('plan-edit-id').value = p.id; document.getElementById('plan-name').value = p.name; document.getElementById('plan-image').value = p.image_url || ''; document.getElementById('plan-dialog-title').textContent = 'Edit Floor Plan'; document.getElementById('plan-dialog').classList.remove('hidden'); }
async function savePlan() { const id = document.getElementById('plan-edit-id').value; const name = document.getElementById('plan-name').value; const img = document.getElementById('plan-image').value; if (!name) return; await fpApi(id ? 'update_floor_plan' : 'create_floor_plan', { id, name, image_url: img || null }); closePlanDialog(); await loadAll(); notyf.success('Floor plan saved.'); }
async function deletePlan(id) { if (!confirm('Delete this floor plan?')) return; await fpApi('delete_floor_plan', { id }); if (selectedPlanId === id) selectedPlanId = null; await loadAll(); notyf.success('Floor plan deleted.'); }

function openRackDialog() { closeAllDialogs(); document.getElementById('rack-edit-id').value = ''; document.getElementById('rack-name').value = ''; document.getElementById('rack-units').value = 42; document.getElementById('rack-dialog-title').textContent = 'Add Rack'; document.getElementById('rack-dialog').classList.remove('hidden'); }
function editRack(id) { closeAllDialogs(); const r = racks.find(x => x.id === id); if (!r) return; document.getElementById('rack-edit-id').value = r.id; document.getElementById('rack-name').value = r.name; document.getElementById('rack-units').value = r.rack_units; document.getElementById('rack-dialog-title').textContent = 'Edit Rack'; document.getElementById('rack-dialog').classList.remove('hidden'); }
async function saveRack() { const id = document.getElementById('rack-edit-id').value; const name = document.getElementById('rack-name').value; const units = +document.getElementById('rack-units').value; if (!name) return; await fpApi(id ? 'update_rack' : 'create_rack', { id, floor_plan_id: selectedPlanId, name, rack_units: units }); closeDialog('rack-dialog'); await loadPlanData(); notyf.success('Rack saved.'); }
async function deleteRack(id) { if (!confirm('Delete this rack?')) return; await fpApi('delete_rack', { id }); await loadPlanData(); notyf.success('Rack deleted.'); }

function openPanelDialog(rackId) { closeAllDialogs(); document.getElementById('panel-edit-id').value = ''; document.getElementById('panel-name').value = ''; document.getElementById('panel-ports').value = 24; document.getElementById('panel-position').value = 1; document.getElementById('panel-type').value = 'rj45'; const sel = document.getElementById('panel-rack'); sel.innerHTML = racks.map(r => `<option value="${r.id}" ${r.id===rackId?'selected':''}>${r.name}</option>`).join(''); document.getElementById('panel-dialog-title').textContent = 'Add Patch Panel'; document.getElementById('panel-dialog').classList.remove('hidden'); }
function editPanel(id) { closeAllDialogs(); const p = panels.find(x => x.id === id); if (!p) return; document.getElementById('panel-edit-id').value = p.id; document.getElementById('panel-name').value = p.name; document.getElementById('panel-ports').value = p.port_count; document.getElementById('panel-position').value = p.rack_position; document.getElementById('panel-type').value = p.panel_type; const sel = document.getElementById('panel-rack'); sel.innerHTML = racks.map(r => `<option value="${r.id}" ${r.id===p.rack_id?'selected':''}>${r.name}</option>`).join(''); document.getElementById('panel-dialog-title').textContent = 'Edit Patch Panel'; document.getElementById('panel-dialog').classList.remove('hidden'); }
async function savePanel() { const id = document.getElementById('panel-edit-id').value; const rackId = document.getElementById('panel-rack').value; const name = document.getElementById('panel-name').value; const ports = +document.getElementById('panel-ports').value; const pos = +document.getElementById('panel-position').value; const type = document.getElementById('panel-type').value; if (!name) return; await fpApi(id ? 'update_panel' : 'create_panel', { id, rack_id: rackId, name, port_count: ports, rack_position: pos, panel_type: type }); closeDialog('panel-dialog'); await loadPlanData(); notyf.success('Panel saved.'); }
async function deletePanel(id) { await fpApi('delete_panel', { id }); await loadPlanData(); notyf.success('Panel deleted.'); }

function openPortDialog() { closeAllDialogs(); document.getElementById('port-edit-id').value = ''; document.getElementById('port-number').value = 1; document.getElementById('port-label').value = ''; document.getElementById('port-status').value = 'inactive'; document.getElementById('port-speed').value = '1G'; document.getElementById('port-vlan').value = ''; document.getElementById('port-connected').value = ''; document.getElementById('port-notes').value = ''; document.getElementById('port-device-row').style.display = ''; const sel = document.getElementById('port-device'); sel.innerHTML = devices.map(d => `<option value="${d.id}">${d.name}</option>`).join(''); document.getElementById('port-dialog-title').textContent = 'Add Switch Port'; document.getElementById('port-dialog').classList.remove('hidden'); }
function editPort(id) { closeAllDialogs(); const p = switchPorts.find(x => x.id === id); if (!p) return; document.getElementById('port-edit-id').value = p.id; document.getElementById('port-number').value = p.port_number; document.getElementById('port-label').value = p.port_label || ''; document.getElementById('port-status').value = p.status; document.getElementById('port-speed').value = p.speed; document.getElementById('port-vlan').value = p.vlan || ''; document.getElementById('port-connected').value = p.connected_device || ''; document.getElementById('port-notes').value = p.notes || ''; document.getElementById('port-device-row').style.display = 'none'; document.getElementById('port-dialog-title').textContent = 'Edit Switch Port'; document.getElementById('port-dialog').classList.remove('hidden'); }
async function savePort() { const id = document.getElementById('port-edit-id').value; const deviceId = document.getElementById('port-device').value; const data = { id, device_id: deviceId, port_number: +document.getElementById('port-number').value, port_label: document.getElementById('port-label').value || null, status: document.getElementById('port-status').value, speed: document.getElementById('port-speed').value, vlan: document.getElementById('port-vlan').value || null, connected_device: document.getElementById('port-connected').value || null, notes: document.getElementById('port-notes').value || null }; await fpApi(id ? 'update_port' : 'create_port', data); closeDialog('port-dialog'); await loadPlanData(); notyf.success('Port saved.'); }
async function deletePort(id) { await fpApi('delete_port', { id }); await loadPlanData(); notyf.success('Port deleted.'); }

function populateEndpoints(typeSelectId, idSelectId) {
    const type = document.getElementById(typeSelectId).value;
    const sel = document.getElementById(idSelectId);
    sel.innerHTML = type === 'patch_panel' ? panels.map(p => `<option value="${p.id}">${p.name}</option>`).join('') : devices.map(d => `<option value="${d.id}">${d.name}</option>`).join('');
}
function openCableDialog() { closeAllDialogs(); document.getElementById('cable-edit-id').value = ''; document.getElementById('cable-type').value = 'cat6'; document.getElementById('cable-color').value = 'blue'; document.getElementById('cable-length').value = ''; document.getElementById('cable-label').value = ''; document.getElementById('cable-src-type').value = 'patch_panel'; document.getElementById('cable-dst-type').value = 'switch'; document.getElementById('cable-src-port').value = 1; document.getElementById('cable-dst-port').value = 1; document.getElementById('cable-notes').value = ''; populateEndpoints('cable-src-type','cable-src-id'); populateEndpoints('cable-dst-type','cable-dst-id'); document.getElementById('cable-dialog-title').textContent = 'Add Cable Run'; document.getElementById('cable-dialog').classList.remove('hidden'); }
function editCable(id) { closeAllDialogs(); const c = cables.find(x => x.id === id); if (!c) return; document.getElementById('cable-edit-id').value = c.id; document.getElementById('cable-type').value = c.cable_type; document.getElementById('cable-color').value = c.cable_color; document.getElementById('cable-length').value = c.cable_length || ''; document.getElementById('cable-label').value = c.label || ''; document.getElementById('cable-src-type').value = c.source_type; document.getElementById('cable-dst-type').value = c.dest_type; document.getElementById('cable-src-port').value = c.source_port; document.getElementById('cable-dst-port').value = c.dest_port; document.getElementById('cable-notes').value = c.notes || ''; populateEndpoints('cable-src-type','cable-src-id'); populateEndpoints('cable-dst-type','cable-dst-id'); document.getElementById('cable-src-id').value = c.source_id; document.getElementById('cable-dst-id').value = c.dest_id; document.getElementById('cable-dialog-title').textContent = 'Edit Cable Run'; document.getElementById('cable-dialog').classList.remove('hidden'); }
async function saveCable() { const id = document.getElementById('cable-edit-id').value; const data = { id, floor_plan_id: selectedPlanId, cable_type: document.getElementById('cable-type').value, cable_color: document.getElementById('cable-color').value, cable_length: document.getElementById('cable-length').value || null, label: document.getElementById('cable-label').value || null, source_type: document.getElementById('cable-src-type').value, source_id: document.getElementById('cable-src-id').value, source_port: +document.getElementById('cable-src-port').value, dest_type: document.getElementById('cable-dst-type').value, dest_id: document.getElementById('cable-dst-id').value, dest_port: +document.getElementById('cable-dst-port').value, notes: document.getElementById('cable-notes').value || null }; await fpApi(id ? 'update_cable' : 'create_cable', data); closeDialog('cable-dialog'); await loadPlanData(); notyf.success('Cable run saved.'); }
async function deleteCable(id) { if (!confirm('Delete this cable run?')) return; await fpApi('delete_cable', { id }); await loadPlanData(); notyf.success('Cable run deleted.'); }

// Event listeners for endpoint type changes
document.getElementById('cable-src-type').addEventListener('change', () => populateEndpoints('cable-src-type','cable-src-id'));
document.getElementById('cable-dst-type').addEventListener('change', () => populateEndpoints('cable-dst-type','cable-dst-id'));

// Init
switchTab('overview');
loadAll();
</script>
<?php include 'footer.php'; ?>
