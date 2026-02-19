import { useEffect, useState } from "react";
import { supabase } from "@/integrations/supabase/client";
import { AppLayout } from "@/components/layout/AppLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Wifi, WifiOff, AlertTriangle, AlertCircle, Activity } from "lucide-react";
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip } from "recharts";

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

export default function DashboardPage() {
  const [counts, setCounts] = useState<StatusCounts>({ online: 0, warning: 0, critical: 0, offline: 0, unknown: 0 });
  const [logs, setLogs] = useState<StatusLog[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const load = async () => {
      const { data: devices } = await supabase.from("devices").select("status, name, id");
      if (devices) {
        const c: StatusCounts = { online: 0, warning: 0, critical: 0, offline: 0, unknown: 0 };
        devices.forEach((d) => {
          const s = (d.status || "unknown") as keyof StatusCounts;
          if (s in c) c[s]++;
          else c.unknown++;
        });
        setCounts(c);
      }

      const { data: statusLogs } = await supabase
        .from("device_status_logs")
        .select("id, new_status, old_status, changed_at, device_id")
        .order("changed_at", { ascending: false })
        .limit(10);

      if (statusLogs && devices) {
        const deviceMap = new Map(devices?.map((d) => [d.id, d.name]) ?? []);
        setLogs(statusLogs.map((l) => ({ ...l, device_name: deviceMap.get(l.device_id) ?? "Unknown" })));
      }
      setLoading(false);
    };
    load();
  }, []);

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
        <div className="flex items-center gap-3">
          <Activity className="h-7 w-7 text-primary" />
          <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>
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
      </div>
    </AppLayout>
  );
}
