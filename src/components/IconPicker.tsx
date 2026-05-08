import { useMemo, useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
  // Connectivity
  Router, Network, Wifi, Radio, Signal, Rss, TowerControl, CircleDot,
  Antenna, SatelliteDish, Cable, Plug,
  // Compute
  Server, Cpu, HardDrive, MemoryStick, Warehouse, Factory,
  // Switch
  CodeXml, Layers, GripHorizontal, Sliders, LayoutGrid,
  // Security
  Shield, ShieldCheck, ShieldAlert, Lock, Fingerprint, Key, Ban, AlertCircle, UserX,
  // Cloud
  Cloud, CloudUpload, CloudDownload, CloudLightning, Wind,
  // Database
  Database, Table, Boxes, Archive, FileArchive,
  // NAS
  FolderOpen, FolderTree,
  // Laptop / Desktop
  Laptop, LaptopMinimal, Monitor, Tv, ScreenShare,
  // Tablet / Mobile
  Tablet, TabletSmartphone, Smartphone, Phone, PhoneCall,
  // Printer
  Printer, FileText, FileImage, Copy, Files,
  // Camera
  Video, Camera, Eye, Glasses, Film, Clapperboard,
  // VoIP
  Headset, Headphones, Mic,
  // Punch / Attendance
  Clock, Timer, CreditCard, UserCheck, CalendarCheck,
  // UPS / Power
  Battery, BatteryFull, BatteryMedium, Zap, Power, BatteryCharging,
  // Modem
  Route, Shuffle, Repeat,
  // Load Balancer
  Scale, ArrowUpDown, Workflow,
  // IoT
  Thermometer, Lightbulb, DoorOpen, Bell, Gauge,
  // Input
  Keyboard, Mouse, Gamepad, PenSquare, Pointer,
  // VPN
  Globe, Earth,
  // DNS
  Search, Signpost,
  // Mail
  Mail, MailOpen, AtSign, Inbox, Send, MailCheck, MessageSquare,
  // Web Server
  Code, FileCode, Link, Terminal, AppWindow,
  // Docker / Container
  Box, Package, Package2, Container,
  // VM
  // Smart Home
  Home, Fan, ToggleRight, Bot,
  // Media
  Play, Music, Volume2, Disc3,
  // Scanner
  Barcode, QrCode, ScanLine, Crosshair,
  // Gateway
  LogIn, LogOut, ArrowLeftRight,
  // PDU
  PlugZap, Unplug, SunMedium,
  // Controller
  Settings, Settings2, Wrench, Hammer, GaugeCircle,
  // Generic
  Circle, Square, Diamond, Star, Asterisk, Target, MapPin,
  // Rack
  Group,
  // Ship
  ShipWheel, Ship,
  // Check
  Check,
  type LucideIcon,
} from 'lucide-react';

