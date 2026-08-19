import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
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

class _AddEditDeviceDialogState extends State<AddEditDeviceDialog> with SingleTickerProviderStateMixin {
  final _formKey = GlobalKey<FormState>();
  late TabController _tabController;
  late TextEditingController _nameController;
  late TextEditingController _ipController;
  late TextEditingController _portController;
  late TextEditingController _descController;
  late TextEditingController _intervalController;
  late TextEditingController _iconUrlController;

  String _selectedType = 'server';
  String _selectedSubchoice = '';
  int _selectedMapId = 1;
  bool _isSaving = false;

  static const List<Map<String, dynamic>> _pngIcons = [
    {'subchoice': '0', 'label': 'Sophos Firewall', 'path': 'assets/images/device-icons/sophos-firewall.png'},
    {'subchoice': '1', 'label': 'Cisco Switch', 'path': 'assets/images/device-icons/cisco-switch.png'},
    {'subchoice': '2', 'label': 'MikroTik Router', 'path': 'assets/images/device-icons/mikrotik-router.png'},
    {'subchoice': '3', 'label': 'Online UPS', 'path': 'assets/images/device-icons/online-ups.png'},
    {'subchoice': '4', 'label': 'Rack Server', 'path': 'assets/images/device-icons/rack-server.png'},
    {'subchoice': '5', 'label': 'Default Device', 'path': 'assets/images/device-icons/default-device.png'},
  ];

