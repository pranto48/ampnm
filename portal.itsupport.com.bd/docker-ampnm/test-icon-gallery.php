<?php
/**
 * Icon Gallery Test Page
 * Interactive preview of all 200+ device icons
 * Access: /test-icon-gallery.php
 */

require_once 'includes/functions.php';
require_once 'includes/auth_check.php';

// Load device icons library
$deviceIconsLibrary = require_once 'includes/device_icons.php';

// Calculate statistics
$totalTypes = count($deviceIconsLibrary);
$totalIcons = 0;
foreach ($deviceIconsLibrary as $type) {
    $totalIcons += count($type['icons']);
}

include 'header.php';
?>

<style>
/* Enhanced styles for test page */
.test-gallery-container {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 16px;
    padding: 32px;
    margin: 20px 0;
}

.test-category-section {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    transition: all 0.3s ease;
}

.test-category-section:hover {
    background: rgba(30, 41, 59, 0.8);
    border-color: rgba(34, 197, 94, 0.4);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(34, 197, 94, 0.1);
}

.test-category-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid rgba(34, 197, 94, 0.3);
}

.test-category-title {
    font-size: 20px;
    font-weight: 700;
    color: #86efac;
    display: flex;
    align-items: center;
    gap: 12px;
}

.test-category-badge {
    background: rgba(34, 197, 94, 0.2);
    color: #86efac;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.test-icon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 16px;
    margin-top: 16px;
}

.test-icon-card {
    background: linear-gradient(135deg, rgba(51, 65, 85, 0.4) 0%, rgba(71, 85, 105, 0.2) 100%);
    border: 2px solid rgba(148, 163, 184, 0.15);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.test-icon-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(34, 197, 94, 0.15), transparent);
    transition: left 0.6s ease;
}

.test-icon-card:hover::before {
    left: 100%;
}

.test-icon-card:hover {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.15) 0%, rgba(34, 197, 94, 0.05) 100%);
    border-color: rgba(34, 197, 94, 0.6);
    transform: translateY(-8px) scale(1.05);
    box-shadow: 0 12px 28px rgba(34, 197, 94, 0.2);
}

.test-icon-display {
    font-size: 48px;
    color: #22d3ee;
    margin-bottom: 12px;
    transition: all 0.3s ease;
}

.test-icon-card:hover .test-icon-display {
    transform: scale(1.2) rotate(10deg);
    color: #86efac;
    text-shadow: 0 0 20px rgba(34, 197, 94, 0.5);
}

.test-icon-name {
    font-size: 11px;
    color: rgba(226, 232, 240, 0.8);
    font-weight: 500;
    line-height: 1.4;
    margin-top: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.test-icon-class {
    font-size: 9px;
    color: rgba(148, 163, 184, 0.6);
    margin-top: 4px;
    font-family: 'Courier New', monospace;
    background: rgba(0, 0, 0, 0.3);
    padding: 4px 8px;
    border-radius: 4px;
}

.test-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}

.test-stat-card {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2) 0%, rgba(34, 197, 94, 0.05) 100%);
    border: 1px solid rgba(34, 197, 94, 0.3);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}

.test-stat-value {
    font-size: 36px;
    font-weight: 700;
    color: #86efac;
    display: block;
}

.test-stat-label {
    font-size: 12px;
    color: rgba(226, 232, 240, 0.7);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-top: 8px;
}

.test-search-box {
    position: sticky;
    top: 80px;
    z-index: 100;
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(10px);
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 24px;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.test-search-input {
    width: 100%;
    padding: 12px 16px;
    padding-left: 44px;
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(148, 163, 184, 0.3);
    border-radius: 8px;
    color: #e2e8f0;
    font-size: 14px;
    transition: all 0.3s ease;
}

.test-search-input:focus {
    outline: none;
    border-color: rgba(34, 197, 94, 0.6);
    background: rgba(30, 41, 59, 1);
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
}

.test-search-wrapper {
    position: relative;
}

.test-search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(148, 163, 184, 0.5);
    font-size: 16px;
}

