import { createClient } from "https://esm.sh/@supabase/supabase-js@2";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers":
    "authorization, x-client-info, apikey, content-type, x-agent-token",
  "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
};

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  const url = new URL(req.url);
  const path = url.pathname.replace(/^\/agent-metrics\/?/, "");

  const supabaseAdmin = createClient(
    Deno.env.get("SUPABASE_URL")!,
    Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!
  );

  try {
    // GET /health
    if (req.method === "GET" && (path === "health" || path === "health/")) {
      return new Response(
        JSON.stringify({ status: "ok", timestamp: new Date().toISOString() }),
        { headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    // POST / - submit metrics (agent token auth)
    if (req.method === "POST" && (path === "" || path === "/")) {
      const agentToken = req.headers.get("x-agent-token") || 
        req.headers.get("authorization")?.replace("Bearer ", "");

      if (!agentToken) {
        return new Response(
          JSON.stringify({ error: "Missing agent token" }),
          { status: 401, headers: { ...corsHeaders, "Content-Type": "application/json" } }
        );
      }

      const { data: tokenRow, error: tokenErr } = await supabaseAdmin
        .from("agent_tokens")
        .select("id, enabled")
        .eq("token", agentToken)
        .maybeSingle();

      if (tokenErr || !tokenRow || !tokenRow.enabled) {
        return new Response(
          JSON.stringify({ error: "Invalid or disabled token" }),
          { status: 403, headers: { ...corsHeaders, "Content-Type": "application/json" } }
        );
      }

      const body = await req.json();
      const hostname = body.hostname || body.host_name;
      if (!hostname) {
        return new Response(
          JSON.stringify({ error: "hostname is required" }),
          { status: 400, headers: { ...corsHeaders, "Content-Type": "application/json" } }
        );
      }

      // Upsert host metrics (including uptime fields)
      const { error: upsertErr } = await supabaseAdmin
        .from("host_metrics")
        .upsert(
          {
            hostname,
            ip_address: body.ip_address || null,
            cpu_usage: body.cpu ?? body.cpu_usage ?? null,
            memory_usage: body.memory_usage ?? null,
            memory_total: body.memory_total ?? null,
            disk_usage: body.disk_usage ?? null,
            disk_total: body.disk_total ?? null,
            network_in: body.network_in ?? null,
            network_out: body.network_out ?? null,
            gpu_usage: body.gpu_usage ?? null,
            uptime_seconds: body.uptime_seconds ?? null,
            boot_time: body.boot_time ?? null,
            os_version: body.os_version ?? null,
            agent_token_id: tokenRow.id,
            status: "online",
            last_seen: new Date().toISOString(),
          },
          { onConflict: "hostname" }
        );

      if (upsertErr) {
        console.error("Upsert error:", upsertErr);
        return new Response(
          JSON.stringify({ error: "Failed to store metrics" }),
          { status: 500, headers: { ...corsHeaders, "Content-Type": "application/json" } }
        );
      }

      // Insert into history
      const { error: historyErr } = await supabaseAdmin
        .from("host_metrics_history")
        .insert({
          hostname,
          cpu_usage: body.cpu ?? body.cpu_usage ?? null,
          memory_usage: body.memory_usage ?? null,
          memory_total: body.memory_total ?? null,
          disk_usage: body.disk_usage ?? null,
          disk_total: body.disk_total ?? null,
          network_in: body.network_in ?? null,
          network_out: body.network_out ?? null,
          gpu_usage: body.gpu_usage ?? null,
        });

      if (historyErr) {
        console.error("History insert error:", historyErr);
      }

      // Store processes/services if provided
      if (Array.isArray(body.processes) && body.processes.length > 0) {
        // Delete old process snapshot for this host
        await supabaseAdmin
          .from("host_processes")
          .delete()
          .eq("hostname", hostname);

        // Insert new process list (limit to top 50)
        const processRows = body.processes.slice(0, 50).map((p: any) => ({
          hostname,
          process_name: p.name || p.process_name || "unknown",
          pid: p.pid ?? null,
          cpu_percent: p.cpu ?? p.cpu_percent ?? null,
          memory_mb: p.memory_mb ?? p.mem ?? null,
          status: p.status ?? "running",
          process_type: p.type ?? p.process_type ?? "process",
        }));

        const { error: procErr } = await supabaseAdmin
          .from("host_processes")
          .insert(processRows);

        if (procErr) {
          console.error("Process insert error:", procErr);
        }
      }

      return new Response(
        JSON.stringify({ status: "ok", hostname }),
        { headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    // GET /recent
    if (req.method === "GET" && (path === "recent" || path === "recent/")) {
      const limit = Math.min(
        Math.max(parseInt(url.searchParams.get("limit") || "50"), 1),
        200
      );
      const { data, error } = await supabaseAdmin
        .from("host_metrics")
        .select("*")
        .order("last_seen", { ascending: false })
        .limit(limit);

      if (error) throw error;

      return new Response(
        JSON.stringify({ items: data }),
        { headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    // GET /processes?hostname=XXX
    if (req.method === "GET" && (path === "processes" || path === "processes/")) {
      const hostname = url.searchParams.get("hostname");
      if (!hostname) {
        return new Response(
          JSON.stringify({ error: "hostname query param required" }),
          { status: 400, headers: { ...corsHeaders, "Content-Type": "application/json" } }
        );
      }

      const { data, error } = await supabaseAdmin
        .from("host_processes")
        .select("*")
        .eq("hostname", hostname)
        .order("cpu_percent", { ascending: false })
        .limit(50);

      if (error) throw error;

      return new Response(
        JSON.stringify({ items: data }),
        { headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    return new Response(
      JSON.stringify({ error: "Not found" }),
      { status: 404, headers: { ...corsHeaders, "Content-Type": "application/json" } }
    );
  } catch (e) {
    console.error("Agent metrics error:", e);
    return new Response(
      JSON.stringify({ error: "Internal error" }),
      { status: 500, headers: { ...corsHeaders, "Content-Type": "application/json" } }
    );
  }
});
