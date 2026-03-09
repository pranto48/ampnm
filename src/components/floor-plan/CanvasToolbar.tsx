import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import {
  MousePointer2, Server, Monitor, Cable, Type,
  ZoomIn, ZoomOut, Maximize, Upload, Grid3X3, Trash2, Download
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
  canvasRef?: React.RefObject<SVGSVGElement | null>;
  planName?: string;
}

const tools: { mode: ToolMode; icon: typeof MousePointer2; label: string }[] = [
  { mode: "select", icon: MousePointer2, label: "Select / Move" },
  { mode: "add-rack", icon: Server, label: "Add Rack" },
  { mode: "add-device", icon: Monitor, label: "Add Device" },
  { mode: "draw-cable", icon: Cable, label: "Draw Cable" },
  { mode: "add-label", icon: Type, label: "Add Label" },
];

function exportSVG(svg: SVGSVGElement, name: string) {
  const clone = svg.cloneNode(true) as SVGSVGElement;
  // Remove zoom indicator text
  const texts = clone.querySelectorAll(':scope > text');
  texts.forEach(t => t.remove());
  // Reset transform to show full canvas
  const g = clone.querySelector('g');
  if (g) g.setAttribute('transform', 'scale(1)');
  const serializer = new XMLSerializer();
  const svgStr = serializer.serializeToString(clone);
  const blob = new Blob([svgStr], { type: 'image/svg+xml' });
  downloadBlob(blob, `${name}.svg`);
}

function exportPNG(svg: SVGSVGElement, name: string) {
  const clone = svg.cloneNode(true) as SVGSVGElement;
  const texts = clone.querySelectorAll(':scope > text');
  texts.forEach(t => t.remove());
  const g = clone.querySelector('g');
  if (g) g.setAttribute('transform', 'scale(1)');
  const serializer = new XMLSerializer();
  const svgStr = serializer.serializeToString(clone);
  const canvas = document.createElement('canvas');
  const w = parseInt(clone.getAttribute('width') || '1200') || 1200;
  const h = parseInt(clone.getAttribute('height') || '800') || 800;
  canvas.width = w * 2;
  canvas.height = h * 2;
  const ctx = canvas.getContext('2d');
  if (!ctx) return;
  const img = new Image();
  const svgBlob = new Blob([svgStr], { type: 'image/svg+xml;charset=utf-8' });
  const url = URL.createObjectURL(svgBlob);
  img.onload = () => {
    ctx.fillStyle = '#1a1a2e';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    URL.revokeObjectURL(url);
    canvas.toBlob(blob => {
      if (blob) downloadBlob(blob, `${name}.png`);
    }, 'image/png');
  };
  img.src = url;
}

function downloadBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

export function CanvasToolbar({
  activeTool, onToolChange, onZoomIn, onZoomOut, onZoomReset,
  onUpload, snapToGrid, onToggleSnap, isAdmin, onDeleteSelected, hasSelection,
  canvasRef, planName,
}: CanvasToolbarProps) {
  const handleExport = (format: 'svg' | 'png') => {
    const svg = canvasRef?.current;
    if (!svg) return;
    const name = (planName || 'floor-plan').replace(/\s+/g, '-').toLowerCase();
    if (format === 'svg') exportSVG(svg, name);
    else exportPNG(svg, name);
  };
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

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="ghost" size="icon" className="h-9 w-9" title="Export Canvas">
            <Download className="h-4 w-4" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent side="right">
          <DropdownMenuItem onClick={() => handleExport('png')}>Export as PNG</DropdownMenuItem>
          <DropdownMenuItem onClick={() => handleExport('svg')}>Export as SVG</DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
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
