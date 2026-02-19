import { useState, useEffect } from "react";
import { supabase } from "@/integrations/supabase/client";
import { AppLayout } from "@/components/layout/AppLayout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Plus, Pencil, Trash2, Server, RefreshCw, Search } from "lucide-react";
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
  const { toast } = useToast();

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

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Server className="h-7 w-7 text-primary" />
            <h1 className="text-2xl font-bold tracking-tight">Devices</h1>
            <Badge variant="secondary" className="ml-2">{devices.length}</Badge>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={fetchDevices} disabled={loading}>
              <RefreshCw className={`h-4 w-4 mr-1 ${loading ? "animate-spin" : ""}`} />
              Refresh
            </Button>
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
                  <TableHead>Name</TableHead>
                  <TableHead>IP / Host</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead>Method</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Latency</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {loading ? (
                  <TableRow>
                    <TableCell colSpan={7} className="text-center py-8 text-muted-foreground">
                      Loading devices...
                    </TableCell>
                  </TableRow>
                ) : devices.filter(d => {
                  if (!search.trim()) return true;
                  const q = search.toLowerCase();
                  return d.name.toLowerCase().includes(q) || (d.ip_address || "").toLowerCase().includes(q);
                }).length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={7} className="text-center py-8 text-muted-foreground">
                      {search ? "No devices match your search." : 'No devices configured. Click "Add Device" to get started.'}
                    </TableCell>
                  </TableRow>
                ) : (
                  devices.filter(d => {
                    if (!search.trim()) return true;
                    const q = search.toLowerCase();
                    return d.name.toLowerCase().includes(q) || (d.ip_address || "").toLowerCase().includes(q);
                  }).map((device) => (
                    <TableRow key={device.id}>
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
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-1">
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
