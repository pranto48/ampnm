import 'package:flutter/material.dart';
import 'package:window_manager/window_manager.dart';

import '../app_theme.dart';
import '../models/device_model.dart';
import '../models/edge_model.dart';
import '../models/map_model.dart';
import '../widgets/add_edit_text_dialog.dart';
import '../widgets/connect_nodes_dialog.dart';
import '../widgets/edit_edge_dialog.dart';

class NativeMapView extends StatefulWidget {
  final List<MapModel> maps;
  final List<DeviceModel> devices;
  final List<EdgeModel> edges;
  final int selectedMapId;
  final ValueChanged<int> onMapChanged;
  final VoidCallback onRefresh;
  final ValueChanged<DeviceModel> onDeviceSelected;
  final Function(DeviceModel) onPingDevice;
  final Function(DeviceModel)? onContinuousPing;
  final Function(DeviceModel)? onEditDevice;
  final Function(DeviceModel)? onDeleteDevice;
  final Function(DeviceModel, double, double)? onUpdatePosition;
  final Function(Map<String, dynamic>)? onCreateEdge;
  final Function(Map<String, dynamic>)? onUpdateEdge;
  final Function(int)? onDeleteEdge;
  final VoidCallback? onOpenScanner;
  final VoidCallback? onAddDevice;
  final Function(Map<String, dynamic>)? onAddTextNode;
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
    this.onContinuousPing,
    this.onEditDevice,
    this.onDeleteDevice,
    this.onUpdatePosition,
    this.onCreateEdge,
    this.onUpdateEdge,
    this.onDeleteEdge,
    this.onOpenScanner,
    this.onAddDevice,
    this.onAddTextNode,
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
  bool _showConnectionLegend = true;
  bool _showStatusLegend = true;
  bool _isFullScreen = false;

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

  void _toggleFullScreen() async {
    final isFull = await windowManager.isFullScreen();
    await windowManager.setFullScreen(!isFull);
    setState(() {
      _isFullScreen = !isFull;
    });
  }

