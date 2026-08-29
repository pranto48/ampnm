<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * Zero-Trust Security Audit, Vulnerability Scanner & Crypto Vault Dashboard
 */

require_once 'includes/bootstrap.php';
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/crypto_vault.php';
require_once 'includes/security_guard.php';

$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'viewer';

// Handle AJAX actions
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];

    if ($action === 'get_security_summary') {
        // Fetch posture score & vulnerability count
        $activeVulns = $pdo->query("SELECT severity, count(*) as count FROM security_vulnerabilities WHERE status = 'active' GROUP BY severity")->fetchAll(PDO::FETCH_KEY_PAIR);
        $jailedIps = $pdo->query("SELECT * FROM security_ip_jail ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        $vaultKeys = $pdo->query("SELECT id, vault_name, credential_type, fingerprint, description, last_rotated_at, created_at FROM encrypted_vault_keys ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        $auditLogs = $pdo->query("SELECT * FROM security_audit_logs ORDER BY created_at DESC LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);

        $criticalCount = (int)($activeVulns['critical'] ?? 0);
        $highCount = (int)($activeVulns['high'] ?? 0);
        $mediumCount = (int)($activeVulns['medium'] ?? 0);
        $lowCount = (int)($activeVulns['low'] ?? 0);

        // Calculate zero-trust security posture score (0 - 100)
        $score = max(10, 100 - ($criticalCount * 25) - ($highCount * 12) - ($mediumCount * 4) - ($lowCount * 1));

        echo json_encode([
            'posture_score' => $score,
            'vuln_counts' => [
                'critical' => $criticalCount,
                'high' => $highCount,
                'medium' => $mediumCount,
                'low' => $lowCount,
                'total' => $criticalCount + $highCount + $mediumCount + $lowCount
            ],
            'jailed_ips' => $jailedIps,
            'vault_keys' => $vaultKeys,
            'audit_logs' => $auditLogs
        ]);
        exit;
    }

    if ($action === 'run_security_scan' && $user_role === 'admin') {
        // Scan managed devices for risky open ports
        $devices = $pdo->query("SELECT id, name, ip FROM devices WHERE ip IS NOT NULL AND ip != '' LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
        $foundVulns = 0;
        $riskyPorts = [
            21 => ['title' => 'Unencrypted FTP Service Detected', 'severity' => 'medium', 'cat' => 'unencrypted_protocol', 'rec' => 'Upgrade to SFTP (Port 22) or FTPS to prevent credential sniffing in transit.'],
            23 => ['title' => 'Legacy Telnet Protocol Open', 'severity' => 'critical', 'cat' => 'unencrypted_protocol', 'rec' => 'Disable Telnet immediately and enforce SSH v2 encryption.'],
            80 => ['title' => 'Plaintext HTTP Management Interface', 'severity' => 'low', 'cat' => 'unencrypted_protocol', 'rec' => 'Enforce HTTPS (Port 443) with strong TLS 1.3 encryption.'],
            445 => ['title' => 'Exposed SMB File Sharing Port', 'severity' => 'high', 'cat' => 'open_port', 'rec' => 'Restrict SMB port 445 via firewall to internal subnets to prevent lateral movement (EternalBlue/Ransomware).'],
            3389 => ['title' => 'Exposed Remote Desktop (RDP)', 'severity' => 'medium', 'cat' => 'open_port', 'rec' => 'Put RDP behind VPN or enforce Network Level Authentication (NLA) with IP whitelisting.']
        ];

        foreach ($devices as $dev) {
            $ip = $dev['ip'];
            $devId = $dev['id'];

            foreach ($riskyPorts as $port => $info) {
                // Quick socket probe (300ms timeout)
                $fp = @fsockopen($ip, $port, $errno, $errstr, 0.3);
                if ($fp) {
                    fclose($fp);
                    $stmt = $pdo->prepare("
                        INSERT INTO security_vulnerabilities (id, device_id, category, severity, title, description, recommendation, detected_port, status)
                        VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, 'active')
                    ");
                    $stmt->execute([
                        $devId,
                        $info['cat'],
                        $info['severity'],
                        "{$info['title']} on {$dev['name']}",
                        "Port {$port} was found actively listening and reachable at IP {$ip}.",
                        $info['rec'],
                        $port
                    ]);
                    $foundVulns++;
                }
            }
        }

        echo json_encode(['success' => true, 'scanned_devices' => count($devices), 'detected_vulnerabilities' => $foundVulns]);
        exit;
    }

    if ($action === 'unjail_ip' && $user_role === 'admin') {
        $ip = $_POST['ip'] ?? '';
        if (!empty($ip)) {
            SecurityGuard::unjailIp($pdo, $ip);
            echo json_encode(['success' => true, 'message' => "IP {$ip} successfully released from jail."]);
        } else {
            echo json_encode(['success' => false, 'error' => 'IP is required.']);
        }
        exit;
    }

    if ($action === 'save_vault_key' && $user_role === 'admin') {
        $name = trim($_POST['vault_name'] ?? '');
        $type = $_POST['credential_type'] ?? 'general';
        $secret = $_POST['secret_value'] ?? '';
        $desc = trim($_POST['description'] ?? '');

        if (empty($name) || empty($secret)) {
            echo json_encode(['success' => false, 'error' => 'Vault name and secret are required.']);
            exit;
        }

        $encrypted = CryptoVault::encrypt($secret);
        $fingerprint = substr(hash('sha256', $secret), 0, 12);

        $stmt = $pdo->prepare("INSERT INTO encrypted_vault_keys (id, user_id, vault_name, credential_type, encrypted_payload, fingerprint, description, last_rotated_at) VALUES (UUID(), ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$current_user_id, $name, $type, $encrypted, $fingerprint, $desc]);

        echo json_encode(['success' => true, 'message' => "Secret '{$name}' encrypted and stored safely in AES-256-GCM vault."]);
        exit;
    }
}

require_once 'header.php';
?>

<div class="container mx-auto px-4 py-6 max-w-7xl">
    <!-- Header Title -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-shield-halved text-cyan-400"></i> Zero-Trust Security &amp; Crypto Vault
            </h1>
            <p class="text-slate-400 text-sm mt-1">Real-time vulnerability audit, AES-256-GCM credential vault, and autonomous IP jail brute-force shield.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" onclick="runSecurityScan()" id="btnScan" class="px-4 py-2 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white rounded-lg text-sm font-semibold shadow-lg shadow-cyan-600/30 transition-all flex items-center gap-2">
                <i class="fas fa-radar"></i> Run Security Radar Scan
            </button>
            <button type="button" onclick="openVaultModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 rounded-lg text-sm font-semibold transition-all flex items-center gap-2">
                <i class="fas fa-key text-amber-400"></i> Store Secret
            </button>
        </div>
    </div>

    <!-- Security KPI Cards & Zero-Trust Score -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Posture Score -->
        <div class="bg-slate-900/80 border border-cyan-500/30 rounded-xl p-5 shadow-xl relative overflow-hidden flex items-center gap-4">
            <div class="w-16 h-16 rounded-full border-4 border-cyan-500/40 flex items-center justify-center font-mono font-bold text-2xl text-cyan-400 shadow-inner" id="postureScore">
                --
            </div>
            <div>
                <span class="text-2xs font-bold text-cyan-400 uppercase tracking-widest">Zero-Trust Posture</span>
                <h3 class="text-lg font-bold text-white mt-0.5" id="postureLabel">Evaluating...</h3>
                <p class="text-3xs text-slate-400">AES-256-GCM Guard Active</p>
            </div>
        </div>

        <!-- Critical Risks -->
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-5 shadow-md flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 text-xl">
                <i class="fas fa-triangle-exclamation"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Critical Threats</span>
                <h3 class="text-xl font-bold text-white" id="statCritical">0</h3>
                <p class="text-3xs text-rose-400">Immediate action needed</p>
            </div>
        </div>

        <!-- Jailed IPs -->
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-5 shadow-md flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 text-xl">
                <i class="fas fa-lock"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Jailed Hostile IPs</span>
                <h3 class="text-xl font-bold text-white" id="statJailed">0</h3>
                <p class="text-3xs text-amber-400">Rate-limited &amp; Jailed</p>
            </div>
        </div>

        <!-- Encrypted Vault Items -->
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-xl p-5 shadow-md flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-xl">
                <i class="fas fa-vault"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Encrypted Secrets</span>
                <h3 class="text-xl font-bold text-white" id="statVault">0</h3>
                <p class="text-3xs text-emerald-400">Hardware &amp; API Keys</p>
            </div>
        </div>
    </div>

    <!-- Main Content Tabs / Sections -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left 2 Cols: Active Vulnerabilities & Port Audit -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 overflow-hidden shadow-xl">
                <div class="px-6 py-4 border-b border-slate-700/60 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fas fa-bug text-rose-400"></i> Active Security Vulnerabilities &amp; Open Port Findings
                    </h2>
                    <span class="text-xs text-slate-400 font-mono" id="vulnCounter">0 Findings</span>
                </div>
                <div class="p-6">
                    <div id="vulnList" class="space-y-3">
                        <div class="p-4 bg-slate-900/60 rounded-lg border border-slate-800 text-slate-400 text-xs text-center">
                            Loading security audit findings...
                        </div>
                    </div>
                </div>
            </div>

            <!-- AES-256-GCM Credential Vault -->
            <div class="bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 overflow-hidden shadow-xl">
                <div class="px-6 py-4 border-b border-slate-700/60 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fas fa-key text-cyan-400"></i> Encrypted Credential Vault (AES-256-GCM)
                    </h2>
                    <button type="button" onclick="openVaultModal()" class="text-xs text-cyan-400 hover:text-cyan-300 font-semibold">
                        + Add Secret
                    </button>
                </div>
                <div class="p-6">
                    <div id="vaultList" class="space-y-2.5">
                        <div class="text-xs text-slate-400 text-center py-2">No secrets registered in vault.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Col: IP Jail & Live Security Event Stream -->
        <div class="space-y-6">
            <!-- Jailed IPs Panel -->
            <div class="bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 overflow-hidden shadow-xl">
                <div class="px-6 py-4 border-b border-slate-700/60 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fas fa-shield-virus text-amber-400"></i> IP Jail &amp; Brute-Force Defense
                    </h2>
                    <span class="text-xs text-slate-400 font-mono" id="jailCounter">0 Jailed</span>
                </div>
                <div class="p-4 max-h-72 overflow-y-auto space-y-2" id="jailList">
                    <div class="text-xs text-slate-400 text-center py-2">No IPs currently jailed.</div>
                </div>
            </div>

            <!-- Security Audit Stream -->
            <div class="bg-slate-800/80 backdrop-blur rounded-xl border border-slate-700/80 overflow-hidden shadow-xl">
                <div class="px-6 py-4 border-b border-slate-700/60 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fas fa-clock-rotate-left text-blue-400"></i> Live Security Events Stream
                    </h2>
                </div>
                <div class="p-4 max-h-80 overflow-y-auto space-y-2 font-mono text-2xs" id="auditLogStream">
                    <div class="text-slate-400 text-center py-2">Streaming security events...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Store Secret in Vault -->
<div id="vaultModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-700 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fas fa-lock text-cyan-400"></i> Store Secret in AES-256-GCM Vault
            </h3>
            <button type="button" onclick="closeVaultModal()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form id="vaultForm" onsubmit="handleVaultSubmit(event)" class="space-y-4 text-xs">
            <div>
                <label class="block text-slate-300 font-semibold mb-1">Secret Label / Name</label>
                <input type="text" name="vault_name" required placeholder="e.g. Core Switch SNMPv3 Auth" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-slate-300 font-semibold mb-1">Credential Type</label>
                <select name="credential_type" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
                    <option value="snmp_v3">SNMP v3 Auth / Priv Key</option>
                    <option value="ssh_password">SSH / Telnet Password</option>
                    <option value="agent_token">Agent Auth Token</option>
                    <option value="api_secret">Webhook / API Secret</option>
                    <option value="general">General Sensitive Passphrase</option>
                </select>
            </div>
            <div>
                <label class="block text-slate-300 font-semibold mb-1">Secret Passphrase / Value</label>
                <input type="password" name="secret_value" required placeholder="••••••••••••••••" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:border-cyan-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-slate-300 font-semibold mb-1">Description (Optional)</label>
                <input type="text" name="description" placeholder="Notes on usage..." class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-cyan-500 focus:outline-none">
            </div>
            <div class="pt-2 flex justify-end gap-2">
                <button type="button" onclick="closeVaultModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold rounded-lg shadow-lg shadow-cyan-600/30 flex items-center gap-2">
                    <i class="fas fa-shield-check"></i> Encrypt &amp; Save
                </button>
            </div>
        </form>
    </div>
</div>

<script>
async function loadSecuritySummary() {
    try {
        const res = await fetch('security_audit.php?action=get_security_summary');
        const data = await res.json();

        // Update Posture Score
        const scoreEl = document.getElementById('postureScore');
        const labelEl = document.getElementById('postureLabel');
        scoreEl.textContent = data.posture_score + '%';
        if (data.posture_score >= 85) {
            scoreEl.className = 'w-16 h-16 rounded-full border-4 border-emerald-500/40 flex items-center justify-center font-mono font-bold text-2xl text-emerald-400 shadow-inner';
            labelEl.textContent = 'Excellent Defense';
            labelEl.className = 'text-lg font-bold text-emerald-400 mt-0.5';
        } else if (data.posture_score >= 60) {
            scoreEl.className = 'w-16 h-16 rounded-full border-4 border-amber-500/40 flex items-center justify-center font-mono font-bold text-2xl text-amber-400 shadow-inner';
            labelEl.textContent = 'Moderate Exposure';
            labelEl.className = 'text-lg font-bold text-amber-400 mt-0.5';
        } else {
            scoreEl.className = 'w-16 h-16 rounded-full border-4 border-rose-500/40 flex items-center justify-center font-mono font-bold text-2xl text-rose-400 shadow-inner';
            labelEl.textContent = 'High Vulnerability';
            labelEl.className = 'text-lg font-bold text-rose-400 mt-0.5';
        }

        // Stats
        document.getElementById('statCritical').textContent = data.vuln_counts.critical;
        document.getElementById('statJailed').textContent = data.jailed_ips.length;
        document.getElementById('statVault').textContent = data.vault_keys.length;
        document.getElementById('vulnCounter').textContent = data.vuln_counts.total + ' Findings';
        document.getElementById('jailCounter').textContent = data.jailed_ips.length + ' Jailed';

        // Render Vault List
        const vaultContainer = document.getElementById('vaultList');
        if (data.vault_keys.length === 0) {
            vaultContainer.innerHTML = '<div class="text-xs text-slate-400 text-center py-2">No secrets stored in vault yet.</div>';
        } else {
            vaultContainer.innerHTML = data.vault_keys.map(k => `
                <div class="p-3 bg-slate-900/60 border border-slate-800 rounded-lg flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-white text-xs">${k.vault_name}</span>
                            <span class="px-2 py-0.5 bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 rounded text-3xs font-mono uppercase">${k.credential_type}</span>
                        </div>
                        <p class="text-3xs text-slate-400 mt-0.5 font-mono">Fingerprint: ${k.fingerprint} • Encrypted (AES-256-GCM)</p>
                    </div>
                    <span class="text-slate-500 text-xs font-mono">••••••••••••</span>
                </div>
            `).join('');
        }

        // Render Jailed IPs
        const jailContainer = document.getElementById('jailList');
        if (data.jailed_ips.length === 0) {
            jailContainer.innerHTML = '<div class="text-xs text-slate-400 text-center py-2">No IPs currently jailed.</div>';
        } else {
            jailContainer.innerHTML = data.jailed_ips.map(j => `
                <div class="p-2.5 bg-rose-500/10 border border-rose-500/20 rounded-lg flex items-center justify-between">
                    <div>
                        <span class="font-mono font-bold text-rose-300 text-xs">${j.ip_address}</span>
                        <p class="text-3xs text-slate-400">${j.reason}</p>
                    </div>
                    <button onclick="unjailIp('${j.ip_address}')" class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded text-3xs font-semibold">
                        Unjail
                    </button>
                </div>
            `).join('');
        }

        // Render Security Audit Log Stream
        const streamContainer = document.getElementById('auditLogStream');
        if (data.audit_logs.length === 0) {
            streamContainer.innerHTML = '<div class="text-slate-400 text-center py-2">No security audit events recorded.</div>';
        } else {
            streamContainer.innerHTML = data.audit_logs.map(log => `
                <div class="flex items-start gap-2 border-b border-slate-800/60 pb-1.5">
                    <span class="text-slate-500 shrink-0">${log.created_at.substring(11, 19)}</span>
                    <span class="text-cyan-400 shrink-0">[${log.event_type}]</span>
                    <span class="text-slate-300">${log.details || log.target_identifier}</span>
                </div>
            `).join('');
        }

    } catch (e) {
        console.error('Failed to load security summary:', e);
    }
}

async function runSecurityScan() {
    const btn = document.getElementById('btnScan');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Probing Network Ports...';
    try {
        const res = await fetch('security_audit.php?action=run_security_scan');
        const data = await res.json();
        if (window.notyf) window.notyf.success({ message: `Security scan complete: Probed ${data.scanned_devices} devices. Detected ${data.detected_vulnerabilities} risks.` });
        loadSecuritySummary();
    } catch (e) {
        if (window.notyf) window.notyf.error({ message: 'Security scan failed.' });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-radar"></i> Run Security Radar Scan';
    }
}

async function unjailIp(ip) {
    const formData = new FormData();
    formData.append('ip', ip);
    const res = await fetch('security_audit.php?action=unjail_ip', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        if (window.notyf) window.notyf.success({ message: data.message });
        loadSecuritySummary();
    }
}

function openVaultModal() {
    document.getElementById('vaultModal').classList.remove('hidden');
}

function closeVaultModal() {
    document.getElementById('vaultModal').classList.add('hidden');
}

async function handleVaultSubmit(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('security_audit.php?action=save_vault_key', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        if (window.notyf) window.notyf.success({ message: data.message });
        closeVaultModal();
        e.target.reset();
        loadSecuritySummary();
    } else {
        if (window.notyf) window.notyf.error({ message: data.error || 'Failed to save secret.' });
    }
}

document.addEventListener('DOMContentLoaded', loadSecuritySummary);
</script>

<?php require_once 'footer.php'; ?>
