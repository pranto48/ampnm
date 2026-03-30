-- Extended alert subsystem: policies, maintenance, dependencies, ownership, routing, and audit transitions

DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'alert_severity') THEN
    CREATE TYPE public.alert_severity AS ENUM ('info', 'warning', 'critical');
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'alert_state') THEN
    CREATE TYPE public.alert_state AS ENUM ('open', 'acknowledged', 'suppressed', 'resolved');
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'alert_channel') THEN
    CREATE TYPE public.alert_channel AS ENUM ('email', 'webhook', 'slack', 'teams');
  END IF;
END $$;

CREATE TABLE IF NOT EXISTS public.alert_policies (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name TEXT NOT NULL,
  target_type TEXT NOT NULL CHECK (target_type IN ('host', 'device', 'group', 'global')),
  target_id UUID,
  group_name TEXT,
  severity alert_severity NOT NULL DEFAULT 'warning',
  dedup_window_seconds INTEGER NOT NULL DEFAULT 300 CHECK (dedup_window_seconds >= 0),
  cooldown_seconds INTEGER NOT NULL DEFAULT 600 CHECK (cooldown_seconds >= 0),
  escalation_delay_seconds INTEGER NOT NULL DEFAULT 900 CHECK (escalation_delay_seconds >= 0),
  enabled BOOLEAN NOT NULL DEFAULT true,
  created_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.alert_maintenance_windows (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name TEXT NOT NULL,
  scope_type TEXT NOT NULL CHECK (scope_type IN ('map', 'device', 'group', 'global')),
  map_id UUID REFERENCES public.maps(id) ON DELETE CASCADE,
  device_id UUID REFERENCES public.devices(id) ON DELETE CASCADE,
  group_name TEXT,
  starts_at TIMESTAMPTZ NOT NULL,
  ends_at TIMESTAMPTZ NOT NULL,
  timezone TEXT NOT NULL DEFAULT 'UTC',
  suppress_alerts BOOLEAN NOT NULL DEFAULT true,
  created_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CHECK (ends_at > starts_at)
);

CREATE TABLE IF NOT EXISTS public.alert_dependencies (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  parent_device_id UUID REFERENCES public.devices(id) ON DELETE CASCADE,
  child_device_id UUID REFERENCES public.devices(id) ON DELETE CASCADE,
  parent_host TEXT,
  child_host TEXT,
  suppress_child_alerts BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CHECK (
    (parent_device_id IS NOT NULL AND child_device_id IS NOT NULL)
    OR (parent_host IS NOT NULL AND child_host IS NOT NULL)
  )
);

CREATE TABLE IF NOT EXISTS public.alert_events (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  dedup_key TEXT NOT NULL,
  source_type TEXT NOT NULL CHECK (source_type IN ('host', 'device', 'service')),
  hostname TEXT,
  device_id UUID REFERENCES public.devices(id) ON DELETE CASCADE,
  map_id UUID REFERENCES public.maps(id) ON DELETE SET NULL,
  group_name TEXT,
  summary TEXT NOT NULL,
  details JSONB NOT NULL DEFAULT '{}'::jsonb,
  severity alert_severity NOT NULL DEFAULT 'warning',
  state alert_state NOT NULL DEFAULT 'open',
  opened_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  last_seen_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  acknowledged_at TIMESTAMPTZ,
  acknowledged_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
  owner_user_id UUID REFERENCES auth.users(id) ON DELETE SET NULL,
  resolved_at TIMESTAMPTZ,
  suppressed_reason TEXT,
  root_cause TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_alert_events_dedup_key_active
ON public.alert_events(dedup_key)
WHERE state IN ('open', 'acknowledged', 'suppressed');

CREATE INDEX IF NOT EXISTS idx_alert_events_state_severity
ON public.alert_events(state, severity, last_seen_at DESC);

CREATE TABLE IF NOT EXISTS public.alert_state_transitions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  alert_event_id UUID NOT NULL REFERENCES public.alert_events(id) ON DELETE CASCADE,
  from_state alert_state,
  to_state alert_state NOT NULL,
  transition_reason TEXT,
  metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
  changed_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
  changed_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_alert_state_transitions_alert_time
ON public.alert_state_transitions(alert_event_id, changed_at DESC);

CREATE TABLE IF NOT EXISTS public.alert_notification_routes (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  route_name TEXT NOT NULL,
  severity alert_severity NOT NULL,
  group_name TEXT,
  channel alert_channel NOT NULL,
  destination TEXT NOT NULL,
  enabled BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_alert_notification_routes_match
ON public.alert_notification_routes(severity, group_name, enabled);

CREATE OR REPLACE FUNCTION public.log_alert_state_transition()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  IF TG_OP = 'INSERT' THEN
    INSERT INTO public.alert_state_transitions (alert_event_id, from_state, to_state, transition_reason, metadata, changed_by)
    VALUES (NEW.id, NULL, NEW.state, 'created', jsonb_build_object('severity', NEW.severity), auth.uid());
    RETURN NEW;
  END IF;

  IF NEW.state IS DISTINCT FROM OLD.state THEN
    INSERT INTO public.alert_state_transitions (alert_event_id, from_state, to_state, transition_reason, metadata, changed_by)
    VALUES (
      NEW.id,
      OLD.state,
      NEW.state,
      COALESCE(NEW.suppressed_reason, CASE WHEN NEW.state = 'resolved' THEN 'resolved' ELSE 'state_changed' END),
      jsonb_strip_nulls(jsonb_build_object(
        'severity', NEW.severity,
        'owner_user_id', NEW.owner_user_id,
        'acknowledged_by', NEW.acknowledged_by,
        'root_cause', NEW.root_cause
      )),
      auth.uid()
    );
  END IF;

  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_alert_event_transitions ON public.alert_events;
CREATE TRIGGER trg_alert_event_transitions
AFTER INSERT OR UPDATE ON public.alert_events
FOR EACH ROW EXECUTE FUNCTION public.log_alert_state_transition();

ALTER TABLE public.alert_policies ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.alert_maintenance_windows ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.alert_dependencies ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.alert_events ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.alert_state_transitions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.alert_notification_routes ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can manage alert policies" ON public.alert_policies
  FOR ALL TO authenticated USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Authenticated users can view alert policies" ON public.alert_policies
  FOR SELECT TO authenticated USING (true);

CREATE POLICY "Admins can manage alert maintenance windows" ON public.alert_maintenance_windows
  FOR ALL TO authenticated USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Authenticated users can view alert maintenance windows" ON public.alert_maintenance_windows
  FOR SELECT TO authenticated USING (true);

CREATE POLICY "Admins can manage alert dependencies" ON public.alert_dependencies
  FOR ALL TO authenticated USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Authenticated users can view alert dependencies" ON public.alert_dependencies
  FOR SELECT TO authenticated USING (true);

CREATE POLICY "Admins can manage alert events" ON public.alert_events
  FOR ALL TO authenticated USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Authenticated users can view alert events" ON public.alert_events
  FOR SELECT TO authenticated USING (true);

CREATE POLICY "Authenticated users can acknowledge alert events" ON public.alert_events
  FOR UPDATE TO authenticated
  USING (true)
  WITH CHECK (
    state IN ('open', 'acknowledged', 'suppressed', 'resolved')
    AND (
      owner_user_id IS NULL
      OR owner_user_id = auth.uid()
      OR has_role(auth.uid(), 'admin'::app_role)
    )
  );

CREATE POLICY "Authenticated users can view alert transitions" ON public.alert_state_transitions
  FOR SELECT TO authenticated USING (true);
CREATE POLICY "Admins can manage alert transitions" ON public.alert_state_transitions
  FOR ALL TO authenticated USING (has_role(auth.uid(), 'admin'::app_role));

CREATE POLICY "Admins can manage alert routes" ON public.alert_notification_routes
  FOR ALL TO authenticated USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Authenticated users can view alert routes" ON public.alert_notification_routes
  FOR SELECT TO authenticated USING (true);

DROP TRIGGER IF EXISTS update_alert_policies_updated_at ON public.alert_policies;
CREATE TRIGGER update_alert_policies_updated_at
  BEFORE UPDATE ON public.alert_policies
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

DROP TRIGGER IF EXISTS update_alert_maintenance_windows_updated_at ON public.alert_maintenance_windows;
CREATE TRIGGER update_alert_maintenance_windows_updated_at
  BEFORE UPDATE ON public.alert_maintenance_windows
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

DROP TRIGGER IF EXISTS update_alert_events_updated_at ON public.alert_events;
CREATE TRIGGER update_alert_events_updated_at
  BEFORE UPDATE ON public.alert_events
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

DROP TRIGGER IF EXISTS update_alert_notification_routes_updated_at ON public.alert_notification_routes;
CREATE TRIGGER update_alert_notification_routes_updated_at
  BEFORE UPDATE ON public.alert_notification_routes
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

ALTER PUBLICATION supabase_realtime ADD TABLE public.alert_events;
ALTER PUBLICATION supabase_realtime ADD TABLE public.alert_state_transitions;
