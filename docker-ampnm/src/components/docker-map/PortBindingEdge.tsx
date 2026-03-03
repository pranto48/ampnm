import { BaseEdge, getSmoothStepPath, type EdgeProps } from "@xyflow/react";

export interface PortBindingEdgeData {
  portLabel?: string;
  sourcePort?: string;
  targetPort?: string;
}

const PortBindingEdge = ({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourcePosition,
  targetPosition,
  data,
  style,
}: EdgeProps) => {
  const d = data as unknown as PortBindingEdgeData | undefined;

  const [edgePath, labelX, labelY] = getSmoothStepPath({
    sourceX,
    sourceY,
    sourcePosition,
    targetX,
    targetY,
    targetPosition,
    borderRadius: 12,
  });

  return (
    <>
      <BaseEdge
        id={id}
        path={edgePath}
        style={{
          stroke: "hsl(var(--muted-foreground))",
          strokeWidth: 1.5,
          strokeDasharray: "6 3",
          ...style,
        }}
      />
      {(d?.portLabel || (d?.sourcePort && d?.targetPort)) && (
        <foreignObject
          x={labelX - 70}
          y={labelY - 12}
          width={140}
          height={24}
          requiredExtensions="http://www.w3.org/1999/xhtml"
        >
          <div className="flex items-center justify-center">
            <span className="text-[9px] font-mono bg-muted/90 backdrop-blur-sm text-muted-foreground border border-border rounded px-2 py-0.5 whitespace-nowrap shadow-sm">
              {d?.sourcePort && d?.targetPort
                ? `${d.sourcePort} ↔ ${d.targetPort}`
                : d?.portLabel}
            </span>
          </div>
        </foreignObject>
      )}
    </>
  );
};

export default PortBindingEdge;
