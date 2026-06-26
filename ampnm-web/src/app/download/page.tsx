"use client";

import { useState } from "react";
import { Terminal, Copy, Check, FileCode, Cpu, ShieldCheck, Download } from "lucide-react";

export default function DownloadPage() {
  const [copiedScript, setCopiedScript] = useState(false);
  const [copiedCompose, setCopiedCompose] = useState(false);

  const installScript = "curl -sSL https://ampnm.itsupport.com.bd/install.sh | bash";
  
  const dockerComposeCode = `version: "3.8"
services:
  ampnm-agent:
    image: pranto48/ampnm-agent:latest
    container_name: ampnm-agent
    restart: unless-stopped
    environment:
      - AMPNM_LICENSE_KEY="AMP256-YOUR-KEY-HERE"
      - TELEMETRY_INTERVAL="60s"
    volumes:
      - /proc:/host/proc:ro
      - /sys:/host/sys:ro
      - /var/run/docker.sock:/var/run/docker.sock:ro`;

  const copyToClipboard = (text: string, type: "script" | "compose") => {
    navigator.clipboard.writeText(text);
    if (type === "script") {
      setCopiedScript(true);
      setTimeout(() => setCopiedScript(false), 2000);
    } else {
      setCopiedCompose(true);
      setTimeout(() => setCopiedCompose(false), 2000);
    }
  };

  return (
    <div className="py-20 bg-zinc-950 relative overflow-hidden flex-1">
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[400px] bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-900/15 via-zinc-950 to-zinc-950 pointer-events-none -z-10" />

      <div className="max-w-7xl mx-auto px-6 space-y-16">
        {/* Title */}
        <div className="text-center max-w-3xl mx-auto space-y-4">
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-white leading-tight">
            Download Licensing Agents & <br />
            <span className="bg-gradient-to-r from-blue-400 to-indigo-400 bg-clip-text text-transparent">
              Daemon Installers
            </span>
          </h1>
          <p className="text-zinc-400 text-sm font-medium">
            Retrieve background daemons, script installers, or container configurations to start monitoring hosts instantly.
          </p>
        </div>

        {/* Installation Channels */}
        <div className="grid gap-8 lg:grid-cols-2">
          
          {/* Linux One-Line Installer */}
          <div className="p-6 sm:p-8 border border-zinc-900 bg-zinc-900/20 rounded-3xl space-y-6 flex flex-col justify-between">
            <div className="space-y-4">
              <div className="flex items-center gap-3">
                <div className="p-2.5 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded-xl">
                  <Terminal size={20} />
                </div>
                <div>
                  <h3 className="font-bold text-sm uppercase tracking-wider text-white">Linux / macOS Install Script</h3>
                  <p className="text-[10px] text-zinc-500 font-bold uppercase tracking-wider">Shell curl script</p>
                </div>
              </div>
              
              <p className="text-xs text-zinc-450 leading-relaxed font-medium">
                The recommended installation channel. Running this bash wrapper downloads the compiled binary, configures systemd, and requests validation keys automatically.
              </p>

              <div className="p-3 bg-zinc-950 border border-zinc-900 rounded-xl flex items-center justify-between gap-3 font-mono text-[11px] font-bold text-zinc-300">
                <span className="truncate select-all select-none">{installScript}</span>
                <button
                  onClick={() => copyToClipboard(installScript, "script")}
                  className="p-1.5 bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white rounded border border-zinc-800 transition-colors"
                >
                  {copiedScript ? <Check size={12} className="text-emerald-500" /> : <Copy size={12} />}
                </button>
              </div>
            </div>

            <div className="pt-4 border-t border-zinc-900 text-[10px] text-zinc-500 font-bold uppercase tracking-wider">
              Requires sudo root privileges
            </div>
          </div>

          {/* Windows Agent MSI package */}
          <div className="p-6 sm:p-8 border border-zinc-900 bg-zinc-900/20 rounded-3xl space-y-6 flex flex-col justify-between">
            <div className="space-y-4">
              <div className="flex items-center gap-3">
                <div className="p-2.5 bg-purple-500/10 text-purple-400 border border-purple-500/20 rounded-xl">
                  <Cpu size={20} />
                </div>
                <div>
                  <h3 className="font-bold text-sm uppercase tracking-wider text-white">Windows Server Agent (.zip)</h3>
                  <p className="text-[10px] text-zinc-500 font-bold uppercase tracking-wider">MSI ZIP Installer package</p>
                </div>
              </div>
              
              <p className="text-xs text-zinc-450 leading-relaxed font-medium">
                Windows native service tracking metrics on Windows Server builds (2019/2022) or client hosts. Download the zip folder containing MSI and sample config files.
              </p>

              <button
                onClick={() => alert("Downloading AMPNM Agent for Windows v1.1.0 (Simulated ZIP package).")}
                className="w-full flex items-center justify-center gap-2 py-3 bg-zinc-900 border border-zinc-800 hover:bg-zinc-850 text-white rounded-xl text-xs font-bold transition-all cursor-pointer shadow-md"
              >
                <Download size={14} />
                Download ampnm-agent-windows.zip
              </button>
            </div>

            <div className="pt-4 border-t border-zinc-900 text-[10px] text-zinc-500 font-bold uppercase tracking-wider">
              Compatible with Powershell shells
            </div>
          </div>

          {/* Docker compose Config column */}
          <div className="lg:col-span-2 p-6 sm:p-8 border border-zinc-900 bg-zinc-900/20 rounded-3xl space-y-6">
            <div className="flex items-center justify-between flex-wrap gap-4">
              <div className="flex items-center gap-3">
                <div className="p-2.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-xl">
                  <FileCode size={20} />
                </div>
                <div>
                  <h3 className="font-bold text-sm uppercase tracking-wider text-white">Docker Compose Configuration</h3>
                  <p className="text-[10px] text-zinc-500 font-bold uppercase tracking-wider">container daemon setups</p>
                </div>
              </div>

              <button
                onClick={() => copyToClipboard(dockerComposeCode, "compose")}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-zinc-900 border border-zinc-800 hover:bg-zinc-800 text-zinc-300 text-xs font-bold rounded-lg cursor-pointer transition-colors"
              >
                {copiedCompose ? (
                  <>
                    <Check size={12} className="text-emerald-500" />
                    <span>Copied!</span>
                  </>
                ) : (
                  <>
                    <Copy size={12} />
                    <span>Copy Config</span>
                  </>
                )}
              </button>
            </div>

            <div className="relative rounded-xl overflow-hidden border border-zinc-900 bg-zinc-950 p-4 font-mono text-[11px] leading-relaxed text-zinc-300 text-left overflow-x-auto select-all max-h-72">
              <pre>{dockerComposeCode}</pre>
            </div>

            <div className="text-[10px] text-zinc-550 leading-relaxed font-semibold">
              Note: Mount host filesystems `/proc` and `/sys` to allow docker telemetry agents to retrieve bare-metal load metrics. Update licensing variables with keys issued by the admin panel console.
            </div>
          </div>

        </div>
      </div>
    </div>
  );
}
