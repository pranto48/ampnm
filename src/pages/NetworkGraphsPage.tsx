import { useState, useEffect } from "react";
import { supabase } from "@/integrations/supabase/client";
import { AppLayout } from "@/components/layout/AppLayout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { BarChart3, Plus, Pencil, Trash2, RefreshCw, ExternalLink } from "lucide-react";
import { useToast } from "@/hooks/use-toast";
import { useAuth } from "@/hooks/useAuth";
import type { Tables } from "@/integrations/supabase/types";

type Graph = Tables<"network_graphs">;

export default function NetworkGraphsPage() {
  const { toast } = useToast();
  const { user } = useAuth();
  const [graphs, setGraphs] = useState<Graph[]>([]);
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<Graph | null>(null);
  const [name, setName] = useState("");
  const [url, setUrl] = useState("");

  const fetch_ = async () => {
    setLoading(true);
    const { data, error } = await supabase.from("network_graphs").select("*").order("name");
    if (error) toast({ title: "Error", description: error.message, variant: "destructive" });
    else setGraphs(data ?? []);
    setLoading(false);
  };

  useEffect(() => { fetch_(); }, []);

  const openAdd = () => { setEditing(null); setName(""); setUrl(""); setDialogOpen(true); };
  const openEdit = (g: Graph) => { setEditing(g); setName(g.name); setUrl(g.url); setDialogOpen(true); };

  const handleSave = async () => {
    if (!name.trim() || !url.trim()) return;
    if (editing) {
      const { error } = await supabase.from("network_graphs").update({ name, url }).eq("id", editing.id);
      if (error) toast({ title: "Error", description: error.message, variant: "destructive" });
      else toast({ title: "Graph updated" });
    } else {
      const { error } = await supabase.from("network_graphs").insert({ name, url, user_id: user!.id });
      if (error) toast({ title: "Error", description: error.message, variant: "destructive" });
      else toast({ title: "Graph added" });
    }
    setDialogOpen(false);
    fetch_();
  };

  const handleDelete = async (g: Graph) => {
    if (!confirm(`Delete "${g.name}"?`)) return;
    await supabase.from("network_graphs").delete().eq("id", g.id);
    toast({ title: "Graph deleted" });
    fetch_();
  };

  return (
    <AppLayout>
      <div className="space-y-4">
        <div className="flex items-center justify-between flex-wrap gap-2">
          <div className="flex items-center gap-3">
            <BarChart3 className="h-7 w-7 text-primary" />
            <h1 className="text-2xl font-bold tracking-tight">Network Graphs</h1>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={fetch_} disabled={loading}>
              <RefreshCw className={`h-4 w-4 mr-1 ${loading ? "animate-spin" : ""}`} /> Refresh
            </Button>
            <Button size="sm" onClick={openAdd}><Plus className="h-4 w-4 mr-1" /> Add Graph</Button>
          </div>
        </div>

        {graphs.length === 0 && !loading ? (
          <Card><CardContent className="py-8 text-center text-muted-foreground">No network graphs configured.</CardContent></Card>
        ) : (
          <div className="grid gap-4">
            {graphs.map((g) => (
              <Card key={g.id}>
                <CardHeader className="flex flex-row items-center justify-between pb-2">
                  <CardTitle className="text-base">{g.name}</CardTitle>
                  <div className="flex gap-1">
                    <Button variant="ghost" size="icon" onClick={() => openEdit(g)}><Pencil className="h-4 w-4" /></Button>
                    <Button variant="ghost" size="icon" onClick={() => handleDelete(g)} className="text-destructive hover:text-destructive"><Trash2 className="h-4 w-4" /></Button>
                    <Button variant="ghost" size="icon" asChild><a href={g.url} target="_blank" rel="noreferrer"><ExternalLink className="h-4 w-4" /></a></Button>
                  </div>
                </CardHeader>
                <CardContent>
                  <iframe src={g.url} className="w-full h-[400px] rounded-md border border-border bg-background" title={g.name} />
                </CardContent>
              </Card>
            ))}
          </div>
        )}

        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
          <DialogContent>
            <DialogHeader><DialogTitle>{editing ? "Edit" : "Add"} Graph</DialogTitle></DialogHeader>
            <div className="space-y-3 py-2">
              <div><Label>Name</Label><Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Cacti Graph" /></div>
              <div><Label>URL</Label><Input value={url} onChange={(e) => setUrl(e.target.value)} placeholder="https://cacti.example.com/graph.php" /></div>
            </div>
            <DialogFooter><Button onClick={handleSave}>Save</Button></DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </AppLayout>
  );
}
