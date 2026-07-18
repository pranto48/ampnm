"use client";

import { useState } from "react";
import Image from "next/image";
import {
  Cloud,
  Layers,
  ShieldCheck,
  Cpu,
  Terminal,
  CheckCircle2,
  ChevronDown,
  ChevronUp,
  Zap,
  RefreshCw,
  Lock,
  Globe,
} from "lucide-react";

const screenshots = [
  {
    id: "login",
    label: "Login Page",
    src: "/ampnm_login.png",
    desc: "Secure sign-in console at localhost:2266/login.php — the entry point to the monitoring system.",
  },
  {
    id: "dashboard",
    label: "Dashboard",
    src: "/ampnm_dashboard.png",
    desc: "Real-time network dashboard showing device health, bandwidth charts, and alert summaries.",
  },
  {
    id: "license",
    label: "License Setup",
    src: "/ampnm_license.png",
    desc: "One-time license activation — enter your free portal license key to unlock the full system.",
  },
];

const dockerSteps = [
  {
    step: "1",
    title: "Install Docker",
    code: "winget install Docker.DockerDesktop",
    desc: "Install Docker Desktop on Windows. For Linux/Mac use the official Docker install script.",
  },
  {
    step: "2",
    title: "Pull & Run AMPNM",
    code: "docker compose up -d --build",
    desc: "Clone the repo and run docker compose. AMPNM starts on port 2266 with a MySQL database.",
  },
  {
    step: "3",
    title: "Get Your Free License",
    code: "portal.itsupport.com.bd/register",
    desc: "Register on the portal, navigate to Licenses → AMPNM Core → Generate Free Key.",
  },
  {
    step: "4",
    title: "Activate & Monitor",
    code: "localhost:2266/license_setup.php",
    desc: "Paste your license key. The system auto-verifies and unlocks the full dashboard.",
  },
];

const solutions = [
  {
    title: "Docker Cluster Orchestration",
    description:
      "Scale licenses securely across hundreds of swarmed containers. Validate key allocations and limits automatically as nodes spin up and collapse.",
    icon: Layers,
    color: "text-blue-500",
    bg: "bg-blue-500/10",
  },
  {
    title: "Private Server Deployments",
    description:
      "Operate licensing validation services inside private air-gapped server configurations, utilizing offline database fallback matrices.",
    icon: Cloud,
    color: "text-purple-500",
    bg: "bg-purple-500/10",
  },
  {
    title: "Corporate Network Compliance",
    description:
      "Enforce license validity, inspect hardware node limits, and audit access logs using centralized web consoles developed by IT Support BD.",
    icon: ShieldCheck,
    color: "text-emerald-500",
    bg: "bg-emerald-500/10",
  },
  {
    title: "Edge Telemetry Integrations",
    description:
      "Collect high-performance metrics directly at node boundaries. Stream performance telemetry data with zero performance regressions.",
    icon: Cpu,
    color: "text-pink-500",
    bg: "bg-pink-500/10",
  },
];

const fixSteps = [
  {
    icon: RefreshCw,
    color: "text-blue-400",
    title: "Root Cause: Redirect Loop",
    body: "On fresh container restart, core_key was missing from DB. auth_check.php redirected to license_setup.php, which detected an active license and redirected back — infinite loop.",
  },
  {
    icon: Zap,
    color: "text-yellow-400",
    title: "Fix: Auto Re-Verification",
    body: "auth_check.php now detects when core_key is missing but a license key exists. It calls verifyLicenseWithPortal(force=true) to silently re-populate the key without any redirect.",
  },
  {
    icon: Lock,
    color: "text-emerald-400",
    title: "Result: Self-Healing on Boot",
    body: "Every container restart auto-recovers the core_key. No manual intervention needed. The HTTP chain is now: localhost:2266 → 302 → login.php → 200 OK.",
  },
  {
    icon: Globe,
    color: "text-purple-400",
    title: "HTTP Flow After Fix",
    body: "localhost:2266 → 302 → login.php → 200 OK ✓. After login, the dashboard loads fully with license status active and all monitoring features enabled.",
  },
];

