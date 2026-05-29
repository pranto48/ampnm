mod agent;
mod config;
mod log_collector;
mod spool;
mod telemetry;

use config::{AgentConfig, SharedConfig, load_config, save_config};
use agent::{register_agent, send_heartbeat, sync_server_config};
use log_collector::{collect_logs, LogCollectorConfig, LogEntry};
use spool::{spool_push, spool_drain, SpoolKind};
use telemetry::collect_telemetry;

use std::sync::Arc;
use std::time::Duration;
use tauri::{
    AppHandle, Emitter, Manager,
    menu::{Menu, MenuItem},
    tray::{MouseButton, TrayIconBuilder, TrayIconEvent},
};
use tauri_plugin_store::StoreExt;
use tokio::sync::Mutex;
use reqwest::Client;
use serde_json::json;
use chrono::Utc;

const STORE_FILE: &str = "ampnm_agent.json";

/// Shared log collector configuration (thread-safe).
type SharedLogConfig = Arc<Mutex<LogCollectorConfig>>;

// ─── Tauri Commands ───────────────────────────────────────────────────────────

/// Get the current agent config (safe to expose — no full secret)
#[tauri::command]
async fn get_config(state: tauri::State<'_, SharedConfig>) -> Result<serde_json::Value, String> {
    let config = state.lock().await;
    Ok(json!({
        "server_url": config.server_url,
        "agent_uuid": config.agent_uuid,
        "agent_id": config.agent_id,
        "is_registered": config.is_registered,
        "is_paused": config.is_paused,
        "heartbeat_interval_seconds": config.heartbeat_interval_seconds,
        "collect_username": config.collect_username,
        "collect_mac_address": config.collect_mac_address,
    }))
}

/// Save the setup wizard input (server URL + enrollment token)
#[tauri::command]
async fn save_setup(
    app: AppHandle,
    state: tauri::State<'_, SharedConfig>,
    server_url: String,
    enrollment_token: String,
) -> Result<(), String> {
    let mut config = state.lock().await;
    config.server_url = server_url.trim().trim_end_matches('/').to_string();
    config.enrollment_token = enrollment_token.trim().to_string();

    let store = app.store(STORE_FILE).map_err(|e| e.to_string())?;
    save_config(&store, &config);
    Ok(())
}

/// Perform initial registration with the AMPNM server
#[tauri::command]
async fn register(
    app: AppHandle,
    state: tauri::State<'_, SharedConfig>,
    http_client: tauri::State<'_, Client>,
) -> Result<(), String> {
    let mut config = state.lock().await;
    register_agent(&app, &mut config, &http_client).await
}

/// Manually trigger a single heartbeat (for "Test Connection" button)
#[tauri::command]
async fn test_heartbeat(
    state: tauri::State<'_, SharedConfig>,
    http_client: tauri::State<'_, Client>,
) -> Result<serde_json::Value, String> {
    let config = state.lock().await;
    let t = collect_telemetry(config.collect_username);
    send_heartbeat(&config, &http_client)
        .await
        .map(|_| json!({ "ok": true, "telemetry": t }))
}

/// Get current telemetry snapshot (for live display in the status view)
#[tauri::command]
async fn get_telemetry(
    state: tauri::State<'_, SharedConfig>,
) -> Result<serde_json::Value, String> {
    let config = state.lock().await;
    let t = collect_telemetry(config.collect_username);
    Ok(serde_json::to_value(t).map_err(|e| e.to_string())?)
}

/// Update privacy and interval settings
#[tauri::command]
async fn update_settings(
    app: AppHandle,
    state: tauri::State<'_, SharedConfig>,
    heartbeat_interval_seconds: u32,
    collect_username: bool,
    collect_mac_address: bool,
) -> Result<(), String> {
    let mut config = state.lock().await;
    config.heartbeat_interval_seconds = heartbeat_interval_seconds.max(5).min(3600);
    config.collect_username = collect_username;
    config.collect_mac_address = collect_mac_address;

    let store = app.store(STORE_FILE).map_err(|e| e.to_string())?;
    save_config(&store, &config);
    Ok(())
}

/// Pause or resume the heartbeat loop
#[tauri::command]
async fn set_paused(
    app: AppHandle,
    state: tauri::State<'_, SharedConfig>,
    paused: bool,
) -> Result<(), String> {
    let mut config = state.lock().await;
    config.is_paused = paused;
    let store = app.store(STORE_FILE).map_err(|e| e.to_string())?;
    save_config(&store, &config);
    Ok(())
}

/// Unregister (clear credentials) and show setup wizard again
#[tauri::command]
async fn reset_agent(
    app: AppHandle,
    state: tauri::State<'_, SharedConfig>,
) -> Result<(), String> {
    let mut config = state.lock().await;
    config.agent_id = None;
    config.agent_secret = None;
    config.enrollment_token = String::new();
    config.is_registered = false;

    let store = app.store(STORE_FILE).map_err(|e| e.to_string())?;
    save_config(&store, &config);
    Ok(())
}

