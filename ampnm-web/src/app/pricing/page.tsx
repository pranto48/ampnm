"use client";
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */

import Link from "next/link";
import { Check, ShieldCheck, Tag, HelpCircle, ArrowRight } from "lucide-react";

export default function PricingPage() {
  const USD_TO_BDT = 120;

  const tiers = [
    {
      id: "prod-ampnm-free",
      name: "AMPNM Core (Free & Open Source)",
      price: 0,
      description: "Self-host the AMPNM Docker monitoring agent. Clean, lightweight, and completely open source.",
      features: [
        "Free & Open Source codebase",
        "Unlimited host CPU/RAM tracking",
        "Self-hosted Docker dashboard",
        "Host-locked license key security",
        "Community support & updates"
      ],
      popular: true,
      free: true,
    },
    {
      id: "prod-std",
      name: "Standard Agent License",
      price: 15,
      description: "Ideal for tracking single server instances and personal container clusters.",
      features: [
        "Single host CPU/RAM tracking",
        "Email incident warnings",
        "24/7 client portal support",
        "Up to 5 custom dashboard widgets",
        "Daily log metrics retention"
      ],
      popular: false,
      free: false,
    },
    {
      id: "prod-cluster",
      name: "Docker Cluster Pack",
      price: 99,
      description: "Best for growing organizations operating Kubernetes or swarm docker setups.",
      features: [
        "Up to 10 cluster nodes monitoring",
        "SMS + Telegram alert integrations",
        "14-day history metrics graphs",
        "Dedicated support line access",
        "Hourly API license security sweeps",
        "Custom node tagging options"
      ],
      popular: false,
      free: false,
    },
    {
      id: "prod-enterprise",
      name: "Enterprise Core Unlimited",
      price: 499,
      description: "Engineered for high-volume corporate networks and mission-critical SLAs.",
      features: [
        "Unlimited server host allocations",
        "Custom Webhook endpoints reporting",
        "Full REST API access",
        "99.9% SLA support contract",
        "On-premises docker setup guides",
        "Lifetime database history logs",
        "Quarterly system engineering reviews"
      ],
      popular: false,
      free: false,
    }
  ];

  return (
    <div className="py-20 bg-white dark:bg-zinc-950 relative overflow-hidden flex-1 transition-colors duration-300">
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[400px] bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-100/40 via-transparent to-transparent dark:from-blue-900/15 dark:via-zinc-950 dark:to-zinc-950 pointer-events-none -z-10" />

      <div className="max-w-7xl mx-auto px-6 space-y-16">
        {/* Title */}
        <div className="text-center max-w-3xl mx-auto space-y-4 animate-fade-in-up">
          <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-zinc-900 dark:text-white leading-tight transition-colors">
            Transparent Pricing, <br />
            <span className="bg-gradient-to-r from-blue-500 to-indigo-500 dark:from-blue-400 dark:to-indigo-400 bg-clip-text text-transparent">
              Flexible Subscriptions
            </span>
          </h1>
          <p className="text-zinc-500 dark:text-zinc-400 text-sm font-medium transition-colors">
            Choose the telemetry scope that aligns with your Docker orchestrators. Purchase license keys securely using local payment gateways.
          </p>
        </div>

        {/* Pricing Cards Grid */}
        <div className="grid gap-8 md:grid-cols-2 lg:grid-cols-3 items-stretch">
          {tiers.map((tier, idx) => (
            <div
              key={tier.id}
              className={`animate-fade-in-up relative flex flex-col justify-between p-8 rounded-2xl border transition-all hover:-translate-y-1 hover:shadow-xl ${
                tier.popular
                  ? "border-blue-400 dark:border-blue-500 bg-blue-50/50 dark:bg-blue-950/10 ring-2 ring-blue-400/20 dark:ring-blue-500/10"
                  : "border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/40"
              }`}
              style={{ animationDelay: `${idx * 100}ms` }}
            >
              {tier.popular && (
                <span className="absolute -top-3.5 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-[10px] uppercase font-bold tracking-widest px-3 py-1 rounded-full shadow-md">
                  Most Popular
                </span>
              )}

              <div className="space-y-6">
                <div className="space-y-2">
                  <h3 className="text-lg font-bold text-zinc-900 dark:text-white transition-colors">{tier.name}</h3>
                  <p className="text-xs text-zinc-500 dark:text-zinc-500 leading-relaxed">{tier.description}</p>
                </div>

                <div className="pt-2">
                  <div className="flex items-baseline gap-1">
                    <span className="text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-white transition-colors">
                      {tier.free ? "Free" : `$${tier.price}`}
                    </span>
                    {!tier.free && (
                      <span className="text-xs text-zinc-500 font-semibold uppercase tracking-wide">/ Month</span>
                    )}
                  </div>
                  <p className="text-[10px] text-zinc-400 font-bold mt-1 uppercase tracking-wider">
                    {tier.free ? "No credit card or payment required" : `≈ ${(tier.price * USD_TO_BDT).toLocaleString()} BDT`}
                  </p>
                </div>

                <div className="h-px bg-zinc-200 dark:bg-zinc-800 transition-colors" />

                <ul className="space-y-4 text-xs font-semibold text-zinc-600 dark:text-zinc-400 transition-colors">
                  {tier.features.map((feature, fidx) => (
                    <li key={fidx} className="flex items-start gap-2.5">
                      <div className="mt-0.5 p-0.5 bg-blue-100 dark:bg-blue-500/15 text-blue-500 dark:text-blue-400 rounded-full flex-shrink-0 transition-colors">
                        <Check size={10} />
                      </div>
                      <span>{feature}</span>
                    </li>
                  ))}
                </ul>
              </div>

              <div className="pt-8 space-y-4">
                <a
                  href="https://portal.itsupport.com.bd/login"
                  className={`w-full flex items-center justify-center gap-1.5 py-3 px-4 rounded-xl text-xs font-bold text-center transition-all cursor-pointer hover:-translate-y-0.5 ${
                    tier.popular
                      ? "bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/20"
                      : "bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700/60"
                  }`}
                >
                  {tier.free ? "Get Free Key" : "Buy License Key"}
                  <ArrowRight size={13} />
                </a>
                <div className="flex items-center gap-1.5 text-[9px] font-bold text-zinc-400 dark:text-zinc-500 tracking-wider uppercase select-none">
                  <Tag size={10} />
                  <span>ID: {tier.id}</span>
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* FAQ note info */}
        <div className="max-w-3xl mx-auto bg-zinc-50 dark:bg-zinc-900/30 border border-zinc-200 dark:border-zinc-900 p-6 rounded-2xl space-y-3 flex items-start gap-3 transition-colors animate-fade-in-up delay-300">
          <HelpCircle size={20} className="text-zinc-400 dark:text-zinc-500 flex-shrink-0 mt-0.5" />
          <div className="space-y-1">
            <h4 className="font-bold text-xs text-zinc-900 dark:text-white uppercase tracking-wider transition-colors">Payment Support & Activation Note</h4>
            <p className="text-xs text-zinc-500 leading-relaxed">
              Upon purchasing, your unique license keys will instantly compile and bind to your organization workspace. Direct mobile payments (bKash, Nagad, Rocket) are fully processed by portal systems verified by IT Support BD.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
