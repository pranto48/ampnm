import { useState, useEffect } from "react";
import { supabase } from "@/integrations/supabase/client";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ListTree, RefreshCw, Search } from "lucide-react";

interface Process {
  id: string;
  hostname: string;
  process_name: string;
  pid: number | null;
  cpu_percent: number | null;
  memory_mb: number | null;
  status: string | null;
  process_type: string | null;
  recorded_at: string;
}

interface Props {
  hostname: string;
}

export function ProcessList({ hostname }: Props) {
  const [processes, setProcesses] = useState<Process[]>([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState("");
  const [tab, setTab] = useState("all");

  const fetch = async () => {
    if (!hostname) return;
    setLoading(true);
    const { data } = await supabase
      .from("host_processes")
      .select("*")
      .eq("hostname", hostname)
      .order("cpu_percent", { ascending: false })
      .limit(50);
    setProcesses((data ?? []) as Process[]);
    setLoading(false);
  };

  useEffect(() => { fetch(); }, [hostname]);

  const filtered = processes.filter((p) => {
    const matchSearch = !search || p.process_name.toLowerCase().includes(search.toLowerCase());
    const matchTab = tab === "all" || p.process_type === tab;
    return matchSearch && matchTab;
  });

  return (
    <Card>
      <CardHeader className="pb-2">
        <div className="flex items-center justify-between flex-wrap gap-2">
          <CardTitle className="text-base flex items-center gap-2">
            <ListTree className="h-4 w-4" /> Processes & Services
          </CardTitle>
          <div className="flex items-center gap-2">
            <div className="relative">
              <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
              <Input placeholder="Filter..." value={search} onChange={(e) => setSearch(e.target.value)} className="h-7 pl-8 text-xs w-[160px]" />
            </div>
            <Button variant="outline" size="sm" className="h-7" onClick={fetch} disabled={loading}>
              <RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} />
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <Tabs value={tab} onValueChange={setTab} className="mb-3">
          <TabsList className="h-8">
            <TabsTrigger value="all" className="text-xs h-6">All ({processes.length})</TabsTrigger>
            <TabsTrigger value="process" className="text-xs h-6">Processes ({processes.filter(p => p.process_type === "process").length})</TabsTrigger>
            <TabsTrigger value="service" className="text-xs h-6">Services ({processes.filter(p => p.process_type === "service").length})</TabsTrigger>
          </TabsList>
        </Tabs>

        {filtered.length === 0 ? (
          <p className="text-center text-muted-foreground text-sm py-6">
            {processes.length === 0 ? "No process data reported by agent yet." : "No matches."}
          </p>
        ) : (
          <div className="max-h-[400px] overflow-y-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="text-xs">Name</TableHead>
                  <TableHead className="text-xs text-right">PID</TableHead>
                  <TableHead className="text-xs text-right">CPU %</TableHead>
                  <TableHead className="text-xs text-right">Mem (MB)</TableHead>
                  <TableHead className="text-xs">Status</TableHead>
                  <TableHead className="text-xs">Type</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filtered.map((p) => (
                  <TableRow key={p.id}>
                    <TableCell className="text-xs font-medium">{p.process_name}</TableCell>
                    <TableCell className="text-xs text-right text-muted-foreground">{p.pid ?? "—"}</TableCell>
                    <TableCell className="text-xs text-right">
                      <span className={
                        (p.cpu_percent ?? 0) > 50 ? "text-red-400" :
                        (p.cpu_percent ?? 0) > 20 ? "text-amber-400" : "text-foreground"
                      }>
                        {p.cpu_percent?.toFixed(1) ?? "—"}
                      </span>
                    </TableCell>
                    <TableCell className="text-xs text-right">{p.memory_mb?.toFixed(1) ?? "—"}</TableCell>
                    <TableCell>
                      <Badge variant="outline" className={`text-[10px] ${p.status === "running" ? "border-emerald-500/30 text-emerald-400" : "border-muted"}`}>
                        {p.status || "—"}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Badge variant="secondary" className="text-[10px]">{p.process_type || "process"}</Badge>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
