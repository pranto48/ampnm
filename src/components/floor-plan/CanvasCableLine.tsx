import { memo } from "react";

const cableColorHex = (c: string) => {
  const m: Record<string, string> = {
    blue: "#3b82f6", red: "#ef4444", green: "#22c55e", yellow: "#eab308",
    orange: "#f97316", white: "#e2e8f0", gray: "#64748b", purple: "#a855f7", black: "#1e293b",
  };
  return m[c] || "#64748b";
};

interface CanvasCableLineProps {
  id: string;
  x1: number; y1: number;
  x2: number; y2: number;
  cableColor: string;
  cableType: string;
  label: string | null;
  selected: boolean;
  onMouseDown: (e: React.MouseEvent) => void;
}

export const CanvasCableLine = memo(function CanvasCableLine({
  x1, y1, x2, y2, cableColor, cableType, label, selected, onMouseDown,
}: CanvasCableLineProps) {
  const color = cableColorHex(cableColor);
  const mx = (x1 + x2) / 2;
  const my = (y1 + y2) / 2;
  const isFiber = cableType.startsWith("fiber");

  return (
    <g onMouseDown={onMouseDown} style={{ cursor: "pointer" }}>
      {/* Hit area */}
      <line x1={x1} y1={y1} x2={x2} y2={y2} stroke="transparent" strokeWidth={12} />
      {/* Cable line */}
      <line
        x1={x1} y1={y1} x2={x2} y2={y2}
        stroke={color}
        strokeWidth={selected ? 3 : 2}
        strokeDasharray={isFiber ? "8 4" : "none"}
        strokeLinecap="round"
      />
      {/* Endpoints */}
      <circle cx={x1} cy={y1} r={3} fill={color} />
      <circle cx={x2} cy={y2} r={3} fill={color} />
      {/* Label */}
      {label && (
        <g transform={`translate(${mx}, ${my})`}>
          <rect x={-30} y={-9} width={60} height={18} rx={3} className="fill-card" opacity={0.85} />
          <text textAnchor="middle" y={4} className="fill-foreground" fontSize={9}>{label}</text>
        </g>
      )}
      {selected && (
        <>
          <circle cx={x1} cy={y1} r={6} fill="none" className="stroke-primary" strokeWidth={2} />
          <circle cx={x2} cy={y2} r={6} fill="none" className="stroke-primary" strokeWidth={2} />
        </>
      )}
    </g>
  );
});
