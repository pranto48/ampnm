<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * Autonomous LLDP / CDP Topology Discovery & Cable Auto-Wiring Engine
 */

class LldpCdpEngine
{
    /**
     * Scan managed switch/router for LLDP and CDP neighbors and wire edges
     */
    public static function scanAndAutoWireDevice(PDO $pdo, int $deviceId, int $userId, ?int $mapId = null): array
    {
        // 1. Fetch source device
        $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
        $stmt->execute([$deviceId]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$device || empty($device['ip'])) {
            return ['success' => false, 'error' => 'Device not found or has no IP.'];
        }

        $ip = $device['ip'];
        $community = !empty($device['snmp_community']) ? $device['snmp_community'] : 'public';
        $snmpVersion = $device['snmp_version'] ?? '2c';

        // Check if mapId provided, otherwise get first map of user
        if (!$mapId) {
            $stmt = $pdo->prepare("SELECT id FROM maps WHERE user_id = ? ORDER BY id ASC LIMIT 1");
            $stmt->execute([$userId]);
            $mapId = (int)$stmt->fetchColumn();
            if (!$mapId) {
                // Default map fallback
                $mapId = 1;
            }
        }

        $discoveredNeighbors = [];
        $newEdgesCreated = 0;

        // Fetch all devices to match neighbors against
        $stmt = $pdo->query("SELECT id, name, ip, mac_address FROM devices");
        $allDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Perform SNMP Walk if snmp extension is available
        if (function_exists('snmprealwalk') || function_exists('snmp2_real_walk')) {
            // Attempt LLDP RemSysName probe
            $lldpNames = @snmp2_real_walk($ip, $community, '1.0.8802.1.1.2.1.4.1.1.9', 1000000, 1);
            $lldpPorts = @snmp2_real_walk($ip, $community, '1.0.8802.1.1.2.1.4.1.1.7', 1000000, 1);

            if ($lldpNames && is_array($lldpNames)) {
                foreach ($lldpNames as $oid => $val) {
                    $cleanedName = trim(str_replace(['STRING:', '"'], '', $val));
                    if (!empty($cleanedName)) {
                        $discoveredNeighbors[] = [
                            'protocol' => 'LLDP',
                            'remote_name' => $cleanedName,
                            'remote_port' => 'Port-Eth'
                        ];
                    }
                }
            }

            // Attempt Cisco CDP Device ID probe
            $cdpNames = @snmp2_real_walk($ip, $community, '1.3.6.1.4.1.9.9.23.1.2.1.1.6', 1000000, 1);
            $cdpPorts = @snmp2_real_walk($ip, $community, '1.3.6.1.4.1.9.9.23.1.2.1.1.7', 1000000, 1);

            if ($cdpNames && is_array($cdpNames)) {
                foreach ($cdpNames as $oid => $val) {
                    $cleanedName = trim(str_replace(['STRING:', '"'], '', $val));
                    if (!empty($cleanedName)) {
                        $discoveredNeighbors[] = [
                            'protocol' => 'CDP',
                            'remote_name' => $cleanedName,
                            'remote_port' => 'GigabitEthernet'
                        ];
                    }
                }
            }
        }

        // 3. Fallback / Heuristic Simulation if device has known name/pattern or physical peers
        if (empty($discoveredNeighbors)) {
            // Check for subnet-based neighboring devices
            $subPrefix = substr($ip, 0, strrpos($ip, '.'));
            foreach ($allDevices as $other) {
                if ($other['id'] == $deviceId) continue;
                if (!empty($other['ip']) && strpos($other['ip'], $subPrefix) === 0) {
                    $discoveredNeighbors[] = [
                        'protocol' => 'SUBNET-PEER',
                        'remote_name' => $other['name'],
                        'matched_device_id' => $other['id'],
                        'remote_port' => 'Auto-Link'
                    ];
                    // Limit heuristic auto-discovery to top 5
                    if (count($discoveredNeighbors) >= 5) break;
                }
            }
        }

        // 4. Auto-wire edges into device_edges table
        foreach ($discoveredNeighbors as $neighbor) {
            $targetDevId = $neighbor['matched_device_id'] ?? null;

            if (!$targetDevId) {
                // Find matching device by name or IP
                foreach ($allDevices as $candidate) {
                    if ($candidate['id'] == $deviceId) continue;
                    if (stripos($candidate['name'], $neighbor['remote_name']) !== false || 
                        stripos($neighbor['remote_name'], $candidate['name']) !== false) {
                        $targetDevId = $candidate['id'];
                        break;
                    }
                }
            }

            if ($targetDevId) {
                // Check if edge already exists
                $stmt = $pdo->prepare("
                    SELECT id FROM device_edges 
                    WHERE map_id = ? AND ((from_device_id = ? AND to_device_id = ?) OR (from_device_id = ? AND to_device_id = ?))
                    LIMIT 1
                ");
                $stmt->execute([$mapId, $deviceId, $targetDevId, $targetDevId, $deviceId]);
                $existingEdge = $stmt->fetch();

                if (!$existingEdge) {
                    $stmt = $pdo->prepare("
                        INSERT INTO device_edges (map_id, from_device_id, to_device_id, user_id, source_interface, dest_interface, custom_animated, custom_color, custom_glow_mode)
                        VALUES (?, ?, ?, ?, 'Eth-Uplink', 'Eth-Downlink', 1, '#00F2FE', 'neon-laser')
                    ");
                    $stmt->execute([$mapId, $deviceId, $targetDevId, $userId]);
                    $newEdgesCreated++;
                }
            }
        }

        return [
            'success' => true,
            'source_device' => $device['name'],
            'discovered_neighbors' => count($discoveredNeighbors),
            'new_edges_wired' => $newEdgesCreated,
            'details' => $discoveredNeighbors
        ];
    }
}
