import { memo } from "react";

interface CanvasAnnotationProps {
  id: string;
  x: number;
  y: number;
  text: string;
  fontSize: number;
  color: string;
  type: string;
  width?: number | null;
  height?: number | null;
  selected: boolean;
  onMouseDown: (e: React.MouseEvent) => void;
}

export const CanvasAnnotation = memo(function CanvasAnnotation({
  x, y, text, fontSize, color, type, width, height, selected, onMouseDown,
}: CanvasAnnotationProps) {
  if (type === "zone" && width && height) {
    return (
      <g transform={`translate(${x}, ${y})`} onMouseDown={onMouseDown} style={{ cursor: "pointer" }}>
        <rect
          x={0} y={0} width={width} height={height}
          rx={6}
          fill={color}
          opacity={0.1}
          stroke={color}
          strokeWidth={selected ? 2.5 : 1.5}
          strokeDasharray="6 3"
        />
        <text x={8} y={20} fill={color} fontSize={fontSize} fontWeight={600}>{text}</text>
        {selected && (
          <>
            <rect x={-3} y={-3} width={6} height={6} rx={1} className="fill-primary" />
            <rect x={width - 3} y={-3} width={6} height={6} rx={1} className="fill-primary" />
            <rect x={-3} y={height - 3} width={6} height={6} rx={1} className="fill-primary" />
            <rect x={width - 3} y={height - 3} width={6} height={6} rx={1} className="fill-primary" />
          </>
        )}
      </g>
    );
  }

  return (
    <g transform={`translate(${x}, ${y})`} onMouseDown={onMouseDown} style={{ cursor: "pointer" }}>
      <text
        textAnchor="start"
        fill={color}
        fontSize={fontSize}
        fontWeight={500}
        className={selected ? "stroke-primary" : ""}
        strokeWidth={selected ? 0.5 : 0}
      >
        {text}
      </text>
      {selected && (
        <rect x={-4} y={-fontSize} width={text.length * fontSize * 0.6 + 8} height={fontSize + 8} fill="none" className="stroke-primary" strokeWidth={1.5} strokeDasharray="4 2" rx={2} />
      )}
    </g>
  );
});
