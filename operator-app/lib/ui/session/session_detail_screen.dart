import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/status_style.dart';
import '../../core/theme/tokens.dart';
import '../../core/util/format.dart';
import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import '../../domain/repositories/billing_repository.dart';
import '../widgets/confirm_dialog.dart';
import '../widgets/countdown_text.dart';
import '../widgets/money_text.dart';
import '../widgets/status_chip.dart';
import 'session_actions.dart';
import 'session_detail_controller.dart';

/// Detail satu sesi: timer, Open Tab, dan semua aksi operator.
///
/// Landscape: dua kolom — kiri timer + aksi, kanan Open Tab.
/// Portrait: satu kolom bergulir.
class SessionDetailScreen extends StatelessWidget {
  const SessionDetailScreen({super.key, required this.sessionId});

  final String sessionId;

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (ctx) =>
          SessionDetailController(ctx.read<BillingRepository>(), sessionId)
            ..load(),
      child: const _SessionDetailBody(),
    );
  }
}

class _SessionDetailBody extends StatelessWidget {
  const _SessionDetailBody();

  @override
  Widget build(BuildContext context) {
    final ctrl = context.watch<SessionDetailController>();
    final session = ctrl.session;

    return Scaffold(
      appBar: AppBar(
        title: Text(session == null
            ? 'Detail Sesi'
            : '${session.station.code} — ${session.code}'),
        actions: [
          IconButton(
            onPressed: ctrl.loading ? null : () => ctrl.refresh(),
            icon: const Icon(Icons.refresh),
            tooltip: 'Muat ulang',
          ),
          const SizedBox(width: AppSpacing.sm),
        ],
      ),
      body: switch ((ctrl.loading, session)) {
        (true, null) => const Center(child: CircularProgressIndicator()),
        (_, null) => _ErrorBody(
            message: ctrl.errorMessage ?? 'Sesi tidak bisa dimuat.',
            onRetry: ctrl.load,
          ),
        (_, final s?) => LayoutBuilder(
            builder: (context, c) {
              final landscape = c.maxWidth >= c.maxHeight;
              final left = _SummaryPane(session: s);
              final right = _OpenTabPane(session: s);

              if (!landscape) {
                return SingleChildScrollView(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  child: Column(children: [
                    left,
                    const SizedBox(height: AppSpacing.md),
                    right,
                  ]),
                );
              }

              return Padding(
                padding: const EdgeInsets.all(AppSpacing.md),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      flex: 5,
                      child: SingleChildScrollView(child: left),
                    ),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      flex: 4,
                      child: SingleChildScrollView(child: right),
                    ),
                  ],
                ),
              );
            },
          ),
      },
    );
  }
}

// ─── Kolom kiri: timer + info + aksi ──────────────────────────────────

class _SummaryPane extends StatelessWidget {
  const _SummaryPane({required this.session});

  final Session session;

  @override
  Widget build(BuildContext context) {
    final style = StatusStyle.ofSession(session.status);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _Panel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  StatusChip(status: style),
                  const SizedBox(width: AppSpacing.sm),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: AppSpacing.sm,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.overlaySubtle,
                      borderRadius: BorderRadius.circular(AppRadius.pill),
                    ),
                    child: Text(
                      session.mode.label,
                      style: AppTypography.bodySm
                          .copyWith(color: AppColors.onSurfaceVariant),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              CountdownText(
                endAt: session.endAt,
                style: AppTypography.displayLg,
              ),
              const SizedBox(height: AppSpacing.sm - 2),
              RemainingLabel(endAt: session.endAt),
              const SizedBox(height: AppSpacing.lg),
              const Divider(),
              const SizedBox(height: AppSpacing.sm),
              _InfoRow(
                icon: Icons.person_outline,
                label: 'Customer',
                value: session.customerLabel,
              ),
              _InfoRow(
                icon: Icons.inventory_2_outlined,
                label: 'Paket',
                value: '${session.package.name} · '
                    '${formatDurationLabel(session.package.durationMinutes)}',
              ),
              if (session.startedAt != null)
                _InfoRow(
                  icon: Icons.play_arrow_outlined,
                  label: 'Mulai',
                  value: formatClock(session.startedAt!),
                ),
              if (session.endAt != null)
                _InfoRow(
                  icon: Icons.flag_outlined,
                  label: 'Selesai',
                  value: formatClock(session.endAt!),
                ),
              if (session.extendDeadlineAt != null && session.extendable)
                _InfoRow(
                  icon: Icons.hourglass_bottom,
                  label: 'Batas extend',
                  value: formatClock(session.extendDeadlineAt!),
                  valueColor: AppColors.statusWarning,
                ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        _ActionPanel(session: session),
      ],
    );
  }
}

