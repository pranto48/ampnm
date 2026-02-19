import { useEffect, useState, useCallback } from "react";
import { supabase } from "@/integrations/supabase/client";
import { AppLayout } from "@/components/layout/AppLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Wifi, WifiOff, AlertTriangle, AlertCircle, Activity, Zap, RefreshCw } from "lucide-react";
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip } from "recharts";
import { useAutoPing } from "@/hooks/useAutoPing";
import type { Tables } from "@/integrations/supabase/types";

interface StatusCounts {
  online: number;
  warning: number;
  critical: number;
  offline: number;
  unknown: number;
}

interface StatusLog {
  id: string;
  new_status: string;
  old_status: string | null;
  changed_at: string;
  device_id: string;
  device_name?: string;
}

type MapRow = Tables<"maps">;

export default function DashboardPage() {
  const [allDevices, setAllDevices] = useState<Tables<"devices">[]>([]);
  const [maps, setMaps] = useState<MapRow[]>([]);
  const [selectedMapId, setSelectedMapId] = useState<string>("all");
  const [counts, setCounts] = useState<StatusCounts>({ online: 0, warning: 0, critical: 0, offline: 0, unknown: 0 });
  const [logs, setLogs] = useState<StatusLog[]>([]);
  const [loading, setLoading] = useState(true);

  // Ping test
  const [pingHost, setPingHost] = useState("192.168.1.1");
  const [pinging, setPinging] = useState(false);
  const [pingResult, setPingResult] = useState<string | null>(null);
  const [isPingingAll, setIsPingingAll] = useState(false);
  const [autoPingEnabled, setAutoPingEnabled] = useState(true);

  const handleAutoPingComplete = useCallback(() => {
    loadData(false);
  }, []);

  useAutoPing(allDevices, autoPingEnabled, handleAutoPingComplete);

  // Load maps
  useEffect(() => {
    supabase.from("maps").select("*").order("name").then(({ data }) => setMaps(data ?? []));
  }, []);

  const loadData = async (showLoader = true) => {
    if (showLoader) setLoading(true);
    let devQuery = supabase.from("devices").select("*");
    if (selectedMapId !== "all") {
      devQuery = devQuery.eq("map_id", selectedMapId);
    }
    const { data: devices } = await devQuery;
    if (devices) setAllDevices(devices);

    if (devices) {
      const c: StatusCounts = { online: 0, warning: 0, critical: 0, offline: 0, unknown: 0 };
      devices.forEach((d) => {
        const s = (d.status || "unknown") as keyof StatusCounts;
        if (s in c) c[s]++;
        else c.unknown++;
      });
      setCounts(c);

      let logQuery = supabase
        .from("device_status_logs")
        .select("id, new_status, old_status, changed_at, device_id")
        .order("changed_at", { ascending: false })
        .limit(10);

      if (selectedMapId !== "all") {
        const deviceIds = devices.map(d => d.id);
        if (deviceIds.length > 0) {
          logQuery = logQuery.in("device_id", deviceIds);
        }
      }

      const { data: statusLogs } = await logQuery;
      if (statusLogs) {
        const deviceMap = new Map(devices.map((d) => [d.id, d.name]));
        setLogs(statusLogs.map((l) => ({ ...l, device_name: deviceMap.get(l.device_id) ?? "Unknown" })));
      }
    }
    if (showLoader) setLoading(false);
  };

  // Initial load + poll every 30s
  useEffect(() => {
    loadData();
    const interval = setInterval(() => loadData(false), 30000);
    return () => clearInterval(interval);
  }, [selectedMapId]);

  const handlePing = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!pingHost.trim()) return;
    setPinging(true);
    setPingResult(`Pinging ${pingHost}...`);

    try {
      const { data, error } = await supabase.functions.invoke("ping-target", {
        body: { host: pingHost.trim() },
      });
      if (error) {
        setPingResult(`Error: ${error.message}`);
      } else {
        const status = data?.status || "unknown";
        const latency = data?.latency_ms;
        setPingResult(
          status === "online"
            ? `✅ ${pingHost} is reachable — Latency: ${latency}ms`
            : `❌ ${pingHost} is unreachable`
        );
      }
    } catch (err: any) {
      setPingResult(`Failed: ${err.message}`);
    }
    setPinging(false);
  };

  const handlePingAll = async () => {
    setIsPingingAll(true);
    try {
      const { data: devicesWithIp } = await supabase
        .from("devices")
        .select("id")
        .not("ip_address", "is", null);
      const ids = (devicesWithIp ?? []).map(d => d.id);
      if (ids.length === 0) { setIsPingingAll(false); return; }
      await supabase.functions.invoke("ping-device", { body: { device_ids: ids } });
      loadData(false);
    } catch {} finally { setIsPingingAll(false); }
  };

  const total = counts.online + counts.warning + counts.critical + counts.offline + counts.unknown;
  const chartData = [
    { name: "Online", value: counts.online, color: "hsl(var(--success))" },
    { name: "Warning", value: counts.warning, color: "hsl(var(--warning))" },
    { name: "Critical", value: counts.critical, color: "hsl(var(--destructive))" },
    { name: "Offline", value: counts.offline, color: "hsl(var(--muted-foreground))" },
  ].filter((d) => d.value > 0);

  const statusCards = [
    { label: "Online", count: counts.online, icon: Wifi, className: "text-success border-success/30 bg-success/5" },
    { label: "Warning", count: counts.warning, icon: AlertTriangle, className: "text-warning border-warning/30 bg-warning/5" },
    { label: "Critical", count: counts.critical, icon: AlertCircle, className: "text-destructive border-destructive/30 bg-destructive/5" },
    { label: "Offline", count: counts.offline, icon: WifiOff, className: "text-muted-foreground border-border bg-muted/30" },
  ];

  const statusColor = (s: string) => {
    switch (s) {
      case "online": return "text-success";
      case "warning": return "text-warning";
      case "critical": return "text-destructive";
      default: return "text-muted-foreground";
    }
  };

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between flex-wrap gap-3">
          <div className="flex items-center gap-3">
            <Activity className="h-7 w-7 text-primary" />
            <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>
          </div>
          <div className="flex items-center gap-2">
            <div className="flex items-center gap-2 border rounded-md px-3 py-1.5 bg-muted/30">
              <Switch id="dash-auto-ping" checked={autoPingEnabled} onCheckedChange={setAutoPingEnabled} />
              <Label htmlFor="dash-auto-ping" className="text-xs font-medium cursor-pointer">Auto-Ping</Label>
            </div>
            <Button variant="outline" size="sm" onClick={handlePingAll} disabled={isPingingAll}>
              <RefreshCw className={`h-4 w-4 mr-1 ${isPingingAll ? "animate-spin" : ""}`} />
              {isPingingAll ? "Pinging..." : "Ping All Devices"}
            </Button>
            <Select value={selectedMapId} onValueChange={setSelectedMapId}>
              <SelectTrigger className="w-[200px]">
                <SelectValue placeholder="All Maps" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Maps</SelectItem>
                {maps.map((m) => (
                  <SelectItem key={m.id} value={m.id}>{m.name}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>

        {/* Status Cards */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {statusCards.map((s) => (
            <Card key={s.label} className={`border ${s.className}`}>
              <CardContent className="p-4 flex items-center gap-3">
                <s.icon className="h-8 w-8" />
                <div>
                  <p className="text-2xl font-bold">{loading ? "—" : s.count}</p>
                  <p className="text-xs font-medium opacity-80">{s.label}</p>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>

        <div className="grid md:grid-cols-2 gap-6">
          {/* Chart */}
          <Card>
            <CardHeader><CardTitle className="text-base">Device Status</CardTitle></CardHeader>
            <CardContent className="flex justify-center">
              {total === 0 ? (
                <p className="text-sm text-muted-foreground py-8">No devices configured yet.</p>
              ) : (
                <ResponsiveContainer width={220} height={220}>
                  <PieChart>
                    <Pie data={chartData} dataKey="value" cx="50%" cy="50%" innerRadius={50} outerRadius={90} paddingAngle={2}>
                      {chartData.map((entry, i) => (
                        <Cell key={i} fill={entry.color} />
                      ))}
                    </Pie>
                    <Tooltip />
                  </PieChart>
                </ResponsiveContainer>
              )}
            </CardContent>
          </Card>

          {/* Manual Ping Test */}
          <Card>
            <CardHeader><CardTitle className="text-base">Manual Ping Test</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              <form onSubmit={handlePing} className="flex gap-2">
                <Input
                  value={pingHost}
                  onChange={(e) => setPingHost(e.target.value)}
                  placeholder="Enter hostname or IP"
                  className="flex-1"
                />
                <Button type="submit" disabled={pinging} size="sm">
                  <Zap className="h-4 w-4 mr-1" />
                  {pinging ? "Pinging..." : "Ping"}
                </Button>
              </form>
              {pingResult && (
                <pre className="bg-background rounded-md border border-border p-3 text-xs font-mono whitespace-pre-wrap text-muted-foreground">
                  {pingResult}
                </pre>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Recent Activity */}
        <Card>
          <CardHeader><CardTitle className="text-base">Recent Activity</CardTitle></CardHeader>
          <CardContent>
            {logs.length === 0 ? (
              <p className="text-sm text-muted-foreground">No recent activity.</p>
            ) : (
              <div className="space-y-2 max-h-[280px] overflow-y-auto">
                {logs.map((log) => (
                  <div key={log.id} className="flex items-center justify-between text-sm border-b border-border pb-2 last:border-0">
                    <div>
                      <span className="font-medium text-foreground">{log.device_name}</span>
                      <span className="mx-1 text-muted-foreground">→</span>
                      <span className={statusColor(log.new_status)}>{log.new_status}</span>
                    </div>
                    <span className="text-xs text-muted-foreground">
                      {new Date(log.changed_at).toLocaleString()}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
