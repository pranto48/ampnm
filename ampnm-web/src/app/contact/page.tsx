"use client";

import { useState } from "react";
import { Mail, Phone, MapPin, Send, Check } from "lucide-react";

export default function ContactPage() {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [subject, setSubject] = useState("");
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setTimeout(() => {
      setLoading(false);
      setSuccess(true);
      setName("");
      setEmail("");
      setSubject("");
      setMessage("");
      setTimeout(() => setSuccess(false), 3000);
    }, 1000);
  };

  return (
    <div className="py-20 bg-white dark:bg-zinc-950 transition-colors duration-300 relative overflow-hidden flex-1">
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[400px] bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-100/40 via-transparent to-transparent dark:from-blue-900/15 dark:via-zinc-950 dark:to-zinc-950 pointer-events-none -z-10" />

      <div className="max-w-7xl mx-auto px-6 space-y-16">
        {/* Title */}
        <div className="text-center max-w-3xl mx-auto space-y-4 animate-fade-in-up">
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-zinc-900 dark:text-white leading-tight transition-all hover:-translate-y-1 hover:shadow-lg">
            Connect With Our <br />
            <span className="bg-gradient-to-r from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 bg-clip-text text-transparent">
              Support Desk
            </span>
          </h1>
          <p className="text-zinc-500 dark:text-zinc-400 transition-colors text-sm font-medium">
            Have questions about dynamic MFS checkouts, custom agent deployments, or whitelists setups? Drop us a line.
          </p>
        </div>

        {/* Contact Layout */}
        <div className="grid gap-12 lg:grid-cols-12 items-stretch">
          
          {/* Info & Map Column */}
          <div className="lg:col-span-5 space-y-8 flex flex-col justify-between">
            <div className="space-y-6">
              <h3 className="text-lg font-bold text-zinc-900 dark:text-white uppercase transition-colors tracking-wider">Contact Coordinates</h3>
              <p className="text-xs text-zinc-500 leading-relaxed font-medium">
                Our support desk is operated by IT Support BD. We provide customized dashboards integrations and licensing compliance deployments.
              </p>

              <div className="space-y-4 font-bold text-xs text-zinc-400">
                <div className="flex items-center gap-3">
                  <div className="p-2 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg text-blue-400">
                    <Mail size={16} />
                  </div>
                  <div>
                    <span className="block text-[10px] text-zinc-500 uppercase tracking-widest leading-none mb-1">Mailing Address</span>
                    <a href="mailto:support@itsupport.com.bd" className="hover:underline text-zinc-200">support@itsupport.com.bd</a>
                  </div>
                </div>

                <div className="flex items-center gap-3">
                  <div className="p-2 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg text-emerald-400">
                    <Phone size={16} />
                  </div>
                  <div>
                    <span className="block text-[10px] text-zinc-500 uppercase tracking-widest leading-none mb-1">Telephones</span>
                    <span className="text-zinc-200">+880 1915 822266</span>
                  </div>
                </div>

                <div className="flex items-center gap-3">
                  <div className="p-2 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg text-purple-400">
                    <MapPin size={16} />
                  </div>
                  <div>
                    <span className="block text-[10px] text-zinc-500 uppercase tracking-widest leading-none mb-1">Dhaka Headquarters</span>
                    <span className="text-zinc-200">Dhaka, Bangladesh</span>
                  </div>
                </div>
              </div>
            </div>

            {/* Stylized Maps coordinate placeholder */}
            <div className="relative rounded-2xl overflow-hidden border border-zinc-200 dark:border-zinc-900 bg-white dark:bg-zinc-900/10 p-5 space-y-3 flex flex-col justify-end h-48 select-none">
              {/* Fake coordinate grid overlay */}
              <div className="absolute inset-0 bg-[linear-gradient(to_right,#80808003_1px,transparent_1px),linear-gradient(to_bottom,#80808003_1px,transparent_1px)] bg-[size:16px_16px] pointer-events-none" />
              <div className="absolute top-4 right-4 text-[10px] text-zinc-500 font-mono tracking-wider">
                LAT: 23.8103° N | LON: 90.4125° E
              </div>
              <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-4 h-4 bg-blue-500/20 border border-blue-500 rounded-full flex items-center justify-center">
                <span className="h-1.5 w-1.5 rounded-full bg-blue-500 animate-ping" />
              </div>

              <div className="relative z-10 space-y-1 text-left">
                <h4 className="font-bold text-xs text-white uppercase tracking-wider">IT Support BD Hub</h4>
                <p className="text-[10px] text-zinc-500">Dhaka, Bangladesh. Dynamic telemetry whitelists services.</p>
              </div>
            </div>
          </div>

          {/* Form Column */}
          <div className="lg:col-span-7 bg-zinc-900/30 border border-zinc-900 p-6 sm:p-8 rounded-3xl relative">
            {success && (
              <div className="absolute inset-4 z-10 bg-zinc-900/95 rounded-2xl flex flex-col items-center justify-center space-y-3 p-6 text-center animate-in fade-in duration-200">
                <div className="w-12 h-12 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center shadow">
                  <Check size={24} />
                </div>
                <h3 className="font-bold text-white text-base">Inquiry Submitted Successfully</h3>
                <p className="text-xs text-zinc-500 max-w-xs font-semibold leading-relaxed">
                  Thank you! Our engineering team will review your custom setup request and contact you at the provided mailbox.
                </p>
              </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-5">
              <h3 className="text-lg font-bold text-zinc-900 dark:text-white uppercase transition-colors tracking-wider mb-2">Request Assistance</h3>

              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <label className="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Full Name</label>
                  <input
                    type="text"
                    required
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="Sayed Arif"
                    className="w-full px-3.5 py-2.5 bg-white dark:bg-zinc-950 transition-colors duration-300 border border-zinc-900 rounded-xl text-xs font-semibold text-zinc-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Email Address</label>
                  <input
                    type="email"
                    required
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="arif@itsupport.com.bd"
                    className="w-full px-3.5 py-2.5 bg-white dark:bg-zinc-950 transition-colors duration-300 border border-zinc-900 rounded-xl text-xs font-semibold text-zinc-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              </div>

              <div className="space-y-1.5">
                <label className="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Subject Header</label>
                <input
                  type="text"
                  required
                  value={subject}
                  onChange={(e) => setSubject(e.target.value)}
                  placeholder="e.g. Requesting quote for 50 cluster nodes deployment"
                  className="w-full px-3.5 py-2.5 bg-white dark:bg-zinc-950 transition-colors duration-300 border border-zinc-900 rounded-xl text-xs font-semibold text-zinc-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div className="space-y-1.5">
                <label className="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Detailed Message</label>
                <textarea
                  required
                  rows={5}
                  value={message}
                  onChange={(e) => setMessage(e.target.value)}
                  placeholder="Provide specifications of your networks, required alert integrations or licensing constraints..."
                  className="w-full px-3.5 py-2.5 bg-white dark:bg-zinc-950 transition-colors duration-300 border border-zinc-900 rounded-xl text-xs font-semibold text-zinc-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <button
                type="submit"
                disabled={loading}
                className="w-full flex items-center justify-center gap-2 py-3 bg-blue-600 hover:bg-blue-700 disabled:opacity-55 text-white rounded-xl text-xs font-bold transition-all cursor-pointer shadow-lg shadow-blue-500/10"
              >
                <Send size={13} />
                {loading ? "Sending inquiry..." : "Submit Inquiry"}
              </button>
            </form>
          </div>

        </div>
      </div>
    </div>
  );
}
