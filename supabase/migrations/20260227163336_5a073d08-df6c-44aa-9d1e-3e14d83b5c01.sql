
-- Floor plans table
CREATE TABLE public.floor_plans (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  user_id UUID NOT NULL,
  name TEXT NOT NULL DEFAULT 'Floor Plan',
  image_url TEXT,
  width INTEGER DEFAULT 1200,
  height INTEGER DEFAULT 800,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.floor_plans ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Admins can manage floor plans" ON public.floor_plans FOR ALL USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Authenticated users can view floor plans" ON public.floor_plans FOR SELECT USING (true);

-- Rack locations on floor plans
CREATE TABLE public.rack_locations (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  floor_plan_id UUID NOT NULL REFERENCES public.floor_plans(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  x NUMERIC DEFAULT 0,
  y NUMERIC DEFAULT 0,
  rack_units INTEGER DEFAULT 42,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.rack_locations ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Admins can manage rack locations" ON public.rack_locations FOR ALL USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Authenticated users can view rack locations" ON public.rack_locations FOR SELECT USING (true);

-- Patch panels within racks
CREATE TABLE public.patch_panels (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  rack_id UUID NOT NULL REFERENCES public.rack_locations(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  port_count INTEGER DEFAULT 24,
  rack_position INTEGER DEFAULT 1,
  panel_type TEXT DEFAULT 'rj45',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.patch_panels ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Admins can manage patch panels" ON public.patch_panels FOR ALL USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Authenticated users can view patch panels" ON public.patch_panels FOR SELECT USING (true);

-- Switch ports on network devices
CREATE TABLE public.switch_ports (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  device_id UUID NOT NULL REFERENCES public.devices(id) ON DELETE CASCADE,
  port_number INTEGER NOT NULL,
  port_label TEXT,
  status TEXT DEFAULT 'inactive',
  speed TEXT DEFAULT '1G',
  vlan TEXT,
  connected_device TEXT,
  notes TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.switch_ports ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Admins can manage switch ports" ON public.switch_ports FOR ALL USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Authenticated users can view switch ports" ON public.switch_ports FOR SELECT USING (true);

-- Cable runs connecting ports
CREATE TABLE public.cable_runs (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  floor_plan_id UUID REFERENCES public.floor_plans(id) ON DELETE CASCADE,
  cable_type TEXT DEFAULT 'cat6',
  cable_color TEXT DEFAULT 'blue',
  cable_length TEXT,
  label TEXT,
  source_type TEXT NOT NULL DEFAULT 'patch_panel',
  source_id UUID NOT NULL,
  source_port INTEGER NOT NULL,
  dest_type TEXT NOT NULL DEFAULT 'switch',
  dest_id UUID NOT NULL,
  dest_port INTEGER NOT NULL,
  notes TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
ALTER TABLE public.cable_runs ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Admins can manage cable runs" ON public.cable_runs FOR ALL USING (has_role(auth.uid(), 'admin'::app_role));
CREATE POLICY "Authenticated users can view cable runs" ON public.cable_runs FOR SELECT USING (true);

-- Triggers for updated_at
CREATE TRIGGER update_floor_plans_updated_at BEFORE UPDATE ON public.floor_plans FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();
