import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../core/time/server_time.dart';
import '../../core/time/ticker.dart';
import '../../core/util/format.dart';
import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import '../../domain/repositories/billing_repository.dart';
import '../session/session_detail_screen.dart';
import '../widgets/confirm_dialog.dart';
import '../widgets/money_text.dart';
import 'fnb_queue_controller.dart';

/// Antrian F&B — PRD §18: "Incoming order, process, delivered/status".
///
/// Prinsip layar ini: **satu tap per order.**
/// Operator dapur sedang sibuk; memilih status dari dropdown berarti tiga
/// sentuhan untuk satu kemajuan. Setiap kartu punya satu tombol utama yang
/// memajukan order ke status berikutnya, dan itu saja.
///
/// Order dikelompokkan per status, bukan satu daftar panjang: "belum
/// disentuh" dan "siap diantar" adalah dua pertanyaan berbeda.
class FnbQueueScreen extends StatelessWidget {
  const FnbQueueScreen({super.key, this.embedded = false});

  /// `true` saat dipasang di dalam [AppShell]: shell sudah punya header,
  /// jadi layar ini tidak membuat AppBar sendiri.
  final bool embedded;

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (ctx) =>
          FnbQueueController(ctx.read<BillingRepository>())..load(),
      child: _FnbQueueBody(embedded: embedded),
    );
  }
}

class _FnbQueueBody extends StatefulWidget {
  const _FnbQueueBody({required this.embedded});

  final bool embedded;

  @override
  State<_FnbQueueBody> createState() => _FnbQueueBodyState();
}

class _FnbQueueBodyState extends State<_FnbQueueBody> {
  bool _showDone = false;

  Future<void> _advance(FnbQueueController ctrl, FnbOrder order) async {
    try {
      final status = await ctrl.advance(order);
      if (!mounted) return;
      showSuccess(
        context,
        '${order.code} → ${status.label.toLowerCase()}',
      );
    } catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  Future<void> _cancel(FnbQueueController ctrl, FnbOrder order) async {
    final ok = await showConfirmDialog(
      context,
      title: 'Batalkan order ${order.code}?',
      message: 'Order di ${order.stationCode} akan dibatalkan. '
          'Item yang sudah masuk Open Tab perlu disesuaikan terpisah.',
      confirmLabel: 'Batalkan Order',
      destructive: true,
    );
    if (!ok) return;

    try {
      await ctrl.cancel(order);
      if (mounted) showSuccess(context, '${order.code} dibatalkan.');
    } catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final ctrl = context.watch<FnbQueueController>();

    final content = Column(
      children: [
            _SegmentedToggle(
              showDone: _showDone,
              activeCount: ctrl.active.length,
              doneCount: ctrl.done.length,
              onChanged: (v) => setState(() => _showDone = v),
            ),
        Expanded(child: _buildBody(ctrl)),
      ],
    );

    if (widget.embedded) return content;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Antrian F&B'),
        actions: [
          IconButton(
            onPressed: ctrl.loading ? null : () => ctrl.refresh(),
            icon: const Icon(Icons.refresh),
            tooltip: 'Muat ulang',
          ),
          const SizedBox(width: AppSpacing.sm),
        ],
      ),
      body: SafeArea(child: content),
    );
  }

  Widget _buildBody(FnbQueueController ctrl) {
    if (ctrl.loading && !ctrl.hasData) {
      return const Center(child: CircularProgressIndicator());
    }
    if (ctrl.error != null && !ctrl.hasData) {
      return _ErrorState(
        message: ctrl.errorMessage ?? 'Gagal memuat antrian.',
        onRetry: ctrl.load,
      );
    }

    if (_showDone) {
      final done = ctrl.done;
      if (done.isEmpty) {
        return const _EmptyState(
          icon: Icons.history,
          title: 'Belum ada riwayat',
          message: 'Order yang sudah diantar atau dibatalkan akan muncul di sini.',
        );
      }
      return RefreshIndicator(
        onRefresh: ctrl.refresh,
        backgroundColor: AppColors.surfaceLow,
        color: AppColors.primary,
        child: ListView.separated(
          padding: const EdgeInsets.all(AppSpacing.md),
          itemCount: done.length,
          separatorBuilder: (_, __) => const SizedBox(height: AppSpacing.sm),
          itemBuilder: (_, i) => _OrderCard(
            order: done[i],
            readOnly: true,
          ),
        ),
      );
    }

    if (ctrl.active.isEmpty) {
      return const _EmptyState(
        icon: Icons.restaurant_outlined,
        title: 'Antrian kosong',
        message: 'Order baru dari operator atau customer akan muncul di sini.',
      );
    }

    // Urutan bagian mengikuti alur kerja dapur.
    const sections = [
      (FnbOrderStatus.pending, 'Belum diproses'),
      (FnbOrderStatus.processing, 'Sedang diproses'),
      (FnbOrderStatus.ready, 'Siap diantar'),
    ];

    return RefreshIndicator(
      onRefresh: ctrl.refresh,
      backgroundColor: AppColors.surfaceLow,
      color: AppColors.primary,
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.md),
        children: [
          for (final (status, title) in sections) ...[
            if (ctrl.ofStatus(status).isNotEmpty) ...[
              _SectionHeader(
                title: title,
                count: ctrl.ofStatus(status).length,
                color: _statusColor(status),
              ),
              const SizedBox(height: AppSpacing.sm),
              ...ctrl.ofStatus(status).map(
                    (o) => Padding(
                      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                      child: _OrderCard(
                        order: o,
                        busy: ctrl.isUpdating(o.id),
                        onAdvance: () => _advance(ctrl, o),
                        onCancel: () => _cancel(ctrl, o),
                      ),
                    ),
                  ),
              const SizedBox(height: AppSpacing.md),
            ],
          ],
        ],
      ),
    );
  }
}

