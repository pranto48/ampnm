import { useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import { RefreshCw, Server, Wifi, WifiOff, Scan } from "lucide-react";
import { showSuccess, showError } from "@/utils/toast";

interface NetworkDevice {
  ip: string;
  status: "online" | "offline";
  responseTime?: number;
  lastSeen: Date;
}

const NetworkScanner = () => {
  const [isScanning, setIsScanning] = useState(false);
  const [scanProgress, setScanProgress] = useState(0);
  const [devices, setDevices] = useState<NetworkDevice[]>([]);

  // Generate common local IP ranges to scan
  const generateIPRange = (base: string, start: number, end: number): string[] => {
    const ips: string[] = [];
    for (let i = start; i <= end; i++) {
      ips.push(`${base}.${i}`);
    }
    return ips;
  };

  const scanNetwork = async () => {
    setIsScanning(true);
    setScanProgress(0);
    setDevices([]);

    // Common local network ranges to scan
    const ipRanges = [
      ...generateIPRange("192.168.1", 1, 254),
      ...generateIPRange("192.168.0", 1, 254),
      ...generateIPRange("192.168.9", 1, 254),
      ...generateIPRange("10.0.0", 1, 254),
    ];

    const foundDevices: NetworkDevice[] = [];
    const totalIPs = ipRanges.length;

    for (let i = 0; i < ipRanges.length; i++) {
      const ip = ipRanges[i];
      setScanProgress(Math.round((i / totalIPs) * 100));

      try {
        // Try to ping the device using multiple methods
        const startTime = performance.now();
        
        // Method 1: Try to load a favicon or common endpoint
        const img = new Image();
        const pingPromise = new Promise<void>((resolve, reject) => {
          img.onload = resolve;
          img.onerror = reject;
          
          // Try common endpoints
          const endpoints = [
            `http://${ip}/favicon.ico?t=${Date.now()}`,
            `http://${ip}:80/favicon.ico?t=${Date.now()}`,
            `http://${ip}:8080/favicon.ico?t=${Date.now()}`,
            `http://${ip}:3000/favicon.ico?t=${Date.now()}`,
          ];
          
          let currentIndex = 0;
          const tryNextEndpoint = () => {
            if (currentIndex >= endpoints.length) {
              reject(new Error("No response"));
              return;
            }
            
            img.src = endpoints[currentIndex];
            currentIndex++;
            
            setTimeout(tryNextEndpoint, 100);
          };
          
          tryNextEndpoint();
        });

        // Set timeout for the ping attempt
        await Promise.race([
          pingPromise,
          new Promise((_, reject) => 
            setTimeout(() => reject(new Error("Timeout")), 1000)
          )
        ]);

        const responseTime = Math.round(performance.now() - startTime);
        
        foundDevices.push({
          ip,
          status: "online",
          responseTime,
          lastSeen: new Date()
        });

        showSuccess(`Found device at ${ip} (${responseTime}ms)`);
      } catch (error) {
        // Device not found or not responding - this is expected for most IPs
        continue;
      }

      // Small delay to avoid overwhelming the network
      await new Promise(resolve => setTimeout(resolve, 10));
    }

    setDevices(foundDevices);
    setIsScanning(false);
    setScanProgress(100);
    
    if (foundDevices.length > 0) {
      showSuccess(`Found ${foundDevices.length} devices on the network`);
    } else {
      showError("No devices found on the network");
    }
  };

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Scan className="h-5 w-5" />
            Network Scanner
          </CardTitle>
          <CardDescription>
            Discover devices on your local network
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <Button 
              onClick={scanNetwork} 
              disabled={isScanning}
              className="w-full"
            >
              <RefreshCw className={`h-4 w-4 mr-2 ${isScanning ? 'animate-spin' : ''}`} />
              {isScanning ? "Scanning Network..." : "Scan Network"}
            </Button>

            {isScanning && (
              <div className="space-y-2">
                <Progress value={scanProgress} className="w-full" />
                <p className="text-sm text-muted-foreground text-center">
                  Scanning... {scanProgress}% complete
                </p>
              </div>
            )}

            {devices.length > 0 && (
              <div className="space-y-3">
                <h3 className="text-sm font-medium">Found Devices ({devices.length})</h3>
                {devices.map((device, index) => (
                  <div key={index} className="flex items-center justify-between p-3 border rounded-lg">
                    <div className="flex items-center gap-3">
                      <Server className="h-5 w-5 text-green-500" />
                      <div>
                        <span className="font-mono text-sm font-medium">{device.ip}</span>
                        <p className="text-xs text-muted-foreground">
                          Last seen: {device.lastSeen.toLocaleTimeString()}
                        </p>
                      </div>
                    </div>
                    <Badge variant="default" className="ml-2">
                      {device.responseTime}ms
                    </Badge>
                  </div>
                ))}
              </div>
            )}

            {!isScanning && devices.length === 0 && (
              <div className="text-center p-6 border rounded-lg bg-muted">
                <WifiOff className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                <p className="text-sm text-muted-foreground">
                  No devices scanned yet. Click "Scan Network" to discover devices on your local network.
                </p>
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
};

export default NetworkScanner;