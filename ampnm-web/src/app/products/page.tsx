"use client";

import { Cpu, ShieldCheck, Activity, Bell, Layers, Terminal } from "lucide-react";

export default function ProductsInfoPage() {
  const specs = [
    {
      title: "Agent Telemetry Daemon",
      description: "Low-overhead background service compiled in native Go, consuming less than 15MB RAM and 0.5% CPU resources under load.",
      icon: Cpu,
      color: "text-blue-500 bg-blue-500/10"
    },
    {
      title: "Secure Verification APIs",
      description: "Edge compatible 256-bit validation checks. Verify installs locally or query endpoints with microsecond response times.",
      icon: ShieldCheck,
      color: "text-emerald-500 bg-emerald-500/10"
    },
    {
      title: "Active Metrics Visualizers",
      description: "Dynamic reporting dashboard widgets demonstrating instant performance indicators for core processes.",
      icon: Activity,
      color: "text-pink-500 bg-pink-500/10"
    },
    {
      title: "Smart Alerts Integration",
      description: "Receive immediate warning pings on Telegram or SMS when host usage limits exceed specified thresholds.",
      icon: Bell,
      color: "text-amber-500 bg-amber-500/10"
    },
    {
      title: "Docker Isolation Matrices",
      description: "Separate logs, container indicators and licensing locks across segregated multi-tenant directories.",
      icon: Layers,
      color: "text-purple-500 bg-purple-500/10"
    },
    {
      title: "Command Controls",
      description: "One-line installation packages compatible with Ubuntu, CentOS, Debian, and Windows server shells.",
      icon: Terminal,
      color: "text-blue-450 bg-blue-450/10"
    }
  ];

  return (
    <div className="py-20 bg-zinc-950 relative overflow-hidden flex-1">
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[400px] bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-900/15 via-zinc-950 to-zinc-950 pointer-events-none -z-10" />

      <div className="max-w-7xl mx-auto px-6 space-y-16">
        
        {/* Title */}
        <div className="text-center max-w-3xl mx-auto space-y-4">
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-white leading-tight">
            Features Built For <br />
            <span className="bg-gradient-to-r from-blue-400 to-indigo-400 bg-clip-text text-transparent">
              System Administrators
            </span>
          </h1>
          <p className="text-zinc-400 text-sm font-medium">
            Discover the comprehensive engineering specifications of the AMPNM licensing agent and dashboard metrics collector.
          </p>
        </div>

        {/* Feature Grid */}
        <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
          {specs.map((spec, idx) => {
            const Icon = spec.icon;
            return (
              <div 
                key={idx}
                className="p-6 border border-zinc-900 bg-zinc-900/30 rounded-2xl space-y-4 hover:border-blue-500/20 transition-all hover:scale-[1.005]"
              >
                <div className={`p-3.5 rounded-xl w-fit ${spec.color}`}>
                  <Icon size={22} />
                </div>
                <div className="space-y-2">
                  <h3 className="text-sm font-bold text-white uppercase tracking-wider">{spec.title}</h3>
                  <p className="text-xs text-zinc-500 leading-relaxed font-medium">{spec.description}</p>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
