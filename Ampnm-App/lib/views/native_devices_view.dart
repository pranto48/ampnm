import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../app_theme.dart';
import '../models/device_model.dart';

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
  bool _isGridView = false;

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

  void _confirmDelete(DeviceModel device) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: AppTheme.surfaceCard,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: const BorderSide(color: AppTheme.border)),
        title: Row(
          children: [
            const Icon(Icons.warning_amber_rounded, color: AppTheme.danger, size: 24),
            const SizedBox(width: 10),
            Text('Delete ${device.name}?', style: const TextStyle(color: Colors.white, fontSize: 16)),
          ],
        ),
        content: Text(
          'Are you sure you want to remove "${device.name}" (${device.ip}) from monitoring? This action cannot be undone.',
          style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
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
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger),
            child: const Text('Delete Device'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final filtered = widget.devices.where((d) {
      if (_statusFilter == 'online' && !d.isOnline) return false;
      if (_statusFilter == 'offline' && !d.isOffline) return false;
      if (_statusFilter == 'warning' && !d.isWarning) return false;

      if (_searchQuery.isNotEmpty) {
        final q = _searchQuery.toLowerCase();
        return d.name.toLowerCase().contains(q) ||
            d.ip.contains(q) ||
            d.type.toLowerCase().contains(q) ||
            d.description.toLowerCase().contains(q);
      }
      return true;
    }).toList();

    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header Bar
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Device Inventory & Live Telemetry',
                    style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                      color: Colors.white,
                      letterSpacing: -0.5,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Showing ${filtered.length} of ${widget.devices.length} network nodes',
                    style: const TextStyle(fontSize: 13, color: AppTheme.textSecondary),
                  ),
                ],
              ),
              Row(
                children: [
                  // Subnet Scanner Action
                  OutlinedButton.icon(
                    onPressed: widget.onOpenScanner,
                    icon: const Icon(Icons.radar, size: 15),
                    label: const Text('IP Scanner', style: TextStyle(fontSize: 12)),
                  ),
                  const SizedBox(width: 8),

                  // Add New Device Action
                  ElevatedButton.icon(
                    onPressed: widget.onAddDevice,
                    icon: const Icon(Icons.add, size: 16),
                    label: const Text('Add Device', style: TextStyle(fontSize: 12)),
                    style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
                  ),
                  const SizedBox(width: 8),

                  // Export CSV
                  OutlinedButton.icon(
                    onPressed: _exportCsv,
                    icon: const Icon(Icons.download, size: 15),
                    label: const Text('Export CSV', style: TextStyle(fontSize: 12)),
                  ),
                  const SizedBox(width: 12),

                  // View Toggle
                  Container(
                    decoration: BoxDecoration(
                      color: AppTheme.surfaceCard,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: AppTheme.border),
                    ),
                    child: Row(
                      children: [
                        IconButton(
                          icon: Icon(
                            Icons.table_rows,
                            size: 18,
                            color: !_isGridView ? AppTheme.primaryGlow : AppTheme.textSecondary,
                          ),
                          tooltip: 'Table List View',
                          onPressed: () => setState(() => _isGridView = false),
                        ),
                        IconButton(
                          icon: Icon(
                            Icons.grid_view,
                            size: 18,
                            color: _isGridView ? AppTheme.primaryGlow : AppTheme.textSecondary,
                          ),
                          tooltip: 'Grid Cards View',
                          onPressed: () => setState(() => _isGridView = true),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton(
                    icon: widget.isLoading
                        ? const SizedBox(
                            width: 14,
                            height: 14,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : const Icon(Icons.refresh, size: 18, color: AppTheme.primaryGlow),
                    tooltip: 'Refresh',
                    onPressed: widget.onRefresh,
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 20),

          // Filters & Search Row
          Row(
            children: [
              Expanded(
                child: Container(
                  height: 42,
                  decoration: BoxDecoration(
                    color: AppTheme.surfaceCard,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: AppTheme.border),
                  ),
                  child: TextField(
                    style: const TextStyle(color: Colors.white, fontSize: 13),
                    decoration: const InputDecoration(
                      hintText: 'Search by device name, IP address, type or description...',
                      prefixIcon: Icon(Icons.search, size: 18, color: AppTheme.textSecondary),
                      contentPadding: EdgeInsets.symmetric(vertical: 10),
                      border: InputBorder.none,
                      enabledBorder: InputBorder.none,
                      focusedBorder: InputBorder.none,
                    ),
                    onChanged: (val) => setState(() => _searchQuery = val),
                  ),
                ),
              ),
              const SizedBox(width: 16),
              _FilterChip(
                label: 'All (${widget.devices.length})',
                isSelected: _statusFilter == 'all',
                onTap: () => setState(() => _statusFilter = 'all'),
              ),
              const SizedBox(width: 8),
              _FilterChip(
                label: 'Online (${widget.devices.where((d) => d.isOnline).length})',
                color: AppTheme.success,
                isSelected: _statusFilter == 'online',
                onTap: () => setState(() => _statusFilter = 'online'),
              ),
              const SizedBox(width: 8),
              _FilterChip(
                label: 'Offline (${widget.devices.where((d) => d.isOffline).length})',
                color: AppTheme.danger,
                isSelected: _statusFilter == 'offline',
                onTap: () => setState(() => _statusFilter = 'offline'),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Content: Table View or Grid View
          Expanded(
            child: filtered.isEmpty
                ? const Center(
                    child: Text(
                      'No matching devices found.',
                      style: TextStyle(color: AppTheme.textMuted, fontSize: 14),
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

  Widget _buildTableView(List<DeviceModel> list) {
    return Card(
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: ListView.separated(
          itemCount: list.length + 1,
          separatorBuilder: (_, __) => const Divider(color: AppTheme.border, height: 1),
          itemBuilder: (context, index) {
            if (index == 0) {
              return Container(
                color: const Color(0xFF0F172A),
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
                child: const Row(
                  children: [
                    SizedBox(width: 90, child: Text('STATUS', style: _headerStyle)),
                    Expanded(flex: 3, child: Text('DEVICE NAME & TYPE', style: _headerStyle)),
                    Expanded(flex: 2, child: Text('IP ADDRESS', style: _headerStyle)),
                    SizedBox(width: 110, child: Text('LATENCY', style: _headerStyle)),
                    Expanded(flex: 2, child: Text('TELEMETRY', style: _headerStyle)),
                    SizedBox(width: 170, child: Text('ACTIONS', style: _headerStyle)),
                  ],
                ),
              );
            }

            final d = list[index - 1];
            return InkWell(
              onTap: () => widget.onDeviceSelected(d),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                child: Row(
                  children: [
                    // Status Badge
                    SizedBox(
                      width: 90,
                      child: Row(
                        children: [
                          Container(
                            width: 8,
                            height: 8,
                            decoration: BoxDecoration(
                              color: d.statusColor,
                              shape: BoxShape.circle,
                              boxShadow: [
                                BoxShadow(color: d.statusColor.withOpacity(0.5), blurRadius: 6),
                              ],
                            ),
                          ),
                          const SizedBox(width: 8),
                          Text(
                            d.status.toUpperCase(),
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                              color: d.statusColor,
                            ),
                          ),
                        ],
                      ),
                    ),

                    // Device Name & Type
                    Expanded(
                      flex: 3,
                      child: Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(6),
                            decoration: BoxDecoration(
                              color: d.statusColor.withOpacity(0.12),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Icon(d.typeIcon, size: 16, color: d.statusColor),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  d.name,
                                  style: const TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w600,
                                    color: Colors.white,
                                  ),
                                  overflow: TextOverflow.ellipsis,
                                ),
                                if (d.description.isNotEmpty)
                                  Text(
                                    d.description,
                                    style: const TextStyle(fontSize: 11, color: AppTheme.textMuted),
                                    overflow: TextOverflow.ellipsis,
                                  ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),

                    // IP Address & Port
                    Expanded(
                      flex: 2,
                      child: Text(
                        d.ip + (d.checkPort > 0 ? ':${d.checkPort}' : ''),
                        style: const TextStyle(
                          fontSize: 12,
                          fontFamily: 'monospace',
                          color: AppTheme.primaryGlow,
                        ),
                      ),
                    ),

                    // Latency
                    SizedBox(
                      width: 110,
                      child: d.lastAvgTime != null && d.lastAvgTime! > 0
                          ? Text(
                              '${d.lastAvgTime!.toStringAsFixed(1)} ms',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.bold,
                                color: d.lastAvgTime! > 100 ? AppTheme.warning : AppTheme.success,
                              ),
                            )
                          : const Text('—', style: TextStyle(color: AppTheme.textMuted)),
                    ),

                    // Hardware Telemetry
                    Expanded(
                      flex: 2,
                      child: (d.cpuUsage != null || d.memoryUsage != null)
                          ? Row(
                              children: [
                                if (d.cpuUsage != null) ...[
                                  Text(
                                    'CPU ${d.cpuUsage!.toStringAsFixed(0)}%',
                                    style: const TextStyle(fontSize: 11, color: AppTheme.textSecondary),
                                  ),
                                  const SizedBox(width: 8),
                                ],
                                if (d.memoryUsage != null)
                                  Text(
                                    'RAM ${d.memoryUsage!.toStringAsFixed(0)}%',
                                    style: const TextStyle(fontSize: 11, color: AppTheme.textSecondary),
                                  ),
                              ],
                            )
                          : const Text('ICMP Node', style: TextStyle(fontSize: 11, color: AppTheme.textMuted)),
                    ),

                    // Row Actions
                    SizedBox(
                      width: 170,
                      child: Row(
                        children: [
                          IconButton(
                            icon: const Icon(Icons.flash_on, size: 16, color: AppTheme.primaryGlow),
                            tooltip: 'Single Ping',
                            onPressed: () => widget.onPingDevice(d),
                          ),
                          IconButton(
                            icon: const Icon(Icons.show_chart, size: 16, color: AppTheme.info),
                            tooltip: 'Continuous Ping Lab',
                            onPressed: () {
                              if (widget.onOpenContinuousPing != null) {
                                widget.onOpenContinuousPing!(d);
                              }
                            },
                          ),
                          IconButton(
                            icon: const Icon(Icons.edit_outlined, size: 16, color: AppTheme.textSecondary),
                            tooltip: 'Edit Device',
                            onPressed: () {
                              if (widget.onEditDevice != null) {
                                widget.onEditDevice!(d);
                              }
                            },
                          ),
                          IconButton(
                            icon: const Icon(Icons.delete_outline, size: 16, color: AppTheme.danger),
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
    );
  }

  Widget _buildGridView(List<DeviceModel> list) {
    return GridView.builder(
      gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
        maxCrossAxisExtent: 320,
        mainAxisExtent: 200,
        crossAxisSpacing: 16,
        mainAxisSpacing: 16,
      ),
      itemCount: list.length,
      itemBuilder: (context, index) {
        final d = list[index];
        return Card(
          child: InkWell(
            onTap: () => widget.onDeviceSelected(d),
            borderRadius: BorderRadius.circular(12),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: d.statusColor.withOpacity(0.15),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Icon(d.typeIcon, size: 20, color: d.statusColor),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: d.statusColor.withOpacity(0.15),
                          borderRadius: BorderRadius.circular(4),
                          border: Border.all(color: d.statusColor.withOpacity(0.3)),
                        ),
                        child: Text(
                          d.status.toUpperCase(),
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                            color: d.statusColor,
                          ),
                        ),
                      ),
                    ],
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        d.name,
                        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Colors.white),
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 2),
                      Text(
                        d.ip,
                        style: const TextStyle(fontSize: 12, fontFamily: 'monospace', color: AppTheme.primaryGlow),
                      ),
                    ],
                  ),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        d.lastAvgTime != null && d.lastAvgTime! > 0
                            ? '${d.lastAvgTime!.toStringAsFixed(1)} ms'
                            : 'Offline',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                          color: d.statusColor,
                        ),
                      ),
                      Row(
                        children: [
                          IconButton(
                            icon: const Icon(Icons.flash_on, size: 14, color: AppTheme.primaryGlow),
                            tooltip: 'Ping',
                            onPressed: () => widget.onPingDevice(d),
                          ),
                          IconButton(
                            icon: const Icon(Icons.show_chart, size: 14, color: AppTheme.info),
                            tooltip: 'Ping Lab',
                            onPressed: () {
                              if (widget.onOpenContinuousPing != null) {
                                widget.onOpenContinuousPing!(d);
                              }
                            },
                          ),
                          IconButton(
                            icon: const Icon(Icons.delete_outline, size: 14, color: AppTheme.danger),
                            tooltip: 'Delete',
                            onPressed: () => _confirmDelete(d),
                          ),
                        ],
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

const TextStyle _headerStyle = TextStyle(
  fontSize: 11,
  fontWeight: FontWeight.bold,
  color: AppTheme.textSecondary,
  letterSpacing: 0.5,
);

class _FilterChip extends StatelessWidget {
  final String label;
  final Color? color;
  final bool isSelected;
  final VoidCallback onTap;

  const _FilterChip({
    required this.label,
    this.color,
    required this.isSelected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final activeColor = color ?? AppTheme.primary;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected ? activeColor.withOpacity(0.18) : AppTheme.surfaceCard,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(
            color: isSelected ? activeColor : AppTheme.border,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 12,
            fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
            color: isSelected ? Colors.white : AppTheme.textSecondary,
          ),
        ),
      ),
    );
  }
}
