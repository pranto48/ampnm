"use client";

import { useState } from "react";
import { useMonitorStore } from "@/store/use-monitor-store";
import { 
  ShieldCheck, 
  CreditCard, 
  Mail, 
  Users, 
  History, 
  Save, 
  RefreshCw, 
  Check, 
  AlertTriangle, 
  Eye, 
  Send, 
  Building, 
  Key,
  X,
  Sparkles
} from "lucide-react";
import { cn } from "@/lib/utils";
import { PaymentSettings, MailSettings, EmailLog } from "@/types";

export default function AdminDashboardPage() {
  const { 
    organizations, 
    licenses,
    paymentSettings, 
    mailSettings, 
    emailLogs, 
    updatePaymentSettings, 
    updateMailSettings, 
    addEmailLog, 
    toggleOrgVerification 
  } = useMonitorStore();

  const [activeTab, setActiveTab] = useState<"payments" | "email-settings" | "clients" | "logs">("payments");
  const [successMsg, setSuccessMsg] = useState("");
  const [errorMsg, setErrorMsg] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Payments State
  const [bkashEnabled, setBkashEnabled] = useState(paymentSettings.bkash.enabled);
  const [bkashNumber, setBkashNumber] = useState(paymentSettings.bkash.number);
  const [bkashType, setBkashType] = useState(paymentSettings.bkash.type);

  const [rocketEnabled, setRocketEnabled] = useState(paymentSettings.rocket.enabled);
  const [rocketNumber, setRocketNumber] = useState(paymentSettings.rocket.number);
  const [rocketType, setRocketType] = useState(paymentSettings.rocket.type);

  const [nagadEnabled, setNagadEnabled] = useState(paymentSettings.nagad.enabled);
  const [nagadNumber, setNagadNumber] = useState(paymentSettings.nagad.number);
  const [nagadType, setNagadType] = useState(paymentSettings.nagad.type);

  // Email Config State
  const [resendApiKey, setResendApiKey] = useState(mailSettings.resendApiKey);
  const [fromEmail, setFromEmail] = useState(mailSettings.fromEmail);

  // Custom Notification State
  const [selectedOrgId, setSelectedOrgId] = useState("");
  const [noticeSubject, setNoticeSubject] = useState("");
  const [noticeBody, setNoticeBody] = useState("");

  // Modal Email Log Preview State
  const [previewLog, setPreviewLog] = useState<EmailLog | null>(null);

  const triggerAlert = (type: "success" | "error", msg: string) => {
    if (type === "success") {
      setSuccessMsg(msg);
      setErrorMsg("");
      setTimeout(() => setSuccessMsg(""), 3000);
    } else {
      setErrorMsg(msg);
      setSuccessMsg("");
      setTimeout(() => setErrorMsg(""), 4000);
    }
  };

  const handleSavePayments = (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setTimeout(() => {
      const updated: PaymentSettings = {
        bkash: { enabled: bkashEnabled, number: bkashNumber, type: bkashType },
        rocket: { enabled: rocketEnabled, number: bkashNumber.length > 0 && rocketNumber ? rocketNumber : "019158222660", type: rocketType },
        nagad: { enabled: nagadEnabled, number: nagadNumber, type: nagadType }
      };
      updatePaymentSettings(updated);
      setIsSubmitting(false);
      triggerAlert("success", "Payment configurations updated successfully.");
    }, 600);
  };

  const handleSaveEmailConfig = (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setTimeout(() => {
      const updated: MailSettings = {
        resendApiKey,
        fromEmail
      };
      updateMailSettings(updated);
      setIsSubmitting(false);
      triggerAlert("success", "Email configuration settings committed successfully.");
    }, 600);
  };

  const sendEmailAPI = async (recipient: string, subject: string, emailHtml: string, type: "verification" | "license" | "custom") => {
    try {
      const response = await fetch("/api/send-email", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          recipient,
          subject,
          emailHtml,
          resendApiKey,
          fromEmail
        })
      });

      const res = await response.json();
      
      const newLog: EmailLog = {
        id: `email_${Date.now()}`,
        recipient,
        subject,
        body: emailHtml,
        timestamp: new Date().toISOString(),
        status: res.status || "simulated",
        type
      };

      addEmailLog(newLog);
      return res;
    } catch (e: any) {
      console.error(e);
      const newLog: EmailLog = {
        id: `email_${Date.now()}`,
        recipient,
        subject,
        body: emailHtml,
        timestamp: new Date().toISOString(),
        status: "failed",
        type
      };
      addEmailLog(newLog);
      throw e;
    }
  };

  // Trigger Client Registration Verification Email
  const handleSendVerification = async (org: any) => {
    const verificationLink = `https://portal.itsupport.com.bd/verify-email?orgId=${org.id}`;
    const emailHtml = `
      <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden; background-color: #ffffff;">
        <div style="background-color: #2563eb; color: #ffffff; padding: 24px; text-align: center;">
          <h2 style="margin: 0; font-size: 20px; font-weight: bold;">Verify Organization Registration</h2>
          <p style="margin: 4px 0 0 0; font-size: 14px; opacity: 0.9;">AMPNM Multi-tenant Licensing Portal</p>
        </div>
        <div style="padding: 24px; color: #3f3f46; line-height: 1.6;">
          <p>Hello <strong>${org.name} Admin</strong>,</p>
          <p>Thank you for registering on our licensing network portal. Before you can generate cryptographic keys or purchase monitoring nodes, please verify your organizational account.</p>
          
          <div style="text-align: center; margin: 32px 0;">
            <a href="${verificationLink}" style="background-color: #2563eb; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; display: inline-block;">Verify Workspace Account</a>
          </div>
          
          <p style="font-size: 12px; color: #71717a;">If the button above does not work, copy and paste this URL into your browser:</p>
          <p style="font-size: 12px; color: #2563eb; word-break: break-all;">${verificationLink}</p>
        </div>
        <div style="background-color: #f4f4f5; padding: 16px; text-align: center; font-size: 11px; color: #71717a; border-top: 1px solid #e4e4e7;">
          <p style="margin: 0 0 4px 0;">This email was sent via AMPNM automated verification system.</p>
          <p style="margin: 0; font-weight: bold; color: #3f3f46;">Developed by IT Support BD</p>
        </div>
      </div>
    `;

    try {
      const res = await sendEmailAPI(org.clientEmail, `Verify Organization Account - ${org.name}`, emailHtml, "verification");
      if (res.success) {
        triggerAlert("success", `Verification email dispatch status: ${res.message}`);
      } else {
        triggerAlert("error", `Failed: ${res.error}`);
      }
    } catch (err: any) {
      triggerAlert("error", `Dispatch failure: ${err.message}`);
    }
  };

  // Trigger License Key Notification Email
  const handleSendLicenseDetails = async (org: any) => {
    const orgLicenses = licenses.filter((l) => l.orgId === org.id && l.status === "active");
    if (orgLicenses.length === 0) {
      triggerAlert("error", `No active software licenses found for ${org.name}.`);
      return;
    }

    const licenseListHtml = orgLicenses.map(lic => `
      <div style="padding: 12px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 12px; font-family: monospace; font-size: 13px; font-weight: bold; color: #1e293b; text-align: center; border-left: 4px solid #2563eb;">
        ${lic.key}
      </div>
    `).join("");

    const emailHtml = `
      <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden; background-color: #ffffff;">
        <div style="background-color: #1e293b; color: #ffffff; padding: 24px; text-align: center;">
          <h2 style="margin: 0; font-size: 20px; font-weight: bold;">Active License Credentials</h2>
          <p style="margin: 4px 0 0 0; font-size: 14px; opacity: 0.9;">Secure Cryptographic Keys Dispatch</p>
        </div>
        <div style="padding: 24px; color: #3f3f46; line-height: 1.6;">
          <p>Hello <strong>${org.name} Ops Team</strong>,</p>
          <p>We are dispatching the active licensing nodes assigned to your organization. Please use the keys listed below for Docker Agent configurations and verification APIs.</p>
          
          <div style="margin: 24px 0;">
            <p style="font-size: 12px; font-weight: bold; text-transform: uppercase; color: #71717a; letter-spacing: 0.5px; margin-bottom: 8px;">Active License Keys (${orgLicenses.length})</p>
            ${licenseListHtml}
          </div>

          <p>For API integration details, point your queries to: <code style="background-color: #f4f4f5; padding: 2px 6px; border-radius: 4px; font-size: 13px;">https://portal.itsupport.com.bd/api/license/verify?key=YOUR_KEY</code></p>
        </div>
        <div style="background-color: #f4f4f5; padding: 16px; text-align: center; font-size: 11px; color: #71717a; border-top: 1px solid #e4e4e7;">
          <p style="margin: 0 0 4px 0;">Confidential security credentials. Do not share licensing strings.</p>
          <p style="margin: 0; font-weight: bold; color: #3f3f46;">Developed by IT Support BD</p>
        </div>
      </div>
    `;

    try {
      const res = await sendEmailAPI(org.clientEmail, `Active Software License Keys - ${org.name}`, emailHtml, "license");
      if (res.success) {
        triggerAlert("success", `License details dispatch status: ${res.message}`);
      } else {
        triggerAlert("error", `Failed: ${res.error}`);
      }
    } catch (err: any) {
      triggerAlert("error", `Dispatch failure: ${err.message}`);
    }
  };

  // Trigger Custom Notice Email
  const handleSendCustomNotice = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedOrgId || !noticeSubject || !noticeBody) {
      triggerAlert("error", "Please fill in all custom notice details.");
      return;
    }

    const org = organizations.find(o => o.id === selectedOrgId);
    if (!org) return;

    const emailHtml = `
      <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e4e4e7; border-radius: 12px; overflow: hidden; background-color: #ffffff;">
        <div style="background-color: #2563eb; color: #ffffff; padding: 24px; text-align: center;">
          <h2 style="margin: 0; font-size: 20px; font-weight: bold;">System Notification Notice</h2>
          <p style="margin: 4px 0 0 0; font-size: 14px; opacity: 0.9;">IT Support BD Administration</p>
        </div>
        <div style="padding: 24px; color: #3f3f46; line-height: 1.6;">
          <p>Hello <strong>${org.name} Team</strong>,</p>
          
          <div style="background-color: #f8fafc; border-left: 4px solid #3b82f6; padding: 16px; margin: 20px 0; border-radius: 0 8px 8px 0;">
            <h3 style="margin-top: 0; font-size: 15px; color: #1e3a8a;">${noticeSubject}</h3>
            <p style="white-space: pre-wrap; font-size: 14px; margin-bottom: 0;">${noticeBody}</p>
          </div>

          <p style="font-size: 13px;">For any technical queries or workspace support requests, contact us at mail@arifmahmud.com.</p>
        </div>
        <div style="background-color: #f4f4f5; padding: 16px; text-align: center; font-size: 11px; color: #71717a; border-top: 1px solid #e4e4e7;">
          <p style="margin: 0; font-weight: bold; color: #3f3f46;">Developed by IT Support BD</p>
        </div>
      </div>
    `;

    setIsSubmitting(true);
    try {
      const res = await sendEmailAPI(org.clientEmail, noticeSubject, emailHtml, "custom");
      setNoticeSubject("");
      setNoticeBody("");
      setSelectedOrgId("");
      setIsSubmitting(false);
      if (res.success) {
        triggerAlert("success", `Custom notification dispatch status: ${res.message}`);
      } else {
        triggerAlert("error", `Failed to send: ${res.error}`);
      }
    } catch (err: any) {
      setIsSubmitting(false);
      triggerAlert("error", `Dispatch failure: ${err.message}`);
    }
  };

  return (
    <div className="space-y-8 relative">
      {/* Alert Banners */}
      {successMsg && (
        <div className="fixed top-4 right-4 z-50 bg-emerald-500 text-white font-bold text-xs py-3 px-5 rounded-xl shadow-xl flex items-center gap-2 border border-emerald-400/20 animate-in fade-in slide-in-from-top-4">
          <Check size={16} />
          <span>{successMsg}</span>
        </div>
      )}

      {errorMsg && (
        <div className="fixed top-4 right-4 z-50 bg-rose-500 text-white font-bold text-xs py-3 px-5 rounded-xl shadow-xl flex items-center gap-2 border border-rose-400/20 animate-in fade-in slide-in-from-top-4">
          <AlertTriangle size={16} />
          <span>{errorMsg}</span>
        </div>
      )}

      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight text-zinc-950 dark:text-zinc-50 flex items-center gap-2">
            <ShieldCheck className="text-blue-500 h-8 w-8" />
            Admin Operations Panel
          </h2>
          <p className="text-zinc-500 dark:text-zinc-400 text-sm">
            Configure Bangladeshi MFS gateways, customize transactional email delivery endpoints, and trigger client key allocations.
          </p>
        </div>
      </div>

      {/* Grid Tabs Selection */}
      <div className="flex flex-wrap border-b border-zinc-200 dark:border-zinc-800 gap-1">
        <button
          onClick={() => setActiveTab("payments")}
          className={cn(
            "px-4 py-2.5 text-sm font-semibold border-b-2 flex items-center gap-2 transition-colors cursor-pointer",
            activeTab === "payments"
              ? "border-blue-600 text-blue-600 dark:border-blue-500 dark:text-blue-400"
              : "border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300"
          )}
        >
          <CreditCard size={16} />
          Payment Systems
        </button>

        <button
          onClick={() => setActiveTab("email-settings")}
          className={cn(
            "px-4 py-2.5 text-sm font-semibold border-b-2 flex items-center gap-2 transition-colors cursor-pointer",
            activeTab === "email-settings"
              ? "border-blue-600 text-blue-600 dark:border-blue-500 dark:text-blue-400"
              : "border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300"
          )}
        >
          <Mail size={16} />
          Email Configuration
        </button>

        <button
          onClick={() => setActiveTab("clients")}
          className={cn(
            "px-4 py-2.5 text-sm font-semibold border-b-2 flex items-center gap-2 transition-colors cursor-pointer",
            activeTab === "clients"
              ? "border-blue-600 text-blue-600 dark:border-blue-500 dark:text-blue-400"
              : "border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300"
          )}
        >
          <Users size={16} />
          Client Communications Console
        </button>

        <button
          onClick={() => setActiveTab("logs")}
          className={cn(
            "px-4 py-2.5 text-sm font-semibold border-b-2 flex items-center gap-2 transition-colors cursor-pointer",
            activeTab === "logs"
              ? "border-blue-600 text-blue-600 dark:border-blue-500 dark:text-blue-400"
              : "border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300"
          )}
        >
          <History size={16} />
          Communication Logs
        </button>
      </div>

      {/* Main Tab Contents */}
      <div className="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm overflow-hidden">
        
        {/* PAYMENT SYSTEMS TAB */}
        {activeTab === "payments" && (
          <form onSubmit={handleSavePayments} className="p-6 space-y-6">
            <div>
              <h3 className="text-base font-bold text-zinc-950 dark:text-zinc-50 mb-1">MFS Gateway Bindings</h3>
              <p className="text-xs text-zinc-500">Configure client cash-out destination numbers for Bangladeshi payment gateways.</p>
            </div>

            <div className="grid gap-6 md:grid-cols-3">
              {/* bKash card */}
              <div className="p-5 border border-zinc-200 dark:border-zinc-800 rounded-xl bg-zinc-50/50 dark:bg-zinc-950/20 space-y-4">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-bold text-pink-600 dark:text-pink-400 uppercase tracking-wider">bKash</span>
                  <label className="relative inline-flex items-center cursor-pointer select-none">
                    <input
                      type="checkbox"
                      checked={bkashEnabled}
                      onChange={(e) => setBkashEnabled(e.target.checked)}
                      className="sr-only peer"
                    />
                    <div className="w-9 h-5 bg-zinc-250 dark:bg-zinc-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-zinc-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-pink-600"></div>
                  </label>
                </div>
                
                <div className="space-y-3">
                  <div>
                    <label className="block text-[10px] uppercase font-bold text-zinc-500 mb-1">Phone Number</label>
                    <input
                      type="text"
                      required={bkashEnabled}
                      value={bkashNumber}
                      onChange={(e) => setBkashNumber(e.target.value)}
                      className="w-full text-xs font-semibold px-3 py-2 bg-white dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-lg text-zinc-900 dark:text-zinc-50"
                    />
                  </div>
                  <div>
                    <label className="block text-[10px] uppercase font-bold text-zinc-500 mb-1">Account Type</label>
                    <select
                      value={bkashType}
                      onChange={(e: any) => setBkashType(e.target.value)}
                      className="w-full text-xs font-semibold px-3 py-2 bg-white dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-lg text-zinc-900 dark:text-zinc-50"
                    >
                      <option value="personal">Personal (Send Money)</option>
                      <option value="merchant">Merchant (Payment)</option>
                    </select>
                  </div>
                </div>
              </div>

              {/* Rocket card */}
              <div className="p-5 border border-zinc-200 dark:border-zinc-800 rounded-xl bg-zinc-50/50 dark:bg-zinc-950/20 space-y-4">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider">Rocket</span>
                  <label className="relative inline-flex items-center cursor-pointer select-none">
                    <input
                      type="checkbox"
                      checked={rocketEnabled}
                      onChange={(e) => setRocketEnabled(e.target.checked)}
                      className="sr-only peer"
                    />
                    <div className="w-9 h-5 bg-zinc-250 dark:bg-zinc-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-zinc-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-purple-600"></div>
                  </label>
                </div>
                
                <div className="space-y-3">
                  <div>
                    <label className="block text-[10px] uppercase font-bold text-zinc-500 mb-1">Phone Number</label>
                    <input
                      type="text"
                      required={rocketEnabled}
                      value={rocketNumber}
                      onChange={(e) => setRocketNumber(e.target.value)}
                      className="w-full text-xs font-semibold px-3 py-2 bg-white dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-lg text-zinc-900 dark:text-zinc-50"
                    />
                  </div>
                  <div>
                    <label className="block text-[10px] uppercase font-bold text-zinc-500 mb-1">Account Type</label>
                    <select
                      value={rocketType}
                      onChange={(e: any) => setRocketType(e.target.value)}
                      className="w-full text-xs font-semibold px-3 py-2 bg-white dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-lg text-zinc-900 dark:text-zinc-50"
                    >
                      <option value="personal">Personal (Send Money)</option>
                      <option value="merchant">Merchant (Payment)</option>
                    </select>
                  </div>
                </div>
              </div>

              {/* Nagad card */}
              <div className="p-5 border border-zinc-200 dark:border-zinc-800 rounded-xl bg-zinc-50/50 dark:bg-zinc-950/20 space-y-4">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-bold text-orange-600 dark:text-orange-400 uppercase tracking-wider">Nagad</span>
                  <label className="relative inline-flex items-center cursor-pointer select-none">
                    <input
                      type="checkbox"
                      checked={nagadEnabled}
                      onChange={(e) => setNagadEnabled(e.target.checked)}
                      className="sr-only peer"
                    />
                    <div className="w-9 h-5 bg-zinc-250 dark:bg-zinc-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-zinc-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-orange-600"></div>
                  </label>
                </div>
                
                <div className="space-y-3">
                  <div>
                    <label className="block text-[10px] uppercase font-bold text-zinc-500 mb-1">Phone Number</label>
                    <input
                      type="text"
                      required={nagadEnabled}
                      value={nagadNumber}
                      onChange={(e) => setNagadNumber(e.target.value)}
                      className="w-full text-xs font-semibold px-3 py-2 bg-white dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-lg text-zinc-900 dark:text-zinc-50"
                    />
                  </div>
                  <div>
                    <label className="block text-[10px] uppercase font-bold text-zinc-500 mb-1">Account Type</label>
                    <select
                      value={nagadType}
                      onChange={(e: any) => setNagadType(e.target.value)}
                      className="w-full text-xs font-semibold px-3 py-2 bg-white dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-lg text-zinc-900 dark:text-zinc-50"
                    >
                      <option value="personal">Personal (Send Money)</option>
                      <option value="merchant">Merchant (Payment)</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <div className="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-800">
              <button
                type="submit"
                disabled={isSubmitting}
                className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm rounded-lg shadow cursor-pointer disabled:opacity-55"
              >
                <Save size={16} />
                {isSubmitting ? "Saving changes..." : "Save Payment Options"}
              </button>
            </div>
          </form>
        )}

        {/* EMAIL CONFIGURATION TAB */}
        {activeTab === "email-settings" && (
          <form onSubmit={handleSaveEmailConfig} className="p-6 space-y-6">
            <div>
              <h3 className="text-base font-bold text-zinc-950 dark:text-zinc-50 mb-1">Resend SMTP Endpoint Setup</h3>
              <p className="text-xs text-zinc-500">Provide a Resend api key to send live transactional notifications, verifications and license details.</p>
            </div>

            <div className="grid gap-6 max-w-xl">
              <div className="space-y-1">
                <label className="block text-xs font-bold text-zinc-500 uppercase tracking-wider">Resend API Key</label>
                <input
                  type="password"
                  placeholder="re_xxxxxxxxxxxxxxxxxxxxxxxx"
                  value={resendApiKey}
                  onChange={(e) => setResendApiKey(e.target.value)}
                  className="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-950 border border-zinc-350 dark:border-zinc-800 rounded-lg text-sm text-zinc-900 dark:text-zinc-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <p className="text-[10px] text-zinc-400">If left blank, the portal will operate in Mock Mode, generating local HTML preview summaries instead.</p>
              </div>

              <div className="space-y-1">
                <label className="block text-xs font-bold text-zinc-500 uppercase tracking-wider">Sender From Address</label>
                <input
                  type="email"
                  placeholder="licensing@itsupport.com.bd"
                  value={fromEmail}
                  onChange={(e) => setFromEmail(e.target.value)}
                  className="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-950 border border-zinc-350 dark:border-zinc-800 rounded-lg text-sm text-zinc-900 dark:text-zinc-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <p className="text-[10px] text-zinc-400">Your Resend domain must verify this sender alias. Use `onboarding@resend.dev` for testing accounts.</p>
              </div>
            </div>

            <div className="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-800">
              <button
                type="submit"
                disabled={isSubmitting}
                className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm rounded-lg shadow cursor-pointer disabled:opacity-55"
              >
                <Save size={16} />
                {isSubmitting ? "Saving config..." : "Save Config Settings"}
              </button>
            </div>
          </form>
        )}

        {/* CLIENT COMMUNICATIONS CONSOLE */}
        {activeTab === "clients" && (
          <div className="p-6 space-y-8">
            <div className="space-y-4">
              <div>
                <h3 className="text-base font-bold text-zinc-950 dark:text-zinc-50 mb-1">Verify Workspace Clients & Dispatch Controls</h3>
                <p className="text-xs text-zinc-500 font-medium">Manually approve customer verification states and resend access credentials or verification alerts.</p>
              </div>

              <div className="overflow-x-auto border border-zinc-200 dark:border-zinc-800 rounded-xl">
                <table className="w-full text-left border-collapse text-sm">
                  <thead>
                    <tr className="bg-zinc-50/50 dark:bg-zinc-950/50 border-b border-zinc-200 dark:border-zinc-800 text-xs text-zinc-400 uppercase font-semibold">
                      <th className="p-4">Client Org</th>
                      <th className="p-4">Primary Contact Email</th>
                      <th className="p-4">State Badges</th>
                      <th className="p-4 text-center">Toggle State</th>
                      <th className="p-4 text-right">Instant Alerts</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-zinc-100 dark:divide-zinc-850">
                    {organizations.map((org) => {
                      const isVerified = org.verified ?? false;
                      return (
                        <tr key={org.id} className="hover:bg-zinc-50/50 dark:hover:bg-zinc-900/10">
                          <td className="p-4 font-bold text-zinc-900 dark:text-zinc-50 flex items-center gap-1.5">
                            <Building size={14} className="text-zinc-400" />
                            {org.name}
                          </td>
                          <td className="p-4 text-zinc-500 dark:text-zinc-400">{org.clientEmail}</td>
                          <td className="p-4">
                            <span className={cn(
                              "inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold border",
                              isVerified 
                                ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400 border-emerald-200 dark:border-emerald-900/30"
                                : "bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400 border-amber-200 dark:border-amber-900/30"
                            )}>
                              {isVerified ? "Verified Account" : "Unverified"}
                            </span>
                          </td>
                          <td className="p-4 text-center">
                            <button
                              onClick={() => {
                                toggleOrgVerification(org.id, !isVerified);
                                triggerAlert("success", `${org.name} verification status toggled.`);
                              }}
                              className="px-3 py-1 bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 text-xs font-semibold rounded-lg hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors cursor-pointer"
                            >
                              Toggle Approval
                            </button>
                          </td>
                          <td className="p-4 text-right space-x-2">
                            <button
                              onClick={() => handleSendVerification(org)}
                              className="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border border-blue-200 dark:border-blue-900/30 text-xs font-bold rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors cursor-pointer"
                            >
                              <RefreshCw size={11} />
                              Resend Verification
                            </button>

                            <button
                              onClick={() => handleSendLicenseDetails(org)}
                              className="inline-flex items-center gap-1 px-2.5 py-1 bg-zinc-950 text-zinc-50 dark:bg-zinc-50 dark:text-zinc-950 text-xs font-bold rounded-lg hover:opacity-90 transition-opacity cursor-pointer shadow-sm"
                            >
                              <Key size={11} />
                              Send API / Keys
                            </button>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>

            {/* SEND CUSTOM NOTIFICATION NOTICE FORM */}
            <div className="border-t border-zinc-200 dark:border-zinc-800 pt-6">
              <h4 className="text-sm font-bold text-zinc-900 dark:text-zinc-50 flex items-center gap-1.5 mb-2">
                <Sparkles size={16} className="text-blue-500" />
                Dispatch Custom Administrative Notice
              </h4>
              <p className="text-xs text-zinc-500 mb-4">Send a stylized system alert message directly to a client's registration mailbox.</p>

              <form onSubmit={handleSendCustomNotice} className="grid gap-4 max-w-xl">
                <div className="grid gap-4 sm:grid-cols-2">
                  <div>
                    <label className="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 mb-1">Target Client Organization</label>
                    <select
                      required
                      value={selectedOrgId}
                      onChange={(e) => setSelectedOrgId(e.target.value)}
                      className="w-full text-xs font-semibold px-3 py-2.5 bg-zinc-50 dark:bg-zinc-950 border border-zinc-350 dark:border-zinc-800 rounded-lg text-zinc-900 dark:text-zinc-50"
                    >
                      <option value="">Select Target...</option>
                      {organizations.map(o => (
                        <option key={o.id} value={o.id}>{o.name}</option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 mb-1">Email Subject Header</label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. Critical System Upgrade Notification"
                      value={noticeSubject}
                      onChange={(e) => setNoticeSubject(e.target.value)}
                      className="w-full px-3 py-2.5 bg-zinc-50 dark:bg-zinc-950 border border-zinc-355 dark:border-zinc-800 rounded-lg text-xs font-semibold text-zinc-900 dark:text-zinc-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                </div>

                <div className="space-y-1">
                  <label className="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 mb-1">Message Notice Body</label>
                  <textarea
                    required
                    rows={4}
                    placeholder="Provide details about license updates, payments, agent upgrades or maintenance notices..."
                    value={noticeBody}
                    onChange={(e) => setNoticeBody(e.target.value)}
                    className="w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-950 border border-zinc-355 dark:border-zinc-800 rounded-lg text-xs font-semibold text-zinc-900 dark:text-zinc-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div className="flex justify-end">
                  <button
                    type="submit"
                    disabled={isSubmitting}
                    className="inline-flex items-center gap-1.5 px-4.5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-lg shadow transition-colors cursor-pointer disabled:opacity-55"
                  >
                    <Send size={12} />
                    {isSubmitting ? "Dispatched..." : "Broadcast Mail Notice"}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* COMMUNICATION DISPATCH LOGS */}
        {activeTab === "logs" && (
          <div className="p-6 space-y-4">
            <div>
              <h3 className="text-base font-bold text-zinc-950 dark:text-zinc-50 mb-1">Communications Delivery Center</h3>
              <p className="text-xs text-zinc-500">Track and inspect all registration emails, license notifications, and custom alerts dispatched by the admin console.</p>
            </div>

            {emailLogs.length === 0 ? (
              <div className="text-center py-10 border border-dashed rounded-xl text-zinc-400 text-sm">
                No email communications dispatched yet.
              </div>
            ) : (
              <div className="overflow-x-auto border border-zinc-200 dark:border-zinc-800 rounded-xl">
                <table className="w-full text-left border-collapse text-sm">
                  <thead>
                    <tr className="bg-zinc-50/50 dark:bg-zinc-950/50 border-b border-zinc-200 dark:border-zinc-800 text-xs text-zinc-400 uppercase font-semibold">
                      <th className="p-4">Recipient Inbox</th>
                      <th className="p-4">Mail Subject</th>
                      <th className="p-4">Dispatch Class</th>
                      <th className="p-4">Delivery Status</th>
                      <th className="p-4">Date Dispatched</th>
                      <th className="p-4 text-right">Inspect Output</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-zinc-100 dark:divide-zinc-850">
                    {emailLogs.map((log) => (
                      <tr key={log.id} className="hover:bg-zinc-50/50 dark:hover:bg-zinc-900/10">
                        <td className="p-4 font-bold text-zinc-800 dark:text-zinc-200">{log.recipient}</td>
                        <td className="p-4 text-zinc-500 dark:text-zinc-400 font-medium truncate max-w-[200px]">{log.subject}</td>
                        <td className="p-4">
                          <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 dark:bg-blue-950/40 text-blue-800 dark:text-blue-400 border border-blue-200 dark:border-blue-900/30 uppercase">
                            {log.type}
                          </span>
                        </td>
                        <td className="p-4">
                          <span className={cn(
                            "px-2 py-0.5 rounded text-[10px] font-bold border uppercase",
                            log.status === "sent" 
                              ? "bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900/30"
                              : log.status === "simulated"
                              ? "bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-900/30"
                              : "bg-red-100 text-red-800 border-red-200 dark:bg-red-950/40 dark:text-red-400 dark:border-red-900/30"
                          )}>
                            {log.status}
                          </span>
                        </td>
                        <td className="p-4 text-xs text-zinc-400 dark:text-zinc-500">
                          {new Date(log.timestamp).toLocaleString()}
                        </td>
                        <td className="p-4 text-right">
                          <button
                            onClick={() => setPreviewLog(log)}
                            className="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:text-blue-500 hover:underline px-2.5 py-1 bg-blue-50 dark:bg-blue-950/30 dark:text-blue-400 rounded-lg cursor-pointer"
                          >
                            <Eye size={12} />
                            Preview Email
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}
      </div>

      {/* HTML EMAIL OVERLAY PREVIEW MODAL */}
      {previewLog && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-2xl max-w-2xl w-full overflow-hidden flex flex-col max-h-[90vh]">
            <div className="p-6 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
              <div>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded bg-blue-100 dark:bg-blue-950/40 text-blue-700 dark:text-blue-400 uppercase tracking-widest">
                  Preview Template: {previewLog.type}
                </span>
                <h3 className="font-bold text-zinc-950 dark:text-zinc-50 mt-1">Recipient: {previewLog.recipient}</h3>
                <p className="text-xs text-zinc-500">Subject: {previewLog.subject}</p>
              </div>
              <button
                onClick={() => setPreviewLog(null)}
                className="p-1.5 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800"
              >
                <X size={18} />
              </button>
            </div>

            {/* Email Canvas Viewport wrapper */}
            <div className="p-6 bg-zinc-100 dark:bg-zinc-950 overflow-y-auto flex-1 flex justify-center">
              <div 
                className="w-full max-w-lg shadow rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-white text-zinc-800"
                dangerouslySetInnerHTML={{ __html: previewLog.body }}
              />
            </div>

            <div className="p-4 border-t border-zinc-200 dark:border-zinc-800 flex justify-end gap-2 bg-zinc-50/50 dark:bg-zinc-900/50">
              <span className="text-[10px] font-semibold text-zinc-400 flex items-center gap-1 mr-auto uppercase tracking-wide">
                Status: {previewLog.status} | ID: {previewLog.id}
              </span>
              <button
                onClick={() => setPreviewLog(null)}
                className="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg cursor-pointer"
              >
                Close Preview
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
