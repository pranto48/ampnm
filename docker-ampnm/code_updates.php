<?php
require_once 'includes/auth_check.php';
include 'header.php';

$canonicalRepoUrl = 'https://github.com/pranto48/ampnm.git';
$canonicalBranch = getenv('AMPNM_UPDATE_BRANCH') ?: 'main';

$autoDetection = (function (string $startPath): array {
    $normalize = static function (string $path): string {
        return rtrim($path, '/\\');
    };

    $attempts = [];
    $addAttempt = static function (string $path) use (&$attempts, $normalize): void {
        $normalized = $normalize($path);
        if (!in_array($normalized, $attempts, true)) {
            $attempts[] = $normalized;
        }
    };

    $envPath = getenv('AMPNM_REPO_PATH') ?: getenv('REPO_PATH');
    if ($envPath) {
        $addAttempt($envPath);
    }

    $current = realpath($startPath) ?: $startPath;
    $addAttempt($current);
    while ($current && $current !== dirname($current)) {
        $current = dirname($current);
        $addAttempt($current);
    }

    $parent = dirname(realpath($startPath) ?: $startPath);
    $addAttempt($parent . DIRECTORY_SEPARATOR . 'ampnm-project');
    $addAttempt('/var/www/html/ampnm-project');

    $detected = null;
    foreach ($attempts as $path) {
        $gitDir = $path . DIRECTORY_SEPARATOR . '.git';
        if (is_dir($gitDir) || is_file($gitDir)) {
            $detected = $path;
            break;
        }
    }

    return [
        'path' => $detected,
        'attempts' => $attempts,
        'fallback' => $attempts[0] ?? realpath($startPath) ?: $startPath,
    ];
})(__DIR__);

$autoDetectedRepoPath = $autoDetection['path'];
$defaultRepoPath = $autoDetectedRepoPath ?? $autoDetection['fallback'];
$repoPath = isset($_POST['repo_path']) && trim($_POST['repo_path']) !== ''
    ? rtrim(trim($_POST['repo_path']), '/\\')
    : $defaultRepoPath;
$gitBinary = trim(shell_exec('which git 2>/dev/null'));
$gitAvailable = $gitBinary !== '';
$phpUser = safeTrim(shell_exec('whoami')) ?: 'www-data';
$containerHostname = safeTrim(shell_exec('hostname')) ?: 'docker';
$nodeVersion = safeTrim(shell_exec('node -v 2>/dev/null'));
$npmVersion = safeTrim(shell_exec('npm -v 2>/dev/null'));
$pnpmVersion = safeTrim(shell_exec('pnpm -v 2>/dev/null'));
$gitMarker = $repoPath . DIRECTORY_SEPARATOR . '.git';
$isGitRepo = $gitAvailable && (is_dir($gitMarker) || is_file($gitMarker));
$remoteUrl = '';
$originConfigured = false;
$remoteReachable = null;
$aheadCount = null;
$behindCount = null;
$workingTreeClean = null;
$targetUpstreamRef = 'origin/' . $canonicalBranch;

$action = $_POST['action'] ?? null;
$statusMessage = '';
$statusType = '';
$commandOutput = [];

function runGitCommand(string $repoPath, string $command): string
{
    $escapedPath = escapeshellarg($repoPath);
    $fullCommand = "cd {$escapedPath} && {$command} 2>&1";
    return shell_exec($fullCommand) ?? '';
}

function runShellCommand(string $command): string
{
    return shell_exec($command) ?? '';
}

function safeTrim(?string $value): string
{
    $value = $value ?? '';
    $lines = explode("\n", $value);
    $lines = array_filter(array_map('trim', $lines));
    return implode("\n", $lines);
}

function ensureDirectory(string $path): bool
{
    if (is_dir($path)) {
        return true;
    }

    return mkdir($path, 0755, true);
}

function isDirectoryEmpty(string $path): bool
{
    if (!is_dir($path)) {
        return true;
    }

    $files = scandir($path);
    if ($files === false) {
        return false;
    }

    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            return false;
        }
    }

    return true;
}

