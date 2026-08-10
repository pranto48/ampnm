<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 */
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
include 'header.php';

$user_role = $_SESSION['user_role'] ?? 'viewer';
if ($user_role !== 'admin') {
    echo "<div class='container mx-auto px-4 py-8 text-center text-red-400 font-semibold'>Access Denied. Administrator privileges required.</div>";
    include 'footer.php';
    exit;
}

$search = trim($_GET['search'] ?? '');
$action_filter = trim($_GET['action_filter'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(username LIKE ? OR action LIKE ? OR entity_type LIKE ? OR details LIKE ? OR ip_address LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($action_filter)) {
    $where[] = "action = ?";
    $params[] = $action_filter;
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM `audit_logs` $whereClause");
$countStmt->execute($params);
$total_records = $countStmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch logs
$stmt = $pdo->prepare("SELECT * FROM `audit_logs` $whereClause ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO_FETCH_ASSOC);

// Distinct actions for filter
$actionTypes = $pdo->query("SELECT DISTINCT action FROM `audit_logs` ORDER BY action ASC")->fetchAll(PDO_FETCH_COLUMN);
?>

<main class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-shield-alt text-cyan-400"></i> Audit Logs & Activity History
            </h1>
            <p class="text-sm text-slate-400 mt-1">Track system logins, security events, map modifications, and device actions.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 bg-cyan-950/80 text-cyan-300 border border-cyan-700/80 rounded-full text-xs font-semibold">
                Total Logs: <?= number_format($total_records) ?>
            </span>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-4 mb-6 shadow-xl">
        <form method="GET" action="audit_logs.php" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-slate-400 mb-1">Search Logs</label>
                <div class="relative">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search user, action, IP, or details..." class="w-full bg-slate-900 border border-slate-600 rounded-lg pl-9 pr-3 py-2 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-cyan-500">
                    <i class="fas fa-search absolute left-3 top-2.5 text-slate-500"></i>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1">Action Type</label>
                <select name="action_filter" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-cyan-500">
                    <option value="">All Actions</option>
                    <?php foreach ($actionTypes as $action): ?>
                        <option value="<?= htmlspecialchars($action) ?>" <?= $action_filter === $action ? 'selected' : '' ?>><?= htmlspecialchars(ucwords(str_replace('_', ' ', $action))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-cyan-600 hover:bg-cyan-500 text-white font-semibold py-2 px-4 rounded-lg text-sm transition flex items-center justify-center gap-2">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="audit_logs.php" class="bg-slate-700 hover:bg-slate-600 text-slate-300 font-medium py-2 px-3 rounded-lg text-sm transition" title="Reset Filters">
                    <i class="fas fa-undo"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Audit Log Table -->
    <div class="bg-slate-800 border border-slate-700 rounded-xl shadow-2xl overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-slate-900/80 text-xs uppercase font-semibold text-slate-400 border-b border-slate-700">
                    <tr>
                        <th class="py-3.5 px-4">Timestamp</th>
                        <th class="py-3.5 px-4">User</th>
                        <th class="py-3.5 px-4">Action</th>
                        <th class="py-3.5 px-4">Entity</th>
                        <th class="py-3.5 px-4">IP Address</th>
                        <th class="py-3.5 px-4">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/60">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                <i class="fas fa-info-circle text-2xl text-slate-500 mb-2 block"></i>
                                No audit log records found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-slate-700/40 transition">
                                <td class="py-3 px-4 text-xs font-mono text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($log['created_at']) ?>
                                </td>
                                <td class="py-3 px-4 font-medium text-white whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-user-circle text-cyan-400"></i>
                                        <span><?= htmlspecialchars($log['username']) ?></span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-900 border border-slate-700 text-cyan-300">
                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $log['action']))) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-xs font-mono text-slate-300 whitespace-nowrap">
                                    <?= htmlspecialchars(strtoupper($log['entity_type'])) ?> <?= $log['entity_id'] ? '#' . intval($log['entity_id']) : '' ?>
                                </td>
                                <td class="py-3 px-4 text-xs font-mono text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?>
                                </td>
                                <td class="py-3 px-4 text-xs text-slate-300 max-w-md truncate" title="<?= htmlspecialchars($log['details']) ?>">
                                    <?= htmlspecialchars($log['details'] ?: '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="flex items-center justify-between">
            <span class="text-xs text-slate-400">Showing page <?= $page ?> of <?= $total_pages ?></span>
            <div class="flex items-center gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&action_filter=<?= urlencode($action_filter) ?>" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded border border-slate-700 text-xs font-medium">Previous</a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&action_filter=<?= urlencode($action_filter) ?>" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded border border-slate-700 text-xs font-medium">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>
