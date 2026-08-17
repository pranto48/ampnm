import 'package:flutter/material.dart';

class StatusLogModel {
  final int id;
  final int? deviceId;
  final String? deviceName;
  final String? deviceIp;
  final String status;
  final String message;
  final String timestamp;

  StatusLogModel({
    required this.id,
    this.deviceId,
    this.deviceName,
    this.deviceIp,
    required this.status,
    required this.message,
    required this.timestamp,
  });

  Color get statusColor {
    final s = status.toLowerCase();
    if (s == 'online' || s == 'up') return const Color(0xFF10B981);
    if (s == 'offline' || s == 'down' || s == 'critical') return const Color(0xFFEF4444);
    if (s == 'warning' || s == 'degraded') return const Color(0xFFF59E0B);
    return const Color(0xFF06B6D4);
  }

  factory StatusLogModel.fromJson(Map<String, dynamic> json) {
    return StatusLogModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      deviceId: int.tryParse(json['device_id']?.toString() ?? ''),
      deviceName: json['device_name']?.toString() ?? json['hostname']?.toString(),
      deviceIp: json['ip']?.toString() ?? json['device_ip']?.toString(),
      status: json['status']?.toString() ?? 'info',
      message: json['message']?.toString() ?? json['event']?.toString() ?? '',
      timestamp: json['timestamp']?.toString() ?? json['created_at']?.toString() ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'device_id': deviceId,
      'device_name': deviceName,
      'ip': deviceIp,
      'status': status,
      'message': message,
      'timestamp': timestamp,
    };
  }
}
