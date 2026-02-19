import { createContext, useContext, useState, useEffect, useCallback, type ReactNode } from "react";
import { supabase } from "@/integrations/supabase/client";
import { useAutoPing } from "@/hooks/useAutoPing";
import type { Tables } from "@/integrations/supabase/types";

interface GlobalAutoPingContext {
  enabled: boolean;
  setEnabled: (v: boolean) => void;
  monitoredCount: number;
  totalCount: number;
}

const Ctx = createContext<GlobalAutoPingContext>({
  enabled: true,
  setEnabled: () => {},
  monitoredCount: 0,
  totalCount: 0,
});

export const useGlobalAutoPing = () => useContext(Ctx);

export function GlobalAutoPingProvider({ children }: { children: ReactNode }) {
  const [enabled, setEnabled] = useState(true);
  const [devices, setDevices] = useState<Tables<"devices">[]>([]);

  const loadDevices = useCallback(async () => {
    const { data } = await supabase.from("devices").select("*");
    if (data) setDevices(data);
  }, []);

  useEffect(() => {
    loadDevices();
    const interval = setInterval(loadDevices, 60000);
    return () => clearInterval(interval);
  }, [loadDevices]);

  // Listen for device changes
  useEffect(() => {
    const channel = supabase
      .channel("global-auto-ping-devices")
      .on("postgres_changes", { event: "*", schema: "public", table: "devices" }, () => {
        loadDevices();
      })
      .subscribe();
    return () => { supabase.removeChannel(channel); };
  }, [loadDevices]);

  const handlePingComplete = useCallback(() => {
    loadDevices();
  }, [loadDevices]);

  useAutoPing(devices, enabled, handlePingComplete);

  const monitoredCount = devices.filter(
    (d) => d.ip_address && d.monitor_method !== "none"
  ).length;

  return (
    <Ctx.Provider value={{ enabled, setEnabled, monitoredCount, totalCount: devices.length }}>
      {children}
    </Ctx.Provider>
  );
}
