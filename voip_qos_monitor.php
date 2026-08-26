<?php
/**
 * AMPNM IP SLA & VoIP MOS / Jitter QoS Monitor Dashboard
 * Copyright (c) IT Support BD. All rights reserved.
 */

require_once 'includes/auth_check.php';
require_once 'includes/voip_probe_engine.php';
include 'header.php';

$pdo = getDbConnection();
$probes = $pdo->query("SELECT * FROM voip_sla_probes ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Fetch initial history for first probe
$firstProbeId = $probes[0]['id'] ?? '';
$engine = new VoipProbeEngine($pdo);
$initialHistory = $firstProbeId ? $engine->getProbeHistory($firstProbeId, 20) : [];
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid px-4 py-4 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-headset text-indigo-400"></i>
                <span>IP SLA & VoIP MOS / Jitter QoS Monitor</span>
            </h1>
            <p class="text-slate-400 text-sm mt-1">
                Real-time voice/video quality assessment, ITU-T G.107 MOS scoring (1.0 - 4.5), and packet jitter analysis.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="openNewProbeModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-lg text-sm font-medium transition flex items-center gap-2">
                <i class="fas fa-plus text-cyan-400"></i> Add Voice Probe
            </button>
            <button onclick="runAllVoipProbes()" id="btnRunProbes" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold shadow-lg shadow-indigo-900/30 transition flex items-center gap-2">
                <i class="fas fa-play"></i> Run All Probes
            </button>
        </div>
    </div>

    <!-- Live MOS Reference Banner -->
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950/40 to-slate-900 border border-indigo-500/20 rounded-xl p-4 mb-6 flex flex-wrap items-center justify-between gap-4 text-xs">
        <div class="flex items-center gap-2">
            <span class="font-bold text-slate-300 uppercase tracking-wider">MOS Score Scale:</span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="px-2.5 py-1 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 font-semibold">4.3 - 4.5: Excellent (HD Voice)</span>
            <span class="px-2.5 py-1 rounded bg-teal-500/10 text-teal-400 border border-teal-500/30 font-semibold">4.0 - 4.29: Good (Toll Quality)</span>
            <span class="px-2.5 py-1 rounded bg-amber-500/10 text-amber-400 border border-amber-500/30 font-semibold">3.6 - 3.99: Fair (Noticeable Impairment)</span>
            <span class="px-2.5 py-1 rounded bg-red-500/10 text-red-400 border border-red-500/30 font-semibold">&lt; 3.6: Poor / Unacceptable</span>
        </div>
    </div>

    <!-- Active Probes Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <?php foreach ($probes as $p): 
            $mos = (float)($p['last_mos_score'] ?? 4.40);
            $jitter = (float)($p['last_jitter_ms'] ?? 2.5);
            $statusClass = $mos >= 4.0 ? 'border-emerald-500/30 bg-emerald-500/5' : ($mos >= 3.6 ? 'border-amber-500/30 bg-amber-500/5' : 'border-red-500/30 bg-red-500/5');
            $badgeClass = $mos >= 4.0 ? 'bg-emerald-500/10 text-emerald-400' : ($mos >= 3.6 ? 'bg-amber-500/10 text-amber-400' : 'bg-red-500/10 text-red-400');
        ?>
        <div class="bg-slate-850 border <?= $statusClass ?> rounded-xl p-5 shadow-lg flex flex-col justify-between cursor-pointer hover:border-cyan-500 transition" onclick="selectProbeChart('<?= $p['id'] ?>', '<?= htmlspecialchars($p['name']) ?>')">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-white text-base truncate"><?= htmlspecialchars($p['name']) ?></h3>
                    <span class="px-2 py-0.5 rounded text-2xs uppercase font-bold tracking-wider <?= $badgeClass ?>">
                        <?= htmlspecialchars($p['status'] ?? 'excellent') ?>
                    </span>
                </div>
                <p class="text-xs text-slate-400 font-mono mb-4 flex items-center gap-1.5">
                    <i class="fas fa-network-wired text-cyan-400"></i> <?= htmlspecialchars($p['target_host']) ?>
                    <span class="text-slate-600">|</span>
                    <span class="text-slate-300"><?= htmlspecialchars($p['codec_simulated']) ?></span>
                </p>
                
                <div class="grid grid-cols-2 gap-3 mb-2">
                    <div class="bg-slate-900/80 p-3 rounded-lg border border-slate-800">
                        <span class="text-2xs text-slate-400 uppercase tracking-wider block">MOS Score</span>
                        <span class="text-xl font-bold <?= $mos >= 4.0 ? 'text-emerald-400' : ($mos >= 3.6 ? 'text-amber-400' : 'text-red-400') ?>">
                            <?= number_format($mos, 2) ?> <span class="text-xs text-slate-500 font-normal">/ 4.5</span>
                        </span>
                    </div>
                    <div class="bg-slate-900/80 p-3 rounded-lg border border-slate-800">
                        <span class="text-2xs text-slate-400 uppercase tracking-wider block">Packet Jitter</span>
                        <span class="text-xl font-bold <?= $jitter <= 20 ? 'text-cyan-400' : 'text-amber-400' ?>">
                            <?= number_format($jitter, 1) ?> <span class="text-xs text-slate-500 font-normal">ms</span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400 mt-2">
                <span>Threshold: &ge; <?= $p['min_mos_threshold'] ?> MOS</span>
                <button onclick="event.stopPropagation(); testSingleProbe('<?= $p['id'] ?>')" class="text-indigo-400 hover:text-indigo-300 font-medium flex items-center gap-1">
                    <i class="fas fa-sync-alt"></i> Probe Now
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Live VoIP Timeline Chart -->
    <div class="bg-slate-850 border border-slate-800 rounded-xl p-5 shadow-xl mb-8">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-semibold text-white text-base flex items-center gap-2">
                    <i class="fas fa-chart-line text-indigo-400"></i>
                    <span id="chartProbeTitle"><?= htmlspecialchars($probes[0]['name'] ?? 'VoIP Probe Quality Timeline') ?></span>
                </h3>
                <p class="text-xs text-slate-400">Historical MOS Score and Jitter trends</p>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <span class="flex items-center gap-1 text-emerald-400"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400 inline-block"></span> MOS Score</span>
                <span class="flex items-center gap-1 text-cyan-400"><span class="w-2.5 h-2.5 rounded-full bg-cyan-400 inline-block"></span> Jitter (ms)</span>
            </div>
        </div>
        <div class="h-64">
            <canvas id="voipQualityChart"></canvas>
        </div>
    </div>
</div>

<!-- Modal: New Probe -->
<div id="newProbeModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md shadow-2xl p-6">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4 mb-4">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-headset text-indigo-400"></i> Add VoIP / IP SLA Probe
            </h3>
            <button onclick="closeNewProbeModal()" class="text-slate-400 hover:text-white text-lg">&times;</button>
        </div>
        <form onsubmit="saveNewVoipProbe(event)" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Probe Name</label>
                <input type="text" id="newProbeName" required placeholder="e.g. Branch PBX Voice Trunk" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Target Host / IP Address</label>
                <input type="text" id="newProbeHost" required placeholder="192.168.10.1 or sip.provider.com" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 outline-none">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Simulated Codec</label>
                    <select id="newProbeCodec" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 outline-none">
                        <option value="G.711_uLaw">G.711 uLaw (Standard)</option>
                        <option value="G.729">G.729 (Low Bandwidth)</option>
                        <option value="Opus_HD">Opus HD (Broadband)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Min Target MOS</label>
                    <input type="number" step="0.1" min="1.0" max="4.5" id="newProbeMinMos" value="3.8" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 outline-none">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeNewProbeModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-sm">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold">Save Probe</button>
            </div>
        </form>
    </div>
</div>

<script>
let voipChart = null;
let currentProbeId = '<?= $firstProbeId ?>';

function initChart(initialData) {
    const ctx = document.getElementById('voipQualityChart').getContext('2d');
    const labels = initialData.map(d => new Date(d.recorded_at).toLocaleTimeString());
    const mosData = initialData.map(d => d.mos_score);
    const jitterData = initialData.map(d => d.jitter_ms);

    voipChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels.length ? labels : ['00:00', '00:01', '00:02'],
            datasets: [
                {
                    label: 'MOS Score',
                    data: mosData.length ? mosData : [4.4, 4.35, 4.4],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    yAxisID: 'yMos',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Jitter (ms)',
                    data: jitterData.length ? jitterData : [2.5, 3.1, 2.8],
                    borderColor: '#06b6d4',
                    borderDash: [4, 4],
                    yAxisID: 'yJitter',
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } },
                yMos: {
                    type: 'linear',
                    position: 'left',
                    min: 1.0,
                    max: 4.6,
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: '#10b981' }
                },
                yJitter: {
                    type: 'linear',
                    position: 'right',
                    min: 0,
                    grid: { display: false },
                    ticks: { color: '#06b6d4' }
                }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const rawData = <?= json_encode($initialHistory) ?>;
    initChart(rawData);
});

