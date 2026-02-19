import { useState, useEffect } from "react";
import { supabase } from "@/integrations/supabase/client";
import { AppLayout } from "@/components/layout/AppLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { Monitor, Cpu, HardDrive, MemoryStick, RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";
import { format } from "date-fns";
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from "recharts";
import { useToast } from "@/hooks/use-toast";
import type { Tables } from "@/integrations/supabase/types";

type Device = Tables<"devices">;
type PingResult = Tables<"device_ping_results">;

export default function HostMetricsPage() {
  const { toast } = useToast();
  const [devices, setDevices] = useState<Device[]>([]);
  const [selectedDevice, setSelectedDevice] = useState<string>("");
  const [results, setResults] = useState<PingResult[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    supabase.from("devices").select("*").order("name").then(({ data }) => {
      const devs = data ?? [];
      setDevices(devs);
      if (devs.length > 0) setSelectedDevice(devs[0].id);
    });
  }, []);

  const fetchMetrics = async () => {
    if (!selectedDevice) return;
    setLoading(true);
    const { data, error } = await supabase
      .from("device_ping_results")
      .select("*")
      .eq("device_id", selectedDevice)
      .order("checked_at", { ascending: true })
      .limit(200);
    if (error) toast({ title: "Error", description: error.message, variant: "destructive" });
    else setResults(data ?? []);
    setLoading(false);
  };

  useEffect(() => { fetchMetrics(); }, [selectedDevice]);

  const device = devices.find((d) => d.id === selectedDevice);
  const chartData = results.map((r) => ({
    time: format(new Date(r.checked_at), "HH:mm"),
    latency: Number(r.latency_ms) || 0,
    loss: Number(r.packet_loss) || 0,
  }));

  const avgLatency = results.length > 0
    ? Math.round(results.reduce((s, r) => s + (Number(r.latency_ms) || 0), 0) / results.length)
    : 0;
  const avgLoss = results.length > 0
    ? (results.reduce((s, r) => s + (Number(r.packet_loss) || 0), 0) / results.length).toFixed(1)
    : "0";

  return (
    <AppLayout>
      <div className="space-y-4">
        <div className="flex items-center justify-between flex-wrap gap-2">
          <div className="flex items-center gap-3">
            <Monitor className="h-7 w-7 text-primary" />
            <h1 className="text-2xl font-bold tracking-tight">Host Metrics</h1>
          </div>
          <div className="flex items-center gap-2">
            <Select value={selectedDevice} onValueChange={setSelectedDevice}>
              <SelectTrigger className="w-[220px]"><SelectValue placeholder="Select device" /></SelectTrigger>
              <SelectContent>
                {devices.map((d) => <SelectItem key={d.id} value={d.id}>{d.name}</SelectItem>)}
              </SelectContent>
            </Select>
            <Button variant="outline" size="sm" onClick={fetchMetrics} disabled={loading}>
              <RefreshCw className={`h-4 w-4 mr-1 ${loading ? "animate-spin" : ""}`} /> Refresh
            </Button>
          </div>
        </div>

        {/* Summary Cards */}
        {device && (
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <Card>
              <CardContent className="pt-4 pb-3 text-center">
                <p className="text-xs text-muted-foreground mb-1">Status</p>
                <Badge className={device.status === "online" ? "bg-success text-success-foreground" : device.status === "critical" ? "bg-destructive text-destructive-foreground" : ""}>{device.status || "unknown"}</Badge>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-4 pb-3 text-center">
                <p className="text-xs text-muted-foreground mb-1">Avg Latency</p>
                <p className="text-xl font-bold">{avgLatency}ms</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-4 pb-3 text-center">
                <p className="text-xs text-muted-foreground mb-1">Avg Packet Loss</p>
                <p className="text-xl font-bold">{avgLoss}%</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-4 pb-3 text-center">
                <p className="text-xs text-muted-foreground mb-1">Data Points</p>
                <p className="text-xl font-bold">{results.length}</p>
              </CardContent>
            </Card>
          </div>
        )}

        {/* Latency Chart */}
        <Card>
          <CardHeader><CardTitle className="text-base flex items-center gap-2"><Cpu className="h-4 w-4" /> Latency Over Time</CardTitle></CardHeader>
          <CardContent>
            {chartData.length === 0 ? (
              <p className="text-center text-muted-foreground py-8">No data available for this device.</p>
            ) : (
              <ResponsiveContainer width="100%" height={300}>
                <LineChart data={chartData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="hsl(220, 20%, 18%)" />
                  <XAxis dataKey="time" stroke="hsl(215, 15%, 55%)" fontSize={11} />
                  <YAxis stroke="hsl(215, 15%, 55%)" fontSize={11} unit="ms" />
                  <Tooltip contentStyle={{ background: "hsl(220, 25%, 8%)", border: "1px solid hsl(220, 20%, 18%)", borderRadius: 8 }} />
                  <Line type="monotone" dataKey="latency" stroke="hsl(190, 95%, 50%)" strokeWidth={2} dot={false} />
                </LineChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>

        {/* Packet Loss Chart */}
        <Card>
          <CardHeader><CardTitle className="text-base flex items-center gap-2"><HardDrive className="h-4 w-4" /> Packet Loss Over Time</CardTitle></CardHeader>
          <CardContent>
            {chartData.length === 0 ? (
              <p className="text-center text-muted-foreground py-8">No data available.</p>
            ) : (
              <ResponsiveContainer width="100%" height={200}>
                <LineChart data={chartData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="hsl(220, 20%, 18%)" />
                  <XAxis dataKey="time" stroke="hsl(215, 15%, 55%)" fontSize={11} />
                  <YAxis stroke="hsl(215, 15%, 55%)" fontSize={11} unit="%" />
                  <Tooltip contentStyle={{ background: "hsl(220, 25%, 8%)", border: "1px solid hsl(220, 20%, 18%)", borderRadius: 8 }} />
                  <Line type="monotone" dataKey="loss" stroke="hsl(0, 75%, 50%)" strokeWidth={2} dot={false} />
                </LineChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
