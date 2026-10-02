import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:uuid/uuid.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../core/util/format.dart';
import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import '../widgets/confirm_dialog.dart';
import '../widgets/money_text.dart';
import 'session_detail_controller.dart';

/// Dialog aksi pada sesi.
///
/// Pola yang dipakai di semua dialog di file ini: `Idempotency-Key` dibuat
/// SEKALI saat dialog dibuka dan dipakai ulang untuk setiap percobaan.
/// Jadi double-tap atau retry setelah timeout tidak menghasilkan transaksi
/// ganda (kontrak §3, T14).

// ─── Pembayaran ───────────────────────────────────────────────────────

Future<bool> showPaymentDialog(
  BuildContext context, {
  required SessionDetailController ctrl,
  required Session session,
  required int suggestedAmount,
  String title = 'Terima Pembayaran',
}) async {
  final result = await showDialog<bool>(
    context: context,
    barrierColor: AppColors.scrim,
    builder: (_) => _PaymentDialog(
      ctrl: ctrl,
      session: session,
      suggestedAmount: suggestedAmount,
      title: title,
    ),
  );
  return result ?? false;
}

class _PaymentDialog extends StatefulWidget {
  const _PaymentDialog({
    required this.ctrl,
    required this.session,
    required this.suggestedAmount,
    required this.title,
  });

  final SessionDetailController ctrl;
  final Session session;
  final int suggestedAmount;
  final String title;

  @override
  State<_PaymentDialog> createState() => _PaymentDialogState();
}

class _PaymentDialogState extends State<_PaymentDialog> {
  final String _intentKey = const Uuid().v4();
  late final TextEditingController _amount =
      TextEditingController(text: widget.suggestedAmount.toString());
  final _reference = TextEditingController();

  PaymentMethod _method = PaymentMethod.cash;
  String? _amountError;
  String? _referenceError;

  @override
  void dispose() {
    _amount.dispose();
    _reference.dispose();
    super.dispose();
  }

  int get _parsedAmount => int.tryParse(_amount.text.trim()) ?? 0;

  Future<void> _submit() async {
    setState(() {
      _amountError = null;
      _referenceError = null;
    });

    if (_parsedAmount <= 0) {
      setState(() => _amountError = 'Nominal harus lebih dari nol.');
      return;
    }
    if (_method.requiresReference && _reference.text.trim().isEmpty) {
      setState(() =>
          _referenceError = 'Nomor referensi QRIS wajib diisi untuk audit.');
      return;
    }

    try {
      await widget.ctrl.pay(
        method: _method,
        amount: _parsedAmount,
        reference:
            _reference.text.trim().isEmpty ? null : _reference.text.trim(),
        idempotencyKey: _intentKey,
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      showApiError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.title, style: AppTypography.headlineSm),
      content: SizedBox(
        width: 420,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            MoneyRow(
              label: 'Sisa tagihan',
              amount: widget.session.totals.balanceDue,
              emphasize: true,
              amountColor: AppColors.tertiaryContainer,
            ),
            const Divider(),
            const SizedBox(height: AppSpacing.sm),
            Text('Metode', style: AppTypography.bodyLg),
            const SizedBox(height: AppSpacing.sm),
            Row(
              children: PaymentMethod.values
                  .where((m) => m != PaymentMethod.unknown)
                  .map((m) => Expanded(
                        child: Padding(
                          padding:
                              const EdgeInsets.only(right: AppSpacing.sm),
                          child: _MethodTile(
                            method: m,
                            selected: _method == m,
                            onTap: () => setState(() => _method = m),
                          ),
                        ),
                      ))
                  .toList(),
            ),
            const SizedBox(height: AppSpacing.md),
            TextField(
              controller: _amount,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: InputDecoration(
                labelText: 'Nominal diterima',
                prefixText: 'Rp ',
                errorText: _amountError,
                helperText: 'Maksimal '
                    '${formatRupiah(widget.session.totals.balanceDue)}',
              ),
            ),
            if (_method.requiresReference) ...[
              const SizedBox(height: AppSpacing.md),
              TextField(
                controller: _reference,
                decoration: InputDecoration(
                  labelText: 'Nomor referensi QRIS',
                  errorText: _referenceError,
                  helperText: 'Dicatat untuk audit (PRD §21)',
                ),
              ),
            ],
          ],
        ),
      ),
      actionsPadding: const EdgeInsets.fromLTRB(
        AppSpacing.md,
        0,
        AppSpacing.md,
        AppSpacing.md,
      ),
      actions: [
        OutlinedButton(
          onPressed: () => Navigator.of(context).pop(false),
          child: const Text('Batal'),
        ),
        const SizedBox(width: AppSpacing.sm),
        AsyncButton(
          label: 'Konfirmasi',
          icon: Icons.check,
          onPressed: _submit,
        ),
      ],
    );
  }
}

