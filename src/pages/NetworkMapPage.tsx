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
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from "@/components/ui/dialog";
import {
  Map, RefreshCw, Plus, Pencil, Trash2, Share2, Settings, Maximize,
  Network, Eye, EyeOff, Copy, Link2,
} from "lucide-react";
import type { Tables } from "@/integrations/supabase/types";

type Device = Tables<"devices">;
type DeviceEdge = Tables<"device_edges">;
type MapRow = Tables<"maps">;

const nodeTypes = { device: DeviceNode };

const CONNECTION_TYPES = [
  { value: "cat5", label: "🔌 CAT5 Cable", color: "#a78bfa" },
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
  const [legendVisible, setLegendVisible] = useState(false);

  // Map settings state
  const [bgColor, setBgColor] = useState("#1e293b");
  const [bgImageUrl, setBgImageUrl] = useState("");
  const [publicView, setPublicView] = useState(false);

  // Live refresh
  const [liveRefresh, setLiveRefresh] = useState(true);

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
    }
  }, [currentMap]);

  // -- Fetch devices & edges --
  const fetchMapData = useCallback(async () => {
    if (!currentMapId) return;
    setLoading(true);

    const [devRes, edgeRes] = await Promise.all([
      supabase.from("devices").select("*").eq("map_id", currentMapId),
      supabase.from("device_edges").select("*").eq("map_id", currentMapId),
    ]);

    if (devRes.data) {
      const flowNodes: Node[] = devRes.data.map((d: Device) => ({
        id: d.id,
        type: "device",
        position: { x: Number(d.x) || 100, y: Number(d.y) || 100 },
        data: {
          name: d.name,
          ip_address: d.ip_address,
          status: d.status || "unknown",
          icon: d.type || "server",
          icon_url: d.icon_url,
          icon_size: d.icon_size || 40,
          name_text_size: d.name_text_size || 12,
        },
      }));
      setNodes(flowNodes);
    }

    if (edgeRes.data) {
      const flowEdges: Edge[] = edgeRes.data.map((e: DeviceEdge) => ({
        id: e.id,
        source: e.source_id,
        target: e.target_id,
        type: "default",
        style: { stroke: edgeColorMap[e.connection_type || "cat5"] || "#a78bfa", strokeWidth: 2 },
        markerEnd: { type: MarkerType.ArrowClosed, color: edgeColorMap[e.connection_type || "cat5"] || "#a78bfa" },
        data: { connection_type: e.connection_type || "cat5" },
        label: e.connection_type || "cat5",
        labelStyle: { fill: "#94a3b8", fontSize: 10 },
        labelBgStyle: { fill: "hsl(220 25% 8%)", fillOpacity: 0.9 },
        labelBgPadding: [6, 3] as [number, number],
        labelBgBorderRadius: 4,
      }));
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
      interval = setInterval(() => fetchMapData(), 30000);
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
        .insert({ source_id: connection.source!, target_id: connection.target!, map_id: currentMapId, connection_type: "cat5" })
        .select()
        .single();

      if (error) {
        toast({ title: "Failed to create connection", description: error.message, variant: "destructive" });
      } else if (data) {
        const newEdge: Edge = {
          id: data.id, source: data.source_id, target: data.target_id,
          style: { stroke: edgeColorMap["cat5"], strokeWidth: 2 },
          markerEnd: { type: MarkerType.ArrowClosed, color: edgeColorMap["cat5"] },
          data: { connection_type: "cat5" }, label: "cat5",
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
    const { error } = await supabase.from("maps").update({
      background_color: bgColor,
      background_image_url: bgImageUrl.trim() || null,
      public_view_enabled: publicView,
    }).eq("id", currentMapId);
    if (error) { toast({ title: "Failed", description: error.message, variant: "destructive" }); return; }
    setMaps((prev) => prev.map((m) => m.id === currentMapId ? { ...m, background_color: bgColor, background_image_url: bgImageUrl.trim() || null, public_view_enabled: publicView } : m));
    setSettingsOpen(false);
    toast({ title: "Map settings saved" });
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

  // Public link
  const publicLink = currentMapId ? `${window.location.origin}/map/public/${currentMapId}` : "";

  return (
    <AppLayout>
      <div className="space-y-3">
        {/* Top Bar: Title + Map selector + Map management buttons */}
        <div className="flex items-center justify-between flex-wrap gap-2">
          <div className="flex items-center gap-3">
            <Map className="h-7 w-7 text-primary" />
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
              {isAdmin && (
                <Button variant="ghost" size="icon" onClick={() => setSettingsOpen(true)} title="Map Settings">
                  <Settings className="h-4 w-4" />
                </Button>
              )}
              <Button variant="ghost" size="icon" onClick={() => setLegendVisible(!legendVisible)} title="Connection Legend">
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

          {/* Connection Type Legend */}
          {legendVisible && (
            <div className="absolute bottom-4 right-4 bg-card/95 backdrop-blur-sm border border-border rounded-lg p-4 shadow-xl z-10">
              <h3 className="text-sm font-semibold text-foreground mb-3 flex items-center gap-2">
                <Network className="h-4 w-4 text-primary" />
                Connection Types
              </h3>
              <div className="space-y-2 text-xs">
                {CONNECTION_TYPES.map((ct) => (
                  <div key={ct.value} className="flex items-center gap-2">
                    <div className="w-8 h-0.5 rounded-full" style={{ backgroundColor: ct.color, boxShadow: `0 0 6px ${ct.color}` }} />
                    <span className="text-muted-foreground">{ct.label}</span>
                  </div>
                ))}
              </div>
              <button onClick={() => setLegendVisible(false)} className="mt-3 text-xs text-primary hover:underline flex items-center gap-1">
                <EyeOff className="h-3 w-3" />Hide Legend
              </button>
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
    </AppLayout>
  );
}
