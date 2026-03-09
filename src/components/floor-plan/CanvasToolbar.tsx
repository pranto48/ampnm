import { Button } from "@/components/ui/button";
import {
  MousePointer2, Server, Monitor, Cable, Type,
  ZoomIn, ZoomOut, Maximize, Upload, Grid3X3, Trash2
} from "lucide-react";

export type ToolMode = "select" | "add-rack" | "add-device" | "draw-cable" | "add-label";

interface CanvasToolbarProps {
  activeTool: ToolMode;
  onToolChange: (tool: ToolMode) => void;
  onZoomIn: () => void;
  onZoomOut: () => void;
  onZoomReset: () => void;
  onUpload: () => void;
  snapToGrid: boolean;
  onToggleSnap: () => void;
  isAdmin: boolean;
  onDeleteSelected: () => void;
  hasSelection: boolean;
}

const tools: { mode: ToolMode; icon: typeof MousePointer2; label: string }[] = [
  { mode: "select", icon: MousePointer2, label: "Select / Move" },
  { mode: "add-rack", icon: Server, label: "Add Rack" },
  { mode: "add-device", icon: Monitor, label: "Add Device" },
  { mode: "draw-cable", icon: Cable, label: "Draw Cable" },
  { mode: "add-label", icon: Type, label: "Add Label" },
];

export function CanvasToolbar({
  activeTool, onToolChange, onZoomIn, onZoomOut, onZoomReset,
  onUpload, snapToGrid, onToggleSnap, isAdmin, onDeleteSelected, hasSelection,
}: CanvasToolbarProps) {
  return (
    <div className="flex flex-col gap-1 p-2 bg-card border border-border rounded-lg shadow-sm w-12">
      {tools.map(t => (
        <Button
          key={t.mode}
          variant={activeTool === t.mode ? "default" : "ghost"}
          size="icon"
          className="h-9 w-9"
          title={t.label}
          onClick={() => onToolChange(t.mode)}
          disabled={!isAdmin && t.mode !== "select"}
        >
          <t.icon className="h-4 w-4" />
        </Button>
      ))}

      <div className="border-t border-border my-1" />

      <Button variant="ghost" size="icon" className="h-9 w-9" title="Zoom In" onClick={onZoomIn}>
        <ZoomIn className="h-4 w-4" />
      </Button>
      <Button variant="ghost" size="icon" className="h-9 w-9" title="Zoom Out" onClick={onZoomOut}>
        <ZoomOut className="h-4 w-4" />
      </Button>
      <Button variant="ghost" size="icon" className="h-9 w-9" title="Reset View" onClick={onZoomReset}>
        <Maximize className="h-4 w-4" />
      </Button>

      <div className="border-t border-border my-1" />

      <Button
        variant={snapToGrid ? "default" : "ghost"}
        size="icon"
        className="h-9 w-9"
        title={`Grid Snap: ${snapToGrid ? "ON" : "OFF"}`}
        onClick={onToggleSnap}
      >
        <Grid3X3 className="h-4 w-4" />
      </Button>

      {isAdmin && (
        <>
          <Button variant="ghost" size="icon" className="h-9 w-9" title="Upload Floor Plan" onClick={onUpload}>
            <Upload className="h-4 w-4" />
          </Button>
          {hasSelection && (
            <Button variant="ghost" size="icon" className="h-9 w-9 text-destructive" title="Delete Selected" onClick={onDeleteSelected}>
              <Trash2 className="h-4 w-4" />
            </Button>
          )}
        </>
      )}
    </div>
  );
}
