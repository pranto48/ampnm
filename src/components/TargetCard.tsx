import { useState } from "react";
import { Trash2, RefreshCw, Globe, Clock } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Target, PingResult, usePingTarget, useDeleteTarget } from "@/hooks/useTargets";
import { formatDistanceToNow } from "date-fns";

interface TargetCardProps {
  target: Target;
  pingResult?: PingResult;
}

export function TargetCard({ target, pingResult }: TargetCardProps) {
  const [isPinging, setIsPinging] = useState(false);
  const pingMutation = usePingTarget();
  const deleteMutation = useDeleteTarget();

  const status = pingResult?.status || "unknown";
  const responseTime = pingResult?.response_time_ms;
  const lastChecked = pingResult?.checked_at;

  const handlePing = async () => {
    setIsPinging(true);
    try {
      await pingMutation.mutateAsync({ targetId: target.id, host: target.host });
    } finally {
      setIsPinging(false);
    }
  };

  const handleDelete = () => {
    deleteMutation.mutate(target.id);
  };

  const statusStyles = {
    online: {
      indicator: "bg-success pulse-online",
      text: "text-success",
      border: "border-success/30 hover:border-success/50",
      glow: "glow-success",
    },
    offline: {
      indicator: "bg-destructive pulse-offline",
      text: "text-destructive",
      border: "border-destructive/30 hover:border-destructive/50",
      glow: "glow-destructive",
    },
    unknown: {
      indicator: "bg-warning",
      text: "text-warning",
      border: "border-warning/30 hover:border-warning/50",
      glow: "",
    },
  };

  const styles = statusStyles[status];

  return (
    <div
      className={`bg-card border ${styles.border} rounded-lg p-5 transition-all duration-300 ${status !== "unknown" ? styles.glow : ""}`}
    >
      <div className="flex items-start justify-between mb-4">
        <div className="flex items-center gap-3">
          <div className={`w-3 h-3 rounded-full ${styles.indicator}`} />
          <div>
            <h3 className="font-semibold text-lg text-foreground">{target.name}</h3>
            <div className="flex items-center gap-1 text-sm text-muted-foreground">
              <Globe className="h-3 w-3" />
              <span className="font-mono">{target.host}</span>
            </div>
          </div>
        </div>
        <div className="flex gap-1">
          <Button
            variant="ghost"
            size="icon"
            onClick={handlePing}
            disabled={isPinging}
            className="h-8 w-8 text-muted-foreground hover:text-primary"
          >
            <RefreshCw className={`h-4 w-4 ${isPinging ? "animate-spin" : ""}`} />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            onClick={handleDelete}
            disabled={deleteMutation.isPending}
            className="h-8 w-8 text-muted-foreground hover:text-destructive"
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
      </div>

      <div className="flex items-center justify-between text-sm">
        <div className="flex items-center gap-4">
          <span className={`font-mono uppercase font-semibold ${styles.text}`}>
            {status}
          </span>
          {responseTime !== null && responseTime !== undefined && (
            <span className="text-muted-foreground font-mono">
              {responseTime}ms
            </span>
          )}
        </div>
        {lastChecked && (
          <div className="flex items-center gap-1 text-muted-foreground">
            <Clock className="h-3 w-3" />
            <span className="text-xs">
              {formatDistanceToNow(new Date(lastChecked), { addSuffix: true })}
            </span>
          </div>
        )}
      </div>
    </div>
  );
}
