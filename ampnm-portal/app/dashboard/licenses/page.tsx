"use client";

import { useState } from "react";
import { useMonitorStore } from "@/store/use-monitor-store";
import { FileText, ShieldCheck, Key, ShieldAlert, Ban, Plus, Building, Package, Copy, Check, Globe } from "lucide-react";
import { cn } from "@/lib/utils";
import { License } from "@/types";

export default function LicensesPage() {
  const { licenses, organizations, products, addLicense, revokeLicense, profile, paymentSettings } = useMonitorStore();
  const [selectedOrgId, setSelectedOrgId] = useState("");
  const [selectedProductId, setSelectedProductId] = useState("");
  const [txnId, setTxnId] = useState("");
  const [selectedGateway, setSelectedGateway] = useState<"bkash" | "rocket" | "nagad">("bkash");
  const [showForm, setShowForm] = useState(false);
  const [loading, setLoading] = useState(false);
  const [generatedKey, setGeneratedKey] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);

  const getStatusColor = (status: string) => {
    switch (status) {
      case "active":
        return "bg-emerald-50 text-emerald-700 dark:text-emerald-400 dark:bg-emerald-950/40 border-emerald-500/20";
      case "revoked":
        return "bg-red-50 text-red-700 dark:text-red-400 dark:bg-red-950/40 border-red-500/20";
      case "expired":
        return "bg-zinc-50 text-zinc-700 dark:text-zinc-400 dark:bg-zinc-950/40 border-zinc-500/20";
      case "pending_payment":
        return "bg-amber-50 text-amber-700 dark:text-amber-400 dark:bg-amber-950/40 border-amber-500/20";
      default:
        return "bg-zinc-50 border-zinc-200";
    }
  };

  const selectedProduct = products.find(p => p.id === selectedProductId);
  const isPaidProduct = selectedProduct ? selectedProduct.price > 0 : false;

  // Enforce customer scoping: normal users only manage their own organization's licenses
  const targetOrgId = profile?.role === "admin" ? selectedOrgId : profile?.orgId || "";

  const handleGenerateLicense = (e: React.FormEvent) => {
    e.preventDefault();
    const finalOrgId = targetOrgId || "admin-global";
    if (!finalOrgId || !selectedProductId) return;
    
    setLoading(true);
    setGeneratedKey(null);
    
    // Simulate payment clearing latency
    setTimeout(() => {
      const formattedKey = "AMP256-B713A3E37B5FE53C-6F38AE70F5CC0DF6-EBB7A8AEFFB261B0-9401F60994D97223";
      
      const newLic: License = {
        id: `l_${Date.now()}`,
        key: formattedKey,
        orgId: finalOrgId,
        productId: selectedProductId,
        status: isPaidProduct ? "active" : "active", // Commercial products activated upon mock checkout
        createdAt: new Date().toISOString().split("T")[0],
        expiresAt: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split("T")[0],
      };

      addLicense(newLic);
      setGeneratedKey(formattedKey);
      
      // Clear forms
      setSelectedOrgId("");
      setSelectedProductId("");
      setTxnId("");
      setLoading(false);
    }, 1000);
  };

  const handleCopy = () => {
    if (!generatedKey) return;
    navigator.clipboard.writeText(generatedKey);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const currentOrgName = organizations.find(o => o.id === profile?.orgId)?.name || "My Organization";

  return (
    <div className="space-y-8">
      {/* Licenses Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight text-zinc-950 dark:text-zinc-50">
            Licensing Keys
          </h2>
          <p className="text-zinc-500 dark:text-zinc-400 text-sm">
            Generate free keys for AMPNM Core, or acquire commercial node licenses for other software packages.
          </p>
        </div>
        
        <button
          onClick={() => {
            setShowForm(!showForm);
            setGeneratedKey(null);
          }}
          className="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm rounded-lg shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-blue-500 cursor-pointer"
        >
          <Plus size={16} />
          {profile?.role === "admin" ? "Issue New License" : "Request License Key"}
        </button>
      </div>

      {/* Generate Key Form */}
      {showForm && (
        <div className="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm space-y-4 max-w-xl transition-all">
          <h3 className="text-sm font-bold text-zinc-900 dark:text-zinc-50 flex items-center gap-1.5">
            <Key size={16} className="text-blue-500" />
            {profile?.role === "admin" ? "Generate Key Allocations (Admin Panel)" : "Generate Free / Premium Key"}
          </h3>
          
          <form onSubmit={handleGenerateLicense} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              {profile?.role === "admin" ? (
                <div>
                  <label className="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Target Client Organization</label>
                  <select
                    value={selectedOrgId}
                    onChange={(e) => setSelectedOrgId(e.target.value)}
                    className="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-lg text-sm text-zinc-900 dark:text-zinc-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="">Select Account (Default: Admin Global)...</option>
                    {organizations.map((org) => (
                      <option key={org.id} value={org.id}>{org.name}</option>
                    ))}
                  </select>
                </div>
              ) : (
                <div>
                  <label className="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Organization Workspace</label>
                  <div className="w-full px-3 py-2 bg-zinc-100 dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 rounded-lg text-sm text-zinc-800 dark:text-zinc-200 font-bold select-none">
                    {currentOrgName}
                  </div>
                </div>
              )}

              <div>
                <label className="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Select Software Product</label>
                <select
                  required
                  value={selectedProductId}
                  onChange={(e) => setSelectedProductId(e.target.value)}
                  className="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-lg text-sm text-zinc-900 dark:text-zinc-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">Select Package...</option>
                  {products.map((prod) => (
                    <option key={prod.id} value={prod.id}>
                      {prod.name} ({prod.price === 0 ? "FREE" : `$${prod.price}/mo`})
                    </option>
                  ))}
                </select>
              </div>
            </div>

            {/* If paid commercial product selected, show cash checkout system */}
            {isPaidProduct && selectedProduct && (
              <div className="p-4 border border-zinc-250 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-950/20 rounded-xl space-y-4 animate-in fade-in slide-in-from-top-2">
                <div className="flex items-center gap-2">
                  <ShieldAlert size={16} className="text-amber-500" />
                  <h4 className="text-xs font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wide">MFS Billing Gateway Checkout</h4>
                </div>
                <p className="text-[11px] text-zinc-500 leading-normal">
                  To acquire commercial nodes, please cash out the payment of <strong className="text-zinc-950 dark:text-zinc-50">${selectedProduct.price} USD</strong> to our official gateway number.
                </p>

                {/* Gateway selection */}
                <div className="grid grid-cols-3 gap-2">
                  {["bkash", "rocket", "nagad"].map((gw) => {
                    const number = paymentSettings[gw as "bkash" | "rocket" | "nagad"]?.number || "Unconfigured";
                    const isEnabled = paymentSettings[gw as "bkash" | "rocket" | "nagad"]?.enabled;
                    if (!isEnabled) return null;
                    return (
                      <button
                        key={gw}
                        type="button"
                        onClick={() => setSelectedGateway(gw as any)}
                        className={cn(
                          "p-3 rounded-lg border text-left flex flex-col justify-between transition-colors cursor-pointer",
                          selectedGateway === gw
                            ? "border-blue-600 bg-blue-50/20 text-blue-600 dark:border-blue-500"
                            : "border-zinc-200 hover:bg-zinc-100/50 dark:border-zinc-800 dark:hover:bg-zinc-800/50"
                        )}
                      >
                        <span className="text-[10px] font-bold uppercase tracking-wider capitalize text-zinc-400">{gw}</span>
                        <span className="text-[11px] font-mono font-bold mt-1 text-zinc-800 dark:text-zinc-100">{number}</span>
                      </button>
                    );
                  })}
                </div>

                <div>
                  <label className="block text-[10px] uppercase font-bold text-zinc-500 mb-1">MFS Transaction ID (TxnID)</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. BB89A92D8X"
                    value={txnId}
                    onChange={(e) => setTxnId(e.target.value)}
                    className="w-full px-3 py-2 bg-white dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-lg text-sm text-zinc-900 dark:text-zinc-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              </div>
            )}

            {/* If free product selected, show open-source notice */}
            {selectedProductId && !isPaidProduct && (
              <div className="p-4 border border-blue-200 dark:border-blue-900/30 bg-blue-50/20 dark:bg-blue-950/10 rounded-xl space-y-1 animate-in fade-in">
                <p className="text-xs font-bold text-blue-700 dark:text-blue-400">Open Source & Free Package</p>
                <p className="text-[11px] text-zinc-500 dark:text-zinc-400 leading-normal">
                  AMPNM Core is free and open source. A cryptographic license key is required to secure your local docker instance configuration, but no purchase is necessary.
                </p>
              </div>
            )}
            
            <div className="flex justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => {
                  setShowForm(false);
                  setGeneratedKey(null);
                }}
                className="px-3 py-1.5 border border-zinc-200 dark:border-zinc-800 text-zinc-650 dark:text-zinc-400 text-sm font-semibold rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-950 cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={loading}
                className="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg cursor-pointer disabled:opacity-50 flex items-center gap-1.5"
              >
                {loading 
                  ? "Verifying..." 
                  : isPaidProduct 
                  ? "Checkout & Verify" 
                  : "Generate Free License"}
              </button>
            </div>
          </form>

          {generatedKey && (
            <div className="mt-4 p-4 bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-900/50 rounded-xl space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold text-blue-700 dark:text-blue-400 uppercase tracking-wider">License Key Generated Successfully</span>
                <button
                  onClick={handleCopy}
                  className="p-1 text-blue-600 hover:bg-blue-100 dark:text-blue-400 dark:hover:bg-blue-900/50 rounded transition-colors flex items-center gap-1 text-xs font-bold"
                >
                  {copied ? (
                    <>
                      <Check size={14} className="text-emerald-500" />
                      <span>Copied!</span>
                    </>
                  ) : (
                    <>
                      <Copy size={14} />
                      <span>Copy</span>
                    </>
                  )}
                </button>
              </div>
              <p className="font-mono text-xs text-blue-900 dark:text-blue-200 break-all select-all p-2 bg-white dark:bg-zinc-950 border border-blue-100 dark:border-blue-950 rounded-lg">
                {generatedKey}
              </p>
              <p className="text-[10px] text-zinc-550 leading-relaxed font-semibold">
                Copy this key and paste it into your local AMPNM web installer or configure the `APP_LICENSE_KEY` environment variable in your `docker-compose.yml`.
              </p>
            </div>
          )}
        </div>
      )}

      {/* Numerical Licenses Summary Info */}
      <div className="grid gap-6 md:grid-cols-3">
        <div className="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex items-center justify-between">
          <div>
            <span className="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block">Active Installs</span>
            <span className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 block mt-1">
              {licenses.filter((l) => l.status === "active" && (profile?.role === "admin" ? true : l.orgId === profile?.orgId)).length} nodes
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
              {licenses.filter((l) => (profile?.role === "admin" ? true : l.orgId === profile?.orgId)).length} keys
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
              {licenses.filter((l) => l.status !== "active" && (profile?.role === "admin" ? true : l.orgId === profile?.orgId)).length} keys
            </span>
          </div>
          <div className="p-3 bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 rounded-lg">
            <ShieldAlert size={20} />
          </div>
        </div>
      </div>

      {/* Licenses Table Grid */}
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
                <th className="p-4">Client/Organization</th>
                <th className="p-4">Bound Product</th>
                <th className="p-4">Status</th>
                <th className="p-4">Last Server IP</th>
                <th className="p-4">Last Verified</th>
                <th className="p-4">Created Date</th>
                <th className="p-4">Expiration Date</th>
                <th className="p-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-zinc-100 dark:divide-zinc-800 text-sm">
              {licenses
                .filter((l) => (profile?.role === "admin" ? true : l.orgId === profile?.orgId))
                .map((lic) => {
                  const orgName = lic.orgId === "admin-global" ? "Admin / Global System" : (organizations.find((o) => o.id === lic.orgId)?.name || "Unknown Client");
                  const prodName = products.find((p) => p.id === lic.productId)?.name || "Unknown Product";
                  return (
                    <tr key={lic.id} className="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 transition-colors">
                      <td className="p-4 font-mono font-bold text-xs text-blue-600 dark:text-blue-400 break-all">{lic.key}</td>
                      <td className="p-4">
                        <span className="inline-flex items-center gap-1.5 font-semibold text-zinc-700 dark:text-zinc-300">
                          <Building size={14} className="text-zinc-400" />
                          {orgName}
                        </span>
                      </td>
                      <td className="p-4">
                        <span className="inline-flex items-center gap-1.5 font-medium text-zinc-600 dark:text-zinc-400">
                          <Package size={14} className="text-zinc-400" />
                          {prodName}
                        </span>
                      </td>
                      <td className="p-4">
                        <span className={cn("inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border", getStatusColor(lic.status))}>
                          <span className="h-1.5 w-1.5 rounded-full bg-current" />
                          {lic.status.toUpperCase()}
                        </span>
                      </td>
                      <td className="p-4">
                        {lic.lastIp ? (
                          <span className="inline-flex items-center gap-1.5 font-mono text-xs font-bold text-zinc-700 dark:text-zinc-300">
                            <Globe size={13} className="text-blue-400" />
                            {lic.lastIp}
                          </span>
                        ) : (
                          <span className="text-xs text-zinc-400 italic">No verification</span>
                        )}
                      </td>
                      <td className="p-4 text-xs text-zinc-500 dark:text-zinc-400">
                        {lic.lastVerifiedAt ? new Date(lic.lastVerifiedAt).toLocaleString() : "—"}
                      </td>
                      <td className="p-4 text-xs text-zinc-500 dark:text-zinc-400">
                        {typeof lic.createdAt === "string" ? lic.createdAt : (lic.createdAt as Date).toLocaleDateString()}
                      </td>
                      <td className="p-4 text-xs text-zinc-500 dark:text-zinc-400">
                        {typeof lic.expiresAt === "string" ? lic.expiresAt : (lic.expiresAt as Date).toLocaleDateString()}
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
                          <span className="text-xs text-zinc-400 dark:text-zinc-500 italic font-medium">No actions</span>
                        )}
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
