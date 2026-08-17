import 'package:flutter/material.dart';
import '../app_theme.dart';
import '../models/device_model.dart';
import '../models/edge_model.dart';
import '../models/map_model.dart';
import '../widgets/connect_nodes_dialog.dart';

class NativeMapView extends StatefulWidget {
  final List<MapModel> maps;
  final List<DeviceModel> devices;
  final List<EdgeModel> edges;
  final int selectedMapId;
  final ValueChanged<int> onMapChanged;
  final VoidCallback onRefresh;
  final ValueChanged<DeviceModel> onDeviceSelected;
  final Function(DeviceModel) onPingDevice;
  final Function(DeviceModel, double, double)? onUpdatePosition;
  final Function(Map<String, dynamic>)? onCreateEdge;
  final Function(int)? onDeleteEdge;
  final VoidCallback? onOpenScanner;
  final VoidCallback? onAddDevice;
  final bool isLiveActive;
  final ValueChanged<bool>? onToggleLive;
  final bool isTabVisible;

  const NativeMapView({
    super.key,
    required this.maps,
    required this.devices,
    required this.edges,
    required this.selectedMapId,
    required this.onMapChanged,
    required this.onRefresh,
    required this.onDeviceSelected,
    required this.onPingDevice,
    this.onUpdatePosition,
    this.onCreateEdge,
    this.onDeleteEdge,
    this.onOpenScanner,
    this.onAddDevice,
    this.isLiveActive = true,
    this.onToggleLive,
    this.isTabVisible = true,
  });

  @override
  State<NativeMapView> createState() => _NativeMapViewState();
}

class _NativeMapViewState extends State<NativeMapView> with SingleTickerProviderStateMixin {
  final TransformationController _transformController = TransformationController();
  late AnimationController _pulseAnimController;

  String _searchQuery = '';
  final String _selectedTypeFilter = 'all';
  final Map<int, Offset> _draggedPositions = {};
  bool _showLegend = true;

