import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../core/util/format.dart';
import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import '../../domain/repositories/billing_repository.dart';
import '../session/session_actions.dart';
import '../session/session_detail_controller.dart';
import '../session/session_detail_screen.dart';
import '../widgets/confirm_dialog.dart';
import '../widgets/connection_banner.dart';
import '../widgets/station_card.dart';
import 'dashboard_controller.dart';
import 'start_session_sheet.dart';

/// Monitor stasiun — layar utama operator.
///
/// UI-UX-SPEC §3: enam station harus terlihat **tanpa scroll**.
/// Grid 3×2 di layar lebar, menyesuaikan jumlah kolom pada layar sempit.
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key, this.embedded = false});

  /// `true` saat dipasang di dalam `AppShell` — shell sudah punya header.
  final bool embedded;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final ctrl = context.read<DashboardController>();
      if (!ctrl.hasData) ctrl.load();
    });
  }

  DashboardController get _ctrl => context.read<DashboardController>();

  // ── Aksi ────────────────────────────────────────────────────────────

  Future<void> _openDetail(String sessionId) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SessionDetailScreen(sessionId: sessionId),
      ),
    );
    if (mounted) await _ctrl.refresh();
  }

  Future<void> _start(Station station) async {
    if (station.status != StationMasterStatus.active) return;

    final session = await showStartSessionSheet(
      context,
      station: station,
      packages: _ctrl.packages,
      repo: context.read<BillingRepository>(),
      onSubmit: ({
        required packageId,
        required mode,
        customerId,
        customerName,
        required idempotencyKey,
      }) =>
          _ctrl.startSession(
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
    await _openDetail(session.id);
  }

  /// Controller sementara untuk satu aksi cepat dari kartu.
  ///
  /// Dibuang setelah dipakai; layar detail punya controller-nya sendiri.
  Future<SessionDetailController> _sessionController(String sessionId) async {
    final c = SessionDetailController(
      context.read<BillingRepository>(),
      sessionId,
    );
    await c.load();
    return c;
  }

  /// Tambah durasi langsung dari kartu.
  ///
  /// Tetap pakai konfirmasi walau ini "aksi cepat": salah tap +1j menagih
  /// customer satu jam yang tidak diminta, dan kontrak tidak punya jalur
  /// pembatalan untuk itu. Satu tap tambahan lebih murah daripada
  /// salah tagih.
  Future<void> _quickExtend(Station station, int minutes) async {
    final sessionId = station.session?.id;
    if (sessionId == null) return;

    final ctrl = await _sessionController(sessionId);
    final session = ctrl.session;
    if (session == null || !mounted) {
      ctrl.dispose();
      return;
    }

    final price = ((session.hourlyRate * minutes) + 59) ~/ 60;
    final ok = await showConfirmDialog(
      context,
      title: 'Tambah ${formatDurationLabel(minutes)}?',
      message: '${station.code} · ${session.customerLabel}\n'
          'Perkiraan tambahan ${formatRupiah(price)}. '
          'Harga final dihitung server dan masuk Open Tab.',
      confirmLabel: 'Tambah ${formatDurationLabel(minutes)}',
    );

    if (!ok || !mounted) {
      ctrl.dispose();
      return;
    }

    try {
      final result = await ctrl.extend(
        durationMinutes: minutes,
        idempotencyKey: SessionDetailController.newIdempotencyKey(),
      );
      if (!mounted) return;
      showSuccess(
        context,
        '${station.code} +${formatDurationLabel(result.durationMinutes)} · '
        '${formatRupiah(result.price)} · selesai '
        '${formatClock(result.newEndAt)}',
      );
      await _ctrl.refresh();
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      ctrl.dispose();
    }
  }

  Future<void> _quickFnb(Station station) async {
    final sessionId = station.session?.id;
    if (sessionId == null) return;

    final ctrl = await _sessionController(sessionId);
    if (!mounted) {
      ctrl.dispose();
      return;
    }
    await showAddFnbSheet(context, ctrl: ctrl);
    ctrl.dispose();
    if (mounted) await _ctrl.refresh();
  }

  Future<void> _quickPay(Station station) async {
    final sessionId = station.session?.id;
    if (sessionId == null) return;

    final ctrl = await _sessionController(sessionId);
    final session = ctrl.session;
    if (session == null || !mounted) {
      ctrl.dispose();
      return;
    }

    if (session.status == SessionStatus.pendingPayment) {
      final paid = await showPaymentDialog(
        context,
        ctrl: ctrl,
        session: session,
        suggestedAmount: session.totals.balanceDue,
        title: 'Pembayaran Rental — ${station.code}',
      );
      if (paid && mounted) {
        showSuccess(context, 'Pembayaran diterima. Sesi dimulai.');
      }
    } else {
      // Sesi berjalan -> checkout. Ini menutup sesi, jadi dibuka lewat
      // dialog checkout yang menampilkan rincian lengkap, bukan tap cepat.
      final result = await showCheckoutDialog(
        context,
        ctrl: ctrl,
        session: session,
      );
      if (result != null && mounted) {
        await showReceiptDialog(context, receipt: result.receipt);
      }
    }

    ctrl.dispose();
    if (mounted) await _ctrl.refresh();
  }

  @override
  Widget build(BuildContext context) {
    final ctrl = context.watch<DashboardController>();

    final content = Column(
      children: [
        const DevDiagnosticBar(),
        _FilterBar(ctrl: ctrl),
        Expanded(child: _buildGrid(ctrl)),
        if (ctrl.hasData) _ShiftStrip(ctrl: ctrl),
      ],
    );

    if (widget.embedded) return content;

    return Scaffold(
      appBar: AppBar(title: const Text('Monitor Stasiun')),
      body: SafeArea(child: content),
    );
  }

  Widget _buildGrid(DashboardController ctrl) {
    if (ctrl.loading && !ctrl.hasData) return const _GridSkeleton();

    if (ctrl.error != null && !ctrl.hasData) {
      return _ErrorState(
        message: ctrl.errorMessage ?? 'Gagal memuat data.',
        onRetry: () => ctrl.load(),
      );
    }

    final stations = ctrl.visibleStations;
    if (stations.isEmpty) {
      return _EmptyFilterState(ctrl: ctrl);
    }

    return RefreshIndicator(
      onRefresh: ctrl.refresh,
      backgroundColor: AppColors.surfaceLow,
      color: AppColors.primaryContainer,
      child: LayoutBuilder(
        builder: (context, c) {
          const gutter = AppSpacing.gutter;
          const pad = AppSpacing.gutterLg;

          // Kolom ditentukan lebar yang tersedia, bukan orientasi: shell
          // sudah memakan sebagian lebar, jadi orientasi perangkat bukan
          // ukuran yang tepat.
          final columns = c.maxWidth >= 1100
              ? 3
              : c.maxWidth >= 700
                  ? 2
                  : 1;
          final rows = (stations.length / columns).ceil();

          final tileWidth =
              (c.maxWidth - pad * 2 - gutter * (columns - 1)) / columns;
          final available = c.maxHeight - pad * 2 - gutter * (rows - 1);

          // Tinggi kartu dijamin minimum; kalau ruangnya kurang, grid
          // di-scroll alih-alih kartunya dipaksa mengecil sampai rusak.
          final tileHeight =
              math.max(available / rows, AppSize.stationCardMinHeight);

          return GridView.builder(
            padding: const EdgeInsets.all(pad),
            physics: const AlwaysScrollableScrollPhysics(),
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: columns,
              mainAxisSpacing: gutter,
              crossAxisSpacing: gutter,
              childAspectRatio:
                  (tileWidth <= 0 ? 1.0 : tileWidth) / tileHeight,
            ),
            itemCount: stations.length,
            itemBuilder: (context, i) {
              final station = stations[i];
              final hasSession = station.session != null;

              return StationCard(
                station: station,
                hourlyRateHint: ctrl.cheapestHourlyRate,
                onTap: hasSession
                    ? () => _openDetail(station.session!.id)
                    : () => _start(station),
                onStart: hasSession ? null : () => _start(station),
                onExtend:
                    hasSession ? (m) => _quickExtend(station, m) : null,
                onAddFnb: hasSession ? () => _quickFnb(station) : null,
                onPay: hasSession ? () => _quickPay(station) : null,
              );
            },
          );
        },
      ),
    );
  }
}

