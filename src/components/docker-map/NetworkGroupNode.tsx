import { memo } from "react";
import { type NodeProps } from "@xyflow/react";
import { Network } from "lucide-react";

export interface NetworkGroupData {
  label: string;
  driver: string;
  width: number;
  height: number;
}

const NetworkGroupNode = memo(({ data, selected }: NodeProps) => {
  const d = data as unknown as NetworkGroupData;

  const borderColor =
    d.driver === "overlay" ? "border-purple-500/40" :
    d.driver === "host" ? "border-amber-500/40" :
    "border-cyan-500/40";

  const bgColor =
    d.driver === "overlay" ? "bg-purple-500/5" :
    d.driver === "host" ? "bg-amber-500/5" :
    "bg-cyan-500/5";

  const textColor =
    d.driver === "overlay" ? "text-purple-400" :
    d.driver === "host" ? "text-amber-400" :
    "text-cyan-400";

  return (
    <div
      className={`rounded-xl border-2 border-dashed ${borderColor} ${bgColor} transition-all ${
        selected ? "ring-2 ring-primary/20" : ""
      }`}
      style={{ width: d.width, height: d.height, pointerEvents: "none" }}
    >
      <div className="flex items-center gap-1.5 px-3 py-1.5">
        <Network className={`h-3.5 w-3.5 ${textColor}`} />
        <span className={`text-[10px] font-semibold uppercase tracking-wider ${textColor}`}>
          {d.label}
        </span>
        <span className="text-[9px] text-muted-foreground ml-1">({d.driver})</span>
      </div>
    </div>
  );
});

NetworkGroupNode.displayName = "NetworkGroupNode";
export default NetworkGroupNode;
