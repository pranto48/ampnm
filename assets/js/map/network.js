/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
window.MapApp = window.MapApp || {};

let edgeAnimFrameId = null;
let globalProgress = 0.0;
let globalSpeedMultiplier = 1.0; // Dynamic speed bound to slider

function startEdgeAnimation() {
    if (MapApp.ui && typeof MapApp.ui.startCanvasAnimationLoop === 'function') {
        MapApp.ui.startCanvasAnimationLoop();
    }
}

function stopEdgeAnimation() {
    if (MapApp.state && MapApp.state.animationFrameId) {
        cancelAnimationFrame(MapApp.state.animationFrameId);
        MapApp.state.animationFrameId = null;
    }
}

MapApp.network = {
    getUserStorageId: () => (window.currentLoggedInUserId || window.currentLoggedInUsername || 'guest'),
    getViewStorageKey: () => `ampnm_map_view:${MapApp.network.getUserStorageId()}:${MapApp.state.currentMapId}`,
    getNodePosStorageKey: () => `ampnm_map_node_positions:${MapApp.network.getUserStorageId()}:${MapApp.state.currentMapId}`,

    saveCurrentView: () => {
        if (!MapApp.state.network || !MapApp.state.currentMapId) return;
        const scale = MapApp.state.network.getScale();
        const position = MapApp.state.network.getViewPosition();
        localStorage.setItem(MapApp.network.getViewStorageKey(), JSON.stringify({ scale, position }));
    },

    saveNodePositionForUser: (nodeId, position) => {
        if (!MapApp.state.currentMapId || !nodeId || !position) return;
        let snapshot = {};
        try {
            snapshot = JSON.parse(localStorage.getItem(MapApp.network.getNodePosStorageKey()) || '{}') || {};
        } catch (error) {
            snapshot = {};
        }
        snapshot[String(nodeId)] = { x: position.x, y: position.y };
        localStorage.setItem(MapApp.network.getNodePosStorageKey(), JSON.stringify(snapshot));
    },

    restoreSavedView: () => {
        if (!MapApp.state.network || !MapApp.state.currentMapId) return;
        try {
            const raw = localStorage.getItem(MapApp.network.getViewStorageKey());
            if (!raw) return;
            const saved = JSON.parse(raw);
            if (!saved?.position || !saved?.scale) return;
            MapApp.state.network.moveTo({
                position: saved.position,
                scale: saved.scale,
                animation: false
            });
        } catch (error) {
            // Ignore malformed preference data
        }
    },

    initializeMap: () => {
        const container = document.getElementById('network-map');
        if (!container) return;

        if (typeof vis === 'undefined' || typeof vis.Network === 'undefined') {
            console.error('Vis.js library is not loaded.');
            if (window.notyf) window.notyf.error('Network map library (Vis.js) failed to load.');
            return;
        }

        const contextMenu = document.getElementById('context-menu');

        MapApp.ui.populateLegend();
        const data = { nodes: MapApp.state.nodes, edges: MapApp.state.edges };
        const options = {
            physics: false,
            interaction: { hover: true },
            edges: { smooth: true, width: 2, font: { color: '#ffffff', size: 12, align: 'top', strokeWidth: 0 } },
            manipulation: {
                enabled: window.userRole === 'admin', // Enable manipulation only for admin
                addEdge: async (edgeData, callback) => {
                    if (window.userRole !== 'admin') {
                        callback(null);
                        return;
                    }
                    try {
                        const newEdge = await MapApp.api.post('create_edge', { source_id: edgeData.from, target_id: edgeData.to, map_id: MapApp.state.currentMapId, connection_type: 'cat6' });
                        edgeData.id = newEdge.id;
                        edgeData.connection_type = 'cat6';
                        edgeData.label = 'cat6';
                        callback(edgeData);
                        MapApp.ui.updateStaticEdgeColors();
                        window.notyf.success('Connection added.');
                    } catch (err) {
                        window.notyf.error(err.message || 'Failed to create connection.');
                        callback(null);
                    }
                },
                deleteEdge: async (edgeData, callback) => {
                    if (window.userRole !== 'admin') {
                        callback(null);
                        return;
                    }
                    if (edgeData && edgeData.edges && edgeData.edges.length > 0) {
                        try {
                            for (const edgeId of edgeData.edges) {
                                const edge = MapApp.state.edges.get(edgeId);
                                await MapApp.api.post('delete_edge', {
                                    id: edgeId,
                                    source_id: edge ? edge.from : null,
                                    target_id: edge ? edge.to : null
                                });
                            }
                            window.notyf.success('Connection deleted.');
                            callback(edgeData);
                        } catch (err) {
                            window.notyf.error(err.message || 'Failed to delete connection.');
                            callback(null);
                        }
                    } else {
                        callback(edgeData);
                    }
                }
            }
        };
        MapApp.state.network = new vis.Network(container, data, options);
        MapApp.network.restoreSavedView();

        // Auto-start unified animation loop when network is initialized
        if (MapApp.ui && typeof MapApp.ui.startCanvasAnimationLoop === 'function') {
            MapApp.ui.startCanvasAnimationLoop();
        }

        MapApp.state.network.on("afterDrawing", (ctx) => {
            if (!MapApp.state.network) return;

            // Synchronize DOM Overlay for animated SVGs
            let overlayContainer = document.getElementById('animated-svg-overlay-container');
            if (!overlayContainer) {
                overlayContainer = document.createElement('div');
                overlayContainer.id = 'animated-svg-overlay-container';
                overlayContainer.style.cssText = 'position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none; overflow:hidden; z-index:5;';
                const wrapper = document.getElementById('network-map-wrapper');
                if (wrapper) {
                    wrapper.appendChild(overlayContainer);
                }
            }

            const nodes = MapApp.state.nodes.get();
            const activeIds = new Set();
            const scale = MapApp.state.network.getScale();

            if (overlayContainer) {
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

                    if (imgEl.getAttribute('data-src') !== node.originalImage) {
                        imgEl.src = node.originalImage;
                        imgEl.setAttribute('data-src', node.originalImage);
                    }

                    const nodePos = MapApp.state.network.getPositions([node.id])[node.id];
                    if (nodePos) {
                        const domPos = MapApp.state.network.canvasToDOM(nodePos);
                        const baseSize = (node.deviceData?.icon_size || 50);
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
            }

            // Get all edges from both vis.DataSet and vis.Network body
            const datasetEdges = (MapApp.state && MapApp.state.edges && typeof MapApp.state.edges.get === 'function') ? MapApp.state.edges.get() : [];
            const bodyEdges = MapApp.state.network.body?.edges || {};

            // Build a unified list of edges to render
            const edgesToRender = [];
            const processedEdgeIds = new Set();

            // First add edges from body
            for (const edgeId in bodyEdges) {
                const bEdge = bodyEdges[edgeId];
                if (bEdge) {
                    edgesToRender.push({ id: edgeId, bodyEdge: bEdge, rawEdge: (MapApp.state.edges && typeof MapApp.state.edges.get === 'function') ? MapApp.state.edges.get(edgeId) : null });
                    processedEdgeIds.add(String(edgeId));
                }
            }

            // Also add any dataset edges that might not be in bodyEdges yet
            datasetEdges.forEach(dEdge => {
                if (dEdge && dEdge.id && !processedEdgeIds.has(String(dEdge.id))) {
                    edgesToRender.push({ id: String(dEdge.id), bodyEdge: null, rawEdge: dEdge });
                    processedEdgeIds.add(String(dEdge.id));
                }
            });

            // Build a lookup map of node statuses
            const deviceStatuses = {};
            if (MapApp.state.nodes && typeof MapApp.state.nodes.forEach === 'function') {
                MapApp.state.nodes.forEach(node => {
                    if (node && node.id) {
                        deviceStatuses[String(node.id)] = node.deviceData ? node.deviceData.status : 'online';
                    }
                });
            }

            // Load dynamic connection line & glow style preferences
            const displaySettings = (MapApp.utils && typeof MapApp.utils.getCurrentTooltipDisplaySettings === 'function')
                ? MapApp.utils.getCurrentTooltipDisplaySettings()
                : { connection_enable_animation: true, connection_glow_mode: 'neon-laser', connection_glow_radius: 14, connection_run_style: 'auto', connection_animation_speed: 100, connection_enable_bandwidth_glow: true };

            const isAnimEnabled = displaySettings.connection_enable_animation !== false && 
                                  displaySettings.connection_enable_animation !== 'false' && 
                                  displaySettings.connection_enable_animation !== 0 && 
                                  displaySettings.connection_enable_animation !== '0';
            const glowMode = displaySettings.connection_glow_mode || 'neon-laser';
            const baseGlowRadius = parseInt(displaySettings.connection_glow_radius, 10) || 14;
            const runStyle = displaySettings.connection_run_style || 'auto';
            const animSpeed = displaySettings.connection_animation_speed !== undefined ? (displaySettings.connection_animation_speed / 100) : 1.0;
            globalSpeedMultiplier = animSpeed;

            // Sync globalProgress with MapApp.state.edgeAnimProgress on every frame
            if (MapApp.state && typeof MapApp.state.edgeAnimProgress === 'number') {
                globalProgress = MapApp.state.edgeAnimProgress;
            } else if (isAnimEnabled) {
                globalProgress = (globalProgress + 0.005 * globalSpeedMultiplier) % 1.0;
            }

            ctx.save();

            function getPointAlongEdge(edge, t, fx, fy, tx, ty) {
                try {
                    if (edge && edge.edgeType && typeof edge.edgeType.getPoint === 'function') {
                        const pt = edge.edgeType.getPoint(t);
                        if (pt && typeof pt.x === 'number' && !isNaN(pt.x) && typeof pt.y === 'number' && !isNaN(pt.y)) {
                            return pt;
                        }
                    }
                    if (edge && typeof edge.getPoint === 'function') {
                        const pt = edge.getPoint(t);
                        if (pt && typeof pt.x === 'number' && !isNaN(pt.x) && typeof pt.y === 'number' && !isNaN(pt.y)) {
                            return pt;
                        }
                    }
                } catch (e) { }

                // Fallback: direct linear interpolation between from and to nodes
                if (typeof fx === 'number' && typeof fy === 'number' && typeof tx === 'number' && typeof ty === 'number') {
                    return {
                        x: fx + (tx - fx) * t,
                        y: fy + (ty - fy) * t
                    };
                }
                return null;
            }

            for (const item of edgesToRender) {
                const bodyEdge = item.bodyEdge;
                const rawEdge = item.rawEdge;

                // Ensure invisible or hidden edges are ignored
                if (bodyEdge?.options?.hidden === true || bodyEdge?.hidden === true || rawEdge?.hidden === true) continue;
                if (bodyEdge?.from?.options?.hidden === true || bodyEdge?.to?.options?.hidden === true) continue;

                const fromId = rawEdge?.from ?? bodyEdge?.from?.id ?? bodyEdge?.from;
                const toId = rawEdge?.to ?? bodyEdge?.to?.id ?? bodyEdge?.to;
                if (fromId === undefined || toId === undefined || fromId === null || toId === null || fromId === '' || toId === '') continue;

                // Robust coordinate extraction
                let fx = (typeof bodyEdge?.from === 'object' && typeof bodyEdge?.from?.x === 'number') ? bodyEdge.from.x : undefined;
                let fy = (typeof bodyEdge?.from === 'object' && typeof bodyEdge?.from?.y === 'number') ? bodyEdge.from.y : undefined;
                let tx = (typeof bodyEdge?.to === 'object' && typeof bodyEdge?.to?.x === 'number') ? bodyEdge.to.x : undefined;
                let ty = (typeof bodyEdge?.to === 'object' && typeof bodyEdge?.to?.y === 'number') ? bodyEdge.to.y : undefined;

                if (typeof fx !== 'number' || typeof fy !== 'number') {
                    const pos = MapApp.state.network.getPositions([fromId, Number(fromId), String(fromId)]) || {};
                    const p = pos[fromId] || pos[Number(fromId)] || pos[String(fromId)];
                    if (p && typeof p.x === 'number' && typeof p.y === 'number') { fx = p.x; fy = p.y; }
                }
                if (typeof tx !== 'number' || typeof ty !== 'number') {
                    const pos = MapApp.state.network.getPositions([toId, Number(toId), String(toId)]) || {};
                    const p = pos[toId] || pos[Number(toId)] || pos[String(toId)];
                    if (p && typeof p.x === 'number' && typeof p.y === 'number') { tx = p.x; ty = p.y; }
                }
                if (typeof fx !== 'number' || typeof fy !== 'number') {
                    const rawNode = MapApp.state.nodes ? (MapApp.state.nodes.get(fromId) || MapApp.state.nodes.get(Number(fromId)) || MapApp.state.nodes.get(String(fromId))) : null;
                    if (rawNode && typeof rawNode.x === 'number' && typeof rawNode.y === 'number') { fx = rawNode.x; fy = rawNode.y; }
                }
                if (typeof tx !== 'number' || typeof ty !== 'number') {
                    const rawNode = MapApp.state.nodes ? (MapApp.state.nodes.get(toId) || MapApp.state.nodes.get(Number(toId)) || MapApp.state.nodes.get(String(toId))) : null;
                    if (rawNode && typeof rawNode.x === 'number' && typeof rawNode.y === 'number') { tx = rawNode.x; ty = rawNode.y; }
                }

                if (typeof fx !== 'number' || typeof fy !== 'number' || typeof tx !== 'number' || typeof ty !== 'number') {
                    continue;
                }

                const sourceStatus = deviceStatuses[String(fromId)] || 'online';
                const targetStatus = deviceStatuses[String(toId)] || 'online';
                const isOffline = (sourceStatus === 'offline' || targetStatus === 'offline');

                // Check if animation is disabled for this specific edge
                const isEdgeAnimated = rawEdge && rawEdge.custom_animated !== undefined 
                    ? (rawEdge.custom_animated == 1 || rawEdge.custom_animated === true || rawEdge.custom_animated === '1') 
                    : (rawEdge?.animated !== undefined ? (rawEdge.animated == 1 || rawEdge.animated === true || rawEdge.animated === '1') : true);

                // Edge Color resolution
                let edgeColor = (typeof bodyEdge?.options?.color === 'string' ? bodyEdge.options.color : bodyEdge?.options?.color?.color) || rawEdge?.custom_color || rawEdge?.color || '#00F2FE';
                if (isOffline) {
                    edgeColor = '#ef4444'; // Slower / diagnostic red alert pulse on offline links
                } else if (displaySettings.connection_enable_bandwidth_glow !== false) {
                    const util = rawEdge && rawEdge.utilization_percent !== undefined ? parseFloat(rawEdge.utilization_percent) : 0;
                    if (util >= 80) {
                        edgeColor = '#ef4444'; // Red - Overload Alert
                    } else if (util >= 50) {
                        edgeColor = '#f59e0b'; // Amber - Moderate Load
                    } else if (util > 0) {
                        edgeColor = '#22c55e'; // Green - Healthy Link
                    }
                }

                // 🌟 LAYER 1: NEON LASER LINE GLOW OVERLAY
                if (glowMode !== 'off') {
                    let glowBlur = baseGlowRadius;
                    if (glowMode === 'cyber-pulse') {
                        glowBlur = baseGlowRadius * (0.6 + 0.4 * Math.sin(globalProgress * Math.PI * 2));
                    } else if (glowMode === 'high-bloom') {
                        glowBlur = baseGlowRadius * 1.5;
                    } else if (glowMode === 'subtle-flow') {
                        glowBlur = Math.max(4, baseGlowRadius * 0.6);
                    }

                    ctx.beginPath();
                    ctx.moveTo(fx, fy);
                    ctx.lineTo(tx, ty);
                    ctx.strokeStyle = edgeColor;
                    ctx.shadowColor = edgeColor;
                    ctx.shadowBlur = glowBlur;
                    ctx.lineWidth = Math.max(1, (bodyEdge?.options?.width || rawEdge?.width || 2) * 0.8);
                    ctx.stroke();
                }

                // 🌟 LAYER 2: ANIMATED CYBER PACKETS / PULSES
                if (!isAnimEnabled || !isEdgeAnimated) continue;

                const effectiveStyle = runStyle === 'auto' ? 'data-flow' : runStyle;

                if (effectiveStyle === 'data-flow') {
                    // Draw flowing quantum cyber packets
                    for (let i = 0; i < 4; i++) {
                        const t = (globalProgress + i / 4) % 1.0;
                        const pt = getPointAlongEdge(bodyEdge, t, fx, fy, tx, ty);
                        if (pt) {
                            // Outer Neon Glow
                            ctx.beginPath();
                            ctx.arc(pt.x, pt.y, 4.5, 0, 2 * Math.PI);
                            ctx.fillStyle = edgeColor;
                            ctx.shadowColor = edgeColor;
                            ctx.shadowBlur = 14;
                            ctx.fill();

                            // Bright Core Center
                            ctx.beginPath();
                            ctx.arc(pt.x, pt.y, 2.0, 0, 2 * Math.PI);
                            ctx.fillStyle = '#FFFFFF';
                            ctx.shadowColor = '#FFFFFF';
                            ctx.shadowBlur = 4;
                            ctx.fill();
                        }
                    }
                } else if (effectiveStyle === 'data-stream') {
                    // High-density stream
                    for (let i = 0; i < 10; i++) {
                        const t = (globalProgress + i / 10) % 1.0;
                        const pt = getPointAlongEdge(bodyEdge, t, fx, fy, tx, ty);
                        if (pt) {
                            ctx.beginPath();
                            ctx.arc(pt.x, pt.y, 3, 0, 2 * Math.PI);
                            ctx.fillStyle = '#00FF87';
                            ctx.shadowColor = edgeColor;
                            ctx.shadowBlur = 8;
                            ctx.fill();
                        }
                    }
                } else if (effectiveStyle === 'pulse') {
                    // Single sweep pulse orb
                    const pt = getPointAlongEdge(bodyEdge, globalProgress, fx, fy, tx, ty);
                    if (pt) {
                        ctx.beginPath();
                        ctx.arc(pt.x, pt.y, 7, 0, 2 * Math.PI);
                        ctx.fillStyle = edgeColor;
                        ctx.shadowColor = edgeColor;
                        ctx.shadowBlur = 20;
                        ctx.fill();

                        ctx.beginPath();
                        ctx.arc(pt.x, pt.y, 3.5, 0, 2 * Math.PI);
                        ctx.fillStyle = '#FFFFFF';
                        ctx.fill();
                    }
                } else if (effectiveStyle === 'wave') {
                    // Sinusoidal wave flow
                    for (let i = 0; i < 6; i++) {
                        const t = (globalProgress + i / 6) % 1.0;
                        const pt = getPointAlongEdge(bodyEdge, t, fx, fy, tx, ty);
                        if (pt) {
                            const angle = Math.atan2(ty - fy, tx - fx) + Math.PI / 2;
                            const waveOffset = Math.sin(t * Math.PI * 4) * 6;
                            const wx = pt.x + Math.cos(angle) * waveOffset;
                            const wy = pt.y + Math.sin(angle) * waveOffset;

                            ctx.beginPath();
                            ctx.arc(wx, wy, 4, 0, 2 * Math.PI);
                            ctx.fillStyle = edgeColor;
                            ctx.shadowColor = edgeColor;
                            ctx.shadowBlur = 10;
                            ctx.fill();
                        }
                    }
                } else if (effectiveStyle === 'morse') {
                    // Morse code telemetry
                    for (let i = 0; i < 5; i++) {
                        const t = (globalProgress + i / 5) % 1.0;
                        const pt = getPointAlongEdge(bodyEdge, t, fx, fy, tx, ty);
                        if (pt) {
                            ctx.fillStyle = edgeColor;
                            ctx.shadowColor = edgeColor;
                            ctx.shadowBlur = 10;
                            if (i % 2 === 0) {
                                ctx.fillRect(pt.x - 6, pt.y - 2, 12, 4);
                            } else {
                                ctx.beginPath();
                                ctx.arc(pt.x, pt.y, 3.5, 0, 2 * Math.PI);
                                ctx.fill();
                            }
                        }
                    }
                } else if (effectiveStyle === 'zipper') {
                    // Zipper interlocking style
                    for (let i = 0; i < 8; i++) {
                        const t = (globalProgress + i / 8) % 1.0;
                        const pt = getPointAlongEdge(bodyEdge, t, fx, fy, tx, ty);
                        if (pt) {
                            const angle = Math.atan2(ty - fy, tx - fx) + Math.PI / 2;
                            const offsetSign = i % 2 === 0 ? 1 : -1;
                            const zx = pt.x + Math.cos(angle) * offsetSign * 3.5;
                            const zy = pt.y + Math.sin(angle) * offsetSign * 3.5;

                            ctx.beginPath();
                            ctx.arc(zx, zy, 3.5, 0, 2 * Math.PI);
                            ctx.fillStyle = edgeColor;
                            ctx.shadowColor = edgeColor;
                            ctx.shadowBlur = 8;
                            ctx.fill();
                        }
                    }
                } else {
                    // Standard 3-dot animation flow
                    for (let i = 0; i < 3; i++) {
                        const offset = i / 3;
                        const t = (globalProgress + offset) % 1.0;
                        const pt = getPointAlongEdge(bodyEdge, t, fx, fy, tx, ty);
                        if (pt) {
                            ctx.beginPath();
                            ctx.arc(pt.x, pt.y, 5, 0, 2 * Math.PI);
                            ctx.fillStyle = edgeColor;
                            ctx.shadowColor = edgeColor;
                            ctx.shadowBlur = 12;
                            ctx.fill();
                        }
                    }
                }

                // 🌟 LAYER 3: LIVE SNMP BANDWIDTH FLOW BADGE ON EDGE
                const midX = (fx + tx) / 2;
                const midY = (fy + ty) / 2;
                const bwSpeed = rawEdge?.bandwidth_speed_mbps || rawEdge?.bandwidth_speed;
                const speedText = bwSpeed ? (parseFloat(bwSpeed) >= 1000 ? (parseFloat(bwSpeed)/1000).toFixed(1) + ' Gbps' : parseFloat(bwSpeed).toFixed(1) + ' Mbps') : null;

                if (speedText) {
                    ctx.save();
                    ctx.font = 'bold 9px "Inter", monospace';
                    const textMetrics = ctx.measureText(speedText);
                    const badgeW = textMetrics.width + 12;
                    const badgeH = 16;

                    // Futuristic pill badge
                    ctx.beginPath();
                    if (ctx.roundRect) {
                        ctx.roundRect(midX - badgeW / 2, midY - badgeH / 2, badgeW, badgeH, 8);
                    } else {
                        ctx.rect(midX - badgeW / 2, midY - badgeH / 2, badgeW, badgeH);
                    }
                    ctx.fillStyle = 'rgba(15, 23, 42, 0.9)';
                    ctx.strokeStyle = edgeColor;
                    ctx.lineWidth = 1;
                    ctx.shadowColor = edgeColor;
                    ctx.shadowBlur = 8;
                    ctx.fill();
                    ctx.stroke();

                    // Text
                    ctx.fillStyle = '#38bdf8';
                    ctx.shadowBlur = 0;
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(speedText, midX, midY);
                    ctx.restore();
                }
            }

            // =========================================================================
            // DEVICE OFFLINE RED ALERT & WARNING YELLOW BLINKING / PULSING SYSTEM
            // =========================================================================
            const now = Date.now();
            const pulseT1 = (now % 1400) / 1400; // 0 to 1 smooth expansion loop
            const pulseT2 = ((now + 700) % 1400) / 1400; // Phase shifted 50%
            const flashAlpha = 0.35 + 0.65 * Math.abs(Math.sin((now % 800) / 800 * Math.PI)); // Fast emergency blink

            const allMapNodes = (MapApp.state && MapApp.state.nodes && typeof MapApp.state.nodes.get === 'function')
                ? MapApp.state.nodes.get()
                : [];

            for (const node of allMapNodes) {
                if (!node || !node.id) continue;
                if (node.deviceData && node.deviceData.type === 'box') continue; // Skip container boxes

                const status = (node.deviceData && node.deviceData.status) ? String(node.deviceData.status).toLowerCase() : 'online';
                if (status !== 'offline' && status !== 'warning' && status !== 'critical') continue;

                // Obtain coordinate of node
                let nodePos = MapApp.state.network.getPositions([node.id])?.[node.id];
                if (!nodePos && typeof node.x === 'number' && typeof node.y === 'number') {
                    nodePos = { x: node.x, y: node.y };
                }
                if (!nodePos || typeof nodePos.x !== 'number' || typeof nodePos.y !== 'number') continue;

                const baseSize = (node.deviceData?.icon_size || node.size || 50);
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

                // 2. Radar Expanding Shockwave 2 (Phase Shifted)
                ctx.beginPath();
                ctx.arc(nodePos.x, nodePos.y, baseRadius + pulseT2 * 34, 0, 2 * Math.PI);
                ctx.strokeStyle = `rgba(${rgb}, ${(1 - pulseT2) * 0.75})`;
                ctx.lineWidth = Math.max(1, 2 * (1 - pulseT2 * 0.5));
                ctx.stroke();

                // 3. Radial Pulsing Core Halo (Glow directly behind/around the icon)
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

                // 5. Emergency Warning Beacon Badge (Top-Right of device icon)
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

                // Exclamation / Warning Icon inside badge
                ctx.fillStyle = isOffline ? '#ffffff' : '#000000';
                ctx.font = 'bold 11px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.shadowBlur = 0;
                ctx.fillText(isOffline ? '!' : '▲', badgeX, badgeY + (isOffline ? 0 : -0.5));

                ctx.restore();
            }

            ctx.restore();
        });

        // Event Handlers
        let boxResizeState = null;
        MapApp.state.network.on("dragStart", (params) => {
            if (!params.nodes || params.nodes.length !== 1) return;
            const nodeId = params.nodes[0];
            const node = MapApp.state.nodes.get(nodeId);
            if (!node?.deviceData || node.deviceData.type !== 'box') return;
            const srcEvent = params?.event?.srcEvent;
            if (!srcEvent || !srcEvent.altKey) return;
            const pointer = params?.pointer?.DOM || { x: 0, y: 0 };
            boxResizeState = {
                id: nodeId,
                startCanvas: MapApp.state.network.canvas.DOMtoCanvas(pointer),
                originalStyle: MapApp.utils.getBoxStyleFromDevice(node.deviceData),
                originalPos: MapApp.state.network.getPositions([nodeId])[nodeId] || { x: node.x || 0, y: node.y || 0 }
            };
            window.notyf.info('ALT + drag to resize group box');
        });

        MapApp.state.network.on("dragging", (params) => {
            MapApp.network.saveCurrentView();
            if (!boxResizeState || !params.nodes || !params.nodes.includes(boxResizeState.id)) return;
            const node = MapApp.state.nodes.get(boxResizeState.id);
            if (!node?.deviceData) return;
            const pointer = params?.pointer?.DOM || { x: 0, y: 0 };
            const currentCanvas = MapApp.state.network.canvas.DOMtoCanvas(pointer);
            const dx = currentCanvas.x - boxResizeState.startCanvas.x;
            const dy = currentCanvas.y - boxResizeState.startCanvas.y;
            const resizedStyle = {
                ...boxResizeState.originalStyle,
                width: Math.max(120, Math.round(boxResizeState.originalStyle.width + dx)),
                height: Math.max(70, Math.round(boxResizeState.originalStyle.height + dy))
            };
            const updatedDeviceData = {
                ...node.deviceData,
                port_config: MapApp.utils.withUpdatedBoxStyle(node.deviceData, resizedStyle)
            };
            const visNode = MapApp.utils.buildVisBoxNode({
                id: node.id,
                label: updatedDeviceData.name || node.label,
                title: MapApp.utils.buildNodeTitle(updatedDeviceData),
                x: boxResizeState.originalPos.x,
                y: boxResizeState.originalPos.y,
                font: {
                    color: updatedDeviceData.name_text_color || 'white', size: parseInt(updatedDeviceData.name_text_size, 10) || 14, multi: true,
                    face: (updatedDeviceData.name_text_bold == 1 && updatedDeviceData.name_text_italic == 1) ? 'bold italic Arial' : updatedDeviceData.name_text_bold == 1 ? 'bold Arial' : updatedDeviceData.name_text_italic == 1 ? 'italic Arial' : 'Arial'
                },
                deviceData: updatedDeviceData
            }, updatedDeviceData);
            MapApp.state.nodes.update(visNode);
        });

        MapApp.state.network.on("dragEnd", async (params) => {
            if (params.nodes.length > 0) {
                const nodeId = params.nodes[0];
                const node = MapApp.state.nodes.get(nodeId);
                const position = MapApp.state.network.getPositions([nodeId])[nodeId];

                if (boxResizeState && nodeId == boxResizeState.id) {
                    try {
                        const updated = await MapApp.api.post('update_device', {
                            id: nodeId,
                            updates: { port_config: node?.deviceData?.port_config || null }
                        });
                        MapApp.state.nodes.update({ id: nodeId, deviceData: updated, title: MapApp.utils.buildNodeTitle(updated), x: boxResizeState.originalPos.x, y: boxResizeState.originalPos.y });
                        window.notyf.success('Group box size updated.');
                    } catch (error) {
                        window.notyf.error(error.message || 'Failed to save group box size.');
                    } finally {
                        boxResizeState = null;
                    }
                } else {
                    MapApp.network.saveNodePositionForUser(nodeId, position);
                    if (window.userRole === 'admin') {
                        await MapApp.api.post('update_device', { id: nodeId, updates: { x: position.x, y: position.y } });
                    }
                }
            }
            MapApp.network.saveCurrentView();
        });
        MapApp.state.network.on("zoom", MapApp.network.saveCurrentView);
        MapApp.state.network.on("doubleClick", (params) => {
            if (params.nodes.length > 0) {
                const node = MapApp.state.nodes.get(params.nodes[0]);
                if (node && node.deviceData) {
                    if (node.deviceData.target_map_id) {
                        MapApp.mapManager.switchMap(node.deviceData.target_map_id);
                        return;
                    }
                    if (node.deviceData.is_rack == 1 || node.deviceData.type === 'rack') {
                        MapApp.mapManager.openRackVisualizer(node.deviceData);
                        return;
                    }
                    if (node.deviceData.type === 'text') {
                        if (typeof MapApp.openTextModal === 'function') MapApp.openTextModal(node.deviceData);
                        return;
                    }
                }
                if (window.userRole === 'admin') MapApp.ui.openDeviceModal(params.nodes[0]);
            } else if (params.edges && params.edges.length > 0) {
                if (window.userRole === 'admin') {
                    MapApp.ui.openEdgeModal(params.edges[0]);
                }
            }
        });

        const closeContextMenu = () => { contextMenu.style.display = 'none'; };
        MapApp.state.network.on("oncontext", (params) => {
            params.event.preventDefault();
            const nodeId = MapApp.state.network.getNodeAt(params.pointer.DOM);
            const edgeId = MapApp.state.network.getEdgeAt(params.pointer.DOM);

            if (nodeId) {
                const node = MapApp.state.nodes.get(nodeId);
                let menuItems = ``;
                if (node && node.deviceData) {
                    if (node.deviceData.target_map_id) {
                        menuItems += `<div class="context-menu-item text-cyan-400 font-bold" data-action="open-submap" data-id="${nodeId}"><i class="fas fa-sitemap fa-fw mr-2"></i>Open Sub-Map</div>`;
                    }
                    if (node.deviceData.is_rack == 1 || node.deviceData.type === 'rack') {
                        menuItems += `<div class="context-menu-item text-cyan-400 font-bold" data-action="open-rack" data-id="${nodeId}"><i class="fas fa-server fa-fw mr-2"></i>Open 19" Rack Visualizer</div>`;
                    }
                }
                if (window.userRole === 'admin') {
                    menuItems += `
                        <div class="context-menu-item" data-action="edit" data-id="${nodeId}"><i class="fas fa-edit fa-fw mr-2"></i>Edit</div>
                        ${node.deviceData.type === 'text' ? `<div class="context-menu-item" data-action="text-settings" data-id="${nodeId}"><i class="fas fa-font fa-fw mr-2"></i>Edit Text Label</div>` : ''}
                        ${node.deviceData.type === 'box' ? `<div class="context-menu-item" data-action="box-settings" data-id="${nodeId}"><i class="fas fa-vector-square fa-fw mr-2"></i>Box Settings</div>` : ''}
                        ${node.deviceData.type !== 'box' && node.deviceData.type !== 'text' ? `<div class="context-menu-item" data-action="view-metrics" data-id="${nodeId}"><i class="fas fa-chart-line fa-fw mr-2"></i>Metrics Graph</div>` : ''}
                        <div class="context-menu-item" data-action="change-icon" data-id="${nodeId}"><i class="fas fa-icons fa-fw mr-2"></i>Change Icon</div>
                        <div class="context-menu-item" data-action="copy" data-id="${nodeId}"><i class="fas fa-copy fa-fw mr-2"></i>Copy</div>
                        ${node.deviceData.ip ? `<div class="context-menu-item" data-action="ping" data-id="${nodeId}"><i class="fas fa-sync fa-fw mr-2"></i>Check Status</div>` : ''}
                        <div class="context-menu-item" data-action="delete" data-id="${nodeId}" style="color: #ef4444;"><i class="fas fa-trash-alt fa-fw mr-2"></i>Delete</div>
                    `;
                } else {
                    menuItems += `<div class="context-menu-item" data-action="view-details" data-id="${nodeId}"><i class="fas fa-info-circle fa-fw mr-2"></i>View Details</div>`;
                    if (node.deviceData.type !== 'box') menuItems += `<div class="context-menu-item" data-action="view-metrics" data-id="${nodeId}"><i class="fas fa-chart-line fa-fw mr-2"></i>Metrics Graph</div>`;
                    if (node.deviceData.ip) {
                        menuItems += `<div class="context-menu-item" data-action="ping" data-id="${nodeId}"><i class="fas fa-sync fa-fw mr-2"></i>Check Status</div>`;
                    }
                }
                contextMenu.innerHTML = menuItems;
                contextMenu.style.left = `${params.pointer.DOM.x}px`;
                contextMenu.style.top = `${params.pointer.DOM.y}px`;
                contextMenu.style.display = 'block';
                document.addEventListener('click', closeContextMenu, { once: true });
            } else if (edgeId) {
                console.log("Context menu opened for edge. Edge ID:", edgeId);
                let menuItems = ``;
                if (window.userRole === 'admin') {
                    menuItems += `
                        <div class="context-menu-item" data-action="edit-edge" data-id="${edgeId}"><i class="fas fa-edit fa-fw mr-2"></i>Edit Connection</div>
                        <div class="context-menu-item" data-action="delete-edge" data-id="${edgeId}" style="color: #ef4444;"><i class="fas fa-trash-alt fa-fw mr-2"></i>Delete Connection</div>
                    `;
                } else {
                    menuItems += `<div class="context-menu-item text-slate-500">No actions available</div>`;
                }
                contextMenu.innerHTML = menuItems;
                contextMenu.style.left = `${params.pointer.DOM.x}px`;
                contextMenu.style.top = `${params.pointer.DOM.y}px`;
                contextMenu.style.display = 'block';
                document.addEventListener('click', closeContextMenu, { once: true });
            } else {
                closeContextMenu();
            }
        });
        contextMenu.addEventListener('click', async (e) => {
            const target = e.target.closest('.context-menu-item');
            if (target) {
                const { action, id } = target.dataset;
                // Parse ID as number to match vis.js integer node IDs from MySQL AUTO_INCREMENT
                if (action === 'open-submap') {
                    const node = MapApp.state.nodes.get(id);
                    if (node && node.deviceData && node.deviceData.target_map_id) {
                        MapApp.mapManager.switchMap(node.deviceData.target_map_id);
                    }
                    return;
                }
                if (action === 'open-rack') {
                    const node = MapApp.state.nodes.get(id);
                    if (node && node.deviceData) {
                        MapApp.mapManager.openRackVisualizer(node.deviceData);
                    }
                    return;
                }

                if (window.userRole === 'admin') {
                    if (action === 'edit') {
                        const node = MapApp.state.nodes.get(id);
                        if (node && node.deviceData && node.deviceData.type === 'text') {
                            if (typeof MapApp.openTextModal === 'function') MapApp.openTextModal(node.deviceData);
                        } else {
                            MapApp.ui.openDeviceModal(id);
                        }
                    } else if (action === 'text-settings') {
                        const node = MapApp.state.nodes.get(id);
                        if (node && node.deviceData && typeof MapApp.openTextModal === 'function') {
                            MapApp.openTextModal(node.deviceData);
                        }
                    } else if (action === 'view-metrics') {
                        await MapApp.ui.openMetricsModal(id);
                    } else if (action === 'change-icon') {
                        // Inline icon/type change without leaving the map
                        const node = MapApp.state.nodes.get(id);
                        if (!node || !node.deviceData) {
                            window.notyf.error('Device not found.');
                            return;
                        }

                        const device = node.deviceData;
                        const currentType = device.type || 'server';
                        const currentSub = parseInt(device.subchoice, 10) || 0;
                        const currentIconSize = Math.max(20, Math.min(180, parseInt(device.icon_size, 10) || 50));

                        // Lightweight modal built on the fly (keeps compatibility with non-React map)
                        const modalId = 'changeIconModal';
                        const existing = document.getElementById(modalId);
                        if (existing) existing.remove();

                        const overlay = document.createElement('div');
                        overlay.id = modalId;
                        overlay.style.position = 'fixed';
                        overlay.style.inset = '0';
                        overlay.style.background = 'rgba(0,0,0,0.55)';
                        overlay.style.zIndex = '9999';
                        overlay.style.display = 'flex';
                        overlay.style.alignItems = 'center';
                        overlay.style.justifyContent = 'center';
                        overlay.innerHTML = `
                            <div style="width: min(620px, 94vw); background: rgba(15, 23, 42, 0.98); border: 1px solid rgba(51, 65, 85, 0.9); border-radius: 12px; padding: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.4); max-height: 90vh; overflow:auto;">
                                <div style="display:flex; align-items:center; justify-content:space-between; gap: 12px;">
                                    <div style="font-weight: 700; color: #fff;">Change Icon</div>
                                    <button type="button" data-ci-close style="background: transparent; border: 0; color: rgba(226,232,240,0.7); font-size: 22px; cursor: pointer;">×</button>
                                </div>
                                <div style="margin-top: 12px; display:grid; grid-template-columns: 1fr; gap: 12px;">
                                    <div>
                                        <label style="display:block; font-size: 12px; color: rgba(148,163,184,1); margin-bottom: 6px;">Device Type</label>
                                        <select data-ci-type style="width: 100%; background: rgba(2,6,23,0.8); border: 1px solid rgba(71,85,105,1); color: #fff; padding: 10px 12px; border-radius: 10px;"></select>
                                    </div>
                                    <div>
                                        <label style="display:block; font-size: 12px; color: rgba(148,163,184,1); margin-bottom: 6px;">Variant</label>
                                        <select data-ci-variant style="width: 100%; background: rgba(2,6,23,0.8); border: 1px solid rgba(71,85,105,1); color: #fff; padding: 10px 12px; border-radius: 10px;"></select>
                                    </div>
                                    <div>
                                        <label style="display:block; font-size: 12px; color: rgba(148,163,184,1); margin-bottom: 6px;">Icon Size (20-180px)</label>
                                        <input data-ci-size type="number" min="20" max="180" step="1" value="${currentIconSize}" style="width: 100%; background: rgba(2,6,23,0.8); border: 1px solid rgba(71,85,105,1); color: #fff; padding: 10px 12px; border-radius: 10px;">
                                    </div>
                                    <div style="padding: 10px 12px; border-radius: 10px; background: rgba(2,6,23,0.55); border: 1px solid rgba(51,65,85,0.7);">
                                        <label style="display:block; font-size: 12px; color: rgba(148,163,184,1); margin-bottom: 6px;">Upload Custom PNG/JPG/SVG/WebP (optional)</label>
                                        <input data-ci-upload type="file" accept=".png,.jpg,.jpeg,.gif,.svg,.webp,image/*" style="width: 100%; font-size: 12px; color: #e2e8f0;">
                                        <div data-ci-upload-note style="margin-top: 6px; font-size: 12px; color: #94a3b8;">Upload replaces current icon with the custom image.</div>
                                        ${device.icon_url ? `<div style="margin-top: 8px; font-size: 11px; color: #67e8f9;">Current custom icon: ${String(device.icon_url)}</div>` : ''}
                                    </div>
                                    <div data-ci-preview style="display:flex; align-items:center; gap: 10px; padding: 10px 12px; border-radius: 10px; background: rgba(2,6,23,0.55); border: 1px solid rgba(51,65,85,0.7);">
                                        <i data-ci-preview-icon class="fas fa-circle" style="color: rgba(226,232,240,0.9);"></i>
                                        <div style="display:flex; flex-direction:column; line-height: 1.1;">
                                            <div data-ci-preview-title style="color:#fff; font-weight: 600; font-size: 13px;"></div>
                                            <div data-ci-preview-sub style="color: rgba(148,163,184,1); font-size: 12px;"></div>
                                        </div>
                                    </div>
                                </div>
                                <div style="display:flex; justify-content:flex-end; gap: 10px; margin-top: 14px;">
                                    <button type="button" data-ci-close style="background: rgba(51,65,85,0.9); border: 1px solid rgba(71,85,105,1); color: rgba(226,232,240,1); padding: 10px 12px; border-radius: 10px; cursor:pointer;">Cancel</button>
                                    <button type="button" data-ci-save style="background: rgba(8,145,178,1); border: 1px solid rgba(6,182,212,0.5); color: #fff; padding: 10px 12px; border-radius: 10px; cursor:pointer; font-weight: 700;">Save</button>
                                </div>
                            </div>
                        `;

                        const close = () => overlay.remove();
                        overlay.addEventListener('click', (evt) => {
                            if (evt.target === overlay) close();
                            if (evt.target && evt.target.closest && evt.target.closest('[data-ci-close]')) close();
                        });
                        document.body.appendChild(overlay);

                        const typeSelect = overlay.querySelector('[data-ci-type]');
                        const variantSelect = overlay.querySelector('[data-ci-variant]');
                        const previewIcon = overlay.querySelector('[data-ci-preview-icon]');
                        const previewTitle = overlay.querySelector('[data-ci-preview-title]');
                        const previewSub = overlay.querySelector('[data-ci-preview-sub]');
                        const sizeInput = overlay.querySelector('[data-ci-size]');
                        const uploadInput = overlay.querySelector('[data-ci-upload]');
                        const saveBtn = overlay.querySelector('[data-ci-save]');

                        const lib = window.deviceIconsLibrary || {};

                        const setPreview = (t, s) => {
                            const typeData = lib[t] || { label: t, icons: [] };
                            const icons = typeData.icons || [];
                            const idx = parseInt(s, 10) || 0;
                            const variant = icons[idx] || icons[0] || { icon: 'fa-circle', label: 'Default' };

                            previewIcon.className = `fas ${variant.icon}`;
                            previewTitle.textContent = `${typeData.label || t}`;
                            previewSub.textContent = variant.label ? `Variant: ${variant.label}` : `Variant #${idx}`;
                        };

                        const populateVariants = (t, selectedIdx) => {
                            const typeData = lib[t] || { icons: [] };
                            const icons = typeData.icons || [];
                            variantSelect.innerHTML = '';
                            icons.forEach((v, idx) => {
                                const opt = document.createElement('option');
                                opt.value = String(idx);
                                opt.textContent = v.label || `Variant ${idx + 1}`;
                                if (idx === selectedIdx) opt.selected = true;
                                variantSelect.appendChild(opt);
                            });

                            // Fallback if no variants exist
                            if (icons.length === 0) {
                                const opt = document.createElement('option');
                                opt.value = '0';
                                opt.textContent = 'Default';
                                variantSelect.appendChild(opt);
                            }
                        };

                        // Populate types
                        typeSelect.innerHTML = '';
                        Object.keys(lib).forEach((key) => {
                            const opt = document.createElement('option');
                            opt.value = key;
                            opt.textContent = (lib[key] && lib[key].label) ? lib[key].label : key;
                            if (key === currentType) opt.selected = true;
                            typeSelect.appendChild(opt);
                        });
                        if (!typeSelect.value) typeSelect.value = currentType;

                        populateVariants(typeSelect.value, currentSub);
                        setPreview(typeSelect.value, variantSelect.value);

                        typeSelect.addEventListener('change', () => {
                            populateVariants(typeSelect.value, 0);
                            setPreview(typeSelect.value, variantSelect.value);
                        });
                        variantSelect.addEventListener('change', () => {
                            setPreview(typeSelect.value, variantSelect.value);
                        });

                        saveBtn.addEventListener('click', async () => {
                            saveBtn.disabled = true;
                            saveBtn.textContent = 'Saving…';
                            try {
                                const nextType = typeSelect.value;
                                const nextSub = parseInt(variantSelect.value, 10) || 0;
                                const nextSize = Math.max(20, Math.min(180, parseInt(sizeInput.value, 10) || currentIconSize));
                                let nextIconUrl = device.icon_url || null;

                                const selectedFile = uploadInput?.files && uploadInput.files[0] ? uploadInput.files[0] : null;
                                if (selectedFile) {
                                    const formData = new FormData();
                                    formData.append('id', String(id));
                                    formData.append('iconFile', selectedFile);

                                    const uploadRes = await fetch(`${MapApp.config.API_URL}?action=upload_device_icon`, {
                                        method: 'POST',
                                        body: formData,
                                    });
                                    const uploadData = await uploadRes.json();
                                    if (!uploadRes.ok || !uploadData.success) {
                                        throw new Error(uploadData.error || 'Icon upload failed.');
                                    }
                                    nextIconUrl = uploadData.url || nextIconUrl;
                                }

                                const updates = {
                                    type: nextType,
                                    subchoice: nextSub,
                                    icon_size: nextSize,
                                    icon_url: nextIconUrl
                                };

                                const updated = await MapApp.api.post('update_device', { id, updates });

                                // Update the node data and redraw icon
                                node.deviceData = updated;
                                if (updated.type === 'box') {
                                    const pos = MapApp.state.network.getPositions([updated.id])[updated.id] || { x: node.x || 0, y: node.y || 0 };
                                    const baseNode = {
                                        id: updated.id,
                                        label: updated.name,
                                        title: MapApp.utils.buildNodeTitle(updated),
                                        x: pos.x,
                                        y: pos.y,
                                        font: {
                                            color: updated.name_text_color || 'white', size: parseInt(updated.name_text_size, 10) || 14, multi: true,
                                            face: (updated.name_text_bold == 1 && updated.name_text_italic == 1) ? 'bold italic Arial' : updated.name_text_bold == 1 ? 'bold Arial' : updated.name_text_italic == 1 ? 'italic Arial' : 'Arial'
                                        },
                                        deviceData: updated
                                    };
                                    MapApp.state.nodes.update(MapApp.utils.buildVisBoxNode(baseNode, updated));
                                } else {
                                    const baseNode = {
                                        id: updated.id,
                                        label: updated.name,
                                        title: MapApp.utils.buildNodeTitle(updated),
                                        font: {
                                            color: updated.name_text_color || 'white', size: parseInt(updated.name_text_size, 10) || 14, multi: true,
                                            face: (updated.name_text_bold == 1 && updated.name_text_italic == 1) ? 'bold italic Arial' : updated.name_text_bold == 1 ? 'bold Arial' : updated.name_text_italic == 1 ? 'italic Arial' : 'Arial',
                                            vadjust: (() => {
                                                let v = (updated.name_text_vadjust !== null && updated.name_text_vadjust !== undefined) ? parseInt(updated.name_text_vadjust, 10) : 0;
                                                if (v === 0) {
                                                    try {
                                                        const g = JSON.parse(localStorage.getItem('globalDeviceLabelSettings') || '{}');
                                                        if (g.vadjust !== undefined) v = parseInt(g.vadjust, 10) || 0;
                                                    } catch(e) {}
                                                }
                                                return v;
                                            })()
                                        },
                                        deviceData: updated
                                    };
                                    const visuals = MapApp.utils.resolveNodeVisuals(updated);
                                    MapApp.state.nodes.update({
                                        ...baseNode,
                                        ...visuals
                                    });
                                }

                                window.notyf.success('Icon updated.');
                                close();
                            } catch (error) {
                                window.notyf.error(error.message || 'Failed to update icon.');
                                saveBtn.disabled = false;
                                saveBtn.textContent = 'Save';
                            }
                        });
                    } else if (action === 'box-settings') {
                        const node = MapApp.state.nodes.get(id);
                        if (!node?.deviceData || node.deviceData.type !== 'box') {
                            window.notyf.error('Group box not found.');
                            return;
                        }
                        const style = MapApp.utils.getBoxStyleFromDevice(node.deviceData);
                        const label = prompt('Group box label text:', node.deviceData.name || node.label || 'Group');
                        if (label === null) return;
                        const width = parseInt(prompt('Box width (px):', String(style.width)), 10);
                        const height = parseInt(prompt('Box height (px):', String(style.height)), 10);
                        const borderWidth = parseInt(prompt('Border width (1-12):', String(style.borderWidth)), 10);
                        const borderColor = prompt('Border color (#hex or CSS):', style.borderColor);
                        const fillColor = prompt('Fill color (rgba/hex):', style.fillColor);
                        const labelAlign = prompt('Label horizontal position (left/center/right):', style.labelAlign || 'center');
                        const labelVAdjust = parseInt(prompt('Label vertical offset (-120..120):', String(style.labelVAdjust || 0)), 10);

                        const nextStyle = {
                            width: Math.max(120, isNaN(width) ? style.width : width),
                            height: Math.max(70, isNaN(height) ? style.height : height),
                            borderWidth: Math.max(1, Math.min(12, isNaN(borderWidth) ? style.borderWidth : borderWidth)),
                            borderColor: borderColor || style.borderColor,
                            fillColor: fillColor || style.fillColor,
                            labelAlign: ['left', 'center', 'right'].includes((labelAlign || '').toLowerCase()) ? (labelAlign || '').toLowerCase() : 'center',
                            labelVAdjust: Math.max(-120, Math.min(120, isNaN(labelVAdjust) ? (style.labelVAdjust || 0) : labelVAdjust))
                        };

                        try {
                            const updated = await MapApp.api.post('update_device', {
                                id,
                                updates: {
                                    name: (label || '').trim() || node.deviceData.name || 'Group',
                                    port_config: MapApp.utils.withUpdatedBoxStyle(node.deviceData, nextStyle),
                                    type: 'box'
                                }
                            });
                            const pos = MapApp.state.network.getPositions([id])[id] || { x: node.x || 0, y: node.y || 0 };
                            const baseNode = {
                                id: updated.id,
                                label: updated.name,
                                title: MapApp.utils.buildNodeTitle(updated),
                                x: pos.x,
                                y: pos.y,
                                font: {
                                    color: updated.name_text_color || 'white', size: parseInt(updated.name_text_size, 10) || 14, multi: true,
                                    face: (updated.name_text_bold == 1 && updated.name_text_italic == 1) ? 'bold italic Arial' : updated.name_text_bold == 1 ? 'bold Arial' : updated.name_text_italic == 1 ? 'italic Arial' : 'Arial'
                                },
                                deviceData: updated
                            };
                            MapApp.state.nodes.update(MapApp.utils.buildVisBoxNode(baseNode, updated));
                            window.notyf.success('Group box updated.');
                        } catch (error) {
                            window.notyf.error(error.message || 'Failed to update group box.');
                        }
                    } else if (action === 'ping') {
                        const icon = document.createElement('i');
                        icon.className = 'fas fa-spinner fa-spin';
                        target.prepend(icon);
                        MapApp.deviceManager.pingSingleDevice(id).finally(() => icon.remove());
                    } else if (action === 'copy') {
                        await MapApp.mapManager.copyDevice(id);
                    } else if (action === 'delete') {
                        if (confirm('Are you sure you want to delete this device?')) {
                            try {
                                await MapApp.api.post('delete_device', { id });
                                window.notyf.success('Device deleted.');
                                // Remove associated edges from DataSet first to avoid visual/logical orphans
                                const edgesToRemove = MapApp.state.edges.get({
                                    filter: (edge) => String(edge.from) === String(id) || String(edge.to) === String(id)
                                }).map(edge => edge.id);
                                if (edgesToRemove.length > 0) {
                                    MapApp.state.edges.remove(edgesToRemove);
                                }
                                MapApp.state.nodes.remove(id);
                            } catch (error) {
                                window.notyf.error(error.message || 'Failed to delete device.');
                            }
                        }
                    } else if (action === 'edit-edge') {
                        MapApp.ui.openEdgeModal(id);
                    } else if (action === 'delete-edge') {
                        if (confirm('Are you sure you want to delete this connection?')) {
                            try {
                                const edge = MapApp.state.edges.get(id);
                                const result = await MapApp.api.post('delete_edge', {
                                    id,
                                    source_id: edge ? edge.from : null,
                                    target_id: edge ? edge.to : null
                                });
                                if (result.success || result.status === 'success') {
                                    window.notyf.success('Connection deleted.');
                                    MapApp.state.edges.remove(id);
                                } else {
                                    window.notyf.error(result.error || 'Failed to delete connection.');
                                }
                            } catch (error) {
                                window.notyf.error(error.message || 'Failed to delete connection.');
                            }
                        }
                    }
                } else { // Viewer role actions
                    if (action === 'view-details') {
                        // For now, just show a toast, but you could open a modal with device details
                        window.notyf.info('Viewer mode: Displaying read-only details (feature not fully implemented for viewers).');
                    } else if (action === 'view-metrics') {
                        await MapApp.ui.openMetricsModal(id);
                    } else if (action === 'ping') {
                        // Viewers can trigger pings, but the server-side API will handle the actual status update.
                        const icon = document.createElement('i');
                        icon.className = 'fas fa-spinner fa-spin';
                        target.prepend(icon);
                        MapApp.deviceManager.pingSingleDevice(id).finally(() => icon.remove());
                    }
                    // Removed the generic error message for viewer actions, as specific actions are handled or disabled.
                }
            }
        });

        // Initialize WebSocket and Timeline
        if (MapApp.network.websocket) {
            MapApp.network.websocket.connect();
        }

        // Bind pulse speed controller
        const speedSelector = document.getElementById('animationSpeedSelector');
        if (speedSelector) {
            globalSpeedMultiplier = parseFloat(speedSelector.value);
            const display = document.getElementById('speedValueDisplay');
            if (display) {
                display.textContent = globalSpeedMultiplier.toFixed(1) + 'x';
            }
            speedSelector.addEventListener('input', (e) => {
                globalSpeedMultiplier = parseFloat(e.target.value);
                if (display) {
                    display.textContent = globalSpeedMultiplier.toFixed(1) + 'x';
                }
            });
        }

        if (MapApp.network.timeline) {
            MapApp.network.timeline.init();
        }
        MapApp.ui.startCanvasAnimationLoop();
    }
};

// --- central websocket client ---
MapApp.network.websocket = {
    socket: null,
    reconnectTimeout: null,
    connect: function () {
        if (this.socket) {
            try { this.socket.close(); } catch (e) { }
        }

        // Connect to ws relative to current window location host
        const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws';
        const wsPort = '8080'; // central websocket notification port
        const wsUrl = `${wsProtocol}://${window.location.hostname}:${wsPort}/ws`;

        console.log(`Connecting to WebSocket: ${wsUrl}`);
        this.socket = new WebSocket(wsUrl);

        this.socket.onopen = () => {
            console.log("WebSocket connected.");
        };

        this.socket.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                this.handleMessage(data);
            } catch (e) {
                console.error("Failed to parse WebSocket message:", e);
            }
        };

        this.socket.onclose = () => {
            console.log("WebSocket disconnected. Retrying in 5s...");
            clearTimeout(this.reconnectTimeout);
            this.reconnectTimeout = setTimeout(() => this.connect(), 5000);
        };

        this.socket.onerror = (err) => {
            console.error("WebSocket error:", err);
            this.socket.close();
        };
    },

    handleMessage: function (data) {
        // Only update if in Live view
        const slider = document.getElementById('timelineSlider');
        if (slider && parseInt(slider.value, 10) !== 24) {
            // Historical view active - ignore live updates
            return;
        }

        if (data && data.device_id && data.status) {
            const nodeId = Number(data.device_id);
            const node = MapApp.state.nodes.get(nodeId);
            if (node && node.deviceData) {
                const oldStatus = node.deviceData.status;

                // Store telemetry metrics if present
                if (data.cpu_usage !== undefined) node.deviceData.cpu_usage = data.cpu_usage;
                if (data.memory_usage !== undefined) node.deviceData.memory_usage = data.memory_usage;
                if (data.network_in !== undefined) node.deviceData.network_in = data.network_in;
                if (data.network_out !== undefined) node.deviceData.network_out = data.network_out;

                // Classify status based on metrics thresholds
                let newStatus = data.status;
                if (newStatus === 'online') {
                    if (data.cpu_usage !== undefined && data.cpu_usage > 95.0) {
                        newStatus = 'critical';
                    } else if (data.cpu_usage !== undefined && data.cpu_usage > 85.0) {
                        newStatus = 'warning';
                    }
                }
                node.deviceData.status = newStatus;

                // Adjust animation speed multiplier dynamically based on CPU usage
                if (data.cpu_usage !== undefined && data.cpu_usage !== null) {
                    const dynamicSpeed = 0.5 + (data.cpu_usage / 100.0) * 4.5;
                    globalSpeedMultiplier = dynamicSpeed;

                    const speedSelector = document.getElementById('animationSpeedSelector');
                    if (speedSelector) {
                        speedSelector.value = dynamicSpeed.toFixed(1);
                    }
                    const display = document.getElementById('speedValueDisplay');
                    if (display) {
                        display.textContent = dynamicSpeed.toFixed(1) + 'x';
                    }
                }

                if (data.last_avg_time !== undefined) node.deviceData.last_avg_time = data.last_avg_time;
                if (data.last_ttl !== undefined) node.deviceData.last_ttl = data.last_ttl;
                if (data.last_seen !== undefined) node.deviceData.last_seen = data.last_seen;

                let label = node.deviceData.name;
                if (node.deviceData.show_live_ping && newStatus === 'online' && node.deviceData.last_avg_time !== null) {
                    label += `\n${node.deviceData.last_avg_time}ms | TTL:${node.deviceData.last_ttl || 'N/A'}`;
                }

                const updatedProps = {
                    id: nodeId,
                    deviceData: node.deviceData,
                    title: MapApp.utils.buildNodeTitle(node.deviceData),
                    label: label
                };

                if (node.shape === 'icon') {
                    updatedProps.icon = {
                        ...node.icon,
                        color: MapApp.config.statusColorMap[newStatus] || MapApp.config.statusColorMap.unknown
                    };
                } else if (node.shape === 'image') {
                    updatedProps.color = {
                        border: MapApp.config.statusColorMap[newStatus] || MapApp.config.statusColorMap.unknown,
                        background: 'transparent'
                    };
                } else if (node.shape === 'box') {
                    const style = MapApp.utils.getBoxStyleFromDevice(node.deviceData);
                    updatedProps.color = {
                        background: style.fillColor,
                        border: style.borderColor
                    };
                }

                MapApp.state.nodes.update(updatedProps);

                if (window.SoundManager && oldStatus !== newStatus) {
                    window.SoundManager.playForStatus(newStatus);
                }
            }
        }
    }
};

