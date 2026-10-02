import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../domain/models/models.dart';
import '../../domain/repositories/billing_repository.dart';
import '../widgets/confirm_dialog.dart';

/// Hasil pemilihan customer.
///
/// DEC-008: **satu** customer per sesi, jadi ini satu pilihan tunggal —
/// bukan daftar.
class CustomerChoice {
  const CustomerChoice.member(Customer this.member) : walkInName = null;
  const CustomerChoice.walkIn([this.walkInName]) : member = null;

  final Customer? member;
  final String? walkInName;

  bool get isMember => member != null;

  /// Yang ditampilkan di layar.
  String get label =>
      member?.name ??
      (walkInName?.trim().isNotEmpty == true ? walkInName!.trim() : 'Walk-in');

  /// Dikirim ke API. `customer_id` dan `customer_name` saling eksklusif
  /// (kontrak §7).
  String? get customerId => member?.id;
  String? get customerName => isMember ? null : walkInName?.trim();

  static const defaultWalkIn = CustomerChoice.walkIn();
}

/// Pemilih customer.
///
/// Mengembalikan `null` kalau ditutup tanpa memilih.
///
/// Operator **tidak bisa mendaftarkan member baru** dari sini. PRD §6
/// memberi akses `customer` hanya kepada Admin/Owner; operator tidak
/// termasuk. Lihat OD-014 — kalau bisnis memang butuh pendaftaran di meja
/// kasir, itu perlu keputusan dulu, bukan diputuskan di kode.
Future<CustomerChoice?> showCustomerPicker(
  BuildContext context, {
  required BillingRepository repo,
  CustomerChoice? current,
}) {
  return showModalBottomSheet<CustomerChoice>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.surfaceLow,
    barrierColor: AppColors.scrim,
    shape: const RoundedRectangleBorder(
      borderRadius:
          BorderRadius.vertical(top: Radius.circular(AppRadius.modal)),
    ),
    builder: (_) => _CustomerPickerSheet(repo: repo, current: current),
  );
}

class _CustomerPickerSheet extends StatefulWidget {
  const _CustomerPickerSheet({required this.repo, this.current});

  final BillingRepository repo;
  final CustomerChoice? current;

  @override
  State<_CustomerPickerSheet> createState() => _CustomerPickerSheetState();
}

class _CustomerPickerSheetState extends State<_CustomerPickerSheet> {
  final _search = TextEditingController();
  final _walkInName = TextEditingController();

  Timer? _debounce;
  List<Customer> _results = const [];
  bool _loading = true;
  Object? _error;

  @override
  void initState() {
    super.initState();
    if (widget.current?.isMember == false) {
      _walkInName.text = widget.current?.walkInName ?? '';
    }
    _query('');
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    _walkInName.dispose();
    super.dispose();
  }

  /// Pencarian di-debounce 300 ms.
  ///
  /// Tanpa ini setiap ketikan mengirim satu request — operator yang mengetik
  /// "budi" mengirim empat permintaan dan hasil yang datang bisa tidak
  /// berurutan, sehingga daftar berkedip ke hasil yang salah.
  void _onSearchChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), () => _query(value));
  }

  Future<void> _query(String q) async {
    setState(() => _loading = true);
    try {
      final list = await widget.repo.searchCustomers(q);
      if (!mounted) return;
      // Hasil yang datang terlambat diabaikan kalau kolom pencarian
      // sudah berubah lagi.
      if (q != _search.text) return;
      setState(() {
        _results = list;
        _error = null;
      });
    } catch (e) {
      if (mounted) setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _pickMember(Customer c) =>
      Navigator.of(context).pop(CustomerChoice.member(c));

  void _pickWalkIn() => Navigator.of(context)
      .pop(CustomerChoice.walkIn(_walkInName.text.trim()));

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.viewInsetsOf(context).bottom;
    final maxHeight = MediaQuery.sizeOf(context).height * 0.85;

    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: SafeArea(
        child: ConstrainedBox(
          constraints: BoxConstraints(maxHeight: maxHeight),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.lg,
                  AppSpacing.lg,
                  AppSpacing.lg,
                  AppSpacing.sm,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text('Pilih Customer',
                              style: AppTypography.headlineSm),
                        ),
                        IconButton(
                          onPressed: () => Navigator.of(context).pop(),
                          icon: const Icon(Icons.close),
                          tooltip: 'Tutup',
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    TextField(
                      controller: _search,
                      autofocus: true,
                      textInputAction: TextInputAction.search,
                      onChanged: _onSearchChanged,
                      decoration: InputDecoration(
                        hintText: 'Cari nama atau nomor telepon',
                        prefixIcon: const Icon(Icons.search),
                        suffixIcon: _search.text.isEmpty
                            ? null
                            : IconButton(
                                icon: const Icon(Icons.clear),
                                tooltip: 'Hapus',
                                onPressed: () {
                                  _search.clear();
                                  _query('');
                                  setState(() {});
                                },
                              ),
                      ),
                    ),
                  ],
                ),
              ),

              // Walk-in selalu di atas: ini pilihan paling sering dipakai.
              _WalkInRow(
                controller: _walkInName,
                selected: widget.current?.isMember == false,
                onPick: _pickWalkIn,
              ),

              const Divider(height: 1),
              Flexible(child: _buildList()),
              const _OperatorScopeNote(),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildList() {
    if (_loading && _results.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(AppSpacing.xl),
        child: Center(child: CircularProgressIndicator()),
      );
    }

    if (_error != null) {
      return Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          children: [
            const Icon(Icons.error_outline, color: AppColors.error, size: 32),
            const SizedBox(height: AppSpacing.sm),
            Text(
              'Gagal memuat daftar customer.',
              style: AppTypography.bodyMd,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: AppSpacing.md),
            AsyncButton(
              label: 'Coba lagi',
              icon: Icons.refresh,
              onPressed: () => _query(_search.text),
            ),
          ],
        ),
      );
    }

    if (_results.isEmpty) {
      return Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          children: [
            const Icon(Icons.person_search_outlined,
                color: AppColors.onSurfaceVariant, size: 32),
            const SizedBox(height: AppSpacing.sm),
            Text(
              _search.text.trim().isEmpty
                  ? 'Belum ada member terdaftar.'
                  : 'Tidak ada member yang cocok dengan '
                      '"${_search.text.trim()}".',
              style:
                  AppTypography.bodySm.copyWith(color: AppColors.onSurfaceVariant),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: AppSpacing.xs),
            Text(
              'Gunakan Walk-in di atas kalau customer bukan member.',
              style:
                  AppTypography.bodySm.copyWith(color: AppColors.outline),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      shrinkWrap: true,
      itemCount: _results.length,
      separatorBuilder: (_, __) => const Divider(height: 1, indent: 64),
      itemBuilder: (_, i) {
        final c = _results[i];
        return _CustomerRow(
          customer: c,
          selected: widget.current?.member?.id == c.id,
          onTap: () => _pickMember(c),
        );
      },
    );
  }
}

