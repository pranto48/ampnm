const mapId = new URLSearchParams(window.location.search).get("map_id");
const canvas = document.getElementById("mapCanvas");
const loader = document.getElementById("mapLoader");
const errorCard = document.getElementById("mapError");
const statusMessage = document.getElementById("statusMessage");
const metaSummary = document.getElementById("metaSummary");
const mapTitle = document.getElementById("mapTitle");
const mapSubtitle = document.getElementById("mapSubtitle");
const copyLinkBtn = document.getElementById("copyLinkBtn");

function showError(message, detail = "") {
    console.error("Map Error:", message, detail);
    loader.hidden = true;
    errorCard.hidden = false;
    errorCard.innerHTML = `
        <div>
            <i class="fa-solid fa-triangle-exclamation fa-2x"></i>
            <h3>Unable to load the map</h3>
            <p>${message}</p>
            ${detail ? `<p id="errorDetails">${detail}</p>` : ""}
        </div>
    `;
    statusMessage.querySelector(".text").textContent = "Load failed";
    statusMessage.querySelector(".dot").style.background = "#f87171";
    statusMessage.querySelector(".dot").classList.remove("pulse");
}

function buildTitle(device) {
    const status = device.status || "unknown";
    const statusLine = `Status: ${status}`;
    const ipLine = device.ip ? `IP: ${device.ip}` : "No IP assigned";
    const latency = device.last_avg_time ? `Latency: ${device.last_avg_time}ms` : null;
    const ttl = device.last_ttl ? `TTL: ${device.last_ttl}` : null;
    const extras = [latency, ttl].filter(Boolean).join(" · ");
    return [device.name || "Unnamed", ipLine, statusLine, extras].filter(Boolean).join("<br>");
}

