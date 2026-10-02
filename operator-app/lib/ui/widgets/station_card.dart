import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/status_style.dart';
import '../../core/theme/tokens.dart';
import '../../core/time/server_time.dart';
import '../../core/time/ticker.dart';
import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import 'countdown_text.dart';
import 'money_text.dart';
import 'status_chip.dart';

/// Tinggi minimum kartu station agar isinya tidak pernah overflow.
///
/// Dipakai `DashboardScreen` saat menghitung rasio grid. Kalau jendela
/// terlalu pendek untuk enam kartu setinggi ini, grid-nya di-scroll —
/// bukan kartunya yang dipaksa mengecil sampai rusak.
///
/// Diuji di `test/station_card_layout_test.dart`.
const double minStationCardHeight = 200;

/// Status yang DITAMPILKAN, diturunkan dari `end_at` di client.
///
/// Kenapa tidak langsung memakai `session.status` dari server:
/// server menandai `WARNING`/`EXPIRED` lewat scheduler, jadi bisa terlambat
/// beberapa detik (REALTIME.md §5). Kalau kartu hanya memakai snapshot
/// server, timer bisa menunjukkan `-00:00:14` sementara label masih
/// "Bermain" — operator jadi tidak percaya pada layar.
///
/// Ini pola yang sama dengan Kotlin TV Agent: warning dihitung lokal dari
/// `end_at`, tidak menunggu event (PRD §16).
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

/// Kartu satu station di dashboard.
///
/// UI-UX-SPEC §3: operator **melirik**, tidak membaca. Enam kartu harus
/// terlihat tanpa scroll, dan status harus terbaca dalam sekali pandang.
///
/// Susunan: rail status di tepi kiri → kode station → chip status →
/// timer sebagai elemen dominan → bar proporsi waktu → customer & tagihan.
/// Rail dan bar memberi dua isyarat visual tambahan di luar warna, sehingga
/// kartu tetap terbaca dari jarak beberapa meter.
///
/// Kartu ini TIDAK berlangganan ticker. Hanya chip status, timer, dan bar
/// progres yang rebuild per detik (UI-UX-SPEC §4).
class StationCard extends StatelessWidget {
  const StationCard({super.key, required this.station, this.onTap});

  final Station station;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final view = station.viewStatus;
    final session = station.session;
    final idle = session == null;
    final maintenance = view == StationViewStatus.maintenance;
    final tappable = onTap != null && !maintenance;