Color _statusColor(FnbOrderStatus s) => switch (s) {
      FnbOrderStatus.pending => AppColors.statusPendingPayment,
      FnbOrderStatus.processing => AppColors.statusActive,
      FnbOrderStatus.ready => AppColors.statusAvailable,
      FnbOrderStatus.delivered => AppColors.onSurfaceVariant,
      FnbOrderStatus.cancelled => AppColors.statusOffline,
      FnbOrderStatus.unknown => AppColors.statusOffline,
    };

IconData _statusIcon(FnbOrderStatus s) => switch (s) {
      FnbOrderStatus.pending => Icons.fiber_new_outlined,
      FnbOrderStatus.processing => Icons.local_fire_department_outlined,
      FnbOrderStatus.ready => Icons.room_service_outlined,
      FnbOrderStatus.delivered => Icons.check_circle_outline,
      FnbOrderStatus.cancelled => Icons.cancel_outlined,
      FnbOrderStatus.unknown => Icons.help_outline,
    };

/// Label tombol utama — kata kerja, bukan nama status.
/// "Proses" lebih jelas daripada "Diproses" untuk sebuah tombol.
String _advanceLabel(FnbOrderStatus s) => switch (s) {
      FnbOrderStatus.pending => 'Proses',
      FnbOrderStatus.processing => 'Tandai Siap',
      FnbOrderStatus.ready => 'Antar',
      _ => 'Selesai',
    };

// ─── Kartu order ──────────────────────────────────────────────────────

class _OrderCard extends StatelessWidget {
  const _OrderCard({
    required this.order,
    this.busy = false,
    this.readOnly = false,
    this.onAdvance,
    this.onCancel,
  });

  final FnbOrder order;
  final bool busy;
  final bool readOnly;
  final Future<void> Function()? onAdvance;
  final Future<void> Function()? onCancel;

