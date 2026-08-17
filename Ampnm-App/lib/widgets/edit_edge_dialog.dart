import 'package:flutter/material.dart';
import '../app_theme.dart';
import '../models/device_model.dart';
import '../models/edge_model.dart';

class EditEdgeDialog extends StatefulWidget {
  final EdgeModel edge;
  final List<DeviceModel> devices;
  final Function(Map<String, dynamic>) onSave;

  const EditEdgeDialog({
    super.key,
    required this.edge,
    required this.devices,
    required this.onSave,
  });

  @override
  State<EditEdgeDialog> createState() => _EditEdgeDialogState();
}

class _EditEdgeDialogState extends State<EditEdgeDialog> {
  late String _selectedType;
  late final TextEditingController _labelController;
  late double _thickness;

  final List<Map<String, dynamic>> _connectionTypes = [
    {'type': 'cat6', 'label': '🔌 CAT6 Cable', 'color': Color(0xFFA78BFA)},
    {'type': 'fiber', 'label': '💡 Fiber Optic', 'color': Color(0xFFF97316)},
    {'type': 'wifi', 'label': '📡 WiFi Link', 'color': Color(0xFF38BDF8)},
    {'type': 'radio', 'label': '📻 Radio Link', 'color': Color(0xFF84CC16)},
    {'type': 'lan', 'label': '🌐 Standard LAN', 'color': Color(0xFF60A5FA)},
    {'type': 'logical-tunneling', 'label': '🔒 VPN / Tunnel', 'color': Color(0xFFC084FC)},
  ];

  @override
  void initState() {
    super.initState();
    _selectedType = widget.edge.connectionType;
    _labelController = TextEditingController(text: widget.edge.label ?? '');
    _thickness = widget.edge.thickness;
  }

  @override
  void dispose() {
    _labelController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final src = widget.devices.firstWhere(
      (d) => d.id == widget.edge.sourceId,
      orElse: () => DeviceModel(id: widget.edge.sourceId, name: 'Node #${widget.edge.sourceId}', ip: ''),
    );
    final dst = widget.devices.firstWhere(
      (d) => d.id == widget.edge.targetId,
      orElse: () => DeviceModel(id: widget.edge.targetId, name: 'Node #${widget.edge.targetId}', ip: ''),
    );

    return Dialog(
      backgroundColor: const Color(0xFF0F172A),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: Color(0xFF334155)),
      ),
      child: Container(
        width: 480,
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Row(
                  children: [
                    Icon(Icons.edit_road, color: Color(0xFF22D3EE), size: 22),
                    SizedBox(width: 10),
                    Text(
                      'Edit Connection Link',
                      style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                  ],
                ),
                IconButton(
                  icon: const Icon(Icons.close, color: Color(0xFF94A3B8), size: 18),
                  onPressed: () => Navigator.of(context).pop(),
                ),
              ],
            ),
            const SizedBox(height: 16),

            // Endpoint Nodes Info Box
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFF1E293B),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFF334155)),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    src.name,
                    style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white, fontSize: 13),
                  ),
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 12),
                    child: Icon(Icons.swap_horiz, color: Color(0xFF22D3EE), size: 20),
                  ),
                  Text(
                    dst.name,
                    style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white, fontSize: 13),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 18),

            // Connection Type Selection
            const Text(
              'Connection Type',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8)),
            ),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              decoration: BoxDecoration(
                color: const Color(0xFF1E293B),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFF334155)),
              ),
              child: DropdownButton<String>(
                value: _selectedType,
                isExpanded: true,
                dropdownColor: const Color(0xFF1E293B),
                underline: const SizedBox(),
                items: _connectionTypes.map((ct) {
                  return DropdownMenuItem<String>(
                    value: ct['type'],
                    child: Row(
                      children: [
                        Container(
                          width: 12,
                          height: 12,
                          decoration: BoxDecoration(
                            color: ct['color'],
                            shape: BoxShape.circle,
                            boxShadow: [
                              BoxShadow(color: ct['color'], blurRadius: 6),
                            ],
                          ),
                        ),
                        const SizedBox(width: 10),
                        Text(ct['label'], style: const TextStyle(color: Colors.white, fontSize: 13)),
                      ],
                    ),
                  );
                }).toList(),
                onChanged: (val) {
                  if (val != null) setState(() => _selectedType = val);
                },
              ),
            ),
            const SizedBox(height: 16),

            // Custom Label
            const Text(
              'Link Label (Optional)',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8)),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _labelController,
              style: const TextStyle(color: Colors.white, fontSize: 13),
              decoration: InputDecoration(
                hintText: 'e.g. 10 Gbps Trunk / Port 24 to 1',
                hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
                filled: true,
                fillColor: const Color(0xFF1E293B),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFF334155))),
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              ),
            ),
            const SizedBox(height: 16),

            // Line Thickness Slider
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Line Thickness',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8)),
                ),
                Text(
                  '${_thickness.toInt()} px',
                  style: const TextStyle(color: Color(0xFF22D3EE), fontWeight: FontWeight.bold, fontSize: 12),
                ),
              ],
            ),
            Slider(
              value: _thickness,
              min: 1.0,
              max: 6.0,
              divisions: 5,
              activeColor: const Color(0xFF06B6D4),
              inactiveColor: const Color(0xFF334155),
              onChanged: (val) => setState(() => _thickness = val),
            ),
            const SizedBox(height: 16),

            // Actions
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                TextButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Cancel', style: TextStyle(color: Color(0xFF94A3B8))),
                ),
                const SizedBox(width: 8),
                ElevatedButton(
                  onPressed: () {
                    widget.onSave({
                      'id': widget.edge.id,
                      'connection_type': _selectedType,
                      'label': _labelController.text.trim(),
                      'thickness': _thickness,
                    });
                    Navigator.of(context).pop();
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF0891B2),
                    padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
                  ),
                  child: const Text('Save Changes'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
