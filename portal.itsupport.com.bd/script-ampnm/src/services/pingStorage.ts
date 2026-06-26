import { supabase } from '@/integrations/supabase/client'

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
  try {
    const { data, error } = await supabase
      .from('ping_results')
      .insert([result])
      .select()

    if (error) {
      console.error('Error storing ping result:', error)
      return null
    }

    return data[0]
  } catch (error) {
    console.error('Failed to store ping result:', error)
    return null
  }
}

export const getPingHistory = async (host?: string, limit: number = 50) => {
  try {
    let query = supabase
      .from('ping_results')
      .select('*')
      .order('created_at', { ascending: false })
      .limit(limit)

    if (host) {
      query = query.eq('host', host)
    }

    const { data, error } = await query

    if (error) {
      console.error('Error fetching ping history:', error)
      return []
    }

    return data
  } catch (error) {
    console.error('Failed to fetch ping history:', error)
    return []
  }
}

export const getPingStats = async (host: string, hours: number = 24) => {
  try {
    const { data, error } = await supabase
      .from('ping_results')
      .select('*')
      .eq('host', host)
      .gte('created_at', new Date(Date.now() - hours * 60 * 60 * 1000).toISOString())
      .order('created_at', { ascending: true })

    if (error) {
      console.error('Error fetching ping stats:', error)
      return []
    }

    return data
  } catch (error) {
    console.error('Failed to fetch ping stats:', error)
    return []
  }
}