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

    const nodes = devices.map((device) => {
        const status = (device.status || "unknown").toLowerCase();
        const color = colorByStatus[status] || colorByStatus.unknown;
        
        return {
            id: device.id,
            label: device.name || device.ip || `Device ${device.id}`,
            title: buildTitle(device),
            shape: device.icon_url ? "image" : "dot",
            image: device.icon_url || undefined,
            size: device.icon_size ? Number(device.icon_size) / 1.5 : 24,
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