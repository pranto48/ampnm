window.MapApp = window.MapApp || {};

MapApp.utils = {
    buildNodeTitle: (deviceData) => {
        let title = `${deviceData.name}<br>${deviceData.ip || 'No IP'}<br>Status: ${deviceData.status}`;
        if (deviceData.status === 'offline' && deviceData.last_ping_output) {
            const lines = deviceData.last_ping_output.split('\n');
            let reason = 'No response';
            for (const line of lines) {
                if (line.toLowerCase().includes('unreachable') || line.toLowerCase().includes('timed out') || line.toLowerCase().includes('could not find host')) {
                    reason = line.trim();
                    break;
                }
            }
            const sanitizedReason = reason.replace(/</g, "&lt;").replace(/>/g, "&gt;");
            title += `<br><small style="color: #fca5a5; font-family: monospace;">${sanitizedReason}</small>`;
        }

        // Get ports from port_config or fallback to type-based defaults
        const ports = MapApp.utils.getPortsFromDevice(deviceData);
        if (ports.length > 0) {
            title += `<br><br><b>Ports (${ports.length})</b>:<br>`;
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
                title += line + '<br>';
            }
        }

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
                            ports.push({ name: (g.prefix || '') + ((g.start || 0) + i), type: g.type || 'Port' });
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
            return { name, type };
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
