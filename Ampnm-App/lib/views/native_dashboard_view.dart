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
    final total = devices.length;
    final online = devices.where((d) => d.isOnline).length;
    final offline = devices.where((d) => d.isOffline).length;
    final warning = devices.where((d) => d.isWarning).length;

    double avgLatency = 0;
    final latencyDevices = devices.where((d) => d.lastAvgTime != null && d.lastAvgTime! > 0).toList();
    if (latencyDevices.isNotEmpty) {
      avgLatency = latencyDevices.map((d) => d.lastAvgTime!).reduce((a, b) => a + b) / latencyDevices.length;
    }

    final availability = total > 0 ? ((online / total) * 100).toStringAsFixed(1) : '100.0';

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
                      'Real-time metrics and live telemetry from AMPNM server',
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
                        backgroundColor: AppTheme.surfaceCard,
                        foregroundColor: AppTheme.primaryGlow,
                        side: const BorderSide(color: AppTheme.border),
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
                      color: AppTheme.primary,
                      onTap: () => onNavigateToTab(2), // Devices
                    ),
                    _StatCard(
                      width: cardWidth,
                      title: 'Availability',
                      value: '$availability%',
                      subtitle: '$online of $total active nodes',
                      icon: Icons.check_circle_outline,
                      color: AppTheme.success,
                      onTap: () => onNavigateToTab(2),
                    ),
                    _StatCard(
                      width: cardWidth,
                      title: 'Alerts / Offline',
                      value: '$offline',
                      subtitle: offline > 0 ? 'Action required immediately' : 'All systems operational',
                      icon: Icons.error_outline,
                      color: offline > 0 ? AppTheme.danger : AppTheme.textMuted,
                      pulse: offline > 0,
                      onTap: () => onNavigateToTab(3), // Logs
                    ),
                    _StatCard(
                      width: cardWidth,
                      title: 'Avg Latency',
                      value: '${avgLatency.toStringAsFixed(1)} ms',
                      subtitle: 'Across ${latencyDevices.length} responding devices',
                      icon: Icons.speed,
                      color: avgLatency > 100 ? AppTheme.warning : AppTheme.info,
                      onTap: () => onNavigateToTab(1), // Map
                    ),
                  ],
                );
              },
            ),
            const SizedBox(height: 32),

            // Middle Section: Live Devices Grid & Recent Alerts
            LayoutBuilder(
              builder: (context, constraints) {
                final isWide = constraints.maxWidth > 1000;
                if (isWide) {
                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Left: Live Devices Grid
                      Expanded(
                        flex: 3,
                        child: _LiveDevicesCard(
                          devices: devices,
                          onDeviceSelected: onDeviceSelected,
                          onViewAll: () => onNavigateToTab(2),
                        ),
                      ),
                      const SizedBox(width: 20),
                      // Right: Recent Alerts
                      Expanded(
                        flex: 2,
                        child: _RecentAlertsCard(
                          logs: logs,
                          onViewAll: () => onNavigateToTab(3),
                        ),
                      ),
                    ],
                  );
                } else {
                  return Column(
                    children: [
                      _LiveDevicesCard(
                        devices: devices,
                        onDeviceSelected: onDeviceSelected,
                        onViewAll: () => onNavigateToTab(2),
                      ),
                      const SizedBox(height: 20),
                      _RecentAlertsCard(
                        logs: logs,
                        onViewAll: () => onNavigateToTab(3),
                      ),
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
    return SizedBox(
      width: width,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Card(
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: AppTheme.textSecondary,
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: color.withOpacity(0.15),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Icon(icon, size: 18, color: color),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  value,
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.w900,
                    color: Colors.white,
                    letterSpacing: -0.5,
                    shadows: pulse
                        ? [
                            Shadow(
                              color: color.withOpacity(0.6),
                              blurRadius: 12,
                            ),
                          ]
                        : null,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  subtitle,
                  style: TextStyle(fontSize: 12, color: AppTheme.textMuted),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _LiveDevicesCard extends StatelessWidget {
  final List<DeviceModel> devices;
  final ValueChanged<DeviceModel> onDeviceSelected;
  final VoidCallback onViewAll;

  const _LiveDevicesCard({
    required this.devices,
    required this.onDeviceSelected,
    required this.onViewAll,
  });

  @override
  Widget build(BuildContext context) {
    final previewList = devices.take(8).toList();

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Row(
                  children: [
                    Icon(Icons.hub_outlined, color: AppTheme.primary, size: 20),
                    SizedBox(width: 8),
                    Text(
                      'Live Monitored Devices',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                  ],
                ),
                TextButton(
                  onPressed: onViewAll,
                  child: const Text('View All Devices →', style: TextStyle(fontSize: 12)),
                ),
              ],
            ),
            const SizedBox(height: 16),
            if (devices.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 32),
                child: Center(
                  child: Text(
                    'No devices found on server.',
                    style: TextStyle(color: AppTheme.textMuted),
                  ),
                ),
              )
            else
              ListView.separated(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: previewList.length,
                separatorBuilder: (_, __) => const Divider(color: AppTheme.border, height: 1),
                itemBuilder: (context, index) {
                  final d = previewList[index];
                  return ListTile(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
                    onTap: () => onDeviceSelected(d),
                    leading: Container(
                      width: 36,
                      height: 36,
                      decoration: BoxDecoration(
                        color: d.statusColor.withOpacity(0.12),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: d.statusColor.withOpacity(0.3)),
                      ),
                      child: Center(
                        child: Icon(d.typeIcon, size: 18, color: d.statusColor),
                      ),
                    ),
                    title: Row(
                      children: [
                        Text(
                          d.name,
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: AppTheme.textPrimary,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: d.statusColor.withOpacity(0.15),
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: Text(
                            d.status.toUpperCase(),
                            style: TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.bold,
                              color: d.statusColor,
                            ),
                          ),
                        ),
                      ],
                    ),
                    subtitle: Text(
                      '${d.ip} ${d.description.isNotEmpty ? '• ${d.description}' : ''}',
                      style: const TextStyle(fontSize: 11, color: AppTheme.textSecondary),
                    ),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if (d.lastAvgTime != null && d.lastAvgTime! > 0)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                            decoration: BoxDecoration(
                              color: AppTheme.surface,
                              borderRadius: BorderRadius.circular(6),
                              border: Border.all(color: AppTheme.border),
                            ),
                            child: Text(
                              '${d.lastAvgTime!.toStringAsFixed(1)} ms',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                                color: d.lastAvgTime! > 100 ? AppTheme.warning : AppTheme.success,
                              ),
                            ),
                          ),
                        const SizedBox(width: 6),
                        const Icon(Icons.chevron_right, size: 16, color: AppTheme.textMuted),
                      ],
                    ),
                  );
                },
              ),
          ],
        ),
      ),
    );
  }
}

