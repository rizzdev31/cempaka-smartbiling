import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../core/util/format.dart';
import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import '../../domain/repositories/billing_repository.dart';
import '../device/device_screen.dart';
import '../fnb/fnb_queue_screen.dart';
import '../session/session_detail_screen.dart';
import '../shift/shift_screen.dart';
import '../settings/settings_screen.dart';
import '../widgets/brand_mark.dart';
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
        repo: context.read<BillingRepository>(),
        onSubmit: ({
          required packageId,
          required mode,
          customerId,
          customerName,
          required idempotencyKey,
        }) =>
            ctrl.startSession(
          stationId: station.id,
          packageId: packageId,
          mode: mode,
          customerId: customerId,
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
      body: SafeArea(
        child: Column(
          children: [
            _Header(ctrl: ctrl),
            const DevDiagnosticBar(),
            if (ctrl.hasData) _SummaryStrip(ctrl: ctrl),
            Expanded(child: _buildBody(ctrl)),
            if (ctrl.hasData) _ActionBar(ctrl: ctrl, onOpenFnb: _openFnbQueue),
          ],
        ),
      ),
    );
  }

  Future<void> _openFnbQueue() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const FnbQueueScreen()),
    );
    if (!mounted) return;
    // Status order mempengaruhi Open Tab, jadi dashboard ikut disegarkan.
    await context.read<DashboardController>().refresh();
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
      backgroundColor: AppColors.surfaceRaised,
      color: AppColors.primary,
      child: LayoutBuilder(
        builder: (context, constraints) {
          // Grid 3×2 di landscape, 2×3 di portrait — enam kartu tanpa scroll.
          final landscape = constraints.maxWidth >= constraints.maxHeight;
          final columns = landscape ? 3 : 2;
          final rows = landscape ? 2 : 3;

          const gutter = AppSpacing.md;
          final gridHeight =
              constraints.maxHeight - (gutter * 2) - (gutter * (rows - 1));
          final gridWidth =
              constraints.maxWidth - (gutter * 2) - (gutter * (columns - 1));

          final tileWidth = gridWidth / columns;

          // Tinggi kartu dijamin minimum [minStationCardHeight].
          //
          // Target spec adalah enam kartu tanpa scroll (UI-UX-SPEC §3), dan
          // pada tablet tinggi kartu selalu jauh di atas minimum ini. Tapi
          // di jendela yang sangat pendek — misalnya Chrome saat
          // pengembangan — membagi rata akan membuat kartu lebih pendek
          // daripada isinya dan memunculkan overflow. Lebih baik grid-nya
          // bisa di-scroll daripada tampilan rusak.
          final tileHeight =
              math.max(gridHeight / rows, minStationCardHeight);
          final tileWidth2 = tileWidth <= 0 ? 1.0 : tileWidth;

          return GridView.builder(
            padding: const EdgeInsets.all(gutter),
            physics: const AlwaysScrollableScrollPhysics(),
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: columns,
              mainAxisSpacing: gutter,
              crossAxisSpacing: gutter,
              childAspectRatio: tileWidth2 / tileHeight,
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

/// Header: merek di kiri, status koneksi dan aksi di kanan.
///
/// Memakai [BrandMark] supaya nama dan logo tetap benar saat mereknya
/// berganti per pengguna (OD-012).
class _Header extends StatelessWidget {
  const _Header({required this.ctrl});

  final DashboardController ctrl;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: AppSize.headerHeight,
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
      child: Row(
        children: [
          const BrandMark(),
          const Spacer(),
          const ConnectionBanner(),
          const SizedBox(width: AppSpacing.sm),
          IconButton(
            onPressed: ctrl.loading ? null : () => ctrl.refresh(),
            icon: ctrl.loading
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.refresh),
            tooltip: 'Muat ulang',
          ),
          IconButton(
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const SettingsScreen()),
            ),
            icon: const Icon(Icons.settings_outlined),
            tooltip: 'Pengaturan',
          ),
        ],
      ),
    );
  }
}

/// Bar aksi di bawah grid.
///
/// Bukan navigasi utama — ini pintasan ke layar kerja yang sering dibuka.
/// Ditaruh di bawah karena paling mudah dijangkau jempol saat tablet
/// diletakkan di meja kasir. Akan menampung Shift dan Device nanti.
class _ActionBar extends StatelessWidget {
  const _ActionBar({required this.ctrl, required this.onOpenFnb});

