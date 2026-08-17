import 'dart:async';
import 'package:flutter/material.dart';
import '../app_theme.dart';
import '../models/device_model.dart';
import '../services/server_service.dart';

class ContinuousPingDialog extends StatefulWidget {
  final DeviceModel device;
  final String serverUrl;
  final String sessionCookie;

  const ContinuousPingDialog({
    super.key,
    required this.device,
    required this.serverUrl,
    required this.sessionCookie,
  });

  @override
  State<ContinuousPingDialog> createState() => _ContinuousPingDialogState();
}

class _ContinuousPingDialogState extends State<ContinuousPingDialog> {
  Timer? _pingTimer;
  bool _isRunning = true;
  final List<double> _latencyHistory = [];
  final List<String> _logEntries = [];
  final ScrollController _scrollController = ScrollController();

  int _transmitted = 0;
  int _received = 0;
  int _lost = 0;
  double _minLatency = double.infinity;
  double _maxLatency = 0;

  @override
  void initState() {
    super.initState();
    _startPingLoop();
  }

  @override
  void dispose() {
    _pingTimer?.cancel();
    _scrollController.dispose();
    super.dispose();
  }

  void _startPingLoop() {
    _pingTimer?.cancel();
    _sendSinglePing();
    _pingTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (_isRunning) _sendSinglePing();
    });
  }

  Future<void> _sendSinglePing() async {
    final seq = _transmitted + 1;
    setState(() => _transmitted = seq);

    final stopwatch = Stopwatch()..start();
    try {
      final res = await ServerService.pingDevice(
        serverUrl: widget.serverUrl,
        sessionCookie: widget.sessionCookie,
        deviceId: widget.device.id,
        ip: widget.device.ip,
        port: widget.device.checkPort,
      );
      stopwatch.stop();

      final elapsedMs = stopwatch.elapsedMilliseconds.toDouble();
      final isSuccess = res != null && res['status'] == 'success';

      if (isSuccess) {
        final serverTime = double.tryParse(res['avg_time']?.toString() ?? '') ?? elapsedMs;
        _received++;
        if (serverTime < _minLatency) _minLatency = serverTime;
        if (serverTime > _maxLatency) _maxLatency = serverTime;

        _latencyHistory.add(serverTime);
        if (_latencyHistory.length > 50) _latencyHistory.removeAt(0);

        _logEntries.add('Reply from ${widget.device.ip}: bytes=32 time=${serverTime.toStringAsFixed(1)}ms TTL=64 seq=$seq');
      } else {
        _lost++;
        _latencyHistory.add(-1); // Loss
        if (_latencyHistory.length > 50) _latencyHistory.removeAt(0);
        _logEntries.add('Request timed out for ${widget.device.ip} seq=$seq');
      }
    } catch (_) {
      _lost++;
      _latencyHistory.add(-1);
      if (_latencyHistory.length > 50) _latencyHistory.removeAt(0);
      _logEntries.add('Destination host unreachable: ${widget.device.ip} seq=$seq');
    }

    if (mounted) {
      setState(() {});
      if (_scrollController.hasClients) {
        _scrollController.jumpTo(_scrollController.position.maxScrollExtent);
      }
    }
  }

  void _togglePause() {
    setState(() => _isRunning = !_isRunning);
  }

  void _clearStats() {
    setState(() {
      _transmitted = 0;
      _received = 0;
      _lost = 0;
      _minLatency = double.infinity;
      _maxLatency = 0;
      _latencyHistory.clear();
      _logEntries.clear();
    });
  }

  @override
  Widget build(BuildContext context) {
    final lossPercent = _transmitted > 0 ? ((_lost / _transmitted) * 100).toStringAsFixed(1) : '0.0';
    final avgLatency = _received > 0
        ? (_latencyHistory.where((l) => l > 0).reduce((a, b) => a + b) / _received).toStringAsFixed(1)
        : '0.0';

    return Dialog(
      backgroundColor: AppTheme.surfaceCard,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: AppTheme.border),
      ),
      child: Container(
        width: 680,
        height: 580,
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: AppTheme.primary.withOpacity(0.15),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(Icons.show_chart, color: AppTheme.primaryGlow, size: 22),
                    ),
                    const SizedBox(width: 12),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Text(
                              'Live Ping Monitor: ${widget.device.name}',
                              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white),
                            ),
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: _isRunning ? AppTheme.success.withOpacity(0.15) : AppTheme.warning.withOpacity(0.15),
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: Text(
                                _isRunning ? 'LIVE 1s' : 'PAUSED',
                                style: TextStyle(
                                  fontSize: 9,
                                  fontWeight: FontWeight.bold,
                                  color: _isRunning ? AppTheme.success : AppTheme.warning,
                                ),
                              ),
                            ),
                          ],
                        ),
                        Text(
                          'Host: ${widget.device.ip} (Check Port: ${widget.device.checkPort > 0 ? widget.device.checkPort : 'ICMP'})',
                          style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary, fontFamily: 'monospace'),
                        ),
                      ],
                    ),
                  ],
                ),
                IconButton(
                  icon: const Icon(Icons.close, color: AppTheme.textMuted, size: 20),
                  onPressed: () => Navigator.of(context).pop(),
                ),
              ],
            ),
            const SizedBox(height: 16),
            const Divider(color: AppTheme.border),
            const SizedBox(height: 12),

            // Real-Time Stats Grid
            Row(
              children: [
                _buildStatTile('Transmitted', '$_transmitted', Colors.white),
                _buildStatTile('Received', '$_received', AppTheme.success),
                _buildStatTile('Packet Loss', '$lossPercent%', double.parse(lossPercent) > 0 ? AppTheme.danger : AppTheme.textSecondary),
                _buildStatTile('Min / Max', '${_minLatency == double.infinity ? '0' : _minLatency.toStringAsFixed(0)} / ${_maxLatency.toStringAsFixed(0)} ms', AppTheme.info),
                _buildStatTile('Avg Latency', '$avgLatency ms', AppTheme.primaryGlow),
              ],
            ),
            const SizedBox(height: 16),

            // Live Latency Sparkline Canvas Chart
            Container(
              height: 120,
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFF070D18),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: AppTheme.border),
              ),
              child: CustomPaint(
                painter: _PingChartPainter(history: _latencyHistory),
              ),
            ),
            const SizedBox(height: 14),

            // Live Console Log Terminal
            const Text('Real-time ICMP Telemetry Stream', style: TextStyle(fontSize: 11, color: AppTheme.textSecondary, fontWeight: FontWeight.bold)),
            const SizedBox(height: 6),
            Expanded(
              child: Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFF020617),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: AppTheme.border),
                ),
                child: ListView.builder(
                  controller: _scrollController,
                  itemCount: _logEntries.length,
                  itemBuilder: (context, idx) {
                    final line = _logEntries[idx];
                    final isTimeout = line.contains('timed out') || line.contains('unreachable');
                    return Text(
                      line,
                      style: TextStyle(
                        fontFamily: 'monospace',
                        fontSize: 11,
                        color: isTimeout ? AppTheme.danger : const Color(0xFF22C55E),
                      ),
                    );
                  },
                ),
              ),
            ),
            const SizedBox(height: 14),

            // Action Buttons
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                TextButton.icon(
                  onPressed: _clearStats,
                  icon: const Icon(Icons.cleaning_services, size: 14),
                  label: const Text('Clear Stats', style: TextStyle(fontSize: 12)),
                ),
                Row(
                  children: [
                    ElevatedButton.icon(
                      onPressed: _togglePause,
                      icon: Icon(_isRunning ? Icons.pause : Icons.play_arrow, size: 16),
                      label: Text(_isRunning ? 'Pause Monitor' : 'Resume Monitor'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _isRunning ? AppTheme.surface : AppTheme.primary,
                      ),
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
          ],
        ),
      ),
    );
  }

  Widget _buildStatTile(String label, String value, Color valColor) {
    return Expanded(
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 4),
        padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 10),
        decoration: BoxDecoration(
          color: AppTheme.surface,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: AppTheme.border.withOpacity(0.5)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: const TextStyle(fontSize: 10, color: AppTheme.textMuted)),
            const SizedBox(height: 2),
            Text(
              value,
              style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: valColor),
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }
}

