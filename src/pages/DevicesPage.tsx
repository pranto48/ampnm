import { useState, useEffect, useRef } from "react";
import { supabase } from "@/integrations/supabase/client";
import { AppLayout } from "@/components/layout/AppLayout";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Plus, Pencil, Trash2, Server, RefreshCw, Search, Download, Upload, Activity, Timer } from "lucide-react";
import { DeviceFormDialog } from "@/components/devices/DeviceFormDialog";
import { useToast } from "@/hooks/use-toast";
import type { Tables } from "@/integrations/supabase/types";

type Device = Tables<"devices">;

const statusVariant = (s: string | null) => {
  switch (s) {
    case "online": return "default" as const;
    case "warning": return "secondary" as const;
    case "critical": return "destructive" as const;
    case "offline": return "outline" as const;
    default: return "secondary" as const;
  }
};

const statusColor = (s: string | null) => {
  switch (s) {
    case "online": return "bg-success text-success-foreground";
    case "warning": return "bg-warning text-warning-foreground";
    case "critical": return "bg-destructive text-destructive-foreground";
    default: return "";
  }
};

export default function DevicesPage() {
  const [devices, setDevices] = useState<Device[]>([]);
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editDevice, setEditDevice] = useState<Device | null>(null);
  const [search, setSearch] = useState("");
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [pingingIds, setPingingIds] = useState<Set<string>>(new Set());
  const [isPingingAll, setIsPingingAll] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const { toast } = useToast();

  const filteredDevices = devices.filter(d => {
    if (!search.trim()) return true;
    const q = search.toLowerCase();
    return d.name.toLowerCase().includes(q) || (d.ip_address || "").toLowerCase().includes(q);
  });

  const allSelected = filteredDevices.length > 0 && filteredDevices.every(d => selected.has(d.id));

  const toggleAll = () => {
    if (allSelected) {
      setSelected(new Set());
    } else {
      setSelected(new Set(filteredDevices.map(d => d.id)));
    }
  };

  const toggleOne = (id: string) => {
    setSelected(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id); else next.add(id);
      return next;
    });
  };

  const handleBulkDelete = async () => {
    if (selected.size === 0) return;
    if (!confirm(`Delete ${selected.size} selected device(s)?`)) return;
    const ids = Array.from(selected);
    const { error } = await supabase.from("devices").delete().in("id", ids);
    if (error) {
      toast({ title: "Bulk delete failed", description: error.message, variant: "destructive" });
    } else {
      toast({ title: `Deleted ${ids.length} device(s)` });
      setSelected(new Set());
      fetchDevices();
    }
  };

  const fetchDevices = async () => {
    setLoading(true);
    const { data, error } = await supabase
      .from("devices")
      .select("*")
      .order("name");
    if (error) {
      toast({ title: "Error loading devices", description: error.message, variant: "destructive" });
    } else {
      setDevices(data ?? []);
    }
    setLoading(false);
  };

  useEffect(() => { fetchDevices(); }, []);

  const handleDelete = async (device: Device) => {
    if (!confirm(`Delete device "${device.name}"?`)) return;
    const { error } = await supabase.from("devices").delete().eq("id", device.id);
    if (error) {
      toast({ title: "Delete failed", description: error.message, variant: "destructive" });
    } else {
      toast({ title: "Device deleted" });
      fetchDevices();
    }
  };

  const handleEdit = (device: Device) => {
    setEditDevice(device);
    setDialogOpen(true);
  };

  const handleAdd = () => {
    setEditDevice(null);
    setDialogOpen(true);
  };

  const handleSaved = () => {
    setDialogOpen(false);
    setEditDevice(null);
    fetchDevices();
  };

  // --- Ping single device ---
  const handlePingDevice = async (deviceId: string) => {
    setPingingIds(prev => new Set(prev).add(deviceId));
    try {
      const { error } = await supabase.functions.invoke("ping-device", { body: { device_id: deviceId } });
      if (error) throw error;
      toast({ title: "Ping complete" });
      fetchDevices();
    } catch (err: any) {
      toast({ title: "Ping failed", description: err.message, variant: "destructive" });
    } finally {
      setPingingIds(prev => { const n = new Set(prev); n.delete(deviceId); return n; });
    }
  };

  // --- Ping all devices ---
  const handlePingAll = async () => {
    const deviceIds = devices.filter(d => d.ip_address).map(d => d.id);
    if (deviceIds.length === 0) { toast({ title: "No devices with IP addresses" }); return; }
    setIsPingingAll(true);
    try {
      const { error } = await supabase.functions.invoke("ping-device", { body: { device_ids: deviceIds } });
      if (error) throw error;
      toast({ title: `Pinged ${deviceIds.length} devices` });
      fetchDevices();
    } catch (err: any) {
      toast({ title: "Ping all failed", description: err.message, variant: "destructive" });
    } finally {
      setIsPingingAll(false);
    }
  };

  // --- Export devices as .amp (JSON) ---
  const handleExport = () => {
    const exportData = devices.map(({ id, created_at, updated_at, user_id, last_ping, last_ping_result, last_latency, status, ...rest }) => rest);
    const blob = new Blob([JSON.stringify({ version: "1.0", format: "ampnm", exported_at: new Date().toISOString(), devices: exportData }, null, 2)], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `ampnm-devices-${new Date().toISOString().slice(0, 10)}.amp`;
    a.click();
    URL.revokeObjectURL(url);
    toast({ title: `Exported ${devices.length} devices` });
  };

  // --- Import devices from .amp ---
  const handleImportFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    try {
      const text = await file.text();
      const parsed = JSON.parse(text);
      const importDevices = parsed.devices ?? parsed;
      if (!Array.isArray(importDevices) || importDevices.length === 0) {
        toast({ title: "Invalid file", description: "No devices found in file.", variant: "destructive" });
        return;
      }
      const { data: { user } } = await supabase.auth.getUser();
      if (!user) { toast({ title: "Not authenticated", variant: "destructive" }); return; }

      let imported = 0;
      for (const dev of importDevices) {
        const { error } = await supabase.from("devices").insert({
          name: dev.name || "Imported Device",
          ip_address: dev.ip_address ?? dev.ip ?? null,
          type: dev.type ?? "server",
          subchoice: dev.subchoice ?? null,
          monitor_method: dev.monitor_method ?? "ping",
          check_port: dev.check_port ?? null,
          ping_interval: dev.ping_interval ?? 300,
          icon_size: dev.icon_size ?? 40,
          name_text_size: dev.name_text_size ?? 12,
          description: dev.description ?? null,
          map_id: dev.map_id ?? null,
          x: dev.x ?? 100,
          y: dev.y ?? 100,
          warning_latency_threshold: dev.warning_latency_threshold ?? 100,
          warning_packetloss_threshold: dev.warning_packetloss_threshold ?? 10,
          critical_latency_threshold: dev.critical_latency_threshold ?? 500,
          critical_packetloss_threshold: dev.critical_packetloss_threshold ?? 50,
          show_live_ping: dev.show_live_ping ?? false,
          icon_url: dev.icon_url ?? null,
          user_id: user.id,
        });
        if (!error) imported++;
      }
      toast({ title: `Imported ${imported} of ${importDevices.length} devices` });
      fetchDevices();
    } catch {
      toast({ title: "Import failed", description: "Could not parse file.", variant: "destructive" });
    }
    if (fileInputRef.current) fileInputRef.current.value = "";
  };

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Server className="h-7 w-7 text-primary" />
            <h1 className="text-2xl font-bold tracking-tight">Devices</h1>
            <Badge variant="secondary" className="ml-2">{devices.length}</Badge>
          </div>
          <div className="flex gap-2 flex-wrap">
            {selected.size > 0 && (
              <Button variant="destructive" size="sm" onClick={handleBulkDelete}>
                <Trash2 className="h-4 w-4 mr-1" />
                Delete {selected.size}
              </Button>
            )}
            <Button variant="outline" size="sm" onClick={handlePingAll} disabled={isPingingAll || devices.length === 0}>
              <Activity className={`h-4 w-4 mr-1 ${isPingingAll ? "animate-spin" : ""}`} />
              {isPingingAll ? "Pinging..." : "Ping All"}
            </Button>
            <Button variant="outline" size="sm" onClick={fetchDevices} disabled={loading}>
              <RefreshCw className={`h-4 w-4 mr-1 ${loading ? "animate-spin" : ""}`} />
              Refresh
            </Button>
            <Button variant="outline" size="sm" onClick={handleExport} disabled={devices.length === 0}>
              <Download className="h-4 w-4 mr-1" />
              Export
            </Button>
            <Button variant="outline" size="sm" onClick={() => fileInputRef.current?.click()}>
              <Upload className="h-4 w-4 mr-1" />
              Import
            </Button>
            <input ref={fileInputRef} type="file" accept=".amp,.json" className="hidden" onChange={handleImportFile} />
            <Button size="sm" onClick={handleAdd}>
              <Plus className="h-4 w-4 mr-1" />
              Add Device
            </Button>
          </div>
        </div>

        {/* Search */}
        <div className="relative max-w-sm">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search devices..."
            className="pl-9"
          />
        </div>

        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-10">
                    <Checkbox checked={allSelected} onCheckedChange={toggleAll} aria-label="Select all" />
                  </TableHead>
                  <TableHead>Name</TableHead>
                  <TableHead>IP / Host</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead>Method</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Latency</TableHead>
                  <TableHead>Interval</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {loading ? (
                  <TableRow>
                   <TableCell colSpan={9} className="text-center py-8 text-muted-foreground">
                       Loading devices...
                     </TableCell>
                  </TableRow>
                ) : filteredDevices.length === 0 ? (
                  <TableRow>
                   <TableCell colSpan={9} className="text-center py-8 text-muted-foreground">
                       {search ? "No devices match your search." : 'No devices configured. Click "Add Device" to get started.'}
                    </TableCell>
                  </TableRow>
                ) : (
                  filteredDevices.map((device) => (
                    <TableRow key={device.id} className={selected.has(device.id) ? "bg-muted/50" : ""}>
                      <TableCell>
                        <Checkbox checked={selected.has(device.id)} onCheckedChange={() => toggleOne(device.id)} aria-label={`Select ${device.name}`} />
                      </TableCell>
                      <TableCell className="font-medium">{device.name}</TableCell>
                      <TableCell className="font-mono text-sm">{device.ip_address || "—"}</TableCell>
                      <TableCell className="capitalize">{device.type || "server"}{device.subchoice ? ` (${device.subchoice})` : ""}</TableCell>
                      <TableCell className="capitalize">{device.monitor_method || "ping"}{device.check_port ? `:${device.check_port}` : ""}</TableCell>
                      <TableCell>
                        <Badge className={statusColor(device.status)} variant={statusVariant(device.status)}>
                          {device.status || "unknown"}
                        </Badge>
                      </TableCell>
                      <TableCell>{device.last_latency != null ? `${device.last_latency}ms` : "—"}</TableCell>
                      <TableCell className="text-sm text-muted-foreground">
                        <span className="flex items-center gap-1">
                          <Timer className="h-3 w-3" />
                          {device.ping_interval ?? 300}s
                        </span>
                      </TableCell>
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-1">
                          {device.ip_address && (
                            <Button variant="ghost" size="icon" onClick={() => handlePingDevice(device.id)} disabled={pingingIds.has(device.id)} title="Ping device">
                              <Activity className={`h-4 w-4 ${pingingIds.has(device.id) ? "animate-spin" : ""}`} />
                            </Button>
                          )}
                          <Button variant="ghost" size="icon" onClick={() => handleEdit(device)}>
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <Button variant="ghost" size="icon" onClick={() => handleDelete(device)} className="text-destructive hover:text-destructive">
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        <DeviceFormDialog
          open={dialogOpen}
          onOpenChange={(open) => { setDialogOpen(open); if (!open) setEditDevice(null); }}
          device={editDevice}
          onSaved={handleSaved}
        />
      </div>
    </AppLayout>
  );
}
