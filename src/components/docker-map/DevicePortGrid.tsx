import { memo } from "react";
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip";

export interface DevicePort {
  id: string;
  name: string;
  type: "gigabit" | "fastethernet" | "sfp" | "serial" | "mgmt" | "console";
  status: "up" | "down" | "disabled";
  speed?: string;
  connectedTo?: string;
}

interface DevicePortGridProps {
  ports: DevicePort[];
  compact?: boolean;
  onPortClick?: (port: DevicePort) => void;
}

const statusColor: Record<DevicePort["status"], string> = {
  up: "bg-green-500 shadow-green-500/50 shadow-sm",
  down: "bg-red-500 shadow-red-500/50 shadow-sm",
  disabled: "bg-muted-foreground/40",
};

const typeLabel: Record<DevicePort["type"], string> = {
  gigabit: "GE",
  fastethernet: "FE",
  sfp: "SFP",
  serial: "Serial",
  mgmt: "Mgmt",
  console: "Con",
};

const DevicePortGrid = memo(({ ports, compact = false, onPortClick }: DevicePortGridProps) => {
  if (!ports || ports.length === 0) return null;

  const half = Math.ceil(ports.length / 2);
  const topRow = ports.slice(0, half);
  const bottomRow = ports.slice(half);

  const renderPort = (port: DevicePort) => {
    const led = statusColor[port.status];
    const connected = !!port.connectedTo;

    return (
      <Tooltip key={port.id}>
        <TooltipTrigger asChild>
          <button
            type="button"
            onClick={() => onPortClick?.(port)}
            className={`relative flex flex-col items-center gap-0.5 rounded-sm border border-border transition-colors
              ${compact ? "w-5 h-5 p-0" : "w-8 px-0.5 py-0.5"}
              ${connected ? "bg-blue-500/10 border-blue-500/40" : "bg-muted/30"}
              hover:bg-accent/40 cursor-pointer`}
          >
            <span className={`rounded-full shrink-0 ${compact ? "h-1.5 w-1.5" : "h-2 w-2"} ${led}`} />
            {!compact && (
              <span className="text-[7px] leading-none font-mono text-muted-foreground truncate w-full text-center">
                {port.name}
              </span>
            )}
          </button>
        </TooltipTrigger>
        <TooltipContent side="top" className="text-xs">
          <div className="font-semibold">{port.name}</div>
          <div className="text-muted-foreground">
            {typeLabel[port.type]} · {port.speed || "Auto"} · <span className="capitalize">{port.status}</span>
          </div>
          {port.connectedTo && (
            <div className="text-blue-400 mt-0.5">→ {port.connectedTo}</div>
          )}
        </TooltipContent>
      </Tooltip>
    );
  };

  return (
    <TooltipProvider delayDuration={200}>
      <div className="space-y-0.5">
        <div className={`flex flex-wrap gap-0.5 ${compact ? "justify-center" : ""}`}>
          {topRow.map(renderPort)}
        </div>
        {bottomRow.length > 0 && (
          <div className={`flex flex-wrap gap-0.5 ${compact ? "justify-center" : ""}`}>
            {bottomRow.map(renderPort)}
          </div>
        )}
      </div>
    </TooltipProvider>
  );
});

DevicePortGrid.displayName = "DevicePortGrid";
export default DevicePortGrid;
