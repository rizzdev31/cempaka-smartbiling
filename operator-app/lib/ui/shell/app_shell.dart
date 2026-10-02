import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/brand.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../core/util/format.dart';
import '../../data/tv/tv_sync_service.dart';
import '../dashboard/dashboard_controller.dart';
import '../dashboard/dashboard_screen.dart';
import '../device/device_screen.dart';
import '../fnb/fnb_queue_screen.dart';
import '../settings/settings_screen.dart';
import '../shift/shift_screen.dart';
import '../widgets/brand_mark.dart';
import '../widgets/connection_banner.dart';

/// Tujuan navigasi utama.
enum ShellSection {
  stations(Icons.grid_view_outlined, 'Monitor Stasiun'),
  fnb(Icons.fastfood_outlined, 'Pesanan F&B'),
  shift(Icons.receipt_long_outlined, 'Laporan Shift'),
  devices(Icons.tv_outlined, 'Status TV'),
  settings(Icons.settings_outlined, 'Pengaturan');

  const ShellSection(this.icon, this.label);
  final IconData icon;
  final String label;
}

/// Kerangka aplikasi: sidebar + header + area kerja.
///
/// Mengikuti `contoh.html`. Sidebar dipakai sebagai navigasi utama karena
/// operator berpindah antar empat area kerja sepanjang shift, dan sidebar
/// membuat posisinya selalu terlihat — berbeda dari bar aksi sebelumnya
/// yang menyembunyikan tujuan di balik tombol.
///
/// **Adaptif** (aturan `adaptive-navigation`): sidebar penuh hanya di layar
/// lebar. Di bawah [AppSize.sidebarExpandBreakpoint] menyusut jadi rail
/// ikon, supaya area kerja tidak kehabisan ruang di tablet portrait —
/// 288 px dari 800 px adalah 36% layar, terlalu banyak untuk navigasi.
class AppShell extends StatefulWidget {
  const AppShell({super.key});

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  ShellSection _section = ShellSection.stations;

