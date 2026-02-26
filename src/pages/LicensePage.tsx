import { AppLayout } from "@/components/layout/AppLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Key, Shield, CheckCircle } from "lucide-react";

export default function LicensePage() {
  return (
    <AppLayout>
      <div className="space-y-4">
        <div className="flex items-center gap-3">
          <Key className="h-7 w-7 text-primary" />
          <h1 className="text-2xl font-bold tracking-tight">License</h1>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              <Shield className="h-5 w-5 text-success" /> License Information
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <p className="text-xs text-muted-foreground mb-1">Product</p>
                <p className="font-medium">AMPNM - Advanced Multi-Platform Network Monitor</p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground mb-1">Edition</p>
                <Badge className="bg-primary text-primary-foreground">Cloud Edition</Badge>
              </div>
              <div>
                <p className="text-xs text-muted-foreground mb-1">Status</p>
                <div className="flex items-center gap-2">
                  <CheckCircle className="h-4 w-4 text-success" />
                  <span className="text-success font-medium">Active</span>
                </div>
              </div>
              <div>
                <p className="text-xs text-muted-foreground mb-1">Deployment</p>
                <p className="font-medium">Lovable Cloud</p>
              </div>
            </div>

            <div className="rounded-md border border-border bg-muted/30 p-4 text-sm text-muted-foreground">
              <p>This is the Cloud edition of AMPNM running on Lovable Cloud infrastructure. License management for the Docker self-hosted edition is handled separately through the Docker installation.</p>
            </div>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
