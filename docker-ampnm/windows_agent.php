<?php
require_once 'includes/auth_check.php';
require_once 'header.php';

// Auto-detect server URL (same logic as host_metrics.php)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$serverUrl = $protocol . $_SERVER['HTTP_HOST'] . ($basePath === '/' ? '' : $basePath);

$metricsEndpoint = $serverUrl . '/api/agent/windows-metrics';
$healthEndpoint = $metricsEndpoint . '/health';
$installerDownload = $serverUrl . '/download-agent.php?file=AMPNM-Agent-Installer.ps1';
$simpleBatDownload = $serverUrl . '/download-agent.php?file=AMPNM-Agent-Simple.bat';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-white">
                <i class="fas fa-person-chalkboard text-cyan-400 mr-2"></i>Windows Agent Onboarding
            </h1>
            <p class="text-slate-400 mt-2">Create a token, download the agent, and install with a copy‑paste command.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="host_metrics.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-sm font-medium transition-colors border border-slate-700">
                <i class="fas fa-chart-area mr-2"></i>Open Host Metrics
            </a>
            <a href="<?php echo htmlspecialchars($healthEndpoint); ?>" target="_blank" rel="noreferrer" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-sm font-medium transition-colors border border-slate-700">
                <i class="fas fa-plug-circle-check mr-2"></i>API Health
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <!-- Step 1: Token -->
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
            <h2 class="text-xl font-semibold text-white mb-3">
                <i class="fas fa-key text-amber-400 mr-2"></i>Step 1 — Create an Agent Token
            </h2>
            <p class="text-slate-300 text-sm leading-relaxed">
                Tokens authenticate Windows hosts posting metrics. Create one token and reuse it on as many hosts as you need.
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="host_metrics.php?modal=tokens" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-key mr-2"></i>Manage / Create Tokens
                </a>
                <a href="documentation.php#windows-agent" class="px-4 py-2 bg-slate-900/40 hover:bg-slate-900/60 text-white rounded-lg text-sm font-medium transition-colors border border-slate-700">
                    <i class="fas fa-book-open mr-2"></i>Read Guide
                </a>
            </div>
            <p class="text-slate-500 text-xs mt-3"><i class="fas fa-info-circle mr-1"></i>After creating, click the token to copy it.</p>
        </section>

        <!-- Step 2: Download -->
        <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
            <h2 class="text-xl font-semibold text-white mb-3">
                <i class="fas fa-download text-purple-400 mr-2"></i>Step 2 — Download
            </h2>
            <div class="space-y-3">
                <div class="bg-slate-900/40 border border-slate-700 rounded-lg p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-white font-medium">PowerShell Installer (recommended)</p>
                            <p class="text-slate-400 text-xs mt-1">Installs as a Windows Service (AMPNM-Agent) and runs continuously.</p>
                        </div>
                        <a class="px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded text-xs font-medium" href="download-agent.php?file=AMPNM-Agent-Installer.ps1">
                            <i class="fas fa-download mr-1"></i>Download
                        </a>
                    </div>
                    <code class="block mt-3 text-xs text-green-400 break-all"><?php echo htmlspecialchars($installerDownload); ?></code>
                </div>

                <div class="bg-slate-900/40 border border-slate-700 rounded-lg p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-white font-medium">Simple BAT (manual / scheduled)</p>
                            <p class="text-slate-400 text-xs mt-1">Useful for quick testing or Task Scheduler based runs.</p>
                        </div>
                        <a class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded text-xs font-medium" href="download-agent.php?file=AMPNM-Agent-Simple.bat">
                            <i class="fas fa-download mr-1"></i>Download
                        </a>
                    </div>
                    <code class="block mt-3 text-xs text-green-400 break-all"><?php echo htmlspecialchars($simpleBatDownload); ?></code>
                </div>
            </div>
        </section>
    </div>

    <!-- Step 3: Install Command -->
    <section class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 mt-6">
        <h2 class="text-xl font-semibold text-white mb-3">
            <i class="fas fa-bolt text-yellow-400 mr-2"></i>Step 3 — Copy & Paste Install Command
        </h2>
        <p class="text-slate-400 text-sm">Run in PowerShell <strong>as Administrator</strong> on the Windows host.</p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-4">
            <div class="md:col-span-2">
                <label for="onboard-server-url" class="block text-xs font-medium text-slate-400 mb-1">Server URL</label>
                <input
                    type="text"
                    id="onboard-server-url"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-xs text-slate-200 focus:ring-2 focus:ring-cyan-500"
                    value="<?php echo htmlspecialchars($metricsEndpoint); ?>"
                >
            </div>
            <div>
                <label for="onboard-token" class="block text-xs font-medium text-slate-400 mb-1">Agent Token</label>
                <input
                    type="text"
                    id="onboard-token"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-xs text-slate-200 focus:ring-2 focus:ring-cyan-500"
                    placeholder="Paste token here"
                    maxlength="200"
                    autocomplete="off"
                >
            </div>
        </div>

        <div class="bg-slate-900/40 border border-slate-700 rounded-lg p-3 mt-4">
            <div class="flex items-start gap-2">
                <code id="onboard-install-command" class="text-xs text-green-400 leading-relaxed flex-1 break-all whitespace-pre-wrap"></code>
                <button
                    type="button"
                    onclick="copyOnboardingInstallCommand()"
                    class="flex-shrink-0 px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded text-xs font-medium transition-colors"
                >
                    <i class="fas fa-copy mr-1"></i>Copy
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mt-4">
            <div class="bg-slate-900/40 border border-slate-700 rounded-lg p-4">
                <p class="text-white font-medium text-sm mb-2"><i class="fas fa-check-double text-green-400 mr-2"></i>Verify</p>
                <ul class="text-slate-300 text-sm space-y-1">
                    <li>• Open <a class="text-cyan-400 hover:underline" href="host_metrics.php">Host Metrics</a> and confirm the host appears within ~60 seconds.</li>
                    <li>• Agent API health: <code class="text-green-400 text-xs"><?php echo htmlspecialchars($healthEndpoint); ?></code></li>
                </ul>
            </div>
            <div class="bg-amber-500/10 border border-amber-500/30 rounded-lg p-4">
                <p class="text-amber-400 font-medium text-sm mb-2"><i class="fas fa-triangle-exclamation mr-2"></i>Common Issues</p>
                <ul class="text-slate-300 text-sm space-y-1">
                    <li>• Must run PowerShell as Administrator</li>
                    <li>• Windows Firewall / outbound HTTP(S) blocked</li>
                    <li>• Wrong URL (include the <code>/api/agent/windows-metrics</code> path)</li>
                    <li>• Token disabled / copied incorrectly</li>
                </ul>
            </div>
        </div>
    </section>