interface IconPickerProps {
  value: string;
  onChange: (value: string) => void;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

interface IconOption {
  value: string;
  label: string;
  Icon: LucideIcon;
  keywords: string[];
  category: string;
}

export interface IconCategory {
  id: string;
  label: string;
  icons: IconOption[];
}

const QUICK_FILTERS = [
  { id: 'router', label: 'Router & Wireless', keywords: ['router', 'wireless', 'gateway', 'antenna', 'satellite', 'wifi', 'ap'] },
  { id: 'switch', label: 'Switching & LAN', keywords: ['switch', 'lan', 'aggregation', 'layer3', 'ethernet'] },
  { id: 'docker', label: 'Docker & Containers', keywords: ['docker', 'container', 'image', 'package', 'registry', 'helm', 'kubernetes'] },
  { id: 'server', label: 'Servers & Compute', keywords: ['server', 'compute', 'rack', 'processor', 'memory', 'bare metal'] },
  { id: 'security', label: 'Security & VPN', keywords: ['firewall', 'shield', 'lock', 'vpn', 'security', 'key'] },
  { id: 'iot', label: 'IoT & Smart Home', keywords: ['iot', 'sensor', 'smart', 'thermostat', 'lightbulb', 'home'] },
  { id: 'endpoints', label: 'User Endpoints', keywords: ['laptop', 'tablet', 'mobile', 'phone', 'endpoint', 'workstation'] },
];

export const ICON_CATEGORIES: IconCategory[] = [
  {
    id: 'router',
    label: 'Router',
    icons: [
      { value: 'router', label: 'Edge Router', Icon: Router, keywords: ['gateway', 'edge', 'router'], category: 'router' },
      { value: 'core-router', label: 'Core Router', Icon: Network, keywords: ['router', 'core', 'backbone'], category: 'router' },
      { value: 'wireless-router', label: 'Wireless Router', Icon: Wifi, keywords: ['router', 'wireless', 'ap'], category: 'router' },
      { value: 'radio-tower-router', label: 'Tower Router', Icon: TowerControl, keywords: ['router', 'wireless', 'tower'], category: 'router' },
      { value: 'ptp-link', label: 'Point-to-Point', Icon: Antenna, keywords: ['router', 'ptp', 'wireless'], category: 'router' },
      { value: 'satellite-gateway', label: 'Satellite Gateway', Icon: SatelliteDish, keywords: ['router', 'satellite', 'backhaul'], category: 'router' },
      { value: 'isp-peering', label: 'ISP / Peering', Icon: Network, keywords: ['router', 'isp', 'peering'], category: 'router' },
      { value: 'mikrotik-router', label: 'MikroTik / CPE', Icon: Router, keywords: ['router', 'mikrotik', 'cpe'], category: 'router' },
      { value: 'branch-gateway', label: 'Branch Gateway', Icon: Plug, keywords: ['router', 'branch', 'gateway'], category: 'router' },
      { value: 'wireless-bridge-router', label: 'Wireless Bridge', Icon: Radio, keywords: ['router', 'wireless', 'bridge'], category: 'router' },
      { value: 'wan-aggregator', label: 'WAN Aggregator', Icon: Cable, keywords: ['router', 'wan', 'aggregation'], category: 'router' },
    ],
  },
  {
    id: 'wifi-router',
    label: 'WiFi Router',
    icons: [
      { value: 'wifi', label: 'WiFi Signal', Icon: Wifi, keywords: ['wireless', 'wifi', 'ap'], category: 'wifi-router' },
      { value: 'tower-broadcast', label: 'Broadcast Tower', Icon: TowerControl, keywords: ['wireless', 'tower'], category: 'wifi-router' },
      { value: 'radio', label: 'Radio', Icon: Radio, keywords: ['wireless', 'radio'], category: 'wifi-router' },
      { value: 'signal-bars', label: 'Signal Bars', Icon: Signal, keywords: ['wireless', 'signal'], category: 'wifi-router' },
      { value: 'rss-broadcast', label: 'RSS Broadcast', Icon: Rss, keywords: ['wireless', 'broadcast'], category: 'wifi-router' },
      { value: 'satellite-dish', label: 'Satellite Dish', Icon: SatelliteDish, keywords: ['wireless', 'satellite'], category: 'wifi-router' },
    ],
  },
  {
    id: 'server',
    label: 'Server',
    icons: [
      { value: 'server', label: 'Server Rack', Icon: Server, keywords: ['server', 'bare metal', 'compute'], category: 'server' },
      { value: 'cpu', label: 'Processor', Icon: Cpu, keywords: ['server', 'processor', 'compute'], category: 'server' },
      { value: 'hard-drive', label: 'Hard Drive', Icon: HardDrive, keywords: ['server', 'drive', 'storage'], category: 'server' },
      { value: 'memory', label: 'Memory', Icon: MemoryStick, keywords: ['server', 'memory', 'ram'], category: 'server' },
      { value: 'warehouse', label: 'Warehouse', Icon: Warehouse, keywords: ['server', 'warehouse', 'data center'], category: 'server' },
      { value: 'factory', label: 'Industrial', Icon: Factory, keywords: ['server', 'industrial', 'factory'], category: 'server' },
    ],
  },
  {
    id: 'switch',
    label: 'Network Switch',
    icons: [
      { value: 'switch', label: 'Ethernet', Icon: Cable, keywords: ['switch', 'ethernet', 'lan'], category: 'switch' },
      { value: 'code-branch', label: 'Branch', Icon: CodeXml, keywords: ['switch', 'branch'], category: 'switch' },
      { value: 'layers', label: 'Layers', Icon: Layers, keywords: ['switch', 'layer3', 'layer2'], category: 'switch' },
      { value: 'grip', label: 'Grip', Icon: GripHorizontal, keywords: ['switch', 'ports'], category: 'switch' },
      { value: 'sliders', label: 'Sliders', Icon: Sliders, keywords: ['switch', 'config'], category: 'switch' },
      { value: 'grid', label: 'Grid', Icon: LayoutGrid, keywords: ['switch', 'grid', 'lan'], category: 'switch' },
    ],
  },
  {
    id: 'firewall',
    label: 'Firewall / Security',
    icons: [
      { value: 'firewall', label: 'Shield', Icon: Shield, keywords: ['firewall', 'security', 'gateway'], category: 'firewall' },
      { value: 'shield-check', label: 'Shield Check', Icon: ShieldCheck, keywords: ['firewall', 'security', 'verified'], category: 'firewall' },
      { value: 'lock', label: 'Lock', Icon: Lock, keywords: ['firewall', 'security', 'lock'], category: 'firewall' },
      { value: 'fingerprint', label: 'Fingerprint', Icon: Fingerprint, keywords: ['firewall', 'security', 'biometric'], category: 'firewall' },
      { value: 'key', label: 'Key', Icon: Key, keywords: ['firewall', 'security', 'key'], category: 'firewall' },
      { value: 'ban', label: 'Ban', Icon: Ban, keywords: ['firewall', 'security', 'block'], category: 'firewall' },
      { value: 'alert', label: 'Alert', Icon: AlertCircle, keywords: ['firewall', 'security', 'alert'], category: 'firewall' },
    ],
  },
  {
    id: 'cloud',
    label: 'Cloud Services',
    icons: [
      { value: 'cloud', label: 'Cloud', Icon: Cloud, keywords: ['cloud', 'virtual', 'service'], category: 'cloud' },
      { value: 'cloud-upload', label: 'Cloud Upload', Icon: CloudUpload, keywords: ['cloud', 'upload'], category: 'cloud' },
      { value: 'cloud-download', label: 'Cloud Download', Icon: CloudDownload, keywords: ['cloud', 'download'], category: 'cloud' },
      { value: 'cloud-lightning', label: 'Cloud Lightning', Icon: CloudLightning, keywords: ['cloud', 'lightning', 'fast'], category: 'cloud' },
      { value: 'cloud-wind', label: 'Cloud Wind', Icon: Wind, keywords: ['cloud', 'hybrid'], category: 'cloud' },
    ],
  },
  {
    id: 'database',
    label: 'Database',
    icons: [
      { value: 'database', label: 'Database', Icon: Database, keywords: ['sql', 'data', 'database'], category: 'database' },
      { value: 'table', label: 'Table', Icon: Table, keywords: ['database', 'table'], category: 'database' },
      { value: 'cubes', label: 'Cubes', Icon: Boxes, keywords: ['database', 'cubes'], category: 'database' },
      { value: 'archive', label: 'Archive', Icon: Archive, keywords: ['database', 'archive', 'backup'], category: 'database' },
      { value: 'file-archive', label: 'Compressed', Icon: FileArchive, keywords: ['database', 'compressed'], category: 'database' },
    ],
  },
  {
    id: 'nas',
    label: 'NAS / Storage',
    icons: [
      { value: 'nas', label: 'Hard Drive', Icon: HardDrive, keywords: ['storage', 'nas', 'backup'], category: 'nas' },
      { value: 'nas-archive', label: 'Archive', Icon: Archive, keywords: ['storage', 'nas', 'archive'], category: 'nas' },
      { value: 'nas-folder', label: 'Folder', Icon: FolderOpen, keywords: ['storage', 'nas', 'share'], category: 'nas' },
      { value: 'nas-tree', label: 'Folder Tree', Icon: FolderTree, keywords: ['storage', 'nas', 'directory'], category: 'nas' },
    ],
  },
  {
    id: 'laptop',
    label: 'Laptop / Desktop',
    icons: [
      { value: 'laptop', label: 'Laptop', Icon: Laptop, keywords: ['endpoint', 'workstation', 'laptop'], category: 'laptop' },
      { value: 'laptop-minimal', label: 'Laptop Minimal', Icon: LaptopMinimal, keywords: ['endpoint', 'laptop', 'thin'], category: 'laptop' },
      { value: 'monitor', label: 'Monitor', Icon: Monitor, keywords: ['endpoint', 'desktop', 'monitor'], category: 'laptop' },
      { value: 'tv', label: 'TV', Icon: Tv, keywords: ['endpoint', 'display', 'tv'], category: 'laptop' },
      { value: 'screen-share', label: 'Screen Share', Icon: ScreenShare, keywords: ['endpoint', 'share', 'screen'], category: 'laptop' },
    ],
  },
  {
    id: 'tablet',
    label: 'Tablet',
    icons: [
      { value: 'tablet', label: 'Tablet', Icon: Tablet, keywords: ['mobile', 'tablet', 'endpoint'], category: 'tablet' },
      { value: 'tablet-phone', label: 'Tablet Phone', Icon: TabletSmartphone, keywords: ['mobile', 'tablet', 'phone'], category: 'tablet' },
      { value: 'smartphone', label: 'Smartphone', Icon: Smartphone, keywords: ['mobile', 'phone', 'endpoint'], category: 'tablet' },
    ],
  },
  {
    id: 'mobile',
    label: 'Mobile Phone',
    icons: [
      { value: 'mobile', label: 'Mobile Phone', Icon: Smartphone, keywords: ['mobile', 'handset', 'phone'], category: 'mobile' },
      { value: 'phone', label: 'Phone', Icon: Phone, keywords: ['mobile', 'landline', 'phone'], category: 'mobile' },
      { value: 'phone-call', label: 'Phone Call', Icon: PhoneCall, keywords: ['mobile', 'call', 'ringing'], category: 'mobile' },
    ],
  },
  {
    id: 'printer',
    label: 'Printer / Scanner',
    icons: [
      { value: 'printer', label: 'Printer', Icon: Printer, keywords: ['print', 'peripheral', 'printer'], category: 'printer' },
      { value: 'file-text', label: 'File Text', Icon: FileText, keywords: ['print', 'document'], category: 'printer' },
      { value: 'file-image', label: 'File Image', Icon: FileImage, keywords: ['print', 'image', 'scan'], category: 'printer' },
      { value: 'copy', label: 'Copy', Icon: Copy, keywords: ['print', 'copy', 'duplicate'], category: 'printer' },
      { value: 'files', label: 'Files', Icon: Files, keywords: ['print', 'batch'], category: 'printer' },
    ],
  },
  {
    id: 'camera',
    label: 'Camera / CCTV',
    icons: [
      { value: 'video-cam', label: 'Video', Icon: Video, keywords: ['surveillance', 'cctv', 'camera'], category: 'camera' },
      { value: 'camera', label: 'Camera', Icon: Camera, keywords: ['surveillance', 'photo', 'camera'], category: 'camera' },
      { value: 'eye', label: 'Eye', Icon: Eye, keywords: ['surveillance', 'monitor'], category: 'camera' },
      { value: 'glasses', label: 'Glasses', Icon: Glasses, keywords: ['surveillance', 'view'], category: 'camera' },
      { value: 'film', label: 'Film', Icon: Film, keywords: ['surveillance', 'recording'], category: 'camera' },
      { value: 'clapperboard', label: 'Clapperboard', Icon: Clapperboard, keywords: ['surveillance', 'recording'], category: 'camera' },
    ],
  },
  {
    id: 'ipphone',
    label: 'IP Phone / VoIP',
    icons: [
      { value: 'ipphone', label: 'IP Phone', Icon: Phone, keywords: ['voice', 'sip', 'voip'], category: 'ipphone' },
      { value: 'headset', label: 'Headset', Icon: Headset, keywords: ['voice', 'headset', 'call center'], category: 'ipphone' },
      { value: 'headphones', label: 'Headphones', Icon: Headphones, keywords: ['voice', 'audio', 'headphones'], category: 'ipphone' },
      { value: 'microphone', label: 'Microphone', Icon: Mic, keywords: ['voice', 'mic', 'audio'], category: 'ipphone' },
    ],
  },
  {
    id: 'radio-tower',
    label: 'Radio Tower / Antenna',
    icons: [
      { value: 'tower-cell', label: 'Cell Tower', Icon: TowerControl, keywords: ['tower', 'cell', 'antenna'], category: 'radio-tower' },
      { value: 'radio-tower', label: 'Radio', Icon: Radio, keywords: ['tower', 'radio', 'broadcast'], category: 'radio-tower' },
      { value: 'signal-tower', label: 'Signal', Icon: Signal, keywords: ['tower', 'signal'], category: 'radio-tower' },
      { value: 'rss-tower', label: 'RSS', Icon: Rss, keywords: ['tower', 'broadcast', 'rss'], category: 'radio-tower' },
      { value: 'satellite-tower', label: 'Satellite Dish', Icon: SatelliteDish, keywords: ['tower', 'satellite', 'dish'], category: 'radio-tower' },
    ],
  },
  {
    id: 'rack',
    label: 'Equipment Rack',
    icons: [
      { value: 'rack-boxes', label: 'Boxes', Icon: Boxes, keywords: ['rack', 'colocation'], category: 'rack' },
      { value: 'rack-box', label: 'Box', Icon: Box, keywords: ['rack', 'unit'], category: 'rack' },
      { value: 'rack-package', label: 'Package', Icon: Package, keywords: ['rack', 'package'], category: 'rack' },
      { value: 'rack-layers', label: 'Layers', Icon: Layers, keywords: ['rack', 'layers'], category: 'rack' },
      { value: 'rack-warehouse', label: 'Warehouse', Icon: Warehouse, keywords: ['rack', 'data center'], category: 'rack' },
      { value: 'rack-group', label: 'Group', Icon: Group, keywords: ['rack', 'group'], category: 'rack' },
    ],
  },
  {
    id: 'punchdevice',
    label: 'Attendance / Punch',
    icons: [
      { value: 'punch-clock', label: 'Clock', Icon: Clock, keywords: ['attendance', 'punch', 'clock'], category: 'punchdevice' },
      { value: 'punch-timer', label: 'Timer', Icon: Timer, keywords: ['attendance', 'timer'], category: 'punchdevice' },
      { value: 'punch-card', label: 'ID Card', Icon: CreditCard, keywords: ['attendance', 'card'], category: 'punchdevice' },
      { value: 'punch-user', label: 'User Check', Icon: UserCheck, keywords: ['attendance', 'check in'], category: 'punchdevice' },
      { value: 'punch-calendar', label: 'Calendar', Icon: CalendarCheck, keywords: ['attendance', 'calendar'], category: 'punchdevice' },
      { value: 'punch-fingerprint', label: 'Fingerprint', Icon: Fingerprint, keywords: ['attendance', 'biometric'], category: 'punchdevice' },
    ],
  },
  {
    id: 'ups',
    label: 'UPS / Power',
    icons: [
      { value: 'ups-plug', label: 'Plug', Icon: Plug, keywords: ['ups', 'power', 'plug'], category: 'ups' },
      { value: 'ups-full', label: 'Battery Full', Icon: BatteryFull, keywords: ['ups', 'power', 'battery'], category: 'ups' },
      { value: 'ups-medium', label: 'Battery Half', Icon: BatteryMedium, keywords: ['ups', 'power', 'battery'], category: 'ups' },
      { value: 'ups-zap', label: 'Lightning', Icon: Zap, keywords: ['ups', 'power', 'lightning'], category: 'ups' },
      { value: 'ups-power', label: 'Power', Icon: Power, keywords: ['ups', 'power', 'button'], category: 'ups' },
      { value: 'ups-charging', label: 'Charging', Icon: BatteryCharging, keywords: ['ups', 'power', 'charging'], category: 'ups' },
    ],
  },
  {
    id: 'modem',
    label: 'Modem / Gateway',
    icons: [
      { value: 'modem-cable', label: 'Cable', Icon: Cable, keywords: ['modem', 'cable', 'dsl'], category: 'modem' },
      { value: 'modem-route', label: 'Route', Icon: Route, keywords: ['modem', 'route'], category: 'modem' },
      { value: 'modem-shuffle', label: 'Shuffle', Icon: Shuffle, keywords: ['modem', 'shuffle'], category: 'modem' },
      { value: 'modem-repeat', label: 'Repeat', Icon: Repeat, keywords: ['modem', 'repeat'], category: 'modem' },
    ],
  },
  {
    id: 'loadbalancer',
    label: 'Load Balancer',
    icons: [
      { value: 'lb-scale', label: 'Scale', Icon: Scale, keywords: ['load balancer', 'scale'], category: 'loadbalancer' },
      { value: 'lb-arrows', label: 'Arrows', Icon: ArrowUpDown, keywords: ['load balancer', 'traffic'], category: 'loadbalancer' },
      { value: 'lb-workflow', label: 'Workflow', Icon: Workflow, keywords: ['load balancer', 'sitemap'], category: 'loadbalancer' },
      { value: 'lb-network', label: 'Network', Icon: Network, keywords: ['load balancer', 'network'], category: 'loadbalancer' },
    ],
  },
  {
    id: 'iot',
    label: 'IoT Devices',
    icons: [
      { value: 'iot-chip', label: 'Microchip', Icon: Cpu, keywords: ['iot', 'sensor', 'chip'], category: 'iot' },
      { value: 'iot-temp', label: 'Temperature', Icon: Thermometer, keywords: ['iot', 'sensor', 'temperature'], category: 'iot' },
      { value: 'iot-light', label: 'Light', Icon: Lightbulb, keywords: ['iot', 'smart', 'lightbulb'], category: 'iot' },
      { value: 'iot-door', label: 'Door', Icon: DoorOpen, keywords: ['iot', 'sensor', 'door'], category: 'iot' },
      { value: 'iot-bell', label: 'Bell', Icon: Bell, keywords: ['iot', 'alarm', 'bell'], category: 'iot' },
      { value: 'iot-gauge', label: 'Gauge', Icon: Gauge, keywords: ['iot', 'sensor', 'gauge'], category: 'iot' },
    ],
  },
  {
    id: 'monitor',
    label: 'Monitor / Display',
    icons: [
      { value: 'display-monitor', label: 'Monitor', Icon: Monitor, keywords: ['display', 'monitor', 'screen'], category: 'monitor' },
      { value: 'display-tv', label: 'TV', Icon: Tv, keywords: ['display', 'tv', 'screen'], category: 'monitor' },
      { value: 'display-share', label: 'Screen Share', Icon: ScreenShare, keywords: ['display', 'share', 'cast'], category: 'monitor' },
      { value: 'display-window', label: 'App Window', Icon: AppWindow, keywords: ['display', 'window', 'app'], category: 'monitor' },
    ],
  },
  {
    id: 'input',
    label: 'Keyboard / Mouse',
    icons: [
      { value: 'keyboard', label: 'Keyboard', Icon: Keyboard, keywords: ['input', 'keyboard'], category: 'input' },
      { value: 'mouse', label: 'Mouse', Icon: Mouse, keywords: ['input', 'mouse'], category: 'input' },
      { value: 'gamepad', label: 'Gamepad', Icon: Gamepad, keywords: ['input', 'controller', 'gamepad'], category: 'input' },
      { value: 'pen', label: 'Pen', Icon: PenSquare, keywords: ['input', 'pen', 'stylus'], category: 'input' },
      { value: 'pointer', label: 'Pointer', Icon: Pointer, keywords: ['input', 'pointer', 'cursor'], category: 'input' },
    ],
  },
  {
    id: 'accesspoint',
    label: 'Access Point',
    icons: [
      { value: 'ap-wifi', label: 'WiFi', Icon: Wifi, keywords: ['access point', 'wireless', 'ap', 'wifi'], category: 'accesspoint' },
      { value: 'ap-tower', label: 'Tower', Icon: TowerControl, keywords: ['access point', 'tower', 'ap'], category: 'accesspoint' },
      { value: 'ap-signal', label: 'Signal', Icon: Signal, keywords: ['access point', 'signal', 'ap'], category: 'accesspoint' },
      { value: 'ap-dot', label: 'Dot', Icon: CircleDot, keywords: ['access point', 'dot', 'ap'], category: 'accesspoint' },
      { value: 'ap-target', label: 'Target', Icon: Target, keywords: ['access point', 'target', 'ap'], category: 'accesspoint' },
      { value: 'ap-rss', label: 'Broadcast', Icon: Rss, keywords: ['access point', 'broadcast', 'ap'], category: 'accesspoint' },
    ],
  },
  {
    id: 'vpn',
    label: 'VPN / Tunnel',
    icons: [
      { value: 'vpn-shield', label: 'Shield Alert', Icon: ShieldAlert, keywords: ['vpn', 'tunnel', 'security'], category: 'vpn' },
      { value: 'vpn-lock', label: 'Lock', Icon: Lock, keywords: ['vpn', 'tunnel', 'encrypted'], category: 'vpn' },
      { value: 'vpn-key', label: 'Key', Icon: Key, keywords: ['vpn', 'tunnel', 'key'], category: 'vpn' },
      { value: 'vpn-mask', label: 'Mask', Icon: UserX, keywords: ['vpn', 'tunnel', 'anonymous'], category: 'vpn' },
      { value: 'vpn-globe', label: 'Globe', Icon: Globe, keywords: ['vpn', 'tunnel', 'global'], category: 'vpn' },
      { value: 'vpn-earth', label: 'Earth', Icon: Earth, keywords: ['vpn', 'tunnel', 'worldwide'], category: 'vpn' },
    ],
  },
  {
    id: 'dns',
    label: 'DNS Server',
    icons: [
      { value: 'dns-globe', label: 'Globe', Icon: Globe, keywords: ['dns', 'server', 'resolve'], category: 'dns' },
      { value: 'dns-earth', label: 'Earth', Icon: Earth, keywords: ['dns', 'server', 'world'], category: 'dns' },
      { value: 'dns-search', label: 'Search', Icon: Search, keywords: ['dns', 'server', 'lookup'], category: 'dns' },
      { value: 'dns-signpost', label: 'Signpost', Icon: Signpost, keywords: ['dns', 'server', 'direction'], category: 'dns' },
      { value: 'dns-network', label: 'Network', Icon: Network, keywords: ['dns', 'server', 'network'], category: 'dns' },
    ],
  },
  {
    id: 'mailserver',
    label: 'Mail Server',
    icons: [
      { value: 'mail-envelope', label: 'Envelope', Icon: Mail, keywords: ['mail', 'server', 'email'], category: 'mailserver' },
      { value: 'mail-open', label: 'Open Mail', Icon: MailOpen, keywords: ['mail', 'server', 'open'], category: 'mailserver' },
      { value: 'mail-at', label: 'At Sign', Icon: AtSign, keywords: ['mail', 'server', 'email'], category: 'mailserver' },
      { value: 'mail-inbox', label: 'Inbox', Icon: Inbox, keywords: ['mail', 'server', 'inbox'], category: 'mailserver' },
      { value: 'mail-send', label: 'Send', Icon: Send, keywords: ['mail', 'server', 'send'], category: 'mailserver' },
      { value: 'mail-check', label: 'Verified', Icon: MailCheck, keywords: ['mail', 'server', 'verified'], category: 'mailserver' },
      { value: 'mail-message', label: 'Message', Icon: MessageSquare, keywords: ['mail', 'server', 'message'], category: 'mailserver' },
    ],
  },
  {
    id: 'webserver',
    label: 'Web Server',
    icons: [
      { value: 'web-globe', label: 'Globe', Icon: Globe, keywords: ['web', 'server', 'http'], category: 'webserver' },
      { value: 'web-code', label: 'Code', Icon: Code, keywords: ['web', 'server', 'code'], category: 'webserver' },
      { value: 'web-file', label: 'File Code', Icon: FileCode, keywords: ['web', 'server', 'file'], category: 'webserver' },
      { value: 'web-window', label: 'Window', Icon: AppWindow, keywords: ['web', 'server', 'app'], category: 'webserver' },
      { value: 'web-link', label: 'Link', Icon: Link, keywords: ['web', 'server', 'url'], category: 'webserver' },
      { value: 'web-terminal', label: 'Terminal', Icon: Terminal, keywords: ['web', 'server', 'cli'], category: 'webserver' },
    ],
  },
  {
    id: 'virtualmachine',
    label: 'Virtual Machine',
    icons: [
      { value: 'vm-copy', label: 'Clone', Icon: Copy, keywords: ['vm', 'virtual', 'clone'], category: 'virtualmachine' },
      { value: 'vm-monitor', label: 'Monitor', Icon: Monitor, keywords: ['vm', 'virtual', 'monitor'], category: 'virtualmachine' },
      { value: 'vm-window', label: 'Window', Icon: AppWindow, keywords: ['vm', 'virtual', 'window'], category: 'virtualmachine' },
      { value: 'vm-server', label: 'Server', Icon: Server, keywords: ['vm', 'virtual', 'server'], category: 'virtualmachine' },
      { value: 'vm-layers', label: 'Layers', Icon: Layers, keywords: ['vm', 'virtual', 'layers'], category: 'virtualmachine' },
    ],
  },
  {
    id: 'containers',
    label: 'Docker & Containers',
    icons: [
      { value: 'docker-box', label: 'Docker Stack', Icon: Boxes, keywords: ['docker', 'container', 'compose'], category: 'containers' },
      { value: 'container', label: 'Container', Icon: Container, keywords: ['container', 'pod', 'docker'], category: 'containers' },
      { value: 'package', label: 'Image Package', Icon: Package, keywords: ['docker', 'image', 'package'], category: 'containers' },
      { value: 'package-2', label: 'Registry Artifact', Icon: Package2, keywords: ['registry', 'package', 'docker'], category: 'containers' },
      { value: 'ship-wheel', label: 'Orchestration', Icon: ShipWheel, keywords: ['helm', 'kubernetes', 'docker'], category: 'containers' },
      { value: 'container-box', label: 'Container Box', Icon: Box, keywords: ['container', 'box', 'docker'], category: 'containers' },
      { value: 'container-ship', label: 'Ship', Icon: Ship, keywords: ['docker', 'ship', 'deploy'], category: 'containers' },
    ],
  },
  {
    id: 'smartdevice',
    label: 'Smart Home',
    icons: [
      { value: 'smart-home', label: 'House', Icon: Home, keywords: ['smart', 'home', 'automation'], category: 'smartdevice' },
      { value: 'smart-light', label: 'Lightbulb', Icon: Lightbulb, keywords: ['smart', 'light', 'home'], category: 'smartdevice' },
      { value: 'smart-fan', label: 'Fan', Icon: Fan, keywords: ['smart', 'fan', 'hvac'], category: 'smartdevice' },
      { value: 'smart-thermo', label: 'Thermostat', Icon: Thermometer, keywords: ['smart', 'thermostat', 'home'], category: 'smartdevice' },
      { value: 'smart-plug', label: 'Plug', Icon: Plug, keywords: ['smart', 'plug', 'outlet'], category: 'smartdevice' },
      { value: 'smart-toggle', label: 'Toggle', Icon: ToggleRight, keywords: ['smart', 'toggle', 'switch'], category: 'smartdevice' },
      { value: 'smart-robot', label: 'Robot', Icon: Bot, keywords: ['smart', 'robot', 'automation'], category: 'smartdevice' },
    ],
  },
  {
    id: 'mediaplayer',
    label: 'Media Player',
    icons: [
      { value: 'media-play', label: 'Play', Icon: Play, keywords: ['media', 'play', 'stream'], category: 'mediaplayer' },
      { value: 'media-tv', label: 'TV', Icon: Tv, keywords: ['media', 'tv', 'display'], category: 'mediaplayer' },
      { value: 'media-music', label: 'Music', Icon: Music, keywords: ['media', 'music', 'audio'], category: 'mediaplayer' },
      { value: 'media-headphones', label: 'Headphones', Icon: Headphones, keywords: ['media', 'headphones', 'audio'], category: 'mediaplayer' },
      { value: 'media-volume', label: 'Volume', Icon: Volume2, keywords: ['media', 'volume', 'speaker'], category: 'mediaplayer' },
      { value: 'media-film', label: 'Film', Icon: Clapperboard, keywords: ['media', 'film', 'video'], category: 'mediaplayer' },
      { value: 'media-vinyl', label: 'Vinyl', Icon: Disc3, keywords: ['media', 'vinyl', 'disc'], category: 'mediaplayer' },
    ],
  },
  {
    id: 'scanner',
    label: 'Barcode / QR Scanner',
    icons: [
      { value: 'scanner-barcode', label: 'Barcode', Icon: Barcode, keywords: ['scanner', 'barcode'], category: 'scanner' },
      { value: 'scanner-qr', label: 'QR Code', Icon: QrCode, keywords: ['scanner', 'qr'], category: 'scanner' },
      { value: 'scanner-scan', label: 'Scan Line', Icon: ScanLine, keywords: ['scanner', 'scan'], category: 'scanner' },
      { value: 'scanner-search', label: 'Search', Icon: Search, keywords: ['scanner', 'search'], category: 'scanner' },
      { value: 'scanner-crosshair', label: 'Crosshair', Icon: Crosshair, keywords: ['scanner', 'crosshair'], category: 'scanner' },
    ],
  },
  {
    id: 'gateway',
    label: 'Internet Gateway',
    icons: [
      { value: 'gw-door', label: 'Door', Icon: DoorOpen, keywords: ['gateway', 'internet', 'door'], category: 'gateway' },
      { value: 'gw-login', label: 'Enter', Icon: LogIn, keywords: ['gateway', 'login', 'enter'], category: 'gateway' },
      { value: 'gw-logout', label: 'Exit', Icon: LogOut, keywords: ['gateway', 'logout', 'exit'], category: 'gateway' },
      { value: 'gw-arrows', label: 'Arrows', Icon: ArrowLeftRight, keywords: ['gateway', 'bidirectional'], category: 'gateway' },
      { value: 'gw-route', label: 'Route', Icon: Route, keywords: ['gateway', 'route'], category: 'gateway' },
      { value: 'gw-signpost', label: 'Signpost', Icon: Signpost, keywords: ['gateway', 'signpost'], category: 'gateway' },
    ],
  },
  {
    id: 'pdu',
    label: 'PDU / Power Distribution',
    icons: [
      { value: 'pdu-plug-bolt', label: 'Plug Bolt', Icon: PlugZap, keywords: ['pdu', 'power', 'plug'], category: 'pdu' },
      { value: 'pdu-unplug', label: 'Unplug', Icon: Unplug, keywords: ['pdu', 'power', 'unplug'], category: 'pdu' },
      { value: 'pdu-plug', label: 'Plug', Icon: Plug, keywords: ['pdu', 'power', 'outlet'], category: 'pdu' },
      { value: 'pdu-bolt', label: 'Bolt', Icon: Zap, keywords: ['pdu', 'power', 'lightning'], category: 'pdu' },
      { value: 'pdu-solar', label: 'Solar', Icon: SunMedium, keywords: ['pdu', 'power', 'solar'], category: 'pdu' },
      { value: 'pdu-battery', label: 'Battery', Icon: BatteryFull, keywords: ['pdu', 'power', 'battery'], category: 'pdu' },
    ],
  },
  {
    id: 'controller',
    label: 'Controller / PLC',
    icons: [
      { value: 'ctrl-gears', label: 'Gears', Icon: Settings, keywords: ['controller', 'plc', 'gears'], category: 'controller' },
      { value: 'ctrl-gear', label: 'Gear', Icon: Settings2, keywords: ['controller', 'plc', 'gear'], category: 'controller' },
      { value: 'ctrl-wrench', label: 'Wrench', Icon: Wrench, keywords: ['controller', 'plc', 'tools'], category: 'controller' },
      { value: 'ctrl-hammer', label: 'Tools', Icon: Hammer, keywords: ['controller', 'plc', 'hardware'], category: 'controller' },
      { value: 'ctrl-gauge', label: 'Gauge', Icon: GaugeCircle, keywords: ['controller', 'plc', 'gauge'], category: 'controller' },
      { value: 'ctrl-robot', label: 'Robot', Icon: Bot, keywords: ['controller', 'plc', 'robot'], category: 'controller' },
      { value: 'ctrl-chip', label: 'Chip', Icon: Cpu, keywords: ['controller', 'plc', 'chip'], category: 'controller' },
    ],
  },
  {
    id: 'box',
    label: 'Group / Container',
    icons: [
      { value: 'box', label: 'Box', Icon: Box, keywords: ['group', 'container', 'box'], category: 'box' },
      { value: 'package-box', label: 'Package', Icon: Package, keywords: ['group', 'package'], category: 'box' },
      { value: 'cubes-box', label: 'Cubes', Icon: Boxes, keywords: ['group', 'cubes'], category: 'box' },
      { value: 'group-box', label: 'Group', Icon: Group, keywords: ['group', 'object'], category: 'box' },
      { value: 'layers-box', label: 'Layers', Icon: Layers, keywords: ['group', 'layers'], category: 'box' },
    ],
  },
  {
    id: 'other',
    label: 'Generic / Other',
    icons: [
      { value: 'circle', label: 'Circle', Icon: Circle, keywords: ['generic', 'other'], category: 'other' },
      { value: 'square', label: 'Square', Icon: Square, keywords: ['generic', 'other'], category: 'other' },
      { value: 'diamond', label: 'Diamond', Icon: Diamond, keywords: ['generic', 'other'], category: 'other' },
      { value: 'star', label: 'Star', Icon: Star, keywords: ['generic', 'other'], category: 'other' },
      { value: 'asterisk', label: 'Asterisk', Icon: Asterisk, keywords: ['generic', 'other'], category: 'other' },
      { value: 'target-other', label: 'Target', Icon: Target, keywords: ['generic', 'other'], category: 'other' },
      { value: 'crosshair-other', label: 'Crosshair', Icon: Crosshair, keywords: ['generic', 'other'], category: 'other' },
      { value: 'map-pin', label: 'Map Pin', Icon: MapPin, keywords: ['generic', 'location', 'other'], category: 'other' },
    ],
  },
];

export const ICON_OPTIONS = ICON_CATEGORIES.flatMap(category => category.icons);

export const IconPicker = ({ value, onChange, open, onOpenChange }: IconPickerProps) => {
  const [category, setCategory] = useState<string>('all');
  const [search, setSearch] = useState('');
  const [quickFilter, setQuickFilter] = useState<string>('all');

  const recentIcons = useMemo(() => {
    try {
      const stored = localStorage.getItem("ampnm-docker-recent-icons");
      const ids: string[] = stored ? JSON.parse(stored) : [];
      return ids.map(id => ICON_OPTIONS.find(i => i.value === id)).filter(Boolean) as typeof ICON_OPTIONS;
    } catch { return []; }
  }, [open]);

  const filteredIcons = useMemo(() => {
    const term = search.toLowerCase();
    const selectedQuickFilter = QUICK_FILTERS.find(filter => filter.id === quickFilter);
    const filterKeywords = selectedQuickFilter?.keywords || [];

    return ICON_OPTIONS.filter(icon => {
      const matchesCategory = category === 'all' || icon.category === category;
      const matchesSearch =
        term === '' ||
        icon.label.toLowerCase().includes(term) ||
        icon.value.toLowerCase().includes(term) ||
        icon.keywords.some(keyword => keyword.includes(term));
      const matchesQuickFilter =
        quickFilter === 'all' || icon.keywords.some(keyword => filterKeywords.includes(keyword));

      return matchesCategory && matchesSearch && matchesQuickFilter;
    });
  }, [category, search, quickFilter]);

  const categoryLabelMap = useMemo(
    () => Object.fromEntries(ICON_CATEGORIES.map(item => [item.id, item.label])),
    [],
  );

  const handleSelect = (iconValue: string) => {
    // Save to recently used
    try {
      const key = "ampnm-docker-recent-icons";
      const stored = localStorage.getItem(key);
      const recent: string[] = stored ? JSON.parse(stored) : [];
      const updated = [iconValue, ...recent.filter(v => v !== iconValue)].slice(0, 10);
      localStorage.setItem(key, JSON.stringify(updated));
    } catch {}
    onChange(iconValue);
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-3xl">
        <DialogHeader>
          <DialogTitle>Select a device icon</DialogTitle>
          <DialogDescription>
            Browse {ICON_OPTIONS.length}+ Lucide icons by category or search for a specific device type.
          </DialogDescription>
        </DialogHeader>

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <Label htmlFor="icon-search">Search icons</Label>
            <Input
              id="icon-search"
              placeholder="Search by name or use-case"
              value={search}
              onChange={event => setSearch(event.target.value)}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="icon-category">Icon category</Label>
            <Select value={category} onValueChange={setCategory}>
              <SelectTrigger id="icon-category">
                <SelectValue placeholder="Filter by category" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All categories</SelectItem>
                {ICON_CATEGORIES.map(cat => (
                  <SelectItem key={cat.id} value={cat.id}>
                    {cat.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>

        <div className="mt-2 space-y-2">
          <p className="text-xs font-medium text-muted-foreground">Quick galleries</p>
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              size="sm"
              variant={quickFilter === 'all' ? 'default' : 'outline'}
              onClick={() => setQuickFilter('all')}
            >
              Show all icons
            </Button>
            {QUICK_FILTERS.map(filter => (
              <Button
                key={filter.id}
                type="button"
                size="sm"
                variant={quickFilter === filter.id ? 'default' : 'outline'}
                onClick={() => setQuickFilter(filter.id)}
              >
                {filter.label}
              </Button>
            ))}
          </div>
        </div>

        <ScrollArea className="mt-2 h-80 pr-2">
          {/* Recently Used */}
          {recentIcons.length > 0 && category === 'all' && quickFilter === 'all' && !search && (
            <div className="mb-4">
              <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Recently Used</p>
              <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
                {recentIcons.map(icon => (
                  <Button
                    key={`recent-${icon.value}`}
                    variant={value === icon.value ? 'default' : 'outline'}
                    className="h-auto justify-start p-3 text-left"
                    onClick={() => handleSelect(icon.value)}
                  >
                    <div className="flex w-full items-center gap-3">
                      <icon.Icon className="h-5 w-5" />
                      <div className="flex-1">
                        <div className="font-medium capitalize leading-tight">{icon.label}</div>
                        <div className="text-xs text-muted-foreground">Recent</div>
                      </div>
                      {value === icon.value && <Check className="h-4 w-4" />}
                    </div>
                  </Button>
                ))}
              </div>
            </div>
          )}

          {filteredIcons.length === 0 ? (
            <p className="text-sm text-muted-foreground">No icons match your search.</p>
          ) : (
            <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
              {filteredIcons.map(icon => (
                <Button
                  key={icon.value}
                  variant={value === icon.value ? 'default' : 'outline'}
                  className="h-auto justify-start p-3 text-left"
                  onClick={() => handleSelect(icon.value)}
                >
                  <div className="flex w-full items-center gap-3">
                    <icon.Icon className="h-5 w-5" />
                    <div className="flex-1">
                      <div className="font-medium capitalize leading-tight">{icon.label}</div>
                      <div className="text-xs text-muted-foreground">{categoryLabelMap[icon.category]}</div>
                    </div>
                    {value === icon.value && <Check className="h-4 w-4" />}
                  </div>
                </Button>
              ))}
            </div>
          )}
        </ScrollArea>
      </DialogContent>
    </Dialog>
  );
};
