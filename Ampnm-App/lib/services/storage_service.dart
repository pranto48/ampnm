import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/device_model.dart';
import '../models/edge_model.dart';
import '../models/map_model.dart';
import '../models/server_profile.dart';
import '../models/status_log_model.dart';

class StorageService {
  static const String _keyProfiles = 'ampnm_server_profiles';
  static const String _keyActiveProfileId = 'ampnm_active_profile_id';
  static const String _keyZoomLevel = 'ampnm_zoom_level';
  static const String _keyStartMinimized = 'ampnm_start_minimized';
  static const String _keyPollInterval = 'ampnm_poll_interval';
  static const String _keySoundAlerts = 'ampnm_sound_alerts';

  final SharedPreferences _prefs;

  StorageService(this._prefs);

  static Future<StorageService> init() async {
    final prefs = await SharedPreferences.getInstance();
    return StorageService(prefs);
  }

  // Server Profiles
  List<ServerProfile> getProfiles() {
    final raw = _prefs.getString(_keyProfiles);
    if (raw == null || raw.isEmpty) return [];
    try {
      final List<dynamic> list = json.decode(raw);
      return list.map((e) => ServerProfile.fromMap(e)).toList();
    } catch (_) {
      return [];
    }
  }

  Future<void> saveProfile(ServerProfile profile) async {
    final profiles = getProfiles();
    final index = profiles.indexWhere((p) => p.id == profile.id || p.cleanUrl == profile.cleanUrl);
    if (index >= 0) {
      profiles[index] = profile;
    } else {
      profiles.add(profile);
    }
    await _prefs.setString(_keyProfiles, json.encode(profiles.map((p) => p.toMap()).toList()));
    await setActiveProfileId(profile.id);
  }

  Future<void> deleteProfile(String id) async {
    final profiles = getProfiles();
    profiles.removeWhere((p) => p.id == id);
    await _prefs.setString(_keyProfiles, json.encode(profiles.map((p) => p.toMap()).toList()));
    
    if (getActiveProfileId() == id) {
      if (profiles.isNotEmpty) {
        await setActiveProfileId(profiles.first.id);
      } else {
        await _prefs.remove(_keyActiveProfileId);
      }
    }
  }

  String? getActiveProfileId() {
    return _prefs.getString(_keyActiveProfileId);
  }

  Future<void> setActiveProfileId(String id) async {
    await _prefs.setString(_keyActiveProfileId, id);
  }

  ServerProfile? getActiveProfile() {
    final id = getActiveProfileId();
    final profiles = getProfiles();
    if (profiles.isEmpty) return null;
    if (id == null) return profiles.first;
    return profiles.firstWhere((p) => p.id == id, orElse: () => profiles.first);
  }

  // Local Data Cache (Offline Fallback & Instant App Start)
  String _cacheKey(String profileId, String type, [dynamic subKey]) {
    return 'ampnm_cache_${profileId}_${type}_${subKey ?? ''}';
  }

  // Cached Maps
  List<MapModel> getCachedMaps(String profileId) {
    final raw = _prefs.getString(_cacheKey(profileId, 'maps'));
    if (raw == null || raw.isEmpty) return [];
    try {
      final List<dynamic> list = json.decode(raw);
      return list.map((e) => MapModel.fromJson(e as Map<String, dynamic>)).toList();
    } catch (_) {
      return [];
    }
  }

  Future<void> saveCachedMaps(String profileId, List<MapModel> maps) async {
    try {
      final list = maps.map((m) => m.toJson()).toList();
      await _prefs.setString(_cacheKey(profileId, 'maps'), json.encode(list));
    } catch (_) {}
  }

  // Cached Devices
  List<DeviceModel> getCachedDevices(String profileId, int mapId) {
    final raw = _prefs.getString(_cacheKey(profileId, 'devices', mapId));
    if (raw == null || raw.isEmpty) return [];
    try {
      final List<dynamic> list = json.decode(raw);
      return list.map((e) => DeviceModel.fromJson(e as Map<String, dynamic>)).toList();
    } catch (_) {
      return [];
    }
  }

  Future<void> saveCachedDevices(String profileId, int mapId, List<DeviceModel> devices) async {
    try {
      final list = devices.map((d) => d.toJson()).toList();
      await _prefs.setString(_cacheKey(profileId, 'devices', mapId), json.encode(list));
    } catch (_) {}
  }

  // Cached Edges
  List<EdgeModel> getCachedEdges(String profileId, int mapId) {
    final raw = _prefs.getString(_cacheKey(profileId, 'edges', mapId));
    if (raw == null || raw.isEmpty) return [];
    try {
      final List<dynamic> list = json.decode(raw);
      return list.map((e) => EdgeModel.fromJson(e as Map<String, dynamic>)).toList();
    } catch (_) {
      return [];
    }
  }

  Future<void> saveCachedEdges(String profileId, int mapId, List<EdgeModel> edges) async {
    try {
      final list = edges.map((e) => e.toJson()).toList();
      await _prefs.setString(_cacheKey(profileId, 'edges', mapId), json.encode(list));
    } catch (_) {}
  }

  // Cached Status Logs
  List<StatusLogModel> getCachedLogs(String profileId) {
    final raw = _prefs.getString(_cacheKey(profileId, 'logs'));
    if (raw == null || raw.isEmpty) return [];
    try {
      final List<dynamic> list = json.decode(raw);
      return list.map((e) => StatusLogModel.fromJson(e as Map<String, dynamic>)).toList();
    } catch (_) {
      return [];
    }
  }

  Future<void> saveCachedLogs(String profileId, List<StatusLogModel> logs) async {
    try {
      final list = logs.map((l) => l.toJson()).toList();
      await _prefs.setString(_cacheKey(profileId, 'logs'), json.encode(list));
    } catch (_) {}
  }

  // User Preferences
  double getZoomLevel() => _prefs.getDouble(_keyZoomLevel) ?? 1.0;
  Future<void> setZoomLevel(double zoom) => _prefs.setDouble(_keyZoomLevel, zoom);

  int getPollInterval() => _prefs.getInt(_keyPollInterval) ?? 2;
  Future<void> setPollInterval(int seconds) => _prefs.setInt(_keyPollInterval, seconds);

  bool getSoundAlerts() => _prefs.getBool(_keySoundAlerts) ?? true;
  Future<void> setSoundAlerts(bool enabled) => _prefs.setBool(_keySoundAlerts, enabled);

  bool getStartMinimized() => _prefs.getBool(_keyStartMinimized) ?? false;
  Future<void> setStartMinimized(bool val) => _prefs.setBool(_keyStartMinimized, val);

  Future<void> clearAll() async {
    await _prefs.clear();
  }
}
