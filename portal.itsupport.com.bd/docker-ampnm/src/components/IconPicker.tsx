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
  Antenna,
  Box,
  Boxes,
  Cable,
  Camera,
  Check,
  Container,
  Cloud,
  Database,
  HardDrive,
  Laptop,
  LucideIcon,
  Network,
  Package,
  Package2,
  Phone,
  Plug,
  Printer,
  Radio,
  RadioTower,
  Router,
  SatelliteDish,
  Server,
  ServerRack,
  ShipWheel,
  Shield,
  Smartphone,
  Switch,
  Tablet,
  Wifi,
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
  { id: 'router', label: 'Router & Wireless', keywords: ['router', 'wireless', 'gateway', 'antenna', 'satellite'] },
  { id: 'switch', label: 'Switching & LAN', keywords: ['switch', 'lan', 'aggregation', 'layer3'] },
  { id: 'docker', label: 'Docker & Containers', keywords: ['docker', 'container', 'image', 'package', 'registry'] },
];

export const ICON_CATEGORIES: IconCategory[] = [
  {
    id: 'connectivity',
    label: 'Connectivity',
    icons: [
      { value: 'router', label: 'Edge Router', Icon: Router, keywords: ['gateway', 'edge', 'router'], category: 'connectivity' },
      { value: 'core-router', label: 'Core Router', Icon: Network, keywords: ['router', 'core', 'backbone'], category: 'connectivity' },
      { value: 'wireless-router', label: 'Wireless Router', Icon: Wifi, keywords: ['router', 'wireless', 'ap'], category: 'connectivity' },
      { value: 'radio-tower-router', label: 'Tower Router', Icon: RadioTower, keywords: ['router', 'wireless', 'tower'], category: 'connectivity' },
      { value: 'ptp-link', label: 'Point-to-Point Link', Icon: Antenna, keywords: ['router', 'ptp', 'wireless'], category: 'connectivity' },
      { value: 'satellite-gateway', label: 'Satellite Gateway', Icon: SatelliteDish, keywords: ['router', 'satellite', 'backhaul'], category: 'connectivity' },
      { value: 'isp-peering', label: 'ISP / Peering', Icon: Network, keywords: ['router', 'isp', 'peering'], category: 'connectivity' },
      { value: 'mikrotik-router', label: 'MikroTik / CPE', Icon: Router, keywords: ['router', 'mikrotik', 'cpe'], category: 'connectivity' },
      { value: 'branch-gateway', label: 'Branch Gateway', Icon: Plug, keywords: ['router', 'branch', 'gateway'], category: 'connectivity' },
      { value: 'wireless-bridge-router', label: 'Wireless Bridge Router', Icon: Radio, keywords: ['router', 'wireless', 'bridge'], category: 'connectivity' },
      { value: 'wan-aggregator', label: 'WAN Aggregator', Icon: Cable, keywords: ['router', 'wan', 'aggregation'], category: 'connectivity' },
      { value: 'switch', label: 'Switch', Icon: Switch, keywords: ['layer2', 'lan', 'aggregation'], category: 'connectivity' },
      { value: 'l3-switch', label: 'Layer 3 Switch', Icon: Switch, keywords: ['layer3', 'routing', 'router'], category: 'connectivity' },
      { value: 'wifi-router', label: 'WiFi Router', Icon: Wifi, keywords: ['wireless', 'access point', 'router'], category: 'connectivity' },
      { value: 'wireless-bridge', label: 'Wireless Bridge', Icon: Radio, keywords: ['wireless', 'backhaul', 'router'], category: 'connectivity' },
      { value: 'directional-antenna', label: 'Directional Antenna', Icon: Antenna, keywords: ['wireless', 'antenna', 'router'], category: 'connectivity' },
      { value: 'satellite-link', label: 'Satellite Link', Icon: SatelliteDish, keywords: ['satellite', 'backhaul', 'router'], category: 'connectivity' },
    ],
  },
  {
    id: 'compute',
    label: 'Compute & Cloud',
    icons: [
      { value: 'server', label: 'Server', Icon: Server, keywords: ['bare metal', 'compute'], category: 'compute' },
      { value: 'rack', label: 'Rack', Icon: ServerRack, keywords: ['colocation', 'rack'], category: 'compute' },
      { value: 'cloud', label: 'Cloud', Icon: Cloud, keywords: ['virtual', 'service'], category: 'compute' },
      { value: 'box', label: 'Appliance', Icon: Box, keywords: ['appliance', 'hub'], category: 'compute' },
    ],
  },
  {
    id: 'storage',
    label: 'Storage & Services',
    icons: [
      { value: 'database', label: 'Database', Icon: Database, keywords: ['sql', 'data'], category: 'storage' },
      { value: 'nas', label: 'NAS', Icon: HardDrive, keywords: ['storage', 'backup'], category: 'storage' },
      { value: 'printer', label: 'Printer', Icon: Printer, keywords: ['print', 'peripheral'], category: 'storage' },
      { value: 'camera', label: 'Camera', Icon: Camera, keywords: ['surveillance', 'cctv'], category: 'storage' },
    ],
  },
  {
    id: 'security',
    label: 'Security & Voice',
    icons: [
      { value: 'firewall', label: 'Firewall', Icon: Shield, keywords: ['security', 'gateway'], category: 'security' },
      { value: 'ipphone', label: 'IP Phone', Icon: Phone, keywords: ['voice', 'sip'], category: 'security' },
      { value: 'punchdevice', label: 'Punch Device', Icon: Plug, keywords: ['patch', 'punch'], category: 'security' },
      { value: 'other', label: 'Generic', Icon: Server, keywords: ['other', 'generic'], category: 'security' },
    ],
  },
  {
    id: 'endpoints',
    label: 'User Endpoints',
    icons: [
      { value: 'laptop', label: 'Laptop', Icon: Laptop, keywords: ['endpoint', 'workstation'], category: 'endpoints' },
      { value: 'tablet', label: 'Tablet', Icon: Tablet, keywords: ['mobile', 'user'], category: 'endpoints' },
      { value: 'mobile', label: 'Mobile Phone', Icon: Smartphone, keywords: ['handset', 'user'], category: 'endpoints' },
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
    ],
  },
];

export const ICON_OPTIONS = ICON_CATEGORIES.flatMap(category => category.icons);

export const IconPicker = ({ value, onChange, open, onOpenChange }: IconPickerProps) => {
  const [category, setCategory] = useState<string>('all');
  const [search, setSearch] = useState('');
  const [quickFilter, setQuickFilter] = useState<string>('all');

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
    onChange(iconValue);
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-3xl">
        <DialogHeader>
          <DialogTitle>Select a device icon</DialogTitle>
          <DialogDescription>
            Browse open-source Lucide icons by category or search for a specific device type.
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
          <p className="text-xs text-muted-foreground">
            Pick “Router & Wireless” to see a gallery of router-focused icons, or choose “Docker & Containers” to browse
            container visuals.
          </p>
        </div>

        <ScrollArea className="mt-2 h-80 pr-2">
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
