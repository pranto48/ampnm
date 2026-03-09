
-- Storage bucket for floor plan file uploads
INSERT INTO storage.buckets (id, name, public) VALUES ('floor-plan-files', 'floor-plan-files', true);

-- Allow authenticated users to upload floor plan files
CREATE POLICY "Authenticated users can upload floor plan files"
ON storage.objects FOR INSERT TO authenticated
WITH CHECK (bucket_id = 'floor-plan-files');

-- Allow public read access to floor plan files
CREATE POLICY "Public can read floor plan files"
ON storage.objects FOR SELECT TO public
USING (bucket_id = 'floor-plan-files');

-- Allow authenticated users to delete their floor plan files
CREATE POLICY "Authenticated users can delete floor plan files"
ON storage.objects FOR DELETE TO authenticated
USING (bucket_id = 'floor-plan-files');

-- Floor plan annotations table
CREATE TABLE public.floor_plan_annotations (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  floor_plan_id uuid REFERENCES public.floor_plans(id) ON DELETE CASCADE NOT NULL,
  x numeric NOT NULL DEFAULT 0,
  y numeric NOT NULL DEFAULT 0,
  text text NOT NULL DEFAULT '',
  font_size integer NOT NULL DEFAULT 14,
  color text NOT NULL DEFAULT '#ffffff',
  type text NOT NULL DEFAULT 'label',
  width numeric,
  height numeric,
  created_at timestamptz NOT NULL DEFAULT now()
);

ALTER TABLE public.floor_plan_annotations ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can manage annotations"
ON public.floor_plan_annotations FOR ALL TO authenticated
USING (has_role(auth.uid(), 'admin'::app_role))
WITH CHECK (has_role(auth.uid(), 'admin'::app_role));

CREATE POLICY "Authenticated users can view annotations"
ON public.floor_plan_annotations FOR SELECT TO authenticated
USING (true);

-- Add rotation and label_visible to rack_locations
ALTER TABLE public.rack_locations ADD COLUMN IF NOT EXISTS rotation integer NOT NULL DEFAULT 0;
ALTER TABLE public.rack_locations ADD COLUMN IF NOT EXISTS label_visible boolean NOT NULL DEFAULT true;
