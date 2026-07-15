"use client";

import { ReactNode, useEffect } from "react";
import { Sidebar } from "@/components/sidebar";
import { Header } from "@/components/header";
import { useMonitorStore } from "@/store/use-monitor-store";
import { cn } from "@/lib/utils";
import { usePathname } from "next/navigation";

interface MainLayoutWrapperProps {
  children: ReactNode;
}

export function MainLayoutWrapper({ children }: MainLayoutWrapperProps) {
  const { sidebarOpen, syncWithFirestore } = useMonitorStore();
  const pathname = usePathname();

  // Check if this is a login or registration screen
  const isAuthPage = pathname === "/login" || pathname === "/register" || pathname === "/";

  useEffect(() => {
    if (!isAuthPage) {
      syncWithFirestore();
    }
  }, [isAuthPage, syncWithFirestore]);

  if (isAuthPage) {
    return <>{children}</>;
  }

  return (
    <div className="min-h-screen flex bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-50 transition-colors duration-200">
      <Sidebar />
      
      <div
        className={cn(
          "flex-1 flex flex-col min-h-screen transition-all duration-300 ease-in-out",
          sidebarOpen ? "pl-64" : "pl-16"
        )}
      >
        <Header />
        <main className="flex-1 p-6 md:p-8 overflow-y-auto">
          {children}
        </main>
      </div>
    </div>
  );
}
export default MainLayoutWrapper;
