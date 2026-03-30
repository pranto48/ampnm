<?php
$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'viewer';

function templateToPayload(PDO $pdo, array $template): array {
    $templateId = (int)$template['id'];

    $stmtItems = $pdo->prepare('SELECT item_key, item_value, is_inheritable FROM template_items WHERE template_id = ? ORDER BY item_key ASC');
    $stmtItems->execute([$templateId]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    $stmtTrig = $pdo->prepare('SELECT warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold FROM template_triggers WHERE template_id = ?');
    $stmtTrig->execute([$templateId]);
    $triggers = $stmtTrig->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'id' => $templateId,
        'name' => $template['name'],
        'description' => $template['description'],
        'enabled' => (bool)$template['enabled'],
        'items' => $items,
        'triggers' => $triggers,
    ];
}

function templatesToSimpleYaml(array $payload): string {
    $lines = ["version: 1", "templates:"];
    foreach ($payload['templates'] as $t) {
        $lines[] = "  - name: \"" . str_replace('"', '\\"', $t['name']) . "\"";
        $lines[] = "    description: \"" . str_replace('"', '\\"', (string)($t['description'] ?? '')) . "\"";
        $lines[] = '    enabled: ' . (($t['enabled'] ?? true) ? 'true' : 'false');
        $lines[] = '    triggers:';
        foreach (['warning_latency_threshold', 'warning_packetloss_threshold', 'critical_latency_threshold', 'critical_packetloss_threshold'] as $key) {
            $value = $t['triggers'][$key] ?? null;
            $lines[] = "      {$key}: " . ($value === null ? 'null' : (int)$value);
        }
        $lines[] = '    items:';
        foreach (($t['items'] ?? []) as $item) {
            $lines[] = '      - item_key: "' . str_replace('"', '\\"', (string)$item['item_key']) . '"';
            $lines[] = '        item_value: "' . str_replace('"', '\\"', (string)($item['item_value'] ?? '')) . '"';
            $lines[] = '        is_inheritable: ' . (!empty($item['is_inheritable']) ? 'true' : 'false');
        }
    }
    return implode("\n", $lines) . "\n";
}

