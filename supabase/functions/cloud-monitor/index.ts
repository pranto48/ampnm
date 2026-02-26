import { createClient } from 'npm:@supabase/supabase-js@2'

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type, x-docker-api-key, x-supabase-client-platform, x-supabase-client-platform-version, x-supabase-client-runtime, x-supabase-client-runtime-version',
}

Deno.serve(async (req) => {
  if (req.method === 'OPTIONS') {
    return new Response(null, { headers: corsHeaders })
  }

  try {
    const supabaseUrl = Deno.env.get('SUPABASE_URL')!
    const supabaseKey = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!
    const supabase = createClient(supabaseUrl, supabaseKey)

    const url = new URL(req.url)
    const action = url.searchParams.get('action')
    const mapId = url.searchParams.get('map_id')

    if (!action) {
      return new Response(
        JSON.stringify({ error: 'Missing action parameter' }),
        { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    if (action === 'pull_devices') {
      let query = supabase.from('devices').select('*').order('name')
      if (mapId) query = query.eq('map_id', mapId)
      const { data, error } = await query
      if (error) throw error

      const devices = (data ?? []).map(d => ({
        id: d.id, name: d.name, ip: d.ip_address, type: d.type, subchoice: d.subchoice,
        status: d.status, x: d.x, y: d.y, icon_url: d.icon_url, icon_size: d.icon_size,
        name_text_size: d.name_text_size, ping_interval: d.ping_interval, check_port: d.check_port,
        monitor_method: d.monitor_method, description: d.description, map_id: d.map_id,
        show_live_ping: d.show_live_ping, warning_latency_threshold: d.warning_latency_threshold,
        warning_packetloss_threshold: d.warning_packetloss_threshold,
        critical_latency_threshold: d.critical_latency_threshold,
        critical_packetloss_threshold: d.critical_packetloss_threshold,
        last_seen: d.last_ping, last_latency: d.last_latency,
      }))

      return new Response(
        JSON.stringify({ success: true, devices }),
        { headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    if (action === 'pull_maps') {
      const { data, error } = await supabase.from('maps').select('*').order('name')
      if (error) throw error
      return new Response(
        JSON.stringify({ success: true, maps: data }),
        { headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    if (action === 'pull_edges') {
      let query = supabase.from('device_edges').select('*')
      if (mapId) query = query.eq('map_id', mapId)
      const { data, error } = await query
      if (error) throw error
      return new Response(
        JSON.stringify({ success: true, edges: data }),
        { headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    if (action === 'pull_settings') {
      const { data, error } = await supabase.from('app_settings').select('*')
      if (error) throw error
      const settings: Record<string, string | null> = {}
      ;(data ?? []).forEach(s => { settings[s.key] = s.value })
      return new Response(
        JSON.stringify({ success: true, settings }),
        { headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    if (action === 'pull_pending_pings') {
      const { data: devices, error } = await supabase
        .from('devices')
        .select('id, name, ip_address, check_port, monitor_method, ping_interval, last_ping, warning_latency_threshold, warning_packetloss_threshold, critical_latency_threshold, critical_packetloss_threshold, status')
        .not('ip_address', 'is', null)
        .neq('monitor_method', 'none')

      if (error) throw error

      const now = Date.now()
      const pending = (devices ?? []).filter(d => {
        if (!d.ip_address) return false
        const intervalMs = Math.max((d.ping_interval ?? 300), 10) * 1000
        if (!d.last_ping) return true
        const lastPingTime = new Date(d.last_ping).getTime()
        return (now - lastPingTime) >= intervalMs
      }).map(d => ({
        id: d.id, name: d.name, ip: d.ip_address, check_port: d.check_port,
        monitor_method: d.monitor_method ?? 'ping', ping_interval: d.ping_interval ?? 300,
        warning_latency_threshold: d.warning_latency_threshold,
        warning_packetloss_threshold: d.warning_packetloss_threshold,
        critical_latency_threshold: d.critical_latency_threshold,
        critical_packetloss_threshold: d.critical_packetloss_threshold,
        status: d.status,
      }))

      return new Response(
        JSON.stringify({ success: true, devices: pending }),
        { headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    if (action === 'push_status' && req.method === 'POST') {
      const body = await req.json()
      const updates: Array<{ device_id: string; status: string; latency_ms?: number; packet_loss?: number }> = body.updates || []

      for (const u of updates) {
        const { data: device } = await supabase.from('devices').select('status').eq('id', u.device_id).single()
        const oldStatus = device?.status

        await supabase.from('devices').update({
          status: u.status,
          last_ping: new Date().toISOString(),
          last_ping_result: u.status === 'online' || u.status === 'warning',
          last_latency: u.latency_ms ?? null,
        }).eq('id', u.device_id)

        if (oldStatus && oldStatus !== u.status) {
          await supabase.from('device_status_logs').insert({
            device_id: u.device_id, old_status: oldStatus, new_status: u.status,
          })
        }
      }

      return new Response(
        JSON.stringify({ success: true, updated: updates.length }),
        { headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    if (action === 'push_ping_results' && req.method === 'POST') {
      const body = await req.json()
      const results: Array<{ device_id: string; status: string; latency_ms?: number; packet_loss?: number }> = body.results || []

      const inserts = results.map(r => ({
        device_id: r.device_id, status: r.status,
        latency_ms: r.latency_ms ?? null, packet_loss: r.packet_loss ?? null,
      }))

      const { error } = await supabase.from('device_ping_results').insert(inserts)
      if (error) throw error

      return new Response(
        JSON.stringify({ success: true, stored: inserts.length }),
        { headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    return new Response(
      JSON.stringify({ error: `Unknown action: ${action}` }),
      { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
    )

  } catch (error: unknown) {
    console.error('Cloud monitor error:', error)
    const msg = error instanceof Error ? error.message : 'Unknown error'
    return new Response(
      JSON.stringify({ error: msg }),
      { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
    )
  }
})
