import { create } from "zustand";
import { Device, Alert, NetworkMetric, UserProfile } from "@/types";

interface MonitorState {
  // UI State
  sidebarOpen: boolean;
  setSidebarOpen: (open: boolean) => void;
  toggleSidebar: () => void;

  // Data State
  devices: Device[];
  alerts: Alert[];
  metrics: NetworkMetric;
  user: UserProfile;

  // Actions
  setDevices: (devices: Device[]) => void;
  acknowledgeAlert: (id: string) => void;
  setMetrics: (metrics: NetworkMetric) => void;
}

export const useMonitorStore = create<MonitorState>((set) => ({
  // UI State Defaults
  sidebarOpen: true,
  setSidebarOpen: (open) => set({ sidebarOpen: open }),
  toggleSidebar: () => set((state) => ({ sidebarOpen: !state.sidebarOpen })),

  // Seeded Monitoring Mock Data
  devices: [
    { id: "1", name: "Core Backbone Switch 01", ip: "10.0.0.1", status: "online", lastActive: "Just now", responseTime: 2 },
    { id: "2", name: "Primary Database Cluster", ip: "10.0.2.10", status: "warning", lastActive: "2m ago", responseTime: 124 },
    { id: "3", name: "Frontend Web Server Pool", ip: "10.0.1.5", status: "online", lastActive: "Just now", responseTime: 14 },
    { id: "4", name: "HQ VPN Gateway Tunnel", ip: "10.10.0.1", status: "offline", lastActive: "15m ago", responseTime: 0 },
    { id: "5", name: "Border Edge Gateway Router", ip: "192.168.1.1", status: "online", lastActive: "Just now", responseTime: 4 },
  ],
  alerts: [
    { id: "a1", severity: "critical", message: "HQ VPN Gateway Tunnel IPSEC link down", timestamp: "15m ago", deviceId: "4", acknowledged: false },
    { id: "a2", severity: "warning", message: "High latency on Primary Database Cluster connection", timestamp: "2m ago", deviceId: "2", acknowledged: false },
    { id: "a3", severity: "info", message: "Border Edge Router routing tables re-sync completed", timestamp: "1h ago", deviceId: "5", acknowledged: true },
  ],
  metrics: {
    cpuUsage: 48.7,
    memoryUsage: 64.2,
    bandwidthIn: 124.5,
    bandwidthOut: 98.2,
  },
  user: {
    name: "Sayed Arif",
    email: "arif@itsupport.com.bd",
    role: "Principal Operations Lead",
  },

  // Actions
  setDevices: (devices) => set({ devices }),
  acknowledgeAlert: (id) => set((state) => ({
    alerts: state.alerts.map((alert) =>
      alert.id === id ? { ...alert, acknowledged: true } : alert
    ),
  })),
  setMetrics: (metrics) => set({ metrics }),
}));