// ─── New Tauri Commands: Logs ────────────────────────────────────────────────

/// Fetch recent log entries using the current log collector config.
#[tauri::command]
async fn get_logs(
    state: tauri::State<'_, SharedConfig>,
    log_cfg: tauri::State<'_, SharedLogConfig>,
) -> Result<serde_json::Value, String> {
    let _config = state.lock().await;
    let lcfg = log_cfg.lock().await.clone();
    // Collect last ~30 min worth
    let since = Some(Utc::now() - chrono::Duration::minutes(30));
    let entries = collect_logs(&lcfg, since);
    let limited: Vec<&LogEntry> = entries.iter().take(100).collect();
    Ok(serde_json::to_value(limited).map_err(|e| e.to_string())?)
}

/// Get the current log collector configuration.
#[tauri::command]
async fn get_log_config(
    log_cfg: tauri::State<'_, SharedLogConfig>,
) -> Result<serde_json::Value, String> {
    let cfg = log_cfg.lock().await;
    Ok(serde_json::to_value(&*cfg).map_err(|e| e.to_string())?)
}

/// Update log collector configuration (channels, file paths, min level).
#[tauri::command]
async fn update_log_config(
    log_cfg: tauri::State<'_, SharedLogConfig>,
    channels: Vec<String>,
    file_paths: Vec<String>,
    min_level: String,
) -> Result<(), String> {
    let mut cfg = log_cfg.lock().await;
    cfg.event_channels = channels;
    cfg.file_paths = file_paths;
    cfg.min_level = match min_level.as_str() {
        "debug"    => log_collector::LogLevel::Debug,
        "info"     => log_collector::LogLevel::Info,
        "warning"  => log_collector::LogLevel::Warning,
        "error"    => log_collector::LogLevel::Error,
        "critical" => log_collector::LogLevel::Critical,
        _          => log_collector::LogLevel::Warning,
    };
    Ok(())
}

// ─── Heartbeat Loop ───────────────────────────────────────────────────────────

fn start_heartbeat_loop(app: AppHandle, shared_config: SharedConfig, http_client: Client) {
    tokio::spawn(async move {
        let mut tick: u64 = 0;

        loop {
            let (is_registered, is_paused, interval_secs, _server_url) = {
                let config = shared_config.lock().await;
                (
                    config.is_registered,
                    config.is_paused,
                    config.heartbeat_interval_seconds,
                    config.server_url.clone(),
                )
            };

            if is_registered && !is_paused {
                let config_snapshot = {
                    let config = shared_config.lock().await;
                    config.clone()
                };

                match send_heartbeat(&config_snapshot, &http_client).await {
                    Ok(_) => {
                        let _ = app.emit("agent-status", json!({ "online": true, "error": null }));
                        // Drain any spooled items now that server is reachable
                        let drained = spool_drain(&http_client).await;
                        if drained > 0 {
                            log::info!("Spool: drained {} queued items", drained);
                        }
                    }
                    Err(e) => {
                        log::warn!("Heartbeat failed (spooling): {}", e);
                        let _ = app.emit("agent-status", json!({ "online": false, "error": e }));
                        // Spool the failed heartbeat payload for later retry
                        let hb_payload = serde_json::to_value(&json!({
                            "agent_id": config_snapshot.agent_id,
                            "agent_uuid": config_snapshot.agent_uuid,
                        })).unwrap_or_default();
                        let endpoint = format!("{}/api/agent/windows-metrics/heartbeat", config_snapshot.server_url);
                        let _ = spool_push(SpoolKind::Heartbeat, &endpoint, hb_payload);
                    }
                }

                // Sync server config every ~5 minutes (every 60 heartbeats at 5s default)
                tick += 1;
                if tick % 60 == 0 {
                    let mut config = shared_config.lock().await;
                    let _ = sync_server_config(&app, &mut config, &http_client).await;
                }
            }

            tokio::time::sleep(Duration::from_secs(interval_secs.max(5) as u64)).await;
        }
    });
}

// ─── Log Sync Loop ────────────────────────────────────────────────────────────