export default function SolutionsPage() {
  const [activeScreenshot, setActiveScreenshot] = useState(0);
  const [expandedFaq, setExpandedFaq] = useState<number | null>(null);

  const faqs = [
    {
      q: "Is AMPNM really free?",
      a: "Yes — AMPNM Core is completely free and open source. Register on the portal to get your free license key. No credit card required.",
    },
    {
      q: "What ports does AMPNM use by default?",
      a: "The web app runs on port 2266. The MySQL database runs internally on port 3306 (not exposed to the host by default).",
    },
    {
      q: "Can I deploy it without Docker?",
      a: "Yes. You need PHP 8.0+, MySQL 8.0+, and Apache/Nginx. Copy the app files to your web root and configure config.php with your DB credentials.",
    },
    {
      q: "What happens if my container restarts?",
      a: "AMPNM auto-recovers. The updated auth_check.php detects missing core_key and silently re-verifies with the license portal on the next request — no redirect loop.",
    },
    {
      q: "How do I update to the latest version?",
      a: "Run: docker pull itsupportbd/ampnm:latest then docker compose up -d. Your database volume data is preserved across updates.",
    },
  ];

  return (
    <div className="py-20 bg-white dark:bg-zinc-950 transition-colors duration-300 relative overflow-hidden flex-1">
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[500px] bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-100/40 via-transparent to-transparent dark:from-blue-900/15 dark:via-zinc-950 dark:to-zinc-950 pointer-events-none -z-10" />

      <div className="max-w-7xl mx-auto px-6 space-y-24">

        {/* Hero */}
        <div className="text-center max-w-3xl mx-auto space-y-4 animate-fade-in-up">
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-500 text-xs font-semibold mb-2">
            <CheckCircle2 size={13} /> Docker · Free · Open Source
          </div>
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-zinc-900 dark:text-white leading-tight transition-colors">
            AMPNM Docker —&nbsp;
            <span className="bg-gradient-to-r from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 bg-clip-text text-transparent">
              Running &amp; Working
            </span>
          </h1>
          <p className="text-zinc-500 dark:text-zinc-400 transition-colors text-sm font-medium">
            Screenshots, Docker setup steps, and the full solution for the
            ERR_TOO_MANY_REDIRECTS fix — all in one place.
          </p>
        </div>

        {/* App Screenshots */}
        <section className="space-y-6 animate-fade-in-up">
          <h2 className="text-xl font-bold text-zinc-900 dark:text-white">App Screenshots</h2>
          <div className="flex gap-2 flex-wrap">
            {screenshots.map((s, i) => (
              <button
                key={s.id}
                onClick={() => setActiveScreenshot(i)}
                className={`px-4 py-2 rounded-xl text-xs font-semibold border transition-all ${
                  activeScreenshot === i
                    ? "bg-blue-500 text-white border-blue-500 shadow-lg shadow-blue-500/20"
                    : "bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400 border-zinc-200 dark:border-zinc-800 hover:border-blue-400"
                }`}
              >
                {s.label}
              </button>
            ))}
          </div>
          <div className="relative rounded-2xl overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-2xl bg-zinc-100 dark:bg-zinc-900">
            <Image
              src={screenshots[activeScreenshot].src}
              alt={screenshots[activeScreenshot].label}
              width={1280}
              height={720}
              className="w-full object-cover"
              priority
            />
            <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-6">
              <p className="text-white text-sm font-medium">{screenshots[activeScreenshot].desc}</p>
            </div>
          </div>
        </section>

        {/* Docker Steps */}
        <section className="space-y-6 animate-fade-in-up">
          <div className="space-y-2">
            <h2 className="text-xl font-bold text-zinc-900 dark:text-white">Get Up &amp; Running in 4 Steps</h2>
            <p className="text-xs text-zinc-500 dark:text-zinc-400">From zero to a live network monitoring dashboard in under 5 minutes.</p>
          </div>
          <div className="grid gap-4 md:grid-cols-2">
            {dockerSteps.map((s) => (
              <div
                key={s.step}
                className="p-6 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/40 space-y-3 hover:border-blue-400 dark:hover:border-blue-500/40 transition-all hover:-translate-y-0.5 hover:shadow-lg"
              >
                <div className="flex items-center gap-3">
                  <span className="w-8 h-8 rounded-full bg-blue-500 text-white text-xs font-bold flex items-center justify-center shrink-0">{s.step}</span>
                  <h3 className="font-bold text-sm text-zinc-900 dark:text-white">{s.title}</h3>
                </div>
                <p className="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">{s.desc}</p>
                <div className="flex items-center gap-2 bg-zinc-950 dark:bg-black rounded-lg px-3 py-2 border border-zinc-800">
                  <Terminal size={12} className="text-blue-400 shrink-0" />
                  <code className="text-xs text-green-400 font-mono truncate">{s.code}</code>
                </div>
              </div>
            ))}
          </div>
        </section>

        {/* Redirect Loop Fix */}
        <section className="space-y-6 animate-fade-in-up">
          <div className="space-y-2">
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 text-xs font-semibold">
              <CheckCircle2 size={13} /> Issue Resolved
            </div>
            <h2 className="text-xl font-bold text-zinc-900 dark:text-white">ERR_TOO_MANY_REDIRECTS — Fix Walkthrough</h2>
            <p className="text-xs text-zinc-500 dark:text-zinc-400">A redirect loop was triggered on fresh Docker container restarts. Here is exactly what happened and how it was fixed.</p>
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            {fixSteps.map((f, i) => {
              const Icon = f.icon;
              return (
                <div key={i} className="p-6 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/30 space-y-3 hover:-translate-y-0.5 hover:shadow-md transition-all">
                  <div className={`p-2 rounded-lg bg-zinc-100 dark:bg-zinc-900 w-fit ${f.color}`}><Icon size={18} /></div>
                  <h3 className="font-bold text-sm text-zinc-900 dark:text-white">{f.title}</h3>
                  <p className="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">{f.body}</p>
                </div>
              );
            })}
          </div>
          {/* Code diff */}
          <div className="rounded-2xl border border-zinc-800 bg-zinc-950 overflow-hidden">
            <div className="flex items-center gap-2 px-4 py-3 border-b border-zinc-800">
              <span className="w-3 h-3 rounded-full bg-red-500" />
              <span className="w-3 h-3 rounded-full bg-yellow-500" />
              <span className="w-3 h-3 rounded-full bg-green-500" />
              <span className="ml-2 text-xs text-zinc-400 font-mono">includes/auth_check.php — key fix</span>
            </div>
            <pre className="p-5 text-xs font-mono overflow-x-auto leading-6">
              <span className="text-red-400">{`- if (empty($raw_key)) {\n-     header('Location: license_setup.php'); exit;\n- }\n`}</span>
              <span className="text-emerald-400">{`+ // Auto-recover: re-verify if license key exists but core_key is missing\n+ if (empty($raw_key) && !empty(getAppLicenseKey())) {\n+     verifyLicenseWithPortal(true); // force re-fetch core_key\n+     $raw_key = decryptSensitiveValue(getAppSetting('core_key'));\n+ }\n`}</span>
            </pre>
          </div>
        </section>

        {/* Solutions Grid */}
        <section className="space-y-6 animate-fade-in-up">
          <h2 className="text-xl font-bold text-zinc-900 dark:text-white">Deployment Solutions</h2>
          <div className="grid gap-6 md:grid-cols-2">
            {solutions.map((opt, idx) => {
              const Icon = opt.icon;
              return (
                <div
                  key={idx}
                  className="p-8 border border-zinc-200 dark:border-zinc-900 bg-white dark:bg-zinc-900/20 rounded-3xl space-y-4 flex flex-col items-start hover:border-blue-300 dark:hover:border-blue-500/25 transition-all hover:-translate-y-1 hover:shadow-lg"
                  style={{ animationDelay: `${idx * 100}ms` }}
                >
                  <div className={`p-3 rounded-xl border border-zinc-200 dark:border-zinc-800 ${opt.bg} ${opt.color}`}><Icon size={22} /></div>
                  <div className="space-y-2">
                    <h3 className="text-base font-bold text-zinc-900 dark:text-white transition-colors">{opt.title}</h3>
                    <p className="text-xs text-zinc-500 leading-relaxed font-medium">{opt.description}</p>
                  </div>
                </div>
              );
            })}
          </div>
        </section>

        {/* FAQ */}
        <section className="space-y-4 animate-fade-in-up">
          <h2 className="text-xl font-bold text-zinc-900 dark:text-white">Frequently Asked Questions</h2>
          <div className="divide-y divide-zinc-100 dark:divide-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden">
            {faqs.map((faq, i) => (
              <div key={i} className="bg-white dark:bg-zinc-900/30">
                <button
                  onClick={() => setExpandedFaq(expandedFaq === i ? null : i)}
                  className="w-full flex items-center justify-between px-6 py-4 text-left text-sm font-semibold text-zinc-900 dark:text-white hover:bg-zinc-50 dark:hover:bg-zinc-900/60 transition-colors"
                >
                  {faq.q}
                  {expandedFaq === i ? <ChevronUp size={16} className="text-zinc-400 shrink-0" /> : <ChevronDown size={16} className="text-zinc-400 shrink-0" />}
                </button>
                {expandedFaq === i && (
                  <div className="px-6 pb-4 text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">{faq.a}</div>
                )}
              </div>
            ))}
          </div>
        </section>

      </div>
    </div>
  );
}

