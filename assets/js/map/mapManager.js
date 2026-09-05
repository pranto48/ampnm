/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
window.MapApp = window.MapApp || {};

MapApp.mapManager = {
    createMap: async () => {
        if (window.userRole !== 'admin') {
            // No error message needed, as the button is disabled for viewers
            return;
        }
        const name = prompt("Enter a name for the new map:");
        if (name === null) { // User clicked cancel
            window.notyf.info("Map creation cancelled.");
            return; // Stop execution if prompt is cancelled
        }
        const trimmedName = name.trim();
        if (trimmedName === '') {
            window.notyf.error("Map name cannot be empty.");
            return; // Stop execution if name is empty
        }
        
        try {
            const newMap = await MapApp.api.post('create_map', { name: trimmedName }); 
            await MapApp.mapManager.loadMaps(); 
            const selector = MapApp.ui.getEl('mapSelector');
            if (selector) selector.value = newMap.id; 
            await MapApp.mapManager.switchMap(newMap.id); 
            window.notyf.success(`Map "${trimmedName}" created.`);
        } catch (error) {
            console.error("Failed to create map:", error);
            window.notyf.error(error.message || "Failed to create map.");
        }
    },

    loadMaps: async () => {
        const maps = await MapApp.api.get('get_maps');
        MapApp.state.maps = maps || [];
        const selector = MapApp.ui.getEl('mapSelector');
        const mapContainer = MapApp.ui.getEl('mapContainer');
        const noMapsContainer = MapApp.ui.getEl('noMapsContainer');
        if (selector) selector.innerHTML = '';
        if (Array.isArray(maps) && maps.length > 0) {
            maps.forEach(map => { 
                if (selector) {
                    const option = document.createElement('option'); 
                    option.value = map.id; 
                    option.textContent = map.name; 
                    selector.appendChild(option); 
                }
            });
            if (mapContainer) mapContainer.classList.remove('hidden'); 
            if (noMapsContainer) noMapsContainer.classList.add('hidden'); 
            return maps[0].id;
        } else { 
            if (mapContainer) mapContainer.classList.add('hidden'); 
            if (noMapsContainer) noMapsContainer.classList.remove('hidden'); 
            return null; 
        }
    },

    /**
     * Get Font Awesome unicode for device icon
     * Uses device.type and device.subchoice to lookup correct icon
     */
    getDeviceIconUnicode: (device) => {
        // Get icon class using the utility function
        const iconClass = MapApp.utils.getDeviceIconClass(
            device.type || 'generic',
            device.subchoice || 0
        );

        // Font Awesome icon unicode map
        const iconMap = {
            // Routers
            'fa-network-wired': '\uf6ff',
            'fa-router': '\uf8da', 
            'fa-circle-nodes': '\ue4e3',
            'fa-sitemap': '\uf0e8',
            'fa-diagram-project': '\uf542',
            'fa-share-nodes': '\uf1e0',
            'fa-bezier-curve': '\uf55b',
            
            // WiFi
            'fa-wifi': '\uf1eb',
            'fa-tower-broadcast': '\uf519',
            'fa-radio': '\uf8d7',
            'fa-signal': '\uf012',
            'fa-broadcast-tower': '\uf519',
            'fa-rss': '\uf09e',
            'fa-podcast': '\uf2ce',
            'fa-satellite-dish': '\uf7c0',
            
            // Servers
            'fa-server': '\uf233',
            'fa-tower-cell': '\ue585',
            'fa-computer': '\uf108',
            'fa-microchip': '\uf2db',
            'fa-memory': '\uf538',
            'fa-hard-drive': '\uf0a0',
            'fa-hdd': '\uf0a0',
            'fa-compact-disc': '\uf51f',
            'fa-warehouse': '\uf494',
            'fa-industry': '\uf275',
            
            // Network Switch
            'fa-ethernet': '\uf796',
            'fa-code-branch': '\uf126',
            'fa-object-group': '\uf247',
            'fa-layer-group': '\uf5fd',
            'fa-grip-horizontal': '\uf58d',
            'fa-bars': '\uf0c9',
            'fa-sliders': '\uf1de',
            'fa-table-cells': '\uf00a',
            
            // Security/Firewall
            'fa-shield-halved': '\uf3ed',
            'fa-shield': '\uf132',
            'fa-lock': '\uf023',
            'fa-shield-virus': '\ue06c',
            'fa-user-shield': '\uf505',
            'fa-fingerprint': '\uf577',
            'fa-key': '\uf084',
            'fa-user-lock': '\uf13e',
            'fa-ban': '\uf05e',
            'fa-circle-exclamation': '\uf06a',
            
            // Cloud
            'fa-cloud': '\uf0c2',
            'fa-cloud-arrow-up': '\uf0ee',
            'fa-cloud-arrow-down': '\uf0ed',
            'fa-cloud-bolt': '\uf76c',
            'fa-cloudflare': '\ue07d',
            'fa-cloud-sun': '\uf6c4',
            'fa-wind': '\uf72e',
            
            // Database
            'fa-database': '\uf1c0',
            'fa-table': '\uf0ce',
            'fa-table-columns': '\uf0db',
            'fa-table-list': '\uf00b',
            'fa-diagram-subtask': '\ue479',
            'fa-cubes': '\uf1b3',
            'fa-box-archive': '\uf187',
            'fa-file-zipper': '\uf1c6',
            
            // Devices
            'fa-laptop': '\uf109',
            'fa-laptop-code': '\uf5fc',
            'fa-laptop-file': '\ue51d',
            'fa-desktop': '\uf390',
            'fa-display': '\uf390',
            'fa-tv': '\uf26c',
            'fa-chalkboard': '\uf51b',
            'fa-tablet-screen-button': '\uf3fa',
            'fa-tablet': '\uf3fb',
            'fa-tablet-button': '\uf10a',
            'fa-square-full': '\uf45c',
            'fa-rectangle': '\uf2fa',
            'fa-window-maximize': '\uf2d0',
            'fa-mobile-screen': '\uf3cf',
            'fa-mobile-screen-button': '\uf3cd',
            'fa-mobile': '\uf3ce',
            'fa-mobile-retro': '\ue527',
            'fa-phone': '\uf095',
            'fa-phone-flip': '\uf879',
            'fa-phone-volume': '\uf2a0',
            'fa-walkie-talkie': '\uf8ef',
            
            // Peripherals
            'fa-print': '\uf02f',
            'fa-fax': '\uf1ac',
            'fa-file-pdf': '\uf1c1',
            'fa-file-image': '\uf1c5',
            'fa-copy': '\uf0c5',
            'fa-clone': '\uf24d',
            'fa-images': '\uf302',
            'fa-file': '\uf15b',
            'fa-video': '\uf03d',
            'fa-camera': '\uf030',
            'fa-camera-retro': '\uf083',
            'fa-camera-viewfinder': '\ue0da',
            'fa-eye': '\uf06e',
            'fa-glasses': '\uf530',
            'fa-binoculars': '\uf1e5',
            'fa-film': '\uf008',
            'fa-clapperboard': '\ue131',
            'fa-headset': '\uf590',
            'fa-headphones': '\uf025',
            'fa-voicemail': '\uf897',
            'fa-microphone': '\uf130',
            
            // Infrastructure
            'fa-box': '\uf466',
            'fa-boxes-stacked': '\uf468',
            'fa-box-open': '\uf49e',
            'fa-cube': '\uf1b2',
            'fa-folder-open': '\uf07c',
            'fa-folder-tree': '\uf802',
            'fa-floppy-disk': '\uf0c7',
            'fa-sd-card': '\uf7c2',
            'fa-clock': '\uf017',
            'fa-stopwatch': '\uf2f2',
            'fa-id-card': '\uf2c2',
            'fa-address-card': '\uf2bb',
            'fa-user-check': '\uf4fc',
            'fa-calendar-check': '\uf274',
            'fa-plug': '\uf1e6',
            
            // Power
            'fa-battery-full': '\uf240',
            'fa-battery-half': '\uf242',
            'fa-car-battery': '\uf5df',
            'fa-bolt': '\uf0e7',
            'fa-bolt-lightning': '\ue0b7',
            'fa-power-off': '\uf011',
            'fa-charging-station': '\uf5e7',
            
            // Load Balancer
            'fa-scale-balanced': '\uf24e',
            'fa-balance-scale': '\uf24e',
            'fa-arrows-split-up': '\ue4bc',
            'fa-route': '\uf4d7',
            'fa-shuffle': '\uf074',
            'fa-repeat': '\uf363',
            'fa-arrows-turn-to-dots': '\ue4c1',
            
            // IoT
            'fa-lightbulb': '\uf0eb',
            'fa-temperature-half': '\uf2c9',
            'fa-door-open': '\uf52b',
            'fa-bell': '\uf0f3',
            'fa-gauge': '\uf624',
            
            // Input
            'fa-keyboard': '\uf11c',
            'fa-computer-mouse': '\uf8cc',
            'fa-gamepad': '\uf11b',
            'fa-pen-to-square': '\uf044',
            'fa-hand-pointer': '\uf25a',
            'fa-square-pen': '\uf14b',

            // Additional library icons
            'fa-globe': '\uf0ac',
            'fa-earth-americas': '\uf57d',
            'fa-earth-asia': '\uf57e',
            'fa-earth-europe': '\uf7a2',
            'fa-earth-africa': '\uf57c',
            'fa-earth-oceania': '\ue47b',
            'fa-clouds': '\uf744',
            'fa-satellite': '\uf7bf',
            'fa-radar': '\ue024',
            'fa-grip-vertical': '\uf58e',
            'fa-gears': '\uf085',
            'fa-gear': '\uf013',
            'fa-wrench': '\uf0ad',
            'fa-screwdriver-wrench': '\uf7d9',
            'fa-robot': '\uf544',
            'fa-terminal': '\uf120',
            'fa-code': '\uf121',
            'fa-link': '\uf0c1',
            'fa-barcode': '\uf02a',
            'fa-qrcode': '\uf029',
            'fa-envelope': '\uf0e0',
            'fa-envelope-open': '\uf2b6',
            'fa-message': '\uf27a',
            'fa-at': '\uf1fa',
            'fa-mailbox': '\uf813',
            'fa-inbox': '\uf01c',
            'fa-map-signs': '\uf277',
            'fa-signs-post': '\uf277',
            'fa-house': '\uf015',
            'fa-house-signal': '\ue012',
            'fa-project-diagram': '\uf542',
            'fa-video-camera': '\uf03d',
            
            // Generic
            'fa-circle': '\uf111',
            'fa-square': '\uf0c8',
            'fa-diamond': '\uf219',
            'fa-star': '\uf005',
            'fa-asterisk': '\uf069',
            'fa-circle-dot': '\uf192',
            'fa-bullseye': '\uf140',
            'fa-crosshairs': '\uf05b',
            'fa-location-dot': '\uf3c5',
            'fa-map-pin': '\uf276'
        };

        // Get unicode or fallback to circle
        return iconMap[iconClass] || '\uf111';
    },

    switchMap: async (mapId) => {
        if (MapApp.state.animationFrameId) { 
            cancelAnimationFrame(MapApp.state.animationFrameId); 
            MapApp.state.animationFrameId = null; 
        }
        if (!mapId) { 
            if (MapApp.state.network) MapApp.state.network.destroy(); 
            MapApp.state.network = null; 
            MapApp.state.nodes.clear(); 
            MapApp.state.edges.clear(); 
            const mapContainer = MapApp.ui.getEl('mapContainer');
            const noMapsContainer = MapApp.ui.getEl('noMapsContainer');
            if (mapContainer) mapContainer.classList.add('hidden'); 
            if (noMapsContainer) noMapsContainer.classList.remove('hidden'); 
            return; 
        }
        
        MapApp.state.currentMapId = mapId; 
        
        // Reset timeline slider state when switching maps
        if (MapApp.network.timeline && typeof MapApp.network.timeline.reset === 'function') {
            MapApp.network.timeline.reset();
        }

        const currentMap = MapApp.state.maps.find(m => m.id == mapId);
        if (currentMap) {
            const currentMapNameEl = MapApp.ui.getEl('currentMapName');
            if (currentMapNameEl) currentMapNameEl.textContent = currentMap.name;
            const mapEl = document.getElementById('network-map');
            mapEl.style.backgroundColor = currentMap.background_color || '';
            mapEl.style.backgroundImage = currentMap.background_image_url ? `url(${currentMap.background_image_url})` : '';
            mapEl.style.backgroundSize = 'cover';
            mapEl.style.backgroundPosition = 'center';
            // Update public view link display
            MapApp.mapManager.updatePublicViewLink(currentMap.id, currentMap.public_view_enabled);
            // Apply offline delay setting
            const delaySeconds = currentMap.offline_delay_seconds || 5;
            MapApp.config.offlineDelayMs = delaySeconds * 1000;
            // Update offline delay badge in toolbar
            const delayBadge = document.getElementById('offlineDelayValue');
            if (delayBadge) delayBadge.textContent = delaySeconds;

            // Render Sub-Map Breadcrumbs
            const breadcrumbBar = document.getElementById('mapBreadcrumbBar');
            const breadcrumbLinks = document.getElementById('mapBreadcrumbLinks');
            if (breadcrumbBar && breadcrumbLinks) {
                const chain = [];
                let curr = currentMap;
                while (curr) {
                    chain.unshift(curr);
                    if (curr.parent_map_id) {
                        curr = MapApp.state.maps.find(m => m.id == curr.parent_map_id);
                    } else {
                        break;
                    }
                }
                if (chain.length > 1) {
                    breadcrumbBar.classList.remove('hidden');
                    breadcrumbBar.classList.add('flex');
                    breadcrumbLinks.innerHTML = chain.map((m, idx) => {
                        const isLast = idx === chain.length - 1;
                        if (isLast) {
                            return `<span class="text-white font-bold">${m.name}</span>`;
                        } else {
                            return `<a href="#" onclick="MapApp.mapManager.switchMap(${m.id}); return false;" class="text-cyan-400 hover:underline">${m.name}</a> <i class="fas fa-chevron-right text-[10px] text-slate-500 mx-0.5"></i>`;
                        }
                    }).join('');
                } else {
                    breadcrumbBar.classList.add('hidden');
                    breadcrumbBar.classList.remove('flex');
                    breadcrumbLinks.innerHTML = '';
                }
            }
        }
        
        // Correctly extract the 'devices' array from the API response
        const [deviceResponse, edgeData] = await Promise.all([
            MapApp.api.get('get_devices', { map_id: mapId }), 
            MapApp.api.get('get_edges', { map_id: mapId })
        ]);
        const deviceData = deviceResponse.devices || []; // Extract the array here
        const userStorageId = window.currentLoggedInUserId || window.currentLoggedInUsername || 'guest';
        const nodePosStorageKey = `ampnm_map_node_positions:${userStorageId}:${mapId}`;
        let nodePositionOverrides = {};
        try {
            nodePositionOverrides = JSON.parse(localStorage.getItem(nodePosStorageKey) || '{}') || {};
        } catch (error) {
            nodePositionOverrides = {};
        }
        
        const visNodes = deviceData.map(d => {
            let label = d.name;
            if (d.show_live_ping && d.status === 'online' && d.last_avg_time !== null) {
                label += `\n${d.last_avg_time}ms | TTL:${d.last_ttl || 'N/A'}`;
            }

            const overridePos = nodePositionOverrides[d.id] || nodePositionOverrides[String(d.id)] || null;

            // Fallback to map-specific settings from localStorage if node styles are default/unset
            let labelColor = d.name_text_color;
            let labelSize = parseInt(d.name_text_size);
            let labelBold = d.name_text_bold == 1;
            let labelItalic = d.name_text_italic == 1;
            let labelVAdjust = (d.name_text_vadjust !== null && d.name_text_vadjust !== undefined && d.name_text_vadjust !== '') ? parseInt(d.name_text_vadjust) : null;

            // Fallback: Check map-specific settings, then fall back to system-wide globalDeviceLabelSettings
            let effSettings = null;
            const mapSettingsRaw = localStorage.getItem(`mapLabelSettings:${mapId}`);
            const globalSettingsRaw = localStorage.getItem('globalDeviceLabelSettings');
            if (mapSettingsRaw) {
                try { effSettings = JSON.parse(mapSettingsRaw); } catch(e) {}
            }
            if (!effSettings && globalSettingsRaw) {
                try { effSettings = JSON.parse(globalSettingsRaw); } catch(e) {}
            } else if (effSettings && globalSettingsRaw) {
                try {
                    const g = JSON.parse(globalSettingsRaw);
                    if ((effSettings.vadjust === undefined || effSettings.vadjust === null) && g.vadjust !== undefined) {
                        effSettings.vadjust = g.vadjust;
                    }
                } catch(e) {}
            }

            if (effSettings) {
                if (!labelColor || labelColor === '#ffffff') {
                    labelColor = effSettings.color || '#ffffff';
                }
                if (isNaN(labelSize) || labelSize === 14) {
                    labelSize = parseInt(effSettings.size) || 14;
                }
                if (!labelBold) labelBold = effSettings.bold == 1;
                if (!labelItalic) labelItalic = effSettings.italic == 1;
                if (labelVAdjust === null || isNaN(labelVAdjust) || labelVAdjust === 0) {
                    if (effSettings.vadjust !== undefined && !isNaN(parseInt(effSettings.vadjust))) {
                        labelVAdjust = parseInt(effSettings.vadjust);
                    }
                }
            }
            if (!labelColor) labelColor = '#ffffff';
            if (isNaN(labelSize)) labelSize = 14;
            if (labelVAdjust === null || isNaN(labelVAdjust)) labelVAdjust = 0;

            const labelFace = labelBold && labelItalic ? 'bold italic Arial' : labelBold ? 'bold Arial' : labelItalic ? 'italic Arial' : 'Arial';
            const baseNode = {
                id: d.id, label: label, title: MapApp.utils.buildNodeTitle(d),
                x: overridePos?.x ?? d.x, y: overridePos?.y ?? d.y,
                font: { color: labelColor, size: labelSize, multi: true, face: labelFace, vadjust: labelVAdjust },
                deviceData: d
            };

            // Box type
            if (d.type === 'box') {
                return MapApp.utils.buildVisBoxNode(baseNode, d);
            }

            // Text type
            if (d.type === 'text') {
                return MapApp.utils.buildVisTextNode(baseNode, d);
            }

            // Resolve visuals for normal nodes
            const visuals = MapApp.utils.resolveNodeVisuals(d);
            return {
                ...baseNode,
                ...visuals
            };
        });
        MapApp.state.nodes.clear(); 
        MapApp.state.nodes.add(visNodes);

        const visEdges = edgeData.map(e => {
            let edgeLabel = e.label || e.connection_type;
            if (!e.label) {
                if (e.source_port_label && e.target_port_label) {
                    edgeLabel = `${e.source_port_label} ↔ ${e.target_port_label}`;
                } else if (e.source_port_label || e.target_port_label) {
                    edgeLabel = `${e.source_port_label || '—'} ↔ ${e.target_port_label || '—'}`;
                }
            }
            // Build rich tooltip for edge
            const srcDevice = deviceData.find(d => d.id === e.source_id || d.id == e.source_id);
            const tgtDevice = deviceData.find(d => d.id === e.target_id || d.id == e.target_id);
            const title = MapApp.utils.buildEdgeTitle(e, srcDevice, tgtDevice);
            
            // Format Vis.js custom properties
            const width = parseInt(e.thickness) || 2;
            const color = e.color ? { color: e.color, hover: e.color, highlight: e.color } : undefined;
            
            let dashes = false;
            if (e.line_style === 'dashed') {
                dashes = [6, 4];
            } else if (e.line_style === 'dotted') {
                dashes = [2, 3];
            } else if (e.line_style === 'solid') {
                dashes = false;
            } else if (e.connection_type === 'wifi' || e.connection_type === 'radio' || e.connection_type === 'logical-tunneling') {
                dashes = [5, 5];
            }
            
            let arrows = undefined;
            if (e.arrows === 'to') arrows = { to: { enabled: true } };
            else if (e.arrows === 'from') arrows = { from: { enabled: true } };
            else if (e.arrows === 'both') arrows = { to: { enabled: true }, from: { enabled: true } };

            return { 
                id: e.id, 
                from: e.source_id, 
                to: e.target_id, 
                connection_type: e.connection_type, 
                source_port_label: e.source_port_label, 
                target_port_label: e.target_port_label, 
                label: edgeLabel, 
                title,
                width,
                color,
                dashes,
                arrows,
                custom_thickness: e.thickness,
                custom_color: e.color,
                custom_line_style: e.line_style,
                custom_arrows: e.arrows,
                custom_label: e.label,
                custom_animated: e.animated
            };
        });
        console.log('visEdges array before adding to dataset:', visEdges);
        MapApp.state.edges.clear(); 
        MapApp.state.edges.add(visEdges);
        console.log('Edges in dataset after load:', MapApp.state.edges.get());
        
        MapApp.deviceManager.setupAutoPing(deviceData);
        const mapContainer = MapApp.ui.getEl('mapContainer');
        if (mapContainer) mapContainer.classList.remove('hidden');
        const noMapsContainer = MapApp.ui.getEl('noMapsContainer');
        if (noMapsContainer) noMapsContainer.classList.add('hidden');

        if (!MapApp.state.network) MapApp.network.initializeMap();
        else MapApp.network.restoreSavedView();
        MapApp.ui.updateStaticEdgeColors();
        MapApp.ui.startCanvasAnimationLoop();
        if (MapApp.ui && typeof MapApp.ui.syncConnectionAnimToggleUI === 'function') {
            const displaySettings = (MapApp.utils && typeof MapApp.utils.getCurrentTooltipDisplaySettings === 'function')
                ? MapApp.utils.getCurrentTooltipDisplaySettings()
                : { connection_enable_animation: true };
            const isAnim = displaySettings.connection_enable_animation !== false &&
                           displaySettings.connection_enable_animation !== 'false' &&
                           displaySettings.connection_enable_animation !== 0 &&
                           displaySettings.connection_enable_animation !== '0';
            MapApp.ui.syncConnectionAnimToggleUI(isAnim);
        }
        setTimeout(() => {
            if (MapApp.state.network) {
                MapApp.state.network.redraw();
            }
        }, 100);
    },

    copyDevice: async (deviceId) => {
        if (window.userRole !== 'admin') {
            return;
        }
        const nodeToCopy = MapApp.state.nodes.get(deviceId);
        if (!nodeToCopy) return;

        const originalDevice = nodeToCopy.deviceData;
        const position = MapApp.state.network.getPositions([deviceId])[deviceId];

        const newDeviceData = {
            ...originalDevice,
            name: `Copy of ${originalDevice.name}`,
            ip: '',
            x: position.x + 50,
            y: position.y + 50,
            map_id: MapApp.state.currentMapId,
            status: 'unknown',
            last_seen: null,
            last_avg_time: null,
            last_ttl: null,
        };
        
        delete newDeviceData.id;
        delete newDeviceData.created_at;
        delete newDeviceData.updated_at;

        try {
            const createdDevice = await MapApp.api.post('create_device', newDeviceData);
            window.notyf.success(`Device "${originalDevice.name}" copied.`);
            
            const cpLabelColor = createdDevice.name_text_color || '#ffffff';
            const cpLabelBold = createdDevice.name_text_bold == 1;
            const cpLabelItalic = createdDevice.name_text_italic == 1;
            const cpLabelFace = cpLabelBold && cpLabelItalic ? 'bold italic Arial' : cpLabelBold ? 'bold Arial' : cpLabelItalic ? 'italic Arial' : 'Arial';
            let cpLabelVAdjust = (createdDevice.name_text_vadjust !== null && createdDevice.name_text_vadjust !== undefined) ? parseInt(createdDevice.name_text_vadjust) : 0;
            if (cpLabelVAdjust === 0) {
                try {
                    const g = JSON.parse(localStorage.getItem('globalDeviceLabelSettings') || '{}');
                    if (g.vadjust !== undefined) cpLabelVAdjust = parseInt(g.vadjust, 10) || 0;
                } catch(e) {}
            }
            const baseNode = {
                id: createdDevice.id,
                label: createdDevice.name,
                title: MapApp.utils.buildNodeTitle(createdDevice),
                x: createdDevice.x,
                y: createdDevice.y,
                font: { color: cpLabelColor, size: parseInt(createdDevice.name_text_size) || 14, multi: true, face: cpLabelFace, vadjust: cpLabelVAdjust },
                deviceData: createdDevice
            };

            let visNode;
            if (createdDevice.icon_url) {
                visNode = { ...baseNode, shape: 'image', image: createdDevice.icon_url, size: (parseInt(createdDevice.icon_size) || 50) / 2, color: { border: MapApp.config.statusColorMap[createdDevice.status] || MapApp.config.statusColorMap.unknown, background: 'transparent' }, borderWidth: 3 };
            } else if (createdDevice.type === 'box') {
                visNode = MapApp.utils.buildVisBoxNode(baseNode, createdDevice);
            } else if (createdDevice.type === 'text') {
                visNode = MapApp.utils.buildVisTextNode(baseNode, createdDevice);
            } else {
                const iconCode = MapApp.mapManager.getDeviceIconUnicode(createdDevice);
                visNode = { ...baseNode, shape: 'icon', icon: { face: "'Font Awesome 6 Free'", weight: "900", code: iconCode, size: parseInt(createdDevice.icon_size) || 50, color: MapApp.config.statusColorMap[createdDevice.status] || MapApp.config.statusColorMap.unknown } };
            }
            MapApp.state.nodes.add(visNode);
        } catch (error) {
            console.error("Failed to copy device:", error);
            window.notyf.error("Could not copy the device.");
        }
    },

    updatePublicViewLink: (mapId, isEnabled) => {
        const linkEl = MapApp.ui.getEl('publicViewLink');
        const containerEl = MapApp.ui.getEl('publicViewLinkContainer');
        if (isEnabled) {
            if (linkEl) linkEl.value = MapApp.utils.buildPublicMapUrl(mapId);
            if (containerEl) containerEl.classList.remove('hidden');
        } else {
            if (linkEl) linkEl.value = '';
            if (containerEl) containerEl.classList.add('hidden');
        }
    },

    openRackVisualizer: (rackDevice) => {
        const modal = document.getElementById('rackVisualizerModal');
        const title = document.getElementById('rackModalTitle');
        const slotsContainer = document.getElementById('rackSlotsContainer');
        const installedList = document.getElementById('rackInstalledList');
        const totalUnitsEl = document.getElementById('rackTotalUnits');
        const mountedCountEl = document.getElementById('rackMountedCount');
        const availableUnitsEl = document.getElementById('rackAvailableUnits');

        if (!modal || !slotsContainer) return;

        const totalUnits = parseInt(rackDevice.rack_units) || 42;
        if (title) title.innerHTML = `<i class="fas fa-server text-cyan-400"></i> 19" Rack: ${rackDevice.name} (${totalUnits}U)`;
        if (totalUnitsEl) totalUnitsEl.textContent = `${totalUnits}U`;

        const allDevices = MapApp.state.nodes ? MapApp.state.nodes.get().map(n => n.deviceData).filter(Boolean) : [];
        const rackDevices = allDevices.filter(d => d.rack_position && parseInt(d.rack_position) > 0);

        const occupiedSlots = {};
        rackDevices.forEach(d => {
            const u = parseInt(d.rack_position);
            occupiedSlots[u] = d;
        });

        let slotsHTML = '';
        let mountedCount = 0;
        for (let u = totalUnits; u >= 1; u--) {
            const dev = occupiedSlots[u];
            if (dev) {
                mountedCount++;
                const statusBg = dev.status === 'online' ? 'bg-emerald-950/80 border-emerald-600 text-emerald-300' : 'bg-red-950/80 border-red-600 text-red-300';
                slotsHTML += `
                    <div class="flex items-center gap-2 p-1.5 rounded border ${statusBg}">
                        <span class="w-8 font-bold text-slate-400 border-r border-slate-700 pr-1 text-center">${u}U</span>
                        <i class="fas fa-server text-cyan-400"></i>
                        <span class="font-bold flex-1 truncate">${dev.name} (${dev.ip || 'DHCP'})</span>
                        <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold bg-slate-900 border border-slate-700">${dev.status || 'unknown'}</span>
                    </div>
                `;
            } else {
                slotsHTML += `
                    <div class="flex items-center gap-2 p-1.5 rounded border border-slate-800/80 bg-slate-900/40 text-slate-600 hover:bg-slate-900/80 transition">
                        <span class="w-8 font-bold text-slate-500 border-r border-slate-800 pr-1 text-center">${u}U</span>
                        <span class="text-[10px] tracking-wider uppercase">--- Empty Slot ---</span>
                    </div>
                `;
            }
        }

        slotsContainer.innerHTML = slotsHTML;
        if (mountedCountEl) mountedCountEl.textContent = `${mountedCount} Units`;
        if (availableUnitsEl) availableUnitsEl.textContent = `${totalUnits - mountedCount}U Free`;

        if (installedList) {
            if (rackDevices.length === 0) {
                installedList.innerHTML = '<span class="text-slate-500 text-xs italic">No hardware mounted yet.</span>';
            } else {
                installedList.innerHTML = rackDevices.map(d => `
                    <div class="flex items-center justify-between p-1.5 bg-slate-900 border border-slate-700 rounded text-xs">
                        <span class="text-white font-medium truncate">${d.name}</span>
                        <span class="text-cyan-400 font-mono font-bold">${d.rack_position}U</span>
                    </div>
                `).join('');
            }
        }

        modal.classList.remove('hidden');
    }
};
