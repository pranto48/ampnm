import { useEffect, useState, useMemo, useCallback } from "react";
import { useParams } from "react-router-dom";
import { supabase } from "@/integrations/supabase/client";
import {
  ReactFlow, MiniMap, Controls, Background,
  type Node, type Edge,
} from "@xyflow/react";
import "@xyflow/react/dist/style.css";
import DeviceNode from "@/components/map/DeviceNode";
import { Badge } from "@/components/ui/badge";
import { Shield } from "lucide-react";

const nodeTypes = { device: DeviceNode };

export default function PublicMapPage() {
  const { mapId } = useParams<{ mapId: string }>();
  const [mapName, setMapName] = useState("Network Map");
  const [nodes, setNodes] = useState<Node[]>([]);
  const [edges, setEdges] = useState<Edge[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchMap = useCallback(async () => {
    if (!mapId) return;
    setLoading(true);

    // Check if map exists and is public
    const { data: map, error: mapError } = await supabase
      .from("maps")
      .select("*")
      .eq("id", mapId)
      .eq("public_view_enabled", true)
      .single();

    if (mapError || !map) {
      setError("Map not found or not publicly shared.");
      setLoading(false);
      return;
    }

    setMapName(map.name);

    // Fetch devices and edges
    const [devRes, edgeRes] = await Promise.all([
      supabase.from("devices").select("*").eq("map_id", mapId),
      supabase.from("device_edges").select("*").eq("map_id", mapId),
    ]);

    const devs = devRes.data ?? [];
    setNodes(devs.map(d => ({
      id: d.id,
      type: "device",
      position: { x: d.x ?? 100, y: d.y ?? 100 },
      data: {
        name: d.name,
        ip_address: d.ip_address,
        status: d.status || "unknown",
        icon: d.type || "server",
        subchoice: d.subchoice,
        icon_url: d.icon_url,
        icon_size: d.icon_size || 40,
        name_text_size: d.name_text_size || 12,
        last_latency: d.last_latency,
      },
    })));

    setEdges((edgeRes.data ?? []).map(e => ({
      id: e.id,
      source: e.source_id,
      target: e.target_id,
      label: e.connection_type || undefined,
      style: { stroke: "hsl(var(--muted-foreground))", strokeWidth: 2 },
      labelStyle: { fill: "hsl(var(--foreground))", fontSize: 10 },
    })));

    setLoading(false);
  }, [mapId]);

  useEffect(() => { fetchMap(); }, [fetchMap]);

  // Auto-refresh every 30s
  useEffect(() => {
    const interval = setInterval(fetchMap, 30000);
    return () => clearInterval(interval);
  }, [fetchMap]);

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <Shield className="h-10 w-10 text-primary animate-pulse" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-center space-y-3">
          <Shield className="h-12 w-12 text-muted-foreground mx-auto" />
          <h1 className="text-xl font-bold text-foreground">Map Unavailable</h1>
          <p className="text-muted-foreground">{error}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="h-screen w-screen bg-background flex flex-col">
      <header className="flex items-center justify-between px-4 py-2 border-b border-border bg-card/95">
        <div className="flex items-center gap-2">
          <Shield className="h-5 w-5 text-primary" />
          <span className="font-bold text-foreground">AMPNM</span>
          <span className="text-muted-foreground text-sm">— {mapName}</span>
        </div>
        <Badge variant="secondary">Public View</Badge>
      </header>
      <div className="flex-1">
        <ReactFlow
          nodes={nodes}
          edges={edges}
          nodeTypes={nodeTypes}
          fitView
          nodesDraggable={false}
          nodesConnectable={false}
          elementsSelectable={false}
          proOptions={{ hideAttribution: true }}
        >
          <Controls showInteractive={false} />
          <MiniMap />
          <Background />
        </ReactFlow>
      </div>
    </div>
  );
}