"use client";

import { useState } from "react";
import { 
  BookOpen, 
  Key, 
  Globe, 
  ShieldAlert, 
  ChevronRight, 
  Terminal, 
  Server, 
  Activity, 
  Settings, 
  Bell, 
  Database, 
  RefreshCw, 
  ShieldCheck,
  Copy,
  Check
} from "lucide-react";

export default function DocsPage() {
  const [copiedIndex, setCopiedIndex] = useState<string | null>(null);

  const copyToClipboard = (text: string, id: string) => {
    navigator.clipboard.writeText(text);
    setCopiedIndex(id);
    setTimeout(() => setCopiedIndex(null), 2000);
  };

  const codeBlocks = {
    pullStable: "docker pull itsupportbd/ampnm:V1.11",
    pullLatest: "docker pull itsupportbd/ampnm:latest",
    runLinux: `docker run -d \\
  --name ampnm_server \\
  -p 2266:2266 \\
  -v /var/run/docker.sock:/var/run/docker.sock \\
  -v ampnm_uploads:/var/www/html/uploads \\
  --restart unless-stopped \\
  itsupportbd/ampnm:latest`,
    runWindows: `docker run -d \`
  --name ampnm_server \`
  -p 2266:2266 \`
  -v /var/run/docker.sock:/var/run/docker.sock \`
  -v ampnm_uploads:/var/www/html/uploads \`
  --restart unless-stopped \`
  itsupportbd/ampnm:latest`,
    agentBatch: `@echo off
set SERVER_URL=http://<YOUR_DOCKER_HOST>:2266/api/agent/windows-metrics
set AGENT_TOKEN=your-random-token-here

powershell -NoProfile -Command "
$metrics = @{
  hostname = [System.Net.Dns]::GetHostName()
  cpu = (Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average).Average
  ram = [math]::round(((Get-CimInstance Win32_OperatingSystem).TotalVisibleMemorySize - (Get-CimInstance Win32_OperatingSystem).FreePhysicalMemory) / (Get-CimInstance Win32_OperatingSystem).TotalVisibleMemorySize * 100, 2)
  disk = [math]::round(((Get-CimInstance Win32_LogicalDisk -Filter 'DeviceID=\"C:\"').Size - (Get-CimInstance Win32_LogicalDisk -Filter 'DeviceID=\"C:\"').FreeSpace) / (Get-CimInstance Win32_LogicalDisk -Filter 'DeviceID=\"C:\"').Size * 100, 2)
}
$json = ConvertTo-Json $metrics
Invoke-RestMethod -Uri $SERVER_URL -Method Post -Body $json -Headers @{ 'X-Agent-Token' = $AGENT_TOKEN } -ContentType 'application/json'
"`,
    verifyLicense: `curl -X POST https://portal.itsupport.com.bd/api/license/verify \\
  -H "Content-Type: application/json" \\
  -d '{"key": "AMPNM-DEVC-8F2B-9A4E-4321"}'`
  };

  return (
    <div className="py-12 bg-slate-50 dark:bg-zinc-950 transition-colors duration-300 relative overflow-hidden flex-1">
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[400px] bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-100/40 via-transparent to-transparent dark:from-blue-900/10 dark:via-zinc-950 dark:to-zinc-950 pointer-events-none -z-10" />

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        {/* Title */}
        <div className="text-center space-y-4">
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 text-blue-500 dark:text-blue-400 text-xs font-semibold tracking-wide">
            <BookOpen size={14} /> Official Documentation
          </div>
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-zinc-900 dark:text-white leading-tight">
            AMPNM Deployment & <br />
            <span className="bg-gradient-to-r from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 bg-clip-text text-transparent">
              Operations Manual
            </span>
          </h1>
          <p className="max-w-2xl mx-auto text-zinc-500 dark:text-zinc-400 text-sm sm:text-base font-medium">
            Learn how to install, configure, manage, and scale the Advanced Multi-Protocol Network Monitor (AMPNM) across Linux and Windows environments.
          </p>
        </div>

        {/* Docs Articles Layout */}
        <div className="grid gap-8 lg:grid-cols-12">
          {/* Quick links Sidebar */}
          <aside className="lg:col-span-3 lg:sticky lg:top-24 h-fit space-y-6">
            <div className="bg-white dark:bg-zinc-900/50 backdrop-blur-md rounded-2xl p-6 border border-zinc-200 dark:border-zinc-800/80 shadow-sm">
              <h4 className="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest mb-4">Chapters</h4>
              <nav className="flex flex-col gap-1 font-semibold text-xs sm:text-sm">
                <a href="#intro" className="flex items-center gap-2 py-2 text-blue-600 dark:text-blue-400 border-l-2 border-blue-500 pl-3">
                  Introduction
                </a>
                <a href="#install-docker" className="flex items-center gap-2 py-2 text-zinc-600 dark:text-zinc-400 hover:text-blue-500 dark:hover:text-blue-400 pl-3 border-l border-zinc-200 dark:border-zinc-800">
                  Docker Installation
                </a>
                <a href="#windows-setup" className="flex items-center gap-2 py-2 text-zinc-600 dark:text-zinc-400 hover:text-blue-500 dark:hover:text-blue-400 pl-3 border-l border-zinc-200 dark:border-zinc-800">
                  Windows Setup
                </a>
                <a href="#app-mgmt" className="flex items-center gap-2 py-2 text-zinc-600 dark:text-zinc-400 hover:text-blue-500 dark:hover:text-blue-400 pl-3 border-l border-zinc-200 dark:border-zinc-800">
                  App Management
                </a>
                <a href="#adding-devices" className="flex items-center gap-2 py-2 text-zinc-600 dark:text-zinc-400 hover:text-blue-500 dark:hover:text-blue-400 pl-3 border-l border-zinc-200 dark:border-zinc-800">
                  Adding Devices
                </a>
                <a href="#map-topology" className="flex items-center gap-2 py-2 text-zinc-600 dark:text-zinc-400 hover:text-blue-500 dark:hover:text-blue-400 pl-3 border-l border-zinc-200 dark:border-zinc-800">
                  Network Map
                </a>
                <a href="#agent-token" className="flex items-center gap-2 py-2 text-zinc-600 dark:text-zinc-400 hover:text-blue-500 dark:hover:text-blue-400 pl-3 border-l border-zinc-200 dark:border-zinc-800">
                  Telemetry Agent
                </a>
                <a href="#notifications" className="flex items-center gap-2 py-2 text-zinc-600 dark:text-zinc-400 hover:text-blue-500 dark:hover:text-blue-400 pl-3 border-l border-zinc-200 dark:border-zinc-800">
                  Notification System
                </a>
                <a href="#updates" className="flex items-center gap-2 py-2 text-zinc-600 dark:text-zinc-400 hover:text-blue-500 dark:hover:text-blue-400 pl-3 border-l border-zinc-200 dark:border-zinc-800">
                  Updates & Upgrades
                </a>
                <a href="#backups" className="flex items-center gap-2 py-2 text-zinc-600 dark:text-zinc-400 hover:text-blue-500 dark:hover:text-blue-400 pl-3 border-l border-zinc-200 dark:border-zinc-800">
                  System Backups
                </a>
                <a href="#api" className="flex items-center gap-2 py-2 text-zinc-600 dark:text-zinc-400 hover:text-blue-500 dark:hover:text-blue-400 pl-3 border-l border-zinc-200 dark:border-zinc-800">
                  REST API & Firewalls
                </a>
              </nav>
            </div>
          </aside>

          {/* Core documentation text */}
          <main className="lg:col-span-9 space-y-16 text-zinc-600 dark:text-zinc-300 text-sm leading-relaxed">
            
            {/* Section 1: Intro */}
            <section id="intro" className="space-y-4 scroll-mt-24">
              <h3 className="text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <BookOpen className="text-blue-500" size={24} />
                Introduction
              </h3>
              <p>
                AMPNM (Advanced Multi-Protocol Network Monitor) is a robust network topology mapping and telemetry platform designed to monitor routing nodes, servers, switch stacks, and client endpoints in real-time. By combining lightweight ICMP and TCP port scanners with dedicated system monitoring agents, AMPNM delivers deep diagnostic insights inside a single unified dashboard.
              </p>
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
                <div className="p-4 rounded-xl bg-white dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 shadow-sm space-y-2">
                  <Activity className="text-cyan-500" size={20} />
                  <h5 className="font-bold text-zinc-900 dark:text-white text-xs">Real-Time Telemetry</h5>
                  <p className="text-xs text-zinc-500 dark:text-zinc-400">Stream hardware statistics directly from Windows nodes using the telemetry helper daemon.</p>
                </div>
                <div className="p-4 rounded-xl bg-white dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 shadow-sm space-y-2">
                  <Globe className="text-indigo-500" size={20} />
                  <h5 className="font-bold text-zinc-900 dark:text-white text-xs">Topology Mapping</h5>
                  <p className="text-xs text-zinc-500 dark:text-zinc-400">Interactive Canvas UI utilizing Vis.js to draw customized connections and animations between nodes.</p>
                </div>
                <div className="p-4 rounded-xl bg-white dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 shadow-sm space-y-2">
                  <ShieldCheck className="text-emerald-500" size={20} />
                  <h5 className="font-bold text-zinc-900 dark:text-white text-xs">License Guard</h5>
                  <p className="text-xs text-zinc-500 dark:text-zinc-400">Secure automated offline-first license verification through the official licensing gateway portal.</p>
                </div>
              </div>
            </section>

            {/* Section 2: Docker Installation */}
            <section id="install-docker" className="space-y-6 scroll-mt-24">
              <h3 className="text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <Server className="text-blue-500" size={24} />
                Docker Hub Installation (Linux Server)
              </h3>
              <p>
                The primary way to deploy AMPNM is using the official pre-built image from Docker Hub. The environment requires Docker Engine and is optimized to run as a non-privileged user within the host container, with self-healing Docker socket authorization for updates.
              </p>

              {/* Step 1: Pull */}
              <div className="space-y-4">
                <h4 className="text-base font-bold text-zinc-800 dark:text-zinc-200">1. Pull the Docker Image</h4>
                
                {/* V1.11 Pull Command */}
                <div className="space-y-1.5">
                  <span className="block text-[10px] uppercase font-bold text-zinc-500 dark:text-zinc-400 tracking-wider">Version 1.11 (Stable Release)</span>
                  <div className="relative group">
                    <div className="absolute right-3 top-3 opacity-80 hover:opacity-100 transition-opacity">
                      <button 
                        onClick={() => copyToClipboard(codeBlocks.pullStable, 'pullStable')} 
                        className="p-1.5 rounded-lg bg-zinc-805 hover:bg-zinc-705 text-zinc-400 hover:text-white transition-colors duration-150 cursor-pointer"
                        title="Copy command"
                      >
                        {copiedIndex === 'pullStable' ? <Check size={14} className="text-emerald-500" /> : <Copy size={14} />}
                      </button>
                    </div>
                    <pre className="p-4 rounded-xl bg-zinc-950 font-mono text-[11px] sm:text-xs text-zinc-200 text-left overflow-x-auto select-all border border-zinc-800">
                      {codeBlocks.pullStable}
                    </pre>
                  </div>
                </div>

                {/* Latest Pull Command */}
                <div className="space-y-1.5">
                  <span className="block text-[10px] uppercase font-bold text-zinc-500 dark:text-zinc-400 tracking-wider">Latest Build (Development / Rolling)</span>
                  <div className="relative group">
                    <div className="absolute right-3 top-3 opacity-80 hover:opacity-100 transition-opacity">
                      <button 
                        onClick={() => copyToClipboard(codeBlocks.pullLatest, 'pullLatest')} 
                        className="p-1.5 rounded-lg bg-zinc-805 hover:bg-zinc-705 text-zinc-400 hover:text-white transition-colors duration-150 cursor-pointer"
                        title="Copy command"
                      >
                        {copiedIndex === 'pullLatest' ? <Check size={14} className="text-emerald-500" /> : <Copy size={14} />}
                      </button>
                    </div>
                    <pre className="p-4 rounded-xl bg-zinc-950 font-mono text-[11px] sm:text-xs text-zinc-200 text-left overflow-x-auto select-all border border-zinc-800">
                      {codeBlocks.pullLatest}
                    </pre>
                  </div>
                </div>
              </div>

              {/* Step 2: Run */}
              <div className="space-y-3">
                <h4 className="text-base font-bold text-zinc-800 dark:text-zinc-200">2. Run the Container</h4>
                <p className="text-xs text-zinc-500 dark:text-zinc-400">
                  Run the container on port <code>2266</code>. Note the Docker socket bind mount (<code>-v /var/run/docker.sock:/var/run/docker.sock</code>) which is necessary if you intend to use the live Docker Hub update feature directly from the admin panel.
                </p>
                <div className="relative group">
                  <div className="absolute right-3 top-3 opacity-80 hover:opacity-100 transition-opacity">
                    <button 
                      onClick={() => copyToClipboard(codeBlocks.runLinux, 'runLinux')} 
                      className="p-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-zinc-400 hover:text-white"
                      title="Copy command"
                    >
                      {copiedIndex === 'runLinux' ? <Check size={14} className="text-emerald-500" /> : <Copy size={14} />}
                    </button>
                  </div>
                  <pre className="p-4 rounded-xl bg-zinc-950 font-mono text-[11px] sm:text-xs text-zinc-200 text-left overflow-x-auto select-all border border-zinc-800">
                    {codeBlocks.runLinux}
                  </pre>
                </div>
              </div>
            </section>

            {/* Section 3: Windows Setup */}
            <section id="windows-setup" className="space-y-4 scroll-mt-24">
              <h3 className="text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <Terminal className="text-blue-500" size={24} />
                Windows Docker Setup
              </h3>
              <p>
                To run AMPNM on Windows servers or desktops, install Docker Desktop with the WSL 2 backend. Launch a PowerShell window as Administrator and run:
              </p>
              <div className="relative group">
                <div className="absolute right-3 top-3 opacity-80 hover:opacity-100 transition-opacity">
                  <button 
                    onClick={() => copyToClipboard(codeBlocks.runWindows, 'runWindows')} 
                    className="p-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-zinc-400 hover:text-white"
                    title="Copy command"
                  >
                    {copiedIndex === 'runWindows' ? <Check size={14} className="text-emerald-500" /> : <Copy size={14} />}
                  </button>
                </div>
                <pre className="p-4 rounded-xl bg-zinc-950 font-mono text-[11px] sm:text-xs text-zinc-200 text-left overflow-x-auto select-all border border-zinc-800">
                  {codeBlocks.runWindows}
                </pre>
              </div>
            </section>

            {/* Section 4: App Management */}
            <section id="app-mgmt" className="space-y-4 scroll-mt-24">
              <h3 className="text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <Settings className="text-blue-500" size={24} />
                App Management & Administration
              </h3>
              <p>
                Once deployed, visit <code>http://your-server-ip:2266</code> to access the dashboard.
              </p>
              <ul className="list-disc pl-5 space-y-2">
                <li><strong>First-time Credentials:</strong> The default administrator login is <code>admin@itsupport.com.bd</code> / <code>admin123</code> (change this immediately under users profile).</li>
                <li><strong>Licensing:</strong> Go to the License panel and input your key purchased from <a href="https://portal.itsupport.com.bd" className="text-blue-500 dark:text-blue-400 hover:underline">portal.itsupport.com.bd</a>. The software allows a 30-day offline grace period.</li>
                <li><strong>User Management:</strong> Administrators can add new users and group them into isolated tenant groups, letting coworkers collaborate on specific network maps while hiding others.</li>
              </ul>
            </section>

            {/* Section 5: Adding Devices */}
            <section id="adding-devices" className="space-y-4 scroll-mt-24">
              <h3 className="text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <Activity className="text-blue-500" size={24} />
                Adding Devices & Monitoring Methods
              </h3>
              <p>
                Under the <strong>Devices</strong> section, click <strong>Add Device</strong> to register a new host:
              </p>
              <div className="space-y-4 mt-4">
                <div className="p-4 rounded-xl bg-white dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800">
                  <h5 className="font-bold text-zinc-900 dark:text-white mb-2">ICMP Ping Checks</h5>
                  <p className="text-xs">Sends periodic ping echoes to monitor host availability, latency metrics, and packet loss. Customizable latency thresholds dynamically label hosts as Online, Warning, or Critical.</p>
                </div>
                <div className="p-4 rounded-xl bg-white dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800">
                  <h5 className="font-bold text-zinc-900 dark:text-white mb-2">TCP Port Checks</h5>
                  <p className="text-xs">Validates service state on specific TCP ports (e.g. port 80 for HTTP, 443 for HTTPS, 22 for SSH, 8728 for MikroTik API). Useful when hosts discard ICMP packets due to firewall rules.</p>
                </div>
                <div className="p-4 rounded-xl bg-white dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800">
                  <h5 className="font-bold text-zinc-900 dark:text-white mb-2">Conflict Avoidance</h5>
                  <p className="text-xs">The duplicate detector validates hostname and IP uniqueness against all active hosts in your group during registration or updates to prevent duplicate polling data.</p>
                </div>
              </div>
            </section>

            {/* Section 6: Network Map */}
            <section id="map-topology" className="space-y-4 scroll-mt-24">
              <h3 className="text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <Globe className="text-blue-500" size={24} />
                Interactive Network Map Topology
              </h3>
              <p>
                The map topology utilizes HTML5 Canvas rendering to layout devices dynamically. You can:
              </p>
              <ul className="list-disc pl-5 space-y-2">
                <li><strong>Drag-and-Drop Positioning:</strong> Left-click and drag devices to customize their visual placement. Positions are saved automatically in real-time.</li>
                <li><strong>Edge Connections:</strong> Link devices together. Edit edge attributes to add custom labels, connection styles (solid, dashed, dotted), color definitions, and arrowheads.</li>
                <li><strong>Universal Line Thickness:</strong> Manage default connection widths globally from the Map Settings form. The slider updates all uncustomized links dynamically.</li>
                <li><strong>Scrollable Settings:</strong> The Settings panel adjusts to fit mobile or low-resolution screens with smooth vertical scrolling to guarantee usability.</li>
                <li><strong>Flow Animations:</strong> Enable packet flow animations on specific lines to visually represent active data throughput.</li>
              </ul>
            </section>

            {/* Section 7: Telemetry Agent */}
            <section id="agent-token" className="space-y-4 scroll-mt-24">
              <h3 className="text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <Key className="text-blue-500" size={24} />
                Windows Telemetry Agent & Token
              </h3>
              <p>
                To monitor hardware performance (CPU, Memory, Disk) on remote Windows hosts, deploy the lightweight PowerShell collector batch script.
              </p>
              <p className="text-xs text-zinc-500 dark:text-zinc-400">
                Create a batch script on the Windows host (e.g. <code>C:\ampnm-agent.bat</code>) and configure it to run periodically in Windows Task Scheduler:
              </p>
              <div className="relative group">
                <div className="absolute right-3 top-3 opacity-80 hover:opacity-100 transition-opacity">
                  <button 
                    onClick={() => copyToClipboard(codeBlocks.agentBatch, 'agentBatch')} 
                    className="p-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-zinc-400 hover:text-white"
                    title="Copy command"
                  >
                    {copiedIndex === 'agentBatch' ? <Check size={14} className="text-emerald-500" /> : <Copy size={14} />}
                  </button>
                </div>
                <pre className="p-4 rounded-xl bg-zinc-950 font-mono text-[11px] sm:text-xs text-zinc-200 text-left overflow-x-auto select-all border border-zinc-800 max-h-[350px]">
                  {codeBlocks.agentBatch}
                </pre>
              </div>
              <p className="text-xs text-zinc-500 dark:text-zinc-400 mt-2">
                Ensure the <code>X-Agent-Token</code> header matches the token configured inside the AMPNM server environment. This token restricts endpoint writes to authenticated agents only.
              </p>
            </section>

            {/* Section 8: Notification System */}
            <section id="notifications" className="space-y-4 scroll-mt-24">
              <h3 className="text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <Bell className="text-blue-500" size={24} />
                Notifications System
              </h3>
              <p>
                AMPNM alerts administrators immediately when devices transition between status states.
              </p>
              <ul className="list-disc pl-5 space-y-2">
                <li><strong>SMTP Integration:</strong> Set up outgoing SMTP servers (e.g. Gmail SMTP using App Passwords, Office 365, Mailgun) directly from the settings panel.</li>
                <li><strong>Rule Subscriptions:</strong> Subscribe users to individual hosts or global notifications. Emails are dispatched automatically on Online / Warning / Critical / Offline transitions.</li>
              </ul>
            </section>

            {/* Section 9: Updates & Upgrades */}
            <section id="updates" className="space-y-4 scroll-mt-24">
              <h3 className="text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <RefreshCw className="text-blue-500" size={24} />
                Dual Update Systems (Git vs Docker Hub)
              </h3>
              <p>
                Keep your application up to date using the dual updater panel:
              </p>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                <div className="p-5 rounded-2xl bg-white dark:bg-zinc-900/40 border border-zinc-200 dark:border-zinc-800 space-y-3">
                  <div className="flex items-center gap-2 font-bold text-zinc-950 dark:text-white">
                    <BookOpen className="text-cyan-500" size={18} /> Git Update
                  </div>
                  <p className="text-xs text-zinc-500 dark:text-zinc-400">Runs <code>git pull</code> locally inside the code directory. Perfect for updating user-facing frontend code templates and scripts without restarting the core server daemon or Apache service.</p>
                </div>
                <div className="p-5 rounded-2xl bg-white dark:bg-zinc-900/40 border border-zinc-200 dark:border-zinc-800 space-y-3">
                  <div className="flex items-center gap-2 font-bold text-zinc-950 dark:text-white">
                    <Server className="text-indigo-500" size={18} /> Docker Hub Update
                  </div>
                  <p className="text-xs text-zinc-500 dark:text-zinc-400">Communicates with the host's Docker socket to pull the latest image layers from Docker Hub, reconstructs run parameters (ports, mounts, envs) dynamically, and cleanly recreates the container on the fly.</p>
                </div>
              </div>
            </section>

            {/* Section 10: System Backups */}
            <section id="backups" className="space-y-4 scroll-mt-24">
              <h3 className="text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <Database className="text-blue-500" size={24} />
                Automated System Backups (FTP & NAS)
              </h3>
              <p>
                Prevent data loss by configuring recurring cron schedules to back up the SQLite/MariaDB database schema and all uploaded maps/icons.
              </p>
              <ul className="list-disc pl-5 space-y-2">
                <li><strong>Local NAS Backups:</strong> Dump compressed tarballs directly to directories mounted locally on the container (e.g. <code>/backups/nas</code>).</li>
                <li><strong>FTP Server Backups:</strong> Securely upload archives to external network storage using passive mode (PASV). The compiled Docker image features native FTP support to avoid dependency failures.</li>
              </ul>
            </section>

            {/* Section 11: REST API */}
            <section id="api" className="space-y-6 scroll-mt-24">
              <h3 className="text-2xl font-extrabold text-zinc-900 dark:text-white flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <Key className="text-blue-500" size={24} />
                Integration REST API
              </h3>
              <p>
                Verify license keys or telemetry health programmatically from third-party clusters:
              </p>

              <div className="space-y-2 text-xs sm:text-sm">
                <p className="font-bold text-zinc-800 dark:text-zinc-200">HTTP Verification specs:</p>
                <ul className="list-disc pl-5 space-y-1">
                  <li>Method: <strong className="text-zinc-900 dark:text-white font-bold">POST</strong></li>
                  <li>Path: <code className="text-blue-500 dark:text-blue-400 bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 rounded font-mono text-xs">https://portal.itsupport.com.bd/api/license/verify</code></li>
                  <li>Content-Type: <code className="text-zinc-700 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 rounded font-mono text-xs">application/json</code></li>
                </ul>
              </div>

              {/* Request code block */}
              <div className="space-y-2">
                <span className="block text-[10px] uppercase font-bold text-zinc-400">Request curl template:</span>
                <div className="relative group">
                  <div className="absolute right-3 top-3 opacity-80 hover:opacity-100 transition-opacity">
                    <button 
                      onClick={() => copyToClipboard(codeBlocks.verifyLicense, 'verifyLicense')} 
                      className="p-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-zinc-400 hover:text-white"
                      title="Copy command"
                    >
                      {copiedIndex === 'verifyLicense' ? <Check size={14} className="text-emerald-500" /> : <Copy size={14} />}
                    </button>
                  </div>
                  <pre className="p-4 rounded-xl bg-zinc-950 font-mono text-[11px] sm:text-xs text-zinc-200 text-left overflow-x-auto select-all border border-zinc-800">
                    {codeBlocks.verifyLicense}
                  </pre>
                </div>
              </div>
            </section>

          </main>
        </div>

      </div>
    </div>
  );
}
