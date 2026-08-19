import 'dart:convert';
import 'package:http/http.dart' as http;

import '../models/device_model.dart';
import '../models/edge_model.dart';
import '../models/map_model.dart';
import '../models/status_log_model.dart';

class ConnectionTestResult {
  final bool isSuccess;
  final int latencyMs;
  final String? version;
  final String? errorMessage;
  final bool isAuthRequired;

  ConnectionTestResult({
    required this.isSuccess,
    required this.latencyMs,
    this.version,
    this.errorMessage,
    this.isAuthRequired = true,
  });
}

class LoginResult {
  final bool isSuccess;
  final String? sessionCookie;
  final String? errorMessage;
  final String? userRole;
  final String? displayName;

  LoginResult({
    required this.isSuccess,
    this.sessionCookie,
    this.errorMessage,
    this.userRole,
    this.displayName,
  });
}

class ServerService {
  static final http.Client _client = http.Client();

  /// Pings and validates that the AMPNM server is alive and reachable
  static Future<ConnectionTestResult> testConnection(String serverUrl) async {
    final cleanUrl = sanitizeUrl(serverUrl);
    if (cleanUrl.isEmpty) {
      return ConnectionTestResult(
        isSuccess: false,
        latencyMs: 0,
        errorMessage: 'Server URL cannot be empty.',
      );
    }

    final stopwatch = Stopwatch()..start();
    try {
      final healthUri = Uri.parse('$cleanUrl/api.php?action=health');
      final response = await _client.get(healthUri).timeout(const Duration(seconds: 4));
      stopwatch.stop();

      if (response.statusCode == 200) {
        String? version;
        try {
          final data = json.decode(response.body);
          version = data['version'] ?? 'v1.2.1';
        } catch (_) {}

        return ConnectionTestResult(
          isSuccess: true,
          latencyMs: stopwatch.elapsedMilliseconds,
          version: version ?? 'AMPNM Online',
        );
      }

      final loginUri = Uri.parse('$cleanUrl/login.php');
      final loginResp = await _client.get(loginUri).timeout(const Duration(seconds: 4));
      if (loginResp.statusCode == 200 || loginResp.statusCode == 302) {
        return ConnectionTestResult(
          isSuccess: true,
          latencyMs: stopwatch.elapsedMilliseconds,
          version: 'AMPNM Server Ready',
        );
      }

      return ConnectionTestResult(
        isSuccess: false,
        latencyMs: stopwatch.elapsedMilliseconds,
        errorMessage: 'Server returned HTTP ${response.statusCode}',
      );
    } catch (e) {
      stopwatch.stop();
      return ConnectionTestResult(
        isSuccess: false,
        latencyMs: stopwatch.elapsedMilliseconds,
        errorMessage: 'Cannot connect to server: ${e.toString().replaceAll('Exception: ', '')}',
      );
    }
  }

  /// Performs authenticating login against the AMPNM server
  static Future<LoginResult> login({
    required String serverUrl,
    required String username,
    required String password,
  }) async {
    final cleanUrl = sanitizeUrl(serverUrl);
    final loginUri = Uri.parse('$cleanUrl/login.php');

    try {
      final response = await _client.post(
        loginUri,
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: {
          'username': username.trim(),
          'password': password,
        },
      ).timeout(const Duration(seconds: 6));

      final rawCookie = response.headers['set-cookie'] ?? '';
      final sessionCookie = _extractSessionCookie(rawCookie);

      final isRedirect = response.statusCode == 302 || response.statusCode == 301;

      if ((isRedirect || response.statusCode == 200) && sessionCookie != null && sessionCookie.isNotEmpty) {
        if (!response.body.contains('Invalid username or password')) {
          return LoginResult(
            isSuccess: true,
            sessionCookie: sessionCookie,
            displayName: username,
          );
        }
      }

      if (response.body.contains('Invalid username or password')) {
        return LoginResult(
          isSuccess: false,
          errorMessage: 'Invalid username or password.',
        );
      }

      if (sessionCookie != null && sessionCookie.isNotEmpty) {
        return LoginResult(
          isSuccess: true,
          sessionCookie: sessionCookie,
          displayName: username,
        );
      }

      return LoginResult(
        isSuccess: false,
        errorMessage: 'Authentication failed. Please verify credentials.',
      );
    } catch (e) {
      return LoginResult(
        isSuccess: false,
        errorMessage: 'Login failed: ${e.toString().replaceAll('Exception: ', '')}',
      );
    }
  }

