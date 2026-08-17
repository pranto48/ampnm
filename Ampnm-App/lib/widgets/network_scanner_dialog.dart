import 'package:flutter/material.dart';
import '../app_theme.dart';
import '../models/map_model.dart';
import '../services/server_service.dart';

class NetworkScannerDialog extends StatefulWidget {
  final String serverUrl;
  final String sessionCookie;
  final List<MapModel> maps;
  final int defaultMapId;
  final Function(Map<String, dynamic>) onImportDevice;

  const NetworkScannerDialog({
    super.key,
    required this.serverUrl,
    required this.sessionCookie,
    required this.maps,
    this.defaultMapId = 1,
    required this.onImportDevice,
  });

  @override
  State<NetworkScannerDialog> createState() => _NetworkScannerDialogState();
}

class _NetworkScannerDialogState extends State<NetworkScannerDialog> {
  final TextEditingController _startIpController = TextEditingController(text: '192.168.9.1');
  final TextEditingController _endIpController = TextEditingController(text: '192.168.9.254');
  final TextEditingController _portController = TextEditingController(text: '0');

  bool _isScanning = false;
  List<Map<String, dynamic>> _discoveredHosts = [];
  final Set<String> _importedIps = {};
  int _selectedMapId = 1;

  @override
  void initState() {
    super.initState();
    _selectedMapId = widget.defaultMapId;
  }

  @override
  void dispose() {
    _startIpController.dispose();
    _endIpController.dispose();
    _portController.dispose();
    super.dispose();
  }

