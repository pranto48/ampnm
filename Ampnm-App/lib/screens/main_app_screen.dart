import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:webview_windows/webview_windows.dart';

import '../app_theme.dart';
import '../models/device_model.dart';
import '../models/edge_model.dart';
import '../models/map_model.dart';
import '../models/server_profile.dart';
import '../models/status_log_model.dart';
import '../services/server_service.dart';
import '../services/storage_service.dart';
import '../views/native_dashboard_view.dart';
import '../views/native_devices_view.dart';
import '../views/native_logs_view.dart';
import '../views/native_map_view.dart';
import '../views/web_console_view.dart';
import '../widgets/add_edit_device_dialog.dart';
import '../widgets/connect_nodes_dialog.dart';
import '../widgets/continuous_ping_dialog.dart';
import '../widgets/custom_window_title_bar.dart';
import '../widgets/device_details_dialog.dart';
import '../widgets/network_scanner_dialog.dart';
import 'server_setup_screen.dart';
import 'settings_dialog.dart';

class MainAppScreen extends StatefulWidget {
  final StorageService storage;
  final ServerProfile profile;

  const MainAppScreen({
    super.key,
    required this.storage,
    required this.profile,
  });

  @override
  State<MainAppScreen> createState() => _MainAppScreenState();
}

class _MainAppScreenState extends State<MainAppScreen> {
  late ServerProfile _activeProfile;
  int _selectedNavIndex = 0; // 0: Dashboard, 1: Map, 2: Devices, 3: Logs, 4: Web Console

  // Comprehensive Data State (100% of Docker Server data)
  List<DeviceModel> _allDevices = []; // All devices across all maps & unmapped
  List<DeviceModel> _mapDevices = []; // Devices placed on current map
  List<MapModel> _maps = [];
  List<EdgeModel> _edges = [];
  List<StatusLogModel> _logs = [];
  int _selectedMapId = 1;
  bool _isDataLoading = false;
  int _currentLatencyMs = 12;
  int _pollIntervalSeconds = 3;
  bool _isLiveActive = true;
  bool _isPollingInProgress = false;
  String _syncStatus = 'Connected'; // 'Connected', 'Syncing', 'Reconnecting', 'Offline'
  Timer? _liveSyncTimer;

  // Embedded Webview (For optional Web Console Tab)
  WebviewController? _webviewController;
  bool _isWebviewInitialized = false;
  bool _isWebviewLoading = false;
  String _currentWebviewUrl = '';

  @override
  void initState() {
    super.initState();
    _activeProfile = widget.profile;
    _pollIntervalSeconds = widget.storage.getPollInterval();

    // 1. Instant load from local disk cache for instant zero-lag launch
    _loadFromLocalCache();

    // 2. Fetch live data from Docker server and start concurrency-safe background sync
    _initialLoadAllData();
    _startLiveSyncTimer();
  }

  @override
  void dispose() {
    _liveSyncTimer?.cancel();
    _webviewController?.dispose();
    super.dispose();
  }

  void _loadFromLocalCache() {
    final cachedMaps = widget.storage.getCachedMaps(_activeProfile.id);
    final mapId = cachedMaps.isNotEmpty ? cachedMaps.first.id : 1;
    final cachedDevices = widget.storage.getCachedDevices(_activeProfile.id, mapId);
    final cachedEdges = widget.storage.getCachedEdges(_activeProfile.id, mapId);
    final cachedLogs = widget.storage.getCachedLogs(_activeProfile.id);

    if (cachedMaps.isNotEmpty || cachedDevices.isNotEmpty) {
      setState(() {
        _maps = cachedMaps;
        _selectedMapId = mapId;
        _allDevices = cachedDevices;
        _mapDevices = cachedDevices;
        _edges = cachedEdges;
        _logs = cachedLogs;
      });
    }
  }

  void _startLiveSyncTimer() {
    _liveSyncTimer?.cancel();
    if (!_isLiveActive) return;

    _liveSyncTimer = Timer.periodic(Duration(seconds: _pollIntervalSeconds), (_) {
      _pollLiveDataSilently();
    });
  }

