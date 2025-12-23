-- Create targets table to store monitored servers/devices
CREATE TABLE public.targets (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  name TEXT NOT NULL,
  host TEXT NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

-- Create ping_results table to store connectivity check results
CREATE TABLE public.ping_results (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  target_id UUID NOT NULL REFERENCES public.targets(id) ON DELETE CASCADE,
  status TEXT NOT NULL CHECK (status IN ('online', 'offline', 'unknown')),
  response_time_ms INTEGER,
  checked_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

-- Enable Row Level Security (public access for this demo app)
ALTER TABLE public.targets ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.ping_results ENABLE ROW LEVEL SECURITY;

-- Allow public read/write access for targets (no auth required for this demo)
CREATE POLICY "Allow public read access on targets" 
ON public.targets 
FOR SELECT 
USING (true);

CREATE POLICY "Allow public insert access on targets" 
ON public.targets 
FOR INSERT 
WITH CHECK (true);

CREATE POLICY "Allow public update access on targets" 
ON public.targets 
FOR UPDATE 
USING (true);

CREATE POLICY "Allow public delete access on targets" 
ON public.targets 
FOR DELETE 
USING (true);

-- Allow public read access for ping results
CREATE POLICY "Allow public read access on ping_results" 
ON public.ping_results 
FOR SELECT 
USING (true);

CREATE POLICY "Allow public insert access on ping_results" 
ON public.ping_results 
FOR INSERT 
WITH CHECK (true);

-- Create index for faster queries on ping results
CREATE INDEX idx_ping_results_target_id ON public.ping_results(target_id);
CREATE INDEX idx_ping_results_checked_at ON public.ping_results(checked_at DESC);

-- Create function to update timestamps
CREATE OR REPLACE FUNCTION public.update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql SET search_path = public;

-- Create trigger for automatic timestamp updates on targets
CREATE TRIGGER update_targets_updated_at
BEFORE UPDATE ON public.targets
FOR EACH ROW
EXECUTE FUNCTION public.update_updated_at_column();

-- Enable realtime for ping_results
ALTER PUBLICATION supabase_realtime ADD TABLE public.ping_results;