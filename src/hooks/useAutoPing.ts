import { useEffect, useRef, useCallback } from "react";
import { supabase } from "@/integrations/supabase/client";

interface AutoPingDevice {
  id: string;
  ip_address: string | null;
  ping_interval: number | null;
  monitor_method: string | null;
}

/**
 * Manages per-device auto-ping timers based on each device's ping_interval.
 * Tracks last-ping timestamps for countdown display.
 */
export function useAutoPing(
  devices: AutoPingDevice[],
  enabled: boolean,
  onPingComplete?: () => void,
  onPingTimestamps?: (timestamps: Map<string, number>) => void
) {
  const timersRef = useRef<Map<string, ReturnType<typeof setTimeout>>>(new Map());
  const devicesRef = useRef(devices);
  const timestampsRef = useRef<Map<string, number>>(new Map());
  devicesRef.current = devices;

  const pingDevice = useCallback(async (deviceId: string) => {
    try {
      await supabase.functions.invoke("ping-device", {
        body: { device_id: deviceId },
      });
      timestampsRef.current.set(deviceId, Date.now());
      onPingTimestamps?.(new Map(timestampsRef.current));
      onPingComplete?.();
    } catch (err) {
      console.error(`Auto-ping failed for ${deviceId}:`, err);
    }
  }, [onPingComplete, onPingTimestamps]);

  const scheduleDevice = useCallback((device: AutoPingDevice) => {
    if (!device.ip_address || device.monitor_method === "none") return;

    const intervalSec = device.ping_interval ?? 300;
    const intervalMs = Math.max(intervalSec, 10) * 1000;

    // Set initial "scheduled at" timestamp
    timestampsRef.current.set(device.id, Date.now());

    const tick = async () => {
      await pingDevice(device.id);
      const still = devicesRef.current.find(d => d.id === device.id);
      if (still && still.ip_address && still.monitor_method !== "none") {
        const nextMs = Math.max((still.ping_interval ?? 300), 10) * 1000;
        timersRef.current.set(device.id, setTimeout(tick, nextMs));
      } else {
        timersRef.current.delete(device.id);
      }
    };

    timersRef.current.set(device.id, setTimeout(tick, intervalMs));
  }, [pingDevice]);

  useEffect(() => {
    timersRef.current.forEach(t => clearTimeout(t));
    timersRef.current.clear();
    timestampsRef.current.clear();

    if (!enabled) {
      onPingTimestamps?.(new Map());
      return;
    }

    for (const device of devices) {
      scheduleDevice(device);
    }
    onPingTimestamps?.(new Map(timestampsRef.current));

    return () => {
      timersRef.current.forEach(t => clearTimeout(t));
      timersRef.current.clear();
    };
  }, [devices, enabled, scheduleDevice, onPingTimestamps]);
}
