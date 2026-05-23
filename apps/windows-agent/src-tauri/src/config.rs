use serde::{Deserialize, Serialize};
use std::sync::Arc;
use tokio::sync::Mutex;
use tauri_plugin_store::StoreExt;

/// Agent config — stored in the Tauri store and loaded on startup
#[derive(Debug, Clone, Serialize, Deserialize, Default)]
pub struct AgentConfig {
    pub server_url: String,
    pub enrollment_token: String,
    pub agent_id: Option<u64>,
    pub agent_secret: Option<String>,
    pub agent_uuid: String,
    pub heartbeat_interval_seconds: u32,
    pub collect_username: bool,
    pub collect_mac_address: bool,
    pub is_registered: bool,
    pub is_paused: bool,
}

impl AgentConfig {
    pub fn new_with_uuid() -> Self {
        Self {
            heartbeat_interval_seconds: 5,
            collect_username: true,
            collect_mac_address: true,
            agent_uuid: uuid::Uuid::new_v4().to_string(),
            is_registered: false,
            is_paused: false,
            ..Default::default()
        }
    }
}

pub type SharedConfig = Arc<Mutex<AgentConfig>>;

/// Load config from Tauri Store plugin
pub fn load_config(store: &tauri_plugin_store::Store<tauri::Wry>) -> AgentConfig {
    let server_url = store
        .get("server_url")
        .and_then(|v| v.as_str().map(|s| s.to_string()))
        .unwrap_or_default();

    let enrollment_token = store
        .get("enrollment_token")
        .and_then(|v| v.as_str().map(|s| s.to_string()))
        .unwrap_or_default();

    let agent_id = store
        .get("agent_id")
        .and_then(|v| v.as_u64());

    let agent_secret = store
        .get("agent_secret")
        .and_then(|v| v.as_str().map(|s| s.to_string()));

    let agent_uuid = store
        .get("agent_uuid")
        .and_then(|v| v.as_str().map(|s| s.to_string()))
        .unwrap_or_else(|| uuid::Uuid::new_v4().to_string());

    let heartbeat_interval_seconds = store
        .get("heartbeat_interval_seconds")
        .and_then(|v| v.as_u64())
        .unwrap_or(5) as u32;

    let collect_username = store
        .get("collect_username")
        .and_then(|v| v.as_bool())
        .unwrap_or(true);

    let collect_mac_address = store
        .get("collect_mac_address")
        .and_then(|v| v.as_bool())
        .unwrap_or(true);

    let is_registered = agent_id.is_some() && agent_secret.is_some() && !server_url.is_empty();
    let is_paused = store
        .get("is_paused")
        .and_then(|v| v.as_bool())
        .unwrap_or(false);

    AgentConfig {
        server_url,
        enrollment_token,
        agent_id,
        agent_secret,
        agent_uuid,
        heartbeat_interval_seconds,
        collect_username,
        collect_mac_address,
        is_registered,
        is_paused,
    }
}

/// Persist config to store
pub fn save_config(store: &tauri_plugin_store::Store<tauri::Wry>, config: &AgentConfig) {
    let _ = store.set("server_url", config.server_url.clone());
    let _ = store.set("enrollment_token", config.enrollment_token.clone());
    let _ = store.set("agent_uuid", config.agent_uuid.clone());
    let _ = store.set("heartbeat_interval_seconds", config.heartbeat_interval_seconds);
    let _ = store.set("collect_username", config.collect_username);
    let _ = store.set("collect_mac_address", config.collect_mac_address);
    let _ = store.set("is_paused", config.is_paused);

    if let Some(id) = config.agent_id {
        let _ = store.set("agent_id", id);
    }
    if let Some(ref secret) = config.agent_secret {
        let _ = store.set("agent_secret", secret.clone());
    }

    let _ = store.save();
}
