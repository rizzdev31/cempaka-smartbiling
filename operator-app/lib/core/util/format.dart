/// Formatter lokal. Paket `intl` sengaja tidak dipakai — kebutuhannya hanya
/// rupiah dan durasi, dan keduanya cukup beberapa baris.
library;

/// Rupiah dengan pemisah ribuan titik. Uang SELALU integer (DEC-005).
///
/// `45000` -> `Rp 45.000`
String formatRupiah(int value, {bool withPrefix = true}) {
  final negative = value < 0;
  final digits = value.abs().toString();
  final out = StringBuffer();

  for (var i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) out.write('.');
    out.write(digits[i]);
  }

  return '${negative ? '-' : ''}${withPrefix ? 'Rp ' : ''}$out';
}

/// Durasi jadi `HH:MM:SS` untuk timer.
///
/// Durasi negatif (sudah lewat `end_at`) diberi awalan `-` dan tetap
/// ditampilkan — operator perlu tahu sudah lewat berapa lama, bukan
/// melihat `00:00:00` yang menyesatkan.
String formatCountdown(Duration d) {
  final negative = d.isNegative;
  final abs = d.abs();

  final h = abs.inHours;
  final m = abs.inMinutes.remainder(60);
  final s = abs.inSeconds.remainder(60);

  String two(int n) => n.toString().padLeft(2, '0');
  return '${negative ? '-' : ''}${two(h)}:${two(m)}:${two(s)}';
}

/// Durasi dalam bahasa manusia: `1 jam 30 menit`, `45 menit`.
String formatDurationLabel(int minutes) {
  if (minutes <= 0) return '0 menit';
  final h = minutes ~/ 60;
  final m = minutes % 60;
  if (h == 0) return '$m menit';
  if (m == 0) return '$h jam';
  return '$h jam $m menit';
}

/// Jam lokal `HH:MM` dari timestamp UTC.
/// Simpan/kirim UTC, tampilkan lokal (DEC-005).
String formatClock(DateTime utc) {
  final local = utc.toLocal();
  String two(int n) => n.toString().padLeft(2, '0');
  return '${two(local.hour)}:${two(local.minute)}';
}

/// "baru saja" / "3 menit lalu" — untuk `last_seen` device.
String formatRelative(DateTime utc, DateTime nowUtc) {
  final diff = nowUtc.difference(utc);
  if (diff.inSeconds < 45) return 'baru saja';
  if (diff.inMinutes < 60) return '${diff.inMinutes} menit lalu';
  if (diff.inHours < 24) return '${diff.inHours} jam lalu';
  return '${diff.inDays} hari lalu';
}
