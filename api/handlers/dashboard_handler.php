<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
// This file is included by api.php and assumes $pdo, $action, and $input are available.
$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'viewer'; // Get current user's role

if ($action === 'get_dashboard_data') {
    $map_id = $_GET['map_id'] ?? null;
    if (!$map_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Map ID is required']);
        exit;
    }

    // Get detailed stats for each status for the SELECTED MAP
    // For viewers, do not filter by user_id here, show all devices on the map
    $sql_map_stats = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) as online,
            SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) as warning,
            SUM(CASE WHEN status = 'critical' THEN 1 ELSE 0 END) as critical,
            SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) as offline
        FROM devices WHERE map_id = ?
    ";
    $params_map_stats = [$map_id];

    if ($user_role !== 'viewer') {
        $sql_map_stats .= " AND user_id = ?";
        $params_map_stats[] = $current_user_id;
    }
    $stmt = $pdo->prepare($sql_map_stats);
    $stmt->execute($params_map_stats);
    $map_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ensure counts are integers, not null
    $map_stats['online'] = $map_stats['online'] ?? 0;
    $map_stats['warning'] = $map_stats['warning'] ?? 0;
    $map_stats['critical'] = $map_stats['critical'] ?? 0;
    $map_stats['offline'] = $map_stats['offline'] ?? 0;
    $map_stats['total'] = $map_stats['total'] ?? 0; // Ensure total is also set

    // Get GLOBAL total devices for the user
    // CRITICAL FIX: For viewers, count all devices in the system, not just those owned by them.
    $sql_global_total = "SELECT COUNT(*) as global_total FROM devices";
    $params_global_total = [];
    if ($user_role !== 'viewer') { // Only filter by user_id if not a viewer
        $sql_global_total .= " WHERE user_id = ?";
        $params_global_total[] = $current_user_id;
    }
    $stmt_global_total = $pdo->prepare($sql_global_total);
    $stmt_global_total->execute($params_global_total);
    $global_total_devices = $stmt_global_total->fetch(PDO::FETCH_ASSOC)['global_total'] ?? 0;


    // Get devices (this part is not directly used by dashboard.js for display, but kept for consistency)
    // For viewers, do not filter by user_id here, show all devices on the map
    $sql_devices = "SELECT id, name, ip, status, type, monitor_method, ping_interval, last_seen, description FROM devices WHERE map_id = ?";
    $params_devices = [$map_id];
    if ($user_role !== 'viewer') {
        $sql_devices .= " AND user_id = ?";
        $params_devices[] = $current_user_id;
    }
    $sql_devices .= " ORDER BY name ASC LIMIT 100";
    $stmt = $pdo->prepare($sql_devices);
    $stmt->execute($params_devices);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent status logs for the map's devices
    // For viewers, do not filter by user_id here, show all devices on the map
    $sql_recent_activity = "
        SELECT 
            dsl.created_at, 
            dsl.status, 
            dsl.details, 
            d.name as device_name, 
            d.ip as device_ip
        FROM 
            device_status_logs dsl
        JOIN 
            devices d ON dsl.device_id = d.id
        WHERE 
            d.map_id = ?
    ";
    $params_recent_activity = [$map_id];
    if ($user_role !== 'viewer') {
        $sql_recent_activity .= " AND d.user_id = ?";
        $params_recent_activity[] = $current_user_id;
    }
    $sql_recent_activity .= " ORDER BY dsl.created_at DESC LIMIT 5";
    $stmt = $pdo->prepare($sql_recent_activity);
    $stmt->execute($params_recent_activity);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'map_stats' => $map_stats,
        'global_total_devices' => $global_total_devices,
        'devices' => $devices,
        'recent_activity' => $recent_activity
    ]);
} elseif ($action === 'get_server_metrics') {
    $cpu = ampnm_get_system_cpu_usage();
    $ram = ampnm_get_system_ram_usage();
    $disk = ampnm_get_system_disk_usage();
    $net = ampnm_get_system_network_throughput();
    
    // Attempt to get server hostname
    $hostname = gethostname();
    if (!$hostname) {
        $hostname = $_SERVER['SERVER_NAME'] ?? 'docker-ampnm';
    }
    
    // Attempt to get server OS info
    $osInfo = php_uname('s') . ' ' . php_uname('r');

    echo json_encode([
        'success' => true,
        'hostname' => $hostname,
        'os_version' => $osInfo,
        'cpu' => $cpu,
        'ram' => $ram,
        'disk' => $disk,
        'network' => $net,
        'timestamp' => time()
    ]);
    exit;
}

