import { useState, useRef, useCallback, useEffect, forwardRef, useImperativeHandle } from "react";
import { CanvasRackNode } from "./CanvasRackNode";
import { CanvasDeviceNode } from "./CanvasDeviceNode";
import { CanvasCableLine } from "./CanvasCableLine";
import { CanvasAnnotation } from "./CanvasAnnotation";
import type { ToolMode } from "./CanvasToolbar";
import type { SelectedItem } from "./PropertiesPanel";

interface RackData { id: string; name: string; x: number; y: number; rack_units: number; rotation: number; label_visible: boolean; }
interface DeviceData { id: string; name: string; type: string | null; x: number; y: number; }
interface CableData { id: string; cable_color: string; cable_type: string; label: string | null; source_id: string; dest_id: string; source_port: number; dest_port: number; }
interface AnnotationData { id: string; x: number; y: number; text: string; font_size: number; color: string; type: string; width: number | null; height: number | null; }

interface FloorPlanCanvasProps {
  backgroundUrl: string | null;
  width: number;
  height: number;
  racks: RackData[];
  devices: DeviceData[];
  cables: CableData[];
  annotations: AnnotationData[];
  activeTool: ToolMode;
  snapToGrid: boolean;
  selectedItem: SelectedItem | null;
  onSelectItem: (item: SelectedItem | null) => void;
  onMoveItem: (kind: string, id: string, x: number, y: number) => void;
  onCanvasClick: (x: number, y: number) => void;
  onCableEndpointClick: (kind: "rack" | "device", id: string) => void;
  zoom: number;
  panX: number;
  panY: number;
  onPanChange: (x: number, y: number) => void;
  onZoomChange: (z: number) => void;
  svgRef?: React.RefObject<SVGSVGElement | null>;
  onContextMenu?: (kind: string, id: string, clientX: number, clientY: number) => void;
}

// Resolve positions for cable endpoints
function findPos(id: string, racks: RackData[], devices: DeviceData[]): { x: number; y: number } | null {
  const r = racks.find(r => r.id === id);
  if (r) return { x: r.x, y: r.y };
  const d = devices.find(d => d.id === id);
  if (d) return { x: d.x, y: d.y };
  return null;
}