async function selectProbeChart(probeId, name) {
    currentProbeId = probeId;
    document.getElementById('chartProbeTitle').textContent = name + ' - Quality Timeline';
    try {
        const res = await fetch(`api.php?action=get_voip_probe_history&probe_id=${probeId}`);
        const json = await res.json();
        if (json.success && json.history) {
            voipChart.data.labels = json.history.map(d => new Date(d.recorded_at).toLocaleTimeString());
            voipChart.data.datasets[0].data = json.history.map(d => d.mos_score);
            voipChart.data.datasets[1].data = json.history.map(d => d.jitter_ms);
            voipChart.update();
        }
    } catch (e) {}
}

async function testSingleProbe(probeId) {
    try {
        const res = await fetch(`api.php?action=run_voip_probe&probe_id=${probeId}`, { method: 'POST' });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            alert('Probe failed: ' + (json.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Network error');
    }
}

async function runAllVoipProbes() {
    const btn = document.getElementById('btnRunProbes');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Probing...';

    try {
        const res = await fetch('api.php?action=run_all_voip_probes', { method: 'POST' });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            alert('Probing failed: ' + (json.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Network error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play"></i> Run All Probes';
    }
}

function openNewProbeModal() {
    const m = document.getElementById('newProbeModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function closeNewProbeModal() {
    const m = document.getElementById('newProbeModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

async function saveNewVoipProbe(e) {
    e.preventDefault();
    const payload = {
        name: document.getElementById('newProbeName').value,
        target_host: document.getElementById('newProbeHost').value,
        codec_simulated: document.getElementById('newProbeCodec').value,
        min_mos_threshold: document.getElementById('newProbeMinMos').value
    };

    try {
        const res = await fetch('api.php?action=create_voip_probe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json.success) {
            location.reload();
        } else {
            alert('Failed: ' + (json.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Network error');
    }
}
</script>

<?php include 'footer.php'; ?>
