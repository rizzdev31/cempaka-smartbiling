import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../core/time/server_time.dart';
import '../../core/time/ticker.dart';
import '../../core/util/format.dart';
import '../../domain/errors/api_error.dart';
import '../../domain/models/models.dart';
import '../../domain/repositories/billing_repository.dart';
import '../widgets/confirm_dialog.dart';

/// Status TV — PRD §18: "Online/offline/last seen".
///
/// Layar ini **read-only**. Pendaftaran, pemetaan ulang, dan pencabutan
/// token device adalah wewenang Admin (PRD §19) dan masuk Tahap 3B.
/// Operator di sini hanya perlu menjawab satu pertanyaan: TV mana yang
/// tidak mengirim kabar, dan sejak kapan.
///
/// Status diambil apa adanya dari server. Berbeda dari status sesi — yang
/// memang diturunkan client dari `end_at` — karena ambang offline adalah
/// kebijakan operasional, bukan hitungan waktu yang pasti.
class DeviceScreen extends StatefulWidget {
  const DeviceScreen({super.key, this.embedded = false});

  /// `true` saat dipasang di dalam [AppShell] — shell sudah punya header.
  final bool embedded;

  @override
  State<DeviceScreen> createState() => _DeviceScreenState();
}

class _DeviceScreenState extends State<DeviceScreen> {
  DeviceList _data = DeviceList.empty;
  bool _loading = false;
  Object? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool silent = false}) async {
    if (!silent) setState(() => _loading = true);
    try {
      final data = await context.read<BillingRepository>().fetchDevices();
      if (!mounted) return;
      setState(() {
        _data = data;
        _error = null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String? get _errorMessage => switch (_error) {
        ApiError e => e.message,
        null => null,
        _ => 'Terjadi kesalahan tidak terduga.',
      };

  @override
  Widget build(BuildContext context) {
    if (widget.embedded) return _buildBody();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Status TV'),
        actions: [
          IconButton(
            onPressed: _loading ? null : () => _load(silent: true),
            icon: _loading
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.refresh),
            tooltip: 'Muat ulang',
          ),
          const SizedBox(width: AppSpacing.sm),
        ],
      ),
      body: SafeArea(child: _buildBody()),
    );
  }

  Widget _buildBody() {
    if (_loading && _data.devices.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null && _data.devices.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline,
                  size: 44, color: AppColors.error),
              const SizedBox(height: AppSpacing.md),
              Text(
                _errorMessage ?? 'Gagal memuat status TV.',
                textAlign: TextAlign.center,
                style: AppTypography.bodyMd,
              ),
              const SizedBox(height: AppSpacing.lg),
              AsyncButton(
                label: 'Coba lagi',
                icon: Icons.refresh,
                onPressed: () => _load(),
              ),
            ],
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => _load(silent: true),
      backgroundColor: AppColors.surfaceLow,
      color: AppColors.primary,
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.md),
        children: [
          Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 720),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _SummaryPanel(data: _data),
                  const SizedBox(height: AppSpacing.md),
                  const _Tahap2Notice(),
                  const SizedBox(height: AppSpacing.md),
                  ..._data.devices.map(
                    (d) => Padding(
                      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                      child: _DeviceCard(
                        device: d,
                        threshold: _data.offlineThreshold,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SummaryPanel extends StatelessWidget {
  const _SummaryPanel({required this.data});

  final DeviceList data;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        boxShadow: AppShadow.card,
      ),
      child: Row(
        children: [
          _Count(
            value: data.onlineCount,
            label: 'Online',
            color: AppColors.statusAvailable,
          ),
          const SizedBox(width: AppSpacing.lg),
          _Count(
            value: data.offlineCount,
            label: 'Offline',
            color: data.offlineCount > 0
                ? AppColors.statusExpired
                : AppColors.onSurfaceVariant,
          ),
          if (data.unmappedCount > 0) ...[
            const SizedBox(width: AppSpacing.lg),
            _Count(
              value: data.unmappedCount,
              label: 'Tanpa station',
              color: AppColors.onSurfaceVariant,
            ),
          ],
          const Spacer(),
          Flexible(
            child: Text(
              'Dianggap offline setelah '
              '${formatDurationLabel(data.offlineThreshold.inMinutes)} '
              'tanpa kabar',
              textAlign: TextAlign.right,
              style: AppTypography.bodySm.copyWith(color: AppColors.outline),
            ),
          ),
        ],
      ),
    );
  }
}

class _Count extends StatelessWidget {
  const _Count({
    required this.value,
    required this.label,
    required this.color,
  });

