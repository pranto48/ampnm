"use client";

import { useTheme } from "next-themes";
import { useEffect, useState } from "react";
import { useMonitorStore } from "@/store/use-monitor-store";
import { Sun, Moon, Bell, Menu, ShieldAlert, Cpu, HardDrive } from "lucide-react";
import { cn } from "@/lib/utils";

export function Header() {
  const { theme, setTheme } = useTheme();
  const [mounted, setMounted] = useState(false);
  const { user, sidebarOpen, toggleSidebar, alerts, metrics } = useMonitorStore();

  // Avoid hydration mismatch by waiting for mount
  useEffect(() => {
    setMounted(true);
  }, []);

  const unreadAlerts = alerts.filter((a) => !a.acknowledged);

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
            Network Operations Center
          </h1>
          <p className="text-xs text-zinc-500 dark:text-zinc-400">
            portal.itsupport.com.bd
          </p>
        </div>
      </div>

      {/* Center Monitoring Telemetry Quick-Bar */}
      <div className="hidden md:flex items-center gap-6 text-xs text-zinc-500 dark:text-zinc-400">
        <div className="flex items-center gap-2">
          <Cpu size={14} className="text-blue-500" />
          <span>
            CPU:{" "}
            <strong className="text-zinc-700 dark:text-zinc-300">
              {metrics.cpuUsage}%
            </strong>
          </span>
        </div>
        <div className="flex items-center gap-2">
          <HardDrive size={14} className="text-emerald-500" />
          <span>
            MEM:{" "}
            <strong className="text-zinc-700 dark:text-zinc-300">
              {metrics.memoryUsage}%
            </strong>
          </span>
        </div>
        <div className="h-4 w-px bg-zinc-200 dark:bg-zinc-800" />
        <div className="flex items-center gap-1.5 px-2.5 py-0.5 bg-red-100 dark:bg-red-950/40 text-red-600 dark:text-red-400 rounded-full font-medium">
          <ShieldAlert size={12} className="animate-pulse" />
          <span>
            {unreadAlerts.length} Active Alert{unreadAlerts.length !== 1 ? "s" : ""}
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
            aria-label={`View ${unreadAlerts.length} unread alerts`}
            className="p-2 text-zinc-500 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-900 rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
          >
            <Bell size={18} />
            {unreadAlerts.length > 0 && (
              <span className="absolute top-1.5 right-1.5 h-2 w-2 bg-red-500 border border-white dark:border-zinc-950 rounded-full" />
            )}
          </button>
        </div>

        <div className="h-6 w-px bg-zinc-200 dark:bg-zinc-800" />

        {/* User Profile Badge */}
        <div className="flex items-center gap-3">
          <div className="h-9 w-9 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-sm shadow-sm select-none">
            {user.name
              .split(" ")
              .map((n) => n[0])
              .join("")}
          </div>
          <div className="hidden lg:flex flex-col text-left">
            <span className="text-sm font-semibold text-zinc-900 dark:text-zinc-100 leading-tight">
              {user.name}
            </span>
            <span className="text-xs text-zinc-500 dark:text-zinc-400">
              {user.role}
            </span>
          </div>
        </div>
      </div>
    </header>
  );
}
