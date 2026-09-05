/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
const mapId = new URLSearchParams(window.location.search).get("map_id");
const canvas = document.getElementById("mapCanvas");
const loader = document.getElementById("mapLoader");
const errorCard = document.getElementById("mapError");
const statusMessage = document.getElementById("statusMessage");
const metaSummary = document.getElementById("metaSummary");
const mapTitle = document.getElementById("mapTitle");
const mapSubtitle = document.getElementById("mapSubtitle");
const copyLinkBtn = document.getElementById("copyLinkBtn");

// --- Icon & Config ---
const statusColorMap = {
    online: '#22c55e', warning: '#f59e0b', critical: '#ef4444',
    offline: '#64748b', unknown: '#94a3b8'
};

const edgeColorMap = {
    cat5: '#a78bfa', fiber: '#f97316', wifi: '#38bdf8',
    radio: '#84cc16', lan: '#60a5fa', 'logical-tunneling': '#c084fc'
};

const edgeDashMap = {
    cat5: false, fiber: false, wifi: true,
    radio: true, lan: false, 'logical-tunneling': [6, 4]
};

const edgeLabelMap = {
    cat5: '🔌 CAT5', fiber: '💡 Fiber', wifi: '📡 WiFi',
    radio: '📻 Radio', lan: '🌐 LAN', 'logical-tunneling': '🔒 Tunnel'
};

