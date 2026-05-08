import { memo } from "react";
import { Handle, Position, type NodeProps } from "@xyflow/react";
import { Server, Shield, Router } from "lucide-react";
import DevicePortGrid, { type DevicePort } from "./DevicePortGrid";

export interface DockerHostData {
  label: string;
  os: string;
  ip: string;
  containersCount: number;
  status: "running" | "stopped";
  deviceType?: "server" | "switch" | "router" | "firewall";
  ports?: DevicePort[];
}

const deviceIcons = {
  server: Server,
  switch: Server,
  router: Router,
  firewall: Shield,
};

const DockerHostNode = memo(({ data, selected }: NodeProps) => {
  const d = data as unknown as DockerHostData;
  const isRunning = d.status === "running";
  const Icon = deviceIcons[d.deviceType || "server"] || Server;

  return (
    <div
      className={`rounded-xl border-2 bg-card/90 backdrop-blur-sm p-4 min-w-[200px] shadow-lg transition-all ${
        selected ? "border-primary ring-2 ring-primary/30" : "border-border"
      }`}
    >
      <Handle type="source" position={Position.Bottom} className="!bg-primary !w-3 !h-3" />
      <Handle type="target" position={Position.Top} className="!bg-primary !w-3 !h-3" />

      <div className="flex items-center gap-3 mb-2">
        <div className="p-2 rounded-lg bg-blue-500/20">
          <Icon className="h-6 w-6 text-blue-400" />
        </div>
        <div className="flex-1 min-w-0">
          <h3 className="font-semibold text-sm text-foreground truncate">{d.label}</h3>
          <p className="text-xs text-muted-foreground">{d.os}</p>
        </div>
        <span className={`h-3 w-3 rounded-full shrink-0 ${isRunning ? "bg-green-500 shadow-green-500/50 shadow-md" : "bg-red-500 shadow-red-500/50 shadow-md"}`} />
      </div>
      <div className="flex items-center justify-between text-xs text-muted-foreground border-t border-border pt-2 mt-1">
        <span className="font-mono">{d.ip}</span>
        <span>{d.containersCount} containers</span>
      </div>

      {/* Port strip */}
      {d.ports && d.ports.length > 0 && (
        <div className="border-t border-border pt-2 mt-2">
          <DevicePortGrid ports={d.ports} compact />
        </div>
      )}
    </div>
  );
});

DockerHostNode.displayName = "DockerHostNode";
export default DockerHostNode;
