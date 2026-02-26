import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { format } from "date-fns";

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

interface Props {
  host: HostMetric;
}

export function HostSnapshotCards({ host }: Props) {
  const memPct = host.memory_total
    ? Math.round((Number(host.memory_usage) / Number(host.memory_total)) * 100)
    : null;
  const diskPct = host.disk_total
    ? Math.round((Number(host.disk_usage) / Number(host.disk_total)) * 100)
    : null;

  const cards = [
    {
      label: "Status",
      render: (
        <Badge className={host.status === "online" ? "bg-emerald-500/20 text-emerald-400 border-emerald-500/30" : "bg-red-500/20 text-red-400 border-red-500/30"}>
          {host.status}
        </Badge>
      ),
    },
    { label: "CPU", value: host.cpu_usage != null ? `${host.cpu_usage.toFixed(1)}%` : "—", color: getColor(host.cpu_usage) },
    { label: "Memory", value: memPct != null ? `${memPct}%` : "—", color: getColor(memPct) },
    { label: "Disk", value: diskPct != null ? `${diskPct}%` : "—", color: getColor(diskPct) },
    { label: "GPU", value: host.gpu_usage != null ? `${host.gpu_usage.toFixed(1)}%` : "—", color: getColor(host.gpu_usage) },
    { label: "IP", value: host.ip_address || "—" },
    { label: "Last Seen", value: format(new Date(host.last_seen), "HH:mm:ss") },
  ];

  return (
    <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
      {cards.map((c) => (
        <Card key={c.label} className="bg-card/50 border-border/50">
          <CardContent className="pt-4 pb-3 text-center">
            <p className="text-xs text-muted-foreground mb-1">{c.label}</p>
            {"render" in c ? c.render : (
              <p className={`text-xl font-bold ${c.color || "text-foreground"}`}>{c.value}</p>
            )}
          </CardContent>
        </Card>
      ))}
    </div>
  );
}

function getColor(val: number | null | undefined): string {
  if (val == null) return "";
  if (val >= 95) return "text-red-400";
  if (val >= 80) return "text-amber-400";
  return "text-emerald-400";
}
