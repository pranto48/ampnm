<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
require_once 'includes/functions.php'; // Include functions early for DB connection and license checks
require_once 'includes/auth_check.php'; // Auth check also needs to be early

$pdo = getDbConnection();
$current_user_id = $_SESSION['user_id'];
$message = '';
$monitor_method = 'ping';

function dbColumnExists(PDO $pdo, string $table, string $column): bool {
    try {
        $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!$dbName) return false;
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$dbName, $table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

// Load device icons library
$deviceIconsLibrary = require_once 'includes/device_icons.php';

// Handle form submission BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $ip = trim($_POST['ip'] ?? '');
    $monitor_method = $_POST['monitor_method'] ?? 'ping';
    $check_port = $_POST['check_port'] ?? null;
    $type = $_POST['type'] ?? 'server';
    $subchoice = $_POST['subchoice'] ?? 0;
    $description = trim($_POST['description'] ?? '');
    $map_id = $_POST['map_id'] ?? null;
    $ping_interval = !empty($_POST['ping_interval']) ? (int)$_POST['ping_interval'] : 20;
    $critical_offline_seconds = !empty($_POST['critical_offline_seconds']) ? (int)$_POST['critical_offline_seconds'] : 20;
    $offline_timeout_seconds = !empty($_POST['offline_timeout_seconds']) ? (int)$_POST['offline_timeout_seconds'] : 30;
    $monitoring_mode = in_array($_POST['monitoring_mode'] ?? '', ['time_threshold', 'packet_count']) ? $_POST['monitoring_mode'] : 'time_threshold';
    $critical_packet_count = !empty($_POST['critical_packet_count']) ? max(1, (int)$_POST['critical_packet_count']) : 20;
    $offline_packet_count = !empty($_POST['offline_packet_count']) ? max(1, (int)$_POST['offline_packet_count']) : 30;
    $icon_size = $_POST['icon_size'] ?? 50;
    $name_text_size = $_POST['name_text_size'] ?? 14;
    $icon_url = trim($_POST['icon_url'] ?? '');
    $warning_latency_threshold = !empty($_POST['warning_latency_threshold']) ? (int)$_POST['warning_latency_threshold'] : 200;
    $warning_packetloss_threshold = !empty($_POST['warning_packetloss_threshold']) ? (int)$_POST['warning_packetloss_threshold'] : 25;
    $critical_latency_threshold = !empty($_POST['critical_latency_threshold']) ? (int)$_POST['critical_latency_threshold'] : 450;
    $critical_packetloss_threshold = !empty($_POST['critical_packetloss_threshold']) ? (int)$_POST['critical_packetloss_threshold'] : 80;
    $show_live_ping = isset($_POST['show_live_ping']) ? 1 : 0;
    $port_config = trim($_POST['port_config'] ?? '');

    // Basic validation
    if (empty($name)) {
        $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Device name is required.</div>';
    } else {
        try {
            // License check for max devices
            $max_devices = $_SESSION['license_max_devices'] ?? 0;
            $current_devices = $_SESSION['current_device_count'] ?? 0;

            // Retrieve all users in the same user group
            $current_user_group = $_SESSION['user_group'] ?? 'default_group';
            $stmtGroup = $pdo->prepare("SELECT id FROM users WHERE user_group = ?");
            $stmtGroup->execute([$current_user_group]);
            $current_group_user_ids = $stmtGroup->fetchAll(PDO::FETCH_COLUMN) ?: [$current_user_id];
            $groupIdsStr = implode(',', array_map('intval', $current_group_user_ids));

            // Check duplicate name
            $stmtDupName = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE LOWER(name) = LOWER(?) AND user_id IN ($groupIdsStr)");
            $stmtDupName->execute([$name]);
            $nameExists = (int)$stmtDupName->fetchColumn() > 0;

            // Check duplicate IP
            $ipExists = false;
            if (!empty($ip)) {
                $stmtDupIp = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE ip = ? AND user_id IN ($groupIdsStr)");
                $stmtDupIp->execute([$ip]);
                $ipExists = (int)$stmtDupIp->fetchColumn() > 0;
            }

            if ($max_devices > 0 && $current_devices >= $max_devices) {
                $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">License limit reached. You cannot add more than ' . $max_devices . ' devices.</div>';
            } elseif ($nameExists) {
                $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">A device with this name already exists in your group.</div>';
            } elseif ($ipExists) {
                $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">A device with this IP address already exists in your group.</div>';
            } else {
                $hasSubchoice = dbColumnExists($pdo, 'devices', 'subchoice');
                $hasPortConfig = dbColumnExists($pdo, 'devices', 'port_config');
                $hasTimeoutCols = dbColumnExists($pdo, 'devices', 'critical_offline_seconds');
                $hasMonitoringMode = dbColumnExists($pdo, 'devices', 'monitoring_mode');
                if ($hasSubchoice) {
                    $cols = "user_id, name, ip, check_port, monitor_method, type, subchoice, description, map_id, x, y, ping_interval, icon_size, name_text_size, icon_url, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold, show_live_ping";
                    $placeholders = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?";
                    $values = [
                        $current_user_id, $name, empty($ip) ? null : $ip, empty($check_port) ? null : $check_port,
                        $monitor_method, $type, is_numeric($subchoice) ? (int)$subchoice : 0,
                        empty($description) ? null : $description, empty($map_id) ? null : $map_id,
                        100, 100, empty($ping_interval) ? 20 : $ping_interval, $icon_size, $name_text_size,
                        empty($icon_url) ? null : $icon_url,
                        empty($warning_latency_threshold) ? 200 : $warning_latency_threshold,
                        empty($warning_packetloss_threshold) ? 25 : $warning_packetloss_threshold,
                        empty($critical_latency_threshold) ? 450 : $critical_latency_threshold,
                        empty($critical_packetloss_threshold) ? 80 : $critical_packetloss_threshold,
                        $show_live_ping
                    ];
                    if ($hasTimeoutCols) {
                        $cols .= ", critical_offline_seconds, offline_timeout_seconds";
                        $placeholders .= ", ?, ?";
                        $values[] = $critical_offline_seconds;
                        $values[] = $offline_timeout_seconds;
                    }
                    if ($hasMonitoringMode) {
                        $cols .= ", monitoring_mode, critical_packet_count, offline_packet_count";
                        $placeholders .= ", ?, ?, ?";
                        $values[] = $monitoring_mode;
                        $values[] = $critical_packet_count;
                        $values[] = $offline_packet_count;
                    }
                    if ($hasPortConfig) {
                        $cols .= ", port_config";
                        $placeholders .= ", ?";
                        $values[] = empty($port_config) ? null : $port_config;
                    }
                    $sql = "INSERT INTO devices ($cols) VALUES ($placeholders)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);
                } else {
                    $sql = "INSERT INTO devices (user_id, name, ip, check_port, monitor_method, type, description, map_id, x, y, ping_interval, icon_size, name_text_size, icon_url, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold, show_live_ping) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $current_user_id, $name, empty($ip) ? null : $ip, empty($check_port) ? null : $check_port,
                        $monitor_method, $type, empty($description) ? null : $description, empty($map_id) ? null : $map_id,
                        100, 100, empty($ping_interval) ? 20 : $ping_interval, $icon_size, $name_text_size,
                        empty($icon_url) ? null : $icon_url,
                        empty($warning_latency_threshold) ? 200 : $warning_latency_threshold,
                        empty($warning_packetloss_threshold) ? 25 : $warning_packetloss_threshold,
                        empty($critical_latency_threshold) ? 450 : $critical_latency_threshold,
                        empty($critical_packetloss_threshold) ? 80 : $critical_packetloss_threshold,
                        $show_live_ping
                    ]);
                }
                if ($map_id) {
                    header('Location: map.php?map_id=' . urlencode($map_id));
                } else {
                    header('Location: map.php');
                }
                exit;
            }
        } catch (PDOException $e) {
            $message = '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">Error adding device: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Now include header.php, after all potential redirects
include 'header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-white">Add New Device</h1>
            <a href="map.php" class="px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-500"><i class="fas fa-arrow-left mr-2"></i>Back to Map</a>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-6 max-w-4xl mx-auto">
            <?= $message ?>
            <form method="POST" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-400 mb-1">Device Name</label>
                        <input type="text" id="name" name="name" placeholder="e.g., Main Router" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label for="ip" class="block text-sm font-medium text-slate-400 mb-1">IP Address (Optional)</label>
                        <input type="text" id="ip" name="ip" placeholder="e.g., 192.168.1.1" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['ip'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-slate-400 mb-1">Description (Optional)</label>
                    <textarea id="description" name="description" rows="2" placeholder="Optional notes about the device" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-slate-400 mb-1">Device Type & Icon</label>
                    <select id="type" name="type" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 mb-4">
                        <?php
                        foreach ($deviceIconsLibrary as $value => $typeData) {
                            $selected = (($_POST['type'] ?? 'server') === $value) ? 'selected' : '';
                            $iconCount = count($typeData['icons'] ?? []);
                            echo "<option value=\"$value\" $selected>{$typeData['label']} ($iconCount variants)</option>";
                        }
                        ?>
                    </select>

                    <!-- Icon variant index (0-based) selected from the icon picker -->
                    <input type="hidden" id="subchoice" name="subchoice" value="<?= htmlspecialchars($_POST['subchoice'] ?? 0) ?>">

                    <!-- Current selection preview (kept in sync by assets/icon-picker.js) -->
                    <div id="selectedIconPreview" class="flex items-center gap-3 mb-4 px-3 py-2 rounded-lg bg-slate-900 border border-slate-700">
                        <i id="selectedIconPreviewIcon" class="fas fa-circle text-slate-200"></i>
                        <div class="leading-tight">
                            <div id="selectedIconPreviewTitle" class="text-sm font-semibold text-white"></div>
                            <div id="selectedIconPreviewSubtitle" class="text-xs text-slate-400"></div>
                        </div>
                    </div>
                    
                    <!-- Enhanced Icon Picker Container -->
                    <link rel="stylesheet" href="assets/icon-picker.css?v=<?= time() ?>">
                    <div id="iconPickerContainer" class="icon-picker-container"></div>
                </div>

                <!-- Network Ports Configuration -->
                <fieldset class="border border-slate-600 rounded-lg p-4">
                    <legend class="text-sm font-medium text-slate-400 px-2"><i class="fas fa-ethernet mr-1"></i> Network Ports</legend>
                    <input type="hidden" id="port_config" name="port_config" value="">

                    <!-- Port Group Builder -->
                    <div class="mb-4">
                        <label class="block text-xs font-medium text-slate-400 mb-2">Port Groups</label>
                        <div id="portGroupRows" class="space-y-2"></div>
                        <button type="button" id="addPortGroupBtn" class="mt-2 px-3 py-1.5 bg-cyan-700 text-white text-xs rounded-lg hover:bg-cyan-600">
                            <i class="fas fa-plus mr-1"></i>Add Port Group
                        </button>
                    </div>

                    <div id="devicePortPanel">
                        <div class="grid grid-cols-3 gap-3 mb-4" id="portSummaryCards">
                            <div class="bg-slate-900 border border-slate-700 rounded-lg p-3 text-center">
                                <div class="text-2xl font-bold text-cyan-400" id="totalPortCount">0</div>
                                <div class="text-xs text-slate-400">Total Ports</div>
                            </div>
                            <div class="bg-slate-900 border border-slate-700 rounded-lg p-3 text-center">
                                <div class="text-2xl font-bold text-green-400" id="freePortCount">0</div>
                                <div class="text-xs text-slate-400">Free Ports</div>
                            </div>
                            <div class="bg-slate-900 border border-slate-700 rounded-lg p-3 text-center">
                                <div class="text-2xl font-bold text-amber-400" id="usedPortCount">0</div>
                                <div class="text-xs text-slate-400">Used Ports</div>
                            </div>
                        </div>
                        <div id="portGridContainer" class="flex flex-wrap gap-1.5"></div>
                    </div>
                </fieldset>

                <div>
                    <label for="map_id" class="block text-sm font-medium text-slate-400 mb-1">Map Assignment (Optional)</label>
                    <select id="map_id" name="map_id" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                        <option value="">Unassigned</option>
                        <?php foreach ($maps as $map): ?>
                            <option value="<?= htmlspecialchars($map['id']) ?>" <?= (($_POST['map_id'] ?? '') == $map['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($map['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="monitor_method" class="block text-sm font-medium text-slate-400 mb-1">Monitoring Method</label>
                        <select id="monitor_method" name="monitor_method" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500">
                            <option value="ping" <?= (($monitor_method ?? 'ping') === 'ping') ? 'selected' : '' ?>>IP Ping (ICMP)</option>
                            <option value="port" <?= (($monitor_method ?? 'ping') === 'port') ? 'selected' : '' ?>>Service Port Check</option>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Choose how availability is checked for this device.</p>
                    </div>
                    <div>
                        <label for="check_port" class="block text-sm font-medium text-slate-400 mb-1">Service Port (Optional)</label>
                        <input type="number" id="check_port" name="check_port" placeholder="e.g., 80 for HTTP" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['check_port'] ?? '') ?>">
                        <p class="text-xs text-slate-500 mt-1">For port checks, provide the port to probe; leave blank for pure ping.</p>
                    </div>
                </div>


                <fieldset class="border border-slate-600 rounded-lg p-4">
                    <legend class="text-sm font-medium text-slate-400 px-2">Custom Icon (Optional)</legend>
                    <div class="space-y-3">
                        <div>
                            <label for="icon_url" class="block text-sm font-medium text-slate-400 mb-1">Icon URL</label>
                            <input type="text" id="icon_url" name="icon_url" placeholder="Leave blank to use default icon" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm" value="<?= htmlspecialchars($_POST['icon_url'] ?? '') ?>">
                        </div>
                    </div>
                </fieldset>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="icon_size" class="block text-sm font-medium text-slate-400 mb-1">Icon Size</label>
                        <input type="number" id="icon_size" name="icon_size" placeholder="e.g., 50" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['icon_size'] ?? '50') ?>">
                    </div>
                    <div>
                        <label for="name_text_size" class="block text-sm font-medium text-slate-400 mb-1">Name Text Size</label>
                        <input type="number" id="name_text_size" name="name_text_size" placeholder="e.g., 14" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['name_text_size'] ?? '14') ?>">
                    </div>
                </div>

                <!-- Status Thresholds & Ping Availability -->
                <fieldset class="border border-cyan-800/60 rounded-xl p-5 bg-slate-900/40 space-y-5 shadow-lg">
                    <legend class="text-sm font-bold text-cyan-400 px-2.5 flex items-center gap-2">
                        <i class="fas fa-heartbeat text-cyan-400"></i>
                        Ping Interval, Critical & Offline Monitoring System
                    </legend>
                    
                    <!-- Monitoring Mode Selection Radio Cards -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-cyan-300">
                            <i class="fas fa-sliders-h mr-1"></i> মনিটরিং মেথড / মোড নির্বাচন (Select Monitoring Mode)
                        </label>
                        <p class="text-[11px] text-slate-400">ডিভাইসটি কোন পদ্ধতিতে পর্যবেক্ষণ করবেন তা নির্ধারণ করুন (শুধুমাত্র একটি অপশন কার্যকর থাকবে):</p>
                        
                        <?php 
                            $current_mode = $_POST['monitoring_mode'] ?? 'time_threshold'; 
                        ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                            <!-- Option 1: Time & Performance Threshold Mode -->
                            <label class="relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 <?= $current_mode === 'time_threshold' ? 'border-cyan-500 bg-cyan-950/30 ring-1 ring-cyan-500' : 'border-slate-800 bg-slate-950/60 hover:border-slate-700' ?>" id="card_mode_time_threshold">
                                <div class="flex items-start gap-3">
                                    <input type="radio" name="monitoring_mode" value="time_threshold" class="mt-1 h-4 w-4 text-cyan-500 border-slate-600 focus:ring-cyan-500" <?= $current_mode === 'time_threshold' ? 'checked' : '' ?> onchange="toggleMonitoringMode('time_threshold')">
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-white uppercase tracking-wide">১. সময় ও পারফরম্যান্স থ্রেশহোল্ড মোড</span>
                                            <span class="text-[10px] px-2 py-0.5 rounded bg-cyan-900/60 text-cyan-300 border border-cyan-700/50">Time-based</span>
                                        </div>
                                        <p class="text-[11px] text-slate-300 mt-1.5 leading-relaxed">
                                            সময়সীমা (যেমন ২০ সেকেন্ড আনরিচেবল হলে Critical, ৩০ সেকেন্ডে Offline) এবং নির্ধারিত ল্যাটেন্সি (ms) ও প্যাকেট লস (%) সীমা দ্বারা ডিভাইস স্ট্যাটাস পর্যবেক্ষণ করে।
                                        </p>
                                    </div>
                                </div>
                            </label>

                            <!-- Option 2: Packet Count Drop Mode -->
                            <label class="relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 <?= $current_mode === 'packet_count' ? 'border-cyan-500 bg-cyan-950/30 ring-1 ring-cyan-500' : 'border-slate-800 bg-slate-950/60 hover:border-slate-700' ?>" id="card_mode_packet_count">
                                <div class="flex items-start gap-3">
                                    <input type="radio" name="monitoring_mode" value="packet_count" class="mt-1 h-4 w-4 text-cyan-500 border-slate-600 focus:ring-cyan-500" <?= $current_mode === 'packet_count' ? 'checked' : '' ?> onchange="toggleMonitoringMode('packet_count')">
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-white uppercase tracking-wide">২. প্যাকেট সংখ্যা ভিত্তিক ড্রপ মোড</span>
                                            <span class="text-[10px] px-2 py-0.5 rounded bg-amber-900/60 text-amber-300 border border-amber-700/50">Packet Count</span>
                                        </div>
                                        <p class="text-[11px] text-slate-300 mt-1.5 leading-relaxed">
                                            টানা প্যাকেট ড্রপের সংখ্যা গণনা করে (যেমন ২০টি প্যাকেট ড্রপে Critical, ৩০টিতে Offline)। এই মোডে ল্যাটেন্সি ও পারফরম্যান্স থ্রেশহোল্ড সম্পূর্ণ নিষ্ক্রিয় থাকবে।
                                        </p>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Availability & Timeout System (Time-based settings) -->
                    <div id="section_time_threshold" class="bg-slate-950/70 p-4 rounded-xl border border-slate-800 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-cyan-300">
                                <i class="fas fa-stopwatch mr-1"></i> Availability & Timeout Timers (সময়সীমা নিয়ন্ত্রণ)
                            </span>
                            <span class="text-[11px] text-slate-400">Destination unreachable / Packet loss delay</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="ping_interval" class="block text-xs font-semibold text-slate-300 mb-1">
                                    <i class="fas fa-wave-square text-cyan-400 mr-1"></i>Ping Interval (seconds)
                                </label>
                                <input type="number" id="ping_interval" name="ping_interval" min="1" placeholder="20" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-cyan-500" value="<?= htmlspecialchars($_POST['ping_interval'] ?? '20') ?>">
                                <p class="text-[11px] text-slate-400 mt-1">কত সেকেন্ড পর পর পিং চেক হবে (Standard: 20s)।</p>
                            </div>
                            <div>
                                <label for="critical_offline_seconds" class="block text-xs font-semibold text-amber-300 mb-1">
                                    <i class="fas fa-exclamation-triangle text-amber-400 mr-1"></i>Critical Timeout (seconds)
                                </label>
                                <input type="number" id="critical_offline_seconds" name="critical_offline_seconds" min="1" placeholder="20" class="w-full bg-slate-900 border border-amber-600/50 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-amber-500 font-semibold" value="<?= htmlspecialchars($_POST['critical_offline_seconds'] ?? '20') ?>">
                                <p class="text-[11px] text-amber-300/80 mt-1">যদি ২০ সেকেন্ড অবিচ্ছিন্নভাবে Unreachable বা ড্রপ থাকে তবে Critical দেখাবে।</p>
                            </div>
                            <div>
                                <label for="offline_timeout_seconds" class="block text-xs font-semibold text-red-400 mb-1">
                                    <i class="fas fa-times-circle text-red-500 mr-1"></i>Offline Timeout (seconds)
                                </label>
                                <input type="number" id="offline_timeout_seconds" name="offline_timeout_seconds" min="1" placeholder="30" class="w-full bg-slate-900 border border-red-600/50 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-red-500 font-semibold" value="<?= htmlspecialchars($_POST['offline_timeout_seconds'] ?? '30') ?>">
                                <p class="text-[11px] text-red-300/80 mt-1">যদি ৩০ সেকেন্ড Unreachable বা Timeout থাকে তবে Offline এবং নোটিফিকেশন পাঠাবে।</p>
                            </div>
                        </div>
                    </div>

                    <!-- Packet Count Drop Configuration (Packet Count settings) -->
                    <div id="section_packet_count" class="bg-slate-950/70 p-4 rounded-xl border border-slate-800 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-amber-300">
                                <i class="fas fa-layer-group mr-1"></i> Packet Count Drop Configuration (প্যাকেট সংখ্যা ড্রপ সেটিংস)
                            </span>
                            <span class="text-[11px] text-slate-400">Strict packet drop threshold without latency interference</span>
                        </div>
                        
                        <div class="p-3 bg-emerald-950/30 border border-emerald-800/40 rounded-lg text-xs text-emerald-300 flex items-start gap-2.5">
                            <i class="fas fa-shield-alt text-emerald-400 mt-0.5"></i>
                            <div>
                                <strong>স্বাভাবিক অবস্থা নির্দেশিকা:</strong>
                                <span class="font-mono bg-emerald-900/60 px-1.5 py-0.5 rounded text-emerald-200 ml-1">Packets: Sent = 20, Received = 20, Lost = 0 (0% loss)</span>
                                <span>হলে ডিভাইস সম্পূর্ণ Online থাকবে (Critical হবে না)। শুধুমাত্র টানা প্যাকেট লস্ট হলে নিচের থ্রেশহোল্ড অনুযায়ী অবস্থা পরিবর্তিত হবে।</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="critical_packet_count" class="block text-xs font-semibold text-amber-300 mb-1">
                                    <i class="fas fa-exclamation-triangle text-amber-400 mr-1"></i>Critical Packet Count (ড্রপ সংখ্যা)
                                </label>
                                <input type="number" id="critical_packet_count" name="critical_packet_count" min="1" placeholder="20" class="w-full bg-slate-900 border border-amber-600/50 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-amber-500 font-semibold" value="<?= htmlspecialchars($_POST['critical_packet_count'] ?? '20') ?>">
                                <p class="text-[11px] text-amber-300/80 mt-1">টানা ২০টি প্যাকেট ড্রপ (বা ১০০% লস) হলে Critical দেখাবে।</p>
                            </div>
                            <div>
                                <label for="offline_packet_count" class="block text-xs font-semibold text-red-400 mb-1">
                                    <i class="fas fa-times-circle text-red-500 mr-1"></i>Offline Packet Count (ড্রপ সংখ্যা)
                                </label>
                                <input type="number" id="offline_packet_count" name="offline_packet_count" min="1" placeholder="30" class="w-full bg-slate-900 border border-red-600/50 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-red-500 font-semibold" value="<?= htmlspecialchars($_POST['offline_packet_count'] ?? '30') ?>">
                                <p class="text-[11px] text-red-300/80 mt-1">টানা ৩০টি প্যাকেট ড্রপ (বা ১০০% লস) হলে Offline এবং নোটিফিকেশন পাঠাবে।</p>
                            </div>
                        </div>
                    </div>

                    <!-- Latency & Packet Loss Performance Thresholds -->
                    <div id="performance_thresholds_container" class="bg-slate-950/70 p-4 rounded-xl border border-slate-800 space-y-3 transition-all duration-300">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-300">
                                <i class="fas fa-chart-bar mr-1"></i> Latency & Packet Loss Thresholds (পারফরম্যান্স থ্রেশহোল্ড)
                            </span>
                            <span id="threshold_status_badge" class="text-[10px] px-2 py-0.5 rounded bg-emerald-900/50 text-emerald-300 border border-emerald-700/40">সক্রিয় (Active)</span>
                        </div>

                        <div id="threshold_disabled_notice" class="hidden p-3 bg-amber-950/40 border border-amber-800/50 rounded-lg text-xs text-amber-300 flex items-center gap-2">
                            <i class="fas fa-ban text-amber-400"></i>
                            <span><strong>বিজ্ঞপ্তি:</strong> 'প্যাকেট সংখ্যা ভিত্তিক ড্রপ মোড' নির্বাচিত থাকায় ল্যাটেন্সি ও প্যাকেট লস পারফরম্যান্স থ্রেশহোল্ড সম্পূর্ণ নিষ্ক্রিয় রয়েছে। শুধুমাত্র প্যাকেট ড্রপ সংখ্যা অনুযায়ী ডিভাইস নিয়ন্ত্রিত হবে।</span>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="threshold_inputs_grid">
                            <div>
                                <label for="warning_latency_threshold" class="block text-xs font-semibold text-yellow-400 mb-1">Warn Latency (ms)</label>
                                <input type="number" id="warning_latency_threshold" name="warning_latency_threshold" class="threshold-input w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm text-white" value="<?= htmlspecialchars($_POST['warning_latency_threshold'] ?? '200') ?>">
                                <p class="text-[10px] text-slate-400 mt-0.5">Default: 200 ms</p>
                            </div>
                            <div>
                                <label for="warning_packetloss_threshold" class="block text-xs font-semibold text-yellow-400 mb-1">Warn Packet Loss (%)</label>
                                <input type="number" id="warning_packetloss_threshold" name="warning_packetloss_threshold" class="threshold-input w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm text-white" value="<?= htmlspecialchars($_POST['warning_packetloss_threshold'] ?? '25') ?>">
                                <p class="text-[10px] text-slate-400 mt-0.5">Default: 25%</p>
                            </div>
                            <div>
                                <label for="critical_latency_threshold" class="block text-xs font-semibold text-red-400 mb-1">Critical Latency (ms)</label>
                                <input type="number" id="critical_latency_threshold" name="critical_latency_threshold" class="threshold-input w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm text-white" value="<?= htmlspecialchars($_POST['critical_latency_threshold'] ?? '450') ?>">
                                <p class="text-[10px] text-slate-400 mt-0.5">Default: 450 ms</p>
                            </div>
                            <div>
                                <label for="critical_packetloss_threshold" class="block text-xs font-semibold text-red-400 mb-1">Critical Packet Loss (%)</label>
                                <input type="number" id="critical_packetloss_threshold" name="critical_packetloss_threshold" class="threshold-input w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm text-white" value="<?= htmlspecialchars($_POST['critical_packetloss_threshold'] ?? '80') ?>">
                                <p class="text-[10px] text-slate-400 mt-0.5">Default: 80%</p>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <div>
                    <label for="show_live_ping" class="flex items-center text-sm font-medium text-slate-400">
                        <input type="checkbox" id="show_live_ping" name="show_live_ping" class="h-4 w-4 rounded border-slate-500 bg-slate-700 text-cyan-600 focus:ring-cyan-500" <?= isset($_POST['show_live_ping']) ? 'checked' : '' ?>>
                        <span class="ml-2">Show live ping status on map</span>
                    </label>
                </div>

                <div class="flex justify-end gap-4 mt-6">
                    <button type="submit" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700">
                        <i class="fas fa-plus mr-2"></i>Add Device
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<!-- Load device icons library to JavaScript -->
<script>
    window.deviceIconsLibrary = <?= json_encode($deviceIconsLibrary, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>;
</script>

<!-- Load enhanced icon picker -->
<script src="assets/icon-picker.js?v=<?= time() ?>"></script>

<!-- Port Group Builder + Grid Visualization -->
<script>
(function() {
    const defaultGroupsByType = {
        switch:   [{ type:'GE', prefix:'G0/', start:1, count:24 }, { type:'SFP', prefix:'SFP', start:1, count:4 }],
        network_switch: [{ type:'GE', prefix:'G0/', start:1, count:24 }, { type:'SFP', prefix:'SFP', start:1, count:4 }],
        router:   [{ type:'GE', prefix:'G0/', start:0, count:4 }, { type:'Serial', prefix:'S0/', start:0, count:2 }, { type:'SFP', prefix:'SFP', start:1, count:1 }],
        firewall: [{ type:'GE', prefix:'G0/', start:0, count:8 }, { type:'Mgmt', prefix:'Mgmt0/', start:0, count:2 }],
        server:   [{ type:'GE', prefix:'G0/', start:0, count:4 }]
    };
    const typeColors = {GE:'#22d3ee', SFP:'#a78bfa', Serial:'#f59e0b', Mgmt:'#f472b6', Console:'#ec4899'};
    const portTypes = ['GE','SFP','Serial','Mgmt','Console'];
    const defaultPrefixes = {GE:'G0/', SFP:'SFP', Serial:'S0/', Mgmt:'Mgmt0/', Console:'Con'};

    function createPortGroupRow(group) {
        const row = document.createElement('div');
        row.className = 'port-group-row flex items-center gap-2';
        row.innerHTML = `
            <select class="pg-type bg-slate-900 border border-slate-600 rounded px-2 py-1 text-xs w-24">
                ${portTypes.map(t => `<option value="${t}" ${t===group.type?'selected':''}>${t}</option>`).join('')}
            </select>
            <input type="text" class="pg-prefix bg-slate-900 border border-slate-600 rounded px-2 py-1 text-xs w-20" value="${group.prefix}" placeholder="Prefix">
            <input type="number" class="pg-start bg-slate-900 border border-slate-600 rounded px-2 py-1 text-xs w-16" value="${group.start}" min="0" placeholder="Start">
            <input type="number" class="pg-count bg-slate-900 border border-slate-600 rounded px-2 py-1 text-xs w-16" value="${group.count}" min="1" placeholder="Count">
            <input type="text" class="pg-vlan bg-slate-900 border border-slate-600 rounded px-2 py-1 text-xs w-20" value="${group.vlan || ''}" placeholder="VLAN">
            <button type="button" class="pg-remove text-red-400 hover:text-red-300 text-xs px-1" title="Remove"><i class="fas fa-times"></i></button>
        `;
        row.querySelector('.pg-type').addEventListener('change', function() {
            row.querySelector('.pg-prefix').value = defaultPrefixes[this.value] || '';
            syncPortConfig();
        });
        row.querySelector('.pg-remove').addEventListener('click', function() { row.remove(); syncPortConfig(); });
        ['pg-prefix','pg-start','pg-count','pg-vlan'].forEach(cls => {
            row.querySelector('.'+cls).addEventListener('input', syncPortConfig);
        });
        return row;
    }

    function getPortGroups() {
        const groups = [];
        document.querySelectorAll('.port-group-row').forEach(row => {
            groups.push({
                type: row.querySelector('.pg-type').value,
                prefix: row.querySelector('.pg-prefix').value,
                start: parseInt(row.querySelector('.pg-start').value) || 0,
                count: parseInt(row.querySelector('.pg-count').value) || 0,
                vlan: row.querySelector('.pg-vlan').value.trim()
            });
        });
        return groups;
    }

    function expandGroups(groups) {
        const ports = [];
        groups.forEach(g => {
            for (let i = 0; i < g.count; i++) {
                ports.push({ name: g.prefix + (g.start + i), type: g.type, vlan: g.vlan || '' });
            }
        });
        return ports;
    }

    function syncPortConfig() {
        const groups = getPortGroups();
        document.getElementById('port_config').value = JSON.stringify(groups);
        renderPortGrid(groups);
    }

    function renderPortGrid(groups) {
        const ports = expandGroups(groups);
        const total = ports.length;
        document.getElementById('totalPortCount').textContent = total;
        document.getElementById('freePortCount').textContent = total;
        document.getElementById('usedPortCount').textContent = 0;

        const container = document.getElementById('portGridContainer');
        container.innerHTML = '';
        ports.forEach(function(p) {
            const color = typeColors[p.type] || '#94a3b8';
            const el = document.createElement('div');
            let tooltip = p.name + ' (' + p.type + ') — Free';
            if (p.vlan) tooltip += ' | VLAN ' + p.vlan;
            el.title = tooltip;
            el.style.cssText = 'width:36px;height:28px;border:2px solid '+color+';border-radius:4px;display:flex;align-items:center;justify-content:center;cursor:default;background:rgba(0,0,0,0.3);transition:all .15s;position:relative;';
            el.innerHTML = '<span style="font-size:8px;font-family:monospace;color:'+color+';font-weight:600;line-height:1;text-align:center;">'+p.name+'</span>'
                + '<span style="position:absolute;top:2px;right:2px;width:5px;height:5px;border-radius:50%;background:#22c55e;box-shadow:0 0 4px #22c55e;"></span>'
                + (p.vlan ? '<span style="position:absolute;bottom:1px;left:1px;font-size:6px;color:#fbbf24;font-weight:700;">V'+p.vlan+'</span>' : '');
            container.appendChild(el);
        });

        let legend = document.getElementById('portLegend');
        if (!legend) { legend = document.createElement('div'); legend.id = 'portLegend'; legend.style.cssText = 'margin-top:10px;display:flex;gap:12px;flex-wrap:wrap;'; container.parentNode.appendChild(legend); }
        legend.innerHTML = Object.entries(typeColors).map(e => '<span style="display:flex;align-items:center;gap:4px;font-size:11px;color:#94a3b8;"><span style="width:10px;height:10px;border-radius:2px;background:'+e[1]+';display:inline-block;"></span>'+e[0]+'</span>').join('');
    }

    function loadDefaultGroups(deviceType) {
        const container = document.getElementById('portGroupRows');
        container.innerHTML = '';
        const groups = defaultGroupsByType[deviceType] || [{ type:'GE', prefix:'G0/', start:0, count:2 }];
        groups.forEach(g => container.appendChild(createPortGroupRow(g)));
        syncPortConfig();
    }

    document.getElementById('addPortGroupBtn').addEventListener('click', function() {
        const container = document.getElementById('portGroupRows');
        container.appendChild(createPortGroupRow({ type:'GE', prefix:'G0/', start:0, count:1 }));
        syncPortConfig();
    });

    const typeSelect = document.getElementById('type');
    if (typeSelect) {
        typeSelect.addEventListener('change', function() { loadDefaultGroups(this.value); });
        loadDefaultGroups(typeSelect.value);
    }
})();

function toggleMonitoringMode(mode) {
    const cardTime = document.getElementById('card_mode_time_threshold');
    const cardPacket = document.getElementById('card_mode_packet_count');
    const sectionTime = document.getElementById('section_time_threshold');
    const sectionPacket = document.getElementById('section_packet_count');
    const threshContainer = document.getElementById('performance_thresholds_container');
    const threshNotice = document.getElementById('threshold_disabled_notice');
    const threshBadge = document.getElementById('threshold_status_badge');
    const threshInputs = document.querySelectorAll('.threshold-input');

    if (mode === 'packet_count') {
        if (cardTime) cardTime.className = 'relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 border-slate-800 bg-slate-950/60 hover:border-slate-700';
        if (cardPacket) cardPacket.className = 'relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 border-cyan-500 bg-cyan-950/30 ring-1 ring-cyan-500';
        if (threshContainer) threshContainer.classList.add('opacity-40', 'pointer-events-none', 'grayscale', 'select-none');
        if (threshNotice) threshNotice.classList.remove('hidden');
        if (threshBadge) {
            threshBadge.textContent = 'নিষ্ক্রিয় (Disabled)';
            threshBadge.className = 'text-[10px] px-2 py-0.5 rounded bg-slate-800 text-slate-400 border border-slate-700';
        }
        threshInputs.forEach(el => el.setAttribute('tabindex', '-1'));
    } else {
        if (cardTime) cardTime.className = 'relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 border-cyan-500 bg-cyan-950/30 ring-1 ring-cyan-500';
        if (cardPacket) cardPacket.className = 'relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition-all duration-200 border-slate-800 bg-slate-950/60 hover:border-slate-700';
        if (threshContainer) threshContainer.classList.remove('opacity-40', 'pointer-events-none', 'grayscale', 'select-none');
        if (threshNotice) threshNotice.classList.add('hidden');
        if (threshBadge) {
            threshBadge.textContent = 'সক্রিয় (Active)';
            threshBadge.className = 'text-[10px] px-2 py-0.5 rounded bg-emerald-900/50 text-emerald-300 border border-emerald-700/40';
        }
        threshInputs.forEach(el => el.removeAttribute('tabindex'));
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const selectedMode = document.querySelector('input[name="monitoring_mode"]:checked')?.value || 'time_threshold';
    toggleMonitoringMode(selectedMode);
});
</script>

<?php include 'footer.php'; ?>