  @override
  Widget build(BuildContext context) {
    final color = _statusColor(order.status);
    final fromCustomer = order.source == 'CUSTOMER';

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: AppColors.surfaceHigh),
        boxShadow: readOnly ? null : AppShadow.card,
      ),
      child: Material(
        color: readOnly ? AppColors.surfaceContainer : AppColors.surfaceLowest,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          // Ketuk kartu -> buka sesi terkait. Operator sering perlu melihat
          // Open Tab-nya, bukan hanya order ini.
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => SessionDetailScreen(sessionId: order.sessionId),
            ),
          ),
          child: Row(
            children: [
              Container(width: AppSize.statusRail, height: 112, color: color),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.md - 2),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Baris 1 — station, kode order, umur
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          Text(
                            order.stationCode,
                            style: AppTypography.headlineSm.copyWith(
                              color: readOnly
                                  ? AppColors.onSurfaceVariant
                                  : AppColors.onSurface,
                            ),
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Text(
                            order.code,
                            style: AppTypography.bodySm
                                .copyWith(color: AppColors.outline),
                          ),
                          if (fromCustomer) ...[
                            const SizedBox(width: AppSpacing.sm),
                            const _SourceBadge(),
                          ],
                          const Spacer(),
                          _OrderAge(order: order, muted: readOnly),
                        ],
                      ),
                      const SizedBox(height: AppSpacing.sm),

                      // Baris 2 — isi order
                      Text(
                        order.items
                            .map((i) => '${i.qty}× ${i.name}')
                            .join(' · '),
                        style: AppTypography.bodyMd.copyWith(
                          color:
                              readOnly ? AppColors.onSurfaceVariant : AppColors.onSurface,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      if (order.note != null && order.note!.isNotEmpty) ...[
                        const SizedBox(height: AppSpacing.xs),
                        Row(
                          children: [
                            const Icon(Icons.sticky_note_2_outlined,
                                size: 13, color: AppColors.tertiaryContainer),
                            const SizedBox(width: AppSpacing.xs + 2),
                            Expanded(
                              child: Text(
                                order.note!,
                                style: AppTypography.bodySm
                                    .copyWith(color: AppColors.tertiaryContainer),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                        ),
                      ],
                      const SizedBox(height: AppSpacing.sm + 2),

                      // Baris 3 — total + aksi
                      Row(
                        children: [
                          Icon(_statusIcon(order.status),
                              size: 14, color: color),
                          const SizedBox(width: AppSpacing.xs + 2),
                          Text(
                            order.status.label,
                            style: AppTypography.bodySm.copyWith(color: color),
                          ),
                          const SizedBox(width: AppSpacing.md),
                          MoneyText.small(
                            order.total,
                            color: AppColors.onSurfaceVariant,
                          ),
                          const Spacer(),
                          if (!readOnly) ...[
                            // Tombol batal hanya muncul kalau statusnya
                            // memang boleh dibatalkan (kontrak §8), supaya
                            // operator tidak pernah menemui error yang
                            // sebenarnya bisa dicegah.
                            if (order.status.canCancel) ...[
                              IconButton(
                                onPressed: busy ? null : onCancel,
                                icon: const Icon(Icons.close, size: 18),
                                tooltip: 'Batalkan order',
                                style: IconButton.styleFrom(
                                  foregroundColor: AppColors.outline,
                                  minimumSize: const Size(40, 40),
                                ),
                              ),
                              const SizedBox(width: AppSpacing.xs),
                            ],
                            // Satu tombol, satu tap, satu kemajuan.
                            AsyncButton(
                              label: _advanceLabel(order.status),
                              icon: order.status == FnbOrderStatus.ready
                                  ? Icons.delivery_dining_outlined
                                  : Icons.arrow_forward,
                              onPressed: busy ? null : onAdvance,
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
      ),
    );
  }
}

/// Umur order.
///
/// Ini sinyal operasional, bukan hiasan: order yang menganggur 12 menit
/// perlu perhatian. Setelah 10 menit warnanya berubah oranye, setelah 20
/// menit merah — tanpa operator harus menghitung sendiri.
class _OrderAge extends StatelessWidget {
  const _OrderAge({required this.order, this.muted = false});

  final FnbOrder order;
  final bool muted;

  @override
  Widget build(BuildContext context) {
    return Consumer<AppTicker>(
      builder: (context, _, __) {
        final now = ServerTime.instance.now;
        final age = now.difference(order.createdAt);
        final stale = !muted &&
            order.status != FnbOrderStatus.ready &&
            age >= const Duration(minutes: 10);
        final veryStale = stale && age >= const Duration(minutes: 20);

        final color = muted
            ? AppColors.outline
            : veryStale
                ? AppColors.statusExpired
                : stale
                    ? AppColors.statusWarning
                    : AppColors.onSurfaceVariant;

        return Row(
          children: [
            Icon(
              stale ? Icons.warning_amber_rounded : Icons.schedule,
              size: 13,
              color: color,
            ),
            const SizedBox(width: AppSpacing.xs),
            Text(
              formatRelative(order.createdAt, now),
              style: AppTypography.bodySm.copyWith(color: color),
            ),
          ],
        );
      },
    );
  }
}

class _SourceBadge extends StatelessWidget {
  const _SourceBadge();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.sm - 2,
        vertical: 2,
      ),
      decoration: BoxDecoration(
        color: AppColors.statusCheckout.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(AppRadius.pill),
      ),
      child: Text(
        'Customer',
        style: AppTypography.bodySm.copyWith(
          color: AppColors.statusCheckout,
          fontSize: 11,
        ),
      ),
    );
  }
}

// ─── Potongan layar ───────────────────────────────────────────────────

class _SegmentedToggle extends StatelessWidget {
  const _SegmentedToggle({
    required this.showDone,
    required this.activeCount,
    required this.doneCount,
    required this.onChanged,
  });

  final bool showDone;
  final int activeCount;
  final int doneCount;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(
        AppSpacing.md,
        AppSpacing.xs,
        AppSpacing.md,
        AppSpacing.xs,
      ),
      padding: const EdgeInsets.all(AppSpacing.xs),
      decoration: BoxDecoration(
        color: AppColors.surfaceContainer,
        borderRadius: BorderRadius.circular(AppRadius.md + 2),
        border: Border.all(color: AppColors.surfaceHigh),
      ),
      child: Row(
        children: [
          Expanded(
            child: _ToggleTab(
              label: 'Aktif',
              count: activeCount,
              selected: !showDone,
              onTap: () => onChanged(false),
            ),
          ),
          Expanded(
            child: _ToggleTab(
              label: 'Riwayat',
              count: doneCount,
              selected: showDone,
              onTap: () => onChanged(true),
            ),
          ),
        ],
      ),
    );
  }
}

