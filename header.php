<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Ensure user_role is set, default to 'viewer' if not (e.g., for new sessions after upgrade)
$user_role = $_SESSION['user_role'] ?? 'viewer';
$current_page = basename($_SERVER['PHP_SELF']); // Get current page filename

// Load dynamic menu items and theme settings
require_once __DIR__ . '/includes/functions.php';
try {
    $pdo = getDbConnection();
    
    // Load theme settings
    $theme_keys = ['theme_accent_color', 'theme_navbar_bg', 'theme_text_color'];
    $theme = [];
    foreach ($theme_keys as $key) {
        $stmt = $pdo->prepare("SELECT `setting_value` FROM `app_settings` WHERE `setting_key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $theme[$key] = $row ? $row['setting_value'] : null;
    }
    
    $theme_accent = $theme['theme_accent_color'] ?: '#06b6d4';
    $theme_nav_bg = $theme['theme_navbar_bg'] ?: '#0f172a';
    $theme_text = $theme['theme_text_color'] ?: '#cbd5e1';

    // Load menu items
    $stmt = $pdo->prepare("SELECT * FROM `menu_items` ORDER BY `parent_id` ASC, `sort_order` ASC, `title` ASC");
    $stmt->execute();
    $all_menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $all_menu_items = [];
    $theme_accent = '#06b6d4';
    $theme_nav_bg = '#0f172a';
    $theme_text = '#cbd5e1';
}

// Build hierarchical menu
$menu_tree = [];
$submenus = [];

foreach ($all_menu_items as $item) {
    if ($item['role_required'] === 'admin' && $user_role !== 'admin') {
        continue;
    }
    if ($item['parent_id'] === null) {
        $menu_tree[$item['id']] = $item;
        $menu_tree[$item['id']]['children'] = [];
    } else {
        $submenus[] = $item;
    }
}

foreach ($submenus as $item) {
    if (isset($menu_tree[$item['parent_id']])) {
        $menu_tree[$item['parent_id']]['children'][] = $item;
    }
}

// Sort main menu
usort($menu_tree, function($a, $b) {
    return $a['sort_order'] <=> $b['sort_order'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMPNM – Network Monitor</title>
    <!-- Animated SVG Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><defs><style>@keyframes spin{from{transform-origin:32px 32px;transform:rotate(0deg)}to{transform-origin:32px 32px;transform:rotate(360deg)}}@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}.ring{animation:spin 3s linear infinite}.dot{animation:pulse 1.5s ease-in-out infinite}</style></defs><circle cx='32' cy='32' r='28' fill='%230f172a' stroke='%2306b6d4' stroke-width='3'/><circle cx='32' cy='32' r='18' fill='none' stroke='%2322d3ee' stroke-width='1.5' stroke-dasharray='8 4' class='ring'/><circle cx='32' cy='32' r='9' fill='%2306b6d4'/><circle cx='32' cy='32' r='5' fill='%230f172a' class='dot'/><circle cx='32' cy='11' r='3' fill='%2322d3ee' class='dot'/><circle cx='53' cy='43' r='3' fill='%2322d3ee' class='dot' style='animation-delay:.5s'/><circle cx='11' cy='43' r='3' fill='%2322d3ee' class='dot' style='animation-delay:1s'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --theme-accent: <?= $theme_accent ?>;
            --theme-nav-bg: <?= $theme_nav_bg ?>;
            --theme-text: <?= $theme_text ?>;
        }
        .text-cyan-400 { color: var(--theme-accent) !important; }
        .bg-cyan-600 { background-color: var(--theme-accent) !important; }
        .bg-cyan-500 { background-color: var(--theme-accent) !important; }
        .hover\:bg-cyan-500:hover { background-color: var(--theme-accent) !important; filter: brightness(0.9); }
        .hover\:bg-cyan-700:hover { background-color: var(--theme-accent) !important; filter: brightness(0.8); }
        .focus\:ring-cyan-500:focus { --tw-ring-color: var(--theme-accent) !important; }
        .border-cyan-500 { border-color: var(--theme-accent) !important; }
        body { color: var(--theme-text) !important; }
        nav.bg-slate-800\/50 { background-color: var(--theme-nav-bg) !important; }
        #main-nav-wrapper { background-color: var(--theme-nav-bg) !important; }

        /* iOS 17 Real Liquid Glass Theme System */
        <?php if (strpos($theme_nav_bg, 'rgba') !== false || strpos($theme_nav_bg, 'hsla') !== false): ?>
        body {
            background: radial-gradient(circle at 15% 15%, rgba(6, 182, 212, 0.12), transparent 45%),
                        radial-gradient(circle at 85% 20%, rgba(139, 92, 246, 0.12), transparent 40%),
                        radial-gradient(circle at 50% 80%, rgba(59, 130, 246, 0.10), transparent 50%),
                        #030712 !important;
            background-attachment: fixed !important;
        }
        .bg-slate-800, 
        .bg-slate-800\/50, 
        .bg-slate-800\/70,
        .host-card, 
        .device-card, 
        main .bg-slate-800,
        main .border-slate-700,
        #widget-server-metrics,
        #widget-device-overview,
        #widget-ping-test,
        #widget-recent-activity,
        #widget-device-explorer,
        .modal-panel,
        .bg-slate-900\/30,
        .bg-slate-900\/40,
        .bg-slate-900\/60 {
            background-color: rgba(15, 23, 42, 0.45) !important;
            backdrop-filter: blur(28px) saturate(210%) contrast(105%) !important;
            -webkit-backdrop-filter: blur(28px) saturate(210%) contrast(105%) !important;
            border: 1px solid rgba(255, 255, 255, 0.14) !important;
            border-radius: 1.25rem !important;
            box-shadow: inset 0 1px 1px 0 rgba(255, 255, 255, 0.25), 
                        inset 0 -1px 1px 0 rgba(0, 0, 0, 0.3), 
                        0 20px 40px -12px rgba(0, 0, 0, 0.5) !important;
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }
        .bg-slate-800:hover, 
        .host-card:hover, 
        .device-card:hover,
        #widget-server-metrics:hover,
        #widget-device-overview:hover,
        #widget-ping-test:hover,
        #widget-recent-activity:hover,
        #widget-device-explorer:hover {
            border-color: rgba(255, 255, 255, 0.28) !important;
            box-shadow: inset 0 1px 1px 0 rgba(255, 255, 255, 0.4), 
                        0 12px 36px -8px rgba(6, 182, 212, 0.25) !important;
        }
        nav, #main-nav-wrapper {
            background-color: <?= $theme_nav_bg ?> !important;
            backdrop-filter: blur(32px) saturate(220%) !important;
            -webkit-backdrop-filter: blur(32px) saturate(220%) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
            box-shadow: inset 0 -1px 1px 0 rgba(255, 255, 255, 0.1), 0 8px 32px rgba(0, 0, 0, 0.35) !important;
        }
        input, select, textarea {
            background-color: rgba(15, 23, 42, 0.55) !important;
            border: 1px solid rgba(255, 255, 255, 0.18) !important;
            border-radius: 0.75rem !important;
            backdrop-filter: blur(12px) !important;
            -webkit-backdrop-filter: blur(12px) !important;
            color: #f8fafc !important;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.25) !important;
        }
        input:focus, select:focus, textarea:focus {
            border-color: var(--theme-accent) !important;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.2), 0 0 15px rgba(6, 182, 212, 0.4) !important;
        }
        .text-slate-400 {
            color: #94a3b8 !important;
        }

        /* Map Page & UI Element Optics - Hardware Accelerated & GPU Optimized */
        #network-map-wrapper {
            background-color: rgba(15, 23, 42, 0.85) !important;
            border: 1px solid rgba(255, 255, 255, 0.16) !important;
            border-radius: 1.25rem !important;
            box-shadow: inset 0 1px 1px 0 rgba(255, 255, 255, 0.28), 0 20px 50px -10px rgba(0, 0, 0, 0.6) !important;
            contain: layout paint;
            transform: translateZ(0);
        }
        #network-map {
            background-color: #0f172a !important;
            border-radius: 1.25rem !important;
        }
        .vis-tooltip {
            background: rgba(15, 23, 42, 0.92) !important;
            backdrop-filter: blur(24px) saturate(200%) !important;
            -webkit-backdrop-filter: blur(24px) saturate(200%) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            border-radius: 1rem !important;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.65) !important;
        }
        #status-legend {
            background-color: rgba(15, 23, 42, 0.75) !important;
            backdrop-filter: blur(24px) saturate(200%) !important;
            -webkit-backdrop-filter: blur(24px) saturate(200%) !important;
            border: 1px solid rgba(255, 255, 255, 0.16) !important;
            border-radius: 1rem !important;
        }
        .nav-group-items {
            background-color: rgba(15, 23, 42, 0.88) !important;
            backdrop-filter: blur(30px) saturate(220%) !important;
            -webkit-backdrop-filter: blur(30px) saturate(220%) !important;
            border: 1px solid rgba(255, 255, 255, 0.18) !important;
            border-radius: 1rem !important;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6) !important;
        }
        <?php endif; ?>
    </style>
