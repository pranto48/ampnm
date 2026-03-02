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
import PortBindingEdge from "./PortBindingEdge";
import ContainerInspector from "./ContainerInspector";

// ---------- Node & Edge types ----------
const nodeTypes = {
  dockerHost: DockerHostNode,
  networkBridge: NetworkBridgeNode,
  container: ContainerNode,
};

const edgeTypes = {
  portBinding: PortBindingEdge,
};

// ---------- Demo data ----------
const demoNodes: Node[] = [
  // Host 1
  {
    id: "host-1",
    type: "dockerHost",
    position: { x: 100, y: 50 },
    data: { label: "prod-server-01", os: "Ubuntu 22.04 LTS", ip: "192.168.1.10", containersCount: 4, status: "running" } satisfies DockerHostData,
  },
  // Host 2
  {
    id: "host-2",
    type: "dockerHost",
    position: { x: 600, y: 50 },
    data: { label: "dev-server-02", os: "Debian 12", ip: "192.168.1.11", containersCount: 2, status: "running" } satisfies DockerHostData,
  },
  // Bridge network
  {
    id: "net-bridge-1",
    type: "networkBridge",
    position: { x: 60, y: 220 },
    data: { label: "app_network", driver: "bridge", subnet: "172.18.0.0/16", gateway: "172.18.0.1", scope: "local" } satisfies NetworkBridgeData,
  },
  // Overlay network
  {
    id: "net-overlay-1",
    type: "networkBridge",
    position: { x: 500, y: 220 },
    data: { label: "swarm_overlay", driver: "overlay", subnet: "10.0.1.0/24", gateway: "10.0.1.1", scope: "swarm" } satisfies NetworkBridgeData,
  },
  // Host network
  {
    id: "net-host-1",
    type: "networkBridge",
    position: { x: 340, y: 220 },
    data: { label: "host_network", driver: "host", subnet: "—", gateway: "—", scope: "local" } satisfies NetworkBridgeData,
  },
  // Containers
  {
    id: "c-nginx",
    type: "container",
    position: { x: 20, y: 420 },
    data: {
      label: "nginx-proxy",
      image: "nginx:1.25-alpine",
      containerId: "a3f8b2c1d4e5",
      internalIp: "172.18.0.2",
      state: "running",
      ports: [{ external: 80, internal: 80, protocol: "tcp" }, { external: 443, internal: 443, protocol: "tcp" }],
      networks: ["app_network"],
    } satisfies ContainerData,
  },
  {
    id: "c-api",
    type: "container",
    position: { x: 220, y: 420 },
    data: {
      label: "backend-api",
      image: "node:20-slim",
      containerId: "b7e9d1f3a2c4",
      internalIp: "172.18.0.3",
      state: "running",
      ports: [{ external: 3000, internal: 3000, protocol: "tcp" }],
      networks: ["app_network", "swarm_overlay"],
    } satisfies ContainerData,
  },
  {
    id: "c-db",
    type: "container",
    position: { x: 420, y: 420 },
    data: {
      label: "postgres-db",
      image: "postgres:16",
      containerId: "c2d4e6f8a1b3",
      internalIp: "172.18.0.4",
      state: "running",
      ports: [{ external: 5432, internal: 5432, protocol: "tcp" }],
      networks: ["app_network"],
    } satisfies ContainerData,
  },
  {
    id: "c-redis",
    type: "container",
    position: { x: 620, y: 420 },
    data: {
      label: "redis-cache",
      image: "redis:7-alpine",
      containerId: "d5f7a9b1c3e2",
      internalIp: "10.0.1.5",
      state: "running",
      ports: [{ external: 6379, internal: 6379, protocol: "tcp" }],
      networks: ["swarm_overlay"],
    } satisfies ContainerData,
  },
  {
    id: "c-worker",
    type: "container",
    position: { x: 300, y: 550 },
    data: {
      label: "bg-worker",
      image: "python:3.12-slim",
      containerId: "e8a1b3c5d7f9",
      internalIp: "172.18.0.6",
      state: "stopped",
      ports: [],
      networks: ["app_network"],
    } satisfies ContainerData,
  },
  {
    id: "c-monitor",
    type: "container",
    position: { x: 520, y: 550 },
    data: {
      label: "prometheus",
      image: "prom/prometheus:v2.51",
      containerId: "f1a2b3c4d5e6",
      internalIp: "—",
      state: "running",
      ports: [{ external: 9090, internal: 9090, protocol: "tcp" }],
      networks: ["host_network"],
    } satisfies ContainerData,
  },
];