</div>

<script>
const onboardServerInput = document.getElementById('onboard-server-url');
const onboardTokenInput = document.getElementById('onboard-token');
const onboardCommandEl = document.getElementById('onboard-install-command');

function buildOnboardingDownloadUrl() {
    const url = new URL('download-agent.php', window.location.href);
    url.searchParams.set('file', 'AMPNM-Agent-Installer.ps1');

    const serverUrl = onboardServerInput?.value.trim();
    const token = onboardTokenInput?.value.trim();

    if (serverUrl) url.searchParams.set('server_url', serverUrl);
    if (token) url.searchParams.set('agent_token', token);

    return url.toString();
}

function buildOnboardingInstallCommand() {
    const serverUrl = onboardServerInput?.value.trim() || '';
    const token = onboardTokenInput?.value.trim() || '';
    const downloadUrl = buildOnboardingDownloadUrl();

    const serverArg = serverUrl ? `-ServerUrl "${serverUrl}"` : '-ServerUrl "<server-url>"';
    const tokenArg = token ? `-AgentToken "${token}"` : '-AgentToken "<agent-token>"';

    // NOTE: Avoid showing an "ExecutionPolicy Bypass" download-and-run one-liner in-page.
    // Many endpoint security products heuristically flag such patterns.
    // This multi-line snippet is functionally equivalent but reduces false positives.
    return [
        `$p = "$env:TEMP\\AMPNM-Agent-Installer.ps1"`,
        `Invoke-WebRequest -Uri "${downloadUrl}" -OutFile $p`,
        `Unblock-File -Path $p`,
        `& $p ${serverArg} ${tokenArg}`,
    ].join('\n');
}

function updateOnboardingInstallCommand() {
    if (!onboardCommandEl) return;
    onboardCommandEl.textContent = buildOnboardingInstallCommand();
}

function copyOnboardingInstallCommand() {
    const text = onboardCommandEl?.textContent || '';
    if (!text) return;

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text)
            .then(() => window.notyf ? window.notyf.success('Install command copied!') : alert('Copied!'))
            .catch(() => fallbackCopy(text));
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try {
        document.execCommand('copy');
        window.notyf ? window.notyf.success('Install command copied!') : alert('Copied!');
    } catch (e) {
        window.notyf ? window.notyf.error('Copy failed. Please copy manually.') : alert('Copy failed.');
    }
    document.body.removeChild(ta);
}

if (onboardServerInput) onboardServerInput.addEventListener('input', updateOnboardingInstallCommand);
if (onboardTokenInput) onboardTokenInput.addEventListener('input', updateOnboardingInstallCommand);
updateOnboardingInstallCommand();
</script>

<?php include 'footer.php'; ?>