function renderMap({ map, devices, edges }) {
    console.log("Rendering map with", devices.length, "devices and", edges.length, "edges");
    
    mapTitle.textContent = map?.name || "Shared network map";
    mapSubtitle.textContent = map?.public_view_enabled ? "Public viewing enabled" : "Read-only preview";
    metaSummary.textContent = `${devices.length} devices • ${edges.length} links`;

    const colorByStatus = {
        online: "#22c55e",
        offline: "#64748b",
        warning: "#f59e0b",
        critical: "#ef4444",
        unknown: "#94a3b8"
    };

    const iconMap = {
        'fa-network-wired': '\uf6ff',
        'fa-router': '\uf8da', 
        'fa-circle-nodes': '\ue4e3',
        'fa-sitemap': '\uf0e8',
        'fa-diagram-project': '\uf542',
        'fa-share-nodes': '\uf1e0',
        'fa-bezier-curve': '\uf55b',
        'fa-wifi': '\uf1eb',
        'fa-tower-broadcast': '\uf519',
        'fa-radio': '\uf8d7',
        'fa-signal': '\uf012',
        'fa-broadcast-tower': '\uf519',
        'fa-rss': '\uf09e',
        'fa-podcast': '\uf2ce',
        'fa-satellite-dish': '\uf7c0',
        'fa-server': '\uf233',
        'fa-tower-cell': '\ue585',
        'fa-computer': '\uf108',
        'fa-microchip': '\uf2db',
        'fa-memory': '\uf538',
        'fa-hard-drive': '\uf0a0',
        'fa-hdd': '\uf0a0',
        'fa-compact-disc': '\uf51f',
        'fa-warehouse': '\uf494',
        'fa-industry': '\uf275',
        'fa-ethernet': '\uf796',
        'fa-code-branch': '\uf126',
        'fa-object-group': '\uf247',
        'fa-layer-group': '\uf5fd',
        'fa-grip-horizontal': '\uf58d',
        'fa-bars': '\uf0c9',
        'fa-sliders': '\uf1de',
        'fa-table-cells': '\uf00a',
        'fa-shield-halved': '\uf3ed',
        'fa-shield': '\uf132',
        'fa-lock': '\uf023',
        'fa-shield-virus': '\ue06c',
        'fa-user-shield': '\uf505',
        'fa-fingerprint': '\uf577',
        'fa-key': '\uf084',
        'fa-user-lock': '\uf13e',
        'fa-ban': '\uf05e',
        'fa-circle-exclamation': '\uf06a',
        'fa-cloud': '\uf0c2',
        'fa-cloud-arrow-up': '\uf0ee',
        'fa-cloud-arrow-down': '\uf0ed',
        'fa-cloud-bolt': '\uf76c',
        'fa-cloudflare': '\ue07d',
        'fa-cloud-sun': '\uf6c4',
        'fa-wind': '\uf72e',
        'fa-database': '\uf1c0',
        'fa-table': '\uf0ce',
        'fa-table-columns': '\uf0db',
        'fa-table-list': '\uf00b',
        'fa-diagram-subtask': '\ue479',
        'fa-cubes': '\uf1b3',
        'fa-box-archive': '\uf187',
        'fa-file-zipper': '\uf1c6',
        'fa-laptop': '\uf109',
        'fa-laptop-code': '\uf5fc',
        'fa-laptop-file': '\ue51d',
        'fa-desktop': '\uf390',
        'fa-display': '\uf390',
        'fa-tv': '\uf26c',
        'fa-chalkboard': '\uf51b',
        'fa-tablet-screen-button': '\uf3fa',
        'fa-tablet': '\uf3fb',
        'fa-tablet-button': '\uf10a',
        'fa-square-full': '\uf45c',
        'fa-rectangle': '\uf2fa',
        'fa-window-maximize': '\uf2d0',
        'fa-mobile-screen': '\uf3cf',
        'fa-mobile-screen-button': '\uf3cd',
        'fa-mobile': '\uf3ce',
        'fa-mobile-retro': '\ue527',
        'fa-phone': '\uf095',
        'fa-phone-flip': '\uf879',
        'fa-phone-volume': '\uf2a0',
        'fa-walkie-talkie': '\uf8ef',
        'fa-print': '\uf02f',
        'fa-fax': '\uf1ac',
        'fa-file-pdf': '\uf1c1',
        'fa-file-image': '\uf1c5',
        'fa-copy': '\uf0c5',
        'fa-clone': '\uf24d',
        'fa-images': '\uf302',
        'fa-file': '\uf15b',
        'fa-video': '\uf03d',
        'fa-camera': '\uf030',
        'fa-camera-retro': '\uf083',
        'fa-camera-viewfinder': '\ue0da',
        'fa-eye': '\uf06e',
        'fa-glasses': '\uf530',
        'fa-binoculars': '\uf1e5',
        'fa-film': '\uf008',
        'fa-clapperboard': '\ue131',
        'fa-headset': '\uf590',
        'fa-headphones': '\uf025',
        'fa-voicemail': '\uf897',
        'fa-microphone': '\uf130',
        'fa-box': '\uf466',
        'fa-boxes-stacked': '\uf468',
        'fa-box-open': '\uf49e',
        'fa-cube': '\uf1b2',
        'fa-folder-open': '\uf07c',
        'fa-folder-tree': '\uf802',
        'fa-floppy-disk': '\uf0c7',
        'fa-sd-card': '\uf7c2',
        'fa-clock': '\uf017',
        'fa-stopwatch': '\uf2f2',
        'fa-id-card': '\uf2c2',
        'fa-address-card': '\uf2bb',
        'fa-user-check': '\uf4fc',
        'fa-calendar-check': '\uf274',
        'fa-plug': '\uf1e6',
        'fa-battery-full': '\uf240',
        'fa-battery-half': '\uf242',
        'fa-car-battery': '\uf5df',
        'fa-bolt': '\uf0e7',
        'fa-bolt-lightning': '\ue0b7',
        'fa-power-off': '\uf011',
        'fa-charging-station': '\uf5e7',
        'fa-scale-balanced': '\uf24e',
        'fa-balance-scale': '\uf24e',
        'fa-arrows-split-up': '\ue4bc',
        'fa-route': '\uf4d7',
        'fa-shuffle': '\uf074',
        'fa-repeat': '\uf363',
        'fa-arrows-turn-to-dots': '\ue4c1',
        'fa-lightbulb': '\uf0eb',
        'fa-house-signal': '\ue012',
        'fa-temperature-half': '\uf2c9',
        'fa-lock': '\uf023',
        'fa-volume-high': '\uf028',
        'fa-battery-full': '\uf240',
        'fa-plug-circle-bolt': '\ue55b',
        'fa-question-circle': '\uf059',
        'fa-microchip': '\uf2db',
        'fa-globe': '\uf0ac',
        'fa-location-dot': '\uf3c5',
        'fa-building': '\uf1ad',
        'fa-layer-group': '\uf5fd',
        'fa-door-closed': '\uf52a'
    };

    const nodes = devices.map((device) => {
        const status = (device.status || "unknown").toLowerCase();
        const color = colorByStatus[status] || colorByStatus.unknown;
        const iconClass = device.icon_class || "";
        const iconCode = iconMap[iconClass];
        const hasImageIcon = Boolean(device.icon_url);
        const hasFontIcon = Boolean(iconCode);
        const nodeSize = device.icon_size ? Number(device.icon_size) / 1.5 : 24;
        const nodeBase = {
            id: device.id,
            label: device.name || device.ip || `Device ${device.id}`,
            title: buildTitle(device),
            size: nodeSize,
            x: device.x ?? undefined,
            y: device.y ?? undefined,
            font: { 
                color: "#e2e8f0", 
                size: device.name_text_size ? Number(device.name_text_size) : 14,
                background: "rgba(15, 23, 42, 0.8)",
                strokeWidth: 0
            },
            color: {
                background: color,
                border: color,
                highlight: {
                    background: color,
                    border: "#ffffff"
                },
                hover: {
                    background: color,
                    border: "#ffffff"
                }
            },
            borderWidth: 2,
            borderWidthSelected: 3
        };
        
        if (hasImageIcon) {
            return {
                ...nodeBase,
                shape: "image",
                image: device.icon_url
            };
        }

        if (hasFontIcon) {
            return {
                ...nodeBase,
                shape: "icon",
                icon: {
                    face: "'Font Awesome 6 Free'",
                    weight: "900",
                    code: iconCode,
                    size: device.icon_size ? Number(device.icon_size) : 50,
                    color
                }
            };
        }

        return {
            ...nodeBase,
            shape: "dot"
        };
    });

    const edgeStyles = {
        cat5: { color: "#a78bfa", dashes: false },
        fiber: { color: "#f97316", dashes: false },
        wifi: { color: "#38bdf8", dashes: true },
        radio: { color: "#84cc16", dashes: true },
        lan: { color: "#60a5fa", dashes: false },
        "logical-tunneling": { color: "#c084fc", dashes: [6, 4] },
        tunnel: { color: "#c084fc", dashes: [6, 4] }
    };

    const visEdges = edges.map((edge) => {
        const connType = (edge.connection_type || "cat5").toLowerCase();
        const style = edgeStyles[connType] || edgeStyles.cat5;
        return {
            from: edge.source_id,
            to: edge.target_id,
            color: { color: style.color },
            dashes: style.dashes || false,
            width: 2,
            smooth: {
                type: "dynamic",
                roundness: 0.5
            }
        };
    });

    const data = {
        nodes: new vis.DataSet(nodes),
        edges: new vis.DataSet(visEdges),
    };

    const options = {
        interaction: { 
            hover: true,
            navigationButtons: true,
            keyboard: true,
            zoomView: true,
            dragView: true
        },
        physics: { 
            enabled: true,
            stabilization: {
                enabled: true,
                iterations: 100,
                updateInterval: 25
            },
            barnesHut: { 
                gravitationalConstant: -2000,
                centralGravity: 0.3,
                springLength: 95,
                springConstant: 0.04,
                damping: 0.09,
                avoidOverlap: 0.1
            } 
        },
        layout: { 
            improvedLayout: true,
            randomSeed: 2
        },
        edges: { 
            smooth: { 
                type: "dynamic",
                roundness: 0.5
            },
            width: 2
        },
        nodes: { 
            borderWidth: 2,
            shadow: {
                enabled: true,
                color: "rgba(0,0,0,0.3)",
                size: 10,
                x: 0,
                y: 0
            },
            font: {
                size: 14,
                color: "#e2e8f0"
            }
        },
    };

    // Apply map background settings
    if (map?.background_color) {
        canvas.style.backgroundColor = map.background_color;
    } else {
        canvas.style.backgroundColor = "#0f172a";
    }
    
    if (map?.background_image_url) {
        canvas.style.backgroundImage = `url(${map.background_image_url})`;
        canvas.style.backgroundSize = "cover";
        canvas.style.backgroundPosition = "center";
        canvas.style.backgroundRepeat = "no-repeat";
    }

    // Make canvas visible
    canvas.style.width = "100%";
    canvas.style.height = "100%";
    canvas.style.display = "block";

    try {
        console.log("Creating vis.Network instance...");
        const network = new vis.Network(canvas, data, options);
        
        network.on("stabilizationIterationsDone", function() {
            console.log("Network stabilization complete");
            network.setOptions({ physics: false });
        });
        
        network.on("stabilizationProgress", function(params) {
            const progress = Math.round((params.iterations / params.total) * 100);
            if (progress % 20 === 0) {
                console.log(`Stabilization progress: ${progress}%`);
            }
        });
        
        console.log("vis.Network instance created successfully");
    } catch (error) {
        console.error("Error creating vis.Network:", error);
        showError("Failed to render network visualization", error.message);
        return;
    }
}

