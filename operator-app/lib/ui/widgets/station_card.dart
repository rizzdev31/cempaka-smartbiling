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
import 'money_text.dart';
import 'status_chip.dart';

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
      ),
    );
  }
}

/// Kartu satu station di dashboard.
///
/// UI-UX-SPEC §3: operator **melirik**, tidak membaca. Enam kartu harus
/// terlihat tanpa scroll, dan status harus terbaca dalam sekali pandang.
///
/// Kartu ini TIDAK berlangganan ticker — hanya [CountdownText] di dalamnya
/// yang rebuild per detik (UI-UX-SPEC §4).
class StationCard extends StatelessWidget {
  const StationCard({super.key, required this.station, this.onTap});

  final Station station;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final view = station.viewStatus;
    final style = StatusStyle.of(view);
    final session = station.session;
    final deviceOffline = station.device?.status == DeviceStatus.offline;
    final tappable = onTap != null && view != StationViewStatus.maintenance;

    return Semantics(
      button: tappable,
      label: '${station.name}, ${style.label}',
      child: Material(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.card),
        child: InkWell(
          onTap: tappable ? onTap : null,
          borderRadius: BorderRadius.circular(AppRadius.card),
          child: Container(
            padding: const EdgeInsets.all(AppSpacing.md),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(AppRadius.card),
              border: Border.all(color: AppColors.border),
              // Garis warna di tepi kiri: isyarat status kedua, bukan
              // pengganti ikon+label.
              gradient: LinearGradient(
                colors: [
                  style.color.withValues(alpha: 0.10),
                  Colors.transparent,
                ],
                stops: const [0.0, 0.35],
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Baris 1 — kode station + status device
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        station.code,
                        style: AppTypography.screenTitle,
                      ),
                    ),
                    if (deviceOffline)
                      Tooltip(
                        message: station.device?.lastSeenAt == null
                            ? 'TV offline'
                            : 'TV offline · terakhir terlihat '
                                '${formatRelative(station.device!.lastSeenAt!, DateTime.now().toUtc())}',
                        child: const Icon(
                          Icons.wifi_off,
                          size: 18,
                          color: AppColors.statusOffline,
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: AppSpacing.sm),

                // Baris 2 — status: warna + ikon + label
                _LiveStatusChip(station: station),
                const SizedBox(height: AppSpacing.md),

                // Baris 3 — timer
                if (session != null && session.status.hasTimer)
                  CountdownText(endAt: session.endAt)
                else
                  Text(
                    _placeholderFor(view),
                    style: AppTypography.timerCard
                        .copyWith(color: AppColors.textMuted),
                  ),

                const Spacer(),

                // Baris 4 — customer + tagihan berjalan
                if (session != null) ...[
                  Row(
                    children: [
                      const Icon(Icons.person_outline,
                          size: 16, color: AppColors.textMuted),
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
                  const SizedBox(height: AppSpacing.xs),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      RemainingLabel(endAt: session.endAt),
                      MoneyText.small(
                        session.balanceDue,
                        color: session.balanceDue > 0
                            ? AppColors.accent
                            : AppColors.textMuted,
                      ),
                    ],
                  ),
                ] else
                  Text(
                    view == StationViewStatus.maintenance
                        ? 'Tidak bisa dipakai'
                        : 'Ketuk untuk mulai',
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

  static String _placeholderFor(StationViewStatus v) => switch (v) {
        StationViewStatus.available => '--:--:--',
        StationViewStatus.pendingPayment => 'Belum bayar',
        StationViewStatus.maintenance => 'Maintenance',
        _ => '--:--:--',
      };
}
