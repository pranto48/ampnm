"use client";

import { useMonitorStore } from "@/store/use-monitor-store";
import {
  Cpu,
  HardDrive,
  ArrowUpRight,
  ArrowDownLeft,
  ShieldAlert,
  CheckCircle,
  Network,
  AlertCircle,
} from "lucide-react";
import { cn } from "@/lib/utils";

export default function DashboardHome() {
  const { devices, alerts, metrics, acknowledgeAlert } = useMonitorStore();

  const getStatusColor = (status: string) => {
    switch (status) {
      case "online":
        return "bg-emerald-50 text-emerald-700 dark:text-emerald-400 dark:bg-emerald-950/40 border-emerald-500/20";
      case "warning":
        return "bg-amber-50 text-amber-700 dark:text-amber-400 dark:bg-amber-950/40 border-amber-500/20";
      case "offline":
        return "bg-red-50 text-red-700 dark:text-red-400 dark:bg-red-950/40 border-red-500/20";
      default:
        return "bg-zinc-50 text-zinc-700 dark:text-zinc-400 dark:bg-zinc-950/40 border-zinc-500/20";
    }
  };

  const activeAlerts = alerts.filter((a) => !a.acknowledged);

  return (
    <div className="space-y-8">
      {/* Header Info Block */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight text-zinc-950 dark:text-zinc-50">
            Monitoring Overview
          </h2>
          <p className="text-zinc-500 dark:text-zinc-400">
            Real-time telemetry feeds and active infrastructure alarms.
          </p>
        </div>
        <div className="flex items-center gap-2 px-3.5 py-2 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 rounded-lg text-xs font-semibold w-fit">
          <span className="relative flex h-2 w-2">
            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
          </span>
          All Operations Nominal
        </div>
      </div>

      {/* Numerical Metrics Grid */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        {/* CPU */}
        <div className="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex flex-col justify-between hover:border-blue-500/40 dark:hover:border-blue-500/30 transition-colors">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Average CPU Load</span>
            <div className="p-2 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 rounded-lg">
              <Cpu size={16} />
            </div>
          </div>
          <div className="mt-4">
            <span className="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{metrics.cpuUsage}%</span>
            <div className="w-full bg-zinc-100 dark:bg-zinc-800 h-1.5 rounded-full mt-3 overflow-hidden">
              <div className="bg-blue-600 h-1.5 rounded-full transition-all duration-500" style={{ width: `${metrics.cpuUsage}%` }} />
            </div>
          </div>
        </div>

        {/* Memory */}
        <div className="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex flex-col justify-between hover:border-emerald-500/40 dark:hover:border-emerald-500/30 transition-colors">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Memory Allocation</span>
            <div className="p-2 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 rounded-lg">
              <HardDrive size={16} />
            </div>
          </div>
          <div className="mt-4">
            <span className="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{metrics.memoryUsage}%</span>
            <div className="w-full bg-zinc-100 dark:bg-zinc-800 h-1.5 rounded-full mt-3 overflow-hidden">
              <div className="bg-emerald-600 h-1.5 rounded-full transition-all duration-500" style={{ width: `${metrics.memoryUsage}%` }} />
            </div>
          </div>
        </div>

        {/* Bandwidth In */}
        <div className="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex flex-col justify-between hover:border-indigo-500/40 dark:hover:border-indigo-500/30 transition-colors">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Transit Inbound</span>
            <div className="p-2 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 rounded-lg">
              <ArrowDownLeft size={16} />
            </div>
          </div>
          <div className="mt-4">
            <span className="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{metrics.bandwidthIn} Mbps</span>
            <span className="text-[10px] text-indigo-500 dark:text-indigo-400 block mt-2 font-medium">Ingress switch telemetry</span>
          </div>
        </div>

        {/* Bandwidth Out */}
        <div className="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex flex-col justify-between hover:border-violet-500/40 dark:hover:border-violet-500/30 transition-colors">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Transit Outbound</span>
            <div className="p-2 bg-violet-50 dark:bg-violet-950/40 text-violet-600 dark:text-violet-400 rounded-lg">
              <ArrowUpRight size={16} />
            </div>
          </div>
          <div className="mt-4">
            <span className="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{metrics.bandwidthOut} Mbps</span>
            <span className="text-[10px] text-violet-500 dark:text-violet-400 block mt-2 font-medium">Egress gateway routing</span>
          </div>
        </div>
      </div>

      {/* Operational Panels Row */}
      <div className="grid gap-8 lg:grid-cols-3">
        {/* Node Status Matrix */}
        <div className="lg:col-span-2 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden flex flex-col">
          <div className="p-6 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Network size={18} className="text-blue-500" />
              <h3 className="text-lg font-bold text-zinc-950 dark:text-zinc-50">Monitored Cluster Nodes</h3>
            </div>
            <span className="text-xs text-zinc-500 dark:text-zinc-400">{devices.length} systems declared</span>
          </div>
          
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse min-w-[500px]" aria-label="Monitored cluster systems">
              <thead>
                <tr className="bg-zinc-50/50 dark:bg-zinc-950/50 text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800">
                  <th className="p-4">Hostname</th>
                  <th className="p-4">IP Address</th>
                  <th className="p-4">System Status</th>
                  <th className="p-4">Response Latency</th>
                  <th className="p-4 text-right">Heartbeat</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-100 dark:divide-zinc-800 text-sm">
                {devices.map((device) => (
                  <tr key={device.id} className="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 transition-colors">
                    <td className="p-4 font-semibold text-zinc-900 dark:text-zinc-50">{device.name}</td>
                    <td className="p-4 font-mono text-xs text-zinc-500 dark:text-zinc-400">{device.ip}</td>
                    <td className="p-4">
                      <span className={cn("inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border", getStatusColor(device.status))}>
                        <span className="h-1.5 w-1.5 rounded-full bg-current" />
                        {device.status.toUpperCase()}
                      </span>
                    </td>
                    <td className="p-4 font-medium text-zinc-700 dark:text-zinc-300">
                      {device.status === "offline" ? (
                        <span className="text-zinc-400 dark:text-zinc-600 font-normal">TIMED OUT</span>
                      ) : (
                        <span>{device.responseTime} ms</span>
                      )}
                    </td>
                    <td className="p-4 text-right text-xs text-zinc-500 dark:text-zinc-400">{device.lastActive}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Live Alarms Feed */}
        <div className="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex flex-col">
          <div className="p-6 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <ShieldAlert size={18} className="text-red-500" />
              <h3 className="text-lg font-bold text-zinc-950 dark:text-zinc-50">Active Alarm List</h3>
            </div>
            <span className={cn("text-xs font-bold px-2 py-0.5 rounded-full", activeAlerts.length > 0 ? "bg-red-100 text-red-600 dark:bg-red-950/40 dark:text-red-400" : "bg-green-100 text-green-600 dark:bg-green-950/40 dark:text-green-400")}>
              {activeAlerts.length}
            </span>
          </div>

          <div className="p-6 flex-1 space-y-4 overflow-y-auto max-h-[380px]" aria-live="polite">
            {activeAlerts.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-16 text-zinc-400 dark:text-zinc-600">
                <CheckCircle size={32} className="text-emerald-500 mb-2" />
                <span className="text-sm font-semibold">Alarm Feed Empty</span>
                <span className="text-xs text-zinc-400 dark:text-zinc-500">No warnings or outages logged.</span>
              </div>
            ) : (
              activeAlerts.map((alert) => (
                <div
                  key={alert.id}
                  className={cn(
                    "p-4 rounded-lg border flex flex-col gap-3 transition-colors",
                    alert.severity === "critical"
                      ? "bg-red-50/50 dark:bg-red-950/10 border-red-200 dark:border-red-950/60"
                      : "bg-amber-50/50 dark:bg-amber-950/10 border-amber-200 dark:border-amber-950/60"
                  )}
                >
                  <div className="flex items-start gap-3">
                    <AlertCircle size={18} className={cn("mt-0.5 flex-shrink-0", alert.severity === "critical" ? "text-red-600 dark:text-red-400" : "text-amber-600 dark:text-amber-400")} />
                    <div className="flex-1 text-xs">
                      <span className={cn("font-bold block uppercase tracking-wider mb-0.5", alert.severity === "critical" ? "text-red-700 dark:text-red-400" : "text-amber-700 dark:text-amber-400")}>
                        {alert.severity} event
                      </span>
                      <p className="text-zinc-700 dark:text-zinc-300 font-medium leading-relaxed">
                        {alert.message}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center justify-between border-t border-zinc-200/50 dark:border-zinc-800/50 pt-2.5">
                    <span className="text-[10px] text-zinc-400 font-medium">{alert.timestamp}</span>
                    <button
                      onClick={() => acknowledgeAlert(alert.id)}
                      className="text-[10px] font-bold text-blue-600 dark:text-blue-400 hover:underline focus:outline-none focus:ring-1 focus:ring-blue-500 rounded px-2 py-0.5 bg-blue-50 dark:bg-blue-950/30"
                    >
                      Dismiss
                    </button>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
