import { useState, useEffect, useCallback, useRef } from "react";
import {
  ReactFlow,
  Background,
  Controls,
  MiniMap,
  useNodesState,
  useEdgesState,
  addEdge,
  type Connection,
  type Edge,
  type Node,
  type NodeChange,
  BackgroundVariant,
  MarkerType,
} from "@xyflow/react";
import "@xyflow/react/dist/style.css";

import { supabase } from "@/integrations/supabase/client";
import { AppLayout } from "@/components/layout/AppLayout";
import { useAuth } from "@/hooks/useAuth";
import { useToast } from "@/hooks/use-toast";
import DeviceNode from "@/components/map/DeviceNode";
import { EdgeEditor, edgeColorMap } from "@/components/map/EdgeEditor";
import { DeviceFormDialog } from "@/components/devices/DeviceFormDialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from "@/components/ui/dialog";
import {
  Map as MapIcon, RefreshCw, Plus, Pencil, Trash2, Share2, Settings, Maximize,
  Network, Eye, EyeOff, Copy, Link2, Download, Upload, Activity, Edit, MapPin,
  Clock, Square,
} from "lucide-react";
import type { Tables } from "@/integrations/supabase/types";

type Device = Tables<"devices">;
type DeviceEdge = Tables<"device_edges">;
type MapRow = Tables<"maps">;

const nodeTypes = { device: DeviceNode };

const CONNECTION_TYPES = [
  { value: "cat6", label: "🔌 CAT6 Cable", color: "#a78bfa" },
  { value: "fiber", label: "💡 Fiber Optic", color: "#f97316" },
  { value: "wifi", label: "📡 WiFi", color: "#38bdf8" },
  { value: "radio", label: "📻 Radio", color: "#84cc16" },
  { value: "lan", label: "🌐 LAN", color: "#60a5fa" },
  { value: "logical-tunneling", label: "🔒 Tunnel", color: "#c084fc" },
];