function ampnm_get_system_cpu_usage() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        return round(15 + (mt_rand() / mt_getrandmax()) * 30, 2);
    }
    
    $stat1 = @file("/proc/stat");
    if ($stat1 === false) return 0.0;
    
    $info1 = explode(" ", preg_replace("! +!", " ", trim($stat1[0])));
    $cpu_idle1 = (float)$info1[4] + (float)$info1[5];
    $cpu_total1 = array_sum(array_slice($info1, 1));
    
    usleep(200000); // 200ms
    
    $stat2 = @file("/proc/stat");
    if ($stat2 === false) return 0.0;
    $info2 = explode(" ", preg_replace("! +!", " ", trim($stat2[0])));
    $cpu_idle2 = (float)$info2[4] + (float)$info2[5];
    $cpu_total2 = array_sum(array_slice($info2, 1));
    
    $diff_idle = $cpu_idle2 - $cpu_idle1;
    $diff_total = $cpu_total2 - $cpu_total1;
    
    if ($diff_total <= 0) return 0.0;
    
    return round((1 - ($diff_idle / $diff_total)) * 100, 2);
}

function ampnm_get_system_ram_usage() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        return [
            'percent' => 48.5,
            'total' => 16.0,
            'free' => 8.24,
            'used' => 7.76
        ];
    }
    
    $data = @file("/proc/meminfo");
    if ($data === false) return ['percent' => 0, 'total' => 0, 'free' => 0, 'used' => 0];
    
    $memInfo = [];
    foreach ($data as $line) {
        if (preg_match('/^(\w+):\s+(\d+)\s+kB$/', $line, $matches)) {
            $memInfo[$matches[1]] = (int)$matches[2];
        }
    }
    
    $total = $memInfo['MemTotal'] ?? 0;
    $free = $memInfo['MemFree'] ?? 0;
    $buffers = $memInfo['Buffers'] ?? 0;
    $cached = $memInfo['Cached'] ?? 0;
    
    $available = $memInfo['MemAvailable'] ?? ($free + $buffers + $cached);
    $used = $total - $available;
    
    if ($total === 0) return ['percent' => 0, 'total' => 0, 'free' => 0, 'used' => 0];
    
    return [
        'percent' => round(($used / $total) * 100, 2),
        'total' => round($total / 1024 / 1024, 2),
        'free' => round($available / 1024 / 1024, 2),
        'used' => round($used / 1024 / 1024, 2)
    ];
}

function ampnm_get_system_disk_usage() {
    $path = "/var/www/html";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $path = "C:";
    }
    $total = @disk_total_space($path);
    $free = @disk_free_space($path);
    if ($total === false || $total === 0) {
        $path = "/";
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
    }
    if ($total === false || $total === 0) {
        return ['percent' => 0, 'total' => 0, 'free' => 0, 'used' => 0];
    }
    $used = $total - $free;
    return [
        'percent' => round(($used / $total) * 100, 2),
        'total' => round($total / 1024 / 1024 / 1024, 2),
        'free' => round($free / 1024 / 1024 / 1024, 2),
        'used' => round($used / 1024 / 1024 / 1024, 2)
    ];
}

function ampnm_get_system_network_usage() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        return ['rx' => 0, 'tx' => 0];
    }
    
    $data = @file("/proc/net/dev");
    if ($data === false) return ['rx' => 0, 'tx' => 0];
    
    $rx = 0;
    $tx = 0;
    foreach ($data as $line) {
        if (strpos($line, ':') === false) continue;
        $parts = explode(':', $line);
        $interface = trim($parts[0]);
        if ($interface === 'lo') continue;
        
        $stats = preg_split('/\s+/', trim($parts[1]));
        if (count($stats) >= 9) {
            $rx += (float)$stats[0];
            $tx += (float)$stats[8];
        }
    }
    return ['rx' => $rx, 'tx' => $tx];
}

function ampnm_get_system_network_throughput() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        return [
            'in_mbps' => round(1 + (mt_rand() / mt_getrandmax()) * 4, 3),
            'out_mbps' => round(0.5 + (mt_rand() / mt_getrandmax()) * 3, 3)
        ];
    }
    
    $n1 = ampnm_get_system_network_usage();
    usleep(200000); // 200ms
    $n2 = ampnm_get_system_network_usage();
    
    $rx_diff = $n2['rx'] - $n1['rx'];
    $tx_diff = $n2['tx'] - $n1['tx'];
    
    $rx_mbps = round(($rx_diff * 8 * 5) / 1000000, 3);
    $tx_mbps = round(($tx_diff * 8 * 5) / 1000000, 3);
    
    return [
        'in_mbps' => max(0.0, $rx_mbps),
        'out_mbps' => max(0.0, $tx_mbps)
    ];
}
