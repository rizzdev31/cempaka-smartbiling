import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../core/util/format.dart';
import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import '../session/session_detail_screen.dart';
import '../settings/settings_screen.dart';
import '../widgets/confirm_dialog.dart';
import '../widgets/connection_banner.dart';
import '../widgets/station_card.dart';
import 'dashboard_controller.dart';
import 'start_session_sheet.dart';

/// Layar utama operator.
///
/// UI-UX-SPEC §3: enam station harus terlihat **tanpa scroll** —
/// grid 3×2 landscape, 2×3 portrait. Operator melirik, tidak membaca.
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<DashboardController>().load();
    });
  }

  Future<void> _openStation(Station station) async {
    final ctrl = context.read<DashboardController>();

    // Station kosong -> mulai sesi baru.
    if (station.session == null) {
      if (station.status != StationMasterStatus.active) return;
      final session = await showStartSessionSheet(
        context,
        station: station,
        packages: ctrl.packages,
        onSubmit: ({
          required packageId,
          required mode,
          customerName,
          required idempotencyKey,
        }) =>
            ctrl.startSession(
          stationId: station.id,
          packageId: packageId,
          mode: mode,
          customerName: customerName,
          idempotencyKey: idempotencyKey,
        ),
      );
      if (session == null || !mounted) return;
      showSuccess(context, 'Sesi ${session.code} dibuat di ${station.code}.');
      await _openSessionDetail(session.id);
      return;
    }

    await _openSessionDetail(station.session!.id);
  }

  Future<void> _openSessionDetail(String sessionId) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SessionDetailScreen(sessionId: sessionId),
      ),
    );
    if (!mounted) return;
    await context.read<DashboardController>().refresh();
  }

  @override
  Widget build(BuildContext context) {
    final ctrl = context.watch<DashboardController>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Cempaka Billing'),
        titleTextStyle: AppTypography.screenTitle.copyWith(
          color: AppColors.text,
        ),
        actions: [
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: AppSpacing.sm),
            child: Center(child: ConnectionBanner()),
          ),
          IconButton(
            onPressed: ctrl.loading ? null : () => ctrl.refresh(),
            icon: const Icon(Icons.refresh),
            tooltip: 'Muat ulang',
          ),
          IconButton(
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const SettingsScreen()),
            ),
            icon: const Icon(Icons.settings_outlined),
            tooltip: 'Pengaturan',
          ),
          const SizedBox(width: AppSpacing.sm),
        ],
      ),
      body: Column(
        children: [
          const DevDiagnosticBar(),
          _SummaryStrip(ctrl: ctrl),
          Expanded(child: _buildBody(ctrl)),
        ],
      ),
    );
  }

  Widget _buildBody(DashboardController ctrl) {
    if (ctrl.loading && !ctrl.hasData) {
      return const _StationGridSkeleton();
    }

    if (ctrl.error != null && !ctrl.hasData) {
      return _ErrorState(
        message: ctrl.errorMessage ?? 'Gagal memuat data.',
        onRetry: () => ctrl.load(),
      );
    }

    return RefreshIndicator(
      onRefresh: ctrl.refresh,
      child: LayoutBuilder(
        builder: (context, constraints) {
          // Grid 3×2 di landscape, 2×3 di portrait — enam kartu tanpa scroll.
          final landscape = constraints.maxWidth >= constraints.maxHeight;
          final columns = landscape ? 3 : 2;
          final rows = landscape ? 2 : 3;

          final gridHeight = constraints.maxHeight -
              (AppSpacing.md * 2) -
              (AppSpacing.md * (rows - 1));
          final gridWidth = constraints.maxWidth -
              (AppSpacing.md * 2) -
              (AppSpacing.md * (columns - 1));

          final tileHeight = gridHeight / rows;
          final tileWidth = gridWidth / columns;

          return GridView.builder(
            padding: const EdgeInsets.all(AppSpacing.md),
            physics: const AlwaysScrollableScrollPhysics(),
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: columns,
              mainAxisSpacing: AppSpacing.md,
              crossAxisSpacing: AppSpacing.md,
              childAspectRatio:
                  tileHeight <= 0 ? 1.4 : (tileWidth / tileHeight),
            ),
            itemCount: ctrl.stations.length,
            itemBuilder: (context, i) {
              final station = ctrl.stations[i];
              return StationCard(
                station: station,
                onTap: () => _openStation(station),
              );
            },
          );
        },
      ),
    );
  }
}

