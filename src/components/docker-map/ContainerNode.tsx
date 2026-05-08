import { memo } from "react";
import { Handle, Position, type NodeProps } from "@xyflow/react";
import { Box, Container } from "lucide-react";

export interface ContainerData {
  label: string;
  image: string;
  containerId: string;
  internalIp: string;
  state: "running" | "stopped" | "paused";
  ports: { external: number; internal: number; protocol: string }[];
  networks: string[];
}

const ContainerNode = memo(({ data, selected }: NodeProps) => {
  const d = data as unknown as ContainerData;
  const isRunning = d.state === "running";
  const isPaused = d.state === "paused";

  const statusColor = isRunning
    ? "bg-green-500 shadow-green-500/50"
    : isPaused
    ? "bg-amber-500 shadow-amber-500/50"
    : "bg-red-500 shadow-red-500/50";

  return (
    <div
      className={`rounded-lg border bg-card/90 backdrop-blur-sm p-3 min-w-[170px] shadow-md transition-all cursor-pointer hover:shadow-lg ${
        selected ? "border-primary ring-2 ring-primary/30" : "border-border"
      }`}
    >
      <Handle type="source" position={Position.Bottom} className="!bg-green-400 !w-2.5 !h-2.5" />
      <Handle type="target" position={Position.Top} className="!bg-green-400 !w-2.5 !h-2.5" />

      <div className="flex items-center gap-2.5">
        <div className="p-1.5 rounded-md bg-emerald-500/20">
          <Box className="h-4 w-4 text-emerald-400" />
        </div>
        <div className="flex-1 min-w-0">
          <h3 className="font-semibold text-xs text-foreground truncate">{d.label}</h3>
          <p className="text-[10px] text-muted-foreground truncate font-mono">{d.image}</p>
        </div>
        <span className={`h-2.5 w-2.5 rounded-full shrink-0 shadow-md ${statusColor}`} />
      </div>

      {d.ports.length > 0 && (
        <div className="mt-2 pt-1.5 border-t border-border">
          <div className="flex flex-wrap gap-1">
            {d.ports.slice(0, 3).map((p, i) => (
              <span key={i} className="text-[9px] font-mono bg-muted px-1.5 py-0.5 rounded text-muted-foreground">
                {p.external}:{p.internal}/{p.protocol}
              </span>
            ))}
            {d.ports.length > 3 && (
              <span className="text-[9px] text-muted-foreground">+{d.ports.length - 3}</span>
            )}
          </div>
        </div>
      )}
    </div>
  );
});

ContainerNode.displayName = "ContainerNode";
export default ContainerNode;
