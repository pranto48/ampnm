"use client";

import { useTheme } from "next-themes";
import { useEffect, useState } from "react";
import { useMonitorStore } from "@/store/use-monitor-store";
import { Sun, Moon, Bell, Menu, ShieldCheck, Building } from "lucide-react";

export function Header() {
  const { theme, setTheme } = useTheme();
  const [mounted, setMounted] = useState(false);
  const { profile, sidebarOpen, toggleSidebar, organizations, licenses } = useMonitorStore();

  useEffect(() => {
    setMounted(true);
  }, []);

  const activeLicensesCount = licenses.filter((l) => l.status === "active").length;

  return (
    <header className="h-16 bg-white dark:bg-zinc-950 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between px-6 sticky top-0 z-30">
      <div className="flex items-center gap-4">
        {!sidebarOpen && (
          <button
            onClick={toggleSidebar}
            aria-label="Expand navigation sidebar"
            className="p-1.5 text-zinc-500 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-900 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
          >
            <Menu size={20} />
          </button>
        )}
        <div className="flex flex-col">
          <h1 className="text-sm font-semibold text-zinc-950 dark:text-zinc-50 leading-tight">
            AMPNM Licensing Portal
          </h1>
          <p className="text-xs text-zinc-500 dark:text-zinc-400">
            portal.itsupport.com.bd
          </p>
        </div>
      </div>

      {/* SaaS Metrics Summary */}
      <div className="hidden md:flex items-center gap-6 text-xs text-zinc-500 dark:text-zinc-400">
        <div className="flex items-center gap-2">
          <Building size={14} className="text-blue-500" />
          <span>
            Clients:{" "}
            <strong className="text-zinc-700 dark:text-zinc-300">
              {organizations.length}
            </strong>
          </span>
        </div>
        <div className="flex items-center gap-2">
          <ShieldCheck size={14} className="text-emerald-500" />
          <span>
            Active Licenses:{" "}
            <strong className="text-zinc-700 dark:text-zinc-300">
              {activeLicensesCount}
            </strong>
          </span>
        </div>
      </div>

      <div className="flex items-center gap-4">
        {/* Theme Toggle Button */}
        <button
          onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
          aria-label="Toggle dark/light theme"
          className="p-2 text-zinc-500 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-900 rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
        >
          {mounted && (theme === "dark" || theme === "system") ? (
            <Sun size={18} className="text-amber-500" />
          ) : (
            <Moon size={18} className="text-blue-600" />
          )}
        </button>

        {/* Notifications Button */}
        <div className="relative">
          <button
            aria-label="View system notifications"
            className="p-2 text-zinc-500 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-900 rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
          >
            <Bell size={18} />
          </button>
        </div>

        <div className="h-6 w-px bg-zinc-200 dark:bg-zinc-800" />

        {/* User profile */}
        <div className="flex items-center gap-3">
          <div className="h-9 w-9 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-sm shadow-sm select-none">
            {profile?.name
              ? profile.name
                  .split(" ")
                  .map((n) => n[0])
                  .join("")
              : "U"}
          </div>
          <div className="hidden lg:flex flex-col text-left">
            <span className="text-sm font-semibold text-zinc-900 dark:text-zinc-100 leading-tight">
              {profile?.name || "User profile"}
            </span>
            <span className="text-xs text-zinc-500 dark:text-zinc-400">
              {profile?.role ? profile.role.toUpperCase() : "MEMBER"}
            </span>
          </div>
        </div>
      </div>
    </header>
  );
}