class _ActionPanel extends StatelessWidget {
  const _ActionPanel({required this.session});

  final Session session;

  bool get _finished =>
      session.status == SessionStatus.completed ||
      session.status == SessionStatus.cancelled;

  @override
  Widget build(BuildContext context) {
    final ctrl = context.read<SessionDetailController>();

    if (_finished) {
      return _Panel(
        child: Row(
          children: [
            Icon(
              session.status == SessionStatus.completed
                  ? Icons.check_circle_outline
                  : Icons.cancel_outlined,
              color: session.status == SessionStatus.completed
                  ? AppColors.statusAvailable
                  : AppColors.statusOffline,
            ),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: Text(
                session.status == SessionStatus.completed
                    ? 'Sesi sudah selesai. Station kembali tersedia.'
                    : 'Sesi dibatalkan.',
                style: AppTypography.bodyMd,
              ),
            ),
          ],
        ),
      );
    }

    final pending = session.status == SessionStatus.pendingPayment;
    final canOrderFnb = const {SessionStatus.active, SessionStatus.warning}
        .contains(session.status);

    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Aksi', style: AppTypography.headlineSm),
          const SizedBox(height: AppSpacing.md),

          // Primary CTA — satu per keadaan (UI-UX-SPEC §3).
          if (pending)
            AsyncButton(
              label: 'Konfirmasi Pembayaran Rental',
              icon: Icons.payments_outlined,
              expand: true,
              onPressed: () async {
                final ok = await showPaymentDialog(
                  context,
                  ctrl: ctrl,
                  session: session,
                  suggestedAmount: session.totals.balanceDue,
                  title: 'Pembayaran Rental',
                );
                if (ok && context.mounted) {
                  showSuccess(context, 'Pembayaran diterima. Sesi dimulai.');
                }
              },
            )
          else
            AsyncButton(
              label: 'Checkout',
              icon: Icons.receipt_long,
              expand: true,
              onPressed: () async {
                final result = await showCheckoutDialog(
                  context,
                  ctrl: ctrl,
                  session: session,
                );
                if (result == null || !context.mounted) return;
                await showReceiptDialog(context, receipt: result.receipt);
              },
            ),

          const SizedBox(height: AppSpacing.md),
          const Divider(),
          const SizedBox(height: AppSpacing.sm),

          // Aksi sekunder
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              AsyncButton(
                label: 'Tambah Durasi',
                icon: Icons.more_time,
                outlined: true,
                onPressed: !session.extendable
                    ? null
                    : () => showExtendDialog(
                          context,
                          ctrl: ctrl,
                          session: session,
                        ),
              ),
              AsyncButton(
                label: 'Tambah F&B',
                icon: Icons.restaurant_outlined,
                outlined: true,
                onPressed: !canOrderFnb
                    ? null
                    : () => showAddFnbSheet(context, ctrl: ctrl),
              ),
              AsyncButton(
                label: 'Pindah Station',
                icon: Icons.swap_horiz,
                outlined: true,
                onPressed: pending
                    ? null
                    : () => showSwapDialog(
                          context,
                          ctrl: ctrl,
                          session: session,
                        ),
              ),
              if (!pending && session.totals.balanceDue > 0)
                AsyncButton(
                  label: 'Bayar Sebagian',
                  icon: Icons.payments_outlined,
                  outlined: true,
                  onPressed: () => showPaymentDialog(
                    context,
                    ctrl: ctrl,
                    session: session,
                    suggestedAmount: session.totals.balanceDue,
                  ),
                ),
            ],
          ),

          // Aksi destruktif dipisah — UI-UX-SPEC §7.
          if (pending) ...[
            const SizedBox(height: AppSpacing.lg),
            const Divider(),
            const SizedBox(height: AppSpacing.sm),
            Align(
              alignment: Alignment.centerLeft,
              child: AsyncButton(
                label: 'Batalkan Sesi',
                icon: Icons.delete_outline,
                outlined: true,
                destructive: true,
                onPressed: () async {
                  final confirmed = await showConfirmDialog(
                    context,
                    title: 'Batalkan sesi?',
                    message:
                        'Sesi ${session.code} di ${session.station.code} akan '
                        'dibatalkan dan station kembali tersedia. '
                        'Tindakan ini tidak bisa dibatalkan.',
                    confirmLabel: 'Batalkan Sesi',
                    destructive: true,
                  );
                  if (!confirmed || !context.mounted) return;
                  try {
                    await ctrl.cancel(
                      idempotencyKey:
                          SessionDetailController.newIdempotencyKey(),
                    );
                    if (context.mounted) {
                      showSuccess(context, 'Sesi dibatalkan.');
                    }
                  } catch (e) {
                    if (context.mounted) showApiError(context, e);
                  }
                },
              ),
            ),
          ],
        ],
      ),
    );
  }
}

