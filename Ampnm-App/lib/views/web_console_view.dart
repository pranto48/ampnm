import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_windows/webview_windows.dart';
import '../app_theme.dart';

class WebConsoleView extends StatelessWidget {
  final WebviewController? webviewController;
  final bool isInitialized;
  final bool isLoading;
  final String currentUrl;
  final ValueChanged<String> onNavigate;
  final VoidCallback onReload;

  const WebConsoleView({
    super.key,
    required this.webviewController,
    required this.isInitialized,
    required this.isLoading,
    required this.currentUrl,
    required this.onNavigate,
    required this.onReload,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // Web Console Navigation Toolbar
        Container(
          height: 48,
          color: const Color(0xFF0F172A),
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(
            children: [
              IconButton(
                icon: const Icon(Icons.arrow_back, size: 16, color: AppTheme.textSecondary),
                tooltip: 'Back',
                onPressed: () => webviewController?.goBack(),
              ),
              IconButton(
                icon: const Icon(Icons.arrow_forward, size: 16, color: AppTheme.textSecondary),
                tooltip: 'Forward',
                onPressed: () => webviewController?.goForward(),
              ),
              IconButton(
                icon: Icon(
                  isLoading ? Icons.hourglass_top : Icons.refresh,
                  size: 16,
                  color: AppTheme.primaryGlow,
                ),
                tooltip: 'Reload',
                onPressed: onReload,
              ),
              const SizedBox(width: 8),

              // Preset Quick Pages
              _QuickPageChip(label: 'Dashboard', path: '/index.php', currentUrl: currentUrl, onNavigate: onNavigate),
              const SizedBox(width: 6),
              _QuickPageChip(label: 'Web Map', path: '/map.php', currentUrl: currentUrl, onNavigate: onNavigate),
              const SizedBox(width: 6),
              _QuickPageChip(label: 'Web Devices', path: '/devices.php', currentUrl: currentUrl, onNavigate: onNavigate),
              const SizedBox(width: 6),
              _QuickPageChip(label: 'Update Center', path: '/update_status.php', currentUrl: currentUrl, onNavigate: onNavigate),

              const Spacer(),

              IconButton(
                icon: const Icon(Icons.open_in_browser, size: 16, color: AppTheme.textSecondary),
                tooltip: 'Open in Default Web Browser',
                onPressed: () async {
                  if (currentUrl.isNotEmpty) {
                    final uri = Uri.parse(currentUrl);
                    if (await canLaunchUrl(uri)) {
                      await launchUrl(uri, mode: LaunchMode.externalApplication);
                    }
                  }
                },
              ),
            ],
          ),
        ),

        // WebView2 Viewport
        Expanded(
          child: isInitialized && webviewController != null
              ? Stack(
                  children: [
                    Webview(webviewController!),
                    if (isLoading)
                      const Positioned(
                        top: 0,
                        left: 0,
                        right: 0,
                        child: LinearProgressIndicator(
                          minHeight: 2,
                          backgroundColor: Colors.transparent,
                          valueColor: AlwaysStoppedAnimation<Color>(AppTheme.primaryGlow),
                        ),
                      ),
                  ],
                )
              : const Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      CircularProgressIndicator(color: AppTheme.primary),
                      SizedBox(height: 16),
                      Text(
                        'Initializing Embedded Web Engine...',
                        style: TextStyle(color: AppTheme.textSecondary),
                      ),
                    ],
                  ),
                ),
        ),
      ],
    );
  }
}

class _QuickPageChip extends StatelessWidget {
  final String label;
  final String path;
  final String currentUrl;
  final ValueChanged<String> onNavigate;

  const _QuickPageChip({
    required this.label,
    required this.path,
    required this.currentUrl,
    required this.onNavigate,
  });

  @override
  Widget build(BuildContext context) {
    final isCurrent = currentUrl.contains(path);
    return InkWell(
      onTap: () => onNavigate(path),
      borderRadius: BorderRadius.circular(6),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: isCurrent ? AppTheme.primary.withOpacity(0.2) : Colors.transparent,
          borderRadius: BorderRadius.circular(6),
          border: Border.all(
            color: isCurrent ? AppTheme.primary : AppTheme.border.withOpacity(0.5),
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 11,
            fontWeight: isCurrent ? FontWeight.bold : FontWeight.normal,
            color: isCurrent ? AppTheme.primaryGlow : AppTheme.textSecondary,
          ),
        ),
      ),
    );
  }
}
