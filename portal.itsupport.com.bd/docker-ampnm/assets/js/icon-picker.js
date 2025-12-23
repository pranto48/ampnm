/**
 * Enhanced Icon Picker Component
 * Provides categorized icon selection for device management
 */

const IconPicker = {
    // Configuration
    config: {
        container: '#icon-picker-container',
        categoryTabs: '#icon-category-tabs',
        deviceTabs: '#icon-device-tabs',
        gallery: '#icon-gallery',
        search: '#icon-search',
        preview: '#icon-preview',
        hiddenInput: '#selected-icon-id',
        deviceTypeSelect: '#device-type'
    },
    
    // State
    selectedIcon: null,
    currentCategory: 'network',
    currentDeviceType: null,
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
        
        console.log('IconPicker: Initialized successfully');
    },
    
    /**
     * Cache DOM elements
     */
    cacheElements: function() {
        this.elements.container = document.querySelector(this.config.container);
        this.elements.categoryTabs = document.querySelector(this.config.categoryTabs);
        this.elements.deviceTabs = document.querySelector(this.config.deviceTabs);
        this.elements.gallery = document.querySelector(this.config.gallery);
        this.elements.search = document.querySelector(this.config.search);
        this.elements.preview = document.querySelector(this.config.preview);
        this.elements.hiddenInput = document.querySelector(this.config.hiddenInput);
        this.elements.deviceTypeSelect = document.querySelector(this.config.deviceTypeSelect);
    },
    
    /**
     * Bind event listeners
     */
    bindEvents: function() {
        // Device type select change
        if (this.elements.deviceTypeSelect) {
            this.elements.deviceTypeSelect.addEventListener('change', (e) => {
                this.switchToDeviceType(e.target.value);
            });
        }
        
        // Search input
        if (this.elements.search) {
            this.elements.search.addEventListener('input', (e) => {
                this.filterIcons(e.target.value);
            });
        }
        
        // Category tab clicks (delegated)
        if (this.elements.categoryTabs) {
            this.elements.categoryTabs.addEventListener('click', (e) => {
                const tab = e.target.closest('.icon-category-tab');
                if (tab) {
                    const category = tab.dataset.category;
                    this.switchCategory(category);
                }
            });
        }
        
        // Device type tab clicks (delegated)
        if (this.elements.deviceTabs) {
            this.elements.deviceTabs.addEventListener('click', (e) => {
                const tab = e.target.closest('.icon-device-tab');
                if (tab) {
                    const deviceType = tab.dataset.type;
                    this.switchDeviceType(deviceType);
                }
            });
        }
        
        // Icon selection (delegated)
        if (this.elements.gallery) {
            this.elements.gallery.addEventListener('click', (e) => {
                const btn = e.target.closest('.icon-gallery-btn');
                if (btn) {
                    this.selectIcon(btn.dataset.iconId, btn.dataset.deviceType, btn.dataset.iconClass);
                }
            });
        }
    },
    
    /**
     * Render the complete picker UI
     */
    render: function() {
        this.renderCategoryTabs();
        this.renderDeviceTypeTabs(this.currentCategory);
        
        // Set initial device type
        const firstType = this.library.categories[this.currentCategory].types[0];
        this.currentDeviceType = firstType;
        this.renderIconGallery(firstType);
    },
    
    /**
     * Render category tabs
     */
    renderCategoryTabs: function() {
        if (!this.elements.categoryTabs) return;
        
        let html = '';
        for (const [key, category] of Object.entries(this.library.categories)) {
            const activeClass = key === this.currentCategory ? 'active' : '';
            html += `
                <button type="button" class="icon-category-tab ${activeClass}" data-category="${key}">
                    <i class="fas ${category.icon}"></i>
                    <span>${category.label}</span>
                </button>
            `;
        }
        this.elements.categoryTabs.innerHTML = html;
    },
    
    /**
     * Render device type tabs for a category
     */
    renderDeviceTypeTabs: function(category) {
        if (!this.elements.deviceTabs) return;
        
        const categoryData = this.library.categories[category];
        if (!categoryData) return;
        
        let html = '';
        categoryData.types.forEach((type, index) => {
            const typeData = this.library.types[type];
            if (typeData) {
                const activeClass = index === 0 ? 'active' : '';
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
        
        const typeData = this.library.types[deviceType];
        if (!typeData) {
            this.elements.gallery.innerHTML = `
                <div class="icon-no-results">
                    <i class="fas fa-search"></i>
                    <p>No icons found for this type</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        typeData.icons.forEach(icon => {
            const selectedClass = this.selectedIcon && this.selectedIcon.id === icon.id ? 'selected' : '';
            html += `
                <button type="button" 
                        class="icon-gallery-btn ${selectedClass}" 
                        data-icon-id="${icon.id}" 
                        data-device-type="${deviceType}"
                        data-icon-class="${icon.icon}"
                        title="${icon.label}">
                    <i class="fas ${icon.icon}"></i>
                    <span class="icon-label">${icon.label}</span>
                </button>
            `;
        });
        this.elements.gallery.innerHTML = html;
    },
    
    /**
     * Switch to a category
     */
    switchCategory: function(category) {
        if (!this.library.categories[category]) return;
        
        this.currentCategory = category;
        
        // Update category tab states
        const tabs = this.elements.categoryTabs.querySelectorAll('.icon-category-tab');
        tabs.forEach(tab => {
            tab.classList.toggle('active', tab.dataset.category === category);
        });
        
        // Render device type tabs and gallery
        this.renderDeviceTypeTabs(category);
        const firstType = this.library.categories[category].types[0];
        this.currentDeviceType = firstType;
        this.renderIconGallery(firstType);
        
        // Clear search
        if (this.elements.search) {
            this.elements.search.value = '';
        }
    },
    
    /**
     * Switch to a device type
     */
    switchDeviceType: function(deviceType) {
        if (!this.library.types[deviceType]) return;
        
        this.currentDeviceType = deviceType;
        
        // Update device tab states
        const tabs = this.elements.deviceTabs.querySelectorAll('.icon-device-tab');
        tabs.forEach(tab => {
            tab.classList.toggle('active', tab.dataset.type === deviceType);
        });
        
        // Render icon gallery
        this.renderIconGallery(deviceType);
        
        // Update device type select if it exists
        if (this.elements.deviceTypeSelect) {
            this.elements.deviceTypeSelect.value = deviceType;
        }
    },
    
    /**
     * Switch to device type (from select dropdown)
     */
    switchToDeviceType: function(deviceType) {
        const typeData = this.library.types[deviceType];
        if (!typeData) return;
        
        // Find and switch to the category
        const category = typeData.category;
        if (category !== this.currentCategory) {
            this.switchCategory(category);
        }
        
        // Switch to device type
        this.switchDeviceType(deviceType);
    },
    
    /**
     * Select an icon
     */
    selectIcon: function(iconId, deviceType, iconClass) {
        // Update state
        this.selectedIcon = {
            id: iconId,
            deviceType: deviceType,
            iconClass: iconClass
        };
        
        // Update hidden input
        if (this.elements.hiddenInput) {
            this.elements.hiddenInput.value = iconId;
        }
        
        // Update gallery button states
        const buttons = this.elements.gallery.querySelectorAll('.icon-gallery-btn');
        buttons.forEach(btn => {
            btn.classList.remove('selected', 'just-selected');
            if (btn.dataset.iconId === iconId) {
                btn.classList.add('selected', 'just-selected');
                setTimeout(() => btn.classList.remove('just-selected'), 300);
            }
        });
        
        // Update preview
        this.updatePreview(iconId, iconClass);
        
        // Dispatch custom event
        const event = new CustomEvent('iconSelected', {
            detail: this.selectedIcon
        });
        document.dispatchEvent(event);
        
        console.log('IconPicker: Selected icon', iconId);
    },
    
    /**
     * Update preview section
     */
    updatePreview: function(iconId, iconClass) {
        if (!this.elements.preview) return;
        
        // Find icon data
        let iconData = null;
        let deviceType = null;
        
        for (const [type, data] of Object.entries(this.library.types)) {
            const found = data.icons.find(i => i.id === iconId);
            if (found) {
                iconData = found;
                deviceType = type;
                break;
            }
        }
        
        if (!iconData) {
            this.elements.preview.innerHTML = '';
            return;
        }
        
        const typeData = this.library.types[deviceType];
        
        this.elements.preview.innerHTML = `
            <div class="icon-preview-section">
                <div class="icon-preview-box">
                    <i class="fas ${iconClass || iconData.icon}"></i>
                </div>
                <div class="icon-preview-info">
                    <div class="icon-preview-label">Selected Icon</div>
                    <div class="icon-preview-name">${typeData.label} - ${iconData.label}</div>
                    <div class="icon-preview-id">${iconId}</div>
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
            const iconId = btn.dataset.iconId.toLowerCase();
            
            const matches = text.includes(term) || iconId.includes(term);
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
     * Set icon programmatically
     */
    setIcon: function(iconId) {
        // Find the icon
        for (const [type, data] of Object.entries(this.library.types)) {
            const icon = data.icons.find(i => i.id === iconId);
            if (icon) {
                // Switch to correct category and type
                this.switchToDeviceType(type);
                // Select the icon
                this.selectIcon(iconId, type, icon.icon);
                return true;
            }
        }
        return false;
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
