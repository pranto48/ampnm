-- Reusable configuration model: host groups, templates, inheritance, overrides, import/export support

CREATE TABLE IF NOT EXISTS host_groups (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_group_name (user_id, name),
    CONSTRAINT fk_host_groups_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS host_group_devices (
    host_group_id INT(10) UNSIGNED NOT NULL,
    device_id INT(6) UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (host_group_id, device_id),
    CONSTRAINT fk_host_group_devices_group FOREIGN KEY (host_group_id) REFERENCES host_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_host_group_devices_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS templates (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_template_name (user_id, name),
    CONSTRAINT fk_templates_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS template_items (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id INT(10) UNSIGNED NOT NULL,
    item_key VARCHAR(80) NOT NULL,
    item_value TEXT NULL,
    is_inheritable BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_template_item_key (template_id, item_key),
    CONSTRAINT fk_template_items_template FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS template_triggers (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id INT(10) UNSIGNED NOT NULL,
    warning_latency_threshold INT(11) NULL,
    warning_packetloss_threshold INT(11) NULL,
    critical_latency_threshold INT(11) NULL,
    critical_packetloss_threshold INT(11) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_template_trigger (template_id),
    CONSTRAINT fk_template_triggers_template FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS group_template_assignments (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    host_group_id INT(10) UNSIGNED NOT NULL,
    template_id INT(10) UNSIGNED NOT NULL,
    priority INT(11) NOT NULL DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_group_template (host_group_id, template_id),
    CONSTRAINT fk_group_template_group FOREIGN KEY (host_group_id) REFERENCES host_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_group_template_template FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS device_template_assignments (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id INT(6) UNSIGNED NOT NULL,
    template_id INT(10) UNSIGNED NOT NULL,
    priority INT(11) NOT NULL DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_device_template (device_id, template_id),
    CONSTRAINT fk_device_template_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    CONSTRAINT fk_device_template_template FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS device_template_overrides (
    device_id INT(6) UNSIGNED PRIMARY KEY,
    warning_latency_threshold INT(11) NULL,
    warning_packetloss_threshold INT(11) NULL,
    critical_latency_threshold INT(11) NULL,
    critical_packetloss_threshold INT(11) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_device_template_overrides_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backfill per-device overrides from existing device-level threshold values so behavior is preserved.
INSERT INTO device_template_overrides (
    device_id,
    warning_latency_threshold,
    warning_packetloss_threshold,
    critical_latency_threshold,
    critical_packetloss_threshold
)
SELECT
    d.id,
    d.warning_latency_threshold,
    d.warning_packetloss_threshold,
    d.critical_latency_threshold,
    d.critical_packetloss_threshold
FROM devices d
LEFT JOIN device_template_overrides dto ON dto.device_id = d.id
WHERE dto.device_id IS NULL;
