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
$current_group_user_ids = $GLOBALS['current_group_user_ids'] ?? [$current_user_id];
$groupIdsStr = implode(',', array_map('intval', $current_group_user_ids));

switch ($action) {
    case 'get_maps':
        $sql = "SELECT m.id, m.parent_map_id, m.name, m.type, m.background_color, m.background_image_url, m.public_view_enabled, m.updated_at as lastModified, (SELECT COUNT(*) FROM devices WHERE map_id = m.id AND user_id IN ($groupIdsStr)) as deviceCount FROM maps m WHERE m.user_id IN ($groupIdsStr)";
        $params = [];
        $sql .= " ORDER BY m.created_at ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $maps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($maps);
        break;

    case 'create_map':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can create maps.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = $input['name'] ?? ''; $type = $input['type'] ?? 'lan';
            $parent_map_id = isset($input['parent_map_id']) ? intval($input['parent_map_id']) : null;
            if (empty($name)) { http_response_code(400); echo json_encode(['error' => 'Name is required']); exit; }
            $stmt = $pdo->prepare("INSERT INTO maps (user_id, parent_map_id, name, type) VALUES (?, ?, ?, ?)"); $stmt->execute([$current_user_id, $parent_map_id, $name, $type]);
            $lastId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT id, parent_map_id, name, type, public_view_enabled, updated_at as lastModified, 0 as deviceCount FROM maps WHERE id = ? AND user_id = ?"); $stmt->execute([$lastId, $current_user_id]);
            $map = $stmt->fetch(PDO::FETCH_ASSOC); echo json_encode($map);
        }
        break;

    case 'update_map':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can update maps.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            $updates = $input['updates'] ?? [];
            if (!$id || empty($updates)) { http_response_code(400); echo json_encode(['error' => 'Map ID and updates are required']); exit; }
            
            $allowed_fields = ['name', 'parent_map_id', 'background_color', 'background_image_url', 'public_view_enabled', 'offline_delay_seconds'];
            $fields = []; $params = [];
            foreach ($updates as $key => $value) {
                if (in_array($key, $allowed_fields)) {
                    $fields[] = "$key = ?";
                    // Handle boolean conversion for public_view_enabled
                    if ($key === 'public_view_enabled') {
                        $params[] = !empty($value) ? 1 : 0;
                    } elseif ($key === 'offline_delay_seconds') {
                        $delay = (int)$value;
                        if ($delay < 1 || $delay > 300) {
                            $delay = 5;
                        }
                        $params[] = $delay;
                    } else {
                        $params[] = ($value === '') ? null : $value;
                    }
                }
            }

            if (empty($fields)) { http_response_code(400); echo json_encode(['error' => 'No valid fields to update']); exit; }
            
            $params[] = $id;
            $sql = "UPDATE maps SET " . implode(', ', $fields) . " WHERE id = ? AND user_id IN ($groupIdsStr)";
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            
            echo json_encode(['success' => true, 'message' => 'Map updated successfully.']);
        }
        break;

    case 'delete_map':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can delete maps.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }
            $stmt = $pdo->prepare("DELETE FROM maps WHERE id = ? AND user_id IN ($groupIdsStr)"); $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Map deleted successfully']);
        }
        break;
        
    case 'get_edges':
        $map_id = $_GET['map_id'] ?? null;
        if (!$map_id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }
        
        $sql = "SELECT * FROM device_edges WHERE map_id = ?";
        $params = [$map_id];

        // For viewers, do NOT filter by user_id here when SELECTING edges.
        // This allows shared maps to show all edges to viewers.
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $edges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($edges);
        break;

    case 'create_edge':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can create edges.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $sql = "INSERT INTO device_edges (user_id, source_id, target_id, map_id, connection_type, source_port_label, target_port_label, thickness, color, line_style, arrows, label, animated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $current_user_id, 
                $input['source_id'], 
                $input['target_id'], 
                $input['map_id'], 
                $input['connection_type'] ?? 'cat5',
                $input['source_port_label'] ?? null,
                $input['target_port_label'] ?? null,
                isset($input['thickness']) ? (int)$input['thickness'] : 2,
                $input['color'] ?? null,
                $input['line_style'] ?? 'solid',
                $input['arrows'] ?? 'none',
                $input['label'] ?? null,
                isset($input['animated']) ? (int)$input['animated'] : 1
            ]);
            $lastId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM device_edges WHERE id = ? AND user_id IN ($groupIdsStr)");
            $stmt->execute([$lastId]);
            $edge = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($edge);
        }
        break;

    case 'update_edge':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can update edges.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            $connection_type = $input['connection_type'] ?? 'cat6';
            $source_port_label = $input['source_port_label'] ?? null;
            $target_port_label = $input['target_port_label'] ?? null;
            $thickness = isset($input['thickness']) ? (int)$input['thickness'] : 2;
            $color = !empty($input['color']) ? $input['color'] : null;
            $line_style = $input['line_style'] ?? 'solid';
            $arrows = $input['arrows'] ?? 'none';
            $label = !empty($input['label']) ? $input['label'] : null;
            $animated = isset($input['animated']) ? (int)$input['animated'] : 1;
            
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Edge ID is required']); exit; }
            $stmt = $pdo->prepare("UPDATE device_edges SET connection_type = ?, source_port_label = ?, target_port_label = ?, thickness = ?, color = ?, line_style = ?, arrows = ?, label = ?, animated = ? WHERE id = ?");
            $stmt->execute([$connection_type, $source_port_label, $target_port_label, $thickness, $color, $line_style, $arrows, $label, $animated, $id]);
            $stmt = $pdo->prepare("SELECT * FROM device_edges WHERE id = ?");
            $stmt->execute([$id]);
            $edge = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($edge ?: ['id' => $id, 'success' => true]);
        }
        break;

    case 'delete_edge':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can delete edges.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? $_GET['id'] ?? null;
            $source_id = $input['source_id'] ?? null;
            $target_id = $input['target_id'] ?? null;

            if (!$id && (!$source_id || !$target_id)) {
                http_response_code(400); echo json_encode(['error' => 'Edge ID or source/target IDs required']); exit;
            }

            $deleted = false;
            if ($id && is_numeric($id)) {
                $stmt = $pdo->prepare("DELETE FROM device_edges WHERE id = ?");
                $stmt->execute([$id]);
                if ($stmt->rowCount() > 0) $deleted = true;
            }

            if (!$deleted && $source_id && $target_id) {
                $stmt = $pdo->prepare("DELETE FROM device_edges WHERE (source_id = ? AND target_id = ?) OR (source_id = ? AND target_id = ?)");
                $stmt->execute([$source_id, $target_id, $target_id, $source_id]);
                if ($stmt->rowCount() > 0) $deleted = true;
            }

            echo json_encode(['success' => true]);
        }
        break;
    

    case 'export_map':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can export maps.']); exit; }
        $map_id = $_GET['map_id'] ?? null;
        if (!$map_id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }

        $stmt = $pdo->prepare("SELECT id FROM maps WHERE id = ? AND user_id IN ($groupIdsStr)");
        $stmt->execute([$map_id]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) { http_response_code(404); echo json_encode(['error' => 'Map not found or access denied.']); exit; }

        $stmt = $pdo->prepare("SELECT * FROM devices WHERE map_id = ? AND user_id IN ($groupIdsStr) ORDER BY id ASC");
        $stmt->execute([$map_id]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT source_id, target_id, connection_type, source_port_label, target_port_label FROM device_edges WHERE map_id = ? AND user_id IN ($groupIdsStr) ORDER BY id ASC");
        $stmt->execute([$map_id]);
        $edges = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $deviceIds = array_map(static fn($d) => (int)$d['id'], $devices);
        $switch_ports = [];
        $cables = [];

        if (!empty($deviceIds)) {
            $in = implode(',', array_fill(0, count($deviceIds), '?'));

            $stmt = $pdo->prepare("SELECT device_id, port_number, port_label, status, speed, vlan, connected_device, notes FROM switch_ports WHERE device_id IN ($in) ORDER BY device_id ASC, port_number ASC");
            $stmt->execute($deviceIds);
            $switch_ports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sqlCables = "SELECT floor_plan_id, cable_type, cable_color, cable_length, label, source_type, source_id, source_port, dest_type, dest_id, dest_port, notes
                FROM cable_runs
                WHERE ((source_type IN ('switch', 'device') AND source_id IN ($in))
                   OR  (dest_type IN ('switch', 'device') AND dest_id IN ($in)))
                ORDER BY id ASC";
            $stmt = $pdo->prepare($sqlCables);
            $stmt->execute(array_merge($deviceIds, $deviceIds));
            $cables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'devices' => $devices,
            'edges' => $edges,
            'switch_ports' => $switch_ports,
            'cables' => $cables
        ]);
        break;

    case 'import_map':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can import maps.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $map_id = $input['map_id'] ?? null;
            $devices = $input['devices'] ?? [];
            $edges = $input['edges'] ?? [];
            $switch_ports = $input['switch_ports'] ?? [];
            $cables = $input['cables'] ?? [];
            if (!$map_id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }

            try {
                $pdo->beginTransaction();

                // Ensure map ownership
                $stmt = $pdo->prepare("SELECT id FROM maps WHERE id = ? AND user_id IN ($groupIdsStr)");
                $stmt->execute([$map_id]);
                if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                    throw new Exception('Map not found or access denied.');
                }

                // Collect existing map device IDs for cable cleanup
                $stmt = $pdo->prepare("SELECT id FROM devices WHERE map_id = ? AND user_id IN ($groupIdsStr)");
                $stmt->execute([$map_id]);
                $existingIds = array_map(static fn($r) => (int)$r['id'], $stmt->fetchAll(PDO::FETCH_ASSOC));
                if (!empty($existingIds)) {
                    $in = implode(',', array_fill(0, count($existingIds), '?'));
                    $sqlDeleteCables = "DELETE FROM cable_runs
                        WHERE ((source_type IN ('switch', 'device') AND source_id IN ($in))
                           OR  (dest_type IN ('switch', 'device') AND dest_id IN ($in)))";
                    $stmt = $pdo->prepare($sqlDeleteCables);
                    $stmt->execute(array_merge($existingIds, $existingIds));
                }

                // Delete old data for this user and map
                $stmt = $pdo->prepare("DELETE FROM device_edges WHERE map_id = ? AND user_id IN ($groupIdsStr)");
                $stmt->execute([$map_id]);
                $stmt = $pdo->prepare("DELETE FROM devices WHERE map_id = ? AND user_id IN ($groupIdsStr)");
                $stmt->execute([$map_id]);

                // Insert new devices
                $device_id_map = [];
                $sql = "INSERT INTO devices (
                    user_id, name, ip, check_port, monitor_method, type, subchoice, description, map_id, x, y,
                    ping_interval, icon_size, name_text_size, name_text_color, name_text_bold, name_text_italic, icon_url,
                    warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold,
                    show_live_ping, port_config
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                foreach ($devices as $device) {
                    $stmt->execute([
                        $current_user_id,
                        $device['name'] ?? 'Unnamed Device',
                        $device['ip'] ?? null,
                        $device['check_port'] ?? null,
                        $device['monitor_method'] ?? 'ping',
                        $device['type'] ?? 'other',
                        $device['subchoice'] ?? 0,
                        $device['description'] ?? null,
                        $map_id,
                        $device['x'] ?? null,
                        $device['y'] ?? null,
                        $device['ping_interval'] ?? null,
                        $device['icon_size'] ?? 50,
                        $device['name_text_size'] ?? 14,
                        $device['name_text_color'] ?? '#ffffff',
                        $device['name_text_bold'] ?? 0,
                        $device['name_text_italic'] ?? 0,
                        $device['icon_url'] ?? null,
                        $device['warning_latency_threshold'] ?? null,
                        $device['warning_packetloss_threshold'] ?? null,
                        $device['critical_latency_threshold'] ?? null,
                        $device['critical_packetloss_threshold'] ?? null,
                        ($device['show_live_ping'] ?? false) ? 1 : 0,
                        $device['port_config'] ?? null
                    ]);
                    $new_id = $pdo->lastInsertId();
                    if (isset($device['id'])) {
                        $device_id_map[(string)$device['id']] = (int)$new_id;
                    }
                }

                // Insert new edges with port labels and cable type
                $sql = "INSERT INTO device_edges (user_id, source_id, target_id, map_id, connection_type, source_port_label, target_port_label) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                foreach ($edges as $edge) {
                    $new_source_id = $device_id_map[(string)($edge['source_id'] ?? '')] ?? null;
                    $new_target_id = $device_id_map[(string)($edge['target_id'] ?? '')] ?? null;
                    if ($new_source_id && $new_target_id) {
                        $stmt->execute([
                            $current_user_id,
                            $new_source_id,
                            $new_target_id,
                            $map_id,
                            $edge['connection_type'] ?? 'cat6',
                            $edge['source_port_label'] ?? null,
                            $edge['target_port_label'] ?? null
                        ]);
                    }
                }

                // Insert switch/device port metadata
                if (!empty($switch_ports)) {
                    $sql = "INSERT INTO switch_ports (device_id, port_number, port_label, status, speed, vlan, connected_device, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    foreach ($switch_ports as $port) {
                        $newDeviceId = $device_id_map[(string)($port['device_id'] ?? '')] ?? null;
                        if (!$newDeviceId) continue;
                        $stmt->execute([
                            $newDeviceId,
                            (int)($port['port_number'] ?? 0),
                            $port['port_label'] ?? null,
                            $port['status'] ?? 'inactive',
                            $port['speed'] ?? '1G',
                            $port['vlan'] ?? null,
                            $port['connected_device'] ?? null,
                            $port['notes'] ?? null
                        ]);
                    }
                }

                // Insert cables and preserve cable type/length/ports
                if (!empty($cables)) {
                    $sql = "INSERT INTO cable_runs (floor_plan_id, cable_type, cable_color, cable_length, label, source_type, source_id, source_port, dest_type, dest_id, dest_port, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    foreach ($cables as $cable) {
                        $sourceType = $cable['source_type'] ?? 'switch';
                        $destType = $cable['dest_type'] ?? 'switch';
                        $sourceId = $cable['source_id'] ?? null;
                        $destId = $cable['dest_id'] ?? null;

                        if (in_array($sourceType, ['switch', 'device'], true)) {
                            $sourceId = $device_id_map[(string)$sourceId] ?? null;
                            if (!$sourceId) continue;
                        }
                        if (in_array($destType, ['switch', 'device'], true)) {
                            $destId = $device_id_map[(string)$destId] ?? null;
                            if (!$destId) continue;
                        }

                        $stmt->execute([
                            null,
                            $cable['cable_type'] ?? 'cat6',
                            $cable['cable_color'] ?? 'blue',
                            $cable['cable_length'] ?? null,
                            $cable['label'] ?? null,
                            $sourceType,
                            (int)$sourceId,
                            (int)($cable['source_port'] ?? 1),
                            $destType,
                            (int)$destId,
                            (int)($cable['dest_port'] ?? 1),
                            $cable['notes'] ?? null
                        ]);
                    }
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Map imported successfully.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
            }
        }
        break;

    case 'upload_map_background':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden: Only admin can upload map backgrounds.']); exit; }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $mapId = $_POST['map_id'] ?? null;
            if (!$mapId || !isset($_FILES['backgroundFile'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Map ID and background file are required.']);
                exit;
            }
    
            $stmt = $pdo->prepare("SELECT id FROM maps WHERE id = ? AND user_id IN ($groupIdsStr)");
            $stmt->execute([$mapId]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Map not found or access denied.']);
                exit;
            }
    
            $uploadDir = __DIR__ . '/../../uploads/map_backgrounds/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to create upload directory.']);
                    exit;
                }
            }
    
            $file = $_FILES['backgroundFile'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                http_response_code(500);
                echo json_encode(['error' => 'File upload error code: ' . $file['error']]);
                exit;
            }
    
            $fileInfo = new SplFileInfo($file['name']);
            $extension = strtolower($fileInfo->getExtension());
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
            if (!in_array($extension, $allowedExtensions)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid file type.']);
                exit;
            }
 
            $newFileName = 'map_' . $mapId . '_' . time() . '.' . $extension;
            $uploadPath = $uploadDir . $newFileName;
            $urlPath = 'uploads/map_backgrounds/' . $newFileName;
    
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $stmt = $pdo->prepare("UPDATE maps SET background_image_url = ? WHERE id = ? AND user_id IN ($groupIdsStr)");
                $stmt->execute([$urlPath, $mapId]);
                echo json_encode(['success' => true, 'url' => $urlPath]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save uploaded file.']);
            }
        }
        break;

    case 'get_device_used_ports':
        $device_id = $_GET['device_id'] ?? null;
        $exclude_edge_id = $_GET['exclude_edge_id'] ?? null;
        if (!$device_id) { http_response_code(400); echo json_encode(['error' => 'device_id is required']); exit; }

        try {
            $stmt = $pdo->prepare("SELECT id, source_id, target_id, source_port_label, target_port_label FROM device_edges WHERE source_id = ? OR target_id = ?");
            $stmt->execute([$device_id, $device_id]);
            $edges = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $ports = [];
            foreach ($edges as $e) {
                // Skip the edge being currently edited so its port remains selectable
                if ($exclude_edge_id && $e['id'] == $exclude_edge_id) continue;
                if ($e['source_id'] == $device_id && !empty($e['source_port_label'])) {
                    $ports[] = $e['source_port_label'];
                }
                if ($e['target_id'] == $device_id && !empty($e['target_port_label'])) {
                    $ports[] = $e['target_port_label'];
                }
            }
            echo json_encode(['ports' => array_values(array_unique($ports))]);
        } catch (PDOException $ex) {
            echo json_encode(['ports' => []]);
        }
        break;

    case 'get_historical_map_state':
        $map_id = $_GET['map_id'] ?? null;
        $hours_ago = isset($_GET['hours_ago']) ? (int)$_GET['hours_ago'] : 0;
        if (!$map_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Map ID is required']);
            exit;
        }

        // Check ownership against user group
        $stmt = $pdo->prepare("SELECT id FROM maps WHERE id = ? AND user_id IN ($groupIdsStr)");
        $stmt->execute([$map_id]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied to this map']);
            exit;
        }

        // Fetch the historical state of devices in the map
        $stmt = $pdo->prepare("
            SELECT 
                d.id,
                d.name,
                d.ip,
                COALESCE(
                    (
                        SELECT status 
                        FROM device_status_logs 
                        WHERE device_id = d.id AND created_at <= (NOW() - INTERVAL ? HOUR)
                        ORDER BY created_at DESC, id DESC
                        LIMIT 1
                    ),
                    d.status
                ) as status
            FROM devices d
            WHERE d.map_id = ?
        ");
        $stmt->execute([$hours_ago, $map_id]);
        $device_states = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($device_states);
        break;
}