// Comprehensive Font Awesome unicode map (matches mapManager.js)
const iconUnicodeMap = {
    'fa-network-wired': '\uf6ff', 'fa-router': '\uf8da', 'fa-circle-nodes': '\ue4e3',
    'fa-sitemap': '\uf0e8', 'fa-diagram-project': '\uf542', 'fa-share-nodes': '\uf1e0',
    'fa-bezier-curve': '\uf55b',
    'fa-wifi': '\uf1eb', 'fa-tower-broadcast': '\uf519', 'fa-radio': '\uf8d7',
    'fa-signal': '\uf012', 'fa-broadcast-tower': '\uf519', 'fa-rss': '\uf09e',
    'fa-podcast': '\uf2ce', 'fa-satellite-dish': '\uf7c0',
    'fa-server': '\uf233', 'fa-tower-cell': '\ue585', 'fa-computer': '\uf108',
    'fa-microchip': '\uf2db', 'fa-memory': '\uf538', 'fa-hard-drive': '\uf0a0',
    'fa-hdd': '\uf0a0', 'fa-compact-disc': '\uf51f', 'fa-warehouse': '\uf494',
    'fa-industry': '\uf275',
    'fa-ethernet': '\uf796', 'fa-code-branch': '\uf126', 'fa-object-group': '\uf247',
    'fa-layer-group': '\uf5fd', 'fa-grip-horizontal': '\uf58d', 'fa-bars': '\uf0c9',
    'fa-sliders': '\uf1de', 'fa-table-cells': '\uf00a',
    'fa-shield-halved': '\uf3ed', 'fa-shield': '\uf132', 'fa-lock': '\uf023',
    'fa-shield-virus': '\ue06c', 'fa-user-shield': '\uf505', 'fa-fingerprint': '\uf577',
    'fa-key': '\uf084', 'fa-user-lock': '\uf13e', 'fa-ban': '\uf05e',
    'fa-circle-exclamation': '\uf06a',
    'fa-cloud': '\uf0c2', 'fa-cloud-arrow-up': '\uf0ee', 'fa-cloud-arrow-down': '\uf0ed',
    'fa-cloud-bolt': '\uf76c', 'fa-cloud-sun': '\uf6c4', 'fa-wind': '\uf72e',
    'fa-database': '\uf1c0', 'fa-table': '\uf0ce', 'fa-table-columns': '\uf0db',
    'fa-table-list': '\uf00b', 'fa-cubes': '\uf1b3', 'fa-box-archive': '\uf187',
    'fa-file-zipper': '\uf1c6',
    'fa-laptop': '\uf109', 'fa-laptop-code': '\uf5fc', 'fa-laptop-file': '\ue51d',
    'fa-desktop': '\uf390', 'fa-display': '\uf390', 'fa-tv': '\uf26c',
    'fa-chalkboard': '\uf51b',
    'fa-tablet-screen-button': '\uf3fa', 'fa-tablet': '\uf3fb', 'fa-tablet-button': '\uf10a',
    'fa-square-full': '\uf45c', 'fa-rectangle': '\uf2fa', 'fa-window-maximize': '\uf2d0',
    'fa-mobile-screen': '\uf3cf', 'fa-mobile-screen-button': '\uf3cd', 'fa-mobile': '\uf3ce',
    'fa-mobile-retro': '\ue527', 'fa-phone': '\uf095', 'fa-phone-flip': '\uf879',
    'fa-phone-volume': '\uf2a0', 'fa-walkie-talkie': '\uf8ef',
    'fa-print': '\uf02f', 'fa-fax': '\uf1ac', 'fa-file-pdf': '\uf1c1',
    'fa-file-image': '\uf1c5', 'fa-copy': '\uf0c5', 'fa-clone': '\uf24d',
    'fa-images': '\uf302', 'fa-file': '\uf15b',
    'fa-video': '\uf03d', 'fa-camera': '\uf030', 'fa-camera-retro': '\uf083',
    'fa-eye': '\uf06e', 'fa-glasses': '\uf530', 'fa-binoculars': '\uf1e5',
    'fa-film': '\uf008', 'fa-clapperboard': '\ue131',
    'fa-headset': '\uf590', 'fa-headphones': '\uf025', 'fa-voicemail': '\uf897',
    'fa-microphone': '\uf130',
    'fa-box': '\uf466', 'fa-boxes-stacked': '\uf468', 'fa-box-open': '\uf49e',
    'fa-cube': '\uf1b2', 'fa-folder-open': '\uf07c', 'fa-folder-tree': '\uf802',
    'fa-floppy-disk': '\uf0c7', 'fa-sd-card': '\uf7c2',
    'fa-clock': '\uf017', 'fa-stopwatch': '\uf2f2', 'fa-id-card': '\uf2c2',
    'fa-address-card': '\uf2bb', 'fa-user-check': '\uf4fc', 'fa-calendar-check': '\uf274',
    'fa-plug': '\uf1e6',
    'fa-battery-full': '\uf240', 'fa-battery-half': '\uf242', 'fa-car-battery': '\uf5df',
    'fa-bolt': '\uf0e7', 'fa-bolt-lightning': '\ue0b7', 'fa-power-off': '\uf011',
    'fa-charging-station': '\uf5e7',
    'fa-scale-balanced': '\uf24e', 'fa-balance-scale': '\uf24e',
    'fa-route': '\uf4d7', 'fa-shuffle': '\uf074', 'fa-repeat': '\uf363',
    'fa-arrows-turn-to-dots': '\ue4c1', 'fa-arrows-split-up-and-left': '\ue4bc',
    'fa-lightbulb': '\uf0eb', 'fa-temperature-half': '\uf2c9', 'fa-door-open': '\uf52b',
    'fa-bell': '\uf0f3', 'fa-gauge': '\uf624',
    'fa-keyboard': '\uf11c', 'fa-computer-mouse': '\uf8cc', 'fa-gamepad': '\uf11b',
    'fa-pen-to-square': '\uf044', 'fa-hand-pointer': '\uf25a', 'fa-square-pen': '\uf14b',
    'fa-circle': '\uf111', 'fa-square': '\uf0c8', 'fa-diamond': '\uf219',
    'fa-star': '\uf005', 'fa-asterisk': '\uf069', 'fa-circle-dot': '\uf192',
    'fa-bullseye': '\uf140', 'fa-crosshairs': '\uf05b', 'fa-location-dot': '\uf3c5',
    'fa-map-pin': '\uf276',
    'fa-diagram-subtask': '\ue479', 'fa-satellite': '\uf7bf',
    'fa-grip-vertical': '\uf58e',
};

// Default icon map (type -> first FA unicode) as fallback
const defaultIconMap = {
    server: '\uf233', router: '\uf6ff', switch: '\uf796', printer: '\uf02f', nas: '\uf0a0',
    camera: '\uf030', other: '\uf108', firewall: '\uf3ed', ipphone: '\uf095',
    punchdevice: '\uf2c2', 'wifi-router': '\uf1eb', 'radio-tower': '\uf519',
    rack: '\uf1b3', laptop: '\uf109', tablet: '\uf3fa', mobile: '\uf3cd',
    cloud: '\uf0c2', database: '\uf1c0', box: '\uf49e', ups: '\uf1e6',
    modem: '\uf796', loadbalancer: '\uf24e', iot: '\uf2db', monitor: '\uf390',
    keyboard: '\uf11c'
};

/**
 * Get icon unicode for a device, using subchoice-aware lookup
 */
function getDeviceIconUnicode(device) {
    if (!window.deviceIconsLibrary) {
        return defaultIconMap[device.type] || '\uf111';
    }
    const typeData = window.deviceIconsLibrary[device.type];
    if (!typeData || !typeData.icons) {
        return defaultIconMap[device.type] || '\uf111';
    }
    const index = parseInt(device.subchoice, 10) || 0;
    const iconData = typeData.icons[index];
    const iconClass = (iconData && iconData.icon) ? iconData.icon : (typeData.icons[0]?.icon || 'fa-circle');
    return iconUnicodeMap[iconClass] || '\uf111';
}

