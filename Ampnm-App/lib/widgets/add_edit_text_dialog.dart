import 'package:flutter/material.dart';
import '../models/device_model.dart';

class AddEditTextDialog extends StatefulWidget {
  final DeviceModel? initialTextNode;
  final int defaultMapId;
  final Function(Map<String, dynamic>) onSave;

  const AddEditTextDialog({
    super.key,
    this.initialTextNode,
    required this.defaultMapId,
    required this.onSave,
  });

  @override
  State<AddEditTextDialog> createState() => _AddEditTextDialogState();
}

class _AddEditTextDialogState extends State<AddEditTextDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _textController;
  late double _fontSize;
  late String _selectedColor;
  late bool _isBold;
  late bool _isItalic;

  final List<Map<String, dynamic>> _colorPresets = [
    {'name': 'Cyan Glow', 'hex': '#22D3EE', 'color': Color(0xFF22D3EE)},
    {'name': 'Pure White', 'hex': '#FFFFFF', 'color': Colors.white},
    {'name': 'Amber Gold', 'hex': '#F59E0B', 'color': Color(0xFFF59E0B)},
    {'name': 'Emerald Green', 'hex': '#10B981', 'color': Color(0xFF10B981)},
    {'name': 'Neon Purple', 'hex': '#A855F7', 'color': Color(0xFFA855F7)},
    {'name': 'Coral Red', 'hex': '#EF4444', 'color': Color(0xFFEF4444)},
    {'name': 'Slate Gray', 'hex': '#94A3B8', 'color': Color(0xFF94A3B8)},
  ];

  final List<Map<String, dynamic>> _quickPresets = [
    {'title': '🗺️ Map Title', 'text': 'Main Network Topology', 'size': 20.0, 'bold': true, 'color': '#22D3EE'},
    {'title': '🏢 Datacenter Zone', 'text': 'Zone A - Core Datacenter', 'size': 16.0, 'bold': true, 'color': '#F59E0B'},
    {'title': '⚡ Fiber Ring', 'text': '10G Backbone Optical Ring', 'size': 14.0, 'bold': false, 'color': '#10B981'},
    {'title': '🔒 Secure Segment', 'text': 'DMZ / Security Zone', 'size': 14.0, 'bold': true, 'color': '#EF4444'},
  ];

  @override
  void initState() {
    super.initState();
    final n = widget.initialTextNode;
    _textController = TextEditingController(text: n?.name ?? '');
    _fontSize = n != null ? n.nameTextSize : 16.0;
    _selectedColor = n?.nameTextColor ?? '#22D3EE';
    _isBold = n?.nameTextBold ?? true;
    _isItalic = n?.nameTextItalic ?? false;
  }

  @override
  void dispose() {
    _textController.dispose();
    super.dispose();
  }

  Color _parseHex(String hex) {
    String clean = hex.replaceAll('#', '');
    if (clean.length == 6) clean = 'FF$clean';
    return Color(int.tryParse(clean, radix: 16) ?? 0xFF22D3EE);
  }

  @override
  Widget build(BuildContext context) {
    final isEditing = widget.initialTextNode != null;

    return Dialog(
      backgroundColor: const Color(0xFF0F172A),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: Color(0xFF334155)),
      ),
      child: Container(
        width: 500,
        padding: const EdgeInsets.all(24),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.title, color: Color(0xFF22D3EE), size: 22),
                      const SizedBox(width: 10),
                      Text(
                        isEditing ? 'Edit Text Label' : 'Add Text Label to Map',
                        style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: Colors.white),
                      ),
                    ],
                  ),
                  IconButton(
                    icon: const Icon(Icons.close, color: Color(0xFF94A3B8), size: 18),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
              const SizedBox(height: 14),

              // Quick Presets
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFF1E293B).withOpacity(0.6),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: const Color(0xFF334155)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'QUICK TEMPLATES',
                      style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF22D3EE), letterSpacing: 0.8),
                    ),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: _quickPresets.map((p) {
                        return InkWell(
                          onTap: () {
                            setState(() {
                              _textController.text = p['text'];
                              _fontSize = p['size'];
                              _isBold = p['bold'];
                              _selectedColor = p['color'];
                            });
                          },
                          borderRadius: BorderRadius.circular(6),
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                            decoration: BoxDecoration(
                              color: const Color(0xFF0F172A),
                              borderRadius: BorderRadius.circular(6),
                              border: Border.all(color: const Color(0xFF475569)),
                            ),
                            child: Text(
                              p['title'],
                              style: const TextStyle(fontSize: 11, color: Colors.white),
                            ),
                          ),
                        );
                      }).toList(),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),

              // Text Input
              const Text(
                'Label Text',
                style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8)),
              ),
              const SizedBox(height: 6),
              TextFormField(
                controller: _textController,
                style: TextStyle(
                  color: _parseHex(_selectedColor),
                  fontSize: _fontSize.clamp(12.0, 22.0),
                  fontWeight: _isBold ? FontWeight.bold : FontWeight.normal,
                  fontStyle: _isItalic ? FontStyle.italic : FontStyle.normal,
                ),
                decoration: InputDecoration(
                  hintText: 'Enter title or section label...',
                  hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 13),
                  filled: true,
                  fillColor: const Color(0xFF1E293B),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFF334155))),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                ),
                validator: (v) => v == null || v.trim().isEmpty ? 'Text cannot be empty' : null,
                onChanged: (_) => setState(() {}),
              ),
              const SizedBox(height: 14),

              // Styling Controls (Font Size, Bold, Italic)
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Font Size', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))),
                            Text('${_fontSize.toInt()} pt', style: const TextStyle(fontSize: 11, color: Color(0xFF22D3EE), fontWeight: FontWeight.bold)),
                          ],
                        ),
                        Slider(
                          value: _fontSize,
                          min: 10.0,
                          max: 36.0,
                          divisions: 13,
                          activeColor: const Color(0xFF06B6D4),
                          inactiveColor: const Color(0xFF334155),
                          onChanged: (val) => setState(() => _fontSize = val),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 14),
                  FilterChip(
                    label: const Text('Bold', style: TextStyle(fontSize: 11)),
                    selected: _isBold,
                    selectedColor: const Color(0xFF0891B2),
                    onSelected: (v) => setState(() => _isBold = v),
                  ),
                  const SizedBox(width: 6),
                  FilterChip(
                    label: const Text('Italic', style: TextStyle(fontSize: 11)),
                    selected: _isItalic,
                    selectedColor: const Color(0xFF0891B2),
                    onSelected: (v) => setState(() => _isItalic = v),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // Color Preset Palette
              const Text('Text Color', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))),
              const SizedBox(height: 6),
              Row(
                children: _colorPresets.map((c) {
                  final isSel = _selectedColor.toUpperCase() == c['hex'].toString().toUpperCase();
                  return InkWell(
                    onTap: () => setState(() => _selectedColor = c['hex']),
                    child: Container(
                      width: 26,
                      height: 26,
                      margin: const EdgeInsets.only(right: 8),
                      decoration: BoxDecoration(
                        color: c['color'],
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: isSel ? Colors.white : Colors.transparent,
                          width: 2,
                        ),
                        boxShadow: [
                          if (isSel) BoxShadow(color: c['color'].withOpacity(0.8), blurRadius: 8),
                        ],
                      ),
                    ),
                  );
                }).toList(),
              ),
              const SizedBox(height: 18),

              // Action Buttons
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
                      if (!_formKey.currentState!.validate()) return;
                      widget.onSave({
                        if (isEditing) 'id': widget.initialTextNode!.id,
                        'name': _textController.text.trim(),
                        'ip': '',
                        'type': 'text',
                        'name_text_size': _fontSize,
                        'name_text_color': _selectedColor,
                        'name_text_bold': _isBold ? 1 : 0,
                        'name_text_italic': _isItalic ? 1 : 0,
                        'map_id': widget.defaultMapId,
                      });
                      Navigator.of(context).pop();
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF0891B2),
                      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
                    ),
                    child: Text(isEditing ? 'Update Label' : 'Add to Map'),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
