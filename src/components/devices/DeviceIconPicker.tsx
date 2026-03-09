import { useState, useMemo } from "react";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import {
  Network, Router, CircleDot, Share2, GitBranch, Workflow,
  Wifi, Radio, Signal, Rss, TowerControl, Antenna, SatelliteDish,
  Server, Cpu, HardDrive, MemoryStick, Warehouse, Factory,
  Cable, CodeXml, Layers, GripHorizontal, Sliders, LayoutGrid,
  Shield, ShieldCheck, Lock, Fingerprint, Key, Ban, AlertCircle,
  Cloud, CloudUpload, CloudDownload, CloudLightning, Wind,
  Database, Table, Boxes, Archive, FileArchive,
  Laptop, LaptopMinimal, Monitor, Tv, ScreenShare,
  Tablet, TabletSmartphone, Smartphone, Phone, PhoneCall, Headset, Headphones, Mic,
  Printer, FileText, FileImage, Copy, Files,
  Video, Camera, Eye, Glasses, Film,
  Clock, Timer, CreditCard, UserCheck, CalendarCheck, Plug,
  Battery, BatteryFull, BatteryMedium, Zap, Power, BatteryCharging,
  Route, Shuffle, Repeat,
  Scale, ArrowUpDown,
  Thermometer, Lightbulb, DoorOpen, Bell, Gauge,
  Keyboard, Mouse, Gamepad, PenSquare, Pointer,
  Box, Package, Boxes as CubeIcon, Group,
  Circle, Square, Diamond, Star, Asterisk, Target, Crosshair, MapPin,
  // New icons for expanded categories
  Globe, Earth, Search, Signpost,
  Mail, MailOpen, AtSign, Inbox, Send, MailCheck, MessageSquare,
  Code, FileCode, Link, Terminal, AppWindow,
  Container, Ship,
  Home, Fan, ToggleRight, Bot,
  Play, Music, Volume2, Clapperboard, Disc3,
  Barcode, QrCode, ScanLine,
  LogIn, LogOut, ArrowLeftRight, MapPinned,
  PlugZap, Unplug, SunMedium,
  Wrench, Settings, Settings2, Hammer, GaugeCircle,
  UserX, ShieldAlert,
  type LucideIcon,
} from "lucide-react";
import { Search as SearchIcon } from "lucide-react";

export interface IconOption {
  id: string;
  label: string;
  icon: LucideIcon;
}

export interface IconCategory {
  key: string;
  label: string;
  icons: IconOption[];
}

