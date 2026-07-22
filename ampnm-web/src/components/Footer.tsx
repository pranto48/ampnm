/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
import Link from "next/link";
import { Activity, Mail, Phone, MapPin } from "lucide-react";

export function Footer() {
  const currentYear = new Date().getFullYear();

  const sections = [
    {
      title: "Product",
      links: [
        { name: "Agent Features", href: "/products" },
        { name: "Pricing Packages", href: "/pricing" },
        { name: "Client Downloads", href: "/download" },
        { name: "Portal Console", href: "https://portal.itsupport.com.bd" },
      ],
    },
    {
      title: "Solutions",
      links: [
        { name: "Cluster Monitoring", href: "/solutions" },
        { name: "Enterprise SaaS", href: "/solutions" },
        { name: "API Integrations", href: "/docs" },
        { name: "Version Timelines", href: "/changelog" },
      ],
    },
    {
      title: "Support",
      links: [
        { name: "Documentation", href: "/docs" },
        { name: "Agent Install Scripts", href: "/download" },
        { name: "IT Support Services", href: "/services" },
        { name: "Contact Helpdesk", href: "/contact" },
      ],
    },
  ];

  return (
    <footer className="border-t border-zinc-200 dark:border-zinc-900 bg-zinc-50 dark:bg-zinc-950 text-zinc-600 dark:text-zinc-400 text-sm mt-auto transition-colors duration-200">
      <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 space-y-8">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
          {/* Logo & Info column */}
          <div className="space-y-4">
            <Link href="/" className="flex items-center gap-2 group w-fit">
              <div className="p-2 bg-blue-600 text-white rounded-xl shadow group-hover:scale-105 transition-transform">
                <Activity size={18} />
              </div>
              <span className="font-extrabold text-zinc-900 dark:text-zinc-50 text-base leading-none tracking-tight">
                AMPNM
              </span>
            </Link>
            <p className="text-xs text-zinc-500 max-w-xs">
              Advanced Multi-Tenant Platform for Node Monitoring, telemetry analysis, and secure Docker clustering license guards.
            </p>
            
            <div className="space-y-2 pt-2 text-xs font-semibold text-zinc-500 dark:text-zinc-400">
              <div className="flex items-center gap-2">
                <Mail size={13} className="text-zinc-400" />
                <a href="mailto:support@itsupport.com.bd" className="hover:text-zinc-900 dark:hover:text-zinc-50">support@itsupport.com.bd</a>
              </div>
              <div className="flex items-center gap-2">
                <Phone size={13} className="text-zinc-400" />
                <span>+880 1915 822266</span>
              </div>
            </div>
          </div>

          {/* Links sections */}
          {sections.map((section, idx) => (
            <div key={idx} className="space-y-3">
              <h4 className="text-xs font-bold text-zinc-900 dark:text-zinc-200 uppercase tracking-wider">
                {section.title}
              </h4>
              <ul className="space-y-2 text-xs font-medium">
                {section.links.map((link, linkIdx) => (
                  <li key={linkIdx}>
                    <Link 
                      href={link.href}
                      className="hover:text-zinc-900 dark:hover:text-zinc-50 transition-colors"
                    >
                      {link.name}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        {/* Footer Bottom */}
        <div className="pt-8 border-t border-zinc-200 dark:border-zinc-900 flex flex-col md:flex-row items-center justify-between gap-4 text-xs font-medium text-zinc-500">
          <p>© {currentYear} AMPNM. All rights reserved.</p>
          <div className="flex items-center gap-1.5 bg-zinc-200/50 dark:bg-zinc-900/50 px-3.5 py-1.5 border border-zinc-200 dark:border-zinc-800 rounded-full font-bold text-[10px] text-zinc-600 dark:text-zinc-300 tracking-wider uppercase select-none">
            Developed by IT Support BD
          </div>
        </div>
      </div>
    </footer>
  );
}
export default Footer;
