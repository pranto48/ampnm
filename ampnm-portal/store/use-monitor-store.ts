import { create } from "zustand";
import { Device, License, NetworkMetric, UserProfile } from "@/types";

interface MonitorState {
  sidebarOpen: boolean;
  setSidebarOpen: (open: boolean) => void;
  toggleSidebar: () => void;

  devices: Device[];
  licenses: License[];
  metrics: NetworkMetric;
  profile: UserProfile | null;

  setProfile: (profile: UserProfile | null) => void;
  addLicense: (license: License) => void;
  revokeLicense: (id: string) => void;
  setMetrics: (metrics: NetworkMetric) => void;
}

export const useMonitorStore = create<MonitorState>((set) => ({
  sidebarOpen: true,
  setSidebarOpen: (open) => set({ sidebarOpen: open }),
  toggleSidebar: () => set((state) => ({ sidebarOpen: !state.sidebarOpen })),

  devices: [
    { id: "1", name: "Core Backbone Switch 01", ip: "10.0.0.1", status: "online", lastActive: "Just now", responseTime: 2 },
    { id: "2", name: "Primary Database Cluster", ip: "10.0.2.10", status: "warning", lastActive: "2m ago", responseTime: 124 },
    { id: "3", name: "Frontend Web Server Pool", ip: "10.0.1.5", status: "online", lastActive: "Just now", responseTime: 14 },
    { id: "4", name: "HQ VPN Gateway Tunnel", ip: "10.10.0.1", status: "offline", lastActive: "15m ago", responseTime: 0 },
    { id: "5", name: "Border Edge Gateway Router", ip: "192.168.1.1", status: "online", lastActive: "Just now", responseTime: 4 },
  ],
  licenses: [
    { id: "l1", key: "AMPNM-DEVC-8F2B-9A4E-4321", orgId: "org1", status: "active", nodeId: "node-srv-db", createdAt: "2026-01-10", expiresAt: "2027-01-10" },
    { id: "l2", key: "AMPNM-DEVC-3C5D-8E1A-7654", orgId: "org1", status: "active", nodeId: "node-srv-web", createdAt: "2026-03-15", expiresAt: "2027-03-15" },
    { id: "l3", key: "AMPNM-DEVC-5D4E-1C2A-9876", orgId: "org1", status: "expired", nodeId: "node-srv-vpn", createdAt: "2025-05-01", expiresAt: "2026-05-01" },
  ],
  metrics: {
    cpuUsage: 48.7,
    memoryUsage: 64.2,
    bandwidthIn: 124.5,
    bandwidthOut: 98.2,
  },
  profile: {
    uid: "u1",
    name: "Sayed Arif",
    email: "arif@itsupport.com.bd",
    role: "owner",
    orgId: "org1",
    createdAt: "2026-01-01",
  },

  setProfile: (profile) => set({ profile }),
  addLicense: (license) => set((state) => ({ licenses: [license, ...state.licenses] })),
  revokeLicense: (id) => set((state) => ({
    licenses: state.licenses.map((lic) =>
      lic.id === id ? { ...lic, status: "revoked" } : lic
    )
  })),
  setMetrics: (metrics) => set({ metrics }),
}));
