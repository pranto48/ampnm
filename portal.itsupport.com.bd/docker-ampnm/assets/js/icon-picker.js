/**
 * Enhanced Icon Picker Component
 * Provides categorized icon selection for device management
 * Works with flat device icons library structure
 */

const IconPicker = {
    // Configuration
    config: {
        container: '#icon-picker-container',
        deviceTabs: '#icon-device-tabs',
        gallery: '#icon-gallery',
        search: '#icon-search',
        preview: '#icon-preview',
        hiddenInput: '#selected-icon-id',
        subchoiceInput: '#selected-icon-subchoice',
        iconClassInput: '#selected-icon-class',
        deviceTypeSelect: '#type'
    },
    
    // State
    selectedIcon: null,
    currentDeviceType: 'server',
    library: null,
    elements: {},
    
    /**
     * Initialize the icon picker
     */
    init: function() {
        // Check if library exists
        if (typeof window.deviceIconsLibrary === 'undefined') {
            console.error('IconPicker: Device icons library not found');
            return;
        }
        
        this.library = window.deviceIconsLibrary;
        this.cacheElements();
        
        if (!this.elements.container) {
            console.warn('IconPicker: Container not found');
            return;
        }
        
        this.bindEvents();
        this.render();
        
        // Check for pre-selected icon from hidden input
        this.loadExistingSelection();
        
        console.log('IconPicker: Initialized successfully');
    },
    
    /**
     * Cache DOM elements
     */
    cacheElements: function() {
        this.elements.container = document.querySelector(this.config.container);
        this.elements.deviceTabs = document.querySelector(this.config.deviceTabs);
        this.elements.gallery = document.querySelector(this.config.gallery);
        this.elements.search = document.querySelector(this.config.search);
        this.elements.preview = document.querySelector(this.config.preview);
        this.elements.hiddenInput = document.querySelector(this.config.hiddenInput);
        this.elements.subchoiceInput = document.querySelector(this.config.subchoiceInput);
        this.elements.iconClassInput = document.querySelector(this.config.iconClassInput);
        this.elements.deviceTypeSelect = document.querySelector(this.config.deviceTypeSelect);
    },
    
    /**
     * Load existing selection from hidden inputs or device type select
     */
    loadExistingSelection: function() {
        // Get the current device type from the select dropdown
        if (this.elements.deviceTypeSelect) {
            const deviceType = this.elements.deviceTypeSelect.value;
            if (deviceType && this.library[deviceType]) {
                this.switchDeviceType(deviceType);
            }
        }
        
        // If there's a pre-selected subchoice, select that icon
        if (this.elements.subchoiceInput) {
            const subchoice = parseInt(this.elements.subchoiceInput.value, 10) || 0;
            const typeData = this.library[this.currentDeviceType];
            if (typeData && typeData.icons && typeData.icons[subchoice]) {
                const icon = typeData.icons[subchoice];
                // Generate icon ID
                const iconId = this.currentDeviceType + '-' + subchoice;
                this.selectIcon(iconId, this.currentDeviceType, icon.icon, subchoice, false);
            }
        }
    },
    
    /**
     * Bind event listeners
     */
    bindEvents: function() {
        // Device type select change
        if (this.elements.deviceTypeSelect) {
            this.elements.deviceTypeSelect.addEventListener('change', (e) => {
                this.switchDeviceType(e.target.value);
            });
        }
        
        // Search input
        if (this.elements.search) {
            this.elements.search.addEventListener('input', (e) => {
                this.filterIcons(e.target.value);
            });
        }
        
        // Device type tab clicks (delegated)
        if (this.elements.deviceTabs) {
            this.elements.deviceTabs.addEventListener('click', (e) => {
                const tab = e.target.closest('.icon-device-tab');
                if (tab) {
                    const deviceType = tab.dataset.type;
                    this.switchDeviceType(deviceType);
                    // Update select dropdown
                    if (this.elements.deviceTypeSelect) {
                        this.elements.deviceTypeSelect.value = deviceType;
                    }
                }
            });
        }
        
        // Icon selection (delegated)
        if (this.elements.gallery) {
            this.elements.gallery.addEventListener('click', (e) => {
                const btn = e.target.closest('.icon-gallery-btn');
                if (btn) {
                    const iconId = btn.dataset.iconId;
                    const deviceType = btn.dataset.deviceType;
                    const iconClass = btn.dataset.iconClass;
                    const iconIndex = parseInt(btn.dataset.iconIndex, 10) || 0;
                    this.selectIcon(iconId, deviceType, iconClass, iconIndex, true);
                }
            });
        }
    },
    
    /**
     * Render the complete picker UI
     */
    render: function() {
        this.renderDeviceTypeTabs();
        
        // Set initial device type from select or use first
        if (this.elements.deviceTypeSelect) {
            this.currentDeviceType = this.elements.deviceTypeSelect.value || 'server';
        }
        
        this.renderIconGallery(this.currentDeviceType);
    },
    
    /**
     * Render device type tabs
     */
    renderDeviceTypeTabs: function() {
        if (!this.elements.deviceTabs) return;
        
        let html = '';
        const deviceTypes = Object.keys(this.library);
        const initialType = this.elements.deviceTypeSelect?.value || 'server';
        
        deviceTypes.forEach(type => {
            const typeData = this.library[type];
            if (typeData) {
                const activeClass = type === initialType ? 'active' : '';
                html += `
                    <button type="button" class="icon-device-tab ${activeClass}" data-type="${type}">
                        ${typeData.label}
                    </button>
                `;
            }
        });
        this.elements.deviceTabs.innerHTML = html;
    },
    
    /**
     * Render icon gallery for a device type
     */
    renderIconGallery: function(deviceType) {
        if (!this.elements.gallery) return;
        
        const typeData = this.library[deviceType];
        if (!typeData || !typeData.icons) {
            this.elements.gallery.innerHTML = `
                <div class="icon-no-results">
                    <i class="fas fa-search"></i>
                    <p>No icons found for this type</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        typeData.icons.forEach((icon, index) => {
            const iconId = deviceType + '-' + index;
            // Check if this specific icon is selected
            const isSelected = this.selectedIcon && 
                               this.selectedIcon.id === iconId && 
                               this.selectedIcon.deviceType === deviceType;
            const selectedClass = isSelected ? 'selected' : '';
            html += `
                <button type="button" 
                        class="icon-gallery-btn ${selectedClass}" 
                        data-icon-id="${iconId}" 
                        data-device-type="${deviceType}"
                        data-icon-class="${icon.icon}"
                        data-icon-index="${index}"
                        title="${icon.label}">
                    <i class="fas ${icon.icon}"></i>
                    <span class="icon-label">${icon.label}</span>
                </button>
            `;
        });
        this.elements.gallery.innerHTML = html;
    },
    
    /**
     * Switch to a device type
     */
    switchDeviceType: function(deviceType) {
        if (!this.library[deviceType]) return;
        
        this.currentDeviceType = deviceType;
        
        // Update device tab states
        if (this.elements.deviceTabs) {
            const tabs = this.elements.deviceTabs.querySelectorAll('.icon-device-tab');
            tabs.forEach(tab => {
                tab.classList.toggle('active', tab.dataset.type === deviceType);
            });
        }
        
        // Render icon gallery
        this.renderIconGallery(deviceType);
        
        // Clear search
        if (this.elements.search) {
            this.elements.search.value = '';
        }
    },
    
    /**
     * Select an icon
     */
    selectIcon: function(iconId, deviceType, iconClass, subchoice, animate = true) {
        // Update state
        this.selectedIcon = {
            id: iconId,
            deviceType: deviceType,
            iconClass: iconClass,
            subchoice: subchoice
        };
        
        // Update hidden inputs
        if (this.elements.hiddenInput) {
            this.elements.hiddenInput.value = iconId;
        }
        
        if (this.elements.subchoiceInput) {
            this.elements.subchoiceInput.value = subchoice;
        }
        
        if (this.elements.iconClassInput) {
            this.elements.iconClassInput.value = iconClass;
        }
        
        // IMPORTANT: Clear ALL selected states from ALL buttons first
        document.querySelectorAll('.icon-gallery-btn.selected').forEach(btn => {
            btn.classList.remove('selected', 'just-selected');
        });
        
        // Update current gallery button states - only select the ONE clicked button
        const buttons = this.elements.gallery.querySelectorAll('.icon-gallery-btn');
        buttons.forEach(btn => {
            btn.classList.remove('selected', 'just-selected');
            // Match by both iconId AND deviceType to ensure uniqueness
            if (btn.dataset.iconId === iconId && btn.dataset.deviceType === deviceType) {
                btn.classList.add('selected');
                if (animate) {
                    btn.classList.add('just-selected');
                    setTimeout(() => btn.classList.remove('just-selected'), 300);
                }
            }
        });
        
        // Update preview
        this.updatePreview(iconId, iconClass, deviceType, subchoice);
        
        // Dispatch custom event
        const event = new CustomEvent('iconSelected', {
            detail: this.selectedIcon
        });
        document.dispatchEvent(event);
        
        console.log('IconPicker: Selected icon', iconId, 'subchoice:', subchoice);
    },
    
    /**
     * Update preview section
     */
    updatePreview: function(iconId, iconClass, deviceType, subchoice) {
        if (!this.elements.preview) return;
        
        const typeData = this.library[deviceType];
        if (!typeData || !typeData.icons || !typeData.icons[subchoice]) {
            this.elements.preview.innerHTML = '';
            return;
        }
        
        const iconData = typeData.icons[subchoice];
        
        this.elements.preview.innerHTML = `
            <div class="icon-preview-section">
                <div class="icon-preview-box">
                    <i class="fas ${iconClass}"></i>
                </div>
                <div class="icon-preview-info">
                    <div class="icon-preview-label">Selected Icon</div>
                    <div class="icon-preview-name">${typeData.label} - ${iconData.label}</div>
                    <div class="icon-preview-id">${iconClass}</div>
                </div>
            </div>
        `;
    },
    
    /**
     * Filter icons by search term
     */
    filterIcons: function(searchTerm) {
        const term = searchTerm.toLowerCase().trim();
        const buttons = this.elements.gallery.querySelectorAll('.icon-gallery-btn');
        let hasVisible = false;
        
        buttons.forEach(btn => {
            const label = btn.querySelector('.icon-label');
            const text = label ? label.textContent.toLowerCase() : '';
            const iconClass = btn.dataset.iconClass.toLowerCase();
            
            const matches = text.includes(term) || iconClass.includes(term);
            btn.style.display = matches ? '' : 'none';
            
            if (matches) hasVisible = true;
        });
        
        // Show no results message
        let noResults = this.elements.gallery.querySelector('.icon-no-results');
        if (!hasVisible && term) {
            if (!noResults) {
                noResults = document.createElement('div');
                noResults.className = 'icon-no-results';
                noResults.innerHTML = `
                    <i class="fas fa-search"></i>
                    <p>No icons match "${searchTerm}"</p>
                `;
                this.elements.gallery.appendChild(noResults);
            }
        } else if (noResults) {
            noResults.remove();
        }
    },
    
    /**
     * Get currently selected icon
     */
    getSelectedIcon: function() {
        return this.selectedIcon;
    },
    
    /**
     * Set icon programmatically by device type and subchoice
     */
    setIcon: function(deviceType, subchoice) {
        if (!this.library[deviceType]) return false;
        
        const typeData = this.library[deviceType];
        const index = parseInt(subchoice, 10) || 0;
        
        if (!typeData.icons || !typeData.icons[index]) return false;
        
        const icon = typeData.icons[index];
        const iconId = deviceType + '-' + index;
        
        // Switch to device type
        this.switchDeviceType(deviceType);
        
        // Select the icon
        this.selectIcon(iconId, deviceType, icon.icon, index, false);
        
        return true;
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Small delay to ensure library is loaded
    setTimeout(function() {
        IconPicker.init();
    }, 100);
});

// Expose globally
window.IconPicker = IconPicker;