export default function NetworkMapPage() {
  const { isAdmin, user } = useAuth();
  const { toast } = useToast();

  const [maps, setMaps] = useState<MapRow[]>([]);
  const [currentMapId, setCurrentMapId] = useState<string | null>(null);
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [loading, setLoading] = useState(true);

  // Dialogs
  const [editingEdge, setEditingEdge] = useState<Edge | null>(null);
  const [settingsOpen, setSettingsOpen] = useState(false);
  const [shareOpen, setShareOpen] = useState(false);
  const [legendVisible, setLegendVisible] = useState(() => {
    try { return localStorage.getItem("map-legend-visible") !== "false"; } catch { return true; }
  });

  // Legend drag position
  const [legendPos, setLegendPos] = useState<{ x: number; y: number }>(() => {
    try {
      const saved = localStorage.getItem("map-legend-pos");
      return saved ? JSON.parse(saved) : { x: -1, y: -1 };
    } catch { return { x: -1, y: -1 }; }
  });
  const legendDragRef = useRef<{ dragging: boolean; offsetX: number; offsetY: number }>({ dragging: false, offsetX: 0, offsetY: 0 });

  // Context menu
  const [contextMenu, setContextMenu] = useState<{ deviceId: string; x: number; y: number } | null>(null);

  // Device edit dialog
  const [editingDevice, setEditingDevice] = useState<Device | null>(null);
  const [deviceDialogOpen, setDeviceDialogOpen] = useState(false);
  const devicesCache = useRef<Device[]>([]);

  // Map settings state
  const [bgColor, setBgColor] = useState("#1e293b");
  const [bgImageUrl, setBgImageUrl] = useState("");
  const [publicView, setPublicView] = useState(false);
  const [offlineDelay, setOfflineDelay] = useState(5);

  // Place device dialog
  const [placeDeviceOpen, setPlaceDeviceOpen] = useState(false);
  const [unassignedDevices, setUnassignedDevices] = useState<Device[]>([]);
  const [selectedPlaceIds, setSelectedPlaceIds] = useState<string[]>([]);
  const [loadingUnassigned, setLoadingUnassigned] = useState(false);

  // Live refresh
  const [liveRefresh, setLiveRefresh] = useState(true);
  const [isPingingAll, setIsPingingAll] = useState(false);

  // Fullscreen
  const mapWrapperRef = useRef<HTMLDivElement>(null);
  const [isFullscreen, setIsFullscreen] = useState(false);

  const currentMap = maps.find((m) => m.id === currentMapId);

  // -- Fetch maps --
  useEffect(() => {
    (async () => {
      const { data } = await supabase.from("maps").select("*").order("name");
      if (data && data.length > 0) {
        setMaps(data);
        setCurrentMapId(data[0].id);
      } else if (isAdmin && user) {
        const { data: newMap } = await supabase
          .from("maps")
          .insert({ name: "Default Map", user_id: user.id })
          .select()
          .single();
        if (newMap) {
          setMaps([newMap]);
          setCurrentMapId(newMap.id);
        }
      }
    })();
  }, [isAdmin, user]);

  // Sync settings when map changes
  useEffect(() => {
    if (currentMap) {
      setBgColor(currentMap.background_color || "#1e293b");
      setBgImageUrl(currentMap.background_image_url || "");
      setPublicView(currentMap.public_view_enabled || false);
      setOfflineDelay((currentMap as any).offline_delay_seconds ?? 5);
    }
  }, [currentMap]);

  // -- Context menu handler (stable ref) --
  const handleNodeContextMenu = useCallback((deviceId: string, x: number, y: number) => {
    setContextMenu({ deviceId, x, y });
  }, []);

  // -- Fetch devices & edges --
  const fetchMapData = useCallback(async () => {
    if (!currentMapId) return;
    setLoading(true);

    const [devRes, edgeRes] = await Promise.all([
      supabase.from("devices").select("*").eq("map_id", currentMapId),
      supabase.from("device_edges").select("*").eq("map_id", currentMapId),
    ]);

    if (devRes.data) {
      devicesCache.current = devRes.data;
      const flowNodes: Node[] = devRes.data.map((d: Device) => {
        const isBox = d.type === "box";
        return {
          id: d.id,
          type: isBox ? "group" : "device",
          position: { x: Number(d.x) || 100, y: Number(d.y) || 100 },
          style: isBox ? {
            width: 250,
            height: 180,
            background: "hsla(215, 25%, 27%, 0.4)",
            border: "2px dashed hsl(215, 20%, 45%)",
            borderRadius: 12,
            padding: 16,
            zIndex: -1,
          } : undefined,
          data: isBox ? {
            label: d.name,
          } : {
            name: d.name,
            ip_address: d.ip_address,
            status: d.status || "unknown",
            icon: d.type || "server",
            subchoice: d.subchoice,
            icon_url: d.icon_url,
            icon_size: d.icon_size || 40,
            name_text_size: d.name_text_size || 12,
            last_latency: d.last_latency,
            last_ping: d.last_ping,
            onContextMenu: handleNodeContextMenu,
          },
        };
      });
      setNodes(flowNodes);
    }

    if (edgeRes.data) {
      // Build a status lookup from devices
      const deviceStatusMap = new Map<string, string>();
      if (devRes.data) {
        devRes.data.forEach((d: Device) => deviceStatusMap.set(d.id, d.status || "unknown"));
      }

      const flowEdges: Edge[] = edgeRes.data.map((e: DeviceEdge) => {
        const connType = e.connection_type || "cat6";
        const sourceStatus = deviceStatusMap.get(e.source_id) || "unknown";
        const targetStatus = deviceStatusMap.get(e.target_id) || "unknown";
        const isOffline = sourceStatus === "offline" || targetStatus === "offline";
        const isActive = sourceStatus === "online" && targetStatus === "online";
        const baseColor = edgeColorMap[connType] || "#a78bfa";
        const color = isOffline ? "#64748b" : baseColor;
        const isDashed = connType === "wifi" || connType === "radio" || connType === "logical-tunneling";

        return {
          id: e.id,
          source: e.source_id,
          target: e.target_id,
          type: "default",
          animated: isActive,
          style: {
            stroke: color,
            strokeWidth: 2,
            strokeDasharray: isDashed && !isActive ? "6 4" : undefined,
          },
          className: isActive ? "edge-animated-flow" : "",
          markerEnd: { type: MarkerType.ArrowClosed, color },
          data: { connection_type: connType },
          label: connType,
          labelStyle: { fill: isOffline ? "#64748b" : "#94a3b8", fontSize: 10 },
          labelBgStyle: { fill: "hsl(220 25% 8%)", fillOpacity: 0.9 },
          labelBgPadding: [6, 3] as [number, number],
          labelBgBorderRadius: 4,
        };
      });
      setEdges(flowEdges);
    }

    setLoading(false);
  }, [currentMapId, setNodes, setEdges]);

  useEffect(() => { fetchMapData(); }, [fetchMapData]);

  // Realtime + live refresh polling
  useEffect(() => {
    if (!currentMapId) return;

    const channel = supabase
      .channel(`map-${currentMapId}`)
      .on("postgres_changes", { event: "*", schema: "public", table: "devices", filter: `map_id=eq.${currentMapId}` }, () => fetchMapData())
      .subscribe();

    let interval: ReturnType<typeof setInterval> | null = null;
    if (liveRefresh) {
      interval = setInterval(() => fetchMapData(), 5000);
    }

    return () => {
      supabase.removeChannel(channel);
      if (interval) clearInterval(interval);
    };
  }, [currentMapId, fetchMapData, liveRefresh]);

  // -- Save position on drag end --
  const saveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const handleNodesChange = useCallback(
    (changes: NodeChange[]) => {
      onNodesChange(changes);
      if (!isAdmin) return;
      const posChanges = changes.filter(
        (c) => c.type === "position" && "position" in c && c.position && !c.dragging
      );
      if (posChanges.length === 0) return;

      if (saveTimer.current) clearTimeout(saveTimer.current);
      saveTimer.current = setTimeout(async () => {
        for (const change of posChanges) {
          if (change.type === "position" && "position" in change && change.position) {
            await supabase.from("devices").update({ x: change.position.x, y: change.position.y }).eq("id", change.id);
          }
        }
      }, 300);
    },
    [isAdmin, onNodesChange]
  );

  // -- Connect nodes --
  const onConnect = useCallback(
    async (connection: Connection) => {
      if (!isAdmin || !currentMapId) return;
      const { data, error } = await supabase
        .from("device_edges")
        .insert({ source_id: connection.source!, target_id: connection.target!, map_id: currentMapId, connection_type: "cat6" })
        .select()
        .single();

      if (error) {
        toast({ title: "Failed to create connection", description: error.message, variant: "destructive" });
      } else if (data) {
        const newEdge: Edge = {
          id: data.id, source: data.source_id, target: data.target_id,
          style: { stroke: edgeColorMap["cat6"], strokeWidth: 2 },
          markerEnd: { type: MarkerType.ArrowClosed, color: edgeColorMap["cat6"] },
          data: { connection_type: "cat6" }, label: "cat6",
          labelStyle: { fill: "#94a3b8", fontSize: 10 },
          labelBgStyle: { fill: "hsl(220 25% 8%)", fillOpacity: 0.9 },
          labelBgPadding: [6, 3] as [number, number], labelBgBorderRadius: 4,
        };
        setEdges((eds) => addEdge(newEdge, eds));
      }
    },
    [isAdmin, currentMapId, setEdges, toast]
  );

  const onEdgeClick = useCallback((_: React.MouseEvent, edge: Edge) => {
    if (!isAdmin) return;
    setEditingEdge(edge);
  }, [isAdmin]);

  const handleEdgeSave = async (type: string) => {
    if (!editingEdge) return;
    await supabase.from("device_edges").update({ connection_type: type }).eq("id", editingEdge.id);
    setEditingEdge(null);
    fetchMapData();
  };

  const handleEdgeDelete = async () => {
    if (!editingEdge) return;
    await supabase.from("device_edges").delete().eq("id", editingEdge.id);
    setEdges((eds) => eds.filter((e) => e.id !== editingEdge.id));
    setEditingEdge(null);
  };

  // -- Map management --
  const handleNewMap = async () => {
    const name = prompt("Enter a name for the new map:");
    if (!name?.trim() || !user) return;
    const { data, error } = await supabase.from("maps").insert({ name: name.trim(), user_id: user.id }).select().single();
    if (error) { toast({ title: "Failed", description: error.message, variant: "destructive" }); return; }
    if (data) {
      setMaps((prev) => [...prev, data]);
      setCurrentMapId(data.id);
      toast({ title: `Map "${data.name}" created` });
    }
  };

  const handleRenameMap = async () => {
    if (!currentMap) return;
    const name = prompt("Rename map:", currentMap.name);
    if (!name?.trim()) return;
    const { error } = await supabase.from("maps").update({ name: name.trim() }).eq("id", currentMap.id);
    if (error) { toast({ title: "Failed", description: error.message, variant: "destructive" }); return; }
    setMaps((prev) => prev.map((m) => m.id === currentMap.id ? { ...m, name: name.trim() } : m));
    toast({ title: "Map renamed" });
  };

  const handleDeleteMap = async () => {
    if (!currentMap || !confirm(`Delete map "${currentMap.name}"? All devices on this map will be unassigned.`)) return;
    await supabase.from("device_edges").delete().eq("map_id", currentMap.id);
    await supabase.from("devices").update({ map_id: null }).eq("map_id", currentMap.id);
    const { error } = await supabase.from("maps").delete().eq("id", currentMap.id);
    if (error) { toast({ title: "Failed", description: error.message, variant: "destructive" }); return; }
    const remaining = maps.filter((m) => m.id !== currentMap.id);
    setMaps(remaining);
    setCurrentMapId(remaining.length > 0 ? remaining[0].id : null);
    toast({ title: "Map deleted" });
  };

  // -- Map settings save --
  const handleSaveSettings = async () => {
    if (!currentMapId) return;
    const clampedDelay = Math.max(1, Math.min(300, offlineDelay));
    const { error } = await supabase.from("maps").update({
      background_color: bgColor,
      background_image_url: bgImageUrl.trim() || null,
      public_view_enabled: publicView,
      offline_delay_seconds: clampedDelay,
    } as any).eq("id", currentMapId);
    if (error) { toast({ title: "Failed", description: error.message, variant: "destructive" }); return; }
    setMaps((prev) => prev.map((m) => m.id === currentMapId ? { ...m, background_color: bgColor, background_image_url: bgImageUrl.trim() || null, public_view_enabled: publicView, offline_delay_seconds: clampedDelay } as any : m));
    setSettingsOpen(false);
    toast({ title: "Map settings saved" });
  };

  // -- Export / Import --
  const importInputRef = useRef<HTMLInputElement>(null);

  const handleExport = () => {
    if (!currentMap) return;
    const exportData = {
      map: { name: currentMap.name, background_color: currentMap.background_color, background_image_url: currentMap.background_image_url },
      devices: nodes.map((n) => ({ name: n.data.name, ip_address: n.data.ip_address, type: n.data.icon, icon_url: n.data.icon_url, x: n.position.x, y: n.position.y, icon_size: n.data.icon_size, name_text_size: n.data.name_text_size })),
      edges: edges.map((e) => ({ source_index: nodes.findIndex((n) => n.id === e.source), target_index: nodes.findIndex((n) => n.id === e.target), connection_type: e.data?.connection_type || "cat6" })),
    };
    const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${currentMap.name.replace(/\s+/g, "-")}-map.json`;
    a.click();
    URL.revokeObjectURL(url);
    toast({ title: "Map exported!" });
  };

  const handleImport = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file || !currentMapId || !user) return;
    if (!confirm("Import will add devices and connections to the current map. Continue?")) { if (importInputRef.current) importInputRef.current.value = ""; return; }
    try {
      const text = await file.text();
      const data = JSON.parse(text);
      if (!data.devices || !Array.isArray(data.devices)) throw new Error("Invalid map file.");

      const insertedIds: string[] = [];
      for (const dev of data.devices) {
        const { data: inserted, error } = await supabase.from("devices").insert({
          name: dev.name || "Imported Device",
          ip_address: dev.ip_address || null,
          type: dev.type || "server",
          icon_url: dev.icon_url || null,
          x: dev.x ?? 100,
          y: dev.y ?? 100,
          icon_size: dev.icon_size ?? 40,
          name_text_size: dev.name_text_size ?? 12,
          map_id: currentMapId,
          user_id: user.id,
        }).select("id").single();
        if (error) throw error;
        if (inserted) insertedIds.push(inserted.id);
      }

      if (data.edges && Array.isArray(data.edges)) {
        for (const edge of data.edges) {
          const srcId = insertedIds[edge.source_index];
          const tgtId = insertedIds[edge.target_index];
          if (srcId && tgtId) {
            await supabase.from("device_edges").insert({ source_id: srcId, target_id: tgtId, map_id: currentMapId, connection_type: edge.connection_type || "cat5" });
          }
        }
      }

      toast({ title: "Map imported successfully!" });
      fetchMapData();
    } catch (err: any) {
      toast({ title: "Import failed", description: err.message, variant: "destructive" });
    }
    if (importInputRef.current) importInputRef.current.value = "";
  };

  // -- Fullscreen --
  const toggleFullscreen = () => {
    if (!mapWrapperRef.current) return;
    if (!document.fullscreenElement) {
      mapWrapperRef.current.requestFullscreen();
      setIsFullscreen(true);
    } else {
      document.exitFullscreen();
      setIsFullscreen(false);
    }
  };

  useEffect(() => {
    const handler = () => setIsFullscreen(!!document.fullscreenElement);
    document.addEventListener("fullscreenchange", handler);
    return () => document.removeEventListener("fullscreenchange", handler);
  }, []);

  // -- Ping All Devices --
  const handlePingAll = async () => {
    if (!currentMapId || isPingingAll) return;
    setIsPingingAll(true);
    try {
      const deviceIds = nodes.filter(n => n.data.ip_address).map(n => n.id);
      if (deviceIds.length === 0) {
        toast({ title: "No devices with IP addresses to ping" });
        setIsPingingAll(false);
        return;
      }
      const { error } = await supabase.functions.invoke("ping-device", {
        body: { device_ids: deviceIds },
      });
      if (error) throw error;
      toast({ title: `Pinged ${deviceIds.length} devices` });
      fetchMapData();
    } catch (err: any) {
      toast({ title: "Ping all failed", description: err.message, variant: "destructive" });
    } finally {
      setIsPingingAll(false);
    }
  };

  // -- Auto-ping devices based on ping interval --
  useEffect(() => {
    if (!currentMapId || !liveRefresh) return;

    // Auto-ping: find shortest interval among devices with IP and ping_interval
    const autoPingInterval = setInterval(async () => {
      // Get device IDs with IP addresses from current nodes
      const deviceIds = nodes.filter(n => n.data.ip_address).map(n => n.id);
      if (deviceIds.length === 0) return;
      
      try {
        await supabase.functions.invoke("ping-device", {
          body: { device_ids: deviceIds },
        });
        fetchMapData();
      } catch (err) {
        console.error("Auto-ping failed:", err);
      }
    }, 60000); // Auto-ping every 60 seconds when live refresh is on

    return () => clearInterval(autoPingInterval);
  }, [currentMapId, liveRefresh, nodes, fetchMapData]);

  // Public link
  const publicLink = currentMapId ? `${window.location.origin}/map/public/${currentMapId}` : "";

  // Fetch unassigned devices for Place Device dialog
  const fetchUnassigned = async () => {
    setLoadingUnassigned(true);
    const { data } = await supabase.from("devices").select("*").is("map_id", null).order("name");
    setUnassignedDevices(data ?? []);
    setSelectedPlaceIds([]);
    setLoadingUnassigned(false);
  };

  const handlePlaceDevices = async () => {
    if (!currentMapId || selectedPlaceIds.length === 0) return;
    // Assign selected devices to this map with staggered positions
    for (let i = 0; i < selectedPlaceIds.length; i++) {
      await supabase.from("devices").update({
        map_id: currentMapId,
        x: 150 + (i % 5) * 120,
        y: 150 + Math.floor(i / 5) * 120,
      }).eq("id", selectedPlaceIds[i]);
    }
    toast({ title: `${selectedPlaceIds.length} device(s) placed on map` });
    setPlaceDeviceOpen(false);
    setSelectedPlaceIds([]);
    fetchMapData();
  };

  return (
    <AppLayout>
      <div className="space-y-3">
        {/* Top Bar: Title + Map selector + Map management buttons */}
        <div className="flex items-center justify-between flex-wrap gap-2">
          <div className="flex items-center gap-3">
            <MapIcon className="h-7 w-7 text-primary" />
            <h1 className="text-2xl font-bold tracking-tight">Network Map</h1>
          </div>
          <div className="flex items-center gap-2 flex-wrap">
            <Select value={currentMapId || "__none__"} onValueChange={(v) => setCurrentMapId(v === "__none__" ? null : v)}>
              <SelectTrigger className="w-[180px]">
                <SelectValue placeholder="Select map" />
              </SelectTrigger>
              <SelectContent>
                {maps.length === 0 && <SelectItem value="__none__">No maps</SelectItem>}
                {maps.map((m) => (
                  <SelectItem key={m.id} value={m.id}>{m.name}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            {isAdmin && (
              <>
                <Button variant="outline" size="sm" onClick={handleNewMap}><Plus className="h-4 w-4 mr-1" />New Map</Button>
                <Button variant="outline" size="sm" onClick={handleRenameMap} disabled={!currentMap}><Pencil className="h-4 w-4 mr-1" />Rename</Button>
                <Button variant="outline" size="sm" onClick={handleDeleteMap} disabled={!currentMap} className="text-destructive hover:text-destructive"><Trash2 className="h-4 w-4 mr-1" />Delete</Button>
              </>
            )}
            <Button variant="outline" size="sm" onClick={() => setShareOpen(true)} disabled={!currentMap}><Share2 className="h-4 w-4 mr-1" />Share</Button>
          </div>
        </div>

        {/* Controls Bar */}
        {currentMap && (
          <div className="flex items-center justify-between flex-wrap gap-2 bg-card border border-border rounded-lg px-4 py-2">
            <span className="font-semibold text-foreground">{currentMap.name}</span>
            <div className="flex items-center gap-2 flex-wrap">
              <div className="flex items-center gap-1.5 border-r border-border pr-2 mr-1">
                <Label htmlFor="live-toggle" className="text-xs text-muted-foreground cursor-pointer">Live Status</Label>
                <Switch id="live-toggle" checked={liveRefresh} onCheckedChange={setLiveRefresh} className="scale-75" />
              </div>
              <Button variant="ghost" size="icon" onClick={fetchMapData} disabled={loading} title="Refresh">
                <RefreshCw className={`h-4 w-4 ${loading ? "animate-spin" : ""}`} />
              </Button>
              <Button variant="ghost" size="sm" onClick={handlePingAll} disabled={isPingingAll || nodes.length === 0} title="Ping All Devices" className="gap-1 text-xs">
                <Activity className={`h-4 w-4 ${isPingingAll ? "animate-spin" : ""}`} />
                {isPingingAll ? "Pinging..." : "Ping All"}
              </Button>
              {isAdmin && (
                <Button variant="ghost" size="sm" onClick={() => { setPlaceDeviceOpen(true); fetchUnassigned(); }} title="Place existing device on map" className="gap-1 text-xs">
                  <MapPin className="h-4 w-4" />
                  Place Device
                </Button>
              )}
              {isAdmin && (
                <Button variant="ghost" size="icon" onClick={() => setSettingsOpen(true)} title="Map Settings">
                  <Settings className="h-4 w-4" />
                </Button>
              )}
              {isAdmin && (
                <>
                  <Button variant="ghost" size="icon" onClick={handleExport} title="Export Map">
                    <Download className="h-4 w-4" />
                  </Button>
                  <Button variant="ghost" size="icon" onClick={() => importInputRef.current?.click()} title="Import Map">
                    <Upload className="h-4 w-4" />
                  </Button>
                  <input type="file" ref={importInputRef} onChange={handleImport} accept=".json" className="hidden" />
                </>
              )}
              <Button variant="ghost" size="icon" onClick={() => {
                const next = !legendVisible;
                setLegendVisible(next);
                try { localStorage.setItem("map-legend-visible", String(next)); } catch {}
              }} title="Connection Legend">
                <Network className="h-4 w-4" />
              </Button>
              <Button variant="ghost" size="icon" onClick={toggleFullscreen} title="Fullscreen">
                <Maximize className="h-4 w-4" />
              </Button>
            </div>
          </div>
        )}

        {/* Map Canvas */}
        <div
          ref={mapWrapperRef}
          className="rounded-lg border border-border overflow-hidden relative"
          style={{
            height: isFullscreen ? "100vh" : "calc(100vh - 220px)",
            background: currentMap?.background_image_url
              ? `url(${currentMap.background_image_url}) center/cover`
              : (currentMap?.background_color || "hsl(220, 25%, 6%)"),
          }}
        >
          <ReactFlow
            nodes={nodes}
            edges={edges}
            onNodesChange={handleNodesChange}
            onEdgesChange={onEdgesChange}
            onConnect={onConnect}
            onEdgeClick={onEdgeClick}
            onPaneClick={() => setContextMenu(null)}
            nodeTypes={nodeTypes}
            nodesDraggable={isAdmin}
            nodesConnectable={isAdmin}
            fitView
            proOptions={{ hideAttribution: true }}
            defaultEdgeOptions={{ type: "default" }}
          >
            <Background variant={BackgroundVariant.Dots} gap={20} size={1} color="hsl(220, 20%, 15%)" />
            <Controls className="!bg-card !border-border !shadow-lg [&>button]:!bg-card [&>button]:!border-border [&>button]:!text-foreground [&>button:hover]:!bg-muted" />
            <MiniMap
              nodeStrokeWidth={3}
              className="!bg-card !border-border"
              maskColor="hsl(220, 25%, 6%, 0.7)"
            />
          </ReactFlow>

          {/* Combined Legend - Draggable */}
          {legendVisible && (
            <div
              className="absolute bg-card/95 backdrop-blur-sm border border-border rounded-lg shadow-xl z-10 max-w-[220px] select-none"
              style={legendPos.x >= 0 && legendPos.y >= 0
                ? { left: legendPos.x, top: legendPos.y }
                : { bottom: 16, right: 16 }
              }
            >
              {/* Drag handle */}
              <div
                className="flex items-center justify-between px-4 pt-3 pb-1 cursor-grab active:cursor-grabbing"
                onMouseDown={(e) => {
                  const el = e.currentTarget.parentElement!;
                  const rect = el.getBoundingClientRect();
                  const parentRect = el.offsetParent?.getBoundingClientRect() || { left: 0, top: 0 };
                  legendDragRef.current = { dragging: true, offsetX: e.clientX - rect.left, offsetY: e.clientY - rect.top };

                  const onMove = (ev: MouseEvent) => {
                    if (!legendDragRef.current.dragging) return;
                    const newX = ev.clientX - parentRect.left - legendDragRef.current.offsetX;
                    const newY = ev.clientY - parentRect.top - legendDragRef.current.offsetY;
                    setLegendPos({ x: Math.max(0, newX), y: Math.max(0, newY) });
                  };
                  const onUp = () => {
                    legendDragRef.current.dragging = false;
                    document.removeEventListener("mousemove", onMove);
                    document.removeEventListener("mouseup", onUp);
                    // persist
                    setLegendPos(prev => {
                      try { localStorage.setItem("map-legend-pos", JSON.stringify(prev)); } catch {}
                      return prev;
                    });
                  };
                  document.addEventListener("mousemove", onMove);
                  document.addEventListener("mouseup", onUp);
                }}
              >
                <span className="text-[10px] text-muted-foreground uppercase tracking-wider">Legend</span>
                <span className="text-muted-foreground/50 text-xs">⠿</span>
              </div>

              <div className="px-4 pb-3">
                {/* Device Status Legend */}
                <h3 className="text-sm font-semibold text-foreground mb-2 flex items-center gap-2">
                  <Activity className="h-4 w-4 text-primary" />
                  Device Status
                </h3>
                <div className="space-y-1.5 text-xs mb-4">
                  {[
                    { status: "online", color: "hsl(150, 100%, 40%)", label: "Online" },
                    { status: "warning", color: "hsl(45, 100%, 55%)", label: "Warning" },
                    { status: "critical", color: "hsl(0, 75%, 50%)", label: "Critical" },
                    { status: "offline", color: "#64748b", label: "Offline" },
                    { status: "unknown", color: "#94a3b8", label: "Unknown" },
                  ].map((s) => (
                    <div key={s.status} className="flex items-center gap-2">
                      <span className="h-3 w-3 rounded-full flex-shrink-0" style={{ backgroundColor: s.color, boxShadow: `0 0 6px ${s.color}` }} />
                      <span className="text-muted-foreground">{s.label}</span>
                    </div>
                  ))}
                </div>

                {/* Connection Types Legend */}
                <h3 className="text-sm font-semibold text-foreground mb-2 flex items-center gap-2">
                  <Network className="h-4 w-4 text-primary" />
                  Connection Types
                </h3>
                <div className="space-y-1.5 text-xs">
                  {CONNECTION_TYPES.map((ct) => (
                    <div key={ct.value} className="flex items-center gap-2">
                      <div className="w-8 h-0.5 rounded-full flex-shrink-0" style={{ backgroundColor: ct.color, boxShadow: `0 0 6px ${ct.color}` }} />
                      <span className="text-muted-foreground">{ct.label}</span>
                    </div>
                  ))}
                </div>
                <div className="flex items-center gap-3 mt-3">
                  <button
                    onClick={() => {
                      const defaultPos = { x: -1, y: -1 };
                      setLegendPos(defaultPos);
                      try { localStorage.setItem("map-legend-pos", JSON.stringify(defaultPos)); } catch {}
                    }}
                    className="text-xs text-primary hover:underline flex items-center gap-1"
                  >
                    <Maximize className="h-3 w-3" />Reset Position
                  </button>
                  <button
                    onClick={() => {
                      setLegendVisible(false);
                      try { localStorage.setItem("map-legend-visible", "false"); } catch {}
                    }}
                    className="text-xs text-primary hover:underline flex items-center gap-1"
                  >
                    <EyeOff className="h-3 w-3" />Hide Legend
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>

        {nodes.length === 0 && !loading && (
          <div className="text-center text-muted-foreground py-8">
            No devices on this map. Add devices from the{" "}
            <a href="/devices" className="text-primary hover:underline">Devices</a> page and assign them to this map.
          </div>
        )}
      </div>

      {/* Context Menu */}
      {contextMenu && (
        <div
          className="fixed z-50 bg-card border border-border rounded-lg shadow-xl py-1 min-w-[160px]"
          style={{ left: contextMenu.x, top: contextMenu.y }}
          onClick={() => setContextMenu(null)}
          onMouseLeave={() => setContextMenu(null)}
        >
          {isAdmin && (
            <button
              className="w-full flex items-center gap-2 px-3 py-2 text-sm text-foreground hover:bg-muted transition-colors"
              onClick={() => {
                const device = devicesCache.current.find(d => d.id === contextMenu.deviceId);
                if (device) {
                  setEditingDevice(device);
                  setDeviceDialogOpen(true);
                }
                setContextMenu(null);
              }}
            >
              <Edit className="h-4 w-4" />
              Edit Device
            </button>
          )}
          <button
            className="w-full flex items-center gap-2 px-3 py-2 text-sm text-foreground hover:bg-muted transition-colors"
            onClick={async () => {
              const deviceId = contextMenu.deviceId;
              setContextMenu(null);
              try {
                await supabase.functions.invoke("ping-device", { body: { device_id: deviceId } });
                toast({ title: "Ping sent" });
                fetchMapData();
              } catch (err: any) {
                toast({ title: "Ping failed", description: err.message, variant: "destructive" });
              }
            }}
          >
            <Activity className="h-4 w-4" />
            Ping Now
          </button>
          {isAdmin && (
            <button
              className="w-full flex items-center gap-2 px-3 py-2 text-sm text-destructive hover:bg-muted transition-colors"
              onClick={async () => {
                const deviceId = contextMenu.deviceId;
                const deviceName = devicesCache.current.find(d => d.id === deviceId)?.name || "this device";
                setContextMenu(null);
                if (!confirm(`Remove "${deviceName}" from this map?`)) return;
                await supabase.from("devices").update({ map_id: null }).eq("id", deviceId);
                toast({ title: "Device removed from map" });
                fetchMapData();
              }}
            >
              <Trash2 className="h-4 w-4" />
              Remove from Map
            </button>
          )}
        </div>
      )}

      {/* Device Edit Dialog */}
      <DeviceFormDialog
        open={deviceDialogOpen}
        onOpenChange={setDeviceDialogOpen}
        device={editingDevice}
        onSaved={() => {
          setDeviceDialogOpen(false);
          setEditingDevice(null);
          fetchMapData();
        }}
      />

      {/* Edge Editor */}
      <EdgeEditor
        open={!!editingEdge}
        onOpenChange={(open) => { if (!open) setEditingEdge(null); }}
        currentType={(editingEdge?.data?.connection_type as string) || "cat5"}
        onSave={handleEdgeSave}
        onDelete={handleEdgeDelete}
      />

      {/* Map Settings Dialog */}
      <Dialog open={settingsOpen} onOpenChange={setSettingsOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Map Settings</DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label>Background Color</Label>
              <div className="flex items-center gap-2">
                <input type="color" value={bgColor} onChange={(e) => setBgColor(e.target.value)} className="h-10 w-14 rounded border border-border cursor-pointer bg-card" />
                <Input value={bgColor} onChange={(e) => setBgColor(e.target.value)} className="flex-1" />
              </div>
            </div>
            <div className="space-y-2">
              <Label>Background Image URL</Label>
              <Input value={bgImageUrl} onChange={(e) => setBgImageUrl(e.target.value)} placeholder="Leave blank for no image" />
            </div>
            <div className="space-y-2">
              <div className="flex items-center gap-3">
                <Switch checked={publicView} onCheckedChange={setPublicView} id="public-view" />
                <Label htmlFor="public-view">Enable Public View</Label>
              </div>
              <p className="text-xs text-muted-foreground">Allow anyone with the link to view this map without logging in.</p>
            </div>
            <div className="space-y-2">
              <Label>Offline Delay (seconds)</Label>
              <Input type="number" min={1} max={300} value={offlineDelay} onChange={(e) => setOfflineDelay(parseInt(e.target.value) || 5)} />
              <p className="text-xs text-muted-foreground">How many seconds a device must fail pings before being marked offline.</p>
            </div>
            {publicView && (
              <div className="space-y-1">
                <Label className="text-xs">Public Link</Label>
                <div className="flex gap-2">
                  <Input value={publicLink} readOnly className="text-xs font-mono" />
                  <Button size="sm" variant="outline" onClick={() => { navigator.clipboard.writeText(publicLink); toast({ title: "Link copied!" }); }}>
                    <Copy className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            )}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setSettingsOpen(false)}>Cancel</Button>
            <Button onClick={handleSaveSettings}>Save Changes</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Share Dialog */}
      <Dialog open={shareOpen} onOpenChange={setShareOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2"><Share2 className="h-5 w-5" />Share Map</DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-2">
            {publicView ? (
              <>
                <p className="text-sm text-muted-foreground">Public view is <span className="text-success font-medium">enabled</span>. Anyone with this link can view the map.</p>
                <div className="flex gap-2">
                  <Input value={publicLink} readOnly className="text-xs font-mono" />
                  <Button size="sm" variant="outline" onClick={() => { navigator.clipboard.writeText(publicLink); toast({ title: "Copied!" }); }}>
                    <Copy className="h-4 w-4" />
                  </Button>
                </div>
                <Button size="sm" variant="outline" onClick={() => window.open(publicLink, "_blank")} className="w-full">
                  <Eye className="h-4 w-4 mr-1" />Open Public View
                </Button>
              </>
            ) : (
              <div className="text-center py-4 text-muted-foreground">
                <Link2 className="h-8 w-8 mx-auto mb-2" />
                <p className="text-sm">Public view is disabled for this map.</p>
                {isAdmin && (
                  <Button size="sm" className="mt-3" onClick={() => { setShareOpen(false); setSettingsOpen(true); }}>
                    Enable in Settings
                  </Button>
                )}
              </div>
            )}
          </div>
        </DialogContent>
      </Dialog>

      {/* Place Device Dialog */}
      <Dialog open={placeDeviceOpen} onOpenChange={setPlaceDeviceOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2"><MapPin className="h-5 w-5" />Place Device on Map</DialogTitle>
          </DialogHeader>
          <div className="space-y-3 py-2">
            <p className="text-sm text-muted-foreground">Select unassigned devices to place on this map.</p>
            {loadingUnassigned ? (
              <p className="text-center text-muted-foreground py-4">Loading...</p>
            ) : unassignedDevices.length === 0 ? (
              <p className="text-center text-muted-foreground py-4">All devices are already assigned to a map.</p>
            ) : (
              <div className="max-h-[300px] overflow-y-auto space-y-1">
                {unassignedDevices.map((d) => (
                  <label key={d.id} className="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-muted cursor-pointer transition-colors">
                    <input
                      type="checkbox"
                      checked={selectedPlaceIds.includes(d.id)}
                      onChange={(e) => {
                        setSelectedPlaceIds((prev) =>
                          e.target.checked ? [...prev, d.id] : prev.filter((id) => id !== d.id)
                        );
                      }}
                      className="rounded border-border"
                    />
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium truncate">{d.name}</p>
                      <p className="text-xs text-muted-foreground truncate">{d.ip_address || "No IP"}</p>
                    </div>
                    <Badge variant="outline" className="text-[10px] shrink-0">{d.type || "server"}</Badge>
                  </label>
                ))}
              </div>
            )}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setPlaceDeviceOpen(false)}>Cancel</Button>
            <Button onClick={handlePlaceDevices} disabled={selectedPlaceIds.length === 0}>
              Place {selectedPlaceIds.length > 0 ? `(${selectedPlaceIds.length})` : ""}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}