function showError(message, detail = "") {
    loader.hidden = true;
    errorCard.hidden = false;
    errorCard.innerHTML = `
        <div>
            <i class="fa-solid fa-triangle-exclamation fa-2x"></i>
            <h3>Unable to load the map</h3>
            <p>${message}</p>
            ${detail ? `<p id="errorDetails">${detail}</p>` : ""}
        </div>
    `;
    statusMessage.querySelector(".text").textContent = "Load failed";
    statusMessage.querySelector(".dot").style.background = "#f87171";
    statusMessage.querySelector(".dot").classList.remove("pulse");
}

function buildTitle(device) {
    const statusColors = {
        online: '#22c55e', warning: '#eab308', critical: '#ef4444',
        offline: '#64748b', unknown: '#94a3b8'
    };
    const status = device.status || 'unknown';
    const statusColor = statusColors[status] || statusColors.unknown;
    const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);

    let title = `<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; min-width: 220px; max-width: 320px; padding: 2px;">`;

    // Header: Name + Status badge
    title += `<div style="display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:8px;">`;
    title += `<b style="font-size:14px; color:#f1f5f9;">${device.name || 'Unnamed'}</b>`;
    title += `<span style="display:inline-block; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; color:#fff; background:${statusColor};">${statusLabel}</span>`;
    title += `</div>`;

    // Divider
    title += `<div style="border-top:1px solid rgba(148,163,184,0.2); margin-bottom:8px;"></div>`;

    // Details grid
    title += `<div style="display:grid; grid-template-columns: auto 1fr; gap: 4px 10px; font-size:12px;">`;

    // IP
    title += `<span style="color:#94a3b8;">IP Address:</span>`;
    title += `<span style="color:#e2e8f0; font-family:monospace;">${device.ip || 'N/A'}</span>`;

    // Type
    const typeLabel = (device.type || 'server').charAt(0).toUpperCase() + (device.type || 'server').slice(1);
    title += `<span style="color:#94a3b8;">Type:</span>`;
    title += `<span style="color:#e2e8f0;">${typeLabel}${device.subchoice ? ' (#' + device.subchoice + ')' : ''}</span>`;

    // Monitor method
    if (device.monitor_method) {
        title += `<span style="color:#94a3b8;">Monitor:</span>`;
        title += `<span style="color:#e2e8f0;">${device.monitor_method}${device.check_port ? ':' + device.check_port : ''}</span>`;
    }

    // Latency
    if (device.last_avg_time !== null && device.last_avg_time !== undefined) {
        const latency = parseFloat(device.last_avg_time);
        const latColor = latency < 50 ? '#22c55e' : latency < 150 ? '#eab308' : '#ef4444';
        title += `<span style="color:#94a3b8;">Latency:</span>`;
        title += `<span style="color:${latColor}; font-weight:600;">${latency}ms</span>`;
    }

    // TTL
    if (device.last_ttl) {
        title += `<span style="color:#94a3b8;">TTL:</span>`;
        title += `<span style="color:#e2e8f0;">${device.last_ttl}</span>`;
    }

    // Ping interval
    if (device.ping_interval) {
        title += `<span style="color:#94a3b8;">Interval:</span>`;
        title += `<span style="color:#e2e8f0;">${device.ping_interval}s</span>`;
    }

    // Last seen
    if (device.last_seen) {
        title += `<span style="color:#94a3b8;">Last Seen:</span>`;
        title += `<span style="color:#e2e8f0;">${device.last_seen}</span>`;
    }

    title += `</div>`;

    // Description
    if (device.description) {
        const desc = device.description.replace(/</g, "&lt;").replace(/>/g, "&gt;");
        title += `<div style="margin-top:6px; font-size:11px; color:#94a3b8; font-style:italic;">${desc}</div>`;
    }

    title += `</div>`;
    return title;
}

// Persistent vis.js datasets and network instance
let visNodesDataset = new vis.DataSet();
let visEdgesDataset = new vis.DataSet();
let visNetwork = null;

