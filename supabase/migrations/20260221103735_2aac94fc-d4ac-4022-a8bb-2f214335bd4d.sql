
-- Host alert settings (global thresholds per user)
CREATE TABLE public.host_alert_settings (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  user_id UUID NOT NULL,
  cpu_warning_threshold INTEGER DEFAULT 80,
  cpu_critical_threshold INTEGER DEFAULT 95,
  memory_warning_threshold INTEGER DEFAULT 80,
  memory_critical_threshold INTEGER DEFAULT 95,
  disk_warning_threshold INTEGER DEFAULT 80,
  disk_critical_threshold INTEGER DEFAULT 95,
  gpu_warning_threshold INTEGER DEFAULT 80,
  gpu_critical_threshold INTEGER DEFAULT 95,
  enabled BOOLEAN DEFAULT true,
  cooldown_minutes INTEGER DEFAULT 30,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(user_id)
);

ALTER TABLE public.host_alert_settings ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can manage alert settings" ON public.host_alert_settings
  FOR ALL USING (has_role(auth.uid(), 'admin'::app_role));

CREATE POLICY "Users can view own alert settings" ON public.host_alert_settings
  FOR SELECT USING (auth.uid() = user_id);

-- Per-host alert overrides
CREATE TABLE public.host_alert_overrides (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  hostname TEXT NOT NULL,
  host_ip TEXT,
  enabled BOOLEAN DEFAULT true,
  cpu_warning INTEGER DEFAULT 80,
  cpu_critical INTEGER DEFAULT 95,
  memory_warning INTEGER DEFAULT 80,
  memory_critical INTEGER DEFAULT 95,
  disk_warning INTEGER DEFAULT 85,
  disk_critical INTEGER DEFAULT 95,
  gpu_warning INTEGER DEFAULT 80,
  gpu_critical INTEGER DEFAULT 95,
  status_delay_seconds INTEGER,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(hostname)
);

ALTER TABLE public.host_alert_overrides ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can manage host overrides" ON public.host_alert_overrides
  FOR ALL USING (has_role(auth.uid(), 'admin'::app_role));

CREATE POLICY "Authenticated users can view host overrides" ON public.host_alert_overrides
  FOR SELECT USING (true);

-- Triggers for updated_at
CREATE TRIGGER update_host_alert_settings_updated_at
  BEFORE UPDATE ON public.host_alert_settings
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

CREATE TRIGGER update_host_alert_overrides_updated_at
  BEFORE UPDATE ON public.host_alert_overrides
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();
