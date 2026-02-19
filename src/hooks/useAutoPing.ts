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
 * Calls the ping-device edge function individually per device on its own schedule.
 */
export function useAutoPing(
  devices: AutoPingDevice[],
  enabled: boolean,
  onPingComplete?: () => void
) {
  const timersRef = useRef<Map<string, ReturnType<typeof setTimeout>>>(new Map());
  const devicesRef = useRef(devices);
  devicesRef.current = devices;

  const pingDevice = useCallback(async (deviceId: string) => {
    try {
      await supabase.functions.invoke("ping-device", {
        body: { device_id: deviceId },
      });
      onPingComplete?.();
    } catch (err) {
      console.error(`Auto-ping failed for ${deviceId}:`, err);
    }
  }, [onPingComplete]);

  const scheduleDevice = useCallback((device: AutoPingDevice) => {
    if (!device.ip_address || device.monitor_method === "none") return;

    const intervalSec = device.ping_interval ?? 300;
    // Minimum 10 seconds to prevent abuse
    const intervalMs = Math.max(intervalSec, 10) * 1000;

    const tick = async () => {
      await pingDevice(device.id);
      // Re-check if device still exists before rescheduling
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
    // Clear all existing timers
    timersRef.current.forEach(t => clearTimeout(t));
    timersRef.current.clear();

    if (!enabled) return;

    // Schedule each eligible device
    for (const device of devices) {
      scheduleDevice(device);
    }

    return () => {
      timersRef.current.forEach(t => clearTimeout(t));
      timersRef.current.clear();
    };
  }, [devices, enabled, scheduleDevice]);
}