class _WalkInRow extends StatelessWidget {
  const _WalkInRow({
    required this.controller,
    required this.selected,
    required this.onPick,
  });

  final TextEditingController controller;
  final bool selected;
  final VoidCallback onPick;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
      padding: const EdgeInsets.all(AppSpacing.md - 2),
      decoration: BoxDecoration(
        color: selected
            ? AppColors.primary.withValues(alpha: 0.12)
            : AppColors.surfaceContainer,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(
          color: selected ? AppColors.primary : AppColors.surfaceHigh,
          width: selected ? 2 : 1,
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: AppColors.overlaySubtle,
              borderRadius: BorderRadius.circular(AppRadius.md),
            ),
            child: const Icon(Icons.person_outline,
                size: 18, color: AppColors.onSurfaceVariant),
          ),
          const SizedBox(width: AppSpacing.sm + 4),
          Expanded(
            child: TextField(
              controller: controller,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(
                isDense: true,
                filled: false,
                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
                contentPadding: EdgeInsets.zero,
                hintText: 'Walk-in — nama opsional',
              ),
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          FilledButton(
            onPressed: onPick,
            style: FilledButton.styleFrom(
              minimumSize: const Size(0, AppSize.minTouchTarget - 8),
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
            ),
            child: const Text('Pakai'),
          ),
        ],
      ),
    );
  }
}

class _CustomerRow extends StatelessWidget {
  const _CustomerRow({
    required this.customer,
    required this.selected,
    required this.onTap,
  });

  final Customer customer;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final m = customer.membership;

    return Material(
      color: selected
          ? AppColors.primary.withValues(alpha: 0.10)
          : Colors.transparent,
      child: InkWell(
        onTap: onTap,
        child: Container(
          constraints:
              const BoxConstraints(minHeight: AppSize.minTouchTarget + 8),
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.lg,
            vertical: AppSpacing.sm + 2,
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
                child: Text(
                  customer.name.isEmpty
                      ? '?'
                      : customer.name.characters.first.toUpperCase(),
                  style: AppTypography.bodyLg
                      .copyWith(color: AppColors.onSurfaceVariant),
                ),
              ),
              const SizedBox(width: AppSpacing.sm + 4),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(customer.name, style: AppTypography.bodyLg),
                    if (customer.phone != null)
                      Text(
                        customer.phone!,
                        style: AppTypography.bodySm
                            .copyWith(color: AppColors.outline),
                      ),
                  ],
                ),
              ),
              if (m != null) _MembershipBadge(membership: m),
              if (selected)
                const Padding(
                  padding: EdgeInsets.only(left: AppSpacing.sm),
                  child: Icon(Icons.check_circle,
                      size: 18, color: AppColors.primary),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Badge membership.
///
/// Membership **kedaluwarsa** sengaja ditampilkan berbeda, bukan
/// disembunyikan: operator perlu tahu bahwa orang ini pernah member tapi
/// tidak berhak harga member sekarang.
class _MembershipBadge extends StatelessWidget {
  const _MembershipBadge({required this.membership});

  final Membership membership;

  @override
  Widget build(BuildContext context) {
    final active = membership.isActive;
    final color = active ? AppColors.tertiaryContainer : AppColors.outline;

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.sm,
        vertical: 3,
      ),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(AppRadius.pill),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            active ? Icons.workspace_premium : Icons.history_toggle_off,
            size: 12,
            color: color,
          ),
          const SizedBox(width: AppSpacing.xs),
          Text(
            active ? membership.tier : '${membership.tier} habis',
            style: AppTypography.bodySm
                .copyWith(color: color, fontSize: 11, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}

/// Catatan batas wewenang operator.
///
/// Menjelaskan kenapa tidak ada tombol "Tambah member" di sini, supaya
/// operator tidak mencarinya dan menyimpulkan aplikasinya belum jadi.
class _OperatorScopeNote extends StatelessWidget {
  const _OperatorScopeNote();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.md),
      color: AppColors.surfaceContainer,
      child: Row(
        children: [
          const Icon(Icons.info_outline, size: 15, color: AppColors.outline),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              'Pendaftaran member baru dilakukan Admin. Customer yang belum '
              'terdaftar bisa dilayani sebagai Walk-in.',
              style:
                  AppTypography.bodySm.copyWith(color: AppColors.outline),
            ),
          ),
        ],
      ),
    );
  }
}