class _PingChartPainter extends CustomPainter {
  final List<double> history;

  _PingChartPainter({required this.history});

  @override
  void paint(Canvas canvas, Size size) {
    if (history.isEmpty) return;

    final linePaint = Paint()
      ..color = AppTheme.primaryGlow
      ..strokeWidth = 2.0
      ..style = PaintingStyle.stroke;

    final lossPaint = Paint()
      ..color = AppTheme.danger
      ..strokeWidth = 3.0;

    double maxVal = 100.0;
    for (final val in history) {
      if (val > maxVal) maxVal = val;
    }

    final stepX = size.width / (history.length > 1 ? history.length - 1 : 1);
    final path = Path();
    bool first = true;

    for (int i = 0; i < history.length; i++) {
      final val = history[i];
      final x = i * stepX;

      if (val < 0) {
        // Lost packet
        canvas.drawCircle(Offset(x, size.height - 10), 3, lossPaint);
        continue;
      }

      final normalizedY = size.height - ((val / maxVal) * (size.height - 20)) - 10;

      if (first) {
        path.moveTo(x, normalizedY);
        first = false;
      } else {
        path.lineTo(x, normalizedY);
      }

      // Draw point circle
      canvas.drawCircle(
        Offset(x, normalizedY),
        2,
        Paint()..color = Colors.white,
      );
    }

    canvas.drawPath(path, linePaint);
  }

  @override
  bool shouldRepaint(covariant _PingChartPainter oldDelegate) => true;
}
