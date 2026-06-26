import { getDockerBaseUrl } from '../utils/url';

const LOCAL_API_URL = `${getDockerBaseUrl()}/api.php`;

interface ApiResponse<T> {
  success?: boolean;
  error?: string;
  message?: string;
  data?: T;
  // For specific endpoints that return arrays directly
  devices?: T[];
  edges?: T[];
  map?: any; // For public map data
}

export interface NetworkDevice {
  id?: string;
  user_id?: string;
  name: string;
  ip_address?: string; // Changed to optional
  position_x?: number; // Changed to optional
  position_y?: number; // Changed to optional
  icon: string; // Changed to type
  status?: 'online' | 'offline' | 'unknown' | 'warning' | 'critical';
  ping_interval?: number;
  icon_size?: number;
  name_text_size?: number;
  monitor_method?: 'ping' | 'port';
  last_ping?: string | null; // Corresponds to last_seen
  last_ping_result?: boolean | null; // Not directly stored, derived from status
  check_port?: number;
  description?: string;
  warning_latency_threshold?: number;
  warning_packetloss_threshold?: number;
  critical_latency_threshold?: number;
  critical_packetloss_threshold?: number;
  show_live_ping?: boolean;
  map_id?: string;
}

export interface NetworkEdge {
  id?: string;
  source_id: string; // Changed to source_id
  target_id: string; // Changed to target_id
  connection_type: string;
  map_id?: string; // Added map_id
}

export interface MapData {
  devices: Omit<NetworkDevice, 'user_id' | 'status'>[];
  edges: Omit<NetworkEdge, 'id'>[];
}

const callApi = async <T>(action: string, method: 'GET' | 'POST', body?: any): Promise<T> => {
  const options: RequestInit = {
    method: method,
    headers: {
      'Content-Type': 'application/json',
    },
  };
  if (body) {
    options.body = JSON.stringify(body);
  }

  const response = await fetch(`${LOCAL_API_URL}?action=${action}`, options);
  const result: ApiResponse<T> = await response.json();

  if (!response.ok || result.error) {
    throw new Error(result.error || `API call failed with status ${response.status}`);
  }
  return result as T; // Cast to T, assuming the data field or direct array is T
};

export const getDevices = async (): Promise<NetworkDevice[]> => {
  const result = await callApi<{ devices: NetworkDevice[] }>('get_devices', 'GET');
  return result.devices || [];
};

export const addDevice = async (device: Omit<NetworkDevice, 'user_id' | 'status' | 'last_ping' | 'last_ping_result'>): Promise<NetworkDevice> => {
  // PHP API expects 'ip' and 'type' for icon, and 'x', 'y' for position
  const payload = {
    name: device.name,
    ip: device.ip_address,
    check_port: device.check_port,
    monitor_method: device.monitor_method,
    type: device.icon, // PHP uses 'type' for icon
    description: device.description,
    map_id: device.map_id,
    x: device.position_x,
    y: device.position_y,
    ping_interval: device.ping_interval,
    icon_size: device.icon_size,
    name_text_size: device.name_text_size,
    icon_url: device.icon_url,
    warning_latency_threshold: device.warning_latency_threshold,
    warning_packetloss_threshold: device.warning_packetloss_threshold,
    critical_latency_threshold: device.critical_latency_threshold,
    critical_packetloss_threshold: device.critical_packetloss_threshold,
    show_live_ping: device.show_live_ping,
  };
  const result = await callApi<NetworkDevice>('create_device', 'POST', payload);
  return result;
};