  Future<void> _select(ShellSection s) async {
    if (s == _section) return;
    setState(() => _section = s);

    // Status order dan pembayaran mempengaruhi angka di header,
    // jadi data dashboard disegarkan saat kembali ke monitor.
    if (s == ShellSection.stations) {
      await context.read<DashboardController>().refresh();
    }
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final expanded = width >= AppSize.sidebarExpandBreakpoint;

    return Scaffold(
      backgroundColor: AppColors.surface,
      body: SafeArea(
        child: Row(
          children: [
            _Sidebar(
              expanded: expanded,
              current: _section,
              onSelect: _select,
            ),
            Expanded(
              child: Column(
                children: [
                  _Header(section: _section),
                  Expanded(
                    child: ClipRect(
                      // Setiap section membawa layarnya sendiri. IndexedStack
                      // dipakai supaya posisi scroll dan filter tidak hilang
                      // saat operator berpindah dan kembali
                      // (aturan `state-preservation`).
                      child: IndexedStack(
                        index: ShellSection.values.indexOf(_section),
                        children: const [
                          DashboardScreen(embedded: true),
                          FnbQueueScreen(embedded: true),
                          ShiftScreen(embedded: true),
                          DeviceScreen(embedded: true),
                          SettingsScreen(embedded: true),
                        ],
                      ),
                    ),
                  ),
                  const _ShellFooter(),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Sidebar ──────────────────────────────────────────────────────────

class _Sidebar extends StatelessWidget {
  const _Sidebar({
    required this.expanded,
    required this.current,
    required this.onSelect,
  });

  final bool expanded;
  final ShellSection current;
  final ValueChanged<ShellSection> onSelect;

  @override
  Widget build(BuildContext context) {
    final ctrl = context.watch<DashboardController>();
    final tv = context.watch<TvSyncService>();

    return AnimatedContainer(
      duration: AppMotion.normal,
      curve: AppMotion.easeOut,
      width: expanded ? AppSize.sidebarWidth : AppSize.sidebarRailWidth,
      decoration: const BoxDecoration(
        color: AppColors.surfaceLowest,
        boxShadow: AppShadow.panel,
      ),
      child: Column(
        children: [
          // Blok merek
          Container(
            height: AppSize.headerHeight,
            padding: EdgeInsets.symmetric(
              horizontal: expanded ? AppSpacing.gutter : 0,
            ),
            alignment: expanded ? Alignment.centerLeft : Alignment.center,
            color: AppColors.surfaceLow.withValues(alpha: 0.6),
            child: BrandMark(showName: expanded),
          ),

          if (expanded)
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.gutter,
                AppSpacing.lg,
                AppSpacing.gutter,
                AppSpacing.sm,
              ),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  'MENU UTAMA',
                  style:
                      AppTypography.labelSm.copyWith(color: AppColors.outline),
                ),
              ),
            )
          else
            const SizedBox(height: AppSpacing.md),

          // Navigasi
          Expanded(
            child: ListView(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.sm),
              children: [
                for (final s in ShellSection.values)
                  _NavItem(
                    section: s,
                    expanded: expanded,
                    active: s == current,
                    badge: switch (s) {
                      ShellSection.fnb => ctrl.fnbActionableCount,
                      // Dari TvSyncService: ini status sambungan yang nyata,
                      // bukan heartbeat karangan dari data contoh.
                      ShellSection.devices => tv.problemCount,
                      _ => 0,
                    },
                    onTap: () => onSelect(s),
                  ),
              ],
            ),
          ),

          // Kartu operator di bawah
          _OperatorCard(expanded: expanded),
        ],
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  const _NavItem({
    required this.section,
    required this.expanded,
    required this.active,
    required this.badge,
    required this.onTap,
  });

  final ShellSection section;
  final bool expanded;
  final bool active;
  final int badge;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final content = Row(
      mainAxisAlignment:
          expanded ? MainAxisAlignment.start : MainAxisAlignment.center,
      children: [
        Icon(
          section.icon,
          size: 20,
          color: active
              ? AppColors.onPrimaryContainer
              : AppColors.onSurfaceVariant,
        ),
        if (expanded) ...[
          const SizedBox(width: AppSpacing.sm + 2),
          Expanded(
            child: Text(
              section.label,
              style: AppTypography.bodyMd.copyWith(
                color: active
                    ? AppColors.onPrimaryContainer
                    : AppColors.onSurfaceVariant,
                fontWeight: active ? FontWeight.w600 : FontWeight.w400,
              ),
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
        if (badge > 0)
          _NavBadge(count: badge, onActive: active, compact: !expanded),
      ],
    );

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.xs),
      child: Semantics(
        button: true,
        selected: active,
        label: badge > 0
            ? '${section.label}, $badge perlu tindakan'
            : section.label,
        excludeSemantics: true,
        child: Tooltip(
          message: expanded ? '' : section.label,
          child: Material(
            // Nav aktif: isian cyan + glow halus. Ini efek khas desainnya.
            color: active ? AppColors.primaryContainer : Colors.transparent,
            borderRadius: BorderRadius.circular(AppRadius.md),
            child: InkWell(
              onTap: onTap,
              borderRadius: BorderRadius.circular(AppRadius.md),
              child: AnimatedContainer(
                duration: AppMotion.fast,
                height: AppSize.minTouchTarget,
                padding: EdgeInsets.symmetric(
                  horizontal: expanded ? AppSpacing.md : 0,
                ),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(AppRadius.md),
                  boxShadow: active ? AppShadow.glowPrimary : null,
                ),
                child: content,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _NavBadge extends StatelessWidget {
  const _NavBadge({
    required this.count,
    required this.onActive,
    required this.compact,
  });

  final int count;
  final bool onActive;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    // Pada rail, badge berupa titik saja — angkanya tidak akan terbaca
    // di ruang sesempit itu.
    if (compact) {
      return Container(
        width: 7,
        height: 7,
        margin: const EdgeInsets.only(left: 2),
        decoration: const BoxDecoration(
          color: AppColors.tertiaryContainer,
          shape: BoxShape.circle,
        ),
      );
    }

    return Container(
      constraints: const BoxConstraints(minWidth: 22),
      height: 20,
      alignment: Alignment.center,
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.sm - 2),
      decoration: BoxDecoration(
        color: onActive
            ? AppColors.onPrimaryContainer
            : AppColors.tertiaryContainer,
        borderRadius: BorderRadius.circular(AppRadius.pill),
      ),
      child: Text(
        '$count',
        style: AppTypography.labelSm.copyWith(
          color: onActive ? AppColors.primary : AppColors.onTertiary,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

/// Kartu operator + tombol tutup shift.
///
/// Tombol tutup shift **dipisah** dari daftar navigasi, sesuai aturan
/// `destructive-nav-separation` — menutup shift bukan navigasi, dan tidak
/// boleh ditekan karena salah sasaran.
class _OperatorCard extends StatelessWidget {
  const _OperatorCard({required this.expanded});

  final bool expanded;

  @override
  Widget build(BuildContext context) {
    final shift = context.watch<DashboardController>().currentShift;
    final name = shift?.operator.name ?? 'Belum login';
    final initials = name
        .split(' ')
        .where((p) => p.isNotEmpty)
        .take(2)
        .map((p) => p.characters.first.toUpperCase())
        .join();

    final avatar = Container(
      width: 34,
      height: 34,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: AppColors.surfaceHigh,
        borderRadius: BorderRadius.circular(AppRadius.pill),
      ),
      child: Text(
        initials.isEmpty ? '?' : initials,
        style: AppTypography.labelMd.copyWith(
          color: AppColors.primary,
          fontWeight: FontWeight.w700,
        ),
      ),
    );

    if (!expanded) {
      return Padding(
        padding: const EdgeInsets.only(bottom: AppSpacing.md),
        child: Tooltip(message: name, child: avatar),
      );
    }

    return Container(
      margin: const EdgeInsets.all(AppSpacing.sm),
      padding: const EdgeInsets.all(AppSpacing.md - 2),
      decoration: BoxDecoration(
        color: AppColors.surfaceLow,
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: Row(
        children: [
          avatar,
          const SizedBox(width: AppSpacing.sm + 2),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  style: AppTypography.bodySm.copyWith(
                    color: AppColors.onSurface,
                    fontWeight: FontWeight.w600,
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Row(
                  children: [
                    Container(
                      width: 6,
                      height: 6,
                      decoration: BoxDecoration(
                        color: shift == null
                            ? AppColors.outline
                            : AppColors.secondary,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: AppSpacing.xs),
                    Text(
                      shift == null
                          ? 'Shift belum dibuka'
                          : 'Shift ${formatClock(shift.openedAt)}',
                      style: AppTypography.labelSm.copyWith(
                        color: shift == null
                            ? AppColors.outline
                            : AppColors.secondary,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Header ───────────────────────────────────────────────────────────

class _Header extends StatelessWidget {
  const _Header({required this.section});

  final ShellSection section;

  @override
  Widget build(BuildContext context) {
    final ctrl = context.watch<DashboardController>();
    final wide = MediaQuery.sizeOf(context).width >= 860;

    return Container(
      height: AppSize.headerHeight,
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.gutterLg),
      decoration: const BoxDecoration(
        color: AppColors.surfaceLowest,
        boxShadow: AppShadow.card,
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              section.label,
              style: AppTypography.headlineSm
                  .copyWith(color: AppColors.onSurface),
              overflow: TextOverflow.ellipsis,
            ),
          ),

          // Dua angka yang paling sering ditanya pemilik.
          if (wide) ...[
            _HeaderStats(ctrl: ctrl),
            const SizedBox(width: AppSpacing.md),
            Container(width: 1, height: 28, color: AppColors.surfaceHigh),
            const SizedBox(width: AppSpacing.md),
          ],

          if (ctrl.isSampleData) ...[
            const _SampleDataChip(),
            const SizedBox(width: AppSpacing.sm),
          ],
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
        ],
      ),
    );
  }
}

/// Penanda bahwa data billing di layar adalah **data contoh**.
///
/// Dipasang selama kontrol TV diuji (DEC-015): sambungan ke TV **nyata**,
/// sementara sesi, customer, dan uang masih contoh. Tanpa penanda ini, angka
/// contoh mudah dibaca sebagai angka asli — dan kekeliruan itu paling
/// berbahaya justru saat sedang menguji, ketika perhatian ada di TV.
///
/// Hilang sendiri begitu `ApiBillingRepository` masuk (DEC-012 syarat 2).
class _SampleDataChip extends StatelessWidget {
  const _SampleDataChip();

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: 'Sesi, customer, dan nominal di layar ini adalah data contoh. '
          'Sambungan ke TV nyata.',
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm,
          vertical: AppSpacing.xs,
        ),
        decoration: BoxDecoration(
          color: AppColors.tertiaryContainer.withValues(alpha: 0.16),
          borderRadius: BorderRadius.circular(AppRadius.pill),
          border: Border.all(
            color: AppColors.tertiaryContainer.withValues(alpha: 0.4),
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.science_outlined,
                size: 13, color: AppColors.tertiaryContainer),
            const SizedBox(width: AppSpacing.xs),
            Text(
              'DATA CONTOH',
              style: AppTypography.labelSm
                  .copyWith(color: AppColors.tertiaryContainer),
            ),
          ],
        ),
      ),
    );
  }
}

class _HeaderStats extends StatelessWidget {
  const _HeaderStats({required this.ctrl});

  final DashboardController ctrl;

  @override
  Widget build(BuildContext context) {
    final total = ctrl.stations.length;
    final busy = total - ctrl.availableCount;

    return Container(
      padding: const EdgeInsets.all(AppSpacing.xs + 2),
      decoration: BoxDecoration(
        color: AppColors.surfaceLow.withValues(alpha: 0.7),
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: AppColors.surfaceHigh),
      ),
      child: Row(
        children: [
          _HeaderStat(
            label: 'OKUPANSI',
            value: '$busy/$total',
            color: AppColors.primary,
          ),
          Container(
            width: 1,
            height: 24,
            margin: const EdgeInsets.symmetric(horizontal: AppSpacing.sm),
            color: AppColors.surfaceHigh,
          ),
          _HeaderStat(
            label: 'TAGIHAN BERJALAN',
            value: formatRupiah(ctrl.openBalance),
            color: AppColors.tertiaryFixedDim,
          ),
        ],
      ),
    );
  }
}

class _HeaderStat extends StatelessWidget {
  const _HeaderStat({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: '$label: $value',
      excludeSemantics: true,
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm + 2,
          vertical: AppSpacing.xs,
        ),
        decoration: BoxDecoration(
          color: AppColors.surfaceLowest.withValues(alpha: 0.6),
          borderRadius: BorderRadius.circular(AppRadius.sm),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              label,
              style: AppTypography.labelSm
                  .copyWith(color: AppColors.outline, fontSize: 10),
            ),
            Text(
              value,
              style: AppTypography.labelMd.copyWith(
                color: color,
                fontWeight: FontWeight.w700,
                fontSize: 13,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Footer ───────────────────────────────────────────────────────────

/// Footer tipis.
///
/// Memuat atribusi naungan Cempaka Smart Billing, yang tetap tampil walau
/// merek pelanggan berbeda (OD-012).
class _ShellFooter extends StatelessWidget {
  const _ShellFooter();

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 34,
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.gutterLg),
      color: AppColors.surfaceLowest,
      child: Row(
        children: [
          Expanded(
            child: Text(
              Brand.poweredBy,
              style: AppTypography.labelSm.copyWith(color: AppColors.outline),
              overflow: TextOverflow.ellipsis,
            ),
          ),
          Text(
            'v${Brand.version}',
            style: AppTypography.labelSm.copyWith(color: AppColors.outline),
          ),
        ],
      ),
    );
  }
}
