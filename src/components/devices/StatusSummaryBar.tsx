import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import type { Tables } from "@/integrations/supabase/types";

type Device = Tables<"devices">;

interface StatusSummaryBarProps {
  devices: Device[];
}

export function StatusSummaryBar({ devices }: StatusSummaryBarProps) {
  const counts = {
    online: devices.filter(d => d.status === "online").length,
    warning: devices.filter(d => d.status === "warning").length,
    critical: devices.filter(d => d.status === "critical").length,
    offline: devices.filter(d => d.status === "offline").length,
    unknown: devices.filter(d => !d.status || d.status === "unknown").length,
  };

  const total = devices.length;

  return (
    <Card className="p-4 mb-4">
      <div className="flex items-center gap-3 flex-wrap">
        <span className="text-sm font-medium text-muted-foreground">Status Summary:</span>
        <Badge variant="outline" className="border-green-200 bg-green-50 text-green-700">
          Online: {counts.online}
        </Badge>
        <Badge variant="outline" className="border-yellow-200 bg-yellow-50 text-yellow-700">
          Warning: {counts.warning}
        </Badge>
        <Badge variant="destructive">
          Critical: {counts.critical}
        </Badge>
        <Badge variant="outline" className="border-slate-300 bg-slate-100 text-slate-700">
          Offline: {counts.offline}
        </Badge>
        <Badge variant="outline">
          Unknown: {counts.unknown}
        </Badge>
        <div className="ml-auto text-sm font-medium">
          Total: {total}
        </div>
      </div>
    </Card>
  );
}
