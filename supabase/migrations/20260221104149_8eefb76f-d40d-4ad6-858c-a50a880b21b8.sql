
-- Add uptime fields to host_metrics
ALTER TABLE public.host_metrics
  ADD COLUMN IF NOT EXISTS uptime_seconds BIGINT,
  ADD COLUMN IF NOT EXISTS boot_time TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS os_version TEXT;

-- Host processes table (latest snapshot per host)
CREATE TABLE public.host_processes (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  hostname TEXT NOT NULL,
  process_name TEXT NOT NULL,
  pid INTEGER,
  cpu_percent NUMERIC,
  memory_mb NUMERIC,
  status TEXT,
  process_type TEXT DEFAULT 'process',
  recorded_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_host_processes_hostname ON public.host_processes(hostname);
CREATE INDEX idx_host_processes_recorded ON public.host_processes(recorded_at DESC);

ALTER TABLE public.host_processes ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can manage host processes" ON public.host_processes
  FOR ALL USING (has_role(auth.uid(), 'admin'::app_role));

CREATE POLICY "Authenticated users can view host processes" ON public.host_processes
  FOR SELECT USING (true);
