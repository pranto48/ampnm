

# Cisco Packet Tracer-Style Device Ports for Docker Network Map

## Overview
Add physical network port visualization to Docker Host and Network Bridge nodes, similar to Cisco Packet Tracer. Each device will display named ports (G0/1, Fa0/1, SFP01, etc.) and connections will show which specific port is used on each end of the link.

## What Changes

### 1. New Device Port Data Model
Each device node gets a `ports` array defining its physical interfaces:

```text
Port types by device:
- Switch:    24x GigabitEthernet (G0/1-G0/24) + 4x SFP (SFP01-SFP04)
- Router:    4x GigabitEthernet (G0/0-G0/3) + 2x Serial (S0/0-S0/1) + 1x SFP (SFP01)
- Firewall:  8x GigabitEthernet (G0/0-G0/7) + 2x Management (Mgmt0/0-Mgmt0/1)
```

Each port has: `id`, `name` (e.g. "G0/1"), `type` (gigabit/sfp/serial/mgmt), `status` (up/down/disabled), and optional `speed`.

### 2. Enhanced Network Bridge Node (Virtual Switch)
The existing decorative port bars on the NetworkBridgeNode will become interactive, real port indicators:
- Replace the static 6-rectangle bars with actual named port slots from the data
- Each port shows a small colored LED: green = up, red = down, gray = disabled
- Hovering a port shows a tooltip with the port name (e.g. "G0/3 - 1Gbps")
- Ports are rendered as small rectangles in rows (top row + bottom row), mimicking a real switch faceplate

### 3. Enhanced Docker Host Node
Add a port strip at the bottom of the DockerHostNode showing its physical interfaces (typically fewer ports like G0/0-G0/3). Same LED status indicators.

### 4. Updated Edge Data (Port-to-Port Connections)
The PortBindingEdge label will change from generic "Ext: 80 -> Int: 80/tcp" to show specific port names on each end:
- Label format: `G0/1 <-> G0/3` or `SFP01 <-> G0/0`
- The `sourcePort` and `targetPort` fields are added to edge data

### 5. Port Management Dialog
A new dialog opens when clicking a device node (instead of just containers). It shows:
- A visual grid of all ports on the device
- Each port's name, type, status, speed
- Which device/port it connects to (if any)
- Ability to toggle port status (up/down/disabled)

### 6. Updated Demo Data
The demo nodes and edges will include port definitions and port-to-port connection info so the feature is visible immediately.

---

## Technical Details

### Files to Create

**`docker-ampnm/src/components/docker-map/DevicePortGrid.tsx`**
- Reusable component rendering a grid of port indicators
- Props: `ports[]`, `onPortClick`, `compact` (for inline node view vs dialog)
- Each port: small rectangle with LED dot + tooltip on hover
- Color coding: green (up), red (down), gray (disabled), blue (connected)

**`docker-ampnm/src/components/docker-map/DeviceInspector.tsx`**
- New Sheet/Dialog for inspecting any device (host, switch, router, firewall)
- Shows device info + full port grid with details table
- Lists all connections from each port

### Files to Modify

**`docker-ampnm/src/components/docker-map/DockerHostNode.tsx`**
- Add `ports` to `DockerHostData` interface
- Add `deviceType` field (server/switch/router/firewall)
- Render a compact port strip at the bottom using `DevicePortGrid`

**`docker-ampnm/src/components/docker-map/NetworkBridgeNode.tsx`**
- Add `ports` to `NetworkBridgeData` interface
- Replace the two static port bars with `DevicePortGrid` showing real named ports
- Highlight connected ports with a different color

**`docker-ampnm/src/components/docker-map/PortBindingEdge.tsx`**
- Update `PortBindingEdgeData` to include `sourcePort` and `targetPort` names
- Change label rendering to show port-to-port format: "G0/1 <-> SFP01"

**`docker-ampnm/src/components/docker-map/DockerNetworkMap.tsx`**
- Add `DeviceInspector` state and open it on host/bridge node clicks (in addition to container clicks)
- Update demo data with port arrays for each device
- Update demo edges with `sourcePort`/`targetPort` fields

**`docker-ampnm/src/components/docker-map/ContainerInspector.tsx`**
- No changes needed (containers keep their existing inspector)

### Port Type Definitions

```text
Interface for DevicePort:
  - id: string
  - name: string          // "G0/1", "SFP01", "S0/0", "Mgmt0/0"
  - type: "gigabit" | "fastethernet" | "sfp" | "serial" | "mgmt" | "console"
  - status: "up" | "down" | "disabled"
  - speed: string         // "1Gbps", "10Gbps", "100Mbps"
  - connectedTo: string   // optional - device name it connects to

Default port counts per device type:
  - Switch:   24 GigabitEthernet + 4 SFP = 28 ports
  - Router:   4 GigabitEthernet + 2 Serial + 1 SFP = 7 ports
  - Firewall: 8 GigabitEthernet + 2 Management = 10 ports
  - Server:   4 GigabitEthernet = 4 ports
```

### Visual Layout in Node

```text
+----------------------------------+
| [icon] Switch-Core-01    [LED]   |
| bridge - local                   |
|----------------------------------|
| Subnet: 172.18.0.0/16           |
| GW: 172.18.0.1                  |
|----------------------------------|
| G0/1  G0/2  G0/3  G0/4  ...    |  <- top port row (green/red dots)
| G0/13 G0/14 ... SFP01 SFP02    |  <- bottom port row
+----------------------------------+
```

### Edge Label Change

```text
Before: "Ext: 80 -> Int: 80/tcp"
After:  "G0/1 <-> G0/3"
```

The existing container port mapping labels remain on container edges. The new port-to-port labels apply to device-to-device and device-to-network connections.

