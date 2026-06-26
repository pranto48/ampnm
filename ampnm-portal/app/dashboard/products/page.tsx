"use client";

import { useState } from "react";
import { useMonitorStore } from "@/store/use-monitor-store";
import { Package, Check, Tag, CreditCard, ShieldCheck, ShieldAlert, X, ArrowRight, ExternalLink, HelpCircle, Coffee } from "lucide-react";
import { cn } from "@/lib/utils";
import { License } from "@/types";

export default function ProductsPage() {
  const { products, organizations, addLicense, paymentSettings, profile } = useMonitorStore();
  const [selectedProduct, setSelectedProduct] = useState<any | null>(null);
  const [paymentMethod, setPaymentMethod] = useState<"bkash" | "nagad" | "rocket" | "coffee" | null>(null);
  const [bkashStep, setBkashStep] = useState<1 | 2 | 3 | 4>(1);
  const [phoneNumber, setPhoneNumber] = useState("");
  const [otp, setOtp] = useState("");
  const [pin, setPin] = useState("");
  const [txnId, setTxnId] = useState("");
  const [isVerifying, setIsVerifying] = useState(false);
  const [successKey, setSuccessKey] = useState<string | null>(null);
  const [errorMsg, setErrorMsg] = useState("");

  const clientOrg = organizations.find((o) => o.id === profile?.orgId);
  const isVerified = clientOrg ? (clientOrg.verified ?? true) : true;


  const USD_TO_BDT = 120;

  const handleClose = () => {
    setSelectedProduct(null);
    setPaymentMethod(null);
    setBkashStep(1);
    setPhoneNumber("");
    setOtp("");
    setPin("");
    setTxnId("");
    setSuccessKey(null);
    setErrorMsg("");
  };

  const handlePurchaseClick = (product: any) => {
    setSelectedProduct(product);
  };

  const generateLicense = () => {
    const buffer = new Uint8Array(32);
    window.crypto.getRandomValues(buffer);
    const hexKey = Array.from(buffer, (b) => b.toString(16).padStart(2, "0")).join("").toUpperCase();
    const formattedKey = `AMP256-${hexKey.slice(0, 16)}-${hexKey.slice(16, 32)}-${hexKey.slice(32, 48)}-${hexKey.slice(48, 64)}`;
    
    const defaultOrg = organizations[0]?.id || "org-it";
    
    const newLic: License = {
      id: `l_${Date.now()}`,
      key: formattedKey,
      orgId: defaultOrg,
      productId: selectedProduct.id,
      status: "active",
      createdAt: new Date().toISOString().split("T")[0],
      expiresAt: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().split("T")[0],
    };

    addLicense(newLic);
    setSuccessKey(formattedKey);
  };

  // bKash Flow Submissions
  const handleBkashNext = (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg("");
    
    if (bkashStep === 1) {
      if (!/^(01)[3-9]\d{8}$/.test(phoneNumber)) {
        setErrorMsg("Please enter a valid 11-digit bKash account number.");
        return;
      }
      setIsVerifying(true);
      setTimeout(() => {
        setIsVerifying(false);
        setBkashStep(2);
      }, 800);
    } else if (bkashStep === 2) {
      if (otp.length !== 6) {
        setErrorMsg("OTP must be exactly 6 digits.");
        return;
      }
      setIsVerifying(true);
      setTimeout(() => {
        setIsVerifying(false);
        setBkashStep(3);
      }, 800);
    } else if (bkashStep === 3) {
      if (pin.length < 4) {
        setErrorMsg("Please enter a valid PIN.");
        return;
      }
      setIsVerifying(true);
      setTimeout(() => {
        setIsVerifying(false);
        generateLicense();
        setBkashStep(4);
      }, 1200);
    }
  };

  // Manual transaction ID verification (Nagad / Rocket)
  const handleManualVerify = (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg("");
    
    if (txnId.length < 8) {
      setErrorMsg("Please enter a valid 8-10 character Transaction ID.");
      return;
    }
    
    setIsVerifying(true);
    setTimeout(() => {
      setIsVerifying(false);
      generateLicense();
      setBkashStep(4); // Trigger success layout
    }, 1000);
  };

  return (
    <div className="space-y-8 relative">
      {/* Product Catalog Header */}
      <div>
        <h2 className="text-3xl font-bold tracking-tight text-zinc-950 dark:text-zinc-50">
          Products Catalog (GMEN)
        </h2>
        <p className="text-zinc-500 dark:text-zinc-400 text-sm">
          Overview of software license products, feature boundaries, and purchase activation portals.
        </p>
      </div>

      {/* Verification Banners */}
      {!isVerified && (
        <div className="bg-amber-500/10 border border-amber-500/30 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 animate-in fade-in slide-in-from-top-4">
          <div className="flex items-start gap-3">
            <div className="p-2 bg-amber-500/20 text-amber-600 dark:text-amber-400 rounded-lg">
              <ShieldAlert size={20} />
            </div>
            <div>
              <h4 className="font-bold text-sm text-zinc-900 dark:text-zinc-50">Organization Account Verification Pending</h4>
              <p className="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                Your workspace organization <strong className="text-zinc-700 dark:text-zinc-300">{clientOrg?.name}</strong> is currently unverified. Some features may be restricted until verified.
              </p>
            </div>
          </div>
          <button
            onClick={async () => {
              if (!clientOrg) return;
              setIsVerifying(true);
              try {
                const verificationLink = `https://portal.itsupport.com.bd/verify-email?orgId=${clientOrg.id}`;
                const emailHtml = `
                  <div style="font-family: Arial; max-width: 600px; padding: 24px; border: 1px solid #e4e4e7; border-radius: 12px;">
                    <h2>Verify Organization Registration</h2>
                    <p>Click below to verify organization account for ${clientOrg.name}:</p>
                    <a href="${verificationLink}" style="background-color: #2563eb; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">Verify Account</a>
                  </div>
                `;
                
                await fetch("/api/send-email", {
                  method: "POST",
                  headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({
                    recipient: clientOrg.clientEmail,
                    subject: `Verify Organization Account - ${clientOrg.name}`,
                    emailHtml,
                  })
                });
                alert("Verification request sent successfully to " + clientOrg.clientEmail);
              } catch (e) {
                console.error(e);
              } finally {
                setIsVerifying(false);
              }
            }}
            disabled={isVerifying}
            className="px-4 py-2 bg-amber-600 text-white rounded-lg text-xs font-bold hover:bg-amber-700 disabled:opacity-55 transition-colors cursor-pointer"
          >
            {isVerifying ? "Requesting..." : "Request Verification Resend"}
          </button>
        </div>
      )}


      {/* Products Grid */}
      <div className="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
        {products.map((product) => {
          const isCluster = product.id === "prod-cluster";
          return (
            <div
              key={product.id}
              className={cn(
                "relative bg-white dark:bg-zinc-900 rounded-2xl border p-8 flex flex-col justify-between shadow-sm hover:shadow-md transition-all duration-200",
                isCluster 
                  ? "border-blue-500 dark:border-blue-500 ring-2 ring-blue-500/10 dark:ring-blue-500/5 scale-105" 
                  : "border-zinc-200 dark:border-zinc-800"
              )}
            >
              {isCluster && (
                <span className="absolute -top-3.5 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-[10px] uppercase font-bold tracking-widest px-3 py-1 rounded-full shadow">
                  Most Popular
                </span>
              )}

              <div>
                <div className="flex items-center gap-3">
                  <div className={cn(
                    "p-2 rounded-lg w-fit",
                    isCluster 
                      ? "bg-blue-100 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400" 
                      : "bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400"
                  )}>
                    <Package size={20} />
                  </div>
                  <h3 className="font-bold text-lg text-zinc-900 dark:text-zinc-50">{product.name}</h3>
                </div>

                <div className="mt-6">
                  <div className="flex items-baseline gap-1">
                    <span className="text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-zinc-50">
                      ${product.price}
                    </span>
                    <span className="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                      / {product.billingPeriod}
                    </span>
                  </div>
                  <p className="text-xs text-zinc-400 mt-1 font-semibold">
                    ≈ {(product.price * USD_TO_BDT).toLocaleString()} BDT
                  </p>
                </div>

                <div className="h-px bg-zinc-200 dark:bg-zinc-800 my-6" />

                {/* Features list */}
                <ul className="space-y-4 text-sm mb-8" aria-label={`Features of ${product.name}`}>
                  {product.features.map((feature, idx) => (
                    <li key={idx} className="flex items-start gap-2.5 text-zinc-600 dark:text-zinc-400 font-medium">
                      <div className="mt-0.5 p-0.5 bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-full flex-shrink-0">
                        <Check size={12} />
                      </div>
                      <span>{feature}</span>
                    </li>
                  ))}
                </ul>
              </div>

              <div className="space-y-4">
                <button
                  onClick={() => handlePurchaseClick(product)}
                  className="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow focus:outline-none cursor-pointer"
                >
                  <CreditCard size={16} />
                  Purchase License
                </button>
                <div className="flex items-center gap-2 text-[10px] font-bold text-zinc-400 uppercase tracking-wider">
                  <Tag size={12} />
                  <span>ID: {product.id}</span>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* CHECKOUT MODAL OVERLAY */}
      {selectedProduct && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-2xl max-w-lg w-full overflow-hidden flex flex-col max-h-[90vh]">
            {/* Modal Header */}
            <div className="p-6 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
              <div>
                <h3 className="font-bold text-zinc-950 dark:text-zinc-50">Checkout Portal</h3>
                <p className="text-xs text-zinc-500">{selectedProduct.name} - ${selectedProduct.price}/mo</p>
              </div>
              <button
                onClick={handleClose}
                className="p-1.5 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800"
              >
                <X size={18} />
              </button>
            </div>

            {/* Modal Content */}
            <div className="p-6 overflow-y-auto flex-1 space-y-6">
              {paymentMethod === null ? (
                // SELECT PAYMENT METHOD
                <div className="space-y-4">
                  <p className="text-sm font-medium text-zinc-600 dark:text-zinc-400">Select Bangladeshi MFS Gateway or Support Developer:</p>
                  
                  <div className="grid gap-3 sm:grid-cols-2">
                    {/* bKash */}
                    {paymentSettings.bkash.enabled && (
                      <button
                        onClick={() => setPaymentMethod("bkash")}
                        className="p-4 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-pink-500 dark:hover:border-pink-500 hover:bg-pink-50/10 text-left transition-all flex flex-col justify-between h-28 cursor-pointer group"
                      >
                        <span className="text-xs font-bold text-pink-600 tracking-wide uppercase">bKash Checkout</span>
                        <span className="text-lg font-bold text-zinc-900 dark:text-zinc-50 group-hover:text-pink-600 transition-colors">
                          {paymentSettings.bkash.type === "personal" ? "Send Money" : "Instant Pay"}
                        </span>
                      </button>
                    )}

                    {/* Nagad */}
                    {paymentSettings.nagad.enabled && (
                      <button
                        onClick={() => setPaymentMethod("nagad")}
                        className="p-4 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-orange-500 dark:hover:border-orange-500 hover:bg-orange-50/10 text-left transition-all flex flex-col justify-between h-28 cursor-pointer group"
                      >
                        <span className="text-xs font-bold text-orange-600 tracking-wide uppercase">Nagad wallet</span>
                        <span className="text-lg font-bold text-zinc-900 dark:text-zinc-50 group-hover:text-orange-600 transition-colors">
                          {paymentSettings.nagad.type === "personal" ? "Send Money" : "Manual Txn"}
                        </span>
                      </button>
                    )}

                    {/* Rocket */}
                    {paymentSettings.rocket.enabled && (
                      <button
                        onClick={() => setPaymentMethod("rocket")}
                        className="p-4 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-purple-500 dark:hover:border-purple-500 hover:bg-purple-50/10 text-left transition-all flex flex-col justify-between h-28 cursor-pointer group"
                      >
                        <span className="text-xs font-bold text-purple-600 tracking-wide uppercase">DBBL Rocket</span>
                        <span className="text-lg font-bold text-zinc-900 dark:text-zinc-50 group-hover:text-purple-600 transition-colors">
                          {paymentSettings.rocket.type === "personal" ? "Send Money" : "Manual Txn"}
                        </span>
                      </button>
                    )}

                    {/* Buy Me a Coffee */}
                    <a
                      href="https://buymeacoffee.com/pranto48"
                      target="_blank"
                      rel="noopener noreferrer"
                      onClick={() => setPaymentMethod("coffee")}
                      className="p-4 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-amber-500 dark:hover:border-amber-500 hover:bg-amber-50/10 text-left transition-all flex flex-col justify-between h-28 cursor-pointer group"
                    >
                      <span className="text-xs font-bold text-amber-600 tracking-wide uppercase flex items-center gap-1">
                        <Coffee size={12} />
                        Buy Me a Coffee
                      </span>
                      <span className="text-lg font-bold text-zinc-900 dark:text-zinc-50 group-hover:text-amber-600 transition-colors flex items-center gap-1">
                        pranto48
                        <ExternalLink size={14} className="text-zinc-400" />
                      </span>
                    </a>
                  </div>

                  <div className="bg-zinc-50 dark:bg-zinc-950 p-4 rounded-xl text-xs text-zinc-500 dark:text-zinc-400 flex gap-2">
                    <HelpCircle size={16} className="text-zinc-400 flex-shrink-0 mt-0.5" />
                    <span>Selected licensing item will automatically map to your active organization workspace account upon payment confirmation.</span>
                  </div>
                </div>
              ) : paymentMethod === "bkash" ? (
                // BKASH MODAL INTERACTIVE CHECKOUT OVERLAY
                <div className="flex flex-col items-center justify-center">
                  {/* bKash Frame Header */}
                  <div className="w-full max-w-sm bg-pink-600 text-white rounded-t-xl py-3 px-4 flex items-center justify-between font-bold text-sm">
                    <span className="tracking-widest italic text-base">bKash</span>
                    <span>Merchant Transfer</span>
                  </div>

                  {/* bKash Interactive Panel Frame */}
                  <div className="w-full max-w-sm bg-pink-700 text-white p-6 rounded-b-xl flex flex-col items-center space-y-4">
                    <div className="w-16 h-16 rounded-full bg-white text-pink-600 flex items-center justify-center font-extrabold text-xl shadow select-none">
                      ৳
                    </div>
                    
                    <div className="text-center">
                      <p className="text-xs text-pink-100 uppercase tracking-widest font-semibold">
                        Amount to Pay ({paymentSettings.bkash.type === "personal" ? "Send Money" : "Merchant Pay"})
                      </p>
                      <p className="text-xl font-extrabold">৳ {(selectedProduct.price * USD_TO_BDT).toLocaleString()}</p>
                    </div>

                    {bkashStep === 4 ? (
                      // BKASH SUCCESS
                      <div className="w-full text-center space-y-3 bg-pink-850 p-4 rounded-lg">
                        <div className="w-10 h-10 rounded-full bg-emerald-500 text-white flex items-center justify-center mx-auto shadow">
                          <Check size={20} />
                        </div>
                        <p className="font-bold text-sm text-pink-100">bKash Transaction Succeeded</p>
                        <p className="text-[10px] text-pink-200 font-mono break-all bg-pink-900/50 p-2 rounded">
                          License Key: {successKey}
                        </p>
                        <button
                          onClick={handleClose}
                          className="w-full py-2 bg-white text-pink-700 rounded-lg text-xs font-bold hover:bg-pink-50 transition-colors cursor-pointer"
                        >
                          Access License Console
                        </button>
                      </div>
                    ) : (
                      // BKASH INPUT FIELDS
                      <form onSubmit={handleBkashNext} className="w-full space-y-4">
                        {bkashStep === 1 && (
                          <div className="space-y-1">
                            <label className="block text-[10px] text-pink-200 uppercase font-bold tracking-wider">bKash Account Number</label>
                            <input
                              type="tel"
                              required
                              value={phoneNumber}
                              onChange={(e) => setPhoneNumber(e.target.value)}
                              placeholder="e.g. 017XXXXXXXX"
                              className="w-full text-center py-2.5 bg-pink-800 border-none text-white rounded-lg placeholder-pink-400 focus:outline-none focus:ring-2 focus:ring-white/40 font-bold tracking-widest"
                            />
                          </div>
                        )}

                        {bkashStep === 2 && (
                          <div className="space-y-1">
                            <label className="block text-[10px] text-pink-200 uppercase font-bold tracking-wider">Enter OTP Verification Code</label>
                            <input
                              type="text"
                              required
                              maxLength={6}
                              value={otp}
                              onChange={(e) => setOtp(e.target.value)}
                              placeholder="6-digit verification code"
                              className="w-full text-center py-2.5 bg-pink-800 border-none text-white rounded-lg placeholder-pink-400 focus:outline-none focus:ring-2 focus:ring-white/40 font-bold tracking-widest"
                            />
                          </div>
                        )}

                        {bkashStep === 3 && (
                          <div className="space-y-1">
                            <label className="block text-[10px] text-pink-200 uppercase font-bold tracking-wider">Enter Account PIN</label>
                            <input
                              type="password"
                              required
                              maxLength={5}
                              value={pin}
                              onChange={(e) => setPin(e.target.value)}
                              placeholder="•••••"
                              className="w-full text-center py-2.5 bg-pink-800 border-none text-white rounded-lg placeholder-pink-400 focus:outline-none focus:ring-2 focus:ring-white/40 font-bold tracking-widest"
                            />
                          </div>
                        )}

                        {errorMsg && (
                          <div className="text-[10px] bg-pink-900/60 p-2 rounded border border-pink-500 text-pink-100 flex items-start gap-1">
                            <ShieldAlert size={12} className="flex-shrink-0 mt-0.5" />
                            <span>{errorMsg}</span>
                          </div>
                        )}

                        <div className="flex gap-2 pt-2">
                          {bkashStep > 1 && (
                            <button
                              type="button"
                              onClick={() => setBkashStep((s) => (s - 1) as any)}
                              className="w-1/3 py-2.5 border border-white/20 text-white rounded-lg text-xs font-bold hover:bg-white/10"
                            >
                              Back
                            </button>
                          )}
                          <button
                            type="submit"
                            disabled={isVerifying}
                            className="flex-1 py-2.5 bg-white text-pink-700 rounded-lg text-xs font-bold hover:bg-pink-50 disabled:opacity-50 transition-colors cursor-pointer"
                          >
                            {isVerifying ? "Verifying..." : bkashStep === 3 ? "Confirm Payment" : "Proceed"}
                          </button>
                        </div>
                      </form>
                    )}
                  </div>
                </div>
              ) : paymentMethod === "coffee" ? (
                // BUY ME A COFFEE REDIRECT PANEL
                <div className="text-center space-y-4">
                  <div className="w-16 h-16 rounded-full bg-amber-100 dark:bg-amber-950/40 text-amber-500 flex items-center justify-center mx-auto shadow-sm">
                    <Coffee size={24} />
                  </div>
                  <div className="space-y-1">
                    <h4 className="font-bold text-zinc-900 dark:text-zinc-50">Support Developer (pranto48)</h4>
                    <p className="text-xs text-zinc-500 max-w-sm mx-auto">Redirect to Buy Me a Coffee to donate or make custom direct transfers using external gateways.</p>
                  </div>

                  {bkashStep === 4 ? (
                    // SUCCESS INFO AFTER MOCK DONATION CONFIRMATION
                    <div className="w-full text-center space-y-3 bg-emerald-50 dark:bg-emerald-950/30 p-4 rounded-xl border border-emerald-200 dark:border-emerald-900/40">
                      <div className="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center mx-auto shadow">
                        <Check size={16} />
                      </div>
                      <p className="font-bold text-xs text-emerald-800 dark:text-emerald-400">Coffee Transfer Confirmed</p>
                      <p className="text-[10px] font-mono break-all bg-white dark:bg-zinc-950 p-2 rounded text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-900 select-all select-none">
                        License Key: {successKey}
                      </p>
                    </div>
                  ) : (
                    <div className="space-y-3 pt-2">
                      <a
                        href="https://buymeacoffee.com/pranto48"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="w-full flex items-center justify-center gap-2 py-3 px-4 bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold rounded-xl transition-colors cursor-pointer"
                      >
                        Visit Buy Me A Coffee Profile
                        <ExternalLink size={14} />
                      </a>
                      <button
                        onClick={() => {
                          setIsVerifying(true);
                          setTimeout(() => {
                            setIsVerifying(false);
                            generateLicense();
                            setBkashStep(4);
                          }, 1000);
                        }}
                        className="w-full py-2.5 border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-950 text-zinc-700 dark:text-zinc-300 text-xs font-semibold rounded-xl cursor-pointer"
                      >
                        Confirm Coffee Completed
                      </button>
                    </div>
                  )}
                </div>
              ) : (
                // MANUAL MFS INPUT (NAGAD / ROCKET)
                <div className="space-y-4">
                  <div className="flex items-center gap-3">
                    <div className={cn(
                      "w-10 h-10 rounded-full flex items-center justify-center font-bold text-lg select-none",
                      paymentMethod === "nagad" ? "bg-orange-100 text-orange-600" : "bg-purple-100 text-purple-600"
                    )}>
                      ৳
                    </div>
                    <div>
                      <h4 className="font-bold text-sm text-zinc-900 dark:text-zinc-50 capitalize">{paymentMethod} Payment</h4>
                      <p className="text-[10px] text-zinc-500">Manual Cashout Transfer</p>
                    </div>
                  </div>

                  {bkashStep === 4 ? (
                    // MANUAL SUCCESS
                    <div className="w-full text-center space-y-3 bg-emerald-50 dark:bg-emerald-950/30 p-4 rounded-xl border border-emerald-200 dark:border-emerald-900/40">
                      <div className="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center mx-auto shadow">
                        <Check size={16} />
                      </div>
                      <p className="font-bold text-xs text-emerald-800 dark:text-emerald-400">Payment Verified Succeeded</p>
                      <p className="text-[10px] font-mono break-all bg-white dark:bg-zinc-950 p-2 rounded text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-900 select-all select-none">
                        License Key: {successKey}
                      </p>
                    </div>
                  ) : (
                    <form onSubmit={handleManualVerify} className="space-y-4">
                      {/* Step-by-step Cashout instruction */}
                      <div className="bg-zinc-50 dark:bg-zinc-950 p-4 rounded-xl space-y-2 text-xs text-zinc-600 dark:text-zinc-400">
                        <p className="font-bold uppercase tracking-wider text-[10px] text-zinc-400">Instructions:</p>
                        <ol className="list-decimal pl-4 space-y-1">
                          <li>Go to your {paymentMethod === "nagad" ? "Nagad" : "Rocket"} wallet app.</li>
                          <li>
                            {paymentMethod && paymentSettings[paymentMethod as keyof typeof paymentSettings]?.type === "personal" 
                              ? "Send Money" 
                              : "Cash Out"
                            } <strong className="text-zinc-900 dark:text-zinc-50">৳ {(selectedProduct.price * USD_TO_BDT).toLocaleString()}</strong> to the {paymentMethod && paymentSettings[paymentMethod as keyof typeof paymentSettings]?.type} account number: <strong className="text-zinc-900 dark:text-zinc-50">
                              {paymentMethod && paymentSettings[paymentMethod as keyof typeof paymentSettings]?.number}
                            </strong>.
                          </li>
                          <li>Once the transfer completes, enter the Transaction ID below for verification.</li>
                        </ol>
                      </div>

                      <div className="space-y-1.5">
                        <label className="block text-xs font-semibold text-zinc-500 uppercase tracking-wider">Transaction ID (TxnID)</label>
                        <input
                          type="text"
                          required
                          value={txnId}
                          onChange={(e) => setTxnId(e.target.value)}
                          placeholder="e.g. 8K3DA9S7D"
                          className="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 text-zinc-900 dark:text-zinc-50 font-mono tracking-wider"
                        />
                      </div>

                      {errorMsg && (
                        <div className="text-[10px] bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/40 p-2.5 rounded-lg text-red-600 dark:text-red-400 flex items-start gap-1">
                          <ShieldAlert size={12} className="flex-shrink-0 mt-0.5" />
                          <span>{errorMsg}</span>
                        </div>
                      )}

                      <div className="flex gap-2">
                        <button
                          type="button"
                          onClick={() => setPaymentMethod(null)}
                          className="w-1/3 py-2 border border-zinc-200 dark:border-zinc-800 text-zinc-600 dark:text-zinc-400 text-xs font-semibold rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-950"
                        >
                          Choose Other
                        </button>
                        <button
                          type="submit"
                          disabled={isVerifying}
                          className="flex-1 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg disabled:opacity-50 transition-colors cursor-pointer"
                        >
                          {isVerifying ? "Contacting Gateway..." : "Verify Payment Transaction"}
                        </button>
                      </div>
                    </form>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
