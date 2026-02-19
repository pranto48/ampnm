import { AppLayout } from "@/components/layout/AppLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { HelpCircle, Map, Server, Activity, Mail, Users, Monitor } from "lucide-react";

const sections = [
  {
    icon: Map, title: "Network Map",
    content: "The Network Map provides an interactive drag-and-drop canvas showing all your network devices. Drag devices to rearrange, connect them by dragging between handles, and click edges to change connection types (CAT5, Fiber, WiFi, Radio, LAN, Tunnel). Device nodes show real-time status with color-coded borders.",
  },
  {
    icon: Server, title: "Device Management",
    content: "Add and configure devices from the Devices page under Administration. Each device can be configured with IP address, monitoring method (ICMP Ping, TCP Port, HTTP), custom thresholds for warning/critical alerts, and visual settings like icon type and size.",
  },
  {
    icon: Activity, title: "Monitoring",
    content: "Host Metrics shows latency and packet loss charts for each device over time. The Windows Agent page provides a monitoring script you can install on Windows hosts to collect CPU, memory, disk, and network metrics.",
  },
  {
    icon: Mail, title: "Email Notifications",
    content: "Configure SMTP settings to enable email alerts. Then create per-device subscriptions to receive notifications when devices go online, offline, or hit warning/critical thresholds.",
  },
  {
    icon: Users, title: "User Management",
    content: "The first user to register automatically gets the Admin role. Admins can manage other users' roles from the Users page. Viewers can see the dashboard and map but cannot modify devices or settings.",
  },
  {
    icon: Monitor, title: "Status & History",
    content: "The History page shows ping result records with latency and packet loss data, filterable by device and date range. Status Logs track every status transition (e.g., online → offline) for audit purposes.",
  },
];

export default function HelpPage() {
  return (
    <AppLayout>
      <div className="space-y-4">
        <div className="flex items-center gap-3">
          <HelpCircle className="h-7 w-7 text-primary" />
          <h1 className="text-2xl font-bold tracking-tight">Help & Documentation</h1>
        </div>

        <Card>
          <CardHeader><CardTitle className="text-base">Getting Started</CardTitle></CardHeader>
          <CardContent className="text-sm text-muted-foreground space-y-2">
            <p>Welcome to AMPNM — Advanced Multi-Platform Network Monitor. This application helps you monitor your network devices, visualize topology, and receive alerts when issues occur.</p>
            <p>Start by adding devices from <strong>Administration → Devices</strong>, then view them on the <strong>Network Map</strong>.</p>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="pt-6">
            <Accordion type="single" collapsible className="w-full">
              {sections.map((s, i) => (
                <AccordionItem key={i} value={`item-${i}`}>
                  <AccordionTrigger className="text-sm">
                    <span className="flex items-center gap-2"><s.icon className="h-4 w-4 text-primary" />{s.title}</span>
                  </AccordionTrigger>
                  <AccordionContent className="text-sm text-muted-foreground">{s.content}</AccordionContent>
                </AccordionItem>
              ))}
            </Accordion>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
