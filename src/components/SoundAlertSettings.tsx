import { Volume2, VolumeX } from "lucide-react";
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import { Button } from "@/components/ui/button";

interface SoundPrefs {
  enabled: boolean;
  online: boolean;
  offline: boolean;
  warning: boolean;
  critical: boolean;
}

interface Props {
  prefs: SoundPrefs;
  onUpdate: (partial: Partial<SoundPrefs>) => void;
}

export function SoundAlertSettings({ prefs, onUpdate }: Props) {
  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button variant="outline" size="icon" title="Sound Alerts">
          {prefs.enabled ? (
            <Volume2 className="h-4 w-4" />
          ) : (
            <VolumeX className="h-4 w-4 text-muted-foreground" />
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-56 space-y-3" align="end">
        <div className="flex items-center justify-between">
          <Label htmlFor="sound-master" className="font-semibold text-sm">Sound Alerts</Label>
          <Switch
            id="sound-master"
            checked={prefs.enabled}
            onCheckedChange={(v) => onUpdate({ enabled: v })}
          />
        </div>
        <div className="space-y-2 pl-1">
          {(["online", "offline", "warning", "critical"] as const).map((s) => (
            <div key={s} className="flex items-center justify-between">
              <Label htmlFor={`sound-${s}`} className="text-xs capitalize text-muted-foreground">
                {s}
              </Label>
              <Switch
                id={`sound-${s}`}
                checked={prefs[s]}
                onCheckedChange={(v) => onUpdate({ [s]: v })}
                disabled={!prefs.enabled}
              />
            </div>
          ))}
        </div>
      </PopoverContent>
    </Popover>
  );
}
