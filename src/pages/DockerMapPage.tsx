import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Container, LayoutDashboard } from "lucide-react";
import DockerNetworkMap from "@/components/docker-map/DockerNetworkMap";
import DockerStatusDashboard from "@/components/docker-map/DockerStatusDashboard";

const DockerMapPage = () => (
  <div className="min-h-screen bg-background">
    <div className="container mx-auto p-4">
      <div className="flex items-center gap-3 mb-4">
        <Container className="h-7 w-7 text-primary" />
        <h1 className="text-2xl font-bold text-foreground">Docker Environment</h1>
      </div>

      <Tabs defaultValue="status" className="w-full">
        <TabsList className="mb-4">
          <TabsTrigger value="status" className="flex items-center gap-2">
            <LayoutDashboard className="h-4 w-4" />
            Status Dashboard
          </TabsTrigger>
          <TabsTrigger value="topology" className="flex items-center gap-2">
            <Container className="h-4 w-4" />
            Network Map
          </TabsTrigger>
        </TabsList>

        <TabsContent value="status">
          <DockerStatusDashboard />
        </TabsContent>

        <TabsContent value="topology">
          <DockerNetworkMap />
        </TabsContent>
      </Tabs>
    </div>
  </div>
);

export default DockerMapPage;