// --- timeline slider & playback management ---
MapApp.network.timeline = {
    playInterval: null,
    isPlaying: false,

    init: function () {
        const slider = document.getElementById('timelineSlider');
        const playBtn = document.getElementById('timelinePlayBtn');
        const statusText = document.getElementById('timelineStatusText');
        const playIcon = document.getElementById('timelinePlayIcon');

        if (!slider || !playBtn) return;

        slider.addEventListener('input', () => {
            const val = parseInt(slider.value, 10);
            if (val === 24) {
                if (typeof startEdgeAnimation === 'function') startEdgeAnimation();
                statusText.innerHTML = `<span class="w-1.5 h-1.5 bg-cyan-400 rounded-full animate-ping"></span>Live View`;
                statusText.className = "text-cyan-400 font-semibold bg-cyan-950/40 border border-cyan-800/30 px-2 py-0.5 rounded-full flex items-center gap-1.5";
            } else {
                if (typeof stopEdgeAnimation === 'function') stopEdgeAnimation();
                const hoursAgo = 24 - val;
                statusText.innerHTML = `<i class="fas fa-history mr-1"></i>${hoursAgo} Hour${hoursAgo > 1 ? 's' : ''} Ago`;
                statusText.className = "text-amber-400 font-semibold bg-amber-950/40 border border-amber-800/30 px-2 py-0.5 rounded-full flex items-center gap-1.5";
            }
        });

        slider.addEventListener('change', async () => {
            const val = parseInt(slider.value, 10);
            if (val === 24) {
                this.stopPlay();
                const currentMapId = MapApp.state.currentMapId;
                if (currentMapId) {
                    try {
                        const deviceResponse = await MapApp.api.get('get_devices', { map_id: currentMapId });
                        const devices = deviceResponse.devices || [];
                        devices.forEach(d => {
                            const node = MapApp.state.nodes.get(d.id);
                            if (node && node.deviceData) {
                                node.deviceData = d;
                                let label = d.name;
                                if (d.show_live_ping && d.status === 'online' && d.last_avg_time !== null) {
                                    label += `\n${d.last_avg_time}ms | TTL:${d.last_ttl || 'N/A'}`;
                                }
                                const updatedProps = { id: d.id, deviceData: d, title: MapApp.utils.buildNodeTitle(d), label };
                                if (node.shape === 'icon') {
                                    updatedProps.icon = { ...node.icon, color: MapApp.config.statusColorMap[d.status] || MapApp.config.statusColorMap.unknown };
                                } else if (node.shape === 'image') {
                                    updatedProps.color = { border: MapApp.config.statusColorMap[d.status] || MapApp.config.statusColorMap.unknown, background: 'transparent' };
                                }
                                MapApp.state.nodes.update(updatedProps);
                            }
                        });
                        MapApp.deviceManager.setupAutoPing(devices);
                    } catch (e) {
                        console.error("Error restoring live view:", e);
                    }
                }
            } else {
                Object.values(MapApp.state.pingIntervals).forEach(clearInterval);
                MapApp.state.pingIntervals = {};

                const hoursAgo = 24 - val;
                const currentMapId = MapApp.state.currentMapId;
                if (currentMapId) {
                    try {
                        const res = await fetch(`api.php?action=get_historical_map_state&map_id=${currentMapId}&hours_ago=${hoursAgo}`);
                        const historicalStates = await res.json();

                        if (Array.isArray(historicalStates)) {
                            historicalStates.forEach(state => {
                                const node = MapApp.state.nodes.get(state.id);
                                if (node && node.deviceData) {
                                    const updatedData = { ...node.deviceData, status: state.status };
                                    let label = updatedData.name;
                                    const updatedProps = {
                                        id: state.id,
                                        deviceData: updatedData,
                                        title: MapApp.utils.buildNodeTitle(updatedData),
                                        label: label
                                    };

                                    if (node.shape === 'icon') {
                                        updatedProps.icon = {
                                            ...node.icon,
                                            color: MapApp.config.statusColorMap[state.status] || MapApp.config.statusColorMap.unknown
                                        };
                                    } else if (node.shape === 'image') {
                                        updatedProps.color = {
                                            border: MapApp.config.statusColorMap[state.status] || MapApp.config.statusColorMap.unknown,
                                            background: 'transparent'
                                        };
                                    } else if (node.shape === 'box') {
                                        const style = MapApp.utils.getBoxStyleFromDevice(updatedData);
                                        updatedProps.color = {
                                            background: style.fillColor,
                                            border: style.borderColor
                                        };
                                    }

                                    MapApp.state.nodes.update(updatedProps);
                                }
                            });
                        }
                    } catch (e) {
                        console.error("Failed to load historical states:", e);
                    }
                }
            }
        });

        playBtn.addEventListener('click', () => {
            if (this.isPlaying) {
                this.stopPlay();
            } else {
                this.startPlay();
            }
        });
    },

    startPlay: function () {
        const playBtn = document.getElementById('timelinePlayBtn');
        const playIcon = document.getElementById('timelinePlayIcon');
        const slider = document.getElementById('timelineSlider');

        if (!playBtn || !slider) return;

        this.isPlaying = true;
        playIcon.className = 'fas fa-pause';
        playBtn.classList.remove('from-cyan-600', 'to-blue-600');
        playBtn.classList.add('from-amber-600', 'to-orange-600');

        if (parseInt(slider.value, 10) === 24) {
            slider.value = 0;
            slider.dispatchEvent(new Event('input'));
            slider.dispatchEvent(new Event('change'));
        }

        this.playInterval = setInterval(() => {
            let nextVal = parseInt(slider.value, 10) + 1;
            if (nextVal > 24) {
                nextVal = 0;
            }
            slider.value = nextVal;
            slider.dispatchEvent(new Event('input'));
            slider.dispatchEvent(new Event('change'));
        }, 1500);
    },

    stopPlay: function () {
        const playBtn = document.getElementById('timelinePlayBtn');
        const playIcon = document.getElementById('timelinePlayIcon');

        if (!playBtn) return;

        this.isPlaying = false;
        playIcon.className = 'fas fa-play';
        playBtn.classList.remove('from-amber-600', 'to-orange-600');
        playBtn.classList.add('from-cyan-600', 'to-blue-600');

        if (this.playInterval) {
            clearInterval(this.playInterval);
            this.playInterval = null;
        }
    },

    reset: function () {
        this.stopPlay();
        const slider = document.getElementById('timelineSlider');
        if (slider) {
            slider.value = 24;
            slider.dispatchEvent(new Event('input'));
        }
    }
};

