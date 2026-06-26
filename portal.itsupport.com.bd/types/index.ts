export interface Device {
  id: string;
  name: string;
  ip: string;
  status: "online" | "offline" | "warning";
  lastActive: string;
  responseTime: number; // in ms
}

export interface Alert {
  id: string;
  severity: "critical" | "warning" | "info";
  message: string;
  timestamp: string;
  deviceId?: string;
  acknowledged: boolean;
}

export interface NetworkMetric {
  cpuUsage: number;
  memoryUsage: number;
  bandwidthIn: number;  // in Mbps
  bandwidthOut: number; // in Mbps
}

export interface UserProfile {
  name: string;
  email: string;
  role: string;
}