  Future<void> _startScan() async {
    final startIp = _startIpController.text.trim();
    final endIp = _endIpController.text.trim();
    final port = int.tryParse(_portController.text.trim()) ?? 0;

    if (startIp.isEmpty || endIp.isEmpty) return;

    setState(() {
      _isScanning = true;
      _discoveredHosts.clear();
    });

    try {
      final results = await ServerService.scanNetwork(
        serverUrl: widget.serverUrl,
        sessionCookie: widget.sessionCookie,
        startIp: startIp,
        endIp: endIp,
        port: port,
      );

      if (mounted) {
        setState(() {
          _discoveredHosts = results;
          _isScanning = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isScanning = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Scan error: $e'), backgroundColor: AppTheme.danger),
        );
      }
    }
  }

  Future<void> _importHost(Map<String, dynamic> host) async {
    final ip = host['ip']?.toString() ?? '';
    final name = host['hostname']?.toString() ?? (host['name']?.toString() ?? 'Host-$ip');
    final port = int.tryParse(host['port']?.toString() ?? '0') ?? 0;

    try {
      await widget.onImportDevice({
        'name': name,
        'ip': ip,
        'check_port': port,
        'type': _guessDeviceType(name, ip),
        'map_id': _selectedMapId,
        'description': 'Discovered via Subnet Scanner',
      });

      setState(() {
        _importedIps.add(ip);
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Imported $name ($ip)'), backgroundColor: AppTheme.success, duration: const Duration(seconds: 1)),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to import $ip: $e'), backgroundColor: AppTheme.danger),
        );
      }
    }
  }

  String _guessDeviceType(String name, String ip) {
    final n = name.toLowerCase();
    if (n.contains('router') || ip.endsWith('.1')) return 'router';
    if (n.contains('switch')) return 'switch';
    if (n.contains('ap') || n.contains('wifi')) return 'wifi';
    if (n.contains('cam')) return 'camera';
    if (n.contains('nas') || n.contains('storage')) return 'nas';
    return 'server';
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: AppTheme.surfaceCard,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: AppTheme.border),
      ),
      child: Container(
        width: 720,
        height: 600,
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
                      child: const Icon(Icons.radar, color: AppTheme.primaryGlow, size: 22),
                    ),
                    const SizedBox(width: 12),
                    const Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Subnet IP Range Scanner',
                          style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.white),
                        ),
                        Text(
                          'Scan network range for alive hosts and 1-click import into AMPNM',
                          style: TextStyle(fontSize: 12, color: AppTheme.textSecondary),
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

            // Range Inputs Bar
            Row(
              children: [
                Expanded(
                  flex: 3,
                  child: _buildInput('Start IP Address', _startIpController, '192.168.9.1'),
                ),
                const SizedBox(width: 10),
                Expanded(
                  flex: 3,
                  child: _buildInput('End IP Address', _endIpController, '192.168.9.254'),
                ),
                const SizedBox(width: 10),
                Expanded(
                  flex: 2,
                  child: _buildInput('Port (0=ICMP)', _portController, '0'),
                ),
                const SizedBox(width: 12),
                Padding(
                  padding: const EdgeInsets.only(top: 18),
                  child: ElevatedButton.icon(
                    onPressed: _isScanning ? null : _startScan,
                    icon: _isScanning
                        ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Icon(Icons.search, size: 16),
                    label: Text(_isScanning ? 'Scanning...' : 'Scan Range'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),

            // Target Map Selector
            Row(
              children: [
                const Text('Import Target Map: ', style: TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  height: 32,
                  decoration: BoxDecoration(
                    color: AppTheme.surface,
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: AppTheme.border),
                  ),
                  child: DropdownButton<int>(
                    value: widget.maps.any((m) => m.id == _selectedMapId) ? _selectedMapId : (widget.maps.isNotEmpty ? widget.maps.first.id : 1),
                    underline: const SizedBox(),
                    dropdownColor: AppTheme.surfaceCard,
                    items: widget.maps.map((m) {
                      return DropdownMenuItem<int>(
                        value: m.id,
                        child: Text(m.name, style: const TextStyle(fontSize: 12, color: Colors.white)),
                      );
                    }).toList(),
                    onChanged: (val) {
                      if (val != null) setState(() => _selectedMapId = val);
                    },
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),

            // Discovered Hosts List
            Expanded(
              child: Container(
                decoration: BoxDecoration(
                  color: const Color(0xFF070D18),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: AppTheme.border),
                ),
                child: _isScanning
                    ? const Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            CircularProgressIndicator(color: AppTheme.primary),
                            SizedBox(height: 16),
                            Text('Scanning subnet range across network...', style: TextStyle(color: AppTheme.textSecondary)),
                          ],
                        ),
                      )
                    : _discoveredHosts.isEmpty
                        ? const Center(
                            child: Text(
                              'No scan results yet. Enter an IP range and click "Scan Range".',
                              style: TextStyle(color: AppTheme.textMuted),
                            ),
                          )
                        : ListView.separated(
                            itemCount: _discoveredHosts.length,
                            separatorBuilder: (_, __) => const Divider(color: AppTheme.border, height: 1),
                            itemBuilder: (context, index) {
                              final host = _discoveredHosts[index];
                              final ip = host['ip']?.toString() ?? '';
                              final name = host['hostname']?.toString() ?? host['name']?.toString() ?? 'Host-$ip';
                              final isAlive = host['status'] == 'online' || host['alive'] == true || host['status'] == 'up';
                              final isImported = _importedIps.contains(ip);

                              return ListTile(
                                dense: true,
                                leading: Container(
                                  width: 8,
                                  height: 8,
                                  decoration: BoxDecoration(
                                    color: isAlive ? AppTheme.success : AppTheme.danger,
                                    shape: BoxShape.circle,
                                  ),
                                ),
                                title: Text(name, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
                                subtitle: Text(ip, style: const TextStyle(color: AppTheme.primaryGlow, fontFamily: 'monospace', fontSize: 11)),
                                trailing: isImported
                                    ? const Chip(
                                        label: Text('Imported', style: TextStyle(fontSize: 10, color: AppTheme.success)),
                                        backgroundColor: Color(0xFF064E3B),
                                      )
                                    : ElevatedButton.icon(
                                        onPressed: () => _importHost(host),
                                        icon: const Icon(Icons.add, size: 14),
                                        label: const Text('Add to AMPNM', style: TextStyle(fontSize: 11)),
                                        style: ElevatedButton.styleFrom(
                                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                          minimumSize: const Size(0, 30),
                                        ),
                                      ),
                              );
                            },
                          ),
              ),
            ),
            const SizedBox(height: 14),

            // Footer
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Found ${_discoveredHosts.length} active nodes',
                  style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary),
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
    );
  }

  Widget _buildInput(String label, TextEditingController controller, String hint) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(fontSize: 11, color: AppTheme.textSecondary)),
        const SizedBox(height: 6),
        TextField(
          controller: controller,
          style: const TextStyle(color: Colors.white, fontSize: 12),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: const TextStyle(color: AppTheme.textMuted, fontSize: 11),
            filled: true,
            fillColor: AppTheme.surface,
            contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(6), borderSide: const BorderSide(color: AppTheme.border)),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(6), borderSide: const BorderSide(color: AppTheme.border)),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(6), borderSide: const BorderSide(color: AppTheme.primary)),
          ),
        ),
      ],
    );
  }
}