function buildVisNode(device) {
    const baseNode = {
        id: device.id,
        label: device.name || device.ip || `Device ${device.id}`,
        title: buildTitle(device),
        x: device.x ?? undefined,
        y: device.y ?? undefined,
        font: { color: '#e2e8f0', size: device.name_text_size ? Number(device.name_text_size) : 14, multi: true },
    };

    if (device.type === 'box') {
        return { ...baseNode, shape: 'box', color: { background: 'rgba(49, 65, 85, 0.5)', border: '#475569' }, margin: 20, level: -1 };
    }

    if (device.type === 'text') {
        const textStyle = (() => {
            try {
                const pc = typeof device.port_config === 'string' ? JSON.parse(device.port_config) : device.port_config;
                return pc?.text_style || {};
            } catch (e) { return {}; }
        })();
        const color = device.name_text_color || textStyle.color || '#22d3ee';
        const size = device.name_text_size ? Number(device.name_text_size) : (textStyle.size || 16);
        const container = textStyle.containerStyle || 'transparent';
        const isBox = container === 'card' || container === 'badge' || container === 'custom';
        return {
            ...baseNode,
            shape: isBox ? 'box' : 'text',
            color: {
                background: container === 'card' ? 'rgba(15, 23, 42, 0.85)' : (textStyle.fillColor || 'transparent'),
                border: container === 'card' ? '#334155' : (textStyle.borderColor || 'transparent')
            },
            borderWidth: isBox ? 1 : 0,
            font: {
                color: color,
                size: size,
                multi: true
            }
        };
    }

    let imagePath = null;
    const name = device.name || "";

    if (device.icon_url) {
        imagePath = device.icon_url;
    } else if (device.type === 'png-icons' || device.type === 'animated-icons') {
        const imgList = device.type === 'png-icons' ? [
            "assets/images/device-icons/sophos-firewall.png",
            "assets/images/device-icons/cisco-switch.png",
            "assets/images/device-icons/mikrotik-router.png",
            "assets/images/device-icons/online-ups.png",
            "assets/images/device-icons/rack-server.png",
            "assets/images/device-icons/default-device.png"
        ] : [
            "assets/images/device-icons/animated-globe.svg",
            "assets/images/device-icons/animated-router.svg",
            "assets/images/device-icons/animated-firewall.svg",
            "assets/images/device-icons/animated-server.svg",
            "assets/images/device-icons/animated-access-point.svg",
            "assets/images/device-icons/animated-switch.svg",
            "assets/images/device-icons/animated-cloud.svg",
            "assets/images/device-icons/animated-camera.svg",
            "assets/images/device-icons/animated-database.svg",
            "assets/images/device-icons/animated-workstation.svg",
            "assets/images/device-icons/animated-phone.svg",
            "assets/images/device-icons/animated-printer.svg",
            "assets/images/device-icons/animated-laptop.svg",
            "assets/images/device-icons/animated-nas.svg",
            "assets/images/device-icons/animated-iot-sensor.svg",
            "assets/images/device-icons/animated-ups.svg",
            "assets/images/device-icons/animated-utm.svg",
            "assets/images/device-icons/animated-tower.svg",
            "assets/images/device-icons/animated-modem.svg",
            "assets/images/device-icons/animated-patch-panel.svg",
            "assets/images/device-icons/animated-vlan.svg",
            "assets/images/device-icons/animated-warehouse.svg",
            "assets/images/device-icons/animated-switch-core.svg",
            "assets/images/device-icons/animated-ups-online.svg",
            "assets/images/device-icons/animated-firewall-nextgen.svg",
            "assets/images/device-icons/animated-unit.svg",
            "assets/images/device-icons/animated-sat.svg",
            "assets/images/device-icons/animated-sdwan.svg",
            "assets/images/device-icons/animated-datacenter.svg",
            "assets/images/device-icons/animated-wifi-router.svg",
            "assets/images/device-icons/animated-optical.svg",
            "assets/images/device-icons/animated-server-blade.svg"
        ];
        imagePath = imgList[device.subchoice] || "assets/images/device-icons/default-device.png";
    } else if (!device.subchoice || device.subchoice == 0) {
        if (/Firewall|CNF|UTM/i.test(name)) {
            imagePath = "assets/images/device-icons/sophos-firewall.png";
        } else if (/Switch|Core SW|DMZ SW/i.test(name)) {
            imagePath = "assets/images/device-icons/cisco-switch.png";
        } else if (/Router|IT Router|Dyeing Floor/i.test(name)) {
            imagePath = "assets/images/device-icons/mikrotik-router.png";
        } else if (/UPS|GMT/i.test(name)) {
            imagePath = "assets/images/device-icons/online-ups.png";
        } else if (/Server|ARIF-OPC/i.test(name)) {
            imagePath = "assets/images/device-icons/rack-server.png";
        }
    }

    if (imagePath) {
        const isAnimated = imagePath.includes('animated-');
        return {
            ...baseNode,
            shape: 'image',
            image: isAnimated ? "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=" : imagePath,
            originalImage: imagePath,
            size: device.icon_size ? Number(device.icon_size) / 2 : 25,
            color: { border: statusColorMap[device.status] || statusColorMap.unknown, background: 'transparent' },
            borderWidth: 3,
        };
    }

    const iconCode = getDeviceIconUnicode(device);
    return {
        ...baseNode,
        shape: 'icon',
        icon: { face: "'Font Awesome 6 Free'", weight: "900", code: iconCode, size: device.icon_size ? Number(device.icon_size) : 50, color: statusColorMap[device.status] || statusColorMap.unknown },
    };
}

