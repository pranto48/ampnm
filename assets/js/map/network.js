window.MapApp = window.MapApp || {};

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
                        // No error message needed, as the button is disabled for viewers
                        callback(null); // Cancel adding edge
                        return;
                    }
                    const newEdge = await MapApp.api.post('create_edge', { source_id: edgeData.from, target_id: edgeData.to, map_id: MapApp.state.currentMapId, connection_type: 'cat6' }); 
                    edgeData.id = newEdge.id; edgeData.label = 'cat6'; callback(edgeData); 
                    window.notyf.success('Connection added.');
                }
            } 
        };
        MapApp.state.network = new vis.Network(container, data, options);
        MapApp.network.restoreSavedView();

        MapApp.state.network.on("afterDrawing", (ctx) => {
            if (!MapApp.state.network) return;
            const edges = MapApp.state.network.body.edges;
            
            // Build a lookup map of node statuses
            const deviceStatuses = {};
            MapApp.state.nodes.forEach(node => {
                if (node.deviceData) {
                    deviceStatuses[node.id] = node.deviceData.status;
                }
            });

            ctx.save();
            
            for (const edgeId in edges) {
                const edge = edges[edgeId];
                if (!edge.from || !edge.to) continue;

                const sourceStatus = deviceStatuses[edge.from.id];
                const targetStatus = deviceStatuses[edge.to.id];
                const isOffline = sourceStatus === 'offline' || targetStatus === 'offline';

                if (isOffline) continue;

                // Render 3 distinct glowing pulses spaced out by an offset (t + i/3) % 1.0 traveling from source to target
                for (let i = 0; i < 3; i++) {
                    const t = (MapApp.state.edgeAnimProgress + i / 3) % 1.0;
                    try {
                        const point = edge.getPoint(t);
                        ctx.beginPath();
                        ctx.arc(point.x, point.y, 4, 0, 2 * Math.PI);
                        ctx.fillStyle = '#00F2FE';
                        ctx.shadowColor = '#00F2FE';
                        ctx.shadowBlur = 8;
                        ctx.fill();
                    } catch (e) {
                        // getPoint can fail during initialization or node dragging
                    }
                }
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
                font: { color: 'white', size: parseInt(updatedDeviceData.name_text_size, 10) || 14, multi: true },
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
            if (window.userRole === 'admin' && params.nodes.length > 0) MapApp.ui.openDeviceModal(params.nodes[0]); 
        });

        const closeContextMenu = () => { contextMenu.style.display = 'none'; };
        MapApp.state.network.on("oncontext", (params) => {
            params.event.preventDefault();
            const nodeId = MapApp.state.network.getNodeAt(params.pointer.DOM);
            const edgeId = MapApp.state.network.getEdgeAt(params.pointer.DOM);

            if (nodeId) {
                const node = MapApp.state.nodes.get(nodeId);
                let menuItems = ``;
                if (window.userRole === 'admin') {
                    menuItems += `
                        <div class="context-menu-item" data-action="edit" data-id="${nodeId}"><i class="fas fa-edit fa-fw mr-2"></i>Edit</div>
                        ${node.deviceData.type === 'box' ? `<div class="context-menu-item" data-action="box-settings" data-id="${nodeId}"><i class="fas fa-vector-square fa-fw mr-2"></i>Box Settings</div>` : ''}
                        ${node.deviceData.type !== 'box' ? `<div class="context-menu-item" data-action="view-metrics" data-id="${nodeId}"><i class="fas fa-chart-line fa-fw mr-2"></i>Metrics Graph</div>` : ''}
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
                console.log("Context menu opened for edge. Edge ID:", edgeId); // Added console.log
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
                const { action } = target.dataset;
                // Parse ID as number to match vis.js integer node IDs from MySQL AUTO_INCREMENT
                const id = isNaN(target.dataset.id) ? target.dataset.id : Number(target.dataset.id);
                closeContextMenu();

                if (window.userRole === 'admin') {
                    if (action === 'edit') {
                        MapApp.ui.openDeviceModal(id);
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
                                const isImage = !!updated.icon_url;
                                if (isImage) {
                                    MapApp.state.nodes.update({
                                        id: updated.id,
                                        label: updated.name,
                                        title: MapApp.utils.buildNodeTitle(updated),
                                        deviceData: updated,
                                        shape: 'image',
                                        image: updated.icon_url,
                                        size: (parseInt(updated.icon_size, 10) || 50) / 2,
                                    });
                                } else if (updated.type === 'box') {
                                    const pos = MapApp.state.network.getPositions([updated.id])[updated.id] || { x: node.x || 0, y: node.y || 0 };
                                    const baseNode = {
                                        id: updated.id,
                                        label: updated.name,
                                        title: MapApp.utils.buildNodeTitle(updated),
                                        x: pos.x,
                                        y: pos.y,
                                        font: { color: 'white', size: parseInt(updated.name_text_size, 10) || 14, multi: true },
                                        deviceData: updated
                                    };
                                    MapApp.state.nodes.update(MapApp.utils.buildVisBoxNode(baseNode, updated));
                                } else {
                                    MapApp.state.nodes.update({
                                        id: updated.id,
                                        label: updated.name,
                                        title: MapApp.utils.buildNodeTitle(updated),
                                        deviceData: updated,
                                        shape: 'icon',
                                        icon: {
                                            face: "'Font Awesome 6 Free'",
                                            weight: '900',
                                            code: MapApp.mapManager.getDeviceIconUnicode(updated),
                                            size: parseInt(updated.icon_size, 10) || 50,
                                            color: (MapApp.config && MapApp.config.statusColorMap && MapApp.config.statusColorMap[updated.status]) ? MapApp.config.statusColorMap[updated.status] : '#94a3b8'
                                        }
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
                                font: { color: 'white', size: parseInt(updated.name_text_size, 10) || 14, multi: true },
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
                                const result = await MapApp.api.post('delete_edge', { id });
                                if (result.success) {
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
    connect: function() {
        if (this.socket) {
            try { this.socket.close(); } catch(e) {}
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
    
    handleMessage: function(data) {
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
                const newStatus = data.status;
                
                node.deviceData.status = newStatus;
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
    
    init: function() {
        const slider = document.getElementById('timelineSlider');
        const playBtn = document.getElementById('timelinePlayBtn');
        const statusText = document.getElementById('timelineStatusText');
        const playIcon = document.getElementById('timelinePlayIcon');
        
        if (!slider || !playBtn) return;
        
        slider.addEventListener('input', () => {
            const val = parseInt(slider.value, 10);
            if (val === 24) {
                statusText.innerHTML = `<span class="w-1.5 h-1.5 bg-cyan-400 rounded-full animate-ping"></span>Live View`;
                statusText.className = "text-cyan-400 font-semibold bg-cyan-950/40 border border-cyan-800/30 px-2 py-0.5 rounded-full flex items-center gap-1.5";
            } else {
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
    
    startPlay: function() {
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
    
    stopPlay: function() {
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
    
    reset: function() {
        this.stopPlay();
        const slider = document.getElementById('timelineSlider');
        if (slider) {
            slider.value = 24;
            slider.dispatchEvent(new Event('input'));
        }
    }
};
