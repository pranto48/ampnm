import 'dart:convert';

class ServerProfile {
  final String id;
  final String name;
  final String url;
  final String username;
  final String? savedPassword;
  final String? sessionCookie;
  final bool autoLogin;
  final DateTime lastConnected;
  final String defaultPage; // 'index.php', 'map.php', 'devices.php'

  ServerProfile({
    required this.id,
    required this.name,
    required this.url,
    required this.username,
    this.savedPassword,
    this.sessionCookie,
    this.autoLogin = true,
    required this.lastConnected,
    this.defaultPage = 'index.php',
  });

  String get cleanUrl {
    String trimmed = url.trim();
    while (trimmed.endsWith('/')) {
      trimmed = trimmed.substring(0, trimmed.length - 1);
    }
    return trimmed;
  }

  String get serverUrl => cleanUrl;

  String get fullTargetUrl {
    final base = cleanUrl;
    final page = defaultPage.startsWith('/') ? defaultPage.substring(1) : defaultPage;
    return '$base/$page';
  }

  ServerProfile copyWith({
    String? id,
    String? name,
    String? url,
    String? username,
    String? savedPassword,
    String? sessionCookie,
    bool? autoLogin,
    DateTime? lastConnected,
    String? defaultPage,
  }) {
    return ServerProfile(
      id: id ?? this.id,
      name: name ?? this.name,
      url: url ?? this.url,
      username: username ?? this.username,
      savedPassword: savedPassword ?? this.savedPassword,
      sessionCookie: sessionCookie ?? this.sessionCookie,
      autoLogin: autoLogin ?? this.autoLogin,
      lastConnected: lastConnected ?? this.lastConnected,
      defaultPage: defaultPage ?? this.defaultPage,
    );
  }

  Map<String, dynamic> toMap() {
    return {
      'id': id,
      'name': name,
      'url': url,
      'username': username,
      'savedPassword': savedPassword,
      'sessionCookie': sessionCookie,
      'autoLogin': autoLogin,
      'lastConnected': lastConnected.toIso8601String(),
      'defaultPage': defaultPage,
    };
  }

  factory ServerProfile.fromMap(Map<String, dynamic> map) {
    return ServerProfile(
      id: map['id'] ?? '',
      name: map['name'] ?? 'AMPNM Server',
      url: map['url'] ?? '',
      username: map['username'] ?? '',
      savedPassword: map['savedPassword'],
      sessionCookie: map['sessionCookie'],
      autoLogin: map['autoLogin'] ?? true,
      lastConnected: map['lastConnected'] != null
          ? DateTime.tryParse(map['lastConnected']) ?? DateTime.now()
          : DateTime.now(),
      defaultPage: map['defaultPage'] ?? 'index.php',
    );
  }

  String toJson() => json.encode(toMap());

  factory ServerProfile.fromJson(String source) =>
      ServerProfile.fromMap(json.decode(source));
}
