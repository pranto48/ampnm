import { createClient } from 'https://esm.sh/@supabase/supabase-js@2'

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
}

interface PingRequest {
  targetId: string
  host: string
}

Deno.serve(async (req) => {
  // Handle CORS preflight requests
  if (req.method === 'OPTIONS') {
    return new Response(null, { headers: corsHeaders })
  }

  try {
    const { targetId, host }: PingRequest = await req.json()
    
    console.log(`Pinging target: ${host} (ID: ${targetId})`)

    if (!targetId || !host) {
      console.error('Missing required fields: targetId or host')
      return new Response(
        JSON.stringify({ error: 'Missing targetId or host' }),
        { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    // Initialize Supabase client
    const supabaseUrl = Deno.env.get('SUPABASE_URL')!
    const supabaseKey = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!
    const supabase = createClient(supabaseUrl, supabaseKey)

    // Perform HTTP connectivity check (simulating ping since true ICMP isn't available)
    let status: 'online' | 'offline' | 'unknown' = 'unknown'
    let responseTimeMs: number | null = null

    const startTime = Date.now()
    
    try {
      // Normalize the host - add protocol if missing
      let url = host
      if (!url.startsWith('http://') && !url.startsWith('https://')) {
        url = `https://${host}`
      }

      console.log(`Attempting to fetch: ${url}`)
      
      const controller = new AbortController()
      const timeoutId = setTimeout(() => controller.abort(), 10000) // 10 second timeout

      const response = await fetch(url, {
        method: 'HEAD',
        signal: controller.signal,
      })

      clearTimeout(timeoutId)
      
      responseTimeMs = Date.now() - startTime
      status = response.ok || response.status < 500 ? 'online' : 'offline'
      
      console.log(`Response status: ${response.status}, time: ${responseTimeMs}ms`)
    } catch (fetchError: unknown) {
      responseTimeMs = Date.now() - startTime
      status = 'offline'
      const errorMessage = fetchError instanceof Error ? fetchError.message : 'Unknown error'
      console.log(`Fetch failed: ${errorMessage}, marking as offline`)
    }

    // Store the result in the database
    const { data, error } = await supabase
      .from('ping_results')
      .insert({
        target_id: targetId,
        status,
        response_time_ms: responseTimeMs,
      })
      .select()
      .single()

    if (error) {
      console.error('Database insert error:', error)
      return new Response(
        JSON.stringify({ error: 'Failed to store ping result', details: error.message }),
        { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    console.log(`Ping result stored: ${status} in ${responseTimeMs}ms`)

    return new Response(
      JSON.stringify({
        success: true,
        result: {
          status,
          responseTimeMs,
          checkedAt: data.checked_at,
        }
      }),
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