class _MethodTile extends StatelessWidget {
  const _MethodTile({
    required this.method,
    required this.selected,
    required this.onTap,
  });

  final PaymentMethod method;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final icon = method == PaymentMethod.cash
        ? Icons.payments_outlined
        : Icons.qr_code_2;

    return Material(
      color:
          selected ? AppColors.primary.withValues(alpha: 0.16) : AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: Container(
          constraints:
              const BoxConstraints(minHeight: AppSize.minTouchTarget),
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.md,
            vertical: AppSpacing.sm,
          ),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.md),
            border: Border.all(
              color: selected ? AppColors.primary : AppColors.surfaceHigh,
              width: selected ? 2 : 1,
            ),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 18),
              const SizedBox(width: AppSpacing.sm),
              Text(method.label, style: AppTypography.bodyLg),
            ],
          ),
        ),
      ),
    );
  }
}

// ─── Extend ───────────────────────────────────────────────────────────

/// Pilihan durasi extend. HANYA kelipatan 30 menit (DEC-007) — UI tidak
/// menyediakan pilihan lain supaya operator tidak pernah menemui error.
const extendOptions = [30, 60, 90, 120];

Future<bool> showExtendDialog(
  BuildContext context, {
  required SessionDetailController ctrl,
  required Session session,
}) async {
  final result = await showDialog<bool>(
    context: context,
    barrierColor: AppColors.scrim,
    builder: (_) => _ExtendDialog(ctrl: ctrl, session: session),
  );
  return result ?? false;
}

class _ExtendDialog extends StatefulWidget {
  const _ExtendDialog({required this.ctrl, required this.session});

  final SessionDetailController ctrl;
  final Session session;

  @override
  State<_ExtendDialog> createState() => _ExtendDialogState();
}

class _ExtendDialogState extends State<_ExtendDialog> {
  final String _intentKey = const Uuid().v4();
  int _minutes = 30;

  /// Estimasi saja — harga final SELALU dari server (DEC-007).
  int get _estimate =>
      ((widget.session.hourlyRate * _minutes) + 59) ~/ 60;

