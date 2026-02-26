import { useState, useEffect } from "react";
import { useGlobalAutoPing } from "@/hooks/useGlobalAutoPing";
import { Radio, Wifi, WifiOff, AlertTriangle, AlertCircle, ChevronDown } from "lucide-react";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Switch } from "@/components/ui/switch";
import { ScrollArea } from "@/components/ui/scroll-area";

const statusIcon = (s: string | null) => {
  switch (s) {
    case "online": return <Wifi className="h-3.5 w-3.5 text-success" />;
    case "warning": return <AlertTriangle className="h-3.5 w-3.5 text-warning" />;
    case "critical": return <AlertCircle className="h-3.5 w-3.5 text-destructive" />;
    case "offline": return <WifiOff className="h-3.5 w-3.5 text-muted-foreground" />;
    default: return <WifiOff className="h-3.5 w-3.5 text-muted-foreground" />;
  }
};

const statusLabel = (s: string | null) => {
  switch (s) {
    case "online": return "text-success";
    case "warning": return "text-warning";
    case "critical": return "text-destructive";
    default: return "text-muted-foreground";
  }
};

function Countdown({ lastPingTs, intervalSec }: { lastPingTs: number | undefined; intervalSec: number }) {
  const [remaining, setRemaining] = useState<number | null>(null);

  useEffect(() => {
    if (!lastPingTs) { setRemaining(null); return; }

    const calc = () => {
      const elapsed = (Date.now() - lastPingTs) / 1000;
      const r = Math.max(0, intervalSec - elapsed);
      setRemaining(Math.round(r));
    };
    calc();
    const id = setInterval(calc, 1000);
    return () => clearInterval(id);
  }, [lastPingTs, intervalSec]);

  if (remaining === null) return <span className="text-muted-foreground">—</span>;
  const mins = Math.floor(remaining / 60);
  const secs = remaining % 60;
  return (
    <span className="tabular-nums text-muted-foreground">
      {mins > 0 ? `${mins}m ${secs}s` : `${secs}s`}
    </span>
  );
}

export function AutoPingDropdown() {
  const {
    enabled, setEnabled,
    monitoredCount, totalCount,
    monitoredDevices, lastPingTimestamps,
  } = useGlobalAutoPing();

  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          className={`flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs font-medium transition-colors ${
            enabled
              ? "border-success/40 bg-success/10 text-success hover:bg-success/20"
              : "border-border bg-muted/30 text-muted-foreground hover:bg-muted/50"
          }`}
        >
          <Radio className={`h-3.5 w-3.5 ${enabled ? "animate-pulse" : ""}`} />
          {monitoredCount}/{totalCount}
          <ChevronDown className="h-3 w-3" />
        </button>
      </PopoverTrigger>
      <PopoverContent
        align="end"
        className="w-80 p-0 bg-popover border border-border shadow-lg z-[60]"
        sideOffset={8}
      >
        {/* Header */}
        <div className="flex items-center justify-between px-4 py-3 border-b border-border">
          <div className="flex items-center gap-2">
            <Radio className={`h-4 w-4 ${enabled ? "text-success" : "text-muted-foreground"}`} />
            <span className="text-sm font-medium">Auto-Ping</span>
          </div>
          <Switch checked={enabled} onCheckedChange={setEnabled} />
        </div>

        {/* Device List */}
        {monitoredDevices.length === 0 ? (
          <div className="px-4 py-6 text-center text-sm text-muted-foreground">
            No monitored devices
          </div>
        ) : (
          <ScrollArea className="max-h-[300px]">
            <div className="divide-y divide-border">
              {monitoredDevices.map((device) => {
                const interval = Math.max(device.ping_interval ?? 300, 10);
                return (
                  <div key={device.id} className="flex items-center gap-3 px-4 py-2.5">
                    {statusIcon(device.status)}
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium truncate">{device.name}</p>
                      <p className="text-xs text-muted-foreground truncate">{device.ip_address}</p>
                    </div>
                    <div className="text-right shrink-0">
                      <p className={`text-xs font-medium capitalize ${statusLabel(device.status)}`}>
                        {device.status ?? "unknown"}
                      </p>
                      {enabled ? (
                        <p className="text-[10px]">
                          <Countdown
                            lastPingTs={lastPingTimestamps.get(device.id)}
                            intervalSec={interval}
                          />
                        </p>
                      ) : (
                        <p className="text-[10px] text-muted-foreground">paused</p>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </ScrollArea>
        )}

        {/* Footer */}
        <div className="px-4 py-2 border-t border-border text-[10px] text-muted-foreground">
          {monitoredCount} of {totalCount} devices monitored
        </div>
      </PopoverContent>
    </Popover>
  );
}
