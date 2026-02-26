
-- =============================================
-- AMPNM Cloud Database Schema
-- =============================================

-- 1. Role enum and user_roles table (security-first approach)
CREATE TYPE public.app_role AS ENUM ('admin', 'viewer');

CREATE TABLE public.user_roles (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE NOT NULL,
  role app_role NOT NULL DEFAULT 'viewer',
  UNIQUE (user_id, role)
);
ALTER TABLE public.user_roles ENABLE ROW LEVEL SECURITY;

-- Security definer function to check roles without recursion
CREATE OR REPLACE FUNCTION public.has_role(_user_id UUID, _role app_role)
RETURNS BOOLEAN
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT EXISTS (
    SELECT 1 FROM public.user_roles
    WHERE user_id = _user_id AND role = _role
  )
$$;

-- RLS for user_roles
CREATE POLICY "Users can view own roles" ON public.user_roles
  FOR SELECT TO authenticated USING (auth.uid() = user_id);
CREATE POLICY "Admins can manage all roles" ON public.user_roles
  FOR ALL TO authenticated USING (public.has_role(auth.uid(), 'admin'));

-- 2. Profiles table
CREATE TABLE public.profiles (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE NOT NULL UNIQUE,
  username TEXT,
  full_name TEXT,
  avatar_url TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can view own profile" ON public.profiles
  FOR SELECT TO authenticated USING (auth.uid() = user_id);
CREATE POLICY "Users can update own profile" ON public.profiles
  FOR UPDATE TO authenticated USING (auth.uid() = user_id);
CREATE POLICY "Admins can view all profiles" ON public.profiles
  FOR SELECT TO authenticated USING (public.has_role(auth.uid(), 'admin'));

-- Trigger: auto-create profile + default admin role on signup
CREATE OR REPLACE FUNCTION public.handle_new_user()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  INSERT INTO public.profiles (user_id, username, full_name)
  VALUES (
    NEW.id,
    COALESCE(NEW.raw_user_meta_data->>'username', NEW.email),
    COALESCE(NEW.raw_user_meta_data->>'full_name', '')
  );
  -- First user gets admin role, others get viewer
  IF (SELECT COUNT(*) FROM public.user_roles) = 0 THEN
    INSERT INTO public.user_roles (user_id, role) VALUES (NEW.id, 'admin');
  ELSE
    INSERT INTO public.user_roles (user_id, role) VALUES (NEW.id, 'viewer');
  END IF;
  RETURN NEW;
END;
$$;

CREATE TRIGGER on_auth_user_created
  AFTER INSERT ON auth.users
  FOR EACH ROW EXECUTE FUNCTION public.handle_new_user();

-- Profile updated_at trigger
CREATE TRIGGER update_profiles_updated_at
  BEFORE UPDATE ON public.profiles
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

-- 3. Maps table
CREATE TABLE public.maps (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE NOT NULL,
  name TEXT NOT NULL DEFAULT 'Default Map',
  background_color TEXT DEFAULT '#1e293b',
  background_image_url TEXT,
  public_view_enabled BOOLEAN DEFAULT false,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.maps ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Authenticated users can view maps" ON public.maps
  FOR SELECT TO authenticated USING (true);
CREATE POLICY "Admins can manage maps" ON public.maps
  FOR ALL TO authenticated USING (public.has_role(auth.uid(), 'admin'));
CREATE POLICY "Public maps are viewable by anyone" ON public.maps
  FOR SELECT TO anon USING (public_view_enabled = true);

CREATE TRIGGER update_maps_updated_at
  BEFORE UPDATE ON public.maps
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

-- 4. Devices table
CREATE TABLE public.devices (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE NOT NULL,
  map_id UUID REFERENCES public.maps(id) ON DELETE SET NULL,
  name TEXT NOT NULL,
  ip_address TEXT,
  check_port INTEGER,
  monitor_method TEXT DEFAULT 'ping' CHECK (monitor_method IN ('ping', 'port')),
  type TEXT DEFAULT 'server',
  subchoice TEXT,
  status TEXT DEFAULT 'unknown' CHECK (status IN ('online', 'offline', 'warning', 'critical', 'unknown')),
  description TEXT,
  x NUMERIC DEFAULT 100,
  y NUMERIC DEFAULT 100,
  ping_interval INTEGER DEFAULT 300,
  icon_size INTEGER DEFAULT 40,
  name_text_size INTEGER DEFAULT 12,
  icon_url TEXT,
  show_live_ping BOOLEAN DEFAULT false,
  warning_latency_threshold INTEGER DEFAULT 100,
  warning_packetloss_threshold INTEGER DEFAULT 10,
  critical_latency_threshold INTEGER DEFAULT 500,
  critical_packetloss_threshold INTEGER DEFAULT 50,
  last_ping TIMESTAMPTZ,
  last_ping_result BOOLEAN,
  last_latency NUMERIC,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.devices ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Authenticated users can view devices" ON public.devices
  FOR SELECT TO authenticated USING (true);
CREATE POLICY "Admins can manage devices" ON public.devices
  FOR ALL TO authenticated USING (public.has_role(auth.uid(), 'admin'));
CREATE POLICY "Public devices viewable via public maps" ON public.devices
  FOR SELECT TO anon USING (
    EXISTS (SELECT 1 FROM public.maps WHERE maps.id = devices.map_id AND maps.public_view_enabled = true)
  );

CREATE TRIGGER update_devices_updated_at
  BEFORE UPDATE ON public.devices
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

-- 5. Device edges
CREATE TABLE public.device_edges (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  map_id UUID REFERENCES public.maps(id) ON DELETE CASCADE NOT NULL,
  source_id UUID REFERENCES public.devices(id) ON DELETE CASCADE NOT NULL,
  target_id UUID REFERENCES public.devices(id) ON DELETE CASCADE NOT NULL,
  connection_type TEXT DEFAULT 'cat5' CHECK (connection_type IN ('cat5', 'fiber', 'wifi', 'radio', 'lan', 'tunnel')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.device_edges ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Authenticated users can view edges" ON public.device_edges
  FOR SELECT TO authenticated USING (true);
CREATE POLICY "Admins can manage edges" ON public.device_edges
  FOR ALL TO authenticated USING (public.has_role(auth.uid(), 'admin'));
CREATE POLICY "Public edges viewable via public maps" ON public.device_edges
  FOR SELECT TO anon USING (
    EXISTS (SELECT 1 FROM public.maps WHERE maps.id = device_edges.map_id AND maps.public_view_enabled = true)
  );

-- 6. Device status logs
CREATE TABLE public.device_status_logs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  device_id UUID REFERENCES public.devices(id) ON DELETE CASCADE NOT NULL,
  old_status TEXT,
  new_status TEXT NOT NULL,
  changed_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.device_status_logs ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Authenticated users can view status logs" ON public.device_status_logs
  FOR SELECT TO authenticated USING (true);
CREATE POLICY "Admins can manage status logs" ON public.device_status_logs
  FOR INSERT TO authenticated WITH CHECK (public.has_role(auth.uid(), 'admin'));

-- Enable realtime for status logs
ALTER PUBLICATION supabase_realtime ADD TABLE public.device_status_logs;

-- 7. New ping_results (will coexist with old table for now)
-- The old ping_results table references targets; new devices table replaces targets
-- We'll add device_id column to work with new schema later, or create new table
-- For now, keep existing ping_results and add device-linked results separately

CREATE TABLE public.device_ping_results (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  device_id UUID REFERENCES public.devices(id) ON DELETE CASCADE NOT NULL,
  status TEXT NOT NULL,
  latency_ms NUMERIC,
  packet_loss NUMERIC,
  checked_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.device_ping_results ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Authenticated users can view ping results" ON public.device_ping_results
  FOR SELECT TO authenticated USING (true);
CREATE POLICY "System can insert ping results" ON public.device_ping_results
  FOR INSERT TO authenticated WITH CHECK (true);

-- 8. Network graphs
CREATE TABLE public.network_graphs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE NOT NULL,
  name TEXT NOT NULL,
  url TEXT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.network_graphs ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Authenticated users can view graphs" ON public.network_graphs
  FOR SELECT TO authenticated USING (true);
CREATE POLICY "Admins can manage graphs" ON public.network_graphs
  FOR ALL TO authenticated USING (public.has_role(auth.uid(), 'admin'));

CREATE TRIGGER update_network_graphs_updated_at
  BEFORE UPDATE ON public.network_graphs
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();

-- 9. SMTP settings
CREATE TABLE public.smtp_settings (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE NOT NULL,
  smtp_host TEXT,
  smtp_port INTEGER DEFAULT 587,
  smtp_username TEXT,
  smtp_password TEXT,
  smtp_from_email TEXT,
  smtp_from_name TEXT,
  smtp_encryption TEXT DEFAULT 'tls',
  enabled BOOLEAN DEFAULT false,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.smtp_settings ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can manage smtp settings" ON public.smtp_settings
  FOR ALL TO authenticated USING (public.has_role(auth.uid(), 'admin'));

-- 10. Device email subscriptions
CREATE TABLE public.device_email_subscriptions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  device_id UUID REFERENCES public.devices(id) ON DELETE CASCADE NOT NULL,
  email TEXT NOT NULL,
  notify_on_offline BOOLEAN DEFAULT true,
  notify_on_online BOOLEAN DEFAULT true,
  notify_on_warning BOOLEAN DEFAULT false,
  notify_on_critical BOOLEAN DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.device_email_subscriptions ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can manage email subscriptions" ON public.device_email_subscriptions
  FOR ALL TO authenticated USING (public.has_role(auth.uid(), 'admin'));

-- 11. App settings (key-value store)
CREATE TABLE public.app_settings (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  key TEXT NOT NULL UNIQUE,
  value TEXT,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.app_settings ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Authenticated users can view settings" ON public.app_settings
  FOR SELECT TO authenticated USING (true);
CREATE POLICY "Admins can manage settings" ON public.app_settings
  FOR ALL TO authenticated USING (public.has_role(auth.uid(), 'admin'));

-- Enable realtime for devices (for live status updates)
ALTER PUBLICATION supabase_realtime ADD TABLE public.devices;
