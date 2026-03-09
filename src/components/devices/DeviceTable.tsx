import { useState, useMemo } from "react";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Pencil, Trash2, Activity, Timer, ArrowUp, ArrowDown, ArrowUpDown, SlidersHorizontal, ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from "lucide-react";
import { getIconComponent } from "@/components/devices/DeviceIconPicker";
import type { Tables } from "@/integrations/supabase/types";

type Device = Tables<"devices">;

type SortKey = "name" | "ip_address" | "type" | "monitor_method" | "status" | "last_latency" | "ping_interval";
type SortDir = "asc" | "desc";

const statusOrder: Record<string, number> = { online: 0, warning: 1, critical: 2, offline: 3, unknown: 4 };

const statusVariant = (s: string | null) => {
  switch (s) {
    case "online": return "default" as const;
    case "warning": return "secondary" as const;
    case "critical": return "destructive" as const;
    case "offline": return "outline" as const;
    default: return "secondary" as const;
  }
};

const statusColor = (s: string | null) => {
  switch (s) {
    case "online": return "bg-success text-success-foreground";
    case "warning": return "bg-warning text-warning-foreground";
    case "critical": return "bg-destructive text-destructive-foreground";
    default: return "";
  }
};

type ColumnId = "icon" | "name" | "ip_address" | "type" | "method" | "status" | "latency" | "interval";

const COLUMNS: { id: ColumnId; label: string }[] = [
  { id: "icon", label: "Icon" },
  { id: "name", label: "Name" },
  { id: "ip_address", label: "IP / Host" },
  { id: "type", label: "Type" },
  { id: "method", label: "Method" },
  { id: "status", label: "Status" },
  { id: "latency", label: "Latency" },
  { id: "interval", label: "Interval" },
];

const STORAGE_KEY = "ampnm-device-columns";
const PAGE_SIZE_KEY = "ampnm-device-page-size";
const PAGE_SIZES = [10, 25, 50, 100];

function loadVisibleColumns(): Set<ColumnId> {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored) return new Set(JSON.parse(stored));
  } catch {}
  return new Set(COLUMNS.map(c => c.id));
}

function loadPageSize(): number {
  try {
    const stored = localStorage.getItem(PAGE_SIZE_KEY);
    if (stored) { const n = parseInt(stored); if (PAGE_SIZES.includes(n)) return n; }
  } catch {}
  return 25;
}

interface DeviceTableProps {
  devices: Device[];
  loading: boolean;
  search: string;
  selected: Set<string>;
  pingingIds: Set<string>;
  onToggleAll: () => void;
  onToggleOne: (id: string) => void;
  onPing: (id: string) => void;
  onEdit: (device: Device) => void;
  onDelete: (device: Device) => void;
  allSelected: boolean;
}