export const DEVICE_ICON_CATEGORIES: IconCategory[] = [
  {
    key: "router", label: "Router",
    icons: [
      { id: "router", label: "Router", icon: Router },
      { id: "network-wired", label: "Network Wired", icon: Network },
      { id: "circle-nodes", label: "Nodes", icon: CircleDot },
      { id: "share-nodes", label: "Share", icon: Share2 },
      { id: "sitemap", label: "Sitemap", icon: Workflow },
      { id: "git-branch", label: "Branch", icon: GitBranch },
    ],
  },
  {
    key: "wifi-router", label: "WiFi Router",
    icons: [
      { id: "wifi", label: "WiFi Signal", icon: Wifi },
      { id: "tower-broadcast", label: "Tower", icon: TowerControl },
      { id: "radio", label: "Radio", icon: Radio },
      { id: "signal", label: "Signal Bars", icon: Signal },
      { id: "rss", label: "RSS", icon: Rss },
    ],
  },
  {
    key: "server", label: "Server",
    icons: [
      { id: "server", label: "Server Rack", icon: Server },
      { id: "cpu", label: "Processor", icon: Cpu },
      { id: "hard-drive", label: "Hard Drive", icon: HardDrive },
      { id: "memory", label: "Memory", icon: MemoryStick },
      { id: "warehouse", label: "Warehouse", icon: Warehouse },
      { id: "factory", label: "Industrial", icon: Factory },
    ],
  },
  {
    key: "switch", label: "Network Switch",
    icons: [
      { id: "ethernet", label: "Ethernet", icon: Cable },
      { id: "code-branch", label: "Branch", icon: CodeXml },
      { id: "layers", label: "Layers", icon: Layers },
      { id: "grip", label: "Grip", icon: GripHorizontal },
      { id: "sliders", label: "Sliders", icon: Sliders },
      { id: "grid", label: "Grid", icon: LayoutGrid },
    ],
  },
  {
    key: "firewall", label: "Firewall / Security",
    icons: [
      { id: "shield", label: "Shield", icon: Shield },
      { id: "shield-check", label: "Shield Check", icon: ShieldCheck },
      { id: "lock", label: "Lock", icon: Lock },
      { id: "fingerprint", label: "Fingerprint", icon: Fingerprint },
      { id: "key", label: "Key", icon: Key },
      { id: "ban", label: "Ban", icon: Ban },
      { id: "alert", label: "Alert", icon: AlertCircle },
    ],
  },
  {
    key: "cloud", label: "Cloud Services",
    icons: [
      { id: "cloud", label: "Cloud", icon: Cloud },
      { id: "cloud-upload", label: "Cloud Upload", icon: CloudUpload },
      { id: "cloud-download", label: "Cloud Download", icon: CloudDownload },
      { id: "cloud-lightning", label: "Cloud Lightning", icon: CloudLightning },
      { id: "wind", label: "Wind", icon: Wind },
    ],
  },
  {
    key: "database", label: "Database",
    icons: [
      { id: "database", label: "Database", icon: Database },
      { id: "table", label: "Table", icon: Table },
      { id: "cubes", label: "Cubes", icon: Boxes },
      { id: "archive", label: "Archive", icon: Archive },
      { id: "file-archive", label: "Compressed", icon: FileArchive },
    ],
  },
  {
    key: "nas", label: "NAS / Storage",
    icons: [
      { id: "nas-drive", label: "Hard Drive", icon: HardDrive },
      { id: "nas-archive", label: "Archive", icon: Archive },
    ],
  },
  {
    key: "laptop", label: "Laptop / Desktop",
    icons: [
      { id: "laptop", label: "Laptop", icon: Laptop },
      { id: "laptop-minimal", label: "Laptop Minimal", icon: LaptopMinimal },
      { id: "monitor", label: "Monitor", icon: Monitor },
      { id: "tv", label: "TV", icon: Tv },
      { id: "screen-share", label: "Screen Share", icon: ScreenShare },
    ],
  },
  {
    key: "mobile", label: "Mobile / Tablet",
    icons: [
      { id: "tablet", label: "Tablet", icon: Tablet },
      { id: "tablet-phone", label: "Tablet Phone", icon: TabletSmartphone },
      { id: "smartphone", label: "Smartphone", icon: Smartphone },
      { id: "phone", label: "Phone", icon: Phone },
      { id: "phone-call", label: "Phone Call", icon: PhoneCall },
    ],
  },
  {
    key: "printer", label: "Printer / Scanner",
    icons: [
      { id: "printer", label: "Printer", icon: Printer },
      { id: "file-text", label: "File Text", icon: FileText },
      { id: "file-image", label: "File Image", icon: FileImage },
      { id: "copy", label: "Copy", icon: Copy },
      { id: "files", label: "Files", icon: Files },
    ],
  },
  {
    key: "camera", label: "Camera / CCTV",
    icons: [
      { id: "video", label: "Video", icon: Video },
      { id: "camera", label: "Camera", icon: Camera },
      { id: "eye", label: "Eye", icon: Eye },
      { id: "glasses", label: "Glasses", icon: Glasses },
      { id: "film", label: "Film", icon: Film },
    ],
  },
  {
    key: "ipphone", label: "IP Phone / VoIP",
    icons: [
      { id: "ip-phone", label: "Phone", icon: Phone },
      { id: "headset", label: "Headset", icon: Headset },
      { id: "headphones", label: "Headphones", icon: Headphones },
      { id: "microphone", label: "Microphone", icon: Mic },
    ],
  },
  {
    key: "radio-tower", label: "Radio Tower / Antenna",
    icons: [
      { id: "tower-cell", label: "Cell Tower", icon: TowerControl },
      { id: "radio-tower", label: "Radio", icon: Radio },
      { id: "signal-tower", label: "Signal", icon: Signal },
    ],
  },
  {
    key: "ups", label: "UPS / Power",
    icons: [
      { id: "plug", label: "Plug", icon: Plug },
      { id: "battery-full", label: "Battery Full", icon: BatteryFull },
      { id: "battery-medium", label: "Battery Half", icon: BatteryMedium },
      { id: "zap", label: "Lightning", icon: Zap },
      { id: "power", label: "Power", icon: Power },
      { id: "battery-charging", label: "Charging", icon: BatteryCharging },
    ],
  },
  {
    key: "punchdevice", label: "Attendance / Punch",
    icons: [
      { id: "clock", label: "Clock", icon: Clock },
      { id: "timer", label: "Stopwatch", icon: Timer },
      { id: "id-card", label: "ID Card", icon: CreditCard },
      { id: "user-check", label: "User Check", icon: UserCheck },
      { id: "calendar-check", label: "Calendar", icon: CalendarCheck },
    ],
  },
  {
    key: "iot", label: "IoT Devices",
    icons: [
      { id: "iot-chip", label: "Microchip", icon: Cpu },
      { id: "thermometer", label: "Temperature", icon: Thermometer },
      { id: "lightbulb", label: "Light", icon: Lightbulb },
      { id: "door", label: "Door", icon: DoorOpen },
      { id: "bell", label: "Bell", icon: Bell },
      { id: "gauge", label: "Gauge", icon: Gauge },
    ],
  },
  {
    key: "modem", label: "Modem / Gateway",
    icons: [
      { id: "modem-ethernet", label: "Ethernet", icon: Cable },
      { id: "route", label: "Route", icon: Route },
      { id: "shuffle", label: "Shuffle", icon: Shuffle },
      { id: "repeat", label: "Repeat", icon: Repeat },
    ],
  },
  {
    key: "loadbalancer", label: "Load Balancer",
    icons: [
      { id: "scale", label: "Scale", icon: Scale },
      { id: "arrows", label: "Arrows", icon: ArrowUpDown },
      { id: "lb-sitemap", label: "Sitemap", icon: Workflow },
      { id: "lb-network", label: "Network", icon: Network },
    ],
  },
  {
    key: "input", label: "Keyboard / Mouse",
    icons: [
      { id: "keyboard", label: "Keyboard", icon: Keyboard },
      { id: "mouse", label: "Mouse", icon: Mouse },
      { id: "gamepad", label: "Gamepad", icon: Gamepad },
      { id: "pen", label: "Pen", icon: PenSquare },
      { id: "pointer", label: "Pointer", icon: Pointer },
    ],
  },
  // ===== NEW ICON PACKS (matching Docker version) =====
  {
    key: "accesspoint", label: "Access Point",
    icons: [
      { id: "ap-wifi", label: "WiFi", icon: Wifi },
      { id: "ap-tower", label: "Tower", icon: TowerControl },
      { id: "ap-signal", label: "Signal", icon: Signal },
      { id: "ap-dot", label: "Dot", icon: CircleDot },
      { id: "ap-target", label: "Target", icon: Target },
      { id: "ap-rss", label: "Broadcast", icon: Rss },
    ],
  },
  {
    key: "vpn", label: "VPN / Tunnel",
    icons: [
      { id: "vpn-shield", label: "Shield", icon: ShieldAlert },
      { id: "vpn-lock", label: "Lock", icon: Lock },
      { id: "vpn-key", label: "Key", icon: Key },
      { id: "vpn-mask", label: "Mask", icon: UserX },
      { id: "vpn-globe", label: "Globe", icon: Globe },
      { id: "vpn-earth", label: "Earth", icon: Earth },
    ],
  },
  {
    key: "dns", label: "DNS Server",
    icons: [
      { id: "dns-globe", label: "Globe", icon: Globe },
      { id: "dns-earth", label: "Earth", icon: Earth },
      { id: "dns-search", label: "Search", icon: Search },
      { id: "dns-signpost", label: "Signpost", icon: Signpost },
      { id: "dns-network", label: "Network", icon: Network },
    ],
  },
  {
    key: "mailserver", label: "Mail Server",
    icons: [
      { id: "mail-envelope", label: "Envelope", icon: Mail },
      { id: "mail-open", label: "Open", icon: MailOpen },
      { id: "mail-at", label: "At", icon: AtSign },
      { id: "mail-inbox", label: "Inbox", icon: Inbox },
      { id: "mail-send", label: "Send", icon: Send },
      { id: "mail-check", label: "Verified", icon: MailCheck },
      { id: "mail-message", label: "Message", icon: MessageSquare },
    ],
  },
  {
    key: "webserver", label: "Web Server",
    icons: [
      { id: "web-globe", label: "Globe", icon: Globe },
      { id: "web-code", label: "Code", icon: Code },
      { id: "web-file", label: "File Code", icon: FileCode },
      { id: "web-window", label: "Window", icon: AppWindow },
      { id: "web-link", label: "Link", icon: Link },
      { id: "web-terminal", label: "Terminal", icon: Terminal },
    ],
  },
  {
    key: "container", label: "Docker / Container",
    icons: [
      { id: "docker-cube", label: "Cube", icon: Box },
      { id: "docker-cubes", label: "Cubes", icon: Boxes },
      { id: "docker-box", label: "Box", icon: Package },
      { id: "docker-container", label: "Container", icon: Container },
      { id: "docker-ship", label: "Ship", icon: Ship },
      { id: "docker-layers", label: "Layers", icon: Layers },
    ],
  },
  {
    key: "virtualmachine", label: "Virtual Machine",
    icons: [
      { id: "vm-copy", label: "Clone", icon: Copy },
      { id: "vm-monitor", label: "Monitor", icon: Monitor },
      { id: "vm-window", label: "Window", icon: AppWindow },
      { id: "vm-server", label: "Server", icon: Server },
      { id: "vm-layers", label: "Layers", icon: Layers },
    ],
  },
  {
    key: "smartdevice", label: "Smart Home",
    icons: [
      { id: "smart-home", label: "House", icon: Home },
      { id: "smart-light", label: "Lightbulb", icon: Lightbulb },
      { id: "smart-fan", label: "Fan", icon: Fan },
      { id: "smart-thermo", label: "Thermostat", icon: Thermometer },
      { id: "smart-plug", label: "Plug", icon: Plug },
      { id: "smart-toggle", label: "Toggle", icon: ToggleRight },
      { id: "smart-robot", label: "Robot", icon: Bot },
    ],
  },
  {
    key: "mediaplayer", label: "Media Player / Streaming",
    icons: [
      { id: "media-play", label: "Play", icon: Play },
      { id: "media-tv", label: "TV", icon: Tv },
      { id: "media-music", label: "Music", icon: Music },
      { id: "media-headphones", label: "Headphones", icon: Headphones },
      { id: "media-volume", label: "Volume", icon: Volume2 },
      { id: "media-film", label: "Film", icon: Clapperboard },
      { id: "media-vinyl", label: "Vinyl", icon: Disc3 },
    ],
  },
  {
    key: "scanner", label: "Barcode / QR Scanner",
    icons: [
      { id: "scanner-barcode", label: "Barcode", icon: Barcode },
      { id: "scanner-qr", label: "QR Code", icon: QrCode },
      { id: "scanner-scan", label: "Scan", icon: ScanLine },
      { id: "scanner-search", label: "Search", icon: Search },
      { id: "scanner-crosshair", label: "Crosshair", icon: Crosshair },
    ],
  },
  {
    key: "gateway", label: "Internet Gateway",
    icons: [
      { id: "gw-door", label: "Door", icon: DoorOpen },
      { id: "gw-login", label: "Enter", icon: LogIn },
      { id: "gw-logout", label: "Exit", icon: LogOut },
      { id: "gw-arrows", label: "Arrows", icon: ArrowLeftRight },
      { id: "gw-route", label: "Route", icon: Route },
      { id: "gw-signpost", label: "Signpost", icon: Signpost },
    ],
  },
  {
    key: "pdu", label: "PDU / Power Distribution",
    icons: [
      { id: "pdu-plug-bolt", label: "Plug Bolt", icon: PlugZap },
      { id: "pdu-unplug", label: "Unplug", icon: Unplug },
      { id: "pdu-plug", label: "Plug", icon: Plug },
      { id: "pdu-bolt", label: "Bolt", icon: Zap },
      { id: "pdu-solar", label: "Solar", icon: SunMedium },
      { id: "pdu-battery", label: "Battery", icon: BatteryFull },
    ],
  },
  {
    key: "controller", label: "Controller / PLC",
    icons: [
      { id: "ctrl-gears", label: "Gears", icon: Settings },
      { id: "ctrl-gear", label: "Gear", icon: Settings2 },
      { id: "ctrl-wrench", label: "Wrench", icon: Wrench },
      { id: "ctrl-hammer", label: "Tools", icon: Hammer },
      { id: "ctrl-gauge", label: "Gauge", icon: GaugeCircle },
      { id: "ctrl-robot", label: "Robot", icon: Bot },
      { id: "ctrl-chip", label: "Chip", icon: Cpu },
    ],
  },
  {
    key: "box", label: "Group / Container",
    icons: [
      { id: "box", label: "Box", icon: Box },
      { id: "package", label: "Package", icon: Package },
      { id: "cube", label: "Cube", icon: CubeIcon },
      { id: "group", label: "Group", icon: Group },
      { id: "box-layers", label: "Layers", icon: Layers },
    ],
  },
  // ===== NEW ICON PACKS (v3 expansion) =====
  {
    key: "display", label: "Monitor / Display",
    icons: [
      { id: "display-monitor", label: "Monitor", icon: Monitor },
      { id: "display-tv", label: "TV", icon: Tv },
      { id: "display-share", label: "Screen Share", icon: ScreenShare },
      { id: "display-window", label: "App Window", icon: AppWindow },
    ],
  },
  {
    key: "satellite", label: "Satellite / Antenna",
    icons: [
      { id: "sat-dish", label: "Satellite Dish", icon: SatelliteDish },
      { id: "sat-antenna", label: "Antenna", icon: Antenna },
      { id: "sat-radio", label: "Radio", icon: Radio },
      { id: "sat-signal", label: "Signal", icon: Signal },
      { id: "sat-tower", label: "Tower", icon: TowerControl },
    ],
  },
  {
    key: "networktap", label: "Network Tap / Probe",
    icons: [
      { id: "tap-scan", label: "Scan Line", icon: ScanLine },
      { id: "tap-activity", label: "Activity", icon: Gauge },
      { id: "tap-search", label: "Search", icon: Search },
      { id: "tap-eye", label: "Eye", icon: Eye },
      { id: "tap-crosshair", label: "Crosshair", icon: Crosshair },
    ],
  },
  {
    key: "proxy", label: "Proxy Server",
    icons: [
      { id: "proxy-arrows", label: "Bidirectional", icon: ArrowLeftRight },
      { id: "proxy-shield", label: "Shield", icon: Shield },
      { id: "proxy-globe", label: "Globe", icon: Globe },
      { id: "proxy-layers", label: "Layers", icon: Layers },
    ],
  },
  {
    key: "nvr", label: "NVR / Video Recorder",
    icons: [
      { id: "nvr-video", label: "Video", icon: Video },
      { id: "nvr-drive", label: "Hard Drive", icon: HardDrive },
      { id: "nvr-film", label: "Film", icon: Film },
      { id: "nvr-clapper", label: "Clapperboard", icon: Clapperboard },
      { id: "nvr-archive", label: "Archive", icon: Archive },
    ],
  },
  {
    key: "pos", label: "POS Terminal",
    icons: [
      { id: "pos-card", label: "Credit Card", icon: CreditCard },
      { id: "pos-qr", label: "QR Code", icon: QrCode },
      { id: "pos-barcode", label: "Barcode", icon: Barcode },
    ],
  },
  {
    key: "kiosk", label: "Kiosk / Digital Signage",
    icons: [
      { id: "kiosk-monitor", label: "Monitor", icon: Monitor },
      { id: "kiosk-layout", label: "Dashboard", icon: LayoutGrid },
      { id: "kiosk-window", label: "App Window", icon: AppWindow },
    ],
  },
  {
    key: "envsensor", label: "Environmental Sensor",
    icons: [
      { id: "env-temp", label: "Temperature", icon: Thermometer },
      { id: "env-wind", label: "Wind", icon: Wind },
      { id: "env-sun", label: "Sun", icon: SunMedium },
      { id: "env-gauge", label: "Gauge", icon: Gauge },
      { id: "env-zap", label: "Lightning", icon: Zap },
    ],
  },
  {
    key: "other", label: "Generic / Other",
    icons: [
      { id: "circle", label: "Circle", icon: Circle },
      { id: "square", label: "Square", icon: Square },
      { id: "diamond", label: "Diamond", icon: Diamond },
      { id: "star", label: "Star", icon: Star },
      { id: "asterisk", label: "Asterisk", icon: Asterisk },
      { id: "target", label: "Target", icon: Target },
      { id: "crosshair", label: "Crosshair", icon: Crosshair },
      { id: "map-pin", label: "Map Pin", icon: MapPin },
    ],
  },
];

