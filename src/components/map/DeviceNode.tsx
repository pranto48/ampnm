import { memo, useState } from "react";
import { Handle, Position, type NodeProps } from "@xyflow/react";
import { Activity } from "lucide-react";
import { getIconComponent } from "@/components/devices/DeviceIconPicker";
import { supabase } from "@/integrations/supabase/client";

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

function DeviceNodeComponent({ data, id }: NodeProps) {
  const Icon = getIconComponent((data.subchoice as string) || (data.icon as string));
  const status = (data.status as string) || "unknown";
  const iconSize = (data.icon_size as number) || 40;
  const nameSize = (data.name_text_size as number) || 12;
  const ipAddress = data.ip_address as string | null;
  const lastLatency = data.last_latency as number | null;

  const [isPinging, setIsPinging] = useState(false);
  const [pingLatency, setPingLatency] = useState<number | null>(null);

  const handlePing = async (e: React.MouseEvent) => {
    e.stopPropagation();
    if (!ipAddress || isPinging) return;

    setIsPinging(true);
    setPingLatency(null);

    try {
      const { data: result, error } = await supabase.functions.invoke("ping-device", {
        body: { device_id: id },
      });

      if (error) throw error;

      if (result?.results?.[0]) {
        const r = result.results[0];
        setPingLatency(r.success ? r.latency_ms : null);
      }
    } catch (err) {
      console.error("Ping failed:", err);
    } finally {
      setIsPinging(false);
    }
  };

  const displayLatency = pingLatency ?? lastLatency;

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
          {displayLatency != null && (
            <span className="text-[10px] text-muted-foreground">{displayLatency}ms</span>
          )}
        </div>
        {ipAddress && (
          <span className="text-[10px] font-mono text-muted-foreground">{ipAddress}</span>
        )}
        {ipAddress && (
          <button
            onClick={handlePing}
            disabled={isPinging}
            className="mt-1 flex items-center gap-1 rounded bg-primary/20 hover:bg-primary/30 text-primary text-[10px] px-2 py-0.5 transition-colors disabled:opacity-50"
          >
            <Activity className={`h-3 w-3 ${isPinging ? "animate-spin" : ""}`} />
            {isPinging ? "Pinging..." : "Ping"}
          </button>
        )}
      </div>
    </>
  );
}

export default memo(DeviceNodeComponent);