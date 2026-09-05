<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * Hybrid Cloud Edge Poller Deployment & Remote Site Observability Manager
 */

require_once 'includes/bootstrap.php';
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/tenant_context.php';

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'viewer';
$orgId = TenantContext::getActiveOrgId($pdo, $userId);

// Handle AJAX actions
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];

    if ($action === 'create_poller' && $userRole === 'admin') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Poller name is required.']);
            exit;
        }

        $pollerId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $authToken = 'pol_' . bin2hex(random_bytes(24));

        $stmt = $pdo->prepare("
            INSERT INTO edge_pollers (id, org_id, name, auth_token, status, version)
            VALUES (?, ?, ?, ?, 'offline', 'v1.0.0')
        ");
        $stmt->execute([$pollerId, $orgId, $name, $authToken]);

        echo json_encode([
            'success' => true,
            'message' => "Edge Poller '{$name}' created successfully.",
            'token' => $authToken,
            'poller_id' => $pollerId
        ]);
        exit;
    }

    if ($action === 'delete_poller' && $userRole === 'admin') {
        $pollerId = $_POST['id'] ?? '';
        $stmt = $pdo->prepare("DELETE FROM edge_pollers WHERE id = ? AND org_id = ?");
        $stmt->execute([$pollerId, $orgId]);
        echo json_encode(['success' => true, 'message' => 'Edge poller deleted.']);
        exit;
    }
}

// Fetch pollers for current org
$stmt = $pdo->prepare("SELECT * FROM edge_pollers WHERE org_id = ? ORDER BY created_at DESC");
$stmt->execute([$orgId]);
$pollers = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'header.php';
?>

