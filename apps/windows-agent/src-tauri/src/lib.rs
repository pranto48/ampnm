mod agent;
mod config;
mod telemetry;

use config::{AgentConfig, SharedConfig, load_config, save_config};
use agent::{register_agent, send_heartbeat, sync_server_config};
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

const STORE_FILE: &str = "ampnm_agent.json";

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
                    }
                    Err(e) => {
                        log::warn!("Heartbeat failed: {}", e);
                        let _ = app.emit("agent-status", json!({ "online": false, "error": e }));
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

            // Start background heartbeat loop
            let app_handle = app.handle().clone();
            start_heartbeat_loop(app_handle, shared_config, http_client);

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
