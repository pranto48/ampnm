import { useState, useCallback, useMemo } from "react";
import {
  ReactFlow,
  Background,
  Controls,
  MiniMap,
  type Node,
  type Edge,
  type OnNodeClick,
  BackgroundVariant,
} from "@xyflow/react";
import "@xyflow/react/dist/style.css";

import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Search } from "lucide-react";

import DockerHostNode, { type DockerHostData } from "./DockerHostNode";
import NetworkBridgeNode, { type NetworkBridgeData } from "./NetworkBridgeNode";
import ContainerNode, { type ContainerData } from "./ContainerNode";
import NetworkGroupNode, { type NetworkGroupData } from "./NetworkGroupNode";
import PortBindingEdge from "./PortBindingEdge";
import ContainerInspector from "./ContainerInspector";
import DeviceInspector, { type DeviceInspectorData } from "./DeviceInspector";
import type { DevicePort } from "./DevicePortGrid";

// ---------- Port generator helpers ----------
function generatePorts(
  deviceType: "server" | "switch" | "router" | "firewall",
  overrides?: Partial<Record<string, Pick<DevicePort, "status" | "connectedTo">>>
): DevicePort[] {
  const ports: DevicePort[] = [];
  const o = overrides || {};

  const make = (name: string, type: DevicePort["type"], speed: string) => {
    const ov = o[name];
    ports.push({ id: name, name, type, status: ov?.status || "down", speed, connectedTo: ov?.connectedTo });
  };

  if (deviceType === "switch") {
    for (let i = 1; i <= 24; i++) make(`G0/${i}`, "gigabit", "1Gbps");
    for (let i = 1; i <= 4; i++) make(`SFP0${i}`, "sfp", "10Gbps");
  } else if (deviceType === "router") {
    for (let i = 0; i <= 3; i++) make(`G0/${i}`, "gigabit", "1Gbps");
    for (let i = 0; i <= 1; i++) make(`S0/${i}`, "serial", "1.544Mbps");
    make("SFP01", "sfp", "10Gbps");
  } else if (deviceType === "firewall") {
    for (let i = 0; i <= 7; i++) make(`G0/${i}`, "gigabit", "1Gbps");
    for (let i = 0; i <= 1; i++) make(`Mgmt0/${i}`, "mgmt", "1Gbps");
  } else {
    // server
    for (let i = 0; i <= 3; i++) make(`G0/${i}`, "gigabit", "1Gbps");
  }
  return ports;
}

// ---------- Node & Edge types ----------
const nodeTypes = {
  dockerHost: DockerHostNode,
  networkBridge: NetworkBridgeNode,
  container: ContainerNode,
  networkGroup: NetworkGroupNode,
};

const edgeTypes = {
  portBinding: PortBindingEdge,
};

// ---------- Demo data ----------
const host1Ports = generatePorts("server", {
  "G0/0": { status: "up", connectedTo: "app_network (G0/1)" },
  "G0/1": { status: "up", connectedTo: "host_network (G0/3)" },
  "G0/2": { status: "up", connectedTo: "nginx-proxy" },
  "G0/3": { status: "down" },
});

const host2Ports = generatePorts("server", {
  "G0/0": { status: "up", connectedTo: "swarm_overlay (G0/5)" },
  "G0/1": { status: "down" },
  "G0/2": { status: "down" },
  "G0/3": { status: "down" },
});

const bridgePorts = generatePorts("switch", {
  "G0/1": { status: "up", connectedTo: "prod-server-01 (G0/0)" },
  "G0/2": { status: "up", connectedTo: "nginx-proxy" },
  "G0/3": { status: "up", connectedTo: "backend-api" },
  "G0/4": { status: "up", connectedTo: "postgres-db" },
  "G0/5": { status: "up", connectedTo: "bg-worker" },
});

const overlayPorts = generatePorts("switch", {
  "G0/1": { status: "up", connectedTo: "redis-cache" },
  "G0/2": { status: "up", connectedTo: "backend-api" },
  "G0/5": { status: "up", connectedTo: "dev-server-02 (G0/0)" },
});

const hostNetPorts = generatePorts("router", {
  "G0/0": { status: "up", connectedTo: "prometheus" },
  "G0/3": { status: "up", connectedTo: "prod-server-01 (G0/1)" },
});