// --- Topology Path Tracer Engine ---
window.TopologyPathTracer = {
    activePathEdges: [],

    init: function() {
        const btn = document.getElementById('pathTracerBtn');
        const execBtn = document.getElementById('btnExecutePathTrace');

        if (btn) {
            btn.addEventListener('click', () => this.openModal());
        }
        if (execBtn) {
            execBtn.addEventListener('click', () => this.executeTrace());
        }
    },

    openModal: function() {
        const modal = document.getElementById('pathTracerModal');
        const srcSelect = document.getElementById('traceSourceSelect');
        const tgtSelect = document.getElementById('traceTargetSelect');
        const resultsBox = document.getElementById('traceResultsBox');

        if (!modal || !srcSelect || !tgtSelect) return;

        resultsBox.classList.add('hidden');

        // Populate selects with all active nodes on the map
        const allNodes = nodes ? nodes.get({ filter: item => item.isDevice !== false }) : [];
        if (allNodes.length === 0) {
            alert('No device nodes present on the current map.');
            return;
        }

        const options = allNodes.map(n => `<option value="${n.id}">${n.label || n.name || 'Device'} (${n.ip || 'No IP'})</option>`).join('');
        srcSelect.innerHTML = options;
        tgtSelect.innerHTML = options;

        if (allNodes.length > 1) {
            tgtSelect.selectedIndex = 1;
        }

        modal.classList.remove('hidden');
    },

    executeTrace: async function() {
        const srcId = document.getElementById('traceSourceSelect').value;
        const tgtId = document.getElementById('traceTargetSelect').value;
        const mapId = currentMapId;

        if (!srcId || !tgtId || srcId === tgtId) {
            alert('Please select two distinct source and destination nodes.');
            return;
        }

        const btn = document.getElementById('btnExecutePathTrace');
        btn.disabled = true;
        btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Tracing Route...`;

        try {
            const resp = await fetch(`api.php?action=trace_topology_path&map_id=${encodeURIComponent(mapId)}&source_id=${encodeURIComponent(srcId)}&target_id=${encodeURIComponent(tgtId)}`);
            const data = await resp.json();

            if (!data.success) {
                alert(data.message || 'No routing path found between the selected nodes.');
                return;
            }

            this.renderTraceResults(data);
            this.highlightPathOnMap(data.path_node_ids, data.path_edge_ids);
        } catch (e) {
            alert('Path tracing failed: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-play"></i> Trace Path &amp; Animate`;
        }
    },

    renderTraceResults: function(data) {
        const box = document.getElementById('traceResultsBox');
        const hopCountEl = document.getElementById('traceHopCount');
        const latencyEl = document.getElementById('traceLatency');
        const listEl = document.getElementById('traceHopsList');

        hopCountEl.textContent = `${data.hop_count} Hop(s)`;
        latencyEl.textContent = `~${data.cumulative_latency_ms} ms Cumulative`;

        listEl.innerHTML = data.hops.map((h, idx) => `
            <div class="p-2.5 bg-slate-950/70 border border-slate-800 rounded-lg flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-5 h-5 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center font-bold text-[10px]">#${idx + 1}</span>
                    <div>
                        <div class="font-bold text-white">${h.name}</div>
                        <div class="text-[10px] text-slate-400 font-mono">${h.ip || 'N/A'} • ${h.type}</div>
                    </div>
                </div>
                <span class="font-mono text-emerald-400 text-xs">${h.hop_latency_ms}ms</span>
            </div>
        `).join('');

        box.classList.remove('hidden');
    },

    highlightPathOnMap: function(nodeIds, edgeIds) {
        if (!network || !edges) return;

        // Focus and select path nodes
        network.selectNodes(nodeIds, true);
        network.fit({
            nodes: nodeIds,
            animation: { duration: 1000, easingFunction: 'easeInOutQuad' }
        });

        // Pulse path edges
        edgeIds.forEach(eId => {
            const edge = edges.get(eId);
            if (edge) {
                edges.update({
                    id: eId,
                    color: { color: '#10b981', highlight: '#34d399', hover: '#34d399' },
                    width: 5
                });
            }
        });
    }
};

window.closePathTracerModal = function() {
    const modal = document.getElementById('pathTracerModal');
    if (modal) modal.classList.add('hidden');
};

document.addEventListener('DOMContentLoaded', () => {
    window.TopologyPathTracer.init();
});

