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

switch ($action) {
    case 'get_menu_items':
        $stmt = $pdo->query("SELECT * FROM `menu_items` ORDER BY `parent_id` ASC, `sort_order` ASC, `title` ASC");
        $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($menus);
        break;

    case 'save_menu_item':
        // Ensure admin only
        if ($_SESSION['user_role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Admin only.']);
            exit;
        }
        
        $id = $input['id'] ?? null;
        $parent_id = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;
        $title = trim($input['title'] ?? '');
        $url = trim($input['url'] ?? '');
        $icon = trim($input['icon'] ?? '');
        $sort_order = isset($input['sort_order']) ? (int)$input['sort_order'] : 0;
        $role_required = $input['role_required'] ?? 'viewer';

        if (empty($title) || empty($url)) {
            http_response_code(400);
            echo json_encode(['error' => 'Title and URL are required.']);
            exit;
        }

        if ($id) {
            // Update
            $stmt = $pdo->prepare("UPDATE `menu_items` SET `parent_id` = ?, `title` = ?, `url` = ?, `icon` = ?, `sort_order` = ?, `role_required` = ? WHERE `id` = ?");
            $stmt->execute([$parent_id, $title, $url, $icon, $sort_order, $role_required, $id]);
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO `menu_items` (parent_id, title, url, icon, sort_order, role_required) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$parent_id, $title, $url, $icon, $sort_order, $role_required]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'delete_menu_item':
        // Ensure admin only
        if ($_SESSION['user_role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Admin only.']);
            exit;
        }
        
        $id = $input['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Menu Item ID is required.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM `menu_items` WHERE `id` = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    case 'get_theme_settings':
        // Return accent, navbar bg, text color from app_settings
        $keys = ['theme_accent_color', 'theme_navbar_bg', 'theme_text_color'];
        $settings = [];
        foreach ($keys as $key) {
            $stmt = $pdo->prepare("SELECT `setting_value` FROM `app_settings` WHERE `setting_key` = ? LIMIT 1");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $settings[$key] = $row ? $row['setting_value'] : null;
        }
        
        // Defaults
        if (empty($settings['theme_accent_color'])) $settings['theme_accent_color'] = '#06b6d4'; // Cyan-500
        if (empty($settings['theme_navbar_bg'])) $settings['theme_navbar_bg'] = '#0f172a';     // Slate-900
        if (empty($settings['theme_text_color'])) $settings['theme_text_color'] = '#cbd5e1';    // Slate-300

        echo json_encode($settings);
        break;

    case 'save_theme_settings':
        // Ensure admin only
        if ($_SESSION['user_role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Admin only.']);
            exit;
        }

        $theme_accent_color = trim($input['theme_accent_color'] ?? '');
        $theme_navbar_bg = trim($input['theme_navbar_bg'] ?? '');
        $theme_text_color = trim($input['theme_text_color'] ?? '');

        $settings = [
            'theme_accent_color' => $theme_accent_color,
            'theme_navbar_bg' => $theme_navbar_bg,
            'theme_text_color' => $theme_text_color
        ];

        foreach ($settings as $key => $value) {
            if ($value === '') continue;
            // Check if exists
            $stmt = $pdo->prepare("SELECT `id` FROM `app_settings` WHERE `setting_key` = ?");
            $stmt->execute([$key]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE `app_settings` SET `setting_value` = ? WHERE `setting_key` = ?");
                $stmt->execute([$value, $key]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
        }
        echo json_encode(['success' => true]);
        break;
}
