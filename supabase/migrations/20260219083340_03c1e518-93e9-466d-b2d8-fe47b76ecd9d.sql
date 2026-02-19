
-- Agent tokens table for authenticating Windows agents
CREATE TABLE public.agent_tokens (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL,
  token text NOT NULL UNIQUE,
  created_by uuid NOT NULL,
  enabled boolean NOT NULL DEFAULT true,
  created_at timestamptz NOT NULL DEFAULT now()
);

ALTER TABLE public.agent_tokens ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can manage agent tokens"
  ON public.agent_tokens FOR ALL
  USING (has_role(auth.uid(), 'admin'::app_role));

CREATE POLICY "Authenticated users can view tokens"
  ON public.agent_tokens FOR SELECT
  USING (true);

-- Host metrics table for agent-submitted data
CREATE TABLE public.host_metrics (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  hostname text NOT NULL,
  ip_address text,
  cpu_usage numeric,
  memory_usage numeric,
  memory_total numeric,
  disk_usage numeric,
  disk_total numeric,
  network_in numeric,
  network_out numeric,
  gpu_usage numeric,
  agent_token_id uuid REFERENCES public.agent_tokens(id) ON DELETE SET NULL,
  status text NOT NULL DEFAULT 'online',
  first_seen timestamptz NOT NULL DEFAULT now(),
  last_seen timestamptz NOT NULL DEFAULT now(),
  created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE(hostname)
);

ALTER TABLE public.host_metrics ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can manage host metrics"
  ON public.host_metrics FOR ALL
  USING (has_role(auth.uid(), 'admin'::app_role));

CREATE POLICY "Authenticated users can view host metrics"
  ON public.host_metrics FOR SELECT
  USING (true);

-- Enable realtime for host_metrics
ALTER PUBLICATION supabase_realtime ADD TABLE public.host_metrics;
