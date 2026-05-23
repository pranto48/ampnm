import { useEffect, useState, useCallback } from "react";
import { invoke } from "@tauri-apps/api/core";
import type { AgentConfig } from "../App";

interface Props {
  config: AgentConfig;
  agentOnline: boolean | null;
  onConfigChange: () => void;
}

interface Telemetry {
  cpu_usage_percent: number;
  memory_usage_percent: number;
  disk_usage_percent: number;
  network_rx_bytes: number;
  network_tx_bytes: number;
  uptime_seconds: number;
  process_count: number;
  battery_percent: number | null;
  battery_status: string;
  hostname: string;
  os_name: string;
  os_version: string;
  architecture: string;
}

type View = "status" | "settings" | "about";

export default function StatusDashboard({ config, agentOnline, onConfigChange }: Props) {
  const [view, setView] = useState<View>("status");
  const [telemetry, setTelemetry] = useState<Telemetry | null>(null);
  const [isPaused, setIsPaused] = useState(config.is_paused);
  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState<string | null>(null);

  // Settings form state
  const [interval, setInterval] = useState(config.heartbeat_interval_seconds);
  const [collectUsername, setCollectUsername] = useState(config.collect_username);
  const [collectMac, setCollectMac] = useState(config.collect_mac_address);
  const [saving, setSaving] = useState(false);

  const refreshTelemetry = useCallback(async () => {
    try {
      const t = await invoke<Telemetry>("get_telemetry");
      setTelemetry(t);
    } catch (e) {
      console.warn("Telemetry fetch failed:", e);
    }
  }, []);

  useEffect(() => {
    refreshTelemetry();
    const timer = setInterval(refreshTelemetry, 3000);
    return () => clearInterval(timer);
  }, [refreshTelemetry]);

  async function togglePause() {
    const next = !isPaused;
    await invoke("set_paused", { paused: next });
    setIsPaused(next);
    onConfigChange();
  }

  async function handleTest() {
    setTesting(true);
    setTestResult(null);
    try {
      await invoke("test_heartbeat");
      setTestResult("✅ Heartbeat sent successfully!");
    } catch (e: any) {
      setTestResult("❌ " + String(e));
    } finally {
      setTesting(false);
      setTimeout(() => setTestResult(null), 4000);
    }
  }

  async function handleSaveSettings() {
    setSaving(true);
    try {
      await invoke("update_settings", {
        heartbeatIntervalSeconds: interval,
        collectUsername,
        collectMacAddress: collectMac,
      });
      onConfigChange();
    } finally {
      setSaving(false);
    }
  }

  async function handleReset() {
    if (!confirm("This will clear your agent credentials and show the setup wizard again. Continue?")) return;
    await invoke("reset_agent");
    onConfigChange();
  }

  function formatBytes(bytes: number) {
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
    if (bytes < 1024 * 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + " MB";
    return (bytes / 1024 / 1024 / 1024).toFixed(2) + " GB";
  }

  function formatUptime(secs: number) {
    const d = Math.floor(secs / 86400);
    const h = Math.floor((secs % 86400) / 3600);
    const m = Math.floor((secs % 3600) / 60);
    if (d > 0) return `${d}d ${h}h ${m}m`;
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
  }

  const statusColor = agentOnline === true ? "#22c55e" : agentOnline === false ? "#ef4444" : "#64748b";
  const statusLabel = agentOnline === true ? "Online" : agentOnline === false ? "Offline" : "Connecting…";

  return (
    <div className="dashboard">
      {/* Header */}
      <div className="dash-header">
        <div className="dash-logo">
          <svg width="28" height="28" viewBox="0 0 48 48" fill="none">
            <rect width="48" height="48" rx="12" fill="#06b6d4" opacity="0.15"/>
            <path d="M12 36L24 12L36 36" stroke="#06b6d4" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"/>
            <path d="M16 28h16" stroke="#06b6d4" strokeWidth="2" strokeLinecap="round"/>
          </svg>
        </div>
        <div className="dash-title-block">
          <span className="dash-title">AMPNM Agent</span>
          <span className="dash-status" style={{ color: statusColor }}>
            <span className="status-dot" style={{ background: statusColor }}></span>
            {isPaused ? "Paused" : statusLabel}
          </span>
        </div>
        <nav className="dash-nav">
          {(["status", "settings", "about"] as View[]).map((v) => (
            <button
              key={v}
              id={`nav-${v}`}
              className={`nav-btn ${view === v ? "active" : ""}`}
              onClick={() => setView(v)}
            >
              {v.charAt(0).toUpperCase() + v.slice(1)}
            </button>
          ))}
        </nav>
      </div>

      {/* ── Status View ── */}
      {view === "status" && (
        <div className="dash-content">
          {/* Metric gauges */}
          {telemetry && (
            <div className="metrics-grid">
              <MetricCard label="CPU" value={telemetry.cpu_usage_percent} unit="%" color="#06b6d4" />
              <MetricCard label="Memory" value={telemetry.memory_usage_percent} unit="%" color="#a78bfa" />
              <MetricCard label="Disk" value={telemetry.disk_usage_percent} unit="%" color="#34d399" />
              <div className="metric-card info-card">
                <div className="metric-top">
                  <span className="metric-label">Network</span>
                </div>
                <div className="metric-net">
                  <span className="net-rx">↓ {formatBytes(telemetry.network_rx_bytes)}</span>
                  <span className="net-tx">↑ {formatBytes(telemetry.network_tx_bytes)}</span>
                </div>
              </div>
            </div>
          )}

          {/* Info rows */}
          {telemetry && (
            <div className="info-panel">
              <InfoRow icon="💻" label="Hostname" value={telemetry.hostname} />
              <InfoRow icon="🪟" label="OS" value={`${telemetry.os_name} ${telemetry.os_version} (${telemetry.architecture})`} />
              <InfoRow icon="⏱️" label="Uptime" value={formatUptime(telemetry.uptime_seconds)} />
              <InfoRow icon="⚙️" label="Processes" value={String(telemetry.process_count)} />
              {telemetry.battery_percent !== null && (
                <InfoRow icon="🔋" label="Battery" value={`${telemetry.battery_percent}% (${telemetry.battery_status})`} />
              )}
              <InfoRow icon="🔗" label="Server" value={config.server_url} />
              <InfoRow icon="🆔" label="Agent ID" value={config.agent_id ? String(config.agent_id) : "—"} />
            </div>
          )}

          {/* Test result */}
          {testResult && (
            <div className={`test-result ${testResult.startsWith("✅") ? "success" : "error"}`}>
              {testResult}
            </div>
          )}

          {/* Action buttons */}
          <div className="action-row">
            <button
              id="btn-pause"
              className={isPaused ? "btn-primary" : "btn-ghost"}
              onClick={togglePause}
            >
              {isPaused ? "▶ Resume" : "⏸ Pause"}
            </button>
            <button
              id="btn-test"
              className="btn-ghost"
              onClick={handleTest}
              disabled={testing || isPaused}
            >
              {testing ? "Sending…" : "🔁 Test Heartbeat"}
            </button>
          </div>
        </div>
      )}

      {/* ── Settings View ── */}
      {view === "settings" && (
        <div className="dash-content">
          <h2 className="section-title">Agent Settings</h2>

          <div className="settings-form">
            <div className="field-group">
              <label className="field-label">Heartbeat Interval (seconds)</label>
              <input
                id="setting-interval"
                type="number"
                min={5}
                max={3600}
                className="field-input"
                value={interval}
                onChange={(e) => setInterval(Number(e.target.value))}
              />
              <p className="field-hint">How often to send system metrics. Minimum: 5 seconds.</p>
            </div>

            <div className="checkbox-group">
              <label className="checkbox-label">
                <input
                  id="setting-username"
                  type="checkbox"
                  checked={collectUsername}
                  onChange={(e) => setCollectUsername(e.target.checked)}
                />
                <span>Report active Windows username</span>
              </label>
              <label className="checkbox-label">
                <input
                  id="setting-mac"
                  type="checkbox"
                  checked={collectMac}
                  onChange={(e) => setCollectMac(e.target.checked)}
                />
                <span>Report MAC / network adapter info</span>
              </label>
            </div>

            <button
              id="btn-save-settings"
              className="btn-primary"
              onClick={handleSaveSettings}
              disabled={saving}
            >
              {saving ? "Saving…" : "💾 Save Settings"}
            </button>
          </div>

          <div className="danger-zone">
            <h3 className="danger-title">Danger Zone</h3>
            <p className="danger-desc">
              Clear all credentials and return to the setup wizard. This does not delete your device from the server.
            </p>
            <button id="btn-reset" className="btn-danger" onClick={handleReset}>
              🗑️ Reset & Re-register
            </button>
          </div>
        </div>
      )}

      {/* ── About View ── */}
      {view === "about" && (
        <div className="dash-content">
          <div className="about-block">
            <div className="about-logo">
              <svg width="56" height="56" viewBox="0 0 48 48" fill="none">
                <rect width="48" height="48" rx="12" fill="#06b6d4" opacity="0.2"/>
                <path d="M12 36L24 12L36 36" stroke="#06b6d4" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"/>
                <path d="M16 28h16" stroke="#06b6d4" strokeWidth="2" strokeLinecap="round"/>
              </svg>
            </div>
            <h2 className="about-title">AMPNM Windows Agent</h2>
            <p className="about-version">Version 1.0.0</p>
            <p className="about-desc">
              This agent monitors your PC hardware metrics and sends them to your AMPNM
              (Advanced Multi-Protocol Network Monitor) server in real-time.
            </p>
            <div className="about-privacy">
              <h3>What is collected</h3>
              <ul>
                <li>CPU usage percentage</li>
                <li>Memory usage percentage</li>
                <li>Disk usage percentage</li>
                <li>Network bytes received / transmitted</li>
                <li>System uptime and process count</li>
                <li>Battery status (if present)</li>
                <li>Hostname, OS, local IP (optional)</li>
              </ul>
              <h3>What is NOT collected</h3>
              <ul>
                <li>❌ Screenshots or screen capture</li>
                <li>❌ Keyboard or mouse activity</li>
                <li>❌ File system contents</li>
                <li>❌ Browser history or application data</li>
                <li>❌ Passwords or credentials</li>
              </ul>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ── Sub-components ──────────────────────────────────────────────────────────

function MetricCard({ label, value, unit, color }: { label: string; value: number; unit: string; color: string }) {
  const rounded = Math.round(value * 10) / 10;
  const barColor = value >= 90 ? "#ef4444" : value >= 70 ? "#f59e0b" : color;

  return (
    <div className="metric-card">
      <div className="metric-top">
        <span className="metric-label">{label}</span>
        <span className="metric-value" style={{ color: barColor }}>{rounded}{unit}</span>
      </div>
      <div className="metric-bar-bg">
        <div
          className="metric-bar-fill"
          style={{ width: `${Math.min(100, value)}%`, background: barColor }}
        />
      </div>
    </div>
  );
}

function InfoRow({ icon, label, value }: { icon: string; label: string; value: string }) {
  return (
    <div className="info-row">
      <span className="info-icon">{icon}</span>
      <span className="info-label">{label}</span>
      <span className="info-value">{value}</span>
    </div>
  );
}
