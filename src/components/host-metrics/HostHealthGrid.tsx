import { useState, useEffect } from "react";
import { supabase } from "@/integrations/supabase/client";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import { Activity, Cpu, MemoryStick, HardDrive, Laptop, Server } from "lucide-react";
import { formatDistanceToNow } from "date-fns";

interface HostSummary {
  id: string;
  hostname: string;
  ip_address: string | null;
  status: string;
  cpu_usage: number | null;
  memory_usage: number | null;
  memory_total: number | null;
  disk_usage: number | null;
  disk_total: number | null;
  gpu_usage: number | null;
  last_seen: string;
  first_seen: string;
  uptime_seconds?: number | null;
  os_version?: string | null;
  platform?: string | null;
  agent_platform?: string | null;
  load_average?: number | null;
  temperature_celsius?: number | null;
}

interface HostWithAvailability extends HostSummary {
  availability: number | null;
}

interface Props {
  hosts: HostSummary[];
  onSelectHost: (hostname: string) => void;
  selectedHost: string;
}

function calcHealthColor(availability: number | null, status: string) {
  if (status === "offline") return { bg: "bg-red-500/15", border: "border-red-500/40", ring: "ring-red-500/20", text: "text-red-400", dot: "bg-red-500" };
  if (availability == null) return { bg: "bg-muted/30", border: "border-border", ring: "", text: "text-muted-foreground", dot: "bg-muted-foreground" };
  if (availability >= 99.5) return { bg: "bg-emerald-500/10", border: "border-emerald-500/30", ring: "ring-emerald-500/10", text: "text-emerald-400", dot: "bg-emerald-500" };
  if (availability >= 95) return { bg: "bg-amber-500/10", border: "border-amber-500/30", ring: "ring-amber-500/10", text: "text-amber-400", dot: "bg-amber-500" };
  return { bg: "bg-red-500/10", border: "border-red-500/30", ring: "ring-red-500/10", text: "text-red-400", dot: "bg-red-500" };
}

function MetricBar({ label, value, icon: Icon, color }: { label: string; value: number | null; icon: any; color: string }) {
  const pct = value ?? 0;
  const barColor = pct >= 95 ? "bg-red-500" : pct >= 80 ? "bg-amber-500" : "bg-emerald-500";
  return (
    <div className="flex items-center gap-2">
      <Icon className={`h-3 w-3 ${color} shrink-0`} />
      <div className="flex-1 min-w-0">
        <div className="h-1.5 rounded-full bg-muted overflow-hidden">
          <div className={`h-full rounded-full transition-all ${barColor}`} style={{ width: `${Math.min(pct, 100)}%` }} />
        </div>
      </div>
      <span className="text-[10px] text-muted-foreground w-8 text-right">{value != null ? `${Math.round(pct)}%` : "—"}</span>
    </div>
  );
}

