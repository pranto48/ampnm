import { createContext, useContext, useState, useEffect, useCallback, type ReactNode } from "react";
import { supabase } from "@/integrations/supabase/client";
import { useAutoPing } from "@/hooks/useAutoPing";
import type { Tables } from "@/integrations/supabase/types";

export interface MonitoredDevice {
  id: string;
  name: string;
  ip_address: string | null;
  status: string | null;
  ping_interval: number | null;
  monitor_method: string | null;
  last_ping: string | null;
}

interface GlobalAutoPingContext {
  enabled: boolean;
  setEnabled: (v: boolean) => void;
  monitoredCount: number;
  totalCount: number;
  monitoredDevices: MonitoredDevice[];
  lastPingTimestamps: Map<string, number>;
}

const Ctx = createContext<GlobalAutoPingContext>({
  enabled: true,
  setEnabled: () => {},
  monitoredCount: 0,
  totalCount: 0,
  monitoredDevices: [],
  lastPingTimestamps: new Map(),
});

export const useGlobalAutoPing = () => useContext(Ctx);

export function GlobalAutoPingProvider({ children }: { children: ReactNode }) {
  const [enabled, setEnabled] = useState(true);
  const [devices, setDevices] = useState<Tables<"devices">[]>([]);
  const [lastPingTimestamps, setLastPingTimestamps] = useState<Map<string, number>>(new Map());

  const loadDevices = useCallback(async () => {
    const { data } = await supabase.from("devices").select("*");
    if (data) setDevices(data);
  }, []);

  useEffect(() => {
    loadDevices();
    const interval = setInterval(loadDevices, 60000);
    return () => clearInterval(interval);
  }, [loadDevices]);

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

  const handlePingTimestamps = useCallback((ts: Map<string, number>) => {
    setLastPingTimestamps(ts);
  }, []);

  useAutoPing(devices, enabled, handlePingComplete, handlePingTimestamps);

  const monitoredDevices: MonitoredDevice[] = devices
    .filter((d) => d.ip_address && d.monitor_method !== "none")
    .map((d) => ({
      id: d.id,
      name: d.name,
      ip_address: d.ip_address,
      status: d.status,
      ping_interval: d.ping_interval,
      monitor_method: d.monitor_method,
      last_ping: d.last_ping,
    }));

  return (
    <Ctx.Provider value={{
      enabled,
      setEnabled,
      monitoredCount: monitoredDevices.length,
      totalCount: devices.length,
      monitoredDevices,
      lastPingTimestamps,
    }}>
      {children}
    </Ctx.Provider>
  );
}
