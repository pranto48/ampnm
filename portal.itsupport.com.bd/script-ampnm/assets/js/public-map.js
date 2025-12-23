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
    loader.hidden = true;
    statusMessage.querySelector(".text").textContent = "Live view ready";
    statusMessage.querySelector(".dot").classList.add("pulse");

    mapTitle.textContent = map?.name || "Shared network map";
    mapSubtitle.textContent = map?.public_view_enabled ? "Public viewing enabled" : "Read-only preview";
    metaSummary.textContent = `${devices.length} devices • ${edges.length} links`;

    // Font Awesome icon mapping based on device type and subchoice
    const iconLibrary = {
        switch: ['fa-network-wired', 'fa-diagram-project', 'fa-sitemap', 'fa-bezier-curve', 'fa-code-branch', 'fa-shuffle', 'fa-arrows-split-up-and-left', 'fa-circle-nodes'],
        router: ['fa-router', 'fa-globe', 'fa-wifi', 'fa-arrows-left-right', 'fa-share-nodes', 'fa-tower-broadcast', 'fa-earth-americas', 'fa-network-wired'],
        server: ['fa-server', 'fa-database', 'fa-hard-drive', 'fa-microchip', 'fa-memory', 'fa-computer', 'fa-desktop', 'fa-hdd'],
        computer: ['fa-desktop', 'fa-computer', 'fa-display', 'fa-tv', 'fa-laptop', 'fa-keyboard', 'fa-personal-computer', 'fa-monitor-waveform'],
        laptop: ['fa-laptop', 'fa-laptop-code', 'fa-laptop-file', 'fa-laptop-medical', 'fa-display', 'fa-computer', 'fa-file-laptop', 'fa-note-laptop'],
        phone: ['fa-mobile', 'fa-mobile-screen', 'fa-phone', 'fa-tablet', 'fa-mobile-retro', 'fa-tablet-screen-button', 'fa-mobile-alt', 'fa-tablet-alt'],
        printer: ['fa-print', 'fa-fax', 'fa-file-export', 'fa-file-arrow-down', 'fa-scanner', 'fa-copy', 'fa-scroll', 'fa-rectangle-list'],
        camera: ['fa-camera', 'fa-video', 'fa-webcam', 'fa-eye', 'fa-record-vinyl', 'fa-camera-retro', 'fa-camera-movie', 'fa-surveillance'],
        firewall: ['fa-shield-halved', 'fa-shield', 'fa-lock', 'fa-user-shield', 'fa-fire', 'fa-fire-flame-curved', 'fa-shield-keyhole', 'fa-firewall'],
        cloud: ['fa-cloud', 'fa-cloud-arrow-up', 'fa-cloud-arrow-down', 'fa-server', 'fa-globe', 'fa-network-wired', 'fa-cloud-bolt', 'fa-clouds'],
        wifi: ['fa-wifi', 'fa-signal', 'fa-tower-broadcast', 'fa-satellite-dish', 'fa-broadcast-tower', 'fa-antenna', 'fa-wifi-strong', 'fa-signal-bars'],
        storage: ['fa-hdd', 'fa-database', 'fa-layer-group', 'fa-cubes', 'fa-boxes-stacked', 'fa-archive', 'fa-warehouse', 'fa-vault'],
        generic: ['fa-circle', 'fa-square', 'fa-diamond', 'fa-star', 'fa-bookmark', 'fa-tag', 'fa-certificate', 'fa-award']
    };

    const getIconClass = (type, subchoice) => {
        const icons = iconLibrary[type] || iconLibrary.generic;
        const index = parseInt(subchoice, 10) || 0;
        return icons[index] || icons[0];
    };

    const nodes = devices.map((device) => {
        const colorByStatus = {
            online: "#22c55e",
            offline: "#ef4444",
            warning: "#f59e0b",
            critical: "#dc2626",
        };

        // Build node configuration
        const nodeConfig = {
            id: device.id,
            label: device.name || device.ip || `Device ${device.id}`,
            title: buildTitle(device),
            size: device.icon_size ? Number(device.icon_size) / 1.5 : 18,
            x: device.x ?? undefined,
            y: device.y ?? undefined,
            font: { color: "#e2e8f0", size: device.name_text_size ? Number(device.name_text_size) : 14 },
            color: colorByStatus[device.status] || "#38bdf8",
        };

        // Use custom icon_url if set, otherwise use Font Awesome icon
        if (device.icon_url) {
            nodeConfig.shape = "image";
            nodeConfig.image = device.icon_url;
        } else if (device.type) {
            // Use Font Awesome icon based on type and subchoice
            nodeConfig.shape = "icon";
            nodeConfig.icon = {
                face: "'Font Awesome 6 Free'",
                code: getIconClass(device.type, device.subchoice || 0),
                size: device.icon_size ? Number(device.icon_size) : 30,
                color: colorByStatus[device.status] || "#38bdf8",
            };
        } else {
            nodeConfig.shape = "dot";
        }

        return nodeConfig;
    });

    const edgeStyles = {
        cat5: { color: "#38bdf8", dashes: false },
        fiber: { color: "#a78bfa", dashes: false },
        wifi: { color: "#fbbf24", dashes: true },
        radio: { color: "#f472b6", dashes: true },
        lan: { color: "#22d3ee", dashes: false },
        "logical-tunneling": { color: "#94a3b8", dashes: [6, 4] },
    };

    const visEdges = edges.map((edge) => {
        const style = edgeStyles[edge.connection_type] || edgeStyles.cat5;
        return {
            from: edge.source_id,
            to: edge.target_id,
            color: { color: style.color },
            dashes: style.dashes,
            width: 2,
        };
    });

    const data = {
        nodes: new vis.DataSet(nodes),
        edges: new vis.DataSet(visEdges),
    };

    const options = {
        interaction: { hover: true },
        physics: { stabilization: true, barnesHut: { damping: 0.18 } },
        layout: { improvedLayout: true },
        edges: { smooth: { type: "dynamic" } },
        nodes: { borderWidth: 1, shadow: true },
    };

    if (map?.background_color) {
        canvas.style.background = map.background_color;
    }
    if (map?.background_image_url) {
        canvas.style.backgroundImage = `url(${map.background_image_url})`;
        canvas.style.backgroundSize = "cover";
        canvas.style.backgroundPosition = "center";
    }

    new vis.Network(canvas, data, options);
}

async function loadMap() {
    if (!mapId) {
        showError("No map selected", "Append ?map_id=123 to view a shared map.");
        return;
    }

    const link = `${window.location.origin}${window.location.pathname}?map_id=${mapId}`;
    copyLinkBtn.addEventListener("click", async () => {
        try {
            await navigator.clipboard.writeText(link);
            statusMessage.querySelector(".text").textContent = "Link copied";
            setTimeout(() => (statusMessage.querySelector(".text").textContent = "Live view ready"), 2000);
        } catch (err) {
            alert(`Copy failed. Link: ${link}`);
        }
    });

    try {
        const response = await fetch(`api.php?action=get_public_map_data&map_id=${mapId}`);
        if (!response.ok) {
            const detail = await response.text();
            showError("The map could not be loaded.", detail);
            return;
        }
        const payload = await response.json();
        if (!payload?.map) {
            showError("No map data returned", "Ensure public view is enabled for this map.");
            return;
        }
        renderMap(payload);
    } catch (error) {
        showError("Unexpected error", error.message);
    }
}

loadMap();
