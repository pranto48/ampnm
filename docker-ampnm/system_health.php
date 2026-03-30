<?php
require_once 'includes/auth_check.php';
require_once __DIR__ . '/includes/telemetry.php';

$pdo = getDbConnection();
$evaluated = telemetryEvaluateAlerts($pdo, telemetryCorrelationId());
$metrics = $evaluated['health']['metrics'];
$alerts = $evaluated['active_alerts'];

function sloPanelClass(string $state): string {
    return match($state) {
        'green' => 'border-green-500/40 bg-green-500/10 text-green-300',
        'yellow' => 'border-yellow-500/40 bg-yellow-500/10 text-yellow-300',
        default => 'border-red-500/40 bg-red-500/10 text-red-300',
    };
}

$slo = [
    ['label' => 'Queue Depth', 'value' => $metrics['queue_depth'], 'state' => telemetrySloStatus((float)$metrics['queue_depth'], 20, 100)],
    ['label' => 'Processing Latency (ms)', 'value' => $metrics['processing_latency_ms'], 'state' => telemetrySloStatus((float)$metrics['processing_latency_ms'], 250, 750)],
    ['label' => 'Failed Jobs / Hour', 'value' => $metrics['failed_jobs_last_hour'], 'state' => telemetrySloStatus((float)$metrics['failed_jobs_last_hour'], 0, 5)],
    ['label' => 'DB Latency (ms)', 'value' => $metrics['db_query_latency_ms'], 'state' => telemetrySloStatus((float)$metrics['db_query_latency_ms'], 120, 250)],
    ['label' => 'Alert Throughput / Min', 'value' => $metrics['alert_throughput_per_min'], 'state' => telemetrySloStatus((float)$metrics['alert_throughput_per_min'], 2, 6)],
];

include 'header.php';
?>
<main class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-white">System Health</h1>
        <a href="metrics" class="text-cyan-400 hover:text-cyan-300 text-sm" target="_blank" rel="noreferrer"><i class="fas fa-chart-line mr-1"></i>Prometheus /metrics</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3 mb-8">
        <?php foreach ($slo as $panel): ?>
        <div class="rounded-lg border p-4 <?= sloPanelClass($panel['state']) ?>">
            <div class="text-xs uppercase tracking-wide opacity-80"><?= htmlspecialchars($panel['label']) ?></div>
            <div class="text-2xl font-semibold mt-2"><?= htmlspecialchars((string)$panel['value']) ?></div>
            <div class="text-xs mt-2 font-semibold"><?= strtoupper($panel['state']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="bg-slate-800 border border-slate-700 rounded-lg p-5">
        <h2 class="text-xl text-white font-semibold mb-3">Active Internal Alerts</h2>
        <?php if (empty($alerts)): ?>
            <p class="text-slate-400 text-sm">No active alerts.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($alerts as $alert): ?>
                    <div class="rounded border border-slate-700 bg-slate-900/60 p-3">
                        <div class="text-sm text-white font-semibold"><?= htmlspecialchars($alert['alert_type']) ?> <span class="text-xs text-slate-400">(<?= htmlspecialchars($alert['severity']) ?>)</span></div>
                        <div class="text-xs text-slate-300 mt-1"><?= htmlspecialchars($alert['message']) ?></div>
                        <div class="text-xs text-slate-500 mt-1"><?= htmlspecialchars((string)$alert['triggered_at']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php include 'footer.php'; ?>
