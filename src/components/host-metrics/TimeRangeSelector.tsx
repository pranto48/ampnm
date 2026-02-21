import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Calendar } from "@/components/ui/calendar";
import { CalendarIcon } from "lucide-react";
import { format } from "date-fns";
import { cn } from "@/lib/utils";

const PRESETS = [
  { label: "1H", hours: 1 },
  { label: "6H", hours: 6 },
  { label: "24H", hours: 24 },
  { label: "3D", hours: 72 },
  { label: "7D", hours: 168 },
];

interface Props {
  value: { hours?: number; from?: Date; to?: Date };
  onChange: (val: { hours?: number; from?: Date; to?: Date }) => void;
}

export function TimeRangeSelector({ value, onChange }: Props) {
  const [fromDate, setFromDate] = useState<Date | undefined>(value.from);
  const [toDate, setToDate] = useState<Date | undefined>(value.to);

  const applyCustom = () => {
    if (fromDate && toDate) {
      onChange({ from: fromDate, to: toDate });
    }
  };

  return (
    <div className="flex items-center gap-1 flex-wrap">
      {PRESETS.map((p) => (
        <Button
          key={p.label}
          size="sm"
          variant={value.hours === p.hours ? "default" : "outline"}
          className="h-7 px-2.5 text-xs"
          onClick={() => onChange({ hours: p.hours })}
        >
          {p.label}
        </Button>
      ))}

      <Popover>
        <PopoverTrigger asChild>
          <Button size="sm" variant={value.from ? "default" : "outline"} className="h-7 px-2.5 text-xs gap-1">
            <CalendarIcon className="h-3 w-3" />
            {value.from && value.to
              ? `${format(value.from, "MM/dd")} – ${format(value.to, "MM/dd")}`
              : "Custom"}
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-auto p-3" align="end">
          <div className="space-y-3">
            <div className="grid grid-cols-2 gap-2">
              <div>
                <p className="text-xs text-muted-foreground mb-1">From</p>
                <Calendar
                  mode="single"
                  selected={fromDate}
                  onSelect={setFromDate}
                  className={cn("p-2 pointer-events-auto")}
                  disabled={(d) => d > new Date()}
                />
              </div>
              <div>
                <p className="text-xs text-muted-foreground mb-1">To</p>
                <Calendar
                  mode="single"
                  selected={toDate}
                  onSelect={setToDate}
                  className={cn("p-2 pointer-events-auto")}
                  disabled={(d) => d > new Date()}
                />
              </div>
            </div>
            <Button size="sm" className="w-full" onClick={applyCustom} disabled={!fromDate || !toDate}>
              Apply Range
            </Button>
          </div>
        </PopoverContent>
      </Popover>
    </div>
  );
}
