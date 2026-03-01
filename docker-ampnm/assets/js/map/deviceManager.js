window.MapApp = window.MapApp || {};

MapApp.deviceManager = {
    pingSingleDevice: async (deviceId) => {
        const node = MapApp.state.nodes.get(deviceId);
        if (!node || node.deviceData.type === 'box') return;
        
        const oldStatus = node.deviceData.status;
        // No blue flicker – device keeps its current color silently
        
        try {
            const result = await MapApp.api.post('check_device', { id: deviceId });
            const rawStatus = result.status;

            // --- Time-based failure logic ---
            let newStatus = rawStatus;
            if (rawStatus === 'offline') {
                if (!MapApp.state.deviceFirstFailTime[deviceId]) {
                    // First failure – record timestamp, keep old status silently
                    MapApp.state.deviceFirstFailTime[deviceId] = Date.now();
                    newStatus = oldStatus !== 'unknown' ? oldStatus : 'offline';
                } else if (Date.now() - MapApp.state.deviceFirstFailTime[deviceId] < MapApp.config.offlineDelayMs) {
                    // Less than 5 seconds since first failure – keep old status
                    newStatus = oldStatus !== 'unknown' ? oldStatus : 'offline';
                }
                // else: 5+ seconds elapsed, newStatus stays 'offline'
            } else {
                // Success or non-offline status → clear timestamp, show immediately
                delete MapApp.state.deviceFirstFailTime[deviceId];
            }

            if (newStatus !== oldStatus) {
                if (newStatus === 'warning') {
                    SoundManager.play('warning');
                } else if (newStatus === 'critical') {
                    SoundManager.play('critical');
                } else if (newStatus === 'offline') {
                    SoundManager.play('offline');
                } else if (newStatus === 'online' && (oldStatus === 'offline' || oldStatus === 'critical' || oldStatus === 'warning')) {
                    SoundManager.play('online');
                }

                if (newStatus === 'critical' || newStatus === 'offline') {
                    window.notyf.error({ message: `Device '${node.deviceData.name}' is now ${newStatus}.`, duration: 5000, dismissible: true });
                } else if (newStatus === 'online' && (oldStatus === 'critical' || oldStatus === 'offline')) {
                    window.notyf.success({ message: `Device '${node.deviceData.name}' is back online.`, duration: 5000 });
                }
            }

            const updatedDeviceData = { ...node.deviceData, status: newStatus, last_avg_time: result.last_avg_time, last_ttl: result.last_ttl, last_ping_output: result.last_ping_output };
            let label = updatedDeviceData.name;
            if (updatedDeviceData.show_live_ping && updatedDeviceData.status === 'online' && updatedDeviceData.last_avg_time !== null) {
                label += `\n${updatedDeviceData.last_avg_time}ms | TTL:${updatedDeviceData.last_ttl || 'N/A'}`;
            }
            
            MapApp.state.nodes.update({ id: deviceId, deviceData: updatedDeviceData, icon: { ...node.icon, color: MapApp.config.statusColorMap[newStatus] || MapApp.config.statusColorMap.unknown }, title: MapApp.utils.buildNodeTitle(updatedDeviceData), label: label });
        } catch (error) {
            console.error("Failed to ping device:", error);
            // Silent – no error toast for transient network issues
            MapApp.state.nodes.update({ id: deviceId, icon: { ...node.icon, color: MapApp.config.statusColorMap[oldStatus] || MapApp.config.statusColorMap.unknown } });
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

                // --- Time-based failure logic for bulk refresh ---
                let effectiveStatus = rawStatus;
                if (rawStatus === 'offline') {
                    if (!MapApp.state.deviceFirstFailTime[device.id]) {
                        MapApp.state.deviceFirstFailTime[device.id] = Date.now();
                        effectiveStatus = oldStatus !== 'unknown' ? oldStatus : 'offline';
                    } else if (Date.now() - MapApp.state.deviceFirstFailTime[device.id] < MapApp.config.offlineDelayMs) {
                        effectiveStatus = oldStatus !== 'unknown' ? oldStatus : 'offline';
                    }
                } else {
                    delete MapApp.state.deviceFirstFailTime[device.id];
                }

                if (effectiveStatus !== oldStatus) {
                    statusChanges++;
                    if (effectiveStatus === 'warning') {
                        SoundManager.play('warning');
                    } else if (effectiveStatus === 'critical') {
                        SoundManager.play('critical');
                    } else if (effectiveStatus === 'offline') {
                        SoundManager.play('offline');
                    } else if (effectiveStatus === 'online' && (oldStatus === 'offline' || oldStatus === 'critical' || oldStatus === 'warning')) {
                        SoundManager.play('online');
                    }
                    
                    if (effectiveStatus === 'critical' || effectiveStatus === 'offline') {
                        window.notyf.error({ message: `Device '${device.name}' is now ${effectiveStatus}.`, duration: 5000, dismissible: true });
                    } else if (effectiveStatus === 'online' && (oldStatus === 'critical' || oldStatus === 'offline')) {
                        window.notyf.success({ message: `Device '${device.name}' is back online.`, duration: 5000 });
                    } else {
                        window.notyf.open({ type: 'info', message: `Device '${device.name}' changed status to ${effectiveStatus}.`, duration: 5000 });
                    }
                }

                const updatedDeviceData = { ...node.deviceData, ...device, status: effectiveStatus };
                let label = updatedDeviceData.name;
                if (updatedDeviceData.show_live_ping && updatedDeviceData.status === 'online' && updatedDeviceData.last_avg_time !== null) {
                    label += `\n${updatedDeviceData.last_avg_time}ms | TTL:${updatedDeviceData.last_ttl || 'N/A'}`;
                }
                
                return {
                    id: device.id,
                    deviceData: updatedDeviceData,
                    icon: { ...node.icon, color: MapApp.config.statusColorMap[effectiveStatus] || MapApp.config.statusColorMap.unknown },
                    title: MapApp.utils.buildNodeTitle(updatedDeviceData),
                    label: label
                };
            }).filter(Boolean);

            if (nodeUpdates.length > 0) {
                MapApp.state.nodes.update(nodeUpdates);
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
