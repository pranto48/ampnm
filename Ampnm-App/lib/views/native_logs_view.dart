import 'package:flutter/material.dart';
import '../app_theme.dart';
import '../models/status_log_model.dart';

class NativeLogsView extends StatefulWidget {
  final List<StatusLogModel> logs;
  final bool isLoading;
  final VoidCallback onRefresh;

  const NativeLogsView({
    super.key,
    required this.logs,
    required this.isLoading,
    required this.onRefresh,
  });

  @override
  State<NativeLogsView> createState() => _NativeLogsViewState();
}

class _NativeLogsViewState extends State<NativeLogsView> {
  String _searchQuery = '';
  String _severityFilter = 'all';

  @override
  Widget build(BuildContext context) {
    final filtered = widget.logs.where((log) {
      if (_severityFilter == 'offline' && !log.status.toLowerCase().contains('offline') && !log.status.toLowerCase().contains('down')) {
        return false;
      }
      if (_severityFilter == 'online' && !log.status.toLowerCase().contains('online') && !log.status.toLowerCase().contains('up')) {
        return false;
      }
      if (_severityFilter == 'warning' && !log.status.toLowerCase().contains('warning') && !log.status.toLowerCase().contains('degraded')) {
        return false;
      }
      if (_searchQuery.isNotEmpty) {
        final q = _searchQuery.toLowerCase();
        return log.message.toLowerCase().contains(q) ||
            (log.deviceName != null && log.deviceName!.toLowerCase().contains(q)) ||
            (log.deviceIp != null && log.deviceIp!.contains(q));
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
                    'Audit & Status Log Stream',
                    style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                      color: Colors.white,
                      letterSpacing: -0.5,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Real-time event tracking and alert history from server',
                    style: const TextStyle(fontSize: 13, color: AppTheme.textSecondary),
                  ),
                ],
              ),
              ElevatedButton.icon(
                onPressed: widget.onRefresh,
                icon: widget.isLoading
                    ? const SizedBox(
                        width: 14,
                        height: 14,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      )
                    : const Icon(Icons.refresh, size: 16),
                label: const Text('Refresh Logs'),
              ),
            ],
          ),
          const SizedBox(height: 20),

          // Search & Filter Row
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
                      hintText: 'Search logs by event message, device name or IP...',
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
              _LogFilterChip(
                label: 'All Logs (${widget.logs.length})',
                isSelected: _severityFilter == 'all',
                onTap: () => setState(() => _severityFilter = 'all'),
              ),
              const SizedBox(width: 8),
              _LogFilterChip(
                label: 'Offline / Down',
                color: AppTheme.danger,
                isSelected: _severityFilter == 'offline',
                onTap: () => setState(() => _severityFilter = 'offline'),
              ),
              const SizedBox(width: 8),
              _LogFilterChip(
                label: 'Online / Recovered',
                color: AppTheme.success,
                isSelected: _severityFilter == 'online',
                onTap: () => setState(() => _severityFilter = 'online'),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Log List Card
          Expanded(
            child: Card(
              child: ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: filtered.isEmpty
                    ? const Center(
                        child: Text(
                          'No event logs matching filter criteria.',
                          style: TextStyle(color: AppTheme.textMuted),
                        ),
                      )
                    : ListView.separated(
                        itemCount: filtered.length,
                        separatorBuilder: (_, __) => const Divider(color: AppTheme.border, height: 1),
                        itemBuilder: (context, index) {
                          final log = filtered[index];
                          return Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Container(
                                  width: 10,
                                  height: 10,
                                  margin: const EdgeInsets.only(top: 4),
                                  decoration: BoxDecoration(
                                    color: log.statusColor,
                                    shape: BoxShape.circle,
                                    boxShadow: [
                                      BoxShadow(color: log.statusColor.withOpacity(0.5), blurRadius: 6),
                                    ],
                                  ),
                                ),
                                const SizedBox(width: 14),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                        children: [
                                          Row(
                                            children: [
                                              Text(
                                                log.deviceName ?? log.deviceIp ?? 'Server Event',
                                                style: const TextStyle(
                                                  fontSize: 13,
                                                  fontWeight: FontWeight.bold,
                                                  color: Colors.white,
                                                ),
                                              ),
                                              const SizedBox(width: 8),
                                              Container(
                                                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                                decoration: BoxDecoration(
                                                  color: log.statusColor.withOpacity(0.15),
                                                  borderRadius: BorderRadius.circular(4),
                                                ),
                                                child: Text(
                                                  log.status.toUpperCase(),
                                                  style: TextStyle(
                                                    fontSize: 9,
                                                    fontWeight: FontWeight.bold,
                                                    color: log.statusColor,
                                                  ),
                                                ),
                                              ),
                                            ],
                                          ),
                                          Text(
                                            log.timestamp,
                                            style: const TextStyle(fontSize: 11, color: AppTheme.textMuted),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        log.message,
                                        style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _LogFilterChip extends StatelessWidget {
  final String label;
  final Color? color;
  final bool isSelected;
  final VoidCallback onTap;

  const _LogFilterChip({
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
