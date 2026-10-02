import 'package:flutter/material.dart';
import 'package:uuid/uuid.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../core/util/format.dart';
import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import '../widgets/confirm_dialog.dart';
import '../widgets/money_text.dart';

/// Bottom sheet "Mulai Sesi".
///
/// Mengembalikan [Session] kalau berhasil, `null` kalau dibatalkan.
///
/// Catatan penting: `Idempotency-Key` dibuat SEKALI saat sheet dibuka
/// (`_intentKey`), bukan setiap kali tombol ditekan. Jadi kalau operator
/// menekan dua kali atau me-retry setelah timeout, server mengenali itu
/// sebagai satu niat yang sama dan tidak membuat sesi ganda (kontrak §3).
Future<Session?> showStartSessionSheet(
  BuildContext context, {
  required Station station,
  required List<Package> packages,
  required Future<Session> Function({
    required String packageId,
    required SessionMode mode,
    String? customerName,
    required String idempotencyKey,
  }) onSubmit,
}) {
  return showModalBottomSheet<Session>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.surfaceRaised,
    barrierColor: AppColors.scrim,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(
        top: Radius.circular(AppRadius.modal),
      ),
    ),
    builder: (_) => _StartSessionSheet(
      station: station,
      packages: packages,
      onSubmit: onSubmit,
    ),
  );
}

class _StartSessionSheet extends StatefulWidget {
  const _StartSessionSheet({
    required this.station,
    required this.packages,
    required this.onSubmit,
  });

  final Station station;
  final List<Package> packages;
  final Future<Session> Function({
    required String packageId,
    required SessionMode mode,
    String? customerName,
    required String idempotencyKey,
  }) onSubmit;

  @override
  State<_StartSessionSheet> createState() => _StartSessionSheetState();
}

class _StartSessionSheetState extends State<_StartSessionSheet> {
  /// Satu key untuk satu niat memulai sesi. Dibuat sekali, dipakai ulang.
  final String _intentKey = const Uuid().v4();

  final _nameController = TextEditingController();
  String? _packageId;
  SessionMode _mode = SessionMode.prepaid;

  @override
  void initState() {
    super.initState();
    if (widget.packages.isNotEmpty) _packageId = widget.packages.first.id;
  }

  @override
  void dispose() {
    _nameController.dispose();
    super.dispose();
  }

  Package? get _package => _packageId == null
      ? null
      : widget.packages.firstWhere((p) => p.id == _packageId);

  Future<void> _submit() async {
    final pkg = _package;
    if (pkg == null) return;

    try {
      final session = await widget.onSubmit(
        packageId: pkg.id,
        mode: _mode,
        customerName: _nameController.text.trim().isEmpty
            ? null
            : _nameController.text.trim(),
        idempotencyKey: _intentKey,
      );
      if (!mounted) return;
      Navigator.of(context).pop(session);
    } catch (e) {
      if (!mounted) return;
      showApiError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final pkg = _package;
    final bottomInset = MediaQuery.viewInsetsOf(context).bottom;

    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      'Mulai Sesi — ${widget.station.code}',
                      style: AppTypography.screenTitle,
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close),
                    tooltip: 'Tutup',
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),

              // ── Paket ────────────────────────────────────────────
              Text('Paket', style: AppTypography.cardLabel),
              const SizedBox(height: AppSpacing.sm),
              Wrap(
                spacing: AppSpacing.sm,
                runSpacing: AppSpacing.sm,
                children: widget.packages.map((p) {
                  final selected = p.id == _packageId;
                  return _ChoiceTile(
                    selected: selected,
                    title: p.name,
                    subtitle: formatRupiah(p.price),
                    onTap: () => setState(() => _packageId = p.id),
                  );
                }).toList(),
              ),
              const SizedBox(height: AppSpacing.lg),

              // ── Mode pembayaran ──────────────────────────────────
              Text('Pembayaran', style: AppTypography.cardLabel),
              const SizedBox(height: AppSpacing.xs),
              Text(
                _mode == SessionMode.prepaid
                    ? 'Bayar rental di depan. Sesi mulai setelah pembayaran dikonfirmasi.'
                    : 'Sesi langsung jalan. Rental masuk Open Tab, ditagih saat checkout.',
                style:
                    AppTypography.caption.copyWith(color: AppColors.textMuted),
              ),
              const SizedBox(height: AppSpacing.sm),
              Row(
                children: [
                  Expanded(
                    child: _ChoiceTile(
                      selected: _mode == SessionMode.prepaid,
                      title: 'Prepaid',
                      subtitle: 'Bayar dulu',
                      onTap: () =>
                          setState(() => _mode = SessionMode.prepaid),
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(
                    child: _ChoiceTile(
                      selected: _mode == SessionMode.postpaid,
                      title: 'Postpaid',
                      subtitle: 'Bayar belakangan',
                      onTap: () =>
                          setState(() => _mode = SessionMode.postpaid),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),

              // ── Customer ─────────────────────────────────────────
              TextField(
                controller: _nameController,
                textCapitalization: TextCapitalization.words,
                decoration: const InputDecoration(
                  labelText: 'Nama customer (opsional)',
                  helperText: 'Kosongkan untuk walk-in',
                ),
              ),
              const SizedBox(height: AppSpacing.lg),

              // ── Ringkasan ────────────────────────────────────────
              if (pkg != null)
                Container(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  decoration: BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.circular(AppRadius.card),
                    border: Border.all(color: AppColors.border),
                  ),
                  child: Column(
                    children: [
                      MoneyRow(
                        label: 'Rental ${pkg.name}',
                        sublabel: formatDurationLabel(pkg.durationMinutes),
                        amount: pkg.price,
                      ),
                      const Divider(),
                      MoneyRow(
                        label: _mode == SessionMode.prepaid
                            ? 'Dibayar sekarang'
                            : 'Masuk Open Tab',
                        amount: pkg.price,
                        emphasize: true,
                        amountColor: AppColors.accent,
                      ),
                    ],
                  ),
                ),
              const SizedBox(height: AppSpacing.lg),

              SizedBox(
                width: double.infinity,
                child: AsyncButton(
                  label: _mode == SessionMode.prepaid
                      ? 'Buat Sesi — Menunggu Bayar'
                      : 'Mulai Sesi Sekarang',
                  icon: Icons.play_arrow,
                  expand: true,
                  onPressed: pkg == null ? null : _submit,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ChoiceTile extends StatelessWidget {
  const _ChoiceTile({
    required this.selected,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final bool selected;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      selected: selected,
      button: true,
      child: Material(
        color: selected
            ? AppColors.primary.withValues(alpha: 0.16)
            : AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.button),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(AppRadius.button),
          child: Container(
            constraints: const BoxConstraints(
              minHeight: AppSize.minTouchTarget + 8,
            ),
            padding: const EdgeInsets.symmetric(
              horizontal: AppSpacing.md,
              vertical: AppSpacing.sm + 2,
            ),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(AppRadius.button),
              border: Border.all(
                color: selected ? AppColors.primary : AppColors.border,
                width: selected ? 2 : 1,
              ),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    if (selected)
                      const Padding(
                        padding: EdgeInsets.only(right: AppSpacing.xs + 2),
                        child: Icon(Icons.check_circle,
                            size: 16, color: AppColors.primary),
                      ),
                    Text(title, style: AppTypography.cardLabel),
                  ],
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: AppTypography.caption
                      .copyWith(color: AppColors.textMuted),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