.test-new-badge {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    font-size: 9px;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 700;
    text-transform: uppercase;
    margin-left: 8px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

.test-copy-notification {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: rgba(34, 197, 94, 0.9);
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.3s ease;
    z-index: 1000;
}

.test-copy-notification.show {
    transform: translateY(0);
    opacity: 1;
}
</style>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-4xl font-bold text-white mb-2">🎨 Icon Gallery Test</h1>
                <p class="text-slate-400">Interactive preview of all <?= $totalIcons ?>+ device icons</p>
            </div>
            <a href="create-device.php" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                <i class="fas fa-arrow-left mr-2"></i>Back to Create Device
            </a>
        </div>

        <!-- Statistics -->
        <div class="test-stats-grid">
            <div class="test-stat-card">
                <span class="test-stat-value"><?= $totalTypes ?></span>
                <span class="test-stat-label">Device Types</span>
            </div>
            <div class="test-stat-card">
                <span class="test-stat-value"><?= $totalIcons ?>+</span>
                <span class="test-stat-label">Total Icons</span>
            </div>
            <div class="test-stat-card">
                <span class="test-stat-value"><?= round($totalIcons / $totalTypes, 1) ?></span>
                <span class="test-stat-label">Avg Icons/Type</span>
            </div>
            <div class="test-stat-card">
                <span class="test-stat-value">163%</span>
                <span class="test-stat-label">Expansion</span>
            </div>
        </div>

        <!-- Search Box -->
        <div class="test-search-box">
            <div class="test-search-wrapper">
                <i class="fas fa-search test-search-icon"></i>
                <input 
                    type="text" 
                    id="iconSearchInput" 
                    class="test-search-input" 
                    placeholder="Search icons by name, type, or keyword... (e.g., 'router', 'wifi', 'server')"
                    autocomplete="off"
                >
            </div>
            <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                <span>💡 Tip: Click any icon to copy its Font Awesome class</span>
                <span id="searchResultCount"></span>
            </div>
        </div>

        <!-- Icon Gallery -->
        <div class="test-gallery-container">
            <?php 
            $newCategories = ['ups', 'modem', 'loadbalancer', 'iot', 'monitor', 'keyboard'];
            foreach ($deviceIconsLibrary as $typeKey => $typeData): 
                $isNew = in_array($typeKey, $newCategories);
            ?>
                <div class="test-category-section" data-category="<?= htmlspecialchars($typeKey) ?>">
                    <div class="test-category-header">
                        <h2 class="test-category-title">
                            <i class="fas <?= htmlspecialchars($typeData['icons'][0]['icon']) ?>"></i>
                            <?= htmlspecialchars($typeData['label']) ?>
                            <?php if ($isNew): ?>
                                <span class="test-new-badge">NEW</span>
                            <?php endif; ?>
                        </h2>
                        <span class="test-category-badge"><?= count($typeData['icons']) ?> variants</span>
                    </div>
                    
                    <div class="test-icon-grid">
                        <?php foreach ($typeData['icons'] as $iconData): ?>
                            <div class="test-icon-card" 
                                 data-icon-class="<?= htmlspecialchars($iconData['icon']) ?>"
                                 data-icon-label="<?= htmlspecialchars($iconData['label']) ?>"
                                 onclick="copyIconClass('<?= htmlspecialchars($iconData['icon']) ?>', this)">
                                <div class="test-icon-display">
                                    <i class="fas <?= htmlspecialchars($iconData['icon']) ?>"></i>
                                </div>
                                <div class="test-icon-name"><?= htmlspecialchars($iconData['label']) ?></div>
                                <div class="test-icon-class"><?= htmlspecialchars($iconData['icon']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Footer Info -->
        <div class="mt-8 p-6 bg-slate-800 rounded-lg border border-slate-700">
            <h3 class="text-lg font-bold text-white mb-4">📚 How to Use</h3>
            <ul class="space-y-2 text-slate-300">
                <li><strong>✨ Browse:</strong> Scroll through all <?= $totalIcons ?>+ icons organized by device type</li>
                <li><strong>🔍 Search:</strong> Use the search box to filter icons by name or keyword</li>
                <li><strong>📋 Copy:</strong> Click any icon to copy its Font Awesome class to clipboard</li>
                <li><strong>🎨 Preview:</strong> Hover over icons to see animation effects</li>
                <li><strong>🚀 Use:</strong> Go to Create/Edit Device to use these icons in your network map</li>
            </ul>
        </div>
    </div>
</main>

<!-- Copy Notification -->
<div id="copyNotification" class="test-copy-notification">
    <i class="fas fa-check-circle mr-2"></i>
    <span id="copyNotificationText">Icon class copied!</span>
</div>

<script>
// Search functionality
const searchInput = document.getElementById('iconSearchInput');
const resultCount = document.getElementById('searchResultCount');
const allCategories = document.querySelectorAll('.test-category-section');
const allCards = document.querySelectorAll('.test-icon-card');

let totalVisible = allCards.length;

searchInput.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase().trim();
    let visibleCount = 0;
    let visibleCategoryCount = 0;

    allCategories.forEach(category => {
        const cards = category.querySelectorAll('.test-icon-card');
        let categoryHasVisible = false;

        cards.forEach(card => {
            const iconClass = card.dataset.iconClass.toLowerCase();
            const iconLabel = card.dataset.iconLabel.toLowerCase();
            const categoryName = category.dataset.category.toLowerCase();
            
            const matches = !searchTerm || 
                iconClass.includes(searchTerm) || 
                iconLabel.includes(searchTerm) ||
                categoryName.includes(searchTerm);
            
            card.style.display = matches ? 'block' : 'none';
            
            if (matches) {
                visibleCount++;
                categoryHasVisible = true;
            }
        });

        category.style.display = categoryHasVisible ? 'block' : 'none';
        if (categoryHasVisible) visibleCategoryCount++;
    });

    // Update result count
    if (searchTerm) {
        resultCount.textContent = `Found ${visibleCount} icon${visibleCount !== 1 ? 's' : ''} in ${visibleCategoryCount} categor${visibleCategoryCount !== 1 ? 'ies' : 'y'}`;
        resultCount.style.color = visibleCount > 0 ? '#86efac' : '#ef4444';
    } else {
        resultCount.textContent = `Showing all ${totalVisible} icons`;
        resultCount.style.color = '#94a3b8';
    }
});

