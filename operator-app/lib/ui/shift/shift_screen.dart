import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:uuid/uuid.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../core/time/server_time.dart';
import '../../core/time/ticker.dart';
import '../../core/util/format.dart';
import '../../domain/models/models.dart';
import '../../domain/repositories/billing_repository.dart';
import '../widgets/confirm_dialog.dart';
import '../widgets/money_text.dart';
import 'shift_controller.dart';

/// Layar shift — PRD §18: "Start/close shift, handover".
///
/// Fokusnya satu pertanyaan: **berapa kas yang seharusnya ada di kotak?**
/// Semua angka lain mendukung pertanyaan itu.
class ShiftScreen extends StatelessWidget {
  const ShiftScreen({super.key, this.embedded = false});

  /// `true` saat dipasang di dalam [AppShell] — shell sudah punya header.
  final bool embedded;

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (ctx) => ShiftController(ctx.read<BillingRepository>())..load(),
      child: _ShiftBody(embedded: embedded),
    );
  }
}

class _ShiftBody extends StatelessWidget {
  const _ShiftBody({required this.embedded});

  final bool embedded;

  @override
  Widget build(BuildContext context) {
    final ctrl = context.watch<ShiftController>();
    final content = _content(context, ctrl);

    if (embedded) return content;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Shift'),
        actions: [
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
          const SizedBox(width: AppSpacing.sm),
        ],
      ),
      body: SafeArea(child: content),
    );
  }

  Widget _content(BuildContext context, ShiftController ctrl) {
    return SafeArea(
        child: ctrl.loading && ctrl.current == null && ctrl.history.isEmpty
            ? const Center(child: CircularProgressIndicator())
            : ctrl.error != null && ctrl.current == null
                ? _ErrorState(
                    message: ctrl.errorMessage ?? 'Gagal memuat shift.',
                    onRetry: ctrl.load,
                  )
                : SingleChildScrollView(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    child: Center(
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 680),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            if (ctrl.current != null)
                              _OpenShiftPanel(shift: ctrl.current!)
                            else
                              const _NoShiftPanel(),
                            if (ctrl.history.isNotEmpty) ...[
                              const SizedBox(height: AppSpacing.lg),
                              Text(
                                'RIWAYAT SHIFT',
                                style: AppTypography.labelSm
                                    .copyWith(color: AppColors.outline),
                              ),
                              const SizedBox(height: AppSpacing.sm),
                              ...ctrl.history.map(
                                (s) => Padding(
                                  padding: const EdgeInsets.only(
                                      bottom: AppSpacing.sm),
                                  child: _HistoryCard(shift: s),
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

// ─── Shift berjalan ───────────────────────────────────────────────────

class _OpenShiftPanel extends StatelessWidget {
  const _OpenShiftPanel({required this.shift});

  final Shift shift;

  @override
  Widget build(BuildContext context) {
    final ctrl = context.read<ShiftController>();
    final s = shift.summary;

    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Container(
                width: 9,
                height: 9,
                decoration: const BoxDecoration(
                  color: AppColors.statusAvailable,
                  shape: BoxShape.circle,
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Text('Shift berjalan', style: AppTypography.headlineSm),
              const Spacer(),
              _ShiftDuration(openedAt: shift.openedAt),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Row(
            children: [
              const Icon(Icons.person_outline,
                  size: 15, color: AppColors.onSurfaceVariant),
              const SizedBox(width: AppSpacing.sm - 2),
              Text(
                shift.operator.name,
                style: AppTypography.bodyMd,
              ),
              const SizedBox(width: AppSpacing.md),
              const Icon(Icons.login, size: 15, color: AppColors.onSurfaceVariant),
              const SizedBox(width: AppSpacing.sm - 2),
              Text(
                'Buka ${formatClock(shift.openedAt)}',
                style:
                    AppTypography.bodySm.copyWith(color: AppColors.onSurfaceVariant),
              ),
            ],
          ),

          const Divider(height: AppSpacing.lg),

          // Yang paling penting: kas yang seharusnya ada di kotak.
          Container(
            padding: const EdgeInsets.all(AppSpacing.md),
            decoration: BoxDecoration(
              color: AppColors.surfaceContainer,
              borderRadius: BorderRadius.circular(AppRadius.lg),
            ),
            child: Column(
              children: [
                MoneyRow(label: 'Kas awal', amount: shift.openingCash),
                MoneyRow(
                  label: 'Penerimaan tunai',
                  sublabel: 'QRIS tidak masuk kotak kas',
                  amount: s.cash,
                ),
                const Divider(),
                MoneyRow(
                  label: 'Kas seharusnya',
                  amount: shift.expectedCash,
                  emphasize: true,
                  amountColor: AppColors.tertiaryContainer,
                ),
              ],
            ),
          ),

          const SizedBox(height: AppSpacing.md),
          Text(
            'UANG MASUK',
            style: AppTypography.labelSm.copyWith(color: AppColors.outline),
          ),
          MoneyRow(label: 'Tunai', amount: s.cash),
          MoneyRow(label: 'QRIS', amount: s.qris),
          const Divider(),
          MoneyRow(label: 'Total diterima', amount: s.total, emphasize: true),

          const SizedBox(height: AppSpacing.md),
          Text(
            'NILAI TRANSAKSI SHIFT INI',
            style: AppTypography.labelSm.copyWith(color: AppColors.outline),
          ),
          MoneyRow(label: 'Rental + Extend', amount: s.rental),
          MoneyRow(label: 'F&B', amount: s.fnb),
          Padding(
            padding: const EdgeInsets.only(top: AppSpacing.xs),
            child: Text(
              'Nilai transaksi bisa berbeda dari uang masuk. Open Tab yang '
              'dibuka di shift ini tapi dibayar di shift berikutnya terhitung '
              'di sini, uangnya terhitung di sana.',
              style: AppTypography.bodySm.copyWith(color: AppColors.outline),
            ),
          ),

          const SizedBox(height: AppSpacing.lg),
          AsyncButton(
            label: 'Tutup Shift',
            icon: Icons.logout,
            expand: true,
            onPressed: () async {
              final closed = await showCloseShiftDialog(
                context,
                ctrl: ctrl,
                shift: shift,
              );
              if (closed == null || !context.mounted) return;
              await showShiftReportDialog(context, shift: closed);
            },
          ),
        ],
      ),
    );
  }
}

/// Lama shift berjalan. Satu-satunya bagian yang berlangganan ticker.
class _ShiftDuration extends StatelessWidget {
  const _ShiftDuration({required this.openedAt});

  final DateTime openedAt;

  @override
  Widget build(BuildContext context) {
    return Consumer<AppTicker>(
      builder: (context, _, __) {
        final d = ServerTime.instance.now.difference(openedAt);
        return Text(
          formatDurationLabel(d.inMinutes),
          style: AppTypography.moneySm.copyWith(color: AppColors.onSurfaceVariant),
        );
      },
    );
  }
}

// ─── Belum ada shift ──────────────────────────────────────────────────

class _NoShiftPanel extends StatelessWidget {
  const _NoShiftPanel();

  @override
  Widget build(BuildContext context) {
    final ctrl = context.read<ShiftController>();

    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Container(
                width: 9,
                height: 9,
                decoration: const BoxDecoration(
                  color: AppColors.statusOffline,
                  shape: BoxShape.circle,
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Text('Belum ada shift', style: AppTypography.headlineSm),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            'Buka shift dengan menghitung kas awal di kotak. Angka itu yang '
            'nanti dibandingkan saat shift ditutup.',
            style: AppTypography.bodySm.copyWith(color: AppColors.onSurfaceVariant),
          ),
          const SizedBox(height: AppSpacing.lg),
          AsyncButton(
            label: 'Buka Shift',
            icon: Icons.login,
            expand: true,
            onPressed: () => showOpenShiftDialog(context, ctrl: ctrl),
          ),
        ],
      ),
    );
  }
}

// ─── Riwayat ──────────────────────────────────────────────────────────

class _HistoryCard extends StatelessWidget {
  const _HistoryCard({required this.shift});

  final Shift shift;

  @override
  Widget build(BuildContext context) {
    final variance = shift.variance ?? 0;
    final exact = variance == 0;
    final color = exact
        ? AppColors.statusAvailable
        : variance > 0
            ? AppColors.statusWarning
            : AppColors.statusExpired;

    return Container(
      padding: const EdgeInsets.all(AppSpacing.md - 2),
      decoration: BoxDecoration(
        color: AppColors.surfaceContainer,
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Text(
                '${formatClock(shift.openedAt)} – '
                '${shift.closedAt == null ? '?' : formatClock(shift.closedAt!)}',
                style: AppTypography.moneySm,
              ),
              const SizedBox(width: AppSpacing.sm),
              Text(
                shift.operator.name,
                style:
                    AppTypography.bodySm.copyWith(color: AppColors.onSurfaceVariant),
              ),
              const Spacer(),
              Icon(
                exact
                    ? Icons.check_circle_outline
                    : Icons.error_outline,
                size: 14,
                color: color,
              ),
              const SizedBox(width: AppSpacing.xs),
              Text(
                exact
                    ? 'Pas'
                    : '${variance > 0 ? '+' : ''}${formatRupiah(variance)}',
                style: AppTypography.moneySm.copyWith(color: color),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          Row(
            children: [
              Expanded(
                child: Text(
                  'Diterima ${formatRupiah(shift.summary.total)}',
                  style: AppTypography.bodySm
                      .copyWith(color: AppColors.onSurfaceVariant),
                ),
              ),
              Text(
                'Kas akhir ${formatRupiah(shift.closingCash ?? 0)}',
                style:
                    AppTypography.bodySm.copyWith(color: AppColors.onSurfaceVariant),
              ),
            ],
          ),
          if (shift.note != null && shift.note!.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.xs),
            Text(
              shift.note!,
              style: AppTypography.bodySm.copyWith(color: AppColors.outline),
            ),
          ],
        ],
      ),
    );
  }
}

// ─── Dialog ───────────────────────────────────────────────────────────

Future<void> showOpenShiftDialog(
  BuildContext context, {
  required ShiftController ctrl,
}) =>
    showDialog<void>(
      context: context,
      barrierColor: AppColors.scrim,
      builder: (_) => _OpenShiftDialog(ctrl: ctrl),
    );

class _OpenShiftDialog extends StatefulWidget {
  const _OpenShiftDialog({required this.ctrl});

  final ShiftController ctrl;

  @override
  State<_OpenShiftDialog> createState() => _OpenShiftDialogState();
}

class _OpenShiftDialogState extends State<_OpenShiftDialog> {
  final String _intentKey = const Uuid().v4();
  final _cash = TextEditingController(text: '0');
  String? _error;

  @override
  void dispose() {
    _cash.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final value = int.tryParse(_cash.text.trim());
    if (value == null || value < 0) {
      setState(() => _error = 'Masukkan nominal kas awal.');
      return;
    }
    setState(() => _error = null);

    try {
      await widget.ctrl.open(openingCash: value, idempotencyKey: _intentKey);
      if (!mounted) return;
      Navigator.of(context).pop();
      showSuccess(context, 'Shift dibuka.');
    } catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Buka Shift', style: AppTypography.headlineSm),
      content: SizedBox(
        width: 420,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Hitung uang yang ada di kotak kas sekarang.',
              style: AppTypography.bodySm.copyWith(color: AppColors.onSurfaceVariant),
            ),
            const SizedBox(height: AppSpacing.md),
            TextField(
              controller: _cash,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              autofocus: true,
              decoration: InputDecoration(
                labelText: 'Kas awal',
                prefixText: 'Rp ',
                errorText: _error,
              ),
            ),
          ],
        ),
      ),
      actionsPadding: const EdgeInsets.fromLTRB(
          AppSpacing.md, 0, AppSpacing.md, AppSpacing.md),
      actions: [
        OutlinedButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        const SizedBox(width: AppSpacing.sm),
        AsyncButton(label: 'Buka Shift', icon: Icons.login, onPressed: _submit),
      ],
    );
  }
}

