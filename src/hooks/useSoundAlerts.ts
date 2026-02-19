import { useEffect, useRef, useState, useCallback } from "react";
import { supabase } from "@/integrations/supabase/client";

type AlertStatus = "online" | "offline" | "warning" | "critical";

const SOUND_URLS: Record<AlertStatus, string> = {
  online: "/sounds/online.mp3",
  offline: "/sounds/offline.mp3",
  warning: "/sounds/warning.mp3",
  critical: "/sounds/critical.mp3",
};

const STORAGE_KEY = "ampnm-sound-alerts";

interface SoundPrefs {
  enabled: boolean;
  online: boolean;
  offline: boolean;
  warning: boolean;
  critical: boolean;
}

const DEFAULT_PREFS: SoundPrefs = {
  enabled: true,
  online: true,
  offline: true,
  warning: true,
  critical: true,
};

function loadPrefs(): SoundPrefs {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw) return { ...DEFAULT_PREFS, ...JSON.parse(raw) };
  } catch {}
  return DEFAULT_PREFS;
}

export function useSoundAlerts() {
  const [prefs, setPrefs] = useState<SoundPrefs>(loadPrefs);
  const soundsRef = useRef<Map<string, HTMLAudioElement>>(new Map());
  const audioUnlocked = useRef(false);

  // Preload sounds
  useEffect(() => {
    for (const [key, url] of Object.entries(SOUND_URLS)) {
      const audio = new Audio(url);
      audio.load();
      soundsRef.current.set(key, audio);
    }

    const unlock = () => {
      if (audioUnlocked.current) return;
      soundsRef.current.forEach((s) => {
        s.volume = 0;
        s.play().catch(() => {});
        s.pause();
        s.currentTime = 0;
        s.volume = 1;
      });
      audioUnlocked.current = true;
    };

    document.addEventListener("click", unlock, { once: true });
    document.addEventListener("keydown", unlock, { once: true });

    return () => {
      document.removeEventListener("click", unlock);
      document.removeEventListener("keydown", unlock);
    };
  }, []);

  const playSound = useCallback(
    (status: string) => {
      if (!prefs.enabled) return;
      const key = status as AlertStatus;
      if (!(key in SOUND_URLS)) return;
      if (!prefs[key]) return;

      const sound = soundsRef.current.get(key);
      if (sound) {
        sound.currentTime = 0;
        sound.play().catch(() => {});
      }
    },
    [prefs]
  );

  const updatePrefs = useCallback((partial: Partial<SoundPrefs>) => {
    setPrefs((prev) => {
      const next = { ...prev, ...partial };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
      return next;
    });
  }, []);

  // Listen for device_status_logs inserts via Realtime
  useEffect(() => {
    const channel = supabase
      .channel("sound-alerts-status-logs")
      .on(
        "postgres_changes",
        { event: "INSERT", schema: "public", table: "device_status_logs" },
        (payload) => {
          const newStatus = payload.new?.new_status;
          if (newStatus) {
            playSound(newStatus);
          }
        }
      )
      .subscribe();

    return () => {
      supabase.removeChannel(channel);
    };
  }, [playSound]);

  return { prefs, updatePrefs, playSound };
}
