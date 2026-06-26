"use client";

import { useState } from "react";
import { BookOpen, Key, Globe, ShieldAlert, ChevronRight, Terminal } from "lucide-react";

export default function DocsPage() {
  const sampleRequest = `curl -X POST https://portal.itsupport.com.bd/api/license/verify \\
  -H "Content-Type: application/json" \\
  -d '{"key": "AMPNM-DEVC-8F2B-9A4E-4321"}'`;

  const sampleResponse = `{
  "valid": true,
  "status": "active",
  "expiresAt": "2027-01-10",
  "orgId": "org-bb",
  "orgName": "Bangladesh Bank IT",
  "productId": "prod-cluster"
}`;

  return (
    <div className="py-20 bg-zinc-950 relative overflow-hidden flex-1">
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[400px] bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-900/15 via-zinc-950 to-zinc-950 pointer-events-none -z-10" />

      <div className="max-w-5xl mx-auto px-6 space-y-12">
        {/* Title */}
        <div className="text-center space-y-4">
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-white leading-tight">
            Integration Guides & <br />
            <span className="bg-gradient-to-r from-blue-400 to-indigo-400 bg-clip-text text-transparent">
              API Documentation
            </span>
          </h1>
          <p className="text-zinc-400 text-sm font-medium">
            Learn how to verify licensing states programmatically and whitelists daemon network protocols.
          </p>
        </div>

        {/* Docs Articles Layout */}
        <div className="grid gap-8 lg:grid-cols-12">
          {/* Quick links Sidebar */}
          <div className="lg:col-span-3 space-y-4 hidden lg:block">
            <h4 className="text-xs font-bold text-zinc-500 uppercase tracking-widest">Chapters</h4>
            <div className="flex flex-col gap-2 font-bold text-xs">
              <a href="#intro" className="text-blue-400 border-l-2 border-blue-500 pl-3">Introduction</a>
              <a href="#api" className="text-zinc-500 hover:text-zinc-300 pl-3">Verification REST API</a>
              <a href="#firewall" className="text-zinc-500 hover:text-zinc-300 pl-3">Network & Firewalls</a>
              <a href="#fallback" className="text-zinc-500 hover:text-zinc-300 pl-3">Database Fallbacks</a>
            </div>
          </div>

          {/* Core documentation text */}
          <div className="lg:col-span-9 space-y-12 text-zinc-400 text-xs sm:text-sm font-medium leading-relaxed">
            
            {/* Section 1: Intro */}
            <div id="intro" className="space-y-4 scroll-mt-20">
              <h3 className="text-lg font-extrabold text-white flex items-center gap-2">
                <BookOpen className="text-blue-500" size={20} />
                Introduction
              </h3>
              <p>
                The AMPNM licensing suite validates active host counts and performance footprints via cryptographic keys. Running background telemetry agents perform verification handshakes before streaming CPU, memory, and container allocations.
              </p>
              <p>
                All licensing validation routes are hosted under our production domain **`https://portal.itsupport.com.bd`** which synchronizes directly with Cloud Firestore databases.
              </p>
            </div>

            <div className="h-px bg-zinc-900" />

            {/* Section 2: REST API */}
            <div id="api" className="space-y-4 scroll-mt-20">
              <h3 className="text-lg font-extrabold text-white flex items-center gap-2">
                <Key className="text-emerald-500" size={20} />
                Verification REST API
              </h3>
              <p>
                To check license validity inside custom docker clusters or binaries, construct an HTTP request to the verify endpoints.
              </p>

              <div className="space-y-2">
                <p className="font-bold text-zinc-300">HTTP Request specifications:</p>
                <ul className="list-disc pl-5 space-y-1">
                  <li>Method: <strong className="text-white">POST</strong></li>
                  <li>Path: <code className="text-blue-400 bg-zinc-900 px-1.5 py-0.5 rounded font-mono text-xs">https://portal.itsupport.com.bd/api/license/verify</code></li>
                  <li>Content-Type: <code className="text-zinc-300 bg-zinc-900 px-1.5 py-0.5 rounded font-mono text-xs">application/json</code></li>
                </ul>
              </div>

              {/* Request code block */}
              <div className="space-y-2">
                <span className="block text-[10px] uppercase font-bold text-zinc-500">Request curl template:</span>
                <div className="p-4 rounded-xl border border-zinc-900 bg-zinc-950 font-mono text-[11px] text-zinc-350 text-left overflow-x-auto select-all">
                  <pre>{sampleRequest}</pre>
                </div>
              </div>

              {/* Response code block */}
              <div className="space-y-2">
                <span className="block text-[10px] uppercase font-bold text-zinc-500">JSON Success Response (200 OK):</span>
                <div className="p-4 rounded-xl border border-zinc-900 bg-zinc-950 font-mono text-[11px] text-emerald-400 text-left overflow-x-auto">
                  <pre>{sampleResponse}</pre>
                </div>
              </div>
            </div>

            <div className="h-px bg-zinc-900" />

            {/* Section 3: Firewall whitelists */}
            <div id="firewall" className="space-y-4 scroll-mt-20">
              <h3 className="text-lg font-extrabold text-white flex items-center gap-2">
                <Globe className="text-purple-500" size={20} />
                Network & Firewalls
              </h3>
              <p>
                For servers running behind enterprise routers or corporate firewalls, ensure outgoing packets whitelists whitelists the following target configurations:
              </p>
              <div className="overflow-x-auto border border-zinc-900 rounded-xl">
                <table className="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr className="bg-zinc-900/50 text-[10px] text-zinc-500 font-bold uppercase tracking-wider border-b border-zinc-900">
                      <th className="p-3">Target Address</th>
                      <th className="p-3">Port</th>
                      <th className="p-3">Direction</th>
                      <th className="p-3">Purpose</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-zinc-900 font-semibold text-zinc-350">
                    <tr>
                      <td className="p-3">portal.itsupport.com.bd</td>
                      <td className="p-3">443 (HTTPS)</td>
                      <td className="p-3">Outgoing</td>
                      <td className="p-3 text-zinc-550">License Verification Handshakes</td>
                    </tr>
                    <tr>
                      <td className="p-3">telemetry.itsupport.com.bd</td>
                      <td className="p-3">443 (HTTPS)</td>
                      <td className="p-3">Outgoing</td>
                      <td className="p-3 text-zinc-550">Metrics Stream Uploads</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div className="h-px bg-zinc-900" />

            {/* Section 4: Offline database checks */}
            <div id="fallback" className="space-y-4 scroll-mt-20">
              <h3 className="text-lg font-extrabold text-white flex items-center gap-2">
                <ShieldAlert className="text-amber-500" size={20} />
                Offline Database Fallbacks
              </h3>
              <p>
                When telemetry nodes operate in intermittent air-gapped locations, agents configure cached states. Verified keys remain locally cached in encrypted storage files (`/var/lib/ampnm/license.cache`) up to 30 days before requesting a live validation handshake.
              </p>
            </div>

          </div>
        </div>

      </div>
    </div>
  );
}
