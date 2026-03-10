<?php
/**
 * Floor Plan API Handler
 * Handles CRUD for floor_plans, rack_locations, patch_panels, switch_ports, cable_runs,
 * floor_plan_devices, floor_plan_annotations (canvas support)
 */

function handleFloorPlanAction($action, $data, $pdo) {
    switch ($action) {
        // Floor Plans
        case 'get_floor_plans':
            $stmt = $pdo->query("SELECT * FROM floor_plans ORDER BY created_at ASC");
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

        case 'create_floor_plan':
            $stmt = $pdo->prepare("INSERT INTO floor_plans (name, image_url, width, height, user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$data['name'], $data['image_url'] ?? null, $data['width'] ?? 2000, $data['height'] ?? 1500, $_SESSION['user_id']]);
            return ['success' => true, 'id' => $pdo->lastInsertId()];

        case 'update_floor_plan':
            $stmt = $pdo->prepare("UPDATE floor_plans SET name = ?, image_url = ?, width = ?, height = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$data['name'], $data['image_url'] ?? null, $data['width'] ?? 2000, $data['height'] ?? 1500, $data['id']]);
            return ['success' => true];

        case 'delete_floor_plan':
            $pdo->prepare("DELETE FROM floor_plans WHERE id = ?")->execute([$data['id']]);
            return ['success' => true];

        // Devices (for dropdowns)
        case 'get_devices':
            $stmt = $pdo->query("SELECT id, name, type, ip, port_config, subchoice FROM devices ORDER BY name ASC");
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

        // Racks
        case 'get_racks':
            $stmt = $pdo->prepare("SELECT * FROM rack_locations WHERE floor_plan_id = ? ORDER BY name ASC");
            $stmt->execute([$data['floor_plan_id']]);
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

        case 'create_rack':
            $stmt = $pdo->prepare("INSERT INTO rack_locations (floor_plan_id, name, rack_units, x, y, rotation, label_visible) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['floor_plan_id'], $data['name'], $data['rack_units'] ?? 42, $data['x'] ?? 100, $data['y'] ?? 100, $data['rotation'] ?? 0, $data['label_visible'] ?? 1]);
            return ['success' => true, 'id' => $pdo->lastInsertId()];

        case 'update_rack':
            $fields = [];
            $params = [];
            $allowed = ['name', 'rack_units', 'x', 'y', 'rotation', 'label_visible'];
            foreach ($allowed as $f) {
                if (array_key_exists($f, $data)) {
                    $fields[] = "$f = ?";
                    $params[] = $data[$f];
                }
            }
            if (empty($fields)) return ['success' => true];
            $params[] = $data['id'];
            $pdo->prepare("UPDATE rack_locations SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
            return ['success' => true];

        case 'delete_rack':
            $pdo->prepare("DELETE FROM rack_locations WHERE id = ?")->execute([$data['id']]);
            return ['success' => true];

        // Panels
        case 'get_panels':
            $rackIds = $data['rack_ids'] ?? [];
            if (empty($rackIds)) return ['success' => true, 'data' => []];
            $placeholders = implode(',', array_fill(0, count($rackIds), '?'));
            $stmt = $pdo->prepare("SELECT * FROM patch_panels WHERE rack_id IN ($placeholders) ORDER BY rack_position ASC");
            $stmt->execute($rackIds);
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

        case 'create_panel':
            $stmt = $pdo->prepare("INSERT INTO patch_panels (rack_id, name, port_count, rack_position, panel_type) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$data['rack_id'], $data['name'], $data['port_count'] ?? 24, $data['rack_position'] ?? 1, $data['panel_type'] ?? 'rj45']);
            return ['success' => true, 'id' => $pdo->lastInsertId()];

        case 'update_panel':
            $stmt = $pdo->prepare("UPDATE patch_panels SET rack_id = ?, name = ?, port_count = ?, rack_position = ?, panel_type = ? WHERE id = ?");
            $stmt->execute([$data['rack_id'], $data['name'], $data['port_count'] ?? 24, $data['rack_position'] ?? 1, $data['panel_type'] ?? 'rj45', $data['id']]);
            return ['success' => true];

        case 'delete_panel':
            $pdo->prepare("DELETE FROM patch_panels WHERE id = ?")->execute([$data['id']]);
            return ['success' => true];

        // Switch Ports
        case 'get_switch_ports':
            $stmt = $pdo->query("SELECT * FROM switch_ports ORDER BY device_id, port_number ASC");
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

        case 'create_port':
            $stmt = $pdo->prepare("INSERT INTO switch_ports (device_id, port_number, port_label, status, speed, vlan, connected_device, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['device_id'], $data['port_number'], $data['port_label'], $data['status'] ?? 'inactive', $data['speed'] ?? '1G', $data['vlan'], $data['connected_device'], $data['notes']]);
            return ['success' => true, 'id' => $pdo->lastInsertId()];

        case 'update_port':
            $stmt = $pdo->prepare("UPDATE switch_ports SET port_number = ?, port_label = ?, status = ?, speed = ?, vlan = ?, connected_device = ?, notes = ? WHERE id = ?");
            $stmt->execute([$data['port_number'], $data['port_label'], $data['status'] ?? 'inactive', $data['speed'] ?? '1G', $data['vlan'], $data['connected_device'], $data['notes'], $data['id']]);
            return ['success' => true];

        case 'delete_port':
            $pdo->prepare("DELETE FROM switch_ports WHERE id = ?")->execute([$data['id']]);
            return ['success' => true];

        // Cable Runs
        case 'get_cables':
            $stmt = $pdo->prepare("SELECT * FROM cable_runs WHERE floor_plan_id = ? ORDER BY created_at ASC");
            $stmt->execute([$data['floor_plan_id']]);
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

        case 'create_cable':
            $stmt = $pdo->prepare("INSERT INTO cable_runs (floor_plan_id, cable_type, cable_color, cable_length, label, source_type, source_id, source_port, dest_type, dest_id, dest_port, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['floor_plan_id'], $data['cable_type'] ?? 'cat6', $data['cable_color'] ?? 'blue', $data['cable_length'], $data['label'], $data['source_type'], $data['source_id'], $data['source_port'], $data['dest_type'], $data['dest_id'], $data['dest_port'], $data['notes']]);
            return ['success' => true, 'id' => $pdo->lastInsertId()];

        case 'update_cable':
            $stmt = $pdo->prepare("UPDATE cable_runs SET cable_type = ?, cable_color = ?, cable_length = ?, label = ?, source_type = ?, source_id = ?, source_port = ?, dest_type = ?, dest_id = ?, dest_port = ?, notes = ? WHERE id = ?");
            $stmt->execute([$data['cable_type'] ?? 'cat6', $data['cable_color'] ?? 'blue', $data['cable_length'], $data['label'], $data['source_type'], $data['source_id'], $data['source_port'], $data['dest_type'], $data['dest_id'], $data['dest_port'], $data['notes'], $data['id']]);
            return ['success' => true];

        case 'delete_cable':
            $pdo->prepare("DELETE FROM cable_runs WHERE id = ?")->execute([$data['id']]);
            return ['success' => true];

        // ==========================================
        // CANVAS: Floor Plan Devices (placed on canvas)
        // ==========================================
        case 'get_floor_plan_devices':
            $stmt = $pdo->prepare("
                SELECT fpd.id, fpd.floor_plan_id, fpd.device_id, fpd.x, fpd.y, d.name, d.type, d.ip, d.status
                FROM floor_plan_devices fpd
                JOIN devices d ON d.id = fpd.device_id
                WHERE fpd.floor_plan_id = ?
                ORDER BY d.name ASC
            ");
            $stmt->execute([$data['floor_plan_id']]);
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

        case 'place_device_on_plan':
            $stmt = $pdo->prepare("INSERT INTO floor_plan_devices (floor_plan_id, device_id, x, y) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE x = VALUES(x), y = VALUES(y)");
            $stmt->execute([$data['floor_plan_id'], $data['device_id'], $data['x'] ?? 200, $data['y'] ?? 200]);
            return ['success' => true, 'id' => $pdo->lastInsertId()];

        case 'move_plan_device':
            $stmt = $pdo->prepare("UPDATE floor_plan_devices SET x = ?, y = ? WHERE id = ?");
            $stmt->execute([$data['x'], $data['y'], $data['id']]);
            return ['success' => true];

        case 'remove_plan_device':
            $pdo->prepare("DELETE FROM floor_plan_devices WHERE id = ?")->execute([$data['id']]);
            return ['success' => true];

        // ==========================================
        // CANVAS: Annotations (labels, zones)
        // ==========================================
        case 'get_annotations':
            $stmt = $pdo->prepare("SELECT * FROM floor_plan_annotations WHERE floor_plan_id = ? ORDER BY created_at ASC");
            $stmt->execute([$data['floor_plan_id']]);
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

        case 'create_annotation':
            $stmt = $pdo->prepare("INSERT INTO floor_plan_annotations (floor_plan_id, x, y, text, font_size, color, type, width, height) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['floor_plan_id'], $data['x'] ?? 0, $data['y'] ?? 0,
                $data['text'] ?? 'Label', $data['font_size'] ?? 14, $data['color'] ?? '#94a3b8',
                $data['type'] ?? 'label', $data['width'] ?? null, $data['height'] ?? null
            ]);
            return ['success' => true, 'id' => $pdo->lastInsertId()];

        case 'update_annotation':
            $fields = [];
            $params = [];
            $allowed = ['x', 'y', 'text', 'font_size', 'color', 'type', 'width', 'height'];
            foreach ($allowed as $f) {
                if (array_key_exists($f, $data)) {
                    $fields[] = "$f = ?";
                    $params[] = $data[$f];
                }
            }
            if (empty($fields)) return ['success' => true];
            $params[] = $data['id'];
            $pdo->prepare("UPDATE floor_plan_annotations SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
            return ['success' => true];

        case 'delete_annotation':
            $pdo->prepare("DELETE FROM floor_plan_annotations WHERE id = ?")->execute([$data['id']]);
            return ['success' => true];

        default:
            return ['success' => false, 'error' => 'Unknown action: ' . $action];
    }
}
