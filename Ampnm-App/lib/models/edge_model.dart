import 'package:flutter/material.dart';

class EdgeModel {
  final int id;
  final int mapId;
  final int sourceId;
  final int targetId;
  final String connectionType;
  final String? label;
  final String? color;
  final double thickness;

  EdgeModel({
    required this.id,
    required this.mapId,
    required this.sourceId,
    required this.targetId,
    this.connectionType = 'cat6',
    this.label,
    this.color,
    this.thickness = 2.0,
  });

  factory EdgeModel.fromJson(Map<String, dynamic> json) {
    return EdgeModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      mapId: int.tryParse(json['map_id']?.toString() ?? '0') ?? 0,
      sourceId: int.tryParse(json['source_id']?.toString() ?? (json['from']?.toString() ?? '0')) ?? 0,
      targetId: int.tryParse(json['target_id']?.toString() ?? (json['to']?.toString() ?? '0')) ?? 0,
      connectionType: json['connection_type']?.toString() ?? 'cat6',
      label: json['label']?.toString(),
      color: json['color']?.toString(),
      thickness: double.tryParse(json['thickness']?.toString() ?? '2.0') ?? 2.0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'map_id': mapId,
      'source_id': sourceId,
      'target_id': targetId,
      'connection_type': connectionType,
      'label': label,
      'color': color,
      'thickness': thickness,
    };
  }

  /// Color palette matching Docker web map `MapApp.config.edgeColorMap`
  Color get displayColor {
    if (color != null && color!.isNotEmpty && color!.startsWith('#')) {
      final hex = color!.replaceAll('#', '');
      if (hex.length == 6) {
        return Color(int.parse('FF$hex', radix: 16));
      }
    }

    switch (connectionType.toLowerCase()) {
      case 'fiber':
        return const Color(0xFFF97316); // Orange - Fiber Optic
      case 'wifi':
        return const Color(0xFF38BDF8); // Light Blue - WiFi
      case 'radio':
        return const Color(0xFF84CC16); // Lime Green - Radio
      case 'lan':
        return const Color(0xFF60A5FA); // Blue - LAN
      case 'logical-tunneling':
      case 'tunnel':
        return const Color(0xFFC084FC); // Light Purple - Logical Tunneling
      case 'cat6':
      default:
        return const Color(0xFFA78BFA); // Purple - CAT6 Cable
    }
  }

  String get displayLabel {
    if (label != null && label!.isNotEmpty) return label!;
    switch (connectionType.toLowerCase()) {
      case 'fiber':
        return '💡 Fiber';
      case 'wifi':
        return '📡 WiFi';
      case 'radio':
        return '📻 Radio';
      case 'lan':
        return '🌐 LAN';
      case 'logical-tunneling':
      case 'tunnel':
        return '🔒 Tunnel';
      case 'cat6':
      default:
        return '🔌 CAT6';
    }
  }
}
