import { Activity, Server, AlertTriangle } from "lucide-react";
import { Target, PingResult } from "@/hooks/useTargets";

interface StatusSummaryProps {
  targets: Target[];
  pingResults: Record<string, PingResult>;
}

export function StatusSummary({ targets, pingResults }: StatusSummaryProps) {
  const totalTargets = targets.length;
  
  const onlineCount = targets.filter((t) => {
    const result = pingResults[t.id];
    return result?.status === "online";
  }).length;
  
  const offlineCount = targets.filter((t) => {
    const result = pingResults[t.id];
    return result?.status === "offline";
  }).length;

  const unknownCount = totalTargets - onlineCount - offlineCount;

  return (
    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
      <SummaryCard
        icon={Server}
        label="Total Targets"
        value={totalTargets}
        variant="default"
      />
      <SummaryCard
        icon={Activity}
        label="Online"
        value={onlineCount}
        variant="success"
      />
      <SummaryCard
        icon={AlertTriangle}
        label="Offline"
        value={offlineCount}
        variant="destructive"
      />
      <SummaryCard
        icon={Server}
        label="Unknown"
        value={unknownCount}
        variant="warning"
      />
    </div>
  );
}

interface SummaryCardProps {
  icon: React.ComponentType<{ className?: string }>;
  label: string;
  value: number;
  variant: "default" | "success" | "destructive" | "warning";
}

function SummaryCard({ icon: Icon, label, value, variant }: SummaryCardProps) {
  const variantStyles = {
    default: "border-border",
    success: "border-success/50",
    destructive: "border-destructive/50",
    warning: "border-warning/50",
  };

  const iconStyles = {
    default: "text-muted-foreground",
    success: "text-success",
    destructive: "text-destructive",
    warning: "text-warning",
  };

  const valueStyles = {
    default: "text-foreground",
    success: "text-success text-glow-success",
    destructive: "text-destructive text-glow-destructive",
    warning: "text-warning",
  };

  return (
    <div
      className={`bg-card border ${variantStyles[variant]} rounded-lg p-6 transition-all hover:bg-muted/50`}
    >
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm text-muted-foreground uppercase tracking-wider">
            {label}
          </p>
          <p className={`text-4xl font-mono font-bold mt-2 ${valueStyles[variant]}`}>
            {value}
          </p>
        </div>
        <Icon className={`h-10 w-10 ${iconStyles[variant]}`} />
      </div>
    </div>
  );
}
