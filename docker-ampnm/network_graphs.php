<?php
require_once 'includes/auth_check.php';
require_once 'config.php';
include 'header.php';

$user_role = $_SESSION['user_role'] ?? 'viewer';
$is_admin = $user_role === 'admin';
$user_id = $_SESSION['user_id'] ?? null;

$pdo = getDbConnection();
$errors = [];
$success_message = '';

function sanitizeGraphInput($value) {
    return trim(strip_tags($value ?? ''));
}

function buildGraphUrlWithRange(string $baseUrl, string $paramName, string $range): string {
    $parts = parse_url($baseUrl);

    // Preserve existing query parameters
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query[$paramName] = $range;

    $parts['query'] = http_build_query($query);

    $url = '';
    if (!empty($parts['scheme'])) {
        $url .= $parts['scheme'] . '://';
    }

    if (!empty($parts['user'])) {
        $url .= $parts['user'];
        if (!empty($parts['pass'])) {
            $url .= ':' . $parts['pass'];
        }
        $url .= '@';
    }

    if (!empty($parts['host'])) {
        $url .= $parts['host'];
    }

    if (!empty($parts['port'])) {
        $url .= ':' . $parts['port'];
    }

    if (!empty($parts['path'])) {
        $url .= $parts['path'];
    }

    $url .= !empty($parts['query']) ? '?' . $parts['query'] : '';

    if (!empty($parts['fragment'])) {
        $url .= '#' . $parts['fragment'];
    }

    return $url;
}

if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeGraphInput($_POST['name'] ?? '');
    $category = sanitizeGraphInput($_POST['category'] ?? 'General');
    $base_url = sanitizeGraphInput($_POST['base_url'] ?? '');
    $param_name = sanitizeGraphInput($_POST['param_name'] ?? 'range');

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if ($base_url === '' || !filter_var($base_url, FILTER_VALIDATE_URL)) {
        $errors[] = 'A valid graph URL is required.';
    }
    if ($param_name === '') {
        $param_name = 'range';
    }
    if ($user_id === null) {
        $errors[] = 'Unable to determine the current user. Please sign in again.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO network_graphs (user_id, name, category, base_url, param_name) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$user_id, $name, $category, $base_url, $param_name]);
        $success_message = 'Network graph added successfully.';
    }
}