export function HostHealthGrid({ hosts, onSelectHost, selectedHost }: Props) {
  const [hostAvailability, setHostAvailability] = useState<Record<string, number | null>>({});
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (hosts.length === 0) return;
    const calcAll = async () => {
      setLoading(true);
      const since = new Date(Date.now() - 24 * 3600000).toISOString();
      const results: Record<string, number | null> = {};

      // Fetch recent history for all hosts in parallel (batch by hostname)
      await Promise.all(
        hosts.map(async (h) => {
          const { data } = await supabase
            .from("host_metrics_history")
            .select("recorded_at")
            .eq("hostname", h.hostname)
            .gte("recorded_at", since)
            .order("recorded_at", { ascending: true })
            .limit(500);

          const points = data ?? [];
          if (points.length < 2) {
            results[h.hostname] = null;
            return;
          }
          const first = new Date(points[0].recorded_at).getTime();
          const last = new Date(points[points.length - 1].recorded_at).getTime();
          const span = (last - first) / 60000;
          if (span < 1) { results[h.hostname] = null; return; }
          let downtime = 0;
          for (let i = 1; i < points.length; i++) {
            const gap = (new Date(points[i].recorded_at).getTime() - new Date(points[i - 1].recorded_at).getTime()) / 60000;
            if (gap > 5) downtime += gap - 1;
          }
          results[h.hostname] = Math.max(0, Math.min(100, ((span - downtime) / span) * 100));
        })
      );

      setHostAvailability(results);
      setLoading(false);
    };
    calcAll();
  }, [hosts]);

  if (hosts.length === 0) {
    return (
      <Card>
        <CardContent className="py-8 text-center text-muted-foreground">
          <Server className="h-8 w-8 mx-auto mb-2 opacity-40" />
          <p className="text-sm">No monitored hosts found.</p>
        </CardContent>
      </Card>
    );
  }

  // Summary counts
  const onlineCount = hosts.filter(h => h.status === "online").length;
  const offlineCount = hosts.filter(h => h.status !== "online").length;
  const avgAvail = Object.values(hostAvailability).filter((v): v is number => v != null);
  const overallAvail = avgAvail.length > 0 ? avgAvail.reduce((a, b) => a + b, 0) / avgAvail.length : null;

  return (
    <div className="space-y-3">
      {/* Summary bar */}
      <div className="flex items-center gap-4 flex-wrap">
        <div className="flex items-center gap-2">
          <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse" />
          <span className="text-sm font-medium">{onlineCount} Online</span>
        </div>
        <div className="flex items-center gap-2">
          <span className="w-2.5 h-2.5 rounded-full bg-red-500" />
          <span className="text-sm font-medium">{offlineCount} Offline</span>
        </div>
        {overallAvail != null && (
          <Badge variant="outline" className={`text-xs ${overallAvail >= 99 ? "border-emerald-500/30 text-emerald-400" : overallAvail >= 95 ? "border-amber-500/30 text-amber-400" : "border-red-500/30 text-red-400"}`}>
            Fleet Avg: {overallAvail.toFixed(1)}%
          </Badge>
        )}
        {loading && <span className="text-xs text-muted-foreground animate-pulse">Calculating availability…</span>}
      </div>

      {/* Host cards grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
        {hosts.map((h) => {
          const avail = hostAvailability[h.hostname] ?? null;
          const colors = calcHealthColor(avail, h.status);
          const isSelected = h.hostname === selectedHost;
          const memPct = h.memory_total ? Math.round((Number(h.memory_usage) / Number(h.memory_total)) * 100) : null;
          const diskPct = h.disk_total ? Math.round((Number(h.disk_usage) / Number(h.disk_total)) * 100) : null;

          return (
            <Card
              key={h.id}
              className={`cursor-pointer transition-all hover:scale-[1.02] ${colors.bg} ${colors.border} ${isSelected ? "ring-2 ring-primary" : ""}`}
              onClick={() => onSelectHost(h.hostname)}
            >
              <CardContent className="pt-3 pb-3 space-y-2.5">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2 min-w-0">
                    <span className={`w-2 h-2 rounded-full shrink-0 ${colors.dot} ${h.status === "online" ? "animate-pulse" : ""}`} />
                    <span className="font-medium text-sm truncate">{h.hostname}</span>
                  </div>
                  <span className={`text-lg font-bold ${colors.text}`}>
                    {avail != null ? `${avail.toFixed(1)}%` : "—"}
                  </span>
                </div>

                {(h.ip_address || h.agent_platform || h.platform) && (
                  <div className="flex items-center justify-between gap-2 text-[10px] text-muted-foreground">
                    <p className="truncate">{h.ip_address || "No IP reported"}</p>
                    {(h.agent_platform || h.platform) && (
                      <span className="inline-flex items-center gap-1 rounded-full border border-border/60 px-1.5 py-0.5 uppercase tracking-wide">
                        <Laptop className="h-2.5 w-2.5" />
                        {h.agent_platform || h.platform}
                      </span>
                    )}
                  </div>
                )}

                <div className="space-y-1.5">
                  <MetricBar label="CPU" value={h.cpu_usage ? Number(h.cpu_usage) : null} icon={Cpu} color="text-sky-400" />
                  <MetricBar label="MEM" value={memPct} icon={MemoryStick} color="text-purple-400" />
                  <MetricBar label="DSK" value={diskPct} icon={HardDrive} color="text-amber-400" />
                </div>

                <div className="flex items-center justify-between text-[10px] text-muted-foreground">
                  <span>{h.status}</span>
                  <span>{formatDistanceToNow(new Date(h.last_seen), { addSuffix: true })}</span>
                </div>

                {(h.load_average != null || h.temperature_celsius != null) && (
                  <div className="flex items-center justify-between gap-2 text-[10px] text-muted-foreground">
                    <span>Load: {h.load_average != null ? h.load_average.toFixed(2) : "—"}</span>
                    <span>Temp: {h.temperature_celsius != null ? `${h.temperature_celsius.toFixed(1)}°C` : "—"}</span>
                  </div>
                )}
              </CardContent>
            </Card>
          );
        })}
      </div>
    </div>
  );
}
