import { useEffect, useState, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import NetworkMap from '@/components/NetworkMap';
import { showError } from '@/utils/toast';
import { Skeleton } from '@/components/ui/skeleton';
import { MapData, getPublicMapData } from '@/services/networkDeviceService';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';

const PublicMapPage = () => {
  const { mapId } = useParams<{ mapId: string }>();
  const [mapData, setMapData] = useState<MapData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [mapName, setMapName] = useState('Public Network Map');
  const [backgroundStyle, setBackgroundStyle] = useState({});

  const fetchPublicMap = useCallback(async () => {
    if (!mapId) {
      showError('Map ID is missing for public view.');
      setIsLoading(false);
      return;
    }
    setIsLoading(true);
    try {
      const data = await getPublicMapData(mapId);
      setMapData({ devices: data.devices, edges: data.edges });
      setMapName(data.map.name || 'Public Network Map');

      let style: React.CSSProperties = {};
      if (data.map.background_image_url) {
        style = { backgroundImage: `url(${data.map.background_image_url})`, backgroundSize: 'cover', backgroundPosition: 'center' };
      } else if (data.map.background_color) {
        style = { backgroundColor: data.map.background_color };
      } else {
        style = { backgroundColor: '#1e293b' }; // Default if nothing set
      }
      setBackgroundStyle(style);

    } catch (error: any) {
      console.error('Failed to fetch public map data:', error);
      showError(error.message || 'Failed to load public map.');
      setMapData(null);
    } finally {
      setIsLoading(false);
    }
  }, [mapId]);

  useEffect(() => {
    fetchPublicMap();
  }, [fetchPublicMap]);

  if (isLoading) {
    return (
      <div className="min-h-screen bg-background p-4">
        <Skeleton className="h-8 w-64 mb-6" />
        <Skeleton className="h-[70vh] w-full rounded-lg" />
      </div>
    );
  }

  if (!mapData) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center p-4">
        <Card className="w-full max-w-md text-center">
          <CardHeader>
            <CardTitle>Map Not Found or Not Public</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-muted-foreground">The requested map could not be loaded or is not enabled for public viewing.</p>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background" style={backgroundStyle}>
      <header className="bg-slate-800/50 backdrop-blur-lg shadow-lg py-3 px-4">
        <div className="container mx-auto flex items-center justify-between">
          <h1 className="text-xl font-bold text-white">Shared Map: {mapName}</h1>
          <a href="/" className="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-sm">
            <i className="fas fa-home mr-2"></i>Go to Dashboard
          </a>
        </div>
      </header>
      <div className="container mx-auto p-4">
        {/* Pass fetchPublicMap as onMapUpdate to trigger refreshes */}
        <NetworkMap devices={mapData.devices} onMapUpdate={fetchPublicMap} isPublicView={true} />
      </div>
    </div>
  );
};

export default PublicMapPage;