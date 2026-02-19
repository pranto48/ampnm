import { useState, useEffect, useMemo } from "react";
import { supabase } from "@/integrations/supabase/client";
import { AppLayout } from "@/components/layout/AppLayout";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { FileText, RefreshCw, ArrowRight } from "lucide-react";
import { format } from "date-fns";
import { useToast } from "@/hooks/use-toast";
import type { Tables } from "@/integrations/supabase/types";

type StatusLog = Tables<"device_status_logs">;
type Device = Pick<Tables<"devices">, "id" | "name">;

const statusBadge = (s: string | null) => {
  switch (s) {
    case "online": return "bg-success text-success-foreground";
    case "warning": return "bg-warning text-warning-foreground";
    case "critical": return "bg-destructive text-destructive-foreground";
    case "offline": return "bg-muted text-muted-foreground";
    default: return "";
  }
};

export default function StatusLogsPage() {
  const { toast } = useToast();
  const [logs, setLogs] = useState<StatusLog[]>([]);
  const [devices, setDevices] = useState<Device[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedDevice, setSelectedDevice] = useState("all");

  useEffect(() => {
    supabase.from("devices").select("id, name").order("name").then(({ data }) => setDevices(data ?? []));
  }, []);

  const fetchLogs = async () => {
    setLoading(true);
    let query = supabase.from("device_status_logs").select("*").order("changed_at", { ascending: false }).limit(500);
    if (selectedDevice !== "all") query = query.eq("device_id", selectedDevice);
    const { data, error } = await query;
    if (error) toast({ title: "Error", description: error.message, variant: "destructive" });
    else setLogs(data ?? []);
    setLoading(false);
  };

  useEffect(() => { fetchLogs(); }, [selectedDevice]);

  const deviceMap = useMemo(() => new Map(devices.map((d) => [d.id, d.name])), [devices]);

  return (
    <AppLayout>
      <div className="space-y-4">
        <div className="flex items-center justify-between flex-wrap gap-2">
          <div className="flex items-center gap-3">
            <FileText className="h-7 w-7 text-primary" />
            <h1 className="text-2xl font-bold tracking-tight">Status Logs</h1>
            <Badge variant="secondary">{logs.length}</Badge>
          </div>
          <div className="flex items-center gap-2">
            <Select value={selectedDevice} onValueChange={setSelectedDevice}>
              <SelectTrigger className="w-[200px]"><SelectValue placeholder="All devices" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Devices</SelectItem>
                {devices.map((d) => <SelectItem key={d.id} value={d.id}>{d.name}</SelectItem>)}
              </SelectContent>
            </Select>
            <Button variant="outline" size="sm" onClick={fetchLogs} disabled={loading}>
              <RefreshCw className={`h-4 w-4 mr-1 ${loading ? "animate-spin" : ""}`} /> Refresh
            </Button>
          </div>
        </div>

        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Device</TableHead>
                  <TableHead>Status Change</TableHead>
                  <TableHead>Changed At</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {loading ? (
                  <TableRow><TableCell colSpan={3} className="text-center py-8 text-muted-foreground">Loading...</TableCell></TableRow>
                ) : logs.length === 0 ? (
                  <TableRow><TableCell colSpan={3} className="text-center py-8 text-muted-foreground">No status logs found.</TableCell></TableRow>
                ) : logs.map((l) => (
                  <TableRow key={l.id}>
                    <TableCell className="font-medium">{deviceMap.get(l.device_id) || l.device_id.slice(0, 8)}</TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Badge className={statusBadge(l.old_status)} variant="secondary">{l.old_status || "—"}</Badge>
                        <ArrowRight className="h-3 w-3 text-muted-foreground" />
                        <Badge className={statusBadge(l.new_status)} variant="secondary">{l.new_status}</Badge>
                      </div>
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">{format(new Date(l.changed_at), "PPp")}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
