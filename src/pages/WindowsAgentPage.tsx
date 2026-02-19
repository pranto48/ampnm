import { AppLayout } from "@/components/layout/AppLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Server, Download, Terminal, Copy } from "lucide-react";
import { useToast } from "@/hooks/use-toast";

export default function WindowsAgentPage() {
  const { toast } = useToast();

  const batScript = `@echo off
REM AMPNM Windows Monitor Agent - Simple Batch Version
REM Configure your server URL and API token below

set SERVER_URL=https://your-ampnm-server.com
set API_TOKEN=your-api-token-here
set INTERVAL=60

:loop
echo Collecting metrics...

REM Collect CPU usage
for /f "skip=1" %%p in ('wmic cpu get loadpercentage') do (
  set CPU=%%p
  goto :gotcpu
)
:gotcpu

REM Collect Memory usage
for /f "skip=1 tokens=1" %%m in ('wmic OS get FreePhysicalMemory') do (
  set FREE_MEM=%%m
  goto :gotmem
)
:gotmem

echo CPU: %CPU%% | Memory Free: %FREE_MEM%KB
echo Sending to %SERVER_URL%...

curl -s -X POST "%SERVER_URL%/api.php?action=submit_metrics" ^
  -H "Content-Type: application/json" ^
  -H "Authorization: Bearer %API_TOKEN%" ^
  -d "{\\"cpu\\": %CPU%, \\"free_memory_kb\\": %FREE_MEM%}"

timeout /t %INTERVAL% /nobreak >nul
goto loop`;

  const copyScript = () => {
    navigator.clipboard.writeText(batScript);
    toast({ title: "Copied to clipboard" });
  };

  return (
    <AppLayout>
      <div className="space-y-4">
        <div className="flex items-center gap-3">
          <Server className="h-7 w-7 text-primary" />
          <h1 className="text-2xl font-bold tracking-tight">Windows Agent</h1>
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          <Card>
            <CardHeader><CardTitle className="text-base">About the Agent</CardTitle></CardHeader>
            <CardContent className="space-y-3 text-sm text-muted-foreground">
              <p>The AMPNM Windows Monitoring Agent collects system metrics from Windows hosts including:</p>
              <ul className="list-disc list-inside space-y-1">
                <li>CPU usage</li>
                <li>Memory usage</li>
                <li>Disk usage</li>
                <li>Network traffic</li>
                <li>GPU usage (if available)</li>
              </ul>
              <p>The agent submits metrics to your AMPNM server via API using token-based authentication.</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle className="text-base">Installation Options</CardTitle></CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <h3 className="font-medium text-sm">Option 1: PowerShell Installer</h3>
                <p className="text-xs text-muted-foreground">Full installer that sets up the agent as a Windows Service with auto-start.</p>
                <Button variant="outline" size="sm" disabled>
                  <Download className="h-4 w-4 mr-1" /> Download Installer (Docker only)
                </Button>
              </div>
              <div className="space-y-2">
                <h3 className="font-medium text-sm">Option 2: Simple Batch Script</h3>
                <p className="text-xs text-muted-foreground">Lightweight script for quick monitoring setup. Copy and configure below.</p>
                <Button variant="outline" size="sm" onClick={copyScript}>
                  <Copy className="h-4 w-4 mr-1" /> Copy Script
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="text-base flex items-center gap-2"><Terminal className="h-4 w-4" /> Batch Script</CardTitle>
              <Button variant="ghost" size="sm" onClick={copyScript}><Copy className="h-4 w-4 mr-1" /> Copy</Button>
            </div>
          </CardHeader>
          <CardContent>
            <pre className="bg-background rounded-md border border-border p-4 text-xs font-mono overflow-x-auto whitespace-pre max-h-[400px] overflow-y-auto text-muted-foreground">
              {batScript}
            </pre>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
