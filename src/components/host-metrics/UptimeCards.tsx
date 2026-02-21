import { Card, CardContent } from "@/components/ui/card";
import { Clock, ArrowUpCircle, Calendar, Server } from "lucide-react";
import { format, formatDistanceToNow } from "date-fns";

interface HostMetric {
  hostname: string;
  status: string;
  first_seen: string;
  last_seen: string;
  uptime_seconds?: number | null;
  boot_time?: string | null;
  os_version?: string | null;
}

interface Props {
  host: HostMetric;
  availabilityPct: number | null;
}

function formatUptime(seconds: number): string {
  const days = Math.floor(seconds / 86400);
  const hours = Math.floor((seconds % 86400) / 3600);
  const mins = Math.floor((seconds % 3600) / 60);
  if (days > 0) return `${days}d ${hours}h ${mins}m`;
  if (hours > 0) return `${hours}h ${mins}m`;
  return `${mins}m`;
}

export function UptimeCards({ host, availabilityPct }: Props) {
  const cards = [
    {
      icon: ArrowUpCircle,
      label: "System Uptime",
      value: host.uptime_seconds ? formatUptime(host.uptime_seconds) : "—",
      sub: host.boot_time ? `Boot: ${format(new Date(host.boot_time), "MMM d, HH:mm")}` : undefined,
      color: "text-emerald-400",
    },
    {
      icon: Clock,
      label: "Availability",
      value: availabilityPct != null ? `${availabilityPct.toFixed(1)}%` : "—",
      sub: "Based on check-in history",
      color: availabilityPct != null && availabilityPct >= 99 ? "text-emerald-400" : availabilityPct != null && availabilityPct >= 95 ? "text-amber-400" : "text-red-400",
    },
    {
      icon: Calendar,
      label: "First Seen",
      value: host.first_seen ? formatDistanceToNow(new Date(host.first_seen), { addSuffix: true }) : "—",
      sub: host.first_seen ? format(new Date(host.first_seen), "MMM d, yyyy") : undefined,
      color: "text-muted-foreground",
    },
    {
      icon: Server,
      label: "OS",
      value: host.os_version || "—",
      color: "text-muted-foreground",
    },
  ];

  return (
    <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
      {cards.map((c) => (
        <Card key={c.label} className="bg-card/50 border-border/50">
          <CardContent className="pt-4 pb-3">
            <div className="flex items-start gap-2">
              <c.icon className={`h-4 w-4 mt-0.5 ${c.color}`} />
              <div>
                <p className="text-xs text-muted-foreground">{c.label}</p>
                <p className={`text-lg font-bold ${c.color}`}>{c.value}</p>
                {c.sub && <p className="text-xs text-muted-foreground">{c.sub}</p>}
              </div>
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
