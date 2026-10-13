import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../core/time/server_time.dart';
import '../../core/time/ticker.dart';
import '../../core/util/format.dart';

/// Satu-satunya tempat timer dirender.
///
/// ATURAN KINERJA (UI-UX-SPEC §4):
/// Hanya widget ini yang mendengarkan [AppTicker]. Card, grid, dan layar
/// TIDAK boleh ikut rebuild per detik. Dengan 6 station, yang di-repaint
/// setiap detik adalah 6 `Text`, bukan 6 subtree.
///
/// Sisa waktu dihitung dari `end_at` + server-time offset (DEC-003) —
/// bukan dari `DateTime.now()` mentah.
class CountdownText extends StatelessWidget {
  const CountdownText({
    super.key,
    required this.endAt,
    this.startedAt,
    this.style,
    this.warningThreshold = const Duration(minutes: 10),
  });

  /// `null` -> placeholder (mis. sesi belum mulai / PENDING_PAYMENT).
  final DateTime? endAt;

  /// Dipakai saat [endAt] kosong padahal sesi sudah jalan — Postpaid
  /// menghitung **maju** dari sini (DEC-034).
  ///
  /// Tanpa ini Postpaid menampilkan `--:--:--` selamanya, dan operator tidak
  /// bisa melihat sudah berapa lama customer bermain — padahal justru itu
  /// yang menentukan tagihannya.
  final DateTime? startedAt;

  final TextStyle? style;

  /// Ambang warna oranye. Warning 10/5/1 menit dihitung LOKAL dari `end_at` —
  /// tidak ada event warning dari server (REALTIME.md §4).
  final Duration warningThreshold;

  @override
  Widget build(BuildContext context) {
    final base = style ?? AppTypography.timerCard;

    if (endAt == null) {
      // Postpaid: tidak ada batas, jadi yang ditampilkan waktu BERJALAN.
      if (startedAt != null) {
        return Consumer<AppTicker>(
          builder: (context, _, __) {
            final elapsed = ServerTime.instance.now.difference(startedAt!);
            return Text(
              formatCountdown(elapsed),
              // Sengaja warna normal: tidak ada yang hampir habis, jadi
              // tidak ada yang perlu diperingatkan.
              style: base,
              semanticsLabel: 'Berjalan ${formatCountdown(elapsed)}',
            );
          },
        );
      }

      return Text(
        '--:--:--',
        style: base.copyWith(color: AppColors.onSurfaceVariant),
        semanticsLabel: 'Timer belum berjalan',
      );
    }

    // Hanya potongan ini yang berlangganan ticker.
    return Consumer<AppTicker>(
      builder: (context, _, __) {
        final remaining = ServerTime.instance.remainingUntil(endAt!);
        final expired = remaining.isNegative;
        final nearEnd = !expired && remaining <= warningThreshold;

        final color = expired
            ? AppColors.statusExpired
            : nearEnd
                ? AppColors.statusWarning
                : (base.color ?? AppColors.onSurface);

        return Text(
          formatCountdown(remaining),
          style: base.copyWith(color: color),
          semanticsLabel: expired
              ? 'Waktu habis ${formatCountdown(remaining.abs())} lalu'
              : 'Sisa waktu ${formatCountdown(remaining)}',
        );
      },
    );
  }
}

/// Teks pendek "Sisa 42 menit" / "Lewat 3 menit".
///
/// Melengkapi [CountdownText] untuk memenuhi aturan `color-not-only`:
/// warna timer saja tidak cukup menyampaikan keadaan.
class RemainingLabel extends StatelessWidget {
  const RemainingLabel({
    super.key,
    required this.endAt,
    this.startedAt,
    this.style,
  });

  final DateTime? endAt;

  /// Lihat [CountdownText.startedAt].
  final DateTime? startedAt;

  final TextStyle? style;

  @override
  Widget build(BuildContext context) {
    if (endAt == null) {
      if (startedAt == null) return const SizedBox.shrink();

      return Consumer<AppTicker>(
        builder: (context, _, __) {
          final e = ServerTime.instance.now.difference(startedAt!);
          return Text(
            'Berjalan ${formatDurationLabel(e.inMinutes)}',
            style: (style ?? AppTypography.bodySm)
                .copyWith(color: AppColors.onSurfaceVariant),
          );
        },
      );
    }

    return Consumer<AppTicker>(
      builder: (context, _, __) {
        final r = ServerTime.instance.remainingUntil(endAt!);
        final label = r.isNegative
            ? 'Lewat ${formatDurationLabel(r.abs().inMinutes)}'
            : 'Sisa ${formatDurationLabel(r.inMinutes)}';

        return Text(
          label,
          style: (style ?? AppTypography.bodySm).copyWith(
            color: r.isNegative ? AppColors.statusExpired : AppColors.onSurfaceVariant,
          ),
        );
      },
    );
  }
}
