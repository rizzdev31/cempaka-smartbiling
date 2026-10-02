import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/status_style.dart';
import '../../core/theme/tokens.dart';
import '../../core/time/server_time.dart';
import '../../core/time/ticker.dart';
import '../../core/util/format.dart';
import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import 'countdown_text.dart';

/// Tinggi minimum kartu station agar isinya tidak pernah overflow.
///
/// Dipakai `DashboardScreen` saat menghitung rasio grid. Kalau jendela
/// terlalu pendek untuk enam kartu setinggi ini, grid-nya di-scroll —
/// bukan kartunya dipaksa mengecil sampai rusak.
const double minStationCardHeight = AppSize.stationCardMinHeight;

/// Status yang DITAMPILKAN, diturunkan dari `end_at` di client.
///
/// Server menandai `WARNING`/`EXPIRED` lewat scheduler, jadi bisa terlambat
/// beberapa detik (REALTIME.md §5). Kalau kartu hanya memakai snapshot
/// server, timer bisa menunjukkan `-00:00:14` sementara label masih
/// "Bermain" — operator jadi tidak percaya pada layar.
///
/// Pola yang sama dipakai Kotlin TV Agent (PRD §16).
StationViewStatus deriveStationViewStatus(Station station) {
  final base = station.viewStatus;
  final endAt = station.session?.endAt;
  if (endAt == null) return base;
  if (base != StationViewStatus.active && base != StationViewStatus.warning) {
    return base;
  }

  final remaining = ServerTime.instance.remainingUntil(endAt);
  if (remaining.isNegative) return StationViewStatus.expired;
  if (remaining <= const Duration(minutes: 10)) {
    return StationViewStatus.warning;
  }
  return StationViewStatus.active;
}

typedef StationQuickAction = Future<void> Function();

/// Kartu satu station di dashboard.
///
/// Susunan mengikuti `contoh.html`:
/// header (kode + tipe konsol | status) → timer + bar waktu → blok customer
/// → baris aksi cepat.
///
/// Aksi cepat (+30m, +1j, F&B, Bayar) ada di kartu supaya operator tidak
/// perlu membuka detail sesi untuk pekerjaan yang paling sering dilakukan.
///
/// Kartu TIDAK berlangganan ticker. Hanya timer, bar waktu, dan label status
/// yang rebuild per detik (UI-UX-SPEC §4).
class StationCard extends StatelessWidget {
  const StationCard({
    super.key,
    required this.station,
    this.onTap,
    this.onExtend,
    this.onAddFnb,
    this.onPay,
    this.onStart,
    this.hourlyRateHint,
  });

  final Station station;

  /// Buka detail sesi.
  final VoidCallback? onTap;

  /// Tambah durasi. Menerima menit — selalu kelipatan 30 (DEC-007).
  final Future<void> Function(int minutes)? onExtend;

  final StationQuickAction? onAddFnb;
  final StationQuickAction? onPay;
  final StationQuickAction? onStart;

  /// Tarif per jam termurah, untuk ditampilkan pada station kosong.
  final int? hourlyRateHint;

