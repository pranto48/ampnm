import { useEffect, useMemo, useState } from "react";
import { format } from "date-fns";
import {
  Check,
  Copy,
  Download,
  Key,
  Laptop,
  Monitor,
  Plus,
  RefreshCw,
  Server,
  Terminal,
  Trash2,
  X,
  Zap,
} from "lucide-react";

import { AppLayout } from "@/components/layout/AppLayout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useToast } from "@/hooks/use-toast";
import { useAuth } from "@/hooks/useAuth";
import { supabase } from "@/integrations/supabase/client";

type AgentPlatform = "windows" | "linux" | "unknown";

interface AgentTokenRow {
  id: string;
  name: string;
  token: string;
  enabled: boolean;
  created_at: string;
}

interface HostRow {
  id: string;
  hostname: string;
  ip_address: string | null;
  os_version: string | null;
  status: string;
  last_seen: string;
  cpu_usage: number | null;
  memory_usage: number | null;
  memory_total: number | null;
  disk_usage: number | null;
  disk_total: number | null;
  platform: string | null;
  agent_platform?: string | null;
  load_average?: number | null;
  temperature_celsius?: number | null;
}

const normalizePlatform = (platform?: string | null): AgentPlatform => {
  const value = platform?.toLowerCase();
  if (value === "windows" || value === "linux") return value;
  return "unknown";
};

const platformBadgeVariant = (platform: AgentPlatform) => {
  if (platform === "windows") return "default" as const;
  if (platform === "linux") return "secondary" as const;
  return "outline" as const;
};

const platformLabel = (platform: AgentPlatform) => {
  if (platform === "unknown") return "Unknown";
  return platform.charAt(0).toUpperCase() + platform.slice(1);
};

function CopyButton({ onClick, label = "Copy" }: { onClick: () => void; label?: string }) {
  return (
    <Button variant="ghost" size="sm" onClick={onClick} aria-label={label}>
      <Copy className="h-3 w-3" />
    </Button>
  );
}

function CommandCard({
  title,
  description,
  command,
  copyLabel,
  onCopy,
}: {
  title: string;
  description: string;
  command: string;
  copyLabel: string;
  onCopy: (command: string, label: string) => void;
}) {
  return (
    <div className="space-y-2 rounded-lg border bg-muted/40 p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h3 className="font-medium text-sm">{title}</h3>
          <p className="text-xs text-muted-foreground">{description}</p>
        </div>
        <CopyButton onClick={() => onCopy(command, copyLabel)} label={copyLabel} />
      </div>
      <pre className="text-xs font-mono text-foreground whitespace-pre-wrap break-all">{command}</pre>
    </div>
  );
}

