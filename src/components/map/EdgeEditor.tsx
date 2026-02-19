import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Label } from "@/components/ui/label";
import { useState } from "react";

const connectionTypes = [
  { value: "cat5", label: "🔌 CAT5 Cable", color: "#a78bfa" },
  { value: "fiber", label: "💡 Fiber Optic", color: "#f97316" },
  { value: "wifi", label: "📡 WiFi", color: "#38bdf8" },
  { value: "radio", label: "📻 Radio", color: "#84cc16" },
  { value: "lan", label: "🌐 LAN", color: "#60a5fa" },
  { value: "logical-tunneling", label: "🔒 Tunnel", color: "#c084fc" },
];

interface EdgeEditorProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  currentType: string;
  onSave: (type: string) => void;
  onDelete: () => void;
}

export function EdgeEditor({ open, onOpenChange, currentType, onSave, onDelete }: EdgeEditorProps) {
  const [type, setType] = useState(currentType);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[360px]">
        <DialogHeader>
          <DialogTitle>Edit Connection</DialogTitle>
        </DialogHeader>
        <div className="space-y-3 py-2">
          <Label>Connection Type</Label>
          <Select value={type} onValueChange={setType}>
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent>
              {connectionTypes.map((ct) => (
                <SelectItem key={ct.value} value={ct.value}>
                  <span className="flex items-center gap-2">
                    <span className="h-3 w-3 rounded-full" style={{ background: ct.color }} />
                    {ct.label}
                  </span>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <DialogFooter className="gap-2">
          <Button variant="destructive" size="sm" onClick={onDelete}>Delete</Button>
          <Button size="sm" onClick={() => onSave(type)}>Save</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export const edgeColorMap: Record<string, string> = Object.fromEntries(
  connectionTypes.map((ct) => [ct.value, ct.color])
);
