import { memo } from "react";
import { Handle, Position, type NodeProps } from "@xyflow/react";
import { Network } from "lucide-react";
import DevicePortGrid, { type DevicePort } from "./DevicePortGrid";

export interface NetworkBridgeData {
  label: string;
  driver: string;
  subnet: string;
  gateway: string;
  scope: string;
  ports?: DevicePort[];
}

const NetworkBridgeNode = memo(({ data, selected }: NodeProps) => {
  const d = data as unknown as NetworkBridgeData;

  const driverColor =
    d.driver === "overlay" ? "text-purple-400 bg-purple-500/20" :
    d.driver === "host" ? "text-amber-400 bg-amber-500/20" :
    "text-cyan-400 bg-cyan-500/20";

  const hasPorts = d.ports && d.ports.length > 0;

  return (
    <div
      className={`rounded-lg border-2 border-dashed bg-card/80 backdrop-blur-sm p-3 min-w-[180px] shadow-md transition-all ${
        selected ? "border-primary ring-2 ring-primary/30" : "border-border"
      }`}
    >
      <Handle type="source" position={Position.Bottom} className="!bg-cyan-400 !w-3 !h-3" />
      <Handle type="target" position={Position.Top} className="!bg-cyan-400 !w-3 !h-3" />

      {/* Top port row – real ports or decorative */}
      {hasPorts ? (
        <div className="mb-2">
          <DevicePortGrid ports={d.ports!.slice(0, Math.ceil(d.ports!.length / 2))} compact />
        </div>
      ) : (
        <div className="flex items-center gap-1 mb-2">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="h-2 w-4 rounded-sm bg-muted-foreground/30 border border-border" />
          ))}
        </div>
      )}

      <div className="flex items-center gap-2 mb-1.5">
        <div className={`p-1.5 rounded-md ${driverColor}`}>
          <Network className="h-4 w-4" />
        </div>
        <div className="flex-1 min-w-0">
          <h3 className="font-semibold text-xs text-foreground truncate">{d.label}</h3>
          <span className="text-[10px] uppercase tracking-wider text-muted-foreground font-medium">{d.driver} · {d.scope}</span>
        </div>
      </div>

      <div className="text-[10px] text-muted-foreground font-mono border-t border-border pt-1.5 space-y-0.5">
        <div>Subnet: {d.subnet}</div>
        <div>GW: {d.gateway}</div>
      </div>

      {/* Bottom port row */}
      {hasPorts ? (
        <div className="mt-2">
          <DevicePortGrid ports={d.ports!.slice(Math.ceil(d.ports!.length / 2))} compact />
        </div>
      ) : (
        <div className="flex items-center gap-1 mt-2">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="h-2 w-4 rounded-sm bg-muted-foreground/30 border border-border" />
          ))}
        </div>
      )}
    </div>
  );
});

NetworkBridgeNode.displayName = "NetworkBridgeNode";
export default NetworkBridgeNode;