export const updateDevice = async (id: string, updates: Partial<NetworkDevice>): Promise<NetworkDevice> => {
  const payload: { [key: string]: any } = {};
  if (updates.name !== undefined) payload.name = updates.name;
  if (updates.ip_address !== undefined) payload.ip = updates.ip_address;
  if (updates.check_port !== undefined) payload.check_port = updates.check_port;
  if (updates.monitor_method !== undefined) payload.monitor_method = updates.monitor_method;
  if (updates.icon !== undefined) payload.type = updates.icon; // PHP uses 'type' for icon
  if (updates.description !== undefined) payload.description = updates.description;
  if (updates.map_id !== undefined) payload.map_id = updates.map_id;
  if (updates.position_x !== undefined) payload.x = updates.position_x;
  if (updates.position_y !== undefined) payload.y = updates.position_y;
  if (updates.ping_interval !== undefined) payload.ping_interval = updates.ping_interval;
  if (updates.icon_size !== undefined) payload.icon_size = updates.icon_size;
  if (updates.name_text_size !== undefined) payload.name_text_size = updates.name_text_size;
  if (updates.icon_url !== undefined) payload.icon_url = updates.icon_url;
  if (updates.warning_latency_threshold !== undefined) payload.warning_latency_threshold = updates.warning_latency_threshold;
  if (updates.warning_packetloss_threshold !== undefined) payload.warning_packetloss_threshold = updates.warning_packetloss_threshold;
  if (updates.critical_latency_threshold !== undefined) payload.critical_latency_threshold = updates.critical_latency_threshold;
  if (updates.critical_packetloss_threshold !== undefined) payload.critical_packetloss_threshold = updates.critical_packetloss_threshold;
  if (updates.show_live_ping !== undefined) payload.show_live_ping = updates.show_live_ping;
  if (updates.status !== undefined) payload.status = updates.status; // For status updates

  const result = await callApi<NetworkDevice>('update_device', 'POST', { id, updates: payload });
  return result;
};

export const updateDeviceStatusByIp = async (ip_address: string, status: 'online' | 'offline'): Promise<NetworkDevice> => {
  const result = await callApi<NetworkDevice>('update_device_status_by_ip', 'POST', { ip_address, status });
  return result;
};

export const deleteDevice = async (id: string): Promise<{ success: boolean }> => {
  const result = await callApi<{ success: boolean }>('delete_device', 'POST', { id });
  return result;
};

export const getEdges = async (): Promise<NetworkEdge[]> => {
  const result = await callApi<{ edges: NetworkEdge[] }>('get_edges', 'GET');
  return result.edges || [];
};

export const addEdgeToDB = async (edge: { source: string; target: string; map_id: string }): Promise<NetworkEdge> => {
  const result = await callApi<NetworkEdge>('create_edge', 'POST', { source_id: edge.source, target_id: edge.target, map_id: edge.map_id, connection_type: 'cat5' });
  return result;
};

export const updateEdgeInDB = async (id: string, updates: { connection_type: string }): Promise<NetworkEdge> => {
  const result = await callApi<NetworkEdge>('update_edge', 'POST', { id, connection_type: updates.connection_type });
  return result;
};

export const deleteEdgeFromDB = async (edgeId: string): Promise<{ success: boolean }> => {
  const result = await callApi<{ success: boolean }>('delete_edge', 'POST', { id: edgeId });
  return result;
};

export const importMap = async (mapData: MapData, map_id: string): Promise<{ success: boolean }> => {
  // The PHP API expects 'devices' and 'edges' directly in the body
  const payload = {
    map_id: map_id, // Pass the current map_id for the import
    devices: mapData.devices.map(d => ({
      ...d,
      ip: d.ip_address, // Map ip_address to ip for PHP
      type: d.icon, // Map icon to type for PHP
    })),
    edges: mapData.edges.map(e => ({
      source_id: e.source, // Map source to source_id for PHP
      target_id: e.target, // Map target to target_id for PHP
      connection_type: e.connection_type,
    })),
  };
  const result = await callApi<{ success: boolean }>('import_map', 'POST', payload);
  return result;
};

export const getPublicMapData = async (mapId: string): Promise<{ map: any; devices: NetworkDevice[]; edges: NetworkEdge[] }> => {
  const result = await callApi<{ map: any; devices: NetworkDevice[]; edges: NetworkEdge[] }>('get_public_map_data', 'GET', undefined, { map_id: mapId });
  return {
    map: result.map,
    devices: result.devices.map(d => ({
      ...d,
      ip_address: d.ip, // Map ip to ip_address for React component
      icon: d.type, // Map type to icon for React component
      position_x: d.x,
      position_y: d.y,
      last_ping: d.last_seen,
    })),
    edges: result.edges.map(e => ({
      ...e,
      source: e.source_id, // Map source_id to source for React component
      target: e.target_id, // Map target_id to target for React component
    })),
  };
};

// Real-time subscription is removed as it's Supabase-specific.
// You would need a different real-time mechanism for PHP/MySQL if desired.
export const subscribeToDeviceChanges = (callback: (payload: any) => void) => {
  console.warn("Real-time subscriptions are not available when using PHP API. Please implement a polling mechanism or a different real-time solution if needed.");
  // Return a dummy object that mimics a channel to prevent errors
  return {
    on: () => ({ subscribe: () => {} }),
    subscribe: () => {},
    removeChannel: () => {},
  };
};