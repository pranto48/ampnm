import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';
import '../app_theme.dart';
import '../models/device_model.dart';
import 'device_icon_widget.dart';

class DeviceDetailsDialog extends StatefulWidget {
  final DeviceModel device;
  final Function(DeviceModel) onPing;
  final Function(DeviceModel)? onContinuousPing;
  final Function(DeviceModel)? onEditDevice;
  final Function(DeviceModel)? onDeleteDevice;

  const DeviceDetailsDialog({
    super.key,
    required this.device,
    required this.onPing,
    this.onContinuousPing,
    this.onEditDevice,
    this.onDeleteDevice,
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

  void _launchSSH() async {
    final ip = widget.device.ip.split(':').first.trim();
    if (ip.isEmpty) return;
    try {
      if (Platform.isWindows) {
        Process.start('powershell.exe', ['-NoExit', '-Command', 'ssh $ip'], mode: ProcessStartMode.detached);
      } else {
        launchUrl(Uri.parse('ssh://$ip'));
      }
    } catch (_) {
      Clipboard.setData(ClipboardData(text: 'ssh $ip'));
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Copied "ssh $ip" to clipboard!'), backgroundColor: AppTheme.primary),
        );
      }
    }
  }

  void _launchWinbox() async {
    final ip = widget.device.ip.split(':').first.trim();
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
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Winbox requested! Copied IP $ip to clipboard.'), backgroundColor: AppTheme.primary),
      );
    }
  }

  void _launchRDP() async {
    final ip = widget.device.ip.split(':').first.trim();
    if (ip.isEmpty) return;
    try {
      if (Platform.isWindows) {
        Process.start('mstsc.exe', ['/v:$ip'], mode: ProcessStartMode.detached);
      }
    } catch (_) {}
  }

  void _launchWeb() {
    final ip = widget.device.ip;
    if (ip.isEmpty) return;
    final port = widget.device.checkPort;
    final protocol = (port == 443 || port == 8443) ? 'https' : 'http';
    final portSuffix = (port > 0 && port != 80 && port != 443) ? ':$port' : '';
    final uri = Uri.parse('$protocol://$ip$portSuffix');
    launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  @override
  Widget build(BuildContext context) {
    final d = widget.device;

    return Dialog(
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
            // Header Row
            Row(
              children: [
                Container(
                  width: 50,
                  height: 50,
                  decoration: BoxDecoration(
                    color: d.statusColor.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: d.statusColor.withOpacity(0.4), width: 1.5),
                  ),
                  child: Center(
                    child: DeviceIconWidget(device: d, size: 32),
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
                        style: const TextStyle(fontSize: 12, color: Color(0xFF22D3EE), fontFamily: 'monospace'),
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
            const SizedBox(height: 18),
            const Divider(color: Color(0xFF1E293B)),
            const SizedBox(height: 14),

            // Quick Diagnostic NOC Launchers Row
            const Text(
              'QUICK NOC DIAGNOSTIC LAUNCHERS',
              style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8), letterSpacing: 0.5),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _ToolButton(
                  icon: Icons.open_in_browser,
                  label: 'Web GUI',
                  color: const Color(0xFF38BDF8),
                  onTap: _launchWeb,
                ),
                if (d.isMikrotikOrRouter)
                  _ToolButton(
                    icon: Icons.router_rounded,
                    label: 'Winbox',
                    color: const Color(0xFF22D3EE),
                    onTap: _launchWinbox,
                  ),
                if (d.isSshCapable)
                  _ToolButton(
                    icon: Icons.terminal_rounded,
                    label: 'SSH Terminal',
                    color: const Color(0xFFA78BFA),
                    onTap: _launchSSH,
                  ),
                if (d.isRdpCapable)
                  _ToolButton(
                    icon: Icons.desktop_windows_rounded,
                    label: 'Remote Desktop',
                    color: const Color(0xFFF59E0B),
                    onTap: _launchRDP,
                  ),
                if (widget.onContinuousPing != null)
                  _ToolButton(
                    icon: Icons.timeline_rounded,
                    label: 'Continuous Ping',
                    color: const Color(0xFF10B981),
                    onTap: () {
                      Navigator.of(context).pop();
                      widget.onContinuousPing!(d);
                    },
                  ),
              ],
            ),
            const SizedBox(height: 18),

            // Key-Value Attributes Grid
            Wrap(
              spacing: 20,
              runSpacing: 14,
              children: [
                _DetailItem(label: 'IP Address', value: d.ip, isMono: true),
                _DetailItem(label: 'Check Port', value: d.checkPort > 0 ? '${d.checkPort}' : 'Default (ICMP Ping)'),
                _DetailItem(
                  label: 'Last Latency',
                  value: d.lastAvgTime != null && d.lastAvgTime! > 0 ? '${d.lastAvgTime!.toStringAsFixed(1)} ms' : 'Offline / No response',
                  valueColor: d.lastAvgTime != null && d.lastAvgTime! > 0 ? const Color(0xFF10B981) : const Color(0xFFEF4444),
                ),
                _DetailItem(label: 'TTL & Jitter', value: 'TTL: ${d.lastTtl ?? 64} • Latency Tier: ${d.isOnline ? "Normal" : "Down"}'),
                _DetailItem(label: 'Ping Interval', value: '${d.pingInterval} seconds'),
                _DetailItem(label: 'Category / Subchoice', value: d.subchoice.isNotEmpty ? d.subchoice : d.type),
              ],
            ),

            if (d.description.isNotEmpty) ...[
              const SizedBox(height: 14),
              const Text('Description / Notes', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8), fontWeight: FontWeight.bold)),
              const SizedBox(height: 4),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFF1E293B),
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: const Color(0xFF334155)),
                ),
                child: Text(d.description, style: const TextStyle(fontSize: 12, color: Colors.white)),
              ),
            ],

            // Host Telemetry Metrics (if available)
            if (d.cpuUsage != null || d.memoryUsage != null || d.diskUsage != null) ...[
              const SizedBox(height: 16),
              const Text('Host Telemetry (AMPNM Host Agent)', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8), fontWeight: FontWeight.bold)),
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
                  color: const Color(0xFF0284C7).withOpacity(0.15),
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: const Color(0xFF0284C7).withOpacity(0.4)),
                ),
                child: Text(
                  _pingResultMsg!,
                  style: const TextStyle(fontSize: 11, color: Color(0xFF38BDF8)),
                  textAlign: TextAlign.center,
                ),
              ),
            ],

            const SizedBox(height: 22),

            // Footer Actions
            Row(
              children: [
                if (widget.onEditDevice != null)
                  OutlinedButton.icon(
                    onPressed: () {
                      Navigator.of(context).pop();
                      widget.onEditDevice!(d);
                    },
                    icon: const Icon(Icons.edit, size: 14),
                    label: const Text('Edit Device'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: const Color(0xFF22D3EE),
                      side: const BorderSide(color: Color(0xFF0891B2)),
                    ),
                  ),
                const Spacer(),
                ElevatedButton.icon(
                  onPressed: _isPinging ? null : _handlePing,
                  icon: _isPinging
                      ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Icon(Icons.flash_on, size: 16),
                  label: Text(_isPinging ? 'Pinging...' : 'Ping Now'),
                  style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF0891B2)),
                ),
                const SizedBox(width: 8),
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

class _ToolButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _ToolButton({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: color.withOpacity(0.12),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: color.withOpacity(0.35)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 14, color: color),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: color),
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
      width: 240,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8))),
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
    final color = value > 85 ? const Color(0xFFEF4444) : (value > 65 ? const Color(0xFFF59E0B) : const Color(0xFF10B981));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(label, style: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8))),
            Text('${value.toStringAsFixed(0)}%', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: color)),
          ],
        ),
        const SizedBox(height: 4),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: LinearProgressIndicator(
            value: clamped,
            minHeight: 6,
            backgroundColor: const Color(0xFF1E293B),
            valueColor: AlwaysStoppedAnimation<Color>(color),
          ),
        ),
      ],
    );
  }
}
