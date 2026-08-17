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

  /// Icon mapping exactly matching Docker web `MapApp.config.iconMap` & `device_icons.php`
  IconData get typeIcon {
    final t = type.toLowerCase();
    if (t == 'router') return Icons.router_rounded;
    if (t == 'wifi-router') return Icons.wifi_tethering_rounded;
    if (t == 'switch') return Icons.alt_route_rounded;
    if (t == 'server') return Icons.dns_rounded;
    if (t == 'firewall') return Icons.shield_rounded;
    if (t == 'camera') return Icons.videocam_rounded;
    if (t == 'printer') return Icons.print_rounded;
    if (t == 'nas') return Icons.save_rounded;
    if (t == 'ipphone') return Icons.phone_in_talk_rounded;
    if (t == 'punchdevice') return Icons.fingerprint_rounded;
    if (t == 'radio' || t == 'radio-tower') return Icons.cell_tower_rounded;
    if (t == 'rack') return Icons.view_headline_rounded;
    if (t == 'laptop') return Icons.laptop_mac_rounded;
    if (t == 'tablet') return Icons.tablet_mac_rounded;
    if (t == 'mobile') return Icons.smartphone_rounded;
    if (t == 'cloud') return Icons.cloud_rounded;
    if (t == 'database') return Icons.storage_rounded;
    if (t == 'box') return Icons.check_box_outline_blank_rounded;
    if (t == 'text') return Icons.text_fields_rounded;
    if (t == 'desktop' || t == 'pc' || t == 'other') return Icons.desktop_windows_rounded;
    return Icons.devices_other_rounded;
  }
}
