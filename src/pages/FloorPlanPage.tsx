import { useState, useEffect, useCallback } from "react";
import { AppLayout } from "@/components/layout/AppLayout";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { toast } from "sonner";
import { Plus, Trash2, Edit, MapPin, Building2, Server, Cable as CableIcon } from "lucide-react";
import { FloorPlanCanvas } from "@/components/floor-plan/FloorPlanCanvas";
import { CanvasToolbar, type ToolMode } from "@/components/floor-plan/CanvasToolbar";
import { PropertiesPanel, type SelectedItem } from "@/components/floor-plan/PropertiesPanel";
import { FloorPlanUploader } from "@/components/floor-plan/FloorPlanUploader";

/* ─── types ─── */
interface FloorPlan { id: string; name: string; image_url: string | null; width: number; height: number; }
interface RackLocation { id: string; floor_plan_id: string; name: string; x: number; y: number; rack_units: number; rotation: number; label_visible: boolean; }
interface CableRun { id: string; floor_plan_id: string | null; cable_type: string; cable_color: string; cable_length: string | null; label: string | null; source_type: string; source_id: string; source_port: number; dest_type: string; dest_id: string; dest_port: number; notes: string | null; }
interface Device { id: string; name: string; type: string | null; x: number | null; y: number | null; }
interface Annotation { id: string; floor_plan_id: string; x: number; y: number; text: string; font_size: number; color: string; type: string; width: number | null; height: number | null; }

const CABLE_TYPES = ["cat5", "cat5e", "cat6", "cat6a", "cat7", "fiber-sm", "fiber-mm", "coax", "dac"];
const CABLE_COLORS = ["blue", "red", "green", "yellow", "orange", "white", "gray", "purple", "black"];
const cableColorHex = (c: string) => {
  const m: Record<string, string> = { blue: "#3b82f6", red: "#ef4444", green: "#22c55e", yellow: "#eab308", orange: "#f97316", white: "#e2e8f0", gray: "#64748b", purple: "#a855f7", black: "#1e293b" };
  return m[c] || "#64748b";
};

