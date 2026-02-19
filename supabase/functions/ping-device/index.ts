import { createClient } from 'https://esm.sh/@supabase/supabase-js@2'

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type, x-supabase-client-platform, x-supabase-client-platform-version, x-supabase-client-runtime, x-supabase-client-runtime-version',
}

interface PingDeviceRequest {
  device_id?: string
  device_ids?: string[]  // for ping-all
}

async function pingHost(host: string, port?: number | null): Promise<{ success: boolean; latencyMs: number }> {
  const startTime = Date.now()

  try {
    let url: string
    if (port) {
      // TCP port check via HTTP
      url = `http://${host}:${port}`
    } else {
      // Default HTTP check
      if (!host.startsWith('http://') && !host.startsWith('https://')) {
        url = `http://${host}`
      } else {
        url = host
      }
    }

    const controller = new AbortController()
    const timeoutId = setTimeout(() => controller.abort(), 5000)

    const response = await fetch(url, {
      method: 'HEAD',
      signal: controller.signal,
    })

    clearTimeout(timeoutId)
    const latencyMs = Date.now() - startTime
    // Consider any response (even 4xx) as "online" - the host responded
    return { success: true, latencyMs }
  } catch (_error) {
    const latencyMs = Date.now() - startTime
    return { success: false, latencyMs }
  }
}

function determineStatus(
  success: boolean,
  latencyMs: number,
  warningLatency: number | null,
  criticalLatency: number | null
): string {
  if (!success) return 'offline'
  if (criticalLatency && latencyMs >= criticalLatency) return 'critical'
  if (warningLatency && latencyMs >= warningLatency) return 'warning'
  return 'online'
}

Deno.serve(async (req) => {
  if (req.method === 'OPTIONS') {
    return new Response(null, { headers: corsHeaders })
  }

  try {
    const supabaseUrl = Deno.env.get('SUPABASE_URL')!
    const supabaseKey = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!
    const supabase = createClient(supabaseUrl, supabaseKey)

    const body: PingDeviceRequest = await req.json()
    const deviceIds = body.device_ids || (body.device_id ? [body.device_id] : [])

    if (deviceIds.length === 0) {
      return new Response(
        JSON.stringify({ error: 'Missing device_id or device_ids' }),
        { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    // Fetch devices
    const { data: devices, error: fetchError } = await supabase
      .from('devices')
      .select('id, ip_address, check_port, monitor_method, warning_latency_threshold, critical_latency_threshold, warning_packetloss_threshold, critical_packetloss_threshold, status')
      .in('id', deviceIds)

    if (fetchError || !devices) {
      return new Response(
        JSON.stringify({ error: 'Failed to fetch devices', details: fetchError?.message }),
        { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    const results: Array<{ device_id: string; status: string; latency_ms: number; success: boolean }> = []

    for (const device of devices) {
      if (!device.ip_address) {
        results.push({ device_id: device.id, status: 'unknown', latency_ms: 0, success: false })
        continue
      }

      const pingResult = await pingHost(device.ip_address, device.check_port)
      const newStatus = determineStatus(
        pingResult.success,
        pingResult.latencyMs,
        device.warning_latency_threshold,
        device.critical_latency_threshold
      )

      // Update device status in DB
      const oldStatus = device.status
      await supabase
        .from('devices')
        .update({
          status: newStatus,
          last_ping: new Date().toISOString(),
          last_ping_result: pingResult.success,
          last_latency: pingResult.success ? pingResult.latencyMs : null,
        })
        .eq('id', device.id)

      // Log status change
      if (oldStatus && oldStatus !== newStatus) {
        await supabase
          .from('device_status_logs')
          .insert({
            device_id: device.id,
            old_status: oldStatus,
            new_status: newStatus,
          })
      }

      // Store ping result
      await supabase
        .from('device_ping_results')
        .insert({
          device_id: device.id,
          status: newStatus,
          latency_ms: pingResult.success ? pingResult.latencyMs : null,
          packet_loss: pingResult.success ? 0 : 100,
        })

      results.push({
        device_id: device.id,
        status: newStatus,
        latency_ms: pingResult.latencyMs,
        success: pingResult.success,
      })

      console.log(`Pinged ${device.ip_address}: ${newStatus} (${pingResult.latencyMs}ms)`)
    }

    return new Response(
      JSON.stringify({ success: true, results }),
      { headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
    )
  } catch (error: unknown) {
    console.error('Unexpected error:', error)
    const errorMessage = error instanceof Error ? error.message : 'Unknown error'
    return new Response(
      JSON.stringify({ error: 'Internal server error', details: errorMessage }),
      { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
    )
  }
})