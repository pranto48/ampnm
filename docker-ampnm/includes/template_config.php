<?php

function getDeviceEffectiveThresholds(PDO $pdo, int $deviceId): array {
    $base = [
        'warning_latency_threshold' => null,
        'warning_packetloss_threshold' => null,
        'critical_latency_threshold' => null,
        'critical_packetloss_threshold' => null,
    ];

    $sql = "
        SELECT
            t.id AS template_id,
            tt.warning_latency_threshold,
            tt.warning_packetloss_threshold,
            tt.critical_latency_threshold,
            tt.critical_packetloss_threshold,
            1 AS layer_rank,
            dta.priority AS layer_priority
        FROM device_template_assignments dta
        INNER JOIN templates t ON t.id = dta.template_id AND t.enabled = TRUE
        LEFT JOIN template_triggers tt ON tt.template_id = t.id
        WHERE dta.device_id = ?

        UNION ALL

        SELECT
            t.id AS template_id,
            tt.warning_latency_threshold,
            tt.warning_packetloss_threshold,
            tt.critical_latency_threshold,
            tt.critical_packetloss_threshold,
            0 AS layer_rank,
            gta.priority AS layer_priority
        FROM host_group_devices hgd
        INNER JOIN group_template_assignments gta ON gta.host_group_id = hgd.host_group_id
        INNER JOIN templates t ON t.id = gta.template_id AND t.enabled = TRUE
        LEFT JOIN template_triggers tt ON tt.template_id = t.id
        WHERE hgd.device_id = ?
        ORDER BY layer_rank ASC, layer_priority ASC, template_id ASC
    ";

    $stmt = $pdo->prepare($sql);
    try {
        $stmt->execute([$deviceId, $deviceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return $base;
    }

    foreach ($rows as $row) {
        foreach ($base as $key => $current) {
            if ($row[$key] !== null) {
                $base[$key] = is_numeric($row[$key]) ? (int)$row[$key] : $row[$key];
            }
        }
    }

    try {
        $stmtOverride = $pdo->prepare('SELECT warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold FROM device_template_overrides WHERE device_id = ?');
        $stmtOverride->execute([$deviceId]);
        $override = $stmtOverride->fetch(PDO::FETCH_ASSOC);

        if ($override) {
            foreach ($base as $key => $current) {
                if ($override[$key] !== null) {
                    $base[$key] = is_numeric($override[$key]) ? (int)$override[$key] : $override[$key];
                }
            }
        }
    } catch (Throwable $e) {
        // Ignore when table does not exist yet.
    }

    return $base;
}

function applyEffectiveThresholds(PDO $pdo, array $device): array {
    if (empty($device['id'])) {
        return $device;
    }

    $effective = getDeviceEffectiveThresholds($pdo, (int)$device['id']);
    $device['effective_warning_latency_threshold'] = $effective['warning_latency_threshold'];
    $device['effective_warning_packetloss_threshold'] = $effective['warning_packetloss_threshold'];
    $device['effective_critical_latency_threshold'] = $effective['critical_latency_threshold'];
    $device['effective_critical_packetloss_threshold'] = $effective['critical_packetloss_threshold'];

    // Compatibility: pipeline reads legacy keys.
    $device['warning_latency_threshold'] = $effective['warning_latency_threshold'];
    $device['warning_packetloss_threshold'] = $effective['warning_packetloss_threshold'];
    $device['critical_latency_threshold'] = $effective['critical_latency_threshold'];
    $device['critical_packetloss_threshold'] = $effective['critical_packetloss_threshold'];

    return $device;
}