  @override
  Widget build(BuildContext context) {
    final session = station.session;
    final idle = session == null;
    final maintenance = station.viewStatus == StationViewStatus.maintenance;

    return Semantics(
      button: onTap != null,
      label: '${station.name}, ${StatusStyle.of(station.viewStatus).label}',
      child: Opacity(
        opacity: maintenance ? 0.8 : 1,
        child: Material(
          color: AppColors.surfaceLow,
          borderRadius: BorderRadius.circular(AppRadius.lg),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: maintenance ? null : onTap,
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.sm + 4),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _CardHeader(station: station),
                  Expanded(
                    child: maintenance
                        ? const _MaintenanceBody()
                        : idle
                            ? _IdleBody(hourlyRateHint: hourlyRateHint)
                            : _ActiveBody(session: session),
                  ),
                  _CustomerBlock(station: station),
                  const SizedBox(height: AppSpacing.sm),
                  _ActionRow(
                    station: station,
                    onExtend: onExtend,
                    onAddFnb: onAddFnb,
                    onPay: onPay,
                    onStart: onStart,
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

// ─── Header ───────────────────────────────────────────────────────────

class _CardHeader extends StatelessWidget {
  const _CardHeader({required this.station});

  final Station station;

  @override
  Widget build(BuildContext context) {
    return Consumer<AppTicker>(
      builder: (context, _, __) {
        final style = StatusStyle.of(deriveStationViewStatus(station));
        final offline = station.device?.status == DeviceStatus.offline;

        return Container(
          padding: const EdgeInsets.only(bottom: AppSpacing.sm),
          decoration: const BoxDecoration(
            border: Border(bottom: BorderSide(color: AppColors.surfaceHigh)),
          ),
          // Kode station tetap penuh; label tipe konsol dan status
          // sama-sama fleksibel. Pada kartu sempit badge di-ellipsis dan
          // label status mengecil — bukan overflow.
          child: Row(
            children: [
              Text(
                station.code,
                style: AppTypography.headlineMd.copyWith(
                  color: style.color,
                  fontWeight: FontWeight.w700,
                ),
              ),
              if (station.consoleType != null) ...[
                const SizedBox(width: AppSpacing.sm - 2),
                Flexible(
                  flex: 3,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: AppSpacing.sm - 2,
                      vertical: 2,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.surfaceHigh,
                      borderRadius: BorderRadius.circular(AppRadius.sm),
                    ),
                    child: Text(
                      station.consoleType!.toUpperCase(),
                      style: AppTypography.labelSm.copyWith(color: style.color),
                      overflow: TextOverflow.ellipsis,
                      maxLines: 1,
                    ),
                  ),
                ),
              ],
              const SizedBox(width: AppSpacing.sm - 2),
              if (offline)
                const Padding(
                  padding: EdgeInsets.only(right: AppSpacing.xs),
                  child: Tooltip(
                    message: 'TV tidak mengirim heartbeat',
                    child: Icon(Icons.wifi_off,
                        size: 15, color: AppColors.statusOffline),
                  ),
                ),
              Flexible(
                flex: 4,
                child: Align(
                  alignment: Alignment.centerRight,
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.centerRight,
                    child: _StatusLabel(style: style),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// Titik warna + teks. Memenuhi `color-not-only` tanpa chip penuh yang akan
/// bersaing dengan timer.
class _StatusLabel extends StatelessWidget {
  const _StatusLabel({required this.style});

  final StatusStyle style;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 8,
          height: 8,
          decoration: BoxDecoration(color: style.color, shape: BoxShape.circle),
        ),
        const SizedBox(width: AppSpacing.sm - 2),
        Text(
          style.label,
          style: AppTypography.labelMd.copyWith(
            color: style.color,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}

// ─── Isi: sesi berjalan ───────────────────────────────────────────────

class _ActiveBody extends StatelessWidget {
  const _ActiveBody({required this.session});

  final StationSessionSummary session;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: CountdownText(endAt: session.endAt),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Text(
                  'Sisa Waktu',
                  style:
                      AppTypography.labelSm.copyWith(color: AppColors.outline),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          _TimeProgressBar(session: session),
          const SizedBox(height: AppSpacing.sm - 2),
          Row(
            children: [
              Flexible(
                child: Text(
                  session.startedAt == null
                      ? 'Belum mulai'
                      : 'Mulai ${formatClock(session.startedAt!)}',
                  style:
                      AppTypography.labelSm.copyWith(color: AppColors.outline),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Flexible(
                child: Text(
                  session.endAt == null
                      ? '—'
                      : 'Selesai ${formatClock(session.endAt!)}',
                  style:
                      AppTypography.labelSm.copyWith(color: AppColors.outline),
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.right,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Bar proporsi waktu. Warnanya ikut status, jadi saat mendekati habis
/// seluruh kartu berubah serentak.
class _TimeProgressBar extends StatelessWidget {
  const _TimeProgressBar({required this.session});

  final StationSessionSummary session;

  @override
  Widget build(BuildContext context) {
    return Consumer<AppTicker>(
      builder: (context, _, __) {
        final now = ServerTime.instance.now;
        final progress = session.progressAt(now) ?? 0.0;

        final remaining = session.endAt == null
            ? Duration.zero
            : ServerTime.instance.remainingUntil(session.endAt!);

        final color = remaining.isNegative
            ? AppColors.statusExpired
            : remaining <= const Duration(minutes: 10)
                ? AppColors.statusWarning
                : AppColors.primaryContainer;

        return ClipRRect(
          borderRadius: BorderRadius.circular(AppSize.progressBar / 2),
          child: SizedBox(
            height: AppSize.progressBar,
            child: Stack(
              children: [
                Container(color: AppColors.surfaceHighest),
                FractionallySizedBox(
                  widthFactor: progress.clamp(0.0, 1.0),
                  child: AnimatedContainer(
                    duration: AppMotion.normal,
                    curve: AppMotion.easeOut,
                    color: color,
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

// ─── Isi: station kosong & maintenance ────────────────────────────────

class _IdleBody extends StatelessWidget {
  const _IdleBody({this.hourlyRateHint});

  final int? hourlyRateHint;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            '--:--',
            style: AppTypography.timerCard.copyWith(
              color: AppColors.outlineVariant,
              letterSpacing: 2,
            ),
          ),
          const SizedBox(height: AppSpacing.xs),
          Text(
            hourlyRateHint == null
                ? 'Siap dipakai'
                : 'Tarif mulai ${formatRupiah(hourlyRateHint!)} / jam',
            style: AppTypography.bodySm
                .copyWith(color: AppColors.onSurfaceVariant),
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}

class _MaintenanceBody extends StatelessWidget {
  const _MaintenanceBody();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.handyman_outlined,
              size: 26, color: AppColors.outline),
          const SizedBox(height: AppSpacing.sm),
          Text(
            'Sedang diperbaiki',
            style: AppTypography.bodySm
                .copyWith(color: AppColors.onSurfaceVariant),
          ),
          // Teknisi, nomor tiket, dan estimasi selesai sengaja TIDAK
          // ditampilkan: data itu tidak ada di kontrak, dan memalsukannya
          // membuat operator mengandalkan informasi yang tidak pernah ada.
          // Lihat OD-016.
        ],
      ),
    );
  }
}

// ─── Blok customer ────────────────────────────────────────────────────

class _CustomerBlock extends StatelessWidget {
  const _CustomerBlock({required this.station});

  final Station station;

  @override
  Widget build(BuildContext context) {
    final session = station.session;
    final maintenance = station.viewStatus == StationViewStatus.maintenance;

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md - 2,
        vertical: AppSpacing.sm + 2,
      ),
      decoration: BoxDecoration(
        color: AppColors.surfaceLowest.withValues(alpha: 0.6),
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Row(
        children: [
          Expanded(
            child: session == null
                ? Text(
                    maintenance ? 'Tidak bisa dipakai' : 'Tidak ada pemain',
                    style:
                        AppTypography.bodySm.copyWith(color: AppColors.outline),
                  )
                : Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        session.customerLabel ?? 'Walk-in',
                        style: AppTypography.bodySm.copyWith(
                          color: AppColors.onSurface,
                          fontWeight: FontWeight.w500,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 1),
                      _RemainingHint(endAt: session.endAt),
                    ],
                  ),
          ),
          const SizedBox(width: AppSpacing.sm),
          if (session != null)
            Flexible(
              child: FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerRight,
                child: Text(
                  formatRupiah(session.balanceDue),
                  style: AppTypography.money.copyWith(
                    color: session.balanceDue > 0
                        ? AppColors.tertiaryContainer
                        : AppColors.secondary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            )
          else
            Text(
              maintenance ? '—' : 'Siap Pakai',
              style: AppTypography.labelSm.copyWith(
                color: maintenance ? AppColors.outline : AppColors.secondary,
              ),
            ),
        ],
      ),
    );
  }
}

class _RemainingHint extends StatelessWidget {
  const _RemainingHint({required this.endAt});

  final DateTime? endAt;

  @override
  Widget build(BuildContext context) {
    if (endAt == null) {
      return Text(
        'Menunggu pembayaran',
        style: AppTypography.labelSm.copyWith(color: AppColors.outline),
      );
    }

    return Consumer<AppTicker>(
      builder: (context, _, __) {
        final r = ServerTime.instance.remainingUntil(endAt!);
        return Text(
          r.isNegative
              ? 'Lewat ${formatDurationLabel(r.abs().inMinutes)}'
              : 'Sisa ${formatDurationLabel(r.inMinutes)}',
          style: AppTypography.labelSm.copyWith(
            color: r.isNegative ? AppColors.statusExpired : AppColors.outline,
          ),
        );
      },
    );
  }
}

// ─── Baris aksi ───────────────────────────────────────────────────────

class _ActionRow extends StatelessWidget {
  const _ActionRow({
    required this.station,
    this.onExtend,
    this.onAddFnb,
    this.onPay,
    this.onStart,
  });

  final Station station;
  final Future<void> Function(int minutes)? onExtend;
  final StationQuickAction? onAddFnb;
  final StationQuickAction? onPay;
  final StationQuickAction? onStart;

  @override
  Widget build(BuildContext context) {
    final session = station.session;

    if (station.viewStatus == StationViewStatus.maintenance) {
      return const SizedBox.shrink();
    }

    // Station kosong -> satu CTA utama (aturan `primary-action`).
    if (session == null) {
      return _PrimaryAction(
        label: 'Mulai Sesi Baru',
        icon: Icons.play_arrow,
        onPressed: onStart,
      );
    }

    // Belum bayar -> extend & F&B tidak relevan; yang penting pembayaran.
    if (session.status == SessionStatus.pendingPayment) {
      return _PrimaryAction(
        label: 'Proses Bayar',
        icon: Icons.payments_outlined,
        onPressed: onPay,
      );
    }

    final canOrderFnb = const {SessionStatus.active, SessionStatus.warning}
        .contains(session.status);

    // Semua tombol `Expanded` supaya baris ini tidak pernah overflow
    // horizontal, berapa pun lebar kartunya. "Bayar" diberi porsi dua kali
    // karena itu aksi yang paling sering dicari.
    return Row(
      children: [
        Expanded(
          child: _MiniAction(
            label: '+30m',
            onPressed: onExtend == null ? null : () => onExtend!(30),
          ),
        ),
        const SizedBox(width: AppSpacing.sm - 2),
        Expanded(
          child: _MiniAction(
            label: '+1j',
            onPressed: onExtend == null ? null : () => onExtend!(60),
          ),
        ),
        const SizedBox(width: AppSpacing.sm - 2),
        Expanded(
          child: _MiniAction(
            label: 'F&B',
            accent: true,
            onPressed: canOrderFnb ? onAddFnb : null,
          ),
        ),
        const SizedBox(width: AppSpacing.sm - 2),
        Expanded(
          flex: 2,
          child: _MiniAction(label: 'Bayar', filled: true, onPressed: onPay),
        ),
      ],
    );
  }
}

class _PrimaryAction extends StatefulWidget {
  const _PrimaryAction({
    required this.label,
    required this.icon,
    this.onPressed,
  });

  final String label;
  final IconData icon;
  final StationQuickAction? onPressed;

  @override
  State<_PrimaryAction> createState() => _PrimaryActionState();
}

class _PrimaryActionState extends State<_PrimaryAction> {
  bool _busy = false;

  Future<void> _run() async {
    setState(() => _busy = true);
    try {
      await widget.onPressed!();
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final enabled = widget.onPressed != null && !_busy;

    return SizedBox(
      width: double.infinity,
      height: 44,
      child: FilledButton(
        onPressed: enabled ? _run : null,
        style: FilledButton.styleFrom(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
          minimumSize: const Size(0, 44),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.sm),
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            if (_busy)
              const SizedBox(
                width: 16,
                height: 16,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            else
              Icon(widget.icon, size: 18),
            const SizedBox(width: AppSpacing.sm),
            Flexible(
              child: Text(widget.label, overflow: TextOverflow.ellipsis),
            ),
          ],
        ),
      ),
    );
  }
}

/// Tombol aksi cepat.
///
/// Menonaktifkan diri selama request berjalan — pertahanan pertama terhadap
/// tap ganda pada aksi yang mengubah uang.
class _MiniAction extends StatefulWidget {
  const _MiniAction({
    required this.label,
    this.onPressed,
    this.filled = false,
    this.accent = false,
  });

  final String label;
  final StationQuickAction? onPressed;
  final bool filled;
  final bool accent;

  @override
  State<_MiniAction> createState() => _MiniActionState();
}

class _MiniActionState extends State<_MiniAction> {
  bool _busy = false;

  @override
  Widget build(BuildContext context) {
    final enabled = widget.onPressed != null && !_busy;

    final bg = widget.filled
        ? AppColors.secondaryContainer
        : AppColors.surfaceContainer;
    final fg = widget.filled
        ? AppColors.onSecondary
        : widget.accent
            ? AppColors.primary
            : AppColors.onSurface;

    return Semantics(
      button: true,
      enabled: enabled,
      child: Material(
        color: enabled ? bg : AppColors.surfaceHigh,
        borderRadius: BorderRadius.circular(AppRadius.sm),
        child: InkWell(
          borderRadius: BorderRadius.circular(AppRadius.sm),
          onTap: enabled
              ? () async {
                  setState(() => _busy = true);
                  try {
                    await widget.onPressed!();
                  } finally {
                    if (mounted) setState(() => _busy = false);
                  }
                }
              : null,
          child: Container(
            height: 44,
            alignment: Alignment.center,
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.sm - 2),
            child: _busy
                ? const SizedBox(
                    width: 14,
                    height: 14,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(
                      widget.label,
                      maxLines: 1,
                      style: AppTypography.labelMd.copyWith(
                        color: enabled ? fg : AppColors.outline,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
          ),
        ),
      ),
    );
  }
}
