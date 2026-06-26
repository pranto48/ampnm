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

            if (d.icon_url) {
                return {
                    ...baseNode,
                    shape: 'image',
                    image: d.icon_url,
                    size: (parseInt(d.icon_size) || 50) / 2, // vis.js size is radius
                    color: { border: MapApp.config.statusColorMap[d.status] || MapApp.config.statusColorMap.unknown, background: 'transparent' },
                    borderWidth: 3
                };
            }
            
            if (d.type === 'box') {
                return { ...baseNode, shape: 'box', color: { background: 'rgba(49, 65, 85, 0.5)', border: '#475569' }, margin: 20, level: -1 };
            }

            return { ...baseNode, shape: 'icon', icon: { face: "'Font Awesome 6 Free'", weight: "900", code: MapApp.config.iconMap[d.type] || MapApp.config.iconMap.other, size: parseInt(d.icon_size) || 50, color: MapApp.config.statusColorMap[d.status] || MapApp.config.statusColorMap.unknown } };
        });
        MapApp.state.nodes.clear(); 
        MapApp.state.nodes.add(visNodes);

        const visEdges = edgeData.map(e => ({ id: e.id, from: e.source_id, to: e.target_id, connection_type: e.connection_type, label: e.connection_type }));
        console.log('visEdges array before adding to dataset:', visEdges); // Added log
        MapApp.state.edges.clear(); 
        MapApp.state.edges.add(visEdges);
        console.log('Edges in dataset after load:', MapApp.state.edges.get()); // Added log
        
        MapApp.deviceManager.setupAutoPing(deviceData);
        if (!MapApp.state.network) MapApp.network.initializeMap();
        if (!MapApp.state.animationFrameId) MapApp.ui.updateAndAnimateEdges();
    },

    copyDevice: async (deviceId) => {
        if (window.userRole !== 'admin') {
            // No error message needed, as the context menu item is hidden for viewers
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
                y: createdDevice.y, // Corrected variable name
                font: { color: 'white', size: parseInt(createdDevice.name_text_size) || 14, multi: true },
                deviceData: createdDevice
            };

            let visNode;
            if (createdDevice.icon_url) {
                visNode = { ...baseNode, shape: 'image', image: createdDevice.icon_url, size: (parseInt(createdDevice.icon_size) || 50) / 2, color: { border: MapApp.config.statusColorMap[createdDevice.status] || MapApp.config.statusColorMap.unknown, background: 'transparent' }, borderWidth: 3 };
            } else if (createdDevice.type === 'box') {
                visNode = { ...baseNode, shape: 'box', color: { background: 'rgba(49, 65, 85, 0.5)', border: '#475569' }, margin: 20, level: -1 };
            } else {
                visNode = { ...baseNode, shape: 'icon', icon: { face: "'Font Awesome 6 Free'", weight: "900", code: MapApp.config.iconMap[createdDevice.type] || MapApp.config.iconMap.other, size: parseInt(createdDevice.icon_size) || 50, color: MapApp.config.statusColorMap[createdDevice.status] || MapApp.config.statusColorMap.unknown } };
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