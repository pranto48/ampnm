import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import '../models/device_model.dart';

class DeviceIconWidget extends StatelessWidget {
  final DeviceModel device;
  final String? serverUrl;
  final double size;
  final bool showStatusGlow;

  const DeviceIconWidget({
    super.key,
    required this.device,
    this.serverUrl,
    this.size = 32.0,
    this.showStatusGlow = true,
  });

  static const List<String> pngIconsList = [
    'assets/images/device-icons/sophos-firewall.png',
    'assets/images/device-icons/cisco-switch.png',
    'assets/images/device-icons/mikrotik-router.png',
    'assets/images/device-icons/online-ups.png',
    'assets/images/device-icons/rack-server.png',
    'assets/images/device-icons/default-device.png',
  ];

  static const List<String> animatedIconsList = [
    'assets/images/device-icons/animated-globe.svg',
    'assets/images/device-icons/animated-router.svg',
    'assets/images/device-icons/animated-firewall.svg',
    'assets/images/device-icons/animated-server.svg',
    'assets/images/device-icons/animated-access-point.svg',
    'assets/images/device-icons/animated-switch.svg',
    'assets/images/device-icons/animated-cloud.svg',
    'assets/images/device-icons/animated-camera.svg',
    'assets/images/device-icons/animated-database.svg',
    'assets/images/device-icons/animated-workstation.svg',
    'assets/images/device-icons/animated-phone.svg',
    'assets/images/device-icons/animated-printer.svg',
    'assets/images/device-icons/animated-laptop.svg',
    'assets/images/device-icons/animated-nas.svg',
    'assets/images/device-icons/animated-iot-sensor.svg',
    'assets/images/device-icons/animated-ups.svg',
    'assets/images/device-icons/animated-utm.svg',
    'assets/images/device-icons/animated-tower.svg',
    'assets/images/device-icons/animated-modem.svg',
    'assets/images/device-icons/animated-patch-panel.svg',
    'assets/images/device-icons/animated-vlan.svg',
    'assets/images/device-icons/animated-warehouse.svg',
    'assets/images/device-icons/animated-switch-core.svg',
    'assets/images/device-icons/animated-ups-online.svg',
    'assets/images/device-icons/animated-firewall-nextgen.svg',
    'assets/images/device-icons/animated-unit.svg',
    'assets/images/device-icons/animated-sat.svg',
    'assets/images/device-icons/animated-sdwan.svg',
    'assets/images/device-icons/animated-datacenter.svg',
    'assets/images/device-icons/animated-wifi-router.svg',
    'assets/images/device-icons/animated-optical.svg',
    'assets/images/device-icons/animated-server-blade.svg',
  ];

  String? _resolveAssetOrUrl() {
    // 1. Explicit Custom Icon URL
    if (device.iconUrl != null && device.iconUrl!.isNotEmpty) {
      final raw = device.iconUrl!.trim();
      if (raw.startsWith('http://') || raw.startsWith('https://')) {
        return raw;
      }
      // If relative path from Docker server, try matching local bundled asset
      final cleanPath = raw.replaceAll('\\', '/');
      if (cleanPath.contains('assets/images/device-icons/')) {
        return 'assets/images/device-icons/' + cleanPath.split('assets/images/device-icons/').last;
      }
      if (cleanPath.contains('device-icons/')) {
        return 'assets/images/device-icons/' + cleanPath.split('device-icons/').last;
      }
      // Or server relative URL
      if (serverUrl != null && serverUrl!.isNotEmpty) {
        final cleanBase = serverUrl!.endsWith('/') ? serverUrl!.substring(0, serverUrl!.length - 1) : serverUrl!;
        final cleanRel = raw.startsWith('/') ? raw : '/$raw';
        return '$cleanBase$cleanRel';
      }
    }

    // 2. Type is png-icons or animated-icons with subchoice
    final subchoiceInt = int.tryParse(device.subchoice.trim()) ?? 0;
    if (device.type == 'png-icons') {
      if (subchoiceInt >= 0 && subchoiceInt < pngIconsList.length) {
        return pngIconsList[subchoiceInt];
      }
      return 'assets/images/device-icons/default-device.png';
    }

    if (device.type == 'animated-icons') {
      if (subchoiceInt >= 0 && subchoiceInt < animatedIconsList.length) {
        return animatedIconsList[subchoiceInt];
      }
      return 'assets/images/device-icons/animated-router.svg';
    }

    // 3. Match by Name and Type exactly like Docker Web App (utils.js)
    final name = device.name.toLowerCase();
    final type = device.type.toLowerCase();

    if (type.contains('firewall') || type.contains('utm') || name.contains('firewall') || name.contains('cnf') || name.contains('sophos')) {
      return 'assets/images/device-icons/sophos-firewall.png';
    }
    if (type.contains('switch') || name.contains('switch') || name.contains('cisco') || name.contains('sw')) {
      return 'assets/images/device-icons/cisco-switch.png';
    }
    if (type.contains('router') || type.contains('mikrotik') || name.contains('router') || name.contains('mikrotik')) {
      return 'assets/images/device-icons/mikrotik-router.png';
    }
    if (type.contains('ups') || name.contains('ups') || name.contains('power')) {
      return 'assets/images/device-icons/online-ups.png';
    }
    if (type.contains('server') || type.contains('host') || name.contains('server') || name.contains('opc') || name.contains('dell')) {
      return 'assets/images/device-icons/rack-server.png';
    }
    if (type.contains('ap') || type.contains('wifi') || type.contains('wireless')) {
      return 'assets/images/device-icons/animated-access-point.svg';
    }
    if (type.contains('camera') || type.contains('cctv') || name.contains('camera') || name.contains('cctv')) {
      return 'assets/images/device-icons/animated-camera.svg';
    }
    if (type.contains('nas') || type.contains('storage') || name.contains('synology') || name.contains('qnap')) {
      return 'assets/images/device-icons/animated-nas.svg';
    }
    if (type.contains('database') || type.contains('db') || name.contains('sql') || name.contains('mysql')) {
      return 'assets/images/device-icons/animated-database.svg';
    }
    if (type.contains('phone') || type.contains('voip') || name.contains('phone')) {
      return 'assets/images/device-icons/animated-phone.svg';
    }

    return null;
  }

  @override
  Widget build(BuildContext context) {
    final imageRef = _resolveAssetOrUrl();

    if (imageRef != null) {
      // Remote Network Image (e.g. uploaded custom icon on Docker server)
      if (imageRef.startsWith('http://') || imageRef.startsWith('https://')) {
        return Image.network(
          imageRef,
          width: size,
          height: size,
          fit: BoxFit.contain,
          errorBuilder: (_, __, ___) => Icon(
            device.typeIcon,
            size: size * 0.75,
            color: device.statusColor,
          ),
        );
      }

      // Local Bundled SVG Asset
      if (imageRef.endsWith('.svg')) {
        return SvgPicture.asset(
          imageRef,
          width: size,
          height: size,
          fit: BoxFit.contain,
          placeholderBuilder: (_) => Icon(
            device.typeIcon,
            size: size * 0.75,
            color: device.statusColor,
          ),
        );
      }

      // Local Bundled PNG Asset
      return Image.asset(
        imageRef,
        width: size,
        height: size,
        fit: BoxFit.contain,
        errorBuilder: (_, __, ___) => Icon(
          device.typeIcon,
          size: size * 0.75,
          color: device.statusColor,
        ),
      );
    }

    // Fallback to high-contrast Material Icon
    return Icon(
      device.typeIcon,
      size: size * 0.75,
      color: device.statusColor,
    );
  }
}
