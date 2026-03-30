import { useState, useEffect } from "react";
import { supabase } from "@/integrations/supabase/client";
import { AppLayout } from "@/components/layout/AppLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { Mail, Plus, Trash2, Save, Bell, BellRing, Link2, Wrench, CheckCircle2 } from "lucide-react";
import { useToast } from "@/hooks/use-toast";
import { useAuth } from "@/hooks/useAuth";
import type { Tables } from "@/integrations/supabase/types";

type SmtpSettings = Tables<"smtp_settings">;
type Subscription = Tables<"device_email_subscriptions">;
type Device = Pick<Tables<"devices">, "id" | "name">;
type Profile = Pick<Tables<"profiles">, "user_id" | "full_name" | "username">;

interface AlertPolicyForm {
  name: string;
  target_type: "global" | "host" | "device" | "group";
  target_id: string;
  group_name: string;
  severity: "info" | "warning" | "critical";
  dedup_window_seconds: number;
  cooldown_seconds: number;
  escalation_delay_seconds: number;
}

interface MaintenanceForm {
  name: string;
  scope_type: "global" | "map" | "device" | "group";
  map_id: string;
  device_id: string;
  group_name: string;
  starts_at: string;
  ends_at: string;
}

export default function NotificationsPage() {
  const { toast } = useToast();
  const { user } = useAuth();

  const [smtp, setSmtp] = useState<Partial<SmtpSettings>>({ enabled: false, smtp_host: "", smtp_port: 587, smtp_username: "", smtp_password: "", smtp_encryption: "tls", smtp_from_name: "AMPNM", smtp_from_email: "" });

  const [subs, setSubs] = useState<Subscription[]>([]);
  const [devices, setDevices] = useState<Device[]>([]);
  const [profiles, setProfiles] = useState<Profile[]>([]);
  const [newEmail, setNewEmail] = useState("");
  const [newDeviceId, setNewDeviceId] = useState("");

  const [policies, setPolicies] = useState<any[]>([]);
  const [newPolicy, setNewPolicy] = useState<AlertPolicyForm>({
    name: "",
    target_type: "global",
    target_id: "",
    group_name: "",
    severity: "warning",
    dedup_window_seconds: 300,
    cooldown_seconds: 600,
    escalation_delay_seconds: 900,
  });

  const [maintenance, setMaintenance] = useState<any[]>([]);
  const [newMaintenance, setNewMaintenance] = useState<MaintenanceForm>({
    name: "",
    scope_type: "global",
    map_id: "",
    device_id: "",
    group_name: "",
    starts_at: "",
    ends_at: "",
  });

  const [dependencies, setDependencies] = useState<any[]>([]);
  const [newDependency, setNewDependency] = useState({ parent_device_id: "", child_device_id: "" });

  const [routes, setRoutes] = useState<any[]>([]);
  const [newRoute, setNewRoute] = useState({
    route_name: "",
    severity: "warning",
    group_name: "",
    channel: "email",
    destination: "",
  });

  const [alerts, setAlerts] = useState<any[]>([]);
  const [selectedAlertId, setSelectedAlertId] = useState<string>("");
  const [transitions, setTransitions] = useState<any[]>([]);
  const [rootCause, setRootCause] = useState("");

  useEffect(() => {
    (async () => {
      const { data } = await supabase.from("smtp_settings").select("*").maybeSingle();
      if (data) setSmtp(data);
    })();

    supabase.from("devices").select("id, name").order("name").then(({ data }) => setDevices(data ?? []));
    supabase.from("profiles").select("user_id, full_name, username").then(({ data }) => setProfiles(data ?? []));

    fetchSubs();
    fetchAlertSubsystem();
  }, []);

  useEffect(() => {
    if (!selectedAlertId) {
      setTransitions([]);
      return;
    }

    (async () => {
      const { data } = await (supabase as any)
        .from("alert_state_transitions")
        .select("*")
        .eq("alert_event_id", selectedAlertId)
        .order("changed_at", { ascending: false });
      setTransitions(data ?? []);
    })();
  }, [selectedAlertId]);

  const fetchSubs = async () => {
    const { data } = await supabase.from("device_email_subscriptions").select("*").order("created_at", { ascending: false });
    setSubs(data ?? []);
  };

  const fetchAlertSubsystem = async () => {
    const [policyRes, maintenanceRes, depsRes, routeRes, alertRes] = await Promise.all([
      (supabase as any).from("alert_policies").select("*").order("created_at", { ascending: false }),
      (supabase as any).from("alert_maintenance_windows").select("*").order("starts_at", { ascending: false }),
      (supabase as any).from("alert_dependencies").select("*").order("created_at", { ascending: false }),
      (supabase as any).from("alert_notification_routes").select("*").order("created_at", { ascending: false }),
      (supabase as any).from("alert_events").select("*").order("last_seen_at", { ascending: false }),
    ]);

    setPolicies(policyRes.data ?? []);
    setMaintenance(maintenanceRes.data ?? []);
    setDependencies(depsRes.data ?? []);
    setRoutes(routeRes.data ?? []);
    setAlerts(alertRes.data ?? []);
  };

  const saveSmtp = async () => {
    const payload = { ...smtp, user_id: user!.id };
    delete (payload as any).id;
    delete (payload as any).created_at;
    delete (payload as any).updated_at;

    if (smtp.id) {
      const { error } = await supabase.from("smtp_settings").update(payload).eq("id", smtp.id);
      if (error) toast({ title: "Error", description: error.message, variant: "destructive" });
      else toast({ title: "SMTP settings saved" });
    } else {
      const { data, error } = await supabase.from("smtp_settings").insert(payload as any).select().single();
      if (error) toast({ title: "Error", description: error.message, variant: "destructive" });
      else {
        setSmtp(data);
        toast({ title: "SMTP settings created" });
      }
    }
  };

  const addSub = async () => {
    if (!newEmail || !newDeviceId) return;
    const { error } = await supabase.from("device_email_subscriptions").insert({ email: newEmail, device_id: newDeviceId });
    if (error) toast({ title: "Error", description: error.message, variant: "destructive" });
    else {
      toast({ title: "Subscription added" });
      setNewEmail("");
      fetchSubs();
    }
  };

  const deleteSub = async (id: string) => {
    await supabase.from("device_email_subscriptions").delete().eq("id", id);
    toast({ title: "Subscription removed" });
    fetchSubs();
  };

  const addPolicy = async () => {
    if (!newPolicy.name) return;
    const payload: any = { ...newPolicy, created_by: user?.id ?? null };
    payload.target_id = newPolicy.target_type === "device" ? newPolicy.target_id || null : null;
    payload.group_name = newPolicy.target_type === "group" ? newPolicy.group_name || null : null;

    const { error } = await (supabase as any).from("alert_policies").insert(payload);
    if (error) {
      toast({ title: "Error", description: error.message, variant: "destructive" });
      return;
    }

    toast({ title: "Alert policy created" });
    setNewPolicy({ ...newPolicy, name: "" });
    fetchAlertSubsystem();
  };

  const addMaintenance = async () => {
    if (!newMaintenance.name || !newMaintenance.starts_at || !newMaintenance.ends_at) return;

    const payload: any = {
      ...newMaintenance,
      map_id: newMaintenance.scope_type === "map" ? newMaintenance.map_id || null : null,
      device_id: newMaintenance.scope_type === "device" ? newMaintenance.device_id || null : null,
      group_name: newMaintenance.scope_type === "group" ? newMaintenance.group_name || null : null,
      created_by: user?.id ?? null,
    };

    const { error } = await (supabase as any).from("alert_maintenance_windows").insert(payload);
    if (error) {
      toast({ title: "Error", description: error.message, variant: "destructive" });
      return;
    }

    toast({ title: "Maintenance window created" });
    setNewMaintenance({ ...newMaintenance, name: "" });
    fetchAlertSubsystem();
  };

  const addDependency = async () => {
    if (!newDependency.parent_device_id || !newDependency.child_device_id) return;
    const { error } = await (supabase as any).from("alert_dependencies").insert(newDependency);
    if (error) toast({ title: "Error", description: error.message, variant: "destructive" });
    else {
      toast({ title: "Dependency added" });
      setNewDependency({ parent_device_id: "", child_device_id: "" });
      fetchAlertSubsystem();
    }
  };

  const addRoute = async () => {
    if (!newRoute.route_name || !newRoute.destination) return;
    const { error } = await (supabase as any).from("alert_notification_routes").insert(newRoute);
    if (error) toast({ title: "Error", description: error.message, variant: "destructive" });
    else {
      toast({ title: "Notification route added" });
      setNewRoute({ ...newRoute, route_name: "", destination: "" });
      fetchAlertSubsystem();
    }
  };

  const ackAlert = async (alertId: string) => {
    const { error } = await (supabase as any)
      .from("alert_events")
      .update({ state: "acknowledged", acknowledged_at: new Date().toISOString(), acknowledged_by: user?.id })
      .eq("id", alertId);

    if (error) toast({ title: "Acknowledge failed", description: error.message, variant: "destructive" });
    else {
      toast({ title: "Alert acknowledged" });
      fetchAlertSubsystem();
    }
  };

  const claimAlert = async (alertId: string) => {
    const { error } = await (supabase as any).from("alert_events").update({ owner_user_id: user?.id }).eq("id", alertId);
    if (error) toast({ title: "Ownership failed", description: error.message, variant: "destructive" });
    else {
      toast({ title: "You now own this alert" });
      fetchAlertSubsystem();
    }
  };

  const resolveAlert = async (alertId: string) => {
    const { error } = await (supabase as any)
      .from("alert_events")
      .update({ state: "resolved", resolved_at: new Date().toISOString(), root_cause: rootCause || null })
      .eq("id", alertId);

    if (error) toast({ title: "Resolve failed", description: error.message, variant: "destructive" });
    else {
      toast({ title: "Alert resolved" });
      setRootCause("");
      fetchAlertSubsystem();
    }
  };

  const deviceName = (id: string) => devices.find((d) => d.id === id)?.name || id.slice(0, 8);
  const userLabel = (id: string | null | undefined) => {
    if (!id) return "-";
    const profile = profiles.find((p) => p.user_id === id);
    return profile?.full_name || profile?.username || id.slice(0, 8);
  };

  return (
    <AppLayout>
      <div className="space-y-4">
        <div className="flex items-center gap-3">
          <Mail className="h-7 w-7 text-primary" />
          <h1 className="text-2xl font-bold tracking-tight">Notifications & Alerts</h1>
        </div>

        <Tabs defaultValue="smtp">
          <TabsList className="flex flex-wrap h-auto gap-1">
            <TabsTrigger value="smtp">SMTP</TabsTrigger>
            <TabsTrigger value="subscriptions">Subscriptions</TabsTrigger>
            <TabsTrigger value="policies">Policies</TabsTrigger>
            <TabsTrigger value="maintenance">Maintenance</TabsTrigger>
            <TabsTrigger value="dependencies">Dependencies</TabsTrigger>
            <TabsTrigger value="routing">Routing Matrix</TabsTrigger>
            <TabsTrigger value="workflow">Ack & Ownership</TabsTrigger>
            <TabsTrigger value="audit">State Audit</TabsTrigger>
          </TabsList>

          <TabsContent value="smtp">
            <Card>
              <CardHeader><CardTitle className="text-base">SMTP Configuration</CardTitle></CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center gap-3">
                  <Switch checked={smtp.enabled ?? false} onCheckedChange={(v) => setSmtp({ ...smtp, enabled: v })} />
                  <Label>Enable email notifications</Label>
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                  <div><Label>SMTP Host</Label><Input value={smtp.smtp_host ?? ""} onChange={(e) => setSmtp({ ...smtp, smtp_host: e.target.value })} placeholder="smtp.gmail.com" /></div>
                  <div><Label>SMTP Port</Label><Input type="number" value={smtp.smtp_port ?? 587} onChange={(e) => setSmtp({ ...smtp, smtp_port: Number(e.target.value) })} /></div>
                  <div><Label>Username</Label><Input value={smtp.smtp_username ?? ""} onChange={(e) => setSmtp({ ...smtp, smtp_username: e.target.value })} /></div>
                  <div><Label>Password</Label><Input type="password" value={smtp.smtp_password ?? ""} onChange={(e) => setSmtp({ ...smtp, smtp_password: e.target.value })} /></div>
                  <div><Label>Encryption</Label>
                    <Select value={smtp.smtp_encryption ?? "tls"} onValueChange={(v) => setSmtp({ ...smtp, smtp_encryption: v })}>
                      <SelectTrigger><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="tls">TLS</SelectItem>
                        <SelectItem value="ssl">SSL</SelectItem>
                        <SelectItem value="none">None</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div><Label>From Name</Label><Input value={smtp.smtp_from_name ?? ""} onChange={(e) => setSmtp({ ...smtp, smtp_from_name: e.target.value })} /></div>
                  <div className="md:col-span-2"><Label>From Email</Label><Input value={smtp.smtp_from_email ?? ""} onChange={(e) => setSmtp({ ...smtp, smtp_from_email: e.target.value })} placeholder="alerts@example.com" /></div>
                </div>
                <Button onClick={saveSmtp}><Save className="h-4 w-4 mr-1" /> Save Settings</Button>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="subscriptions">
            <Card>
              <CardHeader><CardTitle className="text-base">Device Email Subscriptions</CardTitle></CardHeader>
              <CardContent className="space-y-4">
                <div className="flex flex-wrap items-end gap-2">
                  <div><Label>Device</Label>
                    <Select value={newDeviceId} onValueChange={setNewDeviceId}>
                      <SelectTrigger className="w-[200px]"><SelectValue placeholder="Select device" /></SelectTrigger>
                      <SelectContent>{devices.map((d) => <SelectItem key={d.id} value={d.id}>{d.name}</SelectItem>)}</SelectContent>
                    </Select>
                  </div>
                  <div><Label>Email</Label><Input value={newEmail} onChange={(e) => setNewEmail(e.target.value)} placeholder="admin@example.com" className="w-[250px]" /></div>
                  <Button size="sm" onClick={addSub}><Plus className="h-4 w-4 mr-1" /> Add</Button>
                </div>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Device</TableHead><TableHead>Email</TableHead><TableHead>Alerts</TableHead><TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {subs.length === 0 ? (
                      <TableRow><TableCell colSpan={4} className="text-center py-6 text-muted-foreground">No subscriptions.</TableCell></TableRow>
                    ) : subs.map((s) => (
                      <TableRow key={s.id}>
                        <TableCell className="font-medium">{deviceName(s.device_id)}</TableCell>
                        <TableCell>{s.email}</TableCell>
                        <TableCell>
                          <div className="flex gap-1 flex-wrap">
                            {s.notify_on_online && <Badge variant="secondary" className="text-[10px]">Online</Badge>}
                            {s.notify_on_offline && <Badge variant="secondary" className="text-[10px]">Offline</Badge>}
                            {s.notify_on_warning && <Badge variant="secondary" className="text-[10px]">Warning</Badge>}
                            {s.notify_on_critical && <Badge variant="secondary" className="text-[10px]">Critical</Badge>}
                          </div>
                        </TableCell>
                        <TableCell className="text-right">
                          <Button variant="ghost" size="icon" onClick={() => deleteSub(s.id)} className="text-destructive hover:text-destructive"><Trash2 className="h-4 w-4" /></Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="policies">
            <Card>
              <CardHeader><CardTitle className="text-base flex items-center gap-2"><BellRing className="h-4 w-4" /> Alert Policies</CardTitle></CardHeader>
              <CardContent className="space-y-4">
                <div className="grid md:grid-cols-3 gap-3">
                  <Input placeholder="Policy name" value={newPolicy.name} onChange={(e) => setNewPolicy({ ...newPolicy, name: e.target.value })} />
                  <Select value={newPolicy.target_type} onValueChange={(v: any) => setNewPolicy({ ...newPolicy, target_type: v })}>
                    <SelectTrigger><SelectValue placeholder="Target" /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="global">Global</SelectItem>
                      <SelectItem value="host">Host</SelectItem>
                      <SelectItem value="device">Device</SelectItem>
                      <SelectItem value="group">Group</SelectItem>
                    </SelectContent>
                  </Select>
                  <Select value={newPolicy.severity} onValueChange={(v: any) => setNewPolicy({ ...newPolicy, severity: v })}>
                    <SelectTrigger><SelectValue placeholder="Severity" /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="info">Info</SelectItem>
                      <SelectItem value="warning">Warning</SelectItem>
                      <SelectItem value="critical">Critical</SelectItem>
                    </SelectContent>
                  </Select>
                  {newPolicy.target_type === "device" && (
                    <Select value={newPolicy.target_id} onValueChange={(v) => setNewPolicy({ ...newPolicy, target_id: v })}>
                      <SelectTrigger><SelectValue placeholder="Target device" /></SelectTrigger>
                      <SelectContent>{devices.map((d) => <SelectItem key={d.id} value={d.id}>{d.name}</SelectItem>)}</SelectContent>
                    </Select>
                  )}
                  {newPolicy.target_type === "group" && (
                    <Input placeholder="Group name" value={newPolicy.group_name} onChange={(e) => setNewPolicy({ ...newPolicy, group_name: e.target.value })} />
                  )}
                  <Input type="number" placeholder="Dedup seconds" value={newPolicy.dedup_window_seconds} onChange={(e) => setNewPolicy({ ...newPolicy, dedup_window_seconds: Number(e.target.value) })} />
                  <Input type="number" placeholder="Cooldown seconds" value={newPolicy.cooldown_seconds} onChange={(e) => setNewPolicy({ ...newPolicy, cooldown_seconds: Number(e.target.value) })} />
                  <Input type="number" placeholder="Escalation delay" value={newPolicy.escalation_delay_seconds} onChange={(e) => setNewPolicy({ ...newPolicy, escalation_delay_seconds: Number(e.target.value) })} />
                </div>
                <Button onClick={addPolicy}><Plus className="h-4 w-4 mr-1" />Add Policy</Button>
                <div className="space-y-2">
                  {policies.map((p) => (
                    <div key={p.id} className="p-2 rounded border text-sm flex items-center justify-between">
                      <span>{p.name} · {p.target_type} · <strong>{p.severity}</strong></span>
                      <span className="text-muted-foreground">dedup {p.dedup_window_seconds}s · cooldown {p.cooldown_seconds}s · escalation {p.escalation_delay_seconds}s</span>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="maintenance">
            <Card>
              <CardHeader><CardTitle className="text-base flex items-center gap-2"><Wrench className="h-4 w-4" /> Maintenance Windows</CardTitle></CardHeader>
              <CardContent className="space-y-4">
                <div className="grid md:grid-cols-3 gap-3">
                  <Input placeholder="Window name" value={newMaintenance.name} onChange={(e) => setNewMaintenance({ ...newMaintenance, name: e.target.value })} />
                  <Select value={newMaintenance.scope_type} onValueChange={(v: any) => setNewMaintenance({ ...newMaintenance, scope_type: v })}>
                    <SelectTrigger><SelectValue placeholder="Scope" /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="global">Global</SelectItem>
                      <SelectItem value="map">Map</SelectItem>
                      <SelectItem value="device">Device</SelectItem>
                      <SelectItem value="group">Group</SelectItem>
                    </SelectContent>
                  </Select>
                  {newMaintenance.scope_type === "device" && (
                    <Select value={newMaintenance.device_id} onValueChange={(v) => setNewMaintenance({ ...newMaintenance, device_id: v })}>
                      <SelectTrigger><SelectValue placeholder="Scope device" /></SelectTrigger>
                      <SelectContent>{devices.map((d) => <SelectItem key={d.id} value={d.id}>{d.name}</SelectItem>)}</SelectContent>
                    </Select>
                  )}
                  {newMaintenance.scope_type === "group" && (
                    <Input placeholder="Group name" value={newMaintenance.group_name} onChange={(e) => setNewMaintenance({ ...newMaintenance, group_name: e.target.value })} />
                  )}
                  <Input type="datetime-local" value={newMaintenance.starts_at} onChange={(e) => setNewMaintenance({ ...newMaintenance, starts_at: e.target.value })} />
                  <Input type="datetime-local" value={newMaintenance.ends_at} onChange={(e) => setNewMaintenance({ ...newMaintenance, ends_at: e.target.value })} />
                </div>
                <Button onClick={addMaintenance}><Plus className="h-4 w-4 mr-1" />Add Maintenance Window</Button>
                <div className="space-y-2">
                  {maintenance.map((w) => (
                    <div key={w.id} className="p-2 rounded border text-sm flex items-center justify-between">
                      <span>{w.name} · {w.scope_type}</span>
                      <span className="text-muted-foreground">{new Date(w.starts_at).toLocaleString()} → {new Date(w.ends_at).toLocaleString()}</span>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="dependencies">
            <Card>
              <CardHeader><CardTitle className="text-base flex items-center gap-2"><Link2 className="h-4 w-4" /> Parent/Child Dependencies</CardTitle></CardHeader>
              <CardContent className="space-y-4">
                <div className="grid md:grid-cols-2 gap-3">
                  <Select value={newDependency.parent_device_id} onValueChange={(v) => setNewDependency({ ...newDependency, parent_device_id: v })}>
                    <SelectTrigger><SelectValue placeholder="Parent device" /></SelectTrigger>
                    <SelectContent>{devices.map((d) => <SelectItem key={d.id} value={d.id}>{d.name}</SelectItem>)}</SelectContent>
                  </Select>
                  <Select value={newDependency.child_device_id} onValueChange={(v) => setNewDependency({ ...newDependency, child_device_id: v })}>
                    <SelectTrigger><SelectValue placeholder="Child device" /></SelectTrigger>
                    <SelectContent>{devices.map((d) => <SelectItem key={d.id} value={d.id}>{d.name}</SelectItem>)}</SelectContent>
                  </Select>
                </div>
                <Button onClick={addDependency}><Plus className="h-4 w-4 mr-1" />Add Dependency</Button>
                <div className="space-y-2">
                  {dependencies.map((d) => (
                    <div key={d.id} className="p-2 rounded border text-sm">
                      {deviceName(d.parent_device_id)} ⟶ {deviceName(d.child_device_id)}
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="routing">
            <Card>
              <CardHeader><CardTitle className="text-base flex items-center gap-2"><Bell className="h-4 w-4" /> Notification Routing Matrix</CardTitle></CardHeader>
              <CardContent className="space-y-4">
                <div className="grid md:grid-cols-5 gap-3">
                  <Input placeholder="Route name" value={newRoute.route_name} onChange={(e) => setNewRoute({ ...newRoute, route_name: e.target.value })} />
                  <Select value={newRoute.severity} onValueChange={(v) => setNewRoute({ ...newRoute, severity: v })}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="info">Info</SelectItem>
                      <SelectItem value="warning">Warning</SelectItem>
                      <SelectItem value="critical">Critical</SelectItem>
                    </SelectContent>
                  </Select>
                  <Input placeholder="Group (optional)" value={newRoute.group_name} onChange={(e) => setNewRoute({ ...newRoute, group_name: e.target.value })} />
                  <Select value={newRoute.channel} onValueChange={(v) => setNewRoute({ ...newRoute, channel: v })}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="email">Email</SelectItem>
                      <SelectItem value="webhook">Webhook</SelectItem>
                      <SelectItem value="slack">Slack</SelectItem>
                      <SelectItem value="teams">Teams</SelectItem>
                    </SelectContent>
                  </Select>
                  <Input placeholder="Destination" value={newRoute.destination} onChange={(e) => setNewRoute({ ...newRoute, destination: e.target.value })} />
                </div>
                <Button onClick={addRoute}><Plus className="h-4 w-4 mr-1" />Add Route</Button>
                <Table>
                  <TableHeader><TableRow><TableHead>Name</TableHead><TableHead>Severity</TableHead><TableHead>Group</TableHead><TableHead>Channel</TableHead><TableHead>Destination</TableHead></TableRow></TableHeader>
                  <TableBody>
                    {routes.map((r) => (
                      <TableRow key={r.id}>
                        <TableCell>{r.route_name}</TableCell>
                        <TableCell>{r.severity}</TableCell>
                        <TableCell>{r.group_name || "*"}</TableCell>
                        <TableCell>{r.channel}</TableCell>
                        <TableCell className="max-w-[260px] truncate">{r.destination}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="workflow">
            <Card>
              <CardHeader><CardTitle className="text-base">Acknowledgement & Ownership Workflow</CardTitle></CardHeader>
              <CardContent className="space-y-4">
                <Table>
                  <TableHeader><TableRow><TableHead>Summary</TableHead><TableHead>Severity</TableHead><TableHead>State</TableHead><TableHead>Owner</TableHead><TableHead>Ack</TableHead><TableHead className="text-right">Actions</TableHead></TableRow></TableHeader>
                  <TableBody>
                    {alerts.length === 0 ? <TableRow><TableCell colSpan={6} className="text-center py-6 text-muted-foreground">No alert events yet.</TableCell></TableRow> : alerts.map((a) => (
                      <TableRow key={a.id}>
                        <TableCell className="max-w-[280px] truncate">{a.summary}</TableCell>
                        <TableCell><Badge variant={a.severity === "critical" ? "destructive" : "secondary"}>{a.severity}</Badge></TableCell>
                        <TableCell>{a.state}</TableCell>
                        <TableCell>{userLabel(a.owner_user_id)}</TableCell>
                        <TableCell>{userLabel(a.acknowledged_by)}</TableCell>
                        <TableCell className="text-right space-x-1">
                          <Button size="sm" variant="outline" onClick={() => ackAlert(a.id)}>Acknowledge</Button>
                          <Button size="sm" variant="outline" onClick={() => claimAlert(a.id)}>Claim</Button>
                          <Button size="sm" onClick={() => resolveAlert(a.id)}>Resolve</Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
                <div>
                  <Label>Root cause / RCA Notes (applied when resolving)</Label>
                  <Textarea value={rootCause} onChange={(e) => setRootCause(e.target.value)} placeholder="Capture diagnosis and corrective action" />
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="audit">
            <Card>
              <CardHeader><CardTitle className="text-base flex items-center gap-2"><CheckCircle2 className="h-4 w-4" /> Alert State Machine Audit</CardTitle></CardHeader>
              <CardContent className="space-y-3">
                <Select value={selectedAlertId} onValueChange={setSelectedAlertId}>
                  <SelectTrigger><SelectValue placeholder="Select alert event" /></SelectTrigger>
                  <SelectContent>
                    {alerts.map((a) => (
                      <SelectItem key={a.id} value={a.id}>{a.summary.slice(0, 80)}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Table>
                  <TableHeader><TableRow><TableHead>Time</TableHead><TableHead>From</TableHead><TableHead>To</TableHead><TableHead>Reason</TableHead><TableHead>Changed By</TableHead></TableRow></TableHeader>
                  <TableBody>
                    {transitions.length === 0 ? (
                      <TableRow><TableCell colSpan={5} className="text-center py-6 text-muted-foreground">No transitions for selected alert.</TableCell></TableRow>
                    ) : transitions.map((t) => (
                      <TableRow key={t.id}>
                        <TableCell>{new Date(t.changed_at).toLocaleString()}</TableCell>
                        <TableCell>{t.from_state || "-"}</TableCell>
                        <TableCell>{t.to_state}</TableCell>
                        <TableCell>{t.transition_reason || "-"}</TableCell>
                        <TableCell>{userLabel(t.changed_by)}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </AppLayout>
  );
}
