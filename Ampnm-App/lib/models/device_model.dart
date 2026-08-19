import 'package:flutter/material.dart';

class DeviceModel {
  final int id;
  final String name;
  final String ip;
  final int checkPort;
  final String type;
  final String subchoice;
  final String status;
  final String description;
  final double x;
  final double y;
  final int pingInterval;
  final double? lastAvgTime;
  final int? lastTtl;
  final String? lastSeen;
  final double? cpuUsage;
  final double? memoryUsage;
  final double? diskUsage;
  final int mapId;
  final bool showLivePing;
  final double nameTextSize;
  final String? nameTextColor;
  final bool nameTextBold;
  final bool nameTextItalic;
  final String? iconUrl;

  DeviceModel({
    required this.id,
    required this.name,
    required this.ip,
    this.checkPort = 0,
    this.type = 'server',
    this.subchoice = '',
    this.status = 'offline',
    this.description = '',
    this.x = 0,
    this.y = 0,
    this.pingInterval = 5,
    this.lastAvgTime,
    this.lastTtl,
    this.lastSeen,
    this.cpuUsage,
    this.memoryUsage,
    this.diskUsage,
    this.mapId = 1,
    this.showLivePing = true,
    this.nameTextSize = 14,
    this.nameTextColor,
    this.nameTextBold = false,
    this.nameTextItalic = false,
    this.iconUrl,
  });

