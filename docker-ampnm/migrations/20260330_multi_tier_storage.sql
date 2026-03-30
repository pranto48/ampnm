-- Multi-tier storage policy migration
-- Apply manually or via your migration runner.

CREATE TABLE IF NOT EXISTS `host_metrics_hourly_rollup` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `host_metrics_daily_rollup` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `device_status_logs_hourly_rollup` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `device_id` INT(6) UNSIGNED NOT NULL,
  `bucket_start` DATETIME NOT NULL,
  `warning_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `critical_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `offline_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_status_hourly` (`device_id`, `bucket_start`),
  KEY `idx_status_hourly_bucket` (`bucket_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `device_status_logs_daily_rollup` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `device_id` INT(6) UNSIGNED NOT NULL,
  `bucket_date` DATE NOT NULL,
  `warning_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `critical_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `offline_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_status_daily` (`device_id`, `bucket_date`),
  KEY `idx_status_daily_bucket` (`bucket_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `app_settings` (`setting_key`, `setting_value`)
VALUES
  ('metrics_hires_days', '30'),
  ('metrics_hourly_days', '365'),
  ('status_hires_days', '30'),
  ('status_hourly_days', '365')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Time partitioning for high-volume history tables.
-- Keep future partitions extended by your DBA automation.
ALTER TABLE `host_metrics_history`
PARTITION BY RANGE (TO_DAYS(`recorded_at`)) (
  PARTITION p202601 VALUES LESS THAN (TO_DAYS('2026-02-01')),
  PARTITION p202602 VALUES LESS THAN (TO_DAYS('2026-03-01')),
  PARTITION p202603 VALUES LESS THAN (TO_DAYS('2026-04-01')),
  PARTITION pmax VALUES LESS THAN MAXVALUE
);

ALTER TABLE `device_status_logs`
PARTITION BY RANGE (TO_DAYS(`created_at`)) (
  PARTITION p202601 VALUES LESS THAN (TO_DAYS('2026-02-01')),
  PARTITION p202602 VALUES LESS THAN (TO_DAYS('2026-03-01')),
  PARTITION p202603 VALUES LESS THAN (TO_DAYS('2026-04-01')),
  PARTITION pmax VALUES LESS THAN MAXVALUE
);