  void _updatePollInterval(int seconds) {
    setState(() => _pollIntervalSeconds = seconds);
    widget.storage.setPollInterval(seconds);
    _startLiveSyncTimer();
  }

  Future<void> _initialLoadAllData() async {
    setState(() => _isDataLoading = true);
    await _fetchAllData();
    if (mounted) {
      setState(() => _isDataLoading = false);
    }
  }

  Future<void> _fetchAllData() async {
    if (!mounted) return;
    final serverUrl = _activeProfile.serverUrl;
    String cookie = _activeProfile.sessionCookie ?? '';

    // Measure live latency to Docker server
    final testRes = await ServerService.testConnection(serverUrl);
    if (mounted) {
      setState(() {
        if (testRes.isSuccess) {
          _currentLatencyMs = testRes.latencyMs;
          _syncStatus = 'Connected';
        } else {
          _syncStatus = 'Reconnecting';
        }
      });
    }

    try {
      // Proactive authentication if cookie is missing but credentials exist
      if (cookie.isEmpty && _activeProfile.savedPassword != null && _activeProfile.savedPassword!.isNotEmpty) {
        final loginRes = await ServerService.login(
          serverUrl: serverUrl,
          username: _activeProfile.username,
          password: _activeProfile.savedPassword!,
        );
        if (loginRes.isSuccess && loginRes.sessionCookie != null) {
          cookie = loginRes.sessionCookie!;
          _activeProfile = _activeProfile.copyWith(sessionCookie: cookie);
          widget.storage.saveProfile(_activeProfile);
        }
      }

      // 1. Fetch All Maps from Docker Server
      var maps = await ServerService.getMaps(serverUrl: serverUrl, sessionCookie: cookie);

      // 2. Fetch ALL devices on Docker Server
      var allDevices = await ServerService.getAllDevices(
        serverUrl: serverUrl,
        sessionCookie: cookie,
      );

      // If both maps and devices are empty and we have saved credentials, session might have expired in PHP
      if (maps.isEmpty && allDevices.isEmpty && _activeProfile.savedPassword != null && _activeProfile.savedPassword!.isNotEmpty) {
        final loginRes = await ServerService.login(
          serverUrl: serverUrl,
          username: _activeProfile.username,
          password: _activeProfile.savedPassword!,
        );
        if (loginRes.isSuccess && loginRes.sessionCookie != null) {
          cookie = loginRes.sessionCookie!;
          _activeProfile = _activeProfile.copyWith(sessionCookie: cookie);
          widget.storage.saveProfile(_activeProfile);

          // Retry fetching with fresh session
          maps = await ServerService.getMaps(serverUrl: serverUrl, sessionCookie: cookie);
          allDevices = await ServerService.getAllDevices(serverUrl: serverUrl, sessionCookie: cookie);
        }
      }

      int mapIdToUse = _selectedMapId;
      if (maps.isNotEmpty && !maps.any((m) => m.id == mapIdToUse)) {
        mapIdToUse = maps.first.id;
      }

      // 3. Fetch Map Specific devices & Edges
      final mapDevices = await ServerService.getDevices(
        serverUrl: serverUrl,
        sessionCookie: cookie,
        mapId: mapIdToUse,
      );

      final edges = await ServerService.getEdges(
        serverUrl: serverUrl,
        sessionCookie: cookie,
        mapId: mapIdToUse,
      );

      // 4. Fetch Logs from Docker Server
      final logs = await ServerService.getStatusLogs(
        serverUrl: serverUrl,
        sessionCookie: cookie,
        mapId: mapIdToUse,
        limit: 50,
      );

      if (mounted) {
        setState(() {
          if (maps.isNotEmpty) _maps = maps;
          _selectedMapId = mapIdToUse;
          if (allDevices.isNotEmpty || mapDevices.isNotEmpty) {
            _allDevices = allDevices.isNotEmpty ? allDevices : mapDevices;
            _mapDevices = mapDevices.isNotEmpty ? mapDevices : _allDevices;
          }
          if (edges.isNotEmpty || mapDevices.isNotEmpty) _edges = edges;
          if (logs.isNotEmpty) _logs = logs;
          _syncStatus = 'Connected';
        });

        // Save fresh snapshot to Local Disk Cache
        if (maps.isNotEmpty) widget.storage.saveCachedMaps(_activeProfile.id, maps);
        if (_allDevices.isNotEmpty) widget.storage.saveCachedDevices(_activeProfile.id, mapIdToUse, _allDevices);
        if (edges.isNotEmpty) widget.storage.saveCachedEdges(_activeProfile.id, mapIdToUse, edges);
        if (logs.isNotEmpty) widget.storage.saveCachedLogs(_activeProfile.id, logs);
      }
    } catch (e) {
      if (mounted) {
        setState(() => _syncStatus = 'Reconnecting');
      }
    }
  }