function buildEdgeTitle(edge, devices) {
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
    const statusDotColors = { online: '#22c55e', warning: '#eab308', critical: '#ef4444', offline: '#64748b', unknown: '#94a3b8' };

    const srcDevice = devices.find(d => d.id === edge.source_id || d.id == edge.source_id);
    const tgtDevice = devices.find(d => d.id === edge.target_id || d.id == edge.target_id);
    const srcName = srcDevice ? srcDevice.name : 'Unknown';
    const tgtName = tgtDevice ? tgtDevice.name : 'Unknown';
    const srcStatus = srcDevice ? (srcDevice.status || 'unknown') : 'unknown';
    const tgtStatus = tgtDevice ? (tgtDevice.status || 'unknown') : 'unknown';

    let title = `<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; min-width: 200px; max-width: 300px; padding: 2px;">`;
    title += `<div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">`;
    title += `<div style="width:12px; height:3px; border-radius:2px; background:${connColor};"></div>`;
    title += `<b style="font-size:13px; color:#f1f5f9;">${connLabel}</b>`;
    title += `</div>`;
    title += `<div style="border-top:1px solid rgba(148,163,184,0.2); margin-bottom:8px;"></div>`;
    title += `<div style="display:grid; grid-template-columns: auto 1fr; gap: 4px 10px; font-size:12px;">`;
    title += `<span style="color:#94a3b8;">Source:</span>`;
    title += `<span style="color:#e2e8f0;"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:${statusDotColors[srcStatus]}; margin-right:4px;"></span>${srcName}</span>`;
    title += `<span style="color:#94a3b8;">Target:</span>`;
    title += `<span style="color:#e2e8f0;"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:${statusDotColors[tgtStatus]}; margin-right:4px;"></span>${tgtName}</span>`;
    if (edge.source_port_label || edge.target_port_label) {
        title += `<span style="color:#94a3b8;">Ports:</span>`;
        title += `<span style="color:#22d3ee; font-family:monospace; font-weight:600;">${edge.source_port_label || '—'} ↔ ${edge.target_port_label || '—'}</span>`;
    }
    title += `</div>`;
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
}

function buildVisEdge(edge, devices) {
    const color = edgeColorMap[edge.connection_type] || edgeColorMap.cat5;
    const dashes = edgeDashMap[edge.connection_type] ?? false;
    const label = edgeLabelMap[edge.connection_type] || edge.connection_type || '';
    return {
        id: edge.id,
        from: edge.source_id,
        to: edge.target_id,
        color: { color, highlight: color, hover: color },
        dashes, width: 2, label,
        font: { color: '#e2e8f0', size: 10, strokeWidth: 0 },
        title: buildEdgeTitle(edge, devices || []),
    };
}

