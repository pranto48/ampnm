/**
 * Floor Plan Canvas - Interactive SVG editor
 * Pan/zoom, rack & device drag, annotations, cable drawing, export
 */
const FPCanvas = {
    svg: null,
    g: null,
    zoom: 1,
    panX: 0,
    panY: 0,
    isPanning: false,
    panStart: { x: 0, y: 0 },
    dragging: null,
    activeTool: 'select', // select, add-rack, add-device, add-label, draw-cable
    snapToGrid: true,
    gridSize: 20,
    selectedItem: null,
    cableStart: null,
    canvasWidth: 2000,
    canvasHeight: 1500,
    // Data
    racks: [],
    planDevices: [],
    cables: [],
    annotations: [],
    container: null,
    resizeHandler: null,
    backgroundRenderUrl: null,
    backgroundIsPdf: false,

    CABLE_COLOR_MAP: {
        blue: '#3b82f6', red: '#ef4444', green: '#22c55e', yellow: '#eab308',
        orange: '#f97316', white: '#e2e8f0', gray: '#64748b', purple: '#a855f7', black: '#1e293b'
    },

    toNumber(value, fallback) {
        const n = Number(value);
        return Number.isFinite(n) ? n : fallback;
    },

    init(containerId, data) {
        const container = document.getElementById(containerId);
        if (!container) return;
        this.container = container;
        this.canvasWidth = this.toNumber(data.plan?.width, 2000);
        this.canvasHeight = this.toNumber(data.plan?.height, 1500);
        this.backgroundUrl = data.plan?.image_url || null;
        this.backgroundRenderUrl = this.backgroundUrl;
        this.backgroundIsPdf = this.isPdfUrl(this.backgroundUrl);
        this.zoom = 1;
        this.panX = 0;
        this.panY = 0;
        this.isPanning = false;
        this.dragging = null;
        this.cableStart = null;

        container.innerHTML = `<svg id="fp-canvas-svg" class="w-full h-full block" style="height:600px; background:#0f172a; border-radius:8px; border:1px solid #334155; cursor:default; touch-action:none;"></svg>`;
        this.svg = document.getElementById('fp-canvas-svg');
        this.svg.setAttribute('viewBox', `0 0 ${this.canvasWidth} ${this.canvasHeight}`);
        this.svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
        this.g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        this.svg.appendChild(this.g);

        // Zoom indicator
        const zoomText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        zoomText.setAttribute('x', 8);
        zoomText.setAttribute('y', 20);
        zoomText.setAttribute('fill', '#94a3b8');
        zoomText.setAttribute('font-size', 11);
        zoomText.setAttribute('font-family', 'monospace');
        zoomText.id = 'fp-zoom-label';
        zoomText.textContent = '100%';
        this.svg.appendChild(zoomText);

        this.svg.addEventListener('wheel', e => this.onWheel(e), { passive: false });
        this.svg.addEventListener('mousedown', e => this.onMouseDown(e));
        this.svg.addEventListener('mousemove', e => this.onMouseMove(e));
        this.svg.addEventListener('mouseup', () => this.onMouseUp());
        this.svg.addEventListener('mouseleave', () => this.onMouseUp());
        this.svg.addEventListener('contextmenu', e => e.preventDefault());

        if (this.resizeHandler) {
            window.removeEventListener('resize', this.resizeHandler);
        }
        this.resizeHandler = () => this.fitToView();
        window.addEventListener('resize', this.resizeHandler);

        this.render();
        this.prepareBackgroundRender().finally(() => {
            this.render();
            requestAnimationFrame(() => this.fitToView());
        });
    },

    isPdfUrl(url) {
        if (!url) return false;
        const clean = String(url).split('?')[0].split('#')[0].toLowerCase();
        return clean.endsWith('.pdf') || String(url).toLowerCase().startsWith('data:application/pdf');
    },

    async prepareBackgroundRender() {
        if (!this.backgroundUrl || !this.backgroundIsPdf) {
            this.backgroundRenderUrl = this.backgroundUrl;
            return;
        }
        try {
            if (!window.pdfjsLib) throw new Error('pdfjsLib not available');
            if (window.pdfjsLib.GlobalWorkerOptions) {
                window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.8.69/build/pdf.worker.min.mjs';
            }
            const pdf = await window.pdfjsLib.getDocument(this.backgroundUrl).promise;
            const page = await pdf.getPage(1);
            const viewport = page.getViewport({ scale: 1 });
            const renderCanvas = document.createElement('canvas');
            renderCanvas.width = this.canvasWidth;
            renderCanvas.height = this.canvasHeight;
            const ctx = renderCanvas.getContext('2d', { alpha: false });
            ctx.fillStyle = '#0f172a';
            ctx.fillRect(0, 0, this.canvasWidth, this.canvasHeight);
            const pageScale = Math.min(this.canvasWidth / viewport.width, this.canvasHeight / viewport.height);
            const scaledViewport = page.getViewport({ scale: pageScale });
            const offsetX = (this.canvasWidth - scaledViewport.width) / 2;
            const offsetY = (this.canvasHeight - scaledViewport.height) / 2;
            ctx.save();
            ctx.translate(offsetX, offsetY);
            await page.render({ canvasContext: ctx, viewport: scaledViewport }).promise;
            ctx.restore();
            this.backgroundRenderUrl = renderCanvas.toDataURL('image/png');
        } catch (error) {
            console.warn('Failed to render PDF background, fallback to URL.', error);
            this.backgroundRenderUrl = this.backgroundUrl;
        }
    },

    snap(v) { return this.snapToGrid ? Math.round(v / this.gridSize) * this.gridSize : v; },

    svgPoint(clientX, clientY) {
        const rect = this.svg.getBoundingClientRect();
        return {
            x: (clientX - rect.left - this.panX) / this.zoom,
            y: (clientY - rect.top - this.panY) / this.zoom
        };
    },

    onWheel(e) {
        e.preventDefault();
        const delta = e.deltaY > 0 ? 0.9 : 1.1;
        this.zoom = Math.max(0.15, Math.min(5, this.zoom * delta));
        document.getElementById('fp-zoom-label').textContent = Math.round(this.zoom * 100) + '%';
        this.updateTransform();
    },

    onMouseDown(e) {
        if (e.button !== 0) return;
        const target = e.target;
        const kind = target.closest('[data-kind]')?.getAttribute('data-kind');
        const id = target.closest('[data-id]')?.getAttribute('data-id');

        if (kind && id) {
            if (this.activeTool === 'draw-cable' && (kind === 'rack' || kind === 'device')) {
                this.handleCableClick(kind, id);
                return;
            }
            if (this.activeTool === 'select') {
                this.selectItem(kind, id);
                // Start drag
                if (kind === 'rack' || kind === 'device' || kind === 'annotation') {
                    const pt = this.svgPoint(e.clientX, e.clientY);
                    const item = this.findItem(kind, id);
                    if (item && item.movable !== false) {
                        this.dragging = { kind, id, offsetX: pt.x - item.x, offsetY: pt.y - item.y };
                    }
                }
            }
            return;
        }

        // Click on empty space
        if (this.activeTool === 'select') {
            this.selectItem(null, null);
            // Start panning
            this.isPanning = true;
            this.panStart = { x: e.clientX - this.panX, y: e.clientY - this.panY };
        } else if (['add-rack', 'add-device', 'add-label'].includes(this.activeTool)) {
            const pt = this.svgPoint(e.clientX, e.clientY);
            this.handleCanvasClick(this.snap(pt.x), this.snap(pt.y));
        }
    },

    onMouseMove(e) {
        if (this.isPanning) {
            this.panX = e.clientX - this.panStart.x;
            this.panY = e.clientY - this.panStart.y;
            this.updateTransform();
        }
        if (this.dragging) {
            const pt = this.svgPoint(e.clientX, e.clientY);
            const nx = this.snap(pt.x - this.dragging.offsetX);
            const ny = this.snap(pt.y - this.dragging.offsetY);
            this.moveItem(this.dragging.kind, this.dragging.id, nx, ny);
        }
    },

    onMouseUp() {
        if (this.dragging) {
            // Save position to server
            const d = this.dragging;
            const item = this.findItem(d.kind, d.id);
            if (item) this.savePosition(d.kind, d.id, item.x, item.y);
        }
        this.isPanning = false;
        this.dragging = null;
    },

    updateTransform() {
        this.g.setAttribute('transform', `translate(${this.panX}, ${this.panY}) scale(${this.zoom})`);
    },

    findItem(kind, id) {
        if (kind === 'rack') return this.racks.find(r => r.id == id);
        if (kind === 'device') return this.planDevices.find(d => d.id == id);
        if (kind === 'annotation') return this.annotations.find(a => a.id == id);
        return null;
    },

    moveItem(kind, id, x, y) {
        const item = this.findItem(kind, id);
        if (item) { item.x = x; item.y = y; this.render(); }
    },

    selectItem(kind, id) {
        this.selectedItem = kind && id ? { kind, id } : null;
        this.render();
        // Update properties panel
        if (typeof window.onCanvasSelect === 'function') {
            window.onCanvasSelect(kind ? this.findItem(kind, id) : null, kind);
        }
    },

    async savePosition(kind, id, x, y) {
        if (kind === 'rack') {
            await fpApi('update_rack', { id, x, y });
        } else if (kind === 'device') {
            const item = this.findItem(kind, id);
            if (item && item.movable === false) return;
            await fpApi('move_plan_device', { id, x, y });
        } else if (kind === 'annotation') {
            await fpApi('update_annotation', { id, x, y });
        }
    },

    handleCanvasClick(x, y) {
        if (this.activeTool === 'add-rack') {
            this.promptAddRack(x, y);
        } else if (this.activeTool === 'add-device') {
            this.promptAddDevice(x, y);
        } else if (this.activeTool === 'add-label') {
            this.promptAddLabel(x, y);
        }
    },

    async promptAddRack(x, y) {
        const name = prompt('Rack name:', 'Rack ' + (this.racks.length + 1));
        if (!name) return;
        const res = await fpApi('create_rack', { floor_plan_id: selectedPlanId, name, rack_units: 42, x, y });
        if (res.success) { await loadPlanData(); notyf.success('Rack placed.'); }
        this.activeTool = 'select';
        this.updateToolButtons();
    },

    async promptAddDevice(x, y) {
        // Show device picker
        const unplaced = (window.devices || []).filter(d => !this.planDevices.find(pd => pd.device_id == d.id));
        if (!unplaced.length) { notyf.error('All devices are already placed.'); this.activeTool = 'select'; this.updateToolButtons(); return; }
        let html = '<div class="max-h-60 overflow-y-auto space-y-1">';
        unplaced.forEach(d => {
            html += `<button onclick="FPCanvas.placeDevice(${d.id}, ${x}, ${y})" class="w-full text-left px-3 py-2 bg-slate-700 hover:bg-slate-600 rounded text-sm text-white">${d.name} <span class="text-slate-400">(${d.type || 'device'})</span></button>`;
        });
        html += '</div>';
        document.getElementById('canvas-device-picker').innerHTML = html;
        document.getElementById('canvas-device-picker-dialog').classList.remove('hidden');
    },

    async placeDevice(deviceId, x, y) {
        document.getElementById('canvas-device-picker-dialog').classList.add('hidden');
        const res = await fpApi('place_device_on_plan', { floor_plan_id: selectedPlanId, device_id: deviceId, x, y });
        if (res.success) { await loadPlanData(); notyf.success('Device placed.'); }
        this.activeTool = 'select';
        this.updateToolButtons();
    },

    async promptAddLabel(x, y) {
        const text = prompt('Label text:', 'Label');
        if (!text) return;
        const res = await fpApi('create_annotation', { floor_plan_id: selectedPlanId, x, y, text, type: 'label' });
        if (res.success) { await loadPlanData(); notyf.success('Label added.'); }
        this.activeTool = 'select';
        this.updateToolButtons();
    },

    handleCableClick(kind, id) {
        if (!this.cableStart) {
            this.cableStart = { kind, id };
            notyf.open({ type: 'info', message: 'Click a destination rack or device to draw cable.', duration: 3000 });
        } else {
            if (this.cableStart.id == id) { this.cableStart = null; return; }
            this.drawCableBetween(this.cableStart, { kind, id });
            this.cableStart = null;
        }
    },

    async drawCableBetween(src, dst) {
        const res = await fpApi('create_cable', {
            floor_plan_id: selectedPlanId,
            cable_type: 'cat6', cable_color: 'blue',
            source_type: src.kind, source_id: src.id, source_port: 1,
            dest_type: dst.kind, dest_id: dst.id, dest_port: 1
        });
        if (res.success) { await loadPlanData(); notyf.success('Cable drawn.'); }
        this.activeTool = 'select';
        this.updateToolButtons();
    },

    setTool(tool) {
        this.activeTool = tool;
        this.cableStart = null;
        this.svg.style.cursor = tool === 'select' ? 'default' : tool === 'draw-cable' ? 'crosshair' : 'cell';
        this.updateToolButtons();
    },

    updateToolButtons() {
        document.querySelectorAll('.canvas-tool-btn').forEach(btn => {
            btn.classList.toggle('bg-cyan-600', btn.dataset.tool === this.activeTool);
            btn.classList.toggle('text-white', btn.dataset.tool === this.activeTool);
            btn.classList.toggle('bg-slate-700', btn.dataset.tool !== this.activeTool);
            btn.classList.toggle('text-slate-300', btn.dataset.tool !== this.activeTool);
        });
    },

    async deleteSelected() {
        if (!this.selectedItem) return;
        const { kind, id } = this.selectedItem;
        if (!confirm(`Delete this ${kind}?`)) return;
        if (kind === 'rack') await fpApi('delete_rack', { id });
        else if (kind === 'device') await fpApi('remove_plan_device', { id });
        else if (kind === 'annotation') await fpApi('delete_annotation', { id });
        else if (kind === 'cable') await fpApi('delete_cable', { id });
        this.selectedItem = null;
        await loadPlanData();
        notyf.success(`${kind} deleted.`);
    },

    // ==================== RENDERING ====================
    render() {
        const g = this.g;
        g.innerHTML = '';

        // Grid
        if (this.snapToGrid) {
            const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
            defs.innerHTML = `<pattern id="fpgrid" width="${this.gridSize}" height="${this.gridSize}" patternUnits="userSpaceOnUse">
                <path d="M ${this.gridSize} 0 L 0 0 0 ${this.gridSize}" fill="none" stroke="#334155" stroke-width="0.5" opacity="0.3"/>
            </pattern>`;
            g.appendChild(defs);
        }

        // Background rect
        const bgBase = this.createSVG('rect', { x: 0, y: 0, width: this.canvasWidth, height: this.canvasHeight, fill: '#0f172a', rx: 4, class: 'canvas-bg' });
        g.appendChild(bgBase);
        if (this.snapToGrid) {
            const bgGrid = this.createSVG('rect', { x: 0, y: 0, width: this.canvasWidth, height: this.canvasHeight, fill: 'url(#fpgrid)', rx: 4, opacity: 0.45, class: 'canvas-bg' });
            g.appendChild(bgGrid);
        }

        // Background image
        if (this.backgroundRenderUrl) {
            const img = this.createSVG('image', { href: this.backgroundRenderUrl, x: 0, y: 0, width: this.canvasWidth, height: this.canvasHeight, preserveAspectRatio: 'xMidYMid meet', opacity: 0.75 });
            img.style.pointerEvents = 'none';
            g.appendChild(img);
        }

        if (!this.backgroundRenderUrl && !this.racks.length && !this.planDevices.length && !this.cables.length && !this.annotations.length) {
            const hint = this.createSVG('text', { x: this.canvasWidth / 2, y: this.canvasHeight / 2, 'text-anchor': 'middle', fill: '#64748b', 'font-size': 18, 'font-weight': 600 });
            hint.textContent = 'Canvas is ready. Add rack/device or upload a floor plan image.';
            g.appendChild(hint);
        }

        // Zone annotations (back layer)
        this.annotations.filter(a => a.type === 'zone').forEach(a => { try { this.renderAnnotation(a); } catch (e) { console.warn('Skipping bad zone annotation', a, e); } });

        // Cables
        this.cables.forEach(cable => { try { this.renderCable(cable); } catch (e) { console.warn('Skipping bad cable', cable, e); } });

        // Racks
        this.racks.forEach(rack => { try { this.renderRack(rack); } catch (e) { console.warn('Skipping bad rack', rack, e); } });

        // Devices
        this.planDevices.forEach(dev => { try { this.renderDevice(dev); } catch (e) { console.warn('Skipping bad device', dev, e); } });

        // Label annotations (front)
        this.annotations.filter(a => a.type === 'label').forEach(a => { try { this.renderAnnotation(a); } catch (e) { console.warn('Skipping bad label annotation', a, e); } });

        this.updateTransform();
    },

    renderRack(rack) {
        const sel = this.selectedItem?.kind === 'rack' && this.selectedItem?.id == rack.id;
        const w = 60;
        const rackUnits = Math.max(1, this.toNumber(rack.rack_units, 42));
        const h = Math.max(40, rackUnits * 1.2);
        const rg = this.createSVG('g', { transform: `translate(${this.toNumber(rack.x, 100)}, ${this.toNumber(rack.y, 100)})`, 'data-kind': 'rack', 'data-id': rack.id, style: 'cursor:pointer' });
        rg.innerHTML = `
            <rect x="${-w / 2}" y="${-h / 2}" width="${w}" height="${h}" rx="3" fill="#1e293b" stroke="${sel ? '#06b6d4' : '#475569'}" stroke-width="${sel ? 2.5 : 1.5}"/>
            ${Array.from({ length: Math.min(rackUnits, 30) }, (_, i) => {
                const uy = -h / 2 + 4 + i * ((h - 8) / Math.min(rackUnits, 30));
                return `<line x1="${-w / 2 + 4}" y1="${uy}" x2="${w / 2 - 4}" y2="${uy}" stroke="#334155" stroke-width="0.5"/>`;
            }).join('')}
            ${rack.label_visible !== false && rack.label_visible != 0 ? `<text x="0" y="${h / 2 + 14}" text-anchor="middle" fill="#e2e8f0" font-size="10" font-weight="500">${this.esc(rack.name)}</text>` : ''}
            <text x="0" y="4" text-anchor="middle" fill="#94a3b8" font-size="8">${rackUnits}U</text>
            ${sel ? `<rect x="${-w / 2 - 3}" y="${-h / 2 - 3}" width="${w + 6}" height="${h + 6}" rx="5" fill="none" stroke="#06b6d4" stroke-width="1" stroke-dasharray="4 2"/>` : ''}
        `;
        this.g.appendChild(rg);
    },

    renderDevice(dev) {
        const sel = this.selectedItem?.kind === 'device' && this.selectedItem?.id == dev.id;
        const icons = { server: '🖥', switch: '🔀', router: '🌐', firewall: '🛡', printer: '🖨', camera: '📷', phone: '📞', ap: '📡', workstation: '💻' };
        const icon = icons[dev.type] || '📦';
        const statusColor = { online: '#22c55e', warning: '#f59e0b', critical: '#ef4444', offline: '#64748b' };
        const color = statusColor[dev.status] || '#94a3b8';
        const dx = this.toNumber(dev.x, 200);
        const dy = this.toNumber(dev.y, 200);
        const dg = this.createSVG('g', { transform: `translate(${dx}, ${dy})`, 'data-kind': 'device', 'data-id': dev.id, style: 'cursor:pointer' });
        dg.innerHTML = `
            <circle r="20" fill="#1e293b" stroke="${color}" stroke-width="${sel ? 2.5 : 1.5}"/>
            <text x="0" y="5" text-anchor="middle" font-size="16">${icon}</text>
            <text x="0" y="34" text-anchor="middle" fill="#e2e8f0" font-size="10" font-weight="500">${this.esc(dev.name)}</text>
            ${dev.ip ? `<text x="0" y="44" text-anchor="middle" fill="#64748b" font-size="8">${this.esc(dev.ip)}</text>` : ''}
            ${sel ? `<circle r="24" fill="none" stroke="#06b6d4" stroke-width="1" stroke-dasharray="4 2"/>` : ''}
            <circle r="5" cx="16" cy="-14" fill="${color}" opacity="0.9"/>
            ${dev.legacy_map_position ? `<text x="0" y="56" text-anchor="middle" fill="#38bdf8" font-size="8">legacy map pos</text>` : ''}
        `;
        this.g.appendChild(dg);
    },

    renderCable(cable) {
        const sel = this.selectedItem?.kind === 'cable' && this.selectedItem?.id == cable.id;
        const srcPos = this.findEndpointPos(cable.source_type, cable.source_id);
        const dstPos = this.findEndpointPos(cable.dest_type, cable.dest_id);
        if (!srcPos || !dstPos || !isFinite(srcPos.x) || !isFinite(srcPos.y) || !isFinite(dstPos.x) || !isFinite(dstPos.y)) return;
        const color = this.CABLE_COLOR_MAP[cable.cable_color] || '#64748b';
        const sourcePort = cable.source_port || 1;
        const destPort = cable.dest_port || 1;
        const lengthText = cable.cable_length ? ` • ${cable.cable_length}` : '';
        const mark = `P${sourcePort}→P${destPort}${lengthText}`;
        const cg = this.createSVG('g', { 'data-kind': 'cable', 'data-id': cable.id, style: 'cursor:pointer' });
        // Invisible wider hit area
        cg.innerHTML = `
            <line x1="${srcPos.x}" y1="${srcPos.y}" x2="${dstPos.x}" y2="${dstPos.y}" stroke="transparent" stroke-width="12"/>
            <line x1="${srcPos.x}" y1="${srcPos.y}" x2="${dstPos.x}" y2="${dstPos.y}" stroke="${color}" stroke-width="${sel ? 3 : 2}" ${cable.cable_type?.includes('fiber') ? 'stroke-dasharray="8 4"' : ''} opacity="${sel ? 1 : 0.7}"/>
            <circle cx="${srcPos.x}" cy="${srcPos.y}" r="3" fill="${color}"/>
            <circle cx="${dstPos.x}" cy="${dstPos.y}" r="3" fill="${color}"/>
            ${cable.label ? `<text x="${(srcPos.x + dstPos.x) / 2}" y="${(srcPos.y + dstPos.y) / 2 - 10}" text-anchor="middle" fill="${color}" font-size="9">${this.esc(cable.label)}</text>` : ''}
            <text x="${(srcPos.x + dstPos.x) / 2}" y="${(srcPos.y + dstPos.y) / 2 + 2}" text-anchor="middle" fill="#94a3b8" font-size="8">${this.esc(mark)}</text>
            ${sel ? `<circle cx="${srcPos.x}" cy="${srcPos.y}" r="6" fill="none" stroke="#06b6d4" stroke-width="1.5"/><circle cx="${dstPos.x}" cy="${dstPos.y}" r="6" fill="none" stroke="#06b6d4" stroke-width="1.5"/>` : ''}
        `;
        this.g.appendChild(cg);
    },

    renderAnnotation(a) {
        const sel = this.selectedItem?.kind === 'annotation' && this.selectedItem?.id == a.id;
        const ax = this.toNumber(a.x, 0);
        const ay = this.toNumber(a.y, 0);
        const ag = this.createSVG('g', { transform: `translate(${ax}, ${ay})`, 'data-kind': 'annotation', 'data-id': a.id, style: 'cursor:pointer' });
        if (a.type === 'zone') {
            const w = a.width || 200, h = a.height || 150;
            ag.innerHTML = `
                <rect x="0" y="0" width="${w}" height="${h}" rx="6" fill="${a.color || '#94a3b8'}22" stroke="${a.color || '#94a3b8'}" stroke-width="${sel ? 2 : 1}" stroke-dasharray="6 3"/>
                <text x="8" y="${(a.font_size || 14) + 4}" fill="${a.color || '#94a3b8'}" font-size="${a.font_size || 14}" font-weight="600">${this.esc(a.text)}</text>
                ${sel ? `<rect x="-2" y="-2" width="${w + 4}" height="${h + 4}" rx="8" fill="none" stroke="#06b6d4" stroke-width="1" stroke-dasharray="4 2"/>` : ''}
            `;
        } else {
            ag.innerHTML = `
                <text x="0" y="0" fill="${a.color || '#94a3b8'}" font-size="${a.font_size || 14}" font-weight="500">${this.esc(a.text)}</text>
                ${sel ? `<rect x="-4" y="${-(a.font_size || 14)}" width="${(a.text?.length || 5) * (a.font_size || 14) * 0.6 + 8}" height="${(a.font_size || 14) + 8}" rx="3" fill="none" stroke="#06b6d4" stroke-width="1" stroke-dasharray="4 2"/>` : ''}
            `;
        }
        this.g.appendChild(ag);
    },

    findEndpointPos(type, id) {
        if (type === 'rack' || type === 'patch_panel') {
            const rack = this.racks.find(r => r.id == id);
            if (rack) return { x: parseFloat(rack.x), y: parseFloat(rack.y) };
        }
        if (type === 'switch' || type === 'device') {
            const dev = this.planDevices.find(d => d.device_id == id || d.id == id);
            if (dev) return { x: parseFloat(dev.x), y: parseFloat(dev.y) };
        }
        return null;
    },

    createSVG(tag, attrs) {
        const el = document.createElementNS('http://www.w3.org/2000/svg', tag);
        Object.entries(attrs || {}).forEach(([k, v]) => el.setAttribute(k, v));
        return el;
    },

    esc(str) { return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); },

    // ==================== EXPORT ====================
    exportSVG() {
        const clone = this.svg.cloneNode(true);
        // Reset transform for export
        clone.querySelector('g').setAttribute('transform', 'scale(1)');
        clone.setAttribute('width', this.canvasWidth);
        clone.setAttribute('height', this.canvasHeight);
        clone.setAttribute('viewBox', `0 0 ${this.canvasWidth} ${this.canvasHeight}`);
        // Remove zoom label
        const zl = clone.querySelector('#fp-zoom-label');
        if (zl) zl.remove();
        const svgData = new XMLSerializer().serializeToString(clone);
        const blob = new Blob([svgData], { type: 'image/svg+xml' });
        this.downloadBlob(blob, 'floor-plan.svg');
    },

    exportPNG() {
        this.renderToCanvas(2)
            .then((canvas) => canvas.toBlob(blob => this.downloadBlob(blob, 'floor-plan.png'), 'image/png'))
            .catch((error) => {
                console.error('PNG export failed:', error);
                if (typeof notyf !== 'undefined') notyf.error('PNG export failed.');
            });
    },

    exportPDF() {
        this.renderToCanvas(2)
            .then((canvas) => {
                if (!window.jspdf?.jsPDF) throw new Error('jsPDF not loaded');
                const { jsPDF } = window.jspdf;
                const orientation = this.canvasWidth >= this.canvasHeight ? 'landscape' : 'portrait';
                const pdf = new jsPDF({ orientation, unit: 'pt', format: [this.canvasWidth, this.canvasHeight] });
                const imgData = canvas.toDataURL('image/png');
                pdf.addImage(imgData, 'PNG', 0, 0, this.canvasWidth, this.canvasHeight, undefined, 'FAST');
                pdf.save('floor-plan.pdf');
            })
            .catch((error) => {
                console.error('PDF export failed:', error);
                if (typeof notyf !== 'undefined') notyf.error('PDF export failed.');
            });
    },

    renderToCanvas(scale = 2) {
        return new Promise((resolve, reject) => {
            const clone = this.svg.cloneNode(true);
            clone.querySelector('g').setAttribute('transform', 'scale(1)');
            clone.setAttribute('width', this.canvasWidth);
            clone.setAttribute('height', this.canvasHeight);
            clone.setAttribute('viewBox', `0 0 ${this.canvasWidth} ${this.canvasHeight}`);
            const zl = clone.querySelector('#fp-zoom-label');
            if (zl) zl.remove();
            const svgData = new XMLSerializer().serializeToString(clone);
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = this.canvasWidth * scale;
                canvas.height = this.canvasHeight * scale;
                const ctx = canvas.getContext('2d');
                ctx.scale(scale, scale);
                ctx.fillStyle = '#0f172a';
                ctx.fillRect(0, 0, this.canvasWidth, this.canvasHeight);
                ctx.drawImage(img, 0, 0, this.canvasWidth, this.canvasHeight);
                resolve(canvas);
            };
            img.onerror = reject;
            img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgData);
        });
    },

    downloadBlob(blob, filename) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
    },

    fitToView() {
        if (!this.svg) return;
        const rect = this.svg.getBoundingClientRect();
        if (!rect.width || !rect.height || !this.canvasWidth || !this.canvasHeight) return;
        this.zoom = Math.min(rect.width / this.canvasWidth, rect.height / this.canvasHeight) * 0.9;
        this.panX = (rect.width - this.canvasWidth * this.zoom) / 2;
        this.panY = (rect.height - this.canvasHeight * this.zoom) / 2;
        const zoomLabel = document.getElementById('fp-zoom-label');
        if (zoomLabel) zoomLabel.textContent = Math.round(this.zoom * 100) + '%';
        this.updateTransform();
    }
};
