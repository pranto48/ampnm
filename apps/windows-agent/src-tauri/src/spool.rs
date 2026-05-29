//! spool.rs
//! Offline queue for heartbeats and log batches.
//! When the AMPNM server is unreachable, payloads are spooled to disk.
//! A background drain task retries delivery when connectivity is restored.

use chrono::Utc;
use serde::{Deserialize, Serialize};
use serde_json::Value;
use std::fs;
use std::path::PathBuf;

/// A single spooled item waiting to be sent.
#[derive(Debug, Serialize, Deserialize)]
pub struct SpoolItem {
    pub id: String,
    pub kind: SpoolKind,
    pub endpoint: String,
    pub payload: Value,
    pub created_at: String,
    pub attempts: u32,
}

#[derive(Debug, Serialize, Deserialize, PartialEq, Clone)]
#[serde(rename_all = "snake_case")]
pub enum SpoolKind {
    Heartbeat,
    LogBatch,
}

/// Return the spool directory path, creating it if needed.
pub fn spool_dir() -> PathBuf {
    let base = dirs::data_dir()
        .unwrap_or_else(|| PathBuf::from("."))
        .join("ampnm-agent")
        .join("spool");

    if !base.exists() {
        let _ = fs::create_dir_all(&base);
    }
    base
}

/// Persist an item to the spool directory.
pub fn spool_push(kind: SpoolKind, endpoint: &str, payload: Value) -> std::io::Result<()> {
    let id = uuid::Uuid::new_v4().to_string();
    let item = SpoolItem {
        id: id.clone(),
        kind,
        endpoint: endpoint.to_string(),
        payload,
        created_at: Utc::now().to_rfc3339(),
        attempts: 0,
    };

    let path = spool_dir().join(format!("{}.json", id));
    let json = serde_json::to_string_pretty(&item)?;
    fs::write(path, json)?;
    Ok(())
}

/// Load all spooled items, sorted oldest-first.
pub fn spool_load_all() -> Vec<SpoolItem> {
    let dir = spool_dir();
    let mut items: Vec<(std::time::SystemTime, SpoolItem)> = Vec::new();

    if let Ok(read) = fs::read_dir(&dir) {
        for entry in read.flatten() {
            if entry.path().extension().and_then(|e| e.to_str()) != Some("json") {
                continue;
            }
            if let Ok(content) = fs::read_to_string(entry.path()) {
                if let Ok(item) = serde_json::from_str::<SpoolItem>(&content) {
                    let mtime = entry.metadata().ok().and_then(|m| m.modified().ok())
                        .unwrap_or(std::time::SystemTime::UNIX_EPOCH);
                    items.push((mtime, item));
                }
            }
        }
    }

    items.sort_by(|a, b| a.0.cmp(&b.0));
    items.into_iter().map(|(_, i)| i).collect()
}

/// Remove a successfully delivered item from the spool.
pub fn spool_remove(id: &str) {
    let path = spool_dir().join(format!("{}.json", id));
    let _ = fs::remove_file(path);
}

/// Update the attempt count of a spooled item.
pub fn spool_increment_attempts(id: &str) {
    let path = spool_dir().join(format!("{}.json", id));
    if let Ok(content) = fs::read_to_string(&path) {
        if let Ok(mut item) = serde_json::from_str::<SpoolItem>(&content) {
            item.attempts += 1;
            // Discard items that have failed more than 100 times (stale)
            if item.attempts > 100 {
                let _ = fs::remove_file(&path);
                return;
            }
            if let Ok(json) = serde_json::to_string_pretty(&item) {
                let _ = fs::write(&path, json);
            }
        }
    }
}

/// Drain: attempt to deliver all spooled items.
/// Returns the number of items successfully sent.
pub async fn spool_drain(http_client: &reqwest::Client) -> usize {
    let items = spool_load_all();
    if items.is_empty() {
        return 0;
    }

    let mut sent = 0;
    for item in items {
        let result = http_client
            .post(&item.endpoint)
            .json(&item.payload)
            .timeout(std::time::Duration::from_secs(10))
            .send()
            .await;

        match result {
            Ok(resp) if resp.status().is_success() => {
                spool_remove(&item.id);
                sent += 1;
                log::info!("Spool: delivered {} ({})", item.kind.as_str(), item.id);
            }
            Ok(resp) => {
                log::warn!("Spool: server rejected {} with status {}", item.id, resp.status());
                spool_increment_attempts(&item.id);
            }
            Err(e) => {
                log::warn!("Spool: delivery failed for {}: {}", item.id, e);
                spool_increment_attempts(&item.id);
            }
        }
    }

    sent
}

impl SpoolKind {
    pub fn as_str(&self) -> &'static str {
        match self {
            SpoolKind::Heartbeat => "heartbeat",
            SpoolKind::LogBatch => "log_batch",
        }
    }
}
