import { useState, useEffect, useCallback, useRef } from "react";
import { AppLayout } from "@/components/layout/AppLayout";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { toast } from "sonner";
import {
  Plus, Trash2, Edit, MapPin, Building2, Server, Cable as CableIcon,
  LayoutGrid, Grid3X3, CircleDot, Plug, PenTool, List
} from "lucide-react";
import { FloorPlanCanvas } from "@/components/floor-plan/FloorPlanCanvas";
import { CanvasToolbar, type ToolMode } from "@/components/floor-plan/CanvasToolbar";
import { PropertiesPanel, type SelectedItem } from "@/components/floor-plan/PropertiesPanel";
import { FloorPlanUploader } from "@/components/floor-plan/FloorPlanUploader";
import { CanvasContextMenu, type ContextMenuState } from "@/components/floor-plan/CanvasContextMenu";

/* ─── types ─── */
interface FloorPlan { id: string; name: string; image_url: string | null; width: number; height: number; }
interface RackLocation { id: string; floor_plan_id: string; name: string; x: number; y: number; rack_units: number; rotation: number; label_visible: boolean; }
interface PatchPanel { id: string; rack_id: string; name: string; port_count: number; rack_position: number; panel_type: string; }
interface SwitchPort { id: string; device_id: string; port_number: number; port_label: string | null; status: string; speed: string; vlan: string | null; connected_device: string | null; notes: string | null; }
interface CableRun { id: string; floor_plan_id: string | null; cable_type: string; cable_color: string; cable_length: string | null; label: string | null; source_type: string; source_id: string; source_port: number; dest_type: string; dest_id: string; dest_port: number; notes: string | null; }
interface Device { id: string; name: string; type: string | null; x: number | null; y: number | null; }
interface Annotation { id: string; floor_plan_id: string; x: number; y: number; text: string; font_size: number; color: string; type: string; width: number | null; height: number | null; }

const CABLE_TYPES = ["cat5", "cat5e", "cat6", "cat6a", "cat7", "fiber-sm", "fiber-mm", "coax", "dac"];
const CABLE_COLORS = ["blue", "red", "green", "yellow", "orange", "white", "gray", "purple", "black"];
const PORT_STATUSES = ["active", "inactive", "error", "reserved"];
const cableColorHex = (c: string) => {
  const m: Record<string, string> = { blue: "#3b82f6", red: "#ef4444", green: "#22c55e", yellow: "#eab308", orange: "#f97316", white: "#e2e8f0", gray: "#64748b", purple: "#a855f7", black: "#1e293b" };
  return m[c] || "#64748b";
};
const statusColor = (s: string) => {
  switch (s) { case "active": return "bg-emerald-500"; case "error": return "bg-red-500"; case "reserved": return "bg-amber-500"; default: return "bg-muted"; }
};

type ViewMode = "canvas" | "list";

