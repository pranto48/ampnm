export interface Organization {
  id: string;
  name: string;
  createdAt: string | Date;
  ownerUid: string;
}

export interface UserProfile {
  uid: string;
  name: string;
  email: string;
  role: "owner" | "admin" | "member";
  orgId: string;
  createdAt: string | Date;
}

export interface License {
  id: string;
  key: string;
  orgId: string;
  status: "active" | "revoked" | "expired";
  nodeId?: string;
  createdAt: string | Date;
  expiresAt: string | Date;
}

export interface NetworkMetric {
  cpuUsage: number;
  memoryUsage: number;
  bandwidthIn: number;
  bandwidthOut: number;
}

export interface Device {
  id: string;
  name: string;
  ip: string;
  status: "online" | "offline" | "warning";
  lastActive: string;
  responseTime: number;
}
