"use client";

import { Activity, ShieldCheck, Mail, Users, ArrowRight } from "lucide-react";
import Link from "next/link";

export default function ServicesPage() {
  const list = [
    {
      title: "Custom Agent Engineering",
      description: "Our developers build customized Go agent binaries to capture proprietary metrics, databases status pings, and localized hardware indicators.",
      icon: Activity,
    },
    {
      title: "Centralized Licensing Integrations",
      description: "We configure authentication gateways, verify offline rules databases, and embed license check mechanisms into your Docker applications.",
      icon: ShieldCheck,
    },
    {
      title: "Infrastructure Deployment Support",
      description: "Get deployment consultation for setting up air-gapped private licensing nodes, firewall compliance, and automated load balancing.",
      icon: Users,
    }
  ];

  return (
    <div className="py-20 bg-zinc-950 relative overflow-hidden flex-1">
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[400px] bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-900/15 via-zinc-950 to-zinc-950 pointer-events-none -z-10" />

      <div className="max-w-7xl mx-auto px-6 space-y-16">
        
        {/* Title */}
        <div className="text-center max-w-3xl mx-auto space-y-4">
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-white leading-tight">
            Professional Services & <br />
            <span className="bg-gradient-to-r from-blue-400 to-indigo-400 bg-clip-text text-transparent">
              Custom Engineering
            </span>
          </h1>
          <p className="text-zinc-400 text-sm font-medium">
            IT Support BD provides expert consultation and specialized development to align telemetry tracking with your network topology.
          </p>
        </div>

        {/* List of services */}
        <div className="grid gap-8 md:grid-cols-3">
          {list.map((item, idx) => {
            const Icon = item.icon;
            return (
              <div 
                key={idx}
                className="p-6 border border-zinc-900 bg-zinc-900/20 rounded-2xl space-y-4 hover:border-blue-500/20 transition-colors"
              >
                <div className="p-3 bg-blue-500/10 text-blue-400 rounded-xl w-fit">
                  <Icon size={20} />
                </div>
                <div className="space-y-1.5">
                  <h3 className="font-bold text-sm text-white uppercase tracking-wider">{item.title}</h3>
                  <p className="text-xs text-zinc-500 leading-relaxed font-medium">{item.description}</p>
                </div>
              </div>
            );
          })}
        </div>

        {/* CTA */}
        <div className="max-w-3xl mx-auto p-8 border border-zinc-900 bg-zinc-900/40 rounded-3xl text-center space-y-6">
          <div className="space-y-2">
            <h3 className="text-lg font-bold text-white">Need a Specialized Agent Setup?</h3>
            <p className="text-xs text-zinc-500 max-w-lg mx-auto font-medium">
              We offer customizable configurations for corporate monitoring operations in Bangladesh and global teams.
            </p>
          </div>
          <div className="flex justify-center gap-3">
            <Link
              href="/contact"
              className="inline-flex items-center gap-1.5 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow cursor-pointer"
            >
              Request Custom Quote
              <ArrowRight size={13} />
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