export default function FloorPlanPage() {
  const { isAdmin } = useAuth();
  const [viewMode, setViewMode] = useState<ViewMode>("canvas");
  const [floorPlans, setFloorPlans] = useState<FloorPlan[]>([]);
  const [selectedPlan, setSelectedPlan] = useState<FloorPlan | null>(null);
  const [racks, setRacks] = useState<RackLocation[]>([]);
  const [panels, setPanels] = useState<PatchPanel[]>([]);
  const [switchPorts, setSwitchPorts] = useState<SwitchPort[]>([]);
  const [cables, setCables] = useState<CableRun[]>([]);
  const [devices, setDevices] = useState<Device[]>([]);
  const [annotations, setAnnotations] = useState<Annotation[]>([]);
  const [loading, setLoading] = useState(true);

  // Canvas state
  const canvasSvgRef = useRef<SVGSVGElement>(null);
  const [activeTool, setActiveTool] = useState<ToolMode>("select");
  const [snapToGrid, setSnapToGrid] = useState(true);
  const [selectedItem, setSelectedItem] = useState<SelectedItem | null>(null);
  const [zoom, setZoom] = useState(1);
  const [panX, setPanX] = useState(0);
  const [panY, setPanY] = useState(0);

  // Dialogs
  const [planDialog, setPlanDialog] = useState(false);
  const [uploadDialog, setUploadDialog] = useState(false);
  const [rackDialog, setRackDialog] = useState(false);
  const [panelDialog, setPanelDialog] = useState(false);
  const [portDialog, setPortDialog] = useState(false);
  const [cableDialog, setCableDialog] = useState(false);
  const [editItem, setEditItem] = useState<any>(null);
  const [devicePickerDialog, setDevicePickerDialog] = useState(false);
  const [pendingDevicePos, setPendingDevicePos] = useState<{ x: number; y: number } | null>(null);
  const [labelDialog, setLabelDialog] = useState(false);
  const [pendingLabelPos, setPendingLabelPos] = useState<{ x: number; y: number } | null>(null);

  // Cable drawing & context menu
  const [cableSource, setCableSource] = useState<{ kind: string; id: string } | null>(null);
  const [contextMenu, setContextMenu] = useState<ContextMenuState | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    const [fp, dev] = await Promise.all([
      supabase.from("floor_plans").select("*").order("created_at"),
      supabase.from("devices").select("id, name, type, x, y"),
    ]);
    setFloorPlans((fp.data ?? []) as FloorPlan[]);
    setDevices((dev.data ?? []) as Device[]);
    if (fp.data && fp.data.length > 0 && !selectedPlan) setSelectedPlan(fp.data[0] as FloorPlan);
    setLoading(false);
  }, []);

  useEffect(() => { load(); }, [load]);

  useEffect(() => {
    if (!selectedPlan) return;
    (async () => {
      const [r, c, a] = await Promise.all([
        supabase.from("rack_locations").select("*").eq("floor_plan_id", selectedPlan.id),
        supabase.from("cable_runs").select("*").eq("floor_plan_id", selectedPlan.id),
        supabase.from("floor_plan_annotations").select("*").eq("floor_plan_id", selectedPlan.id),
      ]);
      const rackData = (r.data ?? []).map((x: any) => ({ ...x, rotation: x.rotation ?? 0, label_visible: x.label_visible ?? true }));
      setRacks(rackData);
      setCables((c.data ?? []) as CableRun[]);
      setAnnotations((a.data ?? []) as Annotation[]);
      // Load panels for racks
      const rackIds = rackData.map((x: any) => x.id);
      if (rackIds.length) {
        const p = await supabase.from("patch_panels").select("*").in("rack_id", rackIds);
        setPanels((p.data ?? []) as PatchPanel[]);
      } else { setPanels([]); }
    })();
  }, [selectedPlan]);

  // Load switch ports
  useEffect(() => {
    if (!devices.length) return;
    supabase.from("switch_ports").select("*").then(({ data }) => setSwitchPorts((data ?? []) as SwitchPort[]));
  }, [devices]);

  /* ─── CRUD helpers ─── */
  const savePlan = async (name: string, imageUrl: string) => {
    if (editItem?.id) {
      await supabase.from("floor_plans").update({ name, image_url: imageUrl || null }).eq("id", editItem.id);
    } else {
      const { data: { user } } = await supabase.auth.getUser();
      await supabase.from("floor_plans").insert({ name, image_url: imageUrl || null, user_id: user!.id });
    }
    setPlanDialog(false); setEditItem(null); load();
    toast.success(editItem?.id ? "Floor plan updated" : "Floor plan created");
  };

  const deletePlan = async (id: string) => {
    if (!confirm("Delete this floor plan and all its data?")) return;
    await supabase.from("floor_plans").delete().eq("id", id);
    if (selectedPlan?.id === id) setSelectedPlan(null);
    load(); toast.success("Floor plan deleted");
  };

  const saveRack = async (name: string, rackUnits: number) => {
    if (!selectedPlan) return;
    const x = editItem?.x ?? 100;
    const y = editItem?.y ?? 100;
    if (editItem?.id) {
      await supabase.from("rack_locations").update({ name, rack_units: rackUnits }).eq("id", editItem.id);
    } else {
      await supabase.from("rack_locations").insert({ floor_plan_id: selectedPlan.id, name, rack_units: rackUnits, x, y });
    }
    setRackDialog(false); setEditItem(null);
    const r = await supabase.from("rack_locations").select("*").eq("floor_plan_id", selectedPlan.id);
    setRacks((r.data ?? []).map((x: any) => ({ ...x, rotation: x.rotation ?? 0, label_visible: x.label_visible ?? true })));
    toast.success("Rack saved");
  };

  const deleteRack = async (id: string) => {
    if (!confirm("Delete this rack and all its panels?")) return;
    await supabase.from("rack_locations").delete().eq("id", id);
    setRacks(racks.filter(r => r.id !== id));
    setPanels(panels.filter(p => p.rack_id !== id));
    toast.success("Rack deleted");
  };

  const savePanel = async (rackId: string, name: string, portCount: number, rackPosition: number, panelType: string) => {
    if (editItem?.id) {
      await supabase.from("patch_panels").update({ name, port_count: portCount, rack_position: rackPosition, panel_type: panelType }).eq("id", editItem.id);
    } else {
      await supabase.from("patch_panels").insert({ rack_id: rackId, name, port_count: portCount, rack_position: rackPosition, panel_type: panelType });
    }
    setPanelDialog(false); setEditItem(null);
    const rackIds = racks.map(r => r.id);
    if (rackIds.length) {
      const p = await supabase.from("patch_panels").select("*").in("rack_id", rackIds);
      setPanels((p.data ?? []) as PatchPanel[]);
    }
    toast.success("Panel saved");
  };

  const deletePanel = async (id: string) => {
    await supabase.from("patch_panels").delete().eq("id", id);
    setPanels(panels.filter(p => p.id !== id));
    toast.success("Panel deleted");
  };

  const saveSwitchPort = async (deviceId: string, portNumber: number, portLabel: string, status: string, speed: string, vlan: string, connectedDevice: string, notes: string) => {
    if (editItem?.id) {
      await supabase.from("switch_ports").update({ port_label: portLabel || null, status, speed, vlan: vlan || null, connected_device: connectedDevice || null, notes: notes || null }).eq("id", editItem.id);
    } else {
      await supabase.from("switch_ports").insert({ device_id: deviceId, port_number: portNumber, port_label: portLabel || null, status, speed, vlan: vlan || null, connected_device: connectedDevice || null, notes: notes || null });
    }
    setPortDialog(false); setEditItem(null);
    const sp = await supabase.from("switch_ports").select("*");
    setSwitchPorts((sp.data ?? []) as SwitchPort[]);
    toast.success("Port saved");
  };

  const deletePort = async (id: string) => {
    await supabase.from("switch_ports").delete().eq("id", id);
    setSwitchPorts(switchPorts.filter(p => p.id !== id));
    toast.success("Port deleted");
  };

  const saveCable = async (cableType: string, cableColor: string, cableLength: string, label: string, sourceType: string, sourceId: string, sourcePort: number, destType: string, destId: string, destPort: number, notes: string) => {
    const payload = { floor_plan_id: selectedPlan?.id, cable_type: cableType, cable_color: cableColor, cable_length: cableLength || null, label: label || null, source_type: sourceType, source_id: sourceId, source_port: sourcePort, dest_type: destType, dest_id: destId, dest_port: destPort, notes: notes || null };
    if (editItem?.id) {
      await supabase.from("cable_runs").update(payload).eq("id", editItem.id);
    } else {
      await supabase.from("cable_runs").insert(payload);
    }
    setCableDialog(false); setEditItem(null);
    if (selectedPlan) {
      const c = await supabase.from("cable_runs").select("*").eq("floor_plan_id", selectedPlan.id);
      setCables((c.data ?? []) as CableRun[]);
    }
    toast.success("Cable run saved");
  };

  const deleteCable = async (id: string) => {
    await supabase.from("cable_runs").delete().eq("id", id);
    setCables(cables.filter(c => c.id !== id));
    toast.success("Cable run deleted");
  };

  /* ─── Canvas handlers ─── */
  const handleCanvasClick = async (x: number, y: number) => {
    if (activeTool === "add-rack") {
      setPendingLabelPos(null);
      setEditItem({ x, y });
      setRackDialog(true);
    } else if (activeTool === "add-device") {
      setPendingDevicePos({ x, y });
      setDevicePickerDialog(true);
    } else if (activeTool === "add-label") {
      setPendingLabelPos({ x, y });
      setLabelDialog(true);
    }
  };

  const placeDevice = async (deviceId: string) => {
    if (!pendingDevicePos) return;
    await supabase.from("devices").update({ x: pendingDevicePos.x, y: pendingDevicePos.y }).eq("id", deviceId);
    setDevicePickerDialog(false); setPendingDevicePos(null);
    const dev = await supabase.from("devices").select("id, name, type, x, y");
    setDevices((dev.data ?? []) as Device[]);
    toast.success("Device placed on floor plan");
  };

  const saveLabel = async (text: string, fontSize: number, color: string, type: string) => {
    if (!selectedPlan || !pendingLabelPos) return;
    await supabase.from("floor_plan_annotations").insert({
      floor_plan_id: selectedPlan.id, x: pendingLabelPos.x, y: pendingLabelPos.y,
      text, font_size: fontSize, color, type,
      width: type === "zone" ? 200 : null, height: type === "zone" ? 150 : null,
    });
    setLabelDialog(false); setPendingLabelPos(null);
    const a = await supabase.from("floor_plan_annotations").select("*").eq("floor_plan_id", selectedPlan.id);
    setAnnotations((a.data ?? []) as Annotation[]);
    toast.success("Annotation added");
  };

  const handleCableEndpointClick = (kind: "rack" | "device", id: string) => {
    if (!cableSource) {
      setCableSource({ kind, id });
      toast.info("Now click the destination equipment");
    } else {
      setEditItem({ source_id: cableSource.id, source_type: cableSource.kind === "rack" ? "patch_panel" : "switch", dest_id: id, dest_type: kind === "rack" ? "patch_panel" : "switch" });
      setCableDialog(true);
      setCableSource(null);
    }
  };

  const handleMoveItem = async (kind: string, id: string, x: number, y: number) => {
    if (kind === "rack") setRacks(prev => prev.map(r => r.id === id ? { ...r, x, y } : r));
    else if (kind === "device") setDevices(prev => prev.map(d => d.id === id ? { ...d, x, y } : d));
    else if (kind === "annotation") setAnnotations(prev => prev.map(a => a.id === id ? { ...a, x, y } : a));
  };

  useEffect(() => {
    const handler = async () => {
      if (!selectedItem) return;
      if (selectedItem.kind === "rack") {
        await supabase.from("rack_locations").update({ x: selectedItem.x, y: selectedItem.y, name: selectedItem.name, rack_units: selectedItem.rack_units, rotation: selectedItem.rotation }).eq("id", selectedItem.id);
      } else if (selectedItem.kind === "device") {
        await supabase.from("devices").update({ x: selectedItem.x, y: selectedItem.y }).eq("id", selectedItem.id);
      } else if (selectedItem.kind === "cable") {
        await supabase.from("cable_runs").update({ cable_type: selectedItem.cable_type, cable_color: selectedItem.cable_color, cable_length: selectedItem.cable_length, label: selectedItem.label }).eq("id", selectedItem.id);
      } else if (selectedItem.kind === "annotation") {
        await supabase.from("floor_plan_annotations").update({ text: selectedItem.text, font_size: selectedItem.font_size, color: selectedItem.color }).eq("id", selectedItem.id);
      }
    };
    const t = setTimeout(handler, 500);
    return () => clearTimeout(t);
  }, [selectedItem]);

  const handlePropertyUpdate = (item: SelectedItem) => {
    setSelectedItem(item);
    if (item.kind === "rack") setRacks(prev => prev.map(r => r.id === item.id ? { ...r, name: item.name, rack_units: item.rack_units, x: item.x, y: item.y, rotation: item.rotation, label_visible: item.label_visible } : r));
    else if (item.kind === "device") setDevices(prev => prev.map(d => d.id === item.id ? { ...d, x: item.x, y: item.y } : d));
    else if (item.kind === "annotation") setAnnotations(prev => prev.map(a => a.id === item.id ? { ...a, text: item.text, font_size: item.font_size, color: item.color } : a));
  };

  const handleDeleteSelected = async () => {
    if (!selectedItem || !confirm("Delete this item?")) return;
    if (selectedItem.kind === "rack") { await supabase.from("rack_locations").delete().eq("id", selectedItem.id); setRacks(prev => prev.filter(r => r.id !== selectedItem.id)); }
    else if (selectedItem.kind === "cable") { await supabase.from("cable_runs").delete().eq("id", selectedItem.id); setCables(prev => prev.filter(c => c.id !== selectedItem.id)); }
    else if (selectedItem.kind === "annotation") { await supabase.from("floor_plan_annotations").delete().eq("id", selectedItem.id); setAnnotations(prev => prev.filter(a => a.id !== selectedItem.id)); }
    setSelectedItem(null); toast.success("Deleted");
  };

  const handleContextMenu = (kind: string, id: string, clientX: number, clientY: number) => {
    setContextMenu({ kind, id, x: clientX, y: clientY });
  };

  const handleCtxEdit = (kind: string, id: string) => {
    // Select the item to open properties panel
    if (kind === "rack") {
      const r = racks.find(r => r.id === id);
      if (r) setSelectedItem({ kind: "rack", id: r.id, name: r.name, rack_units: r.rack_units, x: r.x, y: r.y, rotation: r.rotation, label_visible: r.label_visible });
    } else if (kind === "device") {
      const d = devices.find(d => d.id === id);
      if (d) setSelectedItem({ kind: "device", id: d.id, name: d.name, type: d.type, x: d.x ?? 0, y: d.y ?? 0 });
    } else if (kind === "cable") {
      const c = cables.find(c => c.id === id);
      if (c) setSelectedItem({ kind: "cable", id: c.id, cable_type: c.cable_type, cable_color: c.cable_color, cable_length: c.cable_length, label: c.label, notes: c.notes });
    } else if (kind === "annotation") {
      const a = annotations.find(a => a.id === id);
      if (a) setSelectedItem({ kind: "annotation", id: a.id, text: a.text, font_size: a.font_size, color: a.color, type: a.type, width: a.width, height: a.height });
    }
  };

  const handleCtxDelete = async (kind: string, id: string) => {
    if (!confirm("Delete this item?")) return;
    if (kind === "rack") { await supabase.from("rack_locations").delete().eq("id", id); setRacks(prev => prev.filter(r => r.id !== id)); }
    else if (kind === "cable") { await supabase.from("cable_runs").delete().eq("id", id); setCables(prev => prev.filter(c => c.id !== id)); }
    else if (kind === "annotation") { await supabase.from("floor_plan_annotations").delete().eq("id", id); setAnnotations(prev => prev.filter(a => a.id !== id)); }
    else if (kind === "device") { await supabase.from("devices").update({ x: null, y: null }).eq("id", id); setDevices(prev => prev.map(d => d.id === id ? { ...d, x: null, y: null } : d)); }
    if (selectedItem?.id === id) setSelectedItem(null);
    toast.success("Deleted");
  };

  const handleCtxDuplicate = async (kind: string, id: string) => {
    if (!selectedPlan) return;
    if (kind === "rack") {
      const r = racks.find(r => r.id === id);
      if (!r) return;
      const { data } = await supabase.from("rack_locations").insert({ floor_plan_id: selectedPlan.id, name: r.name + " (copy)", x: (r.x || 0) + 40, y: (r.y || 0) + 40, rack_units: r.rack_units, rotation: r.rotation, label_visible: r.label_visible }).select().single();
      if (data) setRacks(prev => [...prev, data as any]);
      toast.success("Rack duplicated");
    } else if (kind === "annotation") {
      const a = annotations.find(a => a.id === id);
      if (!a) return;
      const { data } = await supabase.from("floor_plan_annotations").insert({ floor_plan_id: selectedPlan.id, x: a.x + 40, y: a.y + 40, text: a.text + " (copy)", font_size: a.font_size, color: a.color, type: a.type, width: a.width, height: a.height }).select().single();
      if (data) setAnnotations(prev => [...prev, data as any]);
      toast.success("Annotation duplicated");
    }
  };

  const handleImageUploaded = (url: string) => {
    if (selectedPlan) {
      setSelectedPlan({ ...selectedPlan, image_url: url });
      setFloorPlans(prev => prev.map(fp => fp.id === selectedPlan.id ? { ...fp, image_url: url } : fp));
    }
  };

  const placedDevices = devices.filter(d => d.x != null && d.y != null).map(d => ({ id: d.id, name: d.name, type: d.type, x: d.x!, y: d.y! }));

  if (loading) return <AppLayout><div className="flex items-center justify-center h-64 text-muted-foreground">Loading…</div></AppLayout>;

  return (
    <AppLayout>
      <div className="space-y-4">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Building2 className="h-7 w-7 text-primary" />
            <h1 className="text-2xl font-bold text-foreground">Floor Plan & Cable Management</h1>
          </div>
          <div className="flex items-center gap-2">
            {/* View toggle */}
            <div className="flex items-center bg-muted rounded-lg p-0.5">
              <Button
                variant={viewMode === "canvas" ? "default" : "ghost"}
                size="sm"
                className="gap-1.5 h-8"
                onClick={() => setViewMode("canvas")}
              >
                <PenTool className="h-3.5 w-3.5" /> Canvas
              </Button>
              <Button
                variant={viewMode === "list" ? "default" : "ghost"}
                size="sm"
                className="gap-1.5 h-8"
                onClick={() => setViewMode("list")}
              >
                <List className="h-3.5 w-3.5" /> List
              </Button>
            </div>
            {isAdmin && (
              <Button onClick={() => { setEditItem(null); setPlanDialog(true); }} className="gap-2">
                <Plus className="h-4 w-4" /> New Floor Plan
              </Button>
            )}
          </div>
        </div>

        {/* Floor plan selector */}
        {floorPlans.length > 0 && (
          <div className="flex gap-2 flex-wrap items-center">
            {floorPlans.map(fp => (
              <Button key={fp.id} variant={selectedPlan?.id === fp.id ? "default" : "outline"} size="sm" onClick={() => { setSelectedPlan(fp); setSelectedItem(null); }} className="gap-2">
                <MapPin className="h-3 w-3" /> {fp.name}
                {isAdmin && (
                  <>
                    <Edit className="h-3 w-3 ml-1 opacity-60 hover:opacity-100" onClick={(e) => { e.stopPropagation(); setEditItem(fp); setPlanDialog(true); }} />
                    <Trash2 className="h-3 w-3 opacity-60 hover:opacity-100 text-destructive" onClick={(e) => { e.stopPropagation(); deletePlan(fp.id); }} />
                  </>
                )}
              </Button>
            ))}
            {selectedPlan && (
              <div className="flex gap-2 ml-4">
                <Badge variant="secondary" className="gap-1"><Server className="h-3 w-3" />{racks.length} Racks</Badge>
                <Badge variant="secondary" className="gap-1"><CableIcon className="h-3 w-3" />{cables.length} Cables</Badge>
              </div>
            )}
          </div>
        )}

        {!selectedPlan && floorPlans.length === 0 && (
          <div className="flex items-center justify-center h-64 text-muted-foreground border border-dashed border-border rounded-lg">
            No floor plans yet. Create one to get started.
          </div>
        )}

        {/* ═══════════ CANVAS VIEW ═══════════ */}
        {selectedPlan && viewMode === "canvas" && (
          <div className="flex gap-3" style={{ height: "calc(100vh - 220px)" }}>
            <CanvasToolbar
              activeTool={activeTool} onToolChange={setActiveTool}
              onZoomIn={() => setZoom(z => Math.min(5, z * 1.2))}
              onZoomOut={() => setZoom(z => Math.max(0.2, z / 1.2))}
              onZoomReset={() => { setZoom(1); setPanX(0); setPanY(0); }}
              onUpload={() => setUploadDialog(true)}
              snapToGrid={snapToGrid} onToggleSnap={() => setSnapToGrid(s => !s)}
              isAdmin={isAdmin} onDeleteSelected={handleDeleteSelected} hasSelection={!!selectedItem}
              canvasRef={canvasSvgRef} planName={selectedPlan.name}
            />
            <div className="flex-1 min-w-0">
              <FloorPlanCanvas
                backgroundUrl={selectedPlan.image_url} width={selectedPlan.width || 1200} height={selectedPlan.height || 800}
                racks={racks} devices={placedDevices} cables={cables} annotations={annotations}
                activeTool={activeTool} snapToGrid={snapToGrid} selectedItem={selectedItem}
                onSelectItem={setSelectedItem} onMoveItem={handleMoveItem}
                onCanvasClick={handleCanvasClick} onCableEndpointClick={handleCableEndpointClick}
                zoom={zoom} panX={panX} panY={panY}
                onPanChange={(x, y) => { setPanX(x); setPanY(y); }} onZoomChange={setZoom}
                svgRef={canvasSvgRef}
                onContextMenu={handleContextMenu}
              />
            </div>
            {selectedItem && (
              <PropertiesPanel item={selectedItem} onUpdate={handlePropertyUpdate} onClose={() => setSelectedItem(null)} isAdmin={isAdmin} />
            )}
          </div>
        )}

        {/* Context menu */}
        {contextMenu && (
          <CanvasContextMenu
            menu={contextMenu}
            onClose={() => setContextMenu(null)}
            onEdit={handleCtxEdit}
            onDelete={handleCtxDelete}
            onDuplicate={handleCtxDuplicate}
            isAdmin={isAdmin}
          />
        )}

        {/* ═══════════ LIST VIEW ═══════════ */}
        {selectedPlan && viewMode === "list" && (
          <Tabs defaultValue="overview" className="space-y-4">
            <TabsList>
              <TabsTrigger value="overview" className="gap-2"><LayoutGrid className="h-4 w-4" />Overview</TabsTrigger>
              <TabsTrigger value="racks" className="gap-2"><Server className="h-4 w-4" />Racks & Panels</TabsTrigger>
              <TabsTrigger value="ports" className="gap-2"><Grid3X3 className="h-4 w-4" />Switch Ports</TabsTrigger>
              <TabsTrigger value="cables" className="gap-2"><CableIcon className="h-4 w-4" />Cable Runs</TabsTrigger>
            </TabsList>

            {/* Overview */}
            <TabsContent value="overview">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card><CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Racks</CardTitle></CardHeader><CardContent><div className="text-3xl font-bold text-foreground">{racks.length}</div></CardContent></Card>
                <Card><CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Patch Panels</CardTitle></CardHeader><CardContent><div className="text-3xl font-bold text-foreground">{panels.length}</div></CardContent></Card>
                <Card><CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Cable Runs</CardTitle></CardHeader><CardContent><div className="text-3xl font-bold text-foreground">{cables.length}</div></CardContent></Card>
              </div>
              {selectedPlan.image_url && (
                <Card className="mt-4"><CardContent className="p-2"><img src={selectedPlan.image_url} alt={selectedPlan.name} className="w-full rounded-lg max-h-[500px] object-contain" /></CardContent></Card>
              )}
            </TabsContent>

            {/* Racks & Panels */}
            <TabsContent value="racks" className="space-y-4">
              {isAdmin && <Button size="sm" onClick={() => { setEditItem(null); setRackDialog(true); }} className="gap-2"><Plus className="h-4 w-4" />Add Rack</Button>}
              {racks.map(rack => (
                <Card key={rack.id}>
                  <CardHeader className="pb-2 flex flex-row items-center justify-between">
                    <div className="flex items-center gap-2">
                      <Server className="h-5 w-5 text-primary" />
                      <CardTitle className="text-base">{rack.name}</CardTitle>
                      <Badge variant="secondary">{rack.rack_units}U</Badge>
                    </div>
                    {isAdmin && (
                      <div className="flex gap-1">
                        <Button variant="ghost" size="icon" onClick={() => { setEditItem(rack); setRackDialog(true); }}><Edit className="h-4 w-4" /></Button>
                        <Button variant="ghost" size="icon" onClick={() => deleteRack(rack.id)}><Trash2 className="h-4 w-4 text-destructive" /></Button>
                        <Button variant="outline" size="sm" onClick={() => { setEditItem({ rack_id: rack.id }); setPanelDialog(true); }} className="gap-1"><Plus className="h-3 w-3" />Panel</Button>
                      </div>
                    )}
                  </CardHeader>
                  <CardContent>
                    {panels.filter(p => p.rack_id === rack.id).length === 0 && <p className="text-sm text-muted-foreground">No panels in this rack.</p>}
                    <div className="space-y-2">
                      {panels.filter(p => p.rack_id === rack.id).sort((a, b) => a.rack_position - b.rack_position).map(panel => (
                        <div key={panel.id} className="flex items-center justify-between p-3 rounded-lg bg-muted/30 border border-border">
                          <div className="flex items-center gap-3">
                            <Plug className="h-4 w-4 text-muted-foreground" />
                            <span className="font-medium text-foreground">{panel.name}</span>
                            <Badge variant="outline">{panel.port_count} ports</Badge>
                            <Badge variant="outline">U{panel.rack_position}</Badge>
                            <Badge variant="secondary">{panel.panel_type.toUpperCase()}</Badge>
                          </div>
                          {isAdmin && (
                            <div className="flex gap-1">
                              <Button variant="ghost" size="icon" onClick={() => { setEditItem(panel); setPanelDialog(true); }}><Edit className="h-3 w-3" /></Button>
                              <Button variant="ghost" size="icon" onClick={() => deletePanel(panel.id)}><Trash2 className="h-3 w-3 text-destructive" /></Button>
                            </div>
                          )}
                          <div className="flex gap-0.5 flex-wrap max-w-[200px]">
                            {Array.from({ length: Math.min(panel.port_count, 48) }, (_, i) => {
                              const cableOnPort = cables.find(c => (c.source_type === "patch_panel" && c.source_id === panel.id && c.source_port === i + 1) || (c.dest_type === "patch_panel" && c.dest_id === panel.id && c.dest_port === i + 1));
                              return (
                                <div key={i} className={`w-3 h-3 rounded-sm border border-border ${cableOnPort ? "" : "bg-muted/50"}`}
                                  style={cableOnPort ? { backgroundColor: cableColorHex(cableOnPort.cable_color) } : {}}
                                  title={`Port ${i + 1}${cableOnPort ? ` - ${cableOnPort.label || cableOnPort.cable_type}` : ""}`}
                                />
                              );
                            })}
                          </div>
                        </div>
                      ))}
                    </div>
                  </CardContent>
                </Card>
              ))}
            </TabsContent>

            {/* Switch Ports */}
            <TabsContent value="ports" className="space-y-4">
              {isAdmin && <Button size="sm" onClick={() => { setEditItem(null); setPortDialog(true); }} className="gap-2"><Plus className="h-4 w-4" />Add Port</Button>}
              {devices.filter(d => d.type === "switch" || d.type === "router" || switchPorts.some(sp => sp.device_id === d.id)).map(dev => {
                const ports = switchPorts.filter(sp => sp.device_id === dev.id).sort((a, b) => a.port_number - b.port_number);
                if (ports.length === 0) return null;
                const usedPorts = ports.filter(p => p.status === "active" || !!p.connected_device).length;
                const freePorts = Math.max(ports.length - usedPorts, 0);
                return (
                  <Card key={dev.id}>
                    <CardHeader className="pb-2">
                      <div className="flex items-center gap-2 flex-wrap">
                        <Grid3X3 className="h-5 w-5 text-primary" />
                        <CardTitle className="text-base">{dev.name}</CardTitle>
                        <Badge variant="secondary">{ports.length} total</Badge>
                        <Badge variant="outline" className="text-emerald-400 border-emerald-500/40">{usedPorts} used</Badge>
                        <Badge variant="outline">{freePorts} free</Badge>
                      </div>
                    </CardHeader>
                    <CardContent>
                      <div className="grid grid-cols-6 sm:grid-cols-8 md:grid-cols-12 gap-2">
                        {ports.map(port => (
                          <div key={port.id} className="relative group">
                            <div className={`flex flex-col items-center p-2 rounded-lg border border-border cursor-pointer hover:border-primary transition-colors ${statusColor(port.status)}/10`}
                              onClick={() => { if (isAdmin) { setEditItem(port); setPortDialog(true); } }}>
                              <CircleDot className={`h-5 w-5 ${port.status === "active" ? "text-emerald-400" : port.status === "error" ? "text-red-400" : "text-muted-foreground"}`} />
                              <span className="text-xs font-mono mt-1 text-foreground">{port.port_label || port.port_number}</span>
                              <span className="text-[10px] text-muted-foreground">{port.speed}</span>
                            </div>
                            <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-10 bg-popover border border-border rounded-lg p-2 shadow-lg min-w-[160px] text-xs">
                              <div className="font-medium text-foreground">Port {port.port_number}</div>
                              {port.port_label && <div className="text-muted-foreground">Label: {port.port_label}</div>}
                              <div className="text-muted-foreground">Status: <span className={port.status === "active" ? "text-emerald-400" : "text-muted-foreground"}>{port.status}</span></div>
                              <div className="text-muted-foreground">Speed: {port.speed}</div>
                              {port.vlan && <div className="text-muted-foreground">VLAN: {port.vlan}</div>}
                              {port.connected_device && <div className="text-muted-foreground">→ {port.connected_device}</div>}
                              <div className="text-muted-foreground">Cable mark: P{port.port_number}-{(port.port_label || `PORT${port.port_number}`).toUpperCase()}</div>
                              {port.notes && <div className="text-muted-foreground mt-1 italic">{port.notes}</div>}
                              {isAdmin && <div className="mt-1 text-primary cursor-pointer" onClick={() => deletePort(port.id)}>Delete</div>}
                            </div>
                          </div>
                        ))}
                      </div>
                    </CardContent>
                  </Card>
                );
              })}
              {switchPorts.length === 0 && <Card><CardContent className="py-8 text-center text-muted-foreground">No switch ports defined yet.</CardContent></Card>}
            </TabsContent>

            {/* Cable Runs */}
            <TabsContent value="cables" className="space-y-4">
              {isAdmin && <Button size="sm" onClick={() => { setEditItem(null); setCableDialog(true); }} className="gap-2"><Plus className="h-4 w-4" />Add Cable Run</Button>}
              <div className="space-y-2">
                {cables.map(cable => (
                  <div key={cable.id} className="flex items-center gap-3 p-3 rounded-lg bg-card border border-border">
                    <div className="w-4 h-4 rounded-full border-2 border-border" style={{ backgroundColor: cableColorHex(cable.cable_color) }} />
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="font-medium text-foreground">{cable.label || `Cable #${cable.id.slice(0, 6)}`}</span>
                        <Badge variant="outline">{cable.cable_type.toUpperCase()}</Badge>
                        {cable.cable_length && <Badge variant="secondary">{cable.cable_length}</Badge>}
                        <Badge variant="outline" className="font-mono">MARK {`${cable.source_type[0]?.toUpperCase() ?? "S"}${cable.source_port}-${cable.dest_type[0]?.toUpperCase() ?? "D"}${cable.dest_port}`}</Badge>
                      </div>
                      <div className="text-xs text-muted-foreground mt-1">{cable.source_type} port {cable.source_port} → {cable.dest_type} port {cable.dest_port}</div>
                    </div>
                    {isAdmin && (
                      <div className="flex gap-1">
                        <Button variant="ghost" size="icon" onClick={() => { setEditItem(cable); setCableDialog(true); }}><Edit className="h-4 w-4" /></Button>
                        <Button variant="ghost" size="icon" onClick={() => deleteCable(cable.id)}><Trash2 className="h-4 w-4 text-destructive" /></Button>
                      </div>
                    )}
                  </div>
                ))}
                {cables.length === 0 && <Card><CardContent className="py-8 text-center text-muted-foreground">No cable runs defined.</CardContent></Card>}
              </div>
            </TabsContent>
          </Tabs>
        )}
      </div>

      {/* ─── All Dialogs ─── */}
      <FloorPlanDialog open={planDialog} onOpenChange={setPlanDialog} editItem={editItem} onSave={savePlan} />
      <RackDialog open={rackDialog} onOpenChange={setRackDialog} editItem={editItem} onSave={saveRack} />
      <PanelDialog open={panelDialog} onOpenChange={setPanelDialog} editItem={editItem} onSave={savePanel} racks={racks} />
      <PortDialog open={portDialog} onOpenChange={setPortDialog} editItem={editItem} onSave={saveSwitchPort} devices={devices} />
      <CableDialog open={cableDialog} onOpenChange={setCableDialog} editItem={editItem} onSave={saveCable} racks={racks} devices={devices} />
      {selectedPlan && <FloorPlanUploader open={uploadDialog} onOpenChange={setUploadDialog} floorPlanId={selectedPlan.id} onUploaded={handleImageUploaded} />}
      <DevicePickerDialog open={devicePickerDialog} onOpenChange={setDevicePickerDialog} devices={devices} onSelect={placeDevice} />
      <LabelDialog open={labelDialog} onOpenChange={setLabelDialog} onSave={saveLabel} />
    </AppLayout>
  );
}

