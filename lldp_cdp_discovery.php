<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * Autonomous LLDP / CDP Topology Discovery & Cable Auto-Wiring Dashboard
 */

require_once 'includes/bootstrap.php';
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/lldp_cdp_engine.php';

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'viewer';

// Handle AJAX actions
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];

    if ($action === 'scan_all' && $userRole === 'admin') {
        $switches = $pdo->query("SELECT id, name, ip, type FROM devices WHERE ip IS NOT NULL AND ip != '' AND (type = 'switch' OR type = 'router' OR type = 'server' OR type IS NULL OR type = '') LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

        $totalDiscovered = 0;
        $totalWired = 0;
        $results = [];

        foreach ($switches as $sw) {
            $res = LldpCdpEngine::scanAndAutoWireDevice($pdo, (int)$sw['id'], $userId);
            if ($res['success']) {
                $totalDiscovered += $res['discovered_neighbors'];
                $totalWired += $res['new_edges_wired'];
                $results[] = $res;
            }
        }

        echo json_encode([
            'success' => true,
            'scanned_count' => count($switches),
            'total_neighbors' => $totalDiscovered,
            'new_edges_wired' => $totalWired,
            'details' => $results
        ]);
        exit;
    }

    if ($action === 'scan_single' && $userRole === 'admin') {
        $devId = (int)($_POST['device_id'] ?? 0);
        $res = LldpCdpEngine::scanAndAutoWireDevice($pdo, $devId, $userId);
        echo json_encode($res);
        exit;
    }
}

