import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../core/config/api_config.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../core/time/server_time.dart';
import '../../core/util/format.dart';
import '../widgets/confirm_dialog.dart';

/// Pengaturan alamat server.
///
/// Layar ini ada karena alasan praktis di lapangan: IP laptop bisa berubah,
/// dan tanpa cara mengubahnya dari dalam app, setiap salah IP berarti
/// rebuild APK di lokasi (TEST-PLAN-SABTU.md §2.2 nomor 6).
class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key, this.embedded = false});

  /// `true` saat dipasang di dalam [AppShell] — shell sudah punya header.
  final bool embedded;

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  late final TextEditingController _baseUrl;
  late final TextEditingController _wsHost;
  late final TextEditingController _wsPort;

  String? _baseUrlError;
  bool _dirty = false;

  @override
  void initState() {
    super.initState();
    final cfg = ApiConfig.instance;
    _baseUrl = TextEditingController(text: cfg.baseUrl);
    _wsHost = TextEditingController(text: cfg.wsHost);
    _wsPort = TextEditingController(text: cfg.wsPort.toString());

    for (final c in [_baseUrl, _wsHost, _wsPort]) {
      c.addListener(() {
        if (!_dirty) setState(() => _dirty = true);
      });
    }
  }

  @override
  void dispose() {
    _baseUrl.dispose();
    _wsHost.dispose();
    _wsPort.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final error = ApiConfig.validateBaseUrl(_baseUrl.text);
    if (error != null) {
      setState(() => _baseUrlError = error);
      return;
    }
    setState(() => _baseUrlError = null);

    await ApiConfig.instance.update(
      baseUrl: _baseUrl.text,
      wsHost: _wsHost.text.trim().isEmpty ? null : _wsHost.text,
      wsPort: int.tryParse(_wsPort.text.trim()),
    );

    if (!mounted) return;
    setState(() {
      _dirty = false;
      _baseUrl.text = ApiConfig.instance.baseUrl;
    });
    showSuccess(context, 'Alamat server disimpan.');
  }

  Future<void> _reset() async {
    final ok = await showConfirmDialog(
      context,
      title: 'Kembalikan ke default?',
      message: 'Alamat server akan kembali ke nilai dari build APK.',
      confirmLabel: 'Kembalikan',
    );
    if (!ok) return;

    await ApiConfig.instance.resetToBuildDefaults();
    if (!mounted) return;

    final cfg = ApiConfig.instance;
    setState(() {
      _baseUrl.text = cfg.baseUrl;
      _wsHost.text = cfg.wsHost;
      _wsPort.text = cfg.wsPort.toString();
      _dirty = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final cfg = context.watch<ApiConfig>();
    final content = _content(cfg);

    if (widget.embedded) return content;

    return Scaffold(
      appBar: AppBar(title: const Text('Pengaturan')),
      body: content,
    );
  }

  Widget _content(ApiConfig cfg) {
    return SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 560),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text('Server', style: AppTypography.headlineSm),
                  const SizedBox(height: AppSpacing.xs),
                  Text(
                    'Alamat Laravel di jaringan lokal. Contoh: '
                    '192.168.0.50:8000',
                    style: AppTypography.bodySm
                        .copyWith(color: AppColors.onSurfaceVariant),
                  ),
                  const SizedBox(height: AppSpacing.md),

                  TextField(
                    controller: _baseUrl,
                    keyboardType: TextInputType.url,
                    autocorrect: false,
                    decoration: InputDecoration(
                      labelText: 'Alamat API',
                      errorText: _baseUrlError,
                      helperText: 'Tanpa /api/v1 — ditambahkan otomatis',
                      prefixIcon: const Icon(Icons.dns_outlined),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),

                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        flex: 3,
                        child: TextField(
                          controller: _wsHost,
                          autocorrect: false,
                          decoration: const InputDecoration(
                            labelText: 'Host WebSocket',
                            helperText: 'Biasanya sama dengan IP API',
                            prefixIcon: Icon(Icons.bolt_outlined),
                          ),
                        ),
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: TextField(
                          controller: _wsPort,
                          keyboardType: TextInputType.number,
                          inputFormatters: [
                            FilteringTextInputFormatter.digitsOnly
                          ],
                          decoration: const InputDecoration(
                            labelText: 'Port',
                            helperText: 'Reverb: 8080',
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.lg),

                  Row(
                    children: [
                      Expanded(
                        child: AsyncButton(
                          label: 'Simpan',
                          icon: Icons.save_outlined,
                          expand: true,
                          onPressed: _dirty ? _save : null,
                        ),
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      OutlinedButton.icon(
                        onPressed: cfg.isDefault ? null : _reset,
                        icon: const Icon(Icons.restore),
                        label: const Text('Default'),
                      ),
                    ],
                  ),

                  const SizedBox(height: AppSpacing.xl),
                  const Divider(),
                  const SizedBox(height: AppSpacing.md),

                  Text('Diagnostik', style: AppTypography.headlineSm),
                  const SizedBox(height: AppSpacing.md),
                  _DiagRow(
                    label: 'Alamat API aktif',
                    value: cfg.apiBase,
                  ),
                  _DiagRow(
                    label: 'WebSocket',
                    value: '${cfg.wsHost}:${cfg.wsPort} '
                        '(${cfg.forceTls ? 'wss' : 'ws'})',
                  ),
                  _DiagRow(
                    label: 'Mode build',
                    value: cfg.isDev ? 'dev — HTTP diizinkan' : 'prod — HTTPS',
                  ),
                  _DiagRow(
                    label: 'Sinkronisasi jam',
                    value: !ServerTime.instance.isSynced
                        ? 'belum sinkron'
                        : 'beda ${ServerTime.instance.offset.inSeconds}s '
                            '(${formatClock(ServerTime.instance.now)} server)',
                    valueColor: ServerTime.instance.isSynced
                        ? null
                        : AppColors.statusWarning,
                  ),
                  _DiagRow(
                    label: 'Sumber data',
                    value: 'Fake repository (DEC-012)',
                    valueColor: AppColors.statusWarning,
                  ),

                  const SizedBox(height: AppSpacing.lg),
                  Container(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    decoration: BoxDecoration(
                      color: AppColors.statusWarning.withValues(alpha: 0.10),
                      borderRadius: BorderRadius.circular(AppRadius.lg),
                      border: Border.all(
                        color: AppColors.statusWarning.withValues(alpha: 0.3),
                      ),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.science_outlined,
                            color: AppColors.statusWarning, size: 20),
                        const SizedBox(width: AppSpacing.sm),
                        Expanded(
                          child: Text(
                            'Build ini masih memakai data palsu di memori. '
                            'Alamat server belum dipanggil sama sekali.',
                            style: AppTypography.bodySm
                                .copyWith(color: AppColors.statusWarning),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
  }
}

class _DiagRow extends StatelessWidget {
  const _DiagRow({
    required this.label,
    required this.value,
    this.valueColor,
  });

  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm - 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 150,
            child: Text(
              label,
              style:
                  AppTypography.bodySm.copyWith(color: AppColors.onSurfaceVariant),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: AppTypography.moneySm.copyWith(color: valueColor),
            ),
          ),
        ],
      ),
    );
  }
}
