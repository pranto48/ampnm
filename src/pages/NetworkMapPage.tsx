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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Map, RefreshCw, Plus } from "lucide-react";
import type { Tables } from "@/integrations/supabase/types";

type Device = Tables<"devices">;
type DeviceEdge = Tables<"device_edges">;
type MapRow = Tables<"maps">;

const nodeTypes = { device: DeviceNode };

export default function NetworkMapPage() {
  const { isAdmin, user } = useAuth();
  const { toast } = useToast();

  const [maps, setMaps] = useState<MapRow[]>([]);
  const [currentMapId, setCurrentMapId] = useState<string | null>(null);
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [loading, setLoading] = useState(true);

  // Edge editing
  const [editingEdge, setEditingEdge] = useState<Edge | null>(null);

  // -- Fetch maps --
  useEffect(() => {
    (async () => {
      const { data } = await supabase.from("maps").select("*").order("name");
      if (data && data.length > 0) {
        setMaps(data);
        setCurrentMapId(data[0].id);
      } else if (isAdmin && user) {
        // Auto-create default map
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

  // -- Fetch devices & edges for current map --
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

  useEffect(() => {
    fetchMapData();
  }, [fetchMapData]);

  // -- Realtime subscription --
  useEffect(() => {
    if (!currentMapId) return;
    const channel = supabase
      .channel(`map-${currentMapId}`)
      .on("postgres_changes", { event: "*", schema: "public", table: "devices", filter: `map_id=eq.${currentMapId}` }, () => fetchMapData())
      .subscribe();
    return () => { supabase.removeChannel(channel); };
  }, [currentMapId, fetchMapData]);

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
            await supabase
              .from("devices")
              .update({ x: change.position.x, y: change.position.y })
              .eq("id", change.id);
          }
        }
      }, 300);
    },
    [isAdmin, onNodesChange]
  );

  // -- Connect nodes (create edge) --
  const onConnect = useCallback(
    async (connection: Connection) => {
      if (!isAdmin || !currentMapId) return;
      const { data, error } = await supabase
        .from("device_edges")
        .insert({
          source_id: connection.source!,
          target_id: connection.target!,
          map_id: currentMapId,
          connection_type: "cat5",
        })
        .select()
        .single();

      if (error) {
        toast({ title: "Failed to create connection", description: error.message, variant: "destructive" });
      } else if (data) {
        const newEdge: Edge = {
          id: data.id,
          source: data.source_id,
          target: data.target_id,
          style: { stroke: edgeColorMap["cat5"], strokeWidth: 2 },
          markerEnd: { type: MarkerType.ArrowClosed, color: edgeColorMap["cat5"] },
          data: { connection_type: "cat5" },
          label: "cat5",
          labelStyle: { fill: "#94a3b8", fontSize: 10 },
          labelBgStyle: { fill: "hsl(220 25% 8%)", fillOpacity: 0.9 },
          labelBgPadding: [6, 3] as [number, number],
          labelBgBorderRadius: 4,
        };
        setEdges((eds) => addEdge(newEdge, eds));
      }
    },
    [isAdmin, currentMapId, setEdges, toast]
  );

  // -- Edge click → edit --
  const onEdgeClick = useCallback(
    (_: React.MouseEvent, edge: Edge) => {
      if (!isAdmin) return;
      setEditingEdge(edge);
    },
    [isAdmin]
  );

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

  const currentMap = maps.find((m) => m.id === currentMapId);

  return (
    <AppLayout>
      <div className="space-y-4">
        {/* Toolbar */}
        <div className="flex items-center justify-between flex-wrap gap-2">
          <div className="flex items-center gap-3">
            <Map className="h-7 w-7 text-primary" />
            <h1 className="text-2xl font-bold tracking-tight">Network Map</h1>
          </div>
          <div className="flex items-center gap-2">
            <Select value={currentMapId || ""} onValueChange={setCurrentMapId}>
              <SelectTrigger className="w-[200px]">
                <SelectValue placeholder="Select map" />
              </SelectTrigger>
              <SelectContent>
                {maps.map((m) => (
                  <SelectItem key={m.id} value={m.id}>{m.name}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Button variant="outline" size="sm" onClick={fetchMapData} disabled={loading}>
              <RefreshCw className={`h-4 w-4 mr-1 ${loading ? "animate-spin" : ""}`} />
              Refresh
            </Button>
          </div>
        </div>

        {/* Map Canvas */}
        <div
          className="rounded-lg border border-border overflow-hidden"
          style={{
            height: "calc(100vh - 180px)",
            background: currentMap?.background_color || "hsl(220, 25%, 6%)",
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
        </div>

        {nodes.length === 0 && !loading && (
          <div className="text-center text-muted-foreground py-8">
            No devices on this map. Add devices from the{" "}
            <a href="/devices" className="text-primary hover:underline">Devices</a> page and assign them to this map.
          </div>
        )}
      </div>

      {/* Edge Editor Dialog */}
      <EdgeEditor
        open={!!editingEdge}
        onOpenChange={(open) => { if (!open) setEditingEdge(null); }}
        currentType={(editingEdge?.data?.connection_type as string) || "cat5"}
        onSave={handleEdgeSave}
        onDelete={handleEdgeDelete}
      />
    </AppLayout>
  );
}
