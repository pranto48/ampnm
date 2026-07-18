"use client";

import { useState } from "react";
import { Terminal, Copy, Check, FileCode, Cpu, ShieldCheck, Download, Monitor, Server, Globe, Settings, HardDrive, ExternalLink, Package, ArrowRight } from "lucide-react";

export default function DownloadPage() {
  const [copiedScript, setCopiedScript] = useState(false);
  const [copiedServerCompose, setCopiedServerCompose] = useState(false);
  const [copiedAgentCompose, setCopiedAgentCompose] = useState(false);
  const [copiedConfig, setCopiedConfig] = useState(false);
  const [copiedPull, setCopiedPull] = useState(false);
  const [activeTab, setActiveTab] = useState<"windows" | "linux" | "docker">("windows");

  const dockerPullCmd = "docker pull itsupportbd/ampnm:latest";

  const installScript = "curl -sSL https://ampnm.itsupport.com.bd/install.sh | bash";
  
  const dockerServerComposeCode = `version: "3.8"
services:
  ampnm-app:
    image: itsupportbd/ampnm:latest
    container_name: ampnm-app
    restart: unless-stopped
    ports:
      - "2266:2266"
      - "10051:10051"
    environment:
      - DB_HOST=db
      - DB_NAME=network_monitor
      - DB_USER=user
      - DB_PASSWORD=password
      - MYSQL_ROOT_PASSWORD=rootpassword
      - ADMIN_PASSWORD=password
      - APP_LICENSE_KEY=your_license_key_here
    depends_on:
      db:
        condition: service_healthy

  db:
    image: mysql:8.0
    container_name: ampnm-db
    restart: unless-stopped
    command: --default-authentication-plugin=mysql_native_password
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: network_monitor
      MYSQL_USER: user
      MYSQL_PASSWORD: password
    volumes:
      - db_data:/var/lib/mysql
    ports:
      - "3306:3306"
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h localhost -u root -prootpassword"]
      interval: 10s
      timeout: 5s
      retries: 60
      start_period: 300s

volumes:
  db_data:`;

  const dockerAgentComposeCode = `version: "3.8"
services:
  ampnm-agent:
    image: itsupportbd/ampnm-agent:latest
    container_name: ampnm-agent
    restart: unless-stopped
    environment:
      - AMPNM_LICENSE_KEY="AMP256-YOUR-KEY-HERE"
      - AMPNM_SERVER_ADDR="portal.itsupport.com.bd"
      - TELEMETRY_INTERVAL="60s"
    volumes:
      - /proc:/host/proc:ro
      - /sys:/host/sys:ro
      - /var/run/docker.sock:/var/run/docker.sock:ro
    network_mode: "host"`;

  const agentConfigJson = `{
  "ServerUrl": "http://192.168.20.5:2266/api/agent/metrics",
  "AgentToken": "ampnm_YOUR_ENROLLMENT_TOKEN",
  "Interval": 60,
  "TrapperServer": "192.168.20.5:10051",
  "PassivePort": 10050,
  "LANInterface": "auto"
}`;

  const copyToClipboard = (text: string, type: "script" | "server-compose" | "agent-compose" | "config" | "pull") => {
    navigator.clipboard.writeText(text);
    if (type === "script") {
      setCopiedScript(true);
      setTimeout(() => setCopiedScript(false), 2000);
    } else if (type === "server-compose") {
      setCopiedServerCompose(true);
      setTimeout(() => setCopiedServerCompose(false), 2000);
    } else if (type === "agent-compose") {
      setCopiedAgentCompose(true);
      setTimeout(() => setCopiedAgentCompose(false), 2000);
    } else if (type === "pull") {
      setCopiedPull(true);
      setTimeout(() => setCopiedPull(false), 2000);
    } else {
      setCopiedConfig(true);
      setTimeout(() => setCopiedConfig(false), 2000);
    }
  };

  const tabs = [
    { id: "windows" as const, label: "Windows", icon: Monitor },
    { id: "linux" as const, label: "Linux / macOS", icon: Terminal },
    { id: "docker" as const, label: "Docker", icon: Server },
  ];

  return (
    <div className="py-20 bg-white dark:bg-zinc-950 relative overflow-hidden flex-1 transition-colors duration-300">
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[400px] bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-100/40 via-transparent to-transparent dark:from-blue-900/15 dark:via-zinc-950 dark:to-zinc-950 pointer-events-none -z-10" />

      <div className="max-w-7xl mx-auto px-6 space-y-16">
        {/* Title */}
        <div className="text-center max-w-3xl mx-auto space-y-4 animate-fade-in-up">
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-zinc-900 dark:text-white leading-tight transition-colors">
            Download Licensing Agents & <br />
            <span className="bg-gradient-to-r from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 bg-clip-text text-transparent">
              Daemon Installers
            </span>
          </h1>
          <p className="text-zinc-500 dark:text-zinc-400 text-sm font-medium transition-colors">
            Retrieve background daemons, script installers, or container configurations to start monitoring hosts instantly.
          </p>
        </div>

        {/* Platform Tabs */}
        <div className="animate-fade-in-up delay-100 flex flex-wrap gap-2 justify-center">
          {tabs.map((tab) => {
            const Icon = tab.icon;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition-all cursor-pointer ${
                  activeTab === tab.id
                    ? "bg-blue-600 text-white shadow-lg shadow-blue-500/20"
                    : "bg-zinc-100 dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-800"
                }`}
              >
                <Icon size={16} />
                {tab.label}
              </button>
            );
          })}
        </div>

        {/* Windows Tab */}
        {activeTab === "windows" && (
          <div className="animate-fade-in space-y-8">
            <div className="grid gap-6 lg:grid-cols-2">
              {/* MSI Installer */}
              <div className="p-6 sm:p-8 border border-zinc-200 dark:border-zinc-900 bg-white dark:bg-zinc-900/20 rounded-3xl space-y-6 flex flex-col justify-between hover:border-blue-300 dark:hover:border-blue-500/30 transition-all hover:-translate-y-0.5 hover:shadow-lg">
                <div className="space-y-4">
                  <div className="flex items-center gap-3">
                    <div className="p-2.5 bg-blue-50 dark:bg-blue-500/10 text-blue-500 dark:text-blue-400 border border-blue-200 dark:border-blue-500/20 rounded-xl">
                      <HardDrive size={20} />
                    </div>
                    <div>
                      <h3 className="font-bold text-sm uppercase tracking-wider text-zinc-900 dark:text-white transition-colors">Windows MSI Installer</h3>
                      <p className="text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider">v1.1.0 · 3.4 MB</p>
                    </div>
                  </div>
                  <p className="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed font-medium">
                    Professional Windows installer with system tray icon, local web dashboard, LAN adapter selector, and automatic startup integration for Windows Server 2019/2022.
                  </p>
                  <a
                    href="/downloads/ampnm-agent-setup.msi"
                    download
                    className="w-full flex items-center justify-center gap-2 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all cursor-pointer shadow-md hover:shadow-lg hover:-translate-y-0.5"
                  >
                    <Download size={14} />
                    Download ampnm-agent-setup.msi
                  </a>
                </div>
                <div className="pt-4 border-t border-zinc-200 dark:border-zinc-900 text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider transition-colors">
                  Requires Windows 10+ or Windows Server 2019+
                </div>
              </div>

              {/* EXE Standalone */}
              <div className="p-6 sm:p-8 border border-zinc-200 dark:border-zinc-900 bg-white dark:bg-zinc-900/20 rounded-3xl space-y-6 flex flex-col justify-between hover:border-purple-300 dark:hover:border-purple-500/30 transition-all hover:-translate-y-0.5 hover:shadow-lg">
                <div className="space-y-4">
                  <div className="flex items-center gap-3">
                    <div className="p-2.5 bg-purple-50 dark:bg-purple-500/10 text-purple-500 dark:text-purple-400 border border-purple-200 dark:border-purple-500/20 rounded-xl">
                      <Cpu size={20} />
                    </div>
                    <div>
                      <h3 className="font-bold text-sm uppercase tracking-wider text-zinc-900 dark:text-white transition-colors">Windows EXE (Portable)</h3>
                      <p className="text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider">v1.1.0 · 6.9 MB</p>
                    </div>
                  </div>
                  <p className="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed font-medium">
                    Standalone compiled Go binary for Windows. Run directly without installation. Ideal for quick testing or restricted environments where MSI installers are not allowed.
                  </p>
                  <a
                    href="/downloads/ampnm-agent.exe"
                    download
                    className="w-full flex items-center justify-center gap-2 py-3 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-800 text-zinc-800 dark:text-white rounded-xl text-xs font-bold transition-all cursor-pointer shadow-md"
                  >
                    <Download size={14} />
                    Download ampnm-agent.exe
                  </a>
                </div>
                <div className="pt-4 border-t border-zinc-200 dark:border-zinc-900 text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider transition-colors">
                  Portable · No installation required
                </div>
              </div>

              {/* PowerShell Installer */}
              <div className="p-6 sm:p-8 border border-zinc-200 dark:border-zinc-900 bg-white dark:bg-zinc-900/20 rounded-3xl space-y-6 flex flex-col justify-between">
                <div className="space-y-4">
                  <div className="flex items-center gap-3">
                    <div className="p-2.5 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20 rounded-xl">
                      <Terminal size={20} />
                    </div>
                    <div>
                      <h3 className="font-bold text-sm uppercase tracking-wider text-zinc-900 dark:text-white transition-colors">PowerShell Installer Script</h3>
                      <p className="text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Automated deployment</p>
                    </div>
                  </div>
                  <p className="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed font-medium">
                    Automated PowerShell script that downloads the agent binary, creates a Windows service, configures firewall rules, and sets up auto-start.
                  </p>
                  <a
                    href="/downloads/AMPNM-Agent-Installer.ps1"
                    download
                    className="w-full flex items-center justify-center gap-2 py-3 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-800 text-zinc-800 dark:text-white rounded-xl text-xs font-bold transition-all cursor-pointer"
                  >
                    <Download size={14} />
                    Download AMPNM-Agent-Installer.ps1
                  </a>
                </div>
              </div>

              {/* BAT File */}
              <div className="p-6 sm:p-8 border border-zinc-200 dark:border-zinc-900 bg-white dark:bg-zinc-900/20 rounded-3xl space-y-6 flex flex-col justify-between">
                <div className="space-y-4">
                  <div className="flex items-center gap-3">
                    <div className="p-2.5 bg-amber-50 dark:bg-amber-500/10 text-amber-500 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20 rounded-xl">
                      <FileCode size={20} />
                    </div>
                    <div>
                      <h3 className="font-bold text-sm uppercase tracking-wider text-zinc-900 dark:text-white transition-colors">Simple BAT Launcher</h3>
                      <p className="text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Lightweight option</p>
                    </div>
                  </div>
                  <p className="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed font-medium">
                    Minimal batch file launcher for quick agent testing. Double-click to start monitoring metrics with default settings.
                  </p>
                  <a
                    href="/downloads/AMPNM-Agent-Simple.bat"
                    download
                    className="w-full flex items-center justify-center gap-2 py-3 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-800 text-zinc-800 dark:text-white rounded-xl text-xs font-bold transition-all cursor-pointer"
                  >
                    <Download size={14} />
                    Download AMPNM-Agent-Simple.bat
                  </a>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Linux Tab */}
        {activeTab === "linux" && (
          <div className="animate-fade-in space-y-8">
            <div className="grid gap-6 lg:grid-cols-2">
              {/* Linux Tarball */}
              <div className="p-6 sm:p-8 border border-zinc-200 dark:border-zinc-900 bg-white dark:bg-zinc-900/20 rounded-3xl space-y-6 flex flex-col justify-between hover:border-emerald-300 dark:hover:border-emerald-500/30 transition-all hover:-translate-y-0.5 hover:shadow-lg">
                <div className="space-y-4">
                  <div className="flex items-center gap-3">
                    <div className="p-2.5 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20 rounded-xl">
                      <HardDrive size={20} />
                    </div>
                    <div>
                      <h3 className="font-bold text-sm uppercase tracking-wider text-zinc-900 dark:text-white transition-colors">Linux Agent Bundle (.tar.gz)</h3>
                      <p className="text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Contains install script + systemd service</p>
                    </div>
                  </div>
                  <p className="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed font-medium">
                    Complete Linux agent bundle containing the install script, daemon shell runner, and systemd service file. Supports Ubuntu 20+, Debian 11+, CentOS 8+, and RHEL.
                  </p>
                  <a
                    href="/downloads/ampnm-agent-linux.tar.gz"
                    download
                    className="w-full flex items-center justify-center gap-2 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all cursor-pointer shadow-md hover:shadow-lg hover:-translate-y-0.5"
                  >
                    <Download size={14} />
                    Download ampnm-agent-linux.tar.gz
                  </a>
                </div>
                <div className="pt-4 border-t border-zinc-200 dark:border-zinc-900 text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider transition-colors">
                  Requires root/sudo privileges for systemd integration
                </div>
              </div>

              {/* One-line install */}
              <div className="p-6 sm:p-8 border border-zinc-200 dark:border-zinc-900 bg-white dark:bg-zinc-900/20 rounded-3xl space-y-6 flex flex-col justify-between">
                <div className="space-y-4">
                  <div className="flex items-center gap-3">
                    <div className="p-2.5 bg-blue-50 dark:bg-blue-500/10 text-blue-500 dark:text-blue-400 border border-blue-200 dark:border-blue-500/20 rounded-xl">
                      <Terminal size={20} />
                    </div>
                    <div>
                      <h3 className="font-bold text-sm uppercase tracking-wider text-zinc-900 dark:text-white transition-colors">One-Line Install Script</h3>
                      <p className="text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Recommended for quick setup</p>
                    </div>
                  </div>
                  <p className="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed font-medium">
                    Downloads compiled binary, configures systemd, and activates the telemetry daemon automatically with license key verification.
                  </p>
                  <div className="p-3 bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-900 rounded-xl flex items-center justify-between gap-3 font-mono text-[11px] font-bold text-zinc-700 dark:text-zinc-300 transition-colors">
                    <span className="truncate select-all">{installScript}</span>
                    <button
                      onClick={() => copyToClipboard(installScript, "script")}
                      className="p-1.5 bg-zinc-200 dark:bg-zinc-900 hover:bg-zinc-300 dark:hover:bg-zinc-800 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white rounded border border-zinc-300 dark:border-zinc-800 transition-colors"
                    >
                      {copiedScript ? <Check size={12} className="text-emerald-500" /> : <Copy size={12} />}
                    </button>
                  </div>
                </div>
                <div className="pt-4 border-t border-zinc-200 dark:border-zinc-900 text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider transition-colors">
                  Requires sudo root privileges
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Docker Tab */}
        {activeTab === "docker" && (
          <div className="animate-fade-in space-y-8">

            {/* ── Docker Hub Banner ───────────────────────────────────────────── */}
            <div className="relative overflow-hidden rounded-3xl border border-blue-200 dark:border-blue-500/30 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-950/40 dark:to-indigo-950/30 p-6 sm:p-8 space-y-6">
              {/* Background glow */}
              <div className="absolute -right-12 -top-12 w-48 h-48 bg-blue-400/10 rounded-full blur-3xl pointer-events-none" />
              <div className="absolute -left-12 -bottom-12 w-48 h-48 bg-indigo-400/10 rounded-full blur-3xl pointer-events-none" />

              {/* Header row */}
              <div className="flex flex-wrap items-start justify-between gap-4 relative">
                <div className="flex items-center gap-4">
                  <div className="p-3 bg-blue-500 rounded-2xl shadow-lg shadow-blue-500/30">
                    <Package size={24} className="text-white" />
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <h3 className="font-extrabold text-base text-zinc-900 dark:text-white">AMPNM on Docker Hub</h3>
                      <span className="px-2 py-0.5 bg-blue-500 text-white text-[10px] font-bold rounded-full">OFFICIAL</span>
                    </div>
                    <p className="text-xs text-zinc-500 dark:text-zinc-400 font-medium mt-0.5">
                      itsupportbd/ampnm &nbsp;·&nbsp; Latest Release
                    </p>
                  </div>
                </div>
                <a
                  href="https://hub.docker.com/r/itsupportbd/ampnm"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5 shrink-0"
                >
                  <ExternalLink size={13} />
                  View on Docker Hub
                  <ArrowRight size={13} />
                </a>
              </div>

              {/* Stats row */}
              <div className="flex flex-wrap gap-3 relative">
                {[
                  { label: "Image", value: "itsupportbd/ampnm" },
                  { label: "Tag", value: "latest" },
                  { label: "Base", value: "PHP 8.2 · Apache" },
                  { label: "Port", value: "2266" },
                ].map((s) => (
                  <div key={s.label} className="px-3 py-1.5 bg-white/60 dark:bg-white/5 border border-blue-200 dark:border-blue-500/20 rounded-lg">
                    <span className="text-[10px] text-zinc-400 font-bold uppercase tracking-wider">{s.label}: </span>
                    <span className="text-xs text-zinc-900 dark:text-white font-bold font-mono">{s.value}</span>
                  </div>
                ))}
              </div>

              {/* docker pull command */}
              <div className="relative">
                <p className="text-[10px] font-bold uppercase tracking-widest text-zinc-400 mb-2">Pull Command</p>
                <div className="flex items-center gap-2 bg-zinc-950 rounded-xl px-4 py-3 border border-zinc-800 shadow-inner">
                  <Terminal size={14} className="text-blue-400 shrink-0" />
                  <code className="flex-1 text-sm font-mono font-bold text-green-400 select-all">
                    {dockerPullCmd}
                  </code>
                  <button
                    onClick={() => copyToClipboard(dockerPullCmd, "pull")}
                    className="flex items-center gap-1.5 px-3 py-1.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 hover:text-white text-xs font-bold rounded-lg border border-zinc-700 transition-all"
                  >
                    {copiedPull ? (
                      <><Check size={12} className="text-emerald-400" /><span className="text-emerald-400">Copied!</span></>
                    ) : (
                      <><Copy size={12} /><span>Copy</span></>
                    )}
                  </button>
                </div>
              </div>

              {/* Quick links */}
              <div className="flex flex-wrap gap-3 relative">
                <a
                  href="https://hub.docker.com/r/itsupportbd/ampnm/tags"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1.5 text-xs text-blue-500 hover:text-blue-400 font-semibold transition-colors"
                >
                  <ExternalLink size={11} /> All Tags & Versions
                </a>
                <span className="text-zinc-300 dark:text-zinc-700">·</span>
                <a
                  href="https://hub.docker.com/r/itsupportbd/ampnm"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1.5 text-xs text-blue-500 hover:text-blue-400 font-semibold transition-colors"
                >
                  <ExternalLink size={11} /> Hub Overview Page
                </a>
                <span className="text-zinc-300 dark:text-zinc-700">·</span>
                <a
                  href="https://github.com/itsupportbd/ampnm"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1.5 text-xs text-blue-500 hover:text-blue-400 font-semibold transition-colors"
                >
                  <ExternalLink size={11} /> GitHub Source
                </a>
              </div>
            </div>
            {/* ───────────────────────────────────────────────────────────────── */}

            {/* Docker Compose Server */}
            <div className="p-6 sm:p-8 border border-zinc-200 dark:border-zinc-900 bg-white dark:bg-zinc-900/20 rounded-3xl space-y-6 transition-colors">
              <div className="flex items-center justify-between flex-wrap gap-4">
                <div className="flex items-center gap-3">
                  <div className="p-2.5 bg-blue-50 dark:bg-blue-500/10 text-blue-500 dark:text-blue-400 border border-blue-200 dark:border-blue-500/20 rounded-xl">
                    <FileCode size={20} />
                  </div>
                  <div>
                    <h3 className="font-bold text-sm uppercase tracking-wider text-zinc-900 dark:text-white transition-colors">AMPNM Server Installation</h3>
                    <p className="text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Web Console & Database Setup</p>
                  </div>
                </div>
                <button
                  onClick={() => copyToClipboard(dockerServerComposeCode, "server-compose")}
                  className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-800 text-zinc-600 dark:text-zinc-300 text-xs font-bold rounded-lg cursor-pointer transition-colors"
                >
                  {copiedServerCompose ? <><Check size={12} className="text-emerald-500" /><span>Copied!</span></> : <><Copy size={12} /><span>Copy Server Config</span></>}
                </button>
              </div>
              <div className="relative rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-900 bg-zinc-50 dark:bg-zinc-950 p-4 font-mono text-[11px] leading-relaxed text-zinc-700 dark:text-zinc-300 text-left overflow-x-auto select-all max-h-72 transition-colors">
                <pre>{dockerServerComposeCode}</pre>
              </div>
              <div className="text-[10px] text-zinc-500 leading-relaxed font-semibold">
                Note: Deploy the server config to pull the official `itsupportbd/ampnm:latest` image. Access the web installer at `http://localhost:2266` to activate your license key.
              </div>
            </div>

            {/* Docker Compose Agent */}
            <div className="p-6 sm:p-8 border border-zinc-200 dark:border-zinc-900 bg-white dark:bg-zinc-900/20 rounded-3xl space-y-6 transition-colors">
              <div className="flex items-center justify-between flex-wrap gap-4">
                <div className="flex items-center gap-3">
                  <div className="p-2.5 bg-cyan-50 dark:bg-cyan-500/10 text-cyan-500 dark:text-cyan-400 border border-cyan-200 dark:border-cyan-500/20 rounded-xl">
                    <FileCode size={20} />
                  </div>
                  <div>
                    <h3 className="font-bold text-sm uppercase tracking-wider text-zinc-900 dark:text-white transition-colors">AMPNM Agent Installation</h3>
                    <p className="text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Host Telemetry Daemon Setup</p>
                  </div>
                </div>
                <button
                  onClick={() => copyToClipboard(dockerAgentComposeCode, "agent-compose")}
                  className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-800 text-zinc-600 dark:text-zinc-300 text-xs font-bold rounded-lg cursor-pointer transition-colors"
                >
                  {copiedAgentCompose ? <><Check size={12} className="text-emerald-500" /><span>Copied!</span></> : <><Copy size={12} /><span>Copy Agent Config</span></>}
                </button>
              </div>
              <div className="relative rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-900 bg-zinc-50 dark:bg-zinc-950 p-4 font-mono text-[11px] leading-relaxed text-zinc-700 dark:text-zinc-300 text-left overflow-x-auto select-all max-h-72 transition-colors">
                <pre>{dockerAgentComposeCode}</pre>
              </div>
              <div className="text-[10px] text-zinc-500 leading-relaxed font-semibold">
                Note: Mount host filesystems <code className="bg-zinc-100 dark:bg-zinc-900 px-1 rounded">/proc</code> and <code className="bg-zinc-100 dark:bg-zinc-900 px-1 rounded">/sys</code> to allow docker telemetry agents to retrieve bare-metal load metrics. Update licensing variables with keys issued by the admin panel console.
              </div>
            </div>

            {/* Docker Autodiscovery Section */}
            <div className="p-6 sm:p-8 border border-zinc-200 dark:border-zinc-900 bg-white dark:bg-zinc-900/20 rounded-3xl space-y-6 transition-colors">
              <div className="flex items-center gap-3">
                <div className="p-2.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-500 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/20 rounded-xl">
                  <Globe size={20} />
                </div>
                <div>
                  <h3 className="font-bold text-sm uppercase tracking-wider text-zinc-900 dark:text-white transition-colors">Docker Network Autodiscovery</h3>
                  <p className="text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Automatic agent detection</p>
                </div>
              </div>
              <div className="space-y-3 text-xs text-zinc-500 dark:text-zinc-400 font-medium leading-relaxed transition-colors">
                <p>
                  When the AMPNM Docker application and agent run on the <strong className="text-zinc-700 dark:text-zinc-200">same host</strong> using <code className="bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 rounded text-[11px] font-bold text-zinc-700 dark:text-zinc-300">network_mode: &quot;host&quot;</code>, the server automatically discovers local agents listening on port <code className="bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 rounded text-[11px] font-bold">10050</code> (Passive) and <code className="bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 rounded text-[11px] font-bold">10051</code> (Active Trapper).
                </p>
                <p>
                  For <strong className="text-zinc-700 dark:text-zinc-200">remote agents</strong>, configure the <code className="bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 rounded text-[11px] font-bold text-zinc-700 dark:text-zinc-300">AMPNM_SERVER_ADDR</code> environment variable or the <code className="bg-zinc-100 dark:bg-zinc-900 px-1.5 py-0.5 rounded text-[11px] font-bold text-zinc-700 dark:text-zinc-300">ServerUrl</code> field in the agent config to point to your Docker server&apos;s IP or domain name.
                </p>
              </div>
            </div>
          </div>
        )}

        {/* Agent Configuration Section */}
        <div className="animate-fade-in-up delay-200 p-6 sm:p-8 border border-zinc-200 dark:border-zinc-900 bg-white dark:bg-zinc-900/20 rounded-3xl space-y-6 transition-colors">
          <div className="flex items-center justify-between flex-wrap gap-4">
            <div className="flex items-center gap-3">
              <div className="p-2.5 bg-amber-50 dark:bg-amber-500/10 text-amber-500 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20 rounded-xl">
                <Settings size={20} />
              </div>
              <div>
                <h3 className="font-bold text-sm uppercase tracking-wider text-zinc-900 dark:text-white transition-colors">Agent Configuration (config.json)</h3>
                <p className="text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Server address · Token · Intervals</p>
              </div>
            </div>
            <button
              onClick={() => copyToClipboard(agentConfigJson, "config")}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-800 text-zinc-600 dark:text-zinc-300 text-xs font-bold rounded-lg cursor-pointer transition-colors"
            >
              {copiedConfig ? <><Check size={12} className="text-emerald-500" /><span>Copied!</span></> : <><Copy size={12} /><span>Copy Config</span></>}
            </button>
          </div>

          <div className="relative rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-900 bg-zinc-50 dark:bg-zinc-950 p-4 font-mono text-[11px] leading-relaxed text-zinc-700 dark:text-zinc-300 text-left overflow-x-auto select-all transition-colors">
            <pre>{agentConfigJson}</pre>
          </div>

          <div className="grid gap-4 md:grid-cols-3 text-xs">
            <div className="p-4 bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-900 rounded-xl space-y-1 transition-colors">
              <p className="font-bold text-zinc-900 dark:text-zinc-100 transition-colors">ServerUrl</p>
              <p className="text-zinc-500 dark:text-zinc-400">Your AMPNM Docker server IP or domain. Example: <code className="text-blue-500 dark:text-blue-400">http://192.168.20.5:2266/api/agent/metrics</code></p>
            </div>
            <div className="p-4 bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-900 rounded-xl space-y-1 transition-colors">
              <p className="font-bold text-zinc-900 dark:text-zinc-100 transition-colors">AgentToken</p>
              <p className="text-zinc-500 dark:text-zinc-400">Enrollment token created from the Docker AMPNM admin panel under Agent Enrollment.</p>
            </div>
            <div className="p-4 bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-900 rounded-xl space-y-1 transition-colors">
              <p className="font-bold text-zinc-900 dark:text-zinc-100 transition-colors">TrapperServer</p>
              <p className="text-zinc-500 dark:text-zinc-400">TCP endpoint for the active trapper. Usually <code className="text-blue-500 dark:text-blue-400">YOUR_SERVER_IP:10051</code></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