  final int value;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: '$label: $value',
      excludeSemantics: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('$value', style: AppTypography.moneyLg.copyWith(color: color)),
          Text(
            label,
            style: AppTypography.bodySm.copyWith(color: AppColors.onSurfaceVariant),
          ),
        ],
      ),
    );
  }
}

/// Peringatan jujur bahwa angka di layar ini belum nyata.
///
/// Tanpa catatan ini operator bisa menyimpulkan TV benar-benar offline,
/// padahal heartbeat-nya belum ada sama sekali (Tahap 2).
class _Tahap2Notice extends StatelessWidget {
  const _Tahap2Notice();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md - 2),
      decoration: BoxDecoration(
        color: AppColors.statusWarning.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(
          color: AppColors.statusWarning.withValues(alpha: 0.28),
        ),
      ),
      child: Row(
        children: [
          const Icon(Icons.info_outline,
              size: 18, color: AppColors.statusWarning),
          const SizedBox(width: AppSpacing.sm + 2),
          Expanded(
            child: Text(
              'Aplikasi TV belum dibuat, jadi belum ada TV yang benar-benar '
              'mengirim kabar. Angka di layar ini masih data contoh.',
              style: AppTypography.bodySm
                  .copyWith(color: AppColors.statusWarning),
            ),
          ),
        ],
      ),
    );
  }
}

class _DeviceCard extends StatelessWidget {
  const _DeviceCard({required this.device, required this.threshold});

  final Device device;
  final Duration threshold;

  @override
  Widget build(BuildContext context) {
    final color =
        device.isOnline ? AppColors.statusAvailable : AppColors.statusExpired;

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadius.lg),
        boxShadow: AppShadow.card,
      ),
      child: Material(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        clipBehavior: Clip.antiAlias,
        child: Row(
          children: [
            Container(width: AppSize.statusRail, height: 88, color: color),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.md - 2),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(
                          device.station?.code ?? 'Tanpa station',
                          style: AppTypography.headlineSm.copyWith(
                            color: device.isMapped
                                ? AppColors.onSurface
                                : AppColors.onSurfaceVariant,
                          ),
                        ),
                        const SizedBox(width: AppSpacing.sm),
                        Icon(
                          device.isOnline ? Icons.wifi : Icons.wifi_off,
                          size: 15,
                          color: color,
                        ),
                        const SizedBox(width: AppSpacing.xs),
                        Text(
                          device.isOnline ? 'Online' : 'Offline',
                          style: AppTypography.bodySm.copyWith(color: color),
                        ),
                        const Spacer(),
                        _LastSeen(device: device),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    Text(
                      device.hardwareLabel,
                      style: AppTypography.bodySm
                          .copyWith(color: AppColors.onSurfaceVariant),
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: AppSpacing.xs),
                    Row(
                      children: [
                        _MetaChip(
                          icon: Icons.tag,
                          label: device.appVersion == null
                              ? 'Versi tidak diketahui'
                              : 'v${device.appVersion}',
                        ),
                        if (!device.isMapped) ...[
                          const SizedBox(width: AppSpacing.sm),
                          const _MetaChip(
                            icon: Icons.link_off,
                            label: 'Belum dipasangkan',
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Kapan terakhir mengirim kabar.
///
/// Satu-satunya bagian yang berlangganan ticker — "3 menit lalu" harus
/// bertambah sendiri, kalau tidak operator melihat angka basi dan
/// menyimpulkan TV baru saja hidup.
class _LastSeen extends StatelessWidget {
  const _LastSeen({required this.device});

  final Device device;

  @override
  Widget build(BuildContext context) {
    if (device.lastSeenAt == null) {
      return Text(
        'Belum pernah',
        style: AppTypography.bodySm.copyWith(color: AppColors.outline),
      );
    }

    return Consumer<AppTicker>(
      builder: (context, _, __) {
        final now = ServerTime.instance.now;
        return Text(
          formatRelative(device.lastSeenAt!, now),
          style: AppTypography.bodySm.copyWith(
            color:
                device.isOnline ? AppColors.onSurfaceVariant : AppColors.statusExpired,
          ),
        );
      },
    );
  }
}

class _MetaChip extends StatelessWidget {
  const _MetaChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.sm - 2,
        vertical: 3,
      ),
      decoration: BoxDecoration(
        color: AppColors.surfaceContainer,
        borderRadius: BorderRadius.circular(AppRadius.pill),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 12, color: AppColors.outline),
          const SizedBox(width: AppSpacing.xs),
          Text(
            label,
            style: AppTypography.bodySm
                .copyWith(color: AppColors.outline, fontSize: 11),
          ),
        ],
      ),
    );
  }
}
