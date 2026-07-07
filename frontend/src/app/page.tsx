'use client';

import React, { useState, useEffect, useRef } from 'react';
import { 
  Play, 
  Pause, 
  Activity, 
  Cpu, 
  HardDrive, 
  Database, 
  Network, 
  RefreshCw, 
  CheckCircle, 
  AlertTriangle, 
  AlertOctagon, 
  HelpCircle,
  Sliders,
  History
} from 'lucide-react';

interface Device {
  id: number;
  name: string;
  ip: string;
  type: string;
  x: number;
  y: number;
  status: string;
  cpu_usage: number | null;
  memory_usage: number | null;
  disk_usage: number | null;
  network_in: number | null;
  network_out: number | null;
  last_avg_time: number | null;
  last_ttl: number | null;
  last_seen: string | null;
}

interface Edge {
  id: number;
  source_id: number;
  target_id: number;
  connection_type: string;
}

interface MapInfo {
  id: number;
  name: string;
  is_default: number;
}

export default function ObservabilityDashboard() {
  const [maps, setMaps] = useState<MapInfo[]>([]);
  const [selectedMapId, setSelectedMapId] = useState<number | null>(null);
  const [mapName, setMapName] = useState<string>('');
  
  const [devices, setDevices] = useState<Device[]>([]);
  const [edges, setEdges] = useState<Edge[]>([]);
  const [selectedDevice, setSelectedDevice] = useState<Device | null>(null);

  // Animation speed control
  const [speed, setSpeed] = useState<number>(1.0);
  const speedRef = useRef<number>(1.0);

  // Timeline & History scrubbing
  const [timelineVal, setTimelineVal] = useState<number>(0); // 0 = Live, >0 = hours ago
  const [isPlaying, setIsPlaying] = useState<boolean>(false);
  const timelineRef = useRef<number>(0);

  // Real-time vs Historical state tracking
  const [isLive, setIsLive] = useState<boolean>(true);
  const devicesRef = useRef<Device[]>([]);
  const edgesRef = useRef<Edge[]>([]);
  const progressRef = useRef<number>(0);

  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const wsRef = useRef<WebSocket | null>(null);

  // 1. Fetch maps list on startup
  useEffect(() => {
    fetch('/api/map/list')
      .then(res => res.json())
      .then((data: MapInfo[]) => {
        setMaps(data);
        if (data.length > 0) {
          // Find default map or pick the first one
          const defMap = data.find(m => m.is_default === 1) || data[0];
          setSelectedMapId(defMap.id);
          setMapName(defMap.name);
        }
      })
      .catch(err => console.error('Error fetching maps:', err));
  }, []);

  // 2. Load map data (devices and edges) when map selection changes
  useEffect(() => {
    if (selectedMapId === null) return;
    
    // Reset selection and timeline
    setSelectedDevice(null);
    setTimelineVal(0);
    setIsLive(true);
    timelineRef.current = 0;

    fetch(`/api/map/data?map_id=${selectedMapId}`)
      .then(res => res.json())
      .then(data => {
        if (data.devices) {
          setDevices(data.devices);
          devicesRef.current = data.devices;
        }
        if (data.edges) {
          setEdges(data.edges);
          edgesRef.current = data.edges;
        }
      })
      .catch(err => console.error('Error loading map data:', err));
  }, [selectedMapId]);

  // Keep references updated for the 60FPS animation thread
  useEffect(() => {
    devicesRef.current = devices;
  }, [devices]);

  useEffect(() => {
    edgesRef.current = edges;
  }, [edges]);

  // 3. Connect to WebSocket Gateway (only active in Live mode)
  useEffect(() => {
    // Attempt connection
    const connectWS = () => {
      const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
      // Next.js dev server runs on 3000, websocket server route hosts on port 8080
      const wsUrl = `${protocol}//${window.location.hostname}:8080`;
      
      console.log('Connecting to WebSocket:', wsUrl);
      const ws = new WebSocket(wsUrl);
      wsRef.current = ws;

      ws.onmessage = (event) => {
        // Skip real-time updates if user is viewing history
        if (timelineRef.current > 0) return;

        try {
          const payload = JSON.parse(event.data);
          if (payload.device_id && payload.status) {
            // Update device status dynamically in state
            setDevices(prev => prev.map(dev => {
              if (dev.id === Number(payload.device_id)) {
                const updated = {
                  ...dev,
                  status: payload.status,
                  last_seen: payload.last_seen || dev.last_seen,
                  last_avg_time: payload.last_avg_time !== undefined ? payload.last_avg_time : dev.last_avg_time,
                  last_ttl: payload.last_ttl !== undefined ? payload.last_ttl : dev.last_ttl,
                };
                // If this device is currently open in the detail card, update it too
                setSelectedDevice(current => {
                  if (current && current.id === dev.id) {
                    return updated;
                  }
                  return current;
                });
                return updated;
              }
              return dev;
            }));
          }
        } catch (e) {
          console.error('Error handling WS update:', e);
        }
      };

      ws.onclose = () => {
        console.log('WS connection closed. Reconnecting in 5s...');
        setTimeout(connectWS, 5000);
      };

      ws.onerror = (err) => {
        console.error('WS Error:', err);
      };
    };

    connectWS();

    // Trigger API route once to boot up/guarantee ws server is running
    fetch('/api/ws').catch(() => {});

    return () => {
      if (wsRef.current) wsRef.current.close();
    };
  }, []);

  // Update speed ref instantly to avoid thread lags
  useEffect(() => {
    speedRef.current = speed;
  }, [speed]);

  // 4. Ingest Historical scrubbing data
  const handleTimelineChange = async (val: number) => {
    setTimelineVal(val);
    timelineRef.current = val;
    
    if (val === 0) {
      setIsLive(true);
      // Reload live map data
      if (selectedMapId) {
        const res = await fetch(`/api/map/data?map_id=${selectedMapId}`);
        const data = await res.json();
        setDevices(data.devices || []);
      }
    } else {
      setIsLive(false);
      setIsPlaying(false);
      // Query database for historical statuses at X hours ago
      const targetTime = new Date(Date.now() - val * 3600 * 1000).toISOString();
      try {
        const res = await fetch(`/api/map/history?timestamp=${encodeURIComponent(targetTime)}&map_id=${selectedMapId}`);
        const historicalStates: { id: number; status: string }[] = await res.json();
        
        // Map historical states into device layouts
        setDevices(prev => prev.map(dev => {
          const match = historicalStates.find(h => h.id === dev.id);
          return {
            ...dev,
            status: match ? match.status : 'unknown'
          };
        }));
      } catch (err) {
        console.error('Error fetching historical statuses:', err);
      }
    }
  };

  // Timeline autoplay play/pause
  useEffect(() => {
    let playInterval: any;
    if (isPlaying) {
      playInterval = setInterval(() => {
        setTimelineVal(prev => {
          const next = prev - 1;
          if (next <= 0) {
            setIsPlaying(false);
            handleTimelineChange(0);
            return 0;
          }
          handleTimelineChange(next);
          return next;
        });
      }, 2000);
    }
    return () => clearInterval(playInterval);
  }, [isPlaying]);

  // 5. Hardware-Accelerated Canvas Rendering Loop
  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    let animId: number;

    const resizeCanvas = () => {
      const rect = canvas.parentElement?.getBoundingClientRect();
      canvas.width = rect?.width || 800;
      canvas.height = rect?.height || 600;
    };
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    const renderLoop = () => {
      ctx.clearRect(0, 0, canvas.width, canvas.height);

      const currentDevices = devicesRef.current;
      const currentEdges = edgesRef.current;

      // Draw Edges (Links)
      ctx.lineWidth = 2.5;
      currentEdges.forEach(edge => {
        const source = currentDevices.find(d => d.id === edge.source_id);
        const target = currentDevices.find(d => d.id === edge.target_id);
        if (!source || !target) return;

        // Draw static edge
        ctx.strokeStyle = 'rgba(74, 85, 104, 0.4)'; // slate color with alpha
        ctx.beginPath();
        ctx.moveTo(source.x, source.y);
        ctx.lineTo(target.x, target.y);
        ctx.stroke();

        // Draw animated data pulses (Only in Live mode)
        if (timelineRef.current === 0) {
          const dx = target.x - source.x;
          const dy = target.y - source.y;
          const distance = Math.hypot(dx, dy);
          
          // Draw 3 spaced pulses along the link line
          for (let i = 0; i < 3; i++) {
            const pulseProgress = (progressRef.current + (i * 0.33)) % 1;
            const px = source.x + dx * pulseProgress;
            const py = source.y + dy * pulseProgress;

            // Pulse glowing halo effect
            ctx.save();
            ctx.shadowBlur = 12;
            ctx.shadowColor = '#00F2FE';
            ctx.fillStyle = '#00F2FE';
            ctx.beginPath();
            ctx.arc(px, py, 4.5, 0, 2 * Math.PI);
            ctx.fill();
            ctx.restore();
          }
        }
      });

      // Draw Nodes (Devices)
      currentDevices.forEach(dev => {
        const isSelected = selectedDevice && selectedDevice.id === dev.id;
        
        // Match status to modern colors
        let color = '#95A5A6'; // Grey (offline)
        if (dev.status === 'online') {
          color = '#2ECC71'; // Green (healthy)
          
          // Warning threshold checks
          if (dev.cpu_usage && dev.cpu_usage > 85) {
            color = '#F1C40F'; // Yellow (warning)
          }
          if (dev.cpu_usage && dev.cpu_usage > 95) {
            color = '#E74C3C'; // Red (critical)
          }
        } else if (dev.status === 'warning') {
          color = '#F1C40F';
        } else if (dev.status === 'critical') {
          color = '#E74C3C';
        }

        // Circular Glowing Halo for active nodes
        if (dev.status === 'online') {
          ctx.save();
          ctx.shadowBlur = isSelected ? 24 : 10;
          ctx.shadowColor = color;
          ctx.fillStyle = color;
          ctx.beginPath();
          ctx.arc(dev.x, dev.y, 16, 0, 2 * Math.PI);
          ctx.fill();
          ctx.restore();
        } else {
          ctx.fillStyle = color;
          ctx.beginPath();
          ctx.arc(dev.x, dev.y, 16, 0, 2 * Math.PI);
          ctx.fill();
        }

        // Internal indicator circle
        ctx.fillStyle = '#1A202C';
        ctx.beginPath();
        ctx.arc(dev.x, dev.y, 12, 0, 2 * Math.PI);
        ctx.fill();

        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.arc(dev.x, dev.y, 7, 0, 2 * Math.PI);
        ctx.fill();

        // Node label
        ctx.font = isSelected ? 'bold 12px Inter, sans-serif' : '11px Inter, sans-serif';
        ctx.fillStyle = isSelected ? '#FFFFFF' : '#CBD5E0';
        ctx.textAlign = 'center';
        ctx.fillText(dev.name, dev.x, dev.y + 32);

        // IP address subtext
        ctx.font = '9px Inter, sans-serif';
        ctx.fillStyle = '#718096';
        ctx.fillText(dev.ip, dev.x, dev.y + 44);
      });

      // Update pulse progress (only if timeline is Live)
      if (timelineRef.current === 0) {
        progressRef.current = (progressRef.current + (0.005 * speedRef.current)) % 1;
      }

      animId = requestAnimationFrame(renderLoop);
    };

    renderLoop();

    return () => {
      cancelAnimationFrame(animId);
      window.removeEventListener('resize', resizeCanvas);
    };
  }, [selectedDevice]);

  // Handle canvas click mapping to select devices
  const handleCanvasClick = (e: React.MouseEvent<HTMLCanvasElement>) => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const rect = canvas.getBoundingClientRect();
    const clickX = e.clientX - rect.left;
    const clickY = e.clientY - rect.top;

    // Find node within 24px threshold
    const found = devices.find(d => Math.hypot(d.x - clickX, d.y - clickY) < 24);
    setSelectedDevice(found || null);
  };

  const getStatusIcon = (status: string, cpu: number | null) => {
    if (status !== 'online') return <AlertOctagon className="w-5 h-5 text-gray-400" />;
    if (cpu && cpu > 95) return <AlertOctagon className="w-5 h-5 text-red-500 animate-pulse" />;
    if (cpu && cpu > 85) return <AlertTriangle className="w-5 h-5 text-yellow-500" />;
    return <CheckCircle className="w-5 h-5 text-emerald-500" />;
  };

  const getStatusText = (status: string, cpu: number | null) => {
    if (status !== 'online') return 'Offline';
    if (cpu && cpu > 95) return 'Critical Alarm';
    if (cpu && cpu > 85) return 'Warning Alert';
    return 'Healthy / Online';
  };

  return (
    <div className="flex flex-col h-screen bg-slate-950 text-slate-100 font-sans overflow-hidden">
      {/* 1. Header Area */}
      <header className="flex items-center justify-between px-6 py-4 bg-slate-900/80 border-b border-slate-800 backdrop-blur-md z-10">
        <div className="flex items-center gap-3">
          <Activity className="w-7 h-7 text-cyan-400 animate-pulse" />
          <div>
            <h1 className="text-xl font-bold tracking-tight bg-gradient-to-r from-cyan-400 to-blue-500 bg-clip-text text-transparent">
              AMPNM Next-Gen Observability
            </h1>
            <p className="text-[10px] text-slate-400 uppercase tracking-widest">
              High-Concurrency Go + React Ingest Gateway
            </p>
          </div>
        </div>

        <div className="flex items-center gap-4">
          {/* Map Selector */}
          <div className="flex items-center gap-2">
            <span className="text-xs text-slate-400">Target Map:</span>
            <select
              value={selectedMapId || ''}
              onChange={(e) => {
                const id = Number(e.target.value);
                setSelectedMapId(id);
                const m = maps.find(x => x.id === id);
                if (m) setMapName(m.name);
              }}
              className="bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-sm text-slate-200 outline-none focus:border-cyan-500 transition-colors"
            >
              {maps.map(m => (
                <option key={m.id} value={m.id}>{m.name}</option>
              ))}
            </select>
          </div>

          {/* Live indicator bubble */}
          <div className={`flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider transition-colors ${
            isLive ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-amber-500/10 border border-amber-500/30 text-amber-400'
          }`}>
            <span className={`w-2.5 h-2.5 rounded-full ${isLive ? 'bg-emerald-400 animate-ping' : 'bg-amber-400'}`} />
            {isLive ? 'Live Status' : `Historical View`}
          </div>
        </div>
      </header>

      {/* 2. Main content panels */}
      <main className="flex flex-1 overflow-hidden relative">
        {/* Left pane: Network topology rendering */}
        <div className="flex-1 relative bg-slate-950/40 cursor-crosshair">
          {/* Background grid */}
          <div className="absolute inset-0 bg-[linear-gradient(to_right,#0f172a_1px,transparent_1px),linear-gradient(to_bottom,#0f172a_1px,transparent_1px)] bg-[size:32px_32px] opacity-60" />
          
          <canvas
            ref={canvasRef}
            onClick={handleCanvasClick}
            className="absolute inset-0 w-full h-full z-0"
          />

          {/* Floating Speed Control HUD */}
          <div className="absolute top-4 left-4 bg-slate-900/90 border border-slate-800/80 rounded-xl p-4 shadow-2xl backdrop-blur-md z-10 w-64">
            <div className="flex items-center gap-2 text-cyan-400 mb-3 font-semibold text-xs uppercase tracking-wider">
              <Sliders className="w-4 h-4" /> Pulse Edge Animation
            </div>
            <div className="space-y-2">
              <div className="flex justify-between text-xs text-slate-400">
                <span>Ingestion Velocity:</span>
                <span className="font-mono text-cyan-300 font-bold">{speed.toFixed(1)}x</span>
              </div>
              <input
                id="animationSpeedSelector"
                type="range"
                min="0.1"
                max="5.0"
                step="0.1"
                value={speed}
                disabled={!isLive}
                onChange={(e) => setSpeed(parseFloat(e.target.value))}
                className="w-full h-1 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-cyan-400 disabled:opacity-40"
              />
              {!isLive && (
                <div className="text-[10px] text-amber-500 font-medium text-center">
                  Pulses disabled during historical views
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Right pane: Detailed Metrics HUD (glassmorphism details card) */}
        <div className="w-96 bg-slate-900/60 border-l border-slate-800/80 backdrop-blur-lg flex flex-col z-10 shadow-2xl overflow-y-auto">
          {selectedDevice ? (
            <div className="p-6 space-y-6">
              {/* Header metrics card */}
              <div className="border-b border-slate-800 pb-4">
                <div className="flex justify-between items-start mb-2">
                  <h2 className="text-xl font-bold tracking-tight text-white">{selectedDevice.name}</h2>
                  {getStatusIcon(selectedDevice.status, selectedDevice.cpu_usage)}
                </div>
                <p className="text-xs text-slate-400 font-mono mb-2">{selectedDevice.ip}</p>
                <div className="text-xs flex items-center gap-1.5 font-semibold text-slate-300">
                  <span className={`w-2 h-2 rounded-full ${
                    selectedDevice.status === 'online' ? 'bg-emerald-400' : 'bg-gray-400'
                  }`} />
                  {getStatusText(selectedDevice.status, selectedDevice.cpu_usage)}
                </div>
              </div>

              {/* Dynamic stats graphs */}
              {selectedDevice.status === 'online' ? (
                <div className="space-y-4">
                  {/* CPU Usage progress */}
                  <div className="bg-slate-950/50 border border-slate-850 p-4 rounded-xl space-y-2">
                    <div className="flex justify-between text-xs">
                      <span className="flex items-center gap-1.5 text-slate-400">
                        <Cpu className="w-3.5 h-3.5 text-cyan-400" /> CPU Allocation
                      </span>
                      <span className="font-mono font-bold text-cyan-400">
                        {selectedDevice.cpu_usage !== null ? `${selectedDevice.cpu_usage}%` : 'N/A'}
                      </span>
                    </div>
                    <div className="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                      <div 
                        className="bg-cyan-400 h-full transition-all duration-500 rounded-full" 
                        style={{ width: `${selectedDevice.cpu_usage || 0}%` }}
                      />
                    </div>
                  </div>

                  {/* Memory Usage progress */}
                  <div className="bg-slate-950/50 border border-slate-850 p-4 rounded-xl space-y-2">
                    <div className="flex justify-between text-xs">
                      <span className="flex items-center gap-1.5 text-slate-400">
                        <Database className="w-3.5 h-3.5 text-emerald-400" /> Memory Buffer
                      </span>
                      <span className="font-mono font-bold text-emerald-400">
                        {selectedDevice.memory_usage !== null ? `${selectedDevice.memory_usage}%` : 'N/A'}
                      </span>
                    </div>
                    <div className="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                      <div 
                        className="bg-emerald-400 h-full transition-all duration-500 rounded-full" 
                        style={{ width: `${selectedDevice.memory_usage || 0}%` }}
                      />
                    </div>
                  </div>

                  {/* Disk Capacity usage */}
                  <div className="bg-slate-950/50 border border-slate-850 p-4 rounded-xl space-y-2">
                    <div className="flex justify-between text-xs">
                      <span className="flex items-center gap-1.5 text-slate-400">
                        <HardDrive className="w-3.5 h-3.5 text-purple-400" /> Disk Storage
                      </span>
                      <span className="font-mono font-bold text-purple-400">
                        {selectedDevice.disk_usage !== null ? `${selectedDevice.disk_usage}%` : 'N/A'}
                      </span>
                    </div>
                    <div className="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                      <div 
                        className="bg-purple-400 h-full transition-all duration-500 rounded-full" 
                        style={{ width: `${selectedDevice.disk_usage || 0}%` }}
                      />
                    </div>
                  </div>

                  {/* Network stats */}
                  <div className="bg-slate-950/50 border border-slate-850 p-4 rounded-xl space-y-3">
                    <span className="flex items-center gap-1.5 text-xs text-slate-400 border-b border-slate-800/80 pb-2">
                      <Network className="w-3.5 h-3.5 text-blue-400" /> Network Throughput
                    </span>
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <div className="text-[10px] text-slate-500 uppercase">Incoming</div>
                        <div className="font-mono text-sm font-bold text-slate-200">
                          {selectedDevice.network_in !== null ? `${selectedDevice.network_in} Mbps` : '0 Mbps'}
                        </div>
                      </div>
                      <div>
                        <div className="text-[10px] text-slate-500 uppercase">Outgoing</div>
                        <div className="font-mono text-sm font-bold text-slate-200">
                          {selectedDevice.network_out !== null ? `${selectedDevice.network_out} Mbps` : '0 Mbps'}
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Ingestion metadata */}
                  <div className="text-[11px] text-slate-500 space-y-1 pt-2 font-mono">
                    <div>Last Ping RTT: {selectedDevice.last_avg_time || 0} ms</div>
                    <div>TTL Count: {selectedDevice.last_ttl || 'N/A'}</div>
                    <div>Synchronized: {selectedDevice.last_seen || 'Never'}</div>
                  </div>
                </div>
              ) : (
                <div className="text-center py-12 text-slate-500 space-y-2 border border-dashed border-slate-800 rounded-xl">
                  <AlertOctagon className="w-8 h-8 mx-auto text-slate-600" />
                  <p className="text-sm">Host unreachable</p>
                  <p className="text-xs text-slate-600">Check active trapper credentials</p>
                </div>
              )}
            </div>
          ) : (
            <div className="flex-1 flex flex-col items-center justify-center p-6 text-center text-slate-500 space-y-3">
              <Activity className="w-12 h-12 text-slate-700 animate-pulse" />
              <div>
                <p className="font-medium text-sm text-slate-400">Select a device on the map</p>
                <p className="text-xs text-slate-600 mt-1">Click a node to overlay microsecond telemetry charts</p>
              </div>
            </div>
          )}
        </div>
      </main>

      {/* 3. Bottom Timeline Panel */}
      <footer className="px-6 py-4 bg-slate-900 border-t border-slate-800/80 z-10 shadow-2xl">
        <div className="max-w-4xl mx-auto flex items-center gap-6">
          <div className="flex items-center gap-2">
            <button
              onClick={() => setIsPlaying(!isPlaying)}
              disabled={timelineVal === 0 && !isPlaying}
              className="p-2 bg-slate-800 hover:bg-slate-700 text-slate-200 disabled:opacity-40 disabled:hover:bg-slate-800 rounded-lg transition-colors border border-slate-700/80"
              title="Play historical timeline"
            >
              {isPlaying ? <Pause className="w-4 h-4 text-cyan-400" /> : <Play className="w-4 h-4" />}
            </button>
            <div className="text-xs font-semibold text-slate-400 uppercase flex items-center gap-1.5">
              <History className="w-4 h-4" /> Timeline Playback
            </div>
          </div>

          {/* Timeline scrubbing input */}
          <div className="flex-1 flex items-center gap-4">
            <span className="text-xs text-slate-500 font-mono">24h ago</span>
            <input
              type="range"
              min="0"
              max="24"
              step="1"
              value={24 - timelineVal} // invert for chronological logic
              onChange={(e) => handleTimelineChange(24 - parseInt(e.target.value, 10))}
              className="flex-1 h-1 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-cyan-400"
            />
            <span className="text-xs font-bold text-cyan-400 font-mono min-w-[70px]">
              {timelineVal === 0 ? 'LIVE NOW' : `${timelineVal}h ago`}
            </span>
          </div>

          <button
            onClick={() => handleTimelineChange(0)}
            className="text-xs font-bold px-3 py-1.5 bg-slate-800 hover:bg-slate-700 rounded-lg border border-slate-700/80 transition-colors"
          >
            Reset Live
          </button>
        </div>
      </footer>
    </div>
  );
}