  final DashboardController ctrl;
  final VoidCallback onOpenFnb;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.md,
        0,
        AppSpacing.md,
        AppSpacing.md,
      ),
      child: Row(
        children: [
          _ActionTile(
            icon: Icons.restaurant_outlined,
            label: 'Antrian F&B',
            badge: ctrl.fnbActionableCount,
            onTap: onOpenFnb,
          ),
          const SizedBox(width: AppSpacing.sm),
          _ActionTile(
            icon: Icons.badge_outlined,
            label: 'Shift',
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const ShiftScreen()),
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          _ActionTile(
            icon: Icons.tv_outlined,
            label: 'Status TV',
            badge: ctrl.offlineDeviceCount,
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const DeviceScreen()),
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({
    required this.icon,
    required this.label,
    required this.onTap,
    this.badge = 0,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final int badge;

  @override
  Widget build(BuildContext context) {
    final hasBadge = badge > 0;

    return Semantics(
      button: true,
      label: hasBadge ? '$label, $badge order menunggu' : label,
      excludeSemantics: true,
      child: Material(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.card),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(AppRadius.card),
          child: Container(
            height: AppSize.minTouchTarget + 4,
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
            child: Row(
              children: [
                Icon(
                  icon,
                  size: 20,
                  color: hasBadge ? AppColors.accent : AppColors.textMuted,
                ),
                const SizedBox(width: AppSpacing.sm + 2),
                Text(label, style: AppTypography.cardLabel),
                if (hasBadge) ...[
                  const SizedBox(width: AppSpacing.sm + 2),
                  Container(
                    constraints: const BoxConstraints(minWidth: 22),
                    height: 22,
                    alignment: Alignment.center,
                    padding: const EdgeInsets.symmetric(
                      horizontal: AppSpacing.sm - 2,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.accent,
                      borderRadius: BorderRadius.circular(AppRadius.chip),
                    ),
                    child: Text(
                      '$badge',
                      style: AppTypography.moneySmall.copyWith(
                        color: AppColors.bg,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
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
    return Container(
      margin: const EdgeInsets.fromLTRB(
        AppSpacing.md,
        AppSpacing.xs,
        AppSpacing.md,
        0,
      ),
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.sm + 4,
      ),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.card),
        boxShadow: AppShadow.card,
      ),
      child: Row(
        children: [
          _Stat(
            color: AppColors.statusActive,
            label: 'Bermain',
            value: '${ctrl.activeCount}',
          ),
          const _StatDivider(),
          _Stat(
            color: AppColors.statusAvailable,
            label: 'Tersedia',
            value: '${ctrl.availableCount}',
          ),
          if (ctrl.offlineDeviceCount > 0) ...[
            const _StatDivider(),
            _Stat(
              color: AppColors.statusOffline,
              label: 'TV offline',
              value: '${ctrl.offlineDeviceCount}',
            ),
          ],
          const Spacer(),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                'TAGIHAN BERJALAN',
                style: AppTypography.overline
                    .copyWith(color: AppColors.textFaint),
              ),
              const SizedBox(height: 2),
              Text(
                formatRupiah(ctrl.openBalance),
                style: AppTypography.moneyLarge
                    .copyWith(color: AppColors.accent),
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
    required this.color,
    required this.label,
    required this.value,
  });

  final Color color;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: '$label: $value',
      excludeSemantics: true,
      child: Row(
        children: [
          Container(
            width: 7,
            height: 7,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: AppSpacing.sm),
          Text(value, style: AppTypography.money.copyWith(color: color)),
          const SizedBox(width: AppSpacing.xs + 2),
          Text(
            label,
            style: AppTypography.caption.copyWith(color: AppColors.textMuted),
          ),
        ],
      ),
    );
  }
}

class _StatDivider extends StatelessWidget {
  const _StatDivider();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 1,
      height: 18,
      margin: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
      color: AppColors.borderSubtle,
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
            Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(AppRadius.card),
              ),
              child: const Icon(Icons.cloud_off_outlined,
                  size: 26, color: AppColors.statusOffline),
            ),
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
                  icon: const Icon(Icons.settings_outlined, size: 18),
                  label: const Text('Pengaturan'),
                ),
                const SizedBox(width: AppSpacing.sm),
                FilledButton.icon(
                  onPressed: onRetry,
                  icon: const Icon(Icons.refresh, size: 18),
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
