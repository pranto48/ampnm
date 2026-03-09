import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import type { Tables } from "@/integrations/supabase/types";

type Device = Tables<"devices">;

interface StatusSummaryBarProps {
  devices: Device[];
  activeFilter?: string;
  onFilterChange?: (status: string) => void;
}

export function StatusSummaryBar({ devices, activeFilter = "all", onFilterChange }: StatusSummaryBarProps) {
  const counts = {
    online: devices.filter(d => d.status === "online").length,
    warning: devices.filter(d => d.status === "warning").length,
    critical: devices.filter(d => d.status === "critical").length,
    offline: devices.filter(d => d.status === "offline").length,
    unknown: devices.filter(d => !d.status || d.status === "unknown").length,
  };

  const total = devices.length;

  const handleClick = (status: string) => {
    if (!onFilterChange) return;
    onFilterChange(activeFilter === status ? "all" : status);
  };

  const badgeClass = (status: string, base: string) =>
    `cursor-pointer transition-all ${base} ${activeFilter === status ? "ring-2 ring-primary ring-offset-1" : "opacity-80 hover:opacity-100"}`;

  return (
    <Card className="p-4 mb-4">
      <div className="flex items-center gap-3 flex-wrap">
        <span className="text-sm font-medium text-muted-foreground">Status Summary:</span>
        <Badge variant="outline" className={badgeClass("online", "border-green-200 bg-green-50 text-green-700")} onClick={() => handleClick("online")}>
          Online: {counts.online}
        </Badge>
        <Badge variant="outline" className={badgeClass("warning", "border-yellow-200 bg-yellow-50 text-yellow-700")} onClick={() => handleClick("warning")}>
          Warning: {counts.warning}
        </Badge>
        <Badge variant="destructive" className={badgeClass("critical", "")} onClick={() => handleClick("critical")}>
          Critical: {counts.critical}
        </Badge>
        <Badge variant="outline" className={badgeClass("offline", "border-slate-300 bg-slate-100 text-slate-700")} onClick={() => handleClick("offline")}>
          Offline: {counts.offline}
        </Badge>
        <Badge variant="outline" className={badgeClass("unknown", "")} onClick={() => handleClick("unknown")}>
          Unknown: {counts.unknown}
        </Badge>
        <div className="ml-auto text-sm font-medium">
          Total: {total}
        </div>
      </div>
    </Card>
  );
}
