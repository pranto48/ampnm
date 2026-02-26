import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useToast } from "@/hooks/use-toast";

export interface Target {
  id: string;
  name: string;
  host: string;
  created_at: string;
  updated_at: string;
}

export interface PingResult {
  id: string;
  target_id: string;
  status: "online" | "offline" | "unknown";
  response_time_ms: number | null;
  checked_at: string;
}

export function useTargets() {
  return useQuery({
    queryKey: ["targets"],
    queryFn: async () => {
      const { data, error } = await supabase
        .from("targets")
        .select("*")
        .order("created_at", { ascending: false });

      if (error) throw error;
      return data as Target[];
    },
  });
}

export function useLatestPingResults() {
  return useQuery({
    queryKey: ["ping-results-latest"],
    queryFn: async () => {
      // Get the latest ping result for each target
      const { data, error } = await supabase
        .from("ping_results")
        .select("*")
        .order("checked_at", { ascending: false });

      if (error) throw error;

      // Group by target_id and get the latest for each
      const latestByTarget: Record<string, PingResult> = {};
      for (const result of data as PingResult[]) {
        if (!latestByTarget[result.target_id]) {
          latestByTarget[result.target_id] = result;
        }
      }

      return latestByTarget;
    },
    refetchInterval: 5000, // Refetch every 5 seconds
  });
}

export function useAddTarget() {
  const queryClient = useQueryClient();
  const { toast } = useToast();

  return useMutation({
    mutationFn: async ({ name, host }: { name: string; host: string }) => {
      const { data, error } = await supabase
        .from("targets")
        .insert({ name, host })
        .select()
        .single();

      if (error) throw error;
      return data as Target;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["targets"] });
      toast({
        title: "Target Added",
        description: "The target has been added successfully.",
      });
    },
    onError: (error) => {
      toast({
        title: "Error",
        description: error.message,
        variant: "destructive",
      });
    },
  });
}

export function useDeleteTarget() {
  const queryClient = useQueryClient();
  const { toast } = useToast();

  return useMutation({
    mutationFn: async (id: string) => {
      const { error } = await supabase.from("targets").delete().eq("id", id);
      if (error) throw error;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["targets"] });
      queryClient.invalidateQueries({ queryKey: ["ping-results-latest"] });
      toast({
        title: "Target Deleted",
        description: "The target has been removed.",
      });
    },
    onError: (error) => {
      toast({
        title: "Error",
        description: error.message,
        variant: "destructive",
      });
    },
  });
}

export function usePingTarget() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ targetId, host }: { targetId: string; host: string }) => {
      const { data, error } = await supabase.functions.invoke("ping-target", {
        body: { targetId, host },
      });

      if (error) throw error;
      return data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["ping-results-latest"] });
    },
  });
}
