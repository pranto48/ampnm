<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
require_once 'includes/auth_check.php';
require_once 'header.php';

$pdo = getDbConnection();

// Filters
$filter_agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
$filter_severity = isset($_GET['severity']) ? trim($_GET['severity']) : '';
$filter_limit    = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
if ($filter_limit < 10)  $filter_limit = 10;
if ($filter_limit > 500) $filter_limit = 500;

// Build query
$where = [];
$params = [];

if ($filter_agent_id > 0) {
    $where[] = "ae.agent_device_id = ?";
    $params[] = $filter_agent_id;
}
if ($filter_severity !== '') {
    $where[] = "ae.severity = ?";
    $params[] = $filter_severity;
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

$stmt = $pdo->prepare("
    SELECT ae.*, ad.hostname, ad.agent_name
    FROM agent_events ae
    JOIN agent_devices ad ON ae.agent_device_id = ad.id
    {$where_sql}
    ORDER BY ae.created_at DESC
    LIMIT {$filter_limit}
");
$stmt->execute($params);
$events = $stmt->fetchAll();

// Devices for filter dropdown
$devices_stmt = $pdo->query("SELECT id, hostname, agent_name FROM agent_devices ORDER BY hostname ASC");
$devices_list = $devices_stmt->fetchAll();

$sev_colors = [
    'info'     => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20',
    'warning'  => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
    'error'    => 'bg-red-500/10 text-red-400 border-red-500/20',
    'critical' => 'bg-red-700/20 text-red-300 border-red-700/30',
];
$sev_icons = [
    'info'     => 'fa-info-circle',
    'warning'  => 'fa-exclamation-triangle',
    'error'    => 'fa-times-circle',
    'critical' => 'fa-skull-crossbones',
];
?>

<div class="container mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white mb-1">
                <i class="fas fa-file-lines text-cyan-400 mr-2"></i>Agent Logs
            </h1>
            <p class="text-slate-400 text-sm">Audit trail of events, health warnings, authentication issues, and client messages.</p>
        </div>
        <a href="agent_devices.php" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
            <i class="fas fa-desktop"></i>All Agents
        </a>
    </div>

    <!-- Filter Bar -->
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4 mb-6">
        <form method="GET" action="agent_logs.php" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-slate-400 font-semibold uppercase tracking-wider mb-1">Filter by Agent</label>
                <select name="agent_id" class="bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-cyan-500 min-w-[160px]">
                    <option value="0" <?= $filter_agent_id === 0 ? 'selected' : '' ?>>All Agents</option>
                    <?php foreach ($devices_list as $dev): ?>
                        <option value="<?= $dev['id'] ?>" <?= $filter_agent_id === (int)$dev['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dev['agent_name'] ?: $dev['hostname']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-400 font-semibold uppercase tracking-wider mb-1">Severity</label>
                <select name="severity" class="bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-cyan-500">
                    <option value="" <?= $filter_severity === '' ? 'selected' : '' ?>>All Severities</option>
                    <option value="info"     <?= $filter_severity === 'info'     ? 'selected' : '' ?>>Info</option>
                    <option value="warning"  <?= $filter_severity === 'warning'  ? 'selected' : '' ?>>Warning</option>
                    <option value="error"    <?= $filter_severity === 'error'    ? 'selected' : '' ?>>Error</option>
                    <option value="critical" <?= $filter_severity === 'critical' ? 'selected' : '' ?>>Critical</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-400 font-semibold uppercase tracking-wider mb-1">Show</label>
                <select name="limit" class="bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-cyan-500">
                    <?php foreach ([50, 100, 200, 500] as $l): ?>
                        <option value="<?= $l ?>" <?= $filter_limit === $l ? 'selected' : '' ?>><?= $l ?> rows</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                <i class="fas fa-filter"></i>Apply
            </button>
            <?php if ($filter_agent_id || $filter_severity): ?>
                <a href="agent_logs.php" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-times mr-1"></i>Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Counts row -->
    <div class="flex items-center gap-4 text-sm text-slate-400 mb-4">
        <span><i class="fas fa-list mr-1"></i><?= count($events) ?> events shown</span>
    </div>

    <!-- Log Table -->
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
        <?php if (empty($events)): ?>
            <div class="p-10 text-center text-slate-500">
                <i class="fas fa-file-lines text-5xl mb-4 block opacity-20"></i>
                <p class="text-sm">No events match the current filter criteria.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-slate-300">
                    <thead>
                        <tr class="bg-slate-900/60 border-b border-slate-700 text-slate-400 text-xs uppercase tracking-wider">
                            <th class="px-4 py-3 text-left w-28">Severity</th>
                            <th class="px-4 py-3 text-left">Agent</th>
                            <th class="px-4 py-3 text-left">Event Type</th>
                            <th class="px-4 py-3 text-left">Message</th>
                            <th class="px-4 py-3 text-left w-36">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php foreach ($events as $e):
                            $sev = $e['severity'] ?? 'info';
                            $sev_c = $sev_colors[$sev] ?? $sev_colors['info'];
                            $sev_i = $sev_icons[$sev] ?? $sev_icons['info'];
                        ?>
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-bold border <?= $sev_c ?>">
                                        <i class="fas <?= $sev_i ?> text-[10px]"></i>
                                        <?= strtoupper($sev) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="agent_device_view.php?id=<?= $e['agent_device_id'] ?>" class="text-cyan-400 hover:text-cyan-300 font-medium">
                                        <?= htmlspecialchars($e['agent_name'] ?: $e['hostname']) ?>
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-slate-400 text-xs font-mono"><?= htmlspecialchars($e['event_type']) ?></td>
                                <td class="px-4 py-3 text-slate-200 max-w-lg">
                                    <span class="line-clamp-2" title="<?= htmlspecialchars($e['message']) ?>">
                                        <?= htmlspecialchars($e['message']) ?>
                                    </span>
                                    <?php if ($e['metadata_json']): ?>
                                        <details class="mt-1">
                                            <summary class="text-xs text-slate-500 cursor-pointer select-none">Show metadata</summary>
                                            <pre class="text-xs bg-slate-900 p-2 rounded mt-1 text-slate-400 overflow-x-auto max-w-md whitespace-pre-wrap"><?= htmlspecialchars(json_encode(json_decode($e['metadata_json'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                                        </details>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-slate-500 text-xs whitespace-nowrap">
                                    <?= date('M d, H:i:s', strtotime($e['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (file_exists('footer.php')) require_once 'footer.php'; else echo '</body></html>'; ?>
