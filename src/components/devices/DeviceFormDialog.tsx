import { useState, useEffect } from "react";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/hooks/useAuth";
import { useToast } from "@/hooks/use-toast";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Loader2, Plus, Trash2 } from "lucide-react";
import type { Tables } from "@/integrations/supabase/types";
import { DeviceIconPicker, getIconComponent } from "./DeviceIconPicker";

type Device = Tables<"devices">;
type MapRow = Tables<"maps">;

const DEVICE_TYPES = [
  { value: "server", label: "Server" },
  { value: "router", label: "Router" },
  { value: "switch", label: "Switch" },
  { value: "firewall", label: "Firewall" },
  { value: "access_point", label: "Access Point" },
  { value: "printer", label: "Printer" },
  { value: "camera", label: "Camera" },
  { value: "workstation", label: "Workstation" },
  { value: "other", label: "Other" },
];

const MONITOR_METHODS = [
  { value: "ping", label: "ICMP Ping" },
  { value: "tcp", label: "TCP Port Check" },
  { value: "http", label: "HTTP Check" },
  { value: "none", label: "None (Manual)" },
];

interface PortGroup {
  type: string;
  prefix: string;
  start: number;
  count: number;
  vlan: string;
}

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  device: Device | null;
  onSaved: () => void;
}

