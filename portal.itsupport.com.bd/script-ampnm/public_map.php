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
        </section>
    </div>

    <script src="https://unpkg.com/vis-network@9.1.9/dist/vis-network.min.js"></script>
    <script type="module" src="assets/js/public-map.js"></script>
</body>
</html>
