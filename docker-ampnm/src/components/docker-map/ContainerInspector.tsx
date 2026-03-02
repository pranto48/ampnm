import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from "@/components/ui/sheet";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { Box, Network, Globe } from "lucide-react";
import type { ContainerData } from "./ContainerNode";

interface ContainerInspectorProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  container: ContainerData | null;
}

const ContainerInspector = ({ open, onOpenChange, container }: ContainerInspectorProps) => {
  if (!container) return null;

  const stateVariant =
    container.state === "running" ? "default" :
    container.state === "paused" ? "secondary" : "destructive";

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="overflow-y-auto">
        <SheetHeader>
          <SheetTitle className="flex items-center gap-2">
            <Box className="h-5 w-5 text-emerald-400" />
            {container.label}
          </SheetTitle>
          <SheetDescription>Container details &amp; LLD info</SheetDescription>
        </SheetHeader>

        <div className="mt-6 space-y-5">
          {/* Status */}
          <div className="flex items-center justify-between">
            <span className="text-sm text-muted-foreground">State</span>
            <Badge variant={stateVariant} className="capitalize">{container.state}</Badge>
          </div>

          <Separator />

          {/* Details grid */}
          <div className="space-y-3">
            <DetailRow label="Container ID" value={container.containerId} mono />
            <DetailRow label="Image" value={container.image} mono />
            <DetailRow label="Internal IP" value={container.internalIp} mono />
          </div>

          <Separator />

          {/* Networks */}
          <div>
            <h4 className="text-sm font-medium text-foreground mb-2 flex items-center gap-1.5">
              <Network className="h-4 w-4 text-cyan-400" /> Attached Networks
            </h4>
            <div className="flex flex-wrap gap-1.5">
              {container.networks.map((n) => (
                <Badge key={n} variant="outline" className="text-xs font-mono">{n}</Badge>
              ))}
            </div>
          </div>

          <Separator />

          {/* Ports */}
          <div>
            <h4 className="text-sm font-medium text-foreground mb-2 flex items-center gap-1.5">
              <Globe className="h-4 w-4 text-blue-400" /> Exposed Ports
            </h4>
            {container.ports.length === 0 ? (
              <p className="text-xs text-muted-foreground">No ports exposed</p>
            ) : (
              <div className="rounded-md border">
                <table className="w-full text-xs">
                  <thead>
                    <tr className="border-b bg-muted/50">
                      <th className="text-left px-3 py-1.5 font-medium text-muted-foreground">External</th>
                      <th className="text-left px-3 py-1.5 font-medium text-muted-foreground">Internal</th>
                      <th className="text-left px-3 py-1.5 font-medium text-muted-foreground">Proto</th>
                    </tr>
                  </thead>
                  <tbody>
                    {container.ports.map((p, i) => (
                      <tr key={i} className="border-b last:border-0">
                        <td className="px-3 py-1.5 font-mono">{p.external}</td>
                        <td className="px-3 py-1.5 font-mono">{p.internal}</td>
                        <td className="px-3 py-1.5 font-mono uppercase">{p.protocol}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
};

const DetailRow = ({ label, value, mono }: { label: string; value: string; mono?: boolean }) => (
  <div className="flex items-start justify-between gap-4">
    <span className="text-sm text-muted-foreground shrink-0">{label}</span>
    <span className={`text-sm text-foreground text-right break-all ${mono ? "font-mono text-xs" : ""}`}>{value}</span>
  </div>
);

export default ContainerInspector;
