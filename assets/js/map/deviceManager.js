/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
window.MapApp = window.MapApp || {};

MapApp.deviceManager = {
    getStatusNotificationMessage: (deviceData, status) => {
        const name = deviceData.name || 'Device';
        const isPacketMode = (deviceData.monitoring_mode === 'packet_count');
        const critPackets = deviceData.critical_packet_count || 20;
        const offPackets = deviceData.offline_packet_count || 30;

        if (status === 'critical') {
            if (isPacketMode) {
                return `Device '${name}' is Critical (${critPackets} packets lost / 100% loss).`;
            }
            return `Device '${name}' is Critical (20s unreachable).`;
        }
        if (status === 'offline') {
            if (isPacketMode) {
                return `Device '${name}' is now Offline (${offPackets} packets lost / 100% loss).`;
            }
            return `Device '${name}' is now Offline (30s+ down).`;
        }
        if (status === 'online') {
            if (isPacketMode) {
                return `Device '${name}' is back online (Packets: Sent = ${critPackets}, Received = ${critPackets}, Lost = 0).`;
            }
            return `Device '${name}' is back online.`;
        }
        return `Device '${name}' is ${status}.`;
    },

    pingSingleDevice: async (deviceId) => {
        const node = MapApp.state.nodes.get(deviceId);
        if (!node || node.deviceData.type === 'box') return;
        
        const oldStatus = node.deviceData.status;
        // No blue flicker – device keeps its current color silently
        
        try {
            const result = await MapApp.api.post('check_device', { id: deviceId });
            const rawStatus = result.status;
            const isPacketMode = (result.monitoring_mode === 'packet_count' || node.deviceData.monitoring_mode === 'packet_count');

            let newStatus = rawStatus;
            const isUnreachable = (rawStatus === 'critical' && result.last_ping_output && result.last_ping_output.includes('packet loss')) || rawStatus === 'offline';

            if (!isPacketMode && isUnreachable) {
                if (!MapApp.state.deviceFirstFailTime[deviceId]) {
                    // First failure: record 20s elapsed as this is a 20s interval failure
                    MapApp.state.deviceFirstFailTime[deviceId] = Date.now() - 20000;
                }
                const failElapsedMs = Date.now() - MapApp.state.deviceFirstFailTime[deviceId];
                const offlineThresholdMs = MapApp.config.offlineDelayMs || 30000;

                if (failElapsedMs < offlineThresholdMs) {
                    newStatus = 'critical';
                    // Auto-schedule precise check at 30s mark (approx 10s remaining)
                    if (!MapApp.state.offlineTimers) MapApp.state.offlineTimers = {};
                    if (MapApp.state.offlineTimers[deviceId]) clearTimeout(MapApp.state.offlineTimers[deviceId]);
                    const remainingMs = Math.max(1000, offlineThresholdMs - failElapsedMs);
                    MapApp.state.offlineTimers[deviceId] = setTimeout(() => {
                        if (MapApp.state.deviceFirstFailTime[deviceId]) {
                            MapApp.deviceManager.pingSingleDevice(deviceId);
                        }
                    }, remainingMs);
                } else {
                    newStatus = 'offline';
                }
            } else {
                // Success, packet mode, or normal online status
                delete MapApp.state.deviceFirstFailTime[deviceId];
                if (MapApp.state.offlineTimers && MapApp.state.offlineTimers[deviceId]) {
                    clearTimeout(MapApp.state.offlineTimers[deviceId]);
                    delete MapApp.state.offlineTimers[deviceId];
                }
            }

            const updatedDeviceData = { 
                ...node.deviceData, 
                status: newStatus, 
                monitoring_mode: result.monitoring_mode || node.deviceData.monitoring_mode || 'time_threshold',
                critical_packet_count: result.critical_packet_count || node.deviceData.critical_packet_count || 20,
                offline_packet_count: result.offline_packet_count || node.deviceData.offline_packet_count || 30,
                last_avg_time: result.last_avg_time, 
                last_ttl: result.last_ttl, 
                last_ping_output: result.last_ping_output 
            };

            if (newStatus !== oldStatus) {
                if (newStatus === 'warning') {
                    SoundManager.play('warning');
                } else if (newStatus === 'critical') {
                    SoundManager.play('critical');
                    window.notyf.error({ message: MapApp.deviceManager.getStatusNotificationMessage(updatedDeviceData, newStatus), duration: 5000, dismissible: true });
                } else if (newStatus === 'offline') {
                    SoundManager.play('offline');
                    window.notyf.error({ message: MapApp.deviceManager.getStatusNotificationMessage(updatedDeviceData, newStatus), duration: 5000, dismissible: true });
                } else if (newStatus === 'online' && (oldStatus === 'offline' || oldStatus === 'critical' || oldStatus === 'warning')) {
                    SoundManager.play('online');
                    window.notyf.success({ message: MapApp.deviceManager.getStatusNotificationMessage(updatedDeviceData, newStatus), duration: 5000 });
                }
            }
            let label = updatedDeviceData.name;
            if (updatedDeviceData.show_live_ping && updatedDeviceData.status === 'online' && updatedDeviceData.last_avg_time !== null) {
                label += `\n${updatedDeviceData.last_avg_time}ms | TTL:${updatedDeviceData.last_ttl || 'N/A'}`;
            }
            
            const borderCol = MapApp.config.statusColorMap[newStatus] || MapApp.config.statusColorMap.unknown;
            const updatePayload = { 
                id: deviceId, 
                deviceData: updatedDeviceData, 
                title: MapApp.utils.buildNodeTitle(updatedDeviceData), 
                label: label 
            };
            if (node.shape === 'image') {
                updatePayload.color = { border: borderCol, background: 'transparent' };
            } else {
                updatePayload.icon = { ...node.icon, color: borderCol };
            }
            MapApp.state.nodes.update(updatePayload);
            MapApp.ui.updateStaticEdgeColors();
        } catch (error) {
            console.error("Failed to ping device:", error);
            // Silent – no error toast for transient network issues
            const borderCol = MapApp.config.statusColorMap[oldStatus] || MapApp.config.statusColorMap.unknown;
            const errorPayload = { id: deviceId };
            if (node.shape === 'image') {
                errorPayload.color = { border: borderCol, background: 'transparent' };
            } else {
                errorPayload.icon = { ...node.icon, color: borderCol };
            }
            MapApp.state.nodes.update(errorPayload);
        }
    },

    performBulkRefresh: async () => {
        const icon = MapApp.ui.els.refreshStatusBtn.querySelector('i');
        icon.classList.add('fa-spin');
        
        try {
            const result = await MapApp.api.post('ping_all_devices', { map_id: MapApp.state.currentMapId });
            
            if (!result.success) {
                console.error("Bulk refresh API returned failure:", result);
                throw new Error(result.message || 'Failed to refresh device statuses due to an unknown server issue.');
            }
            if (!result.updated_devices) {
                console.error("Bulk refresh API returned no updated_devices:", result);
                throw new Error('Invalid response from server during bulk refresh: missing device data.');
            }

            let statusChanges = 0;
            const nodeUpdates = result.updated_devices.map(device => {
                const node = MapApp.state.nodes.get(device.id);
                if (!node) return null;

                const oldStatus = device.old_status;
                const rawStatus = device.status;

                // --- Mode-based failure logic for bulk refresh ---
                let effectiveStatus = rawStatus;
                const isPacketMode = (device.monitoring_mode === 'packet_count' || node.deviceData.monitoring_mode === 'packet_count');
                const isUnreachable = (rawStatus === 'critical' && device.last_ping_output && device.last_ping_output.includes('packet loss')) || rawStatus === 'offline';

                if (!isPacketMode && isUnreachable) {
                    if (!MapApp.state.deviceFirstFailTime[device.id]) {
                        MapApp.state.deviceFirstFailTime[device.id] = Date.now() - 20000;
                    }
                    const failElapsedMs = Date.now() - MapApp.state.deviceFirstFailTime[device.id];
                    const offlineThresholdMs = MapApp.config.offlineDelayMs || 30000;

                    if (failElapsedMs < offlineThresholdMs) {
                        effectiveStatus = 'critical';
                    } else {
                        effectiveStatus = 'offline';
                    }
                } else {
                    delete MapApp.state.deviceFirstFailTime[device.id];
                    if (MapApp.state.offlineTimers && MapApp.state.offlineTimers[device.id]) {
                        clearTimeout(MapApp.state.offlineTimers[device.id]);
                        delete MapApp.state.offlineTimers[device.id];
                    }
                }

                if (effectiveStatus !== oldStatus) {
                    statusChanges++;
                    if (effectiveStatus === 'warning') {
                        SoundManager.play('warning');
                    } else if (effectiveStatus === 'critical') {
                        SoundManager.play('critical');
                        window.notyf.error({ message: MapApp.deviceManager.getStatusNotificationMessage(device, effectiveStatus), duration: 5000, dismissible: true });
                    } else if (effectiveStatus === 'offline') {
                        SoundManager.play('offline');
                        window.notyf.error({ message: MapApp.deviceManager.getStatusNotificationMessage(device, effectiveStatus), duration: 5000, dismissible: true });
                    } else if (effectiveStatus === 'online' && (oldStatus === 'offline' || oldStatus === 'critical' || oldStatus === 'warning')) {
                        SoundManager.play('online');
                        window.notyf.success({ message: MapApp.deviceManager.getStatusNotificationMessage(device, effectiveStatus), duration: 5000 });
                    } else {
                        window.notyf.open({ type: 'info', message: `Device '${device.name}' changed status to ${effectiveStatus}.`, duration: 5000 });
                    }
                }

                const updatedDeviceData = { ...node.deviceData, ...device, status: effectiveStatus };
                let label = updatedDeviceData.name;
                if (updatedDeviceData.show_live_ping && updatedDeviceData.status === 'online' && updatedDeviceData.last_avg_time !== null) {
                    label += `\n${updatedDeviceData.last_avg_time}ms | TTL:${updatedDeviceData.last_ttl || 'N/A'}`;
                }
                
                const borderCol = MapApp.config.statusColorMap[effectiveStatus] || MapApp.config.statusColorMap.unknown;
                const updateItem = {
                    id: device.id,
                    deviceData: updatedDeviceData,
                    title: MapApp.utils.buildNodeTitle(updatedDeviceData),
                    label: label
                };
                if (node.shape === 'image') {
                    updateItem.color = { border: borderCol, background: 'transparent' };
                } else {
                    updateItem.icon = { ...node.icon, color: borderCol };
                }
                return updateItem;
            }).filter(Boolean);

            if (nodeUpdates.length > 0) {
                MapApp.state.nodes.update(nodeUpdates);
                MapApp.ui.updateStaticEdgeColors();
            }

            // Removed "All device statuses are stable" toast to reduce notification noise

            return result.updated_devices.length;

        } catch (error) {
            console.error("An error occurred during the bulk refresh process:", error);
            window.notyf.error(error.message || "Failed to refresh device statuses.");
            return 0;
        } finally {
            icon.classList.remove('fa-spin');
        }
    },

    setupAutoPing: (devices) => {
        Object.values(MapApp.state.pingIntervals).forEach(clearInterval);
        MapApp.state.pingIntervals = {};
        // Reset failure timestamps when setting up fresh
        MapApp.state.deviceFirstFailTime = {};
        // Enable auto-ping functionality for all roles
        devices.forEach(device => {
            if (device.ping_interval > 0 && device.ip) {
                MapApp.state.pingIntervals[device.id] = setInterval(() => MapApp.deviceManager.pingSingleDevice(device.id), device.ping_interval * 1000);
            }
        });
    },

    // --- New Agent Registration Detection ---
    startAgentPolling: () => {
        // Load known hostnames from localStorage
        const stored = localStorage.getItem('ampnm_known_hostnames');
        if (stored) {
            try {
                JSON.parse(stored).forEach(h => MapApp.state.knownHostnames.add(h));
            } catch (e) { /* ignore */ }
        }

        // Initial fetch to seed known hosts (no notifications on first load)
        MapApp.deviceManager._fetchAndCheckAgents(true);

        // Poll every 10 seconds
        MapApp.state.agentPollIntervalId = setInterval(() => {
            MapApp.deviceManager._fetchAndCheckAgents(false);
        }, 10000);
    },

    stopAgentPolling: () => {
        if (MapApp.state.agentPollIntervalId) {
            clearInterval(MapApp.state.agentPollIntervalId);
            MapApp.state.agentPollIntervalId = null;
        }
    },

    _fetchAndCheckAgents: async (isSeed) => {
        try {
            const res = await fetch(`${MapApp.config.API_URL}?action=get_all_hosts&handler=metrics`);
            if (!res.ok) return;
            const hosts = await res.json();
            if (!Array.isArray(hosts)) return;

            hosts.forEach(host => {
                const hostname = host.host_name || host.hostname || host.name;
                if (!hostname) return;

                if (!MapApp.state.knownHostnames.has(hostname)) {
                    MapApp.state.knownHostnames.add(hostname);
                    // Persist
                    localStorage.setItem('ampnm_known_hostnames', JSON.stringify([...MapApp.state.knownHostnames]));

                    if (!isSeed) {
                        // Show notification for new agent
                        const ip = host.host_ip || host.ip_address || '';
                        const msg = ip
                            ? `New agent registered: ${hostname} (${ip})`
                            : `New agent registered: ${hostname}`;
                        window.notyf.success({ message: msg, duration: 8000, dismissible: true });

                        // Play online sound for new agent
                        const agentSoundPref = localStorage.getItem('ampnm_agent_sound') !== 'false';
                        if (agentSoundPref) {
                            SoundManager.play('online');
                        }
                    }
                }
            });
        } catch (e) {
            // Silently fail – host metrics API may not exist in all setups
        }
    }
};
