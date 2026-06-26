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
    <div className="flex flex-col min-h-screen relative overflow-hidden bg-zinc-950">
      
      {/* Visual background decorations */}
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[500px] bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-900/20 via-zinc-950 to-zinc-950 pointer-events-none -z-10" />
      <div className="absolute top-[300px] -left-[200px] w-[500px] h-[500px] bg-blue-500/5 rounded-full blur-3xl pointer-events-none -z-10" />
      <div className="absolute top-[600px] -right-[200px] w-[500px] h-[500px] bg-purple-500/5 rounded-full blur-3xl pointer-events-none -z-10" />

      {/* Grid Pattern backdrop */}
      <div className="absolute inset-0 bg-[linear-gradient(to_right,#8080800a_1px,transparent_1px),linear-gradient(to_bottom,#8080800a_1px,transparent_1px)] bg-[size:24px_24px] pointer-events-none -z-20" />

      {/* Hero Section */}
      <section className="relative px-6 py-20 md:py-32 max-w-7xl mx-auto w-full">
        <div className="grid gap-12 lg:grid-cols-12 items-center">
          
          {/* Hero text */}
          <div className="lg:col-span-7 space-y-6 text-left">
            <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-xs font-bold text-blue-400 select-none w-fit tracking-wider uppercase">
              <span className="h-1.5 w-1.5 rounded-full bg-blue-500 animate-ping" />
              SaaS licensing & Telemetry Guard
            </div>

            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-white leading-tight">
              Advanced Node <br className="hidden sm:inline" />
              <span className="bg-gradient-to-r from-blue-400 via-blue-500 to-indigo-400 bg-clip-text text-transparent">
                Telemetry Analytics
              </span>
            </h1>

            <p className="text-zinc-400 text-sm sm:text-base max-w-xl font-medium leading-relaxed">
              AMPNM provides secure multi-tenant cryptographic licensing validations and real-time container metrics tracking. Built for mission-critical IT infrastructures.
            </p>

            {/* Quick install box */}
            <div className="p-3 bg-zinc-900 border border-zinc-800 rounded-xl max-w-lg flex items-center justify-between gap-3 shadow-2xl">
              <div className="flex items-center gap-2 overflow-hidden">
                <Terminal size={14} className="text-zinc-500 flex-shrink-0" />
                <code className="text-xs font-mono font-bold text-zinc-300 select-all truncate">
                  {installCommand}
                </code>
              </div>
              <button
                onClick={handleCopy}
                className="p-2 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 hover:text-white rounded-lg transition-colors flex-shrink-0"
                title="Copy install command"
              >
                {copied ? <Check size={14} className="text-emerald-500" /> : <Copy size={14} />}
              </button>
            </div>

            {/* CTAs */}
            <div className="flex flex-wrap gap-4 pt-2">
              <Link
                href="/download"
                className="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-500/20 transition-all hover:translate-x-0.5 cursor-pointer"
              >
                Download Agent
                <ArrowRight size={16} />
              </Link>
              
              <Link
                href="/pricing"
                className="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-sm font-bold text-zinc-300 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-zinc-700 transition-colors cursor-pointer"
              >
                View Solutions Pricing
              </Link>
            </div>
          </div>

          {/* Interactive Simulated Terminal Mockup */}
          <div className="lg:col-span-5 relative">
            <div className="w-full bg-zinc-900/90 border border-zinc-800 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-xl">
              {/* Terminal Window chrome header */}
              <div className="bg-zinc-950 px-4 py-3 flex items-center justify-between border-b border-zinc-850">
                <div className="flex gap-1.5">
                  <div className="w-3 h-3 rounded-full bg-rose-500" />
                  <div className="w-3 h-3 rounded-full bg-amber-500" />
                  <div className="w-3 h-3 rounded-full bg-emerald-500" />
                </div>
                <span className="text-[10px] font-mono text-zinc-500 uppercase tracking-widest font-bold">
                  ampnm-agentd.service
                </span>
              </div>

              {/* Terminal Screen Console */}
              <div className="p-5 font-mono text-xs text-left h-72 overflow-y-auto space-y-2 text-zinc-300">
                <p className="text-zinc-500"># Initializing AMPNM Telemetry Daemon...</p>
                <p className="text-zinc-400"># Connecting to portal.itsupport.com.bd... [OK]</p>
                
                {terminalLine >= 1 && (
                  <p className="text-blue-400 animate-in fade-in duration-300">
                    &gt; Fetching active license key details...
                  </p>
                )}
                
                {terminalLine >= 2 && (
                  <p className="text-zinc-300 font-bold bg-zinc-950 p-2 rounded border border-zinc-850 break-all select-none">
                    KEY: AMP256-4F2B-9A4E-4321-9876-BD90 [VALID]
                  </p>
                )}

                {terminalLine >= 3 && (
                  <p className="text-emerald-500 font-semibold animate-in slide-in-from-bottom-2">
                    ✓ Bound Org: Bangladesh Bank IT Operations
                  </p>
                )}

                {terminalLine >= 4 && (
                  <div className="space-y-1 text-zinc-400 bg-zinc-950 p-2 rounded">
                    <p className="text-[10px] uppercase font-bold text-zinc-500">Metric Stream initialized:</p>
                    <p>CPU Node Load: [||||||||............] 42.1%</p>
                    <p>RAM Node Load: [||||||||||||........] 64.8%</p>
                    <p>Active Cluster Nodes: 5/5 active</p>
                  </div>
                )}

                {terminalLine >= 5 && (
                  <p className="text-pink-500 font-semibold animate-pulse">
                    ▲ Daemon running (uptime: 142h)
                  </p>
                )}
              </div>
            </div>

            {/* Glowing blur under the terminal */}
            <div className="absolute -inset-1 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-2xl blur opacity-15 -z-10" />
          </div>

        </div>
      </section>

      {/* Numerical Metrics Summary Grid */}
      <section className="border-y border-zinc-900 bg-zinc-950/50 py-12 px-6">
        <div className="max-w-7xl mx-auto grid gap-8 grid-cols-2 lg:grid-cols-4">
          {metrics.map((m, idx) => {
            const Icon = m.icon;
            return (
              <div key={idx} className="flex flex-col items-center lg:items-start text-center lg:text-left space-y-1">
                <div className="flex items-center gap-2">
                  <div className={`p-1.5 bg-zinc-900 rounded-lg ${m.color}`}>
                    <Icon size={16} />
                  </div>
                  <span className="text-2xl font-extrabold tracking-tight text-white">{m.value}</span>
                </div>
                <span className="text-xs text-zinc-500 dark:text-zinc-450 font-semibold uppercase tracking-wider">
                  {m.label}
                </span>
              </div>
            );
          })}
        </div>
      </section>

      {/* CORE PLATFORM FEATURES */}
      <section className="px-6 py-20 max-w-7xl mx-auto w-full space-y-12">
        <div className="text-center max-w-2xl mx-auto space-y-3">
          <h2 className="text-2xl sm:text-3xl font-extrabold text-white">Full-Stack Licensing Guard & Performance Aggregator</h2>
          <p className="text-sm text-zinc-400">AMPNM combines secure authorization verification protocols with telemetry metrics collectors.</p>
        </div>

        <div className="grid gap-6 md:grid-cols-3">
          {/* Card 1 */}
          <div className="p-6 border border-zinc-900 bg-zinc-900/40 rounded-2xl space-y-4 hover:border-blue-500/30 transition-all group">
            <div className="p-3 bg-blue-500/10 text-blue-400 rounded-xl w-fit group-hover:scale-105 transition-transform">
              <ShieldCheck size={24} />
            </div>
            <div className="space-y-1.5">
              <h3 className="font-bold text-base text-zinc-100">Cryptographic Verification</h3>
              <p className="text-xs text-zinc-400 font-medium">Verify software nodes dynamically using secure 256-bit keys, bound to multi-tenant organization workspaces.</p>
            </div>
          </div>

          {/* Card 2 */}
          <div className="p-6 border border-zinc-900 bg-zinc-900/40 rounded-2xl space-y-4 hover:border-blue-500/30 transition-all group">
            <div className="p-3 bg-pink-500/10 text-pink-400 rounded-xl w-fit group-hover:scale-105 transition-transform">
              <Cpu size={24} />
            </div>
            <div className="space-y-1.5">
              <h3 className="font-bold text-base text-zinc-100">Node Load Analytics</h3>
              <p className="text-xs text-zinc-400 font-medium">Track agent CPU performance, disk volume indicators, and memory allocations in real-time widgets.</p>
            </div>
          </div>

          {/* Card 3 */}
          <div className="p-6 border border-zinc-900 bg-zinc-900/40 rounded-2xl space-y-4 hover:border-blue-500/30 transition-all group">
            <div className="p-3 bg-purple-500/10 text-purple-400 rounded-xl w-fit group-hover:scale-105 transition-transform">
              <Database size={24} />
            </div>
            <div className="space-y-1.5">
              <h3 className="font-bold text-base text-zinc-100">Multi-tenant Architecture</h3>
              <p className="text-xs text-zinc-400 font-medium">Isolate databases, product licenses, and workspace scopes across distinct corporate directories.</p>
            </div>
          </div>
        </div>
      </section>

      {/* DEVELOPED BY IT SUPPORT BD CTA BANNER */}
      <section className="px-6 pb-20 max-w-7xl mx-auto w-full">
        <div className="bg-gradient-to-r from-blue-900/20 via-zinc-900/80 to-indigo-900/10 border border-zinc-800 rounded-3xl p-8 sm:p-12 text-center space-y-6 relative overflow-hidden">
          <div className="space-y-3 relative z-10">
            <h3 className="text-xl sm:text-2xl font-extrabold text-white">Need Custom Integrations?</h3>
            <p className="text-xs sm:text-sm text-zinc-400 max-w-xl mx-auto">
              Our engineering team at IT Support BD provides customized agent extensions, proprietary server setups, and automated messaging system bindings.
            </p>
          </div>

          <div className="flex flex-wrap items-center justify-center gap-4 relative z-10">
            <Link
              href="/contact"
              className="inline-flex items-center gap-1 px-5 py-2.5 bg-white text-zinc-950 font-bold text-xs rounded-xl hover:bg-zinc-100 transition-colors cursor-pointer"
            >
              Get Professional Support
            </Link>
            <a
              href="mailto:support@itsupport.com.bd"
              className="px-5 py-2.5 bg-zinc-800/80 hover:bg-zinc-800 text-zinc-350 text-xs font-semibold rounded-xl transition-colors border border-zinc-700/60"
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
