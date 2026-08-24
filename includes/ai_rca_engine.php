<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * AMPNM AI Root Cause Analysis (RCA) & Dependency Alert Suppressor Engine
 */

class AMPNM_AiRcaEngine {

    /**
     * Analyze current network outage graph to identify root causes and suppress downstream alerts.
     */
    public static function analyzeOutages($pdo) {
        // 1. Get all offline/down devices
        $offlineDevices = $pdo->query("SELECT id, name, ip, type, map_id FROM devices WHERE status = 'offline'")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($offlineDevices)) {
            return [
                'has_outage' => false,
                'root_causes' => [],
                'suppressed_count' => 0
            ];
        }

        $offlineMap = [];
        foreach ($offlineDevices as $d) {
            $offlineMap[$d['id']] = $d;
        }

        // 2. Fetch all topology connections
        $edges = $pdo->query("SELECT id, map_id, source_id, target_id FROM device_edges")->fetchAll(PDO::FETCH_ASSOC);

        // Build directed / undirected dependency adjacency
        $adj = [];
        $downstreamMap = [];
        foreach ($edges as $e) {
            $u = $e['source_id'];
            $v = $e['target_id'];
            if (!isset($adj[$u])) $adj[$u] = [];
            if (!isset($adj[$v])) $adj[$v] = [];
            $adj[$u][] = $v;
            $adj[$v][] = $u;

            // Treat source as parent/upstream and target as downstream
            if (!isset($downstreamMap[$u])) $downstreamMap[$u] = [];
            $downstreamMap[$u][] = $v;
        }

        $rootCauses = [];
        $suppressedIds = [];

        // Identify root nodes (nodes that are offline and have offline downstream dependents)
        foreach ($offlineDevices as $dev) {
            $devId = $dev['id'];
            $downstreamOffline = [];

            // Check if this device connects downstream to other offline devices
            $visited = [];
            $queue = [$devId];
            while (!empty($queue)) {
                $curr = array_shift($queue);
                foreach ($downstreamMap[$curr] ?? [] as $child) {
                    if (!isset($visited[$child]) && isset($offlineMap[$child])) {
                        $visited[$child] = true;
                        $downstreamOffline[] = $child;
                        $queue[] = $child;
                    }
                }
            }

            if (count($downstreamOffline) > 0) {
                $confidence = min(99.5, 85.0 + (count($downstreamOffline) * 3.5));
                $rootCauses[] = [
                    'root_device_id' => $devId,
                    'root_device_name' => $dev['name'],
                    'root_device_ip' => $dev['ip'],
                    'root_device_type' => $dev['type'],
                    'impact_count' => count($downstreamOffline),
                    'confidence_percent' => round($confidence, 1),
                    'suppressed_device_ids' => $downstreamOffline,
                    'summary' => "AI RCA identified {$dev['name']} as primary root failure causing " . count($downstreamOffline) . " downstream dependent device(s) to become unreachable."
                ];
                $suppressedIds = array_merge($suppressedIds, $downstreamOffline);
            }
        }

        $suppressedIds = array_unique($suppressedIds);

        // If no topological root was found, every down device is standalone
        if (empty($rootCauses) && count($offlineDevices) > 0) {
            foreach ($offlineDevices as $dev) {
                $rootCauses[] = [
                    'root_device_id' => $dev['id'],
                    'root_device_name' => $dev['name'],
                    'root_device_ip' => $dev['ip'],
                    'root_device_type' => $dev['type'],
                    'impact_count' => 0,
                    'confidence_percent' => 100.0,
                    'suppressed_device_ids' => [],
                    'summary' => "Standalone device outage: {$dev['name']} is unreachable."
                ];
            }
        }

        return [
            'has_outage' => true,
            'total_down_devices' => count($offlineDevices),
            'root_cause_count' => count($rootCauses),
            'suppressed_count' => count($suppressedIds),
            'suppressed_device_ids' => $suppressedIds,
            'root_causes' => $rootCauses
        ];
    }
}