/* ═══════════════════════════════════════════════════
   DIALOGS
   ═══════════════════════════════════════════════════ */

function FloorPlanDialog({ open, onOpenChange, editItem, onSave }: { open: boolean; onOpenChange: (o: boolean) => void; editItem: any; onSave: (n: string, img: string) => void }) {
  const [name, setName] = useState(""); const [img, setImg] = useState("");
  useEffect(() => { if (open) { setName(editItem?.name || ""); setImg(editItem?.image_url || ""); } }, [open, editItem]);
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent><DialogHeader><DialogTitle>{editItem?.id ? "Edit" : "New"} Floor Plan</DialogTitle></DialogHeader>
        <div className="space-y-3">
          <div><Label>Name</Label><Input value={name} onChange={e => setName(e.target.value)} /></div>
          <div><Label>Background Image URL (or use Upload in Canvas)</Label><Input value={img} onChange={e => setImg(e.target.value)} placeholder="https://..." /></div>
          {img && <img src={img} alt="preview" className="max-h-32 rounded border border-border" />}
        </div>
        <DialogFooter><Button onClick={() => onSave(name, img)} disabled={!name}>Save</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function RackDialog({ open, onOpenChange, editItem, onSave }: { open: boolean; onOpenChange: (o: boolean) => void; editItem: any; onSave: (n: string, u: number) => void }) {
  const [name, setName] = useState(""); const [units, setUnits] = useState(42);
  useEffect(() => { if (open) { setName(editItem?.name || ""); setUnits(editItem?.rack_units || 42); } }, [open, editItem]);
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent><DialogHeader><DialogTitle>{editItem?.id ? "Edit" : "Add"} Rack</DialogTitle></DialogHeader>
        <div className="space-y-3">
          <div><Label>Name</Label><Input value={name} onChange={e => setName(e.target.value)} /></div>
          <div><Label>Rack Units</Label><Input type="number" value={units} onChange={e => setUnits(+e.target.value)} /></div>
        </div>
        <DialogFooter><Button onClick={() => onSave(name, units)} disabled={!name}>Save</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function PanelDialog({ open, onOpenChange, editItem, onSave, racks }: { open: boolean; onOpenChange: (o: boolean) => void; editItem: any; onSave: (rId: string, n: string, pc: number, rp: number, pt: string) => void; racks: RackLocation[] }) {
  const [rackId, setRackId] = useState(""); const [name, setName] = useState(""); const [portCount, setPortCount] = useState(24); const [pos, setPos] = useState(1); const [type, setType] = useState("rj45");
  useEffect(() => { if (open) { setRackId(editItem?.rack_id || racks[0]?.id || ""); setName(editItem?.name || ""); setPortCount(editItem?.port_count || 24); setPos(editItem?.rack_position || 1); setType(editItem?.panel_type || "rj45"); } }, [open, editItem, racks]);
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent><DialogHeader><DialogTitle>{editItem?.id ? "Edit" : "Add"} Patch Panel</DialogTitle></DialogHeader>
        <div className="space-y-3">
          <div><Label>Rack</Label>
            <Select value={rackId} onValueChange={setRackId}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{racks.map(r => <SelectItem key={r.id} value={r.id}>{r.name}</SelectItem>)}</SelectContent></Select>
          </div>
          <div><Label>Name</Label><Input value={name} onChange={e => setName(e.target.value)} /></div>
          <div className="grid grid-cols-3 gap-2">
            <div><Label>Ports</Label><Input type="number" value={portCount} onChange={e => setPortCount(+e.target.value)} /></div>
            <div><Label>Rack U</Label><Input type="number" value={pos} onChange={e => setPos(+e.target.value)} /></div>
            <div><Label>Type</Label>
              <Select value={type} onValueChange={setType}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="rj45">RJ45</SelectItem><SelectItem value="fiber-lc">Fiber LC</SelectItem><SelectItem value="fiber-sc">Fiber SC</SelectItem><SelectItem value="coax">Coax</SelectItem></SelectContent></Select>
            </div>
          </div>
        </div>
        <DialogFooter><Button onClick={() => onSave(rackId, name, portCount, pos, type)} disabled={!name || !rackId}>Save</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function PortDialog({ open, onOpenChange, editItem, onSave, devices }: { open: boolean; onOpenChange: (o: boolean) => void; editItem: any; onSave: (dId: string, pn: number, pl: string, s: string, sp: string, v: string, cd: string, n: string) => void; devices: Device[] }) {
  const [deviceId, setDeviceId] = useState(""); const [portNum, setPortNum] = useState(1); const [label, setLabel] = useState(""); const [status, setStatus] = useState("inactive"); const [speed, setSpeed] = useState("1G"); const [vlan, setVlan] = useState(""); const [conn, setConn] = useState(""); const [notes, setNotes] = useState("");
  useEffect(() => { if (open) { setDeviceId(editItem?.device_id || devices[0]?.id || ""); setPortNum(editItem?.port_number || 1); setLabel(editItem?.port_label || ""); setStatus(editItem?.status || "inactive"); setSpeed(editItem?.speed || "1G"); setVlan(editItem?.vlan || ""); setConn(editItem?.connected_device || ""); setNotes(editItem?.notes || ""); } }, [open, editItem, devices]);
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent><DialogHeader><DialogTitle>{editItem?.id ? "Edit" : "Add"} Switch Port</DialogTitle></DialogHeader>
        <div className="space-y-3">
          {!editItem?.id && <div><Label>Device</Label><Select value={deviceId} onValueChange={setDeviceId}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{devices.map(d => <SelectItem key={d.id} value={d.id}>{d.name}</SelectItem>)}</SelectContent></Select></div>}
          <div className="grid grid-cols-2 gap-2">
            <div><Label>Port #</Label><Input type="number" value={portNum} onChange={e => setPortNum(+e.target.value)} /></div>
            <div><Label>Label</Label><Input value={label} onChange={e => setLabel(e.target.value)} placeholder="Gi0/1" /></div>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <div><Label>Status</Label><Select value={status} onValueChange={setStatus}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{PORT_STATUSES.map(s => <SelectItem key={s} value={s}>{s}</SelectItem>)}</SelectContent></Select></div>
            <div><Label>Speed</Label><Select value={speed} onValueChange={setSpeed}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{["100M","1G","2.5G","5G","10G","25G","40G","100G"].map(s => <SelectItem key={s} value={s}>{s}</SelectItem>)}</SelectContent></Select></div>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <div><Label>VLAN</Label><Input value={vlan} onChange={e => setVlan(e.target.value)} placeholder="100" /></div>
            <div><Label>Connected To</Label><Input value={conn} onChange={e => setConn(e.target.value)} placeholder="PC-101" /></div>
          </div>
          <div><Label>Notes</Label><Input value={notes} onChange={e => setNotes(e.target.value)} /></div>
        </div>
        <DialogFooter><Button onClick={() => onSave(deviceId, portNum, label, status, speed, vlan, conn, notes)} disabled={!deviceId}>Save</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function DevicePickerDialog({ open, onOpenChange, devices, onSelect }: { open: boolean; onOpenChange: (o: boolean) => void; devices: Device[]; onSelect: (id: string) => void }) {
  const [search, setSearch] = useState("");
  const filtered = devices.filter(d => d.name.toLowerCase().includes(search.toLowerCase()));
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent><DialogHeader><DialogTitle>Place Device on Floor Plan</DialogTitle></DialogHeader>
        <div className="space-y-3">
          <Input placeholder="Search devices..." value={search} onChange={e => setSearch(e.target.value)} />
          <div className="max-h-60 overflow-y-auto space-y-1">
            {filtered.map(d => (
              <Button key={d.id} variant="ghost" className="w-full justify-start gap-2" onClick={() => onSelect(d.id)}>
                <span className="text-sm">{d.name}</span>
                <Badge variant="outline" className="text-xs">{d.type || "unknown"}</Badge>
              </Button>
            ))}
            {filtered.length === 0 && <p className="text-sm text-muted-foreground text-center py-4">No devices found</p>}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}

function LabelDialog({ open, onOpenChange, onSave }: { open: boolean; onOpenChange: (o: boolean) => void; onSave: (text: string, fontSize: number, color: string, type: string) => void }) {
  const [text, setText] = useState(""); const [fontSize, setFontSize] = useState(14); const [color, setColor] = useState("#ffffff"); const [type, setType] = useState("label");
  useEffect(() => { if (open) { setText(""); setFontSize(14); setColor("#ffffff"); setType("label"); } }, [open]);
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent><DialogHeader><DialogTitle>Add Annotation</DialogTitle></DialogHeader>
        <div className="space-y-3">
          <div><Label>Text</Label><Input value={text} onChange={e => setText(e.target.value)} /></div>
          <div className="grid grid-cols-3 gap-2">
            <div><Label>Size</Label><Input type="number" value={fontSize} onChange={e => setFontSize(+e.target.value)} /></div>
            <div><Label>Color</Label><Input type="color" value={color} onChange={e => setColor(e.target.value)} className="h-10" /></div>
            <div><Label>Type</Label>
              <Select value={type} onValueChange={setType}><SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent><SelectItem value="label">Label</SelectItem><SelectItem value="zone">Zone</SelectItem></SelectContent>
              </Select>
            </div>
          </div>
        </div>
        <DialogFooter><Button onClick={() => onSave(text, fontSize, color, type)} disabled={!text}>Add</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function CableDialog({ open, onOpenChange, editItem, onSave, racks, devices }: { open: boolean; onOpenChange: (o: boolean) => void; editItem: any; onSave: (ct: string, cc: string, cl: string, lb: string, st: string, si: string, sp: number, dt: string, di: string, dp: number, n: string) => void; racks: RackLocation[]; devices: Device[] }) {
  const [cType, setCType] = useState("cat6"); const [cColor, setCColor] = useState("blue"); const [cLen, setCLen] = useState(""); const [label, setLabel] = useState("");
  const [srcType, setSrcType] = useState("patch_panel"); const [srcId, setSrcId] = useState(""); const [srcPort, setSrcPort] = useState(1);
  const [dstType, setDstType] = useState("switch"); const [dstId, setDstId] = useState(""); const [dstPort, setDstPort] = useState(1);
  const [notes, setNotes] = useState("");
  useEffect(() => {
    if (open) {
      setCType(editItem?.cable_type || "cat6"); setCColor(editItem?.cable_color || "blue"); setCLen(editItem?.cable_length || ""); setLabel(editItem?.label || "");
      setSrcType(editItem?.source_type || "patch_panel"); setSrcId(editItem?.source_id || ""); setSrcPort(editItem?.source_port || 1);
      setDstType(editItem?.dest_type || "switch"); setDstId(editItem?.dest_id || ""); setDstPort(editItem?.dest_port || 1);
      setNotes(editItem?.notes || "");
    }
  }, [open, editItem]);
  const endpointOptions = (type: string) => type === "patch_panel" ? racks.map(r => ({ id: r.id, name: r.name })) : devices.map(d => ({ id: d.id, name: d.name }));
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg"><DialogHeader><DialogTitle>{editItem?.id ? "Edit" : "Add"} Cable Run</DialogTitle></DialogHeader>
        <div className="space-y-3">
          <div className="grid grid-cols-3 gap-2">
            <div><Label>Type</Label><Select value={cType} onValueChange={setCType}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{CABLE_TYPES.map(t => <SelectItem key={t} value={t}>{t.toUpperCase()}</SelectItem>)}</SelectContent></Select></div>
            <div><Label>Color</Label><Select value={cColor} onValueChange={setCColor}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{CABLE_COLORS.map(c => <SelectItem key={c} value={c}><span className="flex items-center gap-2"><span className="w-3 h-3 rounded-full" style={{ backgroundColor: cableColorHex(c) }} />{c}</span></SelectItem>)}</SelectContent></Select></div>
            <div><Label>Length</Label><Input value={cLen} onChange={e => setCLen(e.target.value)} placeholder="3m" /></div>
          </div>
          <div><Label>Label</Label><Input value={label} onChange={e => setLabel(e.target.value)} placeholder="Desk 12 → SW1-P3" /></div>
          <div className="border border-border rounded-lg p-3 space-y-2">
            <span className="text-xs font-medium text-muted-foreground">SOURCE</span>
            <div className="grid grid-cols-3 gap-2">
              <Select value={srcType} onValueChange={setSrcType}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="patch_panel">Panel/Rack</SelectItem><SelectItem value="switch">Device</SelectItem></SelectContent></Select>
              <Select value={srcId} onValueChange={setSrcId}><SelectTrigger><SelectValue placeholder="Select" /></SelectTrigger><SelectContent>{endpointOptions(srcType).map(o => <SelectItem key={o.id} value={o.id}>{o.name}</SelectItem>)}</SelectContent></Select>
              <Input type="number" value={srcPort} onChange={e => setSrcPort(+e.target.value)} placeholder="Port" />
            </div>
          </div>
          <div className="border border-border rounded-lg p-3 space-y-2">
            <span className="text-xs font-medium text-muted-foreground">DESTINATION</span>
            <div className="grid grid-cols-3 gap-2">
              <Select value={dstType} onValueChange={setDstType}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="patch_panel">Panel/Rack</SelectItem><SelectItem value="switch">Device</SelectItem></SelectContent></Select>
              <Select value={dstId} onValueChange={setDstId}><SelectTrigger><SelectValue placeholder="Select" /></SelectTrigger><SelectContent>{endpointOptions(dstType).map(o => <SelectItem key={o.id} value={o.id}>{o.name}</SelectItem>)}</SelectContent></Select>
              <Input type="number" value={dstPort} onChange={e => setDstPort(+e.target.value)} placeholder="Port" />
            </div>
          </div>
          <div><Label>Notes</Label><Input value={notes} onChange={e => setNotes(e.target.value)} /></div>
        </div>
        <DialogFooter><Button onClick={() => onSave(cType, cColor, cLen, label, srcType, srcId, srcPort, dstType, dstId, dstPort, notes)} disabled={!srcId || !dstId}>Save</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