// Flat lookup for resolving an icon id to its LucideIcon component
const iconLookup = new Map<string, LucideIcon>();
DEVICE_ICON_CATEGORIES.forEach((cat) =>
  cat.icons.forEach((i) => iconLookup.set(i.id, i.icon))
);

export function getIconComponent(id: string | null | undefined): LucideIcon {
  return iconLookup.get(id ?? "") ?? Server;
}

interface DeviceIconPickerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  selectedType: string;
  selectedSubchoice: string;
  onSelect: (type: string, subchoice: string) => void;
}

const RECENT_ICONS_KEY = "ampnm-recent-icons";
const MAX_RECENT = 10;

function getRecentIcons(): string[] {
  try {
    const stored = localStorage.getItem(RECENT_ICONS_KEY);
    return stored ? JSON.parse(stored) : [];
  } catch { return []; }
}

function saveRecentIcon(iconId: string) {
  const recent = getRecentIcons().filter((id) => id !== iconId);
  recent.unshift(iconId);
  localStorage.setItem(RECENT_ICONS_KEY, JSON.stringify(recent.slice(0, MAX_RECENT)));
}

export function DeviceIconPicker({ open, onOpenChange, selectedType, selectedSubchoice, onSelect }: DeviceIconPickerProps) {
  const [search, setSearch] = useState("");
  const [activeCategory, setActiveCategory] = useState<string | null>(null);
  const [recentIds, setRecentIds] = useState<string[]>(getRecentIcons);

  const recentIcons = useMemo(() => {
    return recentIds
      .map((id) => {
        const icon = iconLookup.get(id);
        return icon ? { id, label: id, icon } : null;
      })
      .filter(Boolean) as IconOption[];
  }, [recentIds]);

  const filtered = useMemo(() => {
    const q = search.toLowerCase();
    if (!q && !activeCategory) return DEVICE_ICON_CATEGORIES;
    return DEVICE_ICON_CATEGORIES
      .filter((cat) => !activeCategory || cat.key === activeCategory)
      .map((cat) => ({
        ...cat,
        icons: cat.icons.filter((i) => !q || i.label.toLowerCase().includes(q) || i.id.toLowerCase().includes(q) || cat.label.toLowerCase().includes(q)),
      }))
      .filter((cat) => cat.icons.length > 0);
  }, [search, activeCategory]);

  const currentId = selectedSubchoice || selectedType || "server";

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-2xl max-h-[80vh] flex flex-col">
        <DialogHeader>
          <DialogTitle>Choose Device Icon</DialogTitle>
        </DialogHeader>

        {/* Search */}
        <div className="relative">
          <SearchIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search icons..."
            className="pl-10"
          />
        </div>

        {/* Category chips */}
        <div className="flex flex-wrap gap-1.5">
          <Badge
            variant={activeCategory === null ? "default" : "outline"}
            className="cursor-pointer text-xs"
            onClick={() => setActiveCategory(null)}
          >
            All
          </Badge>
          {DEVICE_ICON_CATEGORIES.map((cat) => (
            <Badge
              key={cat.key}
              variant={activeCategory === cat.key ? "default" : "outline"}
              className="cursor-pointer text-xs"
              onClick={() => setActiveCategory(cat.key === activeCategory ? null : cat.key)}
            >
              {cat.label}
            </Badge>
          ))}
        </div>

        {/* Icon grid */}
        <ScrollArea className="flex-1 min-h-0">
          <div className="space-y-4 pr-4">
            {filtered.map((cat) => (
              <div key={cat.key}>
                <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">{cat.label}</h3>
                <div className="grid grid-cols-6 sm:grid-cols-8 gap-2">
                  {cat.icons.map((iconOpt) => {
                    const Icon = iconOpt.icon;
                    const isActive = currentId === iconOpt.id;
                    return (
                      <button
                        key={iconOpt.id}
                        type="button"
                        title={iconOpt.label}
                        className={cn(
                          "flex flex-col items-center gap-1 p-2 rounded-lg border transition-all hover:bg-accent hover:border-primary/50",
                          isActive ? "bg-primary/10 border-primary ring-1 ring-primary/30" : "border-border bg-card"
                        )}
                        onClick={() => {
                          saveRecentIcon(iconOpt.id);
                          setRecentIds(getRecentIcons());
                          onSelect(cat.key, iconOpt.id);
                          onOpenChange(false);
                        }}
                      >
                        <Icon className="h-6 w-6 text-foreground" />
                        <span className="text-[10px] text-muted-foreground leading-tight text-center truncate w-full">{iconOpt.label}</span>
                      </button>
                    );
                  })}
                </div>
              </div>
            ))}
            {filtered.length === 0 && (
              <p className="text-center text-muted-foreground py-8">No icons match your search.</p>
            )}
          </div>
        </ScrollArea>
      </DialogContent>
    </Dialog>
  );
}
