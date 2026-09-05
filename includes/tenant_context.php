<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * Multi-Tenant Workspace & Organization Context Manager
 */

class TenantContext
{
    /**
     * Get or initialize current active organization ID for user session
     */
    public static function getActiveOrgId(PDO $pdo, int $userId): string
    {
        if (!empty($_SESSION['active_org_id'])) {
            return $_SESSION['active_org_id'];
        }

        // 1. Check if user already belongs to an organization
        $stmt = $pdo->prepare("
            SELECT o.id, o.name, om.role 
            FROM organizations o
            JOIN organization_members om ON o.id = om.org_id
            WHERE om.user_id = ? AND o.status = 'active'
            ORDER BY o.created_at ASC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $org = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($org) {
            $_SESSION['active_org_id'] = $org['id'];
            $_SESSION['active_org_name'] = $org['name'];
            $_SESSION['active_org_role'] = $org['role'];
            return $org['id'];
        }

        // 2. Default initial workspace creation for user
        $orgId = self::createDefaultOrganization($pdo, $userId);
        $_SESSION['active_org_id'] = $orgId;
        $_SESSION['active_org_name'] = 'Default Workspace';
        $_SESSION['active_org_role'] = 'owner';
        return $orgId;
    }

    /**
     * Create default workspace for tenant
     */
    public static function createDefaultOrganization(PDO $pdo, int $userId): string
    {
        // Check if any organization exists in DB
        $stmt = $pdo->query("SELECT id, name FROM organizations WHERE status = 'active' ORDER BY created_at ASC LIMIT 1");
        $first = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($first) {
            // Assign user as member of the primary organization
            $stmt = $pdo->prepare("INSERT IGNORE INTO organization_members (id, org_id, user_id, role) VALUES (UUID(), ?, ?, 'admin')");
            $stmt->execute([$first['id'], $userId]);
            return $first['id'];
        }

        // Generate clean UUID v4
        $orgId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $stmt = $pdo->prepare("
            INSERT INTO organizations (id, name, slug, tier, max_devices, max_pollers, status)
            VALUES (?, 'Default Enterprise Network', 'default-org', 'enterprise', 500, 10, 'active')
        ");
        $stmt->execute([$orgId]);

        $stmt = $pdo->prepare("
            INSERT INTO organization_members (id, org_id, user_id, role)
            VALUES (UUID(), ?, ?, 'owner')
        ");
        $stmt->execute([$orgId, $userId]);

        return $orgId;
    }

    /**
     * Switch active organization for session
     */
    public static function switchOrganization(PDO $pdo, int $userId, string $orgId): bool
    {
        $stmt = $pdo->prepare("
            SELECT o.id, o.name, om.role 
            FROM organizations o
            JOIN organization_members om ON o.id = om.org_id
            WHERE om.user_id = ? AND o.id = ? AND o.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$userId, $orgId]);
        $org = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($org) {
            $_SESSION['active_org_id'] = $org['id'];
            $_SESSION['active_org_name'] = $org['name'];
            $_SESSION['active_org_role'] = $org['role'];
            return true;
        }

        return false;
    }

    /**
     * Get all organizations available for user
     */
    public static function getUserOrganizations(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare("
            SELECT o.id, o.name, o.slug, o.tier, o.max_devices, om.role
            FROM organizations o
            JOIN organization_members om ON o.id = om.org_id
            WHERE om.user_id = ? AND o.status = 'active'
            ORDER BY o.name ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
