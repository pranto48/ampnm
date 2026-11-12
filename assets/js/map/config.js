/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
window.MapApp = window.MapApp || {};

MapApp.config = {
    API_URL: 'api.php',
    REFRESH_INTERVAL_SECONDS: 1, // Set to 1 for live updates
    offlineDelayMs: 30000, // Standard 30s offline threshold, updated from map settings
    iconMap: {
        server: '\uf233', router: '\uf4d7', switch: '\uf796', printer: '\uf02f', nas: '\uf0a0',
        camera: '\uf030', other: '\uf108', firewall: '\uf3ed', ipphone: '\uf87d',
        punchdevice: '\uf2c2', 'wifi-router': '\uf1eb', 'radio-tower': '\uf519',
        rack: '\uf1b3', laptop: '\uf109', tablet: '\uf3fa', mobile: '\uf3cd',
        cloud: '\uf0c2', database: '\uf1c0', box: '\uf0fe'
    },
    statusColorMap: {
        online: '#2ECC71', warning: '#F1C40F', critical: '#E74C3C',
        offline: '#95A5A6', unknown: '#95A5A6'
    },
    connectionTypeDefinitions: [
        { key: 'cat6', label: 'CAT6 Cable', shortLabel: 'CAT6 Cable', emoji: '🔌', defaultColor: '#a78bfa', defaultDashed: false },
        { key: 'fiber', label: 'Fiber Optic', shortLabel: 'Fiber Optic', emoji: '💡', defaultColor: '#f97316', defaultDashed: false },
        { key: 'wifi', label: 'WiFi', shortLabel: 'WiFi', emoji: '📡', defaultColor: '#38bdf8', defaultDashed: true },
        { key: 'radio', label: 'Radio', shortLabel: 'Radio', emoji: '📻', defaultColor: '#84cc16', defaultDashed: true },
        { key: 'lan', label: 'LAN', shortLabel: 'LAN', emoji: '🌐', defaultColor: '#60a5fa', defaultDashed: false },
        { key: 'logical-tunneling', label: 'Logical Tunneling', shortLabel: 'Tunnel', emoji: '🔒', defaultColor: '#c084fc', defaultDashed: true }
    ],
    connectionColorPresets: {
        default: {
            label: 'Default',
            colors: {
                cat6: '#a78bfa',
                fiber: '#f97316',
                wifi: '#38bdf8',
                radio: '#84cc16',
                lan: '#60a5fa',
                'logical-tunneling': '#c084fc'
            }
        },
        'cyber-neon': {
            label: 'Cyber Neon',
            colors: {
                cat6: '#00f2fe',
                fiber: '#ff6b00',
                wifi: '#38bdf8',
                radio: '#39ff14',
                lan: '#3b82f6',
                'logical-tunneling': '#d946ef'
            }
        },
        'high-contrast': {
            label: 'High Contrast',
            colors: {
                cat6: '#facc15',
                fiber: '#ef4444',
                wifi: '#22d3ee',
                radio: '#4ade80',
                lan: '#60a5fa',
                'logical-tunneling': '#f472b6'
            }
        },
        pastel: {
            label: 'Soft Pastel',
            colors: {
                cat6: '#c4b5fd',
                fiber: '#fdba74',
                wifi: '#7dd3fc',
                radio: '#bef264',
                lan: '#93c5fd',
                'logical-tunneling': '#f0abfc'
            }
        }
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