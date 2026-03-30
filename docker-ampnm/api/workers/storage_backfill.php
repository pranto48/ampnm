#!/usr/bin/env php
<?php

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/storage_policy.php';

try {
    $pdo = getDbConnection();
    ensureStoragePolicySchema($pdo);
    runStorageRollupTick($pdo);
    echo "storage backfill completed\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'storage backfill failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
