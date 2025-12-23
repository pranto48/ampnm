import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import ReactFlow, {
  MiniMap,
  Controls,
  Background,
  useNodesState,
  useEdgesState,
  Node,
  Edge,
  Connection,
  NodeDragHandler,
  OnEdgesChange,
  applyNodeChanges,
  applyEdgeChanges,
} from 'reactflow';
import 'reactflow/dist/style.css';
import { Button } from '@/components/ui/button';
import { PlusCircle, Upload, Download, Share2 } from 'lucide-react';
import {
  addDevice,
  updateDevice,
  deleteDevice,
  NetworkDevice,
  getEdges,
  addEdgeToDB,
  deleteEdgeFromDB,
  updateEdgeInDB,
  importMap,
  MapData,
  getPublicMapData,
} from '@/services/networkDeviceService';
import { performServerPing } from '@/services/pingService'; // Import performServerPing
import { EdgeEditorDialog } from './EdgeEditorDialog';
import DeviceNode from './DeviceNode';
import { showSuccess, showError, showLoading, dismissToast } from '@/utils/toast';
import { useNavigate, useParams } from 'react-router-dom';
import { buildPublicMapUrl, getDockerBaseUrl } from '@/utils/url';

// Declare SoundManager globally
declare global {
  interface Window {
    SoundManager: {
      play: (soundName: string) => void;
    };
    userRole: string; // Declare userRole
  }
}

interface NetworkMapProps {
  devices: NetworkDevice[];
  onMapUpdate: () => void; // This prop will now trigger a full refresh including pings
  isPublicView?: boolean; // New prop for public view
}

