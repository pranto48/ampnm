import { memo } from "react";

interface CanvasDeviceNodeProps {
  id: string;
  x: number;
  y: number;
  name: string;
  type: string | null;
  selected: boolean;
  onMouseDown: (e: React.MouseEvent) => void;
}

const typeIcon: Record<string, string> = {
  server: "🖥", switch: "🔀", router: "🌐", firewall: "🛡",
  printer: "🖨", camera: "📷", phone: "📞", ap: "📡",
  workstation: "💻", default: "📦",
};

export const CanvasDeviceNode = memo(function CanvasDeviceNode({
  x, y, name, type, selected, onMouseDown,
}: CanvasDeviceNodeProps) {
  const r = 20;
  const icon = typeIcon[type || ""] || typeIcon.default;

  return (
    <g
      transform={`translate(${x}, ${y})`}
      onMouseDown={onMouseDown}
      style={{ cursor: "pointer" }}
    >
      <circle
        r={r}
        className={`fill-card stroke-primary`}
        strokeWidth={selected ? 2.5 : 1.5}
      />
      <text x={0} y={5} textAnchor="middle" fontSize={16}>{icon}</text>
      <text x={0} y={r + 14} textAnchor="middle" className="fill-foreground" fontSize={10} fontWeight={500}>
        {name}
      </text>
      {selected && (
        <circle r={r + 4} fill="none" className="stroke-primary" strokeWidth={1} strokeDasharray="4 2" />
      )}
    </g>
  );
});
