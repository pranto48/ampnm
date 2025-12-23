<?php
// Public map viewer (no auth required)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Network Map</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://unpkg.com/vis-network@9.1.9/dist/vis-network.min.css" />
    <link rel="stylesheet" href="assets/css/public-map.css">
</head>
<body>
    <div class="page-shell">
        <header class="page-header">
            <div class="title-block">
                <p class="eyebrow">AMPNM Shared Map</p>
                <h1 id="mapTitle">Loading map...</h1>
                <p id="mapSubtitle" class="subtitle">Preparing a read-only view you can share.</p>
            </div>
            <div class="actions">
                <button id="copyLinkBtn" class="pill-action">
                    <i class="fa-solid fa-link"></i>
                    <span>Copy share link</span>
                </button>
                <a id="openAdminBtn" class="pill-action subtle" href="login.php">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    <span>Open admin portal</span>
                </a>
            </div>
        </header>

        <section class="status-strip" id="statusStrip">
            <div class="status-pill" id="statusMessage">
                <span class="dot pulse"></span>
                <span class="text">Fetching map...</span>
            </div>
            <div class="meta" id="metaSummary"></div>
        </section>

        <section class="map-frame">
            <div id="mapLoader" class="loader-card">
                <div class="spinner"></div>
                <p>Loading topology and devices...</p>
            </div>
            <div id="mapError" class="error-card" hidden></div>
            <div id="mapCanvas"></div>
            
            <!-- Status Legend (Draggable) -->
            <div id="status-legend-container" class="legend-container" data-legend="status">
                <div class="legend-header">
                    <span class="legend-drag-handle"><i class="fas fa-grip-vertical"></i></span>
                    <span class="legend-title">Device Status</span>
                    <button class="legend-toggle-btn" data-target="status-legend-content" title="Hide Legend">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
                <div id="status-legend-content" class="legend-content">
                    <div id="status-legend">
                        <div class="legend-item"><div class="legend-dot" style="background-color: #22c55e;"></div><span>Online</span></div>
                        <div class="legend-item"><div class="legend-dot" style="background-color: #f59e0b;"></div><span>Warning</span></div>
                        <div class="legend-item"><div class="legend-dot" style="background-color: #dc2626;"></div><span>Critical</span></div>
                        <div class="legend-item"><div class="legend-dot" style="background-color: #64748b;"></div><span>Offline</span></div>
                        <div class="legend-item"><div class="legend-dot" style="background-color: #94a3b8;"></div><span>Unknown</span></div>
                    </div>
                </div>
            </div>
            
            <!-- Collapsed Status Legend Bar -->
            <button id="status-legend-bar" class="legend-bar legend-bar-hidden" data-legend="status">
                <i class="fas fa-circle" style="color: #22c55e;"></i>
                <span>Status</span>
            </button>
            
            <!-- Connection Types Legend (Draggable) -->
            <div id="connection-legend" class="legend-container" data-legend="connection">
                <div class="legend-header">
                    <span class="legend-drag-handle"><i class="fas fa-grip-vertical"></i></span>
                    <span class="legend-title"><i class="fas fa-project-diagram mr-2" style="color: #22d3ee;"></i>Connection Types</span>
                    <button class="legend-toggle-btn" data-target="connection-legend-content" title="Hide Legend">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
                <div id="connection-legend-content" class="legend-content">
                    <div class="connection-types">
                        <div class="conn-item"><div class="conn-line" style="background-color: #a78bfa;"></div><span>🔌 CAT5 Cable</span></div>
                        <div class="conn-item"><div class="conn-line" style="background-color: #f97316;"></div><span>💡 Fiber Optic</span></div>
                        <div class="conn-item"><div class="conn-line" style="background-color: #38bdf8;"></div><span>📡 WiFi</span></div>
                        <div class="conn-item"><div class="conn-line" style="background-color: #84cc16;"></div><span>📻 Radio</span></div>
                        <div class="conn-item"><div class="conn-line" style="background-color: #60a5fa;"></div><span>🌐 LAN</span></div>
                        <div class="conn-item"><div class="conn-line" style="background-color: #c084fc;"></div><span>🔒 Tunnel</span></div>
                    </div>
                </div>
            </div>
            
            <!-- Collapsed Connection Legend Bar -->
            <button id="connection-legend-bar" class="legend-bar legend-bar-hidden" data-legend="connection">
                <i class="fas fa-project-diagram" style="color: #22d3ee;"></i>
                <span>Connections</span>
            </button>
        </section>
    </div>

    <script src="https://unpkg.com/vis-network@9.1.9/dist/vis-network.min.js"></script>
    <script type="module" src="assets/js/public-map.js"></script>
</body>
</html>
