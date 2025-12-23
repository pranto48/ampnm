/**
 * Network Map Icon Integration
 * Handles displaying selected icons on the network topology map
 */

(function() {
    'use strict';

    const NetworkMapIcons = {
        devices: new Map(), // Store device instances with their icons

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Listen for icon selection events from IconPicker
            document.addEventListener('iconSelected', (e) => {
                this.handleIconSelection(e.detail);
            });

            // Alternative: use the window callback
            window.onIconSelected = (iconData) => {
                this.handleIconSelection(iconData);
            };
        },

        handleIconSelection: function(iconData) {
            console.log('Icon selected for network map:', iconData);
            
            // If there's an active device being edited, update its icon
            const activeDevice = this.getActiveDevice();
            if (activeDevice) {
                this.updateDeviceIcon(activeDevice.id, iconData);
            }

            // Dispatch event for any map implementations to handle
            const event = new CustomEvent('networkMapIconUpdate', {
                detail: {
                    iconId: iconData.id,
                    iconClass: iconData.icon,
                    deviceType: iconData.type
                }
            });
            document.dispatchEvent(event);
        },

        // Register a device on the network map
        registerDevice: function(deviceId, config) {
            this.devices.set(deviceId, {
                id: deviceId,
                name: config.name || 'Unnamed Device',
                iconId: config.iconId || 'device-unknown',
                iconClass: config.iconClass || 'fa-question-circle',
                position: config.position || { x: 0, y: 0 },
                status: config.status || 'unknown',
                metadata: config.metadata || {}
            });

            this.renderDevice(deviceId);
            return this.devices.get(deviceId);
        },

        // Update device icon
        updateDeviceIcon: function(deviceId, iconData) {
            const device = this.devices.get(deviceId);
            if (device) {
                device.iconId = iconData.id;
                device.iconClass = iconData.icon;
                device.type = iconData.type;
                this.renderDevice(deviceId);
            }
        },

        // Render a device on the map
        renderDevice: function(deviceId) {
            const device = this.devices.get(deviceId);
            if (!device) return;

            const mapContainer = document.getElementById('networkMapContainer');
            if (!mapContainer) return;

            // Find or create device element
            let deviceEl = document.getElementById(`device-${deviceId}`);
            if (!deviceEl) {
                deviceEl = document.createElement('div');
                deviceEl.id = `device-${deviceId}`;
                deviceEl.className = 'network-map-device';
                mapContainer.appendChild(deviceEl);
            }

            // Update device element
            deviceEl.innerHTML = `
                <div class="device-icon-wrapper status-${device.status}">
                    <i class="fas ${device.iconClass}"></i>
                </div>
                <div class="device-label">${device.name}</div>
            `;

            // Position device
            if (device.position) {
                deviceEl.style.left = device.position.x + 'px';
                deviceEl.style.top = device.position.y + 'px';
            }

            // Make draggable if needed
            this.makeDeviceDraggable(deviceEl, deviceId);
        },

        // Make device draggable on the map
        makeDeviceDraggable: function(element, deviceId) {
            let isDragging = false;
            let startX, startY, initialLeft, initialTop;

            element.addEventListener('mousedown', (e) => {
                if (e.target.closest('.device-icon-wrapper')) {
                    isDragging = true;
                    element.classList.add('dragging');
                    startX = e.clientX;
                    startY = e.clientY;
                    initialLeft = element.offsetLeft;
                    initialTop = element.offsetTop;
                    e.preventDefault();
                }
            });

            document.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                
                const dx = e.clientX - startX;
                const dy = e.clientY - startY;
                
                element.style.left = (initialLeft + dx) + 'px';
                element.style.top = (initialTop + dy) + 'px';
            });

            document.addEventListener('mouseup', () => {
                if (isDragging) {
                    isDragging = false;
                    element.classList.remove('dragging');
                    
                    // Update device position in data
                    const device = this.devices.get(deviceId);
                    if (device) {
                        device.position = {
                            x: element.offsetLeft,
                            y: element.offsetTop
                        };
                    }
                }
            });
        },

        // Get the currently active/selected device
        getActiveDevice: function() {
            const activeEl = document.querySelector('.network-map-device.active');
            if (activeEl) {
                const deviceId = activeEl.id.replace('device-', '');
                return this.devices.get(deviceId);
            }
            return null;
        },

        // Remove device from map
        removeDevice: function(deviceId) {
            const deviceEl = document.getElementById(`device-${deviceId}`);
            if (deviceEl) {
                deviceEl.remove();
            }
            this.devices.delete(deviceId);
        },

        // Get all devices
        getAllDevices: function() {
            return Array.from(this.devices.values());
        },

        // Export map configuration
        exportMapConfig: function() {
            return {
                devices: this.getAllDevices(),
                timestamp: new Date().toISOString()
            };
        },

        // Import map configuration
        importMapConfig: function(config) {
            if (config.devices && Array.isArray(config.devices)) {
                config.devices.forEach(device => {
                    this.registerDevice(device.id, device);
                });
            }
        },

        // Create icon element for use anywhere
        createIconElement: function(iconClass, options = {}) {
            const wrapper = document.createElement('div');
            wrapper.className = `device-icon-display ${options.size || 'medium'} ${options.status || ''}`;
            wrapper.innerHTML = `<i class="fas ${iconClass}"></i>`;
            return wrapper;
        }
    };

    // CSS for network map devices (can be moved to CSS file)
    const styles = `
        .network-map-device {
            position: absolute;
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: grab;
            user-select: none;
            transition: transform 0.1s ease;
        }

        .network-map-device:hover {
            z-index: 100;
        }

        .network-map-device.dragging {
            cursor: grabbing;
            z-index: 1000;
        }

        .network-map-device.active .device-icon-wrapper {
            border-color: #64ffda;
            box-shadow: 0 0 30px rgba(100, 255, 218, 0.4);
        }

        .device-icon-wrapper {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(30, 41, 59, 0.9);
            border: 2px solid rgba(100, 255, 218, 0.3);
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .device-icon-wrapper i {
            font-size: 24px;
            color: rgba(226, 232, 240, 0.8);
        }

        .device-icon-wrapper.status-online {
            border-color: #00ff88;
            box-shadow: 0 0 15px rgba(0, 255, 136, 0.3);
        }

        .device-icon-wrapper.status-online i {
            color: #00ff88;
        }

        .device-icon-wrapper.status-offline {
            border-color: #ff4444;
            box-shadow: 0 0 15px rgba(255, 68, 68, 0.3);
        }

        .device-icon-wrapper.status-offline i {
            color: #ff4444;
        }

        .device-icon-wrapper.status-warning {
            border-color: #ffbb00;
            box-shadow: 0 0 15px rgba(255, 187, 0, 0.3);
        }

        .device-icon-wrapper.status-warning i {
            color: #ffbb00;
        }

        .device-label {
            margin-top: 6px;
            font-size: 11px;
            color: rgba(226, 232, 240, 0.7);
            text-align: center;
            max-width: 80px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .network-map-device:hover .device-label {
            color: #e2e8f0;
        }

        /* Icon display sizes */
        .device-icon-display.small {
            width: 32px;
            height: 32px;
        }

        .device-icon-display.small i {
            font-size: 16px;
        }

        .device-icon-display.medium {
            width: 48px;
            height: 48px;
        }

        .device-icon-display.medium i {
            font-size: 24px;
        }

        .device-icon-display.large {
            width: 64px;
            height: 64px;
        }

        .device-icon-display.large i {
            font-size: 32px;
        }
    `;

    // Inject styles
    const styleSheet = document.createElement('style');
    styleSheet.textContent = styles;
    document.head.appendChild(styleSheet);

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => NetworkMapIcons.init());
    } else {
        NetworkMapIcons.init();
    }

    // Expose to global scope
    window.NetworkMapIcons = NetworkMapIcons;
})();
