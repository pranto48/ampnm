//! log_collector.rs
//! Collects log entries from:
//!   1. Windows Event Logs (Application, System channels) via the Win32 API
//!   2. Optional user-defined file paths (tail last N lines)

use chrono::{DateTime, Utc};
use serde::{Deserialize, Serialize};

/// A single structured log entry sent to the AMPNM server.
#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct LogEntry {
    pub id: String,
    pub timestamp: DateTime<Utc>,
    pub level: LogLevel,
    pub source: String,
    pub channel: String,
    pub message: String,
    pub event_id: Option<u32>,
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
#[serde(rename_all = "lowercase")]
pub enum LogLevel {
    Info,
    Warning,
    Error,
    Critical,
    Debug,
}

impl std::fmt::Display for LogLevel {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        match self {
            LogLevel::Info => write!(f, "info"),
            LogLevel::Warning => write!(f, "warning"),
            LogLevel::Error => write!(f, "error"),
            LogLevel::Critical => write!(f, "critical"),
            LogLevel::Debug => write!(f, "debug"),
        }
    }
}

/// Configuration for log collection.
#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct LogCollectorConfig {
    /// Windows Event Log channels to collect from.
    pub event_channels: Vec<String>,
    /// Maximum entries to collect per channel per cycle.
    pub max_entries_per_channel: usize,
    /// Optional file paths to tail (e.g. "C:\\logs\\app.log").
    pub file_paths: Vec<String>,
    /// Minimum log level to include.
    pub min_level: LogLevel,
}

impl Default for LogCollectorConfig {
    fn default() -> Self {
        Self {
            event_channels: vec!["Application".to_string(), "System".to_string()],
            max_entries_per_channel: 25,
            file_paths: vec![],
            min_level: LogLevel::Warning,
        }
    }
}

/// Collect log entries from all configured sources.
/// Returns a combined, deduplicated, time-sorted list.
pub fn collect_logs(config: &LogCollectorConfig, since: Option<DateTime<Utc>>) -> Vec<LogEntry> {
    let mut entries: Vec<LogEntry> = Vec::new();

    // 1. Windows Event Logs
    #[cfg(target_os = "windows")]
    {
        for channel in &config.event_channels {
            match collect_windows_event_logs(channel, config.max_entries_per_channel, since, &config.min_level) {
                Ok(mut ch_entries) => entries.append(&mut ch_entries),
                Err(e) => log::warn!("Failed to collect event log '{}': {}", channel, e),
            }
        }
    }

    // 2. File tails
    for path in &config.file_paths {
        match tail_log_file(path, 50, since) {
            Ok(mut file_entries) => entries.append(&mut file_entries),
            Err(e) => log::warn!("Failed to tail log file '{}': {}", path, e),
        }
    }

    // Sort by timestamp (newest first for display)
    entries.sort_by(|a, b| b.timestamp.cmp(&a.timestamp));

    entries
}

// ── Windows Event Log Collection ─────────────────────────────────────────────