export function DeviceTable({ devices, loading, search, selected, pingingIds, onToggleAll, onToggleOne, onPing, onEdit, onDelete, allSelected }: DeviceTableProps) {
  const [sortKey, setSortKey] = useState<SortKey>("name");
  const [sortDir, setSortDir] = useState<SortDir>("asc");
  const [visibleCols, setVisibleCols] = useState<Set<ColumnId>>(loadVisibleColumns);
  const [page, setPage] = useState(0);
  const [pageSize, setPageSize] = useState(loadPageSize);

  const toggleColumn = (col: ColumnId) => {
    setVisibleCols(prev => {
      const next = new Set(prev);
      if (next.has(col)) { if (next.size > 1) next.delete(col); } else next.add(col);
      localStorage.setItem(STORAGE_KEY, JSON.stringify([...next]));
      return next;
    });
  };

  const handleSort = (key: SortKey) => {
    if (sortKey === key) {
      setSortDir(d => d === "asc" ? "desc" : "asc");
    } else {
      setSortKey(key);
      setSortDir("asc");
    }
    setPage(0);
  };

  const sortedDevices = useMemo(() => {
    const mult = sortDir === "asc" ? 1 : -1;
    return [...devices].sort((a, b) => {
      let av: string | number | null, bv: string | number | null;
      switch (sortKey) {
        case "name": av = a.name.toLowerCase(); bv = b.name.toLowerCase(); break;
        case "ip_address": av = a.ip_address?.toLowerCase() ?? ""; bv = b.ip_address?.toLowerCase() ?? ""; break;
        case "type": av = (a.type ?? "").toLowerCase(); bv = (b.type ?? "").toLowerCase(); break;
        case "monitor_method": av = (a.monitor_method ?? "").toLowerCase(); bv = (b.monitor_method ?? "").toLowerCase(); break;
        case "status": av = statusOrder[a.status ?? "unknown"] ?? 4; bv = statusOrder[b.status ?? "unknown"] ?? 4; break;
        case "last_latency": av = a.last_latency ?? 99999; bv = b.last_latency ?? 99999; break;
        case "ping_interval": av = a.ping_interval ?? 300; bv = b.ping_interval ?? 300; break;
        default: av = ""; bv = "";
      }
      if (av < bv) return -1 * mult;
      if (av > bv) return 1 * mult;
      return 0;
    });
  }, [devices, sortKey, sortDir]);

  // Pagination
  const totalPages = Math.max(1, Math.ceil(sortedDevices.length / pageSize));
  const safePage = Math.min(page, totalPages - 1);
  const pagedDevices = sortedDevices.slice(safePage * pageSize, (safePage + 1) * pageSize);
  const startRow = sortedDevices.length === 0 ? 0 : safePage * pageSize + 1;
  const endRow = Math.min((safePage + 1) * pageSize, sortedDevices.length);

  const handlePageSizeChange = (value: string) => {
    const size = parseInt(value);
    setPageSize(size);
    setPage(0);
    localStorage.setItem(PAGE_SIZE_KEY, String(size));
  };

  // Reset page when devices change (e.g. filter)
  useMemo(() => { if (page >= totalPages) setPage(Math.max(0, totalPages - 1)); }, [devices.length, pageSize]);

  const show = (col: ColumnId) => visibleCols.has(col);
  const visibleCount = 2 + visibleCols.size;

  const SortIcon = ({ col }: { col: SortKey }) => {
    if (sortKey !== col) return <ArrowUpDown className="h-3 w-3 ml-1 opacity-40" />;
    return sortDir === "asc" ? <ArrowUp className="h-3 w-3 ml-1" /> : <ArrowDown className="h-3 w-3 ml-1" />;
  };

  const SortableHead = ({ col, children, className }: { col: SortKey; children: React.ReactNode; className?: string }) => (
    <TableHead className={className}>
      <button className="flex items-center gap-0 hover:text-foreground transition-colors" onClick={() => handleSort(col)}>
        {children}
        <SortIcon col={col} />
      </button>
    </TableHead>
  );

  return (
    <div className="space-y-2">
      <div className="flex justify-end">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" size="sm">
              <SlidersHorizontal className="h-4 w-4 mr-1" />
              Columns
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-44">
            <DropdownMenuLabel>Toggle columns</DropdownMenuLabel>
            <DropdownMenuSeparator />
            {COLUMNS.map(col => (
              <DropdownMenuCheckboxItem key={col.id} checked={visibleCols.has(col.id)} onCheckedChange={() => toggleColumn(col.id)}>
                {col.label}
              </DropdownMenuCheckboxItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-10">
              <Checkbox checked={allSelected} onCheckedChange={onToggleAll} aria-label="Select all" />
            </TableHead>
            {show("icon") && <TableHead className="w-10">Icon</TableHead>}
            {show("name") && <SortableHead col="name">Name</SortableHead>}
            {show("ip_address") && <SortableHead col="ip_address">IP / Host</SortableHead>}
            {show("type") && <SortableHead col="type">Type</SortableHead>}
            {show("method") && <SortableHead col="monitor_method">Method</SortableHead>}
            {show("status") && <SortableHead col="status">Status</SortableHead>}
            {show("latency") && <SortableHead col="last_latency">Latency</SortableHead>}
            {show("interval") && <SortableHead col="ping_interval">Interval</SortableHead>}
            <TableHead className="text-right">Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {loading ? (
            <TableRow>
              <TableCell colSpan={visibleCount} className="text-center py-8 text-muted-foreground">Loading devices...</TableCell>
            </TableRow>
          ) : pagedDevices.length === 0 ? (
            <TableRow>
              <TableCell colSpan={visibleCount} className="text-center py-8 text-muted-foreground">
                {search ? "No devices match your search." : 'No devices configured. Click "Add Device" to get started.'}
              </TableCell>
            </TableRow>
          ) : (
            pagedDevices.map(device => (
              <TableRow key={device.id} className={selected.has(device.id) ? "bg-muted/50" : ""}>
                <TableCell>
                  <Checkbox checked={selected.has(device.id)} onCheckedChange={() => onToggleOne(device.id)} aria-label={`Select ${device.name}`} />
                </TableCell>
                {show("icon") && (
                  <TableCell>
                    {device.icon_url ? (
                      <img src={device.icon_url} alt={device.name} className="h-6 w-6 object-contain rounded" />
                    ) : (() => {
                      const DevIcon = getIconComponent(device.subchoice || device.type);
                      return <DevIcon className="h-5 w-5 text-muted-foreground" />;
                    })()}
                  </TableCell>
                )}
                {show("name") && <TableCell className="font-medium">{device.name}</TableCell>}
                {show("ip_address") && <TableCell className="font-mono text-sm">{device.ip_address || "—"}</TableCell>}
                {show("type") && <TableCell className="capitalize">{device.type || "server"}{device.subchoice ? ` (${device.subchoice})` : ""}</TableCell>}
                {show("method") && <TableCell className="capitalize">{device.monitor_method || "ping"}{device.check_port ? `:${device.check_port}` : ""}</TableCell>}
                {show("status") && (
                  <TableCell>
                    <Badge className={statusColor(device.status)} variant={statusVariant(device.status)}>
                      {device.status || "unknown"}
                    </Badge>
                  </TableCell>
                )}
                {show("latency") && <TableCell>{device.last_latency != null ? `${device.last_latency}ms` : "—"}</TableCell>}
                {show("interval") && (
                  <TableCell className="text-sm text-muted-foreground">
                    <span className="flex items-center gap-1">
                      <Timer className="h-3 w-3" />
                      {device.ping_interval ?? 300}s
                    </span>
                  </TableCell>
                )}
                <TableCell className="text-right">
                  <div className="flex justify-end gap-1">
                    {device.ip_address && (
                      <Button variant="ghost" size="icon" onClick={() => onPing(device.id)} disabled={pingingIds.has(device.id)} title="Ping device">
                        <Activity className={`h-4 w-4 ${pingingIds.has(device.id) ? "animate-spin" : ""}`} />
                      </Button>
                    )}
                    <Button variant="ghost" size="icon" onClick={() => onEdit(device)}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" onClick={() => onDelete(device)} className="text-destructive hover:text-destructive">
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {/* Pagination footer */}
      {!loading && sortedDevices.length > 0 && (
        <div className="flex items-center justify-between px-4 py-3">
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <span>Rows per page</span>
            <Select value={String(pageSize)} onValueChange={handlePageSizeChange}>
              <SelectTrigger className="h-8 w-[70px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {PAGE_SIZES.map(s => (
                  <SelectItem key={s} value={String(s)}>{s}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="flex items-center gap-2">
            <span className="text-sm text-muted-foreground">
              {startRow}–{endRow} of {sortedDevices.length}
            </span>
            <div className="flex items-center gap-1">
              <Button variant="outline" size="icon" className="h-8 w-8" onClick={() => setPage(0)} disabled={safePage === 0}>
                <ChevronsLeft className="h-4 w-4" />
              </Button>
              <Button variant="outline" size="icon" className="h-8 w-8" onClick={() => setPage(p => Math.max(0, p - 1))} disabled={safePage === 0}>
                <ChevronLeft className="h-4 w-4" />
              </Button>
              <Button variant="outline" size="icon" className="h-8 w-8" onClick={() => setPage(p => Math.min(totalPages - 1, p + 1))} disabled={safePage >= totalPages - 1}>
                <ChevronRight className="h-4 w-4" />
              </Button>
              <Button variant="outline" size="icon" className="h-8 w-8" onClick={() => setPage(totalPages - 1)} disabled={safePage >= totalPages - 1}>
                <ChevronsRight className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
