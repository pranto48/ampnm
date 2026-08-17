import 'package:flutter/material.dart';
import '../app_theme.dart';
import '../models/device_model.dart';
import '../models/map_model.dart';

class AddEditDeviceDialog extends StatefulWidget {
  final DeviceModel? initialDevice;
  final List<MapModel> maps;
  final int defaultMapId;
  final Function(Map<String, dynamic>) onSave;

  const AddEditDeviceDialog({
    super.key,
    this.initialDevice,
    required this.maps,
    this.defaultMapId = 1,
    required this.onSave,
  });

  @override
  State<AddEditDeviceDialog> createState() => _AddEditDeviceDialogState();
}

class _AddEditDeviceDialogState extends State<AddEditDeviceDialog> {
  final _formKey = GlobalKey<FormState>();
  late TextEditingController _nameController;
  late TextEditingController _ipController;
  late TextEditingController _portController;
  late TextEditingController _descController;
  late TextEditingController _intervalController;

  String _selectedType = 'server';
  int _selectedMapId = 1;
  bool _isSaving = false;

  final List<Map<String, dynamic>> _deviceCategories = [
    {'type': 'server', 'label': 'Server / Host', 'icon': Icons.dns_rounded},
    {'type': 'router', 'label': 'Core Router', 'icon': Icons.router_rounded},
    {'type': 'switch', 'label': 'Network Switch', 'icon': Icons.alt_route_rounded},
    {'type': 'wifi-router', 'label': 'WiFi Router / AP', 'icon': Icons.wifi_tethering_rounded},
    {'type': 'firewall', 'label': 'Firewall / Security', 'icon': Icons.shield_rounded},
    {'type': 'database', 'label': 'Database Node', 'icon': Icons.storage_rounded},
    {'type': 'cloud', 'label': 'Cloud / Gateway', 'icon': Icons.cloud_rounded},
    {'type': 'camera', 'label': 'IP Camera / CCTV', 'icon': Icons.videocam_rounded},
    {'type': 'nas', 'label': 'NAS / Storage', 'icon': Icons.save_rounded},
    {'type': 'printer', 'label': 'Network Printer', 'icon': Icons.print_rounded},
    {'type': 'ipphone', 'label': 'VoIP Phone', 'icon': Icons.phone_in_talk_rounded},
    {'type': 'punchdevice', 'label': 'Biometric Punch', 'icon': Icons.fingerprint_rounded},
    {'type': 'radio', 'label': 'Radio / Tower', 'icon': Icons.cell_tower_rounded},
    {'type': 'rack', 'label': 'Server Rack', 'icon': Icons.view_headline_rounded},
    {'type': 'desktop', 'label': 'PC / Workstation', 'icon': Icons.desktop_windows_rounded},
    {'type': 'laptop', 'label': 'Laptop Device', 'icon': Icons.laptop_mac_rounded},
    {'type': 'box', 'label': 'Custom Box Unit', 'icon': Icons.check_box_outline_blank_rounded},
  ];

  @override
  void initState() {
    super.initState();
    final d = widget.initialDevice;
    _nameController = TextEditingController(text: d?.name ?? '');
    _ipController = TextEditingController(text: d?.ip ?? '');
    _portController = TextEditingController(text: d != null && d.checkPort > 0 ? '${d.checkPort}' : '');
    _descController = TextEditingController(text: d?.description ?? '');
    _intervalController = TextEditingController(text: d != null ? '${d.pingInterval}' : '5');
    _selectedType = d?.type ?? 'server';
    _selectedMapId = d?.mapId ?? widget.defaultMapId;
  }

  @override
  void dispose() {
    _nameController.dispose();
    _ipController.dispose();
    _portController.dispose();
    _descController.dispose();
    _intervalController.dispose();
    super.dispose();
  }

  void _applyPortPreset(int port) {
    setState(() {
      _portController.text = port > 0 ? '$port' : '';
    });
  }

  IconData _getIconForType(String type) {
    final match = _deviceCategories.firstWhere(
      (c) => c['type'] == type,
      orElse: () => {'icon': Icons.devices_other_rounded},
    );
    return match['icon'] as IconData;
  }

