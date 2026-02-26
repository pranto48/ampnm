import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Cpu, MemoryStick, HardDrive, Wifi, Activity } from "lucide-react";
import { AreaChart, Area, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from "recharts";
import { format } from "date-fns";

interface HistoryPoint {
  cpu_usage: number | null;
  memory_usage: number | null;
  memory_total: number | null;
  disk_usage: number | null;
  disk_total: number | null;
  network_in: number | null;
  network_out: number | null;
  gpu_usage: number | null;
  recorded_at: string;
  hostname?: string;
}

interface Props {
  history: HistoryPoint[];
  comparisonHistory?: HistoryPoint[];
  comparisonLabel?: string;
  primaryLabel?: string;
}

const tooltipStyle = {
  background: "hsl(220, 25%, 8%)",
  border: "1px solid hsl(220, 20%, 18%)",
  borderRadius: 8,
  fontSize: 12,
};

function formatBytes(bytes: number) {
  if (bytes >= 1073741824) return `${(bytes / 1073741824).toFixed(1)} GB`;
  if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
  return `${(bytes / 1024).toFixed(1)} KB`;
}

export function MetricsCharts({ history, comparisonHistory, comparisonLabel, primaryLabel }: Props) {
  const isComparing = !!comparisonHistory && comparisonHistory.length > 0;

  const chartData = history.map((h) => ({
    time: format(new Date(h.recorded_at), "HH:mm"),
    cpu: Number(h.cpu_usage) || 0,
    memory: h.memory_total ? Math.round((Number(h.memory_usage) / Number(h.memory_total)) * 100) : 0,
    disk: h.disk_total ? Math.round((Number(h.disk_usage) / Number(h.disk_total)) * 100) : 0,
    netIn: Number(h.network_in) || 0,
    netOut: Number(h.network_out) || 0,
    gpu: Number(h.gpu_usage) || 0,
  }));

  // Merge comparison data if comparing
  if (isComparing) {
    const compData = comparisonHistory!.map((h) => ({
      time: format(new Date(h.recorded_at), "HH:mm"),
      cpu2: Number(h.cpu_usage) || 0,
      memory2: h.memory_total ? Math.round((Number(h.memory_usage) / Number(h.memory_total)) * 100) : 0,
      disk2: h.disk_total ? Math.round((Number(h.disk_usage) / Number(h.disk_total)) * 100) : 0,
      netIn2: Number(h.network_in) || 0,
      netOut2: Number(h.network_out) || 0,
      gpu2: Number(h.gpu_usage) || 0,
    }));
    // Merge by index (simple approach)
    const maxLen = Math.max(chartData.length, compData.length);
    for (let i = 0; i < maxLen; i++) {
      if (i < compData.length) {
        if (i < chartData.length) {
          Object.assign(chartData[i], compData[i]);
        } else {
          chartData.push({ time: compData[i].time, cpu: 0, memory: 0, disk: 0, netIn: 0, netOut: 0, gpu: 0, ...compData[i] } as any);
        }
      }
    }
  }

  if (chartData.length === 0) {
    return (
      <Card>
        <CardContent className="py-12 text-center text-muted-foreground">
          <Activity className="h-10 w-10 mx-auto mb-3 opacity-40" />
          <p>No historical data available for this host yet.</p>
          <p className="text-xs mt-1">Data will appear once the agent starts reporting metrics.</p>
        </CardContent>
      </Card>
    );
  }

  const charts = [
    { key: "cpu", title: "CPU Usage", icon: Cpu, color: "hsl(190, 95%, 50%)", gradId: "cpuGrad" },
    { key: "memory", title: "Memory Usage", icon: MemoryStick, color: "hsl(260, 80%, 60%)", gradId: "memGrad" },
    { key: "disk", title: "Disk Usage", icon: HardDrive, color: "hsl(35, 90%, 55%)", gradId: "diskGrad" },
    { key: "gpu", title: "GPU Usage", icon: Activity, color: "hsl(330, 80%, 60%)", gradId: "gpuGrad" },
  ];

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {charts.map(({ key, title, icon: Icon, color, gradId }) => (
          <Card key={key}>
            <CardHeader className="pb-2">
              <CardTitle className="text-base flex items-center gap-2">
                <Icon className="h-4 w-4" /> {title}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <ResponsiveContainer width="100%" height={220}>
                <AreaChart data={chartData}>
                  <defs>
                    <linearGradient id={gradId} x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor={color} stopOpacity={0.3} />
                      <stop offset="95%" stopColor={color} stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke="hsl(220, 20%, 18%)" />
                  <XAxis dataKey="time" stroke="hsl(215, 15%, 55%)" fontSize={11} />
                  <YAxis stroke="hsl(215, 15%, 55%)" fontSize={11} unit="%" domain={[0, 100]} />
                  <Tooltip contentStyle={tooltipStyle} />
                  {isComparing && <Legend />}
                  <Area type="monotone" dataKey={key} name={primaryLabel || "Primary"} stroke={color} strokeWidth={2} fill={`url(#${gradId})`} />
                  {isComparing && (
                    <Area type="monotone" dataKey={`${key}2`} name={comparisonLabel || "Compare"} stroke="hsl(50, 90%, 55%)" strokeWidth={2} fill="none" strokeDasharray="5 5" />
                  )}
                </AreaChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Network I/O Chart - full width */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-base flex items-center gap-2">
            <Wifi className="h-4 w-4" /> Network I/O
          </CardTitle>
        </CardHeader>
        <CardContent>
          <ResponsiveContainer width="100%" height={200}>
            <LineChart data={chartData}>
              <CartesianGrid strokeDasharray="3 3" stroke="hsl(220, 20%, 18%)" />
              <XAxis dataKey="time" stroke="hsl(215, 15%, 55%)" fontSize={11} />
              <YAxis stroke="hsl(215, 15%, 55%)" fontSize={11} />
              <Tooltip contentStyle={tooltipStyle} formatter={(v: number) => [formatBytes(v)]} />
              <Legend />
              <Line type="monotone" dataKey="netIn" name={`In${primaryLabel ? ` (${primaryLabel})` : ""}`} stroke="hsl(140, 70%, 50%)" strokeWidth={2} dot={false} />
              <Line type="monotone" dataKey="netOut" name={`Out${primaryLabel ? ` (${primaryLabel})` : ""}`} stroke="hsl(0, 75%, 50%)" strokeWidth={2} dot={false} />
              {isComparing && (
                <>
                  <Line type="monotone" dataKey="netIn2" name={`In (${comparisonLabel})`} stroke="hsl(140, 70%, 50%)" strokeWidth={2} dot={false} strokeDasharray="5 5" />
                  <Line type="monotone" dataKey="netOut2" name={`Out (${comparisonLabel})`} stroke="hsl(0, 75%, 50%)" strokeWidth={2} dot={false} strokeDasharray="5 5" />
                </>
              )}
            </LineChart>
          </ResponsiveContainer>
        </CardContent>
      </Card>
    </div>
  );
}
