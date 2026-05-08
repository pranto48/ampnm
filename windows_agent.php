<?php
require_once 'includes/auth_check.php';
require_once 'header.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$serverUrl = $protocol . $_SERVER['HTTP_HOST'] . ($basePath === '/' ? '' : $basePath);

$metricsEndpoint = $serverUrl . '/api/agent/metrics';
$healthEndpoint = $metricsEndpoint . '/health';
$windowsInstallerDownload = $serverUrl . '/download-agent.php?file=AMPNM-Agent-Installer.ps1';
$windowsBatDownload = $serverUrl . '/download-agent.php?file=AMPNM-Agent-Simple.bat';
$linuxInstallDownload = $serverUrl . '/download-agent.php?file=install.sh';
$linuxRuntimeDownload = $serverUrl . '/download-agent.php?file=ampnm-agent.sh';
$linuxServiceDownload = $serverUrl . '/download-agent.php?file=ampnm-agent.service';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-white">
                <i class="fas fa-server text-cyan-400 mr-2"></i>Agent Onboarding
            </h1>
            <p class="text-slate-400 mt-2">Install Windows or Linux agents, register them with the Docker AMPNM server, and stream host/device telemetry into Host Metrics.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="host_metrics.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-sm font-medium transition-colors border border-slate-700">
                <i class="fas fa-chart-area mr-2"></i>Open Host Metrics
            </a>
            <a href="<?php echo htmlspecialchars($healthEndpoint); ?>" target="_blank" rel="noreferrer" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-sm font-medium transition-colors border border-slate-700">
                <i class="fas fa-plug-circle-check mr-2"></i>Agent API Health
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
            <h2 class="text-xl font-semibold text-white mb-3">
                <i class="fas fa-key text-amber-400 mr-2"></i>Step 1 — Shared Agent Tokens
            </h2>
            <p class="text-slate-300 text-sm leading-relaxed">
                Create one token and reuse it for Windows and Linux agents that should report into this Docker server.
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="host_metrics.php?modal=tokens" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-key mr-2"></i>Manage / Create Tokens
                </a>
                <a href="documentation.php#windows-agent" class="px-4 py-2 bg-slate-900/40 hover:bg-slate-900/60 text-white rounded-lg text-sm font-medium transition-colors border border-slate-700">
                    <i class="fas fa-book-open mr-2"></i>Read Guide
                </a>
            </div>
            <div class="mt-4">
                <label for="shared-agent-token" class="block text-xs font-medium text-slate-400 mb-1">Selected Agent Token</label>
                <input id="shared-agent-token" type="text" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-xs text-slate-200 focus:ring-2 focus:ring-cyan-500" placeholder="Paste token here" autocomplete="off">
            </div>
            <p class="text-slate-500 text-xs mt-3"><i class="fas fa-info-circle mr-1"></i>Use the same endpoint and token workflow for both platforms so host data lands in the same inventory.</p>
        </section>

        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
            <h2 class="text-xl font-semibold text-white mb-3">
                <i class="fas fa-link text-emerald-400 mr-2"></i>Step 2 — Docker Server Endpoint
            </h2>
            <p class="text-slate-400 text-sm">Agents send metrics to this Docker-hosted API endpoint using the <code>X-Agent-Token</code> header.</p>
            <div class="bg-slate-900/40 border border-slate-700 rounded-lg p-4 mt-4">
                <code id="shared-endpoint" class="text-xs text-green-400 break-all"><?php echo htmlspecialchars($metricsEndpoint); ?></code>
                <button type="button" onclick="copyText(document.getElementById('shared-endpoint').textContent, 'Endpoint copied')" class="mt-3 px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded text-xs font-medium">
                    <i class="fas fa-copy mr-1"></i>Copy Endpoint
                </button>
            </div>
        </section>
    </div>

    <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 mt-6">
        <div class="flex flex-wrap gap-2 mb-4">
            <button type="button" data-platform-tab="windows" class="platform-tab px-4 py-2 rounded-lg text-sm font-medium bg-cyan-600 text-white">Windows</button>
            <button type="button" data-platform-tab="linux" class="platform-tab px-4 py-2 rounded-lg text-sm font-medium bg-slate-700 text-slate-300">Linux</button>
        </div>

        <div id="platform-panel-windows" class="platform-panel space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="bg-slate-900/40 border border-slate-700 rounded-lg p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-white font-medium">PowerShell Installer</p>
                            <p class="text-slate-400 text-xs mt-1">Recommended for long-running Windows service installs.</p>
                        </div>
                        <a class="px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded text-xs font-medium" href="<?php echo htmlspecialchars($windowsInstallerDownload); ?>">
                            <i class="fas fa-download mr-1"></i>Download
                        </a>
                    </div>
                    <code id="windows-install-command" class="block mt-3 text-xs text-green-400 break-all whitespace-pre-wrap"></code>
                    <button type="button" onclick="copyText(document.getElementById('windows-install-command').textContent, 'Windows install command copied')" class="mt-3 px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded text-xs font-medium">
                        <i class="fas fa-copy mr-1"></i>Copy Command
                    </button>
                </div>
                <div class="bg-slate-900/40 border border-slate-700 rounded-lg p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-white font-medium">Simple BAT</p>
                            <p class="text-slate-400 text-xs mt-1">Useful for quick validation or Task Scheduler runs.</p>
                        </div>
                        <a class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded text-xs font-medium" href="<?php echo htmlspecialchars($windowsBatDownload); ?>">
                            <i class="fas fa-download mr-1"></i>Download
                        </a>
                    </div>
                    <code class="block mt-3 text-xs text-green-400 break-all"><?php echo htmlspecialchars($windowsBatDownload); ?></code>
                </div>
            </div>

            <div class="bg-slate-900/40 border border-slate-700 rounded-lg p-4">
                <p class="text-white font-medium text-sm mb-2"><i class="fas fa-check-double text-green-400 mr-2"></i>Windows Verification</p>
                <pre id="windows-verify-command" class="text-xs text-green-400 whitespace-pre-wrap">Get-Service -Name AMPNM-Agent