class _RecentAlertsCard extends StatelessWidget {
  final List<StatusLogModel> logs;
  final VoidCallback onViewAll;

  const _RecentAlertsCard({
    required this.logs,
    required this.onViewAll,
  });

  @override
  Widget build(BuildContext context) {
    final previewLogs = logs.take(6).toList();

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Row(
                  children: [
                    Icon(Icons.notifications_active_outlined, color: AppTheme.warning, size: 20),
                    SizedBox(width: 8),
                    Text(
                      'Live Event Feed',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                  ],
                ),
                TextButton(
                  onPressed: onViewAll,
                  child: const Text('View All →', style: TextStyle(fontSize: 12)),
                ),
              ],
            ),
            const SizedBox(height: 16),
            if (logs.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 32),
                child: Center(
                  child: Text(
                    'No recent alerts recorded.',
                    style: TextStyle(color: AppTheme.textMuted),
                  ),
                ),
              )
            else
              ListView.separated(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: previewLogs.length,
                separatorBuilder: (_, __) => const Divider(color: AppTheme.border, height: 1),
                itemBuilder: (context, index) {
                  final log = previewLogs[index];
                  return Padding(
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: 8,
                          height: 8,
                          margin: const EdgeInsets.only(top: 5),
                          decoration: BoxDecoration(
                            color: log.statusColor,
                            shape: BoxShape.circle,
                            boxShadow: [
                              BoxShadow(color: log.statusColor.withOpacity(0.5), blurRadius: 6),
                            ],
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(
                                    log.deviceName ?? log.deviceIp ?? 'System Event',
                                    style: const TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.bold,
                                      color: AppTheme.textPrimary,
                                    ),
                                  ),
                                  Text(
                                    log.timestamp.length > 16
                                        ? log.timestamp.substring(11, 16)
                                        : log.timestamp,
                                    style: const TextStyle(fontSize: 10, color: AppTheme.textMuted),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 3),
                              Text(
                                log.message,
                                style: const TextStyle(fontSize: 11, color: AppTheme.textSecondary),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  );
                },
              ),
          ],
        ),
      ),
    );
  }
}
