import { useState, useEffect, useMemo } from "react";
import { supabase } from "@/integrations/supabase/client";
import { AppLayout } from "@/components/layout/AppLayout";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { History, RefreshCw, CalendarIcon, Filter } from "lucide-react";
import { format } from "date-fns";
import { cn } from "@/lib/utils";
import { useToast } from "@/hooks/use-toast";
import type { Tables } from "@/integrations/supabase/types";

type PingResult = Tables<"device_ping_results"> & { device_name?: string };
type Device = Tables<"devices">;

const statusColor = (s: string) => {
  switch (s) {
    case "online": return "bg-success text-success-foreground";
    case "warning": return "bg-warning text-warning-foreground";
    case "critical": return "bg-destructive text-destructive-foreground";
    case "offline": return "bg-muted text-muted-foreground";
    default: return "";
  }
};

export default function HistoryPage() {
  const { toast } = useToast();
  const [results, setResults] = useState<PingResult[]>([]);
  const [devices, setDevices] = useState<Device[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedDevice, setSelectedDevice] = useState<string>("all");
  const [startDate, setStartDate] = useState<Date | undefined>(undefined);
  const [endDate, setEndDate] = useState<Date | undefined>(undefined);

  // Fetch devices for filter dropdown
  useEffect(() => {
    supabase.from("devices").select("id, name").order("name").then(({ data }) => {
      setDevices((data as Device[]) ?? []);
    });
  }, []);

  const fetchResults = async () => {
    setLoading(true);
    let query = supabase
      .from("device_ping_results")
      .select("*")
      .order("checked_at", { ascending: false })
      .limit(500);

    if (selectedDevice !== "all") {
      query = query.eq("device_id", selectedDevice);
    }
    if (startDate) {
      query = query.gte("checked_at", startDate.toISOString());
    }
    if (endDate) {
      const end = new Date(endDate);
      end.setHours(23, 59, 59, 999);
      query = query.lte("checked_at", end.toISOString());
    }

    const { data, error } = await query;
    if (error) {
      toast({ title: "Error loading history", description: error.message, variant: "destructive" });
    } else {
      setResults(data ?? []);
    }
    setLoading(false);
  };

  useEffect(() => { fetchResults(); }, [selectedDevice, startDate, endDate]);

  // Map device names
  const deviceMap = useMemo(() => {
    const m = new Map<string, string>();
    devices.forEach((d) => m.set(d.id, d.name));
    return m;
  }, [devices]);

  const clearFilters = () => {
    setSelectedDevice("all");
    setStartDate(undefined);
    setEndDate(undefined);
  };

  return (
    <AppLayout>
      <div className="space-y-4">
        <div className="flex items-center justify-between flex-wrap gap-2">
          <div className="flex items-center gap-3">
            <History className="h-7 w-7 text-primary" />
            <h1 className="text-2xl font-bold tracking-tight">Ping History</h1>
            <Badge variant="secondary">{results.length}</Badge>
          </div>
          <Button variant="outline" size="sm" onClick={fetchResults} disabled={loading}>
            <RefreshCw className={`h-4 w-4 mr-1 ${loading ? "animate-spin" : ""}`} />
            Refresh
          </Button>
        </div>

        {/* Filters */}
        <Card>
          <CardContent className="flex flex-wrap items-end gap-4 p-4">
            <div className="space-y-1">
              <label className="text-xs text-muted-foreground">Device</label>
              <Select value={selectedDevice} onValueChange={setSelectedDevice}>
                <SelectTrigger className="w-[200px]"><SelectValue placeholder="All devices" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Devices</SelectItem>
                  {devices.map((d) => (
                    <SelectItem key={d.id} value={d.id}>{d.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1">
              <label className="text-xs text-muted-foreground">From</label>
              <Popover>
                <PopoverTrigger asChild>
                  <Button variant="outline" className={cn("w-[160px] justify-start text-left font-normal", !startDate && "text-muted-foreground")}>
                    <CalendarIcon className="mr-2 h-4 w-4" />
                    {startDate ? format(startDate, "PP") : "Start date"}
                  </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                  <Calendar mode="single" selected={startDate} onSelect={setStartDate} initialFocus className={cn("p-3 pointer-events-auto")} />
                </PopoverContent>
              </Popover>
            </div>

            <div className="space-y-1">
              <label className="text-xs text-muted-foreground">To</label>
              <Popover>
                <PopoverTrigger asChild>
                  <Button variant="outline" className={cn("w-[160px] justify-start text-left font-normal", !endDate && "text-muted-foreground")}>
                    <CalendarIcon className="mr-2 h-4 w-4" />
                    {endDate ? format(endDate, "PP") : "End date"}
                  </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                  <Calendar mode="single" selected={endDate} onSelect={setEndDate} initialFocus className={cn("p-3 pointer-events-auto")} />
                </PopoverContent>
              </Popover>
            </div>

            {(selectedDevice !== "all" || startDate || endDate) && (
              <Button variant="ghost" size="sm" onClick={clearFilters}>
                <Filter className="h-4 w-4 mr-1" /> Clear
              </Button>
            )}
          </CardContent>
        </Card>

        {/* Results Table */}
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Device</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Latency</TableHead>
                  <TableHead>Packet Loss</TableHead>
                  <TableHead>Checked At</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {loading ? (
                  <TableRow>
                    <TableCell colSpan={5} className="text-center py-8 text-muted-foreground">Loading history...</TableCell>
                  </TableRow>
                ) : results.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={5} className="text-center py-8 text-muted-foreground">No ping results found.</TableCell>
                  </TableRow>
                ) : (
                  results.map((r) => (
                    <TableRow key={r.id}>
                      <TableCell className="font-medium">{deviceMap.get(r.device_id) || r.device_id.slice(0, 8)}</TableCell>
                      <TableCell>
                        <Badge className={statusColor(r.status)} variant="secondary">{r.status}</Badge>
                      </TableCell>
                      <TableCell className="font-mono text-sm">{r.latency_ms != null ? `${r.latency_ms}ms` : "—"}</TableCell>
                      <TableCell className="font-mono text-sm">{r.packet_loss != null ? `${r.packet_loss}%` : "—"}</TableCell>
                      <TableCell className="text-sm text-muted-foreground">
                        {format(new Date(r.checked_at), "PPp")}
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
