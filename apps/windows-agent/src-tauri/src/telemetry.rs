use serde::{Deserialize, Serialize};
use sysinfo::{
    Components, CpuRefreshKind, Disk, Disks, NetworkData, Networks, RefreshKind, System, MemoryRefreshKind,
};
use std::net::IpAddr;

/// Full system telemetry snapshot collected from sysinfo
#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct Telemetry {
    pub cpu_usage_percent: f32,
    pub memory_used_mb: u64,
    pub memory_total_mb: u64,
    pub memory_usage_percent: f32,
    pub disk_used_gb: u64,
    pub disk_total_gb: u64,
    pub disk_usage_percent: f32,
    pub network_rx_bytes: u64,
    pub network_tx_bytes: u64,
    pub uptime_seconds: u64,
    pub process_count: u32,
    pub battery_percent: Option<u8>,
    pub battery_status: String,
    pub active_user: Option<String>,
    pub current_ip: Option<String>,
    pub hostname: String,
    pub os_name: String,
    pub os_version: String,
    pub architecture: String,
    pub cpu_model: String,
    pub cpu_cores: u32,
    pub total_memory_mb: u64,
    pub total_disk_gb: u64,
}

/// Collect full system telemetry
pub fn collect_telemetry(collect_username: bool) -> Telemetry {
    let mut sys = System::new_with_specifics(
        RefreshKind::nothing()
            .with_cpu(CpuRefreshKind::nothing().with_cpu_usage())
            .with_memory(MemoryRefreshKind::everything()),
    );

    // First refresh: let CPU usage accumulate (sysinfo needs two reads)
    sys.refresh_all();
    std::thread::sleep(std::time::Duration::from_millis(500));
    sys.refresh_all();

    // CPU
    let cpu_usage_percent = sys.global_cpu_usage();
    let cpu_model = sys
        .cpus()
        .first()
        .map(|c| c.brand().to_string())
        .unwrap_or_else(|| "Unknown CPU".to_string());
    let cpu_cores = sys.cpus().len() as u32;

    // Memory
    let memory_total_mb = sys.total_memory() / 1024 / 1024;
    let memory_used_mb = sys.used_memory() / 1024 / 1024;
    let memory_usage_percent = if memory_total_mb > 0 {
        (memory_used_mb as f32 / memory_total_mb as f32) * 100.0
    } else {
        0.0
    };

    // Disk (aggregate all physical disks)
    let disks = Disks::new_with_refreshed_list();
    let disk_total_bytes: u64 = disks.iter().map(|d: &Disk| d.total_space()).sum();
    let disk_available_bytes: u64 = disks.iter().map(|d: &Disk| d.available_space()).sum();
    let disk_used_bytes = disk_total_bytes.saturating_sub(disk_available_bytes);
    let disk_total_gb = disk_total_bytes / 1024 / 1024 / 1024;
    let disk_used_gb = disk_used_bytes / 1024 / 1024 / 1024;
    let disk_usage_percent = if disk_total_bytes > 0 {
        (disk_used_bytes as f32 / disk_total_bytes as f32) * 100.0
    } else {
        0.0
    };

    // Network (aggregate across all non-loopback interfaces)
    let networks = Networks::new_with_refreshed_list();
    let network_rx_bytes: u64 = networks
        .iter()
        .filter(|(name, _): &(&String, &NetworkData)| !name.to_lowercase().contains("loopback"))
        .map(|(_, data)| data.total_received())
        .sum();
    let network_tx_bytes: u64 = networks
        .iter()
        .filter(|(name, _): &(&String, &NetworkData)| !name.to_lowercase().contains("loopback"))
        .map(|(_, data)| data.total_transmitted())
        .sum();

    // Uptime & process count
    let uptime_seconds = System::uptime();
    let process_count = sys.processes().len() as u32;

    // Battery (best-effort via sysinfo Components — may not detect on desktops)
    let (battery_percent, battery_status) = get_battery_info();

    // Active user (respects privacy setting)
    let active_user = if collect_username {
        get_active_user()
    } else {
        None
    };

    // Local IP
    let current_ip = get_local_ip();

    // OS info
    let hostname = System::host_name().unwrap_or_else(|| "Unknown".to_string());
    let os_name = System::name().unwrap_or_else(|| "Windows".to_string());
    let os_version = System::os_version().unwrap_or_else(|| "Unknown".to_string());
    let architecture = std::env::consts::ARCH.to_string();

    Telemetry {
        cpu_usage_percent,
        memory_used_mb,
        memory_total_mb,
        memory_usage_percent,
        disk_used_gb,
        disk_total_gb,
        disk_usage_percent,
        network_rx_bytes,
        network_tx_bytes,
        uptime_seconds,
        process_count,
        battery_percent,
        battery_status,
        active_user,
        current_ip,
        hostname,
        os_name,
        os_version,
        architecture,
        cpu_model,
        cpu_cores,
        total_memory_mb: memory_total_mb,
        total_disk_gb: disk_total_gb,
    }
}

fn get_battery_info() -> (Option<u8>, String) {
    // Try reading from sysinfo components (works on some systems)
    let components = Components::new_with_refreshed_list();
    for comp in components.iter() {
        let label = comp.label().to_lowercase();
        if label.contains("battery") {
            // Rough estimate from temperature if that's all we have
            return (None, "present".to_string());
        }
    }
    (None, "unknown".to_string())
}

fn get_active_user() -> Option<String> {
    // Try environment variables first
    if let Ok(user) = std::env::var("USERNAME") {
        if !user.is_empty() {
            return Some(user);
        }
    }
    if let Ok(user) = std::env::var("USER") {
        if !user.is_empty() {
            return Some(user);
        }
    }
    None
}

fn get_local_ip() -> Option<String> {
    // Get primary local IP by attempting a UDP connect (doesn't actually send packets)
    use std::net::UdpSocket;
    let socket = UdpSocket::bind("0.0.0.0:0").ok()?;
    socket.connect("8.8.8.8:80").ok()?;
    let addr = socket.local_addr().ok()?;
    Some(addr.ip().to_string())
}

/// Get MAC address for the primary network interface
pub fn get_mac_address() -> Option<String> {
    // Simple platform approach using the hostname to find the primary interface
    None // Full implementation requires platform-specific code; agent can send this from the frontend
}
