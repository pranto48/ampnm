<?php

function agentCompatGetHeader($key) {
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
    return isset($_SERVER[$serverKey]) ? trim((string)$_SERVER[$serverKey]) : '';
}

function agentCompatValidateToken($pdo, $token) {
    if ($token === '') {
        return false;
    }
    $stmt = $pdo->prepare("SELECT id, user_id FROM agent_tokens WHERE token = ? AND enabled = 1 LIMIT 1");
    $stmt->execute(array($token));
    $tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tokenRow) {
        return false;
    }
    $touch = $pdo->prepare("UPDATE agent_tokens SET last_used_at = NOW() WHERE id = ?");
    $touch->execute(array($tokenRow['id']));
    return $tokenRow;
}

function agentCompatGetTableColumns($pdo, $table) {
    $stmt = $pdo->query("SHOW COLUMNS FROM `" . $table . "`");
    $columns = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    return $columns;
}

function agentCompatHasColumn($columns, $name) {
    return in_array($name, $columns, true);
}

function agentCompatPickValue($payload, $keys, $defaultValue) {
    foreach ($keys as $k) {
        if (isset($payload[$k]) && $payload[$k] !== '') {
            return $payload[$k];
        }
    }
    return $defaultValue;
}

function agentCompatFindOrCreateDevice($pdo, $userId, $hostName, $hostIp) {
    if ($hostIp !== '') {
        $stmt = $pdo->prepare("SELECT id, name, ip FROM devices WHERE user_id = ? AND ip = ? LIMIT 1");
        $stmt->execute(array($userId, $hostIp));
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return $existing;
        }
    }

    if ($hostName !== '') {
        $stmt = $pdo->prepare("SELECT id, name, ip FROM devices WHERE user_id = ? AND name = ? LIMIT 1");
        $stmt->execute(array($userId, $hostName));
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return $existing;
        }
    }

    $name = $hostName !== '' ? $hostName : ($hostIp !== '' ? $hostIp : 'Agent Host');
    $insert = $pdo->prepare("INSERT INTO devices (user_id, name, ip, monitor_method, type, status, ping_interval, show_live_ping, description) VALUES (?, ?, ?, 'ping', 'server', 'online', NULL, 0, ?)");
    $insert->execute(array($userId, $name, $hostIp !== '' ? $hostIp : null, 'Auto-created from agent telemetry'));
    $id = $pdo->lastInsertId();
    return array('id' => $id, 'name' => $name, 'ip' => $hostIp);
}

function agentCompatSaveMetrics($pdo, $payload, $tokenUserId) {
    $hostIp = trim((string)agentCompatPickValue($payload, array('host_ip', 'ip_address'), ''));
    $hostName = trim((string)agentCompatPickValue($payload, array('host_name', 'hostname'), $hostIp));
    if ($hostIp === '') {
        return array('ok' => false, 'error' => 'host_ip is required');
    }

    $cpu = agentCompatPickValue($payload, array('cpu_percent', 'cpu_usage', 'cpu'), null);
    $mem = agentCompatPickValue($payload, array('memory_percent', 'memory_usage'), null);
    $disk = agentCompatPickValue($payload, array('disk_percent', 'disk_usage'), null);
    $gpu = agentCompatPickValue($payload, array('gpu_percent', 'gpu_usage'), null);
    $netIn = agentCompatPickValue($payload, array('network_in_mbps', 'network_in'), null);
    $netOut = agentCompatPickValue($payload, array('network_out_mbps', 'network_out'), null);

    $device = agentCompatFindOrCreateDevice($pdo, (int)$tokenUserId, $hostName, $hostIp);
    $deviceId = isset($device['id']) ? (int)$device['id'] : null;

    $deviceTouch = $pdo->prepare("UPDATE devices SET status = 'online', last_seen = NOW() WHERE id = ?");
    if ($deviceId) {
        $deviceTouch->execute(array($deviceId));
    }

    $hostCols = agentCompatGetTableColumns($pdo, 'host_metrics');
    $row = array(
        'device_id' => $deviceId,
        'host_name' => $hostName,
        'hostname' => $hostName,
        'host_ip' => $hostIp,
        'ip_address' => $hostIp,
        'cpu_percent' => $cpu,
        'cpu_usage' => $cpu,
        'memory_percent' => $mem,
        'memory_usage' => $mem,
        'disk_percent' => $disk,
        'disk_usage' => $disk,
        'network_in_mbps' => $netIn,
        'network_out_mbps' => $netOut,
        'network_in' => $netIn,
        'network_out' => $netOut,
        'gpu_percent' => $gpu,
        'gpu_usage' => $gpu,
        'status' => 'online',
        'last_seen' => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s')
    );

    $insertCols = array();
    $insertVals = array();
    foreach ($row as $k => $v) {
        if (agentCompatHasColumn($hostCols, $k)) {
            $insertCols[] = "`" . $k . "`";
            $insertVals[] = $v;
        }
    }
    if (!empty($insertCols)) {
        $placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
        
        $updateParts = array();
        $updateVals = array();
        foreach ($row as $k => $v) {
            if (agentCompatHasColumn($hostCols, $k) && $k !== 'hostname' && $k !== 'created_at' && $k !== 'id') {
                $updateParts[] = "`" . $k . "` = ?";
                $updateVals[] = $v;
            }
        }
        
        $sql = "INSERT INTO host_metrics (" . implode(', ', $insertCols) . ") VALUES (" . $placeholders . ") " .
               "ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);
               
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($insertVals, $updateVals));
    }

    $historyCols = agentCompatGetTableColumns($pdo, 'host_metrics_history');
    $historyRow = array(
        'host_name' => $hostName,
        'hostname' => $hostName,
        'host_ip' => $hostIp,
        'ip_address' => $hostIp,
        'cpu_percent' => $cpu,
        'cpu_usage' => $cpu,
        'memory_percent' => $mem,
        'memory_usage' => $mem,
        'disk_percent' => $disk,
        'disk_usage' => $disk,
        'network_in_mbps' => $netIn,
        'network_out_mbps' => $netOut,
        'network_in' => $netIn,
        'network_out' => $netOut,
        'gpu_percent' => $gpu,
        'gpu_usage' => $gpu,
        'recorded_at' => date('Y-m-d H:i:s')
    );
    $hCols = array();
    $hVals = array();
    foreach ($historyRow as $k => $v) {
        if (agentCompatHasColumn($historyCols, $k)) {
            $hCols[] = "`" . $k . "`";
            $hVals[] = $v;
        }
    }
    if (!empty($hCols)) {
        $ph = implode(', ', array_fill(0, count($hCols), '?'));
        $sql = "INSERT INTO host_metrics_history (" . implode(', ', $hCols) . ") VALUES (" . $ph . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($hVals);
    }

    return array('ok' => true, 'host_ip' => $hostIp, 'host_name' => $hostName, 'device_id' => $deviceId);
}

