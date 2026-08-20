/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
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
        const connectionRunStyle = document.getElementById('connectionRunStyle');
        const connectionAnimationSpeed = document.getElementById('connectionAnimationSpeed');
        const connectionAnimationSpeedValue = document.getElementById('connectionAnimationSpeedValue');
        const globalThickness = merged.connection_line_thickness ?? 2;
        const globalThicknessInput = document.getElementById('globalLineThickness');
        const globalThicknessPxInput = document.getElementById('globalLineThicknessPx');
        const globalThicknessVal = document.getElementById('globalLineThicknessValue');

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
        if (connectionRunStyle) connectionRunStyle.value = merged.connection_run_style || 'auto';
        if (connectionAnimationSpeed) connectionAnimationSpeed.value = String(merged.connection_animation_speed ?? 100);
        if (connectionAnimationSpeedValue) connectionAnimationSpeedValue.textContent = `${merged.connection_animation_speed ?? 100}%`;
        if (globalThicknessInput) globalThicknessInput.value = String(globalThickness);
        if (globalThicknessPxInput) globalThicknessPxInput.value = String(globalThickness);
        if (globalThicknessVal) globalThicknessVal.textContent = `${globalThickness}px`;
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
        const connectionRunStyle = document.getElementById('connectionRunStyle')?.value || defaults.connection_run_style;
        const connectionAnimationSpeed = Number(document.getElementById('connectionAnimationSpeed')?.value ?? defaults.connection_animation_speed);
        const globalLineThickness = parseFloat(document.getElementById('globalLineThicknessPx')?.value || document.getElementById('globalLineThickness')?.value || 2);

        return {
            density: density === 'compact' ? 'compact' : 'comfortable',
            font_scale: Math.min(130, Math.max(85, fontScale)),
            max_width: Math.min(480, Math.max(260, maxWidth)),
            font_family: ['system', 'inter', 'roboto', 'mono'].includes(fontFamily) ? fontFamily : defaults.font_family,
            box_scale: Math.min(130, Math.max(85, boxScale)),
            panel_bg_color: panelBgColor,
            panel_text_color: panelTextColor,
            panel_muted_color: panelMutedColor,
            panel_accent_color: panelAccentColor,
            connection_run_style: ['auto', 'solid', 'dashed', 'dotted', 'data-flow', 'data-stream', 'pulse', 'wave', 'morse', 'zipper'].includes(connectionRunStyle) ? connectionRunStyle : defaults.connection_run_style,
            connection_animation_speed: Math.min(200, Math.max(0, connectionAnimationSpeed)),
            connection_line_thickness: Math.min(16, Math.max(1, globalLineThickness))
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

    const connectionAnimationSpeedInput = document.getElementById('connectionAnimationSpeed');
    if (connectionAnimationSpeedInput) {
        connectionAnimationSpeedInput.addEventListener('input', () => {
            const connectionAnimationSpeedValue = document.getElementById('connectionAnimationSpeedValue');
            if (connectionAnimationSpeedValue) {
                connectionAnimationSpeedValue.textContent = `${connectionAnimationSpeedInput.value || 100}%`;
            }
            if (state.currentMapId) {
                state.tooltipDisplaySettingsByMap[state.currentMapId] = state.tooltipDisplaySettingsByMap[state.currentMapId] || {};
                state.tooltipDisplaySettingsByMap[state.currentMapId].connection_animation_speed = Number(connectionAnimationSpeedInput.value);
            }
        });
    }

    const connectionRunStyleSelect = document.getElementById('connectionRunStyle');
    if (connectionRunStyleSelect) {
        connectionRunStyleSelect.addEventListener('change', () => {
            if (state.currentMapId) {
                state.tooltipDisplaySettingsByMap[state.currentMapId] = state.tooltipDisplaySettingsByMap[state.currentMapId] || {};
                state.tooltipDisplaySettingsByMap[state.currentMapId].connection_run_style = connectionRunStyleSelect.value;
                MapApp.ui.updateStaticEdgeColors();
            }
        });
    }

    const globalLineThicknessInput = document.getElementById('globalLineThickness');
    const globalLineThicknessPxInput = document.getElementById('globalLineThicknessPx');
    const globalLineThicknessValue = document.getElementById('globalLineThicknessValue');

    if (globalLineThicknessInput && globalLineThicknessPxInput) {
        globalLineThicknessInput.addEventListener('input', () => {
            const val = parseFloat(globalLineThicknessInput.value) || 2;
            globalLineThicknessPxInput.value = val;
            if (globalLineThicknessValue) globalLineThicknessValue.textContent = `${val}px`;
            if (state.currentMapId) {
                state.tooltipDisplaySettingsByMap[state.currentMapId] = state.tooltipDisplaySettingsByMap[state.currentMapId] || {};
                state.tooltipDisplaySettingsByMap[state.currentMapId].connection_line_thickness = val;
                MapApp.ui.updateStaticEdgeColors();
            }
        });
        globalLineThicknessPxInput.addEventListener('input', () => {
            const val = Math.max(1, Math.min(16, parseFloat(globalLineThicknessPxInput.value) || 2));
            globalLineThicknessInput.value = val;
            if (globalLineThicknessValue) globalLineThicknessValue.textContent = `${val}px`;
            if (state.currentMapId) {
                state.tooltipDisplaySettingsByMap[state.currentMapId] = state.tooltipDisplaySettingsByMap[state.currentMapId] || {};
                state.tooltipDisplaySettingsByMap[state.currentMapId].connection_line_thickness = val;
                MapApp.ui.updateStaticEdgeColors();
            }
        });
    }

    const mapSettingsTabButtons = document.querySelectorAll('.map-settings-tab-btn');
    const mapSettingsPanels = document.querySelectorAll('[data-map-settings-panel]');
    const activateMapSettingsPanel = (tab) => {
        mapSettingsPanels.forEach((panel) => {
            panel.classList.toggle('hidden', panel.getAttribute('data-map-settings-panel') !== tab);
        });
        mapSettingsTabButtons.forEach((btn) => {
            const isActive = btn.getAttribute('data-map-settings-tab') === tab;
            btn.classList.toggle('bg-cyan-700', isActive);
            btn.classList.toggle('text-white', isActive);
            btn.classList.toggle('bg-slate-700', !isActive);
            btn.classList.toggle('text-slate-200', !isActive);
        });
    };
    if (mapSettingsTabButtons.length > 0) {
        mapSettingsTabButtons.forEach((btn) => {
            btn.addEventListener('click', () => activateMapSettingsPanel(btn.getAttribute('data-map-settings-tab')));
        });
        activateMapSettingsPanel('device');
    }
    document.addEventListener('click', (evt) => {
        const tabBtn = evt.target && evt.target.closest ? evt.target.closest('.map-settings-tab-btn') : null;
        if (!tabBtn) return;
        const tab = tabBtn.getAttribute('data-map-settings-tab');
        if (!tab) return;
        activateMapSettingsPanel(tab);
    });

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

        // Color picker and hex input sync
        const edgeColorPicker = document.getElementById('edgeColorPicker');
        const edgeColorHex = document.getElementById('edgeColorHex');
        if (edgeColorPicker && edgeColorHex) {
            edgeColorPicker.addEventListener('input', (e) => {
                edgeColorHex.value = e.target.value;
            });
            edgeColorHex.addEventListener('input', (e) => {
                const val = e.target.value;
                if (/^#[0-9A-F]{6}$/i.test(val)) {
                    edgeColorPicker.value = val;
                }
            });
        }

        els.edgeForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('edgeId').value;
            const connection_type = document.getElementById('connectionType').value;
            const source_port_label = document.getElementById('edgeSourcePort').value || null;
            const target_port_label = document.getElementById('edgeTargetPort').value || null;
            
            // Gather custom edge attributes
            const thickness = parseInt(document.getElementById('edgeThickness').value) || 2;
            const line_style = document.getElementById('edgeLineStyle').value || 'solid';
            const color = document.getElementById('edgeColorHex').value || null;
            const arrowsVal = document.getElementById('edgeArrows').value || 'none';
            const labelVal = document.getElementById('edgeLabel').value || null;
            const animated = document.getElementById('edgeAnimated').checked ? 1 : 0;

            try {
                await api.post('update_edge', { 
                    id, 
                    connection_type, 
                    source_port_label, 
                    target_port_label,
                    thickness,
                    line_style,
                    color,
                    arrows: arrowsVal,
                    label: labelVal,
                    animated
                });
                closeModal('edgeModal');

                // Build default label if no custom label is provided
                let edgeLabel = labelVal;
                if (!edgeLabel) {
                    if (source_port_label && target_port_label) {
                        edgeLabel = `${source_port_label} ↔ ${target_port_label}`;
                    } else if (source_port_label || target_port_label) {
                        edgeLabel = `${source_port_label || '—'} ↔ ${target_port_label || '—'}`;
                    } else {
                        edgeLabel = connection_type;
                    }
                }

                const existingEdge = state.edges.get(id);
                const srcDevice = state.nodes.get(existingEdge?.from)?.deviceData || null;
                const tgtDevice = state.nodes.get(existingEdge?.to)?.deviceData || null;
                
                const edgeTitle = MapApp.utils.buildEdgeTitle({
                    ...(existingEdge || {}),
                    connection_type,
                    source_port_label,
                    target_port_label,
                    label: labelVal
                }, srcDevice, tgtDevice);

                // Set formatting parameters for Vis.js dataset
                const visColor = color ? { color, hover: color, highlight: color } : undefined;
                let visDashes = false;
                if (line_style === 'dashed') {
                    visDashes = [6, 4];
                } else if (line_style === 'dotted') {
                    visDashes = [2, 3];
                } else if (line_style === 'solid') {
                    visDashes = false;
                } else if (connection_type === 'wifi' || connection_type === 'radio' || connection_type === 'logical-tunneling') {
                    visDashes = [5, 5];
                }

                let visArrows = undefined;
                if (arrowsVal === 'to') visArrows = { to: { enabled: true } };
                else if (arrowsVal === 'from') visArrows = { from: { enabled: true } };
                else if (arrowsVal === 'both') visArrows = { to: { enabled: true }, from: { enabled: true } };

                state.edges.update({ 
                    id, 
                    connection_type, 
                    source_port_label, 
                    target_port_label, 
                    label: edgeLabel, 
                    title: edgeTitle,
                    width: thickness,
                    color: visColor,
                    dashes: visDashes,
                    arrows: visArrows,
                    custom_thickness: thickness,
                    custom_color: color,
                    custom_line_style: line_style,
                    custom_arrows: arrowsVal,
                    custom_label: labelVal,
                    custom_animated: animated
                });

                window.notyf.success('Connection updated.');
                // Trigger color/animation updates
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
    if (els.refreshStatusBtn) {
        els.refreshStatusBtn.addEventListener('click', async () => {
            els.refreshStatusBtn.disabled = true;
            await deviceManager.performBulkRefresh();
            if (els.liveRefreshToggle && !els.liveRefreshToggle.checked) els.refreshStatusBtn.disabled = false;
        });
    }

    // Live Refresh toggle logic (now for all roles)
    if (els.liveRefreshToggle) {
        els.liveRefreshToggle.addEventListener('change', (e) => {
            if (e.target.checked) {
                window.notyf.info(`Live status enabled. Updating every ${MapApp.config.REFRESH_INTERVAL_SECONDS} seconds.`);
                if (els.refreshStatusBtn) els.refreshStatusBtn.disabled = true;
                deviceManager.performBulkRefresh();
                state.globalRefreshIntervalId = setInterval(deviceManager.performBulkRefresh, MapApp.config.REFRESH_INTERVAL_SECONDS * 1000);
            } else {
                if (state.globalRefreshIntervalId) clearInterval(state.globalRefreshIntervalId);
                state.globalRefreshIntervalId = null;
                if (els.refreshStatusBtn) els.refreshStatusBtn.disabled = false;
                window.notyf.info('Live status disabled.');
            }
        });
    }

    // Only admin can export/import map
    if (window.userRole === 'admin') {
        if (els.exportBtn) {
            els.exportBtn.addEventListener('click', async () => {
                if (!state.currentMapId) {
                    window.notyf.error('No map selected to export.');
                    return;
                }
                const mapName = els.mapSelector?.options[els.mapSelector.selectedIndex]?.text || 'map';
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
        }

        if (els.importBtn && els.importFile) {
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
        }
    } else {
        // Disable export/import buttons for viewers
        if (els.exportBtn) els.exportBtn.disabled = true;
        if (els.importBtn) els.importBtn.disabled = true;
    }

    if (els.fullscreenBtn && els.mapWrapper) {
        els.fullscreenBtn.addEventListener('click', () => {
            if (!document.fullscreenElement) els.mapWrapper.requestFullscreen();
            else document.exitFullscreen();
        });
        document.addEventListener('fullscreenchange', () => {
            const icon = els.fullscreenBtn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-expand', !document.fullscreenElement);
                icon.classList.toggle('fa-compress', !!document.fullscreenElement);
            }
        });
    }

    // Only admin can create/rename/delete maps
    if (window.userRole === 'admin') {
        if (els.newMapBtn) els.newMapBtn.addEventListener('click', mapManager.createMap);
        if (els.createFirstMapBtn) els.createFirstMapBtn.addEventListener('click', mapManager.createMap);
        if (els.renameMapBtn) {
            els.renameMapBtn.addEventListener('click', async () => {
                if (!state.currentMapId || !els.mapSelector) {
                    window.notyf.error('No map selected to rename.');
                    return;
                }
                const selectedOption = els.mapSelector.options[els.mapSelector.selectedIndex];
                const currentName = selectedOption ? selectedOption.text : '';
                const newName = prompt('Enter a new name for the map:', currentName);
            
                if (newName && newName.trim() !== '' && newName !== currentName) {
                    try {
                        await api.post('update_map', { id: state.currentMapId, updates: { name: newName } });
                        if (selectedOption) selectedOption.text = newName;
                        if (els.currentMapName) els.currentMapName.textContent = newName;
                        window.notyf.success('Map renamed successfully.');
                    } catch (error) {
                        console.error("Failed to rename map:", error);
                        window.notyf.error(error.message || "Could not rename map.");
                    }
                }
            });
        }
        if (els.deleteMapBtn) {
            els.deleteMapBtn.addEventListener('click', async () => {
                const mapText = els.mapSelector?.options[els.mapSelector.selectedIndex]?.text || '';
                if (confirm(`Delete map "${mapText}"?`)) {
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
        }
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

        // Add Text Label button
        if (els.addTextBtn) {
            els.addTextBtn.addEventListener('click', () => {
                MapApp.openTextModal();
            });
        }
        if (els.addMapTitleBtn) {
            els.addMapTitleBtn.addEventListener('click', () => {
                const currentMapName = document.getElementById('currentMapName')?.textContent?.trim() || 'NETWORK MAP';
                MapApp.openTextModal({
                    name: currentMapName.toUpperCase(),
                    name_text_color: '#22d3ee',
                    name_text_bold: 1,
                    name_text_italic: 0,
                    textStyle: {
                        size: 32,
                        bold: true,
                        italic: false,
                        align: 'center',
                        fontFamily: 'sans',
                        color: '#22d3ee',
                        containerStyle: 'card',
                        fillColor: '#0f172a',
                        borderColor: '#06b6d4'
                    }
                });
            });
        }
    } else {
        if (els.addEdgeBtn) els.addEdgeBtn.disabled = true;
        if (els.addGroupBoxBtn) els.addGroupBoxBtn.disabled = true;
        if (els.addTextBtn) els.addTextBtn.disabled = true;
        if (els.addMapTitleBtn) els.addMapTitleBtn.disabled = true;
    }

    // Text Label & Annotation System Controller
    let textModalBoldState = false;
    let textModalItalicState = false;

    function updateTextLabelPreview() {
        const textContent = document.getElementById('textLabelContent')?.value || 'Text Preview';
        const color = document.getElementById('textLabelColorPicker')?.value || '#22d3ee';
        const size = document.getElementById('textLabelSize')?.value || '16';
        const align = document.getElementById('textLabelAlign')?.value || 'center';
        const fontFam = document.getElementById('textLabelFontFamily')?.value || 'sans';
        const container = document.getElementById('textLabelContainerStyle')?.value || 'transparent';
        const fill = document.getElementById('textLabelBgColorPicker')?.value || '#0f172a';
        const border = document.getElementById('textLabelBorderColorPicker')?.value || '#334155';

        const fontFaceMap = {
            sans: "Inter, system-ui, -apple-system, sans-serif",
            mono: "'JetBrains Mono', 'SFMono-Regular', Consolas, monospace",
            serif: "Georgia, Cambria, 'Times New Roman', serif",
            display: "Outfit, Impact, sans-serif"
        };

        const previewText = document.getElementById('textLabelPreviewText');
        const previewBox = document.getElementById('textLabelPreviewBox');

        if (previewText) {
            previewText.textContent = textContent;
            previewText.style.color = color;
            previewText.style.fontSize = size + 'px';
            previewText.style.fontWeight = textModalBoldState ? 'bold' : 'normal';
            previewText.style.fontStyle = textModalItalicState ? 'italic' : 'normal';
            previewText.style.fontFamily = fontFaceMap[fontFam] || fontFaceMap.sans;
            previewText.style.textAlign = align;
        }

        if (previewBox) {
            if (container === 'card') {
                previewBox.style.backgroundColor = 'rgba(15, 23, 42, 0.85)';
                previewBox.style.border = '1px solid #334155';
            } else if (container === 'badge') {
                previewBox.style.backgroundColor = fill;
                previewBox.style.border = '1px solid ' + border;
            } else {
                previewBox.style.backgroundColor = 'transparent';
                previewBox.style.border = '1px solid transparent';
            }
        }
    }

    MapApp.openTextModal = function(deviceData = null) {
        if (!state.currentMapId) {
            if (window.notyf) window.notyf.error('No map selected.');
            return;
        }

        const titleEl = document.getElementById('textModalTitle');
        const idInput = document.getElementById('textLabelDeviceId');
        const contentInput = document.getElementById('textLabelContent');
        const sizeInput = document.getElementById('textLabelSize');
        const boldToggle = document.getElementById('textLabelBoldToggle');
        const italicToggle = document.getElementById('textLabelItalicToggle');
        const alignInput = document.getElementById('textLabelAlign');
        const fontInput = document.getElementById('textLabelFontFamily');
        const colorPicker = document.getElementById('textLabelColorPicker');
        const colorVal = document.getElementById('textLabelColorVal');
        const containerInput = document.getElementById('textLabelContainerStyle');
        const customControls = document.getElementById('textLabelBadgeCustomControls');
        const bgPicker = document.getElementById('textLabelBgColorPicker');
        const borderPicker = document.getElementById('textLabelBorderColorPicker');

        if (deviceData) {
            // Edit Mode
            if (titleEl) titleEl.innerHTML = '<i class="fas fa-font text-cyan-400"></i> Edit Text Label';
            if (idInput) idInput.value = deviceData.id;
            if (contentInput) contentInput.value = deviceData.name || '';

            const style = MapApp.utils.getTextStyleFromDevice(deviceData);
            if (sizeInput) sizeInput.value = style.size || 16;
            textModalBoldState = !!style.bold;
            textModalItalicState = !!style.italic;
            if (alignInput) alignInput.value = style.align || 'center';
            if (fontInput) fontInput.value = style.fontFamily || 'sans';
            if (colorPicker) colorPicker.value = style.color || '#22d3ee';
            if (colorVal) colorVal.textContent = style.color || '#22d3ee';
            if (containerInput) containerInput.value = style.containerStyle || 'transparent';
            if (bgPicker) bgPicker.value = style.fillColor || '#0f172a';
            if (borderPicker) borderPicker.value = style.borderColor || '#334155';
        } else {
            // Create Mode
            if (titleEl) titleEl.innerHTML = '<i class="fas fa-font text-cyan-400"></i> Add Text Label';
            if (idInput) idInput.value = '';
            if (contentInput) contentInput.value = '';
            if (sizeInput) sizeInput.value = '16';
            textModalBoldState = false;
            textModalItalicState = false;
            if (alignInput) alignInput.value = 'center';
            if (fontInput) fontInput.value = 'sans';
            if (colorPicker) colorPicker.value = '#22d3ee';
            if (colorVal) colorVal.textContent = '#22d3ee';
            if (containerInput) containerInput.value = 'transparent';
            if (bgPicker) bgPicker.value = '#0f172a';
            if (borderPicker) borderPicker.value = '#334155';
        }

        // Update Bold / Italic toggle UI
        if (boldToggle) {
            if (textModalBoldState) {
                boldToggle.classList.add('bg-cyan-600', 'border-cyan-500', 'text-white');
                boldToggle.classList.remove('bg-slate-900', 'text-slate-300');
            } else {
                boldToggle.classList.remove('bg-cyan-600', 'border-cyan-500', 'text-white');
                boldToggle.classList.add('bg-slate-900', 'text-slate-300');
            }
        }
        if (italicToggle) {
            if (textModalItalicState) {
                italicToggle.classList.add('bg-cyan-600', 'border-cyan-500', 'text-white');
                italicToggle.classList.remove('bg-slate-900', 'text-slate-300');
            } else {
                italicToggle.classList.remove('bg-cyan-600', 'border-cyan-500', 'text-white');
                italicToggle.classList.add('bg-slate-900', 'text-slate-300');
            }
        }

        if (customControls) {
            if (containerInput && containerInput.value === 'badge') {
                customControls.classList.remove('hidden');
            } else {
                customControls.classList.add('hidden');
            }
        }

        updateTextLabelPreview();
        openModal('textModal');
        if (contentInput) contentInput.focus();
    };

    // Bold / Italic toggle buttons
    const textLabelBoldToggle = document.getElementById('textLabelBoldToggle');
    if (textLabelBoldToggle) {
        textLabelBoldToggle.addEventListener('click', () => {
            textModalBoldState = !textModalBoldState;
            if (textModalBoldState) {
                textLabelBoldToggle.classList.add('bg-cyan-600', 'border-cyan-500', 'text-white');
                textLabelBoldToggle.classList.remove('bg-slate-900', 'text-slate-300');
            } else {
                textLabelBoldToggle.classList.remove('bg-cyan-600', 'border-cyan-500', 'text-white');
                textLabelBoldToggle.classList.add('bg-slate-900', 'text-slate-300');
            }
            updateTextLabelPreview();
        });
    }
    const textLabelItalicToggle = document.getElementById('textLabelItalicToggle');
    if (textLabelItalicToggle) {
        textLabelItalicToggle.addEventListener('click', () => {
            textModalItalicState = !textModalItalicState;
            if (textModalItalicState) {
                textLabelItalicToggle.classList.add('bg-cyan-600', 'border-cyan-500', 'text-white');
                textLabelItalicToggle.classList.remove('bg-slate-900', 'text-slate-300');
            } else {
                textLabelItalicToggle.classList.remove('bg-cyan-600', 'border-cyan-500', 'text-white');
                textLabelItalicToggle.classList.add('bg-slate-900', 'text-slate-300');
            }
            updateTextLabelPreview();
        });
    }

    // Color Swatches
    document.querySelectorAll('.text-color-swatch').forEach(swatch => {
        swatch.addEventListener('click', (e) => {
            const color = e.target.dataset.color;
            const colorPicker = document.getElementById('textLabelColorPicker');
            const colorVal = document.getElementById('textLabelColorVal');
            if (colorPicker) colorPicker.value = color;
            if (colorVal) colorVal.textContent = color;
            updateTextLabelPreview();
        });
    });

    // Inputs change handlers for preview
    ['textLabelContent', 'textLabelSize', 'textLabelAlign', 'textLabelFontFamily', 'textLabelColorPicker', 'textLabelContainerStyle', 'textLabelBgColorPicker', 'textLabelBorderColorPicker'].forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', () => {
                if (id === 'textLabelColorPicker') {
                    const colorVal = document.getElementById('textLabelColorVal');
                    if (colorVal) colorVal.textContent = input.value;
                }
                if (id === 'textLabelBgColorPicker') {
                    const bgHex = document.getElementById('textLabelBgColorHex');
                    if (bgHex) bgHex.textContent = input.value;
                }
                if (id === 'textLabelBorderColorPicker') {
                    const borderHex = document.getElementById('textLabelBorderColorHex');
                    if (borderHex) borderHex.textContent = input.value;
                }
                if (id === 'textLabelContainerStyle') {
                    const customControls = document.getElementById('textLabelBadgeCustomControls');
                    if (customControls) {
                        if (input.value === 'badge') customControls.classList.remove('hidden');
                        else customControls.classList.add('hidden');
                    }
                }
                updateTextLabelPreview();
            });
        }
    });

    // Quick Text Presets apply logic
    function applyTextPreset(preset) {
        const contentInput = document.getElementById('textLabelContent');
        const sizeInput = document.getElementById('textLabelSize');
        const alignInput = document.getElementById('textLabelAlign');
        const fontInput = document.getElementById('textLabelFontFamily');
        const colorPicker = document.getElementById('textLabelColorPicker');
        const colorVal = document.getElementById('textLabelColorVal');
        const containerInput = document.getElementById('textLabelContainerStyle');
        const bgPicker = document.getElementById('textLabelBgColorPicker');
        const borderPicker = document.getElementById('textLabelBorderColorPicker');

        if (contentInput) contentInput.value = preset.text;
        if (sizeInput) sizeInput.value = preset.size;
        textModalBoldState = !!preset.bold;
        textModalItalicState = !!preset.italic;
        if (alignInput) alignInput.value = preset.align || 'center';
        if (fontInput) fontInput.value = preset.fontFamily || 'sans';
        if (colorPicker) colorPicker.value = preset.color;
        if (colorVal) colorVal.textContent = preset.color;
        if (containerInput) containerInput.value = preset.containerStyle;
        if (bgPicker) bgPicker.value = preset.fillColor || '#0f172a';
        if (borderPicker) borderPicker.value = preset.borderColor || '#334155';

        const boldToggle = document.getElementById('textLabelBoldToggle');
        const italicToggle = document.getElementById('textLabelItalicToggle');
        if (boldToggle) {
            if (textModalBoldState) {
                boldToggle.classList.add('bg-cyan-600', 'border-cyan-500', 'text-white');
                boldToggle.classList.remove('bg-slate-900', 'text-slate-300');
            } else {
                boldToggle.classList.remove('bg-cyan-600', 'border-cyan-500', 'text-white');
                boldToggle.classList.add('bg-slate-900', 'text-slate-300');
            }
        }
        if (italicToggle) {
            if (textModalItalicState) {
                italicToggle.classList.add('bg-cyan-600', 'border-cyan-500', 'text-white');
                italicToggle.classList.remove('bg-slate-900', 'text-slate-300');
            } else {
                italicToggle.classList.remove('bg-cyan-600', 'border-cyan-500', 'text-white');
                italicToggle.classList.add('bg-slate-900', 'text-slate-300');
            }
        }

        const customControls = document.getElementById('textLabelBadgeCustomControls');
        if (customControls) {
            if (preset.containerStyle === 'badge') customControls.classList.remove('hidden');
            else customControls.classList.add('hidden');
        }

        updateTextLabelPreview();
    }

    const presetMapNameBtn = document.getElementById('presetMapNameBtn');
    if (presetMapNameBtn) {
        presetMapNameBtn.addEventListener('click', () => {
            const currentMapName = document.getElementById('currentMapName')?.textContent?.trim() || 'NETWORK MAP';
            applyTextPreset({
                text: currentMapName.toUpperCase(),
                size: '32',
                bold: true,
                italic: false,
                align: 'center',
                fontFamily: 'sans',
                color: '#22d3ee',
                containerStyle: 'card',
                fillColor: '#0f172a',
                borderColor: '#06b6d4'
            });
        });
    }

    const presetZoneBtn = document.getElementById('presetZoneBtn');
    if (presetZoneBtn) {
        presetZoneBtn.addEventListener('click', () => {
            applyTextPreset({
                text: 'DATACENTER ZONE A',
                size: '24',
                bold: true,
                italic: false,
                align: 'center',
                fontFamily: 'display',
                color: '#ffffff',
                containerStyle: 'badge',
                fillColor: '#1e293b',
                borderColor: '#64748b'
            });
        });
    }

    const presetBackboneBtn = document.getElementById('presetBackboneBtn');
    if (presetBackboneBtn) {
        presetBackboneBtn.addEventListener('click', () => {
            applyTextPreset({
                text: '10G FIBER BACKBONE',
                size: '18',
                bold: true,
                italic: true,
                align: 'center',
                fontFamily: 'mono',
                color: '#22c55e',
                containerStyle: 'badge',
                fillColor: '#064e3b',
                borderColor: '#10b981'
            });
        });
    }

    const presetNoteBtn = document.getElementById('presetNoteBtn');
    if (presetNoteBtn) {
        presetNoteBtn.addEventListener('click', () => {
            applyTextPreset({
                text: 'CRITICAL INFRASTRUCTURE - 24/7 MONITORED',
                size: '14',
                bold: true,
                italic: false,
                align: 'center',
                fontFamily: 'sans',
                color: '#f59e0b',
                containerStyle: 'badge',
                fillColor: '#451a03',
                borderColor: '#f59e0b'
            });
        });
    }

    // Form submit handler
    const textForm = document.getElementById('textForm');
    if (textForm) {
        textForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const deviceId = document.getElementById('textLabelDeviceId')?.value;
            const textContent = document.getElementById('textLabelContent')?.value?.trim();
            if (!textContent) {
                if (window.notyf) window.notyf.error('Please enter text content.');
                return;
            }

            const size = parseInt(document.getElementById('textLabelSize')?.value, 10) || 16;
            const color = document.getElementById('textLabelColorPicker')?.value || '#22d3ee';
            const align = document.getElementById('textLabelAlign')?.value || 'center';
            const fontFamily = document.getElementById('textLabelFontFamily')?.value || 'sans';
            const containerStyle = document.getElementById('textLabelContainerStyle')?.value || 'transparent';
            const fillColor = document.getElementById('textLabelBgColorPicker')?.value || '#0f172a';
            const borderColor = document.getElementById('textLabelBorderColorPicker')?.value || '#334155';

            const nextStyle = {
                size,
                color,
                bold: textModalBoldState,
                italic: textModalItalicState,
                align,
                fontFamily,
                containerStyle,
                fillColor,
                borderColor
            };

            const saveBtn = document.getElementById('saveTextLabelBtn');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving...';
            }

            try {
                if (deviceId) {
                    // Update existing text label
                    const existingNode = state.nodes.get(deviceId);
                    const deviceData = existingNode?.deviceData || {};
                    const portConfigStr = MapApp.utils.withUpdatedTextStyle(deviceData, nextStyle);

                    const updated = await api.post('update_device', {
                        id: deviceId,
                        updates: {
                            name: textContent,
                            name_text_size: size,
                            name_text_color: color,
                            name_text_bold: textModalBoldState ? 1 : 0,
                            name_text_italic: textModalItalicState ? 1 : 0,
                            port_config: portConfigStr
                        }
                    });

                    const pos = state.network.getPositions([updated.id])[updated.id] || { x: existingNode?.x || 0, y: existingNode?.y || 0 };
                    const baseNode = {
                        id: updated.id,
                        label: updated.name,
                        title: MapApp.utils.buildNodeTitle(updated),
                        x: pos.x,
                        y: pos.y,
                        deviceData: updated
                    };

                    state.nodes.update(MapApp.utils.buildVisTextNode(baseNode, updated));
                    if (window.notyf) window.notyf.success(`Text label "${textContent}" updated.`);
                } else {
                    // Create new text label
                    const viewPosition = state.network.getViewPosition();
                    const canvasPosition = state.network.canvas.DOMtoCanvas(viewPosition);
                    const portConfigStr = MapApp.utils.withUpdatedTextStyle({}, nextStyle);

                    const newDevice = await api.post('create_device', {
                        name: textContent,
                        type: 'text',
                        map_id: state.currentMapId,
                        x: canvasPosition.x,
                        y: canvasPosition.y,
                        name_text_size: size,
                        name_text_color: color,
                        name_text_bold: textModalBoldState ? 1 : 0,
                        name_text_italic: textModalItalicState ? 1 : 0,
                        port_config: portConfigStr
                    });

                    const baseNode = {
                        id: newDevice.id,
                        label: newDevice.name,
                        title: newDevice.name,
                        x: newDevice.x,
                        y: newDevice.y,
                        deviceData: newDevice
                    };

                    const visNode = MapApp.utils.buildVisTextNode(baseNode, newDevice);
                    state.nodes.add(visNode);
                    if (window.notyf) window.notyf.success(`Text label "${textContent}" added to map.`);
                }
                closeModal('textModal');
            } catch (error) {
                console.error('Failed to save text label:', error);
                if (window.notyf) window.notyf.error(error.message || 'Failed to save text label.');
            } finally {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-check"></i> Save Label';
                }
            }
        });
    }

    els.cancelEdgeBtn.addEventListener('click', () => closeModal('edgeModal'));
    const deleteEdgeModalBtn = document.getElementById('deleteEdgeModalBtn');
    if (deleteEdgeModalBtn) {
        deleteEdgeModalBtn.addEventListener('click', async () => {
            const id = document.getElementById('edgeId').value;
            if (!id) return;
            if (confirm('Are you sure you want to delete this connection?')) {
                try {
                    const edge = state.edges.get(id);
                    const result = await api.post('delete_edge', { 
                        id, 
                        source_id: edge ? edge.from : null, 
                        target_id: edge ? edge.to : null 
                    });
                    if (result.success || result.status === 'success') {
                        state.edges.remove(id);
                        closeModal('edgeModal');
                        window.notyf.success('Connection deleted.');
                    } else {
                        window.notyf.error(result.error || 'Failed to delete connection.');
                    }
                } catch (err) {
                    console.error('Error deleting edge:', err);
                    window.notyf.error(err.message || 'Failed to delete connection.');
                }
            }
        });
    }

    els.scanNetworkBtn.addEventListener('click', () => openModal('scanModal'));
    els.closeScanModal.addEventListener('click', () => closeModal('scanModal'));

    // Connection Glow & Flow Settings Modal Bindings
    const connectionSettingsBtn = document.getElementById('connectionSettingsBtn');
    const closeConnectionSettingsBtn = document.getElementById('closeConnectionSettingsBtn');
    const connectionSettingsForm = document.getElementById('connectionSettingsForm');
    const csGlowMode = document.getElementById('csGlowMode');
    const csGlowRadius = document.getElementById('csGlowRadius');
    const csGlowRadiusVal = document.getElementById('csGlowRadiusVal');
    const csSpeed = document.getElementById('csSpeed');
    const csSpeedVal = document.getElementById('csSpeedVal');
    const csThickness = document.getElementById('csThickness');
    const csThicknessVal = document.getElementById('csThicknessVal');
    const csResetDefaultBtn = document.getElementById('csResetDefaultBtn');

    if (connectionSettingsBtn) {
        connectionSettingsBtn.addEventListener('click', () => {
            if (MapApp.ui && typeof MapApp.ui.openConnectionSettingsModal === 'function') {
                MapApp.ui.openConnectionSettingsModal();
            }
        });
    }

    if (closeConnectionSettingsBtn) {
        closeConnectionSettingsBtn.addEventListener('click', () => closeModal('connectionSettingsModal'));
    }

    if (csGlowRadius && csGlowRadiusVal) {
        csGlowRadius.addEventListener('input', (e) => {
            csGlowRadiusVal.textContent = e.target.value + ' px';
            if (MapApp.ui && typeof MapApp.ui._updateConnectionSettingsPreview === 'function') {
                MapApp.ui._updateConnectionSettingsPreview();
            }
        });
    }

    if (csGlowMode) {
        csGlowMode.addEventListener('change', () => {
            if (MapApp.ui && typeof MapApp.ui._updateConnectionSettingsPreview === 'function') {
                MapApp.ui._updateConnectionSettingsPreview();
            }
        });
    }

    if (csSpeed && csSpeedVal) {
        csSpeed.addEventListener('input', (e) => {
            csSpeedVal.textContent = parseFloat(e.target.value).toFixed(1) + 'x';
        });
    }

    if (csThickness && csThicknessVal) {
        csThickness.addEventListener('input', (e) => {
            csThicknessVal.textContent = e.target.value + ' px';
        });
    }

    if (csResetDefaultBtn) {
        csResetDefaultBtn.addEventListener('click', () => {
            document.getElementById('csEnableAnimation').checked = true;
            if (csGlowMode) csGlowMode.value = 'neon-laser';
            if (csGlowRadius) { csGlowRadius.value = 14; csGlowRadiusVal.textContent = '14 px'; }
            const flowStyle = document.getElementById('csFlowStyle');
            if (flowStyle) flowStyle.value = 'auto';
            if (csSpeed) { csSpeed.value = 1.0; csSpeedVal.textContent = '1.0x'; }
            if (csThickness) { csThickness.value = 2; csThicknessVal.textContent = '2 px'; }
            const bwGlow = document.getElementById('csEnableBandwidthGlow');
            if (bwGlow) bwGlow.checked = true;
            if (MapApp.ui && typeof MapApp.ui._updateConnectionSettingsPreview === 'function') {
                MapApp.ui._updateConnectionSettingsPreview();
            }
        });
    }

    if (connectionSettingsForm) {
        connectionSettingsForm.addEventListener('submit', (e) => {
            e.preventDefault();
            if (MapApp.ui && typeof MapApp.ui.saveConnectionSettings === 'function') {
                MapApp.ui.saveConnectionSettings();
            }
        });
    }

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
                activateMapSettingsPanel('device');
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

        const mapSettingsFormEl = document.getElementById('mapSettingsForm');
        if (mapSettingsFormEl) {
            mapSettingsFormEl.addEventListener('submit', async (e) => {
                e.preventDefault();
                try {
                    const offlineDelayInput = document.getElementById('offlineDelaySeconds');
                    const offlineDelay = offlineDelayInput ? parseInt(offlineDelayInput.value, 10) : 5;
                    const bgColorHexInput = document.getElementById('mapBgColorHex');
                    const bgImageUrlInput = document.getElementById('mapBgImageUrl');
                    
                    const updates = {
                        background_color: bgColorHexInput ? bgColorHexInput.value : '#1e293b',
                        background_image_url: bgImageUrlInput ? bgImageUrlInput.value : '',
                        public_view_enabled: els.publicViewToggle ? els.publicViewToggle.checked : false,
                        offline_delay_seconds: (!isNaN(offlineDelay) && offlineDelay >= 1 && offlineDelay <= 300) ? offlineDelay : 5
                    };
                    
                    saveTooltipFieldsForMap(state.currentMapId, readTooltipFieldCheckboxes());
                    saveConnectionTooltipFieldsForMap(state.currentMapId, readConnectionTooltipFieldCheckboxes());
                    saveTooltipDisplayForMap(state.currentMapId, readTooltipDisplayControls());
                    
                    await api.post('update_map', { id: state.currentMapId, updates });
                    
                    // Fast UI Update (avoiding full reload of all map edges/nodes)
                    const currentMapIndex = state.maps.findIndex(m => m.id == state.currentMapId);
                    if (currentMapIndex > -1) {
                        state.maps[currentMapIndex] = { ...state.maps[currentMapIndex], ...updates };
                    }
                    const mapEl = document.getElementById('network-map');
                    if (mapEl) {
                        mapEl.style.backgroundColor = updates.background_color || '';
                        mapEl.style.backgroundImage = updates.background_image_url ? `url(${updates.background_image_url})` : '';
                    }
                    MapApp.mapManager.updatePublicViewLink(state.currentMapId, updates.public_view_enabled);
                    MapApp.config.offlineDelayMs = (updates.offline_delay_seconds || 5) * 1000;
                    const delayBadge = document.getElementById('offlineDelayValue');
                    if (delayBadge) delayBadge.textContent = updates.offline_delay_seconds || 5;

                    if (typeof refreshNodeTooltips === 'function') refreshNodeTooltips();
                    if (typeof refreshEdgeTooltips === 'function') refreshEdgeTooltips();
                    MapApp.ui.updateStaticEdgeColors();
                    closeModal('mapSettingsModal');
                    if (window.notyf) window.notyf.success('Map settings saved.');
                } catch (error) {
                    console.error("Failed to save map settings:", error);
                    if (window.notyf) window.notyf.error(error.message || "Could not save map settings.");
                }
            });
        }
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
            if (els.liveRefreshToggle) {
                els.liveRefreshToggle.checked = true;
                els.liveRefreshToggle.disabled = true; // Disable toggle for viewers
            }
            if (els.refreshStatusBtn) els.refreshStatusBtn.disabled = true; // Disable manual refresh button for viewers when live is on
            deviceManager.performBulkRefresh(); // Initial refresh
            state.globalRefreshIntervalId = setInterval(deviceManager.performBulkRefresh, MapApp.config.REFRESH_INTERVAL_SECONDS * 1000);
        } else {
            if (els.liveRefreshToggle) {
                els.liveRefreshToggle.checked = false; // Default off for admin
                els.liveRefreshToggle.disabled = false; // Enable toggle for admin
            }
        }

        const urlParams = new URLSearchParams(window.location.search);
        const mapToLoad = urlParams.get('map_id'); // Check for map_id in URL
        
        const firstMapId = await mapManager.loadMaps();
        const initialMapId = mapToLoad || firstMapId; // Prioritize URL param
        
        if (initialMapId) {
            if (els.mapSelector) els.mapSelector.value = initialMapId;
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
            if (els.newMapBtn) els.newMapBtn.disabled = true;
            if (els.renameMapBtn) els.renameMapBtn.disabled = true;
            if (els.deleteMapBtn) els.deleteMapBtn.disabled = true;
            if (els.placeDeviceBtn) els.placeDeviceBtn.disabled = true;
            if (els.addDeviceBtn) els.addDeviceBtn.style.display = 'none'; // Hide link
            if (els.addEdgeBtn) els.addEdgeBtn.disabled = true;
            if (els.exportBtn) els.exportBtn.disabled = true;
            if (els.importBtn) els.importBtn.disabled = true;
            if (els.mapSettingsBtn) els.mapSettingsBtn.disabled = true;
            if (els.scanNetworkBtn) els.scanNetworkBtn.disabled = true;
            if (els.createFirstMapBtn) els.createFirstMapBtn.disabled = true; // If no maps exist
            
            const mapSelectionControls = document.querySelector('#map-selection .flex.gap-4');
            if (mapSelectionControls) {
                mapSelectionControls.insertAdjacentHTML('afterend', '<p class="text-red-400 text-sm mt-2">You do not have permission to manage maps or devices.</p>');
            }
        }
    })();
}