  factory DeviceModel.fromJson(Map<String, dynamic> json) {
    return DeviceModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      name: json['name']?.toString() ?? 'Unnamed Node',
      ip: json['ip']?.toString() ?? '',
      checkPort: int.tryParse(json['check_port']?.toString() ?? '0') ?? 0,
      type: json['type']?.toString() ?? 'server',
      subchoice: json['subchoice']?.toString() ?? '',
      status: (json['status']?.toString() ?? 'offline').toLowerCase(),
      description: json['description']?.toString() ?? '',
      x: double.tryParse(json['x']?.toString() ?? '0') ?? 0,
      y: double.tryParse(json['y']?.toString() ?? '0') ?? 0,
      pingInterval: int.tryParse(json['ping_interval']?.toString() ?? '5') ?? 5,
      lastAvgTime: double.tryParse(json['last_avg_time']?.toString() ?? ''),
      lastTtl: int.tryParse(json['last_ttl']?.toString() ?? ''),
      lastSeen: json['last_seen']?.toString(),
      cpuUsage: double.tryParse(json['cpu_usage']?.toString() ?? ''),
      memoryUsage: double.tryParse(json['memory_usage']?.toString() ?? ''),
      diskUsage: double.tryParse(json['disk_usage']?.toString() ?? ''),
      mapId: int.tryParse(json['map_id']?.toString() ?? '1') ?? 1,
      showLivePing: json['show_live_ping'] == 1 || json['show_live_ping'] == true || json['show_live_ping'] == '1',
      nameTextSize: double.tryParse(json['name_text_size']?.toString() ?? '14') ?? 14,
      nameTextColor: json['name_text_color']?.toString(),
      nameTextBold: json['name_text_bold'] == 1 || json['name_text_bold'] == true || json['name_text_bold'] == '1',
      nameTextItalic: json['name_text_italic'] == 1 || json['name_text_italic'] == true || json['name_text_italic'] == '1',
      iconUrl: json['icon_url']?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'ip': ip,
      'check_port': checkPort,
      'type': type,
      'subchoice': subchoice,
      'status': status,
      'description': description,
      'x': x,
      'y': y,
      'ping_interval': pingInterval,
      'last_avg_time': lastAvgTime,
      'last_ttl': lastTtl,
      'last_seen': lastSeen,
      'cpu_usage': cpuUsage,
      'memory_usage': memoryUsage,
      'disk_usage': diskUsage,
      'map_id': mapId,
      'show_live_ping': showLivePing ? 1 : 0,
      'name_text_size': nameTextSize,
      'name_text_color': nameTextColor,
      'name_text_bold': nameTextBold ? 1 : 0,
      'name_text_italic': nameTextItalic ? 1 : 0,
      'icon_url': iconUrl,
    };
  }

  bool get isOnline => status == 'online';
  bool get isOffline => status == 'offline' || status == 'down' || status == 'unknown';
  bool get isWarning => status == 'warning';
  bool get isCritical => status == 'critical';
  bool get isTextNode => type.toLowerCase() == 'text';

  /// Colors matching Docker web `MapApp.config.statusColorMap`
  Color get statusColor {
    switch (status) {
      case 'online':
        return const Color(0xFF2ECC71); // #2ECC71 Emerald Green
      case 'warning':
        return const Color(0xFFF1C40F); // #F1C40F Amber Yellow
      case 'critical':
        return const Color(0xFFE74C3C); // #E74C3C Red
      case 'offline':
      case 'unknown':
      default:
        return const Color(0xFF95A5A6); // #95A5A6 Muted Slate
    }
  }

  /// Numerical IPv4 integer for proper ascending/descending IP sorting
  int get ipNumeric {
    if (ip.isEmpty) return 0;
    try {
      final cleanIp = ip.split(':').first.trim();
      final parts = cleanIp.split('.');
      if (parts.length == 4) {
        final o1 = int.parse(parts[0]);
        final o2 = int.parse(parts[1]);
        final o3 = int.parse(parts[2]);
        final o4 = int.parse(parts[3]);
        return (o1 << 24) | (o2 << 16) | (o3 << 8) | o4;
      }
    } catch (_) {}
    return 0;
  }

  /// Check if device likely supports MikroTik Winbox
  bool get isMikrotikOrRouter =>
      type.toLowerCase().contains('router') ||
      type.toLowerCase().contains('mikrotik') ||
      subchoice.toLowerCase().contains('mikrotik') ||
      checkPort == 8291 ||
      checkPort == 8728;

  /// Check if device likely supports SSH
  bool get isSshCapable =>
      type.toLowerCase().contains('server') ||
      type.toLowerCase().contains('linux') ||
      type.toLowerCase().contains('router') ||
      type.toLowerCase().contains('switch') ||
      type.toLowerCase().contains('firewall') ||
      checkPort == 22;

  /// Check if device likely supports Windows Remote Desktop (RDP)
  bool get isRdpCapable =>
      type.toLowerCase().contains('desktop') ||
      type.toLowerCase().contains('pc') ||
      type.toLowerCase().contains('windows') ||
      type.toLowerCase().contains('workstation') ||
      checkPort == 3389;

  /// Check if device has Web GUI
  bool get isWebCapable =>
      ip.isNotEmpty && !isTextNode &&
      (checkPort == 80 || checkPort == 443 || checkPort == 8080 || checkPort == 8443 || checkPort == 0);

  /// Icon mapping exactly matching Docker web `MapApp.config.iconMap` & `device_icons.php`
  IconData get typeIcon {
    final t = type.toLowerCase().trim();
    if (t.contains('wifi') || t.contains('ap') || t.contains('wireless') || t.contains('access-point')) {
      return Icons.wifi_tethering_rounded;
    }
    if (t.contains('router') || t.contains('mikrotik') || t.contains('gateway')) {
      return Icons.router_rounded;
    }
    if (t.contains('switch') || t.contains('cisco') || t.contains('hub') || t.contains('vlan')) {
      return Icons.alt_route_rounded;
    }
    if (t.contains('server') || t.contains('host') || t.contains('node') || t.contains('blade')) {
      return Icons.dns_rounded;
    }
    if (t.contains('firewall') || t.contains('security') || t.contains('utm') || t.contains('pfsense') || t.contains('sophos')) {
      return Icons.shield_rounded;
    }
    if (t.contains('camera') || t.contains('cctv') || t.contains('cam') || t.contains('dvr') || t.contains('nvr')) {
      return Icons.videocam_rounded;
    }
    if (t.contains('print')) {
      return Icons.print_rounded;
    }
    if (t.contains('nas') || t.contains('storage') || t.contains('san') || t.contains('synology') || t.contains('qnap')) {
      return Icons.save_rounded;
    }
    if (t.contains('phone') || t.contains('voip') || t.contains('ipphone')) {
      return Icons.phone_in_talk_rounded;
    }
    if (t.contains('punch') || t.contains('bio') || t.contains('finger') || t.contains('attendance')) {
      return Icons.fingerprint_rounded;
    }
    if (t.contains('radio') || t.contains('tower') || t.contains('antenna') || t.contains('ptp')) {
      return Icons.cell_tower_rounded;
    }
    if (t.contains('rack')) {
      return Icons.view_headline_rounded;
    }
    if (t.contains('laptop') || t.contains('notebook')) {
      return Icons.laptop_mac_rounded;
    }
    if (t.contains('tab') || t.contains('ipad')) {
      return Icons.tablet_mac_rounded;
    }
    if (t.contains('mobile') || t.contains('phone_android')) {
      return Icons.smartphone_rounded;
    }
    if (t.contains('cloud') || t.contains('wan') || t.contains('internet') || t.contains('isp')) {
      return Icons.cloud_rounded;
    }
    if (t.contains('database') || t.contains('db') || t.contains('sql') || t.contains('oracle')) {
      return Icons.storage_rounded;
    }
    if (t.contains('box') || t.contains('unit') || t.contains('group')) {
      return Icons.check_box_outline_blank_rounded;
    }
    if (t.contains('text') || t.contains('label') || t.contains('note')) {
      return Icons.text_fields_rounded;
    }
    if (t.contains('desktop') || t.contains('pc') || t.contains('computer') || t.contains('workstation')) {
      return Icons.desktop_windows_rounded;
    }
    return Icons.devices_other_rounded;
  }
}