const demoNodes: Node[] = [
  // Network group bounding boxes
  {
    id: "group-app-network",
    type: "networkGroup",
    position: { x: 5, y: 420 },
    data: { label: "app_network", driver: "bridge", width: 430, height: 200 } satisfies NetworkGroupData,
    style: { zIndex: -1 },
    selectable: false,
    draggable: false,
  },
  {
    id: "group-swarm-overlay",
    type: "networkGroup",
    position: { x: 480, y: 420 },
    data: { label: "swarm_overlay", driver: "overlay", width: 250, height: 100 } satisfies NetworkGroupData,
    style: { zIndex: -1 },
    selectable: false,
    draggable: false,
  },
  {
    id: "group-host-network",
    type: "networkGroup",
    position: { x: 500, y: 550 },
    data: { label: "host_network", driver: "host", width: 200, height: 100 } satisfies NetworkGroupData,
    style: { zIndex: -1 },
    selectable: false,
    draggable: false,
  },
  // Host 1
  {
    id: "host-1",
    type: "dockerHost",
    position: { x: 100, y: 20 },
    data: {
      label: "prod-server-01", os: "Ubuntu 22.04 LTS", ip: "192.168.1.10",
      containersCount: 4, status: "running", deviceType: "server", ports: host1Ports,
    } satisfies DockerHostData,
  },
  // Host 2
  {
    id: "host-2",
    type: "dockerHost",
    position: { x: 600, y: 20 },
    data: {
      label: "dev-server-02", os: "Debian 12", ip: "192.168.1.11",
      containersCount: 2, status: "running", deviceType: "server", ports: host2Ports,
    } satisfies DockerHostData,
  },
  // Bridge network (switch)
  {
    id: "net-bridge-1",
    type: "networkBridge",
    position: { x: 30, y: 220 },
    data: {
      label: "app_network", driver: "bridge", subnet: "172.18.0.0/16",
      gateway: "172.18.0.1", scope: "local", ports: bridgePorts,
    } satisfies NetworkBridgeData,
  },
  // Overlay network
  {
    id: "net-overlay-1",
    type: "networkBridge",
    position: { x: 500, y: 220 },
    data: {
      label: "swarm_overlay", driver: "overlay", subnet: "10.0.1.0/24",
      gateway: "10.0.1.1", scope: "swarm", ports: overlayPorts,
    } satisfies NetworkBridgeData,
  },
  // Host network (router-like)
  {
    id: "net-host-1",
    type: "networkBridge",
    position: { x: 340, y: 220 },
    data: {
      label: "host_network", driver: "host", subnet: "—",
      gateway: "—", scope: "local", ports: hostNetPorts,
    } satisfies NetworkBridgeData,
  },
  // Containers
  {
    id: "c-nginx",
    type: "container",
    position: { x: 20, y: 450 },
    data: {
      label: "nginx-proxy", image: "nginx:1.25-alpine", containerId: "a3f8b2c1d4e5",
      internalIp: "172.18.0.2", state: "running",
      ports: [{ external: 80, internal: 80, protocol: "tcp" }, { external: 443, internal: 443, protocol: "tcp" }],
      networks: ["app_network"],
    } satisfies ContainerData,
  },
  {
    id: "c-api",
    type: "container",
    position: { x: 220, y: 450 },
    data: {
      label: "backend-api", image: "node:20-slim", containerId: "b7e9d1f3a2c4",
      internalIp: "172.18.0.3", state: "running",
      ports: [{ external: 3000, internal: 3000, protocol: "tcp" }],
      networks: ["app_network", "swarm_overlay"],
    } satisfies ContainerData,
  },
  {
    id: "c-db",
    type: "container",
    position: { x: 420, y: 450 },
    data: {
      label: "postgres-db", image: "postgres:16", containerId: "c2d4e6f8a1b3",
      internalIp: "172.18.0.4", state: "running",
      ports: [{ external: 5432, internal: 5432, protocol: "tcp" }],
      networks: ["app_network"],
    } satisfies ContainerData,
  },
  {
    id: "c-redis",
    type: "container",
    position: { x: 620, y: 450 },
    data: {
      label: "redis-cache", image: "redis:7-alpine", containerId: "d5f7a9b1c3e2",
      internalIp: "10.0.1.5", state: "running",
      ports: [{ external: 6379, internal: 6379, protocol: "tcp" }],
      networks: ["swarm_overlay"],
    } satisfies ContainerData,
  },
  {
    id: "c-worker",
    type: "container",
    position: { x: 300, y: 580 },
    data: {
      label: "bg-worker", image: "python:3.12-slim", containerId: "e8a1b3c5d7f9",
      internalIp: "172.18.0.6", state: "stopped", ports: [], networks: ["app_network"],
    } satisfies ContainerData,
  },
  {
    id: "c-monitor",
    type: "container",
    position: { x: 520, y: 580 },
    data: {
      label: "prometheus", image: "prom/prometheus:v2.51", containerId: "f1a2b3c4d5e6",
      internalIp: "—", state: "running",
      ports: [{ external: 9090, internal: 9090, protocol: "tcp" }],
      networks: ["host_network"],
    } satisfies ContainerData,
  },
];

