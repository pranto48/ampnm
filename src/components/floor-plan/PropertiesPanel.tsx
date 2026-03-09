import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { X } from "lucide-react";

export type SelectedItem =
  | { kind: "rack"; id: string; name: string; rack_units: number; x: number; y: number; rotation: number; label_visible: boolean }
  | { kind: "device"; id: string; name: string; type: string | null; x: number; y: number }
  | { kind: "cable"; id: string; cable_type: string; cable_color: string; cable_length: string | null; label: string | null; notes: string | null }
  | { kind: "annotation"; id: string; text: string; font_size: number; color: string; type: string; width: number | null; height: number | null };

const CABLE_TYPES = ["cat5", "cat5e", "cat6", "cat6a", "cat7", "fiber-sm", "fiber-mm", "coax", "dac"];
const CABLE_COLORS = ["blue", "red", "green", "yellow", "orange", "white", "gray", "purple", "black"];

interface PropertiesPanelProps {
  item: SelectedItem | null;
  onUpdate: (item: SelectedItem) => void;
  onClose: () => void;
  isAdmin: boolean;
}

export function PropertiesPanel({ item, onUpdate, onClose, isAdmin }: PropertiesPanelProps) {
  if (!item) return null;

  const update = (patch: Partial<SelectedItem>) => {
    onUpdate({ ...item, ...patch } as SelectedItem);
  };

  return (
    <div className="w-64 bg-card border border-border rounded-lg shadow-sm p-4 space-y-3 overflow-y-auto max-h-[calc(100vh-200px)]">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold text-foreground capitalize">{item.kind} Properties</h3>
        <Button variant="ghost" size="icon" className="h-6 w-6" onClick={onClose}>
          <X className="h-3 w-3" />
        </Button>
      </div>

      {item.kind === "rack" && (
        <>
          <div><Label className="text-xs">Name</Label><Input value={item.name} onChange={e => update({ name: e.target.value })} disabled={!isAdmin} className="h-8 text-xs" /></div>
          <div><Label className="text-xs">Rack Units</Label><Input type="number" value={item.rack_units} onChange={e => update({ rack_units: +e.target.value })} disabled={!isAdmin} className="h-8 text-xs" /></div>
          <div className="grid grid-cols-2 gap-2">
            <div><Label className="text-xs">X</Label><Input type="number" value={Math.round(item.x)} onChange={e => update({ x: +e.target.value })} disabled={!isAdmin} className="h-8 text-xs" /></div>
            <div><Label className="text-xs">Y</Label><Input type="number" value={Math.round(item.y)} onChange={e => update({ y: +e.target.value })} disabled={!isAdmin} className="h-8 text-xs" /></div>
          </div>
          <div><Label className="text-xs">Rotation</Label><Input type="number" value={item.rotation} onChange={e => update({ rotation: +e.target.value })} disabled={!isAdmin} className="h-8 text-xs" /></div>
        </>
      )}

      {item.kind === "device" && (
        <>
          <div><Label className="text-xs">Name</Label><Input value={item.name} readOnly className="h-8 text-xs bg-muted" /></div>
          <div><Label className="text-xs">Type</Label><Input value={item.type || "unknown"} readOnly className="h-8 text-xs bg-muted" /></div>
          <div className="grid grid-cols-2 gap-2">
            <div><Label className="text-xs">X</Label><Input type="number" value={Math.round(item.x)} onChange={e => update({ x: +e.target.value })} disabled={!isAdmin} className="h-8 text-xs" /></div>
            <div><Label className="text-xs">Y</Label><Input type="number" value={Math.round(item.y)} onChange={e => update({ y: +e.target.value })} disabled={!isAdmin} className="h-8 text-xs" /></div>
          </div>
        </>
      )}

      {item.kind === "cable" && (
        <>
          <div><Label className="text-xs">Type</Label>
            <Select value={item.cable_type} onValueChange={v => update({ cable_type: v })} disabled={!isAdmin}>
              <SelectTrigger className="h-8 text-xs"><SelectValue /></SelectTrigger>
              <SelectContent>{CABLE_TYPES.map(t => <SelectItem key={t} value={t}>{t.toUpperCase()}</SelectItem>)}</SelectContent>
            </Select>
          </div>
          <div><Label className="text-xs">Color</Label>
            <Select value={item.cable_color} onValueChange={v => update({ cable_color: v })} disabled={!isAdmin}>
              <SelectTrigger className="h-8 text-xs"><SelectValue /></SelectTrigger>
              <SelectContent>{CABLE_COLORS.map(c => <SelectItem key={c} value={c}>{c}</SelectItem>)}</SelectContent>
            </Select>
          </div>
          <div><Label className="text-xs">Label</Label><Input value={item.label || ""} onChange={e => update({ label: e.target.value })} disabled={!isAdmin} className="h-8 text-xs" /></div>
          <div><Label className="text-xs">Length</Label><Input value={item.cable_length || ""} onChange={e => update({ cable_length: e.target.value })} disabled={!isAdmin} className="h-8 text-xs" /></div>
        </>
      )}

      {item.kind === "annotation" && (
        <>
          <div><Label className="text-xs">Text</Label><Input value={item.text} onChange={e => update({ text: e.target.value })} disabled={!isAdmin} className="h-8 text-xs" /></div>
          <div><Label className="text-xs">Font Size</Label><Input type="number" value={item.font_size} onChange={e => update({ font_size: +e.target.value })} disabled={!isAdmin} className="h-8 text-xs" /></div>
          <div><Label className="text-xs">Color</Label><Input value={item.color} onChange={e => update({ color: e.target.value })} disabled={!isAdmin} className="h-8 text-xs" /></div>
        </>
      )}
    </div>
  );
}
