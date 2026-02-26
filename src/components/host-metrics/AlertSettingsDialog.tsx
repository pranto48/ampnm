import { useState, useEffect } from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Bell, Shield, Trash2 } from "lucide-react";
import { supabase } from "@/integrations/supabase/client";
import { useToast } from "@/hooks/use-toast";

interface HostMetric {
  hostname: string;
}

interface Props {
  hosts: HostMetric[];
}

export function AlertSettingsDialog({ hosts }: Props) {
  const { toast } = useToast();
  const [open, setOpen] = useState(false);

  // Global settings
  const [global, setGlobal] = useState({
    enabled: true,
    cpu_warning_threshold: 80, cpu_critical_threshold: 95,
    memory_warning_threshold: 80, memory_critical_threshold: 95,
    disk_warning_threshold: 80, disk_critical_threshold: 95,
    gpu_warning_threshold: 80, gpu_critical_threshold: 95,
    cooldown_minutes: 30,
  });

  // Per-host overrides
  const [overrides, setOverrides] = useState<any[]>([]);

  useEffect(() => {
    if (!open) return;
    loadSettings();
  }, [open]);

  const loadSettings = async () => {
    const { data: user } = await supabase.auth.getUser();
    if (!user.user) return;

    const { data: gs } = await supabase
      .from("host_alert_settings")
      .select("*")
      .eq("user_id", user.user.id)
      .maybeSingle();
    if (gs) setGlobal(gs as any);

    const { data: ov } = await supabase.from("host_alert_overrides").select("*").order("hostname");
    if (ov) setOverrides(ov);
  };

  const saveGlobal = async () => {
    const { data: user } = await supabase.auth.getUser();
    if (!user.user) return;

    const { error } = await supabase.from("host_alert_settings").upsert({
      user_id: user.user.id,
      ...global,
    }, { onConflict: "user_id" });

    if (error) toast({ title: "Error", description: error.message, variant: "destructive" });
    else toast({ title: "Global alert settings saved" });
  };

  const saveOverride = async (ov: any) => {
    const { error } = await supabase.from("host_alert_overrides").upsert(ov, { onConflict: "hostname" });
    if (error) toast({ title: "Error", description: error.message, variant: "destructive" });
    else {
      toast({ title: `Override saved for ${ov.hostname}` });
      loadSettings();
    }
  };

  const deleteOverride = async (hostname: string) => {
    await supabase.from("host_alert_overrides").delete().eq("hostname", hostname);
    toast({ title: `Override removed for ${hostname}` });
    loadSettings();
  };

  const addOverride = (hostname: string) => {
    if (overrides.find(o => o.hostname === hostname)) return;
    setOverrides([...overrides, {
      hostname,
      enabled: true,
      cpu_warning: 80, cpu_critical: 95,
      memory_warning: 80, memory_critical: 95,
      disk_warning: 85, disk_critical: 95,
      gpu_warning: 80, gpu_critical: 95,
      status_delay_seconds: 300,
    }]);
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button variant="outline" size="sm" className="gap-1.5">
          <Bell className="h-4 w-4" /> Alert Settings
        </Button>
      </DialogTrigger>
      <DialogContent className="max-w-2xl max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Shield className="h-5 w-5" /> Alert Threshold Settings
          </DialogTitle>
        </DialogHeader>

        <Tabs defaultValue="global">
          <TabsList className="grid w-full grid-cols-2">
            <TabsTrigger value="global">Global Thresholds</TabsTrigger>
            <TabsTrigger value="overrides">Per-Host Overrides</TabsTrigger>
          </TabsList>

          <TabsContent value="global" className="space-y-4 mt-4">
            <div className="flex items-center justify-between">
              <Label>Alerts Enabled</Label>
              <Switch checked={global.enabled} onCheckedChange={(v) => setGlobal({ ...global, enabled: v })} />
            </div>

            {[
              { label: "CPU", wKey: "cpu_warning_threshold", cKey: "cpu_critical_threshold" },
              { label: "Memory", wKey: "memory_warning_threshold", cKey: "memory_critical_threshold" },
              { label: "Disk", wKey: "disk_warning_threshold", cKey: "disk_critical_threshold" },
              { label: "GPU", wKey: "gpu_warning_threshold", cKey: "gpu_critical_threshold" },
            ].map(({ label, wKey, cKey }) => (
              <div key={label} className="grid grid-cols-3 gap-3 items-center">
                <Label className="text-sm">{label}</Label>
                <div>
                  <Label className="text-xs text-amber-400">Warning %</Label>
                  <Input type="number" value={(global as any)[wKey]} onChange={(e) => setGlobal({ ...global, [wKey]: Number(e.target.value) })} />
                </div>
                <div>
                  <Label className="text-xs text-red-400">Critical %</Label>
                  <Input type="number" value={(global as any)[cKey]} onChange={(e) => setGlobal({ ...global, [cKey]: Number(e.target.value) })} />
                </div>
              </div>
            ))}

            <div className="grid grid-cols-3 gap-3 items-center">
              <Label className="text-sm">Cooldown</Label>
              <div className="col-span-2">
                <Label className="text-xs text-muted-foreground">Minutes between repeat alerts</Label>
                <Input type="number" value={global.cooldown_minutes} onChange={(e) => setGlobal({ ...global, cooldown_minutes: Number(e.target.value) })} />
              </div>
            </div>

            <Button onClick={saveGlobal} className="w-full">Save Global Settings</Button>
          </TabsContent>

          <TabsContent value="overrides" className="space-y-4 mt-4">
            <div className="flex gap-2 flex-wrap">
              {hosts.filter(h => !overrides.find(o => o.hostname === h.hostname)).map((h) => (
                <Button key={h.hostname} size="sm" variant="outline" onClick={() => addOverride(h.hostname)} className="text-xs">
                  + {h.hostname}
                </Button>
              ))}
            </div>

            {overrides.map((ov, idx) => (
              <div key={ov.hostname} className="border border-border rounded-lg p-3 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="font-medium text-sm">{ov.hostname}</span>
                  <div className="flex items-center gap-2">
                    <Switch checked={ov.enabled} onCheckedChange={(v) => {
                      const next = [...overrides];
                      next[idx] = { ...ov, enabled: v };
                      setOverrides(next);
                    }} />
                    <Button size="sm" variant="ghost" onClick={() => deleteOverride(ov.hostname)}>
                      <Trash2 className="h-3.5 w-3.5 text-destructive" />
                    </Button>
                  </div>
                </div>
                <div className="grid grid-cols-4 gap-2 text-xs">
                  {["cpu", "memory", "disk", "gpu"].map((metric) => (
                    <div key={metric}>
                      <Label className="text-xs capitalize">{metric}</Label>
                      <div className="flex gap-1">
                        <Input type="number" className="h-7 text-xs" placeholder="W" value={ov[`${metric}_warning`]}
                          onChange={(e) => { const next = [...overrides]; next[idx] = { ...ov, [`${metric}_warning`]: Number(e.target.value) }; setOverrides(next); }} />
                        <Input type="number" className="h-7 text-xs" placeholder="C" value={ov[`${metric}_critical`]}
                          onChange={(e) => { const next = [...overrides]; next[idx] = { ...ov, [`${metric}_critical`]: Number(e.target.value) }; setOverrides(next); }} />
                      </div>
                    </div>
                  ))}
                </div>
                <div className="flex gap-2 items-center">
                  <Label className="text-xs">Status delay (s)</Label>
                  <Input type="number" className="h-7 text-xs w-24" value={ov.status_delay_seconds ?? ""}
                    onChange={(e) => { const next = [...overrides]; next[idx] = { ...ov, status_delay_seconds: e.target.value ? Number(e.target.value) : null }; setOverrides(next); }} />
                  <Button size="sm" variant="secondary" className="text-xs ml-auto" onClick={() => saveOverride(ov)}>Save</Button>
                </div>
              </div>
            ))}
          </TabsContent>
        </Tabs>
      </DialogContent>
    </Dialog>
  );
}