// ─── Bar filter ───────────────────────────────────────────────────────

class _FilterBar extends StatefulWidget {
  const _FilterBar({required this.ctrl});

  final DashboardController ctrl;

  @override
  State<_FilterBar> createState() => _FilterBarState();
}

class _FilterBarState extends State<_FilterBar> {
  final _search = TextEditingController();
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  /// Debounce 250 ms: tanpa ini setiap ketikan membangun ulang grid enam
  /// kartu, dan terasa tersendat di tablet.
  void _onQuery(String v) {
    _debounce?.cancel();
    _debounce = Timer(
      const Duration(milliseconds: 250),
      () => widget.ctrl.setQuery(v),
    );
    setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final ctrl = widget.ctrl;
    final wide = MediaQuery.sizeOf(context).width >= 900;

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.gutterLg,
        vertical: AppSpacing.sm + 2,
      ),
      decoration: BoxDecoration(
        color: AppColors.surfaceLow.withValues(alpha: 0.4),
        border: const Border(
          bottom: BorderSide(color: AppColors.surfaceHigh),
        ),
      ),
      child: Row(
        children: [
          Expanded(
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  for (final f in StationFilter.values)
                    Padding(
                      padding: const EdgeInsets.only(right: AppSpacing.sm),
                      child: _FilterChip(
                        filter: f,
                        count: ctrl.countFor(f),
                        selected: ctrl.filter == f,
                        onTap: () => ctrl.setFilter(f),
                      ),
                    ),
                ],
              ),
            ),
          ),
          if (wide) ...[
            const SizedBox(width: AppSpacing.md),
            SizedBox(
              width: 240,
              child: TextField(
                controller: _search,
                onChanged: _onQuery,
                textInputAction: TextInputAction.search,
                style: AppTypography.bodySm
                    .copyWith(color: AppColors.onSurface),
                decoration: InputDecoration(
                  isDense: true,
                  hintText: 'Cari station atau customer',
                  prefixIcon: const Icon(Icons.search, size: 18),
                  prefixIconConstraints:
                      const BoxConstraints(minWidth: 38, minHeight: 38),
                  suffixIcon: _search.text.isEmpty
                      ? null
                      : IconButton(
                          icon: const Icon(Icons.clear, size: 16),
                          tooltip: 'Hapus',
                          onPressed: () {
                            _search.clear();
                            ctrl.setQuery('');
                            setState(() {});
                          },
                        ),
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: AppSpacing.sm,
                    vertical: AppSpacing.sm + 2,
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.filter,
    required this.count,
    required this.selected,
    required this.onTap,
  });

  final StationFilter filter;
  final int count;
  final bool selected;
  final VoidCallback onTap;

  /// Warna titik mengikuti status yang diwakili filter, supaya chip dan
  /// kartu memakai bahasa warna yang sama.
  Color? get _dotColor => switch (filter) {
        StationFilter.all => null,
        StationFilter.playing => AppColors.statusActive,
        StationFilter.available => AppColors.statusAvailable,
        StationFilter.warning => AppColors.statusWarning,
        StationFilter.pending => AppColors.statusPendingPayment,
      };

  @override
  Widget build(BuildContext context) {
    final dot = _dotColor;

    return Semantics(
      button: true,
      selected: selected,
      label: '${filter.label}, $count station',
      excludeSemantics: true,
      child: Material(
        color: selected ? AppColors.primaryContainer : AppColors.surfaceLow,
        borderRadius: BorderRadius.circular(AppRadius.pill),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(AppRadius.pill),
          child: Container(
            height: 36,
            padding:
                const EdgeInsets.symmetric(horizontal: AppSpacing.md - 2),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(AppRadius.pill),
              border: Border.all(
                color: selected
                    ? Colors.transparent
                    : AppColors.surfaceHigh,
              ),
              boxShadow: selected ? AppShadow.glowPrimary : null,
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (dot != null && !selected) ...[
                  Container(
                    width: 8,
                    height: 8,
                    decoration:
                        BoxDecoration(color: dot, shape: BoxShape.circle),
                  ),
                  const SizedBox(width: AppSpacing.sm - 2),
                ],
                Text(
                  filter.label,
                  style: AppTypography.labelMd.copyWith(
                    color: selected
                        ? AppColors.onPrimaryContainer
                        : AppColors.onSurfaceVariant,
                    fontWeight:
                        selected ? FontWeight.w600 : FontWeight.w500,
                  ),
                ),
                const SizedBox(width: AppSpacing.sm - 2),
                Container(
                  constraints: const BoxConstraints(minWidth: 20),
                  alignment: Alignment.center,
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  decoration: BoxDecoration(
                    color: selected
                        ? AppColors.onPrimaryContainer
                            .withValues(alpha: 0.22)
                        : AppColors.surfaceHigh,
                    borderRadius: BorderRadius.circular(AppRadius.pill),
                  ),
                  child: Text(
                    '$count',
                    style: AppTypography.labelSm.copyWith(
                      color: selected
                          ? AppColors.onPrimaryContainer
                          : AppColors.outline,
                      fontWeight: FontWeight.w700,
                    ),
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

// ─── Strip shift di bawah grid ────────────────────────────────────────

class _ShiftStrip extends StatelessWidget {
  const _ShiftStrip({required this.ctrl});

  final DashboardController ctrl;

  @override
  Widget build(BuildContext context) {
    final shift = ctrl.currentShift;

    return Container(
      margin: const EdgeInsets.fromLTRB(
        AppSpacing.gutterLg,
        0,
        AppSpacing.gutterLg,
        AppSpacing.md,
      ),
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.lg,
        vertical: AppSpacing.sm + 2,
      ),
      decoration: BoxDecoration(
        color: AppColors.surfaceLow,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: AppColors.surfaceHigh),
      ),
      child: Row(
        children: [
          Icon(
            shift == null ? Icons.badge_outlined : Icons.badge,
            size: 16,
            color: shift == null ? AppColors.outline : AppColors.secondary,
          ),
          const SizedBox(width: AppSpacing.sm),
          Flexible(
            child: Text(
              shift == null
                  ? 'Shift belum dibuka'
                  : 'Shift ${shift.operator.name} · '
                      'buka ${formatClock(shift.openedAt)}',
              style: AppTypography.bodySm
                  .copyWith(color: AppColors.onSurfaceVariant),
              overflow: TextOverflow.ellipsis,
            ),
          ),
          const Spacer(),
          if (ctrl.fnbActionableCount > 0) ...[
            const Icon(Icons.receipt_outlined,
                size: 15, color: AppColors.tertiaryContainer),
            const SizedBox(width: AppSpacing.xs + 2),
            Text(
              '${ctrl.fnbActionableCount} antrian F&B',
              style: AppTypography.labelSm
                  .copyWith(color: AppColors.tertiaryContainer),
            ),
            const SizedBox(width: AppSpacing.md),
          ],
          if (shift != null)
            Text(
              'Tunai ${formatRupiah(shift.summary.cash)}',
              style:
                  AppTypography.labelSm.copyWith(color: AppColors.outline),
            ),
        ],
      ),
    );
  }
}

// ─── Keadaan kosong & error ───────────────────────────────────────────

class _EmptyFilterState extends StatelessWidget {
  const _EmptyFilterState({required this.ctrl});

  final DashboardController ctrl;

  @override
  Widget build(BuildContext context) {
    final filtered = ctrl.filter != StationFilter.all;
    final searching = ctrl.query.isNotEmpty;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 52,
              height: 52,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: AppColors.surfaceLow,
                borderRadius: BorderRadius.circular(AppRadius.lg),
              ),
              child: Icon(
                searching ? Icons.search_off : Icons.filter_alt_off_outlined,
                size: 24,
                color: AppColors.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              searching
                  ? 'Tidak ada station yang cocok dengan "${ctrl.query}"'
                  : 'Tidak ada station berstatus '
                      '"${ctrl.filter.label}" saat ini',
              textAlign: TextAlign.center,
              style: AppTypography.bodyMd,
            ),
            if (filtered) ...[
              const SizedBox(height: AppSpacing.md),
              OutlinedButton.icon(
                onPressed: () => ctrl.setFilter(StationFilter.all),
                icon: const Icon(Icons.clear_all, size: 18),
                label: const Text('Tampilkan semua'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _GridSkeleton extends StatelessWidget {
  const _GridSkeleton();

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      padding: const EdgeInsets.all(AppSpacing.gutterLg),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        mainAxisSpacing: AppSpacing.gutter,
        crossAxisSpacing: AppSpacing.gutter,
        childAspectRatio: 1.15,
      ),
      itemCount: 6,
      itemBuilder: (_, __) => Container(
        decoration: BoxDecoration(
          color: AppColors.surfaceLow,
          borderRadius: BorderRadius.circular(AppRadius.lg),
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
              width: 52,
              height: 52,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: AppColors.surfaceLow,
                borderRadius: BorderRadius.circular(AppRadius.lg),
              ),
              child: const Icon(Icons.cloud_off_outlined,
                  size: 24, color: AppColors.statusOffline),
            ),
            const SizedBox(height: AppSpacing.md),
            Text(message,
                textAlign: TextAlign.center, style: AppTypography.bodyMd),
            const SizedBox(height: AppSpacing.xs),
            Text(
              'Periksa alamat server di Pengaturan.',
              textAlign: TextAlign.center,
              style: AppTypography.bodySm
                  .copyWith(color: AppColors.onSurfaceVariant),
            ),
            const SizedBox(height: AppSpacing.lg),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh, size: 18),
              label: const Text('Coba lagi'),
            ),
          ],
        ),
      ),
    );
  }
}