fn start_log_sync_loop(
    app: AppHandle,
    shared_config: SharedConfig,
    log_config: SharedLogConfig,
    http_client: Client,
) {
    tokio::spawn(async move {
        let mut last_sync = Utc::now() - chrono::Duration::seconds(65);

        loop {
            tokio::time::sleep(Duration::from_secs(60)).await;

            let (is_registered, is_paused, server_url, agent_id, agent_secret) = {
                let cfg = shared_config.lock().await;
                (
                    cfg.is_registered,
                    cfg.is_paused,
                    cfg.server_url.clone(),
                    cfg.agent_id,
                    cfg.agent_secret.clone(),
                )
            };

            if !is_registered || is_paused {
                continue;
            }

            let since = Some(last_sync);
            let lcfg = log_config.lock().await.clone();
            let entries = collect_logs(&lcfg, since);

            if entries.is_empty() {
                last_sync = Utc::now();
                continue;
            }

            let endpoint = format!("{}/api/agent/logs", server_url);
            let payload = json!({
                "agent_id": agent_id,
                "entries": entries,
            });

            let mut headers = reqwest::header::HeaderMap::new();
            if let Some(ref secret) = agent_secret {
                if let Ok(v) = reqwest::header::HeaderValue::from_str(secret) {
                    headers.insert("X-Agent-Secret", v);
                }
            }

            match http_client
                .post(&endpoint)
                .headers(headers)
                .json(&payload)
                .timeout(Duration::from_secs(15))
                .send()
                .await
            {
                Ok(resp) if resp.status().is_success() => {
                    log::info!("Log sync: sent {} entries", entries.len());
                    let _ = app.emit("log-sync-status", json!({ "ok": true, "count": entries.len() }));
                    last_sync = Utc::now();
                }
                Ok(resp) => {
                    log::warn!("Log sync: server rejected with status {}", resp.status());
                    let _ = spool_push(SpoolKind::LogBatch, &endpoint, payload);
                }
                Err(e) => {
                    log::warn!("Log sync: failed to send (spooling): {}", e);
                    let _ = spool_push(SpoolKind::LogBatch, &endpoint, payload);
                    let _ = app.emit("log-sync-status", json!({ "ok": false, "error": e.to_string() }));
                }
            }
        }
    });
}

// ─── App entry ───────────────────────────────────────────────────────────────

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_autostart::init(
            tauri_plugin_autostart::MacosLauncher::LaunchAgent,
            Some(vec!["--minimized"]),
        ))
        .plugin(tauri_plugin_http::init())
        .plugin(tauri_plugin_notification::init())
        .plugin(tauri_plugin_process::init())
        .plugin(tauri_plugin_shell::init())
        .plugin(tauri_plugin_store::Builder::new().build())
        .setup(|app| {
            // Load persisted config
            let store = app.store(STORE_FILE)?;
            let config = load_config(&store);
            drop(store);

            let shared_config: SharedConfig = Arc::new(Mutex::new(config));
            app.manage(shared_config.clone());

            // HTTP client
            let http_client = Client::builder()
                .timeout(Duration::from_secs(10))
                .danger_accept_invalid_certs(false)
                .build()
                .expect("Failed to create HTTP client");
            app.manage(http_client.clone());

            // System tray
            setup_tray(app)?;

            // Log collector config
            let log_config: SharedLogConfig = Arc::new(Mutex::new(LogCollectorConfig::default()));
            app.manage(log_config.clone());

            // Start background heartbeat loop
            let app_handle = app.handle().clone();
            start_heartbeat_loop(app_handle.clone(), shared_config.clone(), http_client.clone());

            // Start background log-sync loop (every 60 seconds)
            start_log_sync_loop(app_handle, shared_config, log_config, http_client);

            Ok(())
        })
        .invoke_handler(tauri::generate_handler![
            get_config,
            save_setup,
            register,
            test_heartbeat,
            get_telemetry,
            update_settings,
            set_paused,
            reset_agent,
            get_logs,
            get_log_config,
            update_log_config,
        ])
        .run(tauri::generate_context!())
        .expect("Error while running AMPNM agent");
}

// ─── Tray Setup ──────────────────────────────────────────────────────────────

fn setup_tray(app: &mut tauri::App) -> tauri::Result<()> {
    let open_item = MenuItem::with_id(app, "open", "Open AMPNM Agent", true, None::<&str>)?;
    let pause_item = MenuItem::with_id(app, "pause", "Pause Monitoring", true, None::<&str>)?;
    let quit_item = MenuItem::with_id(app, "quit", "Quit", true, None::<&str>)?;

    let menu = Menu::with_items(app, &[&open_item, &pause_item, &quit_item])?;

    TrayIconBuilder::new()
        .icon(app.default_window_icon().cloned().unwrap())
        .menu(&menu)
        .tooltip("AMPNM Windows Agent")
        .on_tray_icon_event(|tray, event| {
            match event {
                TrayIconEvent::Click { button: MouseButton::Left, .. } => {
                    if let Some(window) = tray.app_handle().get_webview_window("main") {
                        let _ = window.show();
                        let _ = window.set_focus();
                    }
                }
                _ => {}
            }
        })
        .on_menu_event(|app, event| match event.id.as_ref() {
            "open" => {
                if let Some(window) = app.get_webview_window("main") {
                    let _ = window.show();
                    let _ = window.set_focus();
                }
            }
            "quit" => {
                app.exit(0);
            }
            _ => {}
        })
        .build(app)?;

    Ok(())
}
