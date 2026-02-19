import { memo } from "react";
import { Handle, Position, type NodeProps } from "@xyflow/react";
import { Server } from "lucide-react";
import { getIconComponent } from "@/components/devices/DeviceIconPicker";

const iconMap: Record<string, React.ElementType> = {};
// Legacy fallback kept for compatibility, but getIconComponent is preferred

const statusStyles: Record<string, string> = {
  online: "border-success glow-success",
  warning: "border-warning glow-warning",
  critical: "border-destructive glow-destructive",
  offline: "border-muted-foreground",
  unknown: "border-muted-foreground/50",
};

const statusDot: Record<string, string> = {
  online: "bg-success pulse-online",
  warning: "bg-warning",
  critical: "bg-destructive pulse-offline",
  offline: "bg-muted-foreground",
  unknown: "bg-muted-foreground/50",
};

function DeviceNodeComponent({ data }: NodeProps) {
  const Icon = getIconComponent((data.subchoice as string) || (data.icon as string));
  const status = (data.status as string) || "unknown";
  const iconSize = (data.icon_size as number) || 40;
  const nameSize = (data.name_text_size as number) || 12;

  return (
    <>
      <Handle type="target" position={Position.Top} className="!bg-primary !w-2 !h-2" />
      <Handle type="target" position={Position.Left} className="!bg-primary !w-2 !h-2" />
      <Handle type="source" position={Position.Bottom} className="!bg-primary !w-2 !h-2" />
      <Handle type="source" position={Position.Right} className="!bg-primary !w-2 !h-2" />

      <div
        className={`flex flex-col items-center gap-1 rounded-lg border-2 bg-card p-3 shadow-lg transition-shadow ${statusStyles[status] || statusStyles.unknown}`}
        style={{ minWidth: 80 }}
      >
        {data.icon_url ? (
          <img
            src={data.icon_url as string}
            alt={data.name as string}
            style={{ width: iconSize, height: iconSize, objectFit: "contain" }}
          />
        ) : (
          <Icon style={{ width: iconSize, height: iconSize }} className="text-foreground" />
        )}
        <span
          className="text-foreground font-medium text-center leading-tight max-w-[120px] truncate"
          style={{ fontSize: nameSize }}
        >
          {data.name as string}
        </span>
        <div className="flex items-center gap-1.5 mt-0.5">
          <span className={`h-2 w-2 rounded-full ${statusDot[status] || statusDot.unknown}`} />
          <span className="text-[10px] text-muted-foreground capitalize">{status}</span>
        </div>
        {data.ip_address && (
          <span className="text-[10px] font-mono text-muted-foreground">{data.ip_address as string}</span>
        )}
      </div>
    </>
  );
}

export default memo(DeviceNodeComponent);
