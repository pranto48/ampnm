"use client";

import { Cloud, Layers, ShieldCheck, Cpu } from "lucide-react";

export default function SolutionsPage() {
  const options = [
    {
      title: "Docker Cluster Orchestration",
      description: "Scale licenses securely across hundreds of swarmed containers. Validate key allocations and limits automatically as nodes spin up and collapse.",
      icon: Layers,
      color: "text-blue-500"
    },
    {
      title: "Private Server Deployments",
      description: "Operate licensing validation services inside private air-gapped server configurations, utilizing offline database fallback matrices.",
      icon: Cloud,
      color: "text-purple-500"
    },
    {
      title: "Corporate Network Compliance",
      description: "Enforce license validity, inspect hardware node limits, and audit access logs using centralized web consoles developed by IT Support BD.",
      icon: ShieldCheck,
      color: "text-emerald-500"
    },
    {
      title: "Edge Telemetry Integrations",
      description: "Collect high-performance metrics directly at node boundaries. Stream performance telemetry data with zero performance regressions.",
      icon: Cpu,
      color: "text-pink-500"
    }
  ];

  return (
    <div className="py-20 bg-zinc-950 relative overflow-hidden flex-1">
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[400px] bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-900/15 via-zinc-950 to-zinc-950 pointer-events-none -z-10" />

      <div className="max-w-7xl mx-auto px-6 space-y-16">
        
        {/* Title */}
        <div className="text-center max-w-3xl mx-auto space-y-4">
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-white leading-tight">
            Targeted Solutions For <br />
            <span className="bg-gradient-to-r from-blue-400 to-indigo-400 bg-clip-text text-transparent">
              Modern Scaling
            </span>
          </h1>
          <p className="text-zinc-400 text-sm font-medium">
            Explore architected models designed to enforce license compliance and monitor performance across hybrid cloud orchestrators.
          </p>
        </div>

        {/* Options grid */}
        <div className="grid gap-8 md:grid-cols-2">
          {options.map((opt, idx) => {
            const Icon = opt.icon;
            return (
              <div 
                key={idx} 
                className="p-8 border border-zinc-900 bg-zinc-900/20 rounded-3xl space-y-4 flex flex-col items-start hover:border-blue-500/25 transition-colors"
              >
                <div className={`p-3 bg-zinc-900 border border-zinc-800 rounded-xl ${opt.color}`}>
                  <Icon size={24} />
                </div>
                <div className="space-y-2">
                  <h3 className="text-base font-bold text-white">{opt.title}</h3>
                  <p className="text-xs text-zinc-500 leading-relaxed font-medium">{opt.description}</p>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