<div class="container mx-auto px-4 py-6 max-w-7xl">
    <!-- Header Title -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-tower-broadcast text-cyan-400"></i> Hybrid Cloud Edge Pollers &amp; Remote Sites
            </h1>
            <p class="text-slate-400 text-sm mt-1">Deploy lightweight edge pollers inside client private networks (LANs) to stream telemetry securely into AMPNM SaaS.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="openCreatePollerModal()" class="px-4 py-2 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white rounded-lg text-sm font-semibold shadow-lg shadow-cyan-600/30 transition-all flex items-center gap-2">
                <i class="fas fa-plus"></i> Deploy New Edge Poller
            </button>
        </div>
    </div>

    <!-- Edge Poller Architecture Diagram & 1-Click Deployment Card -->
    <div class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 border border-slate-700/80 rounded-2xl p-6 shadow-xl mb-8">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div class="space-y-2 max-w-2xl">
                <span class="px-2.5 py-1 bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 rounded-full text-3xs font-mono font-bold uppercase tracking-wider">
                    Zero Firewall Inbound Friction
                </span>
                <h2 class="text-lg font-bold text-white">How Remote LAN Observability Works</h2>
                <p class="text-xs text-slate-300 leading-relaxed">
                    Edge Pollers run inside your branch office, factory floor, or private cloud VPC. They query local SNMP and ICMP at lightning speed, then stream telemetry out to this central SaaS dashboard via encrypted WebSocket (<code class="text-cyan-300 font-mono">WSS/mTLS</code>). No VPN or firewall pinholes required.
                </p>
            </div>
            <div class="bg-slate-950/90 border border-slate-700/80 rounded-xl p-4 font-mono text-2xs text-cyan-300 max-w-md w-full shadow-inner">
                <div class="flex items-center justify-between text-slate-400 mb-2 border-b border-slate-800 pb-1">
                    <span>1-Click Docker Run</span>
                    <button onclick="copyDockerCommand()" class="hover:text-white"><i class="fas fa-copy"></i> Copy</button>
                </div>
                <div id="sampleDockerCmd" class="break-all select-all text-slate-300">
                    docker run -d --name ampnm_edge \<br>
                    &nbsp;&nbsp;--restart always \<br>
                    &nbsp;&nbsp;-e SAAS_URL="http://<?= $_SERVER['HTTP_HOST'] ?? '192.168.9.9:2266' ?>" \<br>
                    &nbsp;&nbsp;-e EDGE_AUTH_TOKEN="<span class="text-cyan-400">YOUR_TOKEN</span>" \<br>
                    &nbsp;&nbsp;itsupportbd/ampnm-edge-poller:latest
                </div>
            </div>
        </div>
    </div>

    <!-- Registered Edge Pollers Table -->
    <div class="bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 overflow-hidden shadow-xl">
        <div class="px-6 py-4 border-b border-slate-700/60 flex items-center justify-between">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fas fa-server text-cyan-400"></i> Active Edge Pollers (<?= count($pollers) ?> Deployed)
            </h3>
        </div>

        <?php if (empty($pollers)): ?>
            <div class="p-12 text-center text-slate-400">
                <div class="w-14 h-14 rounded-2xl bg-slate-900 border border-slate-700 flex items-center justify-center text-slate-500 mx-auto mb-3 text-2xl">
                    <i class="fas fa-tower-broadcast"></i>
                </div>
                <h4 class="text-sm font-bold text-white mb-1">No Remote Edge Pollers Connected</h4>
                <p class="text-xs text-slate-500 max-w-sm mx-auto mb-4">Deploy an edge poller container at your remote site to start monitoring internal subnets.</p>
                <button onclick="openCreatePollerModal()" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-xs font-semibold">
                    + Deploy Edge Poller
                </button>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-900/60 text-slate-400 uppercase text-3xs font-semibold tracking-wider border-b border-slate-700/60">
                        <tr>
                            <th class="px-6 py-3">Poller Name</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">IP Address</th>
                            <th class="px-6 py-3">Agent Version</th>
                            <th class="px-6 py-3">Last Heartbeat</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50 font-mono text-2xs">
                        <?php foreach ($pollers as $p): ?>
                            <tr class="hover:bg-slate-700/30 transition-colors">
                                <td class="px-6 py-3.5 font-bold text-white flex items-center gap-2">
                                    <i class="fas fa-microchip text-cyan-400 text-xs"></i>
                                    <?= htmlspecialchars($p['name']) ?>
                                </td>
                                <td class="px-6 py-3.5">
                                    <span class="px-2 py-0.5 rounded-full text-3xs font-semibold <?= $p['status'] === 'online' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-700 text-slate-400 border border-slate-600' ?>">
                                        <?= strtoupper($p['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-3.5 text-cyan-300"><?= htmlspecialchars($p['ip_address'] ?: 'Pending First Contact') ?></td>
                                <td class="px-6 py-3.5 text-slate-300"><?= htmlspecialchars($p['version']) ?></td>
                                <td class="px-6 py-3.5 text-slate-400"><?= $p['last_heartbeat_at'] ?: 'Never' ?></td>
                                <td class="px-6 py-3.5 text-right font-sans">
                                    <button onclick="deletePoller('<?= $p['id'] ?>')" class="px-2.5 py-1 bg-slate-800 hover:bg-rose-900/60 text-rose-300 rounded text-3xs border border-slate-700">
                                        Revoke
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Create Poller -->
<div id="pollerModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-700 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fas fa-tower-broadcast text-cyan-400"></i> Deploy New Edge Poller
            </h3>
            <button onclick="closeCreatePollerModal()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form id="createPollerForm" onsubmit="handleCreatePoller(event)" class="space-y-4 text-xs">
            <div>
                <label class="block text-slate-300 font-semibold mb-1">Site / Branch Location Name</label>
                <input type="text" name="name" required placeholder="e.g. Dhaka Data Center, Chittagong Plant" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
            </div>
            <div class="pt-2 flex justify-end gap-2">
                <button type="button" onclick="closeCreatePollerModal()" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-lg">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg shadow-lg shadow-cyan-600/30 flex items-center gap-2">
                    <i class="fas fa-check"></i> Generate Poller Token
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreatePollerModal() {
    document.getElementById('pollerModal').classList.remove('hidden');
}

function closeCreatePollerModal() {
    document.getElementById('pollerModal').classList.add('hidden');
}

async function handleCreatePoller(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    try {
        const res = await fetch('edge_poller_manager.php?action=create_poller', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            if (window.notyf) window.notyf.success({ message: data.message });
            closeCreatePollerModal();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            if (window.notyf) window.notyf.error({ message: data.error || 'Failed to create poller.' });
        }
    } catch (err) {
        if (window.notyf) window.notyf.error({ message: 'Request failed.' });
    }
}

async function deletePoller(id) {
    if (!confirm('Are you sure you want to revoke this edge poller?')) return;
    const formData = new FormData();
    formData.append('id', id);

    const res = await fetch('edge_poller_manager.php?action=delete_poller', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        if (window.notyf) window.notyf.success({ message: data.message });
        setTimeout(() => window.location.reload(), 1200);
    }
}

function copyDockerCommand() {
    const text = document.getElementById('sampleDockerCmd').innerText;
    navigator.clipboard.writeText(text).then(() => {
        if (window.notyf) window.notyf.success({ message: 'Docker command copied to clipboard!' });
    });
}
</script>

<?php require_once 'footer.php'; ?>
