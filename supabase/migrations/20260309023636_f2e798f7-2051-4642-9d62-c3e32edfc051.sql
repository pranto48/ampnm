
-- Drop old check constraint and add new one with cat6
ALTER TABLE public.device_edges DROP CONSTRAINT device_edges_connection_type_check;
ALTER TABLE public.device_edges ADD CONSTRAINT device_edges_connection_type_check 
  CHECK (connection_type = ANY (ARRAY['cat5','cat6','fiber','wifi','radio','lan','tunnel','logical-tunneling']));

-- Migrate existing cat5 to cat6
UPDATE public.device_edges SET connection_type = 'cat6' WHERE connection_type = 'cat5';

-- Change default
ALTER TABLE public.device_edges ALTER COLUMN connection_type SET DEFAULT 'cat6';

-- Add port_config column to devices
ALTER TABLE public.devices ADD COLUMN IF NOT EXISTS port_config jsonb DEFAULT NULL;
