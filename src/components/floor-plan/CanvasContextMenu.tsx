import { useEffect, useRef } from "react";
import { Pencil, Trash2, Copy } from "lucide-react";

export interface ContextMenuState {
  x: number;
  y: number;
  kind: string;
  id: string;
}

interface CanvasContextMenuProps {
  menu: ContextMenuState;
  onClose: () => void;
  onEdit: (kind: string, id: string) => void;
  onDelete: (kind: string, id: string) => void;
  onDuplicate: (kind: string, id: string) => void;
  isAdmin: boolean;
}

export function CanvasContextMenu({ menu, onClose, onEdit, onDelete, onDuplicate, isAdmin }: CanvasContextMenuProps) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) onClose();
    };
    document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, [onClose]);

  const items = [
    { label: "Edit", icon: Pencil, action: () => onEdit(menu.kind, menu.id), show: isAdmin },
    { label: "Duplicate", icon: Copy, action: () => onDuplicate(menu.kind, menu.id), show: isAdmin && menu.kind !== "cable" },
    { label: "Delete", icon: Trash2, action: () => onDelete(menu.kind, menu.id), show: isAdmin, destructive: true },
  ].filter(i => i.show);

  if (items.length === 0) return null;

  return (
    <div
      ref={ref}
      className="fixed z-50 min-w-[140px] bg-popover border border-border rounded-md shadow-md py-1 animate-in fade-in-0 zoom-in-95"
      style={{ left: menu.x, top: menu.y }}
    >
      {items.map(item => (
        <button
          key={item.label}
          className={`flex items-center gap-2 w-full px-3 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground transition-colors ${item.destructive ? "text-destructive" : "text-popover-foreground"}`}
          onClick={() => { item.action(); onClose(); }}
        >
          <item.icon className="h-3.5 w-3.5" />
          {item.label}
        </button>
      ))}
    </div>
  );
}