  static const List<Map<String, dynamic>> _animatedIcons = [
    {'subchoice': '0', 'label': 'Globe', 'path': 'assets/images/device-icons/animated-globe.svg'},
    {'subchoice': '1', 'label': 'Router', 'path': 'assets/images/device-icons/animated-router.svg'},
    {'subchoice': '2', 'label': 'Firewall', 'path': 'assets/images/device-icons/animated-firewall.svg'},
    {'subchoice': '3', 'label': 'Server', 'path': 'assets/images/device-icons/animated-server.svg'},
    {'subchoice': '4', 'label': 'Access Point', 'path': 'assets/images/device-icons/animated-access-point.svg'},
    {'subchoice': '5', 'label': 'Switch', 'path': 'assets/images/device-icons/animated-switch.svg'},
    {'subchoice': '6', 'label': 'Cloud', 'path': 'assets/images/device-icons/animated-cloud.svg'},
    {'subchoice': '7', 'label': 'Camera', 'path': 'assets/images/device-icons/animated-camera.svg'},
    {'subchoice': '8', 'label': 'Database', 'path': 'assets/images/device-icons/animated-database.svg'},
    {'subchoice': '9', 'label': 'Workstation', 'path': 'assets/images/device-icons/animated-workstation.svg'},
    {'subchoice': '10', 'label': 'Phone', 'path': 'assets/images/device-icons/animated-phone.svg'},
    {'subchoice': '11', 'label': 'Printer', 'path': 'assets/images/device-icons/animated-printer.svg'},
    {'subchoice': '12', 'label': 'Laptop', 'path': 'assets/images/device-icons/animated-laptop.svg'},
    {'subchoice': '13', 'label': 'NAS Storage', 'path': 'assets/images/device-icons/animated-nas.svg'},
    {'subchoice': '14', 'label': 'IoT Sensor', 'path': 'assets/images/device-icons/animated-iot-sensor.svg'},
    {'subchoice': '15', 'label': 'UPS', 'path': 'assets/images/device-icons/animated-ups.svg'},
    {'subchoice': '16', 'label': 'UTM Firewall', 'path': 'assets/images/device-icons/animated-utm.svg'},
    {'subchoice': '17', 'label': 'Tower Antenna', 'path': 'assets/images/device-icons/animated-tower.svg'},
    {'subchoice': '18', 'label': 'Modem', 'path': 'assets/images/device-icons/animated-modem.svg'},
    {'subchoice': '19', 'label': 'Patch Panel', 'path': 'assets/images/device-icons/animated-patch-panel.svg'},
    {'subchoice': '20', 'label': 'VLAN', 'path': 'assets/images/device-icons/animated-vlan.svg'},
    {'subchoice': '21', 'label': 'Warehouse', 'path': 'assets/images/device-icons/animated-warehouse.svg'},
    {'subchoice': '22', 'label': 'Core Switch', 'path': 'assets/images/device-icons/animated-switch-core.svg'},
    {'subchoice': '23', 'label': 'Online UPS', 'path': 'assets/images/device-icons/animated-ups-online.svg'},
    {'subchoice': '24', 'label': 'NextGen FW', 'path': 'assets/images/device-icons/animated-firewall-nextgen.svg'},
    {'subchoice': '25', 'label': 'Unit Box', 'path': 'assets/images/device-icons/animated-unit.svg'},
    {'subchoice': '26', 'label': 'Satellite', 'path': 'assets/images/device-icons/animated-sat.svg'},
    {'subchoice': '27', 'label': 'SD-WAN', 'path': 'assets/images/device-icons/animated-sdwan.svg'},
    {'subchoice': '28', 'label': 'Datacenter', 'path': 'assets/images/device-icons/animated-datacenter.svg'},
    {'subchoice': '29', 'label': 'WiFi Router', 'path': 'assets/images/device-icons/animated-wifi-router.svg'},
    {'subchoice': '30', 'label': 'Optical SFP', 'path': 'assets/images/device-icons/animated-optical.svg'},
    {'subchoice': '31', 'label': 'Blade Server', 'path': 'assets/images/device-icons/animated-server-blade.svg'},
  ];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    final d = widget.initialDevice;
    _nameController = TextEditingController(text: d?.name ?? '');
    _ipController = TextEditingController(text: d?.ip ?? '');
    _portController = TextEditingController(text: d != null && d.checkPort > 0 ? '${d.checkPort}' : '');
    _descController = TextEditingController(text: d?.description ?? '');
    _intervalController = TextEditingController(text: d != null ? '${d.pingInterval}' : '5');
    _iconUrlController = TextEditingController(text: d?.iconUrl ?? '');
    _selectedType = d?.type ?? 'server';
    _selectedSubchoice = d?.subchoice ?? '';
    _selectedMapId = d?.mapId ?? widget.defaultMapId;
  }

  @override
  void dispose() {
    _tabController.dispose();
    _nameController.dispose();
    _ipController.dispose();
    _portController.dispose();
    _descController.dispose();
    _intervalController.dispose();
    _iconUrlController.dispose();
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
        'subchoice': _selectedSubchoice,
        'icon_url': _iconUrlController.text.trim().isEmpty ? null : _iconUrlController.text.trim(),
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
        width: 680,
        height: 600,
        padding: const EdgeInsets.all(24),
        child: Form(
          key: _formKey,
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
                          color: const Color(0xFF22D3EE).withOpacity(0.15),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: const Color(0xFF22D3EE).withOpacity(0.3)),
                        ),
                        child: const Icon(Icons.settings_input_component, color: Color(0xFF22D3EE), size: 20),
                      ),
                      const SizedBox(width: 12),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            isEdit ? 'Edit Device Properties' : 'Add New Network Device',
                            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white),
                          ),
                          const Text(
                            'Live bidirectional sync with Docker AMPNM Server',
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
              const SizedBox(height: 14),

              // Tabs (General Config vs Docker Icon Picker)
              Container(
                decoration: const BoxDecoration(
                  border: Border(bottom: BorderSide(color: Color(0xFF334155))),
                ),
                child: TabBar(
                  controller: _tabController,
                  indicatorColor: const Color(0xFF22D3EE),
                  labelColor: const Color(0xFF22D3EE),
                  unselectedLabelColor: const Color(0xFF94A3B8),
                  labelStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                  tabs: const [
                    Tab(icon: Icon(Icons.tune, size: 16), text: 'General Configuration'),
                    Tab(icon: Icon(Icons.image, size: 16), text: 'Docker Device Icon Picker'),
                  ],
                ),
              ),
              const SizedBox(height: 14),

              // Tab Views
              Expanded(
                child: TabBarView(
                  controller: _tabController,
                  children: [
                    _buildGeneralTab(),
                    _buildIconPickerTab(),
                  ],
                ),
              ),

              const Divider(color: Color(0xFF334155)),
              const SizedBox(height: 10),

              // Action Buttons
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  TextButton(
                    onPressed: _isSaving ? null : () => Navigator.of(context).pop(),
                    child: const Text('Cancel', style: TextStyle(color: Color(0xFF94A3B8))),
                  ),
                  const SizedBox(width: 12),
                  ElevatedButton.icon(
                    onPressed: _isSaving ? null : _handleSave,
                    icon: _isSaving
                        ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black))
                        : const Icon(Icons.check, size: 16),
                    label: Text(isEdit ? 'Update & Sync Device' : 'Create & Sync Device'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF22D3EE),
                      foregroundColor: Colors.black,
                      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildGeneralTab() {
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Device Name & IP Row
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                flex: 3,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Device Name *', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFFCBD5E1))),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _nameController,
                      style: const TextStyle(color: Colors.white, fontSize: 13),
                      decoration: InputDecoration(
                        hintText: 'e.g. Core-MikroTik-Router',
                        hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
                        filled: true,
                        fillColor: const Color(0xFF1E293B),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFF334155))),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      ),
                      validator: (val) => val == null || val.trim().isEmpty ? 'Device name required' : null,
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
                    const Text('IP Address / Host *', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFFCBD5E1))),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _ipController,
                      style: const TextStyle(color: Color(0xFF22D3EE), fontSize: 13, fontFamily: 'monospace'),
                      decoration: InputDecoration(
                        hintText: '192.168.9.1',
                        hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
                        filled: true,
                        fillColor: const Color(0xFF1E293B),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFF334155))),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      ),
                      validator: (val) => val == null || val.trim().isEmpty ? 'IP address required' : null,
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Port & Target Map Row
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Check Port (0 for ICMP Ping)', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFFCBD5E1))),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _portController,
                      keyboardType: TextInputType.number,
                      style: const TextStyle(color: Colors.white, fontSize: 13),
                      decoration: InputDecoration(
                        hintText: '0 (ICMP) or 80/443/8291',
                        hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
                        filled: true,
                        fillColor: const Color(0xFF1E293B),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFF334155))),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Topology Map', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFFCBD5E1))),
                    const SizedBox(height: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      decoration: BoxDecoration(
                        color: const Color(0xFF1E293B),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: const Color(0xFF334155)),
                      ),
                      child: DropdownButtonHideUnderline(
                        child: DropdownButton<int>(
                          value: widget.maps.any((m) => m.id == _selectedMapId) ? _selectedMapId : (widget.maps.isNotEmpty ? widget.maps.first.id : null),
                          dropdownColor: const Color(0xFF0F172A),
                          isExpanded: true,
                          style: const TextStyle(color: Colors.white, fontSize: 13),
                          items: widget.maps.map((m) {
                            return DropdownMenuItem<int>(
                              value: m.id,
                              child: Text(m.name),
                            );
                          }).toList(),
                          onChanged: (val) {
                            if (val != null) setState(() => _selectedMapId = val);
                          },
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),

          // Quick Port Presets
          Wrap(
            spacing: 6,
            children: [
              _buildPortChip('ICMP (0)', 0),
              _buildPortChip('HTTP (80)', 80),
              _buildPortChip('HTTPS (443)', 443),
              _buildPortChip('SSH (22)', 22),
              _buildPortChip('Winbox (8291)', 8291),
              _buildPortChip('RDP (3389)', 3389),
            ],
          ),
          const SizedBox(height: 12),

          // Ping Interval & Description
          Row(
            children: [
              Expanded(
                flex: 1,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Ping Interval (Sec)', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFFCBD5E1))),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _intervalController,
                      keyboardType: TextInputType.number,
                      style: const TextStyle(color: Colors.white, fontSize: 13),
                      decoration: InputDecoration(
                        filled: true,
                        fillColor: const Color(0xFF1E293B),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFF334155))),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                flex: 3,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Description / Notes', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFFCBD5E1))),
                    const SizedBox(height: 6),
                    TextFormField(
                      controller: _descController,
                      style: const TextStyle(color: Colors.white, fontSize: 13),
                      decoration: InputDecoration(
                        hintText: 'Location, rack position, or interface notes...',
                        hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 12),
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
        ],
      ),
    );
  }

  Widget _buildIconPickerTab() {
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // 1. Docker 3D / PNG Icons (Sophos, Cisco, MikroTik, UPS, Server)
          const Text(
            '🌟 Docker Hardware PNG Icons (Matched with Docker AMPNM)',
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF22D3EE)),
          ),
          const SizedBox(height: 8),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 3,
              mainAxisExtent: 64,
              crossAxisSpacing: 8,
              mainAxisSpacing: 8,
            ),
            itemCount: _pngIcons.length,
            itemBuilder: (context, idx) {
              final item = _pngIcons[idx];
              final isSelected = _selectedType == 'png-icons' && _selectedSubchoice == item['subchoice'];

              return InkWell(
                onTap: () {
                  setState(() {
                    _selectedType = 'png-icons';
                    _selectedSubchoice = item['subchoice'];
                    _iconUrlController.clear();
                  });
                },
                borderRadius: BorderRadius.circular(8),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: isSelected ? const Color(0xFF0891B2).withOpacity(0.3) : const Color(0xFF1E293B),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: isSelected ? const Color(0xFF22D3EE) : const Color(0xFF334155),
                      width: isSelected ? 2 : 1,
                    ),
                  ),
                  child: Row(
                    children: [
                      Image.asset(item['path'], width: 34, height: 34, fit: BoxFit.contain),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          item['label'],
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                            color: isSelected ? const Color(0xFF22D3EE) : Colors.white,
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
          const SizedBox(height: 16),

          // 2. Docker Animated SVG Icons
          const Text(
            '✨ Docker Animated SVG Icons (32 Telecom & NOC Icons)',
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF38BDF8)),
          ),
          const SizedBox(height: 8),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 4,
              mainAxisExtent: 60,
              crossAxisSpacing: 6,
              mainAxisSpacing: 6,
            ),
            itemCount: _animatedIcons.length,
            itemBuilder: (context, idx) {
              final item = _animatedIcons[idx];
              final isSelected = _selectedType == 'animated-icons' && _selectedSubchoice == item['subchoice'];

              return InkWell(
                onTap: () {
                  setState(() {
                    _selectedType = 'animated-icons';
                    _selectedSubchoice = item['subchoice'];
                    _iconUrlController.clear();
                  });
                },
                borderRadius: BorderRadius.circular(8),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                  decoration: BoxDecoration(
                    color: isSelected ? const Color(0xFF0369A1).withOpacity(0.35) : const Color(0xFF1E293B),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: isSelected ? const Color(0xFF38BDF8) : const Color(0xFF334155),
                      width: isSelected ? 2 : 1,
                    ),
                  ),
                  child: Row(
                    children: [
                      SvgPicture.asset(item['path'], width: 28, height: 28, fit: BoxFit.contain),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          item['label'],
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                            color: isSelected ? const Color(0xFF38BDF8) : Colors.white,
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
          const SizedBox(height: 16),

          // 3. Custom Icon URL
          const Text(
            '🔗 Custom Icon URL (Docker Uploads or Remote HTTP Image)',
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFFCBD5E1)),
          ),
          const SizedBox(height: 6),
          TextFormField(
            controller: _iconUrlController,
            style: const TextStyle(color: Colors.white, fontSize: 12),
            decoration: InputDecoration(
              hintText: 'e.g. assets/images/device-icons/mikrotik-router.png or https://example.com/icon.png',
              hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 11),
              filled: true,
              fillColor: const Color(0xFF1E293B),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFF334155))),
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPortChip(String label, int port) {
    return ActionChip(
      label: Text(label, style: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8))),
      backgroundColor: const Color(0xFF1E293B),
      side: const BorderSide(color: Color(0xFF334155)),
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 0),
      onPressed: () => _applyPortPreset(port),
    );
  }
}
