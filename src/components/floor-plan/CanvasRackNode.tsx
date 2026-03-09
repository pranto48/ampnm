import { memo } from "react";

interface CanvasRackNodeProps {
  id: string;
  x: number;
  y: number;
  name: string;
  rackUnits: number;
  rotation: number;
  labelVisible: boolean;
  selected: boolean;
  onMouseDown: (e: React.MouseEvent) => void;
}

export const CanvasRackNode = memo(function CanvasRackNode({
  x, y, name, rackUnits, rotation, labelVisible, selected, onMouseDown,
}: CanvasRackNodeProps) {
  const w = 60;
  const h = Math.max(40, Math.min(rackUnits * 2.5, 120));

  return (
    <g
      transform={`translate(${x}, ${y}) rotate(${rotation})`}
      onMouseDown={onMouseDown}
      style={{ cursor: "pointer" }}
    >
      {/* Rack body */}
      <rect
        x={-w / 2} y={-h / 2} width={w} height={h}
        rx={4}
        className="fill-secondary stroke-primary"
        strokeWidth={selected ? 2.5 : 1.5}
        strokeDasharray={selected ? "none" : "4 2"}
      />
      {/* Rack unit lines */}
      {Array.from({ length: Math.min(rackUnits, 20) }, (_, i) => {
        const lineY = -h / 2 + ((i + 1) / (Math.min(rackUnits, 20) + 1)) * h;
        return (
          <line
            key={i}
            x1={-w / 2 + 4} x2={w / 2 - 4}
            y1={lineY} y2={lineY}
            className="stroke-muted-foreground" strokeWidth={0.5} opacity={0.3}
          />
        );
      })}
      {/* Icon */}
      <text x={0} y={-2} textAnchor="middle" className="fill-primary" fontSize={16}>⊞</text>
      {/* Unit count */}
      <text x={0} y={12} textAnchor="middle" className="fill-muted-foreground" fontSize={9}>
        {rackUnits}U
      </text>
      {/* Name label */}
      {labelVisible && (
        <text x={0} y={h / 2 + 14} textAnchor="middle" className="fill-foreground" fontSize={11} fontWeight={500}>
          {name}
        </text>
      )}
      {/* Selection handles */}
      {selected && (
        <>
          <rect x={-w / 2 - 3} y={-h / 2 - 3} width={6} height={6} rx={1} className="fill-primary" />
          <rect x={w / 2 - 3} y={-h / 2 - 3} width={6} height={6} rx={1} className="fill-primary" />
          <rect x={-w / 2 - 3} y={h / 2 - 3} width={6} height={6} rx={1} className="fill-primary" />
          <rect x={w / 2 - 3} y={h / 2 - 3} width={6} height={6} rx={1} className="fill-primary" />
        </>
      )}
    </g>
  );
});