const NetworkMap = ({ devices: initialDevices, onMapUpdate, isPublicView = false }: NetworkMapProps) => {
  const { mapId: publicMapId } = useParams<{ mapId: string }>();
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [isEdgeEditorOpen, setIsEdgeEditorOpen] = useState(false);
  const [editingEdge, setEditingEdge] = useState<Edge | undefined>(undefined);
  const importInputRef = useRef<HTMLInputElement>(null);
  const navigate = useNavigate();

  // Get user role from global scope
  const userRole = window.userRole || 'viewer';
  const isAdmin = userRole === 'admin';

  const nodeTypes = useMemo(() => ({ device: DeviceNode }), []);

  // Function to trigger server-side pings and then fetch updated data
    const refreshMapContent = useCallback(async (currentMapId: string) => {
      const apiBase = `${getDockerBaseUrl()}/api.php`;
      try {
        // 1. Trigger server-side pings for all devices on this map
        // This API call is allowed for all roles (admin, viewer)
        const pingResponse = await fetch(`${apiBase}?action=ping_all_devices`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ map_id: currentMapId }),
        });
      const pingResult = await pingResponse.json();
      if (!pingResponse.ok || pingResult.error || !pingResult.success) {
        console.error('API ping_all_devices failed:', pingResult); // Added detailed log
        throw new Error(pingResult.error || pingResult.message || 'Failed to trigger device pings.');
      }

      // 2. Fetch the updated device and edge data
      let updatedDevices: NetworkDevice[] = [];
      let updatedEdges: Edge[] = [];
      if (isPublicView && publicMapId) {
        const data = await getPublicMapData(publicMapId);
        updatedDevices = data.devices.map(d => ({
          ...d,
          ip_address: d.ip, // Map ip to ip_address for React component
          icon: d.type, // Map type to icon for React component
          position_x: d.x,
          position_y: d.y,
          last_ping: d.last_seen,
        }));
        updatedEdges = data.edges.map(e => ({
          id: e.id,
          source: e.source_id,
          target: e.target_id,
          data: { connection_type: e.connection_type || 'cat5' },
        }));
      } else {
        // For authenticated views, fetch all devices and filter by map_id
        const allDevices = await fetch(`${apiBase}?action=get_devices&map_id=${currentMapId}`).then(res => res.json());
        updatedDevices = (allDevices.devices || []).map((d: any) => ({
          id: d.id,
          name: d.name,
          ip_address: d.ip,
          position_x: d.x,
          position_y: d.y,
          icon: d.type,
          status: d.status || 'unknown',
          ping_interval: d.ping_interval,
          icon_size: d.icon_size,
          name_text_size: d.name_text_size,
          last_ping: d.last_seen,
          last_ping_result: d.status === 'online',
          check_port: d.check_port,
          description: d.description,
          warning_latency_threshold: d.warning_latency_threshold,
          warning_packetloss_threshold: d.warning_packetloss_threshold,
          critical_latency_threshold: d.critical_latency_threshold,
          critical_packetloss_threshold: d.critical_packetloss_threshold,
          show_live_ping: d.show_live_ping,
          map_id: d.map_id,
        }));
        const edgesData = await getEdges();
        updatedEdges = edgesData.map((edge: any) => ({
          id: edge.id,
          source: edge.source_id,
          target: edge.target_id,
          data: { connection_type: edge.connection_type || 'cat5' },
        }));
      }

      setNodes(updatedDevices.map(mapDeviceToNode));
      setEdges(updatedEdges);

    } catch (error) {
      console.error('Failed to refresh map content:', error);
      showError('Failed to refresh map content.');
    }
  }, [isPublicView, publicMapId, setNodes, setEdges]);


  const handleStatusChange = useCallback(
    async (nodeId: string, status: 'online' | 'offline') => {
      // This function is for individual device status updates, typically from manual pings.
      // It's allowed for all roles in public view, and for admins in non-public view.
      if (!isAdmin && !isPublicView) {
        return;
      }
      // Optimistically update UI
      setNodes((nds) =>
        nds.map((node) => (node.id === nodeId ? { ...node, data: { ...node.data, status } } : node))
      );
      try {
        // Update in database
        const device = initialDevices.find(d => d.id === nodeId);
        if (device && device.ip_address) {
          await updateDevice(nodeId, { 
            status,
            last_ping: new Date().toISOString(), // PHP uses last_seen
          });
          // After individual update, refresh the whole map to ensure consistency
          const currentMapId = initialDevices[0]?.map_id || publicMapId;
          if (currentMapId) {
            refreshMapContent(currentMapId);
          }
        }
      } catch (error) {
        console.error('Failed to update device status in DB:', error);
        showError('Failed to update device status.');
        // Revert UI update on failure
        setNodes((nds) =>
          nds.map((node) => (node.id === nodeId ? { ...node, data: { ...node.data, status: device?.status || 'unknown' } : node))
        );
      }
    },
    [setNodes, initialDevices, isAdmin, isPublicView, publicMapId, refreshMapContent]
  );

  const mapDeviceToNode = useCallback(
    (device: NetworkDevice): Node => ({
      id: device.id!,
      type: 'device',
      position: { x: device.position_x || 0, y: device.position_y || 0 }, // Ensure default position
      data: {
        id: device.id,
        name: device.name,
        ip_address: device.ip_address,
        icon: device.icon,
        status: device.status || 'unknown',
        ping_interval: device.ping_interval,
        icon_size: device.icon_size,
        name_text_size: device.name_text_size,
        last_ping: device.last_ping,
        last_ping_result: device.status === 'online', // Derive from status
        check_port: device.check_port,
        description: device.description,
        warning_latency_threshold: device.warning_latency_threshold,
        warning_packetloss_threshold: device.warning_packetloss_threshold,
        critical_latency_threshold: device.critical_latency_threshold,
        critical_packetloss_threshold: device.critical_packetloss_threshold,
        show_live_ping: device.show_live_ping,
        map_id: device.map_id,
        onEdit: (id: string) => {
          if (!isPublicView && isAdmin) {
            navigate(`/edit-device/${id}`);
          }
        },
        onDelete: (id: string) => {
          if (!isPublicView && isAdmin) {
            handleDelete(id);
          }
        },
        onStatusChange: handleStatusChange,
        isPublicView: isPublicView, // Pass public view status to node
      },
    }),
    [handleStatusChange, navigate, isAdmin, isPublicView]
  );

  // Initial load and periodic polling
  useEffect(() => {
    const currentMapId = initialDevices[0]?.map_id || publicMapId;
    if (currentMapId) {
      refreshMapContent(currentMapId); // Initial load

      const pollingInterval = setInterval(() => {
        refreshMapContent(currentMapId);
      }, 15000); // Poll every 15 seconds

      return () => clearInterval(pollingInterval);
    }
  }, [initialDevices, publicMapId, refreshMapContent]);

  // Effect to trigger refresh when parent requests it
  useEffect(() => {
    const currentMapId = initialDevices[0]?.map_id || publicMapId;
    if (currentMapId && onMapUpdate) {
      // This is a dummy effect to make onMapUpdate work as a trigger.
      // The actual refresh is handled by the polling useEffect.
      // If onMapUpdate is called, it means the parent wants a refresh,
      // so we can just call refreshMapContent directly.
      // However, to avoid infinite loops if onMapUpdate itself causes a re-render,
      // we'll rely on the polling and initial load.
      // If onMapUpdate is truly meant to be an *immediate* trigger,
      // the parent should call refreshMapContent directly.
      // For now, we'll ensure the map is updated by the polling.
    }
  }, [onMapUpdate, initialDevices, publicMapId, refreshMapContent]);


  // Ref to store previous devices for status comparison
  const prevDevicesRef = useRef<NetworkDevice[]>([]);

  // Effect to detect status changes and play sounds
  useEffect(() => {
    const prevDevicesMap = new Map(prevDevicesRef.current.map(d => [d.id, d.status]));

    initialDevices.forEach(currentDevice => {
      const prevStatus = prevDevicesMap.get(currentDevice.id);
      const newStatus = currentDevice.status;

      if (prevStatus && newStatus && prevStatus !== newStatus) {
        // Play sound based on new status
        if (window.SoundManager) {
          if (newStatus === 'online' && (prevStatus === 'offline' || prevStatus === 'critical' || prevStatus === 'warning')) {
            window.SoundManager.play('online');
          } else if (newStatus === 'warning') {
            window.SoundManager.play('warning');
          } else if (newStatus === 'critical') {
            window.SoundManager.play('critical');
          } else if (newStatus === 'offline') {
            window.SoundManager.play('offline');
          }
        }
      }
    });

    // Update ref for the next render
    prevDevicesRef.current = initialDevices;
  }, [initialDevices]);


  // Style edges based on connection type and device status
  const styledEdges = useMemo(() => {
    return edges.map((edge) => {
      const sourceNode = nodes.find((n) => n.id === edge.source);
      const targetNode = nodes.find((n) => n.id === edge.target);
      const isConnectionBroken = 
        sourceNode?.data.status === 'offline' || 
        targetNode?.data.status === 'offline';
      
      const type = edge.data?.connection_type || 'cat5';
      let style: React.CSSProperties = { strokeWidth: 2 };
      
      if (isConnectionBroken) {
        style.stroke = '#ef4444'; // Red for offline
      } else {
        switch (type) {
          case 'fiber': 
            style.stroke = '#f97316'; // Orange
            break;
          case 'wifi': 
            style.stroke = '#38bdf8'; // Sky blue
            style.strokeDasharray = '5, 5';
            break;
          case 'radio': 
            style.stroke = '#84cc16'; // Lime green
            style.strokeDasharray = '2, 7';
            break;
          case 'cat5': 
          default: 
            style.stroke = '#a78bfa'; // Violet
            break;
        }
      }

      return { 
        ...edge, 
        animated: !isConnectionBroken, 
        style, 
        label: type,
        labelStyle: { fill: 'white', fontWeight: 'bold' }
      };
    });
  }, [nodes, edges]);

  const onConnect = useCallback(
    async (params: Connection) => {
      if (!isAdmin || isPublicView) {
        return;
      }
      // Optimistically add edge to UI
      const newEdge = { 
        id: `reactflow__edge-${params.source}${params.target}`, 
        source: params.source!, 
        target: params.target!, 
        data: { connection_type: 'cat5' } 
      };
      setEdges((eds) => applyEdgeChanges([{ type: 'add', item: newEdge }], eds));
      
      try {
        // Save to database
        await addEdgeToDB({ source: params.source!, target: params.target!, map_id: initialDevices[0]?.map_id || '1' }); // Assuming map_id 1 if not set
        showSuccess('Connection saved.');
      } catch (error) {
        console.error('Failed to save connection:', error);
        showError('Failed to save connection.');
        // Revert UI update on failure
        setEdges((eds) => eds.filter(e => e.id !== newEdge.id));
      }
    },
    [setEdges, isAdmin, isPublicView, initialDevices]
  );

  const handleDelete = async (deviceId: string) => {
    if (!isAdmin || isPublicView) {
      return;
    }
    if (window.confirm('Are you sure you want to delete this device?')) {
      // Optimistically remove from UI
      const originalNodes = nodes;
      setNodes((nds) => nds.filter((node) => node.id !== deviceId));
      
      try {
        // Delete from database
        await deleteDevice(deviceId);
        showSuccess('Device deleted successfully.');
        const currentMapId = initialDevices[0]?.map_id || publicMapId;
        if (currentMapId) {
          refreshMapContent(currentMapId); // Refresh map after deletion
        }
      } catch (error) {
        console.error('Failed to delete device:', error);
        showError('Failed to delete device.');
        // Revert UI update on failure
        setNodes(originalNodes);
      }
    }
  };

  const onNodeDragStop: NodeDragHandler = useCallback(
    async (_event, node) => {
      if (!isAdmin || isPublicView) {
        return;
      }
      try {
        await updateDevice(node.id, { position_x: node.position.x, position_y: node.position.y });
      } catch (error) {
        console.error('Failed to save device position:', error);
        showError('Failed to save device position.');
      }
    },
    [isAdmin, isPublicView]
  );

  const onEdgesChangeHandler: OnEdgesChange = useCallback(
    (changes) => {
      onEdgesChange(changes);
      changes.forEach(async (change) => {
        if (change.type === 'remove') {
          if (!isAdmin || isPublicView) {
            return;
          }
          try {
            await deleteEdgeFromDB(change.id);
            showSuccess('Connection deleted.');
          } catch (error) {
            console.error('Failed to delete connection:', error);
            showError('Failed to delete connection.');
          }
        }
      });
    },
    [onEdgesChange, isAdmin, isPublicView]
  );

  const onEdgeClick = (_event: React.MouseEvent, edge: Edge) => {
    if (!isAdmin || isPublicView) {
      return;
    }
    setEditingEdge(edge);
    setIsEdgeEditorOpen(true);
  };

  const handleSaveEdge = async (edgeId: string, connectionType: string) => {
    if (!isAdmin || isPublicView) {
      return;
    }
    // Optimistically update UI
    const originalEdges = edges;
    setEdges((eds) => eds.map(e => e.id === edgeId ? { ...e, data: { connection_type } } : e));
    
    try {
      // Update in database
      await updateEdgeInDB(edgeId, { connection_type });
      showSuccess('Connection updated.');
    } catch (error) {
      console.error('Failed to update connection:', error);
      showError('Failed to update connection.');
      // Revert UI update on failure
      setEdges(originalEdges);
    }
  };

  const handleExport = async () => {
    if (!isAdmin || isPublicView) {
      return;
    }
    const exportData: MapData = {
      devices: initialDevices.map(({ user_id, status, last_ping, last_ping_result, ...rest }) => ({
        ...rest,
        ip_address: rest.ip_address || null,
        position_x: rest.position_x || null,
        position_y: rest.position_y || null,
        ping_interval: rest.ping_interval || null,
        icon_size: rest.icon_size || null,
        name_text_size: rest.name_text_size || null,
        check_port: rest.check_port || null,
        description: rest.description || null,
        warning_latency_threshold: rest.warning_latency_threshold || null,
        warning_packetloss_threshold: rest.warning_packetloss_threshold || null,
        critical_latency_threshold: rest.critical_latency_threshold || null,
        critical_packetloss_threshold: rest.critical_packetloss_threshold || null,
        show_live_ping: rest.show_live_ping || false,
        map_id: rest.map_id || null,
      })),
      edges: edges.map(({ id, source, target, data }) => ({ 
        source, 
        target, 
        connection_type: data.connection_type || 'cat5' 
      })),
    };
    const jsonString = JSON.stringify(exportData, null, 2);
    const blob = new Blob([jsonString], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'network-map.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showSuccess('Map exported successfully!');
  };

  const handleImportClick = () => {
    if (!isAdmin || isPublicView) {
      return;
    }
    importInputRef.current?.click();
  };

  const handleFileChange = async (event: React.ChangeEvent<HTMLInputElement>) => {
    if (!isAdmin || isPublicView) {
      return;
    }
    const file = event.target.files?.[0];
    if (!file) return;
    if (!window.confirm('Are you sure you want to import this map? This will overwrite your current map.')) return;

    const reader = new FileReader();
    reader.onload = async (e) => {
      const toastId = showLoading('Importing map...');
      try {
        const mapData = JSON.parse(e.target?.result as string) as MapData;
        if (!mapData.devices || !mapData.edges) throw new Error('Invalid map file format.');
        await importMap(mapData, initialDevices[0]?.map_id || '1'); // Pass current map_id
        dismissToast(toastId);
        showSuccess('Map imported successfully!');
        const currentMapId = initialDevices[0]?.map_id || publicMapId;
        if (currentMapId) {
          refreshMapContent(currentMapId); // Refresh map after import
        }
      } catch (error: any) {
        dismissToast(toastId);
        console.error('Failed to import map:', error);
        showError(error.message || 'Failed to import map.');
      } finally {
        if (importInputRef.current) importInputRef.current.value = '';
      }
    };
    reader.readAsText(file);
  };

  const handleShareMap = async () => {
    // Share map is accessible to all roles, as it's just generating a public link.
    const currentMapId = initialDevices.length > 0 ? initialDevices[0].map_id : null;

    if (!currentMapId) {
      showError('No map selected to share.');
      return;
    }

    // Construct the shareable URL based on the active host/port
    const shareUrl = buildPublicMapUrl(currentMapId);

    try {
      await navigator.clipboard.writeText(shareUrl);
      showSuccess('Share link copied to clipboard!');
    } catch (err) {
      console.error('Failed to copy share link:', err);
      showError('Failed to copy share link. Please copy manually: ' + shareUrl);
    }
  };

  return (
    <div style={{ height: '70vh', width: '100%' }} className="relative border rounded-lg bg-gray-900">
      <ReactFlow
        nodes={nodes}
        edges={styledEdges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChangeHandler}
        onConnect={onConnect}
        nodeTypes={nodeTypes}
        onNodeDragStop={onNodeDragStop}
        onEdgeClick={onEdgeClick}
        fitView
        fitViewOptions={{ padding: 0.1 }}
        proOptions={{ hideAttribution: true }} // Hide React Flow attribution
        nodesDraggable={!isPublicView && isAdmin} // Only allow admins to drag nodes in non-public view
        nodesConnectable={!isPublicView && isAdmin} // Only allow admins to connect nodes in non-public view
        elementsSelectable={!isPublicView && isAdmin} // Only allow admins to select elements in non-public view
      >
        <Controls />
        <MiniMap 
          nodeColor={(n) => {
            switch (n.data.status) {
              case 'online': return '#22c55e';
              case 'offline': return '#ef4444';
              default: return '#94a3b8';
            }
          }} 
          nodeStrokeWidth={3} 
          maskColor="rgba(15, 23, 42, 0.8)"
        />
        <Background gap={16} size={1} color="#444" />
      </ReactFlow>
      <div className="absolute top-4 left-4 flex flex-wrap gap-2">
        {!isPublicView && isAdmin && (
          <Button onClick={() => navigate('/add-device')} size="sm">
            <PlusCircle className="h-4 w-4 mr-2" />Add Device
          </Button>
        )}
        {!isPublicView && isAdmin && (
          <Button onClick={handleExport} variant="outline" size="sm">
            <Download className="h-4 w-4 mr-2" />Export
          </Button>
        )}
        {!isPublicView && isAdmin && (
          <Button onClick={handleImportClick} variant="outline" size="sm">
            <Upload className="h-4 w-4 mr-2" />Import
          </Button>
        )}
        <input 
          type="file" 
          ref={importInputRef} 
          onChange={handleFileChange} 
          accept="application/json" 
          className="hidden" 
        />
        {!isPublicView && ( // Share button is available for all roles in non-public view
          <Button onClick={handleShareMap} variant="outline" size="sm">
            <Share2 className="h-4 w-4 mr-2" />Share Map
          </Button>
        )}
      </div>
      {isEdgeEditorOpen && !isPublicView && isAdmin && ( // Only open for admin in non-public view
        <EdgeEditorDialog 
          isOpen={isEdgeEditorOpen} 
          onClose={() => setIsEdgeEditorOpen(false)} 
          onSave={handleSaveEdge} 
          edge={editingEdge} 
        />
      )}
    </div>
  );
};

export default NetworkMap;