switch ($action) {
    case 'get_templates':
        $stmt = $pdo->prepare('SELECT id, name, description, enabled, created_at, updated_at FROM templates WHERE user_id = ? ORDER BY name ASC');
        $stmt->execute([$current_user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $templates = array_map(fn($t) => templateToPayload($pdo, $t), $rows);
        echo json_encode(['templates' => $templates]);
        break;

    case 'create_template':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') { http_response_code(400); echo json_encode(['error' => 'Template name required']); exit; }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO templates (user_id, name, description, enabled) VALUES (?, ?, ?, ?)');
            $stmt->execute([$current_user_id, $name, $input['description'] ?? null, !empty($input['enabled']) ? 1 : 0]);
            $templateId = (int)$pdo->lastInsertId();

            $tr = $input['triggers'] ?? [];
            $stmtTrig = $pdo->prepare('INSERT INTO template_triggers (template_id, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold) VALUES (?, ?, ?, ?, ?)');
            $stmtTrig->execute([$templateId, $tr['warning_latency_threshold'] ?? null, $tr['warning_packetloss_threshold'] ?? null, $tr['critical_latency_threshold'] ?? null, $tr['critical_packetloss_threshold'] ?? null]);

            $stmtItem = $pdo->prepare('INSERT INTO template_items (template_id, item_key, item_value, is_inheritable) VALUES (?, ?, ?, ?)');
            foreach (($input['items'] ?? []) as $item) {
                if (empty($item['item_key'])) continue;
                $stmtItem->execute([$templateId, $item['item_key'], $item['item_value'] ?? null, !empty($item['is_inheritable']) ? 1 : 0]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'template_id' => $templateId]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'update_template':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $templateId = (int)($input['id'] ?? 0);
        if ($templateId <= 0) { http_response_code(400); echo json_encode(['error' => 'Template id required']); exit; }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE templates SET name = ?, description = ?, enabled = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?');
            $stmt->execute([$input['name'] ?? '', $input['description'] ?? null, !empty($input['enabled']) ? 1 : 0, $templateId, $current_user_id]);

            $tr = $input['triggers'] ?? [];
            $stmtTrig = $pdo->prepare('INSERT INTO template_triggers (template_id, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE warning_latency_threshold = VALUES(warning_latency_threshold), warning_packetloss_threshold = VALUES(warning_packetloss_threshold), critical_latency_threshold = VALUES(critical_latency_threshold), critical_packetloss_threshold = VALUES(critical_packetloss_threshold), updated_at = CURRENT_TIMESTAMP');
            $stmtTrig->execute([$templateId, $tr['warning_latency_threshold'] ?? null, $tr['warning_packetloss_threshold'] ?? null, $tr['critical_latency_threshold'] ?? null, $tr['critical_packetloss_threshold'] ?? null]);

            $pdo->prepare('DELETE FROM template_items WHERE template_id = ?')->execute([$templateId]);
            $stmtItem = $pdo->prepare('INSERT INTO template_items (template_id, item_key, item_value, is_inheritable) VALUES (?, ?, ?, ?)');
            foreach (($input['items'] ?? []) as $item) {
                if (empty($item['item_key'])) continue;
                $stmtItem->execute([$templateId, $item['item_key'], $item['item_value'] ?? null, !empty($item['is_inheritable']) ? 1 : 0]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'delete_template':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $id = (int)($input['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM templates WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $current_user_id]);
        echo json_encode(['success' => true]);
        break;

    case 'get_host_groups':
        $stmt = $pdo->prepare('SELECT id, name, description FROM host_groups WHERE user_id = ? ORDER BY name ASC');
        $stmt->execute([$current_user_id]);
        echo json_encode(['host_groups' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'create_host_group':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $stmt = $pdo->prepare('INSERT INTO host_groups (user_id, name, description) VALUES (?, ?, ?)');
        $stmt->execute([$current_user_id, $input['name'] ?? 'Group', $input['description'] ?? null]);
        echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        break;

    case 'bulk_apply_template':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $templateId = (int)($input['template_id'] ?? 0);
        $deviceIds = array_map('intval', (array)($input['device_ids'] ?? []));
        $groupIds = array_map('intval', (array)($input['group_ids'] ?? []));
        $priority = (int)($input['priority'] ?? 100);

        $pdo->beginTransaction();
        try {
            if ($templateId <= 0) throw new RuntimeException('template_id required');

            $stmtDev = $pdo->prepare('INSERT INTO device_template_assignments (device_id, template_id, priority) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE priority = VALUES(priority)');
            foreach ($deviceIds as $deviceId) {
                if ($deviceId <= 0) continue;
                $stmtDev->execute([$deviceId, $templateId, $priority]);
            }

            $stmtGroup = $pdo->prepare('INSERT INTO group_template_assignments (host_group_id, template_id, priority) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE priority = VALUES(priority)');
            foreach ($groupIds as $groupId) {
                if ($groupId <= 0) continue;
                $stmtGroup->execute([$groupId, $templateId, $priority]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'bulk_assign_group':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $groupId = (int)($input['group_id'] ?? 0);
        $deviceIds = array_map('intval', (array)($input['device_ids'] ?? []));
        $stmt = $pdo->prepare('INSERT IGNORE INTO host_group_devices (host_group_id, device_id) VALUES (?, ?)');
        foreach ($deviceIds as $deviceId) {
            if ($groupId > 0 && $deviceId > 0) {
                $stmt->execute([$groupId, $deviceId]);
            }
        }
        echo json_encode(['success' => true]);
        break;

    case 'export_templates':
        $format = strtolower((string)($_GET['format'] ?? 'json'));
        $stmt = $pdo->prepare('SELECT id, name, description, enabled FROM templates WHERE user_id = ? ORDER BY name ASC');
        $stmt->execute([$current_user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $payload = ['version' => 1, 'templates' => array_map(fn($t) => templateToPayload($pdo, $t), $rows)];

        if ($format === 'yaml' || $format === 'yml') {
            echo json_encode(['format' => 'yaml', 'content' => templatesToSimpleYaml($payload)]);
        } else {
            echo json_encode(['format' => 'json', 'content' => json_encode($payload, JSON_PRETTY_PRINT)]);
        }
        break;

    case 'import_templates':
        if ($user_role !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }
        $format = strtolower((string)($input['format'] ?? 'json'));
        $content = (string)($input['content'] ?? '');
        if ($content === '') { http_response_code(400); echo json_encode(['error' => 'content required']); exit; }

        if ($format === 'yaml' || $format === 'yml') {
            if (function_exists('yaml_parse')) {
                $parsed = @yaml_parse($content);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'YAML import requires php-yaml extension.']);
                exit;
            }
        } else {
            $parsed = json_decode($content, true);
        }

        if (!is_array($parsed) || !is_array($parsed['templates'] ?? null)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid template payload']);
            exit;
        }

        $created = 0;
        $pdo->beginTransaction();
        try {
            foreach ($parsed['templates'] as $tpl) {
                $name = trim((string)($tpl['name'] ?? ''));
                if ($name === '') continue;

                $stmt = $pdo->prepare('INSERT INTO templates (user_id, name, description, enabled) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE description = VALUES(description), enabled = VALUES(enabled), updated_at = CURRENT_TIMESTAMP');
                $stmt->execute([$current_user_id, $name, $tpl['description'] ?? null, !empty($tpl['enabled']) ? 1 : 0]);

                $fetch = $pdo->prepare('SELECT id FROM templates WHERE user_id = ? AND name = ?');
                $fetch->execute([$current_user_id, $name]);
                $templateId = (int)$fetch->fetchColumn();

                $tr = $tpl['triggers'] ?? [];
                $stmtTrig = $pdo->prepare('INSERT INTO template_triggers (template_id, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE warning_latency_threshold = VALUES(warning_latency_threshold), warning_packetloss_threshold = VALUES(warning_packetloss_threshold), critical_latency_threshold = VALUES(critical_latency_threshold), critical_packetloss_threshold = VALUES(critical_packetloss_threshold), updated_at = CURRENT_TIMESTAMP');
                $stmtTrig->execute([$templateId, $tr['warning_latency_threshold'] ?? null, $tr['warning_packetloss_threshold'] ?? null, $tr['critical_latency_threshold'] ?? null, $tr['critical_packetloss_threshold'] ?? null]);

                $pdo->prepare('DELETE FROM template_items WHERE template_id = ?')->execute([$templateId]);
                $stmtItem = $pdo->prepare('INSERT INTO template_items (template_id, item_key, item_value, is_inheritable) VALUES (?, ?, ?, ?)');
                foreach (($tpl['items'] ?? []) as $item) {
                    if (empty($item['item_key'])) continue;
                    $stmtItem->execute([$templateId, $item['item_key'], $item['item_value'] ?? null, !empty($item['is_inheritable']) ? 1 : 0]);
                }
                $created++;
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'templates_processed' => $created]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
}
