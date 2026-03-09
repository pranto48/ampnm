window.MapApp = window.MapApp || {};

MapApp.utils = {
    buildNodeTitle: (deviceData) => {
        const statusColors = {
            online: '#22c55e', warning: '#eab308', critical: '#ef4444',
            offline: '#64748b', unknown: '#94a3b8'
        };
        const status = deviceData.status || 'unknown';
        const statusColor = statusColors[status] || statusColors.unknown;
        const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);

        let title = `<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; min-width: 220px; max-width: 320px; padding: 2px;">`;

        // Header: Name + Status badge
        title += `<div style="display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:8px;">`;
        title += `<b style="font-size:14px; color:#f1f5f9;">${deviceData.name}</b>`;
        title += `<span style="display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; color:#fff; background:${statusColor};">${statusLabel}</span>`;
        title += `</div>`;

        // Divider
        title += `<div style="border-top:1px solid rgba(148,163,184,0.2); margin-bottom:8px;"></div>`;

        // Details grid
        title += `<div style="display:grid; grid-template-columns: auto 1fr; gap: 4px 10px; font-size:12px;">`;

        // IP
        title += `<span style="color:#94a3b8;">IP Address:</span>`;
        title += `<span style="color:#e2e8f0; font-family:monospace;">${deviceData.ip || 'N/A'}</span>`;

        // Type
        const typeLabel = (deviceData.type || 'server').charAt(0).toUpperCase() + (deviceData.type || 'server').slice(1);
        title += `<span style="color:#94a3b8;">Type:</span>`;
        title += `<span style="color:#e2e8f0;">${typeLabel}${deviceData.subchoice ? ' (#' + deviceData.subchoice + ')' : ''}</span>`;

        // Monitor method
        if (deviceData.monitor_method) {
            title += `<span style="color:#94a3b8;">Monitor:</span>`;
            title += `<span style="color:#e2e8f0;">${deviceData.monitor_method}${deviceData.check_port ? ':' + deviceData.check_port : ''}</span>`;
        }

        // Latency
        if (deviceData.last_avg_time !== null && deviceData.last_avg_time !== undefined) {
            const latency = parseFloat(deviceData.last_avg_time);
            const latColor = latency < 50 ? '#22c55e' : latency < 150 ? '#eab308' : '#ef4444';
            title += `<span style="color:#94a3b8;">Latency:</span>`;
            title += `<span style="color:${latColor}; font-weight:600;">${latency}ms</span>`;
        }

        // TTL
        if (deviceData.last_ttl) {
            title += `<span style="color:#94a3b8;">TTL:</span>`;
            title += `<span style="color:#e2e8f0;">${deviceData.last_ttl}</span>`;
        }

        // Ping interval
        if (deviceData.ping_interval) {
            title += `<span style="color:#94a3b8;">Interval:</span>`;
            title += `<span style="color:#e2e8f0;">${deviceData.ping_interval}s</span>`;
        }

        // Last seen
        if (deviceData.last_seen) {
            title += `<span style="color:#94a3b8;">Last Seen:</span>`;
            title += `<span style="color:#e2e8f0;">${deviceData.last_seen}</span>`;
        }

        title += `</div>`;

        // Offline reason
        if (status === 'offline' && deviceData.last_ping_output) {
            const lines = deviceData.last_ping_output.split('\n');
            let reason = 'No response';
            for (const line of lines) {
                if (line.toLowerCase().includes('unreachable') || line.toLowerCase().includes('timed out') || line.toLowerCase().includes('could not find host')) {
                    reason = line.trim(); break;
                }
            }
            const sanitized = reason.replace(/</g, "&lt;").replace(/>/g, "&gt;");
            title += `<div style="margin-top:8px; padding:6px 8px; background:rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.3); border-radius:6px; font-size:11px;">`;
            title += `<span style="color:#fca5a5; font-family:monospace;">⚠ ${sanitized}</span>`;
            title += `</div>`;
        }

        // Description
        if (deviceData.description) {
            const desc = deviceData.description.replace(/</g, "&lt;").replace(/>/g, "&gt;");
            title += `<div style="margin-top:6px; font-size:11px; color:#94a3b8; font-style:italic;">${desc}</div>`;
        }

        // Ports summary
        const ports = MapApp.utils.getPortsFromDevice(deviceData);
        if (ports.length > 0) {
            title += `<div style="border-top:1px solid rgba(148,163,184,0.2); margin-top:8px; padding-top:6px;">`;
            title += `<div style="font-size:11px; font-weight:600; color:#cbd5e1; margin-bottom:4px;">Ports (${ports.length})</div>`;
            const portGroups = {};
            ports.forEach(p => {
                const key = p.type || (p.name.startsWith('G') ? 'GE' : p.name.startsWith('S0') ? 'Serial' : p.name.startsWith('SFP') ? 'SFP' : p.name.startsWith('Mgmt') ? 'Mgmt' : 'Port');
                if (!portGroups[key]) { portGroups[key] = { names: [], vlan: p.vlan || '' }; }
                portGroups[key].names.push(p.name);
                if (p.vlan) portGroups[key].vlan = p.vlan;
            });
            const colorMap = {GE:'#22d3ee', SFP:'#a78bfa', Serial:'#f59e0b', Mgmt:'#f472b6', Console:'#ec4899', Port:'#94a3b8'};
            for (const [type, group] of Object.entries(portGroups)) {
                const color = colorMap[type] || '#94a3b8';
                let line = `<span style="color:${color}">■</span> ${type}: ${group.names.length}x (${group.names[0]}–${group.names[group.names.length-1]})`;
                if (group.vlan) line += ` <span style="color:#fbbf24;font-size:10px;">[VLAN ${group.vlan}]</span>`;
                title += `<div style="font-size:11px; color:#e2e8f0;">${line}</div>`;
            }
            title += `</div>`;
        }

        title += `</div>`;
        return title;
    },

    /**
     * Get structured port list from device's port_config or type-based defaults
     * Returns array of {name, type} objects
     */
    getPortsFromDevice: (deviceData) => {
        // Try custom port_config first
        if (deviceData.port_config) {
            try {
                const groups = typeof deviceData.port_config === 'string' ? JSON.parse(deviceData.port_config) : deviceData.port_config;
                if (Array.isArray(groups) && groups.length > 0) {
                    const ports = [];
                    groups.forEach(g => {
                        for (let i = 0; i < (g.count || 0); i++) {
                            ports.push({ name: (g.prefix || '') + ((g.start || 0) + i), type: g.type || 'Port', vlan: g.vlan || '' });
                        }
                    });
                    return ports;
                }
            } catch (e) { /* fall through */ }
        }
        // Fallback: generate from type
        const rawPorts = MapApp.utils.getPortsForType(deviceData.type);
        return rawPorts.map(name => {
            const type = name.startsWith('G') ? 'GE' : name.startsWith('S0') ? 'Serial' : name.startsWith('SFP') ? 'SFP' : name.startsWith('Mgmt') ? 'Mgmt' : 'Port';
            return { name, type, vlan: '' };
        });
    },

    getPortsForType: (deviceType) => {
        const ports = [];
        const dt = (deviceType || '').toLowerCase();
        if (dt === 'switch' || dt === 'network_switch' || dt.includes('switch')) {
            for (let i = 1; i <= 24; i++) ports.push('G0/' + i);
            for (let i = 1; i <= 4; i++) ports.push('SFP0' + i);
        } else if (dt === 'router' || dt.includes('router')) {
            for (let i = 0; i <= 3; i++) ports.push('G0/' + i);
            for (let i = 0; i <= 1; i++) ports.push('S0/' + i);
            ports.push('SFP01');
        } else if (dt === 'firewall' || dt.includes('firewall') || dt.includes('security')) {
            for (let i = 0; i <= 7; i++) ports.push('G0/' + i);
            for (let i = 0; i <= 1; i++) ports.push('Mgmt0/' + i);
        } else if (dt === 'server' || dt.includes('server')) {
            for (let i = 0; i <= 3; i++) ports.push('G0/' + i);
        } else {
            for (let i = 0; i <= 1; i++) ports.push('G0/' + i);
        }
        return ports;
    },

    buildPublicMapUrl: (mapId) => {
        const { protocol, hostname, port } = window.location;
        const effectivePort = port || '2266';
        const portSegment = effectivePort ? `:${effectivePort}` : '';
        return `${protocol}//${hostname}${portSegment}/public_map.php?map_id=${mapId}`;
    },

    /**
     * Build rich HTML tooltip for a connection edge
     */
    buildEdgeTitle: (edge, srcDevice, tgtDevice) => {
        const typeLabels = {
            cat6: '🔌 CAT6 Cable', cat5: '🔌 CAT5 Cable', fiber: '💡 Fiber Optic',
            wifi: '📡 WiFi', radio: '📻 Radio', lan: '🌐 LAN',
            'logical-tunneling': '🔒 Logical Tunnel'
        };
        const typeColors = {
            cat6: '#a78bfa', cat5: '#a78bfa', fiber: '#f97316', wifi: '#38bdf8',
            radio: '#84cc16', lan: '#60a5fa', 'logical-tunneling': '#c084fc'
        };
        const connType = edge.connection_type || 'unknown';
        const connLabel = typeLabels[connType] || connType;
        const connColor = typeColors[connType] || '#94a3b8';

        let title = `<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; min-width: 200px; max-width: 300px; padding: 2px;">`;

        // Header
        title += `<div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">`;
        title += `<div style="width:12px; height:3px; border-radius:2px; background:${connColor};"></div>`;
        title += `<b style="font-size:13px; color:#f1f5f9;">${connLabel}</b>`;
        title += `</div>`;

        title += `<div style="border-top:1px solid rgba(148,163,184,0.2); margin-bottom:8px;"></div>`;

        // Connected devices
        title += `<div style="display:grid; grid-template-columns: auto 1fr; gap: 4px 10px; font-size:12px;">`;

        const srcName = srcDevice ? srcDevice.name : 'Unknown';
        const tgtName = tgtDevice ? tgtDevice.name : 'Unknown';
        const srcStatus = srcDevice ? (srcDevice.status || 'unknown') : 'unknown';
        const tgtStatus = tgtDevice ? (tgtDevice.status || 'unknown') : 'unknown';
        const statusDotColors = { online: '#22c55e', warning: '#eab308', critical: '#ef4444', offline: '#64748b', unknown: '#94a3b8' };

        title += `<span style="color:#94a3b8;">Source:</span>`;
        title += `<span style="color:#e2e8f0;"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:${statusDotColors[srcStatus] || '#94a3b8'}; margin-right:4px;"></span>${srcName}</span>`;

        title += `<span style="color:#94a3b8;">Target:</span>`;
        title += `<span style="color:#e2e8f0;"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:${statusDotColors[tgtStatus] || '#94a3b8'}; margin-right:4px;"></span>${tgtName}</span>`;

        // Port mapping
        if (edge.source_port_label || edge.target_port_label) {
            title += `<span style="color:#94a3b8;">Ports:</span>`;
            title += `<span style="color:#22d3ee; font-family:monospace; font-weight:600;">${edge.source_port_label || '—'} ↔ ${edge.target_port_label || '—'}</span>`;
        }

        title += `</div>`;

        // Port mapping visual
        if (edge.source_port_label && edge.target_port_label) {
            title += `<div style="margin-top:8px; padding:6px 8px; background:rgba(6,182,212,0.1); border:1px solid rgba(6,182,212,0.2); border-radius:6px; text-align:center;">`;
            title += `<span style="font-size:11px; color:#94a3b8;">Port Mapping</span><br>`;
            title += `<span style="font-size:13px; font-family:monospace; color:#22d3ee; font-weight:600;">${edge.source_port_label}</span>`;
            title += `<span style="font-size:11px; color:#64748b; margin:0 6px;">⟷</span>`;
            title += `<span style="font-size:13px; font-family:monospace; color:#22d3ee; font-weight:600;">${edge.target_port_label}</span>`;
            title += `</div>`;
        }

        title += `</div>`;
        return title;
    },

    /**
     * Get Font Awesome icon class for a device based on type and subchoice
     * @param {string} deviceType - Device type key (e.g., 'router', 'server', 'wifi')
     * @param {number|string} subchoice - Icon variant index (0-based)
     * @returns {string} Font Awesome class (e.g., 'fa-network-wired')
     */
    getDeviceIconClass: (deviceType, subchoice) => {
        // Default icon if library is not loaded
        const defaultIcon = 'fa-circle';
        
        // Check if device icons library is available
        if (!window.deviceIconsLibrary) {
            console.warn('Device icons library not loaded');
            return defaultIcon;
        }

        // Get the type data
        const typeData = window.deviceIconsLibrary[deviceType];
        if (!typeData || !typeData.icons) {
            console.warn(`Unknown device type: ${deviceType}`);
            return defaultIcon;
        }

        // Parse subchoice as integer
        const index = parseInt(subchoice, 10) || 0;
        
        // Get the icon at the specified index
        const iconData = typeData.icons[index];
        if (!iconData || !iconData.icon) {
            console.warn(`No icon found for type '${deviceType}' at index ${index}`);
            // Fallback to first icon of the type
            return typeData.icons[0]?.icon || defaultIcon;
        }

        return iconData.icon;
    }
};
