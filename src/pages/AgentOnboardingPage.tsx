import { useEffect, useMemo, useState } from "react";
import { format } from "date-fns";
import {
  Check,
  Copy,
  Download,
  Key,
  Laptop,
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
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
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
  agent_platform?: string | null;
  platform?: string | null;
  load_average?: number | null;
  temperature_celsius?: number | null;
}

const normalizePlatform = (platform?: string | null): AgentPlatform => {
  const value = platform?.toLowerCase();
  if (value === "windows" || value === "linux") return value;
  return "unknown";
};

function CopyButton({ onClick, label = "Copy" }: { onClick: () => void; label?: string }) {
  return (
    <Button variant="ghost" size="sm" onClick={onClick} aria-label={label}>
      <Copy className="h-3 w-3" />
    </Button>
  );
}

export default function AgentOnboardingPage() {
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
  const linuxShellInstaller = `curl -fsSL ${linuxDownloadBase}/install.sh | sudo bash -s -- --server-url ${agentEndpoint} --agent-token ${selectedToken || "<paste-token-here>"}`;
  const linuxShellInstallerWget = `wget -qO- ${linuxDownloadBase}/install.sh | sudo bash -s -- --server-url ${agentEndpoint} --agent-token ${selectedToken || "<paste-token-here>"}`;
  const linuxDebInstall = `curl -fsSLo /tmp/ampnm-agent.deb ${linuxDownloadBase}/ampnm-agent_latest_amd64.deb && sudo dpkg -i /tmp/ampnm-agent.deb && sudo AMPNM_SERVER_URL=${agentEndpoint} AMPNM_AGENT_TOKEN=${selectedToken || "<paste-token-here>"} /usr/bin/ampnm-agent register`;
  const linuxRpmInstall = `curl -fsSLo /tmp/ampnm-agent.rpm ${linuxDownloadBase}/ampnm-agent-latest.x86_64.rpm && sudo rpm -Uvh /tmp/ampnm-agent.rpm && sudo AMPNM_SERVER_URL=${agentEndpoint} AMPNM_AGENT_TOKEN=${selectedToken || "<paste-token-here>"} /usr/bin/ampnm-agent register`;
  const linuxManualInstall = `mkdir -p /opt/ampnm-agent
curl -fsSLo /opt/ampnm-agent/ampnm-agent ${linuxDownloadBase}/ampnm-agent-linux-amd64
chmod +x /opt/ampnm-agent/ampnm-agent
cat <<'CFG' | sudo tee /etc/ampnm-agent.env
AMPNM_SERVER_URL=${agentEndpoint}
AMPNM_AGENT_TOKEN=${selectedToken || "<paste-token-here>"}
CFG
sudo /opt/ampnm-agent/ampnm-agent install-service
sudo systemctl enable --now ampnm-agent`;

  const windowsInstallCommand = `$p = "$env:TEMP\\AMPNM-Agent-Installer.ps1"
Invoke-WebRequest -Uri "${agentEndpoint}" -OutFile $p
Unblock-File -Path $p
& $p -ServerUrl "${agentEndpoint}" -AgentToken "${selectedToken || "<paste-token-here>"}"`;

  const windowsBatScript = `@echo off
REM AMPNM Windows Monitor Agent - Simple Version
set SERVER_URL=${agentEndpoint}
set AGENT_TOKEN=${selectedToken || "your-agent-token-here"}
set INTERVAL=60

:loop
echo Collecting metrics...
for /f "skip=1" %%%%p in ('wmic cpu get loadpercentage') do (set CPU=%%%%p& goto :gotcpu)
:gotcpu
for /f "skip=1 tokens=1" %%%%m in ('wmic OS get FreePhysicalMemory') do (set FREE_MEM=%%%%m& goto :gotmem)
:gotmem
for /f "tokens=2 delims==" %%%%h in ('wmic computersystem get name /value') do (set HOSTNAME=%%%%h& goto :gothost)
:gothost

echo CPU: %CPU%% | Memory Free: %FREE_MEM%KB | Host: %HOSTNAME%
curl -s -X POST "%SERVER_URL%" ^
  -H "Content-Type: application/json" ^
  -H "x-agent-token: %AGENT_TOKEN%" ^
  -d "{\\"hostname\\": \\"%HOSTNAME%\\", \\"cpu_usage\\": %CPU%, \\"free_memory_kb\\": %FREE_MEM%}"

timeout /t %INTERVAL% /nobreak >nul
goto loop`;

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
              <p className="text-sm text-muted-foreground">Install and register Windows or Linux hosts with a shared token and endpoint workflow.</p>
            </div>
          </div>
          <Button variant="outline" size="sm" onClick={fetchData} disabled={loading}>
            <RefreshCw className={`h-4 w-4 mr-1 ${loading ? "animate-spin" : ""}`} /> Refresh
          </Button>
        </div>

        <div className="grid gap-6 xl:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <Key className="h-4 w-4 text-amber-400" /> Step 1 — Create an Agent Token
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <p className="text-sm text-muted-foreground">
                Tokens authenticate Windows and Linux hosts posting metrics. Create one token and reuse it on as many hosts as you need.
              </p>
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
                              copyText(token.token);
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
                <Download className="h-4 w-4 text-purple-400" /> Step 2 — Install the Agent
              </CardTitle>
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
                  <TabsTrigger value="windows">Windows</TabsTrigger>
                  <TabsTrigger value="linux">Linux</TabsTrigger>
                </TabsList>

                <TabsContent value="windows" className="space-y-4 mt-0">
                  <div className="space-y-2">
                    <h3 className="font-medium text-sm">PowerShell One-Liner</h3>
                    <p className="text-xs text-muted-foreground">Run in PowerShell as Administrator on the Windows host.</p>
                    <div className="bg-muted rounded-md border p-3 relative">
                      <pre className="text-xs font-mono text-foreground whitespace-pre-wrap break-all pr-8">{windowsInstallCommand}</pre>
                      <CopyButton onClick={() => copyText(windowsInstallCommand)} label="Copy Windows one-liner" />
                    </div>
                  </div>

                  <div className="space-y-2">
                    <h3 className="font-medium text-sm">Simple Batch Script</h3>
                    <p className="text-xs text-muted-foreground">Lightweight script for quick monitoring setup.</p>
                    <Button variant="outline" size="sm" onClick={() => copyText(windowsBatScript)}>
                      <Copy className="h-4 w-4 mr-1" /> Copy BAT Script
                    </Button>
                  </div>
                </TabsContent>

                <TabsContent value="linux" className="space-y-4 mt-0">
                  <div className="space-y-2">
                    <div className="flex items-center gap-2">
                      <Laptop className="h-4 w-4 text-emerald-400" />
                      <h3 className="font-medium text-sm">Shell Installer One-Liner</h3>
                    </div>
                    <p className="text-xs text-muted-foreground">Use curl or wget to bootstrap the Linux agent and register it to this endpoint.</p>
                    <div className="bg-muted rounded-md border p-3 space-y-3">
                      <div className="relative">
                        <pre className="text-xs font-mono text-foreground whitespace-pre-wrap break-all pr-8">{linuxShellInstaller}</pre>
                        <CopyButton onClick={() => copyText(linuxShellInstaller, "Shell installer copied")} label="Copy shell installer" />
                      </div>
                      <div>
                        <p className="text-[11px] uppercase tracking-wide text-muted-foreground mb-1">wget alternative</p>
                        <pre className="text-xs font-mono text-foreground whitespace-pre-wrap break-all">{linuxShellInstallerWget}</pre>
                      </div>
                    </div>
                  </div>

                  <div className="space-y-2">
                    <h3 className="font-medium text-sm">Manual Install</h3>
                    <div className="bg-muted rounded-md border p-3">
                      <pre className="text-xs font-mono text-foreground whitespace-pre-wrap break-all">{linuxManualInstall}</pre>
                    </div>
                  </div>

                  <div className="grid gap-4 lg:grid-cols-2">
                    <div className="space-y-2">
                      <h3 className="font-medium text-sm">Package Install — .deb</h3>
                      <div className="bg-muted rounded-md border p-3 relative">
                        <pre className="text-xs font-mono text-foreground whitespace-pre-wrap break-all pr-8">{linuxDebInstall}</pre>
                        <CopyButton onClick={() => copyText(linuxDebInstall, ".deb install command copied")} label="Copy .deb install command" />
                      </div>
                    </div>
                    <div className="space-y-2">
                      <h3 className="font-medium text-sm">Package Install — .rpm</h3>
                      <div className="bg-muted rounded-md border p-3 relative">
                        <pre className="text-xs font-mono text-foreground whitespace-pre-wrap break-all pr-8">{linuxRpmInstall}</pre>
                        <CopyButton onClick={() => copyText(linuxRpmInstall, ".rpm install command copied")} label="Copy .rpm install command" />
                      </div>
                    </div>
                  </div>

                  <div className="space-y-2">
                    <h3 className="font-medium text-sm">Service Management</h3>
                    <div className="bg-muted rounded-md border p-3">
                      <pre className="text-xs font-mono text-foreground whitespace-pre-wrap break-all">sudo systemctl status ampnm-agent
sudo systemctl restart ampnm-agent
sudo journalctl -u ampnm-agent -n 100 -f</pre>
                    </div>
                  </div>
                </TabsContent>
              </Tabs>
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="text-base flex items-center gap-2">
                <Zap className="h-4 w-4 text-yellow-400" /> Registered Agent Hosts
                <Badge variant="secondary">{hosts.length}</Badge>
              </CardTitle>
              <div className="flex items-center gap-4 text-sm">
                <span className="text-success">● Online: {hostCounts.online}</span>
                <span className="text-muted-foreground">● Offline: {hostCounts.offline}</span>
              </div>
            </div>
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
                    const platform = normalizePlatform(host.agent_platform ?? host.platform);
                    return (
                      <TableRow key={host.id}>
                        <TableCell>
                          <div>
                            <p className="font-medium text-sm">{host.hostname}</p>
                            <p className="text-xs text-muted-foreground">{host.ip_address || "No IP reported"}</p>
                          </div>
                        </TableCell>
                        <TableCell>
                          <Badge variant={platform === "unknown" ? "outline" : "secondary"}>{platform}</Badge>
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
              <li>Confirm the host appears in the registered host table with the correct platform badge.</li>
              <li>Use <strong>Host Metrics</strong> to verify CPU, memory, disk, and Linux-only metrics such as load average or temperature.</li>
              <li>Use the raw endpoint copy action when configuring custom agents, scripts, or packages.</li>
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
