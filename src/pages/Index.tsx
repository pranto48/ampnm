import { useEffect } from "react";
import { Activity, RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";
import { StatusSummary } from "@/components/StatusSummary";
import { TargetGrid } from "@/components/TargetGrid";
import { AddTargetDialog } from "@/components/AddTargetDialog";
import { useTargets, useLatestPingResults, usePingTarget } from "@/hooks/useTargets";

const Index = () => {
  const { data: targets = [], isLoading: targetsLoading } = useTargets();
  const { data: pingResults = {}, isLoading: resultsLoading } = useLatestPingResults();
  const pingMutation = usePingTarget();

  // Auto-ping all targets on initial load
  useEffect(() => {
    if (targets.length > 0 && Object.keys(pingResults).length === 0) {
      targets.forEach((target) => {
        pingMutation.mutate({ targetId: target.id, host: target.host });
      });
    }
  }, [targets.length]);

  const handleRefreshAll = () => {
    targets.forEach((target) => {
      pingMutation.mutate({ targetId: target.id, host: target.host });
    });
  };

  const isLoading = targetsLoading || resultsLoading;

  return (
    <div className="min-h-screen bg-background dark">
      <div className="container mx-auto px-4 py-8 max-w-7xl">
        {/* Header */}
        <header className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-primary/10 rounded-lg">
              <Activity className="h-8 w-8 text-primary" />
            </div>
            <div>
              <h1 className="text-3xl font-bold text-foreground tracking-tight">
                Network Monitor
              </h1>
              <p className="text-muted-foreground text-sm">
                Real-time connectivity monitoring
              </p>
            </div>
          </div>
          <div className="flex gap-2">
            <Button
              variant="outline"
              onClick={handleRefreshAll}
              disabled={targets.length === 0 || pingMutation.isPending}
              className="gap-2 border-border text-foreground hover:bg-muted"
            >
              <RefreshCw
                className={`h-4 w-4 ${pingMutation.isPending ? "animate-spin" : ""}`}
              />
              Refresh All
            </Button>
            <AddTargetDialog />
          </div>
        </header>

        {isLoading ? (
          <div className="flex items-center justify-center py-16">
            <RefreshCw className="h-8 w-8 text-primary animate-spin" />
          </div>
        ) : (
          <div className="space-y-8">
            {/* Status Summary */}
            <StatusSummary targets={targets} pingResults={pingResults} />

            {/* Targets Section */}
            <section>
              <h2 className="text-xl font-semibold text-foreground mb-4">
                Monitored Targets
              </h2>
              <TargetGrid targets={targets} pingResults={pingResults} />
            </section>
          </div>
        )}
      </div>
    </div>
  );
};

export default Index;