function renderMap({ map, devices, edges }) {
    mapTitle.textContent = map?.name || "Shared network map";
    mapSubtitle.textContent = map?.public_view_enabled ? "Public viewing enabled" : "Read-only preview";
    metaSummary.textContent = `${devices.length} devices • ${edges.length} links`;

    visNodesDataset.clear();
    visNodesDataset.add(devices.map(buildVisNode));

    visEdgesDataset.clear();
    visEdgesDataset.add(edges.map(e => buildVisEdge(e, devices)));

    if (map?.background_color) canvas.style.background = map.background_color;
    if (map?.background_image_url) {
        canvas.style.backgroundImage = `url(${map.background_image_url})`;
        canvas.style.backgroundSize = "cover";
        canvas.style.backgroundPosition = "center";
    }

    if (!visNetwork) {
        visNetwork = new vis.Network(canvas, { nodes: visNodesDataset, edges: visEdgesDataset }, {
            interaction: { hover: true, dragNodes: false, zoomView: true, dragView: true },
            physics: { stabilization: true, barnesHut: { damping: 0.18 } },
            layout: { improvedLayout: true },
            edges: { smooth: { type: "dynamic" } },
            nodes: { borderWidth: 1, shadow: true },
        });

        // Throttled master animation loop (~25 FPS) with tab visibility pause
        (function startAnimationLoop() {
            let lastFrameTime = performance.now();
            let animRafId = null;
            const targetFPS = 25;
            const fpsInterval = 1000 / targetFPS;

            function loop(timestamp) {
                if (document.hidden) {
                    animRafId = null;
                    return;
                }
                if (!visNetwork) {
                    animRafId = requestAnimationFrame(loop);
                    return;
                }

                if (!lastFrameTime) {
                    lastFrameTime = timestamp;
                }

                const elapsed = timestamp - lastFrameTime;
                if (elapsed >= fpsInterval) {
                    lastFrameTime = timestamp - (elapsed % fpsInterval);

                    const hasAnimated = visNodesDataset.get().some(n =>
                        n.originalImage && typeof n.originalImage === 'string' && n.originalImage.includes('animated-')
                    );
                    const hasEdges = visEdgesDataset ? visEdgesDataset.get().length > 0 : false;
                    if (hasAnimated || hasEdges) {
                        visNetwork.redraw();
                    }
                }
                animRafId = requestAnimationFrame(loop);
            }

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && !animRafId) {
                    lastFrameTime = performance.now();
                    animRafId = requestAnimationFrame(loop);
                }
            });

            animRafId = requestAnimationFrame(loop);
        })();

        // Synchronize DOM Overlay for animated SVGs
        visNetwork.on("afterDrawing", () => {
            if (!visNetwork) return;

            const mapFrame = canvas.parentElement;
            if (!mapFrame) return;

            let overlayContainer = document.getElementById('animated-svg-overlay-container');
            if (!overlayContainer) {
                overlayContainer = document.createElement('div');
                overlayContainer.id = 'animated-svg-overlay-container';
                overlayContainer.style.cssText = 'position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none; overflow:hidden; z-index:5;';
                mapFrame.appendChild(overlayContainer);
            }

            const nodes = visNodesDataset.get();
            const activeIds = new Set();
            const scale = visNetwork.getScale();

            nodes.forEach(node => {
                const isAnimated = node.originalImage && typeof node.originalImage === 'string' && node.originalImage.includes('animated-');
                if (!isAnimated) return;

                activeIds.add(String(node.id));

                let imgEl = document.getElementById(`overlay-anim-${node.id}`);
                if (!imgEl) {
                    imgEl = document.createElement('img');
                    imgEl.id = `overlay-anim-${node.id}`;
                    imgEl.src = node.originalImage;
                    imgEl.setAttribute('data-src', node.originalImage);
                    imgEl.style.cssText = 'position:absolute; pointer-events:none; transition:none; transform-origin: center center; z-index: 5;';
                    overlayContainer.appendChild(imgEl);
                }

                const nodePos = visNetwork.getPositions([node.id])[node.id];
                if (nodePos) {
                    const domPos = visNetwork.canvasToDOM(nodePos);
                    const baseSize = deviceSizeMap[node.id] || 50; // Use actual device icon size or fallback to 50
                    const size = baseSize * scale;

                    imgEl.style.left = (domPos.x - size / 2) + 'px';
                    imgEl.style.top = (domPos.y - size / 2) + 'px';
                    imgEl.style.width = size + 'px';
                    imgEl.style.height = size + 'px';
                    imgEl.style.display = 'block';
                } else {
                    imgEl.style.display = 'none';
                }
            });

            // Clean up any deleted nodes
            Array.from(overlayContainer.children).forEach(child => {
                const id = child.id.replace('overlay-anim-', '');
                if (!activeIds.has(id)) {
                    child.remove();
                }
            });

            // Draw Edge Neon Laser Glow & Flowing Animated Cyber Packets
            const ctx = visNetwork.canvas.getContext();
            if (ctx && visEdgesDataset) {
                ctx.save();
                const edges = visEdgesDataset.get();
                const progress = ((Date.now() % 3000) / 3000);

                edges.forEach(edge => {
                    const fromId = String(edge.from);
                    const toId = String(edge.to);
                    const positions = visNetwork.getPositions([fromId, toId]);
                    const fromPos = positions[fromId];
                    const toPos = positions[toId];

                    if (!fromPos || !toPos) return;

                    const fx = fromPos.x;
                    const fy = fromPos.y;
                    const tx = toPos.x;
                    const ty = toPos.y;
                    const edgeColor = edge.custom_color || (typeof edge.color === 'string' ? edge.color : edge.color?.color) || '#00F2FE';

                    // Layer 1: Neon Glow
                    ctx.beginPath();
                    ctx.moveTo(fx, fy);
                    ctx.lineTo(tx, ty);
                    ctx.strokeStyle = edgeColor;
                    ctx.shadowColor = edgeColor;
                    ctx.shadowBlur = 10;
                    ctx.lineWidth = Math.max(1, (edge.width || 2) * 0.8);
                    ctx.stroke();

                    // Layer 2: Moving Cyber Packets
                    for (let i = 0; i < 4; i++) {
                        const t = (progress + i / 4) % 1.0;
                        const px = fx + (tx - fx) * t;
                        const py = fy + (ty - fy) * t;

                        ctx.beginPath();
                        ctx.arc(px, py, 4, 0, 2 * Math.PI);
                        ctx.fillStyle = edgeColor;
                        ctx.shadowColor = edgeColor;
                        ctx.shadowBlur = 12;
                        ctx.fill();

                        ctx.beginPath();
                        ctx.arc(px, py, 1.8, 0, 2 * Math.PI);
                        ctx.fillStyle = '#FFFFFF';
                        ctx.fill();
                    }
                });

                // =========================================================================
                // DEVICE OFFLINE RED ALERT & WARNING YELLOW BLINKING / PULSING SYSTEM
                // =========================================================================
                const now = Date.now();
                const pulseT1 = (now % 1400) / 1400;
                const pulseT2 = ((now + 700) % 1400) / 1400;
                const flashAlpha = 0.35 + 0.65 * Math.abs(Math.sin((now % 800) / 800 * Math.PI));

                const allNodes = visNodesDataset ? visNodesDataset.get() : [];
                for (const node of allNodes) {
                    if (!node || !node.id) continue;
                    const status = (node.status || node.deviceData?.status || 'online').toLowerCase();
                    if (status !== 'offline' && status !== 'warning' && status !== 'critical') continue;

                    const nodePos = visNetwork.getPositions([node.id])?.[node.id];
                    if (!nodePos || typeof nodePos.x !== 'number' || typeof nodePos.y !== 'number') continue;

                    const baseSize = (node.size || 50);
                    const baseRadius = Math.max(16, baseSize / 2);

                    const isOffline = (status === 'offline' || status === 'critical');
                    const alertColor = isOffline ? '#ef4444' : '#f59e0b';
                    const rgb = isOffline ? '239, 68, 68' : '245, 158, 11';

                    ctx.save();

                    // 1. Radar Expanding Shockwave 1
                    ctx.beginPath();
                    ctx.arc(nodePos.x, nodePos.y, baseRadius + pulseT1 * 34, 0, 2 * Math.PI);
                    ctx.strokeStyle = `rgba(${rgb}, ${(1 - pulseT1) * 0.95})`;
                    ctx.lineWidth = Math.max(1, 2.5 * (1 - pulseT1 * 0.5));
                    ctx.shadowColor = alertColor;
                    ctx.shadowBlur = 14 * (1 - pulseT1);
                    ctx.stroke();

                    // 2. Radar Expanding Shockwave 2
                    ctx.beginPath();
                    ctx.arc(nodePos.x, nodePos.y, baseRadius + pulseT2 * 34, 0, 2 * Math.PI);
                    ctx.strokeStyle = `rgba(${rgb}, ${(1 - pulseT2) * 0.75})`;
                    ctx.lineWidth = Math.max(1, 2 * (1 - pulseT2 * 0.5));
                    ctx.stroke();

                    // 3. Radial Pulsing Core Halo
                    const grad = ctx.createRadialGradient(nodePos.x, nodePos.y, baseRadius * 0.4, nodePos.x, nodePos.y, baseRadius + 14);
                    grad.addColorStop(0, `rgba(${rgb}, ${0.5 * flashAlpha})`);
                    grad.addColorStop(0.7, `rgba(${rgb}, ${0.25 * flashAlpha})`);
                    grad.addColorStop(1, `rgba(${rgb}, 0)`);
                    ctx.fillStyle = grad;
                    ctx.beginPath();
                    ctx.arc(nodePos.x, nodePos.y, baseRadius + 14, 0, 2 * Math.PI);
                    ctx.fill();

                    // 4. Solid High-Visibility Blinking Perimeter Ring
                    ctx.beginPath();
                    ctx.arc(nodePos.x, nodePos.y, baseRadius + 4, 0, 2 * Math.PI);
                    ctx.strokeStyle = `rgba(${rgb}, ${flashAlpha})`;
                    ctx.lineWidth = 2.5;
                    ctx.shadowColor = alertColor;
                    ctx.shadowBlur = 16 * flashAlpha;
                    ctx.stroke();

                    // 5. Emergency Warning Beacon Badge (Top-Right)
                    const badgeX = nodePos.x + baseRadius * 0.72;
                    const badgeY = nodePos.y - baseRadius * 0.72;
                    const badgeRadius = 9;

                    ctx.beginPath();
                    ctx.arc(badgeX, badgeY, badgeRadius, 0, 2 * Math.PI);
                    ctx.fillStyle = isOffline ? `rgba(220, 38, 38, ${Math.max(0.7, flashAlpha)})` : `rgba(217, 119, 6, ${Math.max(0.7, flashAlpha)})`;
                    ctx.shadowColor = alertColor;
                    ctx.shadowBlur = 12 * flashAlpha;
                    ctx.fill();

                    ctx.strokeStyle = '#ffffff';
                    ctx.lineWidth = 1.5;
                    ctx.stroke();

                    ctx.fillStyle = isOffline ? '#ffffff' : '#000000';
                    ctx.font = 'bold 11px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.shadowBlur = 0;
                    ctx.fillText(isOffline ? '!' : '▲', badgeX, badgeY + (isOffline ? 0 : -0.5));

                    ctx.restore();
                }

                ctx.restore();
            }
        });
    }
}