function collectSyncMetrics(string $repoPath, string $upstreamRef): array
{
    $metrics = [
        'workingTreeClean' => null,
        'remoteReachable' => null,
        'aheadCount' => null,
        'behindCount' => null,
    ];

    $workingTree = runGitCommand($repoPath, 'git status --porcelain');
    $metrics['workingTreeClean'] = $workingTree === '';

    $lsRemoteOutput = runGitCommand($repoPath, 'git ls-remote --exit-code origin HEAD');
    $metrics['remoteReachable'] = $lsRemoteOutput !== '' && !str_starts_with($lsRemoteOutput, 'fatal:');

    if ($upstreamRef !== '') {
        $aheadBehind = safeTrim(runGitCommand($repoPath, 'git rev-list --left-right --count ' . escapeshellarg($upstreamRef . '...HEAD')));
        if ($aheadBehind !== '' && !str_starts_with($aheadBehind, 'fatal:')) {
            $parts = preg_split('/\s+/', trim($aheadBehind));
            if (count($parts) >= 2) {
                $metrics['behindCount'] = (int) $parts[0];
                $metrics['aheadCount'] = (int) $parts[1];
            }
        }
    }

    return $metrics;
}

$currentBranch = $isGitRepo ? safeTrim(runGitCommand($repoPath, 'git rev-parse --abbrev-ref HEAD')) : '';
$localCommit = $isGitRepo ? safeTrim(runGitCommand($repoPath, 'git rev-parse HEAD')) : '';
$upstreamRef = $isGitRepo ? $targetUpstreamRef : '';
$remoteCommit = '';
$remoteUrl = $isGitRepo ? safeTrim(runGitCommand($repoPath, 'git config --get remote.origin.url')) : '';
$originConfigured = $remoteUrl !== '';

if ($isGitRepo) {
    if (!$originConfigured) {
        $escapedOrigin = escapeshellarg($canonicalRepoUrl);
        $addedOrigin = safeTrim(runGitCommand($repoPath, "git remote add origin {$escapedOrigin}"));
        $commandOutput['Remote'] = $addedOrigin !== '' ? $addedOrigin : 'origin set to canonical repository.';
        $remoteUrl = $canonicalRepoUrl;
        $originConfigured = true;
    }

    $remoteCommit = safeTrim(runGitCommand($repoPath, 'git rev-parse ' . escapeshellarg($upstreamRef)));
    if (str_starts_with($remoteCommit, 'fatal:')) {
        $remoteCommit = '';
    }

    $metrics = collectSyncMetrics($repoPath, $upstreamRef);
    $workingTreeClean = $metrics['workingTreeClean'];
    $remoteReachable = $metrics['remoteReachable'];
    $aheadCount = $metrics['aheadCount'];
    $behindCount = $metrics['behindCount'];
}

if ($action === 'check' && $isGitRepo) {
    $fetchOutput = runGitCommand($repoPath, 'git fetch --all --prune');
    $commandOutput['Fetch'] = $fetchOutput;
    $currentBranch = safeTrim(runGitCommand($repoPath, 'git rev-parse --abbrev-ref HEAD'));
    if ($currentBranch !== $canonicalBranch) {
        $commandOutput['Branch Check'] = sprintf('Warning: current branch is "%s", expected "%s". Sync metrics are still computed against %s.', $currentBranch ?: 'unknown', $canonicalBranch, $targetUpstreamRef);
    }
    $statusOutput = runGitCommand($repoPath, 'git status -sb');
    $commandOutput['Status'] = $statusOutput;
    $statusMessage = 'Fetched latest metadata. Compare local and remote commits below.';
    $statusType = 'info';

    $remoteCommit = safeTrim(runGitCommand($repoPath, 'git rev-parse ' . escapeshellarg($upstreamRef)));
    if (str_starts_with($remoteCommit, 'fatal:')) {
        $remoteCommit = '';
    }

    $metrics = collectSyncMetrics($repoPath, $upstreamRef);
    $workingTreeClean = $metrics['workingTreeClean'];
    $remoteReachable = $metrics['remoteReachable'];
    $aheadCount = $metrics['aheadCount'];
    $behindCount = $metrics['behindCount'];
}

