import { Target, PingResult } from "@/hooks/useTargets";
import { TargetCard } from "./TargetCard";
import { Server } from "lucide-react";

interface TargetGridProps {
  targets: Target[];
  pingResults: Record<string, PingResult>;
}

export function TargetGrid({ targets, pingResults }: TargetGridProps) {
  if (targets.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-16 text-center">
        <Server className="h-16 w-16 text-muted-foreground/50 mb-4" />
        <h3 className="text-xl font-semibold text-foreground mb-2">
          No Targets Yet
        </h3>
        <p className="text-muted-foreground max-w-md">
          Add your first server or device to start monitoring its connectivity status.
        </p>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      {targets.map((target) => (
        <TargetCard
          key={target.id}
          target={target}
          pingResult={pingResults[target.id]}
        />
      ))}
    </div>
  );
}