const demoEdges: Edge[] = [
  // Host → Networks (port-to-port)
  { id: "e-h1-nb1", source: "host-1", target: "net-bridge-1", type: "portBinding", data: { sourcePort: "G0/0", targetPort: "G0/1" } },
  { id: "e-h1-nh1", source: "host-1", target: "net-host-1", type: "portBinding", data: { sourcePort: "G0/1", targetPort: "G0/3" } },
  { id: "e-h2-no1", source: "host-2", target: "net-overlay-1", type: "portBinding", data: { sourcePort: "G0/0", targetPort: "G0/5" } },
  // Networks → Containers (port-to-port)
  { id: "e-nb1-nginx", source: "net-bridge-1", target: "c-nginx", type: "portBinding", data: { sourcePort: "G0/2", targetPort: "eth0" } },
  { id: "e-nb1-api", source: "net-bridge-1", target: "c-api", type: "portBinding", data: { sourcePort: "G0/3", targetPort: "eth0" } },
  { id: "e-nb1-db", source: "net-bridge-1", target: "c-db", type: "portBinding", data: { sourcePort: "G0/4", targetPort: "eth0" } },
  { id: "e-nb1-worker", source: "net-bridge-1", target: "c-worker", type: "portBinding", data: { sourcePort: "G0/5", targetPort: "eth0" } },
  { id: "e-no1-redis", source: "net-overlay-1", target: "c-redis", type: "portBinding", data: { sourcePort: "G0/1", targetPort: "eth0" } },
  { id: "e-no1-api", source: "net-overlay-1", target: "c-api", type: "portBinding", data: { sourcePort: "G0/2", targetPort: "eth1" } },
  { id: "e-nh1-mon", source: "net-host-1", target: "c-monitor", type: "portBinding", data: { sourcePort: "G0/0", targetPort: "eth0" } },
  // Host → Container direct
  { id: "e-h1-nginx", source: "host-1", target: "c-nginx", type: "portBinding", data: { sourcePort: "G0/2", targetPort: "eth0" } },
];

