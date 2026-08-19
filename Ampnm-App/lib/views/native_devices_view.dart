import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';
import '../app_theme.dart';
import '../models/device_model.dart';
import '../widgets/device_icon_widget.dart';

enum DeviceSortField { name, ip, status, latency, type }

class NativeDevicesView extends StatefulWidget {
  final List<DeviceModel> devices;
  final bool isLoading;
  final VoidCallback onRefresh;
  final ValueChanged<DeviceModel> onDeviceSelected;
  final Function(DeviceModel) onPingDevice;
  final VoidCallback? onAddDevice;
  final VoidCallback? onOpenScanner;
  final Function(DeviceModel)? onEditDevice;
  final Function(DeviceModel)? onDeleteDevice;
  final Function(DeviceModel)? onOpenContinuousPing;

  const NativeDevicesView({
    super.key,
    required this.devices,
    required this.isLoading,
    required this.onRefresh,
    required this.onDeviceSelected,
    required this.onPingDevice,
    this.onAddDevice,
    this.onOpenScanner,
    this.onEditDevice,
    this.onDeleteDevice,
    this.onOpenContinuousPing,
  });

  @override
  State<NativeDevicesView> createState() => _NativeDevicesViewState();
}

class _NativeDevicesViewState extends State<NativeDevicesView> {
  String _searchQuery = '';
  String _statusFilter = 'all';
  String _typeCategoryFilter = 'all';
  bool _isGridView = false;
  final Set<int> _selectedIds = {};
  bool _isBulkPinging = false;

  DeviceSortField _sortField = DeviceSortField.ip;
  bool _sortAscending = true;