    return Semantics(
      button: tappable,
      label: '${station.name}, ${StatusStyle.of(view).label}',
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppRadius.card),
          boxShadow: maintenance ? null : AppShadow.card,
        ),
        child: Material(
          color: maintenance ? AppColors.surfaceSunken : AppColors.surface,
          borderRadius: BorderRadius.circular(AppRadius.card),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: tappable ? onTap : null,
            child: Row(
              children: [
                // Rail status — isyarat paling cepat terbaca.
                _StatusRail(station: station),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(
                      AppSpacing.md - 2,
                      AppSpacing.sm + 4,
                      AppSpacing.md - 2,
                      AppSpacing.sm + 4,
                    ),
                    child: idle
                        ? _IdleContent(
                            station: station, maintenance: maintenance)
                        : _ActiveContent(station: station, session: session),
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

/// Batang warna status setinggi kartu di tepi kiri.
class _StatusRail extends StatelessWidget {
  const _StatusRail({required this.station});

  final Station station;

  @override
  Widget build(BuildContext context) {
    return Consumer<AppTicker>(
      builder: (context, _, __) {
        final style = StatusStyle.of(deriveStationViewStatus(station));
        return AnimatedContainer(
          duration: AppMotion.normal,
          curve: AppMotion.easeOut,
          width: AppSize.statusRail,
          color: style.color,
        );
      },
    );
  }
}

/// Isi kartu saat ada sesi berjalan.
class _ActiveContent extends StatelessWidget {
  const _ActiveContent({required this.station, required this.session});

  final Station station;
  final StationSessionSummary session;

  @override
  Widget build(BuildContext context) {
    final deviceOffline = station.device?.status == DeviceStatus.offline;

    // `spaceBetween` dipakai alih-alih `Spacer()`: dengan Spacer, kartu
    // yang pendek (jendela kecil, portrait sempit) langsung overflow.
    // Dengan spaceBetween, sisa ruang dibagi dan tidak pernah negatif.
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        // Blok 1 — kode station, status device, status sesi
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(station.code, style: AppTypography.display),
                ),
                if (deviceOffline) const _OfflineBadge(),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            _LiveStatusChip(station: station),
          ],
        ),

        // Blok 2 — elemen dominan: timer + bar proporsi waktu
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CountdownText(endAt: session.endAt),
            const SizedBox(height: AppSpacing.sm),
            _TimeProgressBar(session: session),
          ],
        ),

        // Blok 3 — customer & tagihan berjalan
        Row(
          children: [
            Expanded(
              child: Row(
                children: [
                  const Icon(Icons.person_outline,
                      size: 14, color: AppColors.textFaint),
                  const SizedBox(width: AppSpacing.xs + 2),
                  Expanded(
                    child: Text(
                      session.customerLabel ?? 'Walk-in',
                      style: AppTypography.caption
                          .copyWith(color: AppColors.textMuted),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
            // Nominal bisa panjang (mis. Rp 9.850.000) dan kartu bisa sempit.
            // FittedBox mengecilkan sedikit daripada menabrak nama customer.
            Flexible(
              child: FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerRight,
                child: session.balanceDue > 0
                    ? MoneyText.small(
                        session.balanceDue,
                        color: AppColors.accent,
                      )
                    : Row(
                        children: [
                          const Icon(Icons.check_circle_outline,
                              size: 13, color: AppColors.statusAvailable),
                          const SizedBox(width: AppSpacing.xs),
                          Text(
                            'Lunas',
                            style: AppTypography.caption
                                .copyWith(color: AppColors.statusAvailable),
                          ),
                        ],
                      ),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

/// Isi kartu saat station kosong atau maintenance.
///
/// Sengaja TIDAK menampilkan `--:--:--`. Deretan tanda hubung terlihat
/// seperti data gagal dimuat; station kosong justru keadaan normal dan
/// seharusnya mengundang untuk ditekan.
class _IdleContent extends StatelessWidget {
  const _IdleContent({required this.station, required this.maintenance});

  final Station station;
  final bool maintenance;

  @override
  Widget build(BuildContext context) {
    final style = StatusStyle.of(station.viewStatus);
    final deviceOffline = station.device?.status == DeviceStatus.offline;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                    station.code,
                    style: AppTypography.display.copyWith(
                      color:
                          maintenance ? AppColors.textFaint : AppColors.text,
                    ),
                  ),
                ),
                if (deviceOffline && !maintenance) const _OfflineBadge(),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            StatusChip(status: style, compact: true),
          ],
        ),
        Row(
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: maintenance
                    ? AppColors.overlaySubtle
                    : AppColors.primary.withValues(alpha: 0.14),
                borderRadius: BorderRadius.circular(AppRadius.button),
              ),
              child: Icon(
                maintenance ? Icons.build_outlined : Icons.add,
                size: 19,
                color: maintenance ? AppColors.textFaint : AppColors.primary,
              ),
            ),
            const SizedBox(width: AppSpacing.sm + 2),
            Expanded(
              child: Text(
                maintenance ? 'Tidak bisa dipakai' : 'Mulai sesi',
                style: AppTypography.cardLabel.copyWith(
                  color: maintenance ? AppColors.textFaint : AppColors.text,
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

/// Bar tipis proporsi waktu terpakai.
///
/// Warnanya mengikuti status, jadi saat mendekati habis seluruh kartu
/// berubah serentak: rail, chip, timer, dan bar ini.
class _TimeProgressBar extends StatelessWidget {
  const _TimeProgressBar({required this.session});

  final StationSessionSummary session;

  @override
  Widget build(BuildContext context) {
    return Consumer<AppTicker>(
      builder: (context, _, __) {
        final now = ServerTime.instance.now;
        final progress = session.progressAt(now);
        if (progress == null) return const SizedBox(height: AppSize.progressBar);

        final remaining = session.endAt == null
            ? Duration.zero
            : ServerTime.instance.remainingUntil(session.endAt!);

        final color = remaining.isNegative
            ? AppColors.statusExpired
            : remaining <= const Duration(minutes: 10)
                ? AppColors.statusWarning
                : AppColors.statusActive;

        return ClipRRect(
          borderRadius: BorderRadius.circular(AppSize.progressBar / 2),
          child: SizedBox(
            height: AppSize.progressBar,
            child: Stack(
              children: [
                Container(color: AppColors.surfaceSunken),
                FractionallySizedBox(
                  widthFactor: progress,
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

/// Chip status yang ikut berubah saat melewati ambang 10 menit / habis.
///
/// Hanya chip ini yang berlangganan ticker — bukan seluruh kartu.
class _LiveStatusChip extends StatelessWidget {
  const _LiveStatusChip({required this.station});

  final Station station;

  @override
  Widget build(BuildContext context) {
    return Consumer<AppTicker>(
      builder: (context, _, __) => StatusChip(
        status: StatusStyle.of(deriveStationViewStatus(station)),
        compact: true,
      ),
    );
  }
}

class _OfflineBadge extends StatelessWidget {
  const _OfflineBadge();

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: 'TV tidak mengirim heartbeat',
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.xs + 1),
        decoration: BoxDecoration(
          color: AppColors.overlaySubtle,
          borderRadius: BorderRadius.circular(AppRadius.button - 2),
        ),
        child: const Icon(Icons.wifi_off,
            size: 15, color: AppColors.statusOffline),
      ),
    );
  }
}
