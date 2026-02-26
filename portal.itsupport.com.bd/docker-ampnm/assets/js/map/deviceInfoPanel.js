/**
 * Device Info Panel Controller
 * Manages the sliding panel that shows device details and metrics on the map
 */

window.MapApp = window.MapApp || {};

MapApp.deviceInfoPanel = {
    panel: null,
    currentDeviceId: null,
    currentDeviceIp: null,
    
    init() {
        this.panel = document.getElementById('deviceInfoPanel');
        if (!this.panel) return;
        
        // Close button
        document.getElementById('closeDeviceInfoPanel')?.addEventListener('click', () => this.hide());
        
        // Edit button
        document.getElementById('deviceInfoEditBtn')?.addEventListener('click', () => {
            if (this.currentDeviceId) {
                window.location.href = `edit-device.php?id=${this.currentDeviceId}&return=map`;
            }
        });
        
        // Ping button
        document.getElementById('deviceInfoPingBtn')?.addEventListener('click', () => {
            if (this.currentDeviceId && MapApp.deviceManager) {
                const btn = document.getElementById('deviceInfoPingBtn');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Checking...';
                btn.disabled = true;
                
                MapApp.deviceManager.pingSingleDevice(this.currentDeviceId).finally(() => {
                    btn.innerHTML = '<i class="fas fa-sync mr-1"></i>Check';
                    btn.disabled = false;
                    // Refresh panel data
                    this.refreshCurrentDevice();
                });
            }
        });
        
        // Close on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !this.panel.classList.contains('hidden')) {
                this.hide();
            }
        });
    },
    
    show(deviceId) {
        if (!this.panel) return;
        
        const node = MapApp.state?.nodes?.get(deviceId);
        if (!node || !node.deviceData) return;
        
        const device = node.deviceData;
        this.currentDeviceId = deviceId;
        this.currentDeviceIp = device.ip;
        
        // Populate basic info
        document.getElementById('deviceInfoName').textContent = device.name || 'Unknown Device';
        document.getElementById('deviceInfoIp').textContent = device.ip || 'No IP';
        document.getElementById('deviceInfoType').textContent = this.formatType(device.type);
        
        // Status
        const statusEl = document.getElementById('deviceInfoStatus');
        const status = device.status || 'unknown';
        statusEl.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        statusEl.className = `value status-${status}`;
        
        // Latency
        const latencyEl = document.getElementById('deviceInfoLatency');
        if (device.last_avg_time !== null && device.last_avg_time !== undefined) {
            latencyEl.textContent = `${device.last_avg_time}ms`;
        } else {
            latencyEl.textContent = '--';
        }
        
        // Last seen
        const lastSeenEl = document.getElementById('deviceInfoLastSeen');
        if (device.last_seen) {
            const date = new Date(device.last_seen);
            lastSeenEl.textContent = date.toLocaleString();
        } else {
            lastSeenEl.textContent = 'Never';
        }
        
        // Description
        const descEl = document.getElementById('deviceInfoDescription');
        if (device.description) {
            descEl.textContent = device.description;
            descEl.classList.remove('hidden');
        } else {
            descEl.classList.add('hidden');
        }
        
        // Show panel
        this.panel.classList.remove('hidden');
        
        // Load host metrics if device has IP
        if (device.ip && typeof DeviceMetricsPanel !== 'undefined') {
            const metricsContainer = document.getElementById('deviceMetricsContainer');
            DeviceMetricsPanel.show(deviceId, device.ip, metricsContainer);
        }
    },
    
    hide() {
        if (this.panel) {
            this.panel.classList.add('hidden');
        }
        
        if (typeof DeviceMetricsPanel !== 'undefined') {
            DeviceMetricsPanel.hide();
        }
        
        this.currentDeviceId = null;
        this.currentDeviceIp = null;
    },
    
    refreshCurrentDevice() {
        if (this.currentDeviceId) {
            this.show(this.currentDeviceId);
        }
    },
    
    formatType(type) {
        if (!type) return 'Unknown';
        return type.split(/[-_]/).map(word => 
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    MapApp.deviceInfoPanel.init();
});
