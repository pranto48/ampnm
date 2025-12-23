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