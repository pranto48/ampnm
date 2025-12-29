window.MapApp = window.MapApp || {};

MapApp.utils = {
    buildNodeTitle: (deviceData) => {
        const escapeHtml = (value) => {
            if (value === null || value === undefined) return '';
            return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        };

        const formatValue = (value, suffix = '') => {
            if (value === null || value === undefined || value === '') return 'N/A';
            return `${value}${suffix}`;
        };

        const lastSeen = deviceData.last_seen ? new Date(deviceData.last_seen) : null;
        const lastSeenLabel = lastSeen && !Number.isNaN(lastSeen.getTime())
            ? lastSeen.toLocaleString()
            : 'Never';

        const name = escapeHtml(deviceData.name || 'Unknown Device');
        const ip = escapeHtml(deviceData.ip || 'No IP');
        const type = escapeHtml(deviceData.type || 'Unknown');
        const status = escapeHtml(deviceData.status || 'unknown');

        let title = [
            `Host: ${name}`,
            `IP: ${ip}`,
            `Status: ${status}`,
            `Device Type: ${type}`,
            `Last Check: ${escapeHtml(lastSeenLabel)}`,
            `Warn Latency: ${escapeHtml(formatValue(deviceData.warning_latency_threshold, ' ms'))}`,
            `Warn Packet Loss: ${escapeHtml(formatValue(deviceData.warning_packetloss_threshold, ' %'))}`,
            `Critical Latency: ${escapeHtml(formatValue(deviceData.critical_latency_threshold, ' ms'))}`,
            `Critical Packet Loss: ${escapeHtml(formatValue(deviceData.critical_packetloss_threshold, ' %'))}`
        ].join('<br>');

        if (deviceData.status === 'offline' && deviceData.last_ping_output) {
            const lines = deviceData.last_ping_output.split('\n');
            let reason = 'No response';
            for (const line of lines) {
                if (line.toLowerCase().includes('unreachable') || line.toLowerCase().includes('timed out') || line.toLowerCase().includes('could not find host')) {
                    reason = line.trim();
                    break;
                }
            }
            const sanitizedReason = escapeHtml(reason);
            title += `<br><small style="color: #fca5a5; font-family: monospace;">${sanitizedReason}</small>`;
        }
        return title;
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

        // Get the type data (flat structure: library[deviceType])
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