  /// Concurrency-safe background status poller (prevents UI hang)
  Future<void> _pollLiveDataSilently() async {
    if (!mounted || !_isLiveActive || _isPollingInProgress) return;
    _isPollingInProgress = true;

    final serverUrl = _activeProfile.serverUrl;
    final cookie = _activeProfile.sessionCookie ?? '';

    try {
      // Background poll: fetch all devices for live telemetry
      final allDevices = await ServerService.getAllDevices(
        serverUrl: serverUrl,
        sessionCookie: cookie,
      );

      final mapDevices = await ServerService.getDevices(
        serverUrl: serverUrl,
        sessionCookie: cookie,
        mapId: _selectedMapId,
      );

      final logs = await ServerService.getStatusLogs(
        serverUrl: serverUrl,
        sessionCookie: cookie,
        mapId: _selectedMapId,
        limit: 30,
      );

      if (mounted && (allDevices.isNotEmpty || mapDevices.isNotEmpty)) {
        setState(() {
          _allDevices = allDevices.isNotEmpty ? allDevices : mapDevices;
          _mapDevices = mapDevices.isNotEmpty ? mapDevices : _allDevices;
          if (logs.isNotEmpty) _logs = logs;
          _syncStatus = 'Connected';
        });

        widget.storage.saveCachedDevices(_activeProfile.id, _selectedMapId, _allDevices);
        if (logs.isNotEmpty) widget.storage.saveCachedLogs(_activeProfile.id, logs);
      }
    } catch (_) {
      if (mounted) {
        setState(() => _syncStatus = 'Reconnecting');
      }
    } finally {
      _isPollingInProgress = false;
    }
  }

  void _onMapChanged(int newMapId) async {
    setState(() {
      _selectedMapId = newMapId;
      _isDataLoading = true;
    });

    final serverUrl = _activeProfile.serverUrl;
    final cookie = _activeProfile.sessionCookie ?? '';

    // Load from local cache for this map first
    final cachedDevices = widget.storage.getCachedDevices(_activeProfile.id, newMapId);
    final cachedEdges = widget.storage.getCachedEdges(_activeProfile.id, newMapId);
    if (cachedDevices.isNotEmpty) {
      setState(() {
        _mapDevices = cachedDevices;
        _edges = cachedEdges;
      });
    }

    try {
      final mapDevices = await ServerService.getDevices(serverUrl: serverUrl, sessionCookie: cookie, mapId: newMapId);
      final edges = await ServerService.getEdges(serverUrl: serverUrl, sessionCookie: cookie, mapId: newMapId);

      if (mounted) {
        setState(() {
          _mapDevices = mapDevices;
          _edges = edges;
          _isDataLoading = false;
        });

        widget.storage.saveCachedDevices(_activeProfile.id, newMapId, mapDevices);
        widget.storage.saveCachedEdges(_activeProfile.id, newMapId, edges);
      }
    } catch (_) {
      if (mounted) setState(() => _isDataLoading = false);
    }
  }

