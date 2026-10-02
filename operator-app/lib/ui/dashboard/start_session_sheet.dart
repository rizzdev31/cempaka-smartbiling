import 'package:flutter/material.dart';
import 'package:uuid/uuid.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../core/util/format.dart';
import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import '../../domain/repositories/billing_repository.dart';
import '../customer/customer_picker.dart';
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
  required BillingRepository repo,
  required Future<Session> Function({
    required String packageId,
    required SessionMode mode,
    String? customerId,
    String? customerName,
    required String idempotencyKey,
  }) onSubmit,
}) {
  return showModalBottomSheet<Session>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.surfaceLow,
    barrierColor: AppColors.scrim,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(
        top: Radius.circular(AppRadius.modal),
      ),
    ),
    builder: (_) => _StartSessionSheet(
      station: station,
      packages: packages,
      repo: repo,
      onSubmit: onSubmit,
    ),
  );
}

class _StartSessionSheet extends StatefulWidget {
  const _StartSessionSheet({
    required this.station,
    required this.packages,
    required this.repo,
    required this.onSubmit,
  });

  final Station station;
  final List<Package> packages;
  final BillingRepository repo;
  final Future<Session> Function({
    required String packageId,
    required SessionMode mode,
    String? customerId,
    String? customerName,
    required String idempotencyKey,
  }) onSubmit;

  @override
  State<_StartSessionSheet> createState() => _StartSessionSheetState();
}

class _StartSessionSheetState extends State<_StartSessionSheet> {
  /// Satu key untuk satu niat memulai sesi. Dibuat sekali, dipakai ulang.
  final String _intentKey = const Uuid().v4();

  CustomerChoice _customer = CustomerChoice.defaultWalkIn;
  String? _packageId;
  SessionMode _mode = SessionMode.prepaid;

  @override
  void initState() {
    super.initState();
    if (widget.packages.isNotEmpty) _packageId = widget.packages.first.id;
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
        customerId: _customer.customerId,
        customerName: _customer.customerName,
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
                      style: AppTypography.headlineSm,
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
              Text('Paket', style: AppTypography.bodyLg),
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
              Text('Pembayaran', style: AppTypography.bodyLg),
              const SizedBox(height: AppSpacing.xs),
              Text(
                _mode == SessionMode.prepaid
                    ? 'Bayar rental di depan. Sesi mulai setelah pembayaran dikonfirmasi.'
                    : 'Sesi langsung jalan. Rental masuk Open Tab, ditagih saat checkout.',
                style:
                    AppTypography.bodySm.copyWith(color: AppColors.onSurfaceVariant),
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
              Text('Customer', style: AppTypography.bodyLg),
              const SizedBox(height: AppSpacing.sm),
              _CustomerRow(
                choice: _customer,
                onTap: () async {
                  final picked = await showCustomerPicker(
                    context,
                    repo: widget.repo,
                    current: _customer,
                  );
                  if (picked != null) setState(() => _customer = picked);
                },
              ),
              const SizedBox(height: AppSpacing.lg),

              // ── Ringkasan ────────────────────────────────────────
              if (pkg != null)
                Container(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  decoration: BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.circular(AppRadius.lg),
                    border: Border.all(color: AppColors.surfaceHigh),
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
                        amountColor: AppColors.tertiaryContainer,
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
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(AppRadius.md),
          child: Container(
            constraints: const BoxConstraints(
              minHeight: AppSize.minTouchTarget + 8,
            ),
            padding: const EdgeInsets.symmetric(
              horizontal: AppSpacing.md,
              vertical: AppSpacing.sm + 2,
            ),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(AppRadius.md),
              border: Border.all(
                color: selected ? AppColors.primary : AppColors.surfaceHigh,
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
                    Text(title, style: AppTypography.bodyLg),
                  ],
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: AppTypography.bodySm
                      .copyWith(color: AppColors.onSurfaceVariant),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Baris pemilih customer di sheet Mulai Sesi.
///
/// Satu baris yang bisa diketuk, bukan dua kontrol terpisah: dengan DEC-008
/// hanya ada satu customer per sesi, jadi satu nilai dan satu cara mengubahnya.
class _CustomerRow extends StatelessWidget {
  const _CustomerRow({required this.choice, required this.onTap});

  final CustomerChoice choice;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final member = choice.member;
    final m = member?.membership;
    final expired = m != null && !m.isActive;

    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        child: Container(
          constraints:
              const BoxConstraints(minHeight: AppSize.minTouchTarget + 8),
          padding: const EdgeInsets.all(AppSpacing.md - 2),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.lg),
            border: Border.all(color: AppColors.surfaceHigh),
          ),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.overlaySubtle,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Icon(
                  choice.isMember ? Icons.badge_outlined : Icons.person_outline,
                  size: 18,
                  color: choice.isMember
                      ? AppColors.tertiaryContainer
                      : AppColors.onSurfaceVariant,
                ),
              ),
              const SizedBox(width: AppSpacing.sm + 4),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(choice.label, style: AppTypography.bodyLg),
                    Text(
                      choice.isMember
                          ? (expired
                              ? 'Member ${m.tier} — sudah habis'
                              : 'Member ${m?.tier ?? ''}'.trim())
                          : 'Bukan member',
                      style: AppTypography.bodySm.copyWith(
                        color: expired
                            ? AppColors.statusWarning
                            : choice.isMember
                                ? AppColors.tertiaryContainer
                                : AppColors.outline,
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, color: AppColors.onSurfaceVariant),
            ],
          ),
        ),
      ),
    );
  }
}