class _ToggleTab extends StatelessWidget {
  const _ToggleTab({
    required this.label,
    required this.count,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final int count;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      selected: selected,
      button: true,
      child: Material(
        color: selected ? AppColors.surface : Colors.transparent,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(AppRadius.md),
          child: Container(
            height: AppSize.minTouchTarget - 8,
            alignment: Alignment.center,
            child: Text(
              count > 0 ? '$label ($count)' : label,
              style: AppTypography.bodyLg.copyWith(
                color: selected ? AppColors.onSurface : AppColors.onSurfaceVariant,
                fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({
    required this.title,
    required this.count,
    required this.color,
  });

  final String title;
  final int count;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 7,
          height: 7,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: AppSpacing.sm),
        Text(
          title.toUpperCase(),
          style: AppTypography.labelSm.copyWith(color: AppColors.onSurfaceVariant),
        ),
        const SizedBox(width: AppSpacing.sm),
        Text(
          '$count',
          style: AppTypography.labelSm.copyWith(color: color),
        ),
      ],
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

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
                borderRadius: BorderRadius.circular(AppRadius.lg),
              ),
              child: Icon(icon, size: 26, color: AppColors.onSurfaceVariant),
            ),
            const SizedBox(height: AppSpacing.md),
            Text(title, style: AppTypography.headlineSm),
            const SizedBox(height: AppSpacing.xs),
            Text(
              message,
              textAlign: TextAlign.center,
              style:
                  AppTypography.bodySm.copyWith(color: AppColors.onSurfaceVariant),
            ),
          ],
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 44, color: AppColors.error),
            const SizedBox(height: AppSpacing.md),
            Text(message, textAlign: TextAlign.center,
                style: AppTypography.bodyMd),
            const SizedBox(height: AppSpacing.lg),
            AsyncButton(
              label: 'Coba lagi',
              icon: Icons.refresh,
              onPressed: onRetry,
            ),
          ],
        ),
      ),
    );
  }
}
