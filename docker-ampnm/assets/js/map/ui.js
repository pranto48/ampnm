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

    updateAndAnimateEdges: () => {
        MapApp.state.tick++;
        const animatedDashes = [4 - (MapApp.state.tick % 12), 8, MapApp.state.tick % 12];
        const updates = [];
        const allEdges = MapApp.state.edges.get();
        if (MapApp.state.nodes.length > 0 && allEdges.length > 0) {
            const deviceStatusMap = new Map(MapApp.state.nodes.get({ fields: ['id', 'deviceData'] }).map(d => [d.id, d.deviceData.status]));
            allEdges.forEach(edge => {
                const sourceStatus = deviceStatusMap.get(edge.from);
                const targetStatus = deviceStatusMap.get(edge.to);
                const isOffline = sourceStatus === 'offline' || targetStatus === 'offline';
                const isActive = sourceStatus === 'online' && targetStatus === 'online';
                const color = isOffline ? MapApp.config.statusColorMap.offline : (MapApp.config.edgeColorMap[edge.connection_type] || MapApp.config.edgeColorMap.cat6);
                let dashes = false;
                if (isActive) { dashes = animatedDashes; } 
                else if (edge.connection_type === 'wifi' || edge.connection_type === 'radio' || edge.connection_type === 'logical-tunneling') { dashes = [5, 5]; }
                updates.push({ id: edge.id, color, dashes });
            });
        }
        if (updates.length > 0) MapApp.state.edges.update(updates);
        MapApp.state.animationFrameId = requestAnimationFrame(MapApp.ui.updateAndAnimateEdges);
    }
};