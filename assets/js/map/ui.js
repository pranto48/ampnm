/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
window.MapApp = window.MapApp || {};

MapApp.ui = {
    // DOM Elements
    els: {},

    getEl: (id) => {
        if (!MapApp.ui.els || Object.keys(MapApp.ui.els).length === 0) {
            MapApp.ui.cacheElements();
        }
        if (MapApp.ui.els && MapApp.ui.els[id]) {
            return MapApp.ui.els[id];
        }
        const fallbackId = id === 'mapContainer' ? 'map-container' : (id === 'noMapsContainer' ? 'no-maps' : (id === 'mapWrapper' ? 'network-map-wrapper' : id));
        const domEl = document.getElementById(fallbackId);
        if (domEl && MapApp.ui.els) {
            MapApp.ui.els[id] = domEl;
        }
        return domEl;
    },

    // Cache DOM elements
    cacheElements: () => {
        MapApp.ui.els = {
            mapWrapper: document.getElementById('network-map-wrapper'),
            mapSelector: document.getElementById('mapSelector'),
            newMapBtn: document.getElementById('newMapBtn'),
            renameMapBtn: document.getElementById('renameMapBtn'),
            deleteMapBtn: document.getElementById('deleteMapBtn'),
            mapContainer: document.getElementById('map-container'),
            noMapsContainer: document.getElementById('no-maps'),
            createFirstMapBtn: document.getElementById('createFirstMapBtn'),
            currentMapName: document.getElementById('currentMapName'),
            scanNetworkBtn: document.getElementById('scanNetworkBtn'),
            refreshStatusBtn: document.getElementById('refreshStatusBtn'),
            liveRefreshToggle: document.getElementById('liveRefreshToggle'),
            addEdgeBtn: document.getElementById('addEdgeBtn'),
            fixZoomBtn: document.getElementById('fixZoomBtn'),
            resetZoomBtn: document.getElementById('resetZoomBtn'),
            fullscreenBtn: document.getElementById('fullscreenBtn'),
            exportBtn: document.getElementById('exportBtn'),
            importBtn: document.getElementById('importBtn'),
            importFile: document.getElementById('importFile'),
            edgeModal: document.getElementById('edgeModal'),
            edgeForm: document.getElementById('edgeForm'),
            cancelEdgeBtn: document.getElementById('cancelEdgeBtn'),
            scanModal: document.getElementById('scanModal'),
            closeScanModal: document.getElementById('closeScanModal'),
            scanForm: document.getElementById('scanForm'),
            scanLoader: document.getElementById('scanLoader'),
            scanResults: document.getElementById('scanResults'),
            scanInitialMessage: document.getElementById('scanInitialMessage'),
            mapSettingsBtn: document.getElementById('mapSettingsBtn'),
            mapSettingsModal: document.getElementById('mapSettingsModal'),
            mapSettingsForm: document.getElementById('mapSettingsForm'),
            cancelMapSettingsBtn: document.getElementById('cancelMapSettingsBtn'),
            resetMapBgBtn: document.getElementById('resetMapBgBtn'),
            mapBgUpload: document.getElementById('mapBgUpload'),
            placeDeviceBtn: document.getElementById('placeDeviceBtn'),
            addGroupBoxBtn: document.getElementById('addGroupBoxBtn'),
            addTextBtn: document.getElementById('addTextBtn'),
            addMapTitleBtn: document.getElementById('addMapTitleBtn'),
            presetMapNameBtn: document.getElementById('presetMapNameBtn'),
            presetZoneBtn: document.getElementById('presetZoneBtn'),
            presetBackboneBtn: document.getElementById('presetBackboneBtn'),
            presetNoteBtn: document.getElementById('presetNoteBtn'),
            textModal: document.getElementById('textModal'),
            textForm: document.getElementById('textForm'),
            placeDeviceModal: document.getElementById('placeDeviceModal'),
            closePlaceDeviceModal: document.getElementById('closePlaceDeviceModal'),
            placeDeviceList: document.getElementById('placeDeviceList'),
            placeDeviceLoader: document.getElementById('placeDeviceLoader'),
            shareMapBtn: document.getElementById('shareMapBtn'),
            // NEW Public View Elements
            publicViewToggle: document.getElementById('publicViewToggle'),
            publicViewLinkContainer: document.getElementById('publicViewLinkContainer'),
            publicViewLink: document.getElementById('publicViewLink'),
            copyPublicLinkBtn: document.getElementById('copyPublicLinkBtn'),
            openPublicLinkBtn: document.getElementById('openPublicLinkBtn'),
            metricsModal: document.getElementById('metricsModal'),
            closeMetricsModal: document.getElementById('closeMetricsModal'),
            metricsDeviceTitle: document.getElementById('metricsDeviceTitle'),
            metricsHoursRange: document.getElementById('metricsHoursRange'),
            refreshMetricsGraphBtn: document.getElementById('refreshMetricsGraphBtn'),
            metricsNoDataMessage: document.getElementById('metricsNoDataMessage'),
            metricsCpuGraph: document.getElementById('metricsCpuGraph'),
            metricsRamGraph: document.getElementById('metricsRamGraph'),
            metricsDiskGraph: document.getElementById('metricsDiskGraph'),
            metricsNetGraph: document.getElementById('metricsNetGraph'),
        };
    },

    populateLegend: () => {
        const legendContainer = document.getElementById('status-legend');
        if (!legendContainer) return;
        const statusOrder = ['online', 'warning', 'critical', 'offline', 'unknown'];
        legendContainer.innerHTML = statusOrder.map(status => {
            const color = MapApp.config.statusColorMap[status];
            const label = status.charAt(0).toUpperCase() + status.slice(1);
            return `<div class="legend-item"><div class="legend-dot" style="background-color: ${color};"></div><span>${label}</span></div>`;
        }).join('');
    },

    openDeviceModal: (deviceId) => {
        if (!deviceId) return;

        // Use the existing edit-device page so admins can change icons, names, and IPs.
        // Keep navigation simple to avoid modal dependencies that were removed from the PHP map.
        window.location.href = `edit-device.php?id=${encodeURIComponent(deviceId)}&return=map`;
    },

    _renderMiniGraph: (containerEl, series, color = '#22d3ee', secondarySeries = null, secondaryColor = '#a78bfa') => {
        if (!containerEl) return;
        const width = 380;
        const height = 120;
        const pad = 12;
        const toPoints = (values, min, max) => values.map((v, i) => {
            const x = pad + (i * ((width - pad * 2) / Math.max(1, values.length - 1)));
            const y = height - pad - (((v - min) / Math.max(0.0001, max - min)) * (height - pad * 2));
            return `${x.toFixed(1)},${y.toFixed(1)}`;
        }).join(' ');

        const valuesPrimary = series.map(v => Number(v) || 0);
        const valuesSecondary = secondarySeries ? secondarySeries.map(v => Number(v) || 0) : [];
        const allValues = valuesSecondary.length ? valuesPrimary.concat(valuesSecondary) : valuesPrimary;
        const max = Math.max(1, ...allValues);
        const min = Math.min(0, ...allValues);

        const poly1 = toPoints(valuesPrimary, min, max);
        const poly2 = valuesSecondary.length ? toPoints(valuesSecondary, min, max) : '';
        containerEl.innerHTML = `
            <svg viewBox="0 0 ${width} ${height}" class="w-full h-28">
                <rect x="0" y="0" width="${width}" height="${height}" fill="rgba(15,23,42,0.4)" />
                <polyline fill="none" stroke="${color}" stroke-width="2.5" points="${poly1}" />
                ${poly2 ? `<polyline fill="none" stroke="${secondaryColor}" stroke-width="2.5" points="${poly2}" />` : ''}
                <text x="${width - 6}" y="14" text-anchor="end" fill="#94a3b8" font-size="10">max ${max.toFixed(1)}</text>
            </svg>
        `;
    },

    openMetricsModal: async (deviceId) => {
        const node = MapApp.state.nodes.get(deviceId);
        if (!node || !node.deviceData) {
            window.notyf.error('Device data not found.');
            return;
        }
        const { els } = MapApp.ui;
        const loadMetrics = async () => {
            const hours = Number(els.metricsHoursRange.value || 24);
            let history = [];
            let result = await fetch(`api.php?action=get_metrics_history&device_id=${encodeURIComponent(deviceId)}&hours=${hours}`);
            if (result.ok) {
                history = await result.json();
            }

            if ((!Array.isArray(history) || history.length === 0) && node.deviceData?.ip) {
                result = await fetch(`api.php?action=get_metrics_history&host_ip=${encodeURIComponent(node.deviceData.ip)}&hours=${hours}`);
                if (result.ok) {
                    history = await result.json();
                }
            }

            if (!Array.isArray(history) || history.length === 0) {
                els.metricsNoDataMessage.classList.remove('hidden');
                [els.metricsCpuGraph, els.metricsRamGraph, els.metricsDiskGraph, els.metricsNetGraph].forEach(el => { if (el) el.innerHTML = ''; });
                return;
            }
            els.metricsNoDataMessage.classList.add('hidden');
            MapApp.ui._renderMiniGraph(els.metricsCpuGraph, history.map(h => h.cpu_percent), '#22c55e');
            MapApp.ui._renderMiniGraph(els.metricsRamGraph, history.map(h => h.memory_percent), '#38bdf8');
            MapApp.ui._renderMiniGraph(els.metricsDiskGraph, history.map(h => h.disk_percent), '#f59e0b');
            MapApp.ui._renderMiniGraph(els.metricsNetGraph, history.map(h => h.network_in_mbps), '#22d3ee', history.map(h => h.network_out_mbps), '#a78bfa');
        };

        els.metricsDeviceTitle.textContent = `${node.deviceData.name} (${node.deviceData.type || 'device'})`;
        await loadMetrics();
        openModal('metricsModal');

        const refreshHandler = async () => { await loadMetrics(); };
        els.refreshMetricsGraphBtn.onclick = refreshHandler;
        els.metricsHoursRange.onchange = refreshHandler;
        els.closeMetricsModal.onclick = () => closeModal('metricsModal');
    },

    openEdgeModal: (edgeId) => {
        if (window.userRole !== 'admin') {
            window.notyf.error('You do not have permission to edit connections.');
            return;
        }
        // Try both string and number ID lookups for vis.js compatibility
        let edge = MapApp.state.edges.get(edgeId);
        if (!edge && !isNaN(edgeId)) edge = MapApp.state.edges.get(Number(edgeId));
        if (!edge && typeof edgeId === 'number') edge = MapApp.state.edges.get(String(edgeId));
        console.log('openEdgeModal called with edge ID:', edgeId, 'type:', typeof edgeId);
        console.log('Retrieved edge object:', edge);
        if (!edge) {
            console.error('Error: Edge object not found for ID:', edgeId);
            window.notyf.error('Error: Connection data not found.');
            return;
        }
        document.getElementById('edgeId').value = edge.id;
        MapApp.ui.syncConnectionTypeSelectColors();
        const connTypeEl = document.getElementById('connectionType');
        connTypeEl.value = edge.connection_type || '';
        connTypeEl.dispatchEvent(new Event('change'));
        
        // Populate custom edge style fields
        const hasCustomThickness = edge.custom_thickness !== undefined && edge.custom_thickness !== null && edge.custom_thickness !== '' && !isNaN(Number(edge.custom_thickness)) && Number(edge.custom_thickness) > 0;
        document.getElementById('edgeThickness').value = hasCustomThickness ? edge.custom_thickness : '';
        const resetThicknessBtn = document.getElementById('resetEdgeThicknessBtn');
        if (resetThicknessBtn) {
            resetThicknessBtn.onclick = (e) => {
                e.preventDefault();
                document.getElementById('edgeThickness').value = '';
                if (window.notyf) window.notyf.info('Line thickness reset to global map setting.');
            };
        }
        document.getElementById('edgeLineStyle').value = edge.custom_line_style || 'auto';
        
        const typeColor = MapApp.utils.getConnectionTypeColor(edge.connection_type || 'cat6');
        const customColor = edge.custom_color || '';
        document.getElementById('edgeColorHex').value = customColor;
        document.getElementById('edgeColorPicker').value = customColor ? MapApp.utils.normalizeHexColor(customColor, typeColor) : typeColor;
        const resetColorBtn = document.getElementById('resetEdgeColorBtn');
        if (resetColorBtn) {
            resetColorBtn.onclick = (e) => {
                e.preventDefault();
                const currentType = document.getElementById('connectionType').value || 'cat6';
                document.getElementById('edgeColorHex').value = '';
                document.getElementById('edgeColorPicker').value = MapApp.utils.getConnectionTypeColor(currentType);
                if (window.notyf) window.notyf.info('Edge color reset to connection type color.');
            };
        }
        
        document.getElementById('edgeArrows').value = edge.custom_arrows || 'none';
        document.getElementById('edgeLabel').value = edge.custom_label || '';
        document.getElementById('edgeAnimated').checked = edge.custom_animated !== undefined ? (edge.custom_animated == 1) : true;

        // Use loose comparison for node lookup (MySQL IDs can be string or number)
        const allNodes = MapApp.state.nodes.get();
        const sourceNode = allNodes.find(n => n.id == edge.from);
        const targetNode = allNodes.find(n => n.id == edge.to);

        const srcNameEl = document.getElementById('edgeSourceDeviceName');
        const tgtNameEl = document.getElementById('edgeTargetDeviceName');
        srcNameEl.textContent = sourceNode ? sourceNode.deviceData.name : 'Source';
        tgtNameEl.textContent = targetNode ? targetNode.deviceData.name : 'Target';

        // Populate port dropdowns with used-port filtering
        MapApp.ui._populatePortSelectAsync('edgeSourcePort', sourceNode, edge.source_port_label || '', edge.id);
        MapApp.ui._populatePortSelectAsync('edgeTargetPort', targetNode, edge.target_port_label || '', edge.id);

        MapApp.ui._updatePortPreview();
        openModal('edgeModal');
    },

    /**
     * Populate port select with ports from port_config and disable already-used ports.
     * @param {string} selectId - DOM id of the <select>
     * @param {object} node - vis.js node with deviceData
     * @param {string} selectedValue - currently selected port label
     * @param {number|string} currentEdgeId - the edge being edited (so its ports stay selectable)
     */
    _populatePortSelectAsync: (selectId, node, selectedValue, currentEdgeId) => {
        const sel = document.getElementById(selectId);
        sel.innerHTML = '<option value="">None</option>';

        if (!node || !node.deviceData) return;

        const deviceId = node.deviceData.id;
        const deviceType = node.deviceData.type || 'server';

        // Generate ports from port_config (custom) or fallback to type defaults
        const portObjects = MapApp.ui._getPortObjectsFromConfig(node.deviceData);

        // Fetch used ports for this device, excluding current edge
        fetch(`api.php?action=get_device_used_ports&device_id=${encodeURIComponent(deviceId)}&exclude_edge_id=${encodeURIComponent(currentEdgeId || '')}`)
            .then(r => r.json())
            .then(data => {
                const usedSet = new Set((data.ports || []).map(p => p.toLowerCase()));
                portObjects.forEach(po => {
                    const opt = document.createElement('option');
                    opt.value = po.name;
                    const isUsed = usedSet.has(po.name.toLowerCase());
                    let label = po.name;
                    if (po.vlan) label += ` [VLAN ${po.vlan}]`;
                    if (isUsed) label += ' (In Use)';
                    opt.textContent = label;
                    opt.disabled = isUsed;
                    opt.style.color = isUsed ? '#f59e0b' : '';
                    if (po.name === selectedValue) { opt.selected = true; opt.disabled = false; opt.textContent = po.name + (po.vlan ? ` [VLAN ${po.vlan}]` : ''); }
                    sel.appendChild(opt);
                });
            })
            .catch(() => {
                portObjects.forEach(po => {
                    const opt = document.createElement('option');
                    opt.value = po.name;
                    opt.textContent = po.name + (po.vlan ? ` [VLAN ${po.vlan}]` : '');
                    if (po.name === selectedValue) opt.selected = true;
                    sel.appendChild(opt);
                });
            });
    },

    /**
     * Get port list from device's port_config JSON or fall back to type-based defaults
     */
    _getPortsFromConfig: (deviceData) => {
        return MapApp.ui._getPortObjectsFromConfig(deviceData).map(po => po.name);
    },

    /**
     * Get port objects (with VLAN info) from device's port_config JSON or type defaults
     */
    _getPortObjectsFromConfig: (deviceData) => {
        if (deviceData.port_config) {
            try {
                const groups = typeof deviceData.port_config === 'string' ? JSON.parse(deviceData.port_config) : deviceData.port_config;
                if (Array.isArray(groups) && groups.length > 0) {
                    const ports = [];
                    groups.forEach(g => {
                        for (let i = 0; i < (g.count || 0); i++) {
                            ports.push({ name: (g.prefix || '') + ((g.start || 0) + i), vlan: g.vlan || '' });
                        }
                    });
                    return ports;
                }
            } catch (e) { /* fall through */ }
        }
        return MapApp.ui._generatePortOptions(deviceData.type || 'server').map(p => ({ name: p, vlan: '' }));
    },

    _populatePortSelect: (selectId, node, selectedValue) => {
        const sel = document.getElementById(selectId);
        sel.innerHTML = '<option value="">None</option>';
        if (!node || !node.deviceData) return;
        const ports = MapApp.ui._getPortsFromConfig(node.deviceData);
        ports.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p;
            opt.textContent = p;
            if (p === selectedValue) opt.selected = true;
            sel.appendChild(opt);
        });
    },

    _generatePortOptions: (deviceType) => {
        const ports = [];
        const dt = (deviceType || '').toLowerCase();

        if (dt === 'switch' || dt === 'network_switch' || dt.includes('switch')) {
            for (let i = 1; i <= 24; i++) ports.push(`G0/${i}`);
            for (let i = 1; i <= 4; i++) ports.push(`SFP0${i}`);
        } else if (dt === 'router' || dt.includes('router')) {
            for (let i = 0; i <= 3; i++) ports.push(`G0/${i}`);
            for (let i = 0; i <= 1; i++) ports.push(`S0/${i}`);
            ports.push('SFP01');
        } else if (dt === 'firewall' || dt.includes('firewall') || dt.includes('security')) {
            for (let i = 0; i <= 7; i++) ports.push(`G0/${i}`);
            for (let i = 0; i <= 1; i++) ports.push(`Mgmt0/${i}`);
        } else {
            // Server / generic - 4 GigE ports
            for (let i = 0; i <= 3; i++) ports.push(`G0/${i}`);
        }
        return ports;
    },

    _updatePortPreview: () => {
        const srcPort = document.getElementById('edgeSourcePort').value;
        const tgtPort = document.getElementById('edgeTargetPort').value;
        const preview = document.getElementById('portPreview');
        const srcLabel = document.getElementById('portPreviewSource');
        const tgtLabel = document.getElementById('portPreviewTarget');

        if (srcPort || tgtPort) {
            preview.classList.remove('hidden');
            srcLabel.textContent = srcPort || '—';
            tgtLabel.textContent = tgtPort || '—';
        } else {
            preview.classList.add('hidden');
        }
    },

    updateStaticEdgeColors: () => {
        const displaySettings = MapApp.utils.getCurrentTooltipDisplaySettings();
        if (!MapApp.state.nodes || !MapApp.state.edges) return;
        const allEdges = typeof MapApp.state.edges.get === 'function' ? MapApp.state.edges.get() : [];
        const nodeCount = typeof MapApp.state.nodes.get === 'function' ? MapApp.state.nodes.get().length : (MapApp.state.nodes.length || 0);
        if (nodeCount > 0 && allEdges.length > 0) {
            const deviceStatusMap = new Map(MapApp.state.nodes.get({ fields: ['id', 'deviceData'] }).map(d => [String(d.id), d.deviceData ? d.deviceData.status : 'online']));
            const updates = [];
            allEdges.forEach(edge => {
                const sourceStatus = deviceStatusMap.get(String(edge.from));
                const targetStatus = deviceStatusMap.get(String(edge.to));
                const isOffline = sourceStatus === 'offline' || targetStatus === 'offline';
                
                // Color configuration: custom color or dynamic connection type color / status fallback
                let colorVal = edge.custom_color;
                if (!colorVal) {
                    colorVal = isOffline
                        ? MapApp.config.statusColorMap.offline
                        : MapApp.utils.getConnectionTypeColor(edge.connection_type);
                }
                const color = { color: colorVal, hover: colorVal, highlight: colorVal };
                
                // Thickness configuration
                const defaultThickness = Math.max(1, Math.min(16, parseFloat(displaySettings.connection_line_thickness) || 2));
                const hasCustomThickness = edge.custom_thickness !== null && edge.custom_thickness !== undefined && edge.custom_thickness !== '' && !isNaN(Number(edge.custom_thickness)) && Number(edge.custom_thickness) > 0;
                const width = hasCustomThickness ? Math.max(1, Math.min(16, parseFloat(edge.custom_thickness))) : defaultThickness;
                
                // Dashes style configuration
                const dashes = MapApp.utils.resolveEdgeDashPattern(edge.connection_type, edge.custom_line_style);
                
                // Arrows configuration
                let arrows = undefined;
                if (edge.custom_arrows === 'to') arrows = { to: { enabled: true } };
                else if (edge.custom_arrows === 'from') arrows = { from: { enabled: true } };
                else if (edge.custom_arrows === 'both') arrows = { to: { enabled: true }, from: { enabled: true } };

                updates.push({ id: edge.id, color, dashes, width, arrows });
            });
            if (updates.length > 0) {
                MapApp.state.edges.update(updates);
            }
            if (MapApp.state.network) {
                MapApp.state.network.redraw();
            }
        }
    },

    startCanvasAnimationLoop: () => {
        let lastFrameTime = performance.now();
        const targetFPS = 30; // 30 FPS for crisp, smooth packet animation
        const fpsInterval = 1000 / targetFPS;

        const loop = (timestamp) => {
            if (document.hidden) {
                // Pause animation loop when browser tab is inactive to save GPU/CPU
                MapApp.state.animationFrameId = null;
                return;
            }

            if (!MapApp.state.network) {
                MapApp.state.animationFrameId = requestAnimationFrame(loop);
                return;
            }

            if (!lastFrameTime) {
                lastFrameTime = timestamp;
            }

            const elapsed = timestamp - lastFrameTime;
            if (elapsed >= fpsInterval) {
                lastFrameTime = timestamp - (elapsed % fpsInterval);

                const displaySettings = (MapApp.utils && typeof MapApp.utils.getCurrentTooltipDisplaySettings === 'function')
                    ? MapApp.utils.getCurrentTooltipDisplaySettings()
                    : { connection_enable_animation: true, connection_animation_speed: 100 };
                
                const isAnim = displaySettings.connection_enable_animation !== false && 
                               displaySettings.connection_enable_animation !== 'false' && 
                               displaySettings.connection_enable_animation !== 0 && 
                               displaySettings.connection_enable_animation !== '0';

                const speedPercent = Math.min(200, Math.max(0, Number(displaySettings.connection_animation_speed) ?? 100));
                
                const timelineSlider = document.getElementById('timelineSlider');
                const isLive = timelineSlider ? parseInt(timelineSlider.value, 10) === 24 : true;
                
                const speedMultiplier = (speedPercent / 100) * (isLive ? 1 : 0);
                const effSpeed = speedMultiplier > 0 ? speedMultiplier : (isLive ? 1.0 : 0);

                if (isAnim) {
                    MapApp.state.edgeAnimProgress = ((MapApp.state.edgeAnimProgress || 0) + 0.005 * effSpeed) % 1.0;
                }

                // Redraw canvas if network exists
                if (MapApp.state.network) {
                    MapApp.state.network.redraw();
                }
            }

            MapApp.state.animationFrameId = requestAnimationFrame(loop);
        };

        if (MapApp.state.animationFrameId) {
            cancelAnimationFrame(MapApp.state.animationFrameId);
            MapApp.state.animationFrameId = null;
        }

        // Auto resume on visibility change if stopped
        if (!MapApp.state._visibilityListenerAttached) {
            MapApp.state._visibilityListenerAttached = true;
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && !MapApp.state.animationFrameId) {
                    lastFrameTime = performance.now();
                    MapApp.state.animationFrameId = requestAnimationFrame(loop);
                }
            });
        }

        MapApp.state.animationFrameId = requestAnimationFrame(loop);
    },

    updateAndAnimateEdges: () => {
        MapApp.ui.updateStaticEdgeColors();
        MapApp.ui.startCanvasAnimationLoop();
    },

    syncConnectionAnimToggleUI: (isEnabled) => {
        const btn = document.getElementById('toggleConnectionAnimBtn');
        const icon = document.getElementById('toggleConnectionAnimIcon');
        const text = document.getElementById('toggleConnectionAnimText');
        const motionChk = document.getElementById('motionEnableAnimation');
        const csChk = document.getElementById('csEnableAnimation');

        if (motionChk && motionChk.checked !== isEnabled) {
            motionChk.checked = isEnabled;
        }
        if (csChk && csChk.checked !== isEnabled) {
            csChk.checked = isEnabled;
        }

        if (btn) {
            if (isEnabled) {
                btn.className = 'px-3 py-2 bg-cyan-950/70 text-cyan-400 rounded-lg hover:bg-cyan-900/80 border border-cyan-500/60 flex items-center gap-1.5 transition-all shadow-sm shadow-cyan-500/20';
                btn.title = 'Connection Data Flow Animation is ON (কানেকশন অ্যানিমেশন চালু - বন্ধ করতে ক্লিক করুন)';
                if (icon) icon.className = 'fas fa-wave-square text-cyan-400 animate-pulse';
                if (text) text.textContent = 'Flow: ON';
            } else {
                btn.className = 'px-3 py-2 bg-slate-800 text-slate-400 rounded-lg hover:bg-slate-700 hover:text-slate-200 border border-slate-700 flex items-center gap-1.5 transition-all';
                btn.title = 'Connection Data Flow Animation is OFF (কানেকশন অ্যানিমেশন বন্ধ - চালু করতে ক্লিক করুন)';
                if (icon) icon.className = 'fas fa-pause text-slate-500';
                if (text) text.textContent = 'Flow: OFF';
            }
        }
    },

    toggleConnectionAnimation: (forceState) => {
        const currentMapId = MapApp.state?.currentMapId;
        const currentSettings = (MapApp.utils && typeof MapApp.utils.getCurrentTooltipDisplaySettings === 'function')
            ? MapApp.utils.getCurrentTooltipDisplaySettings()
            : { connection_enable_animation: true };

        const currentState = currentSettings.connection_enable_animation !== false &&
                             currentSettings.connection_enable_animation !== 'false' &&
                             currentSettings.connection_enable_animation !== 0 &&
                             currentSettings.connection_enable_animation !== '0';

        const newState = typeof forceState === 'boolean' ? forceState : !currentState;

        // Update in MapApp.state
        if (currentMapId) {
            if (!MapApp.state.tooltipDisplaySettingsByMap) {
                MapApp.state.tooltipDisplaySettingsByMap = {};
            }
            MapApp.state.tooltipDisplaySettingsByMap[currentMapId] = {
                ...currentSettings,
                connection_enable_animation: newState
            };
            try {
                localStorage.setItem(`mapTooltipDisplay:${currentMapId}`, JSON.stringify(MapApp.state.tooltipDisplaySettingsByMap[currentMapId]));
            } catch (e) {
                console.warn('Failed to save to localStorage:', e);
            }
        }

        // Also save to global ampnm_tooltip_display_settings
        try {
            const globalSettings = JSON.parse(localStorage.getItem('ampnm_tooltip_display_settings') || '{}');
            globalSettings.connection_enable_animation = newState;
            localStorage.setItem('ampnm_tooltip_display_settings', JSON.stringify(globalSettings));
        } catch (e) {}

        // Sync all UI toggle elements
        MapApp.ui.syncConnectionAnimToggleUI(newState);

        // Notify user
        if (window.notyf) {
            if (newState) {
                window.notyf.success('Connection flow animation enabled (কানেকশন ডাটা ফ্লো চালু)');
            } else {
                window.notyf.success('Connection flow animation disabled (কানেকশন ডাটা ফ্লো বন্ধ)');
            }
        }

        // Trigger redraw
        if (MapApp.state.network) {
            MapApp.state.network.redraw();
        }
    },

    openConnectionSettingsModal: () => {
        const displaySettings = (MapApp.utils && typeof MapApp.utils.getCurrentTooltipDisplaySettings === 'function') 
            ? MapApp.utils.getCurrentTooltipDisplaySettings() 
            : {};
        
        const isAnim = displaySettings.connection_enable_animation !== false && 
                       displaySettings.connection_enable_animation !== 'false' &&
                       displaySettings.connection_enable_animation !== 0 &&
                       displaySettings.connection_enable_animation !== '0';
        const animEl = document.getElementById('csEnableAnimation');
        if (animEl) animEl.checked = isAnim;

        const motionAnimEl = document.getElementById('motionEnableAnimation');
        if (motionAnimEl) motionAnimEl.checked = isAnim;
        
        const glowModeEl = document.getElementById('csGlowMode');
        if (glowModeEl) glowModeEl.value = displaySettings.connection_glow_mode || 'neon-laser';
        
        const glowRadius = parseInt(displaySettings.connection_glow_radius, 10) || 14;
        const glowRadiusEl = document.getElementById('csGlowRadius');
        const glowRadiusValEl = document.getElementById('csGlowRadiusVal');
        if (glowRadiusEl) glowRadiusEl.value = glowRadius;
        if (glowRadiusValEl) glowRadiusValEl.textContent = glowRadius + ' px';
        
        const flowStyleEl = document.getElementById('csFlowStyle');
        if (flowStyleEl) flowStyleEl.value = displaySettings.connection_run_style || 'auto';
        
        const speedVal = displaySettings.connection_animation_speed !== undefined ? (displaySettings.connection_animation_speed / 100) : 1.0;
        const speedEl = document.getElementById('csSpeed');
        const speedValEl = document.getElementById('csSpeedVal');
        if (speedEl) speedEl.value = speedVal.toFixed(1);
        if (speedValEl) speedValEl.textContent = speedVal.toFixed(1) + 'x';
        
        const thickVal = parseInt(displaySettings.connection_line_thickness, 10) || 2;
        const thickEl = document.getElementById('csThickness');
        const thickValEl = document.getElementById('csThicknessVal');
        if (thickEl) thickEl.value = thickVal;
        if (thickValEl) thickValEl.textContent = thickVal + ' px';
        
        const bwGlowEl = document.getElementById('csEnableBandwidthGlow');
        if (bwGlowEl) bwGlowEl.checked = displaySettings.connection_enable_bandwidth_glow !== false;
        
        MapApp.ui._updateConnectionSettingsPreview();
        openModal('connectionSettingsModal');
    },

    _updateConnectionSettingsPreview: () => {
        const mode = document.getElementById('csGlowMode')?.value || 'neon-laser';
        const radius = parseInt(document.getElementById('csGlowRadius')?.value, 10) || 14;
        const line = document.getElementById('csPreviewLine');
        const dot = document.getElementById('csPreviewDot');
        
        if (!line || !dot) return;
        
        if (mode === 'off') {
            line.style.boxShadow = 'none';
            dot.style.boxShadow = 'none';
        } else if (mode === 'high-bloom') {
            line.style.boxShadow = `0 0 ${radius * 1.5}px #00f2fe, 0 0 ${radius * 3}px #00f2fe`;
            dot.style.boxShadow = `0 0 ${radius * 2}px #00f2fe`;
        } else if (mode === 'cyber-pulse') {
            line.style.boxShadow = `0 0 ${radius}px #00f2fe, 0 0 ${radius * 2}px #00ff87`;
            dot.style.boxShadow = `0 0 ${radius * 1.5}px #00ff87`;
        } else if (mode === 'subtle-flow') {
            line.style.boxShadow = `0 0 ${Math.max(4, radius * 0.6)}px #00f2fe`;
            dot.style.boxShadow = `0 0 6px #00f2fe`;
        } else {
            line.style.boxShadow = `0 0 ${radius}px #00f2fe, 0 0 ${radius * 2}px #00f2fe`;
            dot.style.boxShadow = `0 0 ${radius}px #00f2fe`;
        }
    },

    saveConnectionSettings: async () => {
        const isAnim = document.getElementById('csEnableAnimation') ? document.getElementById('csEnableAnimation').checked : true;
        const glowMode = document.getElementById('csGlowMode')?.value || 'neon-laser';
        const glowRadius = parseInt(document.getElementById('csGlowRadius')?.value, 10) || 14;
        const flowStyle = document.getElementById('csFlowStyle')?.value || 'auto';
        const speed = parseFloat(document.getElementById('csSpeed')?.value) || 1.0;
        const thickness = Math.max(1, Math.min(16, parseFloat(document.getElementById('csThickness')?.value) || 2));
        const bandwidthGlow = document.getElementById('csEnableBandwidthGlow') ? document.getElementById('csEnableBandwidthGlow').checked : true;
        
        const currentMapId = MapApp.state?.currentMapId;
        const currentSettings = (MapApp.utils && typeof MapApp.utils.getCurrentTooltipDisplaySettings === 'function')
            ? MapApp.utils.getCurrentTooltipDisplaySettings()
            : {};

        const updatedSettings = {
            ...currentSettings,
            connection_enable_animation: isAnim,
            connection_glow_mode: glowMode,
            connection_glow_radius: glowRadius,
            connection_run_style: flowStyle,
            connection_animation_speed: Math.round(speed * 100),
            connection_line_thickness: thickness,
            connection_enable_bandwidth_glow: bandwidthGlow
        };

        const userStorageId = window.currentLoggedInUserId || window.currentLoggedInUsername || 'guest';
        if (currentMapId) {
            if (!MapApp.state.tooltipDisplaySettingsByMap) {
                MapApp.state.tooltipDisplaySettingsByMap = {};
            }
            MapApp.state.tooltipDisplaySettingsByMap[currentMapId] = updatedSettings;
            try {
                localStorage.setItem(`ampnm_tooltip_display:${userStorageId}:${currentMapId}`, JSON.stringify(updatedSettings));
                localStorage.setItem(`mapTooltipDisplay:${currentMapId}`, JSON.stringify(updatedSettings));
            } catch (e) {}

            try {
                if (MapApp.api && typeof MapApp.api.post === 'function') {
                    await MapApp.api.post('save_user_map_settings', {
                        map_id: currentMapId,
                        settings: {
                            tooltip_display: updatedSettings
                        }
                    });
                }
            } catch (dbErr) {
                console.warn('Could not sync connection settings to DB:', dbErr);
            }
        }

        try {
            const stored = JSON.parse(localStorage.getItem('ampnm_tooltip_display_settings') || '{}');
            localStorage.setItem('ampnm_tooltip_display_settings', JSON.stringify({ ...stored, ...updatedSettings }));
        } catch (e) {}

        // Sync inputs in map settings modal
        const gThicknessInput = document.getElementById('globalLineThickness');
        const gThicknessPx = document.getElementById('globalLineThicknessPx');
        const gThicknessVal = document.getElementById('globalLineThicknessValue');
        if (gThicknessInput) gThicknessInput.value = thickness;
        if (gThicknessPx) gThicknessPx.value = thickness;
        if (gThicknessVal) gThicknessVal.textContent = `${thickness}px`;

        MapApp.ui.syncConnectionAnimToggleUI(isAnim);
        closeModal('connectionSettingsModal');
        
        if (window.notyf) window.notyf.success('Connection settings saved!');
        MapApp.ui.updateStaticEdgeColors();
        if (MapApp.state.network) MapApp.state.network.redraw();
    },

    renderConnectionLegend: () => {
        const listEl = document.getElementById('connectionLegendList');
        if (!listEl) return;
        const defs = MapApp.config?.connectionTypeDefinitions || [];
        const activeColors = MapApp.utils.getConnectionTypeColorsForMap(MapApp.state?.currentMapId);

        listEl.innerHTML = defs.map((def) => {
            const color = activeColors[def.key] || def.defaultColor;
            const labelText = def.shortLabel || def.label;
            const lineSvg = MapApp.utils.buildLinePreviewSvg(color, def.defaultDashed, 32, 6, `data-legend-swatch="${def.key}"`);
            return `
                <div class="flex items-center justify-between gap-3 px-1.5 py-1 rounded hover:bg-slate-700/60 cursor-pointer transition group" data-conn-type-row="${def.key}" data-no-drag="true" title="Click to customize ${def.label} color">
                    <div class="flex items-center gap-2 pointer-events-none">
                        ${lineSvg}
                        <span class="text-slate-300 group-hover:text-white transition-colors">${def.emoji} ${labelText}</span>
                    </div>
                    <span class="w-2.5 h-2.5 rounded-full border border-white/30 opacity-80 group-hover:opacity-100 pointer-events-none" style="background-color: ${color};"></span>
                </div>
            `;
        }).join('');

        listEl.querySelectorAll('[data-conn-type-row]').forEach((row) => {
            row.addEventListener('click', (e) => {
                e.stopPropagation();
                const typeKey = row.getAttribute('data-conn-type-row');
                MapApp.ui.openConnectionTypeColorsModal(typeKey);
            });
        });
    },

    syncConnectionTypeSelectColors: () => {
        const selectEl = document.getElementById('connectionType');
        if (!selectEl) return;
        const activeColors = MapApp.utils.getConnectionTypeColorsForMap(MapApp.state?.currentMapId);
        Array.from(selectEl.options).forEach((opt) => {
            if (opt.value && activeColors[opt.value]) {
                opt.setAttribute('data-color', activeColors[opt.value]);
            }
        });
        if (selectEl.value) {
            selectEl.dispatchEvent(new Event('change'));
        }
    },

    _applyLiveConnectionColorsPreview: (colorMap) => {
        const currentMapId = MapApp.state?.currentMapId;
        if (currentMapId) {
            MapApp.state.connectionTypeColorsByMap = MapApp.state.connectionTypeColorsByMap || {};
            MapApp.state.connectionTypeColorsByMap[currentMapId] = MapApp.utils.sanitizeConnectionTypeColors(colorMap);
        }
        MapApp.ui.renderConnectionLegend();
        MapApp.ui.syncConnectionTypeSelectColors();
        MapApp.ui.updateStaticEdgeColors();
        if (MapApp.state.network) MapApp.state.network.redraw();
    },

    _readConnectionTypeColorInputs: () => {
        const defs = MapApp.config?.connectionTypeDefinitions || [];
        const colors = {};
        defs.forEach((def) => {
            const hexInput = document.getElementById(`ctc-hex-${def.key}`);
            const pickerInput = document.getElementById(`ctc-picker-${def.key}`);
            const rawVal = (hexInput && hexInput.value) || (pickerInput && pickerInput.value) || def.defaultColor;
            colors[def.key] = MapApp.utils.normalizeHexColor(rawVal, def.defaultColor);
        });
        return colors;
    },

    _updateCtcPreviewElement: (previewEl, def, hex) => {
        if (!previewEl) return;
        previewEl.innerHTML = MapApp.utils.buildLinePreviewSvg(hex, def.defaultDashed, 80, 6);
    },

    _renderConnectionTypeColorRows: (colors, focusTypeKey = null) => {
        const container = document.getElementById('connectionTypeColorRows');
        if (!container) return;
        const defs = MapApp.config?.connectionTypeDefinitions || [];

        container.innerHTML = defs.map((def) => {
            const hex = MapApp.utils.normalizeHexColor(colors[def.key], def.defaultColor);
            const isFocused = focusTypeKey && focusTypeKey === def.key;
            const ringCls = isFocused ? 'border-cyan-500/80 ring-1 ring-cyan-500/50 bg-slate-900/90' : 'border-slate-700/80 bg-slate-900/60';
            const previewSvg = MapApp.utils.buildLinePreviewSvg(hex, def.defaultDashed, 80, 6);

            return `
                <div id="ctc-row-${def.key}" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-lg border ${ringCls} transition-all">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="text-base leading-none">${def.emoji}</span>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-white truncate">${def.label}</div>
                            <div class="flex items-center gap-2 mt-1">
                                <div id="ctc-preview-${def.key}" class="flex items-center">${previewSvg}</div>
                                <span class="text-[10px] text-slate-400 uppercase font-mono">${def.defaultDashed ? 'Dashed' : 'Solid'}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <input type="color" id="ctc-picker-${def.key}" value="${hex}" class="h-9 w-11 bg-slate-950 border border-slate-600 rounded-md p-0.5 cursor-pointer" title="Pick ${def.label} color">
                        <input type="text" id="ctc-hex-${def.key}" value="${hex}" maxlength="7" placeholder="${def.defaultColor}" class="w-24 bg-slate-950 border border-slate-600 rounded-md px-2.5 py-1.5 text-xs font-mono text-white uppercase focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                        <button type="button" class="ctc-single-reset-btn px-2 py-1.5 rounded-md bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white border border-slate-700 text-xs transition" data-reset-key="${def.key}" title="Reset ${def.label} to default (${def.defaultColor})">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');

        defs.forEach((def) => {
            const picker = document.getElementById(`ctc-picker-${def.key}`);
            const hexInput = document.getElementById(`ctc-hex-${def.key}`);
            const preview = document.getElementById(`ctc-preview-${def.key}`);
            const applyRowChange = (nextHex) => {
                MapApp.ui._updateCtcPreviewElement(preview, def, nextHex);
                MapApp.ui._applyLiveConnectionColorsPreview(MapApp.ui._readConnectionTypeColorInputs());
            };

            if (picker && hexInput) {
                picker.addEventListener('input', (e) => {
                    const val = MapApp.utils.normalizeHexColor(e.target.value, def.defaultColor);
                    hexInput.value = val;
                    applyRowChange(val);
                });
                hexInput.addEventListener('input', (e) => {
                    const raw = e.target.value.trim();
                    if (/^#[0-9a-fA-F]{6}$/.test(raw)) {
                        const normalized = raw.toLowerCase();
                        picker.value = normalized;
                        applyRowChange(normalized);
                    }
                });
                hexInput.addEventListener('blur', (e) => {
                    const normalized = MapApp.utils.normalizeHexColor(e.target.value, def.defaultColor);
                    hexInput.value = normalized;
                    picker.value = normalized;
                    applyRowChange(normalized);
                });
            }
        });

        container.querySelectorAll('.ctc-single-reset-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const key = btn.getAttribute('data-reset-key');
                const def = defs.find(d => d.key === key);
                if (!def) return;
                const picker = document.getElementById(`ctc-picker-${key}`);
                const hexInput = document.getElementById(`ctc-hex-${key}`);
                const preview = document.getElementById(`ctc-preview-${key}`);
                if (picker) picker.value = def.defaultColor;
                if (hexInput) hexInput.value = def.defaultColor;
                MapApp.ui._updateCtcPreviewElement(preview, def, def.defaultColor);
                MapApp.ui._applyLiveConnectionColorsPreview(MapApp.ui._readConnectionTypeColorInputs());
            });
        });
    },

    openConnectionTypeColorsModal: (focusTypeKey = null) => {
        const currentColors = MapApp.utils.getConnectionTypeColorsForMap(MapApp.state?.currentMapId);
        MapApp.state._connColorSnapshot = { ...currentColors };

        MapApp.ui._renderConnectionTypeColorRows(currentColors, focusTypeKey);
        const globalChk = document.getElementById('ctcApplyGlobal');
        if (globalChk) {
            globalChk.checked = false;
        }
        openModal('connectionTypeColorsModal');

        if (focusTypeKey) {
            const focusedRow = document.getElementById(`ctc-row-${focusTypeKey}`);
            if (focusedRow && typeof focusedRow.scrollIntoView === 'function') {
                focusedRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    },

    cancelConnectionTypeColorsModal: () => {
        if (MapApp.state._connColorSnapshot) {
            MapApp.ui._applyLiveConnectionColorsPreview(MapApp.state._connColorSnapshot);
            MapApp.state._connColorSnapshot = null;
        }
        closeModal('connectionTypeColorsModal');
    },

    applyConnectionColorPreset: (presetKey) => {
        const presets = MapApp.config?.connectionColorPresets || {};
        const preset = presets[presetKey];
        if (!preset || !preset.colors) return;
        const sanitized = MapApp.utils.sanitizeConnectionTypeColors(preset.colors);
        MapApp.ui._renderConnectionTypeColorRows(sanitized, null);
        MapApp.ui._applyLiveConnectionColorsPreview(sanitized);
    },

    resetAllConnectionTypeColors: () => {
        const defaults = MapApp.utils.getDefaultConnectionTypeColors();
        MapApp.ui._renderConnectionTypeColorRows(defaults, null);
        MapApp.ui._applyLiveConnectionColorsPreview(defaults);
    },

    saveConnectionTypeColors: async () => {
        const colors = MapApp.ui._readConnectionTypeColorInputs();
        const currentMapId = MapApp.state?.currentMapId;
        const userStorageId = window.currentLoggedInUserId || window.currentLoggedInUsername || 'guest';
        const applyGlobal = document.getElementById('ctcApplyGlobal')?.checked === true;

        MapApp.state._connColorSnapshot = null;

        if (currentMapId) {
            MapApp.state.connectionTypeColorsByMap = MapApp.state.connectionTypeColorsByMap || {};
            MapApp.state.connectionTypeColorsByMap[currentMapId] = colors;
            try {
                localStorage.setItem(`ampnm_conn_type_colors:${userStorageId}:${currentMapId}`, JSON.stringify(colors));
                localStorage.setItem(`mapConnectionTypeColors:${currentMapId}`, JSON.stringify(colors));
            } catch (e) {}

            try {
                if (MapApp.api && typeof MapApp.api.post === 'function') {
                    await MapApp.api.post('save_user_map_settings', {
                        map_id: currentMapId,
                        settings: {
                            connection_type_colors: colors
                        }
                    });
                }
            } catch (err) {
                console.warn('Could not sync user connection colors to DB:', err);
            }

            if (window.userRole === 'admin') {
                const currentMap = Array.isArray(MapApp.state.maps)
                    ? MapApp.state.maps.find(m => m.id == currentMapId)
                    : null;
                const existingMapSettings = currentMap && currentMap.settings
                    ? (typeof currentMap.settings === 'string' ? (() => { try { return JSON.parse(currentMap.settings); } catch (e) { return {}; } })() : { ...currentMap.settings })
                    : {};
                const nextMapSettings = {
                    ...existingMapSettings,
                    connection_type_colors: colors
                };
                if (currentMap) {
                    currentMap.settings = nextMapSettings;
                }
                try {
                    await MapApp.api.post('update_map', {
                        id: currentMapId,
                        updates: { settings: nextMapSettings }
                    });
                } catch (err) {
                    console.warn('Could not save map default connection colors to DB:', err);
                }
            }
        }

        if (applyGlobal && window.userRole === 'admin') {
            MapApp.state.globalConnectionTypeColors = colors;
            try {
                localStorage.setItem('ampnm_global_connection_type_colors', JSON.stringify(colors));
            } catch (e) {}
            try {
                await MapApp.api.post('save_global_connection_type_colors', { colors });
            } catch (err) {
                console.warn('Could not save global connection colors to DB:', err);
            }
        }

        // Refresh edge tooltips with new connection color swatch
        if (MapApp.state.edges && MapApp.state.nodes) {
            const edgeUpdates = [];
            MapApp.state.edges.forEach((edge) => {
                const fromNode = MapApp.state.nodes.get(edge.from);
                const toNode = MapApp.state.nodes.get(edge.to);
                edgeUpdates.push({
                    id: edge.id,
                    title: MapApp.utils.buildEdgeTitle(edge, fromNode?.deviceData || null, toNode?.deviceData || null)
                });
            });
            if (edgeUpdates.length > 0) MapApp.state.edges.update(edgeUpdates);
        }

        MapApp.ui.renderConnectionLegend();
        MapApp.ui.syncConnectionTypeSelectColors();
        MapApp.ui.updateStaticEdgeColors();
        if (MapApp.state.network) MapApp.state.network.redraw();

        closeModal('connectionTypeColorsModal');
        if (window.notyf) {
            window.notyf.success(applyGlobal
                ? 'Connection type colors saved for this map & all maps!'
                : 'Connection type colors saved!');
        }
    }
};
