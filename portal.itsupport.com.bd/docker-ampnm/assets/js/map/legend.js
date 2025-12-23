/**
 * Legend Manager - Handles draggable and collapsible legend boxes
 */
window.MapApp = window.MapApp || {};

MapApp.legend = {
    // Store positions in localStorage
    storageKey: 'ampnm_legend_positions',
    
    // Initialize legend functionality
    init: function() {
        this.initDraggable();
        this.initToggle();
        this.loadPositions();
    },
    
    // Initialize draggable legends
    initDraggable: function() {
        const legends = document.querySelectorAll('.legend-container');
        
        legends.forEach(legend => {
            const handle = legend.querySelector('.legend-drag-handle');
            if (!handle) return;
            
            let isDragging = false;
            let startX, startY, startLeft, startTop;
            
            handle.addEventListener('mousedown', (e) => {
                isDragging = true;
                startX = e.clientX;
                startY = e.clientY;
                
                const rect = legend.getBoundingClientRect();
                const wrapper = document.getElementById('network-map-wrapper');
                const wrapperRect = wrapper.getBoundingClientRect();
                
                startLeft = rect.left - wrapperRect.left;
                startTop = rect.top - wrapperRect.top;
                
                legend.style.transition = 'none';
                legend.classList.add('dragging');
                
                e.preventDefault();
            });
            
            document.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                
                const wrapper = document.getElementById('network-map-wrapper');
                const wrapperRect = wrapper.getBoundingClientRect();
                
                let newLeft = startLeft + (e.clientX - startX);
                let newTop = startTop + (e.clientY - startY);
                
                // Boundary constraints
                const legendRect = legend.getBoundingClientRect();
                const maxLeft = wrapperRect.width - legendRect.width;
                const maxTop = wrapperRect.height - legendRect.height;
                
                newLeft = Math.max(0, Math.min(newLeft, maxLeft));
                newTop = Math.max(0, Math.min(newTop, maxTop));
                
                legend.style.left = newLeft + 'px';
                legend.style.top = newTop + 'px';
                legend.style.right = 'auto';
                legend.style.bottom = 'auto';
            });
            
            document.addEventListener('mouseup', () => {
                if (!isDragging) return;
                isDragging = false;
                legend.style.transition = '';
                legend.classList.remove('dragging');
                this.savePositions();
            });
            
            // Touch support
            handle.addEventListener('touchstart', (e) => {
                const touch = e.touches[0];
                isDragging = true;
                startX = touch.clientX;
                startY = touch.clientY;
                
                const rect = legend.getBoundingClientRect();
                const wrapper = document.getElementById('network-map-wrapper');
                const wrapperRect = wrapper.getBoundingClientRect();
                
                startLeft = rect.left - wrapperRect.left;
                startTop = rect.top - wrapperRect.top;
                
                legend.classList.add('dragging');
            }, { passive: true });
            
            document.addEventListener('touchmove', (e) => {
                if (!isDragging) return;
                
                const touch = e.touches[0];
                const wrapper = document.getElementById('network-map-wrapper');
                const wrapperRect = wrapper.getBoundingClientRect();
                
                let newLeft = startLeft + (touch.clientX - startX);
                let newTop = startTop + (touch.clientY - startY);
                
                const legendRect = legend.getBoundingClientRect();
                const maxLeft = wrapperRect.width - legendRect.width;
                const maxTop = wrapperRect.height - legendRect.height;
                
                newLeft = Math.max(0, Math.min(newLeft, maxLeft));
                newTop = Math.max(0, Math.min(newTop, maxTop));
                
                legend.style.left = newLeft + 'px';
                legend.style.top = newTop + 'px';
                legend.style.right = 'auto';
                legend.style.bottom = 'auto';
            }, { passive: true });
            
            document.addEventListener('touchend', () => {
                if (!isDragging) return;
                isDragging = false;
                legend.classList.remove('dragging');
                this.savePositions();
            });
        });
    },
    
    // Initialize toggle (hide/show) functionality
    initToggle: function() {
        // Toggle buttons on legend headers
        document.querySelectorAll('.legend-toggle-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const targetId = btn.dataset.target;
                const content = document.getElementById(targetId);
                const legend = btn.closest('.legend-container');
                const legendType = legend.dataset.legend;
                const bar = document.getElementById(legendType + '-legend-bar');
                
                if (content) {
                    // Hide the full legend
                    legend.classList.add('legend-hidden');
                    
                    // Show the collapsed bar
                    if (bar) {
                        bar.classList.remove('legend-bar-hidden');
                        // Position the bar where the legend was
                        const legendRect = legend.getBoundingClientRect();
                        const wrapper = document.getElementById('network-map-wrapper');
                        const wrapperRect = wrapper.getBoundingClientRect();
                        bar.style.left = (legendRect.left - wrapperRect.left) + 'px';
                        bar.style.top = (legendRect.top - wrapperRect.top) + 'px';
                    }
                    
                    this.saveState(legendType, 'collapsed');
                }
            });
        });
        
        // Collapsed bar click to expand
        document.querySelectorAll('.legend-bar').forEach(bar => {
            bar.addEventListener('click', (e) => {
                const legendType = bar.dataset.legend;
                const legend = document.getElementById(legendType === 'status' ? 'status-legend-container' : 'connection-legend');
                
                if (legend) {
                    // Show the full legend
                    legend.classList.remove('legend-hidden');
                    
                    // Hide the bar
                    bar.classList.add('legend-bar-hidden');
                    
                    // Position legend where bar was
                    const barRect = bar.getBoundingClientRect();
                    const wrapper = document.getElementById('network-map-wrapper');
                    const wrapperRect = wrapper.getBoundingClientRect();
                    legend.style.left = (barRect.left - wrapperRect.left) + 'px';
                    legend.style.top = (barRect.top - wrapperRect.top) + 'px';
                    
                    this.saveState(legendType, 'expanded');
                }
            });
        });
    },
    
    // Save positions to localStorage
    savePositions: function() {
        const positions = {};
        document.querySelectorAll('.legend-container').forEach(legend => {
            const legendType = legend.dataset.legend;
            positions[legendType] = {
                left: legend.style.left,
                top: legend.style.top
            };
        });
        
        try {
            const stored = JSON.parse(localStorage.getItem(this.storageKey) || '{}');
            stored.positions = positions;
            localStorage.setItem(this.storageKey, JSON.stringify(stored));
        } catch (e) {
            console.warn('Failed to save legend positions:', e);
        }
    },
    
    // Save collapsed/expanded state
    saveState: function(legendType, state) {
        try {
            const stored = JSON.parse(localStorage.getItem(this.storageKey) || '{}');
            stored.states = stored.states || {};
            stored.states[legendType] = state;
            localStorage.setItem(this.storageKey, JSON.stringify(stored));
        } catch (e) {
            console.warn('Failed to save legend state:', e);
        }
    },
    
    // Load positions and states from localStorage
    loadPositions: function() {
        try {
            const stored = JSON.parse(localStorage.getItem(this.storageKey) || '{}');
            
            // Restore positions
            if (stored.positions) {
                Object.entries(stored.positions).forEach(([legendType, pos]) => {
                    const legend = document.querySelector(`.legend-container[data-legend="${legendType}"]`);
                    if (legend && pos.left && pos.top) {
                        legend.style.left = pos.left;
                        legend.style.top = pos.top;
                        legend.style.right = 'auto';
                        legend.style.bottom = 'auto';
                    }
                });
            }
            
            // Restore collapsed states
            if (stored.states) {
                Object.entries(stored.states).forEach(([legendType, state]) => {
                    const legend = document.querySelector(`.legend-container[data-legend="${legendType}"]`);
                    const bar = document.getElementById(legendType + '-legend-bar');
                    
                    if (state === 'collapsed') {
                        if (legend) legend.classList.add('legend-hidden');
                        if (bar) bar.classList.remove('legend-bar-hidden');
                    }
                });
            }
        } catch (e) {
            console.warn('Failed to load legend settings:', e);
        }
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Delay initialization to ensure map wrapper exists
    setTimeout(() => {
        MapApp.legend.init();
    }, 100);
});
