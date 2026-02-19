/**
 * Enhanced Icon Picker for Device Management
 * Provides interactive icon selection with categories, search, and preview
 */

(function() {
    'use strict';

    const IconPicker = {
        config: {
            containerSelector: '#iconPickerContainer',
            typeSelectSelector: '#type',
            tabSelector: '[data-icon-category]',
            buttonSelector: '.icon-gallery-btn',
            searchSelector: '.icon-picker-search input'
        },

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
            this.subchoiceInput = document.querySelector('#subchoice');

            // Optional preview block (create/edit pages)
            this.previewIcon = document.getElementById('selectedIconPreviewIcon');
            this.previewTitle = document.getElementById('selectedIconPreviewTitle');
            this.previewSubtitle = document.getElementById('selectedIconPreviewSubtitle');
        },

        updateSelectionPreview: function(deviceType, subchoice) {
            if (!this.previewIcon || !this.previewTitle || !this.previewSubtitle || !window.deviceIconsLibrary) {
                return;
            }
            const typeData = window.deviceIconsLibrary[deviceType];
            const icons = typeData?.icons || [];
            const idx = parseInt(subchoice, 10) || 0;
            const variant = icons[idx] || icons[0] || { icon: 'fa-circle', label: 'Default' };

            this.previewIcon.className = `fas ${variant.icon}`;
            this.previewTitle.textContent = typeData?.label ? typeData.label : deviceType;
            this.previewSubtitle.textContent = variant.label ? `Variant: ${variant.label}` : `Variant #${idx}`;
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

            // Delegate event for icon buttons
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('.icon-gallery-btn[data-icon-choice]');
                if (btn) {
                    e.preventDefault();
                    self.selectIcon(btn.dataset.iconChoice, btn.dataset.iconSubchoice || 0);
                }
            });

            // Search functionality
            const searchInput = document.querySelector(this.config.searchSelector);
            if (searchInput) {
                searchInput.addEventListener('input', (e) => self.filterIcons(e.target.value));
            }
        },

        render: function() {
            const currentType = this.typeSelect?.value || 'server';
            this.renderPicker(currentType);
        },

        renderPicker: function(deviceType) {
            if (!this.container || !window.deviceIconsLibrary || !window.deviceIconsLibrary[deviceType]) {
                return;
            }

            const typeData = window.deviceIconsLibrary[deviceType];
            const iconVariants = typeData.icons || [];

            let html = `
                <div class="icon-picker-header">
                    <span class="icon-picker-title">📦 ${typeData.label} Icons</span>
                    <span class="icon-picker-stats">${iconVariants.length} variant${iconVariants.length !== 1 ? 's' : ''}</span>
                </div>
            `;

            // Add search box
            html += `
                <div class="icon-picker-search">
                    <input type="text" placeholder="Search icons..." class="icon-search-input">
                </div>
            `;

            // Render icon gallery
            html += '<div class="icon-gallery" id="iconGallery">';
            iconVariants.forEach((variant, idx) => {
                html += `
                    <button type="button" class="icon-gallery-btn" 
                            data-icon-choice="${deviceType}" 
                            data-icon-subchoice="${idx}"
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
            const currentSubchoice = this.subchoiceInput ? (parseInt(this.subchoiceInput.value, 10) || 0) : 0;
            this.highlightCurrentSelection(deviceType, currentSubchoice);
            this.updateSelectionPreview(deviceType, currentSubchoice);
        },

        updateCategory: function() {
            const newType = this.typeSelect?.value || 'server';
            // When switching type manually, default to the first variant
            if (this.subchoiceInput) {
                this.subchoiceInput.value = '0';
            }
            this.renderPicker(newType);
            this.updateSelectionPreview(newType, 0);
        },

        switchCategory: function(category) {
            if (this.typeSelect) {
                this.typeSelect.value = category;
                this.typeSelect.dispatchEvent(new Event('change'));
                if (this.subchoiceInput) {
                    this.subchoiceInput.value = '0';
                }
                this.renderPicker(category);
                this.updateSelectionPreview(category, 0);
            }
        },

        selectIcon: function(deviceType, subchoice) {
            if (this.typeSelect) {
                this.typeSelect.value = deviceType;
                this.typeSelect.dispatchEvent(new Event('change'));
            }
            if (this.subchoiceInput) {
                this.subchoiceInput.value = String(parseInt(subchoice, 10) || 0);
            }
            this.highlightCurrentSelection(deviceType, subchoice);
            this.updateSelectionPreview(deviceType, subchoice);
        },

        highlightCurrentSelection: function(deviceType, subchoice = 0) {
            const buttons = document.querySelectorAll('.icon-gallery-btn');
            buttons.forEach(btn => {
                const isSelected = 
                    btn.dataset.iconChoice === deviceType && 
                    (btn.dataset.iconSubchoice === String(subchoice) || btn.dataset.iconSubchoice === undefined);
                
                btn.classList.toggle('selected', isSelected);
            });
        },

        filterIcons: function(searchTerm) {
            const buttons = document.querySelectorAll('.icon-gallery-btn');
            const term = searchTerm.toLowerCase().trim();

            buttons.forEach(btn => {
                const label = btn.title.toLowerCase();
                const matches = !term || label.includes(term);
                btn.style.display = matches ? 'flex' : 'none';
            });

            // Update search status
            const gallery = document.getElementById('iconGallery');
            if (gallery) {
                const visible = Array.from(buttons).filter(btn => btn.style.display !== 'none').length;
                const total = buttons.length;
                if (visible === 0 && term) {
                    gallery.style.minHeight = '200px';
                    gallery.innerHTML += '<p style="grid-column: 1/-1; text-align: center; color: rgba(226, 232, 240, 0.5); padding: 20px;">No icons match your search</p>';
                }
            }
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => IconPicker.init());
    } else {
        IconPicker.init();
    }

    // Expose to global scope if needed
    window.IconPicker = IconPicker;
})();
