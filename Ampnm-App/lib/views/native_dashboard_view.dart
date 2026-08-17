import 'package:flutter/material.dart';
import '../app_theme.dart';
import '../models/device_model.dart';
import '../models/map_model.dart';
import '../models/status_log_model.dart';

class NativeDashboardView extends StatelessWidget {
  final List<DeviceModel> devices;
  final List<MapModel> maps;
  final List<StatusLogModel> logs;
  final bool isLoading;
  final VoidCallback onRefresh;
  final ValueChanged<DeviceModel> onDeviceSelected;
  final ValueChanged<int> onNavigateToTab;

  const NativeDashboardView({
    super.key,
    required this.devices,
    required this.maps,
    required this.logs,
    required this.isLoading,
    required this.onRefresh,
    required this.onDeviceSelected,
    required this.onNavigateToTab,
  });

  @override
  Widget build(BuildContext context) {
    final hardwareDevices = devices.where((d) => !d.isTextNode).toList();
    final total = hardwareDevices.length;
    final online = hardwareDevices.where((d) => d.isOnline).length;
    final offline = hardwareDevices.where((d) => d.isOffline).length;
    final warning = hardwareDevices.where((d) => d.isWarning).length;
    final critical = hardwareDevices.where((d) => d.isCritical).length;

    double avgLatency = 0;
    final latencyDevices = hardwareDevices.where((d) => d.lastAvgTime != null && d.lastAvgTime! > 0).toList();
    if (latencyDevices.isNotEmpty) {
      avgLatency = latencyDevices.map((d) => d.lastAvgTime!).reduce((a, b) => a + b) / latencyDevices.length;
    }

    final availability = total > 0 ? ((online / total) * 100).toStringAsFixed(1) : '100.0';

    // Device Category Distribution
    final Map<String, int> categoryCounts = {};
    for (final d in hardwareDevices) {
      final t = d.type.toLowerCase();
      categoryCounts[t] = (categoryCounts[t] ?? 0) + 1;
    }

    // Top Slowest / Highest Latency Devices
    final sortedByLatency = List<DeviceModel>.from(latencyDevices)
      ..sort((a, b) => (b.lastAvgTime ?? 0).compareTo(a.lastAvgTime ?? 0));
    final topSlowest = sortedByLatency.take(5).toList();

    return RefreshIndicator(
      onRefresh: () async => onRefresh(),
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Page Header
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Live Network Overview',
                      style: TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                        letterSpacing: -0.5,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Real-time metrics, telemetry, and device diagnostics from AMPNM server',
                      style: TextStyle(fontSize: 13, color: AppTheme.textSecondary.withOpacity(0.8)),
                    ),
                  ],
                ),
                Row(
                  children: [
                    ElevatedButton.icon(
                      onPressed: onRefresh,
                      icon: isLoading
                          ? const SizedBox(
                              width: 14,
                              height: 14,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                            )
                          : const Icon(Icons.refresh, size: 16),
                      label: Text(isLoading ? 'Syncing...' : 'Sync Live Data'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF1E293B),
                        foregroundColor: const Color(0xFF22D3EE),
                        side: const BorderSide(color: Color(0xFF334155)),
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 24),

            // Top Stat Cards Grid
            LayoutBuilder(
              builder: (context, constraints) {
                final isWide = constraints.maxWidth > 900;
                final cardWidth = isWide ? (constraints.maxWidth - 48) / 4 : (constraints.maxWidth - 16) / 2;

                return Wrap(
                  spacing: 16,
                  runSpacing: 16,
                  children: [
                    _StatCard(
                      width: cardWidth,
                      title: 'Total Monitored',
                      value: '$total',
                      subtitle: '$online Online • $offline Offline',
                      icon: Icons.devices,
                      color: const Color(0xFF3B82F6),
                      onTap: () => onNavigateToTab(2), // Devices
                    ),
                    _StatCard(
                      width: cardWidth,
                      title: 'Availability',
                      value: '$availability%',
                      subtitle: '$online of $total active nodes',
                      icon: Icons.check_circle_outline,
                      color: const Color(0xFF10B981),
                      onTap: () => onNavigateToTab(2),
                    ),
                    _StatCard(
                      width: cardWidth,
                      title: 'Alerts / Offline',
                      value: '${offline + warning + critical}',
                      subtitle: (offline + warning + critical) > 0 ? '$offline Down • $warning Warn • $critical Crit' : 'All systems operational',
                      icon: Icons.error_outline,
                      color: (offline + warning + critical) > 0 ? const Color(0xFFEF4444) : const Color(0xFF94A3B8),
                      pulse: offline > 0,
                      onTap: () => onNavigateToTab(3), // Logs
                    ),
                    _StatCard(
                      width: cardWidth,
                      title: 'Avg Latency',
                      value: '${avgLatency.toStringAsFixed(1)} ms',
                      subtitle: 'Across ${latencyDevices.length} responding nodes',
                      icon: Icons.speed,
                      color: avgLatency > 100 ? const Color(0xFFF59E0B) : const Color(0xFF22D3EE),
                      onTap: () => onNavigateToTab(1), // Map
                    ),
                  ],
                );
              },
            ),
            const SizedBox(height: 28),

            // Category Breakdown Row
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: const Color(0xFF0F172A),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: const Color(0xFF334155)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Row(
                        children: [
                          Icon(Icons.pie_chart, size: 16, color: Color(0xFF22D3EE)),
                          SizedBox(width: 8),
                          Text(
                            'Network Device Categories Breakdown',
                            style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Colors.white),
                          ),
                        ],
                      ),
                      Text(
                        '${maps.length} Maps • ${hardwareDevices.length} Endpoints',
                        style: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 12,
                    runSpacing: 10,
                    children: categoryCounts.entries.map((e) {
                      final type = e.key;
                      final count = e.value;
                      final catDevices = hardwareDevices.where((d) => d.type.toLowerCase() == type).toList();
                      final catOnline = catDevices.where((d) => d.isOnline).length;
                      final dummyDev = DeviceModel(id: 0, name: '', ip: '', type: type);

                      return Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          color: const Color(0xFF1E293B),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFF475569)),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(dummyDev.typeIcon, size: 16, color: const Color(0xFF22D3EE)),
                            const SizedBox(width: 8),
                            Text(
                              type.toUpperCase(),
                              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.white),
                            ),
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                              decoration: BoxDecoration(
                                color: catOnline == count ? const Color(0xFF10B981).withOpacity(0.2) : const Color(0xFFEF4444).withOpacity(0.2),
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: Text(
                                '$catOnline/$count',
                                style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.bold,
                                  color: catOnline == count ? const Color(0xFF10B981) : const Color(0xFFEF4444),
                                ),
                              ),
                            ),
                          ],
                        ),
                      );
                    }).toList(),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 28),

            // Latency Leaders & Live Telemetry Stream
            LayoutBuilder(
              builder: (context, constraints) {
                final isWide = constraints.maxWidth > 1000;
                if (isWide) {
                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Left: Live Devices & Latency Leaders
                      Expanded(
                        flex: 3,
                        child: _buildLatencyLeadersCard(topSlowest),
                      ),
                      const SizedBox(width: 24),
                      // Right: Audit Logs & Events
                      Expanded(
                        flex: 2,
                        child: _buildRecentLogsCard(),
                      ),
                    ],
                  );
                } else {
                  return Column(
                    children: [
                      _buildLatencyLeadersCard(topSlowest),
                      const SizedBox(height: 24),
                      _buildRecentLogsCard(),
                    ],
                  );
                }
              },
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLatencyLeadersCard(List<DeviceModel> slowest) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: const Color(0xFF0F172A),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFF334155)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(
                children: [
                  Icon(Icons.network_ping, size: 18, color: Color(0xFF22D3EE)),
                  SizedBox(width: 8),
                  Text(
                    'Node Response Times & Latency',
                    style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Colors.white),
                  ),
                ],
              ),
              TextButton(
                onPressed: () => onNavigateToTab(2),
                child: const Text('View All Nodes', style: TextStyle(color: Color(0xFF22D3EE), fontSize: 12)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (slowest.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 24),
              child: Center(
                child: Text('No active ICMP latency telemetry recorded yet.', style: TextStyle(color: Color(0xFF64748B))),
              ),
            )
          else
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: slowest.length,
              separatorBuilder: (_, __) => const Divider(color: Color(0xFF1E293B), height: 1),
              itemBuilder: (context, idx) {
                final d = slowest[idx];
                final lat = d.lastAvgTime ?? 0;
                Color latColor = const Color(0xFF10B981);
                if (lat > 100) latColor = const Color(0xFFEF4444);
                else if (lat > 40) latColor = const Color(0xFFF59E0B);

                return ListTile(
                  contentPadding: EdgeInsets.zero,
                  dense: true,
                  onTap: () => onDeviceSelected(d),
                  leading: Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: const Color(0xFF1E293B),
                      shape: BoxShape.circle,
                      border: Border.all(color: d.statusColor, width: 1.5),
                    ),
                    child: Icon(d.typeIcon, size: 18, color: d.statusColor),
                  ),
                  title: Text(
                    d.name,
                    style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white, fontSize: 13),
                  ),
                  subtitle: Text(
                    '${d.ip} • TTL: ${d.lastTtl ?? 64}',
                    style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11, fontFamily: 'monospace'),
                  ),
                  trailing: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: latColor.withOpacity(0.15),
                      borderRadius: BorderRadius.circular(6),
                      border: Border.all(color: latColor.withOpacity(0.4)),
                    ),
                    child: Text(
                      '${lat.toStringAsFixed(1)} ms',
                      style: TextStyle(color: latColor, fontWeight: FontWeight.bold, fontSize: 12),
                    ),
                  ),
                );
              },
            ),
        ],
      ),
    );
  }

  Widget _buildRecentLogsCard() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: const Color(0xFF0F172A),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFF334155)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(
                children: [
                  Icon(Icons.history, size: 18, color: Color(0xFF22D3EE)),
                  SizedBox(width: 8),
                  Text(
                    'Live Event Logs',
                    style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Colors.white),
                  ),
                ],
              ),
              TextButton(
                onPressed: () => onNavigateToTab(3),
                child: const Text('View All Logs', style: TextStyle(color: Color(0xFF22D3EE), fontSize: 12)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (logs.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 24),
              child: Center(
                child: Text('No recent network state changes logged.', style: TextStyle(color: Color(0xFF64748B))),
              ),
            )
          else
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: logs.take(6).length,
              separatorBuilder: (_, __) => const Divider(color: Color(0xFF1E293B), height: 1),
              itemBuilder: (context, idx) {
                final log = logs[idx];
                final isDown = log.status.toLowerCase() == 'offline' || log.status.toLowerCase() == 'down';

                return ListTile(
                  contentPadding: EdgeInsets.zero,
                  dense: true,
                  leading: Container(
                    width: 8,
                    height: 8,
                    decoration: BoxDecoration(
                      color: isDown ? const Color(0xFFEF4444) : const Color(0xFF10B981),
                      shape: BoxShape.circle,
                    ),
                  ),
                  title: Text(
                    log.deviceName ?? 'Unknown Node',
                    style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white, fontSize: 12),
                  ),
                  subtitle: Text(
                    log.timestamp,
                    style: const TextStyle(color: Color(0xFF64748B), fontSize: 10),
                  ),
                  trailing: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                      color: isDown ? const Color(0xFFEF4444).withOpacity(0.15) : const Color(0xFF10B981).withOpacity(0.15),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      log.status.toUpperCase(),
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        color: isDown ? const Color(0xFFEF4444) : const Color(0xFF10B981),
                      ),
                    ),
                  ),
                );
              },
            ),
        ],
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final double width;
  final String title;
  final String value;
  final String subtitle;
  final IconData icon;
  final Color color;
  final bool pulse;
  final VoidCallback onTap;

  const _StatCard({
    required this.width,
    required this.title,
    required this.value,
    required this.subtitle,
    required this.icon,
    required this.color,
    this.pulse = false,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        width: width,
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: const Color(0xFF0F172A),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: pulse ? color : const Color(0xFF334155), width: pulse ? 1.5 : 1),
          boxShadow: [
            if (pulse) BoxShadow(color: color.withOpacity(0.2), blurRadius: 10),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  title.toUpperCase(),
                  style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8), letterSpacing: 0.5),
                ),
                Container(
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    color: color.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Icon(icon, size: 16, color: color),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              value,
              style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: Colors.white),
            ),
            const SizedBox(height: 4),
            Text(
              subtitle,
              style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8)),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }
}
