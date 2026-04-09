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
    const TOOLTIP_FIELDS_STORAGE_PREFIX = 'mapTooltipFields:';
    const CONNECTION_TOOLTIP_FIELDS_STORAGE_PREFIX = 'mapConnectionTooltipFields:';
    const TOOLTIP_DISPLAY_STORAGE_PREFIX = 'mapTooltipDisplay:';

    const loadTooltipFieldsForMap = (mapId) => {
        const defaults = MapApp.utils.getDefaultTooltipFields();
        if (!mapId) return defaults;
        try {
            const raw = localStorage.getItem(`${TOOLTIP_FIELDS_STORAGE_PREFIX}${mapId}`);
            if (!raw) return defaults;
            const parsed = JSON.parse(raw);
            return { ...defaults, ...(parsed || {}) };
        } catch (error) {
            console.warn('Failed to load tooltip field settings. Using defaults.', error);
            return defaults;
        }
    };

    const saveTooltipFieldsForMap = (mapId, settings) => {
        if (!mapId) return;
        localStorage.setItem(`${TOOLTIP_FIELDS_STORAGE_PREFIX}${mapId}`, JSON.stringify(settings));
        state.tooltipFieldSettingsByMap[mapId] = settings;
    };

    const loadConnectionTooltipFieldsForMap = (mapId) => {
        const defaults = MapApp.utils.getDefaultConnectionTooltipFields();
        if (!mapId) return defaults;
        try {
            const raw = localStorage.getItem(`${CONNECTION_TOOLTIP_FIELDS_STORAGE_PREFIX}${mapId}`);
            if (!raw) return defaults;
            const parsed = JSON.parse(raw);
            return { ...defaults, ...(parsed || {}) };
        } catch (error) {
            console.warn('Failed to load connection tooltip settings. Using defaults.', error);
            return defaults;
        }
    };

    const saveConnectionTooltipFieldsForMap = (mapId, settings) => {
        if (!mapId) return;
        localStorage.setItem(`${CONNECTION_TOOLTIP_FIELDS_STORAGE_PREFIX}${mapId}`, JSON.stringify(settings));
        state.connectionTooltipFieldSettingsByMap[mapId] = settings;
    };

    const loadTooltipDisplayForMap = (mapId) => {
        const defaults = MapApp.utils.getDefaultTooltipDisplaySettings();
        if (!mapId) return defaults;
        try {
            const raw = localStorage.getItem(`${TOOLTIP_DISPLAY_STORAGE_PREFIX}${mapId}`);
            if (!raw) return defaults;
            const parsed = JSON.parse(raw);
            return { ...defaults, ...(parsed || {}) };
        } catch (error) {
            console.warn('Failed to load tooltip display settings. Using defaults.', error);
            return defaults;
        }
    };

    const saveTooltipDisplayForMap = (mapId, settings) => {
        if (!mapId) return;
        localStorage.setItem(`${TOOLTIP_DISPLAY_STORAGE_PREFIX}${mapId}`, JSON.stringify(settings));
        state.tooltipDisplaySettingsByMap[mapId] = settings;
    };

    const applyTooltipFieldCheckboxes = (settings) => {
        const merged = { ...MapApp.utils.getDefaultTooltipFields(), ...(settings || {}) };
        document.querySelectorAll('[data-tooltip-field]').forEach((checkbox) => {
            checkbox.checked = merged[checkbox.dataset.tooltipField] !== false;
        });
    };

    const readTooltipFieldCheckboxes = () => {
        const settings = MapApp.utils.getDefaultTooltipFields();
        document.querySelectorAll('[data-tooltip-field]').forEach((checkbox) => {
            settings[checkbox.dataset.tooltipField] = !!checkbox.checked;
        });
        return settings;
    };

    const applyConnectionTooltipFieldCheckboxes = (settings) => {
        const merged = { ...MapApp.utils.getDefaultConnectionTooltipFields(), ...(settings || {}) };
        document.querySelectorAll('[data-connection-tooltip-field]').forEach((checkbox) => {
            checkbox.checked = merged[checkbox.dataset.connectionTooltipField] !== false;
        });
    };

    const readConnectionTooltipFieldCheckboxes = () => {
        const settings = MapApp.utils.getDefaultConnectionTooltipFields();
        document.querySelectorAll('[data-connection-tooltip-field]').forEach((checkbox) => {
            settings[checkbox.dataset.connectionTooltipField] = !!checkbox.checked;
        });
        return settings;
    };

    const applyTooltipDisplayControls = (settings) => {
        const merged = { ...MapApp.utils.getDefaultTooltipDisplaySettings(), ...(settings || {}) };
        const density = document.getElementById('tooltipDensity');
        const fontScale = document.getElementById('tooltipFontScale');
        const fontScaleValue = document.getElementById('tooltipFontScaleValue');
        const maxWidth = document.getElementById('tooltipMaxWidth');
        const fontFamily = document.getElementById('tooltipFontFamily');
        const boxScale = document.getElementById('tooltipBoxScale');
        const boxScaleValue = document.getElementById('tooltipBoxScaleValue');
        const bgColor = document.getElementById('tooltipBgColor');
        const textColor = document.getElementById('tooltipTextColor');
        const mutedColor = document.getElementById('tooltipMutedColor');
        const accentColor = document.getElementById('tooltipAccentColor');
        if (density) density.value = merged.density || 'comfortable';
        if (fontScale) fontScale.value = String(merged.font_scale ?? 100);
        if (fontScaleValue) fontScaleValue.textContent = `${merged.font_scale ?? 100}%`;
        if (maxWidth) maxWidth.value = String(merged.max_width ?? 320);
        if (fontFamily) fontFamily.value = merged.font_family || 'system';
        if (boxScale) boxScale.value = String(merged.box_scale ?? 100);
        if (boxScaleValue) boxScaleValue.textContent = `${merged.box_scale ?? 100}%`;
        if (bgColor) bgColor.value = merged.panel_bg_color || '#0f172a';
        if (textColor) textColor.value = merged.panel_text_color || '#e2e8f0';
        if (mutedColor) mutedColor.value = merged.panel_muted_color || '#94a3b8';
        if (accentColor) accentColor.value = merged.panel_accent_color || '#22d3ee';
    };

    const readTooltipDisplayControls = () => {
        const defaults = MapApp.utils.getDefaultTooltipDisplaySettings();
        const density = document.getElementById('tooltipDensity')?.value || defaults.density;
        const fontScale = Number(document.getElementById('tooltipFontScale')?.value ?? defaults.font_scale);
        const maxWidth = Number(document.getElementById('tooltipMaxWidth')?.value ?? defaults.max_width);
        const fontFamily = document.getElementById('tooltipFontFamily')?.value || defaults.font_family;
        const boxScale = Number(document.getElementById('tooltipBoxScale')?.value ?? defaults.box_scale);
        const panelBgColor = document.getElementById('tooltipBgColor')?.value || defaults.panel_bg_color;
        const panelTextColor = document.getElementById('tooltipTextColor')?.value || defaults.panel_text_color;
        const panelMutedColor = document.getElementById('tooltipMutedColor')?.value || defaults.panel_muted_color;
        const panelAccentColor = document.getElementById('tooltipAccentColor')?.value || defaults.panel_accent_color;
        return {
            density: density === 'compact' ? 'compact' : 'comfortable',
            font_scale: Math.min(130, Math.max(85, fontScale)),
            max_width: Math.min(480, Math.max(260, maxWidth)),
            font_family: ['system', 'inter', 'roboto', 'mono'].includes(fontFamily) ? fontFamily : defaults.font_family,
            box_scale: Math.min(130, Math.max(85, boxScale)),
            panel_bg_color: panelBgColor,
            panel_text_color: panelTextColor,
            panel_muted_color: panelMutedColor,
            panel_accent_color: panelAccentColor
        };
    };

    const refreshNodeTooltips = () => {
        const updates = [];
        state.nodes.forEach((node) => {
            if (node?.deviceData) {
                updates.push({ id: node.id, title: MapApp.utils.buildNodeTitle(node.deviceData) });
            }
        });
        if (updates.length > 0) state.nodes.update(updates);
    };

    const refreshEdgeTooltips = () => {
        const updates = [];
        state.edges.forEach((edge) => {
            const fromNode = state.nodes.get(edge.from);
            const toNode = state.nodes.get(edge.to);
            const srcDevice = fromNode?.deviceData || null;
            const tgtDevice = toNode?.deviceData || null;
            updates.push({ id: edge.id, title: MapApp.utils.buildEdgeTitle(edge, srcDevice, tgtDevice) });
        });
        if (updates.length > 0) state.edges.update(updates);
    };

    const tooltipFontScaleInput = document.getElementById('tooltipFontScale');
    if (tooltipFontScaleInput) {
        tooltipFontScaleInput.addEventListener('input', () => {
            const tooltipFontScaleValue = document.getElementById('tooltipFontScaleValue');
            if (tooltipFontScaleValue) {
                tooltipFontScaleValue.textContent = `${tooltipFontScaleInput.value || 100}%`;
            }
        });
    }

    const tooltipBoxScaleInput = document.getElementById('tooltipBoxScale');
    if (tooltipBoxScaleInput) {
        tooltipBoxScaleInput.addEventListener('input', () => {
            const tooltipBoxScaleValue = document.getElementById('tooltipBoxScaleValue');
            if (tooltipBoxScaleValue) {
                tooltipBoxScaleValue.textContent = `${tooltipBoxScaleInput.value || 100}%`;
            }
        });
    }

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
        deviceManager.stopAgentPolling();
        if (state.network) {
            state.network.destroy();
            state.network = null;
        }
        window.cleanup = null;
    };

    // Event Listeners Setup
    // Only admin can edit edges
    if (window.userRole === 'admin') {
        // Connection type color preview
        const connectionTypeSelect = document.getElementById('connectionType');
        const colorPreviewContainer = document.getElementById('connectionColorPreview');
        const colorPreviewLine = document.getElementById('colorPreviewLine');
        const colorPreviewLabel = document.getElementById('colorPreviewLabel');

        if (connectionTypeSelect && colorPreviewContainer) {
            connectionTypeSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const color = selectedOption.getAttribute('data-color');

                if (color) {
                    colorPreviewContainer.classList.remove('hidden');
                    colorPreviewLine.style.backgroundColor = color;
                    colorPreviewLine.style.boxShadow = `0 0 10px ${color}`;
                    colorPreviewLabel.textContent = color;
                } else {
                    colorPreviewContainer.classList.add('hidden');
                }
            });
        }

        els.edgeForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('edgeId').value;
            const connection_type = document.getElementById('connectionType').value;
            const source_port_label = document.getElementById('edgeSourcePort').value || null;
            const target_port_label = document.getElementById('edgeTargetPort').value || null;
            try {
                await api.post('update_edge', { id, connection_type, source_port_label, target_port_label });
                closeModal('edgeModal');
                // Build label with port info
                let edgeLabel = connection_type;
                if (source_port_label && target_port_label) {
                    edgeLabel = `${source_port_label} ↔ ${target_port_label}`;
                } else if (source_port_label || target_port_label) {
                    edgeLabel = `${source_port_label || '—'} ↔ ${target_port_label || '—'}`;
                }
                const existingEdge = state.edges.get(id);
                const srcDevice = state.nodes.get(existingEdge?.from)?.deviceData || null;
                const tgtDevice = state.nodes.get(existingEdge?.to)?.deviceData || null;
                const edgeTitle = MapApp.utils.buildEdgeTitle({
                    ...(existingEdge || {}),
                    connection_type,
                    source_port_label,
                    target_port_label
                }, srcDevice, tgtDevice);
                state.edges.update({ id, connection_type, source_port_label, target_port_label, label: edgeLabel, title: edgeTitle });
                window.notyf.success('Connection updated.');
                // Trigger color update
                MapApp.ui.updateAndAnimateEdges();
            } catch (error) {
                console.error("Failed to update connection:", error);
                window.notyf.error(error.message || "An error occurred while updating connection.");
            }
        });

        // Port select change listeners for live preview
        document.getElementById('edgeSourcePort').addEventListener('change', () => MapApp.ui._updatePortPreview());
        document.getElementById('edgeTargetPort').addEventListener('change', () => MapApp.ui._updatePortPreview());
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
        els.exportBtn.addEventListener('click', async () => {
            if (!state.currentMapId) {
                window.notyf.error('No map selected to export.');
                return;
            }
            const mapName = els.mapSelector.options[els.mapSelector.selectedIndex]?.text || 'map';
            try {
                const exportData = await api.get('export_map', { map_id: state.currentMapId });
                const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(exportData, null, 2));
                const downloadAnchorNode = document.createElement('a');
                downloadAnchorNode.setAttribute("href", dataStr);
                downloadAnchorNode.setAttribute("download", `${mapName.replace(/\s+/g, '_')}_export.json`);
                document.body.appendChild(downloadAnchorNode);
                downloadAnchorNode.click();
                downloadAnchorNode.remove();
                window.notyf.success('Map exported successfully (devices, links, ports, cables).');
            } catch (error) {
                window.notyf.error(error.message || 'Failed to export map.');
            }
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
                        if (!Array.isArray(data.devices) || !Array.isArray(data.edges)) {
                            throw new Error('Invalid import file: missing devices/edges arrays.');
                        }
                        await api.post('import_map', {
                            map_id: state.currentMapId,
                            devices: data.devices,
                            edges: data.edges,
                            switch_ports: Array.isArray(data.switch_ports) ? data.switch_ports : [],
                            cables: Array.isArray(data.cables) ? data.cables : []
                        });
                        await mapManager.switchMap(state.currentMapId);
                        window.notyf.success('Map imported successfully with links, ports, and cables.');
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

    els.mapSelector.addEventListener('change', (e) => {
        state.tooltipFieldSettingsByMap[e.target.value] = loadTooltipFieldsForMap(e.target.value);
        state.connectionTooltipFieldSettingsByMap[e.target.value] = loadConnectionTooltipFieldsForMap(e.target.value);
        state.tooltipDisplaySettingsByMap[e.target.value] = loadTooltipDisplayForMap(e.target.value);
        mapManager.switchMap(e.target.value);
    });
    
    // Only admin can add edges
    if (window.userRole === 'admin') {
        els.addEdgeBtn.addEventListener('click', () => {
            state.network.addEdgeMode();
            window.notyf.info('Click on a node to start a connection.');
        });

        // Add Group Box button
        els.addGroupBoxBtn.addEventListener('click', async () => {
            if (!state.currentMapId) {
                window.notyf.error('No map selected.');
                return;
            }
            const name = prompt('Enter a name for the group box:', 'Group');
            if (!name || !name.trim()) return;
            try {
                const viewPosition = state.network.getViewPosition();
                const canvasPosition = state.network.canvas.DOMtoCanvas(viewPosition);
                const newDevice = await api.post('create_device', {
                    name: name.trim(),
                    type: 'box',
                    map_id: state.currentMapId,
                    x: canvasPosition.x,
                    y: canvasPosition.y,
                    port_config: MapApp.utils.withUpdatedBoxStyle({}, MapApp.utils.getDefaultBoxStyle())
                });
                const baseNode = {
                    id: newDevice.id,
                    label: newDevice.name,
                    title: newDevice.name,
                    x: newDevice.x,
                    y: newDevice.y,
                    font: { color: 'white', size: 16, multi: true },
                    deviceData: newDevice
                };
                const visNode = MapApp.utils.buildVisBoxNode(baseNode, newDevice);
                state.nodes.add(visNode);
                window.notyf.success(`Group box "${name.trim()}" added.`);
            } catch (error) {
                console.error('Failed to create group box:', error);
                window.notyf.error(error.message || 'Failed to create group box.');
            }
        });
    } else {
        if (els.addEdgeBtn) els.addEdgeBtn.disabled = true;
        if (els.addGroupBoxBtn) els.addGroupBoxBtn.disabled = true;
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
                document.getElementById('offlineDelaySeconds').value = currentMap.offline_delay_seconds ?? 5;
                els.publicViewToggle.checked = currentMap.public_view_enabled;
                MapApp.mapManager.updatePublicViewLink(currentMap.id, currentMap.public_view_enabled);
                applyTooltipFieldCheckboxes(loadTooltipFieldsForMap(currentMap.id));
                applyConnectionTooltipFieldCheckboxes(loadConnectionTooltipFieldsForMap(currentMap.id));
                applyTooltipDisplayControls(loadTooltipDisplayForMap(currentMap.id));
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
            const offlineDelay = parseInt(document.getElementById('offlineDelaySeconds').value, 10);
            const updates = {
                background_color: document.getElementById('mapBgColorHex').value,
                background_image_url: document.getElementById('mapBgImageUrl').value,
                public_view_enabled: els.publicViewToggle.checked,
                offline_delay_seconds: (offlineDelay >= 1 && offlineDelay <= 300) ? offlineDelay : 5
            };
            try {
                saveTooltipFieldsForMap(state.currentMapId, readTooltipFieldCheckboxes());
                saveConnectionTooltipFieldsForMap(state.currentMapId, readConnectionTooltipFieldCheckboxes());
                saveTooltipDisplayForMap(state.currentMapId, readTooltipDisplayControls());
                await api.post('update_map', { id: state.currentMapId, updates });
                await mapManager.loadMaps(); // Reload maps to get fresh data
                await mapManager.switchMap(state.currentMapId); // Re-apply settings
                refreshNodeTooltips();
                refreshEdgeTooltips();
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
                saveTooltipFieldsForMap(state.currentMapId, MapApp.utils.getDefaultTooltipFields());
                saveConnectionTooltipFieldsForMap(state.currentMapId, MapApp.utils.getDefaultConnectionTooltipFields());
                saveTooltipDisplayForMap(state.currentMapId, MapApp.utils.getDefaultTooltipDisplaySettings());
                await api.post('update_map', { id: state.currentMapId, updates });
                await mapManager.loadMaps();
                await mapManager.switchMap(state.currentMapId);
                refreshNodeTooltips();
                refreshEdgeTooltips();
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

    // Connection Type Legend Toggle
    const connectionLegend = document.getElementById('connection-legend');
    const showConnectionLegendBtn = document.getElementById('showConnectionLegend');
    const toggleConnectionLegendBtn = document.getElementById('toggleConnectionLegend');
    const mapWrapper = document.getElementById('network-map-wrapper');

    const makeDraggable = (element, boundaryContainer) => {
        let isDragging = false;
        let offsetX = 0;
        let offsetY = 0;

        const onPointerDown = (event) => {
            isDragging = true;
            const rect = element.getBoundingClientRect();
            offsetX = event.clientX - rect.left;
            offsetY = event.clientY - rect.top;

            // Switch to top/left positioning for smoother dragging
            element.style.right = 'auto';
            element.style.bottom = 'auto';
            element.style.left = `${rect.left - (boundaryContainer?.getBoundingClientRect().left || 0)}px`;
            element.style.top = `${rect.top - (boundaryContainer?.getBoundingClientRect().top || 0)}px`;

            element.setPointerCapture(event.pointerId);
        };

        const onPointerMove = (event) => {
            if (!isDragging) return;
            const bounds = boundaryContainer?.getBoundingClientRect();
            const parentLeft = bounds?.left || 0;
            const parentTop = bounds?.top || 0;
            const parentWidth = bounds?.width || window.innerWidth;
            const parentHeight = bounds?.height || window.innerHeight;

            let newLeft = event.clientX - parentLeft - offsetX;
            let newTop = event.clientY - parentTop - offsetY;

            // Clamp within container
            newLeft = Math.max(0, Math.min(newLeft, parentWidth - element.offsetWidth));
            newTop = Math.max(0, Math.min(newTop, parentHeight - element.offsetHeight));

            element.style.left = `${newLeft}px`;
            element.style.top = `${newTop}px`;
        };

        const onPointerUp = (event) => {
            isDragging = false;
            element.releasePointerCapture(event.pointerId);
        };

        element.addEventListener('pointerdown', onPointerDown);
        window.addEventListener('pointermove', onPointerMove);
        window.addEventListener('pointerup', onPointerUp);
    };

    if (showConnectionLegendBtn && connectionLegend && toggleConnectionLegendBtn) {
        showConnectionLegendBtn.addEventListener('click', () => {
            connectionLegend.classList.remove('hidden');
            showConnectionLegendBtn.classList.add('hidden');
        });

        toggleConnectionLegendBtn.addEventListener('click', () => {
            connectionLegend.classList.add('hidden');
            showConnectionLegendBtn.classList.remove('hidden');
        });

        // Show legend by default
        connectionLegend.classList.remove('hidden');
        showConnectionLegendBtn.classList.add('hidden');

        // Allow the legend to be dragged anywhere on the map wrapper
        makeDraggable(connectionLegend, mapWrapper);
    }

    // Initial Load
    (async () => {
        // Start agent registration polling for real-time notifications
        deviceManager.startAgentPolling();

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
            state.tooltipFieldSettingsByMap[initialMapId] = loadTooltipFieldsForMap(initialMapId);
            state.connectionTooltipFieldSettingsByMap[initialMapId] = loadConnectionTooltipFieldsForMap(initialMapId);
            state.tooltipDisplaySettingsByMap[initialMapId] = loadTooltipDisplayForMap(initialMapId);
            applyTooltipDisplayControls(state.tooltipDisplaySettingsByMap[initialMapId]);
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
