import { useState, useEffect } from "react";
import { supabase } from "@/integrations/supabase/client";
import { AppLayout } from "@/components/layout/AppLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { Monitor, Cpu, HardDrive, MemoryStick, RefreshCw, Wifi, Activity } from "lucide-react";
import { Button } from "@/components/ui/button";
import { format } from "date-fns";
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Area, AreaChart } from "recharts";
import { useToast } from "@/hooks/use-toast";

interface HostMetric {
  id: string;
  hostname: string;
  cpu_usage: number | null;
  memory_usage: number | null;
  memory_total: number | null;
  disk_usage: number | null;
  disk_total: number | null;
  network_in: number | null;
  network_out: number | null;
  gpu_usage: number | null;
  ip_address: string | null;
  status: string;
  last_seen: string;
}

interface HistoryPoint {
  id: string;
  hostname: string;
  cpu_usage: number | null;
  memory_usage: number | null;
  memory_total: number | null;
  disk_usage: number | null;
  disk_total: number | null;
  network_in: number | null;
  network_out: number | null;
  gpu_usage: number | null;
  recorded_at: string;
}

export default function HostMetricsPage() {
  const { toast } = useToast();
  const [hosts, setHosts] = useState<HostMetric[]>([]);
  const [selectedHost, setSelectedHost] = useState<string>("");
  const [history, setHistory] = useState<HistoryPoint[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    supabase.from("host_metrics").select("*").order("hostname").then(({ data }) => {
      const h = (data ?? []) as HostMetric[];
      setHosts(h);
      if (h.length > 0) setSelectedHost(h[0].hostname);
    });
  }, []);

  const fetchHistory = async () => {
    if (!selectedHost) return;
    setLoading(true);
    const { data, error } = await supabase
      .from("host_metrics_history")
      .select("*")
      .eq("hostname", selectedHost)
      .order("recorded_at", { ascending: true })
      .limit(500);
    if (error) toast({ title: "Error", description: error.message, variant: "destructive" });
    else setHistory((data ?? []) as HistoryPoint[]);
    setLoading(false);
  };

  useEffect(() => { fetchHistory(); }, [selectedHost]);

  const host = hosts.find((h) => h.hostname === selectedHost);

  const chartData = history.map((h) => ({
    time: format(new Date(h.recorded_at), "HH:mm"),
    cpu: Number(h.cpu_usage) || 0,
    memory: h.memory_total ? Math.round((Number(h.memory_usage) / Number(h.memory_total)) * 100) : 0,
    memoryRaw: Number(h.memory_usage) || 0,
    disk: h.disk_total ? Math.round((Number(h.disk_usage) / Number(h.disk_total)) * 100) : 0,
    diskRaw: Number(h.disk_usage) || 0,
    netIn: Number(h.network_in) || 0,
    netOut: Number(h.network_out) || 0,
    gpu: Number(h.gpu_usage) || 0,
  }));

  const formatBytes = (bytes: number) => {
    if (bytes >= 1073741824) return `${(bytes / 1073741824).toFixed(1)} GB`;
    if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
    return `${(bytes / 1024).toFixed(1)} KB`;
  };

  const tooltipStyle = {
    background: "hsl(220, 25%, 8%)",
    border: "1px solid hsl(220, 20%, 18%)",
    borderRadius: 8,
    fontSize: 12,
  };

  return (
    <AppLayout>
      <div className="space-y-4">
        <div className="flex items-center justify-between flex-wrap gap-2">
          <div className="flex items-center gap-3">
            <Monitor className="h-7 w-7 text-primary" />
            <h1 className="text-2xl font-bold tracking-tight">Host Metrics</h1>
          </div>
          <div className="flex items-center gap-2">
            <Select value={selectedHost} onValueChange={setSelectedHost}>
              <SelectTrigger className="w-[220px]"><SelectValue placeholder="Select host" /></SelectTrigger>
              <SelectContent>
                {hosts.map((h) => (
                  <SelectItem key={h.id} value={h.hostname}>{h.hostname}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Button variant="outline" size="sm" onClick={fetchHistory} disabled={loading}>
              <RefreshCw className={`h-4 w-4 mr-1 ${loading ? "animate-spin" : ""}`} /> Refresh
            </Button>
          </div>
        </div>

        {/* Current snapshot cards */}
        {host && (
          <div className="grid grid-cols-2 md:grid-cols-6 gap-3">
            <Card>
              <CardContent className="pt-4 pb-3 text-center">
                <p className="text-xs text-muted-foreground mb-1">Status</p>
                <Badge className={host.status === "online" ? "bg-success text-success-foreground" : "bg-destructive text-destructive-foreground"}>
                  {host.status}
                </Badge>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-4 pb-3 text-center">
                <p className="text-xs text-muted-foreground mb-1">CPU</p>
                <p className="text-xl font-bold">{host.cpu_usage?.toFixed(1) ?? "—"}%</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-4 pb-3 text-center">
                <p className="text-xs text-muted-foreground mb-1">Memory</p>
                <p className="text-xl font-bold">
                  {host.memory_total ? `${Math.round((Number(host.memory_usage) / Number(host.memory_total)) * 100)}%` : "—"}
                </p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-4 pb-3 text-center">
                <p className="text-xs text-muted-foreground mb-1">Disk</p>
                <p className="text-xl font-bold">
                  {host.disk_total ? `${Math.round((Number(host.disk_usage) / Number(host.disk_total)) * 100)}%` : "—"}
                </p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-4 pb-3 text-center">
                <p className="text-xs text-muted-foreground mb-1">GPU</p>
                <p className="text-xl font-bold">{host.gpu_usage?.toFixed(1) ?? "—"}%</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-4 pb-3 text-center">
                <p className="text-xs text-muted-foreground mb-1">Last Seen</p>
                <p className="text-sm font-medium">{format(new Date(host.last_seen), "HH:mm:ss")}</p>
              </CardContent>
            </Card>
          </div>
        )}

        {chartData.length === 0 ? (
          <Card>
            <CardContent className="py-12 text-center text-muted-foreground">
              <Activity className="h-10 w-10 mx-auto mb-3 opacity-40" />
              <p>No historical data available for this host yet.</p>
              <p className="text-xs mt-1">Data will appear once the Windows agent starts reporting metrics.</p>
            </CardContent>
          </Card>
        ) : (
          <>
            {/* CPU Chart */}
            <Card>
              <CardHeader className="pb-2">
                <CardTitle className="text-base flex items-center gap-2">
                  <Cpu className="h-4 w-4" /> CPU Usage Over Time
                </CardTitle>
              </CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={250}>
                  <AreaChart data={chartData}>
                    <defs>
                      <linearGradient id="cpuGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor="hsl(190, 95%, 50%)" stopOpacity={0.3} />
                        <stop offset="95%" stopColor="hsl(190, 95%, 50%)" stopOpacity={0} />
                      </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(220, 20%, 18%)" />
                    <XAxis dataKey="time" stroke="hsl(215, 15%, 55%)" fontSize={11} />
                    <YAxis stroke="hsl(215, 15%, 55%)" fontSize={11} unit="%" domain={[0, 100]} />
                    <Tooltip contentStyle={tooltipStyle} formatter={(v: number) => [`${v.toFixed(1)}%`, "CPU"]} />
                    <Area type="monotone" dataKey="cpu" stroke="hsl(190, 95%, 50%)" strokeWidth={2} fill="url(#cpuGrad)" />
                  </AreaChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>

            {/* Memory Chart */}
            <Card>
              <CardHeader className="pb-2">
                <CardTitle className="text-base flex items-center gap-2">
                  <MemoryStick className="h-4 w-4" /> Memory Usage Over Time
                </CardTitle>
              </CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={250}>
                  <AreaChart data={chartData}>
                    <defs>
                      <linearGradient id="memGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor="hsl(260, 80%, 60%)" stopOpacity={0.3} />
                        <stop offset="95%" stopColor="hsl(260, 80%, 60%)" stopOpacity={0} />
                      </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(220, 20%, 18%)" />
                    <XAxis dataKey="time" stroke="hsl(215, 15%, 55%)" fontSize={11} />
                    <YAxis stroke="hsl(215, 15%, 55%)" fontSize={11} unit="%" domain={[0, 100]} />
                    <Tooltip contentStyle={tooltipStyle} formatter={(v: number) => [`${v}%`, "Memory"]} />
                    <Area type="monotone" dataKey="memory" stroke="hsl(260, 80%, 60%)" strokeWidth={2} fill="url(#memGrad)" />
                  </AreaChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>

            {/* Disk Chart */}
            <Card>
              <CardHeader className="pb-2">
                <CardTitle className="text-base flex items-center gap-2">
                  <HardDrive className="h-4 w-4" /> Disk Usage Over Time
                </CardTitle>
              </CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={250}>
                  <AreaChart data={chartData}>
                    <defs>
                      <linearGradient id="diskGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor="hsl(35, 90%, 55%)" stopOpacity={0.3} />
                        <stop offset="95%" stopColor="hsl(35, 90%, 55%)" stopOpacity={0} />
                      </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(220, 20%, 18%)" />
                    <XAxis dataKey="time" stroke="hsl(215, 15%, 55%)" fontSize={11} />
                    <YAxis stroke="hsl(215, 15%, 55%)" fontSize={11} unit="%" domain={[0, 100]} />
                    <Tooltip contentStyle={tooltipStyle} formatter={(v: number) => [`${v}%`, "Disk"]} />
                    <Area type="monotone" dataKey="disk" stroke="hsl(35, 90%, 55%)" strokeWidth={2} fill="url(#diskGrad)" />
                  </AreaChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>

            {/* Network I/O Chart */}
            <Card>
              <CardHeader className="pb-2">
                <CardTitle className="text-base flex items-center gap-2">
                  <Wifi className="h-4 w-4" /> Network I/O Over Time
                </CardTitle>
              </CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={200}>
                  <LineChart data={chartData}>
                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(220, 20%, 18%)" />
                    <XAxis dataKey="time" stroke="hsl(215, 15%, 55%)" fontSize={11} />
                    <YAxis stroke="hsl(215, 15%, 55%)" fontSize={11} />
                    <Tooltip contentStyle={tooltipStyle} formatter={(v: number) => [formatBytes(v)]} />
                    <Line type="monotone" dataKey="netIn" name="In" stroke="hsl(140, 70%, 50%)" strokeWidth={2} dot={false} />
                    <Line type="monotone" dataKey="netOut" name="Out" stroke="hsl(0, 75%, 50%)" strokeWidth={2} dot={false} />
                  </LineChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>
          </>
        )}
      </div>
    </AppLayout>
  );
}
