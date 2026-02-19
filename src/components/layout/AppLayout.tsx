import { useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { useAuth } from "@/hooks/useAuth";
import {
  LayoutDashboard, Map, BarChart3, Activity, Monitor,
  Server, History, FileText, Mail, Users, Key, HelpCircle,
  LogOut, Menu, X, ChevronDown, Shield
} from "lucide-react";
import { Button } from "@/components/ui/button";

interface NavItem {
  label: string;
  to?: string;
  icon: React.ElementType;
  adminOnly?: boolean;
  children?: { label: string; to: string; icon: React.ElementType }[];
}

const navItems: NavItem[] = [
  { label: "Dashboard", to: "/", icon: LayoutDashboard },
  {
    label: "Network", icon: Map,
    children: [
      { label: "Map", to: "/map", icon: Map },
      { label: "Network Graphs", to: "/network-graphs", icon: BarChart3 },
    ],
  },
  {
    label: "Monitoring", icon: Activity,
    children: [
      { label: "Host Metrics", to: "/host-metrics", icon: Monitor },
      { label: "Windows Agent", to: "/windows-agent", icon: Server },
    ],
  },
  {
    label: "Administration", icon: Shield, adminOnly: true,
    children: [
      { label: "Devices", to: "/devices", icon: Server },
      { label: "History", to: "/history", icon: History },
      { label: "Status Logs", to: "/status-logs", icon: FileText },
      { label: "Email Notifications", to: "/notifications", icon: Mail },
      { label: "Users", to: "/users", icon: Users },
      { label: "License", to: "/license", icon: Key },
    ],
  },
  { label: "Help", to: "/help", icon: HelpCircle },
];

export function AppLayout({ children }: { children: React.ReactNode }) {
  const { user, isAdmin, signOut } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [openDropdown, setOpenDropdown] = useState<string | null>(null);

  const handleLogout = async () => {
    await signOut();
    navigate("/login");
  };

  const isActive = (to?: string) => to && location.pathname === to;

  const renderNavItem = (item: NavItem, mobile = false) => {
    if (item.adminOnly && !isAdmin) return null;

    if (item.children) {
      const childActive = item.children.some((c) => location.pathname === c.to);
      return (
        <div key={item.label} className="relative">
          <button
            onClick={() => setOpenDropdown(openDropdown === item.label ? null : item.label)}
            className={`flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition-colors hover:bg-muted/50 ${
              childActive ? "text-primary" : "text-muted-foreground hover:text-foreground"
            }`}
          >
            <item.icon className="h-4 w-4" />
            {item.label}
            <ChevronDown className={`h-3 w-3 transition-transform ${openDropdown === item.label ? "rotate-180" : ""}`} />
          </button>
          {openDropdown === item.label && (
            <div className={`${mobile ? "" : "absolute left-0 top-full mt-1"} z-50 min-w-[180px] rounded-md border border-border bg-popover p-1 shadow-lg`}>
              {item.children.map((child) => (
                <Link
                  key={child.to}
                  to={child.to}
                  onClick={() => { setOpenDropdown(null); setMobileOpen(false); }}
                  className={`flex items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors hover:bg-muted/50 ${
                    location.pathname === child.to ? "text-primary bg-muted/30" : "text-muted-foreground hover:text-foreground"
                  }`}
                >
                  <child.icon className="h-4 w-4" />
                  {child.label}
                </Link>
              ))}
            </div>
          )}
        </div>
      );
    }

    return (
      <Link
        key={item.label}
        to={item.to!}
        onClick={() => setMobileOpen(false)}
        className={`flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition-colors hover:bg-muted/50 ${
          isActive(item.to) ? "text-primary bg-muted/30" : "text-muted-foreground hover:text-foreground"
        }`}
      >
        <item.icon className="h-4 w-4" />
        {item.label}
      </Link>
    );
  };

  return (
    <div className="min-h-screen bg-background">
      {/* Top Navbar */}
      <header className="sticky top-0 z-50 border-b border-border bg-card/95 backdrop-blur">
        <div className="flex h-14 items-center px-4 gap-4">
          {/* Brand */}
          <Link to="/" className="flex items-center gap-2 mr-4">
            <Shield className="h-6 w-6 text-primary" />
            <span className="font-bold text-lg text-foreground tracking-tight">AMPNM</span>
          </Link>

          {/* Desktop Nav */}
          <nav className="hidden md:flex items-center gap-1 flex-1">
            {navItems.map((item) => renderNavItem(item))}
          </nav>

          {/* User + Logout */}
          <div className="hidden md:flex items-center gap-2 ml-auto">
            <span className="text-xs text-muted-foreground">{user?.email}</span>
            <Button variant="ghost" size="sm" onClick={handleLogout} className="gap-1 text-muted-foreground hover:text-foreground">
              <LogOut className="h-4 w-4" />
              Logout
            </Button>
          </div>

          {/* Mobile Toggle */}
          <Button variant="ghost" size="icon" className="md:hidden ml-auto" onClick={() => setMobileOpen(!mobileOpen)}>
            {mobileOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
          </Button>
        </div>

        {/* Mobile Nav */}
        {mobileOpen && (
          <div className="md:hidden border-t border-border p-4 space-y-1 bg-card">
            {navItems.map((item) => renderNavItem(item, true))}
            <button
              onClick={handleLogout}
              className="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-muted/50 w-full"
            >
              <LogOut className="h-4 w-4" />
              Logout
            </button>
          </div>
        )}
      </header>

      {/* Page Content */}
      <main className="p-4 md:p-6 max-w-7xl mx-auto" onClick={() => setOpenDropdown(null)}>
        {children}
      </main>
    </div>
  );
}
