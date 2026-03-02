import { serve } from "https://deno.land/std@0.168.0/http/server.ts";
import { createClient } from "https://esm.sh/@supabase/supabase-js@2";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers":
    "authorization, x-client-info, apikey, content-type, x-supabase-client-platform, x-supabase-client-platform-version, x-supabase-client-runtime, x-supabase-client-runtime-version",
};

interface DockerContainer {
  id: string;
  name: string;
  image: string;
  state: string;
  status: string;
  ports: { external: number; internal: number; protocol: string }[];
  networks: string[];
  ip: string;
}

interface DockerNetwork {
  name: string;
  driver: string;
  subnet: string;
  gateway: string;
  scope: string;
  containers: string[];
}

interface DockerHostPayload {
  hostname: string;
  ip: string;
  docker_version: string;
  containers: DockerContainer[];
  networks: DockerNetwork[];
  volumes_orphaned: number;
}

serve(async (req: Request) => {
  if (req.method === "OPTIONS") {
    return new Response(null, { headers: corsHeaders });
  }

  try {
    const supabaseUrl = Deno.env.get("SUPABASE_URL")!;
    const supabaseKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
    const supabase = createClient(supabaseUrl, supabaseKey);

    // --- GET: Return current topology for a host or all hosts ---
    if (req.method === "GET") {
      const url = new URL(req.url);
      const hostname = url.searchParams.get("hostname");

      let query = supabase.from("docker_hosts").select("*");
      if (hostname) query = query.eq("hostname", hostname);
      const { data: hosts, error: hErr } = await query;
      if (hErr) throw hErr;

      // Fetch containers and networks for these hosts
      const hostIds = (hosts || []).map((h: any) => h.id);

      const [{ data: containers }, { data: networks }] = await Promise.all([
        supabase.from("docker_containers").select("*").in("host_id", hostIds),
        supabase.from("docker_networks").select("*").in("host_id", hostIds),
      ]);

      // Detect port conflicts
      const portMap = new Map<string, string[]>();
      (containers || []).forEach((c: any) => {
        const ports = c.ports as any[] || [];
        ports.forEach((p: any) => {
          const key = `${c.host_id}:${p.external}/${p.protocol}`;
          if (!portMap.has(key)) portMap.set(key, []);
          portMap.get(key)!.push(c.name);
        });
      });

      const conflicts = Array.from(portMap.entries())
        .filter(([, names]) => names.length > 1)
        .map(([key, names]) => ({
          port: key,
          containers: names,
          message: `Port Conflict: Port ${key.split(":")[1]} is bound to multiple containers (${names.join(", ")})`,
        }));

      return new Response(
        JSON.stringify({
          hosts: hosts || [],
          containers: containers || [],
          networks: networks || [],
          conflicts,
        }),
        { headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    // --- POST: Receive topology push from Docker agent ---
    if (req.method === "POST") {
      const payload: DockerHostPayload = await req.json();

      if (!payload.hostname) {
        return new Response(
          JSON.stringify({ error: "hostname is required" }),
          { status: 400, headers: { ...corsHeaders, "Content-Type": "application/json" } }
        );
      }

      // Upsert host
      const { data: host, error: hostErr } = await supabase
        .from("docker_hosts")
        .upsert(
          {
            hostname: payload.hostname,
            ip: payload.ip,
            docker_version: payload.docker_version,
            status: "online",
            last_synced: new Date().toISOString(),
            active_containers: payload.containers.filter((c) => c.state === "running").length,
            orphaned_volumes: payload.volumes_orphaned || 0,
          },
          { onConflict: "hostname" }
        )
        .select()
        .single();

      if (hostErr) throw hostErr;

      const hostId = host.id;

      // Clear old containers & networks for this host
      await Promise.all([
        supabase.from("docker_containers").delete().eq("host_id", hostId),
        supabase.from("docker_networks").delete().eq("host_id", hostId),
      ]);

      // Insert containers
      if (payload.containers.length > 0) {
        const containerRows = payload.containers.map((c) => ({
          host_id: hostId,
          container_id: c.id,
          name: c.name,
          image: c.image,
          state: c.state,
          status_text: c.status,
          ports: c.ports,
          networks: c.networks,
          internal_ip: c.ip,
        }));
        const { error: cErr } = await supabase.from("docker_containers").insert(containerRows);
        if (cErr) throw cErr;
      }

      // Insert networks
      if (payload.networks.length > 0) {
        const networkRows = payload.networks.map((n) => ({
          host_id: hostId,
          name: n.name,
          driver: n.driver,
          subnet: n.subnet,
          gateway: n.gateway,
          scope: n.scope,
          connected_containers: n.containers,
        }));
        const { error: nErr } = await supabase.from("docker_networks").insert(networkRows);
        if (nErr) throw nErr;
      }

      return new Response(
        JSON.stringify({ success: true, host_id: hostId, synced_at: host.last_synced }),
        { headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    return new Response(
      JSON.stringify({ error: "Method not allowed" }),
      { status: 405, headers: { ...corsHeaders, "Content-Type": "application/json" } }
    );
  } catch (error: any) {
    console.error("docker-sync error:", error);
    return new Response(
      JSON.stringify({ error: error.message }),
      { status: 500, headers: { ...corsHeaders, "Content-Type": "application/json" } }
    );
  }
});