export function FloorPlanCanvas({
  backgroundUrl, width, height, racks, devices, cables, annotations,
  activeTool, snapToGrid, selectedItem, onSelectItem, onMoveItem,
  onCanvasClick, onCableEndpointClick, zoom, panX, panY, onPanChange, onZoomChange,
  svgRef: externalSvgRef, onContextMenu: onCtxMenu,
}: FloorPlanCanvasProps) {
  const internalRef = useRef<SVGSVGElement>(null);
  const svgRef = externalSvgRef || internalRef;
  const [isPanning, setIsPanning] = useState(false);
  const [panStart, setPanStart] = useState({ x: 0, y: 0 });
  const [dragging, setDragging] = useState<{ kind: string; id: string; offsetX: number; offsetY: number } | null>(null);

  const snap = (v: number) => snapToGrid ? Math.round(v / 20) * 20 : v;

  const svgPoint = useCallback((clientX: number, clientY: number) => {
    const svg = svgRef.current;
    if (!svg) return { x: 0, y: 0 };
    const rect = svg.getBoundingClientRect();
    return {
      x: (clientX - rect.left - panX) / zoom,
      y: (clientY - rect.top - panY) / zoom,
    };
  }, [zoom, panX, panY]);

  const handleWheel = useCallback((e: React.WheelEvent) => {
    e.preventDefault();
    const delta = e.deltaY > 0 ? 0.9 : 1.1;
    const newZoom = Math.max(0.2, Math.min(5, zoom * delta));
    onZoomChange(newZoom);
  }, [zoom, onZoomChange]);

  const handleMouseDown = (e: React.MouseEvent) => {
    if (e.button !== 0) return;
    // If clicking empty space in select/pan mode, start panning
    if (e.target === svgRef.current || (e.target as SVGElement).classList.contains("canvas-bg")) {
      if (activeTool === "select") {
        onSelectItem(null);
      }
      if (["select", "add-rack", "add-device", "add-label"].includes(activeTool)) {
        const pt = svgPoint(e.clientX, e.clientY);
        if (activeTool !== "select") {
          onCanvasClick(snap(pt.x), snap(pt.y));
          return;
        }
      }
      setIsPanning(true);
      setPanStart({ x: e.clientX - panX, y: e.clientY - panY });
    }
  };

  const handleMouseMove = (e: React.MouseEvent) => {
    if (isPanning) {
      onPanChange(e.clientX - panStart.x, e.clientY - panStart.y);
    }
    if (dragging) {
      const pt = svgPoint(e.clientX, e.clientY);
      onMoveItem(dragging.kind, dragging.id, snap(pt.x - dragging.offsetX), snap(pt.y - dragging.offsetY));
    }
  };

  const handleMouseUp = () => {
    setIsPanning(false);
    setDragging(null);
  };

  const startDrag = (kind: string, id: string, e: React.MouseEvent) => {
    e.stopPropagation();
    if (activeTool === "draw-cable" && (kind === "rack" || kind === "device")) {
      onCableEndpointClick(kind as "rack" | "device", id);
      return;
    }
    if (activeTool !== "select") return;

    // Select the item
    if (kind === "rack") {
      const r = racks.find(r => r.id === id);
      if (r) onSelectItem({ kind: "rack", id: r.id, name: r.name, rack_units: r.rack_units, x: r.x, y: r.y, rotation: r.rotation, label_visible: r.label_visible });
    } else if (kind === "device") {
      const d = devices.find(d => d.id === id);
      if (d) onSelectItem({ kind: "device", id: d.id, name: d.name, type: d.type, x: d.x, y: d.y });
    } else if (kind === "cable") {
      const c = cables.find(c => c.id === id);
      if (c) onSelectItem({ kind: "cable", id: c.id, cable_type: c.cable_type, cable_color: c.cable_color, cable_length: null, label: c.label, notes: null });
    } else if (kind === "annotation") {
      const a = annotations.find(a => a.id === id);
      if (a) onSelectItem({ kind: "annotation", id: a.id, text: a.text, font_size: a.font_size, color: a.color, type: a.type, width: a.width, height: a.height });
    }

    // Start dragging for movable items
    if (kind === "rack" || kind === "device" || kind === "annotation") {
      const pt = svgPoint(e.clientX, e.clientY);
      const item = kind === "rack" ? racks.find(r => r.id === id) :
                   kind === "device" ? devices.find(d => d.id === id) :
                   annotations.find(a => a.id === id);
      if (item) {
        setDragging({ kind, id, offsetX: pt.x - (item as any).x, offsetY: pt.y - (item as any).y });
      }
    }
  };

  // Cursor style based on tool
  const cursorStyle = activeTool === "select" ? (isPanning ? "grabbing" : "default") :
    activeTool === "draw-cable" ? "crosshair" : "cell";

  return (
    <svg
      ref={svgRef}
      className="w-full h-full bg-background rounded-lg border border-border"
      style={{ cursor: cursorStyle }}
      onWheel={handleWheel}
      onMouseDown={handleMouseDown}
      onMouseMove={handleMouseMove}
      onMouseUp={handleMouseUp}
      onMouseLeave={handleMouseUp}
    >
      <g transform={`translate(${panX}, ${panY}) scale(${zoom})`}>
        {/* Grid */}
        {snapToGrid && (
          <defs>
            <pattern id="grid" width={20} height={20} patternUnits="userSpaceOnUse">
              <path d="M 20 0 L 0 0 0 20" fill="none" className="stroke-border" strokeWidth={0.5} opacity={0.3} />
            </pattern>
          </defs>
        )}
        <rect
          className="canvas-bg fill-muted/20"
          x={0} y={0} width={width} height={height}
          fill={snapToGrid ? "url(#grid)" : undefined}
          rx={4}
        />

        {/* Background image */}
        {backgroundUrl && (
          <image
            href={backgroundUrl}
            x={0} y={0} width={width} height={height}
            preserveAspectRatio="xMidYMid meet"
            opacity={0.6}
            className="pointer-events-none"
          />
        )}

        {/* Annotations (back layer) */}
        {annotations.filter(a => a.type === "zone").map(a => (
          <CanvasAnnotation
            key={a.id} id={a.id} x={a.x} y={a.y} text={a.text}
            fontSize={a.font_size} color={a.color} type={a.type}
            width={a.width} height={a.height}
            selected={selectedItem?.kind === "annotation" && selectedItem.id === a.id}
            onMouseDown={e => startDrag("annotation", a.id, e)}
          />
        ))}

        {/* Cable runs */}
        {cables.map(cable => {
          const srcPos = findPos(cable.source_id, racks, devices);
          const dstPos = findPos(cable.dest_id, racks, devices);
          if (!srcPos || !dstPos) return null;
          return (
            <CanvasCableLine
              key={cable.id}
              id={cable.id}
              x1={srcPos.x} y1={srcPos.y}
              x2={dstPos.x} y2={dstPos.y}
              cableColor={cable.cable_color}
              cableType={cable.cable_type}
              label={cable.label}
              selected={selectedItem?.kind === "cable" && selectedItem.id === cable.id}
              onMouseDown={e => startDrag("cable", cable.id, e)}
            />
          );
        })}

        {/* Racks */}
        {racks.map(rack => (
          <CanvasRackNode
            key={rack.id}
            id={rack.id} x={rack.x} y={rack.y} name={rack.name}
            rackUnits={rack.rack_units} rotation={rack.rotation}
            labelVisible={rack.label_visible}
            selected={selectedItem?.kind === "rack" && selectedItem.id === rack.id}
            onMouseDown={e => startDrag("rack", rack.id, e)}
          />
        ))}

        {/* Devices */}
        {devices.map(dev => (
          <CanvasDeviceNode
            key={dev.id}
            {...dev}
            selected={selectedItem?.kind === "device" && selectedItem.id === dev.id}
            onMouseDown={e => startDrag("device", dev.id, e)}
          />
        ))}

        {/* Label annotations (front layer) */}
        {annotations.filter(a => a.type === "label").map(a => (
          <CanvasAnnotation
            key={a.id} id={a.id} x={a.x} y={a.y} text={a.text}
            fontSize={a.font_size} color={a.color} type={a.type}
            width={a.width} height={a.height}
            selected={selectedItem?.kind === "annotation" && selectedItem.id === a.id}
            onMouseDown={e => startDrag("annotation", a.id, e)}
          />
        ))}
      </g>

      {/* Zoom indicator */}
      <text x={8} y={20} className="fill-muted-foreground" fontSize={11} fontFamily="monospace">
        {Math.round(zoom * 100)}%
      </text>
    </svg>
  );
}