if ($action === 'update' && $isGitRepo) {
    $fetchOutput = runGitCommand($repoPath, 'git fetch --all --prune');
    $currentBranch = safeTrim(runGitCommand($repoPath, 'git rev-parse --abbrev-ref HEAD'));
    if ($currentBranch !== $canonicalBranch) {
        $checkoutOutput = runGitCommand($repoPath, 'git checkout ' . escapeshellarg($canonicalBranch));
        $commandOutput['Branch Switch'] = 'Warning: switched to target branch before pulling.' . "\n" . $checkoutOutput;
        $currentBranch = safeTrim(runGitCommand($repoPath, 'git rev-parse --abbrev-ref HEAD'));
    }
    $pullOutput = runGitCommand($repoPath, 'git pull --ff-only origin ' . escapeshellarg($canonicalBranch));
    $statusOutput = runGitCommand($repoPath, 'git status -sb');
    $commandOutput['Fetch'] = $fetchOutput;
    $commandOutput['Pull'] = $pullOutput;
    $commandOutput['Status'] = $statusOutput;

    $pullLower = strtolower($pullOutput);
    $pullSucceeded = !str_contains($pullLower, 'fatal:') && !str_contains($pullLower, 'error:');

    if ($pullSucceeded) {
        $composeDirectory = __DIR__;
        $restartOutput = runShellCommand('cd ' . escapeshellarg($composeDirectory) . ' && (docker compose restart 2>&1 || docker-compose restart 2>&1)');
        $commandOutput['Restart'] = $restartOutput;
        $statusMessage = 'AmpNM updated from GitHub and Docker services were restarted automatically.';
        $statusType = 'success';
    } else {
        $statusMessage = 'Update failed before restart. Review the logs below for details.';
        $statusType = 'error';
    }

    $currentBranch = safeTrim(runGitCommand($repoPath, 'git rev-parse --abbrev-ref HEAD'));
    $localCommit = safeTrim(runGitCommand($repoPath, 'git rev-parse HEAD'));
    $remoteCommit = safeTrim(runGitCommand($repoPath, 'git rev-parse ' . escapeshellarg($upstreamRef)));
    if (str_starts_with($remoteCommit, 'fatal:')) {
        $remoteCommit = '';
    }

    $metrics = collectSyncMetrics($repoPath, $upstreamRef);
    $workingTreeClean = $metrics['workingTreeClean'];
    $remoteReachable = $metrics['remoteReachable'];
    $aheadCount = $metrics['aheadCount'];
    $behindCount = $metrics['behindCount'];
}

if ($action === 'clone' && $gitAvailable && !$isGitRepo) {
    $cloneTarget = $repoPath;
    $parentDir = dirname($cloneTarget);
    $commandOutput['Validation'] = '';

    if (!is_dir($parentDir) && !ensureDirectory($parentDir)) {
        $statusMessage = 'Failed to create parent directory for the repository path.';
        $statusType = 'error';
    } elseif (file_exists($cloneTarget) && !is_dir($cloneTarget)) {
        $statusMessage = 'Repository path points to a file. Please choose a directory.';
        $statusType = 'error';
    } elseif (!isDirectoryEmpty($cloneTarget) && !is_dir($cloneTarget . DIRECTORY_SEPARATOR . '.git')) {
        $statusMessage = 'Repository path is not empty. Please point to an empty directory or existing Git repo.';
        $statusType = 'error';
    } else {
        ensureDirectory($cloneTarget);
        $cloneCommand = sprintf('git clone --depth 1 %s %s', escapeshellarg($canonicalRepoUrl), escapeshellarg($cloneTarget));
        $cloneOutput = runShellCommand($cloneCommand);
        $commandOutput['Clone'] = $cloneOutput;
        $isGitRepo = is_dir($gitMarker) || is_file($gitMarker);

        if ($isGitRepo) {
            $statusMessage = 'Repository cloned successfully from GitHub.';
            $statusType = 'success';
            $remoteUrl = $canonicalRepoUrl;
            $originConfigured = true;
            $currentBranch = safeTrim(runGitCommand($repoPath, 'git rev-parse --abbrev-ref HEAD'));
            $localCommit = safeTrim(runGitCommand($repoPath, 'git rev-parse HEAD'));
            $upstreamRef = $targetUpstreamRef;
            $remoteCommit = safeTrim(runGitCommand($repoPath, 'git rev-parse ' . escapeshellarg($upstreamRef)));
            if (str_starts_with($remoteCommit, 'fatal:')) {
                $remoteCommit = '';
            }

            $metrics = collectSyncMetrics($repoPath, $upstreamRef);
            $workingTreeClean = $metrics['workingTreeClean'];
            $remoteReachable = $metrics['remoteReachable'];
            $aheadCount = $metrics['aheadCount'];
            $behindCount = $metrics['behindCount'];
        } else {
            $statusMessage = 'Clone failed. Review the output below for details.';
            $statusType = 'error';
        }
    }
}

