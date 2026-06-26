function initMap() {
    // Initialize all modules
    MapApp.ui.cacheElements();

    const {
        els
    } = MapApp.ui;
    const {
        api
    } = MapApp;
    const {
        state
    } = MapApp;
    const {
        mapManager
    } = MapApp;
    const {
        deviceManager
    } = MapApp;

    // Cleanup function for SPA navigation
    window.cleanup = () => {
        if (state.animationFrameId) {
            cancelAnimationFrame(state.animationFrameId);
            state.animationFrameId = null;
        }
        Object.values(state.pingIntervals).forEach(clearInterval);
        state.pingIntervals = {};
        if (state.globalRefreshIntervalId) {
            clearInterval(state.globalRefreshIntervalId);
            state.globalRefreshIntervalId = null;
        }
        if (state.network) {
            state.network.destroy();
            state.network = null;
        }
        window.cleanup = null;
    };

    // Event Listeners Setup
    // Only admin can edit edges
    if (window.userRole === 'admin') {
        els.edgeForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('edgeId').value;
            const connection_type = document.getElementById('connectionType').value;
            try {
                await api.post('update_edge', { id, connection_type });
                closeModal('edgeModal');
                state.edges.update({ id, connection_type, label: connection_type });
                window.notyf.success('Connection updated.');
            } catch (error) {
                console.error("Failed to update connection:", error);
                window.notyf.error(error.message || "An error occurred while updating connection.");
            }
        });
    } else {
        // Disable edge form elements for viewers
        if (els.edgeForm) {
            els.edgeForm.querySelectorAll('select, button').forEach(el => el.disabled = true);
            els.edgeForm.insertAdjacentHTML('afterend', '<p class="text-red-400 text-sm mt-2">You do not have permission to edit connections.</p>');
        }
    }

    // Only admin can scan network
    if (window.userRole === 'admin') {
        els.scanForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const subnet = document.getElementById('subnetInput').value;
            if (!subnet) return;
            els.scanInitialMessage.classList.add('hidden');
            els.scanResults.innerHTML = '';
            els.scanLoader.classList.remove('hidden');
            try {
                const result = await api.post('scan_network', { subnet });
                els.scanResults.innerHTML = result.devices.map(device => `<div class="flex items-center justify-between p-2 border-b border-slate-700"><div><div class="font-mono text-white">${device.ip}</div><div class="text-sm text-slate-400">${device.hostname || 'N/A'}</div></div><button class="add-scanned-device-btn px-3 py-1 bg-cyan-600/50 text-cyan-300 rounded-lg hover:bg-cyan-600/80 text-sm" data-ip="${device.ip}" data-name="${device.hostname || device.ip}">Add</button></div>`).join('') || '<p class="text-center text-slate-500 py-4">No devices found.</p>';
            } catch (error) {
                els.scanResults.innerHTML = '<p class="text-center text-red-400 py-4">Scan failed. Ensure nmap is installed.</p>';
            } finally {
                els.scanLoader.classList.add('hidden');
            }
        });

        els.scanResults.addEventListener('click', (e) => {
            if (e.target.classList.contains('add-scanned-device-btn')) {
                const { ip, name } = e.target.dataset;
                closeModal('scanModal');
                window.notyf.info(`Device "${name}" (IP: ${ip}) copied to clipboard. Navigate to Add Device page to create it.`);
                navigator.clipboard.writeText(JSON.stringify({ ip, name })).then(() => {
                    window.notyf.success('Device details copied to clipboard.');
                }).catch(err => {
                    console.error('Failed to copy text:', err);
                    window.notyf.error('Failed to copy device details.');
                });
                e.target.textContent = 'Added';
                e.target.disabled = true;
            }
        });
    } else {
        // Disable scan form elements for viewers
        if (els.scanForm) {
            els.scanForm.querySelectorAll('input, button').forEach(el => el.disabled = true);
            els.scanForm.insertAdjacentHTML('afterend', '<p class="text-red-400 text-sm mt-2">You do not have permission to scan the network.</p>');
        }
    }

    // Refresh Status button logic (now for all roles)
    els.refreshStatusBtn.addEventListener('click', async () => {
        els.refreshStatusBtn.disabled = true;
        await deviceManager.performBulkRefresh();
        if (!els.liveRefreshToggle.checked) els.refreshStatusBtn.disabled = false;
    });

    // Live Refresh toggle logic (now for all roles)
    els.liveRefreshToggle.addEventListener('change', (e) => {
        if (e.target.checked) {
            window.notyf.info(`Live status enabled. Updating every ${MapApp.config.REFRESH_INTERVAL_SECONDS} seconds.`);
            els.refreshStatusBtn.disabled = true;
            deviceManager.performBulkRefresh();
            state.globalRefreshIntervalId = setInterval(deviceManager.performBulkRefresh, MapApp.config.REFRESH_INTERVAL_SECONDS * 1000);
        } else {
            if (state.globalRefreshIntervalId) clearInterval(state.globalRefreshIntervalId);
            state.globalRefreshIntervalId = null;
            els.refreshStatusBtn.disabled = false;
            window.notyf.info('Live status disabled.');
        }
    });

    // Only admin can export/import map
    if (window.userRole === 'admin') {
        els.exportBtn.addEventListener('click', () => {
            if (!state.currentMapId) {
                window.notyf.error('No map selected to export.');
                return;
            }
            const mapName = els.mapSelector.options[els.mapSelector.selectedIndex].text;
            const devices = state.nodes.get({ fields: ['id', 'deviceData'] }).map(node => ({
                id: node.id,
                ...node.deviceData
            }));
            const edges = state.edges.get({ fields: ['from', 'to', 'connection_type'] }).map(edge => ({
                source_id: edge.from, // Map 'from' to 'source_id'
                target_id: edge.to,   // Map 'to' to 'target_id'
                connection_type: edge.connection_type
            }));
            const exportData = { devices, edges };
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(exportData, null, 2));
            const downloadAnchorNode = document.createElement('a');
            downloadAnchorNode.setAttribute("href", dataStr);
            downloadAnchorNode.setAttribute("download", `${mapName.replace(/\s+/g, '_')}_export.json`);
            document.body.appendChild(downloadAnchorNode);
            downloadAnchorNode.click();
            downloadAnchorNode.remove();
            window.notyf.success('Map exported successfully.');
        });

        els.importBtn.addEventListener('click', () => els.importFile.click());
        els.importFile.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            if (confirm('This will overwrite the current map. Are you sure?')) {
                const reader = new FileReader();
                reader.onload = async (event) => {
                    try {
                        const data = JSON.parse(event.target.result);
                        await api.post('import_map', { map_id: state.currentMapId, ...data });
                        await mapManager.switchMap(state.currentMapId);
                        window.notyf.success('Map imported successfully.');
                    } catch (err) {
                        window.notyf.error('Failed to import map: ' + err.message);
                    }
                };
                reader.readAsText(file);
            }
            els.importFile.value = '';
        });
    } else {
        // Disable export/import buttons for viewers
        if (els.exportBtn) els.exportBtn.disabled = true;
        if (els.importBtn) els.importBtn.disabled = true;
    }

    els.fullscreenBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) els.mapWrapper.requestFullscreen();
        else document.exitFullscreen();
    });
    document.addEventListener('fullscreenchange', () => {
        const icon = els.fullscreenBtn.querySelector('i');
        icon.classList.toggle('fa-expand', !document.fullscreenElement);
        icon.classList.toggle('fa-compress', !!document.fullscreenElement);
    });

    // Only admin can create/rename/delete maps
    if (window.userRole === 'admin') {
        els.newMapBtn.addEventListener('click', mapManager.createMap);
        els.createFirstMapBtn.addEventListener('click', mapManager.createMap);
        els.renameMapBtn.addEventListener('click', async () => {
            if (!state.currentMapId) {
                window.notyf.error('No map selected to rename.');
                return;
            }
            const selectedOption = els.mapSelector.options[els.mapSelector.selectedIndex];
            const currentName = selectedOption.text;
            const newName = prompt('Enter a new name for the map:', currentName);
        
            if (newName && newName.trim() !== '' && newName !== currentName) {
                try {
                    await api.post('update_map', { id: state.currentMapId, updates: { name: newName } });
                    selectedOption.text = newName;
                    els.currentMapName.textContent = newName;
                    window.notyf.success('Map renamed successfully.');
                } catch (error) {
                    console.error("Failed to rename map:", error);
                    window.notyf.error(error.message || "Could not rename map.");
                }
            }
        });
        els.deleteMapBtn.addEventListener('click', async () => {
            if (confirm(`Delete map "${els.mapSelector.options[els.mapSelector.selectedIndex].text}"?`)) {
                try {
                    await api.post('delete_map', { id: state.currentMapId });
                    const firstMapId = await mapManager.loadMaps();
                    await mapManager.switchMap(firstMapId);
                    window.notyf.success('Map deleted.');
                } catch (error) {
                    console.error("Failed to delete map:", error);
                    window.notyf.error(error.message || "Could not delete map.");
                }
            }
        });
    } else {
        // Disable map management buttons for viewers
        if (els.newMapBtn) els.newMapBtn.disabled = true;
        if (els.createFirstMapBtn) els.createFirstMapBtn.disabled = true;
        if (els.renameMapBtn) els.renameMapBtn.disabled = true;
        if (els.deleteMapBtn) els.deleteMapBtn.disabled = true;
        // Add a message for viewers
        const mapSelectionControls = document.querySelector('#map-selection .flex.gap-4');
        if (mapSelectionControls) {
            mapSelectionControls.insertAdjacentHTML('afterend', '<p class="text-red-400 text-sm mt-2">You do not have permission to manage maps.</p>');
        }
    }

    els.mapSelector.addEventListener('change', (e) => mapManager.switchMap(e.target.value));
    
    // Only admin can add edges
    if (window.userRole === 'admin') {
        els.addEdgeBtn.addEventListener('click', () => {
            state.network.addEdgeMode();
            window.notyf.info('Click on a node to start a connection.');
        });
    } else {
        if (els.addEdgeBtn) els.addEdgeBtn.disabled = true;
    }

    els.cancelEdgeBtn.addEventListener('click', () => closeModal('edgeModal'));
    els.scanNetworkBtn.addEventListener('click', () => openModal('scanModal'));
    els.closeScanModal.addEventListener('click', () => closeModal('scanModal'));

    // Place Device Modal Logic (Admin only)
    if (window.userRole === 'admin') {
        els.placeDeviceBtn.addEventListener('click', async () => {
            openModal('placeDeviceModal');
            els.placeDeviceLoader.classList.remove('hidden');
            els.placeDeviceList.innerHTML = '';
            try {
                const unmappedDevices = await api.get('get_devices', { unmapped: true });
                if (unmappedDevices.devices.length > 0) { // Access 'devices' array
                    els.placeDeviceList.innerHTML = unmappedDevices.devices.map(device => `
                        <div class="flex items-center justify-between p-2 border-b border-slate-700 hover:bg-slate-700/50">
                            <div>
                                <div class="font-medium text-white">${device.name}</div>
                                <div class="text-sm text-slate-400 font-mono">${device.ip || 'No IP'}</div>
                            </div>
                            <button class="place-device-item-btn px-3 py-1 bg-cyan-600/50 text-cyan-300 rounded-lg hover:bg-cyan-600/80 text-sm" data-id="${device.id}">
                                Place
                            </button>
                        </div>
                    `).join('');
                } else {
                    els.placeDeviceList.innerHTML = '<p class="text-center text-slate-500 py-4">No unassigned devices found.</p>';
                }
            } catch (error) {
                console.error('Failed to load unmapped devices:', error);
                window.notyf.error('Could not load unassigned devices.');
                els.placeDeviceList.innerHTML = '<p class="text-center text-red-400 py-4">Could not load devices.</p>';
            } finally {
                els.placeDeviceLoader.classList.add('hidden');
            }
        });
        els.closePlaceDeviceModal.addEventListener('click', () => closeModal('placeDeviceModal'));
        els.placeDeviceList.addEventListener('click', async (e) => {
            if (e.target.classList.contains('place-device-item-btn')) {
                const deviceId = e.target.dataset.id;
                e.target.disabled = true;
                e.target.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                const viewPosition = state.network.getViewPosition();
                const canvasPosition = state.network.canvas.DOMtoCanvas(viewPosition);

                try {
                    const updatedDevice = await api.post('update_device', {
                        id: deviceId,
                        updates: { map_id: state.currentMapId, x: canvasPosition.x, y: canvasPosition.y }
                    });

                    // Add the device to the map visually
                    const baseNode = {
                        id: updatedDevice.id, label: updatedDevice.name, title: MapApp.utils.buildNodeTitle(updatedDevice),
                        x: updatedDevice.x, y: updatedDevice.y, // Corrected variable name
                        font: { color: 'white', size: parseInt(updatedDevice.name_text_size) || 14, multi: true },
                        deviceData: updatedDevice
                    };
                    let visNode;
                    if (updatedDevice.icon_url) {
                        visNode = { ...baseNode, shape: 'image', image: updatedDevice.icon_url, size: (parseInt(updatedDevice.icon_size) || 50) / 2, color: { border: MapApp.config.statusColorMap[updatedDevice.status] || MapApp.config.statusColorMap.unknown, background: 'transparent' }, borderWidth: 3 };
                    } else if (updatedDevice.type === 'box') {
                        visNode = { ...baseNode, shape: 'box', color: { background: 'rgba(49, 65, 85, 0.5)', border: '#475569' }, margin: 20, level: -1 };
                    } else {
                        visNode = { ...baseNode, shape: 'icon', icon: { face: "'Font Awesome 6 Free'", weight: "900", code: MapApp.config.iconMap[updatedDevice.type] || MapApp.config.iconMap.other, size: parseInt(updatedDevice.icon_size) || 50, color: MapApp.config.statusColorMap[updatedDevice.status] || MapApp.config.statusColorMap.unknown } };
                    }
                    state.nodes.add(visNode);
                    
                    window.notyf.success(`Device "${updatedDevice.name}" placed on map.`);
                    e.target.closest('.flex').remove(); // Remove from list
                    if (els.placeDeviceList.children.length === 0) {
                        els.placeDeviceList.innerHTML = '<p class="text-center text-slate-500 py-4">No unassigned devices found.</p>';
                    }
                } catch (error) {
                    console.error('Failed to place device:', error);
                    window.notyf.error('Failed to place device.');
                }
            }
        });
    } else {
        if (els.placeDeviceBtn) els.placeDeviceBtn.disabled = true;
    }

    // Map Settings Modal Logic (Admin only)
    if (window.userRole === 'admin') {
        els.mapSettingsBtn.addEventListener('click', () => {
            const currentMap = state.maps.find(m => m.id == state.currentMapId);
            if (currentMap) {
                document.getElementById('mapBgColor').value = currentMap.background_color || '#1e293b';
                document.getElementById('mapBgColorHex').value = currentMap.background_color || '#1e293b';
                document.getElementById('mapBgImageUrl').value = currentMap.background_image_url || '';
                els.publicViewToggle.checked = currentMap.public_view_enabled;
                MapApp.mapManager.updatePublicViewLink(currentMap.id, currentMap.public_view_enabled);
                openModal('mapSettingsModal');
            }
        });
        els.cancelMapSettingsBtn.addEventListener('click', () => closeModal('mapSettingsModal'));
        document.getElementById('mapBgColor').addEventListener('input', (e) => {
            document.getElementById('mapBgColorHex').value = e.target.value;
        });
        document.getElementById('mapBgColorHex').addEventListener('input', (e) => {
            document.getElementById('mapBgColor').value = e.target.value;
        });

        els.publicViewToggle.addEventListener('change', () => {
            MapApp.mapManager.updatePublicViewLink(state.currentMapId, els.publicViewToggle.checked);
        });

        els.copyPublicLinkBtn.addEventListener('click', async () => {
            const publicLink = els.publicViewLink.value;
            if (publicLink) {
                try {
                    await navigator.clipboard.writeText(publicLink);
                    window.notyf.success('Public link copied to clipboard!');
                } catch (err) {
                    console.error('Failed to copy public link:', err);
                    window.notyf.error('Failed to copy public link. Please copy manually.');
                }
            }
        });

        els.openPublicLinkBtn.addEventListener('click', () => {
            const publicLink = els.publicViewLink.value;
            if (publicLink) {
                window.open(publicLink, '_blank', 'noopener');
            } else {
                window.notyf.error('Enable public view to generate a link first.');
            }
        });

        els.mapSettingsForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const updates = {
                background_color: document.getElementById('mapBgColorHex').value,
                background_image_url: document.getElementById('mapBgImageUrl').value,
                public_view_enabled: els.publicViewToggle.checked
            };
            try {
                await api.post('update_map', { id: state.currentMapId, updates });
                await mapManager.loadMaps(); // Reload maps to get fresh data
                await mapManager.switchMap(state.currentMapId); // Re-apply settings
                closeModal('mapSettingsModal');
                window.notyf.success('Map settings saved.');
            } catch (error) {
                console.error("Failed to save map settings:", error);
                window.notyf.error(error.message || "Could not save map settings.");
            }
        });
        els.resetMapBgBtn.addEventListener('click', async () => {
            try {
                const updates = { background_color: null, background_image_url: null, public_view_enabled: false };
                await api.post('update_map', { id: state.currentMapId, updates });
                await mapManager.loadMaps();
                await mapManager.switchMap(state.currentMapId);
                closeModal('mapSettingsModal');
                window.notyf.success('Map background and public view reset to default.');
            } catch (error) {
                console.error("Failed to reset map background:", error);
                window.notyf.error(error.message || "Could not reset map background.");
            }
        });
        els.mapBgUpload.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const loader = document.getElementById('mapBgUploadLoader');
            loader.classList.remove('hidden');
            const formData = new FormData();
            formData.append('map_id', state.currentMapId);
            formData.append('backgroundFile', file);
            try {
                const res = await fetch(`${MapApp.config.API_URL}?action=upload_map_background`, { method: 'POST', body: formData });
                const result = await res.json();
                if (result.success) {
                    document.getElementById('mapBgImageUrl').value = result.url;
                    window.notyf.success('Image uploaded. Click Save to apply.');
                } else { throw new Error(result.error); }
            } catch (error) {
                window.notyf.error('Upload failed: ' + error.message);
            } finally {
                loader.classList.add('hidden');
                e.target.value = '';
            }
        });
    } else {
        if (els.mapSettingsBtn) els.mapSettingsBtn.disabled = true;
    }

    // Share Map Logic for map.php (Accessible to all roles)
    els.shareMapBtn.addEventListener('click', async () => {
        if (!state.currentMapId) {
            window.notyf.error('No map selected to share.');
            return;
        }
        const shareUrl = MapApp.utils.buildPublicMapUrl(state.currentMapId);
        try {
            await navigator.clipboard.writeText(shareUrl);
            window.notyf.success('Share link copied to clipboard!');
        } catch (err) {
            console.error('Failed to copy share link:', err);
            window.notyf.error('Failed to copy share link. Please copy manually: ' + shareUrl);
        }
    });

    // Initial Load
    (async () => {
        // Set live refresh to ON by default for viewers
        if (window.userRole === 'viewer') {
            els.liveRefreshToggle.checked = true;
            els.liveRefreshToggle.disabled = true; // Disable toggle for viewers
            els.refreshStatusBtn.disabled = true; // Disable manual refresh button for viewers when live is on
            deviceManager.performBulkRefresh(); // Initial refresh
            state.globalRefreshIntervalId = setInterval(deviceManager.performBulkRefresh, MapApp.config.REFRESH_INTERVAL_SECONDS * 1000);
        } else {
            els.liveRefreshToggle.checked = false; // Default off for admin
            els.liveRefreshToggle.disabled = false; // Enable toggle for admin
        }

        const urlParams = new URLSearchParams(window.location.search);
        const mapToLoad = urlParams.get('map_id'); // Check for map_id in URL
        
        const firstMapId = await mapManager.loadMaps();
        const initialMapId = mapToLoad || firstMapId; // Prioritize URL param
        
        if (initialMapId) {
            els.mapSelector.value = initialMapId;
            await mapManager.switchMap(initialMapId);
            const deviceToEdit = urlParams.get('edit_device_id');
            if (deviceToEdit && state.nodes.get(deviceToEdit)) {
                window.notyf.info('To edit a device, click the "Edit" option from its context menu.');
                const newUrl = window.location.pathname + `?map_id=${initialMapId}`;
                history.replaceState(null, '', newUrl);
            }
        }

        // Disable modification buttons for viewers after initial load
        if (window.userRole === 'viewer') {
            els.newMapBtn.disabled = true;
            els.renameMapBtn.disabled = true;
            els.deleteMapBtn.disabled = true;
            els.placeDeviceBtn.disabled = true;
            els.addDeviceBtn.style.display = 'none'; // Hide link
            els.addEdgeBtn.disabled = true;
            els.exportBtn.disabled = true;
            els.importBtn.disabled = true;
            els.mapSettingsBtn.disabled = true;
            els.scanNetworkBtn.disabled = true;
            if (els.createFirstMapBtn) els.createFirstMapBtn.disabled = true; // If no maps exist
            
            const mapSelectionControls = document.querySelector('#map-selection .flex.gap-4');
            if (mapSelectionControls) {
                mapSelectionControls.insertAdjacentHTML('afterend', '<p class="text-red-400 text-sm mt-2">You do not have permission to manage maps or devices.</p>');
            }
        }
    })();
}