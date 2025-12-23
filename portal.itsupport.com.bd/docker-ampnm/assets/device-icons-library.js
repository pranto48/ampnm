/**
 * Device Icons Library for Network Monitoring
 * Organized by device categories with multiple icon variants
 */

window.deviceIconsLibrary = {
    // Network Switches
    switch: {
        label: 'Network Switches',
        category: 'network',
        icons: [
            { id: 'switch-default', icon: 'fa-network-wired', label: 'Switch (Default)' },
            { id: 'switch-managed', icon: 'fa-sitemap', label: 'Managed Switch' },
            { id: 'switch-layer2', icon: 'fa-layer-group', label: 'Layer 2 Switch' },
            { id: 'switch-layer3', icon: 'fa-project-diagram', label: 'Layer 3 Switch' },
            { id: 'switch-poe', icon: 'fa-plug', label: 'PoE Switch' },
            { id: 'switch-stackable', icon: 'fa-th-list', label: 'Stackable Switch' },
            { id: 'switch-industrial', icon: 'fa-industry', label: 'Industrial Switch' },
            { id: 'switch-small', icon: 'fa-ethernet', label: 'Desktop Switch' }
        ]
    },

    // Routers
    router: {
        label: 'Routers',
        category: 'network',
        icons: [
            { id: 'router-default', icon: 'fa-route', label: 'Router (Default)' },
            { id: 'router-wireless', icon: 'fa-wifi', label: 'Wireless Router' },
            { id: 'router-edge', icon: 'fa-border-all', label: 'Edge Router' },
            { id: 'router-core', icon: 'fa-circle-nodes', label: 'Core Router' },
            { id: 'router-vpn', icon: 'fa-shield-halved', label: 'VPN Router' },
            { id: 'router-gateway', icon: 'fa-door-open', label: 'Gateway Router' },
            { id: 'router-mesh', icon: 'fa-diagram-project', label: 'Mesh Router' },
            { id: 'router-lte', icon: 'fa-signal', label: 'LTE/4G Router' }
        ]
    },

    // Servers
    server: {
        label: 'Servers',
        category: 'compute',
        icons: [
            { id: 'server-default', icon: 'fa-server', label: 'Server (Default)' },
            { id: 'server-rack', icon: 'fa-hard-drive', label: 'Rack Server' },
            { id: 'server-blade', icon: 'fa-bars', label: 'Blade Server' },
            { id: 'server-tower', icon: 'fa-building', label: 'Tower Server' },
            { id: 'server-database', icon: 'fa-database', label: 'Database Server' },
            { id: 'server-web', icon: 'fa-globe', label: 'Web Server' },
            { id: 'server-mail', icon: 'fa-envelope', label: 'Mail Server' },
            { id: 'server-file', icon: 'fa-folder-open', label: 'File Server' },
            { id: 'server-vm', icon: 'fa-cubes', label: 'Virtual Host' },
            { id: 'server-cluster', icon: 'fa-circle-nodes', label: 'Server Cluster' }
        ]
    },

    // Firewalls & Security
    firewall: {
        label: 'Firewalls & Security',
        category: 'security',
        icons: [
            { id: 'firewall-default', icon: 'fa-fire', label: 'Firewall (Default)' },
            { id: 'firewall-utm', icon: 'fa-shield', label: 'UTM Firewall' },
            { id: 'firewall-ngfw', icon: 'fa-shield-virus', label: 'NGFW' },
            { id: 'firewall-waf', icon: 'fa-globe-americas', label: 'Web App Firewall' },
            { id: 'security-ids', icon: 'fa-eye', label: 'IDS/IPS' },
            { id: 'security-vpn', icon: 'fa-lock', label: 'VPN Concentrator' },
            { id: 'security-proxy', icon: 'fa-user-shield', label: 'Proxy Server' },
            { id: 'security-nac', icon: 'fa-id-card', label: 'NAC Appliance' }
        ]
    },

    // Wireless Access Points
    wireless: {
        label: 'Wireless Devices',
        category: 'network',
        icons: [
            { id: 'ap-default', icon: 'fa-wifi', label: 'Access Point' },
            { id: 'ap-outdoor', icon: 'fa-satellite-dish', label: 'Outdoor AP' },
            { id: 'ap-controller', icon: 'fa-tower-broadcast', label: 'Wireless Controller' },
            { id: 'ap-mesh', icon: 'fa-diagram-project', label: 'Mesh AP' },
            { id: 'wireless-bridge', icon: 'fa-link', label: 'Wireless Bridge' },
            { id: 'wireless-extender', icon: 'fa-expand', label: 'Range Extender' }
        ]
    },

    // Storage Devices
    storage: {
        label: 'Storage Devices',
        category: 'storage',
        icons: [
            { id: 'storage-nas', icon: 'fa-hdd', label: 'NAS Storage' },
            { id: 'storage-san', icon: 'fa-database', label: 'SAN Storage' },
            { id: 'storage-backup', icon: 'fa-box-archive', label: 'Backup Appliance' },
            { id: 'storage-tape', icon: 'fa-tape', label: 'Tape Library' },
            { id: 'storage-raid', icon: 'fa-clone', label: 'RAID Array' },
            { id: 'storage-cloud', icon: 'fa-cloud', label: 'Cloud Storage' }
        ]
    },

    // End User Devices
    workstation: {
        label: 'Workstations & Endpoints',
        category: 'endpoint',
        icons: [
            { id: 'pc-desktop', icon: 'fa-desktop', label: 'Desktop PC' },
            { id: 'pc-laptop', icon: 'fa-laptop', label: 'Laptop' },
            { id: 'pc-mac', icon: 'fa-apple-whole', label: 'Mac Computer' },
            { id: 'workstation-thin', icon: 'fa-display', label: 'Thin Client' },
            { id: 'workstation-terminal', icon: 'fa-terminal', label: 'Terminal' },
            { id: 'workstation-kiosk', icon: 'fa-tv', label: 'Kiosk' }
        ]
    },

    // Mobile Devices
    mobile: {
        label: 'Mobile Devices',
        category: 'endpoint',
        icons: [
            { id: 'mobile-phone', icon: 'fa-mobile-screen', label: 'Smartphone' },
            { id: 'mobile-tablet', icon: 'fa-tablet-screen-button', label: 'Tablet' },
            { id: 'mobile-pda', icon: 'fa-mobile-retro', label: 'PDA/Scanner' },
            { id: 'mobile-voip', icon: 'fa-phone', label: 'VoIP Phone' }
        ]
    },

    // Printers & Peripherals
    printer: {
        label: 'Printers & Peripherals',
        category: 'peripheral',
        icons: [
            { id: 'printer-default', icon: 'fa-print', label: 'Printer' },
            { id: 'printer-network', icon: 'fa-print', label: 'Network Printer' },
            { id: 'printer-mfp', icon: 'fa-copy', label: 'MFP/Copier' },
            { id: 'scanner-default', icon: 'fa-barcode', label: 'Scanner' },
            { id: 'camera-ip', icon: 'fa-video', label: 'IP Camera' },
            { id: 'camera-security', icon: 'fa-camera', label: 'Security Camera' }
        ]
    },

    // IoT & Smart Devices
    iot: {
        label: 'IoT & Smart Devices',
        category: 'iot',
        icons: [
            { id: 'iot-sensor', icon: 'fa-microchip', label: 'Sensor' },
            { id: 'iot-controller', icon: 'fa-sliders', label: 'IoT Controller' },
            { id: 'iot-hub', icon: 'fa-house-signal', label: 'Smart Hub' },
            { id: 'iot-thermostat', icon: 'fa-temperature-half', label: 'Thermostat' },
            { id: 'iot-light', icon: 'fa-lightbulb', label: 'Smart Light' },
            { id: 'iot-lock', icon: 'fa-lock', label: 'Smart Lock' },
            { id: 'iot-speaker', icon: 'fa-volume-high', label: 'Smart Speaker' }
        ]
    },

    // Power & Infrastructure
    power: {
        label: 'Power & Infrastructure',
        category: 'infrastructure',
        icons: [
            { id: 'ups-default', icon: 'fa-battery-full', label: 'UPS' },
            { id: 'pdu-default', icon: 'fa-plug-circle-bolt', label: 'PDU' },
            { id: 'generator', icon: 'fa-bolt', label: 'Generator' },
            { id: 'cooling', icon: 'fa-fan', label: 'Cooling Unit' },
            { id: 'rack-cabinet', icon: 'fa-server', label: 'Rack Cabinet' }
        ]
    },

    // Cloud & Virtual
    cloud: {
        label: 'Cloud & Virtual',
        category: 'cloud',
        icons: [
            { id: 'cloud-default', icon: 'fa-cloud', label: 'Cloud Service' },
            { id: 'cloud-aws', icon: 'fa-aws', label: 'AWS' },
            { id: 'cloud-azure', icon: 'fa-microsoft', label: 'Azure' },
            { id: 'cloud-gcp', icon: 'fa-google', label: 'Google Cloud' },
            { id: 'vm-default', icon: 'fa-cube', label: 'Virtual Machine' },
            { id: 'container', icon: 'fa-box', label: 'Container' },
            { id: 'kubernetes', icon: 'fa-dharmachakra', label: 'Kubernetes' }
        ]
    },

    // Network Services
    service: {
        label: 'Network Services',
        category: 'service',
        icons: [
            { id: 'dns-server', icon: 'fa-compass', label: 'DNS Server' },
            { id: 'dhcp-server', icon: 'fa-network-wired', label: 'DHCP Server' },
            { id: 'ldap-server', icon: 'fa-address-book', label: 'LDAP/AD Server' },
            { id: 'ntp-server', icon: 'fa-clock', label: 'NTP Server' },
            { id: 'radius-server', icon: 'fa-key', label: 'RADIUS Server' },
            { id: 'syslog-server', icon: 'fa-scroll', label: 'Syslog Server' },
            { id: 'monitoring', icon: 'fa-chart-line', label: 'Monitoring System' }
        ]
    },

    // Generic/Other
    generic: {
        label: 'Generic Devices',
        category: 'other',
        icons: [
            { id: 'device-unknown', icon: 'fa-question-circle', label: 'Unknown Device' },
            { id: 'device-generic', icon: 'fa-microchip', label: 'Generic Device' },
            { id: 'internet', icon: 'fa-globe', label: 'Internet' },
            { id: 'cloud-network', icon: 'fa-cloud', label: 'Cloud Network' },
            { id: 'site-location', icon: 'fa-location-dot', label: 'Site/Location' },
            { id: 'building', icon: 'fa-building', label: 'Building' },
            { id: 'floor', icon: 'fa-layer-group', label: 'Floor' },
            { id: 'room', icon: 'fa-door-closed', label: 'Room' }
        ]
    }
};

// Helper function to get all icons flat
window.getAllDeviceIcons = function() {
    const allIcons = [];
    Object.entries(window.deviceIconsLibrary).forEach(([type, data]) => {
        data.icons.forEach(icon => {
            allIcons.push({
                ...icon,
                type: type,
                typeLabel: data.label,
                category: data.category
            });
        });
    });
    return allIcons;
};

// Helper function to get icons by category
window.getIconsByCategory = function(category) {
    return Object.entries(window.deviceIconsLibrary)
        .filter(([_, data]) => data.category === category)
        .reduce((acc, [type, data]) => {
            acc[type] = data;
            return acc;
        }, {});
};

// Get unique categories
window.getIconCategories = function() {
    const categories = new Set();
    Object.values(window.deviceIconsLibrary).forEach(data => {
        categories.add(data.category);
    });
    return Array.from(categories);
};
