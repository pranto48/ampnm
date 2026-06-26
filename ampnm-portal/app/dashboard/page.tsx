"use client";

import { useState } from "react";
import { useMonitorStore } from "@/store/use-monitor-store";
import { Building, Users, CreditCard, ShieldCheck, Mail, Calendar, Key, Plus } from "lucide-react";
import { cn } from "@/lib/utils";
import { Organization } from "@/types";

export default function DashboardHome() {
  const { organizations, licenses, products, addOrganization } = useMonitorStore();
  const [newOrgName, setNewOrgName] = useState("");
  const [newOrgEmail, setNewOrgEmail] = useState("");
  const [showForm, setShowForm] = useState(false);

  const activeLicensesCount = licenses.filter((l) => l.status === "active").length;

  // Calculate MRR from active licenses
  const mrr = licenses
    .filter((l) => l.status === "active")
    .reduce((sum, lic) => {
      const prod = products.find((p) => p.id === lic.productId);
      return sum + (prod ? prod.price : 0);
    }, 0);

  const handleAddClient = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newOrgName || !newOrgEmail) return;

    const newOrg: Organization = {
      id: `org_${Date.now()}`,
      name: newOrgName,
      clientEmail: newOrgEmail,
      createdAt: new Date().toISOString().split("T")[0],
      licenseCount: 0,
    };

    addOrganization(newOrg);
    setNewOrgName("");
    setNewOrgEmail("");
    setShowForm(false);
  };

  return (
    <div className="space-y-8">
      {/* SaaS Admin Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight text-zinc-950 dark:text-zinc-50">
            Overview Dashboard
          </h2>
          <p className="text-zinc-500 dark:text-zinc-400 text-sm">
            Administrative console for Client Organizations, Subscriptions, and Licensing.
          </p>
        </div>

        <button
          onClick={() => setShowForm(!showForm)}
          className="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm rounded-lg shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-blue-500 cursor-pointer"
        >
          <Plus size={16} />
          Add Client Organization
        </button>
      </div>

      {/* Add Client Inline Form */}
      {showForm && (
        <form onSubmit={handleAddClient} className="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm space-y-4 max-w-xl">
          <h3 className="text-sm font-bold text-zinc-900 dark:text-zinc-50">Register New Client Account</h3>
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label className="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Company Name</label>
              <input
                type="text"
                required
                value={newOrgName}
                onChange={(e) => setNewOrgName(e.target.value)}
                className="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-lg text-sm text-zinc-900 dark:text-zinc-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Client Corp"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Admin Email Address</label>
              <input
                type="email"
                required
                value={newOrgEmail}
                onChange={(e) => setNewOrgEmail(e.target.value)}
                className="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-lg text-sm text-zinc-900 dark:text-zinc-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="admin@client.com"
              />
            </div>
          </div>
          <div className="flex justify-end gap-3 pt-2">
            <button
              type="button"
              onClick={() => setShowForm(false)}
              className="px-3 py-1.5 border border-zinc-200 dark:border-zinc-800 text-zinc-600 dark:text-zinc-400 text-sm font-semibold rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-950"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg cursor-pointer"
            >
              Save Client
            </button>
          </div>
        </form>
      )}

      {/* Metrics Summary Grid */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        {/* Total Clients */}
        <div className="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex flex-col justify-between hover:border-blue-500/40 transition-colors">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Registered Clients</span>
            <div className="p-2 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 rounded-lg">
              <Building size={16} />
            </div>
          </div>
          <div className="mt-4">
            <span className="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{organizations.length}</span>
            <span className="text-[10px] text-zinc-400 block mt-2">Active corporate accounts</span>
          </div>
        </div>

        {/* Active Licenses */}
        <div className="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex flex-col justify-between hover:border-emerald-500/40 transition-colors">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Active License Nodes</span>
            <div className="p-2 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 rounded-lg">
              <ShieldCheck size={16} />
            </div>
          </div>
          <div className="mt-4">
            <span className="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{activeLicensesCount}</span>
            <span className="text-[10px] text-zinc-400 block mt-2">Valid cluster installations</span>
          </div>
        </div>

        {/* MRR */}
        <div className="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex flex-col justify-between hover:border-indigo-500/40 transition-colors">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Recurring Revenue (MRR)</span>
            <div className="p-2 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 rounded-lg">
              <CreditCard size={16} />
            </div>
          </div>
          <div className="mt-4">
            <span className="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">${mrr} / mo</span>
            <span className="text-[10px] text-indigo-500 dark:text-indigo-400 block mt-2 font-medium">Active license subscriptions</span>
          </div>
        </div>

        {/* System Keys */}
        <div className="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex flex-col justify-between hover:border-violet-500/40 transition-colors">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Licensing Nodes</span>
            <div className="p-2 bg-violet-50 dark:bg-violet-950/40 text-violet-600 dark:text-violet-400 rounded-lg">
              <Key size={16} />
            </div>
          </div>
          <div className="mt-4">
            <span className="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{licenses.length}</span>
            <span className="text-[10px] text-zinc-400 block mt-2">Total generated license keys</span>
          </div>
        </div>
      </div>

      {/* Client Accounts Table */}
      <div className="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden flex flex-col">
        <div className="p-6 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Users size={18} className="text-blue-500" />
            <h3 className="text-lg font-bold text-zinc-950 dark:text-zinc-50">Client Organizations Management</h3>
          </div>
          <span className="text-xs text-zinc-500 dark:text-zinc-400">{organizations.length} accounts listed</span>
        </div>
        
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse min-w-[500px]" aria-label="Tenant organization clients">
            <thead>
              <tr className="bg-zinc-50/50 dark:bg-zinc-950/50 text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800">
                <th className="p-4">Organization Name</th>
                <th className="p-4">Billing/Admin Email</th>
                <th className="p-4">Issued Keys</th>
                <th className="p-4 text-right">Registration Date</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-zinc-100 dark:divide-zinc-800 text-sm">
              {organizations.map((org) => (
                <tr key={org.id} className="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 transition-colors">
                  <td className="p-4 font-semibold text-zinc-900 dark:text-zinc-50 flex items-center gap-2">
                    <Building size={16} className="text-zinc-400" />
                    {org.name}
                  </td>
                  <td className="p-4 text-zinc-500 dark:text-zinc-400">
                    <span className="inline-flex items-center gap-1.5">
                      <Mail size={12} />
                      {org.clientEmail}
                    </span>
                  </td>
                  <td className="p-4">
                    <span className={cn(
                      "inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold border",
                      org.licenseCount > 0 
                        ? "bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border-blue-200 dark:border-blue-950" 
                        : "bg-zinc-50 text-zinc-600 dark:bg-zinc-950/40 dark:text-zinc-500 border-zinc-200 dark:border-zinc-800"
                    )}>
                      {org.licenseCount} active keys
                    </span>
                  </td>
                  <td className="p-4 text-right text-xs text-zinc-500 dark:text-zinc-400">
                    <span className="inline-flex items-center gap-1 justify-end w-full">
                      <Calendar size={12} />
                      {org.createdAt}
                    </span>
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
