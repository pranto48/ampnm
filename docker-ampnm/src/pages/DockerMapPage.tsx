import DockerNetworkMap from "@/components/docker-map/DockerNetworkMap";
import { Container } from "lucide-react";

const DockerMapPage = () => (
  <div className="min-h-screen bg-background">
    <div className="container mx-auto p-4">
      <div className="flex items-center gap-3 mb-4">
        <Container className="h-7 w-7 text-primary" />
        <h1 className="text-2xl font-bold text-foreground">Docker Network Map</h1>
      </div>
      <DockerNetworkMap />
    </div>
  </div>
);

export default DockerMapPage;
