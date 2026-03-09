<?php
/**
 * Floor Plan API Handler
 * Handles CRUD for floor_plans, rack_locations, patch_panels, switch_ports, cable_runs
 */

function handleFloorPlanAction($action, $data, $pdo) {
    switch ($action) {
        // Floor Plans
        case 'get_floor_plans':
            $stmt = $pdo->query("SELECT * FROM floor_plans ORDER BY created_at ASC");
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

        case 'create_floor_plan':
            $stmt = $pdo->prepare("INSERT INTO floor_plans (name, image_url, user_id) VALUES (?, ?, ?)");
            $stmt->execute([$data['name'], $data['image_url'] ?? null, $_SESSION['user_id']]);
            return ['success' => true, 'id' => $pdo->lastInsertId()];

        case 'update_floor_plan':
            $stmt = $pdo->prepare("UPDATE floor_plans SET name = ?, image_url = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$data['name'], $data['image_url'] ?? null, $data['id']]);
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
            $stmt = $pdo->prepare("INSERT INTO rack_locations (floor_plan_id, name, rack_units) VALUES (?, ?, ?)");
            $stmt->execute([$data['floor_plan_id'], $data['name'], $data['rack_units'] ?? 42]);
            return ['success' => true, 'id' => $pdo->lastInsertId()];

        case 'update_rack':
            $stmt = $pdo->prepare("UPDATE rack_locations SET name = ?, rack_units = ? WHERE id = ?");
            $stmt->execute([$data['name'], $data['rack_units'] ?? 42, $data['id']]);
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

        default:
            return ['success' => false, 'error' => 'Unknown action: ' . $action];
    }
}
