use crate::config::{AgentConfig, SharedConfig, save_config, load_config};
use crate::telemetry::collect_telemetry;
use reqwest::Client;
use serde::{Deserialize, Serialize};
use serde_json::{json, Value};
use tauri::AppHandle;
use tauri_plugin_store::StoreExt;

const STORE_FILE: &str = "ampnm_agent.json";

#[derive(Debug, Serialize, Deserialize)]
struct RegisterResponse {
    success: bool,
    agent_id: Option<u64>,
    secret: Option<String>,
    message: Option<String>,
}

#[derive(Debug, Serialize, Deserialize)]
struct ConfigResponse {
    heartbeat_interval_seconds: Option<u32>,
    collect_username: Option<bool>,
    collect_mac_address: Option<bool>,
}

/// Attempt registration with the AMPNM server using an enrollment token.
pub async fn register_agent(
    app: &AppHandle,
    config: &mut AgentConfig,
    client: &Client,
) -> Result<(), String> {
    if config.server_url.is_empty() || config.enrollment_token.is_empty() {
        return Err("Server URL and enrollment token must be set.".to_string());
    }

    let telemetry = collect_telemetry(config.collect_username);

    let url = format!("{}/api/agent/register.php", config.server_url.trim_end_matches('/'));

    let body = json!({
        "enrollment_token": config.enrollment_token,
        "agent_uuid": config.agent_uuid,
        "hostname": telemetry.hostname,
        "os_name": telemetry.os_name,
        "os_version": telemetry.os_version,
        "architecture": telemetry.architecture,
        "cpu_model": telemetry.cpu_model,
        "cpu_cores": telemetry.cpu_cores,
        "total_memory_mb": telemetry.total_memory_mb,
        "total_disk_gb": telemetry.total_disk_gb,
        "local_ip": telemetry.current_ip,
        "app_version": env!("CARGO_PKG_VERSION"),
    });

    let response = client
        .post(&url)
        .json(&body)
        .timeout(std::time::Duration::from_secs(10))
        .send()
        .await
        .map_err(|e| format!("Connection failed: {}", e))?;

    let status = response.status();
    let text = response.text().await.map_err(|e| e.to_string())?;

    let data: RegisterResponse = serde_json::from_str(&text)
        .map_err(|_| format!("Invalid server response (HTTP {}): {}", status.as_u16(), &text[..text.len().min(200)]))?;

    if !data.success {
        return Err(data.message.unwrap_or_else(|| "Registration rejected by server.".to_string()));
    }

    let agent_id = data.agent_id.ok_or("Server returned no agent_id")?;
    let secret = data.secret.ok_or("Server returned no secret — registration failed")?;

    config.agent_id = Some(agent_id);
    config.agent_secret = Some(secret);
    config.is_registered = true;

    // Persist to store
    let store = app.store(STORE_FILE).map_err(|e| e.to_string())?;
    save_config(&store, config);

    // Fetch server-supplied config
    let _ = sync_server_config(app, config, client).await;

    Ok(())
}

/// Send a heartbeat to the server
pub async fn send_heartbeat(
    config: &AgentConfig,
    client: &Client,
) -> Result<(), String> {
    let agent_id = config.agent_id.ok_or("Agent not registered")?;
    let secret = config.agent_secret.as_ref().ok_or("No agent secret")?;

    let telemetry = collect_telemetry(config.collect_username);

    let url = format!("{}/api/agent/heartbeat.php", config.server_url.trim_end_matches('/'));

    let body = json!({
        "agent_id": agent_id,
        "cpu_usage_percent": telemetry.cpu_usage_percent,
        "memory_usage_percent": telemetry.memory_usage_percent,
        "disk_usage_percent": telemetry.disk_usage_percent,
        "network_rx_bytes": telemetry.network_rx_bytes,
        "network_tx_bytes": telemetry.network_tx_bytes,
        "uptime_seconds": telemetry.uptime_seconds,
        "process_count": telemetry.process_count,
        "battery_percent": telemetry.battery_percent,
        "battery_status": telemetry.battery_status,
        "active_user": telemetry.active_user,
        "local_ip": telemetry.current_ip,
        "agent_version": env!("CARGO_PKG_VERSION"),
    });

    let auth = format!("{}:{}", agent_id, secret);
    let encoded_auth = base64_encode(&auth);

    client
        .post(&url)
        .header("Authorization", format!("Bearer {}", encoded_auth))
        .json(&body)
        .timeout(std::time::Duration::from_secs(5))
        .send()
        .await
        .map_err(|e| format!("Heartbeat failed: {}", e))?;

    Ok(())
}

/// Sync server-supplied global config into local config
pub async fn sync_server_config(
    app: &AppHandle,
    config: &mut AgentConfig,
    client: &Client,
) -> Result<(), String> {
    let url = format!("{}/api/agent/config.php", config.server_url.trim_end_matches('/'));

    let response = client
        .get(&url)
        .timeout(std::time::Duration::from_secs(5))
        .send()
        .await
        .map_err(|e| e.to_string())?;

    let data: Value = response.json().await.map_err(|e| e.to_string())?;

    if let Some(interval) = data.get("heartbeat_interval_seconds").and_then(|v| v.as_u64()) {
        config.heartbeat_interval_seconds = interval as u32;
    }
    if let Some(val) = data.get("collect_username").and_then(|v| v.as_bool()) {
        config.collect_username = val;
    }

    let store = app.store(STORE_FILE).map_err(|e| e.to_string())?;
    save_config(&store, config);

    Ok(())
}

/// Simple base64 encoder (avoids extra dependency)
fn base64_encode(input: &str) -> String {
    use std::fmt::Write;
    const CHARS: &[u8] = b"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
    let bytes = input.as_bytes();
    let mut result = String::new();
    for chunk in bytes.chunks(3) {
        let b0 = chunk[0] as u32;
        let b1 = if chunk.len() > 1 { chunk[1] as u32 } else { 0 };
        let b2 = if chunk.len() > 2 { chunk[2] as u32 } else { 0 };
        let n = (b0 << 16) | (b1 << 8) | b2;
        result.push(CHARS[((n >> 18) & 0x3F) as usize] as char);
        result.push(CHARS[((n >> 12) & 0x3F) as usize] as char);
        if chunk.len() > 1 { result.push(CHARS[((n >> 6) & 0x3F) as usize] as char); } else { result.push('='); }
        if chunk.len() > 2 { result.push(CHARS[(n & 0x3F) as usize] as char); } else { result.push('='); }
    }
    result
}