Get-Service -Name AMPNM-Agent | Select-Object Status, Name, DisplayName
Get-Content "$env:ProgramData\AMPNM-Agent\logs\agent.log" -Tail 50</pre>
                <button type="button" onclick="copyText(document.getElementById('windows-verify-command').textContent, 'Windows verification copied')" class="mt-3 px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded text-xs font-medium">
                    <i class="fas fa-copy mr-1"></i>Copy Verify Commands
                </button>
            </div>
        </div>

        <div id="platform-panel-linux" class="platform-panel hidden space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="bg-slate-900/40 border border-slate-700 rounded-lg p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-white font-medium">Shell Installer</p>
                            <p class="text-slate-400 text-xs mt-1">Downloads the Linux installer and registers the host to this Docker endpoint.</p>
                        </div>
                        <a class="px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded text-xs font-medium" href="<?php echo htmlspecialchars($linuxInstallDownload); ?>">
                            <i class="fas fa-download mr-1"></i>Download
                        </a>
                    </div>
                    <code id="linux-install-command" class="block mt-3 text-xs text-green-400 break-all whitespace-pre-wrap"></code>
                    <button type="button" onclick="copyText(document.getElementById('linux-install-command').textContent, 'Linux install command copied')" class="mt-3 px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded text-xs font-medium">
                        <i class="fas fa-copy mr-1"></i>Copy Command
                    </button>
                </div>
                <div class="bg-slate-900/40 border border-slate-700 rounded-lg p-4">
                    <p class="text-white font-medium">Linux Runtime Files</p>
                    <p class="text-slate-400 text-xs mt-1">Use these if you want to stage the service manually.</p>
                    <div class="mt-3 space-y-2 text-xs">
                        <a class="block text-cyan-400 hover:underline" href="<?php echo htmlspecialchars($linuxRuntimeDownload); ?>">Download ampnm-agent.sh</a>
                        <a class="block text-cyan-400 hover:underline" href="<?php echo htmlspecialchars($linuxServiceDownload); ?>">Download ampnm-agent.service</a>
                    </div>
                </div>
            </div>

            <div class="bg-slate-900/40 border border-slate-700 rounded-lg p-4">
                <p class="text-white font-medium text-sm mb-2"><i class="fas fa-terminal text-emerald-400 mr-2"></i>Linux Service Verification</p>
                <pre id="linux-verify-command" class="text-xs text-green-400 whitespace-pre-wrap">sudo systemctl status ampnm-agent.service