const demoEdges: Edge[] = [
  // Host → Networks
  { id: "e-h1-nb1", source: "host-1", target: "net-bridge-1", type: "smoothstep", animated: true, style: { stroke: "hsl(var(--muted-foreground))", strokeWidth: 1 } },
  { id: "e-h1-nh1", source: "host-1", target: "net-host-1", type: "smoothstep", animated: true, style: { stroke: "hsl(var(--muted-foreground))", strokeWidth: 1 } },
  { id: "e-h2-no1", source: "host-2", target: "net-overlay-1", type: "smoothstep", animated: true, style: { stroke: "hsl(var(--muted-foreground))", strokeWidth: 1 } },
  // Networks → Containers
  { id: "e-nb1-nginx", source: "net-bridge-1", target: "c-nginx", type: "portBinding", data: { portLabel: "Ext: 80 ➔ Int: 80/tcp" } },
  { id: "e-nb1-api", source: "net-bridge-1", target: "c-api", type: "portBinding", data: { portLabel: "Ext: 3000 ➔ Int: 3000/tcp" } },
  { id: "e-nb1-db", source: "net-bridge-1", target: "c-db", type: "portBinding", data: { portLabel: "Ext: 5432 ➔ Int: 5432/tcp" } },
  { id: "e-nb1-worker", source: "net-bridge-1", target: "c-worker", type: "smoothstep", style: { stroke: "hsl(var(--muted-foreground))", strokeWidth: 1, strokeDasharray: "4 4" } },
  { id: "e-no1-redis", source: "net-overlay-1", target: "c-redis", type: "portBinding", data: { portLabel: "Ext: 6379 ➔ Int: 6379/tcp" } },
  { id: "e-no1-api", source: "net-overlay-1", target: "c-api", type: "smoothstep", style: { stroke: "hsl(var(--muted-foreground))", strokeWidth: 1 } },
  { id: "e-nh1-mon", source: "net-host-1", target: "c-monitor", type: "portBinding", data: { portLabel: "Ext: 9090 ➔ Int: 9090/tcp" } },
  // Host → Container direct (port binding edges)
  { id: "e-h1-nginx", source: "host-1", target: "c-nginx", type: "portBinding", data: { portLabel: "Ext: 443 ➔ Int: 443/tcp" } },
];

// ---------- Component ----------
const DockerNetworkMap = () => {
  const [search, setSearch] = useState("");
  const [hostFilter, setHostFilter] = useState("all");
  const [networkFilter, setNetworkFilter] = useState("all");
  const [inspectorOpen, setInspectorOpen] = useState(false);
  const [selectedContainer, setSelectedContainer] = useState<ContainerData | null>(null);

  // Extract unique hosts & network drivers for filter dropdowns
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

  // Filtering logic
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
      const hostNode = hosts.find((h) => h.id === hostFilter);
      if (hostNode) {
        // Keep the host, its networks, and connected containers
        const hostEdges = demoEdges.filter((e) => e.source === hostFilter || e.target === hostFilter);
        const connectedIds = new Set([hostFilter, ...hostEdges.map((e) => e.source), ...hostEdges.map((e) => e.target)]);
        // Also find containers connected to those networks
        demoEdges.forEach((e) => {
          if (connectedIds.has(e.source)) connectedIds.add(e.target);
          if (connectedIds.has(e.target)) connectedIds.add(e.source);
        });
        nodes = nodes.filter((n) => connectedIds.has(n.id));
      }
    }

    if (networkFilter !== "all") {
      nodes = nodes.filter((n) => {
        if (n.type === "networkBridge") return (n.data as unknown as NetworkBridgeData).driver === networkFilter;
        if (n.type === "dockerHost") return true;
        if (n.type === "container") {
          const cData = n.data as unknown as ContainerData;
          // Check if any network this container belongs to matches the driver filter
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
            ))
            }
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
            ))
            }
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

      {/* Inspector sidebar */}
      <ContainerInspector
        open={inspectorOpen}
        onOpenChange={setInspectorOpen}
        container={selectedContainer}
      />
    </div>
  );
};

export default DockerNetworkMap;