export default function AgentPage() {
  const { toast } = useToast();
  const { user } = useAuth();
  const [tokens, setTokens] = useState<AgentTokenRow[]>([]);
  const [hosts, setHosts] = useState<HostRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [showCreateToken, setShowCreateToken] = useState(false);
  const [newTokenName, setNewTokenName] = useState("");
  const [creatingToken, setCreatingToken] = useState(false);
  const [selectedToken, setSelectedToken] = useState("");

  const projectId = import.meta.env.VITE_SUPABASE_PROJECT_ID || "";
  const agentEndpoint = `https://${projectId}.supabase.co/functions/v1/agent-metrics`;
  const linuxDownloadBase = `${agentEndpoint.replace(/\/agent-metrics$/, "")}/agent-downloads`;
  const selectedTokenValue = selectedToken || "<paste-token-here>";

  const linuxShellInstaller = `curl -fsSL ${linuxDownloadBase}/install.sh | sudo bash -s -- --server-url ${agentEndpoint} --agent-token ${selectedTokenValue}`;
  const linuxDebInstall = `curl -fsSLo /tmp/ampnm-agent.deb ${linuxDownloadBase}/ampnm-agent_latest_amd64.deb && sudo dpkg -i /tmp/ampnm-agent.deb && sudo AMPNM_SERVER_URL=${agentEndpoint} AMPNM_AGENT_TOKEN=${selectedTokenValue} /usr/bin/ampnm-agent register`;
  const linuxRpmInstall = `curl -fsSLo /tmp/ampnm-agent.rpm ${linuxDownloadBase}/ampnm-agent-latest.x86_64.rpm && sudo rpm -Uvh /tmp/ampnm-agent.rpm && sudo AMPNM_SERVER_URL=${agentEndpoint} AMPNM_AGENT_TOKEN=${selectedTokenValue} /usr/bin/ampnm-agent register`;
  const linuxServiceCommands = `sudo systemctl status ampnm-agent\nsudo systemctl restart ampnm-agent`;
  const windowsInstallCommand = `$p = "$env:TEMP\\AMPNM-Agent-Installer.ps1"\nInvoke-WebRequest -Uri "${agentEndpoint}" -OutFile $p\nUnblock-File -Path $p\n& $p -ServerUrl "${agentEndpoint}" -AgentToken "${selectedTokenValue}"`;
  const windowsVerifyCommands = `Get-Service -Name ampnm-agent\nGet-Service -Name ampnm-agent | Select-Object Status, Name, DisplayName\nGet-Content "C:\\ProgramData\\ampnm-agent\\agent.log" -Tail 50`;

  const fetchData = async () => {
    setLoading(true);
    const [tokensRes, hostsRes] = await Promise.all([
      supabase.from("agent_tokens").select("*").order("created_at", { ascending: false }),
      supabase.from("host_metrics").select("*").order("last_seen", { ascending: false }),
    ]);

    setTokens((tokensRes.data ?? []) as AgentTokenRow[]);
    setHosts((hostsRes.data ?? []) as HostRow[]);
    setLoading(false);
  };

  useEffect(() => {
    fetchData();
  }, []);

  const generateToken = () => {
    const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    let result = "ampnm_";
    for (let i = 0; i < 40; i += 1) result += chars.charAt(Math.floor(Math.random() * chars.length));
    return result;
  };

  const copyText = (text: string, description = "Copied to clipboard") => {
    navigator.clipboard.writeText(text);
    toast({ title: description });
  };

  const createToken = async () => {
    if (!newTokenName.trim()) return;

    setCreatingToken(true);
    const token = generateToken();
    const { error } = await supabase.from("agent_tokens").insert({
      name: newTokenName.trim(),
      token,
      created_by: user?.id || "",
    });

    if (error) {
      toast({ title: "Error", description: error.message, variant: "destructive" });
    } else {
      toast({ title: "Token created", description: "Copy the token value — it won't be shown again in full." });
      navigator.clipboard.writeText(token);
    }

    setCreatingToken(false);
    setShowCreateToken(false);
    setNewTokenName("");
    fetchData();
  };

  const deleteToken = async (id: string) => {
    const { error } = await supabase.from("agent_tokens").delete().eq("id", id);
    if (error) {
      toast({ title: "Error", description: error.message, variant: "destructive" });
      return;
    }
    fetchData();
  };

  const toggleToken = async (id: string, enabled: boolean) => {
    await supabase.from("agent_tokens").update({ enabled: !enabled }).eq("id", id);
    fetchData();
  };

  const isRecentlySeen = (lastSeen: string) => Date.now() - new Date(lastSeen).getTime() < 120000;

  const hostCounts = useMemo(() => ({
    online: hosts.filter((host) => isRecentlySeen(host.last_seen)).length,
    offline: hosts.filter((host) => !isRecentlySeen(host.last_seen)).length,
  }), [hosts]);

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between flex-wrap gap-2">
          <div className="flex items-center gap-3">
            <Server className="h-7 w-7 text-primary" />
            <div>
              <h1 className="text-2xl font-bold tracking-tight">Agent Onboarding</h1>
              <p className="text-sm text-muted-foreground">Install and register Windows or Linux agents with a shared token workflow and platform-aware instructions.</p>
            </div>
          </div>
          <Button variant="outline" size="sm" onClick={fetchData} disabled={loading}>
            <RefreshCw className={`h-4 w-4 mr-1 ${loading ? "animate-spin" : ""}`} /> Refresh
          </Button>
        </div>

        <div className="grid gap-6 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
          <Card>
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <Key className="h-4 w-4 text-amber-400" /> Step 1 — Shared Agent Tokens
              </CardTitle>
              <CardDescription>Create one token and reuse it across Windows and Linux hosts.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <Button size="sm" onClick={() => setShowCreateToken(true)}>
                <Plus className="h-4 w-4 mr-1" /> Create Token
              </Button>

              {tokens.length > 0 && (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Name</TableHead>
                      <TableHead>Token</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {tokens.map((token) => (
                      <TableRow key={token.id}>
                        <TableCell className="font-medium text-sm">{token.name}</TableCell>
                        <TableCell>
                          <button
                            onClick={() => {
                              copyText(token.token, "Token copied");
                              setSelectedToken(token.token);
                            }}
                            className="text-xs font-mono text-primary hover:underline cursor-pointer"
                          >
                            {token.token.substring(0, 12)}...
                          </button>
                        </TableCell>
                        <TableCell>
                          <Badge variant={token.enabled ? "default" : "secondary"}>{token.enabled ? "Active" : "Disabled"}</Badge>
                        </TableCell>
                        <TableCell>
                          <div className="flex gap-1">
                            <Button variant="ghost" size="sm" onClick={() => toggleToken(token.id, token.enabled)}>
                              {token.enabled ? <X className="h-3 w-3" /> : <Check className="h-3 w-3" />}
                            </Button>
                            <Button variant="ghost" size="sm" className="text-destructive" onClick={() => deleteToken(token.id)}>
                              <Trash2 className="h-3 w-3" />
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <Download className="h-4 w-4 text-purple-400" /> Step 2 — Platform Installers
              </CardTitle>
              <CardDescription>Choose the host platform, then copy the matching install or service commands.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-1">
                <h3 className="font-medium text-sm">Agent API Endpoint</h3>
                <div className="flex items-center gap-2">
                  <code className="text-xs font-mono text-primary bg-muted px-2 py-1 rounded break-all flex-1">{agentEndpoint}</code>
                  <CopyButton onClick={() => copyText(agentEndpoint, "Endpoint copied")} label="Copy raw endpoint URL" />
                </div>
              </div>

              <Tabs defaultValue="windows" className="space-y-4">
                <TabsList className="grid w-full grid-cols-2">
                  <TabsTrigger value="windows" className="gap-2"><Monitor className="h-4 w-4" /> Windows</TabsTrigger>
                  <TabsTrigger value="linux" className="gap-2"><Laptop className="h-4 w-4" /> Linux</TabsTrigger>
                </TabsList>

                <TabsContent value="windows" className="mt-0 space-y-4">
                  <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                      <CardHeader className="pb-3">
                        <CardTitle className="text-sm">Windows Installer</CardTitle>
                        <CardDescription>Run the PowerShell installer from an elevated session.</CardDescription>
                      </CardHeader>
                      <CardContent>
                        <CommandCard
                          title="PowerShell installer"
                          description="Downloads the installer script, unblocks it, and registers the service."
                          command={windowsInstallCommand}
                          copyLabel="Copy Windows installer"
                          onCopy={copyText}
                        />
                      </CardContent>
                    </Card>
                    <Card>
                      <CardHeader className="pb-3">
                        <CardTitle className="text-sm">Windows Verification</CardTitle>
                        <CardDescription>Verify the service is installed and the agent log is updating.</CardDescription>
                      </CardHeader>
                      <CardContent>
                        <CommandCard
                          title="Service verification commands"
                          description="Checks the Windows service and tails the local agent log."
                          command={windowsVerifyCommands}
                          copyLabel="Copy Windows verification commands"
                          onCopy={copyText}
                        />
                      </CardContent>
                    </Card>
                  </div>
                </TabsContent>

                <TabsContent value="linux" className="mt-0 space-y-4">
                  <div className="grid gap-4 xl:grid-cols-2">
                    <Card>
                      <CardHeader className="pb-3">
                        <CardTitle className="text-sm">Linux Installer</CardTitle>
                        <CardDescription>Bootstrap and register the Linux agent with the selected token.</CardDescription>
                      </CardHeader>
                      <CardContent className="space-y-4">
                        <CommandCard
                          title="Shell installer one-liner"
                          description="Fetches the install script and registers the host in one command."
                          command={linuxShellInstaller}
                          copyLabel="Copy Linux shell installer"
                          onCopy={copyText}
                        />
                        <CommandCard
                          title=".deb install command"
                          description="Installs the Debian package and registers the agent on Ubuntu or Debian."
                          command={linuxDebInstall}
                          copyLabel="Copy .deb install command"
                          onCopy={copyText}
                        />
                      </CardContent>
                    </Card>
                    <Card>
                      <CardHeader className="pb-3">
                        <CardTitle className="text-sm">Linux Operations</CardTitle>
                        <CardDescription>Package and service controls for Red Hat, Rocky, Alma, or Fedora style systems.</CardDescription>
                      </CardHeader>
                      <CardContent className="space-y-4">
                        <CommandCard
                          title=".rpm install command"
                          description="Installs the RPM package and registers the host after installation."
                          command={linuxRpmInstall}
                          copyLabel="Copy .rpm install command"
                          onCopy={copyText}
                        />
                        <CommandCard
                          title="systemd status/restart commands"
                          description="Checks service health and restarts the agent when configuration changes."
                          command={linuxServiceCommands}
                          copyLabel="Copy systemd commands"
                          onCopy={copyText}
                        />
                      </CardContent>
                    </Card>
                  </div>
                </TabsContent>
              </Tabs>
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <div className="flex items-center justify-between gap-3 flex-wrap">
              <CardTitle className="text-base flex items-center gap-2">
                <Zap className="h-4 w-4 text-yellow-400" /> Registered Hosts
                <Badge variant="secondary">{hosts.length}</Badge>
              </CardTitle>
              <div className="flex items-center gap-4 text-sm">
                <span className="text-success">● Online: {hostCounts.online}</span>
                <span className="text-muted-foreground">● Offline: {hostCounts.offline}</span>
              </div>
            </div>
            <CardDescription>Shared host inventory for every enrolled Windows and Linux agent.</CardDescription>
          </CardHeader>
          <CardContent>
            {hosts.length === 0 ? (
              <p className="text-center text-muted-foreground py-8">No agent hosts registered yet. Install the agent on a Windows or Linux machine to see it appear here.</p>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Host</TableHead>
                    <TableHead>Platform</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>OS</TableHead>
                    <TableHead>CPU</TableHead>
                    <TableHead>Memory</TableHead>
                    <TableHead>Load Avg</TableHead>
                    <TableHead>Temperature</TableHead>
                    <TableHead>Last Seen</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {hosts.map((host) => {
                    const memoryPct = host.memory_total ? Math.round((Number(host.memory_usage) / Number(host.memory_total)) * 100) : null;
                    const platform = normalizePlatform(host.platform ?? host.agent_platform);

                    return (
                      <TableRow key={host.id}>
                        <TableCell>
                          <div>
                            <p className="font-medium text-sm">{host.hostname}</p>
                            <p className="text-xs text-muted-foreground">{host.ip_address || "No IP reported"}</p>
                          </div>
                        </TableCell>
                        <TableCell>
                          <Badge variant={platformBadgeVariant(platform)}>{platformLabel(platform)}</Badge>
                        </TableCell>
                        <TableCell>
                          <Badge className={isRecentlySeen(host.last_seen) ? "bg-success text-success-foreground" : "bg-destructive text-destructive-foreground"}>
                            {isRecentlySeen(host.last_seen) ? "online" : "offline"}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-xs text-muted-foreground">{host.os_version || "—"}</TableCell>
                        <TableCell className="text-sm">{host.cpu_usage != null ? `${host.cpu_usage.toFixed(1)}%` : "—"}</TableCell>
                        <TableCell className="text-sm">{memoryPct != null ? `${memoryPct}%` : "—"}</TableCell>
                        <TableCell className="text-sm">{host.load_average != null ? host.load_average.toFixed(2) : "—"}</TableCell>
                        <TableCell className="text-sm">{host.temperature_celsius != null ? `${host.temperature_celsius.toFixed(1)}°C` : "—"}</TableCell>
                        <TableCell className="text-xs text-muted-foreground">{format(new Date(host.last_seen), "yyyy-MM-dd HH:mm:ss")}</TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              <Terminal className="h-4 w-4 text-cyan-400" /> Step 3 — Validate Agent Check-In
            </CardTitle>
          </CardHeader>
          <CardContent className="text-sm text-muted-foreground space-y-2">
            <p>Select a token above before copying install commands so they are pre-filled with the correct credential.</p>
            <ul className="list-disc ml-5 space-y-1">
              <li>Confirm the host appears in the registered host table with the correct platform badge from <code>host_metrics.platform</code>.</li>
              <li>Use <strong>Host Metrics</strong> to verify CPU, memory, disk, and Linux-only metrics such as load average or temperature.</li>
              <li>Use the endpoint copy action when configuring custom agents, packages, or automation workflows.</li>
            </ul>
          </CardContent>
        </Card>

        <Dialog open={showCreateToken} onOpenChange={setShowCreateToken}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Create Agent Token</DialogTitle>
            </DialogHeader>
            <div className="space-y-2 py-2">
              <Label htmlFor="token-name">Token Name</Label>
              <Input id="token-name" value={newTokenName} onChange={(event) => setNewTokenName(event.target.value)} placeholder="Production Linux Hosts" />
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setShowCreateToken(false)}>Cancel</Button>
              <Button onClick={createToken} disabled={creatingToken || !newTokenName.trim()}>
                {creatingToken ? "Creating..." : "Create Token"}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </AppLayout>
  );
}