?>
<main>
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <p class="text-sm text-slate-400 uppercase tracking-wide">Maintenance</p>
                <h1 class="text-3xl font-bold text-white">Docker Update Manager</h1>
                <p class="text-slate-400 mt-1">Sync the AMPNM Docker app with the latest code from <a href="https://github.com/pranto48/ampnm" class="text-cyan-400 hover:underline" target="_blank" rel="noopener noreferrer">github.com/pranto48/ampnm</a>.</p>
            </div>
            <div class="flex items-center gap-2 text-sm text-slate-400 bg-slate-800 border border-slate-700 rounded-lg px-3 py-2">
                <i class="fas fa-shield-alt text-cyan-400"></i>
                <span class="text-slate-200">Admins only</span>
            </div>
        </div>

        <?php if (!$gitAvailable): ?>
            <div class="bg-red-500/10 border border-red-500/40 text-red-300 rounded-lg p-4 mb-6">
                <p class="font-semibold mb-1">Git not found on this container.</p>
                <p class="text-sm">Install Git in the Docker image to enable code syncing (e.g., <code>apt-get update && apt-get install -y git</code>).</p>
            </div>
        <?php elseif (!$isGitRepo): ?>
            <div class="bg-yellow-500/10 border border-yellow-500/40 text-yellow-200 rounded-lg p-4 mb-6">
                <p class="font-semibold mb-1">Repository not detected at <code><?php echo htmlspecialchars($repoPath); ?></code>.</p>
                <p class="text-sm">Make sure the Docker app files include the <code>.git</code> folder or adjust the path below. You can also set <code>AMPNM_REPO_PATH</code> in the container to point directly to the mounted repository (e.g., <code>/var/www/html/ampnm-project</code>) or clone the official repo into that path.</p>
                <?php if (empty($autoDetectedRepoPath) && !empty($autoDetection['attempts'])): ?>
                    <p class="text-xs text-yellow-100 mt-2">
                        Checked automatically: <code><?php echo htmlspecialchars(implode(', ', $autoDetection['attempts'])); ?></code>
                    </p>
                <?php elseif (!empty($autoDetectedRepoPath)): ?>
                    <p class="text-xs text-yellow-100 mt-2">Nearest <code>.git</code> found at <code><?php echo htmlspecialchars($autoDetectedRepoPath); ?></code>.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($statusMessage): ?>
            <div class="mb-6 <?php echo $statusType === 'success' ? 'bg-green-500/10 border-green-500/40 text-green-200' : ($statusType === 'error' ? 'bg-red-500/10 border-red-500/40 text-red-200' : 'bg-slate-700/50 border-slate-600 text-slate-200'); ?> border rounded-lg p-4">
                <p class="font-semibold flex items-center gap-2">
                    <i class="fas fa-info-circle"></i>
                    <span><?php echo htmlspecialchars($statusMessage); ?></span>
                </p>
            </div>
        <?php endif; ?>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-white">Repository Status</h2>
                        <span class="px-3 py-1 rounded-full text-xs uppercase tracking-wide <?php echo $isGitRepo ? 'bg-green-500/20 text-green-300 border border-green-500/30' : 'bg-red-500/20 text-red-300 border border-red-500/30'; ?>">
                            <?php echo $isGitRepo ? 'Connected' : 'Unavailable'; ?>
                        </span>
                    </div>
                    <dl class="grid sm:grid-cols-2 gap-4 text-sm text-slate-300">
                        <div>
                            <dt class="text-slate-400">Repository Path</dt>
                            <dd class="font-mono text-slate-100 mt-1 break-all"><?php echo htmlspecialchars($repoPath); ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Current Branch</dt>
                            <dd class="font-mono text-slate-100 mt-1"><?php echo $currentBranch ?: 'Unknown'; ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Local Commit</dt>
                            <dd class="font-mono text-slate-100 mt-1"><?php echo $localCommit ?: 'n/a'; ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Upstream Ref</dt>
                            <dd class="font-mono text-slate-100 mt-1"><?php echo $upstreamRef ?: htmlspecialchars($targetUpstreamRef); ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Remote Commit</dt>
                            <dd class="font-mono text-slate-100 mt-1"><?php echo $remoteCommit ?: 'Unknown'; ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Remote Origin</dt>
                            <dd class="font-mono text-slate-100 mt-1 break-all"><?php echo $remoteUrl ?: $canonicalRepoUrl; ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Remote Reachability</dt>
                            <dd class="mt-1">
                                <?php if ($remoteReachable === true): ?>
                                    <span class="text-emerald-200">Reachable (ls-remote succeeded)</span>
                                <?php elseif ($remoteReachable === false): ?>
                                    <span class="text-amber-200">Not reachable yet</span>
                                <?php else: ?>
                                    <span class="text-slate-400">n/a</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Sync Target</dt>
                            <dd class="mt-1 font-mono">github.com/pranto48/ampnm.git (<?php echo htmlspecialchars($targetUpstreamRef); ?>)</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Ahead / Behind</dt>
                            <dd class="mt-1">
                                <?php if ($aheadCount !== null && $behindCount !== null): ?>
                                    <span class="text-emerald-200"><?php echo $aheadCount; ?> ahead</span>
                                    <span class="text-slate-500"> / </span>
                                    <span class="text-amber-200"><?php echo $behindCount; ?> behind</span>
                                <?php else: ?>
                                    <span class="text-slate-400">n/a</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Working Tree</dt>
                            <dd class="mt-1">
                                <?php if ($workingTreeClean === true): ?>
                                    <span class="text-emerald-200">Clean</span>
                                <?php elseif ($workingTreeClean === false): ?>
                                    <span class="text-amber-200">Uncommitted changes present</span>
                                <?php else: ?>
                                    <span class="text-slate-400">Unknown</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>
                    <p class="text-xs text-slate-500 mt-3">If commits differ, use "↻ Check for Updates" to compare or "🚀 Update AmpNM" to pull the newest code from <code><?php echo htmlspecialchars($targetUpstreamRef); ?></code> and auto-restart this Docker app.</p>
                </div>

                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                    <h2 class="text-xl font-semibold text-white mb-4">Actions</h2>
                    <div class="grid md:grid-cols-2 gap-4">
                        <form method="POST" class="space-y-3">
                            <input type="hidden" name="action" value="check">
                            <label class="block text-sm text-slate-400">Repository Path</label>
                            <input type="text" name="repo_path" value="<?php echo htmlspecialchars($repoPath); ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500" placeholder="/var/www/html/docker-ampnm">
                            <button type="submit" class="w-full px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg flex items-center justify-center gap-2<?php echo !$isGitRepo ? ' opacity-60 cursor-not-allowed' : ''; ?>" <?php echo !$isGitRepo ? 'disabled aria-disabled="true"' : ''; ?>>
                                <i class="fas fa-sync-alt"></i>
                                <span>↻ Check for Updates</span>
                            </button>
                            <p class="text-xs text-slate-500">Fetches remote metadata so you can compare local commits against <code><?php echo htmlspecialchars($targetUpstreamRef); ?></code> without applying changes.</p>
                        </form>
                        <form method="POST" class="space-y-3">
                            <input type="hidden" name="action" value="update">
                            <label class="block text-sm text-slate-400">Repository Path</label>
                            <input type="text" name="repo_path" value="<?php echo htmlspecialchars($repoPath); ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                            <button type="submit" class="w-full px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg flex items-center justify-center gap-2<?php echo !$isGitRepo ? ' opacity-60 cursor-not-allowed' : ''; ?>" <?php echo !$isGitRepo ? 'disabled aria-disabled="true"' : ''; ?>>
                                <i class="fas fa-cloud-download-alt"></i>
                                <span>🚀 Update AmpNM</span>
                            </button>
                            <p class="text-xs text-slate-500">Runs <code>git fetch --all</code> then <code>git pull --ff-only origin <?php echo htmlspecialchars($canonicalBranch); ?></code> from <code>https://github.com/pranto48/ampnm.git</code>, then automatically restarts this Docker app with <code>docker compose restart</code>.</p>
                        </form>
                        <?php if (!$isGitRepo && $gitAvailable): ?>
                            <form method="POST" class="space-y-3 md:col-span-2">
                                <input type="hidden" name="action" value="clone">
                                <label class="block text-sm text-slate-400">Clone into Path</label>
                                <input type="text" name="repo_path" value="<?php echo htmlspecialchars($repoPath); ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500" placeholder="/var/www/html/ampnm-project">
                                <button type="submit" class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg flex items-center justify-center gap-2">
                                    <i class="fas fa-cloud-download-alt"></i>
                                    <span>Clone from GitHub</span>
                                </button>
                                <p class="text-xs text-slate-500">Creates (if needed) the directory and performs <code>git clone --depth 1</code> from <code><?php echo htmlspecialchars($canonicalRepoUrl); ?></code> so updates can be applied.</p>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($commandOutput)): ?>
                    <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="text-xl font-semibold text-white">Command Logs</h2>
                            <span class="text-xs text-slate-500">Most recent action</span>
                        </div>
                        <div class="space-y-4 text-sm">
                            <?php foreach ($commandOutput as $title => $output): ?>
                                <div>
                                    <p class="text-slate-400 mb-1 font-semibold flex items-center gap-2"><i class="fas fa-terminal text-cyan-400"></i><?php echo htmlspecialchars($title); ?></p>
                                    <pre class="bg-slate-900 border border-slate-700 rounded-lg p-3 text-slate-100 overflow-auto whitespace-pre-wrap text-xs"><?php echo htmlspecialchars($output ?: 'No output'); ?></pre>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="space-y-6">
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                    <h3 class="text-lg font-semibold text-white mb-3">How it works</h3>
                    <ul class="list-disc list-inside space-y-2 text-sm text-slate-300">
                        <li>Targets the Docker app at <code>portal.itsupport.com.bd/docker-ampnm</code> (current container path: <code><?php echo htmlspecialchars($defaultRepoPath); ?></code>). We auto-detect the nearest <code>.git</code> above this folder and use it as the default path.</li>
                        <li>Checks out updates from the official repository: <code>https://github.com/pranto48/ampnm.git</code> on branch <code><?php echo htmlspecialchars($canonicalBranch); ?></code> (<code><?php echo htmlspecialchars($targetUpstreamRef); ?></code>).</li>
                        <li>Uses <span class="font-semibold">fetch</span> to compare and <span class="font-semibold">pull</span> to apply new versions without overwriting local, uncommitted changes.</li>
                    </ul>
                </div>
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                    <h3 class="text-lg font-semibold text-white mb-3">Tips</h3>
                    <ul class="list-disc list-inside space-y-2 text-sm text-slate-300">
                        <li>Ensure the container has outbound internet access to reach GitHub.</li>
                        <li>If <span class="font-semibold">Remote Reachability</span> shows "Not reachable", verify DNS/proxy settings and that <code>github.com</code> is accessible from the container.</li>
                        <li>Commit or back up any local changes before pulling to avoid merge conflicts.</li>
                        <li>If you maintain a fork, change the repository path to your mounted code directory inside the container.</li>
                        <li>If the code was copied without <code>.git</code>, use "Clone from GitHub" to pull a fresh working copy into the desired path.</li>
                    </ul>
                </div>
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                    <h3 class="text-lg font-semibold text-white mb-3">Environment & tooling</h3>
                    <dl class="space-y-3 text-sm text-slate-300">
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-400">Container hostname</dt>
                            <dd class="font-mono text-slate-100"><?php echo htmlspecialchars($containerHostname); ?></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-400">PHP user</dt>
                            <dd class="font-mono text-slate-100"><?php echo htmlspecialchars($phpUser); ?></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-400">Git binary</dt>
                            <dd class="font-mono text-slate-100"><?php echo $gitAvailable ? htmlspecialchars($gitBinary) : 'Not found'; ?></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-400">Node</dt>
                            <dd class="font-mono text-slate-100"><?php echo $nodeVersion !== '' ? htmlspecialchars($nodeVersion) : 'Not installed'; ?></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-400">npm</dt>
                            <dd class="font-mono text-slate-100"><?php echo $npmVersion !== '' ? htmlspecialchars($npmVersion) : 'Not installed'; ?></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-400">pnpm</dt>
                            <dd class="font-mono text-slate-100"><?php echo $pnpmVersion !== '' ? htmlspecialchars($pnpmVersion) : 'Not installed'; ?></dd>
                        </div>
                    </dl>
                    <p class="text-xs text-slate-500 mt-3">Use these details when installing dependencies or troubleshooting why fetch/pull commands might fail in this container.</p>
                </div>
                <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 shadow-lg">
                    <h3 class="text-lg font-semibold text-white mb-3">After updating</h3>
                    <ul class="list-disc list-inside space-y-2 text-sm text-slate-300">
                        <li>Rebuild the frontend bundles from the repo root:<br><code class="block bg-slate-900 border border-slate-700 rounded mt-1 p-2">pnpm install<br>pnpm run build && pnpm run build:server</code></li>
                        <li>Restart the Docker services to pick up new assets:<br><code class="block bg-slate-900 border border-slate-700 rounded mt-1 p-2">docker compose down<br>docker compose up -d --build</code></li>
                        <li>Verify the portal at <code>https://portal.itsupport.com.bd</code> and the Docker app at <code>/docker-ampnm</code> both reflect the latest commit hashes above.</li>
                    </ul>
                    <p class="text-xs text-slate-500 mt-3">Run these commands inside the same container or host where the repository is mounted. Adjust the compose project name or paths if you keep the stack elsewhere.</p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
