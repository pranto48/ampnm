-- Extend host metrics with Linux load/temperature fields and add service snapshots
ALTER TABLE public.host_metrics
  ADD COLUMN IF NOT EXISTS load_1 NUMERIC,
  ADD COLUMN IF NOT EXISTS load_5 NUMERIC,
  ADD COLUMN IF NOT EXISTS load_15 NUMERIC,
  ADD COLUMN IF NOT EXISTS temperature_c NUMERIC,
  ADD COLUMN IF NOT EXISTS sensor_summary JSONB;

ALTER TABLE public.host_metrics_history
  ADD COLUMN IF NOT EXISTS load_1 NUMERIC,
  ADD COLUMN IF NOT EXISTS load_5 NUMERIC,
  ADD COLUMN IF NOT EXISTS load_15 NUMERIC,
  ADD COLUMN IF NOT EXISTS temperature_c NUMERIC;

CREATE TABLE IF NOT EXISTS public.host_services (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  hostname TEXT NOT NULL,
  service_name TEXT NOT NULL,
  display_name TEXT,
  state TEXT,
  sub_state TEXT,
  enabled BOOLEAN,
  recorded_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_host_services_hostname
  ON public.host_services(hostname);

CREATE INDEX IF NOT EXISTS idx_host_services_recorded_at
  ON public.host_services(recorded_at DESC);

ALTER TABLE public.host_services ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can manage host services"
  ON public.host_services FOR ALL
  USING (has_role(auth.uid(), 'admin'::app_role));

CREATE POLICY "Authenticated users can view host services"
  ON public.host_services FOR SELECT
  USING (true);

ALTER PUBLICATION supabase_realtime ADD TABLE public.host_services;