  @override
  void initState() {
    super.initState();
    _pulseAnimController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1500),
    );
    if (widget.isTabVisible) {
      _pulseAnimController.repeat();
    }
  }

  @override
  void didUpdateWidget(covariant NativeMapView oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.isTabVisible && !_pulseAnimController.isAnimating) {
      _pulseAnimController.repeat();
    } else if (!widget.isTabVisible && _pulseAnimController.isAnimating) {
      _pulseAnimController.stop();
    }
  }

  @override
  void dispose() {
    _transformController.dispose();
    _pulseAnimController.dispose();
    super.dispose();
  }

  void _zoom(double factor) {
    final matrix = _transformController.value.clone();
    matrix.scale(factor, factor);
    _transformController.value = matrix;
  }

  void _resetZoom() {
    _transformController.value = Matrix4.identity();
  }

  void _applyAutoGridLayout() {
    const cols = 5;
    const startX = 160.0;
    const startY = 160.0;
    const spacingX = 280.0;
    const spacingY = 220.0;

    for (int i = 0; i < widget.devices.length; i++) {
      final d = widget.devices[i];
      final col = i % cols;
      final row = i ~/ cols;
      final newX = startX + col * spacingX;
      final newY = startY + row * spacingY;

      setState(() {
        _draggedPositions[d.id] = Offset(newX, newY);
      });

      if (widget.onUpdatePosition != null) {
        widget.onUpdatePosition!(d, newX, newY);
      }
    }

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Auto-grid layout synced with Docker server!'),
        backgroundColor: AppTheme.success,
        duration: Duration(seconds: 2),
      ),
    );
  }

  void _openConnectDialog() {
    showDialog(
      context: context,
      builder: (_) => ConnectNodesDialog(
        devices: widget.devices,
        onSave: (edgeData) async {
          if (widget.onCreateEdge != null) {
            await widget.onCreateEdge!({
              ...edgeData,
              'map_id': widget.selectedMapId,
            });
          }
        },
      ),
    );
  }

  void _showLinksManager() {
    showDialog(
      context: context,
      builder: (context) => Dialog(
        backgroundColor: const Color(0xFF0F172A),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: Color(0xFF334155)),
        ),
        child: Container(
          width: 560,
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Row(
                    children: [
                      Icon(Icons.hub, color: Color(0xFF22D3EE), size: 22),
                      SizedBox(width: 10),
                      Text(
                        'Map Connection Topology Links',
                        style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: Colors.white),
                      ),
                    ],
                  ),
                  IconButton(
                    icon: const Icon(Icons.close, color: Color(0xFF94A3B8), size: 18),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              if (widget.edges.isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 24),
                  child: Center(
                    child: Text(
                      'No connection links configured for this map.',
                      style: TextStyle(color: Color(0xFF64748B)),
                    ),
                  ),
                )
              else
                ConstrainedBox(
                  constraints: const BoxConstraints(maxHeight: 340),
                  child: ListView.separated(
                    shrinkWrap: true,
                    itemCount: widget.edges.length,
                    separatorBuilder: (_, __) => const Divider(color: Color(0xFF1E293B), height: 1),
                    itemBuilder: (context, idx) {
                      final edge = widget.edges[idx];
                      final src = widget.devices.firstWhere(
                        (d) => d.id == edge.sourceId,
                        orElse: () => DeviceModel(id: 0, name: 'Node #${edge.sourceId}', ip: ''),
                      );
                      final dst = widget.devices.firstWhere(
                        (d) => d.id == edge.targetId,
                        orElse: () => DeviceModel(id: 0, name: 'Node #${edge.targetId}', ip: ''),
                      );

                      return ListTile(
                        dense: true,
                        leading: Container(
                          width: 12,
                          height: 12,
                          decoration: BoxDecoration(color: edge.displayColor, shape: BoxShape.circle),
                        ),
                        title: Text(
                          '${src.name} ⟷ ${dst.name}',
                          style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold),
                        ),
                        subtitle: Text(
                          '${edge.displayLabel} ${edge.label != null && edge.label!.isNotEmpty ? '• ${edge.label}' : ''}',
                          style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11),
                        ),
                        trailing: IconButton(
                          icon: const Icon(Icons.delete_outline, size: 18, color: Color(0xFFEF4444)),
                          tooltip: 'Delete Link',
                          onPressed: () {
                            if (widget.onDeleteEdge != null) {
                              widget.onDeleteEdge!(edge.id);
                              Navigator.of(context).pop();
                            }
                          },
                        ),
                      );
                    },
                  ),
                ),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  ElevatedButton.icon(
                    onPressed: () {
                      Navigator.of(context).pop();
                      _openConnectDialog();
                    },
                    icon: const Icon(Icons.add, size: 14),
                    label: const Text('Add Connection', style: TextStyle(fontSize: 12)),
                    style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF0891B2)),
                  ),
                  TextButton(
                    onPressed: () => Navigator.of(context).pop(),
                    child: const Text('Close'),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Offset _getDeviceOffset(DeviceModel device) {
    if (_draggedPositions.containsKey(device.id)) {
      return _draggedPositions[device.id]!;
    }
    double left = (device.x != 0 ? device.x : 150 + (device.id * 240) % 2500).toDouble();
    double top = (device.y != 0 ? device.y : 150 + (device.id * 180) % 1600).toDouble();
    if (left < 50) left = 120 + (device.id * 140.0) % 1900;
    if (top < 50) top = 120 + (device.id * 110.0) % 1300;
    return Offset(left, top);
  }

  @override
  Widget build(BuildContext context) {
    final filteredDevices = widget.devices.where((d) {
      if (_selectedTypeFilter != 'all' && d.type.toLowerCase() != _selectedTypeFilter) {
        return false;
      }
      if (_searchQuery.isNotEmpty) {
        final q = _searchQuery.toLowerCase();
        return d.name.toLowerCase().contains(q) || d.ip.contains(q) || d.subchoice.toLowerCase().contains(q);
      }
      return true;
    }).toList();

    return Stack(
      children: [
        // 1. Interactive Topology Canvas (Dark Slate theme matching Docker web)
        Container(
          color: const Color(0xFF0B1120), // Docker web map dark slate background
          child: InteractiveViewer(
            transformationController: _transformController,
            boundaryMargin: const EdgeInsets.all(3000),
            minScale: 0.1,
            maxScale: 4.0,
            child: SizedBox(
              width: 3600,
              height: 2600,
              child: AnimatedBuilder(
                animation: _pulseAnimController,
                builder: (context, _) {
                  return CustomPaint(
                    painter: _MapCanvasPainter(
                      devices: widget.devices,
                      edges: widget.edges,
                      pulseValue: _pulseAnimController.value,
                      positionGetter: _getDeviceOffset,
                    ),
                    child: Stack(
                      children: filteredDevices.map((device) {
                        return _buildDockerStyleDeviceNode(device);
                      }).toList(),
                    ),
                  );
                },
              ),
            ),
          ),
        ),

        // 2. Grid Lines Overlay
        Positioned.fill(
          child: IgnorePointer(
            child: CustomPaint(
              painter: _GridBackgroundPainter(),
            ),
          ),
        ),

        // 3. Top Floating Docker-Style Map Controls Bar
        Positioned(
          top: 14,
          left: 14,
          right: 14,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            decoration: BoxDecoration(
              color: const Color(0xFF1E293B).withOpacity(0.95),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFF334155)),
              boxShadow: const [BoxShadow(color: Colors.black54, blurRadius: 12)],
            ),
            child: Row(
              children: [
                // Map Selector Dropdown
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
                  decoration: BoxDecoration(
                    color: const Color(0xFF0F172A),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: const Color(0xFF475569)),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.map, color: Color(0xFF22D3EE), size: 16),
                      const SizedBox(width: 8),
                      DropdownButton<int>(
                        value: widget.maps.any((m) => m.id == widget.selectedMapId)
                            ? widget.selectedMapId
                            : (widget.maps.isNotEmpty ? widget.maps.first.id : null),
                        dropdownColor: const Color(0xFF0F172A),
                        underline: const SizedBox(),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                        ),
                        items: widget.maps.map((m) {
                          return DropdownMenuItem<int>(
                            value: m.id,
                            child: Text(m.name),
                          );
                        }).toList(),
                        onChanged: (val) {
                          if (val != null) widget.onMapChanged(val);
                        },
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 10),

                // Live Search Input
                Container(
                  width: 200,
                  height: 36,
                  decoration: BoxDecoration(
                    color: const Color(0xFF0F172A),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: const Color(0xFF475569)),
                  ),
                  child: TextField(
                    style: const TextStyle(color: Colors.white, fontSize: 12),
                    decoration: const InputDecoration(
                      hintText: 'Search node or IP...',
                      hintStyle: TextStyle(color: Color(0xFF64748B), fontSize: 11),
                      prefixIcon: Icon(Icons.search, size: 16, color: Color(0xFF94A3B8)),
                      contentPadding: EdgeInsets.symmetric(vertical: 8),
                      border: InputBorder.none,
                      enabledBorder: InputBorder.none,
                      focusedBorder: InputBorder.none,
                    ),
                    onChanged: (val) => setState(() => _searchQuery = val),
                  ),
                ),

                const Spacer(),

                // Scan Network Action
                IconButton(
                  icon: const Icon(Icons.search, size: 18, color: Color(0xFFCBD5E1)),
                  tooltip: 'Scan Network Subnet',
                  onPressed: widget.onOpenScanner,
                ),

                // Add Device Action
                IconButton(
                  icon: const Icon(Icons.add, size: 18, color: Color(0xFF22D3EE)),
                  tooltip: 'Add New Device',
                  onPressed: widget.onAddDevice,
                ),

                // Connect Connection Action
                ElevatedButton.icon(
                  onPressed: _openConnectDialog,
                  icon: const Icon(Icons.cable, size: 14),
                  label: const Text('Add Connection', style: TextStyle(fontSize: 11)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF0891B2),
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    minimumSize: const Size(0, 32),
                  ),
                ),
                const SizedBox(width: 8),

                // Links Manager
                OutlinedButton.icon(
                  onPressed: _showLinksManager,
                  icon: const Icon(Icons.hub, size: 14),
                  label: Text('Links (${widget.edges.length})', style: const TextStyle(fontSize: 11)),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Colors.white,
                    side: const BorderSide(color: Color(0xFF475569)),
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    minimumSize: const Size(0, 32),
                  ),
                ),
                const SizedBox(width: 8),

                // Auto Layout
                OutlinedButton.icon(
                  onPressed: _applyAutoGridLayout,
                  icon: const Icon(Icons.grid_goldenratio, size: 14),
                  label: const Text('Auto Layout', style: TextStyle(fontSize: 11)),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: const Color(0xFF22D3EE),
                    side: const BorderSide(color: Color(0xFF0E7490)),
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    minimumSize: const Size(0, 32),
                  ),
                ),
                const SizedBox(width: 10),

                // Live Status Badge / Toggle
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFF0F172A),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: const Color(0xFF334155)),
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 8,
                        height: 8,
                        decoration: BoxDecoration(
                          color: widget.isLiveActive ? const Color(0xFF2ECC71) : const Color(0xFF95A5A6),
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        widget.isLiveActive ? 'LIVE SYNC' : 'OFFLINE',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          color: widget.isLiveActive ? const Color(0xFF2ECC71) : const Color(0xFF95A5A6),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 10),

                // Zoom & Refresh Controls
                Container(
                  decoration: BoxDecoration(
                    color: const Color(0xFF0F172A),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: const Color(0xFF334155)),
                  ),
                  child: Row(
                    children: [
                      IconButton(
                        icon: const Icon(Icons.zoom_in, size: 16, color: Color(0xFFCBD5E1)),
                        tooltip: 'Zoom In',
                        padding: const EdgeInsets.all(6),
                        constraints: const BoxConstraints(),
                        onPressed: () => _zoom(1.2),
                      ),
                      IconButton(
                        icon: const Icon(Icons.zoom_out, size: 16, color: Color(0xFFCBD5E1)),
                        tooltip: 'Zoom Out',
                        padding: const EdgeInsets.all(6),
                        constraints: const BoxConstraints(),
                        onPressed: () => _zoom(0.8),
                      ),
                      IconButton(
                        icon: const Icon(Icons.center_focus_strong, size: 16, color: Color(0xFFCBD5E1)),
                        tooltip: 'Reset View',
                        padding: const EdgeInsets.all(6),
                        constraints: const BoxConstraints(),
                        onPressed: _resetZoom,
                      ),
                      IconButton(
                        icon: const Icon(Icons.refresh, size: 16, color: Color(0xFF22D3EE)),
                        tooltip: 'Refresh Server Status',
                        padding: const EdgeInsets.all(6),
                        constraints: const BoxConstraints(),
                        onPressed: widget.onRefresh,
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),

        // 4. Bottom Left Connection Types Legend (Exact match to Docker web legend)
        if (_showLegend)
          Positioned(
            bottom: 14,
            left: 14,
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFF1E293B).withOpacity(0.92),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFF334155)),
                boxShadow: const [BoxShadow(color: Colors.black54, blurRadius: 8)],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Connection Types', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.white)),
                      const SizedBox(width: 20),
                      InkWell(
                        onTap: () => setState(() => _showLegend = false),
                        child: const Text('Hide', style: TextStyle(fontSize: 10, color: Color(0xFF22D3EE))),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  _buildLegendItem('🔌 CAT6', const Color(0xFFA78BFA)),
                  _buildLegendItem('💡 Fiber Optic', const Color(0xFFF97316)),
                  _buildLegendItem('📡 WiFi', const Color(0xFF38BDF8)),
                  _buildLegendItem('📻 Radio', const Color(0xFF84CC16)),
                  _buildLegendItem('🌐 LAN', const Color(0xFF60A5FA)),
                  _buildLegendItem('🔒 Tunnel', const Color(0xFFC084FC)),
                ],
              ),
            ),
          )
        else
          Positioned(
            bottom: 14,
            left: 14,
            child: InkWell(
              onTap: () => setState(() => _showLegend = true),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: const Color(0xFF1E293B).withOpacity(0.9),
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: const Color(0xFF334155)),
                ),
                child: const Row(
                  children: [
                    Icon(Icons.hub, size: 12, color: Color(0xFF22D3EE)),
                    SizedBox(width: 6),
                    Text('Show Legend', style: TextStyle(fontSize: 11, color: Colors.white)),
                  ],
                ),
              ),
            ),
          ),

        // 5. Bottom Right Radar
        Positioned(
          bottom: 14,
          right: 14,
          child: Container(
            width: 140,
            height: 95,
            decoration: BoxDecoration(
              color: const Color(0xFF0F172A).withOpacity(0.85),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: const Color(0xFF334155)),
              boxShadow: const [BoxShadow(color: Colors.black54, blurRadius: 6)],
            ),
            child: CustomPaint(
              painter: _MiniMapPainter(devices: widget.devices, positionGetter: _getDeviceOffset),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildLegendItem(String label, Color color) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 18,
            height: 3,
            decoration: BoxDecoration(
              color: color,
              borderRadius: BorderRadius.circular(2),
              boxShadow: [BoxShadow(color: color.withOpacity(0.6), blurRadius: 4)],
            ),
          ),
          const SizedBox(width: 8),
          Text(label, style: const TextStyle(fontSize: 10, color: Color(0xFFCBD5E1))),
        ],
      ),
    );
  }

  /// Node rendered matching Docker Web `map.php` style
  Widget _buildDockerStyleDeviceNode(DeviceModel device) {
    final pos = _getDeviceOffset(device);

    return Positioned(
      left: pos.dx,
      top: pos.dy,
      child: GestureDetector(
        onTap: () => widget.onDeviceSelected(device),
        onPanUpdate: (details) {
          setState(() {
            final cur = _getDeviceOffset(device);
            _draggedPositions[device.id] = Offset(cur.dx + details.delta.dx, cur.dy + details.delta.dy);
          });
        },
        onPanEnd: (_) {
          final cur = _getDeviceOffset(device);
          if (widget.onUpdatePosition != null) {
            widget.onUpdatePosition!(device, cur.dx, cur.dy);
          }
        },
        child: MouseRegion(
          cursor: SystemMouseCursors.grab,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Glowing Circular Icon Node
              Container(
                width: 58,
                height: 58,
                decoration: BoxDecoration(
                  color: const Color(0xFF0F172A),
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: device.statusColor,
                    width: device.isOnline ? 2.5 : 2.0,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: device.statusColor.withOpacity(device.isOnline ? 0.45 : 0.25),
                      blurRadius: device.isOnline ? 16 : 8,
                      spreadRadius: device.isOnline ? 2 : 0,
                    ),
                  ],
                ),
                child: Center(
                  child: Icon(
                    device.typeIcon,
                    size: 28,
                    color: device.statusColor,
                  ),
                ),
              ),
              const SizedBox(height: 6),

              // Docker Web Style Info Card Label
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFF0F172A).withOpacity(0.95),
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: const Color(0xFF334155)),
                  boxShadow: const [BoxShadow(color: Colors.black54, blurRadius: 4)],
                ),
                child: Column(
                  children: [
                    Text(
                      device.name,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    Text(
                      device.ip + (device.checkPort > 0 ? ':${device.checkPort}' : ''),
                      style: const TextStyle(
                        color: Color(0xFF22D3EE),
                        fontSize: 9,
                        fontFamily: 'monospace',
                      ),
                    ),
                    if (device.isOnline && device.lastAvgTime != null && device.lastAvgTime! > 0)
                      Text(
                        '${device.lastAvgTime!.toStringAsFixed(1)}ms | TTL:${device.lastTtl ?? 64}',
                        style: const TextStyle(
                          color: Color(0xFF2ECC71),
                          fontSize: 9,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MapCanvasPainter extends CustomPainter {
  final List<DeviceModel> devices;
  final List<EdgeModel> edges;
  final double pulseValue;
  final Offset Function(DeviceModel) positionGetter;

  _MapCanvasPainter({
    required this.devices,
    required this.edges,
    required this.pulseValue,
    required this.positionGetter,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final deviceMap = {for (var d in devices) d.id: d};

    for (final edge in edges) {
      final src = deviceMap[edge.sourceId];
      final dst = deviceMap[edge.targetId];
      if (src == null || dst == null) continue;

      final srcPos = positionGetter(src);
      final dstPos = positionGetter(dst);

      final srcX = srcPos.dx + 29;
      final srcY = srcPos.dy + 29;
      final dstX = dstPos.dx + 29;
      final dstY = dstPos.dy + 29;

      final isDown = src.isOffline || dst.isOffline;
      final edgeColor = isDown ? const Color(0xFFE74C3C) : edge.displayColor;

      final paint = Paint()
        ..color = edgeColor.withOpacity(isDown ? 0.7 : 0.8)
        ..strokeWidth = edge.thickness.clamp(1.5, 6.0)
        ..style = PaintingStyle.stroke;

      canvas.drawLine(Offset(srcX, srcY), Offset(dstX, dstY), paint);

      if (!isDown) {
        final particleX = srcX + (dstX - srcX) * pulseValue;
        final particleY = srcY + (dstY - srcY) * pulseValue;
        final particlePaint = Paint()
          ..color = const Color(0xFF22D3EE)
          ..style = PaintingStyle.fill;
        canvas.drawCircle(Offset(particleX, particleY), 4.0, particlePaint);
      }
    }
  }

  @override
  bool shouldRepaint(covariant _MapCanvasPainter oldDelegate) => true;
}

class _GridBackgroundPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFF1E293B).withOpacity(0.2)
      ..strokeWidth = 1;

    const spacing = 40.0;
    for (double x = 0; x < size.width; x += spacing) {
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), paint);
    }
    for (double y = 0; y < size.height; y += spacing) {
      canvas.drawLine(Offset(0, y), Offset(size.width, y), paint);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _MiniMapPainter extends CustomPainter {
  final List<DeviceModel> devices;
  final Offset Function(DeviceModel) positionGetter;

  _MiniMapPainter({required this.devices, required this.positionGetter});

  @override
  void paint(Canvas canvas, Size size) {
    final scaleX = size.width / 3600;
    final scaleY = size.height / 2600;

    for (final d in devices) {
      final pos = positionGetter(d);
      final x = pos.dx * scaleX;
      final y = pos.dy * scaleY;

      final paint = Paint()
        ..color = d.statusColor
        ..style = PaintingStyle.fill;
      canvas.drawCircle(Offset(x, y), 2.5, paint);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => true;
}
