
-- Docker hosts table
CREATE TABLE public.docker_hosts (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  hostname TEXT NOT NULL UNIQUE,
  ip TEXT,
  docker_version TEXT,
  status TEXT NOT NULL DEFAULT 'unknown',
  active_containers INTEGER NOT NULL DEFAULT 0,
  orphaned_volumes INTEGER NOT NULL DEFAULT 0,
  last_synced TIMESTAMP WITH TIME ZONE DEFAULT now(),
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

ALTER TABLE public.docker_hosts ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can manage docker hosts"
  ON public.docker_hosts FOR ALL
  USING (has_role(auth.uid(), 'admin'::app_role));

CREATE POLICY "Authenticated users can view docker hosts"
  ON public.docker_hosts FOR SELECT
  USING (true);

-- Docker containers table
CREATE TABLE public.docker_containers (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  host_id UUID NOT NULL REFERENCES public.docker_hosts(id) ON DELETE CASCADE,
  container_id TEXT NOT NULL,
  name TEXT NOT NULL,
  image TEXT,
  state TEXT NOT NULL DEFAULT 'unknown',
  status_text TEXT,
  ports JSONB DEFAULT '[]'::jsonb,
  networks JSONB DEFAULT '[]'::jsonb,
  internal_ip TEXT,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

ALTER TABLE public.docker_containers ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can manage docker containers"
  ON public.docker_containers FOR ALL
  USING (has_role(auth.uid(), 'admin'::app_role));

CREATE POLICY "Authenticated users can view docker containers"
  ON public.docker_containers FOR SELECT
  USING (true);

-- Docker networks table
CREATE TABLE public.docker_networks (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  host_id UUID NOT NULL REFERENCES public.docker_hosts(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  driver TEXT DEFAULT 'bridge',
  subnet TEXT,
  gateway TEXT,
  scope TEXT DEFAULT 'local',
  connected_containers JSONB DEFAULT '[]'::jsonb,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

ALTER TABLE public.docker_networks ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can manage docker networks"
  ON public.docker_networks FOR ALL
  USING (has_role(auth.uid(), 'admin'::app_role));

CREATE POLICY "Authenticated users can view docker networks"
  ON public.docker_networks FOR SELECT
  USING (true);

-- Triggers for updated_at
CREATE TRIGGER update_docker_hosts_updated_at
  BEFORE UPDATE ON public.docker_hosts
  FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();
