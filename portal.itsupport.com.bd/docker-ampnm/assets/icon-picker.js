/**
 * Enhanced Icon Picker for Device Management
 * Provides interactive icon selection with categories, search, and preview
 * Selected icons are displayed on the network map
 */

(function() {
    'use strict';

    const IconPicker = {
        config: {
            containerSelector: '#icon-picker-container, #iconPickerContainer',
            categoryTabsSelector: '#icon-category-tabs, #categoryTabsContainer',
            deviceTabsSelector: '#icon-device-tabs, #deviceTypeTabsContainer',
            gallerySelector: '#icon-gallery, #iconGallery',
            searchSelector: '#icon-search, .icon-search-input',
            previewSelector: '#icon-preview, #selectedIconPreview',
            typeSelectSelector: '#type',
            hiddenIconIdSelector: '#selected-icon-id, #selectedIconId',
            hiddenSubchoiceSelector: '#selected-icon-subchoice',
            hiddenIconClassSelector: '#selected-icon-class'
        },

        selectedIcon: null,
        currentCategory: null,
        currentDeviceType: null,

        init: function() {
            // Wait for DOM and icons library
            if (!this.validateDom()) {
                console.log('Icon picker container not found');
                return;
            }
            
            if (!window.deviceIconsLibrary) {
                console.error('Device icons library not loaded');
                return;
            }
            
            this.cacheElements();
            this.bindEvents();
            this.render();
            console.log('Icon picker initialized');
        },

        validateDom: function() {
            return document.querySelector('#icon-picker-container') !== null || 
                   document.querySelector('#iconPickerContainer') !== null;
        },

        cacheElements: function() {
            this.container = document.querySelector('#icon-picker-container') || 
                             document.querySelector('#iconPickerContainer');
            this.categoryTabs = document.querySelector('#icon-category-tabs') || 
                                document.querySelector('#categoryTabsContainer');
            this.deviceTabs = document.querySelector('#icon-device-tabs') || 
                              document.querySelector('#deviceTypeTabsContainer');
            this.gallery = document.querySelector('#icon-gallery') || 
                          document.querySelector('#iconGallery');
            this.searchInput = document.querySelector('#icon-search') || 
                              document.querySelector('.icon-search-input');
            this.preview = document.querySelector('#icon-preview') || 
                          document.querySelector('#selectedIconPreview');
            this.typeSelect = document.querySelector('#type');
            this.hiddenIconId = document.querySelector('#selected-icon-id') || 
                               document.querySelector('#selectedIconId');
            this.hiddenSubchoice = document.querySelector('#selected-icon-subchoice');
            this.hiddenIconClass = document.querySelector('#selected-icon-class');
        },

        bindEvents: function() {
            const self = this;
            
            // Type select change
            if (this.typeSelect) {
                this.typeSelect.addEventListener('change', () => self.onTypeChange());
            }

            // Delegate event for category tabs
            document.addEventListener('click', (e) => {
                if (e.target.closest('.icon-category-tab')) {
                    e.preventDefault();
                    const btn = e.target.closest('.icon-category-tab');
                    const category = btn.dataset.category;
                    if (category) {
                        self.switchCategory(category);
                    }
                }
            });

            // Delegate event for device type tabs
            document.addEventListener('click', (e) => {
                if (e.target.closest('.icon-device-tab')) {
                    e.preventDefault();
                    const btn = e.target.closest('.icon-device-tab');
                    const deviceType = btn.dataset.deviceType;
                    if (deviceType) {
                        self.switchDeviceType(deviceType);
                    }
                }
            });

            // Delegate event for icon buttons
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('.icon-gallery-btn');
                if (btn && btn.dataset.iconIndex !== undefined) {
                    e.preventDefault();
                    const deviceType = btn.dataset.deviceType;
                    const iconIndex = parseInt(btn.dataset.iconIndex);
                    const iconClass = btn.dataset.iconClass;
                    const iconLabel = btn.dataset.iconLabel;
                    self.selectIcon(deviceType, iconIndex, iconClass, iconLabel);
                }
            });

            // Search functionality
            if (this.searchInput) {
                this.searchInput.addEventListener('input', (e) => self.filterIcons(e.target.value));
            }
            
            // Also listen for dynamically added search input
            document.addEventListener('input', (e) => {
                if (e.target.matches('#icon-search, .icon-search-input')) {
                    self.filterIcons(e.target.value);
                }
            });
        },

        render: function() {
            // Get unique categories from device types
            const categories = this.getCategories();
            
            // Render category tabs
            this.renderCategoryTabs(categories);
            
            // Get first category and its device types
            const firstCategory = Object.keys(categories)[0];
            this.currentCategory = firstCategory;
            
            // Render device type tabs for first category
            this.renderDeviceTypeTabs(firstCategory);
            
            // Render icons for first device type
            const deviceTypes = Object.keys(window.deviceIconsLibrary);
            if (deviceTypes.length > 0) {
                // If type select has a value, use that, otherwise use first device type
                const selectedType = this.typeSelect?.value || deviceTypes[0];
                this.currentDeviceType = selectedType;
                this.renderIcons(selectedType);
            }
        },

        getCategories: function() {
            // Group device types by first letter or create simple categories
            const categories = {
                'network': { label: 'Network', icon: 'fa-network-wired', types: [] },
                'compute': { label: 'Compute', icon: 'fa-server', types: [] },
                'security': { label: 'Security', icon: 'fa-shield-halved', types: [] },
                'storage': { label: 'Storage', icon: 'fa-database', types: [] },
                'endpoint': { label: 'Endpoints', icon: 'fa-desktop', types: [] },
                'other': { label: 'Other', icon: 'fa-ellipsis', types: [] }
            };
            
            // Categorize device types
            const typeCategories = {
                'router': 'network', 'wifi-router': 'network', 'switch': 'network', 
                'modem': 'network', 'loadbalancer': 'network', 'radio-tower': 'network',
                'server': 'compute', 'database': 'compute', 'cloud': 'compute', 'rack': 'compute',
                'firewall': 'security', 'camera': 'security',
                'nas': 'storage',
                'laptop': 'endpoint', 'tablet': 'endpoint', 'mobile': 'endpoint',
                'printer': 'endpoint', 'ipphone': 'endpoint', 'monitor': 'endpoint',
                'keyboard': 'endpoint', 'punchdevice': 'endpoint'
            };
            
            Object.keys(window.deviceIconsLibrary).forEach(type => {
                const cat = typeCategories[type] || 'other';
                if (categories[cat]) {
                    categories[cat].types.push(type);
                }
            });
            
            return categories;
        },

        renderCategoryTabs: function(categories) {
            if (!this.categoryTabs) return;
            
            let html = '';
            let isFirst = true;
            
            Object.entries(categories).forEach(([key, cat]) => {
                if (cat.types.length === 0) return;
                html += `
                    <button type="button" class="icon-category-tab ${isFirst ? 'active' : ''}" 
                            data-category="${key}">
                        <i class="fas ${cat.icon}"></i>
                        <span>${cat.label}</span>
                    </button>
                `;
                isFirst = false;
            });
            
            this.categoryTabs.innerHTML = html;
        },

        renderDeviceTypeTabs: function(category) {
            if (!this.deviceTabs) return;
            
            const categories = this.getCategories();
            const types = categories[category]?.types || [];
            
            let html = '';
            let isFirst = true;
            
            types.forEach(type => {
                const typeData = window.deviceIconsLibrary[type];
                if (!typeData) return;
                
                html += `
                    <button type="button" class="icon-device-tab ${isFirst ? 'active' : ''}" 
                            data-device-type="${type}">
                        ${typeData.label}
                    </button>
                `;
                isFirst = false;
            });
            
            this.deviceTabs.innerHTML = html;
            
            // Render icons for first type
            if (types.length > 0) {
                this.currentDeviceType = types[0];
                this.renderIcons(types[0]);
            }
        },

        renderIcons: function(deviceType) {
            if (!this.gallery) {
                this.gallery = document.querySelector('#icon-gallery');
            }
            if (!this.gallery) return;
            
            const typeData = window.deviceIconsLibrary[deviceType];
            if (!typeData) {
                this.gallery.innerHTML = '<p class="icon-no-results"><i class="fas fa-exclamation-circle"></i><br>No icons found</p>';
                return;
            }
            
            const icons = typeData.icons || [];
            
            let html = '';
            icons.forEach((icon, index) => {
                const isSelected = this.selectedIcon && 
                                   this.selectedIcon.type === deviceType && 
                                   this.selectedIcon.index === index;
                html += `
                    <button type="button" class="icon-gallery-btn ${isSelected ? 'selected' : ''}" 
                            data-device-type="${deviceType}"
                            data-icon-index="${index}"
                            data-icon-class="${icon.icon}"
                            data-icon-label="${icon.label}"
                            title="${icon.label}">
                        <i class="fas ${icon.icon}"></i>
                        <span class="icon-label">${icon.label}</span>
                    </button>
                `;
            });
            
            if (icons.length === 0) {
                html = '<p class="icon-no-results"><i class="fas fa-exclamation-circle"></i><br>No icons available</p>';
            }
            
            this.gallery.innerHTML = html;
        },

        onTypeChange: function() {
            if (!this.typeSelect) return;
            
            const newType = this.typeSelect.value;
            this.currentDeviceType = newType;
            
            // Find which category this type belongs to
            const categories = this.getCategories();
            for (const [catKey, cat] of Object.entries(categories)) {
                if (cat.types.includes(newType)) {
                    this.switchCategory(catKey, false);
                    break;
                }
            }
            
            // Highlight correct device tab
            document.querySelectorAll('.icon-device-tab').forEach(tab => {
                tab.classList.toggle('active', tab.dataset.deviceType === newType);
            });
            
            this.renderIcons(newType);
        },

        switchCategory: function(category, updateIcons = true) {
            this.currentCategory = category;
            
            // Update active state on category tabs
            document.querySelectorAll('.icon-category-tab').forEach(tab => {
                tab.classList.toggle('active', tab.dataset.category === category);
            });
            
            // Render device type tabs for this category
            this.renderDeviceTypeTabs(category);
        },

        switchDeviceType: function(deviceType) {
            this.currentDeviceType = deviceType;
            
            // Update active state on device type tabs
            document.querySelectorAll('.icon-device-tab').forEach(tab => {
                tab.classList.toggle('active', tab.dataset.deviceType === deviceType);
            });
            
            // Update type select if present
            if (this.typeSelect) {
                this.typeSelect.value = deviceType;
            }
            
            this.renderIcons(deviceType);
        },

        selectIcon: function(deviceType, iconIndex, iconClass, iconLabel) {
            this.selectedIcon = {
                type: deviceType,
                index: iconIndex,
                icon: iconClass,
                label: iconLabel
            };

            // Update hidden inputs for form submission
            if (this.hiddenIconId) {
                this.hiddenIconId.value = `${deviceType}-${iconIndex}`;
            }
            if (this.hiddenSubchoice) {
                this.hiddenSubchoice.value = iconIndex;
            }
            if (this.hiddenIconClass) {
                this.hiddenIconClass.value = iconClass;
            }

            // Update visual selection
            document.querySelectorAll('.icon-gallery-btn').forEach(btn => {
                const isMatch = btn.dataset.deviceType === deviceType && 
                               parseInt(btn.dataset.iconIndex) === iconIndex;
                btn.classList.toggle('selected', isMatch);
                if (isMatch) {
                    btn.classList.add('just-selected');
                    setTimeout(() => btn.classList.remove('just-selected'), 300);
                }
            });

            // Update preview
            this.updatePreview();

            // Dispatch custom event
            const event = new CustomEvent('iconSelected', {
                detail: this.selectedIcon
            });
            document.dispatchEvent(event);

            // Call global callback if registered
            if (typeof window.onIconSelected === 'function') {
                window.onIconSelected(this.selectedIcon);
            }
        },

        updatePreview: function() {
            if (!this.preview) {
                this.preview = document.querySelector('#icon-preview');
            }
            if (!this.preview || !this.selectedIcon) return;
            
            this.preview.innerHTML = `
                <div class="icon-preview-section">
                    <div class="icon-preview-box">
                        <i class="fas ${this.selectedIcon.icon}"></i>
                    </div>
                    <div class="icon-preview-info">
                        <span class="icon-preview-label">Selected Icon</span>
                        <span class="icon-preview-name">${this.selectedIcon.label}</span>
                        <span class="icon-preview-id">${this.selectedIcon.type} #${this.selectedIcon.index}</span>
                    </div>
                </div>
            `;
        },

        filterIcons: function(searchTerm) {
            const buttons = document.querySelectorAll('.icon-gallery-btn');
            const term = searchTerm.toLowerCase().trim();

            let visibleCount = 0;
            buttons.forEach(btn => {
                const label = (btn.dataset.iconLabel || btn.title || '').toLowerCase();
                const iconClass = (btn.dataset.iconClass || '').toLowerCase();
                const matches = !term || label.includes(term) || iconClass.includes(term);
                btn.style.display = matches ? '' : 'none';
                if (matches) visibleCount++;
            });

            // Show no results message
            const gallery = document.querySelector('#icon-gallery');
            if (gallery) {
                const existingMsg = gallery.querySelector('.icon-no-results');
                if (existingMsg) existingMsg.remove();

                if (visibleCount === 0 && term) {
                    const msg = document.createElement('p');
                    msg.className = 'icon-no-results';
                    msg.innerHTML = '<i class="fas fa-search"></i><br>No icons match your search';
                    gallery.appendChild(msg);
                }
            }
        },

        // Get the currently selected icon
        getSelectedIcon: function() {
            return this.selectedIcon;
        },

        // Set icon programmatically
        setIcon: function(deviceType, iconIndex) {
            const typeData = window.deviceIconsLibrary[deviceType];
            if (typeData && typeData.icons[iconIndex]) {
                const icon = typeData.icons[iconIndex];
                this.selectIcon(deviceType, iconIndex, icon.icon, icon.label);
            }
        }
    };

    // Initialize when DOM is ready
    function initPicker() {
        if (window.deviceIconsLibrary) {
            IconPicker.init();
        } else {
            // Wait a bit for the library to load
            setTimeout(() => {
                if (window.deviceIconsLibrary) {
                    IconPicker.init();
                } else {
                    console.error('Device icons library not loaded after timeout');
                }
            }, 500);
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPicker);
    } else {
        initPicker();
    }

    // Expose to global scope
    window.IconPicker = IconPicker;
})();
