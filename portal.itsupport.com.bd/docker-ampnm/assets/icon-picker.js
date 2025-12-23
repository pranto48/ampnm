/**
 * Enhanced Icon Picker for Device Management
 * Provides interactive icon selection with categories, search, and preview
 * Selected icons are displayed on the network map
 */

(function() {
    'use strict';

    const IconPicker = {
        config: {
            containerSelector: '#iconPickerContainer',
            typeSelectSelector: '#type',
            tabSelector: '[data-icon-category]',
            buttonSelector: '.icon-gallery-btn',
            searchSelector: '.icon-picker-search input',
            previewSelector: '#selectedIconPreview',
            hiddenInputSelector: '#selectedIconId'
        },

        selectedIcon: null,

        init: function() {
            if (!this.validateDom()) return;
            this.cacheElements();
            this.bindEvents();
            this.render();
        },

        validateDom: function() {
            return document.querySelector(this.config.containerSelector) !== null;
        },

        cacheElements: function() {
            this.typeSelect = document.querySelector(this.config.typeSelectSelector);
            this.container = document.querySelector(this.config.containerSelector);
            this.preview = document.querySelector(this.config.previewSelector);
            this.hiddenInput = document.querySelector(this.config.hiddenInputSelector);
        },

        bindEvents: function() {
            const self = this;
            
            // Type select change
            if (this.typeSelect) {
                this.typeSelect.addEventListener('change', () => self.updateCategory());
            }

            // Delegate event for category tabs
            document.addEventListener('click', (e) => {
                if (e.target.closest('[data-icon-category]')) {
                    const btn = e.target.closest('[data-icon-category]');
                    self.switchCategory(btn.dataset.iconCategory);
                }
            });

            // Delegate event for device type tabs
            document.addEventListener('click', (e) => {
                if (e.target.closest('[data-device-type]')) {
                    const btn = e.target.closest('[data-device-type]');
                    self.switchDeviceType(btn.dataset.deviceType);
                }
            });

            // Delegate event for icon buttons
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('.icon-gallery-btn[data-icon-id]');
                if (btn) {
                    e.preventDefault();
                    self.selectIcon(btn.dataset.iconId, btn.dataset.deviceType, btn.dataset.iconClass);
                }
            });

            // Search functionality
            document.addEventListener('input', (e) => {
                if (e.target.matches('.icon-search-input')) {
                    self.filterIcons(e.target.value);
                }
            });
        },

        render: function() {
            if (!window.deviceIconsLibrary) {
                console.error('Device icons library not loaded');
                return;
            }
            this.renderCategoryTabs();
            this.renderDeviceTypeTabs('network'); // Default to network category
            this.renderPicker('switch'); // Default to switch type
        },

        renderCategoryTabs: function() {
            const categories = {
                'network': { label: 'Network', icon: 'fa-network-wired' },
                'compute': { label: 'Compute', icon: 'fa-server' },
                'security': { label: 'Security', icon: 'fa-shield-halved' },
                'storage': { label: 'Storage', icon: 'fa-database' },
                'endpoint': { label: 'Endpoints', icon: 'fa-desktop' },
                'peripheral': { label: 'Peripherals', icon: 'fa-print' },
                'iot': { label: 'IoT', icon: 'fa-microchip' },
                'infrastructure': { label: 'Infrastructure', icon: 'fa-plug' },
                'cloud': { label: 'Cloud', icon: 'fa-cloud' },
                'service': { label: 'Services', icon: 'fa-cogs' },
                'other': { label: 'Other', icon: 'fa-ellipsis' }
            };

            let html = '<div class="icon-category-tabs">';
            Object.entries(categories).forEach(([key, cat], idx) => {
                html += `
                    <button type="button" class="category-tab ${idx === 0 ? 'active' : ''}" 
                            data-icon-category="${key}">
                        <i class="fas ${cat.icon}"></i>
                        <span>${cat.label}</span>
                    </button>
                `;
            });
            html += '</div>';

            // Insert category tabs before the container content
            const tabsContainer = document.getElementById('categoryTabsContainer');
            if (tabsContainer) {
                tabsContainer.innerHTML = html;
            }
        },

        renderDeviceTypeTabs: function(category) {
            const deviceTypes = window.getIconsByCategory ? window.getIconsByCategory(category) : {};
            
            let html = '<div class="device-type-tabs">';
            let isFirst = true;
            Object.entries(deviceTypes).forEach(([type, data]) => {
                html += `
                    <button type="button" class="device-type-tab ${isFirst ? 'active' : ''}" 
                            data-device-type="${type}">
                        ${data.label}
                    </button>
                `;
                isFirst = false;
            });
            html += '</div>';

            const typesContainer = document.getElementById('deviceTypeTabsContainer');
            if (typesContainer) {
                typesContainer.innerHTML = html;
            }

            // Render first device type's icons
            const firstType = Object.keys(deviceTypes)[0];
            if (firstType) {
                this.renderPicker(firstType);
            }
        },

        renderPicker: function(deviceType) {
            if (!this.container || !window.deviceIconsLibrary || !window.deviceIconsLibrary[deviceType]) {
                return;
            }

            const typeData = window.deviceIconsLibrary[deviceType];
            const iconVariants = typeData.icons || [];

            let html = `
                <div class="icon-picker-header">
                    <span class="icon-picker-title">
                        <i class="fas ${iconVariants[0]?.icon || 'fa-cube'}"></i>
                        ${typeData.label}
                    </span>
                    <span class="icon-picker-stats">${iconVariants.length} icon${iconVariants.length !== 1 ? 's' : ''}</span>
                </div>
            `;

            // Add search box
            html += `
                <div class="icon-picker-search">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search ${typeData.label.toLowerCase()}..." class="icon-search-input">
                </div>
            `;

            // Render icon gallery
            html += '<div class="icon-gallery" id="iconGallery">';
            iconVariants.forEach((variant) => {
                const isSelected = this.selectedIcon && this.selectedIcon.id === variant.id;
                html += `
                    <button type="button" class="icon-gallery-btn ${isSelected ? 'selected' : ''}" 
                            data-icon-id="${variant.id}" 
                            data-device-type="${deviceType}"
                            data-icon-class="${variant.icon}"
                            title="${variant.label}">
                        <div class="icon-gallery-btn-content">
                            <i class="fas ${variant.icon}"></i>
                            <span>${variant.label}</span>
                        </div>
                    </button>
                `;
            });
            html += '</div>';

            this.container.innerHTML = html;
        },

        updateCategory: function() {
            const newType = this.typeSelect?.value || 'switch';
            this.renderPicker(newType);
        },

        switchCategory: function(category) {
            // Update active state on category tabs
            document.querySelectorAll('.category-tab').forEach(tab => {
                tab.classList.toggle('active', tab.dataset.iconCategory === category);
            });
            
            this.renderDeviceTypeTabs(category);
        },

        switchDeviceType: function(deviceType) {
            // Update active state on device type tabs
            document.querySelectorAll('.device-type-tab').forEach(tab => {
                tab.classList.toggle('active', tab.dataset.deviceType === deviceType);
            });
            
            if (this.typeSelect) {
                this.typeSelect.value = deviceType;
                this.typeSelect.dispatchEvent(new Event('change'));
            }
            this.renderPicker(deviceType);
        },

        selectIcon: function(iconId, deviceType, iconClass) {
            this.selectedIcon = {
                id: iconId,
                type: deviceType,
                icon: iconClass
            };

            // Update hidden input for form submission
            if (this.hiddenInput) {
                this.hiddenInput.value = iconId;
            }

            // Update visual selection
            document.querySelectorAll('.icon-gallery-btn').forEach(btn => {
                btn.classList.toggle('selected', btn.dataset.iconId === iconId);
            });

            // Update preview
            this.updatePreview(iconClass, iconId);

            // Dispatch custom event for network map integration
            const event = new CustomEvent('iconSelected', {
                detail: {
                    id: iconId,
                    type: deviceType,
                    icon: iconClass
                }
            });
            document.dispatchEvent(event);

            // If there's a callback registered, call it
            if (typeof window.onIconSelected === 'function') {
                window.onIconSelected(this.selectedIcon);
            }
        },

        updatePreview: function(iconClass, iconId) {
            const preview = document.querySelector(this.config.previewSelector);
            if (preview) {
                preview.innerHTML = `
                    <div class="selected-icon-preview">
                        <div class="preview-icon">
                            <i class="fas ${iconClass}"></i>
                        </div>
                        <div class="preview-info">
                            <span class="preview-label">Selected Icon</span>
                            <span class="preview-id">${iconId}</span>
                        </div>
                    </div>
                `;
                preview.classList.add('has-selection');
            }
        },

        filterIcons: function(searchTerm) {
            const buttons = document.querySelectorAll('.icon-gallery-btn');
            const term = searchTerm.toLowerCase().trim();

            buttons.forEach(btn => {
                const label = btn.title.toLowerCase();
                const id = btn.dataset.iconId?.toLowerCase() || '';
                const matches = !term || label.includes(term) || id.includes(term);
                btn.style.display = matches ? 'flex' : 'none';
            });

            // Update search status
            const gallery = document.getElementById('iconGallery');
            if (gallery) {
                const existingMsg = gallery.querySelector('.no-results-message');
                if (existingMsg) existingMsg.remove();

                const visible = Array.from(buttons).filter(btn => btn.style.display !== 'none').length;
                if (visible === 0 && term) {
                    const msg = document.createElement('p');
                    msg.className = 'no-results-message';
                    msg.style.cssText = 'grid-column: 1/-1; text-align: center; color: rgba(226, 232, 240, 0.5); padding: 20px;';
                    msg.textContent = 'No icons match your search';
                    gallery.appendChild(msg);
                }
            }
        },

        // Get the currently selected icon
        getSelectedIcon: function() {
            return this.selectedIcon;
        },

        // Set icon programmatically
        setIcon: function(iconId) {
            const allIcons = window.getAllDeviceIcons ? window.getAllDeviceIcons() : [];
            const icon = allIcons.find(i => i.id === iconId);
            if (icon) {
                this.selectIcon(icon.id, icon.type, icon.icon);
            }
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => IconPicker.init());
    } else {
        IconPicker.init();
    }

    // Expose to global scope
    window.IconPicker = IconPicker;
})();
