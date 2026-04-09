window.MapApp = window.MapApp || {};

MapApp.utils = {
    getDefaultBoxStyle: () => ({
        width: 220,
        height: 120,
        borderWidth: 2,
        borderColor: '#475569',
        fillColor: 'rgba(49, 65, 85, 0.50)',
        labelAlign: 'center',
        labelVAdjust: 0
    }),

    getBoxStyleFromDevice: (deviceData) => {
        const defaults = MapApp.utils.getDefaultBoxStyle();
        if (!deviceData || !deviceData.port_config) return defaults;
        try {
            const parsed = typeof deviceData.port_config === 'string'
                ? JSON.parse(deviceData.port_config)
                : deviceData.port_config;
            const style = parsed?.box_style || {};
            return {
                ...defaults,
                ...style,
                width: Math.max(120, parseInt(style.width, 10) || defaults.width),
                height: Math.max(70, parseInt(style.height, 10) || defaults.height),
                borderWidth: Math.max(1, Math.min(12, parseInt(style.borderWidth, 10) || defaults.borderWidth)),
                labelVAdjust: Math.max(-120, Math.min(120, parseInt(style.labelVAdjust, 10) || 0))
            };
        } catch (e) {
            return defaults;
        }
    },

    withUpdatedBoxStyle: (deviceData, nextStyle) => {
        const current = (deviceData && deviceData.port_config)
            ? (typeof deviceData.port_config === 'string' ? (() => {
                try { return JSON.parse(deviceData.port_config); } catch (e) { return {}; }
            })() : { ...(deviceData.port_config || {}) })
            : {};
        current.box_style = {
            ...MapApp.utils.getDefaultBoxStyle(),
            ...current.box_style,
            ...(nextStyle || {})
        };
        return JSON.stringify(current);
    },

    buildVisBoxNode: (baseNode, deviceData) => {
        const style = MapApp.utils.getBoxStyleFromDevice(deviceData);
        return {
            ...baseNode,
            shape: 'box',
            color: {
                background: style.fillColor,
                border: style.borderColor
            },
            borderWidth: style.borderWidth,
            margin: 16,
            level: -1,
            widthConstraint: { minimum: style.width, maximum: style.width },
            heightConstraint: { minimum: style.height, maximum: style.height },
            font: {
                ...(baseNode.font || {}),
                align: style.labelAlign || 'center',
                vadjust: style.labelVAdjust || 0
            }
        };
    },

    getDefaultTooltipFields: () => ({
        ip: true,
        type: true,
        monitor: true,
        latency: true,
        ttl: true,
        interval: true,
        last_seen: true,
        description: true,
        ports: true
    }),

    getDefaultConnectionTooltipFields: () => ({
        type: true,
        source_target: true,
        status: true,
        ports: true
    }),

    getDefaultTooltipDisplaySettings: () => ({
        density: 'comfortable', // compact | comfortable
        font_scale: 100, // percentage
        max_width: 320,
        font_family: 'system', // system | inter | roboto | mono
        box_scale: 100,
        panel_bg_color: '#0f172a',
        panel_text_color: '#e2e8f0',
        panel_muted_color: '#94a3b8',
        panel_accent_color: '#22d3ee'
    }),

    getTooltipFontStack: (fontFamilyKey) => {
        const stacks = {
            system: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
            inter: "Inter, 'Segoe UI', Roboto, sans-serif",
            roboto: "Roboto, 'Segoe UI', Arial, sans-serif",
            mono: "'JetBrains Mono', 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace"
        };
        return stacks[fontFamilyKey] || stacks.system;
    },

    getCurrentTooltipFields: () => {
        const defaults = MapApp.utils.getDefaultTooltipFields();
        const currentMapId = MapApp.state?.currentMapId;
        if (!currentMapId) return defaults;
        let mapSettings = MapApp.state?.tooltipFieldSettingsByMap?.[currentMapId] || {};
        if ((!mapSettings || Object.keys(mapSettings).length === 0) && typeof localStorage !== 'undefined') {
            try {
                const raw = localStorage.getItem(`mapTooltipFields:${currentMapId}`);
                mapSettings = raw ? JSON.parse(raw) : {};
            } catch (error) {
                mapSettings = {};
            }
        }
        return { ...defaults, ...mapSettings };
    },

    getCurrentTooltipDisplaySettings: () => {
        const defaults = MapApp.utils.getDefaultTooltipDisplaySettings();
        const currentMapId = MapApp.state?.currentMapId;
        if (!currentMapId) return defaults;
        let mapSettings = MapApp.state?.tooltipDisplaySettingsByMap?.[currentMapId] || {};
        if ((!mapSettings || Object.keys(mapSettings).length === 0) && typeof localStorage !== 'undefined') {
            try {
                const raw = localStorage.getItem(`mapTooltipDisplay:${currentMapId}`);
                mapSettings = raw ? JSON.parse(raw) : {};
            } catch (error) {
                mapSettings = {};
            }
        }
        return { ...defaults, ...(mapSettings || {}) };
    },

    getCurrentConnectionTooltipFields: () => {
        const defaults = MapApp.utils.getDefaultConnectionTooltipFields();
        const currentMapId = MapApp.state?.currentMapId;
        if (!currentMapId) return defaults;
        let mapSettings = MapApp.state?.connectionTooltipFieldSettingsByMap?.[currentMapId] || {};
        if ((!mapSettings || Object.keys(mapSettings).length === 0) && typeof localStorage !== 'undefined') {
            try {
                const raw = localStorage.getItem(`mapConnectionTooltipFields:${currentMapId}`);
                mapSettings = raw ? JSON.parse(raw) : {};
            } catch (error) {
                mapSettings = {};
            }
        }
        return { ...defaults, ...(mapSettings || {}) };
    },

    buildNodeTitle: (deviceData) => {
        const statusColors = {
            online: '#22c55e', warning: '#eab308', critical: '#ef4444',
            offline: '#64748b', unknown: '#94a3b8'
        };
        const status = deviceData.status || 'unknown';
        const statusColor = statusColors[status] || statusColors.unknown;
        const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
        const tooltipFields = MapApp.utils.getCurrentTooltipFields();
        const tooltipDisplay = MapApp.utils.getCurrentTooltipDisplaySettings();
        const densityCompact = tooltipDisplay.density === 'compact';
        const fontScale = Math.min(130, Math.max(85, Number(tooltipDisplay.font_scale) || 100));
        const maxWidth = Math.min(480, Math.max(260, Number(tooltipDisplay.max_width) || 320));
        const boxScale = Math.min(130, Math.max(85, Number(tooltipDisplay.box_scale) || 100));
        const panelBgColor = tooltipDisplay.panel_bg_color || '#0f172a';
        const panelTextColor = tooltipDisplay.panel_text_color || '#e2e8f0';
        const panelMutedColor = tooltipDisplay.panel_muted_color || '#94a3b8';
        const panelAccentColor = tooltipDisplay.panel_accent_color || '#22d3ee';
        const fontStack = MapApp.utils.getTooltipFontStack(tooltipDisplay.font_family);
        const baseFont = Math.round((densityCompact ? 11 : 12) * (fontScale / 100));
        const headerFont = Math.round((densityCompact ? 13 : 14) * (fontScale / 100));
        const spacing = Math.round((densityCompact ? 3 : 5) * (boxScale / 100));
        const sectionMargin = Math.round((densityCompact ? 6 : 8) * (boxScale / 100));
        const minWidth = Math.round(230 * (boxScale / 100));
        const headerPaddingY = Math.round(2 * (boxScale / 100));
        const headerPaddingX = Math.round(8 * (boxScale / 100));

        let title = `<div style="font-family:${fontStack}; min-width:${minWidth}px; max-width:${maxWidth}px; padding:${Math.max(2, Math.round(4 * (boxScale / 100)))}px; line-height:1.35; background:${panelBgColor}; border:1px solid rgba(148,163,184,0.35); border-radius:8px;">`;

        // Header: Name + Status badge
        title += `<div style="display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:${sectionMargin}px;">`;
        title += `<b style="font-size:${headerFont}px; color:${panelTextColor};">${deviceData.name}</b>`;
        title += `<span style="display:inline-block; padding:${headerPaddingY}px ${headerPaddingX}px; border-radius:9999px; font-size:${Math.max(10, Math.round(11 * (fontScale / 100)))}px; font-weight:600; color:#fff; background:${statusColor};">${statusLabel}</span>`;
        title += `</div>`;

        // Divider
        title += `<div style="border-top:1px solid ${panelMutedColor}55; margin-bottom:${sectionMargin}px;"></div>`;

        // Details grid
        title += `<div style="display:grid; grid-template-columns: auto 1fr; gap: ${spacing}px 10px; font-size:${baseFont}px;">`;

        // IP
        if (tooltipFields.ip) {
            title += `<span style="color:${panelMutedColor};">IP Address:</span>`;
            title += `<span style="color:${panelTextColor}; font-family:monospace;">${deviceData.ip || 'N/A'}</span>`;
        }

        // Type
        if (tooltipFields.type) {
            const typeLabel = (deviceData.type || 'server').charAt(0).toUpperCase() + (deviceData.type || 'server').slice(1);
            title += `<span style="color:${panelMutedColor};">Type:</span>`;
            title += `<span style="color:${panelTextColor};">${typeLabel}${deviceData.subchoice ? ' (#' + deviceData.subchoice + ')' : ''}</span>`;
        }

        // Monitor method
        if (tooltipFields.monitor && deviceData.monitor_method) {
            title += `<span style="color:${panelMutedColor};">Monitor:</span>`;
            title += `<span style="color:${panelTextColor};">${deviceData.monitor_method}${deviceData.check_port ? ':' + deviceData.check_port : ''}</span>`;
        }

        // Latency
        if (tooltipFields.latency && deviceData.last_avg_time !== null && deviceData.last_avg_time !== undefined) {
            const latency = parseFloat(deviceData.last_avg_time);
            const latColor = latency < 50 ? '#22c55e' : latency < 150 ? '#eab308' : '#ef4444';
            title += `<span style="color:${panelMutedColor};">Latency:</span>`;
            title += `<span style="color:${latColor}; font-weight:600;">${latency}ms</span>`;
        }

        // TTL
        if (tooltipFields.ttl && deviceData.last_ttl) {
            title += `<span style="color:${panelMutedColor};">TTL:</span>`;
            title += `<span style="color:${panelTextColor};">${deviceData.last_ttl}</span>`;
        }

        // Ping interval
        if (tooltipFields.interval && deviceData.ping_interval) {
            title += `<span style="color:${panelMutedColor};">Interval:</span>`;
            title += `<span style="color:${panelTextColor};">${deviceData.ping_interval}s</span>`;
        }

        // Last seen
        if (tooltipFields.last_seen && deviceData.last_seen) {
            title += `<span style="color:${panelMutedColor};">Last Seen:</span>`;
            title += `<span style="color:${panelTextColor};">${deviceData.last_seen}</span>`;
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
            title += `<div style="margin-top:${sectionMargin}px; padding:6px 8px; background:rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.3); border-radius:6px; font-size:${Math.max(10, baseFont - 1)}px;">`;
            title += `<span style="color:#fca5a5; font-family:monospace;">⚠ ${sanitized}</span>`;
            title += `</div>`;
        }

        // Description
        if (tooltipFields.description && deviceData.description) {
            const desc = deviceData.description.replace(/</g, "&lt;").replace(/>/g, "&gt;");
            title += `<div style="margin-top:6px; font-size:${Math.max(10, baseFont - 1)}px; color:${panelMutedColor}; font-style:italic;">${desc}</div>`;
        }

        // Ports summary
        const ports = MapApp.utils.getPortsFromDevice(deviceData);
        if (tooltipFields.ports && ports.length > 0) {
            title += `<div style="border-top:1px solid ${panelMutedColor}55; margin-top:${sectionMargin}px; padding-top:6px;">`;
            title += `<div style="font-size:${Math.max(10, baseFont - 1)}px; font-weight:600; color:${panelAccentColor}; margin-bottom:4px;">Ports (${ports.length})</div>`;
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
                if (group.vlan) line += ` <span style="color:#fbbf24;font-size:${Math.max(10, baseFont - 2)}px;">[VLAN ${group.vlan}]</span>`;
                title += `<div style="font-size:${Math.max(10, baseFont - 1)}px; color:${panelTextColor};">${line}</div>`;
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
        const connectionTooltipFields = MapApp.utils.getCurrentConnectionTooltipFields();
        const tooltipDisplay = MapApp.utils.getCurrentTooltipDisplaySettings();
        const fontScale = Math.min(130, Math.max(85, Number(tooltipDisplay.font_scale) || 100));
        const maxWidth = Math.min(480, Math.max(260, Number(tooltipDisplay.max_width) || 320));
        const boxScale = Math.min(130, Math.max(85, Number(tooltipDisplay.box_scale) || 100));
        const panelBgColor = tooltipDisplay.panel_bg_color || '#0f172a';
        const panelTextColor = tooltipDisplay.panel_text_color || '#e2e8f0';
        const panelMutedColor = tooltipDisplay.panel_muted_color || '#94a3b8';
        const panelAccentColor = tooltipDisplay.panel_accent_color || '#22d3ee';
        const fontStack = MapApp.utils.getTooltipFontStack(tooltipDisplay.font_family);

        let title = `<div style="font-family:${fontStack}; min-width:${Math.round(200 * (boxScale / 100))}px; max-width:${maxWidth}px; padding:${Math.max(2, Math.round(4 * (boxScale / 100)))}px; background:${panelBgColor}; border:1px solid rgba(148,163,184,0.35); border-radius:8px;">`;

        // Header
        title += `<div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">`;
        title += `<div style="width:12px; height:3px; border-radius:2px; background:${panelAccentColor || connColor};"></div>`;
        title += `<b style="font-size:${Math.round(13 * (fontScale / 100))}px; color:${panelTextColor};">${connectionTooltipFields.type ? connLabel : 'Connection'}</b>`;
        title += `</div>`;

        title += `<div style="border-top:1px solid ${panelMutedColor}55; margin-bottom:8px;"></div>`;

        // Connected devices
        title += `<div style="display:grid; grid-template-columns: auto 1fr; gap: 4px 10px; font-size:${Math.round(12 * (fontScale / 100))}px;">`;

        const srcName = srcDevice ? srcDevice.name : 'Unknown';
        const tgtName = tgtDevice ? tgtDevice.name : 'Unknown';
        const srcStatus = srcDevice ? (srcDevice.status || 'unknown') : 'unknown';
        const tgtStatus = tgtDevice ? (tgtDevice.status || 'unknown') : 'unknown';
        const statusDotColors = { online: '#22c55e', warning: '#eab308', critical: '#ef4444', offline: '#64748b', unknown: '#94a3b8' };

        if (connectionTooltipFields.source_target) {
            title += `<span style="color:${panelMutedColor};">Source:</span>`;
            title += `<span style="color:${panelTextColor};">${srcName}</span>`;
            title += `<span style="color:${panelMutedColor};">Target:</span>`;
            title += `<span style="color:${panelTextColor};">${tgtName}</span>`;
        }

        if (connectionTooltipFields.status) {
            title += `<span style="color:${panelMutedColor};">Source Status:</span>`;
            title += `<span style="color:${panelTextColor};"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:${statusDotColors[srcStatus] || '#94a3b8'}; margin-right:4px;"></span>${srcStatus}</span>`;
            title += `<span style="color:${panelMutedColor};">Target Status:</span>`;
            title += `<span style="color:${panelTextColor};"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:${statusDotColors[tgtStatus] || '#94a3b8'}; margin-right:4px;"></span>${tgtStatus}</span>`;
        }

        // Port mapping
        if (connectionTooltipFields.ports && (edge.source_port_label || edge.target_port_label)) {
            title += `<span style="color:${panelMutedColor};">Ports:</span>`;
            title += `<span style="color:${panelAccentColor}; font-family:monospace; font-weight:600;">${edge.source_port_label || '—'} ↔ ${edge.target_port_label || '—'}</span>`;
        }

        title += `</div>`;

        // Port mapping visual
        if (connectionTooltipFields.ports && edge.source_port_label && edge.target_port_label) {
            title += `<div style="margin-top:8px; padding:6px 8px; background:${panelAccentColor}1A; border:1px solid ${panelAccentColor}55; border-radius:6px; text-align:center;">`;
            title += `<span style="font-size:11px; color:${panelMutedColor};">Port Mapping</span><br>`;
            title += `<span style="font-size:${Math.round(13 * (fontScale / 100))}px; font-family:monospace; color:${panelAccentColor}; font-weight:600;">${edge.source_port_label}</span>`;
            title += `<span style="font-size:11px; color:#64748b; margin:0 6px;">⟷</span>`;
            title += `<span style="font-size:${Math.round(13 * (fontScale / 100))}px; font-family:monospace; color:${panelAccentColor}; font-weight:600;">${edge.target_port_label}</span>`;
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
