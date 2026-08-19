import 'dart:io';
import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:window_manager/window_manager.dart';

import '../app_theme.dart';
import '../models/device_model.dart';
import '../models/edge_model.dart';
import '../models/map_model.dart';
import '../widgets/add_edit_text_dialog.dart';
import '../widgets/connect_nodes_dialog.dart';
import '../widgets/device_icon_widget.dart';
import '../widgets/edit_edge_dialog.dart';

class NativeMapView extends StatefulWidget {
  final List<MapModel> maps;
  final List<DeviceModel> devices;
  final List<DeviceModel>? allDevices;
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
    this.allDevices,
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
  final TextEditingController _searchController = TextEditingController();
  late AnimationController _pulseAnimController;

  static const double kNodeWidth = 140.0;
  static const double kCircleRadius = 28.0;
  static const Offset kCircleCenterOffset = Offset(kNodeWidth / 2, kCircleRadius + 2);

  String _searchQuery = '';
  int? _highlightedDeviceId;
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

    // Auto-fit nodes on initial mount
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _fitToScreen();
    });
  }

  @override
  void didUpdateWidget(covariant NativeMapView oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.isTabVisible && !_pulseAnimController.isAnimating) {
      _pulseAnimController.repeat();
    } else if (!widget.isTabVisible && _pulseAnimController.isAnimating) {
      _pulseAnimController.stop();
    }

    if (widget.selectedMapId != oldWidget.selectedMapId || 
        widget.devices.length != oldWidget.devices.length ||
        (widget.allDevices?.length ?? 0) != (oldWidget.allDevices?.length ?? 0)) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _fitToScreen();
      });
    }
  }

  @override
  void dispose() {
    _transformController.dispose();
    _searchController.dispose();
    _pulseAnimController.dispose();
    super.dispose();
  }

  List<DeviceModel> get _effectiveDevices {
    if (widget.devices.isNotEmpty) {
      return widget.devices;
    }
    if (widget.allDevices != null && widget.allDevices!.isNotEmpty) {
      return widget.allDevices!;
    }
    return [];
  }

  void _zoom(double factor) {
    final matrix = _transformController.value.clone();
    matrix.scale(factor, factor);
    _transformController.value = matrix;
  }

  void _resetZoom() {
    _transformController.value = Matrix4.identity();
  }

  Offset _getDeviceOffset(DeviceModel device) {
    if (_draggedPositions.containsKey(device.id)) {
      return _draggedPositions[device.id]!;
    }
    double rawX = device.x.toDouble();
    double rawY = device.y.toDouble();

    // If position is at default (0, 0), calculate a pleasant spread
    if (rawX == 0 && rawY == 0) {
      final allList = _effectiveDevices;
      final index = allList.indexOf(device);
      final idx = index >= 0 ? index : device.id;
      const cols = 4;
      final col = idx % cols;
      final row = idx ~/ cols;
      return Offset(1300.0 + col * 320.0, 950.0 + row * 240.0);
    }

    if (rawX.abs() < 1200 && rawY.abs() < 1200) {
      return Offset(1900.0 + rawX * 1.8 - (kNodeWidth / 2), 1400.0 + rawY * 1.8 - 30.0);
    }

    return Offset(rawX.clamp(100.0, 3600.0), rawY.clamp(100.0, 2600.0));
  }

  void _centerOnDevice(DeviceModel device) {
    final pos = _getDeviceOffset(device);
    final viewSize = MediaQuery.of(context).size;
    const targetScale = 1.1;

    final targetX = (viewSize.width - 240) / 2 - (pos.dx + kNodeWidth / 2) * targetScale;
    final targetY = (viewSize.height - 80) / 2 - (pos.dy + 30) * targetScale;

    final matrix = Matrix4.identity()
      ..translate(targetX, targetY)
      ..scale(targetScale, targetScale);

    setState(() {
      _transformController.value = matrix;
      _highlightedDeviceId = device.id;
    });

    Future.delayed(const Duration(seconds: 4), () {
      if (mounted) setState(() => _highlightedDeviceId = null);
    });
  }

  void _fitToScreen() {
    final devList = _effectiveDevices;
    if (devList.isEmpty) {
      _resetZoom();
      return;
    }

    double minX = double.infinity;
    double minY = double.infinity;
    double maxX = double.negativeInfinity;
    double maxY = double.negativeInfinity;

    for (final d in devList) {
      final pos = _getDeviceOffset(d);
      minX = math.min(minX, pos.dx);
      minY = math.min(minY, pos.dy);
      maxX = math.max(maxX, pos.dx + kNodeWidth);
      maxY = math.max(maxY, pos.dy + 120);
    }

    final boxW = math.max(300.0, maxX - minX + 240);
    final boxH = math.max(300.0, maxY - minY + 240);

    final viewSize = MediaQuery.of(context).size;
    final scaleX = (viewSize.width - 340) / boxW;
    final scaleY = (viewSize.height - 200) / boxH;
    final scale = math.min(scaleX, scaleY).clamp(0.25, 1.4);

    final centerX = (minX + maxX) / 2;
    final centerY = (minY + maxY) / 2;

    final targetX = (viewSize.width - 240) / 2 - centerX * scale;
    final targetY = (viewSize.height - 80) / 2 - centerY * scale;

    final matrix = Matrix4.identity()
      ..translate(targetX, targetY)
      ..scale(scale, scale);

    setState(() {
      _transformController.value = matrix;
    });
  }

  void _toggleFullScreen() async {
    final isFull = await windowManager.isFullScreen();
    await windowManager.setFullScreen(!isFull);
    setState(() {
      _isFullScreen = !isFull;
    });
  }

  void _autoLayout(String layoutStyle) {
    final hardwareNodes = _effectiveDevices.where((d) => !d.isTextNode).toList();
    final total = hardwareNodes.length;
    if (total == 0) return;

    setState(() {
      if (layoutStyle == 'tree') {
        // Hierarchical Tree by type (Routers on top, switches mid, servers & clients below)
        final routers = hardwareNodes.where((d) => d.type.contains('router')).toList();
        final switches = hardwareNodes.where((d) => d.type.contains('switch')).toList();
        final others = hardwareNodes.where((d) => !d.type.contains('router') && !d.type.contains('switch')).toList();

        void placeRow(List<DeviceModel> list, double y) {
          final count = list.length;
          const spacing = 280.0;
          final startX = 1900.0 - ((count - 1) * spacing) / 2;
          for (int i = 0; i < count; i++) {
            final x = startX + i * spacing;
            _draggedPositions[list[i].id] = Offset(x, y);
            if (widget.onUpdatePosition != null) {
              final serverX = (x + (kNodeWidth / 2) - 1900) / 1.8;
              final serverY = (y + 30 - 1400) / 1.8;
              widget.onUpdatePosition!(list[i], serverX, serverY);
            }
          }
        }

        placeRow(routers, 1000.0);
        placeRow(switches, 1300.0);
        placeRow(others, 1650.0);
      } else {
        // Uniform Grid
        final cols = (total > 15) ? 5 : ((total > 8) ? 4 : 3);
        const spacingX = 300.0;
        const spacingY = 220.0;
        const startX = 1100.0;
        const startY = 900.0;

        for (int i = 0; i < total; i++) {
          final d = hardwareNodes[i];
          final col = i % cols;
          final row = i ~/ cols;
          final x = startX + col * spacingX;
          final y = startY + row * spacingY;
          _draggedPositions[d.id] = Offset(x, y);
          if (widget.onUpdatePosition != null) {
            final serverX = (x + (kNodeWidth / 2) - 1900) / 1.8;
            final serverY = (y + 30 - 1400) / 1.8;
            widget.onUpdatePosition!(d, serverX, serverY);
          }
        }
      }
    });

    _fitToScreen();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Auto-arranged map topology ($layoutStyle layout)!'),
        backgroundColor: AppTheme.primary,
        duration: const Duration(seconds: 2),
      ),
    );
  }

  void _launchSSH(DeviceModel device) {
    final ip = device.ip.split(':').first.trim();
    if (ip.isEmpty) return;
    try {
      if (Platform.isWindows) {
        Process.start('powershell.exe', ['-NoExit', '-Command', 'ssh $ip'], mode: ProcessStartMode.detached);
      } else {
        launchUrl(Uri.parse('ssh://$ip'));
      }
    } catch (_) {
      Clipboard.setData(ClipboardData(text: 'ssh $ip'));
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Copied "ssh $ip" to clipboard!'), backgroundColor: AppTheme.primary));
    }
  }

  void _launchWinbox(DeviceModel device) {
    final ip = device.ip.split(':').first.trim();
    if (ip.isEmpty) return;
    try {
      if (Platform.isWindows) {
        Process.start('winbox64.exe', [ip], mode: ProcessStartMode.detached).catchError((_) {
          return Process.start('winbox.exe', [ip], mode: ProcessStartMode.detached);
        });
      } else {
        launchUrl(Uri.parse('winbox://$ip'));
      }
    } catch (_) {}
    Clipboard.setData(ClipboardData(text: ip));
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Copied IP $ip for Winbox!'), backgroundColor: AppTheme.primary));
  }

  void _launchRDP(DeviceModel device) {
    final ip = device.ip.split(':').first.trim();
    if (ip.isEmpty) return;
    try {
      if (Platform.isWindows) {
        Process.start('mstsc.exe', ['/v:$ip'], mode: ProcessStartMode.detached);
      }
    } catch (_) {}
  }

  void _launchWeb(DeviceModel device) {
    final ip = device.ip;
    if (ip.isEmpty) return;
    final port = device.checkPort;
    final protocol = (port == 443 || port == 8443) ? 'https' : 'http';
    final portSuffix = (port > 0 && port != 80 && port != 443) ? ':$port' : '';
    final uri = Uri.parse('$protocol://$ip$portSuffix');
    launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  void _openConnectDialog([DeviceModel? initialSource]) {
    showDialog(
      context: context,
      builder: (context) => ConnectNodesDialog(
        devices: _effectiveDevices.where((d) => !d.isTextNode).toList(),
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
        devices: _effectiveDevices,
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
          width: 600,
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
                      final src = _effectiveDevices.firstWhere(
                        (d) => d.id == edge.sourceId,
                        orElse: () => DeviceModel(id: 0, name: 'Node #${edge.sourceId}', ip: ''),
                      );
                      final dst = _effectiveDevices.firstWhere(
                        (d) => d.id == edge.targetId,
                        orElse: () => DeviceModel(id: 0, name: 'Node #${edge.targetId}', ip: ''),
                      );

                      return ListTile(
                        dense: true,
                        leading: Container(
                          width: 14,
                          height: 14,
                          decoration: BoxDecoration(
                            color: edge.displayColor,
                            shape: BoxShape.circle,
                            boxShadow: [BoxShadow(color: edge.displayColor.withOpacity(0.6), blurRadius: 6)],
                          ),
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
                            ElevatedButton.icon(
                              onPressed: () {
                                Navigator.of(context).pop();
                                _openEditEdgeModal(edge);
                              },
                              icon: const Icon(Icons.edit, size: 12),
                              label: const Text('Edit', style: TextStyle(fontSize: 11)),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF0891B2),
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                minimumSize: const Size(0, 30),
                              ),
                            ),
                            const SizedBox(width: 6),
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
                Text('Edit Device & Icon', style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold)),
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
          if (device.isWebCapable)
            const PopupMenuItem<String>(
              value: 'web',
              child: Row(
                children: [
                  Icon(Icons.open_in_browser, color: Color(0xFF38BDF8), size: 16),
                  SizedBox(width: 10),
                  Text('Open Web GUI', style: TextStyle(color: Colors.white, fontSize: 13)),
                ],
              ),
            ),
          if (device.isMikrotikOrRouter)
            const PopupMenuItem<String>(
              value: 'winbox',
              child: Row(
                children: [
                  Icon(Icons.router, color: Color(0xFFA78BFA), size: 16),
                  SizedBox(width: 10),
                  Text('Open Winbox', style: TextStyle(color: Colors.white, fontSize: 13)),
                ],
              ),
            ),
          if (device.isSshCapable)
            const PopupMenuItem<String>(
              value: 'ssh',
              child: Row(
                children: [
                  Icon(Icons.terminal, color: Color(0xFFF59E0B), size: 16),
                  SizedBox(width: 10),
                  Text('Open SSH Terminal', style: TextStyle(color: Colors.white, fontSize: 13)),
                ],
              ),
            ),
          if (device.isRdpCapable)
            const PopupMenuItem<String>(
              value: 'rdp',
              child: Row(
                children: [
                  Icon(Icons.desktop_windows, color: Color(0xFFFB923C), size: 16),
                  SizedBox(width: 10),
                  Text('Remote Desktop (RDP)', style: TextStyle(color: Colors.white, fontSize: 13)),
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
      } else if (action == 'web') {
        _launchWeb(device);
      } else if (action == 'winbox') {
        _launchWinbox(device);
      } else if (action == 'ssh') {
        _launchSSH(device);
      } else if (action == 'rdp') {
        _launchRDP(device);
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
    final devList = _effectiveDevices;
    final filteredDevices = devList.where((d) {
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
        // 1. Interactive Topology Canvas
        Container(
          color: const Color(0xFF0B1120),
          child: InteractiveViewer(
            transformationController: _transformController,
            boundaryMargin: const EdgeInsets.all(3000),
            minScale: 0.05,
            maxScale: 4.0,
            child: SizedBox(
              width: 3800,
              height: 2800,
              child: Stack(
                clipBehavior: Clip.none,
                children: [
                  // Layer A: Grid Pattern
                  Positioned.fill(
                    child: CustomPaint(
                      painter: _GridBackgroundPainter(),
                    ),
                  ),

                  // Layer B: Connection Lines & Animated Glow Packets
                  Positioned.fill(
                    child: AnimatedBuilder(
                      animation: _pulseAnimController,
                      builder: (context, _) {
                        return CustomPaint(
                          painter: _MapCanvasPainter(
                            devices: devList,
                            edges: widget.edges,
                            pulseValue: _pulseAnimController.value,
                            positionGetter: _getDeviceOffset,
                            circleOffset: kCircleCenterOffset,
                          ),
                        );
                      },
                    ),
                  ),

                  // Layer C: All Device Nodes (Positioned directly in 3800x2800 canvas)
                  ...filteredDevices.map((device) {
                    return _buildDockerStyleDeviceNode(device);
                  }),
                ],
              ),
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

                // Live Search Input with Instant Node Centering
                Container(
                  width: 220,
                  height: 34,
                  decoration: BoxDecoration(
                    color: const Color(0xFF0F172A),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: const Color(0xFF475569)),
                  ),
                  child: TextField(
                    controller: _searchController,
                    style: const TextStyle(color: Colors.white, fontSize: 12),
                    decoration: InputDecoration(
                      hintText: 'Search or jump to node/IP...',
                      hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 11),
                      prefixIcon: const Icon(Icons.search, size: 16, color: Color(0xFF94A3B8)),
                      suffixIcon: _searchQuery.isNotEmpty
                          ? IconButton(
                              icon: const Icon(Icons.clear, size: 12, color: Color(0xFF94A3B8)),
                              onPressed: () {
                                _searchController.clear();
                                setState(() => _searchQuery = '');
                              },
                            )
                          : null,
                      contentPadding: const EdgeInsets.symmetric(vertical: 8),
                      border: InputBorder.none,
                    ),
                    onChanged: (val) => setState(() => _searchQuery = val),
                    onSubmitted: (val) {
                      if (filteredDevices.isNotEmpty) {
                        _centerOnDevice(filteredDevices.first);
                      }
                    },
                  ),
                ),

                const Spacer(),

                // Device count badge
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFF0F172A),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: const Color(0xFF334155)),
                  ),
                  child: Text(
                    '${filteredDevices.length} Devices',
                    style: const TextStyle(color: Color(0xFF38BDF8), fontSize: 11, fontWeight: FontWeight.bold),
                  ),
                ),
                const SizedBox(width: 8),

                // Scan Network Action
                IconButton(
                  icon: const Icon(Icons.radar, size: 18, color: Color(0xFFCBD5E1)),
                  tooltip: 'Scan Subnet',
                  onPressed: widget.onOpenScanner,
                ),

                // Add Device Action
                IconButton(
                  icon: const Icon(Icons.add_circle, size: 20, color: Color(0xFF22D3EE)),
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

                // Links & Connection Manager
                OutlinedButton.icon(
                  onPressed: _showLinksManager,
                  icon: const Icon(Icons.hub, size: 14, color: Color(0xFF38BDF8)),
                  label: Text('Links (${widget.edges.length}) • Edit', style: const TextStyle(fontSize: 11, color: Colors.white)),
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(color: Color(0xFF0284C7)),
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    minimumSize: const Size(0, 32),
                  ),
                ),
                const SizedBox(width: 8),

                // Fit to Screen Button
                IconButton(
                  icon: const Icon(Icons.filter_center_focus, size: 18, color: Color(0xFF22D3EE)),
                  tooltip: 'Fit All Devices to Screen',
                  onPressed: _fitToScreen,
                ),

                // Topology Auto Arrange Menu (Tree / Grid)
                PopupMenuButton<String>(
                  icon: const Icon(Icons.auto_awesome_mosaic, size: 18, color: Color(0xFFCBD5E1)),
                  tooltip: 'Auto Layout Topology',
                  color: const Color(0xFF0F172A),
                  onSelected: _autoLayout,
                  itemBuilder: (context) => [
                    const PopupMenuItem(
                      value: 'tree',
                      child: Row(
                        children: [
                          Icon(Icons.account_tree, size: 16, color: Color(0xFF22D3EE)),
                          SizedBox(width: 8),
                          Text('Hierarchical Tree (Routers -> Switches -> Nodes)', style: TextStyle(color: Colors.white, fontSize: 12)),
                        ],
                      ),
                    ),
                    const PopupMenuItem(
                      value: 'grid',
                      child: Row(
                        children: [
                          Icon(Icons.grid_view, size: 16, color: Color(0xFF38BDF8)),
                          SizedBox(width: 8),
                          Text('Clean Uniform Grid Layout', style: TextStyle(color: Colors.white, fontSize: 12)),
                        ],
                      ),
                    ),
                  ],
                ),

                // Refresh Status
                IconButton(
                  icon: const Icon(Icons.refresh, size: 18, color: Color(0xFFCBD5E1)),
                  tooltip: 'Refresh Status',
                  onPressed: widget.onRefresh,
                ),

                // Toggle Fullscreen
                IconButton(
                  icon: Icon(_isFullScreen ? Icons.fullscreen_exit : Icons.fullscreen, size: 22, color: const Color(0xFF22D3EE)),
                  tooltip: _isFullScreen ? 'Exit Fullscreen' : 'Enter Fullscreen',
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
                  width: 210,
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
                      _buildConnectionLegendItem(const Color(0xFF38BDF8), '📡 WiFi Link'),
                      _buildConnectionLegendItem(const Color(0xFF84CC16), '📻 Radio Link'),
                      _buildConnectionLegendItem(const Color(0xFF60A5FA), '🌐 Standard LAN'),
                      _buildConnectionLegendItem(const Color(0xFFC084FC), '🔒 VPN / Tunnel'),
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

        // 6. Floating Zoom & Fit-to-Screen Controls (Bottom Center)
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
                    icon: const Icon(Icons.filter_center_focus, size: 18, color: Color(0xFF22D3EE)),
                    onPressed: _fitToScreen,
                    tooltip: 'Fit All Devices to Screen',
                  ),
                  IconButton(
                    icon: const Icon(Icons.center_focus_strong, size: 18, color: Colors.white),
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
    final isBeaconHighlighted = _highlightedDeviceId == device.id;

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
              final serverX = (finalPos.dx + (kNodeWidth / 2) - 1900) / 1.8;
              final serverY = (finalPos.dy + 30 - 1400) / 1.8;
              widget.onUpdatePosition!(device, serverX, serverY);
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
            final serverX = (finalPos.dx + (kNodeWidth / 2) - 1900) / 1.8;
            final serverY = (finalPos.dy + 30 - 1400) / 1.8;
            widget.onUpdatePosition!(device, serverX, serverY);
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
          child: SizedBox(
            width: kNodeWidth,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                // Circular Glowing Node with Pulse Radar
                Stack(
                  alignment: Alignment.center,
                  children: [
                    if (isBeaconHighlighted)
                      Container(
                        width: 72,
                        height: 72,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          border: Border.all(color: const Color(0xFF22D3EE), width: 2),
                          boxShadow: const [
                            BoxShadow(color: Color(0xFF22D3EE), blurRadius: 20, spreadRadius: 4),
                          ],
                        ),
                      ),
                    Container(
                      width: kCircleRadius * 2,
                      height: kCircleRadius * 2,
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
                        child: DeviceIconWidget(
                          device: device,
                          size: 34,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),

                // Docker Web Style Info Card Label with Edit Handle
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFF0F172A).withOpacity(0.95),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: isBeaconHighlighted ? const Color(0xFF22D3EE) : const Color(0xFF334155)),
                    boxShadow: const [BoxShadow(color: Colors.black54, blurRadius: 4)],
                  ),
                  child: Column(
                    children: [
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Flexible(
                            child: Text(
                              device.name,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 11,
                                fontWeight: FontWeight.bold,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          const SizedBox(width: 4),
                          InkWell(
                            onTap: () {
                              if (widget.onEditDevice != null) widget.onEditDevice!(device);
                            },
                            child: const Icon(Icons.edit, size: 11, color: Color(0xFF22D3EE)),
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
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
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
      ),
    );
  }
}

class _MapCanvasPainter extends CustomPainter {
  final List<DeviceModel> devices;
  final List<EdgeModel> edges;
  final double pulseValue;
  final Offset Function(DeviceModel) positionGetter;
  final Offset circleOffset;

  _MapCanvasPainter({
    required this.devices,
    required this.edges,
    required this.pulseValue,
    required this.positionGetter,
    required this.circleOffset,
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

      final srcX = srcPos.dx + circleOffset.dx;
      final srcY = srcPos.dy + circleOffset.dy;
      final dstX = dstPos.dx + circleOffset.dx;
      final dstY = dstPos.dy + circleOffset.dy;

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
