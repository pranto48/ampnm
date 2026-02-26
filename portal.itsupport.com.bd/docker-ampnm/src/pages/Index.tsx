import { useState, useEffect, useCallback, useMemo, useRef } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Activity, Wifi, Server, Clock, RefreshCw, Monitor, Network, WifiOff } from "lucide-react";
import { showSuccess, showError, showLoading, dismissToast } from "@/utils/toast";
import PingTest from "@/components/PingTest";
import NetworkStatus from "@/components/NetworkStatus";
import NetworkScanner from "@/components/NetworkScanner";
import ServerPingTest from "@/components/ServerPingTest";
import PingHistory from "@/components/PingHistory";
import { MadeWithDyad } from "@/components/made-with-dyad";
import NetworkMap from "@/components/NetworkMap";
import { 
  getDevices, 
  NetworkDevice, 
  updateDeviceStatusByIp, 
} from "@/services/networkDeviceService";
import { performServerPing } from "@/services/pingService";
import { Skeleton } from "@/components/ui/skeleton";

// Declare userRole globally
declare global {
  interface Window {
    userRole: string;
  }
}

const Index = () => {
  const [networkStatus, setNetworkStatus] = useState<boolean>(true);
  const [lastChecked, setLastChecked] = useState<Date>(new Date());
  const [devices, setDevices] = useState<NetworkDevice[]>([]);
  const [isCheckingDevices, setIsCheckingDevices] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [currentMapId, setCurrentMapId] = useState<string | null>(null); // State to hold current map ID

  const userRole = window.userRole || 'viewer';
  const isAdmin = userRole === 'admin';

  const fetchDevices = useCallback(async () => {
    try {
      const dbDevices = await getDevices();
      // Map PHP API response to NetworkDevice interface
      const mappedDevices: NetworkDevice[] = dbDevices.map(d => ({
        id: d.id,
        name: d.name,
        ip_address: d.ip, // PHP uses 'ip'
        position_x: d.x, // PHP uses 'x'
        position_y: d.y, // PHP uses 'y'
        icon: d.type, // PHP uses 'type' for icon
        status: d.status || 'unknown',
        ping_interval: d.ping_interval,
        icon_size: d.icon_size,
        name_text_size: d.name_text_size,
        last_ping: d.last_seen, // PHP uses 'last_seen'
        last_ping_result: d.status === 'online', // Derive from status
        check_port: d.check_port,
        description: d.description,
        warning_latency_threshold: d.warning_latency_threshold,
        warning_packetloss_threshold: d.warning_packetloss_threshold,
        critical_latency_threshold: d.critical_latency_threshold,
        critical_packetloss_threshold: d.critical_packetloss_threshold,
        show_live_ping: d.show_live_ping,
        map_id: d.map_id,
      }));
      setDevices(mappedDevices);
      // Set the current map ID from the first device, or null if no devices
      setCurrentMapId(mappedDevices.length > 0 ? mappedDevices[0].map_id || null : null);
    } catch (error) {
      showError("Failed to load devices from database.");
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchDevices();
  }, [fetchDevices]);

  // Auto-ping devices based on their ping interval
  useEffect(() => {
    const intervals: NodeJS.Timeout[] = [];
    
    // Only admins can have auto-ping functionality
    if (isAdmin) {
      devices.forEach((device) => {
        if (device.ping_interval && device.ping_interval > 0 && device.ip_address) {
          const intervalId = setInterval(async () => {
            try {
              console.log(`Auto-pinging ${device.ip_address}`);
              const result = await performServerPing(device.ip_address, 1);
              const newStatus = result.success ? 'online' : 'offline';
              
              // Update device status in database
              await updateDeviceStatusByIp(device.ip_address, newStatus);
              
              console.log(`Ping result for ${device.ip_address}: ${newStatus}`);
            } catch (error) {
              console.error(`Auto-ping failed for ${device.ip_address}:`, error);
              // Update status to offline on error
              await updateDeviceStatusByIp(device.ip_address, 'offline');
            }
          }, device.ping_interval * 1000);
          
          intervals.push(intervalId);
        }
      });
    }

    // Cleanup intervals on component unmount or devices change
    return () => {
      intervals.forEach(clearInterval);
    };
  }, [devices, isAdmin]);

  const checkNetworkStatus = useCallback(async () => {
    try {
      await fetch("https://www.google.com/favicon.ico", { mode: 'no-cors', cache: 'no-cache' });
      setNetworkStatus(true);
    } catch (error) {
      setNetworkStatus(false);
    }
    setLastChecked(new Date());
  }, []);

  const handleCheckAllDevices = async () => {
    if (!isAdmin) {
      showError('You do not have permission to check all devices.');
      return;
    }
    setIsCheckingDevices(true);
    const toastId = showLoading(`Pinging ${devices.length} devices...`);
    try {
      const pingPromises = devices.map(async (device) => {
        if (device.ip_address) {
          const result = await performServerPing(device.ip_address, 1);
          const newStatus = result.success ? 'online' : 'offline';
          await updateDeviceStatusByIp(device.ip_address, newStatus);
        }
      });

      await Promise.all(pingPromises);
      
      dismissToast(toastId);
      showSuccess(`Finished checking all devices.`);
    } catch (error: any) {
      dismissToast(toastId);
      showError(error.message || "An error occurred while checking devices.");
    } finally {
      setIsCheckingDevices(false);
    }
  };

  useEffect(() => {
    checkNetworkStatus();
    const networkInterval = setInterval(checkNetworkStatus, 60000);
    return () => clearInterval(networkInterval);
  }, [checkNetworkStatus]);

  const onlineDevicesCount = useMemo(() => 
    devices.filter(d => d.status === "online").length, 
    [devices]
  );

  const deviceStatusCounts = useMemo(() => {
    return devices.reduce((acc, device) => {
      const status = device.status || 'unknown';
      acc[status] = (acc[status] || 0) + 1;
      return acc;
    }, {} as Record<string, number>);
  }, [devices]);

  return (
    <div className="min-h-screen bg-background">
      <div className="container mx-auto p-4">
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <Monitor className="h-8 w-8 text-primary" />
            <h1 className="text-3xl font-bold">Local Network Monitor</h1>
          </div>
          <Badge variant={networkStatus ? "default" : "destructive"} className="text-sm">
            {networkStatus ? "Internet Online" : "Internet Offline"}
          </Badge>
        </div>

        <Tabs defaultValue="dashboard" className="w-full">
          <TabsList className="mb-4">
            <TabsTrigger value="dashboard" className="flex items-center gap-2">
              <Activity className="h-4 w-4" />
              Dashboard
            </TabsTrigger>
            <TabsTrigger value="devices" className="flex items-center gap-2">
              <Server className="h-4 w-4" />
              Devices
            </TabsTrigger>
            <TabsTrigger value="ping" className="flex items-center gap-2">
              <Wifi className="h-4 w-4" />
              Browser Ping
            </TabsTrigger>
            <TabsTrigger value="server-ping" className="flex items-center gap-2">
              <Server className="h-4 w-4" />
              Server Ping
            </TabsTrigger>
            <TabsTrigger value="status" className="flex items-center gap-2">
              <Network className="h-4 w-4" />
              Network Status
            </TabsTrigger>
            <TabsTrigger value="scanner" className="flex items-center gap-2">
              <RefreshCw className="h-4 w-4" />
              Network Scanner
            </TabsTrigger>
            <TabsTrigger value="history" className="flex items-center gap-2">
              <Clock className="h-4 w-4" />
              Ping History
            </TabsTrigger>
            <TabsTrigger value="map" className="flex items-center gap-2">
              <Network className="h-4 w-4" />
              Network Map
            </TabsTrigger>
          </TabsList>

          <TabsContent value="dashboard">
            {isLoading ? (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                {[...Array(4)].map((_, i) => (
                  <Card key={i}>
                    <CardHeader>
                      <Skeleton className="h-4 w-3/4" />
                    </CardHeader>
                    <CardContent>
                      <Skeleton className="h-8 w-1/2" />
                    </CardContent>
                  </Card>
                ))}
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <Card>
                  <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Internet Status</CardTitle>
                    <Activity className="h-4 w-4 text-muted-foreground" />
                  </CardHeader>
                  <CardContent>
                    <div className="text-2xl font-bold">{networkStatus ? "Online" : "Offline"}</div>
                    <p className="text-xs text-muted-foreground">Internet connectivity</p>
                  </CardContent>
                </Card>
                <Card>
                  <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Last Check</CardTitle>
                    <Clock className="h-4 w-4 text-muted-foreground" />
                  </CardHeader>
                  <CardContent>
                    <div className="text-2xl font-bold">{lastChecked.toLocaleTimeString()}</div>
                    <p className="text-xs text-muted-foreground">Last status check</p>
                  </CardContent>
                </Card>
                <Card>
                  <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Devices Online</CardTitle>
                    <Wifi className="h-4 w-4 text-muted-foreground" />
                  </CardHeader>
                  <CardContent>
                    <div className="text-2xl font-bold">{onlineDevicesCount}/{devices.length}</div>
                    <p className="text-xs text-muted-foreground">Devices online</p>
                  </CardContent>
                </Card>
                <Card>
                  <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Device Status</CardTitle>
                    <Server className="h-4 w-4 text-muted-foreground" />
                  </CardHeader>
                  <CardContent>
                    <div className="flex gap-2">
                      <Badge variant="default" className="text-xs">
                        Online {deviceStatusCounts.online || 0}
                      </Badge>
                      <Badge variant="destructive" className="text-xs">
                        Offline {deviceStatusCounts.offline || 0}
                      </Badge>
                    </div>
                  </CardContent>
                </Card>
              </div>
            )}

            <Card className="mb-6">
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Network className="h-5 w-5" />Quick Actions
                </CardTitle>
              </CardHeader>
              <CardContent className="flex flex-wrap gap-4">
                <Button onClick={checkNetworkStatus} variant="outline">
                  <RefreshCw className="h-4 w-4 mr-2" />Check Internet
                </Button>
                <Button 
                  onClick={handleCheckAllDevices} 
                  disabled={isCheckingDevices || isLoading || !isAdmin}
                  variant="outline"
                >
                  <RefreshCw className={`h-4 w-4 mr-2 ${isCheckingDevices ? 'animate-spin' : ''}`} />
                  {isCheckingDevices ? 'Checking...' : 'Check All Devices'}
                </Button>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="devices">
            <Card>
              <CardHeader>
                <CardTitle>Local Network Devices</CardTitle>
                <CardDescription>Monitor the status of devices on your local network</CardDescription>
              </CardHeader>
              <CardContent>
                {isLoading ? (
                  <div className="space-y-4">
                    {[...Array(5)].map((_, i) => (
                      <Skeleton key={i} className="h-16 w-full rounded-lg" />
                    ))}
                  </div>
                ) : devices.length === 0 ? (
                  <div className="text-center py-8 text-muted-foreground">
                    <Server className="h-12 w-12 mx-auto mb-4" />
                    <p>No devices found. Add devices to start monitoring.</p>
                  </div>
                ) : (
                  <div className="space-y-4">
                    {devices.map((device) => (
                      <div 
                        key={device.id} 
                        className="flex items-center justify-between p-4 border rounded-lg transition-colors hover:bg-muted"
                      >
                        <div className="flex items-center gap-3">
                          {device.status === "online" ? (
                            <Wifi className="h-5 w-5 text-green-500" />
                          ) : device.status === "offline" ? (
                            <WifiOff className="h-5 w-5 text-red-500" />
                          ) : (
                            <Wifi className="h-5 w-5 text-gray-500" />
                          )}
                          <div>
                            <span className="font-medium">{device.name}</span>
                            <p className="text-sm text-muted-foreground">{device.ip_address}</p>
                          </div>
                        </div>
                        <div className="flex items-center gap-4">
                          {device.last_ping && (
                            <div className="text-xs text-muted-foreground">
                              Last ping: {new Date(device.last_ping).toLocaleTimeString()}
                            </div>
                          )}
                          <Badge 
                            variant={
                              device.status === "online" ? "default" : 
                              device.status === "offline" ? "destructive" : "secondary"
                            }
                          >
                            {device.status || 'unknown'}
                          </Badge>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="ping">
            {isAdmin ? <PingTest /> : <p className="text-red-400 text-center py-8">You do not have permission to perform browser ping tests.</p>}
          </TabsContent>
          
          <TabsContent value="server-ping">
            {isAdmin ? <ServerPingTest /> : <p className="text-red-400 text-center py-8">You do not have permission to perform server ping tests.</p>}
          </TabsContent>
          
          <TabsContent value="status">
            <NetworkStatus />
          </TabsContent>
          
          <TabsContent value="scanner">
            {isAdmin ? <NetworkScanner /> : <p className="text-red-400 text-center py-8">You do not have permission to scan the network.</p>}
          </TabsContent>
          
          <TabsContent value="history">
            <PingHistory />
          </TabsContent>
          
          <TabsContent value="map">
            <NetworkMap devices={devices} onMapUpdate={fetchDevices} />
          </TabsContent>
        </Tabs>

        <MadeWithDyad />
      </div>
    </div>
  );
};

export default Index;