#[cfg(target_os = "windows")]
fn collect_windows_event_logs(
    channel: &str,
    max_entries: usize,
    since: Option<DateTime<Utc>>,
    min_level: &LogLevel,
) -> Result<Vec<LogEntry>, String> {
    use windows::core::PCWSTR;

    use windows::Win32::System::EventLog::{
        CloseEventLog, OpenEventLogW, ReadEventLogW,
        EVENTLOGRECORD, EVENTLOG_BACKWARDS_READ, EVENTLOG_SEQUENTIAL_READ,
    };

    // Win32 EVENTLOG event type constants (raw u16 values)
    const EVENTLOG_ERROR: u16       = 0x0001;
    const EVENTLOG_WARNING: u16     = 0x0002;
    const EVENTLOG_INFORMATION: u16 = 0x0004;
    const EVENTLOG_SUCCESS: u16     = 0x0000;

    let channel_wide: Vec<u16> = channel.encode_utf16().chain(std::iter::once(0)).collect();
    let log_handle = unsafe {
        OpenEventLogW(PCWSTR::null(), PCWSTR(channel_wide.as_ptr()))
            .map_err(|e| format!("OpenEventLogW: {}", e))?
    };

    let mut entries = Vec::new();
    let mut buffer = vec![0u8; 65536];
    let mut bytes_read: u32 = 0;
    let mut min_bytes_needed: u32 = 0;
    let mut count = 0;

    loop {
        if count >= max_entries {
            break;
        }

        let ok = unsafe {
            ReadEventLogW(
                log_handle,
                EVENTLOG_BACKWARDS_READ | EVENTLOG_SEQUENTIAL_READ,
                0,
                buffer.as_mut_ptr() as *mut _,
                buffer.len() as u32,
                &mut bytes_read,
                &mut min_bytes_needed,
            )
        };

        if ok.is_err() {
            // ERROR_HANDLE_EOF (38) means no more records
            break;
        }

        let mut offset = 0usize;
        while offset < bytes_read as usize {
            let record = unsafe {
                &*(buffer.as_ptr().add(offset) as *const EVENTLOGRECORD)
            };

            if record.Length == 0 {
                break;
            }

            // Parse timestamp
            let ts = DateTime::from_timestamp(record.TimeGenerated as i64, 0)
                .unwrap_or_else(Utc::now);

            // Skip if before `since`
            if let Some(since_ts) = since {
                if ts <= since_ts {
                    offset += record.Length as usize;
                    count += 1;
                    continue;
                }
            }

            // Map event type to level
            let level = match record.EventType {
                EVENTLOG_ERROR       => LogLevel::Error,
                EVENTLOG_WARNING     => LogLevel::Warning,
                EVENTLOG_INFORMATION => LogLevel::Info,
                EVENTLOG_SUCCESS     => LogLevel::Info,
                _                    => LogLevel::Info,
            };

            // Filter by min_level
            if !level_passes(&level, min_level) {
                offset += record.Length as usize;
                count += 1;
                continue;
            }

            // Extract SourceName (starts right after the fixed struct header)
            let source_start = offset + std::mem::size_of::<EVENTLOGRECORD>();
            let source_slice = &buffer[source_start..];
            let source_wide: Vec<u16> = source_slice
                .chunks(2)
                .take_while(|ch| ch.len() == 2 && (ch[0] != 0 || ch[1] != 0))
                .map(|ch| u16::from_le_bytes([ch[0], ch[1]]))
                .collect();
            let source = String::from_utf16_lossy(&source_wide);

            // Extract message strings (simplified — concatenate raw string data)
            let strings_offset = record.StringOffset as usize + offset;
            let strings_raw = if strings_offset < bytes_read as usize {
                let s = &buffer[strings_offset..bytes_read as usize];
                // Read NumStrings wide strings separated by null terminators
                let mut result = String::new();
                let mut i = 0usize;
                let mut str_count = 0u16;
                while i + 1 < s.len() && str_count < record.NumStrings {
                    let ch = u16::from_le_bytes([s[i], s[i + 1]]);
                    if ch == 0 {
                        result.push('\n');
                        str_count += 1;
                    } else {
                        result.push(char::from_u32(ch as u32).unwrap_or('?'));
                    }
                    i += 2;
                }
                result.trim().to_string()
            } else {
                String::new()
            };

            let message = if strings_raw.is_empty() {
                format!("Event ID {}", record.EventID & 0xFFFF)
            } else {
                strings_raw
            };

            entries.push(LogEntry {
                id: format!("{}-{}-{}", channel, record.RecordNumber, record.TimeGenerated),
                timestamp: ts,
                level,
                source: source.clone(),
                channel: channel.to_string(),
                message: message.chars().take(500).collect(), // cap at 500 chars
                event_id: Some(record.EventID & 0xFFFF),
            });

            count += 1;
            offset += record.Length as usize;
        }
    }

    unsafe { let _ = CloseEventLog(log_handle); }

    Ok(entries)
}

fn level_passes(level: &LogLevel, min: &LogLevel) -> bool {
    let rank = |l: &LogLevel| match l {
        LogLevel::Debug => 0,
        LogLevel::Info => 1,
        LogLevel::Warning => 2,
        LogLevel::Error => 3,
        LogLevel::Critical => 4,
    };
    rank(level) >= rank(min)
}

// ── File Tail ────────────────────────────────────────────────────────────────

/// Read the last `n` lines from a file and parse them as simple log entries.
fn tail_log_file(
    path: &str,
    n: usize,
    since: Option<DateTime<Utc>>,
) -> Result<Vec<LogEntry>, String> {
    use std::fs::File;
    use std::io::{BufRead, BufReader};

    use uuid::Uuid;

    let file = File::open(path).map_err(|e| e.to_string())?;
    let reader = BufReader::new(file);
    let lines: Vec<String> = reader
        .lines()
        .filter_map(|l| l.ok())
        .filter(|l| !l.trim().is_empty())
        .collect();

    let tail: Vec<&String> = lines.iter().rev().take(n).collect();

    let entries = tail
        .into_iter()
        .enumerate()
        .map(|(i, line)| {
            let level = if line.to_lowercase().contains("error") {
                LogLevel::Error
            } else if line.to_lowercase().contains("warn") {
                LogLevel::Warning
            } else if line.to_lowercase().contains("crit") {
                LogLevel::Critical
            } else {
                LogLevel::Info
            };

            let ts = since.map(|s| s + chrono::Duration::seconds(i as i64)).unwrap_or_else(Utc::now);

            LogEntry {
                id: Uuid::new_v4().to_string(),
                timestamp: ts,
                level,
                source: std::path::Path::new(path)
                    .file_name()
                    .map(|n| n.to_string_lossy().to_string())
                    .unwrap_or_else(|| path.to_string()),
                channel: "file".to_string(),
                message: line.chars().take(500).collect(),
                event_id: None,
            }
        })
        .filter(|e| {
            since.map(|s| e.timestamp > s).unwrap_or(true)
        })
        .collect();

    Ok(entries)
}

// ── Non-Windows stub ─────────────────────────────────────────────────────────

#[cfg(not(target_os = "windows"))]
fn collect_windows_event_logs(
    _channel: &str,
    _max: usize,
    _since: Option<DateTime<Utc>>,
    _min_level: &LogLevel,
) -> Result<Vec<LogEntry>, String> {
    // On non-Windows platforms, Windows Event Log is not available.
    Ok(vec![])
}
