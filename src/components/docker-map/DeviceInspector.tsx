import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from "@/components/ui/sheet";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { Server, Network } from "lucide-react";
import DevicePortGrid, { type DevicePort } from "./DevicePortGrid";

export interface DeviceInspectorData {
  label: string;
  deviceType: "server" | "switch" | "router" | "firewall";
  ip?: string;
  os?: string;
  driver?: string;
  subnet?: string;
  gateway?: string;
  ports: DevicePort[];
}

interface DeviceInspectorProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  device: DeviceInspectorData | null;
  onTogglePortStatus?: (portId: string) => void;
}

const statusCycle: Record<DevicePort["status"], DevicePort["status"]> = {
  up: "down",
  down: "disabled",
  disabled: "up",
};

const DeviceInspector = ({ open, onOpenChange, device, onTogglePortStatus }: DeviceInspectorProps) => {
  if (!device) return null;

  const isNetwork = !!device.driver;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="overflow-y-auto sm:max-w-md">
        <SheetHeader>
          <SheetTitle className="flex items-center gap-2">
            {isNetwork ? (
              <Network className="h-5 w-5 text-cyan-400" />
            ) : (
              <Server className="h-5 w-5 text-blue-400" />
            )}
            {device.label}
          </SheetTitle>
          <SheetDescription>
            {device.deviceType.charAt(0).toUpperCase() + device.deviceType.slice(1)} · {device.ports.length} ports
          </SheetDescription>
        </SheetHeader>

        <div className="mt-6 space-y-5">
          {/* Device info */}
          <div className="space-y-2">
            {device.ip && <DetailRow label="IP Address" value={device.ip} mono />}
            {device.os && <DetailRow label="OS" value={device.os} />}
            {device.driver && <DetailRow label="Driver" value={device.driver} />}
            {device.subnet && <DetailRow label="Subnet" value={device.subnet} mono />}
            {device.gateway && <DetailRow label="Gateway" value={device.gateway} mono />}
          </div>

          <Separator />

          {/* Port summary */}
          <div className="flex gap-4 text-xs">
            <div className="flex items-center gap-1.5">
              <span className="h-2 w-2 rounded-full bg-green-500" /> {device.ports.filter((p) => p.status === "up").length} Up
            </div>
            <div className="flex items-center gap-1.5">
              <span className="h-2 w-2 rounded-full bg-red-500" /> {device.ports.filter((p) => p.status === "down").length} Down
            </div>
            <div className="flex items-center gap-1.5">
              <span className="h-2 w-2 rounded-full bg-muted-foreground/40" /> {device.ports.filter((p) => p.status === "disabled").length} Disabled
            </div>
          </div>

          {/* Visual port grid */}
          <div className="p-3 bg-muted/30 rounded-lg border border-border">
            <DevicePortGrid
              ports={device.ports}
              onPortClick={(port) => onTogglePortStatus?.(port.id)}
            />
          </div>

          <Separator />

          {/* Port details table */}
          <div className="rounded-md border">
            <table className="w-full text-xs">
              <thead>
                <tr className="border-b bg-muted/50">
                  <th className="text-left px-3 py-1.5 font-medium text-muted-foreground">Port</th>
                  <th className="text-left px-3 py-1.5 font-medium text-muted-foreground">Type</th>
                  <th className="text-left px-3 py-1.5 font-medium text-muted-foreground">Speed</th>
                  <th className="text-left px-3 py-1.5 font-medium text-muted-foreground">Status</th>
                  <th className="text-left px-3 py-1.5 font-medium text-muted-foreground">Connected To</th>
                </tr>
              </thead>
              <tbody>
                {device.ports.map((p) => (
                  <tr key={p.id} className="border-b last:border-0">
                    <td className="px-3 py-1.5 font-mono font-semibold">{p.name}</td>
                    <td className="px-3 py-1.5 capitalize">{p.type}</td>
                    <td className="px-3 py-1.5 font-mono">{p.speed || "Auto"}</td>
                    <td className="px-3 py-1.5">
                      <Badge
                        variant={p.status === "up" ? "default" : p.status === "down" ? "destructive" : "secondary"}
                        className="text-[10px] px-1.5 py-0"
                      >
                        {p.status}
                      </Badge>
                    </td>
                    <td className="px-3 py-1.5 font-mono text-blue-400">{p.connectedTo || "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
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

export default DeviceInspector;
