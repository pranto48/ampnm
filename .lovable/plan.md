

# Fix map.php Edge Error + Add Port Visualization to create-device.php

## Problem 1: `updateEdgeColorsAndDashes is not a function`

**Root cause**: In `docker-ampnm/assets/js/map.js` line 85, the edge form submit handler calls `MapApp.ui.updateEdgeColorsAndDashes()`, but the actual function name in `ui.js` is `MapApp.ui.updateAndAnimateEdges()`.

**Fix**: Change line 85 in `map.js` from `updateEdgeColorsAndDashes()` to `updateAndAnimateEdges()`.

## Problem 2: Port Visualization on create-device.php

Add a visual port panel to the Add New Device form that shows networking port counts and a visual port grid based on the selected device type. When the user selects switch, router, firewall, or server, they see:
- Total ports, used ports, and free ports
- A visual grid of port indicators (like the Docker map's DevicePortGrid)

### Changes to `docker-ampnm/create-device.php`

Add a new fieldset section after the Device Type selector:

- Title: "Network Ports"
- Summary cards: Total Ports | Used Ports | Free Ports
- Visual port grid showing all ports as small colored rectangles with port names
- Port grid updates dynamically when device type changes

Port counts per device type:
- **Switch**: 24x GigabitEthernet (G0/1-G0/24) + 4x SFP (SFP01-SFP04) = 28 ports
- **Router**: 4x GigabitEthernet (G0/0-G0/3) + 2x Serial (S0/0-S0/1) + 1x SFP (SFP01) = 7 ports
- **Firewall**: 8x GigabitEthernet (G0/0-G0/7) + 2x Management (Mgmt0/0-Mgmt0/1) = 10 ports
- **Server**: 4x GigabitEthernet (G0/0-G0/3) = 4 ports
- **Other device types**: 2x GigabitEthernet (G0/0-G0/1) = 2 ports

### Implementation

Add inline JavaScript (or extend `icon-picker.js`) that listens to the `#type` select change event and renders port grid HTML into a new container `#devicePortPanel`. Each port is a small rectangle with:
- Green border = free/available
- Port name tooltip on hover
- Summary counts above the grid

### Files to modify

1. **`docker-ampnm/assets/js/map.js`** (line 85) -- Fix function name: `updateEdgeColorsAndDashes` → `updateAndAnimateEdges`
2. **`docker-ampnm/create-device.php`** -- Add port visualization fieldset with inline JS after the device type selector