// ---------- Component ----------
const DockerNetworkMap = () => {
  const [search, setSearch] = useState("");
  const [hostFilter, setHostFilter] = useState("all");
  const [networkFilter, setNetworkFilter] = useState("all");
  const [inspectorOpen, setInspectorOpen] = useState(false);
  const [selectedContainer, setSelectedContainer] = useState<ContainerData | null>(null);
  const [deviceInspectorOpen, setDeviceInspectorOpen] = useState(false);
  const [selectedDevice, setSelectedDevice] = useState<DeviceInspectorData | null>(null);

  const hosts = useMemo(() => demoNodes.filter((n) => n.type === "dockerHost"), []);
  const networkDrivers = useMemo(() => {
    const drivers = new Set<string>();
    demoNodes.forEach((n) => {
      if (n.type === "networkBridge") {
        drivers.add((n.data as unknown as NetworkBridgeData).driver);
      }
    });
    return Array.from(drivers);
  }, []);

  const filteredNodes = useMemo(() => {
    let nodes = demoNodes;
    if (search) {
      const q = search.toLowerCase();
      nodes = nodes.filter((n) => {
        const d = n.data as any;
        return (d.label || "").toLowerCase().includes(q) ||
               (d.image || "").toLowerCase().includes(q) ||
               (d.ip || "").toLowerCase().includes(q);
      });
    }
    if (hostFilter !== "all") {
      const hostEdges = demoEdges.filter((e) => e.source === hostFilter || e.target === hostFilter);
      const connectedIds = new Set([hostFilter, ...hostEdges.map((e) => e.source), ...hostEdges.map((e) => e.target)]);
      demoEdges.forEach((e) => {
        if (connectedIds.has(e.source)) connectedIds.add(e.target);
        if (connectedIds.has(e.target)) connectedIds.add(e.source);
      });
      nodes = nodes.filter((n) => connectedIds.has(n.id));
    }
    if (networkFilter !== "all") {
      nodes = nodes.filter((n) => {
        if (n.type === "networkBridge") return (n.data as unknown as NetworkBridgeData).driver === networkFilter;
        if (n.type === "dockerHost") return true;
        if (n.type === "container") {
          const cData = n.data as unknown as ContainerData;
          return demoNodes.some(
            (nn) =>
              nn.type === "networkBridge" &&
              (nn.data as unknown as NetworkBridgeData).driver === networkFilter &&
              cData.networks.includes((nn.data as unknown as NetworkBridgeData).label)
          );
        }
        return true;
      });
    }
    return nodes;
  }, [search, hostFilter, networkFilter, hosts]);

  const filteredEdges = useMemo(() => {
    const nodeIds = new Set(filteredNodes.map((n) => n.id));
    return demoEdges.filter((e) => nodeIds.has(e.source) && nodeIds.has(e.target));
  }, [filteredNodes]);

  const handleNodeClick: OnNodeClick = useCallback((_event, node) => {
    if (node.type === "container") {
      setSelectedContainer(node.data as unknown as ContainerData);
      setInspectorOpen(true);
    } else if (node.type === "dockerHost") {
      const d = node.data as unknown as DockerHostData;
      setSelectedDevice({
        label: d.label,
        deviceType: d.deviceType || "server",
        ip: d.ip,
        os: d.os,
        ports: d.ports || [],
      });
      setDeviceInspectorOpen(true);
    } else if (node.type === "networkBridge") {
      const d = node.data as unknown as NetworkBridgeData;
      setSelectedDevice({
        label: d.label,
        deviceType: "switch",
        driver: d.driver,
        subnet: d.subnet,
        gateway: d.gateway,
        ports: d.ports || [],
      });
      setDeviceInspectorOpen(true);
    }
  }, []);

  return (
    <div className="flex flex-col h-[calc(100vh-120px)]">
      {/* Filter bar */}
      <div className="flex items-center gap-3 p-3 border-b border-border bg-card/50 backdrop-blur-sm rounded-t-lg">
        <div className="relative flex-1 max-w-xs">
          <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Search containers, hosts, IPs..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="pl-9 h-9 text-sm"
          />
        </div>

        <Select value={hostFilter} onValueChange={setHostFilter}>
          <SelectTrigger className="w-[180px] h-9 text-sm">
            <SelectValue placeholder="Filter by Host" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Hosts</SelectItem>
            {hosts.map((h) => (
              <SelectItem key={h.id} value={h.id}>
                {(h.data as unknown as DockerHostData).label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={networkFilter} onValueChange={setNetworkFilter}>
          <SelectTrigger className="w-[160px] h-9 text-sm">
            <SelectValue placeholder="Network Type" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Networks</SelectItem>
            {networkDrivers.map((d) => (
              <SelectItem key={d} value={d} className="capitalize">
                {d}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {/* Map */}
      <div className="flex-1 bg-background rounded-b-lg border border-t-0 border-border">
        <ReactFlow
          nodes={filteredNodes}
          edges={filteredEdges}
          nodeTypes={nodeTypes}
          edgeTypes={edgeTypes}
          onNodeClick={handleNodeClick}
          fitView
          fitViewOptions={{ padding: 0.3 }}
          minZoom={0.3}
          maxZoom={2}
          proOptions={{ hideAttribution: true }}
        >
          <Background variant={BackgroundVariant.Dots} gap={20} size={1} color="hsl(var(--muted-foreground) / 0.15)" />
          <Controls className="!bg-card !border-border !shadow-lg [&>button]:!bg-card [&>button]:!border-border [&>button]:!text-foreground [&>button:hover]:!bg-muted" />
          <MiniMap
            className="!bg-card !border-border"
            nodeColor={(n) =>
              n.type === "dockerHost" ? "#3b82f6" :
              n.type === "networkBridge" ? "#06b6d4" :
              "#22c55e"
            }
            maskColor="hsl(var(--background) / 0.7)"
          />
        </ReactFlow>
      </div>

      {/* Container Inspector */}
      <ContainerInspector
        open={inspectorOpen}
        onOpenChange={setInspectorOpen}
        container={selectedContainer}
      />

      {/* Device Inspector (hosts & bridges) */}
      <DeviceInspector
        open={deviceInspectorOpen}
        onOpenChange={setDeviceInspectorOpen}
        device={selectedDevice}
      />
    </div>
  );
};

export default DockerNetworkMap;
