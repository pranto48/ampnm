import 'package:flutter/material.dart';
import 'package:window_manager/window_manager.dart';
import '../app_theme.dart';
import '../models/server_profile.dart';

class CustomWindowTitleBar extends StatefulWidget {
  final String title;
  final Widget? leading;
  final List<Widget>? actions;
  final String? serverName;
  final bool isConnected;
  final ServerProfile? serverProfile;
  final int? latencyMs;
  final VoidCallback? onSettingsPressed;
  final VoidCallback? onSwitchServerPressed;

  const CustomWindowTitleBar({
    super.key,
    this.title = 'AMPNM Network Manager',
    this.leading,
    this.actions,
    this.serverName,
    this.isConnected = false,
    this.serverProfile,
    this.latencyMs,
    this.onSettingsPressed,
    this.onSwitchServerPressed,
  });

  @override
  State<CustomWindowTitleBar> createState() => _CustomWindowTitleBarState();
}

class _CustomWindowTitleBarState extends State<CustomWindowTitleBar> with WindowListener {
  bool _isMaximized = false;

  @override
  void initState() {
    super.initState();
    windowManager.addListener(this);
    _checkMaximized();
  }

  @override
  void dispose() {
    windowManager.removeListener(this);
    super.dispose();
  }

  Future<void> _checkMaximized() async {
    final max = await windowManager.isMaximized();
    if (mounted) {
      setState(() => _isMaximized = max);
    }
  }

  @override
  void onWindowMaximize() {
    if (mounted) setState(() => _isMaximized = true);
  }

  @override
  void onWindowUnmaximize() {
    if (mounted) setState(() => _isMaximized = false);
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 40,
      decoration: const BoxDecoration(
        color: AppTheme.surface,
        border: Border(
          bottom: BorderSide(color: AppTheme.border, width: 1),
        ),
      ),
      child: Row(
        children: [
          // Drag region for window move
          Expanded(
            child: GestureDetector(
              behavior: HitTestBehavior.translucent,
              onPanStart: (details) => windowManager.startDragging(),
              onDoubleTap: () async {
                if (await windowManager.isMaximized()) {
                  windowManager.unmaximize();
                } else {
                  windowManager.maximize();
                }
              },
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: Row(
                  children: [
                    // Brand Icon
                    ClipRRect(
                      borderRadius: BorderRadius.circular(5),
                      child: Image.asset(
                        'assets/images/ampnm-logo.png',
                        width: 22,
                        height: 22,
                        fit: BoxFit.contain,
                        errorBuilder: (_, __, ___) => Container(
                          width: 22,
                          height: 22,
                          decoration: BoxDecoration(
                            color: AppTheme.secondary,
                            borderRadius: BorderRadius.circular(5),
                          ),
                          child: const Center(
                            child: Icon(Icons.monitor_heart, size: 14, color: Colors.white),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    // App Title
                    Text(
                      widget.title,
                      style: const TextStyle(
                        color: AppTheme.textPrimary,
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        letterSpacing: 0.2,
                      ),
                    ),
                    if (widget.serverProfile?.name != null || widget.serverName != null) ...[
                      const SizedBox(width: 12),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: (widget.serverProfile != null || widget.isConnected)
                              ? AppTheme.success.withOpacity(0.15)
                              : AppTheme.surfaceCard,
                          border: Border.all(
                            color: (widget.serverProfile != null || widget.isConnected)
                                ? AppTheme.success.withOpacity(0.4)
                                : AppTheme.border,
                          ),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: 6,
                              height: 6,
                              decoration: BoxDecoration(
                                color: (widget.serverProfile != null || widget.isConnected)
                                    ? AppTheme.success
                                    : AppTheme.textMuted,
                                shape: BoxShape.circle,
                              ),
                            ),
                            const SizedBox(width: 6),
                            Text(
                              widget.serverProfile?.name ?? widget.serverName ?? 'Connected',
                              style: TextStyle(
                                color: (widget.serverProfile != null || widget.isConnected)
                                    ? AppTheme.success
                                    : AppTheme.textSecondary,
                                fontSize: 11,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                            if (widget.latencyMs != null) ...[
                              const SizedBox(width: 4),
                              Text(
                                '• ${widget.latencyMs}ms',
                                style: const TextStyle(
                                  color: AppTheme.primaryGlow,
                                  fontSize: 10,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),

          // Custom Actions
          if (widget.actions != null) ...widget.actions!,

          // Window Buttons
          _WindowButton(
            icon: Icons.remove,
            tooltip: 'Minimize',
            onPressed: () => windowManager.minimize(),
          ),
          _WindowButton(
            icon: _isMaximized ? Icons.crop_square : Icons.crop_din,
            tooltip: _isMaximized ? 'Restore' : 'Maximize',
            onPressed: () async {
              if (_isMaximized) {
                await windowManager.unmaximize();
              } else {
                await windowManager.maximize();
              }
            },
          ),
          _WindowButton(
            icon: Icons.close,
            tooltip: 'Close',
            isClose: true,
            onPressed: () => windowManager.close(),
          ),
        ],
      ),
    );
  }
}

class _WindowButton extends StatefulWidget {
  final IconData icon;
  final String tooltip;
  final VoidCallback onPressed;
  final bool isClose;

  const _WindowButton({
    required this.icon,
    required this.tooltip,
    required this.onPressed,
    this.isClose = false,
  });

  @override
  State<_WindowButton> createState() => _WindowButtonState();
}

class _WindowButtonState extends State<_WindowButton> {
  bool _isHovered = false;

  @override
  Widget build(BuildContext context) {
    Color hoverColor = widget.isClose
        ? AppTheme.danger
        : AppTheme.surfaceHover;

    return Tooltip(
      message: widget.tooltip,
      child: MouseRegion(
        onEnter: (_) => setState(() => _isHovered = true),
        onExit: (_) => setState(() => _isHovered = false),
        child: GestureDetector(
          onTap: widget.onPressed,
          child: Container(
            width: 44,
            height: 40,
            color: _isHovered ? hoverColor : Colors.transparent,
            child: Center(
              child: Icon(
                widget.icon,
                size: 16,
                color: _isHovered && widget.isClose ? Colors.white : AppTheme.textSecondary,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
