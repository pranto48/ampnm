import 'package:flutter/material.dart';
import '../app_theme.dart';
import '../models/server_profile.dart';
import '../services/storage_service.dart';

class SettingsDialog extends StatefulWidget {
  final StorageService storage;
  final ServerProfile currentProfile;
  final ValueChanged<ServerProfile> onProfileChanged;
  final int? currentPollSeconds;
  final ValueChanged<int>? onPollSecondsChanged;

  const SettingsDialog({
    super.key,
    required this.storage,
    required this.currentProfile,
    required this.onProfileChanged,
    this.currentPollSeconds = 5,
    this.onPollSecondsChanged,
  });

  @override
  State<SettingsDialog> createState() => _SettingsDialogState();
}

class _SettingsDialogState extends State<SettingsDialog> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  late List<ServerProfile> _profiles;
  late int _pollSeconds;
  bool _soundAlerts = true;
  bool _toastAlerts = true;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _profiles = widget.storage.getProfiles();
    _pollSeconds = widget.currentPollSeconds ?? 5;
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  void _deleteProfile(String id) async {
    await widget.storage.deleteProfile(id);
    setState(() {
      _profiles = widget.storage.getProfiles();
    });
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
        width: 620,
        height: 520,
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Row(
                  children: [
                    Icon(Icons.tune, color: AppTheme.primaryGlow, size: 22),
                    SizedBox(width: 10),
                    Text(
                      'AMPNM Desktop Settings',
                      style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.white),
                    ),
                  ],
                ),
                IconButton(
                  icon: const Icon(Icons.close, color: AppTheme.textMuted, size: 20),
                  onPressed: () => Navigator.of(context).pop(),
                ),
              ],
            ),
            const SizedBox(height: 12),

            // Tab Bar
            TabBar(
              controller: _tabController,
              indicatorColor: AppTheme.primaryGlow,
              labelColor: AppTheme.primaryGlow,
              unselectedLabelColor: AppTheme.textSecondary,
              tabs: const [
                Tab(icon: Icon(Icons.sync, size: 16), text: 'Live Polling & Alerts'),
                Tab(icon: Icon(Icons.dns, size: 16), text: 'Server Profiles'),
                Tab(icon: Icon(Icons.keyboard, size: 16), text: 'Shortcuts Guide'),
              ],
            ),
            const SizedBox(height: 16),

            // Tab View Body
            Expanded(
              child: TabBarView(
                controller: _tabController,
                children: [
                  // Tab 1: Live Polling & Alerts
                  _buildPollingTab(),

                  // Tab 2: Server Profiles
                  _buildProfilesTab(),

                  // Tab 3: Shortcuts Guide
                  _buildShortcutsTab(),
                ],
              ),
            ),
            const SizedBox(height: 14),

            // Footer
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                ElevatedButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Save & Close'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPollingTab() {
    return ListView(
      children: [
        const Text('Live Telemetry Refresh Speed', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Colors.white)),
        const SizedBox(height: 4),
        const Text('Control background REST API polling frequency for device statuses', style: TextStyle(fontSize: 11, color: AppTheme.textSecondary)),
        const SizedBox(height: 12),

        Wrap(
          spacing: 10,
          children: [
            _buildPollOption(2, 'Extreme (2s)', 'Fastest response, higher server requests'),
            _buildPollOption(5, 'Fast (5s)', 'Recommended for standard NOC monitoring'),
            _buildPollOption(10, 'Normal (10s)', 'Balanced network & bandwidth usage'),
            _buildPollOption(30, 'Eco (30s)', 'Battery / low bandwidth mode'),
          ],
        ),
        const SizedBox(height: 20),
        const Divider(color: AppTheme.border),
        const SizedBox(height: 12),

        const Text('Notification Alarms', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Colors.white)),
        const SizedBox(height: 8),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Sound Alert on Device Offline', style: TextStyle(fontSize: 13, color: Colors.white)),
          subtitle: const Text('Play alert sound whenever a monitored node goes down', style: TextStyle(fontSize: 11, color: AppTheme.textSecondary)),
          value: _soundAlerts,
          activeColor: AppTheme.primaryGlow,
          onChanged: (val) => setState(() => _soundAlerts = val),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Desktop Tray Notifications', style: TextStyle(fontSize: 13, color: Colors.white)),
          subtitle: const Text('Show native Windows banner when network state changes', style: TextStyle(fontSize: 11, color: AppTheme.textSecondary)),
          value: _toastAlerts,
          activeColor: AppTheme.primaryGlow,
          onChanged: (val) => setState(() => _toastAlerts = val),
        ),
      ],
    );
  }

  Widget _buildPollOption(int seconds, String title, String desc) {
    final isSelected = _pollSeconds == seconds;
    return InkWell(
      onTap: () {
        setState(() => _pollSeconds = seconds);
        if (widget.onPollSecondsChanged != null) {
          widget.onPollSecondsChanged!(seconds);
        }
      },
      borderRadius: BorderRadius.circular(8),
      child: Container(
        width: 260,
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: isSelected ? AppTheme.primary.withOpacity(0.15) : AppTheme.surface,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: isSelected ? AppTheme.primaryGlow : AppTheme.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(title, style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: isSelected ? Colors.white : AppTheme.textPrimary)),
                if (isSelected) const Icon(Icons.check_circle, size: 14, color: AppTheme.primaryGlow),
              ],
            ),
            const SizedBox(height: 2),
            Text(desc, style: const TextStyle(fontSize: 10, color: AppTheme.textMuted)),
          ],
        ),
      ),
    );
  }

  Widget _buildProfilesTab() {
    return _profiles.isEmpty
        ? const Center(child: Text('No saved server profiles.', style: TextStyle(color: AppTheme.textMuted)))
        : ListView.separated(
            itemCount: _profiles.length,
            separatorBuilder: (_, __) => const Divider(color: AppTheme.border, height: 1),
            itemBuilder: (context, idx) {
              final p = _profiles[idx];
              final isCurrent = p.id == widget.currentProfile.id;

              return ListTile(
                dense: true,
                leading: Container(
                  width: 8,
                  height: 8,
                  decoration: BoxDecoration(
                    color: isCurrent ? AppTheme.success : AppTheme.textMuted,
                    shape: BoxShape.circle,
                  ),
                ),
                title: Text(p.name, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
                subtitle: Text(p.url, style: const TextStyle(color: AppTheme.primaryGlow, fontFamily: 'monospace', fontSize: 11)),
                trailing: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (!isCurrent)
                      TextButton(
                        onPressed: () {
                          widget.onProfileChanged(p);
                          Navigator.of(context).pop();
                        },
                        child: const Text('Switch', style: TextStyle(fontSize: 11)),
                      ),
                    IconButton(
                      icon: const Icon(Icons.delete_outline, size: 16, color: AppTheme.danger),
                      onPressed: () => _deleteProfile(p.id),
                    ),
                  ],
                ),
              );
            },
          );
  }

  Widget _buildShortcutsTab() {
    final shortcuts = [
      {'key': 'Ctrl + F', 'desc': 'Search Devices / Nodes across views'},
      {'key': 'Ctrl + R', 'desc': 'Force Refresh Live Data from server'},
      {'key': 'Ctrl + N', 'desc': 'Add New Network Device'},
      {'key': 'Ctrl + P', 'desc': 'Bulk Ping All Monitored Devices'},
      {'key': '1', 'desc': 'Switch to Live Dashboard View'},
      {'key': '2', 'desc': 'Switch to Live Network Topology Map'},
      {'key': '3', 'desc': 'Switch to Device Manager View'},
      {'key': '4', 'desc': 'Switch to Audit Logs & Alerts'},
      {'key': '5', 'desc': 'Switch to Embedded Web Console'},
      {'key': 'F11', 'desc': 'Toggle Fullscreen Mode'},
    ];

    return ListView.separated(
      itemCount: shortcuts.length,
      separatorBuilder: (_, __) => const Divider(color: AppTheme.border, height: 1),
      itemBuilder: (context, idx) {
        final s = shortcuts[idx];
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(s['desc']!, style: const TextStyle(fontSize: 12, color: Colors.white)),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: AppTheme.surface,
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: AppTheme.border),
                ),
                child: Text(
                  s['key']!,
                  style: const TextStyle(fontSize: 11, fontFamily: 'monospace', color: AppTheme.primaryGlow, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
