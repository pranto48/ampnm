import { useEffect, useState } from "react";
import { invoke } from "@tauri-apps/api/core";
import { listen } from "@tauri-apps/api/event";
import SetupWizard from "./views/SetupWizard";
import StatusDashboard from "./views/StatusDashboard";

export interface AgentConfig {
  server_url: string;
  agent_uuid: string;
  agent_id: number | null;
  is_registered: boolean;
  is_paused: boolean;
  heartbeat_interval_seconds: number;
  collect_username: boolean;
  collect_mac_address: boolean;
}

export default function App() {
  const [config, setConfig] = useState<AgentConfig | null>(null);
  const [loading, setLoading] = useState(true);
  const [agentOnline, setAgentOnline] = useState<boolean | null>(null);

  async function reloadConfig() {
    try {
      const cfg = await invoke<AgentConfig>("get_config");
      setConfig(cfg);
    } catch (e) {
      console.error("Failed to load config:", e);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    reloadConfig();

    // Listen for heartbeat status events from Rust backend
    const unlisten = listen<{ online: boolean; error: string | null }>("agent-status", (event) => {
      setAgentOnline(event.payload.online);
    });

    return () => {
      unlisten.then((fn) => fn());
    };
  }, []);

  if (loading) {
    return (
      <div className="splash">
        <div className="splash-logo">
          <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
            <rect width="48" height="48" rx="12" fill="#06b6d4" opacity="0.15" />
            <path d="M12 36L24 12L36 36" stroke="#06b6d4" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M16 28h16" stroke="#06b6d4" strokeWidth="2" strokeLinecap="round" />
          </svg>
        </div>
        <p className="splash-text">Starting AMPNM Agent…</p>
      </div>
    );
  }

  if (!config?.is_registered) {
    return <SetupWizard onComplete={reloadConfig} />;
  }

  return (
    <StatusDashboard
      config={config}
      agentOnline={agentOnline}
      onConfigChange={reloadConfig}
    />
  );
}