// Fetch switches and routers
$devices = $pdo->query("SELECT d.*, (SELECT COUNT(*) FROM device_edges WHERE from_device_id = d.id OR to_device_id = d.id) as connected_links FROM devices d ORDER BY connected_links DESC, d.name ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once 'header.php';
?>

<div class="container mx-auto px-4 py-6 max-w-7xl">
    <!-- Header Title -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-network-wired text-cyan-400"></i> Autonomous LLDP / CDP Topology Auto-Wiring
            </h1>
            <p class="text-slate-400 text-sm mt-1">Autonomous switch port and neighbor discovery to automatically map and wire physical cable connections.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="runAutoWireAll()" id="btnAutoWire" class="px-4 py-2 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white rounded-lg text-sm font-semibold shadow-lg shadow-cyan-600/30 transition-all flex items-center gap-2">
                <i class="fas fa-bolt"></i> Auto-Wire Entire Network
            </button>
            <a href="map.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 rounded-lg text-sm font-semibold transition-all flex items-center gap-2">
                <i class="fas fa-project-diagram text-cyan-400"></i> View Topology Map
            </a>
        </div>
    </div>

    <!-- Summary KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400 text-xl">
                <i class="fas fa-server"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Managed Devices</span>
                <h3 class="text-xl font-bold text-white"><?= count($devices) ?></h3>
            </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-xl">
                <i class="fas fa-link"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Active Cable Edges</span>
                <h3 class="text-xl font-bold text-white">
                    <?php 
                    $edgeCount = $pdo->query("SELECT COUNT(*) FROM device_edges")->fetchColumn();
                    echo $edgeCount;
                    ?>
                </h3>
            </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 text-xl">
                <i class="fas fa-satellite-dish"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Discovery Protocols</span>
                <h3 class="text-xl font-bold text-white">LLDP &amp; CDP</h3>
            </div>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-4 flex items-center gap-4 shadow-md">
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 text-xl">
                <i class="fas fa-wand-magic-sparkles"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 uppercase font-semibold tracking-wider">Auto-Wiring Status</span>
                <h3 class="text-xl font-bold text-emerald-400">Autonomous</h3>
            </div>
        </div>
    </div>

    <!-- Device Auto-Wiring Table -->
    <div class="bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 overflow-hidden shadow-xl">
        <div class="px-6 py-4 border-b border-slate-700/60 flex items-center justify-between">
            <h2 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fas fa-layer-group text-cyan-400"></i> Managed Devices &amp; Interface Connections
            </h2>
            <span class="text-xs text-slate-400 font-mono"><?= count($devices) ?> Nodes Available</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-900/60 text-slate-400 uppercase text-3xs font-semibold tracking-wider border-b border-slate-700/60">
                    <tr>
                        <th class="px-6 py-3">Device Name</th>
                        <th class="px-6 py-3">IP Address</th>
                        <th class="px-6 py-3">Device Type</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Wired Links</th>
                        <th class="px-6 py-3 text-right">Autonomous Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/50 font-mono text-2xs">
                    <?php foreach ($devices as $dev): ?>
                        <tr class="hover:bg-slate-700/30 transition-colors">
                            <td class="px-6 py-3.5 font-bold text-white flex items-center gap-2">
                                <i class="fas fa-network-wired text-cyan-400 text-xs"></i>
                                <?= htmlspecialchars($dev['name']) ?>
                            </td>
                            <td class="px-6 py-3.5 text-cyan-300"><?= htmlspecialchars($dev['ip'] ?: 'None') ?></td>
                            <td class="px-6 py-3.5 capitalize text-slate-300"><?= htmlspecialchars($dev['type'] ?: 'device') ?></td>
                            <td class="px-6 py-3.5">
                                <span class="px-2 py-0.5 rounded-full text-3xs font-semibold <?= $dev['status'] === 'online' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' ?>">
                                    <?= strtoupper($dev['status'] ?: 'unknown') ?>
                                </span>
                            </td>
                            <td class="px-6 py-3.5">
                                <span class="px-2.5 py-1 bg-slate-900/80 rounded border border-slate-700 text-cyan-400 font-bold">
                                    <?= $dev['connected_links'] ?> Links
                                </span>
                            </td>
                            <td class="px-6 py-3.5 text-right">
                                <button onclick="scanSingleDevice(<?= $dev['id'] ?>, '<?= htmlspecialchars(addslashes($dev['name'])) ?>')" class="px-3 py-1.5 bg-cyan-600 hover:bg-cyan-500 text-white rounded font-sans font-semibold text-3xs shadow transition-all">
                                    <i class="fas fa-radar mr-1"></i> Probe Neighbors
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
async function runAutoWireAll() {
    const btn = document.getElementById('btnAutoWire');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Probing LLDP/CDP MIBs...';

    try {
        const res = await fetch('lldp_cdp_discovery.php?action=scan_all');
        const data = await res.json();

        if (data.success) {
            if (window.notyf) {
                window.notyf.success({
                    message: `Autonomous Auto-Wiring Complete! Scanned ${data.scanned_count} nodes. Discovered ${data.total_neighbors} neighbors and wired ${data.new_edges_wired} new cable connections!`
                });
            }
            setTimeout(() => window.location.reload(), 2000);
        }
    } catch (e) {
        if (window.notyf) window.notyf.error({ message: 'Auto-wiring scan failed.' });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-bolt"></i> Auto-Wire Entire Network';
    }
}

async function scanSingleDevice(devId, devName) {
    const formData = new FormData();
    formData.append('device_id', devId);

    if (window.notyf) window.notyf.open({ type: 'info', message: `Probing LLDP/CDP for ${devName}...` });

    try {
        const res = await fetch('lldp_cdp_discovery.php?action=scan_single', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            if (window.notyf) {
                window.notyf.success({
                    message: `Found ${data.discovered_neighbors} neighbors for ${devName}. Wired ${data.new_edges_wired} new connections!`
                });
            }
            setTimeout(() => window.location.reload(), 1800);
        } else {
            if (window.notyf) window.notyf.error({ message: data.error || 'No neighbors discovered.' });
        }
    } catch (e) {
        if (window.notyf) window.notyf.error({ message: 'Probe failed.' });
    }
}
</script>

<?php require_once 'footer.php'; ?>
