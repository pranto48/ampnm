import 'package:flutter/material.dart';
import '../app_theme.dart';
import '../models/server_profile.dart';
import '../services/server_service.dart';
import '../services/storage_service.dart';
import '../widgets/custom_window_title_bar.dart';
import 'main_app_screen.dart';

class ServerSetupScreen extends StatefulWidget {
  final StorageService storage;
  final ServerProfile? initialProfile;

  const ServerSetupScreen({
    super.key,
    required this.storage,
    this.initialProfile,
  });

  @override
  State<ServerSetupScreen> createState() => _ServerSetupScreenState();
}

class _ServerSetupScreenState extends State<ServerSetupScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _serverUrlController;
  late final TextEditingController _usernameController;
  late final TextEditingController _passwordController;
  late final TextEditingController _profileNameController;

  bool _rememberAutoLogin = true;
  bool _obscurePassword = true;
  bool _isTesting = false;
  bool _isConnecting = false;
  ConnectionTestResult? _testResult;
  String? _errorMessage;

  final List<String> _quickServerPresets = [
    'http://192.168.9.9:2266',
    'http://localhost:2266',
    'http://127.0.0.1:2266',
  ];

  @override
  void initState() {
    super.initState();
    final p = widget.initialProfile ?? widget.storage.getActiveProfile();
    _serverUrlController = TextEditingController(text: p?.url ?? 'http://192.168.9.9:2266');
    _usernameController = TextEditingController(text: p?.username ?? 'admin');
    _passwordController = TextEditingController(text: p?.savedPassword ?? '');
    _profileNameController = TextEditingController(text: p?.name ?? 'Main Network Server');
    _rememberAutoLogin = p?.autoLogin ?? true;
  }

  @override
  void dispose() {
    _serverUrlController.dispose();
    _usernameController.dispose();
    _passwordController.dispose();
    _profileNameController.dispose();
    super.dispose();
  }

  Future<void> _testConnection() async {
    setState(() {
      _isTesting = true;
      _testResult = null;
      _errorMessage = null;
    });

    final res = await ServerService.testConnection(_serverUrlController.text);
    if (mounted) {
      setState(() {
        _isTesting = false;
        _testResult = res;
      });
    }
  }

  Future<void> _handleConnect() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isConnecting = true;
      _errorMessage = null;
    });

    final serverUrl = _serverUrlController.text.trim();
    final username = _usernameController.text.trim();
    final password = _passwordController.text;
    final profileName = _profileNameController.text.trim().isEmpty
        ? 'AMPNM Server'
        : _profileNameController.text.trim();

    // 1. Authenticate with server
    final loginRes = await ServerService.login(
      serverUrl: serverUrl,
      username: username,
      password: password,
    );

    if (!loginRes.isSuccess) {
      if (mounted) {
        setState(() {
          _isConnecting = false;
          _errorMessage = loginRes.errorMessage ?? 'Failed to authenticate.';
        });
      }
      return;
    }

    // 2. Save profile
    final profile = ServerProfile(
      id: widget.initialProfile?.id ?? DateTime.now().millisecondsSinceEpoch.toString(),
      name: profileName,
      url: serverUrl,
      username: username,
      savedPassword: _rememberAutoLogin ? password : null,
      sessionCookie: loginRes.sessionCookie,
      autoLogin: _rememberAutoLogin,
      lastConnected: DateTime.now(),
    );

    await widget.storage.saveProfile(profile);

    if (mounted) {
      setState(() => _isConnecting = false);
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => MainAppScreen(
            storage: widget.storage,
            profile: profile,
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final savedProfiles = widget.storage.getProfiles();

    return Scaffold(
      body: Column(
        children: [
          const CustomWindowTitleBar(title: 'AMPNM - Server Setup'),
          Expanded(
            child: Container(
              decoration: const BoxDecoration(
                gradient: RadialGradient(
                  center: Alignment(0, -0.6),
                  radius: 1.2,
                  colors: [
                    Color(0xFF0F1E36),
                    AppTheme.background,
                  ],
                ),
              ),
              child: Center(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 540),
                    child: Card(
                      child: Padding(
                        padding: const EdgeInsets.all(32),
                        child: Form(
                          key: _formKey,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              // Hero Icon & Title
                              Center(
                                child: Column(
                                  children: [
                                    Container(
                                      width: 64,
                                      height: 64,
                                      decoration: BoxDecoration(
                                        color: AppTheme.secondary,
                                        borderRadius: BorderRadius.circular(18),
                                        boxShadow: [
                                          BoxShadow(
                                            color: AppTheme.secondary.withOpacity(0.4),
                                            blurRadius: 16,
                                            offset: const Offset(0, 4),
                                          ),
                                        ],
                                      ),
                                      child: const Center(
                                        child: Icon(
                                          Icons.monitor_heart,
                                          size: 36,
                                          color: Colors.white,
                                        ),
                                      ),
                                    ),
                                    const SizedBox(height: 16),
                                    const Row(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      children: [
                                        Text(
                                          'AMPNM',
                                          style: TextStyle(
                                            fontSize: 26,
                                            fontWeight: FontWeight.w800,
                                            color: Colors.white,
                                            letterSpacing: 1.2,
                                          ),
                                        ),
                                        SizedBox(width: 8),
                                        Text(
                                          'Desktop',
                                          style: TextStyle(
                                            fontSize: 26,
                                            fontWeight: FontWeight.w300,
                                            color: AppTheme.primaryGlow,
                                          ),
                                        ),
                                      ],
                                    ),
                                    const SizedBox(height: 4),
                                    const Text(
                                      'Enterprise Network Topology & Device Monitor',
                                      style: TextStyle(
                                        fontSize: 13,
                                        color: AppTheme.textSecondary,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 28),

                              if (savedProfiles.isNotEmpty) ...[
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    const Text(
                                      'Saved Server Profiles:',
                                      style: TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.w600,
                                        color: AppTheme.textSecondary,
                                      ),
                                    ),
                                    DropdownButton<String>(
                                      value: savedProfiles.any((p) => p.url == _serverUrlController.text)
                                          ? _serverUrlController.text
                                          : null,
                                      hint: const Text('Select profile', style: TextStyle(fontSize: 12)),
                                      dropdownColor: AppTheme.surfaceCard,
                                      underline: const SizedBox(),
                                      style: const TextStyle(color: AppTheme.primary, fontSize: 12),
                                      items: savedProfiles.map((p) {
                                        return DropdownMenuItem(
                                          value: p.url,
                                          child: Text('${p.name} (${p.cleanUrl})'),
                                        );
                                      }).toList(),
                                      onChanged: (val) {
                                        if (val != null) {
                                          final p = savedProfiles.firstWhere((e) => e.url == val);
                                          setState(() {
                                            _serverUrlController.text = p.url;
                                            _usernameController.text = p.username;
                                            _passwordController.text = p.savedPassword ?? '';
                                            _profileNameController.text = p.name;
                                            _rememberAutoLogin = p.autoLogin;
                                          });
                                        }
                                      },
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 8),
                              ],

                              // Server Address Input
                              const Text(
                                'AMPNM Server Address',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: AppTheme.textPrimary,
                                ),
                              ),
                              const SizedBox(height: 8),
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Expanded(
                                    child: TextFormField(
                                      controller: _serverUrlController,
                                      style: const TextStyle(color: Colors.white),
                                      decoration: const InputDecoration(
                                        prefixIcon: Icon(Icons.dns_outlined, color: AppTheme.primary),
                                        hintText: 'http://192.168.9.9:2266',
                                      ),
                                      validator: (val) {
                                        if (val == null || val.trim().isEmpty) {
                                          return 'Please enter the AMPNM server URL';
                                        }
                                        return null;
                                      },
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  SizedBox(
                                    height: 48,
                                    child: OutlinedButton.icon(
                                      onPressed: _isTesting ? null : _testConnection,
                                      icon: _isTesting
                                          ? const SizedBox(
                                              width: 14,
                                              height: 14,
                                              child: CircularProgressIndicator(strokeWidth: 2),
                                            )
                                          : const Icon(Icons.bolt, size: 18),
                                      label: const Text('Test'),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 8),

                              // Quick presets
                              Wrap(
                                spacing: 8,
                                children: _quickServerPresets.map((preset) {
                                  return ActionChip(
                                    label: Text(preset, style: const TextStyle(fontSize: 11)),
                                    backgroundColor: AppTheme.surface,
                                    side: const BorderSide(color: AppTheme.border),
                                    labelStyle: const TextStyle(color: AppTheme.textSecondary),
                                    onPressed: () {
                                      setState(() {
                                        _serverUrlController.text = preset;
                                      });
                                    },
                                  );
                                }).toList(),
                              ),

                              // Test Status Display
                              if (_testResult != null) ...[
                                const SizedBox(height: 12),
                                Container(
                                  padding: const EdgeInsets.all(10),
                                  decoration: BoxDecoration(
                                    color: _testResult!.isSuccess
                                        ? AppTheme.success.withOpacity(0.12)
                                        : AppTheme.danger.withOpacity(0.12),
                                    border: Border.all(
                                      color: _testResult!.isSuccess
                                          ? AppTheme.success.withOpacity(0.4)
                                          : AppTheme.danger.withOpacity(0.4),
                                    ),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Row(
                                    children: [
                                      Icon(
                                        _testResult!.isSuccess
                                            ? Icons.check_circle_outline
                                            : Icons.error_outline,
                                        size: 18,
                                        color: _testResult!.isSuccess
                                            ? AppTheme.success
                                            : AppTheme.danger,
                                      ),
                                      const SizedBox(width: 8),
                                      Expanded(
                                        child: Text(
                                          _testResult!.isSuccess
                                              ? 'Server reachable (${_testResult!.latencyMs}ms) - ${_testResult!.version}'
                                              : _testResult!.errorMessage ?? 'Server unreachable',
                                          style: TextStyle(
                                            fontSize: 12,
                                            color: _testResult!.isSuccess
                                                ? AppTheme.success
                                                : AppTheme.danger,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                              const SizedBox(height: 16),

                              // Profile Name
                              const Text(
                                'Profile Name',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: AppTheme.textPrimary,
                                ),
                              ),
                              const SizedBox(height: 8),
                              TextFormField(
                                controller: _profileNameController,
                                style: const TextStyle(color: Colors.white),
                                decoration: const InputDecoration(
                                  prefixIcon: Icon(Icons.bookmark_outline, color: AppTheme.textSecondary),
                                  hintText: 'e.g. Main Production Server',
                                ),
                              ),
                              const SizedBox(height: 16),

                              // Username & Password
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        const Text(
                                          'Username',
                                          style: TextStyle(
                                            fontSize: 13,
                                            fontWeight: FontWeight.w600,
                                            color: AppTheme.textPrimary,
                                          ),
                                        ),
                                        const SizedBox(height: 8),
                                        TextFormField(
                                          controller: _usernameController,
                                          style: const TextStyle(color: Colors.white),
                                          decoration: const InputDecoration(
                                            prefixIcon: Icon(Icons.person_outline, color: AppTheme.textSecondary),
                                            hintText: 'admin',
                                          ),
                                          validator: (val) {
                                            if (val == null || val.trim().isEmpty) {
                                              return 'Required';
                                            }
                                            return null;
                                          },
                                        ),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(width: 14),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        const Text(
                                          'Password',
                                          style: TextStyle(
                                            fontSize: 13,
                                            fontWeight: FontWeight.w600,
                                            color: AppTheme.textPrimary,
                                          ),
                                        ),
                                        const SizedBox(height: 8),
                                        TextFormField(
                                          controller: _passwordController,
                                          obscureText: _obscurePassword,
                                          style: const TextStyle(color: Colors.white),
                                          decoration: InputDecoration(
                                            prefixIcon: const Icon(Icons.lock_outline, color: AppTheme.textSecondary),
                                            suffixIcon: IconButton(
                                              icon: Icon(
                                                _obscurePassword
                                                    ? Icons.visibility_off_outlined
                                                    : Icons.visibility_outlined,
                                                size: 18,
                                                color: AppTheme.textSecondary,
                                              ),
                                              onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                                            ),
                                            hintText: '••••••••',
                                          ),
                                          validator: (val) {
                                            if (val == null || val.isEmpty) {
                                              return 'Required';
                                            }
                                            return null;
                                          },
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 12),

                              // Auto Login Switch
                              Row(
                                children: [
                                  Checkbox(
                                    value: _rememberAutoLogin,
                                    activeColor: AppTheme.primary,
                                    onChanged: (val) => setState(() => _rememberAutoLogin = val ?? true),
                                  ),
                                  const Text(
                                    'Remember this server & sign in automatically',
                                    style: TextStyle(
                                      fontSize: 13,
                                      color: AppTheme.textSecondary,
                                    ),
                                  ),
                                ],
                              ),

                              // Error Banner
                              if (_errorMessage != null) ...[
                                const SizedBox(height: 12),
                                Container(
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(
                                    color: AppTheme.danger.withOpacity(0.15),
                                    border: Border.all(color: AppTheme.danger.withOpacity(0.4)),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Row(
                                    children: [
                                      const Icon(Icons.error_outline, color: AppTheme.danger, size: 20),
                                      const SizedBox(width: 10),
                                      Expanded(
                                        child: Text(
                                          _errorMessage!,
                                          style: const TextStyle(color: AppTheme.danger, fontSize: 13),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                              const SizedBox(height: 24),

                              // Connect Action Button
                              ElevatedButton(
                                onPressed: _isConnecting ? null : _handleConnect,
                                style: ElevatedButton.styleFrom(
                                  padding: const EdgeInsets.symmetric(vertical: 16),
                                  backgroundColor: AppTheme.primary,
                                ),
                                child: _isConnecting
                                    ? const Row(
                                        mainAxisAlignment: MainAxisAlignment.center,
                                        children: [
                                          SizedBox(
                                            width: 18,
                                            height: 18,
                                            child: CircularProgressIndicator(
                                              strokeWidth: 2,
                                              color: Colors.white,
                                            ),
                                          ),
                                          SizedBox(width: 12),
                                          Text('Connecting to Server...'),
                                        ],
                                      )
                                    : const Row(
                                        mainAxisAlignment: MainAxisAlignment.center,
                                        children: [
                                          Icon(Icons.login, size: 20),
                                          SizedBox(width: 8),
                                          Text(
                                            'Connect & Open AMPNM App',
                                            style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                                          ),
                                        ],
                                      ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