async function fetchWithTimeout(url, options = {}, timeout = 15000) {
    const controller = new AbortController();
    const id = setTimeout(() => controller.abort(), timeout);

    try {
        console.log("Fetching:", url);
        const response = await fetch(url, { ...options, signal: controller.signal });
        clearTimeout(id);
        console.log("Response status:", response.status);
        return response;
    } catch (error) {
        clearTimeout(id);
        console.error("Fetch error:", error);
        throw error;
    }
}

async function loadMap() {
    console.log("Loading map with ID:", mapId);
    
    if (!mapId) {
        showError("No map selected", "Append ?map_id=123 to view a shared map.");
        return;
    }

    const link = `${window.location.origin}${window.location.pathname}?map_id=${mapId}`;
    copyLinkBtn.addEventListener("click", async () => {
        try {
            await navigator.clipboard.writeText(link);
            statusMessage.querySelector(".text").textContent = "Link copied";
            setTimeout(() => {
                const currentText = statusMessage.querySelector(".text").textContent;
                if (currentText === "Link copied") {
                    statusMessage.querySelector(".text").textContent = "Live view ready";
                }
            }, 2000);
        } catch (err) {
            console.error("Copy failed:", err);
            prompt("Copy this link:", link);
        }
    });

    try {
        const response = await fetchWithTimeout(
            `api.php?action=get_public_map_data&map_id=${mapId}`,
            {},
            15000
        );
        
        if (!response.ok) {
            const contentType = response.headers.get("content-type");
            let detail = "Server returned an error";
            
            if (contentType && contentType.includes("application/json")) {
                try {
                    const errorData = await response.json();
                    detail = errorData.error || errorData.message || detail;
                } catch (e) {
                    detail = await response.text();
                }
            } else {
                detail = await response.text();
            }
            
            console.error("API error:", detail);
            showError("The map could not be loaded.", detail);
            return;
        }
        
        const payload = await response.json();
        console.log("Map data received:", payload);
        
        if (!payload?.map) {
            showError("No map data returned", "Ensure public view is enabled for this map.");
            return;
        }
        
        const hasDevices = Array.isArray(payload.devices) && payload.devices.length > 0;
        
        if (!hasDevices) {
            console.warn("No devices in map");
            statusMessage.querySelector(".text").textContent = "No devices published";
            statusMessage.querySelector(".dot").classList.remove("pulse");
            loader.querySelector("p").textContent = "No devices have been shared for this map yet.";
            
            // Still render empty map with background
            loader.hidden = true;
            renderMap(payload);
            initLegends();
        } else {
            console.log("Rendering map with devices...");
            statusMessage.querySelector(".text").textContent = "Live view ready";
            statusMessage.querySelector(".dot").style.background = "#22c55e";
            statusMessage.querySelector(".dot").classList.add("pulse");
            loader.hidden = true;
            canvas.style.display = "block";
            
            renderMap(payload);
            initLegends();
        }
    } catch (error) {
        console.error("Load map error:", error);
        const message = error.name === "AbortError" ? "Map request timed out" : "Unexpected error";
        showError(message, error.message || "Check browser console for details");
    }
}

// Legend toggle functionality
function initLegends() {
    console.log("Initializing legends");
    
    // Toggle buttons
    document.querySelectorAll('.legend-toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const legend = btn.closest('.legend-container');
            const legendType = legend.dataset.legend;
            const bar = document.getElementById(legendType + '-legend-bar');
            legend.classList.add('legend-hidden');
            if (bar) bar.classList.remove('legend-bar-hidden');
        });
    });
    
    // Collapsed bar click to expand
    document.querySelectorAll('.legend-bar').forEach(bar => {
        bar.addEventListener('click', () => {
            const legendType = bar.dataset.legend;
            const legend = document.getElementById(legendType === 'status' ? 'status-legend-container' : 'connection-legend');
            if (legend) {
                legend.classList.remove('legend-hidden');
                bar.classList.add('legend-bar-hidden');
            }
        });
    });
    
    console.log("Legends initialized");
}

// Check if vis is loaded
if (typeof vis === 'undefined') {
    console.error("vis.js library not loaded!");
    showError("Visualization library failed to load", "Please refresh the page");
} else {
    console.log("vis.js loaded successfully");
    loadMap();
}