// ─── Kolom kanan: Open Tab ────────────────────────────────────────────

class _OpenTabPane extends StatelessWidget {
  const _OpenTabPane({required this.session});

  final Session session;

  @override
  Widget build(BuildContext context) {
    final t = session.totals;

    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: Text('Open Tab', style: AppTypography.headlineSm)),
              Text(
                '${session.items.length} item',
                style: AppTypography.bodySm
                    .copyWith(color: AppColors.onSurfaceVariant),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          const Divider(),

          ...session.items.map(
            (i) => MoneyRow(
              label: i.name,
              sublabel: '${i.type.label} · ${i.qty}× · '
                  '${i.isPaid ? 'sudah dibayar' : 'belum dibayar'}',
              amount: i.subtotal,
              amountColor:
                  i.isPaid ? AppColors.onSurfaceVariant : AppColors.onSurface,
            ),
          ),

          const Divider(height: AppSpacing.lg),

          if (t.rental > 0) MoneyRow(label: 'Rental', amount: t.rental),
          if (t.fnb > 0) MoneyRow(label: 'F&B', amount: t.fnb),
          if (t.extend > 0) MoneyRow(label: 'Extend', amount: t.extend),
          if (t.discount > 0)
            MoneyRow(label: 'Diskon', amount: -t.discount),
          if (t.adjustment != 0)
            MoneyRow(label: 'Penyesuaian', amount: t.adjustment),

          const Divider(),
          MoneyRow(label: 'Total', amount: t.grandTotal),
          MoneyRow(
            label: 'Sudah dibayar',
            amount: t.paid,
            amountColor: AppColors.statusAvailable,
          ),
          const Divider(),
          MoneyRow(
            label: 'Sisa tagihan',
            amount: t.balanceDue,
            emphasize: true,
            amountColor: t.balanceDue > 0
                ? AppColors.tertiaryContainer
                : AppColors.statusAvailable,
          ),
        ],
      ),
    );
  }
}

// ─── Potongan kecil ───────────────────────────────────────────────────

class _Panel extends StatelessWidget {
  const _Panel({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.md + 2),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: AppColors.surfaceHigh),
        boxShadow: AppShadow.card,
      ),
      child: child,
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.icon,
    required this.label,
    required this.value,
    this.valueColor,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs + 2),
      child: Row(
        children: [
          Icon(icon, size: 16, color: AppColors.onSurfaceVariant),
          const SizedBox(width: AppSpacing.sm),
          Text(
            label,
            style: AppTypography.bodySm.copyWith(color: AppColors.onSurfaceVariant),
          ),
          const Spacer(),
          Text(
            value,
            style: AppTypography.bodyMd.copyWith(color: valueColor),
          ),
        ],
      ),
    );
  }
}

class _ErrorBody extends StatelessWidget {
  const _ErrorBody({required this.message, required this.onRetry});

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
            const Icon(Icons.error_outline,
                size: 48, color: AppColors.error),
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
