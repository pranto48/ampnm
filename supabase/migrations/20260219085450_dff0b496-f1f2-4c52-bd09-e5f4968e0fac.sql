
-- Create host_metrics_history table for time-series agent data
CREATE TABLE public.host_metrics_history (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  hostname TEXT NOT NULL,
  cpu_usage NUMERIC,
  memory_usage NUMERIC,
  memory_total NUMERIC,
  disk_usage NUMERIC,
  disk_total NUMERIC,
  network_in NUMERIC,
  network_out NUMERIC,
  gpu_usage NUMERIC,
  recorded_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

-- Index for efficient queries by hostname + time
CREATE INDEX idx_host_metrics_history_hostname_time 
  ON public.host_metrics_history (hostname, recorded_at DESC);

-- Enable RLS
ALTER TABLE public.host_metrics_history ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can manage host metrics history"
  ON public.host_metrics_history FOR ALL
  USING (has_role(auth.uid(), 'admin'::app_role));

CREATE POLICY "Authenticated users can view host metrics history"
  ON public.host_metrics_history FOR SELECT
  USING (true);

-- Allow edge function inserts (service role bypasses RLS, but let's be explicit)
-- Enable realtime
ALTER PUBLICATION supabase_realtime ADD TABLE public.host_metrics_history;