</head>
<body class="bg-slate-900 text-slate-300 min-h-screen">
    <nav class="bg-slate-800/50 backdrop-blur-lg shadow-lg sticky top-0 z-50 nav-3d-shell">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <!-- Mobile menu button -->
                    <button id="mobile-menu-button" class="md:hidden p-2 text-slate-300 hover:text-white focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white">
                        <i class="fas fa-bars h-6 w-6"></i>
                    </button>
                    <a href="index.php" class="flex items-center gap-2.5 text-white font-bold ml-3 md:ml-0 brand-3d group">
                        <div class="p-2 bg-blue-600 text-white rounded-xl shadow-lg shadow-blue-500/20 group-hover:scale-110 group-hover:rotate-3 transition-all duration-300 flex items-center justify-center">
                            <i class="fas fa-heartbeat text-base animate-pulse"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-extrabold text-white text-base leading-tight tracking-tight flex items-center gap-1.5">
                                AMPNM
                                <span class="text-[10px] bg-blue-900/80 text-blue-300 font-bold px-1.5 py-0.5 rounded border border-blue-700/50">
                                    v1.19
                                </span>
                            </span>
                            <span class="text-[9px] text-slate-400 font-medium tracking-wide uppercase select-none">
                                by IT Support BD
                            </span>
                        </div>
                    </a>
                </div>
                
                <!-- Mobile Sidebar / Desktop Navigation -->
                <div id="main-nav-wrapper" class="fixed inset-y-0 left-0 w-64 bg-slate-800/95 backdrop-blur-lg z-50 transform -translate-x-full transition-transform duration-300 ease-in-out md:relative md:w-auto md:bg-transparent md:backdrop-blur-none md:transform-none md:transition-none md:flex md:items-center">
                    <!-- Close button for mobile sidebar -->
                    <div class="flex items-center justify-between p-4 border-b border-slate-700 md:hidden">
                        <a href="index.php" class="flex items-center gap-2.5 text-white font-bold brand-3d group">
                            <div class="p-2 bg-blue-600 text-white rounded-xl shadow-lg shadow-blue-500/20 group-hover:scale-110 group-hover:rotate-3 transition-all duration-300 flex items-center justify-center">
                                <i class="fas fa-heartbeat text-base animate-pulse"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-extrabold text-white text-base leading-tight tracking-tight flex items-center gap-1.5">
                                    AMPNM
                                    <span class="text-[10px] bg-blue-900/80 text-blue-300 font-bold px-1.5 py-0.5 rounded border border-blue-700/50">
                                        v1.20
                                    </span>
                                </span>
                                <span class="text-[9px] text-slate-400 font-medium tracking-wide uppercase select-none">
                                    by IT Support BD
                                </span>
                            </div>
                        </a>
                        <button id="close-mobile-menu-button" class="p-2 text-slate-300 hover:text-white focus:outline-none">
                            <i class="fas fa-times h-6 w-6"></i>
                        </button>
                    </div>
                    <div id="main-nav" class="flex flex-col p-4 space-y-1 md:flex-row md:p-0 md:space-y-0 md:space-x-1 md:ml-10">
                        <?php if (empty($menu_tree)): ?>
                            <!-- Fallback to static menu if DB load fails or table is empty before setup runs -->
                            <a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt fa-fw mr-2"></i>Dashboard</a>
                            <div class="nav-group">
                                <button type="button" class="nav-link nav-group-toggle">
                                    <span class="flex items-center"><i class="fas fa-network-wired fa-fw mr-2"></i>Network</span>
                                    <i class="fas fa-chevron-down nav-group-caret"></i>
                                </button>
                                <div class="nav-group-items">
                                    <a href="map.php" class="nav-link nav-sublink"><i class="fas fa-project-diagram fa-fw mr-2"></i>Map</a>
                                    <a href="floor_plan.php" class="nav-link nav-sublink"><i class="fas fa-building fa-fw mr-2"></i>Floor Plan</a>
                                    <a href="network_graphs.php" class="nav-link nav-sublink"><i class="fas fa-chart-line fa-fw mr-2"></i>Network Graphs</a>
                                </div>
                            </div>
                            <div class="nav-group">
                                <button type="button" class="nav-link nav-group-toggle">
                                    <span class="flex items-center"><i class="fas fa-heartbeat fa-fw mr-2"></i>Monitoring</span>
                                    <i class="fas fa-chevron-down nav-group-caret"></i>
                                </button>
                                <div class="nav-group-items">
                                    <a href="host_metrics.php" class="nav-link nav-sublink"><i class="fas fa-microchip fa-fw mr-2"></i>Host Metrics</a>
                                    <a href="ssl_monitors.php" class="nav-link nav-sublink"><i class="fas fa-lock fa-fw mr-2 text-cyan-400"></i>SSL Expiry Tracker</a>
                                    <a href="agent_devices.php" class="nav-link nav-sublink"><i class="fas fa-desktop fa-fw mr-2"></i>Windows Agents</a>
                                    <a href="agent_enrollment.php" class="nav-link nav-sublink"><i class="fas fa-key fa-fw mr-2"></i>Agent Enrollment</a>
                                    <a href="agent_settings.php" class="nav-link nav-sublink"><i class="fas fa-sliders fa-fw mr-2"></i>Agent Settings</a>
                                    <a href="agent_logs.php" class="nav-link nav-sublink"><i class="fas fa-file-lines fa-fw mr-2"></i>Agent Logs</a>
                                    <a href="alert_settings.php" class="nav-link nav-sublink"><i class="fas fa-bell fa-fw mr-2"></i>Alert Settings</a>
                                    <a href="windows_agent.php" class="nav-link nav-sublink"><i class="fas fa-person-chalkboard fa-fw mr-2"></i>Agent Onboarding</a>
                                    <a href="download-agent.php" class="nav-link nav-sublink"><i class="fas fa-download fa-fw mr-2"></i>Download Agents</a>
                                    <a href="documentation.php#windows-agent" class="nav-link nav-sublink"><i class="fas fa-book-open fa-fw mr-2"></i>Windows Agent Guide</a>
                                    <a href="api/agent/windows-metrics/health" class="nav-link nav-sublink" target="_blank" rel="noreferrer"><i class="fas fa-plug-circle-check fa-fw mr-2"></i>Agent API Health</a>
                                </div>
                            </div>
                            <?php if ($user_role === 'admin'): ?>
                                <div class="nav-group">
                                    <button type="button" class="nav-link nav-group-toggle">
                                        <span class="flex items-center"><i class="fas fa-cogs fa-fw mr-2"></i>Administration</span>
                                        <i class="fas fa-chevron-down nav-group-caret"></i>
                                    </button>
                                    <div class="nav-group-items">
                                        <a href="devices.php" class="nav-link nav-sublink"><i class="fas fa-server fa-fw mr-2"></i>Devices</a>
                                        <a href="history.php" class="nav-link nav-sublink"><i class="fas fa-history fa-fw mr-2"></i>History</a>
                                        <a href="status_logs.php" class="nav-link nav-sublink"><i class="fas fa-clipboard-list fa-fw mr-2"></i>Status Logs</a>
                                        <a href="system_backup.php" class="nav-link nav-sublink"><i class="fas fa-database fa-fw mr-2"></i>System Backup</a>
                                        <a href="email_notifications.php" class="nav-link nav-sublink"><i class="fas fa-envelope fa-fw mr-2"></i>Email Notifications</a>
                                        <a href="sms_notifications.php" class="nav-link nav-sublink"><i class="fas fa-sms fa-fw mr-2"></i>SMS Notifications</a>
                                        <a href="telegram_notifications.php" class="nav-link nav-sublink"><i class="fab fa-telegram fa-fw mr-2"></i>Telegram Notifications</a>
                                        <a href="whatsapp_notifications.php" class="nav-link nav-sublink"><i class="fab fa-whatsapp fa-fw mr-2"></i>WhatsApp Notifications</a>
                                        <a href="update_status.php" class="nav-link nav-sublink"><i class="fas fa-cloud-download-alt fa-fw mr-2"></i>Update Status</a>
                                        <a href="users.php" class="nav-link nav-sublink"><i class="fas fa-users-cog fa-fw mr-2"></i>Users</a>
                                        <a href="audit_logs.php" class="nav-link nav-sublink"><i class="fas fa-shield-alt fa-fw mr-2"></i>Audit Logs</a>
                                        <a href="menu_settings.php" class="nav-link nav-sublink"><i class="fas fa-palette fa-fw mr-2"></i>Menu &amp; Themes</a>
                                        <a href="license_management.php" class="nav-link nav-sublink"><i class="fas fa-id-card fa-fw mr-2"></i>License</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <a href="documentation.php" class="nav-link"><i class="fas fa-book fa-fw mr-2"></i>Help</a>
                            <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt fa-fw mr-2"></i>Logout</a>
                        <?php else: ?>
                            <!-- Dynamic menu rendering -->
                            <?php foreach ($menu_tree as $item): ?>
                                <?php if (!empty($item['children'])): ?>
                                    <!-- Dropdown menu -->
                                    <div class="nav-group">
                                        <button type="button" class="nav-link nav-group-toggle">
                                            <span class="flex items-center">
                                                <?php if (!empty($item['icon'])): ?>
                                                    <i class="<?= htmlspecialchars($item['icon']) ?> fa-fw mr-2"></i>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($item['title']) ?>
                                            </span>
                                            <i class="fas fa-chevron-down nav-group-caret"></i>
                                        </button>
                                        <div class="nav-group-items">
                                            <?php foreach ($item['children'] as $child): ?>
                                                <a href="<?= htmlspecialchars($child['url']) ?>" class="nav-link nav-sublink" <?= strpos($child['url'], 'http') === 0 ? 'target="_blank" rel="noreferrer"' : '' ?>>
                                                    <?php if (!empty($child['icon'])): ?>
                                                        <i class="<?= htmlspecialchars($child['icon']) ?> fa-fw mr-2"></i>
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars($child['title']) ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Direct link -->
                                    <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link" <?= strpos($item['url'], 'http') === 0 ? 'target="_blank" rel="noreferrer"' : '' ?>>
                                        <?php if (!empty($item['icon'])): ?>
                                            <i class="<?= htmlspecialchars($item['icon']) ?> fa-fw mr-2"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($item['title']) ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <div class="page-content">
    <?php 
    // Only show license status if it's not 'unconfigured' OR if we are on the license_management.php page
    // Additionally, for 'active' and 'free' licenses, only show on license_management.php
    if (isset($_SESSION['license_status_code']) && 
        ($_SESSION['license_status_code'] !== 'unconfigured' || $current_page === 'license_management.php') &&
        (($_SESSION['license_status_code'] !== 'active' && $_SESSION['license_status_code'] !== 'free') || $current_page === 'license_management.php')
    ): 
        $license_status_code = $_SESSION['license_status_code'];
        $license_message = $_SESSION['license_message'];
        $max_devices = $_SESSION['license_max_devices'];
        $current_devices = $_SESSION['current_device_count'];
        $expires_at = $_SESSION['license_expires_at'];
        $grace_period_end = $_SESSION['license_grace_period_end'];

        $status_class = '';
        $status_icon = '';
        $display_message = '';
        $show_manage_link = true;

        switch ($license_status_code) {
            case 'active':
            case 'free':
                $status_class = 'bg-green-500/20 text-green-400 border-green-500/30';
                $status_icon = '<i class="fas fa-check-circle mr-1"></i>';
                $max_dev_str = ($max_devices <= 0 || $max_devices >= 99999) ? 'Unlimited' : $max_devices;
                $display_message = "License Active ({$current_devices}/{$max_dev_str} devices)";
                if ($expires_at) {
                    $display_message .= " - Expires: " . date('Y-m-d', strtotime($expires_at));
                }
                break;
            case 'grace_period':
                $status_class = 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30';
                $status_icon = '<i class="fas fa-exclamation-triangle mr-1"></i>';
                $display_message = "License Expired! Grace period until " . date('Y-m-d', $grace_period_end) . ".";
                break;
            case 'expired': // Should be caught by grace_period or disabled
            case 'revoked':
            case 'in_use':
            case 'disabled':
                $status_class = 'bg-red-500/20 text-red-400 border-red-500/30';
                $status_icon = '<i class="fas fa-ban mr-1"></i>';
                $display_message = "License Disabled! ({$license_message})";
                break;
            case 'unconfigured':
                $status_class = 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30';
                $status_icon = '<i class="fas fa-exclamation-circle mr-1"></i>';
                $display_message = "License Unconfigured! Please set up your license key.";
                break;
            case 'portal_unreachable':
                $status_class = 'bg-orange-500/20 text-orange-400 border-orange-500/30';
                $status_icon = '<i class="fas fa-cloud-offline mr-1"></i>';
                $display_message = "License Portal Unreachable! ({$license_message})";
                break;
            case 'invalid':
            case 'not_found':
            case 'error':
            default:
                $status_class = 'bg-red-500/20 text-red-400 border-red-500/30';
                $status_icon = '<i class="fas fa-times-circle mr-1"></i>';
                $display_message = "License Invalid! ({$license_message})";
                break;
        }
    ?>
        <div class="container mx-auto px-4 mt-4">
            <div class="p-3 rounded-lg text-sm flex items-center justify-between <?= $status_class ?>">
                <div><?= $status_icon ?> <?= htmlspecialchars($display_message) ?></div>
                <?php if ($show_manage_link && $user_role === 'admin'): ?>
                    <a href="license_management.php" class="text-cyan-400 hover:underline ml-4">Manage License</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
