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
        document.getElementById('connectionType').value = edge.connection_type || '';
        
        // Populate custom edge style fields
        document.getElementById('edgeThickness').value = edge.custom_thickness !== undefined ? edge.custom_thickness : 2;
        document.getElementById('edgeLineStyle').value = edge.custom_line_style || 'solid';
        
        const customColor = edge.custom_color || '';
        document.getElementById('edgeColorHex').value = customColor;
        document.getElementById('edgeColorPicker').value = customColor ? customColor : '#00f2fe';
        
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
        const allEdges = MapApp.state.edges.get();
        if (MapApp.state.nodes.length > 0 && allEdges.length > 0) {
            const deviceStatusMap = new Map(MapApp.state.nodes.get({ fields: ['id', 'deviceData'] }).map(d => [String(d.id), d.deviceData.status]));
            const updates = [];
            allEdges.forEach(edge => {
                const sourceStatus = deviceStatusMap.get(String(edge.from));
                const targetStatus = deviceStatusMap.get(String(edge.to));
                const isOffline = sourceStatus === 'offline' || targetStatus === 'offline';
                
                // Color configuration: custom color or status fallback
                let colorVal = edge.custom_color;
                if (!colorVal) {
                    colorVal = isOffline ? MapApp.config.statusColorMap.offline : (MapApp.config.edgeColorMap[edge.connection_type] || MapApp.config.edgeColorMap.cat6);
                }
                const color = { color: colorVal, hover: colorVal, highlight: colorVal };
                
                // Thickness configuration
                const defaultThickness = parseInt(displaySettings.connection_line_thickness) || 2;
                const width = parseInt(edge.custom_thickness) || defaultThickness;
                
                // Dashes style configuration
                let dashes = false;
                if (edge.custom_line_style === 'dashed') {
                    dashes = [6, 4];
                } else if (edge.custom_line_style === 'dotted') {
                    dashes = [2, 3];
                } else if (edge.custom_line_style === 'solid') {
                    dashes = false;
                } else if (edge.connection_type === 'wifi' || edge.connection_type === 'radio' || edge.connection_type === 'logical-tunneling') {
                    dashes = [5, 5];
                }
                
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
        }
    },

    startCanvasAnimationLoop: () => {
        let lastFrameTime = 0;
        const targetFPS = 25;
        const fpsInterval = 1000 / targetFPS; // ~40ms per frame

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

            const elapsed = timestamp - (lastFrameTime || timestamp);
            if (elapsed >= fpsInterval) {
                lastFrameTime = timestamp - (elapsed % fpsInterval);

                const displaySettings = MapApp.utils.getCurrentTooltipDisplaySettings();
                const speedPercent = Math.min(200, Math.max(0, Number(displaySettings.connection_animation_speed) ?? 100));
                
                const timelineSlider = document.getElementById('timelineSlider');
                const isLive = timelineSlider ? parseInt(timelineSlider.value, 10) === 24 : true;
                
                const speedMultiplier = (speedPercent / 100) * (isLive ? 1 : 0);

                // Check if any nodes use animated SVG icons
                const hasAnimatedNodes = MapApp.state.nodes ? MapApp.state.nodes.get().some(n =>
                    n.originalImage && typeof n.originalImage === 'string' && n.originalImage.includes('animated-')
                ) : false;

                // Check if any edges exist on map to animate connections
                const hasAnimatedEdges = speedMultiplier > 0 && MapApp.state.edges ? MapApp.state.edges.length > 0 : false;

                if (speedMultiplier > 0) {
                    MapApp.state.edgeAnimProgress = (MapApp.state.edgeAnimProgress + 0.003 * speedMultiplier) % 1.0;
                }

                // Redraw canvas if active animated elements or edges exist
                if (hasAnimatedEdges || hasAnimatedNodes) {
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
    }
};
