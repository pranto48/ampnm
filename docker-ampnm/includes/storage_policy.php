<?php
require_once __DIR__ . '/../config.php';

function storagePolicyDefaults(): array {
    return [
        'metrics_hires_days' => 30,
        'metrics_hourly_days' => 365,
        'status_hires_days' => 30,
        'status_hourly_days' => 365,
    ];
}

function getStoragePolicySettings(): array {
    $defaults = storagePolicyDefaults();
    $resolved = [];
    foreach ($defaults as $key => $default) {
        $raw = getAppSetting($key);
        $value = is_numeric($raw) ? (int)$raw : $default;
        $resolved[$key] = max(1, $value);
    }
    return $resolved;
}

function saveStoragePolicySettings(array $input): array {
    $defaults = storagePolicyDefaults();
    $saved = [];
    foreach ($defaults as $key => $default) {
        $value = isset($input[$key]) ? (int)$input[$key] : $default;
        $value = max(1, $value);
        updateAppSetting($key, (string)$value);
        $saved[$key] = $value;
    }
    return $saved;
}

function ensureStoragePolicySchema(PDO $pdo): void {
    $ddl = [
        "CREATE TABLE IF NOT EXISTS `host_metrics_hourly_rollup` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `host_ip` VARCHAR(45) NULL,
            `host_name` VARCHAR(255) NULL,
            `bucket_start` DATETIME NOT NULL,
            `samples` INT UNSIGNED NOT NULL DEFAULT 0,
            `cpu_avg` DECIMAL(6,2) NULL,
            `memory_avg` DECIMAL(6,2) NULL,
            `disk_avg` DECIMAL(6,2) NULL,
            `gpu_avg` DECIMAL(6,2) NULL,
            `network_in_avg` DECIMAL(14,4) NULL,
            `network_out_avg` DECIMAL(14,4) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_host_hourly` (`host_ip`, `bucket_start`),
            KEY `idx_host_hourly_bucket` (`bucket_start`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS `host_metrics_daily_rollup` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `host_ip` VARCHAR(45) NULL,
            `host_name` VARCHAR(255) NULL,
            `bucket_date` DATE NOT NULL,
            `samples` INT UNSIGNED NOT NULL DEFAULT 0,
            `cpu_avg` DECIMAL(6,2) NULL,
            `memory_avg` DECIMAL(6,2) NULL,
            `disk_avg` DECIMAL(6,2) NULL,
            `gpu_avg` DECIMAL(6,2) NULL,
            `network_in_avg` DECIMAL(14,4) NULL,
            `network_out_avg` DECIMAL(14,4) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_host_daily` (`host_ip`, `bucket_date`),
            KEY `idx_host_daily_bucket` (`bucket_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS `device_status_logs_hourly_rollup` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `device_id` INT(6) UNSIGNED NOT NULL,
            `bucket_start` DATETIME NOT NULL,
            `warning_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `critical_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `offline_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_status_hourly` (`device_id`, `bucket_start`),
            KEY `idx_status_hourly_bucket` (`bucket_start`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS `device_status_logs_daily_rollup` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `device_id` INT(6) UNSIGNED NOT NULL,
            `bucket_date` DATE NOT NULL,
            `warning_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `critical_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `offline_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_status_daily` (`device_id`, `bucket_date`),
            KEY `idx_status_daily_bucket` (`bucket_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($ddl as $sql) {
        $pdo->exec($sql);
    }
}

function runStorageRollupTick(PDO $pdo): void {
    ensureStoragePolicySchema($pdo);
    $policy = getStoragePolicySettings();

    $pdo->exec("\n        INSERT INTO host_metrics_hourly_rollup (
            host_ip, host_name, bucket_start, samples,
            cpu_avg, memory_avg, disk_avg, gpu_avg,
            network_in_avg, network_out_avg
        )
        SELECT
            COALESCE(host_ip, ip_address) AS host_ip,
            COALESCE(host_name, hostname) AS host_name,
            DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00:00') AS bucket_start,
            COUNT(*) AS samples,
            AVG(COALESCE(cpu_percent, cpu_usage)) AS cpu_avg,
            AVG(COALESCE(memory_percent, memory_usage)) AS memory_avg,
            AVG(COALESCE(disk_percent, disk_usage)) AS disk_avg,
            AVG(COALESCE(gpu_percent, gpu_usage)) AS gpu_avg,
            AVG(COALESCE(network_in_mbps, network_in)) AS network_in_avg,
            AVG(COALESCE(network_out_mbps, network_out)) AS network_out_avg
        FROM host_metrics_history
        WHERE recorded_at >= (NOW() - INTERVAL 14 DAY)
        GROUP BY COALESCE(host_ip, ip_address), COALESCE(host_name, hostname), DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00:00')
        ON DUPLICATE KEY UPDATE
            host_name = VALUES(host_name),
            samples = VALUES(samples),
            cpu_avg = VALUES(cpu_avg),
            memory_avg = VALUES(memory_avg),
            disk_avg = VALUES(disk_avg),
            gpu_avg = VALUES(gpu_avg),
            network_in_avg = VALUES(network_in_avg),
            network_out_avg = VALUES(network_out_avg)
    ");

    $pdo->exec("\n        INSERT INTO host_metrics_daily_rollup (
            host_ip, host_name, bucket_date, samples,
            cpu_avg, memory_avg, disk_avg, gpu_avg,
            network_in_avg, network_out_avg
        )
        SELECT
            COALESCE(host_ip, ip_address) AS host_ip,
            COALESCE(host_name, hostname) AS host_name,
            DATE(recorded_at) AS bucket_date,
            COUNT(*) AS samples,
            AVG(COALESCE(cpu_percent, cpu_usage)) AS cpu_avg,
            AVG(COALESCE(memory_percent, memory_usage)) AS memory_avg,
            AVG(COALESCE(disk_percent, disk_usage)) AS disk_avg,
            AVG(COALESCE(gpu_percent, gpu_usage)) AS gpu_avg,
            AVG(COALESCE(network_in_mbps, network_in)) AS network_in_avg,
            AVG(COALESCE(network_out_mbps, network_out)) AS network_out_avg
        FROM host_metrics_history
        WHERE recorded_at >= (NOW() - INTERVAL 400 DAY)
        GROUP BY COALESCE(host_ip, ip_address), COALESCE(host_name, hostname), DATE(recorded_at)
        ON DUPLICATE KEY UPDATE
            host_name = VALUES(host_name),
            samples = VALUES(samples),
            cpu_avg = VALUES(cpu_avg),
            memory_avg = VALUES(memory_avg),
            disk_avg = VALUES(disk_avg),
            gpu_avg = VALUES(gpu_avg),
            network_in_avg = VALUES(network_in_avg),
            network_out_avg = VALUES(network_out_avg)
    ");

    $pdo->exec("\n        INSERT INTO device_status_logs_hourly_rollup (
            device_id, bucket_start, warning_count, critical_count, offline_count
        )
        SELECT
            device_id,
            DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') AS bucket_start,
            SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) AS warning_count,
            SUM(CASE WHEN status = 'critical' THEN 1 ELSE 0 END) AS critical_count,
            SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) AS offline_count
        FROM device_status_logs
        WHERE created_at >= (NOW() - INTERVAL 14 DAY)
        GROUP BY device_id, DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')
        ON DUPLICATE KEY UPDATE
            warning_count = VALUES(warning_count),
            critical_count = VALUES(critical_count),
            offline_count = VALUES(offline_count)
    ");

    $pdo->exec("\n        INSERT INTO device_status_logs_daily_rollup (
            device_id, bucket_date, warning_count, critical_count, offline_count
        )
        SELECT
            device_id,
            DATE(created_at) AS bucket_date,
            SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) AS warning_count,
            SUM(CASE WHEN status = 'critical' THEN 1 ELSE 0 END) AS critical_count,
            SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) AS offline_count
        FROM device_status_logs
        WHERE created_at >= (NOW() - INTERVAL 400 DAY)
        GROUP BY device_id, DATE(created_at)
        ON DUPLICATE KEY UPDATE
            warning_count = VALUES(warning_count),
            critical_count = VALUES(critical_count),
            offline_count = VALUES(offline_count)
    ");

    $stmt = $pdo->prepare("DELETE FROM host_metrics_history WHERE recorded_at < (NOW() - INTERVAL ? DAY)");
    $stmt->execute([$policy['metrics_hires_days']]);

    $stmt = $pdo->prepare("DELETE FROM device_status_logs WHERE created_at < (NOW() - INTERVAL ? DAY)");
    $stmt->execute([$policy['status_hires_days']]);

    $stmt = $pdo->prepare("DELETE FROM host_metrics_hourly_rollup WHERE bucket_start < (NOW() - INTERVAL ? DAY)");
    $stmt->execute([$policy['metrics_hourly_days']]);

    $stmt = $pdo->prepare("DELETE FROM device_status_logs_hourly_rollup WHERE bucket_start < (NOW() - INTERVAL ? DAY)");
    $stmt->execute([$policy['status_hourly_days']]);

    $pdo->exec("DELETE FROM host_metrics_daily_rollup WHERE bucket_date < (CURDATE() - INTERVAL 5 YEAR)");
    $pdo->exec("DELETE FROM device_status_logs_daily_rollup WHERE bucket_date < (CURDATE() - INTERVAL 5 YEAR)");
}