  /// Fetches all maps from the AMPNM server
  static Future<List<MapModel>> getMaps({
    required String serverUrl,
    required String sessionCookie,
  }) async {
    final cleanUrl = sanitizeUrl(serverUrl);
    final uri = Uri.parse('$cleanUrl/api.php?action=get_maps');
    try {
      final response = await _client.get(
        uri,
        headers: _buildHeaders(sessionCookie),
      ).timeout(const Duration(seconds: 5));

      if (response.statusCode == 200) {
        final dynamic data = json.decode(response.body);
        List<dynamic> list = [];
        if (data is List) {
          list = data;
        } else if (data is Map) {
          if (data['maps'] is List) {
            list = data['maps'];
          } else if (data['data'] is List) {
            list = data['data'];
          }
        }
        return list.map((e) => MapModel.fromJson(e as Map<String, dynamic>)).toList();
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  /// Fetches ALL devices from Docker server (without map_id filter)
  static Future<List<DeviceModel>> getAllDevices({
    required String serverUrl,
    required String sessionCookie,
  }) async {
    final cleanUrl = sanitizeUrl(serverUrl);
    final uri = Uri.parse('$cleanUrl/api.php?action=get_devices');

    try {
      final response = await _client.get(
        uri,
        headers: _buildHeaders(sessionCookie),
      ).timeout(const Duration(seconds: 6));

      if (response.statusCode == 200) {
        final dynamic data = json.decode(response.body);
        List<dynamic> list = [];
        if (data is List) {
          list = data;
        } else if (data is Map) {
          if (data['devices'] is List) {
            list = data['devices'];
          } else if (data['data'] is List) {
            list = data['data'];
          } else if (data['results'] is List) {
            list = data['results'];
          }
        }
        return list.map((e) => DeviceModel.fromJson(e as Map<String, dynamic>)).toList();
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  /// Fetches devices for a specific map (or all devices if mapId is null)
  static Future<List<DeviceModel>> getDevices({
    required String serverUrl,
    required String sessionCookie,
    int? mapId,
  }) async {
    if (mapId == null) {
      return getAllDevices(serverUrl: serverUrl, sessionCookie: sessionCookie);
    }

    final cleanUrl = sanitizeUrl(serverUrl);
    final uri = Uri.parse('$cleanUrl/api.php?action=get_devices&map_id=$mapId');

    try {
      final response = await _client.get(
        uri,
        headers: _buildHeaders(sessionCookie),
      ).timeout(const Duration(seconds: 6));

      if (response.statusCode == 200) {
        final dynamic data = json.decode(response.body);
        List<dynamic> list = [];
        if (data is List) {
          list = data;
        } else if (data is Map) {
          if (data['devices'] is List) {
            list = data['devices'];
          } else if (data['data'] is List) {
            list = data['data'];
          } else if (data['results'] is List) {
            list = data['results'];
          }
        }
        return list.map((e) => DeviceModel.fromJson(e as Map<String, dynamic>)).toList();
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  /// Creates a new device on the server
  static Future<Map<String, dynamic>?> createDevice({
    required String serverUrl,
    required String sessionCookie,
    required String name,
    required String ip,
    int checkPort = 0,
    String type = 'server',
    String subchoice = '',
    String? iconUrl,
    String description = '',
    double x = 300,
    double y = 300,
    int pingInterval = 5,
    int mapId = 1,
  }) async {
    final cleanUrl = sanitizeUrl(serverUrl);
    final uri = Uri.parse('$cleanUrl/api.php?action=create_device');

    try {
      final payload = <String, dynamic>{
        'name': name,
        'ip': ip,
        'check_port': checkPort,
        'type': type,
        'subchoice': subchoice,
        'description': description,
        'x': x,
        'y': y,
        'ping_interval': pingInterval,
        'map_id': mapId,
      };
      if (iconUrl != null && iconUrl.isNotEmpty) {
        payload['icon_url'] = iconUrl;
      }

      final response = await _client.post(
        uri,
        headers: _buildHeaders(sessionCookie, isJson: true),
        body: json.encode(payload),
      ).timeout(const Duration(seconds: 6));

      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      return null;
    } catch (e) {
      return null;
    }
  }

  /// Updates an existing device on the server
  static Future<Map<String, dynamic>?> updateDevice({
    required String serverUrl,
    required String sessionCookie,
    required int id,
    required String name,
    required String ip,
    int checkPort = 0,
    String type = 'server',
    String subchoice = '',
    String? iconUrl,
    String description = '',
    int pingInterval = 5,
    int mapId = 1,
  }) async {
    final cleanUrl = sanitizeUrl(serverUrl);
    final uri = Uri.parse('$cleanUrl/api.php?action=update_device');

    try {
      final updates = <String, dynamic>{
        'name': name,
        'ip': ip,
        'check_port': checkPort,
        'type': type,
        'subchoice': subchoice,
        'description': description,
        'ping_interval': pingInterval,
        'map_id': mapId,
      };
      if (iconUrl != null) {
        updates['icon_url'] = iconUrl;
      }

      final response = await _client.post(
        uri,
        headers: _buildHeaders(sessionCookie, isJson: true),
        body: json.encode({
          'id': id,
          'updates': updates,
        }),
      ).timeout(const Duration(seconds: 6));

      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      return null;
    } catch (e) {
      return null;
    }
  }

  /// Updates device canvas coordinates (x, y)
  static Future<bool> updateDeviceCoordinates({
    required String serverUrl,
    required String sessionCookie,
    required int id,
    required double x,
    required double y,
  }) async {
    final cleanUrl = sanitizeUrl(serverUrl);
    final uri = Uri.parse('$cleanUrl/api.php?action=update_device');

    try {
      final response = await _client.post(
        uri,
        headers: _buildHeaders(sessionCookie, isJson: true),
        body: json.encode({
          'id': id,
          'updates': {
            'x': x,
            'y': y,
          }
        }),
      ).timeout(const Duration(seconds: 4));

      return response.statusCode == 200;
    } catch (e) {
      return false;
    }
  }

  /// Deletes a device from the server
  static Future<bool> deleteDevice({
    required String serverUrl,
    required String sessionCookie,
    required int id,
  }) async {
    final cleanUrl = sanitizeUrl(serverUrl);
    final uri = Uri.parse('$cleanUrl/api.php?action=delete_device');

    try {
      final response = await _client.post(
        uri,
        headers: _buildHeaders(sessionCookie, isJson: true),
        body: json.encode({'id': id}),
      ).timeout(const Duration(seconds: 6));

      return response.statusCode == 200;
    } catch (e) {
      return false;
    }
  }

  /// Fetches topology edges (connections) for a given map
  static Future<List<EdgeModel>> getEdges({
    required String serverUrl,
    required String sessionCookie,
    required int mapId,
  }) async {
    final cleanUrl = sanitizeUrl(serverUrl);
    final uri = Uri.parse('$cleanUrl/api.php?action=get_edges&map_id=$mapId');

    try {
      final response = await _client.get(
        uri,
        headers: _buildHeaders(sessionCookie),
      ).timeout(const Duration(seconds: 5));

      if (response.statusCode == 200) {
        final dynamic data = json.decode(response.body);
        List<dynamic> list = [];
        if (data is List) {
          list = data;
        } else if (data is Map) {
          if (data['edges'] is List) {
            list = data['edges'];
          } else if (data['data'] is List) {
            list = data['data'];
          }
        }
        return list.map((e) => EdgeModel.fromJson(e as Map<String, dynamic>)).toList();
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  /// Creates a new connection link (edge) between two devices
  static Future<Map<String, dynamic>?> createEdge({
    required String serverUrl,
    required String sessionCookie,
    required int mapId,
    required int sourceId,
    required int targetId,
    String connectionType = 'cat6',
    String? label,
    String? color,
    double thickness = 2.0,
  }) async {
    final cleanUrl = sanitizeUrl(serverUrl);
    final uri = Uri.parse('$cleanUrl/api.php?action=create_edge');

    try {
      final response = await _client.post(
        uri,
        headers: _buildHeaders(sessionCookie, isJson: true),
        body: json.encode({
          'map_id': mapId,
          'source_id': sourceId,
          'target_id': targetId,
          'connection_type': connectionType,
          'label': label ?? '',
          'color': color ?? '',
          'thickness': thickness,
        }),
      ).timeout(const Duration(seconds: 6));

      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      return null;
    } catch (e) {
      return null;
    }
  }

  /// Updates an existing connection link (edge)
  static Future<Map<String, dynamic>?> updateEdge({
    required String serverUrl,
    required String sessionCookie,
    required int id,
    required String connectionType,
    String? label,
    String? color,
    double thickness = 2.0,
  }) async {
    final cleanUrl = sanitizeUrl(serverUrl);
    final uri = Uri.parse('$cleanUrl/api.php?action=update_edge');

    try {
      final response = await _client.post(
        uri,
        headers: _buildHeaders(sessionCookie, isJson: true),
        body: json.encode({
          'id': id,
          'connection_type': connectionType,
          'label': label ?? '',
          'color': color ?? '',
          'thickness': thickness.toInt(),
        }),
      ).timeout(const Duration(seconds: 6));

      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      return null;
    } catch (e) {
      return null;
    }
  }


  /// Deletes a connection link (edge)
  static Future<bool> deleteEdge({
    required String serverUrl,
    required String sessionCookie,
    required int id,
  }) async {
    final cleanUrl = sanitizeUrl(serverUrl);
    final uri = Uri.parse('$cleanUrl/api.php?action=delete_edge');

    try {
      final response = await _client.post(
        uri,
        headers: _buildHeaders(sessionCookie, isJson: true),
        body: json.encode({'id': id}),
      ).timeout(const Duration(seconds: 6));

      return response.statusCode == 200;
    } catch (e) {
      return false;
    }
  }

  /// Scans a subnet / IP range for active devices
  static Future<List<Map<String, dynamic>>> scanNetwork({
    required String serverUrl,
    required String sessionCookie,
    required String startIp,
    required String endIp,
    int port = 0,
  }) async {
    final cleanUrl = sanitizeUrl(serverUrl);
    final uri = Uri.parse('$cleanUrl/api.php?action=scan_network');

    try {
      final response = await _client.post(
        uri,
        headers: _buildHeaders(sessionCookie, isJson: true),
        body: json.encode({
          'start_ip': startIp,
          'end_ip': endIp,
          'port': port,
        }),
      ).timeout(const Duration(seconds: 25));

      if (response.statusCode == 200) {
        final dynamic data = json.decode(response.body);
        if (data is List) {
          return List<Map<String, dynamic>>.from(data);
        } else if (data is Map && data['results'] is List) {
          return List<Map<String, dynamic>>.from(data['results']);
        }
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  /// Fetches status logs / recent alerts
  static Future<List<StatusLogModel>> getStatusLogs({
    required String serverUrl,
    required String sessionCookie,
    int? mapId,
    int limit = 50,
  }) async {
    final cleanUrl = sanitizeUrl(serverUrl);
    final mapParam = mapId != null ? '&map_id=$mapId' : '&map_id=1';
    final uri = Uri.parse('$cleanUrl/api.php?action=get_dashboard_data$mapParam');

    try {
      final response = await _client.get(
        uri,
        headers: _buildHeaders(sessionCookie),
      ).timeout(const Duration(seconds: 5));

      if (response.statusCode == 200) {
        final dynamic data = json.decode(response.body);
        List<dynamic> list = [];
        if (data is List) {
          list = data;
        } else if (data is Map) {
          if (data['recent_activity'] is List) {
            list = data['recent_activity'];
          } else if (data['logs'] is List) {
            list = data['logs'];
          }
        }
        return list.map((e) => StatusLogModel.fromJson(e as Map<String, dynamic>)).toList();
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  /// Triggers a single live ping for a device
  static Future<Map<String, dynamic>?> pingDevice({
    required String serverUrl,
    required String sessionCookie,
    required int deviceId,
    required String ip,
    int port = 0,
  }) async {
    final cleanUrl = sanitizeUrl(serverUrl);
    final uri = Uri.parse('$cleanUrl/api.php?action=ping_device');

    try {
      final response = await _client.post(
        uri,
        headers: _buildHeaders(sessionCookie, isJson: true),
        body: json.encode({
          'id': deviceId,
          'ip': ip,
          'check_port': port,
        }),
      ).timeout(const Duration(seconds: 8));

      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      return null;
    } catch (e) {
      return null;
    }
  }

  /// Triggers a bulk ping of all devices on a map
  static Future<bool> pingAllDevices({
    required String serverUrl,
    required String sessionCookie,
    required int mapId,
  }) async {
    final cleanUrl = sanitizeUrl(serverUrl);
    final uri = Uri.parse('$cleanUrl/api.php?action=ping_all_devices');

    try {
      final response = await _client.post(
        uri,
        headers: _buildHeaders(sessionCookie, isJson: true),
        body: json.encode({'map_id': mapId}),
      ).timeout(const Duration(seconds: 15));

      return response.statusCode == 200;
    } catch (e) {
      return false;
    }
  }

  static Map<String, String> _buildHeaders(String sessionCookie, {bool isJson = false}) {
    final cleanCookie = sessionCookie.trim();
    final cookieHeader = cleanCookie.startsWith('PHPSESSID=') ? cleanCookie : 'PHPSESSID=$cleanCookie';
    final headers = <String, String>{
      'Cookie': cookieHeader,
      'Accept': 'application/json',
    };
    if (isJson) {
      headers['Content-Type'] = 'application/json';
    }
    return headers;
  }

  static String sanitizeUrl(String url) {
    String trimmed = url.trim();
    if (!trimmed.startsWith('http://') && !trimmed.startsWith('https://')) {
      trimmed = 'http://$trimmed';
    }
    while (trimmed.endsWith('/')) {
      trimmed = trimmed.substring(0, trimmed.length - 1);
    }
    return trimmed;
  }

  static String? _extractSessionCookie(String rawCookie) {
    if (rawCookie.isEmpty) return null;
    final match = RegExp(r'PHPSESSID=([^;,\s]+)').firstMatch(rawCookie);
    if (match != null) {
      return match.group(1);
    }
    return rawCookie.split(';').firstOrNull?.replaceFirst('PHPSESSID=', '');
  }
}
