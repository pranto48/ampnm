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
import { Mail, Plus, Trash2, RefreshCw, Save } from "lucide-react";
import { useToast } from "@/hooks/use-toast";
import { useAuth } from "@/hooks/useAuth";
import type { Tables } from "@/integrations/supabase/types";

type SmtpSettings = Tables<"smtp_settings">;
type Subscription = Tables<"device_email_subscriptions">;
type Device = Pick<Tables<"devices">, "id" | "name">;

export default function NotificationsPage() {
  const { toast } = useToast();
  const { user } = useAuth();

  // SMTP
  const [smtp, setSmtp] = useState<Partial<SmtpSettings>>({ enabled: false, smtp_host: "", smtp_port: 587, smtp_username: "", smtp_password: "", smtp_encryption: "tls", smtp_from_name: "AMPNM", smtp_from_email: "" });
  const [smtpLoading, setSmtpLoading] = useState(true);

  // Subscriptions
  const [subs, setSubs] = useState<Subscription[]>([]);
  const [devices, setDevices] = useState<Device[]>([]);
  const [newEmail, setNewEmail] = useState("");
  const [newDeviceId, setNewDeviceId] = useState("");

  useEffect(() => {
    (async () => {
      const { data } = await supabase.from("smtp_settings").select("*").maybeSingle();
      if (data) setSmtp(data);
      setSmtpLoading(false);
    })();
    supabase.from("devices").select("id, name").order("name").then(({ data }) => setDevices(data ?? []));
    fetchSubs();
  }, []);

  const fetchSubs = async () => {
    const { data } = await supabase.from("device_email_subscriptions").select("*").order("created_at", { ascending: false });
    setSubs(data ?? []);
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
      else { setSmtp(data); toast({ title: "SMTP settings created" }); }
    }
  };

  const addSub = async () => {
    if (!newEmail || !newDeviceId) return;
    const { error } = await supabase.from("device_email_subscriptions").insert({ email: newEmail, device_id: newDeviceId });
    if (error) toast({ title: "Error", description: error.message, variant: "destructive" });
    else { toast({ title: "Subscription added" }); setNewEmail(""); fetchSubs(); }
  };

  const deleteSub = async (id: string) => {
    await supabase.from("device_email_subscriptions").delete().eq("id", id);
    toast({ title: "Subscription removed" });
    fetchSubs();
  };

  const deviceName = (id: string) => devices.find((d) => d.id === id)?.name || id.slice(0, 8);

  return (
    <AppLayout>
      <div className="space-y-4">
        <div className="flex items-center gap-3">
          <Mail className="h-7 w-7 text-primary" />
          <h1 className="text-2xl font-bold tracking-tight">Email Notifications</h1>
        </div>

        <Tabs defaultValue="smtp">
          <TabsList><TabsTrigger value="smtp">SMTP Settings</TabsTrigger><TabsTrigger value="subscriptions">Subscriptions</TabsTrigger></TabsList>

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
        </Tabs>
      </div>
    </AppLayout>
  );
}
