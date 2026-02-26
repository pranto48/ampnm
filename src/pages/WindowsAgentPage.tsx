import { useState, useEffect, useRef } from "react";
import { supabase } from "@/integrations/supabase/client";
import { AppLayout } from "@/components/layout/AppLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Server, Download, Terminal, Copy, Key, Plus, Trash2, RefreshCw, Check, X, Zap } from "lucide-react";
import { useToast } from "@/hooks/use-toast";
import { format } from "date-fns";
import { useAuth } from "@/hooks/useAuth";

export default function WindowsAgentPage() {
  const { toast } = useToast();
  const { user } = useAuth();
  const [tokens, setTokens] = useState<any[]>([]);
  const [hosts, setHosts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [showCreateToken, setShowCreateToken] = useState(false);
  const [newTokenName, setNewTokenName] = useState("");
  const [creatingToken, setCreatingToken] = useState(false);

  const projectId = import.meta.env.VITE_SUPABASE_PROJECT_ID || "";
  const agentEndpoint = `https://${projectId}.supabase.co/functions/v1/agent-metrics`;

  const fetchData = async () => {
    setLoading(true);
    const [tokensRes, hostsRes] = await Promise.all([
      supabase.from("agent_tokens").select("*").order("created_at", { ascending: false }),
      supabase.from("host_metrics").select("*").order("last_seen", { ascending: false }),
    ]);
    setTokens(tokensRes.data ?? []);
    setHosts(hostsRes.data ?? []);
    setLoading(false);
  };

  useEffect(() => { fetchData(); }, []);

  const generateToken = () => {
    const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    let result = "ampnm_";
    for (let i = 0; i < 40; i++) result += chars.charAt(Math.floor(Math.random() * chars.length));
    return result;
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
    if (error) toast({ title: "Error", description: error.message, variant: "destructive" });
    else fetchData();
  };

  const toggleToken = async (id: string, enabled: boolean) => {
    await supabase.from("agent_tokens").update({ enabled: !enabled }).eq("id", id);
    fetchData();
  };

  const copyText = (text: string) => {
    navigator.clipboard.writeText(text);
    toast({ title: "Copied to clipboard" });
  };

  const [selectedToken, setSelectedToken] = useState("");

  const installCommand = `$p = "$env:TEMP\\AMPNM-Agent-Installer.ps1"
Invoke-WebRequest -Uri "${agentEndpoint}" -OutFile $p
Unblock-File -Path $p
& $p -ServerUrl "${agentEndpoint}" -AgentToken "${selectedToken || "<paste-token-here>"}"`;

  const batScript = `@echo off
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

  const hostStatusColor = (status: string) => {
    switch (status) {
      case "online": return "bg-success text-success-foreground";
      case "offline": return "bg-destructive text-destructive-foreground";
      default: return "";
    }
  };

  const isRecentlySeen = (lastSeen: string) => {
    const diff = Date.now() - new Date(lastSeen).getTime();
    return diff < 120000; // 2 minutes
  };

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between flex-wrap gap-2">
          <div className="flex items-center gap-3">
            <Server className="h-7 w-7 text-primary" />
            <h1 className="text-2xl font-bold tracking-tight">Windows Agent Onboarding</h1>
          </div>
          <Button variant="outline" size="sm" onClick={fetchData} disabled={loading}>
            <RefreshCw className={`h-4 w-4 mr-1 ${loading ? "animate-spin" : ""}`} /> Refresh
          </Button>
        </div>

        <div className="grid gap-6 xl:grid-cols-2">
          {/* Step 1: Token */}
          <Card>
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <Key className="h-4 w-4 text-amber-400" /> Step 1 — Create an Agent Token
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <p className="text-sm text-muted-foreground">
                Tokens authenticate Windows hosts posting metrics. Create one token and reuse it on as many hosts as you need.
              </p>
              <div className="flex gap-2">
                <Button size="sm" onClick={() => setShowCreateToken(true)}>
                  <Plus className="h-4 w-4 mr-1" /> Create Token
                </Button>
              </div>
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
                    {tokens.map((t) => (
                      <TableRow key={t.id}>
                        <TableCell className="font-medium text-sm">{t.name}</TableCell>
                        <TableCell>
                          <button onClick={() => { copyText(t.token); setSelectedToken(t.token); }} className="text-xs font-mono text-primary hover:underline cursor-pointer">
                            {t.token.substring(0, 12)}...
                          </button>
                        </TableCell>
                        <TableCell>
                          <Badge variant={t.enabled ? "default" : "secondary"}>
                            {t.enabled ? "Active" : "Disabled"}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          <div className="flex gap-1">
                            <Button variant="ghost" size="sm" onClick={() => toggleToken(t.id, t.enabled)}>
                              {t.enabled ? <X className="h-3 w-3" /> : <Check className="h-3 w-3" />}
                            </Button>
                            <Button variant="ghost" size="sm" className="text-destructive" onClick={() => deleteToken(t.id)}>
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

          {/* Step 2: Download / Install */}
          <Card>
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <Download className="h-4 w-4 text-purple-400" /> Step 2 — Install the Agent
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <h3 className="font-medium text-sm">Option 1: PowerShell One-Liner</h3>
                <p className="text-xs text-muted-foreground">Run in PowerShell as Administrator on the Windows host.</p>
                <div className="bg-muted rounded-md border p-3 relative">
                  <pre className="text-xs font-mono text-foreground whitespace-pre-wrap break-all pr-8">{installCommand}</pre>
                  <Button variant="ghost" size="sm" className="absolute top-2 right-2" onClick={() => copyText(installCommand)}>
                    <Copy className="h-3 w-3" />
                  </Button>
                </div>
              </div>

              <div className="space-y-2">
                <h3 className="font-medium text-sm">Option 2: Simple Batch Script</h3>
                <p className="text-xs text-muted-foreground">Lightweight script for quick monitoring setup.</p>
                <Button variant="outline" size="sm" onClick={() => copyText(batScript)}>
                  <Copy className="h-4 w-4 mr-1" /> Copy BAT Script
                </Button>
              </div>

              <div className="space-y-1">
                <h3 className="font-medium text-sm">Agent API Endpoint</h3>
                <div className="flex items-center gap-2">
                  <code className="text-xs font-mono text-primary bg-muted px-2 py-1 rounded break-all flex-1">{agentEndpoint}</code>
                  <Button variant="ghost" size="sm" onClick={() => copyText(agentEndpoint)}>
                    <Copy className="h-3 w-3" />
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Step 3: Agent Hosts / Status */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="text-base flex items-center gap-2">
                <Zap className="h-4 w-4 text-yellow-400" /> Registered Agent Hosts
                <Badge variant="secondary">{hosts.length}</Badge>
              </CardTitle>
              <div className="flex items-center gap-4 text-sm">
                <span className="text-success">● Online: {hosts.filter(h => isRecentlySeen(h.last_seen)).length}</span>
                <span className="text-muted-foreground">● Offline: {hosts.filter(h => !isRecentlySeen(h.last_seen)).length}</span>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            {hosts.length === 0 ? (
              <p className="text-center text-muted-foreground py-8">No agent hosts registered yet. Install the agent on a Windows machine to see it appear here.</p>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Hostname</TableHead>
                    <TableHead>IP Address</TableHead>
                    <TableHead>CPU</TableHead>
                    <TableHead>Memory</TableHead>
                    <TableHead>Disk</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>First Seen</TableHead>
                    <TableHead>Last Seen</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {hosts.map((h) => {
                    const online = isRecentlySeen(h.last_seen);
                    return (
                      <TableRow key={h.id}>
                        <TableCell className="font-medium">{h.hostname}</TableCell>
                        <TableCell className="text-sm">{h.ip_address || "—"}</TableCell>
                        <TableCell className="text-sm">{h.cpu_usage != null ? `${Number(h.cpu_usage).toFixed(0)}%` : "—"}</TableCell>
                        <TableCell className="text-sm">{h.memory_usage != null ? `${Number(h.memory_usage).toFixed(0)}%` : "—"}</TableCell>
                        <TableCell className="text-sm">{h.disk_usage != null ? `${Number(h.disk_usage).toFixed(0)}%` : "—"}</TableCell>
                        <TableCell>
                          <Badge className={online ? "bg-success text-success-foreground" : "bg-muted text-muted-foreground"}>
                            {online ? "Online" : "Offline"}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-xs text-muted-foreground">{format(new Date(h.first_seen), "PP p")}</TableCell>
                        <TableCell className="text-xs text-muted-foreground">{format(new Date(h.last_seen), "PP p")}</TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>

        {/* Common Issues */}
        <div className="grid md:grid-cols-2 gap-4">
          <Card className="border-success/30 bg-success/5">
            <CardContent className="pt-4 space-y-2">
              <h3 className="font-medium text-sm flex items-center gap-2"><Check className="h-4 w-4 text-success" /> Verify Installation</h3>
              <ul className="text-sm text-muted-foreground space-y-1">
                <li>• New hosts appear in the table above within ~60 seconds</li>
                <li>• Check Host Metrics page for detailed monitoring</li>
                <li>• Agent API health: <code className="text-xs text-primary">{agentEndpoint}/health</code></li>
              </ul>
            </CardContent>
          </Card>
          <Card className="border-warning/30 bg-warning/5">
            <CardContent className="pt-4 space-y-2">
              <h3 className="font-medium text-sm flex items-center gap-2 text-warning">⚠ Common Issues</h3>
              <ul className="text-sm text-muted-foreground space-y-1">
                <li>• Must run PowerShell as Administrator</li>
                <li>• Windows Firewall / outbound HTTPS blocked</li>
                <li>• Token disabled or copied incorrectly</li>
                <li>• Agent needs internet access to reach the API</li>
              </ul>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Create Token Dialog */}
      <Dialog open={showCreateToken} onOpenChange={setShowCreateToken}>
        <DialogContent>
          <DialogHeader><DialogTitle>Create Agent Token</DialogTitle></DialogHeader>
          <div className="space-y-3">
            <div>
              <Label>Token Name</Label>
              <Input placeholder="e.g. Production Servers" value={newTokenName} onChange={(e) => setNewTokenName(e.target.value)} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowCreateToken(false)}>Cancel</Button>
            <Button onClick={createToken} disabled={creatingToken || !newTokenName.trim()}>
              {creatingToken ? "Creating..." : "Create & Copy Token"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}
