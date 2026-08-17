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
    };
  }

  bool get isOnline => status == 'online';
  bool get isOffline => status == 'offline' || status == 'down' || status == 'unknown';
  bool get isWarning => status == 'warning';
  bool get isCritical => status == 'critical';

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

  /// Icon mapping matching Docker web `MapApp.config.iconMap`
  IconData get typeIcon {
    final t = type.toLowerCase();
    if (t.contains('router') || t == 'wifi-router') return Icons.router;
    if (t.contains('switch')) return Icons.alt_route;
    if (t.contains('server') || t.contains('host')) return Icons.dns;
    if (t.contains('firewall')) return Icons.security;
    if (t.contains('wifi') || t.contains('ap')) return Icons.wifi;
    if (t.contains('camera') || t.contains('cctv')) return Icons.videocam;
    if (t.contains('printer')) return Icons.print;
    if (t.contains('nas') || t.contains('storage')) return Icons.storage;
    if (t.contains('ipphone') || t.contains('phone')) return Icons.phone_in_talk;
    if (t.contains('punchdevice') || t.contains('biometric')) return Icons.fingerprint;
    if (t.contains('radio') || t.contains('tower')) return Icons.cell_tower;
    if (t.contains('laptop')) return Icons.laptop;
    if (t.contains('tablet')) return Icons.tablet_mac;
    if (t.contains('mobile')) return Icons.smartphone;
    if (t.contains('cloud') || t.contains('wan')) return Icons.cloud;
    if (t.contains('database') || t.contains('db')) return Icons.storage_rounded;
    if (t.contains('rack')) return Icons.shelves;
    if (t.contains('box')) return Icons.check_box_outline_blank;
    if (t.contains('desktop') || t.contains('pc')) return Icons.computer;
    return Icons.devices_other;
  }
}
