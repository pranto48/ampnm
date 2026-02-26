import { useState } from "react";
import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Download } from "lucide-react";
import { format } from "date-fns";

interface HistoryPoint {
  hostname: string;
  cpu_usage: number | null;
  memory_usage: number | null;
  memory_total: number | null;
  disk_usage: number | null;
  disk_total: number | null;
  network_in: number | null;
  network_out: number | null;
  gpu_usage: number | null;
  recorded_at: string;
}

interface Props {
  history: HistoryPoint[];
  hostname: string;
}

export function ExportButton({ history, hostname }: Props) {
  const exportCSV = () => {
    const headers = ["timestamp", "cpu_usage", "memory_usage", "memory_total", "disk_usage", "disk_total", "network_in", "network_out", "gpu_usage"];
    const rows = history.map((h) =>
      [h.recorded_at, h.cpu_usage, h.memory_usage, h.memory_total, h.disk_usage, h.disk_total, h.network_in, h.network_out, h.gpu_usage].join(",")
    );
    const csv = [headers.join(","), ...rows].join("\n");
    downloadFile(csv, `${hostname}_metrics_${format(new Date(), "yyyyMMdd")}.csv`, "text/csv");
  };

  const exportJSON = () => {
    const json = JSON.stringify(history, null, 2);
    downloadFile(json, `${hostname}_metrics_${format(new Date(), "yyyyMMdd")}.json`, "application/json");
  };

  const downloadFile = (content: string, filename: string, type: string) => {
    const blob = new Blob([content], { type });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="sm" className="gap-1.5" disabled={history.length === 0}>
          <Download className="h-4 w-4" /> Export
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent>
        <DropdownMenuItem onClick={exportCSV}>Export as CSV</DropdownMenuItem>
        <DropdownMenuItem onClick={exportJSON}>Export as JSON</DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
