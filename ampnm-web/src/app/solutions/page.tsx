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
    <div className="py-20 bg-white dark:bg-zinc-950 transition-colors duration-300 relative overflow-hidden flex-1">
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[400px] bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-100/40 via-transparent to-transparent dark:from-blue-900/15 dark:via-zinc-950 dark:to-zinc-950 pointer-events-none -z-10" />

      <div className="max-w-7xl mx-auto px-6 space-y-16">
        
        {/* Title */}
        <div className="text-center max-w-3xl mx-auto space-y-4 animate-fade-in-up">
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-zinc-900 dark:text-white leading-tight transition-colors">
            Targeted Solutions For <br />
            <span className="bg-gradient-to-r from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 bg-clip-text text-transparent">
              Modern Scaling
            </span>
          </h1>
          <p className="text-zinc-500 dark:text-zinc-400 transition-colors text-sm font-medium">
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
                className="animate-fade-in-up p-8 border border-zinc-200 dark:border-zinc-900 bg-white dark:bg-zinc-900/20 rounded-3xl space-y-4 flex flex-col items-start hover:border-blue-300 dark:hover:border-blue-500/25 transition-all hover:-translate-y-1 hover:shadow-lg"
                style={{ animationDelay: `${idx * 100}ms` }}
              >
                <div className={`p-3 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl ${opt.color}`}>
                  <Icon size={24} />
                </div>
                <div className="space-y-2">
                  <h3 className="text-base font-bold text-zinc-900 dark:text-white transition-colors">{opt.title}</h3>
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
