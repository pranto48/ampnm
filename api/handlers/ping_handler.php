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
$user_role = $_SESSION['user_role'] ?? 'viewer'; // Get current user's role

switch ($action) {
    case 'manual_ping':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can perform manual pings.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $host = $input['host'] ?? '';
            $count = $input['count'] ?? 4; // Use count from input, default to 4
            if (empty($host)) {
                http_response_code(400);
                echo json_encode(['error' => 'Host is required']);
                exit;
            }
            $result = executePing($host, $count);
            savePingResult($pdo, $host, $result);
            echo json_encode($result);
        }
        break;

    case 'ping_device':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can ping devices.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ip = $input['ip'] ?? '';
            if (empty($ip)) {
                http_response_code(400);
                echo json_encode(['error' => 'IP address is required']);
                exit;
            }
            $result = pingDevice($ip);
            echo json_encode($result);
        }
        break;

    case 'scan_network':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can scan the network.']); exit; }
        require_once __DIR__ . '/../../includes/advanced_scanner.php';
        $target = trim($input['subnet'] ?? $input['target'] ?? $_POST['subnet'] ?? '192.168.1.0/24');
        $ips = AdvancedScanner::parseTargets($target);
        if (empty($ips)) {
            echo json_encode(['success' => false, 'error' => 'Invalid subnet or IP range provided', 'devices' => []]);
            exit;
        }

        $devices = AdvancedScanner::sweep($ips);
        echo json_encode([
            'success' => true,
            'target' => $target,
            'scanned_count' => count($ips),
            'discovered_count' => count($devices),
            'devices' => $devices
        ]);
        break;

    case 'bulk_import_scanned_devices':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can import devices.']); exit; }
        $devices = $input['devices'] ?? [];
        $mapId = (int)($input['map_id'] ?? 0);

        if (empty($devices) || !is_array($devices)) {
            http_response_code(400);
            echo json_encode(['error' => 'No devices provided for import']);
            exit;
        }

        $imported = 0;
        $stmtCheck = $pdo->prepare("SELECT id FROM devices WHERE ip_address = ? AND user_id = ?");
        $stmtInsert = $pdo->prepare("INSERT INTO devices (user_id, name, ip_address, type, icon, status, is_monitored) VALUES (?, ?, ?, ?, ?, 'online', 1)");
        $stmtMap = $pdo->prepare("INSERT IGNORE INTO map_devices (map_id, device_id, x, y) VALUES (?, ?, ?, ?)");

        // Stagger positions in grid
        $gridX = 100;
        $gridY = 100;

        foreach ($devices as $idx => $d) {
            $ip = trim($d['ip'] ?? '');
            if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) continue;

            $name = trim($d['name'] ?? $d['device_name'] ?? $d['hostname'] ?? "Device {$ip}");
            $type = trim($d['type'] ?? $d['device_type'] ?? 'generic');
            $icon = trim($d['icon'] ?? $type);

            $stmtCheck->execute([$ip, $current_user_id]);
            $existingId = $stmtCheck->fetchColumn();

            $devId = $existingId;
            if (!$devId) {
                $stmtInsert->execute([$current_user_id, $name, $ip, $type, $icon]);
                $devId = $pdo->lastInsertId();
                $imported++;
            }

            if ($mapId > 0 && $devId) {
                $posX = $gridX + (($idx % 5) * 160);
                $posY = $gridY + (floor($idx / 5) * 140);
                try {
                    $stmtMap->execute([$mapId, $devId, $posX, $posY]);
                } catch (Exception $e) {}
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Successfully imported {$imported} new device(s)",
            'imported_count' => $imported
        ]);
        break;

    case 'get_ping_history':
        // Allow viewers to see ping history
        $host = $_GET['host'] ?? '';
        $limit = $_GET['limit'] ?? 100;

        $sql = "SELECT host, avg_time, packet_loss, success, created_at FROM ping_results";
        $params = [];
        if ($host) {
            $sql .= " WHERE host = ?";
            $params[] = $host;
        }
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = (int)$limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Reverse the array so the chart shows oldest to newest
        echo json_encode(array_reverse($history));
        break;
}