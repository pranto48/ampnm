window.MapApp = window.MapApp || {};

MapApp.config = {
    API_URL: 'api.php',
    REFRESH_INTERVAL_SECONDS: 1, // Set to 1 for live updates
    offlineDelayMs: 5000, // Default, updated from map settings
    iconMap: {
        server: '\uf233', router: '\uf4d7', switch: '\uf796', printer: '\uf02f', nas: '\uf0a0',
        camera: '\uf030', other: '\uf108', firewall: '\uf3ed', ipphone: '\uf87d',
        punchdevice: '\uf2c2', 'wifi-router': '\uf1eb', 'radio-tower': '\uf519',
        rack: '\uf1b3', laptop: '\uf109', tablet: '\uf3fa', mobile: '\uf3cd',
        cloud: '\uf0c2', database: '\uf1c0', box: '\uf0fe'
    },
    statusColorMap: {
        online: '#22c55e', warning: '#f59e0b', critical: '#ef4444',
        offline: '#64748b', unknown: '#94a3b8'
    },
    edgeColorMap: {
        cat6: '#a78bfa',              // Purple - CAT6 Cable
        fiber: '#f97316',             // Orange - Fiber Optic
        wifi: '#38bdf8',              // Light Blue - WiFi
        radio: '#84cc16',             // Lime Green - Radio
        lan: '#60a5fa',               // Blue - LAN
        'logical-tunneling': '#c084fc' // Light Purple - Logical Tunneling
    },
    edgeLabelMap: {
        cat6: '🔌 CAT6',
        fiber: '💡 Fiber',
        wifi: '📡 WiFi',
        radio: '📻 Radio',
        lan: '🌐 LAN',
        'logical-tunneling': '🔒 Tunnel'
    }
};