"use client";

import { Activity, ShieldCheck, Mail, Building, Phone } from "lucide-react";

export default function AboutPage() {
  return (
    <div className="py-20 bg-white dark:bg-zinc-950 transition-colors duration-300 relative overflow-hidden flex-1">
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[400px] bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-100/40 via-transparent to-transparent dark:from-blue-900/15 dark:via-zinc-950 dark:to-zinc-950 pointer-events-none -z-10" />

      <div className="max-w-4xl mx-auto px-6 space-y-12">
        {/* Title */}
        <div className="text-center space-y-4">
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-zinc-900 dark:text-white leading-tight transition-all hover:-translate-y-1 hover:shadow-lg">
            Platform Mission & <br />
            <span className="bg-gradient-to-r from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 bg-clip-text text-transparent">
              Engineering Excellence
            </span>
          </h1>
          <p className="text-zinc-500 dark:text-zinc-400 transition-colors text-sm font-medium">
            Discover the technology vision and corporate support background of the AMPNM licensing suite.
          </p>
        </div>

        {/* Narrative content block */}
        <div className="space-y-6 text-zinc-400 text-xs sm:text-sm font-medium leading-relaxed bg-zinc-900/10 border border-zinc-900 p-8 sm:p-10 rounded-3xl">
          <h3 className="font-extrabold text-white text-base">Who We Are</h3>
          <p>
            AMPNM is a high-performance, multi-tenant software license verification and node telemetry monitoring aggregator. Designed for developers and network operators, our daemon tracking utilities collect CPU, memory, container health and cluster status metrics dynamically.
          </p>
          <p>
            Developed and verified by **IT Support BD**, our platform runs secure 256-bit cryptographic verification checks protecting node licenses across hybrid cloud endpoints.
          </p>

          <div className="h-px bg-zinc-200 dark:bg-zinc-800 my-6" />

          <h3 className="font-extrabold text-white text-base">Our Core Philosophy</h3>
          <div className="grid gap-6 sm:grid-cols-2 pt-2">
            <div className="flex gap-3">
              <div className="p-2 bg-blue-500/10 text-blue-400 rounded-lg h-fit">
                <ShieldCheck size={18} />
              </div>
              <div className="space-y-1">
                <h4 className="font-bold text-white text-xs uppercase tracking-wider">Uncompromising Security</h4>
                <p className="text-[11px] text-zinc-500">256-bit security keys bind dynamically to corporate tenant workspaces, verifying installs against Firestore caches.</p>
              </div>
            </div>

            <div className="flex gap-3">
              <div className="p-2 bg-pink-500/10 text-pink-400 rounded-lg h-fit">
                <Activity size={18} />
              </div>
              <div className="space-y-1">
                <h4 className="font-bold text-white text-xs uppercase tracking-wider">Telemetry Precision</h4>
                <p className="text-[11px] text-zinc-500">Go telemetry agents stream performance indicators with sub-millisecond precision and minimal host overhead.</p>
              </div>
            </div>
          </div>
        </div>

        {/* Contact Coordinates */}
        <div className="grid gap-6 sm:grid-cols-3 text-center">
          <div className="p-4 border border-zinc-900 rounded-2xl bg-zinc-900/20 space-y-2">
            <Building size={18} className="text-zinc-500 mx-auto" />
            <h4 className="font-bold text-white text-xs uppercase tracking-wider">Corporate Hub</h4>
            <p className="text-[10px] text-zinc-500 font-semibold leading-relaxed">IT Support BD<br />Dhaka, Bangladesh</p>
          </div>
          <div className="p-4 border border-zinc-900 rounded-2xl bg-zinc-900/20 space-y-2">
            <Mail size={18} className="text-zinc-500 mx-auto" />
            <h4 className="font-bold text-white text-xs uppercase tracking-wider">Inquiries</h4>
            <p className="text-[10px] text-zinc-500 font-semibold leading-relaxed"><a href="mailto:support@itsupport.com.bd" className="hover:underline">support@itsupport.com.bd</a><br /><a href="mailto:mail@arifmahmud.com" className="hover:underline">mail@arifmahmud.com</a></p>
          </div>
          <div className="p-4 border border-zinc-900 rounded-2xl bg-zinc-900/20 space-y-2">
            <Phone size={18} className="text-zinc-500 mx-auto" />
            <h4 className="font-bold text-white text-xs uppercase tracking-wider">Operations Desk</h4>
            <p className="text-[10px] text-zinc-500 font-semibold leading-relaxed">+880 1915 822266<br />Sat - Thu: 9 AM - 6 PM</p>
          </div>
        </div>
      </div>
    </div>
  );
}
