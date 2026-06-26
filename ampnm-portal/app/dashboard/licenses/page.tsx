"use client";

import { useState } from "react";
import { useMonitorStore } from "@/store/use-monitor-store";
import { FileText, ShieldCheck, Key, ShieldAlert, Ban, Plus } from "lucide-react";
import { cn } from "@/lib/utils";
import { License } from "@/types";

export default function LicensesPage() {
  const { licenses, addLicense, revokeLicense, profile } = useMonitorStore();
  const [loading, setLoading] = useState(false);

  const getStatusColor = (status: string) => {
    switch (status) {
      case "active":
        return "bg-emerald-50 text-emerald-700 dark:text-emerald-400 dark:bg-emerald-950/40 border-emerald-500/20";
      case "revoked":
        return "bg-red-50 text-red-700 dark:text-red-400 dark:bg-red-950/40 border-red-500/20";
      case "expired":
        return "bg-zinc-50 text-zinc-700 dark:text-zinc-400 dark:bg-zinc-950/40 border-zinc-500/20";
      default:
        return "bg-zinc-50 border-zinc-200";
    }
  };

  const handleGenerateLicense = () => {
    setLoading(true);
    // Simulate generation latency
    setTimeout(() => {
      // Random license key generation
      const randomHex = () => Math.floor((1 + Math.random()) * 0x10000).toString(16).substring(1).toUpperCase();
      const newKey = `AMPNM-DEVC-${randomHex()}-${randomHex()}-${randomHex()}`;
      
      const newLic: License = {
        id: `l_${Date.now()}`,
        key: newKey,
        orgId: profile?.orgId || "org-default",
        status: "active",
        createdAt: new Date().toISOString().split("T")[0],
        expiresAt: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split("T")[0],
      };

      addLicense(newLic);
      setLoading(false);
    }, 400);
  };

  return (
    <div className="space-y-8">
      {/* License Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight text-zinc-950 dark:text-zinc-50">
            Licensing Keys
          </h2>
          <p className="text-zinc-500 dark:text-zinc-400 text-sm">
            Provision and revoke cluster node licenses for organization workspace ID:{" "}
            <strong className="font-mono text-zinc-800 dark:text-zinc-200">{profile?.orgId}</strong>
          </p>
        </div>
        
        <button
          onClick={handleGenerateLicense}
          disabled={loading}
          className="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm rounded-lg shadow-sm disabled:opacity-50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-blue-500 cursor-pointer"
        >
          <Plus size={16} />
          {loading ? "Generating..." : "Issue License Key"}
        </button>
      </div>

      {/* Licenses Summary info cards */}
      <div className="grid gap-6 md:grid-cols-3">
        <div className="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex items-center justify-between">
          <div>
            <span className="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block">Active Installs</span>
            <span className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 block mt-1">
              {licenses.filter((l) => l.status === "active").length} nodes
            </span>
          </div>
          <div className="p-3 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 rounded-lg">
            <ShieldCheck size={20} />
          </div>
        </div>

        <div className="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex items-center justify-between">
          <div>
            <span className="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block">Total Allocated Keys</span>
            <span className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 block mt-1">
              {licenses.length} keys
            </span>
          </div>
          <div className="p-3 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 rounded-lg">
            <Key size={20} />
          </div>
        </div>

        <div className="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex items-center justify-between">
          <div>
            <span className="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block">Revoked/Expired</span>
            <span className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 block mt-1">
              {licenses.filter((l) => l.status !== "active").length} keys
            </span>
          </div>
          <div className="p-3 bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 rounded-lg">
            <ShieldAlert size={20} />
          </div>
        </div>
      </div>

      {/* Licenses Table grid */}
      <div className="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden flex flex-col">
        <div className="p-6 border-b border-zinc-200 dark:border-zinc-800">
          <div className="flex items-center gap-2">
            <FileText size={18} className="text-blue-500" />
            <h3 className="text-lg font-bold text-zinc-950 dark:text-zinc-50">Issued Node Licenses</h3>
          </div>
        </div>
        
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse min-w-[600px]" aria-label="Tenant license keys">
            <thead>
              <tr className="bg-zinc-50/50 dark:bg-zinc-950/50 text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800">
                <th className="p-4">License Key String</th>
                <th className="p-4">Binding Node ID</th>
                <th className="p-4">Status</th>
                <th className="p-4">Created Date</th>
                <th className="p-4">Expiration Date</th>
                <th className="p-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-zinc-100 dark:divide-zinc-800 text-sm">
              {licenses.map((lic) => (
                <tr key={lic.id} className="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 transition-colors">
                  <td className="p-4 font-mono font-bold text-xs text-blue-600 dark:text-blue-400">{lic.key}</td>
                  <td className="p-4 font-semibold text-zinc-700 dark:text-zinc-300">
                    {lic.nodeId ? (
                      <span className="font-mono text-xs">{lic.nodeId}</span>
                    ) : (
                      <span className="text-zinc-400 dark:text-zinc-500 font-normal italic">Unbound</span>
                    )}
                  </td>
                  <td className="p-4">
                    <span className={cn("inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border", getStatusColor(lic.status))}>
                      <span className="h-1.5 w-1.5 rounded-full bg-current" />
                      {lic.status.toUpperCase()}
                    </span>
                  </td>
                  <td className="p-4 text-xs text-zinc-500 dark:text-zinc-400">
                    {typeof lic.createdAt === "string"
                      ? lic.createdAt
                      : (lic.createdAt as Date).toLocaleDateString()}
                  </td>
                  <td className="p-4 text-xs text-zinc-500 dark:text-zinc-400">
                    {typeof lic.expiresAt === "string"
                      ? lic.expiresAt
                      : (lic.expiresAt as Date).toLocaleDateString()}
                  </td>
                  <td className="p-4 text-right">
                    {lic.status === "active" ? (
                      <button
                        onClick={() => revokeLicense(lic.id)}
                        className="inline-flex items-center gap-1 text-xs font-bold text-red-600 hover:text-red-500 dark:text-red-400 hover:underline px-2 py-1 hover:bg-red-50 dark:hover:bg-red-950/20 rounded transition-colors cursor-pointer"
                      >
                        <Ban size={12} />
                        Revoke
                      </button>
                    ) : (
                      <span className="text-xs text-zinc-400 dark:text-zinc-500 italic">No actions</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