export default function FloorPlanPage() {
  const { isAdmin } = useAuth();
  const [floorPlans, setFloorPlans] = useState<FloorPlan[]>([]);
  const [selectedPlan, setSelectedPlan] = useState<FloorPlan | null>(null);
  const [racks, setRacks] = useState<RackLocation[]>([]);
  const [cables, setCables] = useState<CableRun[]>([]);
  const [devices, setDevices] = useState<Device[]>([]);
  const [annotations, setAnnotations] = useState<Annotation[]>([]);
  const [loading, setLoading] = useState(true);

  // Canvas state
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
  const [cableDialog, setCableDialog] = useState(false);
  const [editItem, setEditItem] = useState<any>(null);
  const [devicePickerDialog, setDevicePickerDialog] = useState(false);
  const [pendingDevicePos, setPendingDevicePos] = useState<{ x: number; y: number } | null>(null);
  const [labelDialog, setLabelDialog] = useState(false);
  const [pendingLabelPos, setPendingLabelPos] = useState<{ x: number; y: number } | null>(null);

  // Cable drawing
  const [cableSource, setCableSource] = useState<{ kind: string; id: string } | null>(null);

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
      setRacks((r.data ?? []).map((x: any) => ({ ...x, rotation: x.rotation ?? 0, label_visible: x.label_visible ?? true })));
      setCables((c.data ?? []) as CableRun[]);
      setAnnotations((a.data ?? []) as Annotation[]);
    })();
  }, [selectedPlan]);

  /* ─── CRUD ─── */
  const savePlan = async (name: string, imageUrl: string) => {
    if (editItem) {
      await supabase.from("floor_plans").update({ name, image_url: imageUrl || null }).eq("id", editItem.id);
    } else {
      const { data: { user } } = await supabase.auth.getUser();
      await supabase.from("floor_plans").insert({ name, image_url: imageUrl || null, user_id: user!.id });
    }
    setPlanDialog(false); setEditItem(null); load();
    toast.success(editItem ? "Floor plan updated" : "Floor plan created");
  };

  const deletePlan = async (id: string) => {
    if (!confirm("Delete this floor plan and all its data?")) return;
    await supabase.from("floor_plans").delete().eq("id", id);
    if (selectedPlan?.id === id) setSelectedPlan(null);
    load(); toast.success("Floor plan deleted");
  };

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

  const placeDevice = async (deviceId: string) => {
    if (!pendingDevicePos) return;
    // We'll store device position as x/y on the device record itself
    await supabase.from("devices").update({ x: pendingDevicePos.x, y: pendingDevicePos.y }).eq("id", deviceId);
    setDevicePickerDialog(false); setPendingDevicePos(null);
    const dev = await supabase.from("devices").select("id, name, type, x, y");
    setDevices((dev.data ?? []) as Device[]);
    toast.success("Device placed on floor plan");
  };

  const saveLabel = async (text: string, fontSize: number, color: string, type: string) => {
    if (!selectedPlan || !pendingLabelPos) return;
    await supabase.from("floor_plan_annotations").insert({
      floor_plan_id: selectedPlan.id,
      x: pendingLabelPos.x, y: pendingLabelPos.y,
      text, font_size: fontSize, color, type,
      width: type === "zone" ? 200 : null,
      height: type === "zone" ? 150 : null,
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

  const handleMoveItem = async (kind: string, id: string, x: number, y: number) => {
    if (kind === "rack") {
      setRacks(prev => prev.map(r => r.id === id ? { ...r, x, y } : r));
    } else if (kind === "device") {
      setDevices(prev => prev.map(d => d.id === id ? { ...d, x, y } : d));
    } else if (kind === "annotation") {
      setAnnotations(prev => prev.map(a => a.id === id ? { ...a, x, y } : a));
    }
  };

  // Persist position on mouse up (debounced via selectedItem updates)
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
    // Also update local state
    if (item.kind === "rack") {
      setRacks(prev => prev.map(r => r.id === item.id ? { ...r, name: item.name, rack_units: item.rack_units, x: item.x, y: item.y, rotation: item.rotation, label_visible: item.label_visible } : r));
    } else if (item.kind === "device") {
      setDevices(prev => prev.map(d => d.id === item.id ? { ...d, x: item.x, y: item.y } : d));
    } else if (item.kind === "annotation") {
      setAnnotations(prev => prev.map(a => a.id === item.id ? { ...a, text: item.text, font_size: item.font_size, color: item.color } : a));
    }
  };

  const handleDeleteSelected = async () => {
    if (!selectedItem) return;
    if (!confirm("Delete this item?")) return;
    if (selectedItem.kind === "rack") {
      await supabase.from("rack_locations").delete().eq("id", selectedItem.id);
      setRacks(prev => prev.filter(r => r.id !== selectedItem.id));
    } else if (selectedItem.kind === "cable") {
      await supabase.from("cable_runs").delete().eq("id", selectedItem.id);
      setCables(prev => prev.filter(c => c.id !== selectedItem.id));
    } else if (selectedItem.kind === "annotation") {
      await supabase.from("floor_plan_annotations").delete().eq("id", selectedItem.id);
      setAnnotations(prev => prev.filter(a => a.id !== selectedItem.id));
    }
    setSelectedItem(null);
    toast.success("Deleted");
  };

  const handleImageUploaded = (url: string) => {
    if (selectedPlan) {
      setSelectedPlan({ ...selectedPlan, image_url: url });
      setFloorPlans(prev => prev.map(fp => fp.id === selectedPlan.id ? { ...fp, image_url: url } : fp));
    }
  };

  // Devices on this floor plan (those with x/y set)
  const placedDevices = devices.filter(d => d.x != null && d.y != null).map(d => ({
    id: d.id, name: d.name, type: d.type, x: d.x!, y: d.y!,
  }));

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
          {isAdmin && (
            <Button onClick={() => { setEditItem(null); setPlanDialog(true); }} className="gap-2">
              <Plus className="h-4 w-4" /> New Floor Plan
            </Button>
          )}
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
            {/* Stats badges */}
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

        {/* Canvas layout */}
        {selectedPlan && (
          <div className="flex gap-3" style={{ height: "calc(100vh - 220px)" }}>
            {/* Toolbar */}
            <CanvasToolbar
              activeTool={activeTool}
              onToolChange={setActiveTool}
              onZoomIn={() => setZoom(z => Math.min(5, z * 1.2))}
              onZoomOut={() => setZoom(z => Math.max(0.2, z / 1.2))}
              onZoomReset={() => { setZoom(1); setPanX(0); setPanY(0); }}
              onUpload={() => setUploadDialog(true)}
              snapToGrid={snapToGrid}
              onToggleSnap={() => setSnapToGrid(s => !s)}
              isAdmin={isAdmin}
              onDeleteSelected={handleDeleteSelected}
              hasSelection={!!selectedItem}
            />

            {/* Canvas */}
            <div className="flex-1 min-w-0">
              <FloorPlanCanvas
                backgroundUrl={selectedPlan.image_url}
                width={selectedPlan.width || 1200}
                height={selectedPlan.height || 800}
                racks={racks}
                devices={placedDevices}
                cables={cables}
                annotations={annotations}
                activeTool={activeTool}
                snapToGrid={snapToGrid}
                selectedItem={selectedItem}
                onSelectItem={setSelectedItem}
                onMoveItem={handleMoveItem}
                onCanvasClick={handleCanvasClick}
                onCableEndpointClick={handleCableEndpointClick}
                zoom={zoom}
                panX={panX}
                panY={panY}
                onPanChange={(x, y) => { setPanX(x); setPanY(y); }}
                onZoomChange={setZoom}
              />
            </div>

            {/* Properties panel */}
            {selectedItem && (
              <PropertiesPanel
                item={selectedItem}
                onUpdate={handlePropertyUpdate}
                onClose={() => setSelectedItem(null)}
                isAdmin={isAdmin}
              />
            )}
          </div>
        )}
      </div>

      {/* Dialogs */}
      <FloorPlanDialog open={planDialog} onOpenChange={setPlanDialog} editItem={editItem} onSave={savePlan} />
      <RackDialog open={rackDialog} onOpenChange={setRackDialog} editItem={editItem} onSave={saveRack} />
      <CableDialog open={cableDialog} onOpenChange={setCableDialog} editItem={editItem} onSave={saveCable} racks={racks} devices={devices} />
      {selectedPlan && <FloorPlanUploader open={uploadDialog} onOpenChange={setUploadDialog} floorPlanId={selectedPlan.id} onUploaded={handleImageUploaded} />}
      <DevicePickerDialog open={devicePickerDialog} onOpenChange={setDevicePickerDialog} devices={devices} onSelect={placeDevice} />
      <LabelDialog open={labelDialog} onOpenChange={setLabelDialog} onSave={saveLabel} />
    </AppLayout>
  );
}

/* ─── Dialogs ─── */
function FloorPlanDialog({ open, onOpenChange, editItem, onSave }: { open: boolean; onOpenChange: (o: boolean) => void; editItem: any; onSave: (n: string, img: string) => void }) {
  const [name, setName] = useState(""); const [img, setImg] = useState("");
  useEffect(() => { if (open) { setName(editItem?.name || ""); setImg(editItem?.image_url || ""); } }, [open, editItem]);
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent><DialogHeader><DialogTitle>{editItem?.id ? "Edit" : "New"} Floor Plan</DialogTitle></DialogHeader>
        <div className="space-y-3">
          <div><Label>Name</Label><Input value={name} onChange={e => setName(e.target.value)} /></div>
          <div><Label>Background Image URL (or use Upload)</Label><Input value={img} onChange={e => setImg(e.target.value)} placeholder="https://..." /></div>
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