  Future<void> _handleSave() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSaving = true);
    try {
      final payload = {
        'id': widget.initialDevice?.id,
        'name': _nameController.text.trim(),
        'ip': _ipController.text.trim(),
        'check_port': int.tryParse(_portController.text.trim()) ?? 0,
        'type': _selectedType,
        'description': _descController.text.trim(),
        'ping_interval': int.tryParse(_intervalController.text.trim()) ?? 5,
        'map_id': _selectedMapId,
      };
      await widget.onSave(payload);
      if (mounted) Navigator.of(context).pop();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to save device: $e'), backgroundColor: AppTheme.danger),
        );
      }
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isEdit = widget.initialDevice != null;

    return Dialog(
      backgroundColor: const Color(0xFF0F172A),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: Color(0xFF334155)),
      ),
      child: Container(
        width: 620,
        padding: const EdgeInsets.all(24),
        child: Form(
          key: _formKey,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
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
                            color: const Color(0xFF22D3EE).withOpacity(0.15),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Icon(
                            isEdit ? Icons.edit_note : Icons.add_to_queue,
                            color: const Color(0xFF22D3EE),
                            size: 22,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              isEdit ? 'Edit Monitored Device' : 'Add New Network Device',
                              style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                                color: Colors.white,
                              ),
                            ),
                            const Text(
                              'Configure network endpoint, icon and telemetry intervals',
                              style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8)),
                            ),
                          ],
                        ),
                      ],
                    ),
                    IconButton(
                      icon: const Icon(Icons.close, color: Color(0xFF94A3B8), size: 18),
                      onPressed: () => Navigator.of(context).pop(),
                    ),
                  ],
                ),
                const SizedBox(height: 18),

                // Device Name & IP Row
                Row(
                  children: [
                    Expanded(
                      flex: 3,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Device Name', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFFCBD5E1))),
                          const SizedBox(height: 6),
                          TextFormField(
                            controller: _nameController,
                            style: const TextStyle(color: Colors.white, fontSize: 13),
                            decoration: InputDecoration(
                              hintText: 'e.g. Core Switch 01 / Gateway',
                              hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
                              prefixIcon: const Icon(Icons.label_outline, size: 16, color: Color(0xFF94A3B8)),
                              filled: true,
                              fillColor: const Color(0xFF1E293B),
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFF334155))),
                              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                            ),
                            validator: (v) => v == null || v.trim().isEmpty ? 'Name is required' : null,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      flex: 2,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('IP Address / Hostname', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFFCBD5E1))),
                          const SizedBox(height: 6),
                          TextFormField(
                            controller: _ipController,
                            style: const TextStyle(color: Colors.white, fontSize: 13, fontFamily: 'monospace'),
                            decoration: InputDecoration(
                              hintText: '192.168.1.1',
                              hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
                              prefixIcon: const Icon(Icons.dns, size: 16, color: Color(0xFF94A3B8)),
                              filled: true,
                              fillColor: const Color(0xFF1E293B),
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFF334155))),
                              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                            ),
                            validator: (v) => v == null || v.trim().isEmpty ? 'IP is required' : null,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),

                // Device Type & Icon Selector Section
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFF1E293B).withOpacity(0.7),
                    borderRadius: BorderRadius.circular(10),
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
                              Icon(Icons.category, size: 14, color: Color(0xFF22D3EE)),
                              SizedBox(width: 6),
                              Text(
                                'DEVICE TYPE & ICON',
                                style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF22D3EE), letterSpacing: 0.8),
                              ),
                            ],
                          ),
                          Row(
                            children: [
                              const Text('Selected: ', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))),
                              Icon(_getIconForType(_selectedType), size: 14, color: const Color(0xFF22D3EE)),
                              const SizedBox(width: 4),
                              Text(_selectedType.toUpperCase(), style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.white)),
                            ],
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: _deviceCategories.map((cat) {
                          final isSel = _selectedType == cat['type'];
                          return InkWell(
                            onTap: () => setState(() => _selectedType = cat['type']),
                            borderRadius: BorderRadius.circular(8),
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                              decoration: BoxDecoration(
                                color: isSel ? const Color(0xFF0891B2).withOpacity(0.3) : const Color(0xFF0F172A),
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(
                                  color: isSel ? const Color(0xFF22D3EE) : const Color(0xFF334155),
                                  width: isSel ? 1.5 : 1,
                                ),
                                boxShadow: [
                                  if (isSel) const BoxShadow(color: Color(0x3322D3EE), blurRadius: 6),
                                ],
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(cat['icon'], size: 16, color: isSel ? const Color(0xFF22D3EE) : const Color(0xFF94A3B8)),
                                  const SizedBox(width: 6),
                                  Text(
                                    cat['label'],
                                    style: TextStyle(
                                      fontSize: 11,
                                      color: isSel ? Colors.white : const Color(0xFFCBD5E1),
                                      fontWeight: isSel ? FontWeight.bold : FontWeight.normal,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        }).toList(),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),

                // Port & Map Selection Row
                Row(
                  children: [
                    Expanded(
                      flex: 2,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('TCP Check Port (0 = ICMP Ping)', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFFCBD5E1))),
                          const SizedBox(height: 6),
                          TextFormField(
                            controller: _portController,
                            style: const TextStyle(color: Colors.white, fontSize: 13),
                            keyboardType: TextInputType.number,
                            decoration: InputDecoration(
                              hintText: '0 (ICMP)',
                              hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
                              prefixIcon: const Icon(Icons.settings_ethernet, size: 16, color: Color(0xFF94A3B8)),
                              filled: true,
                              fillColor: const Color(0xFF1E293B),
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFF334155))),
                              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                            ),
                          ),
                          const SizedBox(height: 6),
                          Wrap(
                            spacing: 4,
                            children: [
                              _portChip(0, 'ICMP'),
                              _portChip(80, 'HTTP'),
                              _portChip(443, 'HTTPS'),
                              _portChip(22, 'SSH'),
                              _portChip(53, 'DNS'),
                              _portChip(3389, 'RDP'),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      flex: 2,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Network Map Assignment', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFFCBD5E1))),
                          const SizedBox(height: 6),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFF1E293B),
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: const Color(0xFF334155)),
                            ),
                            child: DropdownButton<int>(
                              value: widget.maps.any((m) => m.id == _selectedMapId) ? _selectedMapId : (widget.maps.isNotEmpty ? widget.maps.first.id : 1),
                              isExpanded: true,
                              dropdownColor: const Color(0xFF1E293B),
                              underline: const SizedBox(),
                              items: widget.maps.map((m) {
                                return DropdownMenuItem<int>(
                                  value: m.id,
                                  child: Text(m.name, style: const TextStyle(color: Colors.white, fontSize: 12)),
                                );
                              }).toList(),
                              onChanged: (val) {
                                if (val != null) setState(() => _selectedMapId = val);
                              },
                            ),
                          ),
                          const SizedBox(height: 12),
                          const Text('Ping Interval (Seconds)', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFFCBD5E1))),
                          const SizedBox(height: 6),
                          TextFormField(
                            controller: _intervalController,
                            style: const TextStyle(color: Colors.white, fontSize: 13),
                            keyboardType: TextInputType.number,
                            decoration: InputDecoration(
                              hintText: '5',
                              hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
                              prefixIcon: const Icon(Icons.timer, size: 16, color: Color(0xFF94A3B8)),
                              filled: true,
                              fillColor: const Color(0xFF1E293B),
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFF334155))),
                              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),

                // Description
                const Text('Notes / Description (Optional)', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFFCBD5E1))),
                const SizedBox(height: 6),
                TextFormField(
                  controller: _descController,
                  style: const TextStyle(color: Colors.white, fontSize: 12),
                  maxLines: 2,
                  decoration: InputDecoration(
                    hintText: 'e.g. Core distribution switch rack B2',
                    hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 11),
                    filled: true,
                    fillColor: const Color(0xFF1E293B),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFF334155))),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  ),
                ),
                const SizedBox(height: 20),

                // Dialog Action Buttons
                Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    TextButton(
                      onPressed: () => Navigator.of(context).pop(),
                      child: const Text('Cancel', style: TextStyle(color: Color(0xFF94A3B8))),
                    ),
                    const SizedBox(width: 10),
                    ElevatedButton(
                      onPressed: _isSaving ? null : _handleSave,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF0891B2),
                        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                      ),
                      child: _isSaving
                          ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                          : Text(isEdit ? 'Update Device' : 'Add Device'),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _portChip(int port, String label) {
    return InkWell(
      onTap: () => _applyPortPreset(port),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
        margin: const EdgeInsets.only(top: 2),
        decoration: BoxDecoration(
          color: const Color(0xFF0F172A),
          borderRadius: BorderRadius.circular(4),
          border: Border.all(color: const Color(0xFF475569)),
        ),
        child: Text('$port ($label)', style: const TextStyle(fontSize: 9, color: Color(0xFF94A3B8))),
      ),
    );
  }
}
