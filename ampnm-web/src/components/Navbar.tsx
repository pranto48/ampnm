"use client";

import { useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { Menu, X, ArrowRight, Activity, Sun, Moon } from "lucide-react";
import { useTheme } from "@/components/ThemeProvider";

export function Navbar() {
  const pathname = usePathname();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const { theme, toggleTheme } = useTheme();

  const navItems = [
    { name: "Products", href: "/products" },
    { name: "Solutions", href: "/solutions" },
    { name: "Pricing", href: "/pricing" },
    { name: "Services", href: "/services" },
    { name: "Downloads", href: "/download" },
    { name: "Docs", href: "/docs" },
    { name: "Changelog", href: "/changelog" },
    { name: "About", href: "/about" },
    { name: "Contact", href: "/contact" },
  ];

  return (
    <header className="sticky top-0 z-50 w-full border-b border-zinc-200/40 dark:border-zinc-800/40 bg-white/80 dark:bg-zinc-950/80 backdrop-blur-xl transition-colors duration-300">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="flex h-16 items-center justify-between">
          {/* Logo Brand */}
          <Link href="/" className="flex items-center gap-2.5 group">
            <div className="p-2 bg-blue-600 text-white rounded-xl shadow-lg shadow-blue-500/20 group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
              <Activity className="h-5 w-5 animate-pulse" />
            </div>
            <div className="flex flex-col">
              <span className="font-extrabold text-zinc-900 dark:text-zinc-50 text-base leading-tight tracking-tight flex items-center gap-1.5 transition-colors">
                AMPNM
                <span className="text-[10px] bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-400 font-bold px-1.5 py-0.5 rounded transition-colors">
                  v1.9
                </span>
              </span>
              <span className="text-[9px] text-zinc-400 dark:text-zinc-500 font-medium tracking-wide uppercase select-none">
                by IT Support BD
              </span>
            </div>
          </Link>

          {/* Desktop Navigation Link items */}
          <nav className="hidden lg:flex items-center gap-1" aria-label="Desktop menu">
            {navItems.map((item) => {
              const isActive = pathname === item.href;
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={`px-3 py-1.5 rounded-lg text-xs font-semibold tracking-wide transition-all duration-200 ${
                    isActive
                      ? "bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400 shadow-sm"
                      : "text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:text-zinc-100 dark:hover:bg-zinc-800/50"
                  }`}
                >
                  {item.name}
                </Link>
              );
            })}
          </nav>

          {/* CTA, Theme Toggle & Mobile Trigger */}
          <div className="flex items-center gap-2">
            {/* Theme Toggle Button */}
            <button
              onClick={toggleTheme}
              className="p-2 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-100 dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-200 dark:hover:bg-zinc-800 transition-all duration-200 hover:scale-105"
              aria-label="Toggle theme"
              title={theme === "dark" ? "Switch to light mode" : "Switch to dark mode"}
            >
              {theme === "dark" ? (
                <Sun size={16} className="transition-transform duration-300 hover:rotate-45" />
              ) : (
                <Moon size={16} className="transition-transform duration-300 hover:-rotate-12" />
              )}
            </button>

            <a
              href="https://portal.itsupport.com.bd/login"
              className="hidden sm:inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-md shadow-blue-500/10 transition-all duration-200 hover:shadow-lg hover:shadow-blue-500/20 hover:-translate-y-0.5"
            >
              Access Portal
              <ArrowRight size={13} />
            </a>

            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className="lg:hidden p-2 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-900 rounded-lg transition-colors"
              aria-label="Toggle menu"
            >
              {mobileMenuOpen ? <X size={20} /> : <Menu size={20} />}
            </button>
          </div>
        </div>
      </div>

      {/* Mobile Drawer Menu */}
      {mobileMenuOpen && (
        <div className="lg:hidden border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 py-4 px-4 space-y-1.5 animate-fade-in transition-colors">
          {navItems.map((item) => {
            const isActive = pathname === item.href;
            return (
              <Link
                key={item.href}
                href={item.href}
                onClick={() => setMobileMenuOpen(false)}
                className={`block px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors ${
                  isActive
                    ? "bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400"
                    : "text-zinc-600 hover:bg-zinc-50 dark:text-zinc-400 dark:hover:bg-zinc-900/50 dark:hover:text-zinc-50"
                }`}
              >
                {item.name}
              </Link>
            );
          })}
          <div className="pt-4 border-t border-zinc-200 dark:border-zinc-900">
            <a
              href="https://portal.itsupport.com.bd/login"
              className="flex w-full items-center justify-center gap-1.5 py-3 px-4 rounded-xl text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-md shadow-blue-500/15"
            >
              Access Portal
              <ArrowRight size={14} />
            </a>
          </div>
        </div>
      )}
    </header>
  );
}
