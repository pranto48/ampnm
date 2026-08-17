import 'package:flutter/material.dart';
import '../app_theme.dart';
import '../models/device_model.dart';

class ConnectNodesDialog extends StatefulWidget {
  final List<DeviceModel> devices;
  final int? initialSourceId;
  final int? initialTargetId;
  final Function(Map<String, dynamic>) onSave;

  const ConnectNodesDialog({
    super.key,
    required this.devices,
    this.initialSourceId,
    this.initialTargetId,
    required this.onSave,
  });

  @override
  State<ConnectNodesDialog> createState() => _ConnectNodesDialogState();
}

class _ConnectNodesDialogState extends State<ConnectNodesDialog> {
  int? _sourceId;
  int? _targetId;
  String _connectionType = 'cat6';
  final TextEditingController _labelController = TextEditingController();
  bool _isSaving = false;

  final List<Map<String, dynamic>> _cableTypes = [
    {'type': 'cat6', 'label': 'Cat6 / Cat6A Gigabit (Ethernet)', 'color': Color(0xFF3B82F6)},
    {'type': 'fiber', 'label': 'Fiber Optic (SFP+ / 10G/40G)', 'color': Color(0xFF06B6D4)},
    {'type': 'wireless', 'label': 'Wireless Link / Wi-Fi PtP', 'color': Color(0xFFA855F7)},
    {'type': 'trunk', 'label': 'Trunk / Core Uplink', 'color': Color(0xFF10B981)},
  ];

  @override
  void initState() {
    super.initState();
    _sourceId = widget.initialSourceId ?? (widget.devices.isNotEmpty ? widget.devices.first.id : null);
    _targetId = widget.initialTargetId ?? (widget.devices.length > 1 ? widget.devices[1].id : null);
  }

  @override
  void dispose() {
    _labelController.dispose();
    super.dispose();
  }

  Future<void> _handleSave() async {
    if (_sourceId == null || _targetId == null || _sourceId == _targetId) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select two different devices to connect.'), backgroundColor: AppTheme.warning),
      );
      return;
    }

    setState(() => _isSaving = true);
    try {
      await widget.onSave({
        'source_id': _sourceId,
        'target_id': _targetId,
        'connection_type': _connectionType,
        'label': _labelController.text.trim(),
      });
      if (mounted) Navigator.of(context).pop();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to link devices: $e'), backgroundColor: AppTheme.danger),
        );
      }
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
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
        width: 480,
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppTheme.primary.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.cable, color: AppTheme.primaryGlow, size: 22),
                ),
                const SizedBox(width: 12),
                const Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Link Device Connection',
                      style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.white),
                    ),
                    Text(
                      'Create a visual network topology edge between nodes',
                      style: TextStyle(fontSize: 12, color: AppTheme.textSecondary),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 18),
            const Divider(color: AppTheme.border),
            const SizedBox(height: 14),

            // Source Device Selector
            const Text('Source Node (From):', style: TextStyle(fontSize: 11, color: AppTheme.textSecondary)),
            const SizedBox(height: 6),
            _buildDeviceDropdown(_sourceId, (val) => setState(() => _sourceId = val)),
            const SizedBox(height: 14),

            // Target Device Selector
            const Text('Target Node (To):', style: TextStyle(fontSize: 11, color: AppTheme.textSecondary)),
            const SizedBox(height: 6),
            _buildDeviceDropdown(_targetId, (val) => setState(() => _targetId = val)),
            const SizedBox(height: 14),

            // Cable / Connection Type
            const Text('Connection / Cable Type:', style: TextStyle(fontSize: 11, color: AppTheme.textSecondary)),
            const SizedBox(height: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10),
              decoration: BoxDecoration(
                color: AppTheme.surface,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: AppTheme.border),
              ),
              child: DropdownButton<String>(
                value: _connectionType,
                isExpanded: true,
                underline: const SizedBox(),
                dropdownColor: AppTheme.surfaceCard,
                items: _cableTypes.map((c) {
                  return DropdownMenuItem<String>(
                    value: c['type'] as String,
                    child: Row(
                      children: [
                        Container(
                          width: 10,
                          height: 10,
                          decoration: BoxDecoration(color: c['color'] as Color, shape: BoxShape.circle),
                        ),
                        const SizedBox(width: 8),
                        Text(c['label'] as String, style: const TextStyle(fontSize: 12, color: Colors.white)),
                      ],
                    ),
                  );
                }).toList(),
                onChanged: (val) {
                  if (val != null) setState(() => _connectionType = val);
                },
              ),
            ),
            const SizedBox(height: 14),

            // Link Label (Optional)
            const Text('Link Port / Label (Optional):', style: TextStyle(fontSize: 11, color: AppTheme.textSecondary)),
            const SizedBox(height: 6),
            TextField(
              controller: _labelController,
              style: const TextStyle(color: Colors.white, fontSize: 13),
              decoration: InputDecoration(
                hintText: 'e.g. Eth1 -> Port 24, SFP+ 10G',
                hintStyle: const TextStyle(color: AppTheme.textMuted, fontSize: 12),
                filled: true,
                fillColor: AppTheme.surface,
                contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AppTheme.border)),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AppTheme.border)),
                focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AppTheme.primary)),
              ),
            ),
            const SizedBox(height: 24),

            // Footer
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
                      : const Icon(Icons.link, size: 16),
                  label: Text(_isSaving ? 'Linking...' : 'Connect Link'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDeviceDropdown(int? selectedId, ValueChanged<int?> onChanged) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: AppTheme.border),
      ),
      child: DropdownButton<int>(
        value: widget.devices.any((d) => d.id == selectedId) ? selectedId : (widget.devices.isNotEmpty ? widget.devices.first.id : null),
        isExpanded: true,
        underline: const SizedBox(),
        dropdownColor: AppTheme.surfaceCard,
        items: widget.devices.map((d) {
          return DropdownMenuItem<int>(
            value: d.id,
            child: Row(
              children: [
                Icon(d.typeIcon, size: 16, color: d.statusColor),
                const SizedBox(width: 8),
                Text('${d.name} (${d.ip})', style: const TextStyle(fontSize: 12, color: Colors.white)),
              ],
            ),
          );
        }).toList(),
        onChanged: onChanged,
      ),
    );
  }
}
