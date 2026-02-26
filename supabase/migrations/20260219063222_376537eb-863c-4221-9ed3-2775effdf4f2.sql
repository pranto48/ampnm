
-- Fix: restrict device_ping_results insert to admins only (ping results come from edge functions using service role key)
DROP POLICY "System can insert ping results" ON public.device_ping_results;
CREATE POLICY "Admins can insert ping results" ON public.device_ping_results
  FOR INSERT TO authenticated WITH CHECK (public.has_role(auth.uid(), 'admin'));

-- The remaining warnings are from the OLD targets/ping_results tables which will be removed in Phase 7