// Track device sizes for DOM overlay scaling
const deviceSizeMap = {};
visNodesDataset.on('*', () => {
    visNodesDataset.get().forEach(n => {
        if (n.size) {
            deviceSizeMap[n.id] = n.size * 2;
        }
    });
});


/**
 * In-place status update — only updates node icon colors and titles without resetting the network
 */
function refreshStatuses(devices) {
    metaSummary.textContent = `${devices.length} devices • ${visEdgesDataset.length} links`;

    const updates = devices.map(device => {
        const existing = visNodesDataset.get(device.id);
        if (!existing) return null;

        const update = { id: device.id, title: buildTitle(device) };

        if (device.icon_url) {
            update.color = { border: statusColorMap[device.status] || statusColorMap.unknown, background: 'transparent' };
        } else if (device.type !== 'box' && existing.icon) {
            update.icon = { ...existing.icon, color: statusColorMap[device.status] || statusColorMap.unknown };
        }

        // Update label with live ping info if available
        let label = device.name || device.ip || `Device ${device.id}`;
        if (device.show_live_ping && device.status === 'online' && device.last_avg_time !== null) {
            label += `\n${device.last_avg_time}ms | TTL:${device.last_ttl || 'N/A'}`;
        }
        update.label = label;

        return update;
    }).filter(Boolean);

    if (updates.length > 0) visNodesDataset.update(updates);
}