/// Ringkasan di atas grid — angka yang paling sering ditanya.
class _SummaryStrip extends StatelessWidget {
  const _SummaryStrip({required this.ctrl});

  final DashboardController ctrl;

  @override
  Widget build(BuildContext context) {
    if (!ctrl.hasData) return const SizedBox.shrink();

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.sm + 2,
      ),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: AppColors.border)),
      ),
      child: Row(
        children: [
          _Stat(
            icon: Icons.play_circle_outline,
            color: AppColors.statusActive,
            label: 'Bermain',
            value: '${ctrl.activeCount}',
          ),
          _Stat(
            icon: Icons.circle_outlined,
            color: AppColors.statusAvailable,
            label: 'Tersedia',
            value: '${ctrl.availableCount}',
          ),
          if (ctrl.offlineDeviceCount > 0)
            _Stat(
              icon: Icons.wifi_off,
              color: AppColors.statusOffline,
              label: 'TV offline',
              value: '${ctrl.offlineDeviceCount}',
            ),
          const Spacer(),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                'Tagihan berjalan',
                style:
                    AppTypography.caption.copyWith(color: AppColors.textMuted),
              ),
              Text(
                formatRupiah(ctrl.openBalance),
                style:
                    AppTypography.money.copyWith(color: AppColors.accent),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat({
    required this.icon,
    required this.color,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final Color color;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: AppSpacing.lg),
      child: Semantics(
        label: '$label: $value',
        excludeSemantics: true,
        child: Row(
          children: [
            Icon(icon, size: 16, color: color),
            const SizedBox(width: AppSpacing.xs + 2),
            Text(value, style: AppTypography.money.copyWith(color: color)),
            const SizedBox(width: AppSpacing.xs + 2),
            Text(
              label,
              style:
                  AppTypography.caption.copyWith(color: AppColors.textMuted),
            ),
          ],
        ),
      ),
    );
  }
}

/// Skeleton, bukan spinner penuh layar — UI-UX-SPEC §5.
class _StationGridSkeleton extends StatelessWidget {
  const _StationGridSkeleton();

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      padding: const EdgeInsets.all(AppSpacing.md),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        mainAxisSpacing: AppSpacing.md,
        crossAxisSpacing: AppSpacing.md,
        childAspectRatio: 1.4,
      ),
      itemCount: 6,
      itemBuilder: (_, __) => Container(
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(AppRadius.card),
          border: Border.all(color: AppColors.border),
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.cloud_off_outlined,
                size: 48, color: AppColors.statusOffline),
            const SizedBox(height: AppSpacing.md),
            Text(
              message,
              textAlign: TextAlign.center,
              style: AppTypography.body,
            ),
            const SizedBox(height: AppSpacing.xs),
            Text(
              'Periksa alamat server di Pengaturan.',
              textAlign: TextAlign.center,
              style:
                  AppTypography.caption.copyWith(color: AppColors.textMuted),
            ),
            const SizedBox(height: AppSpacing.lg),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                OutlinedButton.icon(
                  onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => const SettingsScreen(),
                    ),
                  ),
                  icon: const Icon(Icons.settings_outlined),
                  label: const Text('Pengaturan'),
                ),
                const SizedBox(width: AppSpacing.sm),
                FilledButton.icon(
                  onPressed: onRetry,
                  icon: const Icon(Icons.refresh),
                  label: const Text('Coba lagi'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
