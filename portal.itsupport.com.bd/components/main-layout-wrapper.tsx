"use client";

import { ReactNode } from "react";
import { Sidebar } from "@/components/sidebar";
import { Header } from "@/components/header";
import { useMonitorStore } from "@/store/use-monitor-store";
import { cn } from "@/lib/utils";

interface MainLayoutWrapperProps {
  children: ReactNode;
}

export function MainLayoutWrapper({ children }: MainLayoutWrapperProps) {
  const { sidebarOpen } = useMonitorStore();

  return (
    <div className="min-h-screen flex bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-50 transition-colors duration-200">
      {/* Accessible Sidebar Navigation */}
      <Sidebar />

      {/* Main Panel Content Area */}
      <div
        className={cn(
          "flex-1 flex flex-col min-h-screen transition-all duration-300 ease-in-out",
          sidebarOpen ? "pl-64" : "pl-16"
        )}
      >
        <Header />
        
        {/* Main Work Area viewport */}
        <main className="flex-1 p-6 md:p-8 overflow-y-auto">
          {children}
        </main>
      </div>
    </div>
  );
}
