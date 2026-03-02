import { useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Server, Box, HardDrive, RefreshCw, AlertTriangle, Container } from "lucide-react";
import { useToast } from "@/hooks/use-toast";

// Demo data – will be replaced by live sync
interface DockerHost {
  id: string;
  hostname: string;
  ip: string;
  dockerVersion: string;
  activeContainers: number;
  lastSynced: string;
  status: "online" | "offline";
}

interface PortAlert {
  id: string;
  severity: "warning" | "critical";
  message: string;
  host: string;
  timestamp: string;
}

const demoHosts: DockerHost[] = [
  { id: "h1", hostname: "prod-server-01", ip: "192.168.1.10", dockerVersion: "25.0.3", activeContainers: 4, lastSynced: new Date(Date.now() - 120000).toISOString(), status: "online" },
  { id: "h2", hostname: "dev-server-02", ip: "192.168.1.11", dockerVersion: "24.0.7", activeContainers: 2, lastSynced: new Date(Date.now() - 300000).toISOString(), status: "online" },
  { id: "h3", hostname: "staging-03", ip: "192.168.1.12", dockerVersion: "25.0.3", activeContainers: 0, lastSynced: new Date(Date.now() - 3600000).toISOString(), status: "offline" },
];

const demoAlerts: PortAlert[] = [
  { id: "a1", severity: "warning", message: "Port Conflict: Port 8080 is bound to multiple containers on prod-server-01", host: "prod-server-01", timestamp: new Date(Date.now() - 600000).toISOString() },
  { id: "a2", severity: "critical", message: "Port Conflict: Port 443 collision between nginx-proxy and traefik on prod-server-01", host: "prod-server-01", timestamp: new Date(Date.now() - 180000).toISOString() },
  { id: "a3", severity: "warning", message: "Orphaned network 'legacy_bridge' has no connected containers on dev-server-02", host: "dev-server-02", timestamp: new Date(Date.now() - 900000).toISOString() },
];

const DockerStatusDashboard = () => {
  const { toast } = useToast();
  const [syncingHosts, setSyncingHosts] = useState<Set<string>>(new Set());

  const totalHosts = demoHosts.length;
  const activeContainers = demoHosts.reduce((sum, h) => sum + h.activeContainers, 0);
  const orphanedVolumes = 3; // demo value

  const handleForceSync = (host: DockerHost) => {
    setSyncingHosts((prev) => new Set(prev).add(host.id));
    toast({
      title: "Syncing Docker topology",
      description: `Syncing Docker topology for ${host.hostname}...`,
    });

    // Simulate sync completion
    setTimeout(() => {
      setSyncingHosts((prev) => {
        const next = new Set(prev);
        next.delete(host.id);
        return next;
      });
      toast({
        title: "Sync complete",
        description: `${host.hostname} topology synced successfully.`,
      });
    }, 3000);
  };

  const formatTimeAgo = (iso: string) => {
    const diff = Date.now() - new Date(iso).getTime();
    if (diff < 60000) return "Just now";
    if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
    return `${Math.floor(diff / 3600000)}h ago`;
  };

  return (
    <div className="space-y-6">
      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Docker Hosts</CardTitle>
            <Server className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold">{totalHosts}</div>
            <p className="text-xs text-muted-foreground mt-1">
              {demoHosts.filter((h) => h.status === "online").length} online · {demoHosts.filter((h) => h.status === "offline").length} offline
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Active Containers</CardTitle>
            <Box className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold">{activeContainers}</div>
            <p className="text-xs text-muted-foreground mt-1">Across all hosts</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Orphaned Volumes</CardTitle>
            <HardDrive className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold">{orphanedVolumes}</div>
            <p className="text-xs text-muted-foreground mt-1">Unused, can be pruned</p>
          </CardContent>
        </Card>
      </div>

      {/* Data Sync Table */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Container className="h-5 w-5" />
            Connected Docker Hosts
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Hostname</TableHead>
                  <TableHead>IP</TableHead>
                  <TableHead>Docker Version</TableHead>
                  <TableHead className="text-center">Active Containers</TableHead>
                  <TableHead>Last Synced</TableHead>
                  <TableHead className="text-right">Action</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {demoHosts.map((host) => (
                  <TableRow key={host.id}>
                    <TableCell className="font-medium">
                      <div className="flex items-center gap-2">
                        <span className={`h-2 w-2 rounded-full ${host.status === "online" ? "bg-green-500" : "bg-red-500"}`} />
                        {host.hostname}
                      </div>
                    </TableCell>
                    <TableCell className="font-mono text-sm text-muted-foreground">{host.ip}</TableCell>
                    <TableCell>
                      <Badge variant="outline" className="text-xs font-mono">{host.dockerVersion}</Badge>
                    </TableCell>
                    <TableCell className="text-center">{host.activeContainers}</TableCell>
                    <TableCell className="text-sm text-muted-foreground">{formatTimeAgo(host.lastSynced)}</TableCell>
                    <TableCell className="text-right">
                      <Button
                        size="sm"
                        variant="outline"
                        disabled={syncingHosts.has(host.id)}
                        onClick={() => handleForceSync(host)}
                        className="h-8 gap-1.5"
                      >
                        <RefreshCw className={`h-3.5 w-3.5 ${syncingHosts.has(host.id) ? "animate-spin" : ""}`} />
                        {syncingHosts.has(host.id) ? "Syncing..." : "Force Sync"}
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      {/* Port Conflict Alerts */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <AlertTriangle className="h-5 w-5 text-amber-400" />
            Active Alerts
          </CardTitle>
        </CardHeader>
        <CardContent>
          {demoAlerts.length === 0 ? (
            <p className="text-sm text-muted-foreground text-center py-4">No active alerts</p>
          ) : (
            <div className="space-y-3">
              {demoAlerts.map((alert) => (
                <div
                  key={alert.id}
                  className={`flex items-start gap-3 p-3 rounded-lg border ${
                    alert.severity === "critical"
                      ? "border-red-500/30 bg-red-500/5"
                      : "border-amber-500/30 bg-amber-500/5"
                  }`}
                >
                  <AlertTriangle
                    className={`h-5 w-5 shrink-0 mt-0.5 ${
                      alert.severity === "critical" ? "text-red-400" : "text-amber-400"
                    }`}
                  />
                  <div className="flex-1 min-w-0">
                    <p className="text-sm text-foreground">{alert.message}</p>
                    <div className="flex items-center gap-3 mt-1.5">
                      <Badge
                        variant={alert.severity === "critical" ? "destructive" : "secondary"}
                        className="text-[10px] uppercase"
                      >
                        {alert.severity}
                      </Badge>
                      <span className="text-xs text-muted-foreground">{formatTimeAgo(alert.timestamp)}</span>
                      <span className="text-xs text-muted-foreground font-mono">{alert.host}</span>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default DockerStatusDashboard;
