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
            MapApp.ui.els.mapSelector.value = newMap.id; 
            await MapApp.mapManager.switchMap(newMap.id); 
            window.notyf.success(`Map "${trimmedName}" created.`);
        } catch (error) {
            console.error("Failed to create map:", error);
            window.notyf.error(error.message || "Failed to create map.");
        }
    },

    loadMaps: async () => {
        const maps = await MapApp.api.get('get_maps');
        MapApp.state.maps = maps;
        MapApp.ui.els.mapSelector.innerHTML = '';
        if (maps.length > 0) {
            maps.forEach(map => { 
                const option = document.createElement('option'); 
                option.value = map.id; 
                option.textContent = map.name; 
                MapApp.ui.els.mapSelector.appendChild(option); 
            });
            MapApp.ui.els.mapContainer.classList.remove('hidden'); 
            MapApp.ui.els.noMapsContainer.classList.add('hidden'); 
            return maps[0].id;
        } else { 
            MapApp.ui.els.mapContainer.classList.add('hidden'); 
            MapApp.ui.els.noMapsContainer.classList.remove('hidden'); 
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
            MapApp.ui.els.mapContainer.classList.add('hidden'); 
            MapApp.ui.els.noMapsContainer.classList.remove('hidden'); 
            return; 
        }
        
        MapApp.state.currentMapId = mapId; 
        const currentMap = MapApp.state.maps.find(m => m.id == mapId);
        if (currentMap) {
            MapApp.ui.els.currentMapName.textContent = currentMap.name;
            const mapEl = document.getElementById('network-map');
            mapEl.style.backgroundColor = currentMap.background_color || '';
            mapEl.style.backgroundImage = currentMap.background_image_url ? `url(${currentMap.background_image_url})` : '';
            mapEl.style.backgroundSize = 'cover';
            mapEl.style.backgroundPosition = 'center';
            // Update public view link display
            MapApp.mapManager.updatePublicViewLink(currentMap.id, currentMap.public_view_enabled);
        }
        
        // Correctly extract the 'devices' array from the API response
        const [deviceResponse, edgeData] = await Promise.all([
            MapApp.api.get('get_devices', { map_id: mapId }), 
            MapApp.api.get('get_edges', { map_id: mapId })
        ]);
        const deviceData = deviceResponse.devices || []; // Extract the array here
        
        const visNodes = deviceData.map(d => {
            let label = d.name;
            if (d.show_live_ping && d.status === 'online' && d.last_avg_time !== null) {
                label += `\n${d.last_avg_time}ms | TTL:${d.last_ttl || 'N/A'}`;
            }

            const baseNode = {
                id: d.id, label: label, title: MapApp.utils.buildNodeTitle(d),
                x: d.x, y: d.y,
                font: { color: 'white', size: parseInt(d.name_text_size) || 14, multi: true },
                deviceData: d
            };

            // Custom icon URL takes precedence
            if (d.icon_url) {
                return {
                    ...baseNode,
                    shape: 'image',
                    image: d.icon_url,
                    size: (parseInt(d.icon_size) || 50) / 2,
                    color: { border: MapApp.config.statusColorMap[d.status] || MapApp.config.statusColorMap.unknown, background: 'transparent' },
                    borderWidth: 3
                };
            }
            
            // Box type
            if (d.type === 'box') {
                return { ...baseNode, shape: 'box', color: { background: 'rgba(49, 65, 85, 0.5)', border: '#475569' }, margin: 20, level: -1 };
            }

            // Use dynamic icon mapping based on type and subchoice
            const iconCode = MapApp.mapManager.getDeviceIconUnicode(d);
            
            return { 
                ...baseNode, 
                shape: 'icon', 
                icon: { 
                    face: "'Font Awesome 6 Free'", 
                    weight: "900", 
                    code: iconCode,
                    size: parseInt(d.icon_size) || 50, 
                    color: MapApp.config.statusColorMap[d.status] || MapApp.config.statusColorMap.unknown 
                } 
            };
        });
        MapApp.state.nodes.clear(); 
        MapApp.state.nodes.add(visNodes);

        const visEdges = edgeData.map(e => ({ id: e.id, from: e.source_id, to: e.target_id, connection_type: e.connection_type, label: e.connection_type }));
        console.log('visEdges array before adding to dataset:', visEdges);
        MapApp.state.edges.clear(); 
        MapApp.state.edges.add(visEdges);
        console.log('Edges in dataset after load:', MapApp.state.edges.get());
        
        MapApp.deviceManager.setupAutoPing(deviceData);
        if (!MapApp.state.network) MapApp.network.initializeMap();
        if (!MapApp.state.animationFrameId) MapApp.ui.updateAndAnimateEdges();
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
            
            const baseNode = {
                id: createdDevice.id,
                label: createdDevice.name,
                title: MapApp.utils.buildNodeTitle(createdDevice),
                x: createdDevice.x,
                y: createdDevice.y,
                font: { color: 'white', size: parseInt(createdDevice.name_text_size) || 14, multi: true },
                deviceData: createdDevice
            };

            let visNode;
            if (createdDevice.icon_url) {
                visNode = { ...baseNode, shape: 'image', image: createdDevice.icon_url, size: (parseInt(createdDevice.icon_size) || 50) / 2, color: { border: MapApp.config.statusColorMap[createdDevice.status] || MapApp.config.statusColorMap.unknown, background: 'transparent' }, borderWidth: 3 };
            } else if (createdDevice.type === 'box') {
                visNode = { ...baseNode, shape: 'box', color: { background: 'rgba(49, 65, 85, 0.5)', border: '#475569' }, margin: 20, level: -1 };
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
        if (isEnabled) {
            MapApp.ui.els.publicViewLink.value = MapApp.utils.buildPublicMapUrl(mapId);
            MapApp.ui.els.publicViewLinkContainer.classList.remove('hidden');
        } else {
            MapApp.ui.els.publicViewLink.value = '';
            MapApp.ui.els.publicViewLinkContainer.classList.add('hidden');
        }
    }
};