<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
// Public map viewer (no auth required)
$deviceIconsLibrary = require_once 'includes/device_icons.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Network Map</title>
    <!-- Animated SVG Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><defs><style>@keyframes spin{from{transform-origin:32px 32px;transform:rotate(0deg)}to{transform-origin:32px 32px;transform:rotate(360deg)}}@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}.ring{animation:spin 3s linear infinite}.dot{animation:pulse 1.5s ease-in-out infinite}</style></defs><circle cx='32' cy='32' r='28' fill='%230f172a' stroke='%2306b6d4' stroke-width='3'/><circle cx='32' cy='32' r='18' fill='none' stroke='%2322d3ee' stroke-width='1.5' stroke-dasharray='8 4' class='ring'/><circle cx='32' cy='32' r='9' fill='%2306b6d4'/><circle cx='32' cy='32' r='5' fill='%230f172a' class='dot'/><circle cx='32' cy='11' r='3' fill='%2322d3ee' class='dot'/><circle cx='53' cy='43' r='3' fill='%2322d3ee' class='dot' style='animation-delay:.5s'/><circle cx='11' cy='43' r='3' fill='%2322d3ee' class='dot' style='animation-delay:1s'/></svg>">
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

    <!-- Load device icons library for JavaScript icon mapping -->
    <script>
        window.deviceIconsLibrary = <?= json_encode($deviceIconsLibrary) ?>;
    </script>
    <script src="https://unpkg.com/vis-network@9.1.9/dist/vis-network.min.js"></script>
    <script type="module" src="assets/js/public-map.js?v=<?= time() ?>"></script>
</body>
</html>