export function DeviceFormDialog({ open, onOpenChange, device, onSaved }: Props) {
  const { user } = useAuth();
  const { toast } = useToast();
  const [saving, setSaving] = useState(false);
  const [maps, setMaps] = useState<MapRow[]>([]);
  const [agentHosts, setAgentHosts] = useState<any[]>([]);
  const [iconPickerOpen, setIconPickerOpen] = useState(false);

  const [name, setName] = useState("");
  const [mapId, setMapId] = useState<string>("__none__");
  const [ipAddress, setIpAddress] = useState("");
  const [description, setDescription] = useState("");
  const [type, setType] = useState("server");
  const [subchoice, setSubchoice] = useState("");
  const [monitorMethod, setMonitorMethod] = useState("ping");
  const [checkPort, setCheckPort] = useState<number | "">("");
  const [pingInterval, setPingInterval] = useState(300);
  const [iconUrl, setIconUrl] = useState("");
  const [iconSize, setIconSize] = useState(40);
  const [nameTextSize, setNameTextSize] = useState(12);
  const [showLivePing, setShowLivePing] = useState(false);
  const [warningLatency, setWarningLatency] = useState(100);
  const [warningPacketloss, setWarningPacketloss] = useState(10);
  const [criticalLatency, setCriticalLatency] = useState(500);
  const [criticalPacketloss, setCriticalPacketloss] = useState(50);
  const [portGroups, setPortGroups] = useState<PortGroup[]>([]);

  // Fetch maps
  useEffect(() => {
    if (open) {
      supabase.from("maps").select("*").order("name").then(({ data }) => setMaps(data ?? []));
      if (!device) {
        supabase.from("host_metrics").select("hostname, ip_address").order("hostname").then(({ data }) => setAgentHosts(data ?? []));
      }
    }
  }, [open, device]);

  useEffect(() => {
    if (device) {
      setName(device.name);
      setMapId(device.map_id ?? "__none__");
      setIpAddress(device.ip_address ?? "");
      setDescription(device.description ?? "");
      setType(device.type ?? "server");
      setSubchoice(device.subchoice ?? "");
      setMonitorMethod(device.monitor_method ?? "ping");
      setCheckPort(device.check_port ?? "");
      setPingInterval(device.ping_interval ?? 300);
      setIconUrl(device.icon_url ?? "");
      setIconSize(device.icon_size ?? 40);
      setNameTextSize(device.name_text_size ?? 12);
      setShowLivePing(device.show_live_ping ?? false);
      setWarningLatency(device.warning_latency_threshold ?? 100);
      setWarningPacketloss(device.warning_packetloss_threshold ?? 10);
      setCriticalLatency(device.critical_latency_threshold ?? 500);
      setCriticalPacketloss(device.critical_packetloss_threshold ?? 50);
      // Parse port_config
      try {
        const pc = (device as any).port_config;
        if (pc && Array.isArray(pc)) setPortGroups(pc);
        else setPortGroups([]);
      } catch { setPortGroups([]); }
    } else {
      setName(""); setMapId("__none__"); setIpAddress(""); setDescription(""); setType("server");
      setSubchoice(""); setMonitorMethod("ping"); setCheckPort("");
      setPingInterval(300); setIconUrl(""); setIconSize(40); setNameTextSize(12);
      setShowLivePing(false); setWarningLatency(100); setWarningPacketloss(10);
      setCriticalLatency(500); setCriticalPacketloss(50); setPortGroups([]);
    }
  }, [device, open]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!user) return;
    setSaving(true);

    const payload = {
      name: name.trim(),
      map_id: mapId === "__none__" ? null : mapId,
      ip_address: ipAddress.trim() || null,
      description: description.trim() || null,
      type,
      subchoice: subchoice.trim() || null,
      monitor_method: monitorMethod,
      check_port: checkPort === "" ? null : Number(checkPort),
      ping_interval: pingInterval,
      icon_url: iconUrl.trim() || null,
      icon_size: iconSize,
      name_text_size: nameTextSize,
      show_live_ping: showLivePing,
      warning_latency_threshold: warningLatency,
      warning_packetloss_threshold: warningPacketloss,
      critical_latency_threshold: criticalLatency,
      critical_packetloss_threshold: criticalPacketloss,
      user_id: device?.user_id ?? user.id,
    };

    let error;
    if (device) {
      ({ error } = await supabase.from("devices").update(payload).eq("id", device.id));
    } else {
      ({ error } = await supabase.from("devices").insert(payload));
    }

    if (error) {
      toast({ title: "Save failed", description: error.message, variant: "destructive" });
    } else {
      toast({ title: device ? "Device updated" : "Device created" });
      onSaved();
    }
    setSaving(false);
  };

  return (
    <>
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{device ? "Edit Device" : "Add Device"}</DialogTitle>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="space-y-4">
          <Tabs defaultValue="general">
            <TabsList className="grid w-full grid-cols-3">
              <TabsTrigger value="general">General</TabsTrigger>
              <TabsTrigger value="monitoring">Monitoring</TabsTrigger>
              <TabsTrigger value="thresholds">Thresholds</TabsTrigger>
            </TabsList>

            <TabsContent value="general" className="space-y-4 mt-4">
              {/* Agent host quick-fill */}
              {!device && agentHosts.length > 0 && (
                <div className="space-y-2">
                  <Label>Import from Agent Host</Label>
                  <Select value="" onValueChange={(hostname) => {
                    const host = agentHosts.find(h => h.hostname === hostname);
                    if (host) {
                      setName(host.hostname);
                      setIpAddress(host.ip_address || "");
                      setType("workstation");
                      toast({ title: `Filled from agent host: ${host.hostname}` });
                    }
                  }}>
                    <SelectTrigger><SelectValue placeholder="Select an agent host to auto-fill..." /></SelectTrigger>
                    <SelectContent>
                      {agentHosts.map((h) => (
                        <SelectItem key={h.hostname} value={h.hostname}>
                          {h.hostname} {h.ip_address ? `(${h.ip_address})` : ""}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              )}
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="name">Device Name *</Label>
                  <Input id="name" required value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. Core Router" />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="ip">IP Address / Hostname</Label>
                  <Input id="ip" value={ipAddress} onChange={(e) => setIpAddress(e.target.value)} placeholder="e.g. 192.168.1.1" />
                </div>
              </div>
              <div className="space-y-2">
                <Label>Map</Label>
                <Select value={mapId} onValueChange={setMapId}>
                  <SelectTrigger><SelectValue placeholder="No map assigned" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__none__">None</SelectItem>
                    {maps.map((m) => (
                      <SelectItem key={m.id} value={m.id}>{m.name}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="desc">Description</Label>
                <Textarea id="desc" value={description} onChange={(e) => setDescription(e.target.value)} placeholder="Optional description" rows={2} />
              </div>
              <div className="space-y-2">
                <Label>Device Icon</Label>
                <div className="flex items-center gap-3">
                  {(() => {
                    const SelectedIcon = getIconComponent(subchoice || type);
                    return (
                      <div className="flex items-center justify-center w-12 h-12 rounded-lg border border-border bg-muted">
                        <SelectedIcon className="h-6 w-6 text-foreground" />
                      </div>
                    );
                  })()}
                  <div className="flex-1">
                    <Button type="button" variant="outline" className="w-full" onClick={() => setIconPickerOpen(true)}>
                      Choose Icon...
                    </Button>
                    <p className="text-xs text-muted-foreground mt-1">
                      Type: <span className="font-medium">{type}</span>
                      {subchoice && <> · Variant: <span className="font-medium">{subchoice}</span></>}
                    </p>
                  </div>
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="icon_url">Custom Icon URL</Label>
                <div className="flex items-center gap-3">
                  {iconUrl.trim() && (
                    <div className="flex-shrink-0 w-12 h-12 rounded-lg border border-border bg-muted flex items-center justify-center overflow-hidden">
                      <img
                        src={iconUrl}
                        alt="Custom icon preview"
                        className="max-w-full max-h-full object-contain"
                        onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
                        onLoad={(e) => { (e.target as HTMLImageElement).style.display = 'block'; }}
                      />
                    </div>
                  )}
                  <div className="flex-1">
                    <Input id="icon_url" value={iconUrl} onChange={(e) => setIconUrl(e.target.value)} placeholder="https://example.com/icon.png" />
                    <p className="text-xs text-muted-foreground mt-1">
                      {iconUrl.trim() ? "Custom URL overrides the icon picker above" : "Leave blank to use the icon picker above"}
                    </p>
                  </div>
                  {iconUrl.trim() && (
                    <Button type="button" variant="ghost" size="sm" onClick={() => setIconUrl("")} className="text-xs text-muted-foreground">
                      Clear
                    </Button>
                  )}
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="icon_size">Icon Size</Label>
                  <Input id="icon_size" type="number" min={16} max={128} value={iconSize} onChange={(e) => setIconSize(Number(e.target.value))} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="text_size">Text Size</Label>
                  <Input id="text_size" type="number" min={8} max={32} value={nameTextSize} onChange={(e) => setNameTextSize(Number(e.target.value))} />
                </div>
              </div>
            </TabsContent>

            <TabsContent value="monitoring" className="space-y-4 mt-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label>Monitor Method</Label>
                  <Select value={monitorMethod} onValueChange={setMonitorMethod}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {MONITOR_METHODS.map((m) => (
                        <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="port">Check Port</Label>
                  <Input
                    id="port" type="number" min={1} max={65535}
                    value={checkPort} onChange={(e) => setCheckPort(e.target.value === "" ? "" : Number(e.target.value))}
                    placeholder="e.g. 80, 443"
                    disabled={monitorMethod === "ping" || monitorMethod === "none"}
                  />
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="interval">Ping Interval (seconds)</Label>
                <Input id="interval" type="number" min={10} max={3600} value={pingInterval} onChange={(e) => setPingInterval(Number(e.target.value))} />
              </div>
              <div className="flex items-center gap-3">
                <Switch checked={showLivePing} onCheckedChange={setShowLivePing} id="live_ping" />
                <Label htmlFor="live_ping">Show live ping on map</Label>
              </div>
            </TabsContent>

            <TabsContent value="thresholds" className="space-y-4 mt-4">
              <p className="text-sm text-muted-foreground">Set thresholds to determine when a device status changes to warning or critical.</p>
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="wl">Warning Latency (ms)</Label>
                  <Input id="wl" type="number" min={1} value={warningLatency} onChange={(e) => setWarningLatency(Number(e.target.value))} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="wp">Warning Packet Loss (%)</Label>
                  <Input id="wp" type="number" min={0} max={100} value={warningPacketloss} onChange={(e) => setWarningPacketloss(Number(e.target.value))} />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="cl">Critical Latency (ms)</Label>
                  <Input id="cl" type="number" min={1} value={criticalLatency} onChange={(e) => setCriticalLatency(Number(e.target.value))} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="cp">Critical Packet Loss (%)</Label>
                  <Input id="cp" type="number" min={0} max={100} value={criticalPacketloss} onChange={(e) => setCriticalPacketloss(Number(e.target.value))} />
                </div>
              </div>
            </TabsContent>
          </Tabs>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
            <Button type="submit" disabled={saving || !name.trim()}>
              {saving && <Loader2 className="h-4 w-4 animate-spin mr-2" />}
              {device ? "Update" : "Create"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <DeviceIconPicker
      open={iconPickerOpen}
      onOpenChange={setIconPickerOpen}
      selectedType={type}
      selectedSubchoice={subchoice}
      onSelect={(newType, newSubchoice) => {
        setType(newType);
        setSubchoice(newSubchoice);
      }}
    />
    </>
  );
}
