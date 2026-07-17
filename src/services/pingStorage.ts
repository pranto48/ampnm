export interface PingStorageResult {
  host: string;
  packet_loss: number;
  avg_time: number;
  min_time: number;
  max_time: number;
  success: boolean;
  output?: string;
  created_at?: string;
}

export const storePingResult = async (result: PingStorageResult) => {
  // Supabase connection is removed. All operations are handled via PHP API.
  return null;
}

export const getPingHistory = async (host?: string, limit: number = 50) => {
  // Supabase connection is removed. All operations are handled via PHP API.
  return [];
}

export const getPingStats = async (host: string, hours: number = 24) => {
  // Supabase connection is removed. All operations are handled via PHP API.
  return [];
}