<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/telemetry.php';

$pdo = getDbConnection();

header('Content-Type: text/plain; version=0.0.4; charset=utf-8');
echo telemetryPrometheus($pdo);
