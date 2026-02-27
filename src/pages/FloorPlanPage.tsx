import { useState, useEffect, useCallback } from "react";
import { AppLayout } from "@/components/layout/AppLayout";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/hooks/useAuth";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { toast } from "sonner";
import {
  Plus, Trash2, Edit, MapPin, Server, Cable, Grid3X3,
  CircleDot, Plug, LayoutGrid, Building2
} from "lucide-react";

/* ─── types ─── */
interface FloorPlan { id: string; name: string; image_url: string | null; width: number; height: number; }
interface RackLocation { id: string; floor_plan_id: string; name: string; x: number; y: number; rack_units: number; }
interface PatchPanel { id: string; rack_id: string; name: string; port_count: number; rack_position: number; panel_type: string; }
interface SwitchPort { id: string; device_id: string; port_number: number; port_label: string | null; status: string; speed: string; vlan: string | null; connected_device: string | null; notes: string | null; }
interface CableRun { id: string; floor_plan_id: string | null; cable_type: string; cable_color: string; cable_length: string | null; label: string | null; source_type: string; source_id: string; source_port: number; dest_type: string; dest_id: string; dest_port: number; notes: string | null; }
interface Device { id: string; name: string; type: string | null; }

const CABLE_TYPES = ["cat5", "cat5e", "cat6", "cat6a", "cat7", "fiber-sm", "fiber-mm", "coax", "dac"];
const CABLE_COLORS = ["blue", "red", "green", "yellow", "orange", "white", "gray", "purple", "black"];
const PORT_STATUSES = ["active", "inactive", "error", "reserved"];

const cableColorHex = (c: string) => {
  const m: Record<string,string> = { blue:"#3b82f6", red:"#ef4444", green:"#22c55e", yellow:"#eab308", orange:"#f97316", white:"#e2e8f0", gray:"#64748b", purple:"#a855f7", black:"#1e293b" };
  return m[c] || "#64748b";
};

