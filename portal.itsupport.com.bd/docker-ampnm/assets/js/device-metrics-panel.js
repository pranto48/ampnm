/**
 * Device Metrics Panel - Shows Windows agent metrics in device popup
 */

const DeviceMetricsPanel = {
    container: null,
    currentDeviceId: null,
    currentDeviceIp: null,
    refreshInterval: null,
    
    /**
     * Initialize the metrics panel
     */
    init() {
        // Create the metrics container if it doesn't exist
        this.createContainer();
    },
    
    /**
     * Create the metrics HTML container
     */
    createContainer() {
        if (document.getElementById('device-metrics-panel')) return;
        
        const container = document.createElement('div');
        container.id = 'device-metrics-panel';
        container.className = 'device-metrics-panel hidden';
        container.innerHTML = `
            <div class="metrics-header">
                <h4><i class="fas fa-microchip"></i> Host Metrics</h4>
                <span class="metrics-status" id="metrics-status">Loading...</span>
            </div>
            <div class="metrics-grid">
                <div class="metric-item">
                    <div class="metric-label"><i class="fas fa-microchip text-cyan-400"></i> CPU</div>
                    <div class="metric-bar">
                        <div class="metric-bar-fill" id="metric-cpu-bar" style="width: 0%"></div>
                    </div>
                    <div class="metric-value" id="metric-cpu">--</div>
                </div>
                <div class="metric-item">
                    <div class="metric-label"><i class="fas fa-memory text-purple-400"></i> Memory</div>
                    <div class="metric-bar">
                        <div class="metric-bar-fill memory" id="metric-memory-bar" style="width: 0%"></div>
                    </div>
                    <div class="metric-value" id="metric-memory">--</div>
                </div>
                <div class="metric-item">
                    <div class="metric-label"><i class="fas fa-hdd text-green-400"></i> Disk</div>
                    <div class="metric-bar">
                        <div class="metric-bar-fill disk" id="metric-disk-bar" style="width: 0%"></div>
                    </div>
                    <div class="metric-value" id="metric-disk">--</div>
                </div>
                <div class="metric-item">
                    <div class="metric-label"><i class="fas fa-network-wired text-orange-400"></i> Network</div>
                    <div class="metric-value" id="metric-network">--</div>
                </div>
                <div class="metric-item">
                    <div class="metric-label"><i class="fas fa-tv text-yellow-400"></i> GPU</div>
                    <div class="metric-bar">
                        <div class="metric-bar-fill gpu" id="metric-gpu-bar" style="width: 0%"></div>
                    </div>
                    <div class="metric-value" id="metric-gpu">--</div>
                </div>
            </div>
            <div class="metrics-footer">
                <span class="metrics-updated" id="metrics-updated">Never</span>
                <a href="host_metrics.php" class="metrics-link">View History <i class="fas fa-arrow-right"></i></a>
            </div>
        `;
        
        this.container = container;
    },
    
    /**
     * Show metrics for a specific device
     */
    async show(deviceId, deviceIp, appendToElement) {
        this.currentDeviceId = deviceId;
        this.currentDeviceIp = deviceIp;
        
        // Append to target element
        if (appendToElement && this.container) {
            appendToElement.appendChild(this.container);
            this.container.classList.remove('hidden');
        }
        
        // Load metrics immediately
        await this.loadMetrics();
        
        // Start auto-refresh
        this.startAutoRefresh();
    },
    
    /**
     * Hide and cleanup
     */
    hide() {
        if (this.container) {
            this.container.classList.add('hidden');
        }
        this.stopAutoRefresh();
        this.currentDeviceId = null;
        this.currentDeviceIp = null;
    },
    
    /**
     * Load metrics from API
     */
    async loadMetrics() {
        if (!this.currentDeviceIp && !this.currentDeviceId) return;
        
        try {
            let url = 'api.php?action=get_latest_metrics';
            if (this.currentDeviceId) {
                url += `&device_id=${this.currentDeviceId}`;
            } else if (this.currentDeviceIp) {
                url += `&host_ip=${this.currentDeviceIp}`;
            }
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.error) {
                this.showNoData();
                return;
            }
            
            this.updateDisplay(data);
            
        } catch (error) {
            console.error('Failed to load device metrics:', error);
            this.showNoData();
        }
    },
    
    /**
     * Update the display with metric data
     */
    updateDisplay(data) {
        const statusEl = document.getElementById('metrics-status');
        const isRecent = data.created_at && (Date.now() - new Date(data.created_at).getTime()) < 300000;
        
        if (isRecent) {
            statusEl.textContent = 'Live';
            statusEl.className = 'metrics-status online';
        } else {
            statusEl.textContent = 'Stale';
            statusEl.className = 'metrics-status offline';
        }
        
        // CPU
        this.updateMetric('cpu', data.cpu_percent, '%');
        
        // Memory
        this.updateMetric('memory', data.memory_percent, '%');
        
        // Disk
        if (data.disk_percent !== null) {
            this.updateMetric('disk', data.disk_percent, '%');
        } else if (data.disk_total_gb && data.disk_free_gb) {
            const diskUsed = data.disk_total_gb - data.disk_free_gb;
            const diskPercent = (diskUsed / data.disk_total_gb) * 100;
            this.updateMetric('disk', diskPercent.toFixed(1), '%');
        } else {
            document.getElementById('metric-disk').textContent = '--';
            document.getElementById('metric-disk-bar').style.width = '0%';
        }
        
        // Network
        if (data.network_in_mbps !== null || data.network_out_mbps !== null) {
            const inMbps = data.network_in_mbps || 0;
            const outMbps = data.network_out_mbps || 0;
            document.getElementById('metric-network').innerHTML = 
                `<span class="text-green-400">↓${inMbps.toFixed(1)}</span> / <span class="text-orange-400">↑${outMbps.toFixed(1)}</span> Mbps`;
        } else {
            document.getElementById('metric-network').textContent = '--';
        }
        
        // GPU
        this.updateMetric('gpu', data.gpu_percent, '%');
        
        // Updated time
        if (data.created_at) {
            const date = new Date(data.created_at);
            document.getElementById('metrics-updated').textContent = `Updated: ${date.toLocaleTimeString()}`;
        }
    },
    
    /**
     * Update a single metric display
     */
    updateMetric(name, value, suffix = '') {
        const valueEl = document.getElementById(`metric-${name}`);
        const barEl = document.getElementById(`metric-${name}-bar`);
        
        if (value !== null && value !== undefined) {
            valueEl.textContent = value + suffix;
            if (barEl) {
                barEl.style.width = Math.min(100, Math.max(0, value)) + '%';
                
                // Color based on value
                barEl.classList.remove('warning', 'critical');
                if (value > 90) {
                    barEl.classList.add('critical');
                } else if (value > 75) {
                    barEl.classList.add('warning');
                }
            }
        } else {
            valueEl.textContent = '--';
            if (barEl) barEl.style.width = '0%';
        }
    },
    
    /**
     * Show "No data" state
     */
    showNoData() {
        const statusEl = document.getElementById('metrics-status');
        statusEl.textContent = 'No Agent';
        statusEl.className = 'metrics-status no-data';
        
        ['cpu', 'memory', 'disk', 'gpu'].forEach(name => {
            document.getElementById(`metric-${name}`).textContent = '--';
            const bar = document.getElementById(`metric-${name}-bar`);
            if (bar) bar.style.width = '0%';
        });
        document.getElementById('metric-network').textContent = '--';
        document.getElementById('metrics-updated').textContent = 'No agent installed';
    },
    
    /**
     * Start auto-refresh
     */
    startAutoRefresh() {
        this.stopAutoRefresh();
        this.refreshInterval = setInterval(() => this.loadMetrics(), 30000);
    },
    
    /**
     * Stop auto-refresh
     */
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }
};

// Initialize on load
document.addEventListener('DOMContentLoaded', () => DeviceMetricsPanel.init());