  Future<void> _submit() async {
    try {
      final result = await widget.ctrl.extend(
        durationMinutes: _minutes,
        idempotencyKey: _intentKey,
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
      showSuccess(
        context,
        'Extend ${formatDurationLabel(result.durationMinutes)} · '
        '${formatRupiah(result.price)} · selesai '
        '${formatClock(result.newEndAt)}',
      );
    } catch (e) {
      if (!mounted) return;
      showApiError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final deadline = widget.session.extendDeadlineAt;

    return AlertDialog(
      title: const Text('Tambah Durasi', style: AppTypography.headlineSm),
      content: SizedBox(
        width: 420,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Durasi kelipatan 30 menit. Waktu baru dihitung dari jam '
              'selesai lama, jadi waktu yang sudah lewat tetap terhitung.',
              style:
                  AppTypography.bodySm.copyWith(color: AppColors.onSurfaceVariant),
            ),
            const SizedBox(height: AppSpacing.md),
            Wrap(
              spacing: AppSpacing.sm,
              runSpacing: AppSpacing.sm,
              children: extendOptions.map((m) {
                final selected = m == _minutes;
                return ChoiceChip(
                  label: Text(formatDurationLabel(m)),
                  selected: selected,
                  onSelected: (_) => setState(() => _minutes = m),
                  labelStyle: AppTypography.bodyLg.copyWith(
                    color: selected ? AppColors.onPrimary : AppColors.onSurface,
                  ),
                  selectedColor: AppColors.primary,
                  backgroundColor: AppColors.surface,
                  side: const BorderSide(color: AppColors.surfaceHigh),
                  padding: const EdgeInsets.symmetric(
                    horizontal: AppSpacing.sm,
                    vertical: AppSpacing.sm + 2,
                  ),
                );
              }).toList(),
            ),
            const SizedBox(height: AppSpacing.md),
            const Divider(),
            MoneyRow(
              label: 'Perkiraan tambahan',
              sublabel: 'Harga final dihitung server',
              amount: _estimate,
              emphasize: true,
              amountColor: AppColors.tertiaryContainer,
            ),
            if (widget.session.endAt != null)
              Text(
                'Selesai jadi ± '
                '${formatClock(widget.session.endAt!.add(Duration(minutes: _minutes)))}',
                style: AppTypography.bodySm
                    .copyWith(color: AppColors.onSurfaceVariant),
              ),
            if (deadline != null) ...[
              const SizedBox(height: AppSpacing.sm),
              Container(
                padding: const EdgeInsets.all(AppSpacing.sm + 2),
                decoration: BoxDecoration(
                  color: AppColors.statusWarning.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.info_outline,
                        size: 16, color: AppColors.statusWarning),
                    const SizedBox(width: AppSpacing.sm),
                    Expanded(
                      child: Text(
                        'Batas extend sampai ${formatClock(deadline)}',
                        style: AppTypography.bodySm
                            .copyWith(color: AppColors.statusWarning),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
      actionsPadding: const EdgeInsets.fromLTRB(
        AppSpacing.md,
        0,
        AppSpacing.md,
        AppSpacing.md,
      ),
      actions: [
        OutlinedButton(
          onPressed: () => Navigator.of(context).pop(false),
          child: const Text('Batal'),
        ),
        const SizedBox(width: AppSpacing.sm),
        AsyncButton(
          label: 'Tambah Durasi',
          icon: Icons.more_time,
          onPressed: _submit,
        ),
      ],
    );
  }
}

// ─── Station Swap ─────────────────────────────────────────────────────

Future<bool> showSwapDialog(
  BuildContext context, {
  required SessionDetailController ctrl,
  required Session session,
}) async {
  final targets = ctrl.swapTargets;

  if (targets.isEmpty) {
    showWarning(context, 'Tidak ada station tujuan yang tersedia.');
    return false;
  }

  final result = await showDialog<bool>(
    context: context,
    barrierColor: AppColors.scrim,
    builder: (_) => _SwapDialog(ctrl: ctrl, session: session, targets: targets),
  );
  return result ?? false;
}

class _SwapDialog extends StatefulWidget {
  const _SwapDialog({
    required this.ctrl,
    required this.session,
    required this.targets,
  });

  final SessionDetailController ctrl;
  final Session session;
  final List<Station> targets;

  @override
  State<_SwapDialog> createState() => _SwapDialogState();
}

class _SwapDialogState extends State<_SwapDialog> {
  final String _intentKey = const Uuid().v4();
  late String _targetId = widget.targets.first.id;

  Future<void> _submit() async {
    try {
      final s = await widget.ctrl.swap(
        targetStationId: _targetId,
        idempotencyKey: _intentKey,
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
      showSuccess(context, 'Sesi dipindah ke ${s.station.code}.');
    } catch (e) {
      if (!mounted) return;
      showApiError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Pindah Station', style: AppTypography.headlineSm),
      content: SizedBox(
        width: 420,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Sisa waktu, Open Tab, dan riwayat pembayaran tetap mengikuti '
              'sesi ini. Nomor sesi tidak berubah.',
              style:
                  AppTypography.bodySm.copyWith(color: AppColors.onSurfaceVariant),
            ),
            const SizedBox(height: AppSpacing.md),
            Row(
              children: [
                Text(widget.session.station.code,
                    style: AppTypography.headlineSm),
                const Padding(
                  padding: EdgeInsets.symmetric(horizontal: AppSpacing.md),
                  child: Icon(Icons.arrow_forward, color: AppColors.onSurfaceVariant),
                ),
                Text(
                  widget.targets
                      .firstWhere((t) => t.id == _targetId)
                      .code,
                  style: AppTypography.headlineSm
                      .copyWith(color: AppColors.primary),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.md),
            Text('Station tujuan', style: AppTypography.bodyLg),
            const SizedBox(height: AppSpacing.sm),
            Wrap(
              spacing: AppSpacing.sm,
              runSpacing: AppSpacing.sm,
              children: widget.targets.map((t) {
                final selected = t.id == _targetId;
                return ChoiceChip(
                  label: Text(t.code),
                  selected: selected,
                  onSelected: (_) => setState(() => _targetId = t.id),
                  labelStyle: AppTypography.bodyLg.copyWith(
                    color: selected ? AppColors.onPrimary : AppColors.onSurface,
                  ),
                  selectedColor: AppColors.primary,
                  backgroundColor: AppColors.surface,
                  side: const BorderSide(color: AppColors.surfaceHigh),
                  padding: const EdgeInsets.symmetric(
                    horizontal: AppSpacing.sm,
                    vertical: AppSpacing.sm + 2,
                  ),
                );
              }).toList(),
            ),
          ],
        ),
      ),
      actionsPadding: const EdgeInsets.fromLTRB(
        AppSpacing.md,
        0,
        AppSpacing.md,
        AppSpacing.md,
      ),
      actions: [
        OutlinedButton(
          onPressed: () => Navigator.of(context).pop(false),
          child: const Text('Batal'),
        ),
        const SizedBox(width: AppSpacing.sm),
        AsyncButton(
          label: 'Pindahkan',
          icon: Icons.swap_horiz,
          onPressed: _submit,
        ),
      ],
    );
  }
}

// ─── F&B ──────────────────────────────────────────────────────────────

Future<bool> showAddFnbSheet(
  BuildContext context, {
  required SessionDetailController ctrl,
}) async {
  final result = await showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.surfaceLow,
    barrierColor: AppColors.scrim,
    shape: const RoundedRectangleBorder(
      borderRadius:
          BorderRadius.vertical(top: Radius.circular(AppRadius.modal)),
    ),
    builder: (_) => _AddFnbSheet(ctrl: ctrl),
  );
  return result ?? false;
}

class _AddFnbSheet extends StatefulWidget {
  const _AddFnbSheet({required this.ctrl});

  final SessionDetailController ctrl;

  @override
  State<_AddFnbSheet> createState() => _AddFnbSheetState();
}

class _AddFnbSheetState extends State<_AddFnbSheet> {
  final String _intentKey = const Uuid().v4();
  final Map<String, int> _cart = {};

  int get _total => _cart.entries.fold(0, (a, e) {
        final p = widget.ctrl.products.firstWhere((x) => x.id == e.key);
        return a + p.price * e.value;
      });

  void _add(FnbProduct p) =>
      setState(() => _cart[p.id] = (_cart[p.id] ?? 0) + 1);

  void _remove(FnbProduct p) => setState(() {
        final n = (_cart[p.id] ?? 0) - 1;
        if (n <= 0) {
          _cart.remove(p.id);
        } else {
          _cart[p.id] = n;
        }
      });

  Future<void> _submit() async {
    if (_cart.isEmpty) return;
    try {
      final result = await widget.ctrl.addFnb(
        items: _cart.entries
            .map((e) => (productId: e.key, qty: e.value))
            .toList(growable: false),
        idempotencyKey: _intentKey,
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
      showSuccess(context, 'Order ${result.order.code} masuk Open Tab.');
    } catch (e) {
      if (!mounted) return;
      showApiError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final byCategory = <String, List<FnbProduct>>{};
    for (final p in widget.ctrl.products) {
      byCategory.putIfAbsent(p.category, () => []).add(p);
    }

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text('Tambah F&B',
                      style: AppTypography.headlineSm),
                ),
                IconButton(
                  onPressed: () => Navigator.of(context).pop(false),
                  icon: const Icon(Icons.close),
                  tooltip: 'Tutup',
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            Flexible(
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: byCategory.entries.map((entry) {
                    return Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Padding(
                          padding: const EdgeInsets.only(
                            top: AppSpacing.md,
                            bottom: AppSpacing.sm,
                          ),
                          child: Text(
                            entry.key,
                            style: AppTypography.bodySm
                                .copyWith(color: AppColors.onSurfaceVariant),
                          ),
                        ),
                        ...entry.value.map((p) => _FnbRow(
                              product: p,
                              qty: _cart[p.id] ?? 0,
                              onAdd: () => _add(p),
                              onRemove: () => _remove(p),
                            )),
                      ],
                    );
                  }).toList(),
                ),
              ),
            ),
            const Divider(height: AppSpacing.lg),
            MoneyRow(label: 'Total order', amount: _total, emphasize: true),
            const SizedBox(height: AppSpacing.md),
            SizedBox(
              width: double.infinity,
              child: AsyncButton(
                label: 'Masukkan ke Open Tab',
                icon: Icons.add_shopping_cart,
                expand: true,
                onPressed: _cart.isEmpty ? null : _submit,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _FnbRow extends StatelessWidget {
  const _FnbRow({
    required this.product,
    required this.qty,
    required this.onAdd,
    required this.onRemove,
  });

  final FnbProduct product;
  final int qty;
  final VoidCallback onAdd;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final soldOut = !product.isAvailable || (product.stock ?? 1) <= 0;

    return Opacity(
      opacity: soldOut ? 0.45 : 1,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(product.name, style: AppTypography.bodyMd),
                  Text(
                    soldOut
                        ? 'Habis'
                        : product.stock == null
                            ? formatRupiah(product.price)
                            : '${formatRupiah(product.price)} · stok ${product.stock}',
                    style: AppTypography.bodySm.copyWith(
                      color: soldOut
                          ? AppColors.statusExpired
                          : AppColors.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
            IconButton(
              onPressed: soldOut || qty == 0 ? null : onRemove,
              icon: const Icon(Icons.remove_circle_outline),
              tooltip: 'Kurangi',
            ),
            SizedBox(
              width: 32,
              child: Text(
                '$qty',
                textAlign: TextAlign.center,
                style: AppTypography.money,
              ),
            ),
            IconButton(
              onPressed: soldOut ? null : onAdd,
              icon: const Icon(Icons.add_circle_outline),
              tooltip: 'Tambah',
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Checkout ─────────────────────────────────────────────────────────

Future<CheckoutResult?> showCheckoutDialog(
  BuildContext context, {
  required SessionDetailController ctrl,
  required Session session,
}) {
  return showDialog<CheckoutResult>(
    context: context,
    barrierColor: AppColors.scrim,
    builder: (_) => _CheckoutDialog(ctrl: ctrl, session: session),
  );
}

class _CheckoutDialog extends StatefulWidget {
  const _CheckoutDialog({required this.ctrl, required this.session});

  final SessionDetailController ctrl;
  final Session session;

  @override
  State<_CheckoutDialog> createState() => _CheckoutDialogState();
}

class _CheckoutDialogState extends State<_CheckoutDialog> {
  final String _intentKey = const Uuid().v4();
  final _reference = TextEditingController();
  PaymentMethod _method = PaymentMethod.cash;
  String? _referenceError;

  @override
  void dispose() {
    _reference.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _referenceError = null);

    if (_method.requiresReference && _reference.text.trim().isEmpty) {
      setState(() => _referenceError = 'Nomor referensi QRIS wajib diisi.');
      return;
    }

    try {
      final result = await widget.ctrl.checkout(
        payments: [
          (
            method: _method,
            amount: widget.session.totals.balanceDue,
            reference: _reference.text.trim().isEmpty
                ? null
                : _reference.text.trim(),
          ),
        ],
        idempotencyKey: _intentKey,
      );
      if (!mounted) return;
      Navigator.of(context).pop(result);
    } catch (e) {
      if (!mounted) return;
      showApiError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = widget.session.totals;
    final unpaid = widget.session.items.where((i) => !i.isPaid).toList();

    return AlertDialog(
      title: const Text('Checkout', style: AppTypography.headlineSm),
      content: SizedBox(
        width: 460,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                widget.session.mode == SessionMode.prepaid
                    ? 'Rental sudah dibayar di depan. Yang ditagih hanya '
                        'item yang belum dibayar.'
                    : 'Rental dihitung dari durasi aktual, lalu dibulatkan '
                        'per 30 menit dengan toleransi 5 menit.',
                style: AppTypography.bodySm
                    .copyWith(color: AppColors.onSurfaceVariant),
              ),
              const SizedBox(height: AppSpacing.md),
              if (unpaid.isEmpty)
                Text('Tidak ada item yang belum dibayar.',
                    style: AppTypography.bodyMd)
              else
                ...unpaid.map((i) => MoneyRow(
                      label: i.name,
                      sublabel: '${i.type.label} · ${i.qty}×',
                      amount: i.subtotal,
                    )),
              const Divider(),
              MoneyRow(label: 'Total sesi', amount: t.grandTotal),
              MoneyRow(label: 'Sudah dibayar', amount: t.paid),
              MoneyRow(
                label: 'Ditagih sekarang',
                amount: t.balanceDue,
                emphasize: true,
                amountColor: AppColors.tertiaryContainer,
              ),
              const SizedBox(height: AppSpacing.md),
              Text('Metode', style: AppTypography.bodyLg),
              const SizedBox(height: AppSpacing.sm),
              Row(
                children: PaymentMethod.values
                    .where((m) => m != PaymentMethod.unknown)
                    .map((m) => Expanded(
                          child: Padding(
                            padding:
                                const EdgeInsets.only(right: AppSpacing.sm),
                            child: _MethodTile(
                              method: m,
                              selected: _method == m,
                              onTap: () => setState(() => _method = m),
                            ),
                          ),
                        ))
                    .toList(),
              ),
              if (_method.requiresReference) ...[
                const SizedBox(height: AppSpacing.md),
                TextField(
                  controller: _reference,
                  decoration: InputDecoration(
                    labelText: 'Nomor referensi QRIS',
                    errorText: _referenceError,
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
      actionsPadding: const EdgeInsets.fromLTRB(
        AppSpacing.md,
        0,
        AppSpacing.md,
        AppSpacing.md,
      ),
      actions: [
        OutlinedButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        const SizedBox(width: AppSpacing.sm),
        AsyncButton(
          label: 'Selesaikan Sesi',
          icon: Icons.receipt_long,
          onPressed: _submit,
        ),
      ],
    );
  }
}

/// Struk setelah checkout.
///
/// Menampilkan durasi aktual DAN durasi tertagih — supaya operator bisa
/// menjelaskan ke customer kenapa 63 menit ditagih 60 (DEC-009).
Future<void> showReceiptDialog(
  BuildContext context, {
  required Receipt receipt,
}) {
  final rounded =
      receipt.actualDurationMinutes != receipt.billableDurationMinutes;

  return showDialog<void>(
    context: context,
    barrierColor: AppColors.scrim,
    builder: (ctx) => AlertDialog(
      title: Row(
        children: [
          const Icon(Icons.check_circle,
              color: AppColors.statusAvailable, size: 28),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text('Sesi Selesai', style: AppTypography.headlineSm),
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
              Text(receipt.number, style: AppTypography.money),
              Text(
                formatClock(receipt.issuedAt),
                style: AppTypography.bodySm
                    .copyWith(color: AppColors.onSurfaceVariant),
              ),
              const Divider(height: AppSpacing.lg),
              if (receipt.actualDurationMinutes > 0) ...[
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Durasi bermain',
                        style: AppTypography.bodyMd
                            .copyWith(color: AppColors.onSurfaceVariant),
                      ),
                    ),
                    Text(
                      formatDurationLabel(receipt.actualDurationMinutes),
                      style: AppTypography.moneySm,
                    ),
                  ],
                ),
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Durasi ditagih',
                        style: AppTypography.bodyMd
                            .copyWith(color: AppColors.onSurfaceVariant),
                      ),
                    ),
                    Text(
                      formatDurationLabel(receipt.billableDurationMinutes),
                      style: AppTypography.moneySm.copyWith(
                        color: rounded
                            ? AppColors.statusAvailable
                            : AppColors.onSurface,
                      ),
                    ),
                  ],
                ),
                if (rounded)
                  Padding(
                    padding: const EdgeInsets.only(top: AppSpacing.xs),
                    child: Text(
                      'Dibulatkan per 30 menit, toleransi 5 menit.',
                      style: AppTypography.bodySm
                          .copyWith(color: AppColors.statusAvailable),
                    ),
                  ),
                const Divider(height: AppSpacing.lg),
              ],
              ...receipt.lines.map((l) => MoneyRow(
                    label: l.name,
                    sublabel: '${l.qty}×',
                    amount: l.subtotal,
                  )),
              const Divider(),
              MoneyRow(
                label: 'Total',
                amount: receipt.totals.grandTotal,
                emphasize: true,
              ),
              ...receipt.payments.map((p) => MoneyRow(
                    label: 'Dibayar — ${p.method.label}',
                    sublabel: p.reference,
                    amount: p.amount,
                  )),
            ],
          ),
        ),
      ),
      actionsPadding: const EdgeInsets.fromLTRB(
        AppSpacing.md,
        0,
        AppSpacing.md,
        AppSpacing.md,
      ),
      actions: [
        FilledButton(
          onPressed: () => Navigator.of(ctx).pop(),
          child: const Text('Tutup'),
        ),
      ],
    ),
  );
}
