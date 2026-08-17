import 'package:flutter/material.dart';
import 'package:window_manager/window_manager.dart';

import 'app_theme.dart';
import 'screens/main_app_screen.dart';
import 'screens/server_setup_screen.dart';
import 'services/storage_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize Desktop Window Manager
  await windowManager.ensureInitialized();

  const windowOptions = WindowOptions(
    size: Size(1366, 820),
    minimumSize: Size(1024, 680),
    center: true,
    backgroundColor: Colors.transparent,
    skipTaskbar: false,
    titleBarStyle: TitleBarStyle.hidden,
    title: 'AMPNM Network Manager',
  );

  await windowManager.waitUntilReadyToShow(windowOptions, () async {
    await windowManager.show();
    await windowManager.focus();
  });

  // Initialize Local Storage
  final storage = await StorageService.init();

  runApp(AmpnmDesktopApp(storage: storage));
}

class AmpnmDesktopApp extends StatelessWidget {
  final StorageService storage;

  const AmpnmDesktopApp({super.key, required this.storage});

  @override
  Widget build(BuildContext context) {
    final activeProfile = storage.getActiveProfile();
    final hasAutoLogin = activeProfile != null && activeProfile.autoLogin && activeProfile.url.isNotEmpty;

    return MaterialApp(
      title: 'AMPNM Network Manager',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.darkTheme,
      home: hasAutoLogin
          ? MainAppScreen(storage: storage, profile: activeProfile)
          : ServerSetupScreen(storage: storage, initialProfile: activeProfile),
    );
  }
}
