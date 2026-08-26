<?php
/**
 * AMPNM Planned Maintenance Windows & Alert Silence Engine
 * Copyright (c) IT Support BD. All rights reserved.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config.php';

class MaintenanceEngine {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? getDbConnection();
    }

    /**
     * Check if a specific device is currently in an active maintenance window
     */
    public function isDeviceInMaintenance(string $deviceId): bool {
        $now = date('Y-m-d H:i:s');
        
        // 1. Direct device target or global maintenance
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM maintenance_windows 
            WHERE (target_type = 'all' OR (target_type = 'device' AND target_id = ?))
            AND start_time <= ? AND end_time >= ?");
        $stmt->execute([$deviceId, $now, $now]);
        if ((int)$stmt->fetchColumn() > 0) {
            return true;
        }

        // 2. Checked via maintenance device assignment table
        $stmt2 = $this->pdo->prepare("SELECT COUNT(*) FROM maintenance_device_assignments a
            JOIN maintenance_windows w ON a.maintenance_id = w.id
            WHERE a.device_id = ? AND w.start_time <= ? AND w.end_time >= ?");
        $stmt2->execute([$deviceId, $now, $now]);
        return (int)$stmt2->fetchColumn() > 0;
    }

    /**
     * Fetch all active maintenance windows
     */
    public function getActiveWindows(): array {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("SELECT * FROM maintenance_windows 
            WHERE start_time <= ? AND end_time >= ? 
            ORDER BY start_time ASC");
        $stmt->execute([$now, $now]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Fetch all upcoming and past maintenance windows
     */
    public function getAllWindows(int $limit = 50): array {
        $stmt = $this->pdo->prepare("SELECT w.*, 
            (SELECT COUNT(*) FROM maintenance_device_assignments a WHERE a.maintenance_id = w.id) as assigned_device_count
            FROM maintenance_windows w 
            ORDER BY w.start_time DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Schedule a new maintenance window
     */
    public function scheduleWindow(array $data, array $deviceIds = []): string {
        $id = generateUuid();
        $stmt = $this->pdo->prepare("INSERT INTO maintenance_windows 
            (id, title, target_type, target_id, start_time, end_time, suppress_alerts, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $id,
            $data['title'] ?? 'Scheduled System Maintenance',
            $data['target_type'] ?? 'device',
            $data['target_id'] ?? null,
            $data['start_time'],
            $data['end_time'],
            isset($data['suppress_alerts']) ? (int)$data['suppress_alerts'] : 1,
            $data['notes'] ?? ''
        ]);

        if (!empty($deviceIds)) {
            $ins = $this->pdo->prepare("INSERT IGNORE INTO maintenance_device_assignments (id, maintenance_id, device_id) VALUES (?, ?, ?)");
            foreach ($deviceIds as $devId) {
                $ins->execute([generateUuid(), $id, $devId]);
            }
        }

        return $id;
    }
}