/* ─── Main Page ─── */
export default function FloorPlanPage() {
  const { isAdmin } = useAuth();
  const [floorPlans, setFloorPlans] = useState<FloorPlan[]>([]);
  const [selectedPlan, setSelectedPlan] = useState<FloorPlan | null>(null);
  const [racks, setRacks] = useState<RackLocation[]>([]);
  const [panels, setPanels] = useState<PatchPanel[]>([]);
  const [switchPorts, setSwitchPorts] = useState<SwitchPort[]>([]);
  const [cables, setCables] = useState<CableRun[]>([]);
  const [devices, setDevices] = useState<Device[]>([]);
  const [loading, setLoading] = useState(true);

  // dialogs
  const [planDialog, setPlanDialog] = useState(false);
  const [rackDialog, setRackDialog] = useState(false);
  const [panelDialog, setPanelDialog] = useState(false);
  const [portDialog, setPortDialog] = useState(false);
  const [cableDialog, setCableDialog] = useState(false);
  const [editItem, setEditItem] = useState<any>(null);

  const load = useCallback(async () => {
    setLoading(true);
    const [fp, dev] = await Promise.all([
      supabase.from("floor_plans").select("*").order("created_at"),
      supabase.from("devices").select("id, name, type"),
    ]);
    setFloorPlans((fp.data ?? []) as FloorPlan[]);
    setDevices((dev.data ?? []) as Device[]);
    if (fp.data && fp.data.length > 0 && !selectedPlan) setSelectedPlan(fp.data[0] as FloorPlan);
    setLoading(false);
  }, []);

  useEffect(() => { load(); }, [load]);

  // load sub-data when plan changes
  useEffect(() => {
    if (!selectedPlan) return;
    (async () => {
      const [r, c] = await Promise.all([
        supabase.from("rack_locations").select("*").eq("floor_plan_id", selectedPlan.id),
        supabase.from("cable_runs").select("*").eq("floor_plan_id", selectedPlan.id),
      ]);
      setRacks((r.data ?? []) as RackLocation[]);
      setCables((c.data ?? []) as CableRun[]);
      // load panels for these racks
      const rackIds = (r.data ?? []).map((x: any) => x.id);
      if (rackIds.length) {
        const p = await supabase.from("patch_panels").select("*").in("rack_id", rackIds);
        setPanels((p.data ?? []) as PatchPanel[]);
      } else { setPanels([]); }
    })();
  }, [selectedPlan]);

  // load switch ports for all devices
  useEffect(() => {
    if (!devices.length) return;
    supabase.from("switch_ports").select("*").then(({ data }) => setSwitchPorts((data ?? []) as SwitchPort[]));
  }, [devices]);

  /* ─── CRUD helpers ─── */
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
    if (!confirm("Delete this floor plan and all its racks/cables?")) return;
    await supabase.from("floor_plans").delete().eq("id", id);
    if (selectedPlan?.id === id) setSelectedPlan(null);
    load(); toast.success("Floor plan deleted");
  };

  const saveRack = async (name: string, rackUnits: number) => {
    if (!selectedPlan) return;
    if (editItem) {
      await supabase.from("rack_locations").update({ name, rack_units: rackUnits }).eq("id", editItem.id);
    } else {
      await supabase.from("rack_locations").insert({ floor_plan_id: selectedPlan.id, name, rack_units: rackUnits });
    }
    setRackDialog(false); setEditItem(null);
    const r = await supabase.from("rack_locations").select("*").eq("floor_plan_id", selectedPlan.id);
    setRacks((r.data ?? []) as RackLocation[]);
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
    if (editItem) {
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
    if (editItem) {
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
    if (editItem) {
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

  const statusColor = (s: string) => {
    switch (s) { case "active": return "bg-emerald-500"; case "error": return "bg-red-500"; case "reserved": return "bg-amber-500"; default: return "bg-muted"; }
  };

  const cableColorHexLocal = (c: string) => cableColorHex(c);

  if (loading) return <AppLayout><div className="flex items-center justify-center h-64 text-muted-foreground">Loading…</div></AppLayout>;

  return (
    <AppLayout>
      <div className="space-y-6">
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
          <div className="flex gap-2 flex-wrap">
            {floorPlans.map(fp => (
              <Button key={fp.id} variant={selectedPlan?.id === fp.id ? "default" : "outline"} size="sm" onClick={() => setSelectedPlan(fp)} className="gap-2">
                <MapPin className="h-3 w-3" /> {fp.name}
                {isAdmin && (
                  <>
                    <Edit className="h-3 w-3 ml-1 opacity-60 hover:opacity-100" onClick={(e) => { e.stopPropagation(); setEditItem(fp); setPlanDialog(true); }} />
                    <Trash2 className="h-3 w-3 opacity-60 hover:opacity-100 text-destructive" onClick={(e) => { e.stopPropagation(); deletePlan(fp.id); }} />
                  </>
                )}
              </Button>
            ))}
          </div>
        )}

        {!selectedPlan && floorPlans.length === 0 && (
          <Card><CardContent className="py-12 text-center text-muted-foreground">No floor plans yet. Create one to get started.</CardContent></Card>
        )}

        {selectedPlan && (
          <Tabs defaultValue="overview" className="space-y-4">
            <TabsList>
              <TabsTrigger value="overview" className="gap-2"><LayoutGrid className="h-4 w-4" />Overview</TabsTrigger>
              <TabsTrigger value="racks" className="gap-2"><Server className="h-4 w-4" />Racks & Panels</TabsTrigger>
              <TabsTrigger value="ports" className="gap-2"><Grid3X3 className="h-4 w-4" />Switch Ports</TabsTrigger>
              <TabsTrigger value="cables" className="gap-2"><Cable className="h-4 w-4" />Cable Runs</TabsTrigger>
            </TabsList>

            {/* ── Overview ── */}
            <TabsContent value="overview">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card><CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Racks</CardTitle></CardHeader><CardContent><div className="text-3xl font-bold text-foreground">{racks.length}</div></CardContent></Card>
                <Card><CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Patch Panels</CardTitle></CardHeader><CardContent><div className="text-3xl font-bold text-foreground">{panels.length}</div></CardContent></Card>
                <Card><CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Cable Runs</CardTitle></CardHeader><CardContent><div className="text-3xl font-bold text-foreground">{cables.length}</div></CardContent></Card>
              </div>
              {selectedPlan.image_url && (
                <Card className="mt-4">
                  <CardContent className="p-2">
                    <img src={selectedPlan.image_url} alt={selectedPlan.name} className="w-full rounded-lg max-h-[500px] object-contain" />
                  </CardContent>
                </Card>
              )}
            </TabsContent>

            {/* ── Racks & Panels ── */}
            <TabsContent value="racks" className="space-y-4">
              {isAdmin && (
                <Button size="sm" onClick={() => { setEditItem(null); setRackDialog(true); }} className="gap-2"><Plus className="h-4 w-4" />Add Rack</Button>
              )}
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
                      {panels.filter(p => p.rack_id === rack.id).sort((a,b) => a.rack_position - b.rack_position).map(panel => (
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
                          {/* Mini port grid */}
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

            {/* ── Switch Ports ── */}
            <TabsContent value="ports" className="space-y-4">
              {isAdmin && (
                <Button size="sm" onClick={() => { setEditItem(null); setPortDialog(true); }} className="gap-2"><Plus className="h-4 w-4" />Add Port</Button>
              )}
              {devices.filter(d => d.type === "switch" || d.type === "router" || switchPorts.some(sp => sp.device_id === d.id)).map(dev => {
                const ports = switchPorts.filter(sp => sp.device_id === dev.id).sort((a, b) => a.port_number - b.port_number);
                if (ports.length === 0) return null;
                return (
                  <Card key={dev.id}>
                    <CardHeader className="pb-2">
                      <div className="flex items-center gap-2">
                        <Grid3X3 className="h-5 w-5 text-primary" />
                        <CardTitle className="text-base">{dev.name}</CardTitle>
                        <Badge variant="secondary">{ports.length} ports</Badge>
                      </div>
                    </CardHeader>
                    <CardContent>
                      {/* Port grid */}
                      <div className="grid grid-cols-6 sm:grid-cols-8 md:grid-cols-12 gap-2">
                        {ports.map(port => (
                          <div key={port.id} className="relative group">
                            <div className={`flex flex-col items-center p-2 rounded-lg border border-border cursor-pointer hover:border-primary transition-colors ${statusColor(port.status)}/10`}
                              onClick={() => { if (isAdmin) { setEditItem(port); setPortDialog(true); } }}>
                              <CircleDot className={`h-5 w-5 ${port.status === "active" ? "text-emerald-400" : port.status === "error" ? "text-red-400" : "text-muted-foreground"}`} />
                              <span className="text-xs font-mono mt-1 text-foreground">{port.port_label || port.port_number}</span>
                              <span className="text-[10px] text-muted-foreground">{port.speed}</span>
                            </div>
                            {/* Tooltip */}
                            <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-10 bg-popover border border-border rounded-lg p-2 shadow-lg min-w-[160px] text-xs">
                              <div className="font-medium text-foreground">Port {port.port_number}</div>
                              {port.port_label && <div className="text-muted-foreground">Label: {port.port_label}</div>}
                              <div className="text-muted-foreground">Status: <span className={port.status === "active" ? "text-emerald-400" : "text-muted-foreground"}>{port.status}</span></div>
                              <div className="text-muted-foreground">Speed: {port.speed}</div>
                              {port.vlan && <div className="text-muted-foreground">VLAN: {port.vlan}</div>}
                              {port.connected_device && <div className="text-muted-foreground">→ {port.connected_device}</div>}
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

            {/* ── Cable Runs ── */}
            <TabsContent value="cables" className="space-y-4">
              {isAdmin && (
                <Button size="sm" onClick={() => { setEditItem(null); setCableDialog(true); }} className="gap-2"><Plus className="h-4 w-4" />Add Cable Run</Button>
              )}
              <div className="space-y-2">
                {cables.map(cable => (
                  <div key={cable.id} className="flex items-center gap-3 p-3 rounded-lg bg-card border border-border">
                    <div className="w-4 h-4 rounded-full border-2 border-border" style={{ backgroundColor: cableColorHex(cable.cable_color) }} />
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="font-medium text-foreground">{cable.label || `Cable #${cable.id.slice(0,6)}`}</span>
                        <Badge variant="outline">{cable.cable_type.toUpperCase()}</Badge>
                        {cable.cable_length && <Badge variant="secondary">{cable.cable_length}</Badge>}
                      </div>
                      <div className="text-xs text-muted-foreground mt-1">
                        {cable.source_type} port {cable.source_port} → {cable.dest_type} port {cable.dest_port}
                      </div>
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

      {/* ── Dialogs ── */}
      <FloorPlanDialog open={planDialog} onOpenChange={setPlanDialog} editItem={editItem} onSave={savePlan} />
      <RackDialog open={rackDialog} onOpenChange={setRackDialog} editItem={editItem} onSave={saveRack} />
      <PanelDialog open={panelDialog} onOpenChange={setPanelDialog} editItem={editItem} onSave={savePanel} racks={racks} />
      <PortDialog open={portDialog} onOpenChange={setPortDialog} editItem={editItem} onSave={saveSwitchPort} devices={devices} />
      <CableDialog open={cableDialog} onOpenChange={setCableDialog} editItem={editItem} onSave={saveCable} panels={panels} devices={devices} />
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
          <div><Label>Background Image URL</Label><Input value={img} onChange={e => setImg(e.target.value)} placeholder="https://..." /></div>
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

function CableDialog({ open, onOpenChange, editItem, onSave, panels, devices }: { open: boolean; onOpenChange: (o: boolean) => void; editItem: any; onSave: (ct: string, cc: string, cl: string, lb: string, st: string, si: string, sp: number, dt: string, di: string, dp: number, n: string) => void; panels: PatchPanel[]; devices: Device[] }) {
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
  const endpointOptions = (type: string) => type === "patch_panel" ? panels.map(p => ({ id: p.id, name: p.name })) : devices.map(d => ({ id: d.id, name: d.name }));
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg"><DialogHeader><DialogTitle>{editItem?.id ? "Edit" : "Add"} Cable Run</DialogTitle></DialogHeader>
        <div className="space-y-3">
          <div className="grid grid-cols-3 gap-2">
            <div><Label>Type</Label><Select value={cType} onValueChange={setCType}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{CABLE_TYPES.map(t => <SelectItem key={t} value={t}>{t.toUpperCase()}</SelectItem>)}</SelectContent></Select></div>
            <div><Label>Color</Label><Select value={cColor} onValueChange={setCColor}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{CABLE_COLORS.map(c => <SelectItem key={c} value={c}><span className="flex items-center gap-2"><span className="w-3 h-3 rounded-full" style={{backgroundColor:cableColorHex(c)}} />{c}</span></SelectItem>)}</SelectContent></Select></div>
            <div><Label>Length</Label><Input value={cLen} onChange={e => setCLen(e.target.value)} placeholder="3m" /></div>
          </div>
          <div><Label>Label</Label><Input value={label} onChange={e => setLabel(e.target.value)} placeholder="Desk 12 → SW1-P3" /></div>
          <div className="border border-border rounded-lg p-3 space-y-2">
            <span className="text-xs font-medium text-muted-foreground">SOURCE</span>
            <div className="grid grid-cols-3 gap-2">
              <div><Select value={srcType} onValueChange={setSrcType}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="patch_panel">Panel</SelectItem><SelectItem value="switch">Switch</SelectItem></SelectContent></Select></div>
              <div><Select value={srcId} onValueChange={setSrcId}><SelectTrigger><SelectValue placeholder="Select" /></SelectTrigger><SelectContent>{endpointOptions(srcType).map(o => <SelectItem key={o.id} value={o.id}>{o.name}</SelectItem>)}</SelectContent></Select></div>
              <div><Input type="number" value={srcPort} onChange={e => setSrcPort(+e.target.value)} placeholder="Port" /></div>
            </div>
          </div>
          <div className="border border-border rounded-lg p-3 space-y-2">
            <span className="text-xs font-medium text-muted-foreground">DESTINATION</span>
            <div className="grid grid-cols-3 gap-2">
              <div><Select value={dstType} onValueChange={setDstType}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="patch_panel">Panel</SelectItem><SelectItem value="switch">Switch</SelectItem></SelectContent></Select></div>
              <div><Select value={dstId} onValueChange={setDstId}><SelectTrigger><SelectValue placeholder="Select" /></SelectTrigger><SelectContent>{endpointOptions(dstType).map(o => <SelectItem key={o.id} value={o.id}>{o.name}</SelectItem>)}</SelectContent></Select></div>
              <div><Input type="number" value={dstPort} onChange={e => setDstPort(+e.target.value)} placeholder="Port" /></div>
            </div>
          </div>
          <div><Label>Notes</Label><Input value={notes} onChange={e => setNotes(e.target.value)} /></div>
        </div>
        <DialogFooter><Button onClick={() => onSave(cType, cColor, cLen, label, srcType, srcId, srcPort, dstType, dstId, dstPort, notes)} disabled={!srcId || !dstId}>Save</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
