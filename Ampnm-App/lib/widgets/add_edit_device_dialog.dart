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

  final List<Map<String, dynamic>> _deviceTypes = [
    {'type': 'router', 'label': 'Router / Gateway', 'icon': Icons.router},
    {'type': 'switch', 'label': 'Network Switch', 'icon': Icons.alt_route},
    {'type': 'server', 'label': 'Server / Host', 'icon': Icons.dns},
    {'type': 'wifi', 'label': 'Access Point (Wi-Fi)', 'icon': Icons.wifi},
    {'type': 'firewall', 'label': 'Firewall / Security', 'icon': Icons.security},
    {'type': 'camera', 'label': 'IP Camera / CCTV', 'icon': Icons.videocam},
    {'type': 'desktop', 'label': 'Workstation / PC', 'icon': Icons.computer},
    {'type': 'nas', 'label': 'Storage / NAS', 'icon': Icons.storage},
    {'type': 'cloud', 'label': 'Cloud / WAN', 'icon': Icons.cloud},
    {'type': 'other', 'label': 'Other Device', 'icon': Icons.devices_other},
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
      backgroundColor: AppTheme.surfaceCard,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: AppTheme.border),
      ),
      child: Container(
        width: 540,
        padding: const EdgeInsets.all(24),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Dialog Header
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
                        child: Icon(
                          isEdit ? Icons.edit_note : Icons.add_to_queue,
                          color: AppTheme.primaryGlow,
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
                          Text(
                            isEdit ? 'Update properties for ${widget.initialDevice!.name}' : 'Configure IP, port check and ping interval',
                            style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary),
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
              const SizedBox(height: 20),
              const Divider(color: AppTheme.border),
              const SizedBox(height: 16),

              // Inputs Row 1: Name & Type
              Row(
                children: [
                  Expanded(
                    flex: 3,
                    child: _buildTextField(
                      controller: _nameController,
                      label: 'Device Name / Hostname *',
                      hint: 'e.g. Core-Router-01, Web-Server',
                      validator: (val) => val == null || val.trim().isEmpty ? 'Required' : null,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    flex: 2,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Device Type', style: TextStyle(fontSize: 11, color: AppTheme.textSecondary)),
                        const SizedBox(height: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10),
                          decoration: BoxDecoration(
                            color: AppTheme.surface,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: AppTheme.border),
                          ),
                          child: DropdownButton<String>(
                            value: _selectedType,
                            isExpanded: true,
                            underline: const SizedBox(),
                            dropdownColor: AppTheme.surfaceCard,
                            items: _deviceTypes.map((item) {
                              return DropdownMenuItem<String>(
                                value: item['type'] as String,
                                child: Row(
                                  children: [
                                    Icon(item['icon'] as IconData, size: 16, color: AppTheme.primaryGlow),
                                    const SizedBox(width: 8),
                                    Text(
                                      item['label'] as String,
                                      style: const TextStyle(fontSize: 12, color: Colors.white),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ],
                                ),
                              );
                            }).toList(),
                            onChanged: (val) {
                              if (val != null) setState(() => _selectedType = val);
                            },
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),

              // Inputs Row 2: IP Address & Check Port
              Row(
                children: [
                  Expanded(
                    flex: 3,
                    child: _buildTextField(
                      controller: _ipController,
                      label: 'IP Address / Domain *',
                      hint: '192.168.9.1 or gateway.local',
                      validator: (val) => val == null || val.trim().isEmpty ? 'Required' : null,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    flex: 2,
                    child: _buildTextField(
                      controller: _portController,
                      label: 'Check Port (0=ICMP Ping)',
                      hint: '0, 80, 443, 22',
                      isNumber: true,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),

              // Port Presets Row
              Row(
                children: [
                  const Text('Port Presets: ', style: TextStyle(fontSize: 10, color: AppTheme.textMuted)),
                  _buildPortPresetChip('ICMP (0)', 0),
                  _buildPortPresetChip('HTTP (80)', 80),
                  _buildPortPresetChip('HTTPS (443)', 443),
                  _buildPortPresetChip('SSH (22)', 22),
                  _buildPortPresetChip('Winbox (8291)', 8291),
                  _buildPortPresetChip('RDP (3389)', 3389),
                ],
              ),
              const SizedBox(height: 14),

              // Inputs Row 3: Target Map & Ping Interval
              Row(
                children: [
                  Expanded(
                    flex: 3,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Assigned Map', style: TextStyle(fontSize: 11, color: AppTheme.textSecondary)),
                        const SizedBox(height: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10),
                          decoration: BoxDecoration(
                            color: AppTheme.surface,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: AppTheme.border),
                          ),
                          child: DropdownButton<int>(
                            value: widget.maps.any((m) => m.id == _selectedMapId)
                                ? _selectedMapId
                                : (widget.maps.isNotEmpty ? widget.maps.first.id : 1),
                            isExpanded: true,
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
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    flex: 2,
                    child: _buildTextField(
                      controller: _intervalController,
                      label: 'Ping Interval (Seconds)',
                      hint: '5',
                      isNumber: true,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),

              // Description / Notes
              _buildTextField(
                controller: _descController,
                label: 'Description / Location / Notes',
                hint: 'e.g. Server Rack 2, 3rd Floor Switch, Uplink to ISP',
              ),
              const SizedBox(height: 24),

              // Dialog Footer Actions
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  TextButton(
                    onPressed: () => Navigator.of(context).pop(),
                    child: const Text('Cancel'),
                  ),
                  const SizedBox(width: 10),
                  ElevatedButton.icon(
                    onPressed: _isSaving ? null : _handleSave,
                    icon: _isSaving
                        ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Icon(Icons.check, size: 16),
                    label: Text(_isSaving ? 'Saving...' : (isEdit ? 'Update Device' : 'Save Device')),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildPortPresetChip(String label, int port) {
    return InkWell(
      onTap: () => _applyPortPreset(port),
      borderRadius: BorderRadius.circular(4),
      child: Container(
        margin: const EdgeInsets.only(right: 4),
        padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
        decoration: BoxDecoration(
          color: AppTheme.surface,
          borderRadius: BorderRadius.circular(4),
          border: Border.all(color: AppTheme.border),
        ),
        child: Text(label, style: const TextStyle(fontSize: 9, color: AppTheme.primaryGlow)),
      ),
    );
  }

  Widget _buildTextField({
    required TextEditingController controller,
    required String label,
    String? hint,
    bool isNumber = false,
    String? Function(String?)? validator,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(fontSize: 11, color: AppTheme.textSecondary)),
        const SizedBox(height: 6),
        TextFormField(
          controller: controller,
          keyboardType: isNumber ? TextInputType.number : TextInputType.text,
          validator: validator,
          style: const TextStyle(color: Colors.white, fontSize: 13),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: const TextStyle(color: AppTheme.textMuted, fontSize: 12),
            filled: true,
            fillColor: AppTheme.surface,
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AppTheme.border)),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AppTheme.border)),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AppTheme.primary)),
          ),
        ),
      ],
    );
  }
}
