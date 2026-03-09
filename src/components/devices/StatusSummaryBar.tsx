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
        <Badge variant="default" className="bg-green-600 hover:bg-green-700">
          Online: {counts.online}
        </Badge>
        <Badge variant="default" className="bg-yellow-600 hover:bg-yellow-700">
          Warning: {counts.warning}
        </Badge>
        <Badge variant="destructive">
          Critical: {counts.critical}
        </Badge>
        <Badge variant="default" className="bg-gray-600 hover:bg-gray-700">
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
