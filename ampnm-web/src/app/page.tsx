"use client";

import { useState, useEffect } from "react";
import Link from "next/link";
import { 
  ArrowRight, 
  Terminal, 
  Copy, 
  Check, 
  Activity, 
  ShieldCheck, 
  Cpu, 
  Database,
  Cloud,
  Layers,
  ChevronRight,
  TrendingUp,
  MessageSquare,
  Users
} from "lucide-react";

export default function LandingPage() {
  const [copied, setCopied] = useState(false);
  const [terminalLine, setTerminalLine] = useState(0);

  const installCommand = "curl -sSL https://ampnm.itsupport.com.bd/install.sh | bash";

  const handleCopy = () => {
    navigator.clipboard.writeText(installCommand);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  // Simulate terminal lines loader
  useEffect(() => {
    const timer = setInterval(() => {
      setTerminalLine(prev => (prev < 5 ? prev + 1 : 0));
    }, 1500);
    return () => clearInterval(timer);
  }, []);

  const metrics = [
    { label: "Active Monitored Nodes", value: "12,482+", icon: Cpu, color: "text-blue-500" },
    { label: "License Keys Verified", value: "98.98%", icon: ShieldCheck, color: "text-emerald-500" },
    { label: "Daily API Transactions", value: "3.4M+", icon: Activity, color: "text-pink-500" },
    { label: "Global Corporate Tenants", value: "482", icon: Users, color: "text-purple-500" },
  ];

  return (
    <div className="flex flex-col min-h-screen relative overflow-hidden bg-white dark:bg-zinc-950 transition-colors duration-300">
      
      {/* Visual background decorations */}
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[500px] bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-100/50 via-transparent to-transparent dark:from-blue-900/20 dark:via-zinc-950 dark:to-zinc-950 pointer-events-none -z-10 transition-colors duration-300" />
      <div className="absolute top-[300px] -left-[200px] w-[500px] h-[500px] bg-blue-200/30 dark:bg-blue-500/5 rounded-full blur-3xl pointer-events-none -z-10 animate-pulse-glow" />
      <div className="absolute top-[600px] -right-[200px] w-[500px] h-[500px] bg-purple-200/30 dark:bg-purple-500/5 rounded-full blur-3xl pointer-events-none -z-10 animate-pulse-glow delay-300" />

      {/* Grid Pattern backdrop */}
      <div className="absolute inset-0 bg-[linear-gradient(to_right,#8080800a_1px,transparent_1px),linear-gradient(to_bottom,#8080800a_1px,transparent_1px)] bg-[size:24px_24px] pointer-events-none -z-20" />

      {/* Hero Section */}
      <section className="relative px-6 py-20 md:py-32 max-w-7xl mx-auto w-full">
        <div className="grid gap-12 lg:grid-cols-12 items-center">
          
          {/* Hero text */}
          <div className="lg:col-span-7 space-y-6 text-left">
            <div className="animate-fade-in-up inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-100 dark:bg-blue-500/10 border border-blue-300/50 dark:border-blue-500/20 text-xs font-bold text-blue-600 dark:text-blue-400 select-none w-fit tracking-wider uppercase transition-colors">
              <span className="h-1.5 w-1.5 rounded-full bg-blue-500 animate-ping" />
              Free & Open Source Telemetry Guard
            </div>

            <h1 className="animate-fade-in-up delay-100 text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-zinc-900 dark:text-white leading-tight transition-colors">
              Advanced Node <br className="hidden sm:inline" />
              <span className="bg-gradient-to-r from-blue-500 via-blue-600 to-indigo-500 dark:from-blue-400 dark:via-blue-500 dark:to-indigo-400 bg-clip-text text-transparent">
                Telemetry Analytics
              </span>
            </h1>

            <p className="animate-fade-in-up delay-200 text-zinc-500 dark:text-zinc-400 text-sm sm:text-base max-w-xl font-medium leading-relaxed transition-colors">
              AMPNM is a free and open-source Docker telemetry platform. It provides real-time container metrics tracking, system alerts, and custom dashboard layouts. Completely self-hosted and free to deploy.
            </p>

            {/* Quick install box */}
            <div className="animate-fade-in-up delay-300 p-3 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl max-w-lg flex items-center justify-between gap-3 shadow-lg dark:shadow-2xl transition-colors">
              <div className="flex items-center gap-2 overflow-hidden">
                <Terminal size={14} className="text-zinc-400 dark:text-zinc-500 flex-shrink-0" />
                <code className="text-xs font-mono font-bold text-zinc-700 dark:text-zinc-300 select-all truncate">
                  {installCommand}
                </code>
              </div>
              <button
                onClick={handleCopy}
                className="p-2 bg-zinc-200 dark:bg-zinc-800 hover:bg-zinc-300 dark:hover:bg-zinc-700 text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white rounded-lg transition-all flex-shrink-0 hover:scale-105"
                title="Copy install command"
              >
                {copied ? <Check size={14} className="text-emerald-500" /> : <Copy size={14} />}
              </button>
            </div>

            {/* CTAs */}
            <div className="animate-fade-in-up delay-400 flex flex-wrap gap-4 pt-2">
              <Link
                href="/download"
                className="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-500/20 transition-all hover:-translate-y-0.5 hover:shadow-xl hover:shadow-blue-500/30 cursor-pointer"
              >
                Download Agent
                <ArrowRight size={16} />
              </Link>
              
              <Link
                href="/pricing"
                className="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-sm font-bold text-zinc-700 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-900 hover:bg-zinc-200 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 hover:border-zinc-300 dark:hover:border-zinc-700 transition-all cursor-pointer"
              >
                Get Free License Key
              </Link>
            </div>
          </div>

          {/* Interactive Simulated Terminal Mockup */}
          <div className="lg:col-span-5 relative animate-fade-in-up delay-300">
            <div className="w-full bg-white/80 dark:bg-zinc-900/90 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-xl animate-float transition-colors">
              {/* Terminal Window chrome header */}
              <div className="bg-zinc-100 dark:bg-zinc-950 px-4 py-3 flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 transition-colors">
                <div className="flex gap-1.5">
                  <div className="w-3 h-3 rounded-full bg-rose-500" />
                  <div className="w-3 h-3 rounded-full bg-amber-500" />
                  <div className="w-3 h-3 rounded-full bg-emerald-500" />
                </div>
                <span className="text-[10px] font-mono text-zinc-400 dark:text-zinc-500 uppercase tracking-widest font-bold">
                  ampnm-agentd.service
                </span>
              </div>

              {/* Terminal Screen Console */}
              <div className="p-5 font-mono text-xs text-left h-72 overflow-y-auto space-y-2 text-zinc-700 dark:text-zinc-300 bg-zinc-50 dark:bg-zinc-900 transition-colors">
                <p className="text-zinc-400 dark:text-zinc-500"># Initializing AMPNM Telemetry Daemon...</p>
                <p className="text-zinc-500 dark:text-zinc-400"># Connecting to portal.itsupport.com.bd... [OK]</p>
                
                {terminalLine >= 1 && (
                  <p className="text-blue-500 dark:text-blue-400 animate-fade-in">
                    &gt; Fetching active license key details...
                  </p>
                )}
                
                {terminalLine >= 2 && (
                  <p className="text-zinc-800 dark:text-zinc-300 font-bold bg-zinc-200/50 dark:bg-zinc-950 p-2 rounded border border-zinc-200 dark:border-zinc-800 break-all select-none animate-fade-in transition-colors">
                    KEY: AMP256-4F2B-9A4E-4321-9876-BD90 [VALID]
                  </p>
                )}

                {terminalLine >= 3 && (
                  <p className="text-emerald-600 dark:text-emerald-500 font-semibold animate-fade-in-up">
                    ✓ Bound Org: Bangladesh Bank IT Operations
                  </p>
                )}

                {terminalLine >= 4 && (
                  <div className="space-y-1 text-zinc-500 dark:text-zinc-400 bg-zinc-200/30 dark:bg-zinc-950 p-2 rounded animate-fade-in transition-colors">
                    <p className="text-[10px] uppercase font-bold text-zinc-400 dark:text-zinc-500">Metric Stream initialized:</p>
                    <p>CPU Node Load: [||||||||............] 42.1%</p>
                    <p>RAM Node Load: [||||||||||||........] 64.8%</p>
                    <p>Active Cluster Nodes: 5/5 active</p>
                  </div>
                )}

                {terminalLine >= 5 && (
                  <p className="text-pink-600 dark:text-pink-500 font-semibold animate-pulse">
                    ▲ Daemon running (uptime: 142h)
                  </p>
                )}
              </div>
            </div>

            {/* Glowing blur under the terminal */}
            <div className="absolute -inset-1 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-2xl blur opacity-10 dark:opacity-15 -z-10 animate-pulse-glow" />
          </div>

        </div>
      </section>

      {/* Numerical Metrics Summary Grid */}
      <section className="border-y border-zinc-200 dark:border-zinc-900 bg-zinc-50/50 dark:bg-zinc-950/50 py-12 px-6 transition-colors">
        <div className="max-w-7xl mx-auto grid gap-8 grid-cols-2 lg:grid-cols-4">
          {metrics.map((m, idx) => {
            const Icon = m.icon;
            return (
              <div key={idx} className="flex flex-col items-center lg:items-start text-center lg:text-left space-y-1 animate-fade-in-up" style={{ animationDelay: `${idx * 100}ms` }}>
                <div className="flex items-center gap-2">
                  <div className={`p-1.5 bg-zinc-100 dark:bg-zinc-900 rounded-lg ${m.color} transition-colors`}>
                    <Icon size={16} />
                  </div>
                  <span className="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white transition-colors">{m.value}</span>
                </div>
                <span className="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase tracking-wider">
                  {m.label}
                </span>
              </div>
            );
          })}
        </div>
      </section>

      {/* CORE PLATFORM FEATURES */}
      <section className="px-6 py-20 max-w-7xl mx-auto w-full space-y-12">
        <div className="text-center max-w-2xl mx-auto space-y-3 animate-fade-in-up">
          <h2 className="text-2xl sm:text-3xl font-extrabold text-zinc-900 dark:text-white transition-colors">Full-Stack Licensing Guard & Performance Aggregator</h2>
          <p className="text-sm text-zinc-500 dark:text-zinc-400 transition-colors">AMPNM combines secure authorization verification protocols with telemetry metrics collectors.</p>
        </div>

        <div className="grid gap-6 md:grid-cols-3">
          {/* Card 1 */}
          <div className="animate-fade-in-up delay-100 p-6 border border-zinc-200 dark:border-zinc-900 bg-white dark:bg-zinc-900/40 rounded-2xl space-y-4 hover:border-blue-300 dark:hover:border-blue-500/30 transition-all group hover:-translate-y-1 hover:shadow-lg">
            <div className="p-3 bg-blue-50 dark:bg-blue-500/10 text-blue-500 dark:text-blue-400 rounded-xl w-fit group-hover:scale-110 transition-transform">
              <ShieldCheck size={24} />
            </div>
            <div className="space-y-1.5">
              <h3 className="font-bold text-base text-zinc-900 dark:text-zinc-100 transition-colors">Cryptographic Verification</h3>
              <p className="text-xs text-zinc-500 dark:text-zinc-400 font-medium transition-colors">Verify software nodes dynamically using secure 256-bit keys, bound to multi-tenant organization workspaces.</p>
            </div>
          </div>

          {/* Card 2 */}
          <div className="animate-fade-in-up delay-200 p-6 border border-zinc-200 dark:border-zinc-900 bg-white dark:bg-zinc-900/40 rounded-2xl space-y-4 hover:border-pink-300 dark:hover:border-pink-500/30 transition-all group hover:-translate-y-1 hover:shadow-lg">
            <div className="p-3 bg-pink-50 dark:bg-pink-500/10 text-pink-500 dark:text-pink-400 rounded-xl w-fit group-hover:scale-110 transition-transform">
              <Cpu size={24} />
            </div>
            <div className="space-y-1.5">
              <h3 className="font-bold text-base text-zinc-900 dark:text-zinc-100 transition-colors">Node Load Analytics</h3>
              <p className="text-xs text-zinc-500 dark:text-zinc-400 font-medium transition-colors">Track agent CPU performance, disk volume indicators, and memory allocations in real-time widgets.</p>
            </div>
          </div>

          {/* Card 3 */}
          <div className="animate-fade-in-up delay-300 p-6 border border-zinc-200 dark:border-zinc-900 bg-white dark:bg-zinc-900/40 rounded-2xl space-y-4 hover:border-purple-300 dark:hover:border-purple-500/30 transition-all group hover:-translate-y-1 hover:shadow-lg">
            <div className="p-3 bg-purple-50 dark:bg-purple-500/10 text-purple-500 dark:text-purple-400 rounded-xl w-fit group-hover:scale-110 transition-transform">
              <Database size={24} />
            </div>
            <div className="space-y-1.5">
              <h3 className="font-bold text-base text-zinc-900 dark:text-zinc-100 transition-colors">Multi-tenant Architecture</h3>
              <p className="text-xs text-zinc-500 dark:text-zinc-400 font-medium transition-colors">Isolate databases, product licenses, and workspace scopes across distinct corporate directories.</p>
            </div>
          </div>
        </div>
      </section>

      {/* QUICK START SECTION */}
      <section className="px-6 py-12 max-w-7xl mx-auto w-full space-y-8">
        <div className="text-center max-w-2xl mx-auto space-y-3 animate-fade-in-up">
          <h2 className="text-2xl sm:text-3xl font-extrabold text-zinc-900 dark:text-white transition-colors">Quick Deployment Guide</h2>
          <p className="text-sm text-zinc-500 dark:text-zinc-400 transition-colors">Get your AMPNM monitor server up and running on Docker in minutes.</p>
        </div>

        <div className="grid gap-6 md:grid-cols-3">
          <div className="p-6 bg-zinc-50 dark:bg-zinc-900/30 border border-zinc-200 dark:border-zinc-800 rounded-2xl space-y-3">
            <span className="flex items-center justify-center w-8 h-8 rounded-full bg-blue-500 text-white font-bold text-sm">1</span>
            <h4 className="font-bold text-zinc-950 dark:text-white text-sm">Pull Image</h4>
            <p className="text-xs text-zinc-500 dark:text-zinc-400">Download the optimized application container directly from Docker Hub repository.</p>
            <pre className="p-2.5 rounded bg-zinc-950 text-zinc-200 font-mono text-[10px] overflow-x-auto select-all">
              docker pull itsupportbd/ampnm:V1.15
              {"\n"}
              docker pull itsupportbd/ampnm:latest
            </pre>
          </div>

          <div className="p-6 bg-zinc-50 dark:bg-zinc-900/30 border border-zinc-200 dark:border-zinc-800 rounded-2xl space-y-3">
            <span className="flex items-center justify-center w-8 h-8 rounded-full bg-blue-500 text-white font-bold text-sm">2</span>
            <h4 className="font-bold text-zinc-950 dark:text-white text-sm">Run Container</h4>
            <p className="text-xs text-zinc-500 dark:text-zinc-400">Expose port 2266 and bind mount the host Docker socket to enable self-updating.</p>
            <pre className="p-2.5 rounded bg-zinc-950 text-zinc-200 font-mono text-[10px] overflow-x-auto select-all">
              docker run -d -p 2266:2266 -v /var/run/docker.sock:/var/run/docker.sock itsupportbd/ampnm:latest
            </pre>
          </div>

          <div className="p-6 bg-zinc-50 dark:bg-zinc-900/30 border border-zinc-200 dark:border-zinc-800 rounded-2xl space-y-3">
            <span className="flex items-center justify-center w-8 h-8 rounded-full bg-blue-500 text-white font-bold text-sm">3</span>
            <h4 className="font-bold text-zinc-950 dark:text-white text-sm">Activate & Connect</h4>
            <p className="text-xs text-zinc-500 dark:text-zinc-400">Access the dashboard, get your free open-source license, and start monitoring.</p>
            <div className="flex gap-4 pt-1">
              <Link href="/pricing" className="text-xs font-bold text-blue-500 hover:underline">Get Free Key &rarr;</Link>
              <Link href="/docs" className="text-xs font-bold text-indigo-500 hover:underline">Read Manual &rarr;</Link>
            </div>
          </div>
        </div>
      </section>

      {/* ARCHITECTURE DIAGRAM */}
      <section className="px-6 py-12 max-w-7xl mx-auto w-full space-y-8">
        <div className="text-center max-w-2xl mx-auto space-y-3">
          <h2 className="text-2xl sm:text-3xl font-extrabold text-zinc-900 dark:text-white transition-colors">Unified Monitoring Topology</h2>
          <p className="text-sm text-zinc-500 dark:text-zinc-400">How the AMPNM ecosystem coordinates telemetry across routers, agents, and gateways.</p>
        </div>

        <div className="p-8 bg-zinc-50 dark:bg-zinc-900/20 border border-zinc-200 dark:border-zinc-800 rounded-3xl relative overflow-hidden">
          <div className="grid gap-6 md:grid-cols-4 items-center text-center relative z-10">
            {/* Box 1: Sources */}
            <div className="p-5 bg-white dark:bg-zinc-950 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-sm space-y-2">
              <span className="text-xs font-bold bg-blue-500/10 text-blue-500 px-2.5 py-0.5 rounded-full">Telemetry Agents</span>
              <p className="text-xs text-zinc-500 dark:text-zinc-400">Windows/Linux PowerShell telemetry services streaming system load.</p>
            </div>

            {/* Box 2: Routers */}
            <div className="p-5 bg-white dark:bg-zinc-950 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-sm space-y-2">
              <span className="text-xs font-bold bg-purple-500/10 text-purple-500 px-2.5 py-0.5 rounded-full">Network Routers</span>
              <p className="text-xs text-zinc-500 dark:text-zinc-400">MikroTik ROS nodes polling interface traffic via secure API connections.</p>
            </div>

            {/* Box 3: Central Server */}
            <div className="p-6 bg-gradient-to-b from-blue-500 to-indigo-600 rounded-2xl text-white shadow-lg space-y-2 md:col-span-2">
              <span className="text-xs font-bold bg-white/20 text-white px-3 py-1 rounded-full">AMPNM Central Server</span>
              <p className="text-xs text-blue-100">Drives visual Vis.js network maps, processes trap alerts, schedules NAS/FTP backups, and triggers SMTP/Telegram notifications.</p>
            </div>
          </div>
          
          <div className="absolute inset-0 bg-[linear-gradient(to_right,#80808003_1px,transparent_1px),linear-gradient(to_bottom,#80808003_1px,transparent_1px)] bg-[size:14px_14px]" />
        </div>
      </section>

      {/* DEVELOPED BY IT SUPPORT BD CTA BANNER */}
      <section className="px-6 pb-20 max-w-7xl mx-auto w-full">
        <div className="bg-gradient-to-r from-blue-50 via-white to-indigo-50 dark:from-blue-900/20 dark:via-zinc-900/80 dark:to-indigo-900/10 border border-zinc-200 dark:border-zinc-800 rounded-3xl p-8 sm:p-12 text-center space-y-6 relative overflow-hidden transition-colors">
          <div className="space-y-3 relative z-10">
            <h3 className="text-xl sm:text-2xl font-extrabold text-zinc-900 dark:text-white transition-colors">Need Custom Integrations?</h3>
            <p className="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 max-w-xl mx-auto transition-colors">
              Our engineering team at IT Support BD provides customized agent extensions, proprietary server setups, and automated messaging system bindings.
            </p>
          </div>

          <div className="flex flex-wrap items-center justify-center gap-4 relative z-10">
            <Link
              href="/contact"
              className="inline-flex items-center gap-1 px-5 py-2.5 bg-blue-600 dark:bg-white text-white dark:text-zinc-950 font-bold text-xs rounded-xl hover:bg-blue-700 dark:hover:bg-zinc-100 transition-all hover:-translate-y-0.5 cursor-pointer"
            >
              Get Professional Support
            </Link>
            <a
              href="mailto:support@itsupport.com.bd"
              className="px-5 py-2.5 bg-zinc-100 dark:bg-zinc-800/80 hover:bg-zinc-200 dark:hover:bg-zinc-800 text-zinc-600 dark:text-zinc-300 text-xs font-semibold rounded-xl transition-colors border border-zinc-200 dark:border-zinc-700/60"
            >
              Email support@itsupport.com.bd
            </a>
          </div>

          {/* Grid visual background for the banner */}
          <div className="absolute inset-0 bg-[linear-gradient(to_right,#80808005_1px,transparent_1px),linear-gradient(to_bottom,#80808005_1px,transparent_1px)] bg-[size:16px_16px] pointer-events-none" />
        </div>
      </section>

    </div>
  );
}