// Copy icon class to clipboard
function copyIconClass(iconClass, element) {
    // Copy to clipboard
    navigator.clipboard.writeText(iconClass).then(() => {
        // Show notification
        const notification = document.getElementById('copyNotification');
        const notificationText = document.getElementById('copyNotificationText');
        notificationText.textContent = `Copied: ${iconClass}`;
        
        notification.classList.add('show');
        
        // Add temporary highlight to clicked card
        element.style.background = 'linear-gradient(135deg, rgba(34, 197, 94, 0.3) 0%, rgba(34, 197, 94, 0.1) 100%)';
        element.style.borderColor = 'rgba(34, 197, 94, 0.8)';
        
        setTimeout(() => {
            notification.classList.remove('show');
            element.style.background = '';
            element.style.borderColor = '';
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy:', err);
        alert('Failed to copy. Icon class: ' + iconClass);
    });
}

// Initialize result count
resultCount.textContent = `Showing all ${totalVisible} icons`;
resultCount.style.color = '#94a3b8';

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + K to focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        searchInput.focus();
    }
    
    // Escape to clear search
    if (e.key === 'Escape' && document.activeElement === searchInput) {
        searchInput.value = '';
        searchInput.dispatchEvent(new Event('input'));
    }
});

console.log('🎨 Icon Gallery loaded successfully!');
console.log('📊 Statistics:', {
    deviceTypes: <?= $totalTypes ?>,
    totalIcons: <?= $totalIcons ?>,
    avgIconsPerType: <?= round($totalIcons / $totalTypes, 1) ?>
});
</script>

<?php include 'footer.php'; ?>