$stmt = $pdo->query('SELECT id, user_id, name, category, base_url, param_name, created_at FROM network_graphs ORDER BY category ASC, name ASC');
$graphs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grouped_graphs = [];
foreach ($graphs as $graph) {
    $category_key = $graph['category'] ?: 'Uncategorized';
    if (!isset($grouped_graphs[$category_key])) {
        $grouped_graphs[$category_key] = [];
    }
    $grouped_graphs[$category_key][] = $graph;
}
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-white">Network Graphs</h1>
                <p class="text-slate-400 mt-1">Attach Mikrotik and other device graph links and flip between daily, weekly, monthly, and yearly views.</p>
            </div>
            <div class="bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 text-sm text-slate-300 shadow-lg max-w-md">
                <p class="flex items-start gap-2"><i class="fas fa-lightbulb text-amber-400 mt-0.5"></i><span>Use the full graph link from your device (e.g. <code>http://103.30.189.74:14000/graphs/iface/ether8%2DDFLL%2DRadio/</code>). The range parameter defaults to <code>range</code>, but you can match your router&rsquo;s query key.</span></p>
            </div>
        </div>

        <?php if ($is_admin): ?>
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 shadow-xl mb-8">
                <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-plus-circle text-cyan-400"></i>
                    Add Network Graph
                </h2>
                <?php if (!empty($errors)): ?>
                    <div class="mb-4 bg-red-900/50 border border-red-700 text-red-200 rounded-lg p-4">
                        <ul class="list-disc list-inside space-y-1">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if ($success_message): ?>
                    <div class="mb-4 bg-green-900/40 border border-green-700 text-green-200 rounded-lg p-3 flex items-center gap-2">
                        <i class="fas fa-check-circle"></i>
                        <span><?= htmlspecialchars($success_message) ?></span>
                    </div>
                <?php endif; ?>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-slate-400 mb-1" for="name">Graph Name</label>
                        <input type="text" id="name" name="name" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-cyan-500" placeholder="Mikrotik Radio Graph">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-400 mb-1" for="category">Category</label>
                        <input type="text" id="category" name="category" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-cyan-500" placeholder="Routers">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm text-slate-400 mb-1" for="base_url">Graph URL</label>
                        <input type="url" id="base_url" name="base_url" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-cyan-500" placeholder="http://103.30.189.74:14000/graphs/iface/ether8%2DDFLL%2DRadio/">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-400 mb-1" for="param_name">Range Parameter</label>
                        <input type="text" id="param_name" name="param_name" value="range" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:ring-2 focus:ring-cyan-500" placeholder="range">
                        <p class="text-xs text-slate-500 mt-1">Query key appended for Daily/Weekly/Monthly/Yearly links.</p>
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition"><i class="fas fa-save mr-2"></i>Save Graph</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if (empty($graphs)): ?>
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-8 text-center text-slate-400">
                <i class="fas fa-chart-line text-5xl text-slate-600 mb-4"></i>
                <p class="text-lg">No network graphs have been added yet.</p>
                <?php if ($is_admin): ?>
                    <p class="text-slate-500 mt-2">Use the form above to save your first Mikrotik graph link.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($grouped_graphs as $category_name => $category_graphs): ?>
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xl font-semibold text-white flex items-center gap-2"><i class="fas fa-folder-open text-cyan-400"></i><?= htmlspecialchars($category_name) ?></h3>
                        <span class="text-xs bg-slate-800 border border-slate-700 text-slate-300 rounded-full px-3 py-1"><?= count($category_graphs) ?> graph<?= count($category_graphs) !== 1 ? 's' : '' ?></span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php foreach ($category_graphs as $graph):
                            $daily_url = buildGraphUrlWithRange($graph['base_url'], $graph['param_name'], 'daily');
                            $weekly_url = buildGraphUrlWithRange($graph['base_url'], $graph['param_name'], 'weekly');
                            $monthly_url = buildGraphUrlWithRange($graph['base_url'], $graph['param_name'], 'monthly');
                            $yearly_url = buildGraphUrlWithRange($graph['base_url'], $graph['param_name'], 'yearly');
                        ?>
                        <div class="graph-card bg-slate-800 border border-slate-700 rounded-xl p-4 shadow-lg flex flex-col" data-daily-url="<?= htmlspecialchars($daily_url) ?>" data-weekly-url="<?= htmlspecialchars($weekly_url) ?>" data-monthly-url="<?= htmlspecialchars($monthly_url) ?>" data-yearly-url="<?= htmlspecialchars($yearly_url) ?>">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h4 class="text-lg font-semibold text-white flex items-center gap-2"><i class="fas fa-chart-area text-emerald-400"></i><?= htmlspecialchars($graph['name']) ?></h4>
                                    <p class="text-xs text-slate-500">Added on <?= date('M j, Y', strtotime($graph['created_at'])) ?></p>
                                </div>
                                <span class="range-pill text-xs bg-slate-700 text-slate-200 px-3 py-1 rounded-full">Daily</span>
                            </div>
                            <div class="flex flex-wrap gap-2 mb-3">
                                <button class="range-button nav-link text-xs px-3 py-1" data-range="daily"><i class="fas fa-sun mr-1"></i>Daily</button>
                                <button class="range-button nav-link text-xs px-3 py-1" data-range="weekly"><i class="fas fa-calendar-week mr-1"></i>Weekly</button>
                                <button class="range-button nav-link text-xs px-3 py-1" data-range="monthly"><i class="fas fa-calendar-alt mr-1"></i>Monthly</button>
                                <button class="range-button nav-link text-xs px-3 py-1" data-range="yearly"><i class="fas fa-calendar mr-1"></i>Yearly</button>
                            </div>
                            <div class="flex-1 bg-slate-900 border border-slate-700 rounded-lg overflow-hidden">
                                <iframe class="graph-frame w-full h-48" src="<?= htmlspecialchars($daily_url) ?>" loading="lazy"></iframe>
                            </div>
                            <div class="mt-3 flex flex-col gap-2 text-sm text-slate-300">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="inline-flex items-center gap-2 bg-slate-900 border border-slate-700 rounded-md px-3 py-1">
                                        <i class="fas fa-arrow-down text-emerald-400"></i>
                                        <span class="text-slate-400">Current In:</span>
                                        <span data-current-in class="font-semibold text-emerald-300">—</span>
                                    </span>
                                    <span class="inline-flex items-center gap-2 bg-slate-900 border border-slate-700 rounded-md px-3 py-1">
                                        <i class="fas fa-arrow-up text-cyan-400"></i>
                                        <span class="text-slate-400">Current Out:</span>
                                        <span data-current-out class="font-semibold text-cyan-300">—</span>
                                    </span>
                                    <button class="refresh-graph-stats text-xs px-3 py-1 bg-slate-700 text-white rounded-md hover:bg-slate-600 transition">
                                        <i class="fas fa-sync-alt mr-1"></i>Refresh
                                    </button>
                                    <span class="graph-stats-status text-xs text-slate-500"></span>
                                </div>
                                <div class="text-right">
                                    <a class="graph-open-link text-cyan-400 hover:text-cyan-300 text-sm" href="<?= htmlspecialchars($daily_url) ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-external-link-alt mr-1"></i>Open graph</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>
