

# Plan: Expand Device Icon Selection in Both Apps

## Current State
- **Web app** (`DeviceIconPicker.tsx`): ~30 categories, ~150 icons — already comprehensive
- **Docker AMPNM React** (`IconPicker.tsx`): Only 6 categories, ~40 icons — severely behind the PHP version (`device_icons.php` has 35 categories, ~280 icons)

## Changes

### 1. Docker AMPNM `IconPicker.tsx` — Major Expansion

Rebuild `ICON_CATEGORIES` to match all 35 categories from `device_icons.php`, using Lucide equivalents. Add missing categories:

| New Category | Icons (Lucide) |
|---|---|
| Server | Server, Cpu, HardDrive, MemoryStick, Warehouse, Factory |
| Network Switch | Cable, CodeXml, Layers, GripHorizontal, Sliders, LayoutGrid |
| Firewall / Security | Shield, ShieldCheck, Lock, Fingerprint, Key, Ban, AlertCircle |
| Cloud Services | Cloud, CloudUpload, CloudDownload, CloudLightning, Wind |
| Database | Database, Table, Boxes, Archive, FileArchive |
| NAS / Storage | HardDrive, Archive, FolderOpen, FolderTree |
| Laptop / Desktop | Laptop, LaptopMinimal, Monitor, Tv, ScreenShare |
| Tablet | Tablet, TabletSmartphone, Smartphone |
| Mobile Phone | Smartphone, Phone, PhoneCall |
| Printer / Scanner | Printer, FileText, FileImage, Copy, Files |
| Camera / CCTV | Video, Camera, Eye, Glasses, Film, Clapperboard |
| IP Phone / VoIP | Phone, Headset, Headphones, Mic |
| Radio Tower | TowerControl, Radio, Signal, Rss, SatelliteDish |
| Equipment Rack | Boxes, Box, Package, Layers, Warehouse |
| Attendance / Punch | Clock, Timer, CreditCard, UserCheck, CalendarCheck, Fingerprint |
| UPS / Power | Plug, BatteryFull, BatteryMedium, Zap, Power, BatteryCharging |
| Modem / Gateway | Cable, Route, Shuffle, Repeat |
| Load Balancer | Scale, ArrowUpDown, Workflow, Network |
| IoT Devices | Cpu, Thermometer, Lightbulb, DoorOpen, Bell, Gauge |
| Monitor / Display | Monitor, Tv, ScreenShare, AppWindow |
| Keyboard / Mouse | Keyboard, Mouse, Gamepad, PenSquare, Pointer |
| Access Point | Wifi, TowerControl, Signal, CircleDot, Target, Rss |
| VPN / Tunnel | ShieldAlert, Lock, Key, UserX, Globe, Earth |
| DNS Server | Globe, Earth, Search, Signpost, Network |
| Mail Server | Mail, MailOpen, AtSign, Inbox, Send, MailCheck, MessageSquare |
| Web Server | Globe, Code, FileCode, AppWindow, Link, Terminal |
| Virtual Machine | Copy, Monitor, AppWindow, Server, Layers |
| Smart Home | Home, Lightbulb, Fan, Thermometer, Plug, ToggleRight, Bot |
| Media Player | Play, Tv, Music, Headphones, Volume2, Clapperboard, Disc3 |
| Barcode / QR Scanner | Barcode, QrCode, ScanLine, Search, Crosshair |
| Internet Gateway | DoorOpen, LogIn, LogOut, ArrowLeftRight, Route, Signpost |
| PDU / Power Distribution | PlugZap, Unplug, Plug, Zap, SunMedium, BatteryFull |
| Controller / PLC | Settings, Settings2, Wrench, Hammer, GaugeCircle, Bot, Cpu |

Also add quick filters for Server, Security, IoT, and Endpoints.

### 2. Web App `DeviceIconPicker.tsx` — Add 8 New Categories

| New Category | Icons (Lucide) |
|---|---|
| Monitor / Display | Monitor, Tv, ScreenShare, AppWindow, Projector |
| Satellite / Antenna | SatelliteDish, Antenna, Radio, Radar, Signal |
| Network Tap / Probe | ScanLine, Activity, Stethoscope, Search, Eye |
| Proxy Server | ArrowLeftRight, Shield, Globe, Filter, Layers |
| NVR / Video Recorder | Video, HardDrive, Film, Clapperboard, Archive |
| POS Terminal | CreditCard, ShoppingCart, Receipt, Banknote, QrCode |
| Kiosk / Digital Signage | Monitor, Presentation, LayoutDashboard, PanelTop |
| Environmental Sensor | Thermometer, Droplets, Wind, Sun, Waves, Gauge |

### 3. Docker AMPNM `device_icons.php` — Add 8 Matching Categories

Add the same 8 new categories (Monitor, Satellite, Network Tap, Proxy, NVR, POS, Kiosk, Environmental Sensor) with Font Awesome equivalents.

## Files Modified

| File | Change |
|---|---|
| `docker-ampnm/src/components/IconPicker.tsx` | Expand from 6 to 35+ categories (~280 icons) |
| `src/components/devices/DeviceIconPicker.tsx` | Add 8 new categories (~45 new icons) |
| `docker-ampnm/includes/device_icons.php` | Add 8 new categories with FA icons |

No database changes needed -- icon selection is stored as text strings.

