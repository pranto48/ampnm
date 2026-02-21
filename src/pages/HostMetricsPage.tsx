import { useState, useEffect, useCallback } from "react";
import { supabase } from "@/integrations/supabase/client";
import { AppLayout } from "@/components/layout/AppLayout";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Monitor, RefreshCw, GitCompareArrows } from "lucide-react";
import { useToast } from "@/hooks/use-toast";
import { HostSnapshotCards } from "@/components/host-metrics/HostSnapshotCards";
import { TimeRangeSelector } from "@/components/host-metrics/TimeRangeSelector";
import { MetricsCharts } from "@/components/host-metrics/MetricsCharts";
import { AlertSettingsDialog } from "@/components/host-metrics/AlertSettingsDialog";
import { ExportButton } from "@/components/host-metrics/ExportButton";

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
  const [selectedHost, setSelectedHost] = useState("");
  const [compareHost, setCompareHost] = useState("");
  const [comparing, setComparing] = useState(false);
  const [history, setHistory] = useState<HistoryPoint[]>([]);
  const [compHistory, setCompHistory] = useState<HistoryPoint[]>([]);
  const [loading, setLoading] = useState(false);
  const [timeRange, setTimeRange] = useState<{ hours?: number; from?: Date; to?: Date }>({ hours: 24 });

  useEffect(() => {
    supabase.from("host_metrics").select("*").order("hostname").then(({ data }) => {
      const h = (data ?? []) as HostMetric[];
      setHosts(h);
      if (h.length > 0 && !selectedHost) setSelectedHost(h[0].hostname);
    });
  }, []);

  const fetchHistory = useCallback(async (hostname: string): Promise<HistoryPoint[]> => {
    if (!hostname) return [];
    let query = supabase.from("host_metrics_history").select("*").eq("hostname", hostname).order("recorded_at", { ascending: true }).limit(1000);

    if (timeRange.hours) {
      const since = new Date(Date.now() - timeRange.hours * 3600000).toISOString();
      query = query.gte("recorded_at", since);
    } else if (timeRange.from && timeRange.to) {
      query = query.gte("recorded_at", timeRange.from.toISOString()).lte("recorded_at", timeRange.to.toISOString());
    }

    const { data, error } = await query;
    if (error) toast({ title: "Error", description: error.message, variant: "destructive" });
    return (data ?? []) as HistoryPoint[];
  }, [timeRange, toast]);

  const refresh = useCallback(async () => {
    setLoading(true);
    const [primary, comp] = await Promise.all([
      fetchHistory(selectedHost),
      comparing && compareHost ? fetchHistory(compareHost) : Promise.resolve([]),
    ]);
    setHistory(primary);
    setCompHistory(comp);
    setLoading(false);
  }, [selectedHost, compareHost, comparing, fetchHistory]);

  useEffect(() => { refresh(); }, [selectedHost, timeRange, comparing, compareHost]);

  const host = hosts.find((h) => h.hostname === selectedHost);

  return (
    <AppLayout>
      <div className="space-y-4">
        {/* Header */}
        <div className="flex items-center justify-between flex-wrap gap-3">
          <div className="flex items-center gap-3">
            <Monitor className="h-7 w-7 text-primary" />
            <h1 className="text-2xl font-bold tracking-tight">Host Metrics</h1>
          </div>
          <div className="flex items-center gap-2 flex-wrap">
            <AlertSettingsDialog hosts={hosts} />
            <ExportButton history={history} hostname={selectedHost} />
            <Button variant="outline" size="sm" onClick={() => { setComparing(!comparing); if (comparing) { setCompareHost(""); setCompHistory([]); } }} className={`gap-1.5 ${comparing ? "border-primary text-primary" : ""}`}>
              <GitCompareArrows className="h-4 w-4" /> Compare
            </Button>
          </div>
        </div>

        {/* Controls row */}
        <div className="flex items-center gap-3 flex-wrap">
          <Select value={selectedHost} onValueChange={setSelectedHost}>
            <SelectTrigger className="w-[200px]"><SelectValue placeholder="Select host" /></SelectTrigger>
            <SelectContent>
              {hosts.map((h) => (
                <SelectItem key={h.id} value={h.hostname}>{h.hostname}</SelectItem>
              ))}
            </SelectContent>
          </Select>

          {comparing && (
            <Select value={compareHost} onValueChange={setCompareHost}>
              <SelectTrigger className="w-[200px]"><SelectValue placeholder="Compare with..." /></SelectTrigger>
              <SelectContent>
                {hosts.filter(h => h.hostname !== selectedHost).map((h) => (
                  <SelectItem key={h.id} value={h.hostname}>{h.hostname}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}

          <TimeRangeSelector value={timeRange} onChange={setTimeRange} />

          <Button variant="outline" size="sm" onClick={refresh} disabled={loading}>
            <RefreshCw className={`h-4 w-4 mr-1 ${loading ? "animate-spin" : ""}`} /> Refresh
          </Button>
        </div>

        {/* Snapshot cards */}
        {host && <HostSnapshotCards host={host} />}

        {/* Charts */}
        <MetricsCharts
          history={history}
          comparisonHistory={comparing ? compHistory : undefined}
          primaryLabel={selectedHost}
          comparisonLabel={compareHost}
        />
      </div>
    </AppLayout>
  );
}
