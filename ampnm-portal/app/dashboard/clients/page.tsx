"use client";

import { useMonitorStore } from "@/store/use-monitor-store";
import { Building, Mail, Calendar, ShieldCheck } from "lucide-react";

export default function ClientsPage() {
  const { organizations, licenses, products } = useMonitorStore();

  return (
    <div className="space-y-8">
      <div>
        <h2 className="text-3xl font-bold tracking-tight text-zinc-950 dark:text-zinc-50">
          Client Directory
        </h2>
        <p className="text-zinc-500 dark:text-zinc-400 text-sm">
          Overview of registered customer accounts, primary contact details, and activated subscription products.
        </p>
      </div>

      <div className="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden flex flex-col">
        <div className="p-6 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
          <h3 className="text-lg font-bold text-zinc-950 dark:text-zinc-50">Client Organizations</h3>
          <span className="text-xs text-zinc-500 dark:text-zinc-400">{organizations.length} accounts total</span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse min-w-[600px]" aria-label="Clients table">
            <thead>
              <tr className="bg-zinc-50/50 dark:bg-zinc-950/50 text-xs font-semibold text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800">
                <th className="p-4">Organization Name</th>
                <th className="p-4">Contact Email</th>
                <th className="p-4">Active Subscription Tiers</th>
                <th className="p-4">Total Keys</th>
                <th className="p-4 text-right">Registration Date</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-zinc-100 dark:divide-zinc-800 text-sm">
              {organizations.map((org) => {
                // Find all active products for this organization
                const orgLicenses = licenses.filter((l) => l.orgId === org.id);
                const activeLicenses = orgLicenses.filter((l) => l.status === "active");
                const activeProducts = activeLicenses.map((lic) => {
                  return products.find((p) => p.id === lic.productId)?.name || "Unknown Product";
                });
                // Deduplicate product names
                const uniqueProducts = Array.from(new Set(activeProducts));

                return (
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
                      {uniqueProducts.length > 0 ? (
                        <div className="flex flex-wrap gap-1.5">
                          {uniqueProducts.map((prodName, idx) => (
                            <span
                              key={idx}
                              className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border-blue-200 dark:border-blue-950"
                            >
                              <ShieldCheck size={10} />
                              {prodName}
                            </span>
                          ))}
                        </div>
                      ) : (
                        <span className="text-xs text-zinc-400 dark:text-zinc-500 italic">No active products</span>
                      )}
                    </td>
                    <td className="p-4 text-zinc-600 dark:text-zinc-400 font-medium">
                      {orgLicenses.length} generated ({activeLicenses.length} active)
                    </td>
                    <td className="p-4 text-right text-xs text-zinc-500 dark:text-zinc-400">
                      <span className="inline-flex items-center gap-1 justify-end w-full">
                        <Calendar size={12} />
                        {typeof org.createdAt === "string" ? org.createdAt : org.createdAt.toLocaleDateString()}
                      </span>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