  void _exportCsv() {
    final buffer = StringBuffer();
    buffer.writeln('ID,Name,IP,Port,Type,Status,AvgLatencyMs,LastSeen,Description');
    for (final d in widget.devices) {
      buffer.writeln('${d.id},"${d.name}","${d.ip}",${d.checkPort},"${d.type}","${d.status}",${d.lastAvgTime ?? 0},"${d.lastSeen ?? ''}","${d.description}"');
    }

    final bytes = utf8.encode(buffer.toString());
    final base64Data = base64Encode(bytes);
    final uri = Uri.parse('data:text/csv;base64,$base64Data');
    launchUrl(uri);

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Device inventory exported!'), backgroundColor: AppTheme.success),
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

  void _bulkPingSelected() async {
    final selectedDevices = widget.devices.where((d) => _selectedIds.contains(d.id)).toList();
    if (selectedDevices.isEmpty) return;

    setState(() => _isBulkPinging = true);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Running bulk ping on ${selectedDevices.length} nodes...'), backgroundColor: const Color(0xFF0891B2)),
    );

    for (final dev in selectedDevices) {
      if (!mounted) break;
      await widget.onPingDevice(dev);
      await Future.delayed(const Duration(milliseconds: 150));
    }

    if (mounted) {
      setState(() => _isBulkPinging = false);
      widget.onRefresh();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Bulk ping check completed!'), backgroundColor: AppTheme.success),
      );
    }
  }

  void _confirmDelete(DeviceModel device) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: const Color(0xFF0F172A),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: const BorderSide(color: Color(0xFF334155))),
        title: Row(
          children: [
            const Icon(Icons.warning_amber_rounded, color: Color(0xFFEF4444), size: 24),
            const SizedBox(width: 10),
            Text('Delete ${device.name}?', style: const TextStyle(color: Colors.white, fontSize: 16)),
          ],
        ),
        content: Text(
          'Are you sure you want to remove "${device.name}" (${device.ip}) from monitoring? This action cannot be undone.',
          style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.of(context).pop();
              if (widget.onDeleteDevice != null) {
                widget.onDeleteDevice!(device);
              }
            },
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFEF4444)),
            child: const Text('Delete Device'),
          ),
        ],
      ),
    );
  }

  void _setSort(DeviceSortField field) {
    setState(() {
      if (_sortField == field) {
        _sortAscending = !_sortAscending;
      } else {
        _sortField = field;
        _sortAscending = true;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final filtered = widget.devices.where((d) {
      if (_statusFilter == 'online' && !d.isOnline) return false;
      if (_statusFilter == 'offline' && !d.isOffline) return false;
      if (_statusFilter == 'warning' && !d.isWarning) return false;
      if (_statusFilter == 'critical' && !d.isCritical) return false;

      if (_typeCategoryFilter != 'all') {
        if (!d.type.toLowerCase().contains(_typeCategoryFilter)) return false;
      }

      if (_searchQuery.isNotEmpty) {
        final q = _searchQuery.toLowerCase();
        return d.name.toLowerCase().contains(q) ||
            d.ip.toLowerCase().contains(q) ||
            d.type.toLowerCase().contains(q) ||
            d.subchoice.toLowerCase().contains(q) ||
            d.description.toLowerCase().contains(q);
      }
      return true;
    }).toList();

    // Numerical IP and Smart Multi-field sorting
    filtered.sort((a, b) {
      int cmp = 0;
      switch (_sortField) {
        case DeviceSortField.name:
          cmp = a.name.toLowerCase().compareTo(b.name.toLowerCase());
          break;
        case DeviceSortField.ip:
          cmp = a.ipNumeric.compareTo(b.ipNumeric);
          if (cmp == 0) cmp = a.ip.compareTo(b.ip);
          break;
        case DeviceSortField.status:
          cmp = a.status.compareTo(b.status);
          break;
        case DeviceSortField.latency:
          cmp = (a.lastAvgTime ?? 0).compareTo(b.lastAvgTime ?? 0);
          break;
        case DeviceSortField.type:
          cmp = a.type.compareTo(b.type);
          break;
      }
      return _sortAscending ? cmp : -cmp;
    });

    final totalCount = widget.devices.where((d) => !d.isTextNode).length;
    final onlineCount = widget.devices.where((d) => d.isOnline).length;
    final offlineCount = widget.devices.where((d) => d.isOffline && !d.isTextNode).length;

    return Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // 1. Top Action Toolbar
          Row(
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Device Inventory & Management',
                    style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.white),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '$totalCount Total Endpoints • $onlineCount Online • $offlineCount Offline',
                    style: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
                  ),
                ],
              ),
              const Spacer(),
              if (widget.onOpenScanner != null)
                OutlinedButton.icon(
                  onPressed: widget.onOpenScanner,
                  icon: const Icon(Icons.radar, size: 16),
                  label: const Text('Subnet Scanner'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: const Color(0xFF22D3EE),
                    side: const BorderSide(color: Color(0xFF0891B2)),
                  ),
                ),
              const SizedBox(width: 10),
              if (widget.onAddDevice != null)
                ElevatedButton.icon(
                  onPressed: widget.onAddDevice,
                  icon: const Icon(Icons.add, size: 16),
                  label: const Text('Add Device'),
                  style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF0891B2)),
                ),
              const SizedBox(width: 10),
              IconButton(
                icon: const Icon(Icons.file_download_outlined, color: Color(0xFF94A3B8)),
                tooltip: 'Export CSV Inventory',
                onPressed: _exportCsv,
              ),
              IconButton(
                icon: Icon(widget.isLoading ? Icons.hourglass_top : Icons.refresh, color: const Color(0xFF22D3EE)),
                tooltip: 'Refresh & Sync',
                onPressed: widget.onRefresh,
              ),
            ],
          ),
          const SizedBox(height: 14),

          // 2. Search, Status Filters & View Toggle Bar
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFF0F172A),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFF334155)),
            ),
            child: Column(
              children: [
                Row(
                  children: [
                    // Search Box
                    Expanded(
                      flex: 3,
                      child: Container(
                        height: 38,
                        decoration: BoxDecoration(
                          color: const Color(0xFF1E293B),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFF475569)),
                        ),
                        child: TextField(
                          style: const TextStyle(color: Colors.white, fontSize: 13),
                          decoration: InputDecoration(
                            hintText: 'Search by hostname, IP address, type...',
                            hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
                            prefixIcon: const Icon(Icons.search, size: 18, color: Color(0xFF94A3B8)),
                            suffixIcon: _searchQuery.isNotEmpty
                                ? IconButton(
                                    icon: const Icon(Icons.clear, size: 14, color: Color(0xFF94A3B8)),
                                    onPressed: () => setState(() => _searchQuery = ''),
                                  )
                                : null,
                            border: InputBorder.none,
                            contentPadding: const EdgeInsets.symmetric(vertical: 10),
                          ),
                          onChanged: (val) => setState(() => _searchQuery = val),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),

                    // Status Filters
                    _buildStatusFilterChip('all', 'All (${widget.devices.length})'),
                    const SizedBox(width: 6),
                    _buildStatusFilterChip('online', 'Online ($onlineCount)', color: const Color(0xFF10B981)),
                    const SizedBox(width: 6),
                    _buildStatusFilterChip('offline', 'Offline ($offlineCount)', color: const Color(0xFFEF4444)),
                    const SizedBox(width: 6),
                    _buildStatusFilterChip('warning', 'Warn', color: const Color(0xFFF59E0B)),

                    const Spacer(),

                    // Grid / List Toggle
                    Container(
                      decoration: BoxDecoration(
                        color: const Color(0xFF1E293B),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        children: [
                          IconButton(
                            icon: Icon(Icons.view_list, color: !_isGridView ? const Color(0xFF22D3EE) : const Color(0xFF64748B), size: 20),
                            onPressed: () => setState(() => _isGridView = false),
                            tooltip: 'Table List View',
                          ),
                          IconButton(
                            icon: Icon(Icons.grid_view, color: _isGridView ? const Color(0xFF22D3EE) : const Color(0xFF64748B), size: 20),
                            onPressed: () => setState(() => _isGridView = true),
                            tooltip: 'NOC Grid Cards View',
                          ),
                        ],
                      ),
                    ),
                  ],
                ),

                // Category Quick Filter Pills
                const SizedBox(height: 10),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      const Text('Category: ', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8), fontWeight: FontWeight.bold)),
                      _buildCategoryFilterChip('all', 'All Types'),
                      _buildCategoryFilterChip('router', 'Routers'),
                      _buildCategoryFilterChip('switch', 'Switches'),
                      _buildCategoryFilterChip('server', 'Servers'),
                      _buildCategoryFilterChip('wifi', 'WiFi / AP'),
                      _buildCategoryFilterChip('firewall', 'Firewalls'),
                      _buildCategoryFilterChip('camera', 'Cameras'),
                      _buildCategoryFilterChip('nas', 'Storage / NAS'),
                      _buildCategoryFilterChip('pc', 'Workstations'),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),

          // 3. Multi-Select Bulk Actions Bar (if any selected)
          if (_selectedIds.isNotEmpty)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              margin: const EdgeInsets.only(bottom: 12),
              decoration: BoxDecoration(
                color: const Color(0xFF0369A1).withOpacity(0.2),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFF0284C7)),
              ),
              child: Row(
                children: [
                  Text(
                    '${_selectedIds.length} devices selected',
                    style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(width: 16),
                  ElevatedButton.icon(
                    onPressed: _isBulkPinging ? null : _bulkPingSelected,
                    icon: _isBulkPinging
                        ? const SizedBox(width: 12, height: 12, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Icon(Icons.flash_on, size: 14),
                    label: Text(_isBulkPinging ? 'Bulk Pinging...' : 'Bulk Ping Selected'),
                    style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF0891B2), minimumSize: const Size(0, 32)),
                  ),
                  const SizedBox(width: 10),
                  TextButton.icon(
                    onPressed: () {
                      final selected = widget.devices.where((d) => _selectedIds.contains(d.id)).toList();
                      final buffer = StringBuffer();
                      buffer.writeln('ID,Name,IP,Type,Status,AvgLatency');
                      for (final d in selected) {
                        buffer.writeln('${d.id},"${d.name}","${d.ip}","${d.type}","${d.status}",${d.lastAvgTime ?? 0}');
                      }
                      final bytes = utf8.encode(buffer.toString());
                      final base64Data = base64Encode(bytes);
                      launchUrl(Uri.parse('data:text/csv;base64,$base64Data'));
                    },
                    icon: const Icon(Icons.download, size: 14, color: Color(0xFF38BDF8)),
                    label: const Text('Export Selected', style: TextStyle(color: Color(0xFF38BDF8), fontSize: 12)),
                  ),
                  const Spacer(),
                  TextButton(
                    onPressed: () => setState(() => _selectedIds.clear()),
                    child: const Text('Clear Selection', style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12)),
                  ),
                ],
              ),
            ),

          // 4. Device Items View (List or Grid)
          Expanded(
            child: filtered.isEmpty
                ? const Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.devices_other, size: 48, color: Color(0xFF475569)),
                        SizedBox(height: 12),
                        Text('No matching devices found in inventory.', style: TextStyle(color: Color(0xFF94A3B8))),
                      ],
                    ),
                  )
                : _isGridView
                    ? _buildGridView(filtered)
                    : _buildTableView(filtered),
          ),
        ],
      ),
    );
  }

  Widget _buildStatusFilterChip(String key, String label, {Color? color}) {
    final isSelected = _statusFilter == key;
    final chipColor = color ?? const Color(0xFF22D3EE);

    return InkWell(
      onTap: () => setState(() => _statusFilter = key),
      borderRadius: BorderRadius.circular(6),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: isSelected ? chipColor.withOpacity(0.2) : Colors.transparent,
          borderRadius: BorderRadius.circular(6),
          border: Border.all(color: isSelected ? chipColor : const Color(0xFF334155)),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 11,
            fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
            color: isSelected ? chipColor : const Color(0xFF94A3B8),
          ),
        ),
      ),
    );
  }

  Widget _buildCategoryFilterChip(String key, String label) {
    final isSelected = _typeCategoryFilter == key;

    return Padding(
      padding: const EdgeInsets.only(left: 6),
      child: InkWell(
        onTap: () => setState(() => _typeCategoryFilter = key),
        borderRadius: BorderRadius.circular(6),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
          decoration: BoxDecoration(
            color: isSelected ? const Color(0xFF0284C7).withOpacity(0.25) : const Color(0xFF1E293B),
            borderRadius: BorderRadius.circular(6),
            border: Border.all(color: isSelected ? const Color(0xFF0284C7) : const Color(0xFF334155)),
          ),
          child: Text(
            label,
            style: TextStyle(
              fontSize: 10,
              fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
              color: isSelected ? const Color(0xFF38BDF8) : const Color(0xFF94A3B8),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildTableView(List<DeviceModel> list) {
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFF0F172A),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFF334155)),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: Column(
          children: [
            // Table Header with Sort Arrows
            Container(
              color: const Color(0xFF1E293B),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              child: Row(
                children: [
                  Checkbox(
                    value: _selectedIds.length == list.length && list.isNotEmpty,
                    onChanged: (val) {
                      setState(() {
                        if (val == true) {
                          _selectedIds.addAll(list.map((d) => d.id));
                        } else {
                          _selectedIds.clear();
                        }
                      });
                    },
                    fillColor: MaterialStateProperty.resolveWith((states) => const Color(0xFF0891B2)),
                  ),
                  const SizedBox(width: 6),
                  _buildSortHeader('DEVICE NAME', DeviceSortField.name, flex: 3),
                  _buildSortHeader('IP ADDRESS', DeviceSortField.ip, flex: 2),
                  _buildSortHeader('TYPE / ROLE', DeviceSortField.type, flex: 2),
                  _buildSortHeader('STATUS', DeviceSortField.status, flex: 2),
                  _buildSortHeader('LATENCY', DeviceSortField.latency, flex: 2),
                  const Expanded(
                    flex: 3,
                    child: Text(
                      'QUICK NOC ACTIONS',
                      style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8)),
                      textAlign: TextAlign.end,
                    ),
                  ),
                ],
              ),
            ),
            const Divider(color: Color(0xFF334155), height: 1),

            // Table Rows
            Expanded(
              child: ListView.separated(
                itemCount: list.length,
                separatorBuilder: (_, __) => const Divider(color: Color(0xFF1E293B), height: 1),
                itemBuilder: (context, idx) {
                  final d = list[idx];
                  final isSelected = _selectedIds.contains(d.id);

                  return InkWell(
                    onTap: () => widget.onDeviceSelected(d),
                    hoverColor: const Color(0xFF1E293B).withOpacity(0.5),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                      child: Row(
                        children: [
                          Checkbox(
                            value: isSelected,
                            onChanged: (val) {
                              setState(() {
                                if (val == true) {
                                  _selectedIds.add(d.id);
                                } else {
                                  _selectedIds.remove(d.id);
                                }
                              });
                            },
                            fillColor: MaterialStateProperty.resolveWith((states) => const Color(0xFF0891B2)),
                          ),
                          const SizedBox(width: 6),
                          // Name & Icon
                          Expanded(
                            flex: 3,
                            child: Row(
                              children: [
                                Container(
                                  width: 32,
                                  height: 32,
                                  decoration: BoxDecoration(
                                    color: const Color(0xFF1E293B),
                                    shape: BoxShape.circle,
                                    border: Border.all(color: d.statusColor, width: 1.5),
                                  ),
                                  child: Center(
                                    child: DeviceIconWidget(device: d, size: 20),
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Text(
                                    d.name,
                                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          // IP
                          Expanded(
                            flex: 2,
                            child: Text(
                              d.ip + (d.checkPort > 0 ? ':${d.checkPort}' : ''),
                              style: const TextStyle(color: Color(0xFF22D3EE), fontSize: 12, fontFamily: 'monospace'),
                            ),
                          ),
                          // Type
                          Expanded(
                            flex: 2,
                            child: Text(
                              d.type.toUpperCase(),
                              style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11),
                            ),
                          ),
                          // Status Badge
                          Expanded(
                            flex: 2,
                            child: Align(
                              alignment: Alignment.centerLeft,
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                decoration: BoxDecoration(
                                  color: d.statusColor.withOpacity(0.15),
                                  borderRadius: BorderRadius.circular(4),
                                  border: Border.all(color: d.statusColor.withOpacity(0.4)),
                                ),
                                child: Text(
                                  d.status.toUpperCase(),
                                  style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: d.statusColor),
                                ),
                              ),
                            ),
                          ),
                          // Latency
                          Expanded(
                            flex: 2,
                            child: Text(
                              d.isOnline && d.lastAvgTime != null && d.lastAvgTime! > 0
                                  ? '${d.lastAvgTime!.toStringAsFixed(1)} ms'
                                  : (d.isOnline ? 'Online' : 'Down'),
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                                color: d.isOnline ? const Color(0xFF10B981) : const Color(0xFFEF4444),
                              ),
                            ),
                          ),
                          // Quick Actions Row
                          Expanded(
                            flex: 3,
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.end,
                              children: [
                                IconButton(
                                  icon: const Icon(Icons.flash_on, size: 16, color: Color(0xFF10B981)),
                                  tooltip: 'Ping Now',
                                  onPressed: () => widget.onPingDevice(d),
                                ),
                                if (widget.onOpenContinuousPing != null)
                                  IconButton(
                                    icon: const Icon(Icons.timeline, size: 16, color: Color(0xFF38BDF8)),
                                    tooltip: 'Continuous Ping',
                                    onPressed: () => widget.onOpenContinuousPing!(d),
                                  ),
                                if (d.isWebCapable)
                                  IconButton(
                                    icon: const Icon(Icons.open_in_browser, size: 16, color: Color(0xFF22D3EE)),
                                    tooltip: 'Open Web GUI',
                                    onPressed: () => _launchWeb(d),
                                  ),
                                if (d.isMikrotikOrRouter)
                                  IconButton(
                                    icon: const Icon(Icons.router, size: 16, color: Color(0xFFA78BFA)),
                                    tooltip: 'Open Winbox',
                                    onPressed: () => _launchWinbox(d),
                                  ),
                                if (d.isSshCapable)
                                  IconButton(
                                    icon: const Icon(Icons.terminal, size: 16, color: Color(0xFFF59E0B)),
                                    tooltip: 'Open SSH Terminal',
                                    onPressed: () => _launchSSH(d),
                                  ),
                                if (d.isRdpCapable)
                                  IconButton(
                                    icon: const Icon(Icons.desktop_windows, size: 16, color: Color(0xFFFB923C)),
                                    tooltip: 'Open Remote Desktop (RDP)',
                                    onPressed: () => _launchRDP(d),
                                  ),
                                if (widget.onEditDevice != null)
                                  IconButton(
                                    icon: const Icon(Icons.edit, size: 15, color: Color(0xFF94A3B8)),
                                    tooltip: 'Edit Device',
                                    onPressed: () => widget.onEditDevice!(d),
                                  ),
                                if (widget.onDeleteDevice != null)
                                  IconButton(
                                    icon: const Icon(Icons.delete_outline, size: 15, color: Color(0xFFEF4444)),
                                    tooltip: 'Delete Device',
                                    onPressed: () => _confirmDelete(d),
                                  ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSortHeader(String title, DeviceSortField field, {required int flex}) {
    final isCurrent = _sortField == field;

    return Expanded(
      flex: flex,
      child: InkWell(
        onTap: () => _setSort(field),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              title,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.bold,
                color: isCurrent ? const Color(0xFF22D3EE) : const Color(0xFF94A3B8),
              ),
            ),
            if (isCurrent)
              Icon(
                _sortAscending ? Icons.arrow_upward : Icons.arrow_downward,
                size: 12,
                color: const Color(0xFF22D3EE),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildGridView(List<DeviceModel> list) {
    return GridView.builder(
      gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
        maxCrossAxisExtent: 320,
        mainAxisExtent: 180,
        crossAxisSpacing: 14,
        mainAxisSpacing: 14,
      ),
      itemCount: list.length,
      itemBuilder: (context, idx) {
        final d = list[idx];
        final isSelected = _selectedIds.contains(d.id);

        return InkWell(
          onTap: () => widget.onDeviceSelected(d),
          borderRadius: BorderRadius.circular(12),
          child: Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFF0F172A),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: isSelected ? const Color(0xFF0284C7) : const Color(0xFF334155), width: isSelected ? 1.5 : 1),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 36,
                      height: 36,
                      decoration: BoxDecoration(
                        color: const Color(0xFF1E293B),
                        shape: BoxShape.circle,
                        border: Border.all(color: d.statusColor, width: 1.5),
                      ),
                      child: Center(
                        child: DeviceIconWidget(device: d, size: 24),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            d.name,
                            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          Text(
                            d.ip,
                            style: const TextStyle(color: Color(0xFF22D3EE), fontSize: 11, fontFamily: 'monospace'),
                          ),
                        ],
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: d.statusColor.withOpacity(0.15),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Text(
                        d.status.toUpperCase(),
                        style: TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: d.statusColor),
                      ),
                    ),
                  ],
                ),
                const Spacer(),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      d.type.toUpperCase(),
                      style: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8)),
                    ),
                    Text(
                      d.isOnline && d.lastAvgTime != null && d.lastAvgTime! > 0 ? '${d.lastAvgTime!.toStringAsFixed(1)} ms' : '',
                      style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF10B981)),
                    ),
                  ],
                ),
                const Divider(color: Color(0xFF1E293B), height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    IconButton(
                      icon: const Icon(Icons.flash_on, size: 16, color: Color(0xFF10B981)),
                      tooltip: 'Ping',
                      onPressed: () => widget.onPingDevice(d),
                    ),
                    if (d.isWebCapable)
                      IconButton(
                        icon: const Icon(Icons.open_in_browser, size: 16, color: Color(0xFF22D3EE)),
                        tooltip: 'Web GUI',
                        onPressed: () => _launchWeb(d),
                      ),
                    if (d.isMikrotikOrRouter)
                      IconButton(
                        icon: const Icon(Icons.router, size: 16, color: Color(0xFFA78BFA)),
                        tooltip: 'Winbox',
                        onPressed: () => _launchWinbox(d),
                      ),
                    if (d.isSshCapable)
                      IconButton(
                        icon: const Icon(Icons.terminal, size: 16, color: Color(0xFFF59E0B)),
                        tooltip: 'SSH',
                        onPressed: () => _launchSSH(d),
                      ),
                    if (widget.onEditDevice != null)
                      IconButton(
                        icon: const Icon(Icons.edit, size: 15, color: Color(0xFF94A3B8)),
                        tooltip: 'Edit',
                        onPressed: () => widget.onEditDevice!(d),
                      ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
