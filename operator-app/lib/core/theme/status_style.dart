import 'package:flutter/material.dart';

import '../../domain/models/enums.dart';
import 'tokens.dart';

/// Gaya status station — sumber tunggal untuk warna + ikon + label.
///
/// ATURAN `color-not-only` (UI-UX-SPEC §2): status TIDAK BOLEH dibedakan
/// hanya dengan warna. Setiap tampilan status wajib memakai ketiganya.
/// Operator bisa buta warna, dan di layar gelap biru vs ungu sulit
/// dibedakan sekilas.
///
/// Dipakai juga sebagai acuan Kotlin TV dan Admin Web supaya konsisten.
class StatusStyle {
  const StatusStyle({
    required this.color,
    required this.icon,
    required this.label,
  });

  final Color color;
  final IconData icon;
  final String label;

  static StatusStyle of(StationViewStatus s) => switch (s) {
        StationViewStatus.available => const StatusStyle(
            color: AppColors.statusAvailable,
            icon: Icons.circle_outlined,
            label: 'Tersedia',
          ),
        StationViewStatus.pendingPayment => const StatusStyle(
            color: AppColors.statusPendingPayment,
            icon: Icons.schedule,
            label: 'Menunggu Bayar',
          ),
        StationViewStatus.active => const StatusStyle(
            color: AppColors.statusActive,
            icon: Icons.play_circle_outline,
            label: 'Bermain',
          ),
        StationViewStatus.warning => const StatusStyle(
            color: AppColors.statusWarning,
            icon: Icons.warning_amber_rounded,
            label: 'Hampir Habis',
          ),
        StationViewStatus.expired => const StatusStyle(
            color: AppColors.statusExpired,
            icon: Icons.highlight_off,
            label: 'Habis',
          ),
        StationViewStatus.checkout => const StatusStyle(
            color: AppColors.statusCheckout,
            icon: Icons.receipt_long,
            label: 'Checkout',
          ),
        StationViewStatus.offline => const StatusStyle(
            color: AppColors.statusOffline,
            icon: Icons.wifi_off,
            label: 'Offline',
          ),
        StationViewStatus.maintenance => const StatusStyle(
            color: AppColors.statusOffline,
            icon: Icons.build_outlined,
            label: 'Maintenance',
          ),
      };

  static StatusStyle ofSession(SessionStatus s) => switch (s) {
        SessionStatus.pendingPayment =>
          of(StationViewStatus.pendingPayment),
        SessionStatus.active => of(StationViewStatus.active),
        SessionStatus.warning => of(StationViewStatus.warning),
        SessionStatus.expired => of(StationViewStatus.expired),
        SessionStatus.checkout => of(StationViewStatus.checkout),
        SessionStatus.completed => const StatusStyle(
            color: AppColors.statusAvailable,
            icon: Icons.check_circle_outline,
            label: 'Selesai',
          ),
        SessionStatus.cancelled => const StatusStyle(
            color: AppColors.statusOffline,
            icon: Icons.cancel_outlined,
            label: 'Dibatalkan',
          ),
        SessionStatus.unknown => const StatusStyle(
            color: AppColors.statusOffline,
            icon: Icons.help_outline,
            label: 'Tidak diketahui',
          ),
      };
}