async function fetchWithTimeout(url, options = {}, timeout = 12000) {
    const controller = new AbortController();
    const id = setTimeout(() => controller.abort(), timeout);
    try {
        const response = await fetch(url, { ...options, signal: controller.signal });
        clearTimeout(id);
        return response;
    } catch (error) {
        clearTimeout(id);
        throw error;
    }
}

async function loadMap() {
    if (!mapId) {
        showError("No map selected", "Append ?map_id=123 to view a shared map.");
        return;
    }

    const link = `${window.location.origin}${window.location.pathname}?map_id=${mapId}`;
    copyLinkBtn.addEventListener("click", async () => {
        try {
            await navigator.clipboard.writeText(link);
            statusMessage.querySelector(".text").textContent = "Link copied";
            setTimeout(() => (statusMessage.querySelector(".text").textContent = "Live view ready"), 2000);
        } catch (err) {
            alert(`Copy failed. Link: ${link}`);
        }
    });

    try {
        const response = await fetchWithTimeout(`api.php?action=get_public_map_data&map_id=${mapId}`, {}, 12000);
        if (!response.ok) {
            showError("The map could not be loaded.", await response.text());
            return;
        }
        const payload = await response.json();
        if (!payload?.map) {
            showError("No map data returned", "Ensure public view is enabled for this map.");
            return;
        }
        const hasDevices = Array.isArray(payload.devices) && payload.devices.length > 0;
        if (!hasDevices) {
            statusMessage.querySelector(".text").textContent = "No devices published";
            statusMessage.querySelector(".dot").classList.remove("pulse");
            loader.querySelector("p").textContent = "No devices have been shared for this map yet.";
            loader.hidden = false;
        } else {
            statusMessage.querySelector(".text").textContent = "Live view ready";
            statusMessage.querySelector(".dot").classList.add("pulse");
            loader.hidden = true;
        }
        renderMap(payload);
    } catch (error) {
        const message = error.name === "AbortError" ? "Map request timed out" : "Unexpected error";
        showError(message, error.message || "");
    }
}

loadMap();

// Auto-refresh every 15 seconds — updates statuses in-place without resetting the map view
setInterval(async () => {
    if (!mapId || !visNetwork) return;
    try {
        const response = await fetchWithTimeout(`api.php?action=get_public_map_data&map_id=${mapId}`, {}, 12000);
        if (!response.ok) return;
        const payload = await response.json();
        if (!payload?.map || !Array.isArray(payload.devices)) return;
        refreshStatuses(payload.devices);
    } catch (e) {
        console.warn("Auto-refresh failed:", e.message);
    }
}, 15000);
