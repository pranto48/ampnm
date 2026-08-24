<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
require_once 'includes/functions.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$handler = $_GET['handler'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    $pdo = getDbConnection(); // Get PDO connection early for all actions

    // --- Public Actions (NO AUTH REQUIRED) ---
    // These actions must be handled and exit BEFORE any authentication checks.
    if ($action === 'get_public_map_data') {
        $map_id = $_GET['map_id'] ?? null;
        if (!$map_id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required.']); exit; }

        $stmt_map = $pdo->prepare("SELECT id, name, background_color, background_image_url, public_view_enabled FROM maps WHERE id = ? AND public_view_enabled = TRUE");
        $stmt_map->execute([$map_id]);
        $map = $stmt_map->fetch(PDO::FETCH_ASSOC);

        if (!$map) { http_response_code(404); echo json_encode(['error' => 'Map not found or not enabled for public view.']); exit; }

        $stmt_devices = $pdo->prepare("
            SELECT 
                d.id, d.name, d.ip, d.check_port, d.type, d.subchoice, d.description, d.x, d.y, 
                d.ping_interval, d.icon_size, d.name_text_size, d.icon_url, 
                d.warning_latency_threshold, d.warning_packetloss_threshold, 
                d.critical_latency_threshold, d.critical_packetloss_threshold, 
                d.show_live_ping, d.status, d.last_seen, d.last_avg_time, d.last_ttl,
                p.output as last_ping_output,
                hm.cpu_usage, hm.memory_usage, hm.disk_usage, hm.network_in, hm.network_out, hm.last_seen AS agent_last_seen
            FROM 
                devices d
            LEFT JOIN 
                maps m ON d.map_id = m.id
            LEFT JOIN 
                ping_results p ON p.id = (
                    SELECT id 
                    FROM ping_results 
                    WHERE host = d.ip 
                    ORDER BY created_at DESC 
                    LIMIT 1
                )
            LEFT JOIN 
                host_metrics hm ON (
                    (d.ip IS NOT NULL AND d.ip != '' AND hm.ip_address = d.ip) OR 
                    (d.name IS NOT NULL AND d.name != '' AND hm.hostname = d.name)
                )
            WHERE d.map_id = ?
        ");
        $stmt_devices->execute([$map_id]);
        $devices = $stmt_devices->fetchAll(PDO::FETCH_ASSOC);

        $stmt_edges = $pdo->prepare("SELECT id, source_id, target_id, connection_type FROM device_edges WHERE map_id = ?");
        $stmt_edges->execute([$map_id]);
        $edges = $stmt_edges->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'map' => $map,
            'devices' => $devices,
            'edges' => $edges
        ]);
        exit;
    }

    // Allow ping_all_devices for public maps without authentication
    if ($action === 'ping_all_devices' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $map_id = $input['map_id'] ?? null;
        if (!$map_id) { http_response_code(400); echo json_encode(['error' => 'Map ID is required']); exit; }

        // Check if the map is public_view_enabled
        $stmt_map = $pdo->prepare("SELECT public_view_enabled FROM maps WHERE id = ?");
        $stmt_map->execute([$map_id]);
        $map_info = $stmt_map->fetch(PDO::FETCH_ASSOC);

        if (!$map_info || !$map_info['public_view_enabled']) {
            // If map is not found or not public, then return 403
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: This map is not enabled for public pinging.']);
            exit;
        }

        // Temporarily set user_role to 'viewer' and user_id to a dummy for the duration of this public ping
        // This allows the device_handler.php logic to proceed without session-based auth
        $original_user_role = $_SESSION['user_role'] ?? null;
        $original_user_id = $_SESSION['user_id'] ?? null;
        $_SESSION['user_role'] = 'viewer';
        $_SESSION['user_id'] = 'public_viewer'; // Dummy ID for public pings

        // Include the device handler to process the ping
        require __DIR__ . '/api/handlers/device_handler.php';

        // Restore original session values
        if ($original_user_role !== null) {
            $_SESSION['user_role'] = $original_user_role;
        } else {
            unset($_SESSION['user_role']);
        }
        if ($original_user_id !== null) {
            $_SESSION['user_id'] = $original_user_id;
        } else {
            unset($_SESSION['user_id']);
        }
        exit; // Exit after handling public ping
    }


    if ($action === 'telegram_webhook') {
        require_once __DIR__ . '/includes/telegram_bot.php';
        handleTelegramWebhook($pdo);
        exit;
    }

    if ($action === 'whatsapp_webhook') {
        require_once __DIR__ . '/includes/whatsapp_bot.php';
        handleWhatsappWebhook($pdo);
        exit;
    }

    if ($action === 'get_public_status_page') {
        $settings = $pdo->query("SELECT * FROM status_page_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$settings || empty($settings['is_public_enabled'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Status page is not publicly accessible.']);
            exit;
        }

        $components = $pdo->query("SELECT c.*, d.name AS linked_device_name, d.status AS linked_device_status 
            FROM status_page_components c
            LEFT JOIN devices d ON c.device_id = d.id
            ORDER BY c.group_name ASC, c.display_order ASC")->fetchAll(PDO::FETCH_ASSOC);

        $incidents = $pdo->query("SELECT * FROM status_page_incidents WHERE status != 'resolved' OR created_at >= NOW() - INTERVAL 7 DAY ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($incidents as &$inc) {
            $stmtUp = $pdo->prepare("SELECT * FROM status_page_incident_updates WHERE incident_id = ? ORDER BY created_at DESC");
            $stmtUp->execute([$inc['id']]);
            $inc['updates'] = $stmtUp->fetchAll(PDO::FETCH_ASSOC);
        }

        $maintenances = $pdo->query("SELECT * FROM maintenance_windows WHERE end_time >= NOW() ORDER BY start_time ASC")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'settings' => $settings,
            'components' => $components,
            'incidents' => $incidents,
            'maintenance_windows' => $maintenances
        ]);
        exit;
    }

    // --- Authenticated Actions (AUTH REQUIRED) ---
    // Support Bearer Token & X-Agent-Token Authentication for REST API & Telemetry Agents
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $agentTokenHeader = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? $_GET['agent_token'] ?? $_POST['agent_token'] ?? '';
    $rawToken = '';
    if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $rawToken = trim($matches[1]);
    } elseif (!empty($agentTokenHeader)) {
        $rawToken = trim($agentTokenHeader);
    }

    if (!empty($rawToken)) {
        try {
            $stmtToken = $pdo->prepare("SELECT user_id, name FROM agent_tokens WHERE token = ? AND enabled = 1");
            $stmtToken->execute([$rawToken]);
            $tokenInfo = $stmtToken->fetch(PDO::FETCH_ASSOC);
            if ($tokenInfo) {
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    @session_start();
                }
                $_SESSION['user_id'] = $tokenInfo['user_id'] ?? 1;
                $_SESSION['user_role'] = 'admin';
                $_SESSION['agent_token'] = $rawToken;
            }
        } catch (Exception $e) {
            // Continue
        }
    }

    require_once 'includes/auth_check.php'; // This will now only run if the above public action didn't exit.

    // Broadcast API event to WebSocket broadcaster
    require_once __DIR__ . '/includes/websocket_server.php';
    AMPNM_WebSocketBroadcaster::getInstance()->broadcastEvent('api_action', ['action' => $action, 'user' => $_SESSION['user_id'] ?? 'anonymous']);

    // Release session write lock early for concurrent API performance, except for session-modifying actions
    if ($action !== 'force_license_recheck') {
        session_write_close();
    }

    // Define actions that 'viewer' role can perform (mostly GET requests for viewing)
    $viewer_allowed_get_actions = [
        'get_maps', 'get_devices', 'get_edges', 'get_dashboard_data', 'get_ping_history',
        'get_status_logs', 'get_downtime_summary', 'get_offline_logs', 'get_log_backup_schedules', 'get_device_details', 'get_device_uptime',
        'get_smtp_settings', 'get_all_devices_for_subscriptions', 'get_device_subscriptions',
        'health', 'get_current_license_info', 'get_historical_map_state',
        // Host metrics & SNMP viewing
        'get_latest_metrics', 'get_metrics_history', 'get_all_hosts', 'get_server_metrics',
        'get_snmp_interfaces', 'get_snmp_history', 'get_agent_command', 'list_agent_commands',
        // Floor plan viewing
        'get_floor_plans', 'get_floor_plan', 'get_floor_plan_annotations',
        // Maintenance viewing
        'get_maintenance_windows', 'get_device_maintenance_status',
        // Status page viewing (public/viewer)
        'get_public_status_page', 'get_status_page_incidents',
        // Notification channel reading
        'get_sms_settings', 'get_device_sms_subscriptions', 'get_telegram_settings',
        'get_device_telegram_subscriptions', 'get_whatsapp_settings', 'get_device_whatsapp_subscriptions',
        'get_host_override', 'get_all_host_overrides',
        'get_menu_items', 'get_theme_settings',
        // Rack viewer
        'get_rack_locations', 'get_rack_location', 'get_rack_units',
        // IPAM viewer
        'get_ipam_subnets', 'get_ipam_subnet', 'get_ipam_ips',
    ];

    // Define specific POST actions that 'viewer' role can perform
    $viewer_allowed_post_actions = [
        'ping_all_devices', // ADDED: Allow viewers to trigger bulk pings
        'check_device',
        'update_device_status_by_ip',
    ];

    // If user is a 'viewer', restrict actions
    if ($_SESSION['user_role'] === 'viewer') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!in_array($action, $viewer_allowed_post_actions)) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Your role does not permit this write action.']);
                exit;
            }
        } else { // GET request
            if (!in_array($action, $viewer_allowed_get_actions)) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden: Your role does not permit this read action.']);
                exit;
            }
            // Further restrict 'get_devices' and 'get_edges' to only allow mapped devices/edges if map_id is provided
            if (($action === 'get_devices' || $action === 'get_edges') && !isset($_GET['map_id'])) {
                 http_response_code(403);
                 echo json_encode(['error' => 'Forbidden: Viewers can only access devices/edges within a specific map.']);
                 exit;
            }
        }
    }

    // Group actions by handler
    $pingActions = ['manual_ping', 'scan_network', 'bulk_import_scanned_devices', 'ping_device', 'get_ping_history'];
    $deviceActions = [
        'get_devices', 'create_device', 'update_device', 'delete_device', 'bulk_delete_devices',
        'copy_device', 'get_device_details', 'check_device', 'check_all_devices_globally',
        'get_device_uptime', 'upload_device_icon', 'import_devices', 'update_device_status_by_ip',
        // SNMP Actions
        'test_snmp', 'poll_snmp', 'get_snmp_interfaces', 'get_snmp_history',
        // Agent Remote Command Actions
        'queue_agent_command', 'get_agent_command', 'list_agent_commands', 'agent_poll_commands', 'agent_report_command_result',
        // SSL / TLS Certificate Monitor Actions
        'create_ssl_monitor', 'check_ssl_monitor', 'check_all_ssl_monitors', 'delete_ssl_monitor', 'get_ssl_monitors',
        // Escalation & Webhook Actions
        'get_escalation_settings', 'save_escalation_settings', 'test_webhook_endpoint',
        // Config Backup Vault Actions
        'backup_device_config', 'get_device_config_history', 'get_device_config_content', 'compare_device_configs',
        // IPAM Actions
        'get_ipam_subnets', 'create_ipam_subnet', 'delete_ipam_subnet', 'get_ipam_subnet_ips', 'assign_ipam_ip', 'scan_ipam_subnet',
        // Rack Elevation Actions
        'get_rack_cabinets', 'create_rack_cabinet', 'delete_rack_cabinet', 'get_rack_devices', 'mount_rack_device', 'unmount_rack_device',
        // Status Page & Incident Actions
        'get_status_page_admin', 'save_status_page_settings', 'save_status_component', 'delete_status_component', 'create_status_incident', 'update_status_incident', 'resolve_status_incident',
        // Maintenance Windows Actions
        'get_maintenance_windows', 'create_maintenance_window', 'delete_maintenance_window', 'check_device_maintenance_status',
        // SLA & Reporting Actions
        'get_sla_report_data', 'get_sla_profiles', 'save_sla_profile', 'delete_sla_profile',
        // Topology Path Tracer Actions
        'trace_topology_path'
    ];
    $mapActions = ['get_maps', 'create_map', 'delete_map', 'get_edges', 'create_edge', 'update_edge', 'delete_edge', 'export_map', 'import_map', 'update_map', 'upload_map_background', 'get_device_used_ports', 'get_historical_map_state'];
    $dashboardActions = ['get_dashboard_data', 'get_server_metrics'];
    $userActions = ['get_users', 'create_user', 'delete_user', 'update_user_role', 'update_user_password'];
    $logActions = ['get_status_logs', 'get_downtime_summary', 'get_offline_logs', 'get_log_backup_schedules', 'save_log_backup_schedule', 'delete_log_backup_schedule', 'run_log_backup_now', 'run_due_log_backups'];
    $systemBackupActions = ['get_system_backup_schedules', 'save_system_backup_schedule', 'delete_system_backup_schedule', 'run_system_backup_now', 'run_due_system_backups', 'get_system_backup_runs', 'delete_system_backup_run', 'nas_test_connection', 'nas_browse_path'];
    $notificationActions = ['get_smtp_settings', 'save_smtp_settings', 'send_test_email', 'get_device_subscriptions', 'save_device_subscription', 'delete_device_subscription', 'get_all_devices_for_subscriptions', 'get_sms_settings', 'save_sms_settings', 'send_test_sms', 'get_device_sms_subscriptions', 'save_device_sms_subscription', 'delete_device_sms_subscription', 'get_telegram_settings', 'save_telegram_settings', 'send_test_telegram', 'get_device_telegram_subscriptions', 'save_device_telegram_subscription', 'delete_device_telegram_subscription', 'get_whatsapp_settings', 'save_whatsapp_settings', 'send_test_whatsapp', 'get_device_whatsapp_subscriptions', 'save_device_whatsapp_subscription', 'delete_device_whatsapp_subscription', 'register_telegram_webhook'];
    $licenseActions = ['get_current_license_info', 'update_app_license_key', 'force_license_recheck']; // Added license actions
    $metricsActions = [
        'get_latest_metrics', 'get_metrics_history', 'get_all_hosts',
        'get_agent_tokens', 'create_agent_token', 'delete_agent_token', 'toggle_agent_token',
        'create_device_from_host', 'register_host_ip', 'pull_device_by_ip',
        'get_alert_settings', 'save_alert_settings',
        'get_host_override',
        'get_all_host_overrides', 'save_host_override', 'delete_host_override', 'delete_host',
        'export_host_overrides', 'import_host_overrides',
    ];
    $settingsActions = ['get_menu_items', 'save_menu_item', 'delete_menu_item', 'get_theme_settings', 'save_theme_settings'];
 
    if (in_array($action, $pingActions)) {
        require __DIR__ . '/api/handlers/ping_handler.php';
    } elseif (in_array($action, $deviceActions)) {
        require __DIR__ . '/api/handlers/device_handler.php';
    } elseif (in_array($action, $mapActions)) {
        require __DIR__ . '/api/handlers/map_handler.php';
    } elseif (in_array($action, $dashboardActions)) {
        require __DIR__ . '/api/handlers/dashboard_handler.php';
    } elseif (in_array($action, $userActions)) {
        require __DIR__ . '/api/handlers/user_handler.php';
    } elseif (in_array($action, $logActions)) {
        require __DIR__ . '/api/handlers/log_handler.php';
    } elseif (in_array($action, $systemBackupActions)) {
        require __DIR__ . '/api/handlers/backup_handler.php';
    } elseif (in_array($action, $notificationActions)) {
        require __DIR__ . '/api/handlers/notification_handler.php';
    } elseif (in_array($action, $licenseActions)) { // Handle new license actions
        require __DIR__ . '/api/handlers/license_handler.php';
    } elseif (in_array($action, $metricsActions)) {
        require __DIR__ . '/api/handlers/metrics_handler.php';
    } elseif (in_array($action, $settingsActions)) {
        require __DIR__ . '/api/handlers/settings_handler.php';
    } elseif ($handler === 'floor_plan') {
        require __DIR__ . '/api/handlers/floor_plan_handler.php';
        echo json_encode(handleFloorPlanAction($action, $input, $pdo));
    } elseif ($action === 'poll_snmp') {
        require_once __DIR__ . '/includes/snmp_monitor.php';
        $host = $_GET['host'] ?? '';
        $community = $_GET['community'] ?? 'public';
        $version = $_GET['version'] ?? '2c';
        if (empty($host)) {
            echo json_encode(['success' => false, 'error' => 'Host IP required']);
            exit;
        }
        $monitor = new SNMPMonitor($host, $community, $version);
        $metrics = $monitor->getMetrics();
        echo json_encode(['success' => true, 'metrics' => $metrics]);
        exit;
    } elseif ($action === 'health') {
        echo json_encode(['status' => 'ok', 'timestamp' => date('c')]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("API Error for action '{$action}': " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An internal server error occurred: ' . $e->getMessage()]);
}
?>
