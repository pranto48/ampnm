#!/usr/bin/env php
<?php

require_once __DIR__ . '/../../includes/functions.php';

$interval = (int)(getenv('SCHEDULER_INTERVAL_SECONDS') ?: 30);
if ($interval < 5) {
    $interval = 5;
}

$retentionDays = (int)(getenv('QUEUE_RETENTION_DAYS') ?: 7);
if ($retentionDays < 1) {
    $retentionDays = 1;
}

$heartbeatFile = '/tmp/ampnm-scheduler-heartbeat';

while (true) {
    try {
        $pdo = getDbConnection();

        $stmt1 = $pdo->prepare("DELETE FROM metrics_ingest_queue WHERE status IN ('processed', 'dead_letter') AND processed_at < (NOW() - INTERVAL ? DAY)");
        $stmt1->execute([$retentionDays]);

        $stmt2 = $pdo->prepare("DELETE FROM metrics_ingest_dedup WHERE status = 'done' AND processed_at < (NOW() - INTERVAL ? DAY)");
        $stmt2->execute([$retentionDays]);

        @touch($heartbeatFile);
    } catch (Throwable $e) {
        error_log('scheduler tick failed: ' . $e->getMessage());
    }

    sleep($interval);
}
