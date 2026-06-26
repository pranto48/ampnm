"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useMonitorStore } from "@/store/use-monitor-store";
import { cn } from "@/lib/utils";
import { app } from "@/lib/firebase";
import { getAuth, signOut } from "firebase/auth";
import {
  Key,
  Users,
  ChevronLeft,
  ChevronRight,
  FileText,
  Package,
  LogOut,
  ShieldCheck,
} from "lucide-react";

export function Sidebar() {
  const pathname = usePathname();
  const router = useRouter();
  const { sidebarOpen, toggleSidebar, profile } = useMonitorStore();
  const auth = getAuth(app);

  const navItems = [
    { name: "Clients", href: "/dashboard/clients", icon: Users },
    { name: "Products (GMEN)", href: "/dashboard/products", icon: Package },
    { name: "Licenses", href: "/dashboard/licenses", icon: FileText },
  ];

  if (profile?.role === "admin") {
    navItems.push({ name: "Admin Panel", href: "/dashboard/admin", icon: ShieldCheck });
  }


  const handleSignOut = async () => {
    try {
      await signOut(auth);
      router.push("/login");
    } catch (e) {
      console.error("Sign out failure:", e);
    }
  };

  return (
    <aside
      aria-label="Main Navigation"
      className={cn(
        "h-screen fixed top-0 left-0 z-40 bg-white dark:bg-zinc-950 border-r border-zinc-200 dark:border-zinc-800 flex flex-col transition-all duration-300 ease-in-out",
        sidebarOpen ? "w-64" : "w-16"
      )}
    >
      <div className="h-16 flex items-center justify-between px-4 border-b border-zinc-200 dark:border-zinc-800">
        <Link href="/dashboard" className="flex items-center gap-3 overflow-hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 rounded-lg">
          <div className="p-1.5 bg-blue-100 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 rounded-lg flex-shrink-0">
            <Key className="h-5 w-5" />
          </div>
          <span
            className={cn(
              "font-bold text-zinc-900 dark:text-zinc-50 text-sm tracking-tight transition-opacity duration-200 whitespace-nowrap",
              sidebarOpen ? "opacity-100" : "opacity-0 w-0 pointer-events-none"
            )}
          >
            AMPNM Portal
          </span>
        </Link>

        {sidebarOpen && (
          <button
            onClick={toggleSidebar}
            aria-label="Collapse navigation sidebar"
            className="p-1 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-900 hover:text-zinc-900 dark:hover:text-zinc-50 rounded-md transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
          >
            <ChevronLeft size={18} />
          </button>
        )}
      </div>

      <nav className="flex-1 py-4 px-2 space-y-1 overflow-y-auto" aria-label="Sidebar navigation links">
        {navItems.map((item) => {
          const Icon = item.icon;
          const isActive = pathname === item.href;
          return (
            <Link
              key={item.href}
              href={item.href}
              className={cn(
                "flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-colors group relative focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500",
                isActive
                  ? "bg-blue-600 text-white"
                  : "text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-900 hover:text-zinc-900 dark:hover:text-zinc-50"
              )}
              aria-current={isActive ? "page" : undefined}
            >
              <Icon className="h-5 w-5 flex-shrink-0" />
              <span
                className={cn(
                  "transition-opacity duration-200 whitespace-nowrap",
                  sidebarOpen ? "opacity-100" : "opacity-0 w-0 pointer-events-none"
                )}
              >
                {item.name}
              </span>
              {!sidebarOpen && (
                <div className="absolute left-16 bg-zinc-950 text-zinc-50 dark:bg-zinc-50 dark:text-zinc-950 px-2.5 py-1.5 text-xs font-semibold rounded-md opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50 pointer-events-none shadow-lg border border-zinc-800 dark:border-zinc-200">
                  {item.name}
                </div>
              )}
            </Link>
          );
        })}
      </nav>

      <div className="p-2 border-t border-zinc-200 dark:border-zinc-800 space-y-1">
        {sidebarOpen && (
          <div className="px-3 py-1 text-[10px] font-semibold text-zinc-400 dark:text-zinc-500 tracking-wider uppercase text-center select-none">
            Made by IT Support BD
          </div>
        )}
        <button
          onClick={handleSignOut}
          className={cn(
            "w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 transition-colors group relative focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500",
            !sidebarOpen && "justify-center"
          )}
        >
          <LogOut className="h-5 w-5 flex-shrink-0" />
          {sidebarOpen && <span>Sign Out</span>}
          {!sidebarOpen && (
            <div className="absolute left-16 bg-red-600 text-white px-2.5 py-1.5 text-xs font-semibold rounded-md opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50 pointer-events-none shadow-lg">
              Sign Out
            </div>
          )}
        </button>

        {!sidebarOpen && (
          <div className="flex justify-center pt-2">
            <button
              onClick={toggleSidebar}
              aria-label="Expand navigation sidebar"
              className="p-2 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-900 hover:text-zinc-900 dark:hover:text-zinc-50 rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
            >
              <ChevronRight size={18} />
            </button>
          </div>
        )}
      </div>
    </aside>
  );
}