sudo journalctl -u ampnm-agent.service -n 50 --no-pager
sudo systemctl restart ampnm-agent.service</pre>
                <button type="button" onclick="copyText(document.getElementById('linux-verify-command').textContent, 'Linux verification copied')" class="mt-3 px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded text-xs font-medium">
                    <i class="fas fa-copy mr-1"></i>Copy Verify Commands
                </button>
            </div>
        </div>
    </section>

    <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 mt-6">
        <h2 class="text-xl font-semibold text-white mb-3">
            <i class="fas fa-bolt text-yellow-400 mr-2"></i>Step 4 — Validate Host Registration
        </h2>
        <ul class="text-slate-300 text-sm space-y-2 list-disc ml-5">
            <li>Confirm the agent appears in <a class="text-cyan-400 hover:underline" href="host_metrics.php">Host Metrics</a> within about 60 seconds.</li>
            <li>Make sure the host IP or hostname matches a device in AMPNM if you want automatic linking to an existing device record.</li>
            <li>Both Windows and Linux agents send telemetry into the same Docker server inventory so host/device information stays shared in one place.</li>
        </ul>
    </section>
</div>

<script>
const metricsEndpoint = <?php echo json_encode($metricsEndpoint); ?>;
const windowsDownloadUrl = <?php echo json_encode($windowsInstallerDownload); ?>;
const linuxInstallUrl = <?php echo json_encode($linuxInstallDownload); ?>;
const platformTabs = document.querySelectorAll('.platform-tab');
const panels = document.querySelectorAll('.platform-panel');

function activeAgentToken() {
    const tokenField = document.getElementById('shared-agent-token');
    return tokenField ? tokenField.value.trim() : '';
}

function buildWindowsCommand() {
    const token = activeAgentToken() || '<agent-token>';
    return [
        '$p = "$env:TEMP\\AMPNM-Agent-Installer.ps1"',
        `Invoke-WebRequest -Uri "${windowsDownloadUrl}" -OutFile $p`,
        'Unblock-File -Path $p',
        `& $p -ServerUrl "${metricsEndpoint}" -AgentToken "${token}"`,
    ].join('\n');
}

function buildLinuxCommand() {
    const token = activeAgentToken() || '<agent-token>';
    return `curl -fsSL ${linuxInstallUrl} | sudo bash -s -- --server-url ${metricsEndpoint} --agent-token ${token}`;
}

function refreshCommands() {
    const windowsEl = document.getElementById('windows-install-command');
    const linuxEl = document.getElementById('linux-install-command');
    if (windowsEl) windowsEl.textContent = buildWindowsCommand();
    if (linuxEl) linuxEl.textContent = buildLinuxCommand();
}

function setPlatformTab(target) {
    platformTabs.forEach((tab) => {
        const active = tab.dataset.platformTab === target;
        tab.classList.toggle('bg-cyan-600', active);
        tab.classList.toggle('text-white', active);
        tab.classList.toggle('bg-slate-700', !active);
        tab.classList.toggle('text-slate-300', !active);
    });
    panels.forEach((panel) => panel.classList.toggle('hidden', panel.id !== `platform-panel-${target}`));
}

function copyText(text, successMessage) {
    if (!text) return;
    navigator.clipboard.writeText(text)
        .then(() => window.notyf ? window.notyf.success(successMessage || 'Copied') : alert(successMessage || 'Copied'))
        .catch(() => {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        });
}

platformTabs.forEach((tab) => tab.addEventListener('click', () => setPlatformTab(tab.dataset.platformTab)));
const sharedTokenInput = document.getElementById('shared-agent-token');
if (sharedTokenInput) sharedTokenInput.addEventListener('input', refreshCommands);
refreshCommands();
setPlatformTab('windows');
</script>

<?php include 'footer.php'; ?>