  Future<dynamic> _handlePingDevice(DeviceModel device) async {
    final res = await ServerService.pingDevice(
      serverUrl: _activeProfile.serverUrl,
      sessionCookie: _activeProfile.sessionCookie ?? '',
      deviceId: device.id,
      ip: device.ip,
      port: device.checkPort,
    );

    _pollLiveDataSilently();

    if (mounted && res != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Ping ${device.name} (${device.ip}): ${res['status'] ?? 'Done'}'),
          backgroundColor: res['status'] == 'success' ? AppTheme.success : AppTheme.danger,
          duration: const Duration(seconds: 2),
        ),
      );
    }
    return res;
  }

  void _handleBulkPingAll() async {
    final res = await ServerService.pingAllDevices(
      serverUrl: _activeProfile.serverUrl,
      sessionCookie: _activeProfile.sessionCookie ?? '',
      mapId: _selectedMapId,
    );

    _pollLiveDataSilently();

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res ? 'Bulk ping triggered on Docker server!' : 'Bulk ping request sent.'),
          backgroundColor: res ? AppTheme.success : AppTheme.info,
          duration: const Duration(seconds: 2),
        ),
      );
    }
  }

  void _showDeviceInspector(DeviceModel device) {
    showDialog(
      context: context,
      builder: (context) => DeviceDetailsDialog(
        device: device,
        onPing: _handlePingDevice,
        onContinuousPing: _openContinuousPingModal,
        onEditDevice: _openEditDeviceModal,
        onDeleteDevice: _handleDeleteDevice,
      ),
    );
  }

  void _openAddDeviceModal() {
    showDialog(
      context: context,
      builder: (context) => AddEditDeviceDialog(
        maps: _maps,
        defaultMapId: _selectedMapId,
        onSave: (data) async {
          final res = await ServerService.createDevice(
            serverUrl: _activeProfile.serverUrl,
            sessionCookie: _activeProfile.sessionCookie ?? '',
            name: data['name'],
            ip: data['ip'],
            checkPort: data['check_port'] ?? 0,
            type: data['type'] ?? 'server',
            subchoice: data['subchoice']?.toString() ?? '',
            iconUrl: data['icon_url'],
            description: data['description'] ?? '',
            pingInterval: data['ping_interval'] ?? 5,
            mapId: data['map_id'] ?? _selectedMapId,
          );
          await _fetchAllData();
          if (mounted && res != null) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Device added & synced to Docker server!'), backgroundColor: AppTheme.success),
            );
          }
        },
      ),
    );
  }

  void _openEditDeviceModal(DeviceModel device) {
    showDialog(
      context: context,
      builder: (context) => AddEditDeviceDialog(
        initialDevice: device,
        maps: _maps,
        defaultMapId: _selectedMapId,
        onSave: (data) async {
          final res = await ServerService.updateDevice(
            serverUrl: _activeProfile.serverUrl,
            sessionCookie: _activeProfile.sessionCookie ?? '',
            id: device.id,
            name: data['name'],
            ip: data['ip'],
            checkPort: data['check_port'] ?? 0,
            type: data['type'] ?? 'server',
            subchoice: data['subchoice']?.toString() ?? '',
            iconUrl: data['icon_url'],
            description: data['description'] ?? '',
            pingInterval: data['ping_interval'] ?? 5,
            mapId: data['map_id'] ?? _selectedMapId,
          );
          await _fetchAllData();
          if (mounted && res != null) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Device updated & synced to Docker server!'), backgroundColor: AppTheme.success),
            );
          }
        },
      ),
    );
  }

  void _handleDeleteDevice(DeviceModel device) async {
    final ok = await ServerService.deleteDevice(
      serverUrl: _activeProfile.serverUrl,
      sessionCookie: _activeProfile.sessionCookie ?? '',
      id: device.id,
    );
    await _fetchAllData();
    if (mounted && ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Deleted ${device.name} on Docker server!'), backgroundColor: AppTheme.success),
      );
    }
  }

  void _openNetworkScanner() {
    showDialog(
      context: context,
      builder: (context) => NetworkScannerDialog(
        serverUrl: _activeProfile.serverUrl,
        sessionCookie: _activeProfile.sessionCookie ?? '',
        maps: _maps,
        defaultMapId: _selectedMapId,
        onImportDevice: (data) async {
          await ServerService.createDevice(
            serverUrl: _activeProfile.serverUrl,
            sessionCookie: _activeProfile.sessionCookie ?? '',
            name: data['name'],
            ip: data['ip'],
            checkPort: data['check_port'] ?? 0,
            type: data['type'] ?? 'server',
            description: data['description'] ?? '',
            mapId: data['map_id'] ?? _selectedMapId,
          );
          _pollLiveDataSilently();
        },
      ),
    );
  }

  void _openContinuousPingModal(DeviceModel device) {
    showDialog(
      context: context,
      builder: (context) => ContinuousPingDialog(
        device: device,
        serverUrl: _activeProfile.serverUrl,
        sessionCookie: _activeProfile.sessionCookie ?? '',
      ),
    );
  }

  void _handleUpdatePosition(DeviceModel device, double newX, double newY) async {
    await ServerService.updateDeviceCoordinates(
      serverUrl: _activeProfile.serverUrl,
      sessionCookie: _activeProfile.sessionCookie ?? '',
      id: device.id,
      x: newX,
      y: newY,
    );
  }

  Future<void> _handleCreateEdge(Map<String, dynamic> edgeData) async {
    final res = await ServerService.createEdge(
      serverUrl: _activeProfile.serverUrl,
      sessionCookie: _activeProfile.sessionCookie ?? '',
      mapId: edgeData['map_id'] ?? _selectedMapId,
      sourceId: edgeData['source_id'],
      targetId: edgeData['target_id'],
      connectionType: edgeData['connection_type'] ?? 'cat6',
      label: edgeData['label'],
    );
    await _fetchAllData();
    if (mounted && res != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Topology connection synced to Docker server!'), backgroundColor: AppTheme.success),
      );
    }
  }

  Future<void> _handleUpdateEdge(Map<String, dynamic> edgeData) async {
    final res = await ServerService.updateEdge(
      serverUrl: _activeProfile.serverUrl,
      sessionCookie: _activeProfile.sessionCookie ?? '',
      id: edgeData['id'],
      connectionType: edgeData['connection_type'],
      label: edgeData['label'],
      thickness: edgeData['thickness'] ?? 2.0,
    );
    await _fetchAllData();
    if (mounted && res != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Topology connection updated & synced!'), backgroundColor: AppTheme.success),
      );
    }
  }

  Future<void> _handleAddTextNode(Map<String, dynamic> textData) async {
    final res = await ServerService.createDevice(
      serverUrl: _activeProfile.serverUrl,
      sessionCookie: _activeProfile.sessionCookie ?? '',
      name: textData['name'],
      ip: '',
      type: 'text',
      mapId: textData['map_id'] ?? _selectedMapId,
    );
    await _fetchAllData();
    if (mounted && res != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Text label added to map!'), backgroundColor: AppTheme.success),
      );
    }
  }

  Future<void> _handleDeleteEdge(int edgeId) async {
    final ok = await ServerService.deleteEdge(
      serverUrl: _activeProfile.serverUrl,
      sessionCookie: _activeProfile.sessionCookie ?? '',
      id: edgeId,
    );
    await _fetchAllData();
    if (mounted && ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Topology connection removed on Docker server!'), backgroundColor: AppTheme.success),
      );
    }
  }

  // Web Console Initializer
  Future<void> _ensureWebviewInitialized() async {
    if (_isWebviewInitialized) return;

    try {
      final controller = WebviewController();
      await controller.initialize();

      controller.url.listen((url) {
        if (mounted) setState(() => _currentWebviewUrl = url);
      });

      controller.loadingState.listen((state) {
        if (mounted) {
          setState(() => _isWebviewLoading = state == LoadingState.loading);
        }
      });

      final initialUrl = '${ServerService.sanitizeUrl(_activeProfile.serverUrl)}/index.php';
      await controller.loadUrl(initialUrl);

      if (mounted) {
        setState(() {
          _webviewController = controller;
          _isWebviewInitialized = true;
          _currentWebviewUrl = initialUrl;
        });
      }
    } catch (_) {}
  }

  void _navigateToWebPath(String path) {
    final cleanBase = ServerService.sanitizeUrl(_activeProfile.serverUrl);
    final targetUrl = '$cleanBase$path';
    _webviewController?.loadUrl(targetUrl);
  }

  void _handleSwitchServer() {
    _liveSyncTimer?.cancel();
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(
        builder: (_) => ServerSetupScreen(
          storage: widget.storage,
          initialProfile: _activeProfile,
        ),
      ),
    );
  }

  void _openSettings() {
    showDialog(
      context: context,
      builder: (context) => SettingsDialog(
        storage: widget.storage,
        currentProfile: _activeProfile,
        currentPollSeconds: _pollIntervalSeconds,
        onPollSecondsChanged: _updatePollInterval,
        onProfileChanged: (newProfile) async {
          await widget.storage.saveProfile(newProfile);
          setState(() {
            _activeProfile = newProfile;
          });
          _initialLoadAllData();
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return CallbackShortcuts(
      bindings: {
        const SingleActivator(LogicalKeyboardKey.keyR, control: true): _initialLoadAllData,
        const SingleActivator(LogicalKeyboardKey.f5): _initialLoadAllData,
        const SingleActivator(LogicalKeyboardKey.keyN, control: true): _openAddDeviceModal,
        const SingleActivator(LogicalKeyboardKey.keyP, control: true): _handleBulkPingAll,
        const SingleActivator(LogicalKeyboardKey.digit1, control: true): () => setState(() => _selectedNavIndex = 0),
        const SingleActivator(LogicalKeyboardKey.digit2, control: true): () => setState(() => _selectedNavIndex = 1),
        const SingleActivator(LogicalKeyboardKey.digit3, control: true): () => setState(() => _selectedNavIndex = 2),
        const SingleActivator(LogicalKeyboardKey.digit4, control: true): () => setState(() => _selectedNavIndex = 3),
        const SingleActivator(LogicalKeyboardKey.digit5, control: true): () {
          setState(() => _selectedNavIndex = 4);
          _ensureWebviewInitialized();
        },
      },
      child: Focus(
        autofocus: true,
        child: Scaffold(
          backgroundColor: AppTheme.background,
          body: Column(
            children: [
              // 1. Custom Desktop Frameless Title Bar
              CustomWindowTitleBar(
                serverProfile: _activeProfile,
                latencyMs: _currentLatencyMs,
                onSettingsPressed: _openSettings,
                onSwitchServerPressed: _handleSwitchServer,
              ),

              // 2. Main Desktop Workstation Area (Sidebar + Content)
              Expanded(
                child: Row(
                  children: [
                    // Desktop Left Sidebar Navigation
                    _buildDesktopSidebar(),

                    // Vertical Divider
                    Container(width: 1, color: AppTheme.border),

                    // Main Content View
                    Expanded(
                      child: IndexedStack(
                        index: _selectedNavIndex,
                        children: [
                          // 0: Native Software Dashboard (Shows all server devices)
                          NativeDashboardView(
                            devices: _allDevices,
                            maps: _maps,
                            logs: _logs,
                            isLoading: _isDataLoading,
                            onRefresh: _initialLoadAllData,
                            onDeviceSelected: _showDeviceInspector,
                            onNavigateToTab: (tabIdx) {
                              setState(() => _selectedNavIndex = tabIdx);
                              if (tabIdx == 4) _ensureWebviewInitialized();
                            },
                          ),

                          // 1: Native Interactive Topology Map (Docker-Matched)
                          NativeMapView(
                            maps: _maps,
                            devices: _mapDevices.isNotEmpty ? _mapDevices : _allDevices,
                            allDevices: _allDevices,
                            edges: _edges,
                            selectedMapId: _selectedMapId,
                            onMapChanged: _onMapChanged,
                            onRefresh: _initialLoadAllData,
                            onDeviceSelected: _showDeviceInspector,
                            onPingDevice: _handlePingDevice,
                            onContinuousPing: _openContinuousPingModal,
                            onEditDevice: _openEditDeviceModal,
                            onDeleteDevice: _handleDeleteDevice,
                            onUpdatePosition: _handleUpdatePosition,
                            onCreateEdge: _handleCreateEdge,
                            onUpdateEdge: _handleUpdateEdge,
                            onDeleteEdge: _handleDeleteEdge,
                            onOpenScanner: _openNetworkScanner,
                            onAddDevice: _openAddDeviceModal,
                            onAddTextNode: _handleAddTextNode,
                            isLiveActive: _isLiveActive,
                            isTabVisible: _selectedNavIndex == 1,
                            onToggleLive: (val) {
                              setState(() => _isLiveActive = val);
                              _startLiveSyncTimer();
                            },
                          ),

                          // 2: Native Device Inventory & Telemetry Manager (Shows 100% of all devices)
                          NativeDevicesView(
                            devices: _allDevices,
                            isLoading: _isDataLoading,
                            onRefresh: _initialLoadAllData,
                            onDeviceSelected: _showDeviceInspector,
                            onPingDevice: _handlePingDevice,
                            onAddDevice: _openAddDeviceModal,
                            onOpenScanner: _openNetworkScanner,
                            onEditDevice: _openEditDeviceModal,
                            onDeleteDevice: _handleDeleteDevice,
                            onOpenContinuousPing: _openContinuousPingModal,
                          ),

                          // 3: Native Audit Logs & Status Stream
                          NativeLogsView(
                            logs: _logs,
                            isLoading: _isDataLoading,
                            onRefresh: _initialLoadAllData,
                          ),

                          // 4: Embedded Web Console (Browser View)
                          WebConsoleView(
                            webviewController: _webviewController,
                            isInitialized: _isWebviewInitialized,
                            isLoading: _isWebviewLoading,
                            currentUrl: _currentWebviewUrl,
                            onNavigate: _navigateToWebPath,
                            onReload: () => _webviewController?.reload(),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDesktopSidebar() {
    final onlineCount = _allDevices.where((d) => d.isOnline).length;
    final totalCount = _allDevices.length;

    return Container(
      width: 230,
      color: const Color(0xFF090E17),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Brand Header
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
            child: Row(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.asset(
                    'assets/images/ampnm-logo.png',
                    width: 34,
                    height: 34,
                    fit: BoxFit.contain,
                    errorBuilder: (_, __, ___) => Container(
                      width: 34,
                      height: 34,
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(colors: [AppTheme.primary, AppTheme.primaryDark]),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Center(child: Icon(Icons.hub, size: 18, color: Colors.black)),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                const Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'AMPNM',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                        color: Colors.white,
                        letterSpacing: 1.0,
                      ),
                    ),
                    Text(
                      'ENTERPRISE NOC',
                      style: TextStyle(
                        fontSize: 8,
                        fontWeight: FontWeight.bold,
                        color: AppTheme.primaryGlow,
                        letterSpacing: 1.0,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),

          const Divider(color: AppTheme.border, height: 1),
          const SizedBox(height: 12),

          // Section Title
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 18, vertical: 6),
            child: Text(
              'SOFTWARE VIEWS',
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.bold,
                color: AppTheme.textMuted,
                letterSpacing: 1.0,
              ),
            ),
          ),

          // Navigation Links
          _SidebarNavItem(
            icon: Icons.dashboard_outlined,
            activeIcon: Icons.dashboard,
            label: 'Dashboard',
            isSelected: _selectedNavIndex == 0,
            onTap: () => setState(() => _selectedNavIndex = 0),
          ),
          _SidebarNavItem(
            icon: Icons.map_outlined,
            activeIcon: Icons.map,
            label: 'Live Network Map',
            isSelected: _selectedNavIndex == 1,
            badge: '${_mapDevices.length}',
            onTap: () => setState(() => _selectedNavIndex = 1),
          ),
          _SidebarNavItem(
            icon: Icons.devices_outlined,
            activeIcon: Icons.devices,
            label: 'Device Manager',
            isSelected: _selectedNavIndex == 2,
            badge: '$onlineCount / $totalCount',
            badgeColor: onlineCount == totalCount && totalCount > 0 ? AppTheme.success : AppTheme.primary,
            onTap: () => setState(() => _selectedNavIndex = 2),
          ),
          _SidebarNavItem(
            icon: Icons.receipt_long_outlined,
            activeIcon: Icons.receipt_long,
            label: 'Audit & Status Logs',
            isSelected: _selectedNavIndex == 3,
            onTap: () => setState(() => _selectedNavIndex = 3),
          ),

          const SizedBox(height: 12),
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 18, vertical: 6),
            child: Text(
              'WEB BROWSER VIEW',
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.bold,
                color: AppTheme.textMuted,
                letterSpacing: 1.0,
              ),
            ),
          ),

          _SidebarNavItem(
            icon: Icons.language_outlined,
            activeIcon: Icons.language,
            label: 'Web Console',
            badge: 'Web',
            isSelected: _selectedNavIndex == 4,
            onTap: () {
              setState(() => _selectedNavIndex = 4);
              _ensureWebviewInitialized();
            },
          ),

          const Spacer(),

          // Footer Profile & Status
          Container(
            padding: const EdgeInsets.all(12),
            margin: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppTheme.surfaceCard,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: AppTheme.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 8,
                      height: 8,
                      decoration: BoxDecoration(
                        color: _syncStatus == 'Connected' ? AppTheme.success : (_syncStatus == 'Syncing' ? AppTheme.info : AppTheme.warning),
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: _syncStatus == 'Connected' ? AppTheme.success : AppTheme.warning,
                            blurRadius: 6,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        _activeProfile.name,
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    Text(
                      '$_currentLatencyMs ms',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        color: _currentLatencyMs > 100 ? AppTheme.warning : AppTheme.success,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  _activeProfile.serverUrl,
                  style: const TextStyle(fontSize: 10, color: AppTheme.textMuted),
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: _openSettings,
                        icon: const Icon(Icons.settings, size: 12),
                        label: const Text('Settings', style: TextStyle(fontSize: 10)),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 4),
                          minimumSize: const Size(0, 26),
                        ),
                      ),
                    ),
                    const SizedBox(width: 6),
                    IconButton(
                      icon: const Icon(Icons.logout, size: 14, color: AppTheme.danger),
                      tooltip: 'Disconnect / Switch Server',
                      onPressed: _handleSwitchServer,
                      style: IconButton.styleFrom(
                        padding: const EdgeInsets.all(4),
                        minimumSize: const Size(26, 26),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SidebarNavItem extends StatelessWidget {
  final IconData icon;
  final IconData activeIcon;
  final String label;
  final bool isSelected;
  final String? badge;
  final Color? badgeColor;
  final VoidCallback onTap;

  const _SidebarNavItem({
    required this.icon,
    required this.activeIcon,
    required this.label,
    required this.isSelected,
    this.badge,
    this.badgeColor,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
      decoration: BoxDecoration(
        color: isSelected ? AppTheme.primary.withOpacity(0.15) : Colors.transparent,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: isSelected ? AppTheme.primary.withOpacity(0.4) : Colors.transparent,
        ),
      ),
      child: ListTile(
        onTap: onTap,
        dense: true,
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 0),
        leading: Icon(
          isSelected ? activeIcon : icon,
          size: 18,
          color: isSelected ? AppTheme.primaryGlow : AppTheme.textSecondary,
        ),
        title: Text(
          label,
          style: TextStyle(
            fontSize: 12,
            fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
            color: isSelected ? Colors.white : AppTheme.textSecondary,
          ),
        ),
        trailing: badge != null
            ? Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: (badgeColor ?? AppTheme.primary).withOpacity(0.2),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: (badgeColor ?? AppTheme.primary).withOpacity(0.4),
                  ),
                ),
                child: Text(
                  badge!,
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                    color: badgeColor ?? AppTheme.primaryGlow,
                  ),
                ),
              )
            : null,
      ),
    );
  }
}
