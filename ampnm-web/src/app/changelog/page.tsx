"use client";

import { Calendar, GitCommit, Sparkles, AlertTriangle } from "lucide-react";

export default function ChangelogPage() {
  const versions = [
    {
      version: "v1.3.0",
      date: "July 2026",
      badge: "Repository Restructure",
      badgeColor: "bg-orange-500/10 text-orange-400 border-orange-500/20",
      highlights: [
        {
          title: "Portal Decoupled from Docker Image",
          description: "Moved the license management portal (ampnm-portal) to its own standalone git repository portal.itsupport.com.bd, keeping the Docker image lean and focused on the monitoring core."
        },
        {
          title: "Slimmer Docker Image",
          description: "Removed Next.js portal source, node_modules, and build artifacts from the Docker build context, resulting in a faster build and smaller image footprint."
        },
        {
          title: "Independent Portal Deployment",
          description: "portal.itsupport.com.bd now deploys independently via Vercel, allowing portal updates without requiring a Docker image rebuild or redeployment."
        }
      ]
    },
    {
      version: "v1.2.0",
      date: "July 2026",
      badge: "Performance & Tenancy Release",
      badgeColor: "bg-purple-500/10 text-purple-400 border-purple-500/20",
      highlights: [
        {
          title: "Multi-Tenancy User Group Isolation",
          description: "Confined maps, devices, edges, logs, and dashboards to isolated user groups so multiple operators in the same group collaborate seamlessly."
        },
        {
          title: "Advanced Connection Customizations",
          description: "Added rich edge layout controls on the map: adjustable thickness, connection styles (dashed, dotted, solid), customized color picker, directional arrows, custom labels, and dynamic canvas animation toggles."
        },
        {
          title: "Scheduled NAS & FTP Backups",
          description: "Implemented a system-wide automated backup engine supporting database dumps, tarball creation, history tracking, and scheduled delivery to local NAS mounts or remote FTP servers."
        },
        {
          title: "Portal Login Latency Fix",
          description: "Optimized database server hostname configurations from localhost to 127.0.0.1, eliminating IPv6 loopback and DNS query overhead for instant console access."
        }
      ]
    },
    {
      version: "v1.1.0",
      date: "June 2026",
      badge: "Feature Release",
      badgeColor: "bg-blue-500/10 text-blue-400 border-blue-500/20",
      highlights: [
        {
          title: "Administrative Payment Operations Console",
          description: "Created an admin settings dashboard at `/dashboard/admin` to manage gateway parameters and customize cash-out account numbers."
        },
        {
          title: "Dynamic Checkout Payments Integrations",
          description: "Products checkout overlay updated to pull configs from Zustand/Firestore. MFS terminology adjusts dynamically for Personal Send Money or Merchant Cash Out options."
        },
        {
          title: "Resend Transactional Mail Dispatcher",
          description: "Implemented serverless `/api/send-email` using Resend REST API to trigger verification emails, system notifications, or license key summaries."
        },
        {
          title: "Client Email Verification Pending badging",
          description: "Added warning triggers to client directory, blocking or whitelisting subscription products activations based on verification status."
        }
      ]
    },
    {
      version: "v1.0.0",
      date: "January 2026",
      badge: "Initial Launch",
      badgeColor: "bg-emerald-500/10 text-emerald-400 border-emerald-500/20",
      highlights: [
        {
          title: "SaaS Licensing Portal Scaffolding",
          description: "Bootstrap Next.js App Router, Tailwind design systems, and client-side Zustand store allocations."
        },
        {
          title: "Cryptographic 256-bit Key Generator",
          description: "Generate cryptographically secure 32-byte license keys using `window.crypto.getRandomValues` inside the admin console."
        },
        {
          title: "Rest licensing verify routes",
          description: "Created serverless dynamic API routes at `/api/license/verify` for Docker client handshakes."
        },
        {
          title: "Firebase database whitelists",
          description: "Configured Cloud Firestore security rules, client fallbacks for offline errors, and authentication paths."
        }
      ]
    }
  ];

  return (
    <div className="py-20 bg-white dark:bg-zinc-950 transition-colors duration-300 relative overflow-hidden flex-1">
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[400px] bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-100/40 via-transparent to-transparent dark:from-blue-900/15 dark:via-zinc-950 dark:to-zinc-950 pointer-events-none -z-10" />

      <div className="max-w-4xl mx-auto px-6 space-y-16">
        
        {/* Title */}
        <div className="text-center space-y-4">
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-zinc-900 dark:text-white leading-tight transition-all hover:-translate-y-1 hover:shadow-lg">
            Release Changelog & <br />
            <span className="bg-gradient-to-r from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 bg-clip-text text-transparent">
              Version Histories
            </span>
          </h1>
          <p className="text-zinc-500 dark:text-zinc-400 transition-colors text-sm font-medium">
            Track technical features, updates, patch logs and releases on the AMPNM platform.
          </p>
        </div>

        {/* Timeline list */}
        <div className="space-y-12 relative before:absolute before:inset-0 before:left-3.5 before:w-px before:bg-zinc-900">
          {versions.map((ver, idx) => (
            <div key={idx} className="relative pl-10 space-y-6">
              
              {/* Commit Dot */}
              <div className="absolute left-1.5 top-1.5 p-1 bg-white dark:bg-zinc-950 transition-colors duration-300 border-2 border-zinc-800 text-blue-500 rounded-full">
                <GitCommit size={14} />
              </div>

              {/* Version Header details */}
              <div className="flex flex-wrap items-center gap-3">
                <h3 className="text-lg font-extrabold text-zinc-900 dark:text-white transition-colors">{ver.version}</h3>
                <span className="text-xs text-zinc-500 flex items-center gap-1">
                  <Calendar size={12} />
                  {ver.date}
                </span>
                <span className={`px-2 py-0.5 rounded text-[10px] font-bold border ${ver.badgeColor}`}>
                  {ver.badge}
                </span>
              </div>

              {/* Highlights cards */}
              <div className="grid gap-4 sm:grid-cols-2">
                {ver.highlights.map((hl, hlIdx) => (
                  <div 
                    key={hlIdx}
                    className="p-5 border border-zinc-200 dark:border-zinc-900 bg-white dark:bg-zinc-900/10 rounded-2xl space-y-1.5 text-left"
                  >
                    <h4 className="font-bold text-xs text-zinc-200 flex items-center gap-1.5 uppercase tracking-wide">
                      <Sparkles size={11} className="text-blue-400" />
                      {hl.title}
                    </h4>
                    <p className="text-xs text-zinc-500 leading-relaxed font-medium">
                      {hl.description}
                    </p>
                  </div>
                ))}
              </div>

            </div>
          ))}
        </div>

      </div>
    </div>
  );
}
