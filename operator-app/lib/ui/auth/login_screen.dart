import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/config/api_config.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../data/api/server_connector.dart';
import '../../data/api/server_discovery.dart';
import '../widgets/brand_mark.dart';
import '../widgets/confirm_dialog.dart';
import 'auth_controller.dart';

/// Layar masuk operator.
///
/// Memuat pengaturan alamat server, dan itu bukan tempelan: kalau alamatnya
/// salah, login gagal — dan layar Pengaturan ada **di dalam** shell yang baru
/// terbuka setelah login. Tanpa jalan keluar di sini, operator terkunci di
/// layar yang tidak bisa diperbaiki dari mana pun.
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _username = TextEditingController();
  final _password = TextEditingController();
  final _passwordFocus = FocusNode();
  bool _obscure = true;
  bool _scanning = false;

  @override
  void dispose() {
    _username.dispose();
    _password.dispose();
    _passwordFocus.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final auth = context.read<AuthController>();
    if (auth.busy) return;

    if (_username.text.trim().isEmpty || _password.text.isEmpty) {
      showWarning(context, 'Username dan password harus diisi.');
      return;
    }

    await auth.signIn(_username.text, _password.text);
    // Kalau berhasil, gate di `app.dart` yang mengganti layar — tidak ada
    // Navigator.push di sini. Satu sumber kebenaran untuk "sudah masuk".
  }

  Future<void> _autoFindServer() async {
    setState(() => _scanning = true);
    final connector = ServerConnector();

    try {
      final found = await connector.scan();
      if (!mounted) return;

      if (found.isEmpty) {
        showWarning(
          context,
          'Tidak ada server billing di jaringan ini. Pastikan laptop server '
          'hidup dan tablet tersambung ke WiFi yang sama.',
        );
        return;
      }

      final chosen = found.length == 1 ? found.first : await _pick(found);
      if (chosen == null || !mounted) return;

      await connector.remember(chosen);
      if (!mounted) return;

      setState(() {});
      showSuccess(context, 'Tersambung ke ${chosen.instance}.');
    } finally {
      connector.close();
      if (mounted) setState(() => _scanning = false);
    }
  }

  Future<DiscoveredServer?> _pick(List<DiscoveredServer> servers) {
    return showDialog<DiscoveredServer>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Pilih server'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (final s in servers)
              ListTile(
                title: Text(s.instance),
                subtitle: Text(s.baseUrl, style: AppTypography.moneySm),
                onTap: () => Navigator.of(ctx).pop(s),
              ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();

    return Scaffold(
      backgroundColor: AppColors.surface,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(AppSpacing.gutterLg),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Center(child: BrandMark(size: 56)),
                  const SizedBox(height: AppSpacing.xl),

                  Container(
                    padding: const EdgeInsets.all(AppSpacing.lg),
                    decoration: AppDecoration.card(),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text('Masuk', style: AppTypography.headlineMd),
                        const SizedBox(height: AppSpacing.xs),
                        Text(
                          'Pakai akun operator yang diberikan admin.',
                          style: AppTypography.bodySm.copyWith(
                            color: AppColors.onSurfaceVariant,
                          ),
                        ),
                        const SizedBox(height: AppSpacing.lg),

                        if (auth.error != null) ...[
                          _ErrorNote(message: auth.error!),
                          const SizedBox(height: AppSpacing.md),
                        ],

                        TextField(
                          controller: _username,
                          enabled: !auth.busy,
                          autofocus: true,
                          textInputAction: TextInputAction.next,
                          autocorrect: false,
                          enableSuggestions: false,
                          decoration: const InputDecoration(
                            labelText: 'Username',
                            prefixIcon: Icon(Icons.person_outline),
                          ),
                          onChanged: (_) => auth.clearError(),
                          onSubmitted: (_) => _passwordFocus.requestFocus(),
                        ),
                        const SizedBox(height: AppSpacing.md),

                        TextField(
                          controller: _password,
                          focusNode: _passwordFocus,
                          enabled: !auth.busy,
                          obscureText: _obscure,
                          textInputAction: TextInputAction.done,
                          decoration: InputDecoration(
                            labelText: 'Password',
                            prefixIcon: const Icon(Icons.lock_outline),
                            suffixIcon: IconButton(
                              // Tablet kasir dipakai berdiri dan terburu-buru;
                              // salah ketik password yang tidak terlihat
                              // adalah kegagalan login yang paling sering.
                              onPressed: () =>
                                  setState(() => _obscure = !_obscure),
                              icon: Icon(
                                _obscure
                                    ? Icons.visibility_outlined
                                    : Icons.visibility_off_outlined,
                              ),
                              tooltip:
                                  _obscure ? 'Tampilkan' : 'Sembunyikan',
                            ),
                          ),
                          onChanged: (_) => auth.clearError(),
                          onSubmitted: (_) => _submit(),
                        ),
                        const SizedBox(height: AppSpacing.lg),

                        SizedBox(
                          height: AppSize.minTouchTarget,
                          child: FilledButton(
                            onPressed: auth.busy ? null : _submit,
                            child: auth.busy
                                ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: AppColors.onPrimary,
                                    ),
                                  )
                                : const Text('Masuk'),
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: AppSpacing.md),
                  _ServerRow(
                    scanning: _scanning,
                    onScan: _scanning ? null : _autoFindServer,
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Pesan gagal dari server, ditampilkan di dekat field — bukan snackbar.
///
/// Snackbar hilang sendiri, dan "Username atau password salah" perlu tetap
/// terbaca selama operator mencoba lagi.
class _ErrorNote extends StatelessWidget {
  const _ErrorNote({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.sm + 2),
      decoration: BoxDecoration(
        color: AppColors.errorContainer,
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.error_outline,
              size: 16, color: AppColors.onErrorContainer),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              message,
              style: AppTypography.bodySm
                  .copyWith(color: AppColors.onErrorContainer),
            ),
          ),
        ],
      ),
    );
  }
}

/// Alamat server yang sedang dipakai, dengan jalan keluar kalau salah.
class _ServerRow extends StatelessWidget {
  const _ServerRow({required this.scanning, required this.onScan});

  final bool scanning;
  final VoidCallback? onScan;

  @override
  Widget build(BuildContext context) {
    final cfg = context.watch<ApiConfig>();

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.sm,
      ),
      decoration: AppDecoration.inset(radius: AppRadius.md),
      child: Row(
        children: [
          const Icon(Icons.dns_outlined,
              size: 15, color: AppColors.onSurfaceVariant),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'Server',
                  style: AppTypography.labelSm
                      .copyWith(color: AppColors.outline),
                ),
                Text(
                  cfg.baseUrl,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.moneySm
                      .copyWith(color: AppColors.onSurfaceVariant),
                ),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          TextButton.icon(
            onPressed: onScan,
            icon: scanning
                ? const SizedBox(
                    width: 14,
                    height: 14,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.wifi_find_outlined, size: 16),
            label: Text(scanning ? 'Mencari…' : 'Cari otomatis'),
          ),
        ],
      ),
    );
  }
}