Future<Shift?> showCloseShiftDialog(
  BuildContext context, {
  required ShiftController ctrl,
  required Shift shift,
}) =>
    showDialog<Shift>(
      context: context,
      barrierColor: AppColors.scrim,
      builder: (_) => _CloseShiftDialog(ctrl: ctrl, shift: shift),
    );

class _CloseShiftDialog extends StatefulWidget {
  const _CloseShiftDialog({required this.ctrl, required this.shift});

  final ShiftController ctrl;
  final Shift shift;

  @override
  State<_CloseShiftDialog> createState() => _CloseShiftDialogState();
}

class _CloseShiftDialogState extends State<_CloseShiftDialog> {
  final String _intentKey = const Uuid().v4();
  final _cash = TextEditingController();
  final _note = TextEditingController();
  String? _error;

  @override
  void dispose() {
    _cash.dispose();
    _note.dispose();
    super.dispose();
  }

  int? get _counted => int.tryParse(_cash.text.trim());

  /// Selisih dihitung langsung saat operator mengetik, supaya terlihat
  /// sebelum shift ditutup — bukan kejutan setelahnya.
  int? get _variance =>
      _counted == null ? null : _counted! - widget.shift.expectedCash;

  Future<void> _submit() async {
    final value = _counted;
    if (value == null || value < 0) {
      setState(() => _error = 'Masukkan hasil hitungan kas.');
      return;
    }
    setState(() => _error = null);

    // Selisih besar dikonfirmasi dua kali — ini angka yang diaudit.
    final v = _variance ?? 0;
    if (v != 0) {
      final ok = await showConfirmDialog(
        context,
        title: v > 0 ? 'Kas lebih ${formatRupiah(v)}' : 'Kas kurang ${formatRupiah(v.abs())}',
        message: 'Selisih ini akan dicatat permanen di audit log. '
            'Pastikan hitungannya sudah benar.',
        confirmLabel: 'Ya, tutup shift',
        destructive: v < 0,
      );
      if (!ok || !mounted) return;
    }

    try {
      final closed = await widget.ctrl.close(
        closingCash: value,
        note: _note.text.trim().isEmpty ? null : _note.text.trim(),
        idempotencyKey: _intentKey,
      );
      if (!mounted) return;
      Navigator.of(context).pop(closed);
    } catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final v = _variance;

    return AlertDialog(
      title: const Text('Tutup Shift', style: AppTypography.headlineSm),
      content: SizedBox(
        width: 440,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              MoneyRow(
                label: 'Kas seharusnya',
                sublabel: 'Kas awal + penerimaan tunai',
                amount: widget.shift.expectedCash,
                emphasize: true,
              ),
              const Divider(),
              const SizedBox(height: AppSpacing.sm),
              TextField(
                controller: _cash,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                autofocus: true,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(
                  labelText: 'Hasil hitungan kas',
                  prefixText: 'Rp ',
                  errorText: _error,
                  helperText: 'Hitung fisik uang di kotak',
                ),
              ),
              if (v != null) ...[
                const SizedBox(height: AppSpacing.md),
                Container(
                  padding: const EdgeInsets.all(AppSpacing.sm + 4),
                  decoration: BoxDecoration(
                    color: (v == 0
                            ? AppColors.statusAvailable
                            : v > 0
                                ? AppColors.statusWarning
                                : AppColors.statusExpired)
                        .withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(AppRadius.md),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        v == 0
                            ? Icons.check_circle_outline
                            : Icons.error_outline,
                        size: 18,
                        color: v == 0
                            ? AppColors.statusAvailable
                            : v > 0
                                ? AppColors.statusWarning
                                : AppColors.statusExpired,
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: Text(
                          v == 0
                              ? 'Pas, tidak ada selisih'
                              : v > 0
                                  ? 'Lebih ${formatRupiah(v)}'
                                  : 'Kurang ${formatRupiah(v.abs())}',
                          style: AppTypography.bodyLg.copyWith(
                            color: v == 0
                                ? AppColors.statusAvailable
                                : v > 0
                                    ? AppColors.statusWarning
                                    : AppColors.statusExpired,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: AppSpacing.md),
              TextField(
                controller: _note,
                maxLines: 2,
                decoration: const InputDecoration(
                  labelText: 'Catatan (opsional)',
                  helperText: 'Alasan selisih, kejadian penting, serah terima',
                ),
              ),
            ],
          ),
        ),
      ),
      actionsPadding: const EdgeInsets.fromLTRB(
          AppSpacing.md, 0, AppSpacing.md, AppSpacing.md),
      actions: [
        OutlinedButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        const SizedBox(width: AppSpacing.sm),
        AsyncButton(
          label: 'Tutup Shift',
          icon: Icons.logout,
          onPressed: _submit,
        ),
      ],
    );
  }
}

/// Laporan setelah shift ditutup, sekaligus jalur serah terima:
/// kas akhir shift ini menjadi usulan kas awal shift berikutnya.
Future<void> showShiftReportDialog(
  BuildContext context, {
  required Shift shift,
}) {
  final v = shift.variance ?? 0;
  final s = shift.summary;

  return showDialog<void>(
    context: context,
    barrierColor: AppColors.scrim,
    builder: (ctx) => AlertDialog(
      title: Row(
        children: [
          Icon(
            v == 0 ? Icons.check_circle : Icons.error_outline,
            color: v == 0 ? AppColors.statusAvailable : AppColors.statusWarning,
            size: 26,
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text('Shift Ditutup', style: AppTypography.headlineSm),
          ),
        ],
      ),
      content: SizedBox(
        width: 420,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${formatClock(shift.openedAt)} – '
                '${shift.closedAt == null ? '?' : formatClock(shift.closedAt!)}'
                '  ·  ${shift.operator.name}',
                style: AppTypography.bodySm.copyWith(color: AppColors.onSurfaceVariant),
              ),
              const Divider(height: AppSpacing.lg),
              MoneyRow(label: 'Kas awal', amount: shift.openingCash),
              MoneyRow(label: 'Tunai diterima', amount: s.cash),
              MoneyRow(label: 'QRIS diterima', amount: s.qris),
              const Divider(),
              MoneyRow(label: 'Kas seharusnya', amount: shift.expectedCash),
              MoneyRow(label: 'Kas dihitung', amount: shift.closingCash ?? 0),
              const Divider(),
              MoneyRow(
                label: v == 0 ? 'Pas' : v > 0 ? 'Lebih' : 'Kurang',
                amount: v,
                emphasize: true,
                amountColor: v == 0
                    ? AppColors.statusAvailable
                    : v > 0
                        ? AppColors.statusWarning
                        : AppColors.statusExpired,
              ),
              if (shift.note != null && shift.note!.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.sm),
                Text(
                  shift.note!,
                  style:
                      AppTypography.bodySm.copyWith(color: AppColors.onSurfaceVariant),
                ),
              ],
              const SizedBox(height: AppSpacing.md),
              Container(
                padding: const EdgeInsets.all(AppSpacing.sm + 4),
                decoration: BoxDecoration(
                  color: AppColors.surfaceContainer,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Text(
                  'Serah terima: kas yang dihitung '
                  '(${formatRupiah(shift.closingCash ?? 0)}) menjadi kas awal '
                  'shift berikutnya.',
                  style:
                      AppTypography.bodySm.copyWith(color: AppColors.onSurfaceVariant),
                ),
              ),
            ],
          ),
        ),
      ),
      actionsPadding: const EdgeInsets.fromLTRB(
          AppSpacing.md, 0, AppSpacing.md, AppSpacing.md),
      actions: [
        FilledButton(
          onPressed: () => Navigator.of(ctx).pop(),
          child: const Text('Tutup'),
        ),
      ],
    ),
  );
}

// ─── Potongan ─────────────────────────────────────────────────────────

class _Panel extends StatelessWidget {
  const _Panel({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md + 2),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        boxShadow: AppShadow.card,
      ),
      child: child,
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
                label: 'Coba lagi', icon: Icons.refresh, onPressed: onRetry),
          ],
        ),
      ),
    );
  }
}
