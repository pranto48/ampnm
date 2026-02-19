window.MapApp = window.MapApp || {};

MapApp.network = {
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
                    const newEdge = await MapApp.api.post('create_edge', { source_id: edgeData.from, target_id: edgeData.to, map_id: MapApp.state.currentMapId, connection_type: 'cat5' }); 
                    edgeData.id = newEdge.id; edgeData.label = 'cat5'; callback(edgeData); 
                    window.notyf.success('Connection added.');
                }
            } 
        };
        MapApp.state.network = new vis.Network(container, data, options);
        
        // Event Handlers
        MapApp.state.network.on("dragEnd", async (params) => { 
            if (window.userRole !== 'admin') return; // Only admin can drag
            if (params.nodes.length > 0) { 
                const nodeId = params.nodes[0]; 
                const position = MapApp.state.network.getPositions([nodeId])[nodeId]; 
                await MapApp.api.post('update_device', { id: nodeId, updates: { x: position.x, y: position.y } }); 
            } 
        });
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
                        <div class="context-menu-item" data-action="change-icon" data-id="${nodeId}"><i class="fas fa-icons fa-fw mr-2"></i>Change Icon</div>
                        <div class="context-menu-item" data-action="copy" data-id="${nodeId}"><i class="fas fa-copy fa-fw mr-2"></i>Copy</div>
                        ${node.deviceData.ip ? `<div class="context-menu-item" data-action="ping" data-id="${nodeId}"><i class="fas fa-sync fa-fw mr-2"></i>Check Status</div>` : ''}
                        <div class="context-menu-item" data-action="delete" data-id="${nodeId}" style="color: #ef4444;"><i class="fas fa-trash-alt fa-fw mr-2"></i>Delete</div>
                    `;
                } else {
                    menuItems += `<div class="context-menu-item" data-action="view-details" data-id="${nodeId}"><i class="fas fa-info-circle fa-fw mr-2"></i>View Details</div>`;
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
                const { action, id } = target.dataset;
                closeContextMenu();

                if (window.userRole === 'admin') {
                    if (action === 'edit') {
                        MapApp.ui.openDeviceModal(id);
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
                            <div style="width: min(520px, 92vw); background: rgba(15, 23, 42, 0.98); border: 1px solid rgba(51, 65, 85, 0.9); border-radius: 12px; padding: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.4);">
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
                                const updated = await MapApp.api.post('update_device', { id, updates: { type: nextType, subchoice: nextSub } });

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
                                    MapApp.state.nodes.update({
                                        id: updated.id,
                                        label: updated.name,
                                        title: MapApp.utils.buildNodeTitle(updated),
                                        deviceData: updated,
                                        shape: 'box',
                                    });
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
    }
};