  void _autoLayout() {
    final total = widget.devices.length;
    if (total == 0) return;

    final cols = (total > 9) ? 4 : 3;
    const spacingX = 320.0;
    const spacingY = 220.0;
    const startX = 180.0;
    const startY = 160.0;

    setState(() {
      for (int i = 0; i < total; i++) {
        final d = widget.devices[i];
        final col = i % cols;
        final row = i ~/ cols;
        final x = startX + col * spacingX;
        final y = startY + row * spacingY;
        _draggedPositions[d.id] = Offset(x, y);
        if (widget.onUpdatePosition != null) {
          widget.onUpdatePosition!(d, x, y);
        }
      }
    });

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Auto-arranged device nodes on map!'),
        backgroundColor: AppTheme.primary,
        duration: Duration(seconds: 2),
      ),
    );
  }

  void _openConnectDialog([DeviceModel? initialSource]) {
    showDialog(
      context: context,
      builder: (context) => ConnectNodesDialog(
        devices: widget.devices.where((d) => !d.isTextNode).toList(),
        initialSourceId: initialSource?.id,
        onSave: (edgeData) {
          if (widget.onCreateEdge != null) {
            widget.onCreateEdge!({
              ...edgeData,
              'map_id': widget.selectedMapId,
            });
          }
        },
      ),
    );
  }

  void _openAddTextModal([DeviceModel? initialTextNode]) {
    showDialog(
      context: context,
      builder: (context) => AddEditTextDialog(
        initialTextNode: initialTextNode,
        defaultMapId: widget.selectedMapId,
        onSave: (data) {
          if (initialTextNode != null) {
            if (widget.onEditDevice != null) {
              widget.onEditDevice!(DeviceModel(
                id: initialTextNode.id,
                name: data['name'],
                ip: '',
                type: 'text',
                nameTextSize: data['name_text_size'] ?? 16.0,
                nameTextColor: data['name_text_color'] ?? '#22D3EE',
                nameTextBold: data['name_text_bold'] == 1,
                nameTextItalic: data['name_text_italic'] == 1,
                mapId: widget.selectedMapId,
              ));
            }
          } else {
            if (widget.onAddTextNode != null) {
              widget.onAddTextNode!(data);
            }
          }
        },
      ),
    );
  }

  void _openEditEdgeModal(EdgeModel edge) {
    showDialog(
      context: context,
      builder: (context) => EditEdgeDialog(
        edge: edge,
        devices: widget.devices,
        onSave: (edgeData) {
          if (widget.onUpdateEdge != null) {
            widget.onUpdateEdge!(edgeData);
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
          width: 580,
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
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            IconButton(
                              icon: const Icon(Icons.edit, size: 16, color: Color(0xFF22D3EE)),
                              tooltip: 'Edit Link',
                              onPressed: () {
                                Navigator.of(context).pop();
                                _openEditEdgeModal(edge);
                              },
                            ),
                            IconButton(
                              icon: const Icon(Icons.delete_outline, size: 18, color: Color(0xFFEF4444)),
                              tooltip: 'Delete Link',
                              onPressed: () {
                                if (widget.onDeleteEdge != null) {
                                  widget.onDeleteEdge!(edge.id);
                                  Navigator.of(context).pop();
                                }
                              },
                            ),
                          ],
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

  void _showNodeContextMenu(BuildContext context, DeviceModel device, Offset tapPos) {
    final RenderBox overlay = Overlay.of(context).context.findRenderObject() as RenderBox;

    showMenu<String>(
      context: context,
      position: RelativeRect.fromRect(
        tapPos & const Size(40, 40),
        Offset.zero & overlay.size,
      ),
      color: const Color(0xFF0F172A),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: const BorderSide(color: Color(0xFF334155)),
      ),
      items: [
        if (device.isTextNode) ...[
          const PopupMenuItem<String>(
            value: 'edit_text',
            child: Row(
              children: [
                Icon(Icons.edit, color: Color(0xFF22D3EE), size: 16),
                SizedBox(width: 10),
                Text('Edit Text Label', style: TextStyle(color: Colors.white, fontSize: 13)),
              ],
            ),
          ),
          const PopupMenuItem<String>(
            value: 'delete',
            child: Row(
              children: [
                Icon(Icons.delete_outline, color: Color(0xFFEF4444), size: 16),
                SizedBox(width: 10),
                Text('Delete Label', style: TextStyle(color: Color(0xFFEF4444), fontSize: 13)),
              ],
            ),
          ),
        ] else ...[
          const PopupMenuItem<String>(
            value: 'edit',
            child: Row(
              children: [
                Icon(Icons.edit, color: Color(0xFF22D3EE), size: 16),
                SizedBox(width: 10),
                Text('Edit Device', style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold)),
              ],
            ),
          ),
          const PopupMenuItem<String>(
            value: 'ping',
            child: Row(
              children: [
                Icon(Icons.flash_on, color: Color(0xFF10B981), size: 16),
                SizedBox(width: 10),
                Text('Ping Now', style: TextStyle(color: Colors.white, fontSize: 13)),
              ],
            ),
          ),
          const PopupMenuItem<String>(
            value: 'continuous_ping',
            child: Row(
              children: [
                Icon(Icons.timeline, color: Color(0xFF38BDF8), size: 16),
                SizedBox(width: 10),
                Text('Continuous Ping', style: TextStyle(color: Colors.white, fontSize: 13)),
              ],
            ),
          ),
          const PopupMenuItem<String>(
            value: 'details',
            child: Row(
              children: [
                Icon(Icons.info_outline, color: Color(0xFFF59E0B), size: 16),
                SizedBox(width: 10),
                Text('Device Details & Metrics', style: TextStyle(color: Colors.white, fontSize: 13)),
              ],
            ),
          ),
          const PopupMenuItem<String>(
            value: 'connect',
            child: Row(
              children: [
                Icon(Icons.cable, color: Color(0xFFA855F7), size: 16),
                SizedBox(width: 10),
                Text('Connect To...', style: TextStyle(color: Colors.white, fontSize: 13)),
              ],
            ),
          ),
          const PopupMenuDivider(height: 1),
          const PopupMenuItem<String>(
            value: 'delete',
            child: Row(
              children: [
                Icon(Icons.delete_outline, color: Color(0xFFEF4444), size: 16),
                SizedBox(width: 10),
                Text('Delete Device', style: TextStyle(color: Color(0xFFEF4444), fontSize: 13)),
              ],
            ),
          ),
        ],
      ],
    ).then((action) {
      if (action == null) return;
      if (action == 'edit') {
        if (widget.onEditDevice != null) widget.onEditDevice!(device);
      } else if (action == 'edit_text') {
        _openAddTextModal(device);
      } else if (action == 'ping') {
        widget.onPingDevice(device);
      } else if (action == 'continuous_ping') {
        if (widget.onContinuousPing != null) widget.onContinuousPing!(device);
      } else if (action == 'details') {
        widget.onDeviceSelected(device);
      } else if (action == 'connect') {
        _openConnectDialog(device);
      } else if (action == 'delete') {
        if (widget.onDeleteDevice != null) widget.onDeleteDevice!(device);
      }
    });
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

                // Add Text Label Action
                OutlinedButton.icon(
                  onPressed: () => _openAddTextModal(),
                  icon: const Icon(Icons.text_fields, size: 14),
                  label: const Text('Add Text', style: TextStyle(fontSize: 11)),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: const Color(0xFF22D3EE),
                    side: const BorderSide(color: Color(0xFF0891B2)),
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    minimumSize: const Size(0, 32),
                  ),
                ),
                const SizedBox(width: 8),

                // Connect Connection Action
                ElevatedButton.icon(
                  onPressed: () => _openConnectDialog(),
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
                IconButton(
                  icon: const Icon(Icons.auto_awesome_mosaic, size: 18, color: Color(0xFFCBD5E1)),
                  tooltip: 'Auto Arrange Nodes',
                  onPressed: _autoLayout,
                ),

                // Refresh Status
                IconButton(
                  icon: const Icon(Icons.refresh, size: 18, color: Color(0xFFCBD5E1)),
                  tooltip: 'Refresh Status',
                  onPressed: widget.onRefresh,
                ),

                // Toggle Fullscreen
                IconButton(
                  icon: Icon(_isFullScreen ? Icons.fullscreen_exit : Icons.fullscreen, size: 20, color: const Color(0xFF22D3EE)),
                  tooltip: 'Toggle Fullscreen',
                  onPressed: _toggleFullScreen,
                ),
              ],
            ),
          ),
        ),

        // 4. Bottom-Left Docker Web Style Status Legend
        if (_showStatusLegend)
          Positioned(
            bottom: 16,
            left: 16,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              decoration: BoxDecoration(
                color: const Color(0xFF0F172A).withOpacity(0.92),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFF334155)),
                boxShadow: const [BoxShadow(color: Colors.black54, blurRadius: 10)],
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  _buildStatusLegendDot(const Color(0xFF2ECC71), 'Online'),
                  const SizedBox(width: 14),
                  _buildStatusLegendDot(const Color(0xFFF1C40F), 'Warning'),
                  const SizedBox(width: 14),
                  _buildStatusLegendDot(const Color(0xFFE74C3C), 'Critical'),
                  const SizedBox(width: 14),
                  _buildStatusLegendDot(const Color(0xFF95A5A6), 'Offline'),
                ],
              ),
            ),
          ),

        // 5. Bottom-Right Docker Web Style Connection Types Legend
        Positioned(
          bottom: 16,
          right: 16,
          child: _showConnectionLegend
              ? Container(
                  width: 200,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFF0F172A).withOpacity(0.92),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: const Color(0xFF334155)),
                    boxShadow: const [BoxShadow(color: Colors.black54, blurRadius: 10)],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Row(
                            children: [
                              Icon(Icons.cable, color: Color(0xFF22D3EE), size: 14),
                              SizedBox(width: 6),
                              Text(
                                'Connection Types',
                                style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.white),
                              ),
                            ],
                          ),
                          InkWell(
                            onTap: () => setState(() => _showConnectionLegend = false),
                            child: const Icon(Icons.close, size: 14, color: Color(0xFF94A3B8)),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      _buildConnectionLegendItem(const Color(0xFFA78BFA), '🔌 CAT6 Cable'),
                      _buildConnectionLegendItem(const Color(0xFFF97316), '💡 Fiber Optic'),
                      _buildConnectionLegendItem(const Color(0xFF38BDF8), '📡 WiFi'),
                      _buildConnectionLegendItem(const Color(0xFF84CC16), '📻 Radio'),
                      _buildConnectionLegendItem(const Color(0xFF60A5FA), '🌐 LAN'),
                      _buildConnectionLegendItem(const Color(0xFFC084FC), '🔒 Tunnel'),
                    ],
                  ),
                )
              : OutlinedButton.icon(
                  onPressed: () => setState(() => _showConnectionLegend = true),
                  icon: const Icon(Icons.cable, size: 14),
                  label: const Text('Connection Types', style: TextStyle(fontSize: 11)),
                  style: OutlinedButton.styleFrom(
                    backgroundColor: const Color(0xFF0F172A).withOpacity(0.92),
                    foregroundColor: const Color(0xFF22D3EE),
                    side: const BorderSide(color: Color(0xFF334155)),
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  ),
                ),
        ),

        // 6. Floating Zoom & Viewport Controls (Bottom Center)
        Positioned(
          bottom: 16,
          left: 0,
          right: 0,
          child: Center(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: const Color(0xFF1E293B).withOpacity(0.9),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: const Color(0xFF334155)),
                boxShadow: const [BoxShadow(color: Colors.black45, blurRadius: 8)],
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  IconButton(
                    icon: const Icon(Icons.remove, size: 18, color: Colors.white),
                    onPressed: () => _zoom(0.85),
                    tooltip: 'Zoom Out',
                  ),
                  IconButton(
                    icon: const Icon(Icons.center_focus_strong, size: 18, color: Color(0xFF22D3EE)),
                    onPressed: _resetZoom,
                    tooltip: 'Reset Zoom (100%)',
                  ),
                  IconButton(
                    icon: const Icon(Icons.add, size: 18, color: Colors.white),
                    onPressed: () => _zoom(1.15),
                    tooltip: 'Zoom In',
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildStatusLegendDot(Color color, String label) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 8,
          height: 8,
          decoration: BoxDecoration(
            color: color,
            shape: BoxShape.circle,
            boxShadow: [BoxShadow(color: color, blurRadius: 4)],
          ),
        ),
        const SizedBox(width: 6),
        Text(
          label,
          style: const TextStyle(fontSize: 10, color: Color(0xFFCBD5E1), fontWeight: FontWeight.w500),
        ),
      ],
    );
  }

  Widget _buildConnectionLegendItem(Color color, String label) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2.5),
      child: Row(
        children: [
          Container(
            width: 24,
            height: 3,
            decoration: BoxDecoration(
              color: color,
              borderRadius: BorderRadius.circular(2),
              boxShadow: [BoxShadow(color: color.withOpacity(0.8), blurRadius: 4)],
            ),
          ),
          const SizedBox(width: 8),
          Text(
            label,
            style: const TextStyle(fontSize: 10, color: Color(0xFFCBD5E1)),
          ),
        ],
      ),
    );
  }

  Widget _buildDockerStyleDeviceNode(DeviceModel device) {
    final pos = _getDeviceOffset(device);

    // Render Text Annotation Label Node
    if (device.isTextNode) {
      Color textColor = const Color(0xFF22D3EE);
      if (device.nameTextColor != null && device.nameTextColor!.isNotEmpty) {
        String hex = device.nameTextColor!.replaceAll('#', '');
        if (hex.length == 6) hex = 'FF$hex';
        textColor = Color(int.tryParse(hex, radix: 16) ?? 0xFF22D3EE);
      }

      return Positioned(
        left: pos.dx,
        top: pos.dy,
        child: GestureDetector(
          onPanUpdate: (details) {
            setState(() {
              _draggedPositions[device.id] = Offset(pos.dx + details.delta.dx, pos.dy + details.delta.dy);
            });
          },
          onPanEnd: (_) {
            final finalPos = _draggedPositions[device.id] ?? pos;
            if (widget.onUpdatePosition != null) {
              widget.onUpdatePosition!(device, finalPos.dx, finalPos.dy);
            }
          },
          onSecondaryTapUp: (details) {
            _showNodeContextMenu(context, device, details.globalPosition);
          },
          onDoubleTap: () => _openAddTextModal(device),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            decoration: BoxDecoration(
              color: const Color(0xFF0F172A).withOpacity(0.85),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: textColor.withOpacity(0.5)),
              boxShadow: [
                BoxShadow(color: textColor.withOpacity(0.2), blurRadius: 8),
              ],
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  device.name,
                  style: TextStyle(
                    color: textColor,
                    fontSize: device.nameTextSize.clamp(12.0, 32.0),
                    fontWeight: device.nameTextBold ? FontWeight.bold : FontWeight.normal,
                    fontStyle: device.nameTextItalic ? FontStyle.italic : FontStyle.normal,
                  ),
                ),
                const SizedBox(width: 8),
                InkWell(
                  onTap: () => _openAddTextModal(device),
                  child: Icon(Icons.edit, size: 12, color: textColor.withOpacity(0.7)),
                ),
              ],
            ),
          ),
        ),
      );
    }

    // Standard Hardware Device Node
    return Positioned(
      left: pos.dx,
      top: pos.dy,
      child: GestureDetector(
        onPanUpdate: (details) {
          setState(() {
            _draggedPositions[device.id] = Offset(pos.dx + details.delta.dx, pos.dy + details.delta.dy);
          });
        },
        onPanEnd: (_) {
          final finalPos = _draggedPositions[device.id] ?? pos;
          if (widget.onUpdatePosition != null) {
            widget.onUpdatePosition!(device, finalPos.dx, finalPos.dy);
          }
        },
        onTap: () => widget.onDeviceSelected(device),
        onDoubleTap: () {
          if (widget.onEditDevice != null) widget.onEditDevice!(device);
        },
        onSecondaryTapUp: (details) {
          _showNodeContextMenu(context, device, details.globalPosition);
        },
        child: MouseRegion(
          cursor: SystemMouseCursors.grab,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Circular Glowing Node
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

              // Docker Web Style Info Card Label with Edit Handle
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
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          device.name,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const SizedBox(width: 4),
                        InkWell(
                          onTap: () {
                            if (widget.onEditDevice != null) widget.onEditDevice!(device);
                          },
                          child: const Icon(Icons.edit, size: 10, color: Color(0xFF94A3B8)),
                        ),
                      ],
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
      final baseThickness = edge.thickness.clamp(2.0, 5.0);

      final p1 = Offset(srcX, srcY);
      final p2 = Offset(dstX, dstY);

      // Layer 1: Wide Radiant Outer Neon Aura
      final outerGlowPaint = Paint()
        ..color = edgeColor.withOpacity(isDown ? 0.35 : 0.45)
        ..strokeWidth = (baseThickness * 4.8).clamp(9.0, 24.0)
        ..strokeCap = StrokeCap.round
        ..style = PaintingStyle.stroke
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 10.0);
      canvas.drawLine(p1, p2, outerGlowPaint);

      // Layer 2: Intense Medium Ambient Neon Beam
      final mediumGlowPaint = Paint()
        ..color = edgeColor.withOpacity(isDown ? 0.65 : 0.85)
        ..strokeWidth = (baseThickness * 2.4).clamp(5.0, 14.0)
        ..strokeCap = StrokeCap.round
        ..style = PaintingStyle.stroke
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 4.0);
      canvas.drawLine(p1, p2, mediumGlowPaint);

      // Layer 3: Solid Saturated Laser Core
      final corePaint = Paint()
        ..color = edgeColor
        ..strokeWidth = baseThickness
        ..strokeCap = StrokeCap.round
        ..style = PaintingStyle.stroke;
      canvas.drawLine(p1, p2, corePaint);

      // Layer 4: Ultra-Bright White/Cyan Hotspot Filament
      final centerHotspotPaint = Paint()
        ..color = Colors.white.withOpacity(isDown ? 0.5 : 0.85)
        ..strokeWidth = (baseThickness * 0.45).clamp(1.0, 2.5)
        ..strokeCap = StrokeCap.round
        ..style = PaintingStyle.stroke;
      canvas.drawLine(p1, p2, centerHotspotPaint);

      // Layer 5: Dynamic Animated Energy Packets Flowing
      if (!isDown) {
        // Particle 1 (Primary Energy Packet)
        final t1 = pulseValue;
        final px1 = srcX + (dstX - srcX) * t1;
        final py1 = srcY + (dstY - srcY) * t1;

        final particle1Glow = Paint()
          ..color = const Color(0xFF38BDF8).withOpacity(0.7)
          ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 6.0)
          ..style = PaintingStyle.fill;
        canvas.drawCircle(Offset(px1, py1), 7.5, particle1Glow);

        final particle1Core = Paint()
          ..color = Colors.white
          ..style = PaintingStyle.fill;
        canvas.drawCircle(Offset(px1, py1), 3.5, particle1Core);

        // Particle 2 (Secondary Trailing Packet at 50% phase offset)
        final t2 = (pulseValue + 0.5) % 1.0;
        final px2 = srcX + (dstX - srcX) * t2;
        final py2 = srcY + (dstY - srcY) * t2;

        final particle2Glow = Paint()
          ..color = edgeColor.withOpacity(0.8)
          ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 5.0)
          ..style = PaintingStyle.fill;
        canvas.drawCircle(Offset(px2, py2), 6.0, particle2Glow);

        final particle2Core = Paint()
          ..color = const Color(0xFFE0F2FE)
          ..style = PaintingStyle.fill;
        canvas.drawCircle(Offset(px2, py2), 2.5, particle2Core);
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
