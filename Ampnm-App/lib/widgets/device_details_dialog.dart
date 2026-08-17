import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../app_theme.dart';
import '../models/device_model.dart';

class DeviceDetailsDialog extends StatefulWidget {
  final DeviceModel device;
  final Function(DeviceModel) onPing;

  const DeviceDetailsDialog({
    super.key,
    required this.device,
    required this.onPing,
  });

  @override
  State<DeviceDetailsDialog> createState() => _DeviceDetailsDialogState();
}

class _DeviceDetailsDialogState extends State<DeviceDetailsDialog> {
  bool _isPinging = false;
  String? _pingResultMsg;

  Future<void> _handlePing() async {
    setState(() {
      _isPinging = true;
      _pingResultMsg = null;
    });

    try {
      final res = await widget.onPing(widget.device);
      setState(() {
        _isPinging = false;
        if (res != null && res is Map) {
          _pingResultMsg = res['message'] ?? (res['status'] == 'success' ? 'Ping Successful' : 'Ping Failed');
        } else {
          _pingResultMsg = 'Ping request sent.';
        }
      });
    } catch (e) {
      setState(() {
        _isPinging = false;
        _pingResultMsg = 'Ping error: $e';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final d = widget.device;

    return Dialog(
      backgroundColor: AppTheme.surfaceCard,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: AppTheme.border),
      ),
      child: Container(
        width: 520,
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header Row
            Row(
              children: [
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: d.statusColor.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: d.statusColor.withOpacity(0.4)),
                  ),
                  child: Center(
                    child: Icon(d.typeIcon, size: 24, color: d.statusColor),
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        d.name,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '${d.type.toUpperCase()} • ${d.ip}${d.checkPort > 0 ? ':${d.checkPort}' : ''}',
                        style: const TextStyle(fontSize: 12, color: AppTheme.primaryGlow, fontFamily: 'monospace'),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: d.statusColor.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: d.statusColor.withOpacity(0.4)),
                  ),
                  child: Text(
                    d.status.toUpperCase(),
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      color: d.statusColor,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),
            const Divider(color: AppTheme.border),
            const SizedBox(height: 16),

            // Key-Value Attributes Grid
            Wrap(
              spacing: 20,
              runSpacing: 14,
              children: [
                _DetailItem(label: 'IP Address', value: d.ip, isMono: true),
                _DetailItem(label: 'Check Port', value: d.checkPort > 0 ? '${d.checkPort}' : 'Default (ICMP)'),
                _DetailItem(
                  label: 'Last Latency',
                  value: d.lastAvgTime != null && d.lastAvgTime! > 0 ? '${d.lastAvgTime!.toStringAsFixed(1)} ms' : 'Offline / No response',
                  valueColor: d.lastAvgTime != null && d.lastAvgTime! > 0 ? AppTheme.success : AppTheme.danger,
                ),
                _DetailItem(label: 'Last Seen', value: d.lastSeen ?? 'N/A'),
                _DetailItem(label: 'Ping Interval', value: '${d.pingInterval}s'),
                _DetailItem(label: 'Category / Subchoice', value: d.subchoice.isNotEmpty ? d.subchoice : d.type),
              ],
            ),

            if (d.description.isNotEmpty) ...[
              const SizedBox(height: 16),
              const Text('Description / Notes', style: TextStyle(fontSize: 11, color: AppTheme.textSecondary, fontWeight: FontWeight.bold)),
              const SizedBox(height: 4),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppTheme.surface,
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: AppTheme.border),
                ),
                child: Text(d.description, style: const TextStyle(fontSize: 12, color: AppTheme.textPrimary)),
              ),
            ],

            // Host Telemetry Metrics (if available)
            if (d.cpuUsage != null || d.memoryUsage != null || d.diskUsage != null) ...[
              const SizedBox(height: 18),
              const Text('Host Telemetry (Host Agent)', style: TextStyle(fontSize: 11, color: AppTheme.textSecondary, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              Row(
                children: [
                  if (d.cpuUsage != null)
                    Expanded(child: _TelemetryBar(label: 'CPU', value: d.cpuUsage!)),
                  if (d.cpuUsage != null && d.memoryUsage != null)
                    const SizedBox(width: 12),
                  if (d.memoryUsage != null)
                    Expanded(child: _TelemetryBar(label: 'RAM', value: d.memoryUsage!)),
                  if (d.diskUsage != null) ...[
                    const SizedBox(width: 12),
                    Expanded(child: _TelemetryBar(label: 'Disk', value: d.diskUsage!)),
                  ],
                ],
              ),
            ],

            if (_pingResultMsg != null) ...[
              const SizedBox(height: 14),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: AppTheme.primary.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: AppTheme.primary.withOpacity(0.4)),
                ),
                child: Text(
                  _pingResultMsg!,
                  style: const TextStyle(fontSize: 11, color: AppTheme.primaryGlow),
                  textAlign: TextAlign.center,
                ),
              ),
            ],

            const SizedBox(height: 24),

            // Footer Actions
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                OutlinedButton.icon(
                  onPressed: () {
                    final uri = Uri.parse('http://${d.ip}${d.checkPort > 0 ? ':${d.checkPort}' : ''}');
                    launchUrl(uri, mode: LaunchMode.externalApplication);
                  },
                  icon: const Icon(Icons.open_in_browser, size: 16),
                  label: const Text('Open Web GUI'),
                ),
                const SizedBox(width: 10),
                ElevatedButton.icon(
                  onPressed: _isPinging ? null : _handlePing,
                  icon: _isPinging
                      ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Icon(Icons.flash_on, size: 16),
                  label: Text(_isPinging ? 'Pinging...' : 'Ping Device Now'),
                ),
                const SizedBox(width: 10),
                TextButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Close'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _DetailItem extends StatelessWidget {
  final String label;
  final String value;
  final bool isMono;
  final Color? valueColor;

  const _DetailItem({
    required this.label,
    required this.value,
    this.isMono = false,
    this.valueColor,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 220,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(fontSize: 11, color: AppTheme.textMuted)),
          const SizedBox(height: 2),
          Text(
            value,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              fontFamily: isMono ? 'monospace' : null,
              color: valueColor ?? Colors.white,
            ),
          ),
        ],
      ),
    );
  }
}

class _TelemetryBar extends StatelessWidget {
  final String label;
  final double value;

  const _TelemetryBar({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    final clamped = (value / 100).clamp(0.0, 1.0);
    final color = value > 85 ? AppTheme.danger : (value > 65 ? AppTheme.warning : AppTheme.success);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(label, style: const TextStyle(fontSize: 10, color: AppTheme.textSecondary)),
            Text('${value.toStringAsFixed(0)}%', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: color)),
          ],
        ),
        const SizedBox(height: 4),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: LinearProgressIndicator(
            value: clamped,
            minHeight: 6,
            backgroundColor: AppTheme.surface,
            valueColor: AlwaysStoppedAnimation<Color>(color),
          ),
        ),
      ],
    );
  }
}
