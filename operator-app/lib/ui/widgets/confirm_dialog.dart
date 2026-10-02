import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../domain/errors/api_error.dart';

/// Dialog konfirmasi untuk aksi yang sulit dibatalkan.
///
/// UI-UX-SPEC §7: aksi destruktif wajib pakai warna `danger`, terpisah dari
/// aksi normal, dan dikonfirmasi lebih dulu.
Future<bool> showConfirmDialog(
  BuildContext context, {
  required String title,
  required String message,
  String confirmLabel = 'Lanjutkan',
  String cancelLabel = 'Batal',
  bool destructive = false,
}) async {
  final result = await showDialog<bool>(
    context: context,
    barrierColor: AppColors.scrim,
    builder: (ctx) => AlertDialog(
      title: Text(title, style: AppTypography.headlineSm),
      content: Text(
        message,
        style: AppTypography.bodyMd.copyWith(color: AppColors.onSurfaceVariant),
      ),
      actionsPadding: const EdgeInsets.fromLTRB(
        AppSpacing.md,
        0,
        AppSpacing.md,
        AppSpacing.md,
      ),
      actions: [
        OutlinedButton(
          onPressed: () => Navigator.of(ctx).pop(false),
          child: Text(cancelLabel),
        ),
        const SizedBox(width: AppSpacing.sm),
        FilledButton(
          style: destructive
              ? FilledButton.styleFrom(backgroundColor: AppColors.error)
              : null,
          onPressed: () => Navigator.of(ctx).pop(true),
          child: Text(confirmLabel),
        ),
      ],
    ),
  );
  return result ?? false;
}

/// Tampilkan [ApiError] sebagai snackbar.
///
/// Pesan datang dari server dalam Bahasa Indonesia dan sudah siap dibaca
/// operator (kontrak §1). Error validasi per-field TIDAK ditampilkan di sini —
/// itu harus muncul di bawah field terkait (UI-UX-SPEC §7).
void showApiError(BuildContext context, Object error) {
  final messenger = ScaffoldMessenger.maybeOf(context);
  if (messenger == null) return;

  final (message, color) = switch (error) {
    ApiError e => (e.message, e.isRetryable ? AppColors.statusWarning : AppColors.error),
    _ => ('Terjadi kesalahan tidak terduga.', AppColors.error),
  };

  messenger.hideCurrentSnackBar();
  messenger.showSnackBar(
    SnackBar(
      content: Row(
        children: [
          Icon(
            error is ApiError && error.isRetryable
                ? Icons.wifi_off
                : Icons.error_outline,
            color: color,
            size: 20,
          ),
          const SizedBox(width: AppSpacing.sm + 2),
          Expanded(child: Text(message, style: AppTypography.bodyMd)),
        ],
      ),
      duration: const Duration(seconds: 5),
    ),
  );
}

/// Peringatan yang berasal dari client, bukan dari server.
/// Dipakai saat aksi tidak mungkin dilakukan sebelum request dikirim.
void showWarning(BuildContext context, String message) {
  final messenger = ScaffoldMessenger.maybeOf(context);
  if (messenger == null) return;

  messenger.hideCurrentSnackBar();
  messenger.showSnackBar(
    SnackBar(
      content: Row(
        children: [
          const Icon(Icons.info_outline,
              color: AppColors.statusWarning, size: 20),
          const SizedBox(width: AppSpacing.sm + 2),
          Expanded(child: Text(message, style: AppTypography.bodyMd)),
        ],
      ),
      duration: const Duration(seconds: 4),
    ),
  );
}

/// Snackbar sukses singkat — UI-UX-SPEC §7 `success-feedback`.
void showSuccess(BuildContext context, String message) {
  final messenger = ScaffoldMessenger.maybeOf(context);
  if (messenger == null) return;

  messenger.hideCurrentSnackBar();
  messenger.showSnackBar(
    SnackBar(
      content: Row(
        children: [
          const Icon(Icons.check_circle_outline,
              color: AppColors.statusAvailable, size: 20),
          const SizedBox(width: AppSpacing.sm + 2),
          Expanded(child: Text(message, style: AppTypography.bodyMd)),
        ],
      ),
      duration: const Duration(seconds: 3),
    ),
  );
}

/// Tombol yang menonaktifkan diri + menampilkan spinner selama aksi async.
///
/// Ini pertahanan PERTAMA terhadap double payment, sebelum
/// `Idempotency-Key` di lapisan data (T14).
class AsyncButton extends StatefulWidget {
  const AsyncButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.destructive = false,
    this.outlined = false,
    this.expand = false,
  });

  final String label;
  final IconData? icon;

  /// `null` -> tombol nonaktif.
  final Future<void> Function()? onPressed;
  final bool destructive;
  final bool outlined;
  final bool expand;

  @override
  State<AsyncButton> createState() => _AsyncButtonState();
}

class _AsyncButtonState extends State<AsyncButton> {
  bool _busy = false;

  Future<void> _run() async {
    if (_busy || widget.onPressed == null) return;
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

    final child = Row(
      mainAxisSize: widget.expand ? MainAxisSize.max : MainAxisSize.min,
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        if (_busy)
          const SizedBox(
            width: 18,
            height: 18,
            child: CircularProgressIndicator(strokeWidth: 2),
          )
        else if (widget.icon != null)
          Icon(widget.icon, size: 20),
        if (_busy || widget.icon != null) const SizedBox(width: AppSpacing.sm),
        Text(widget.label),
      ],
    );

    if (widget.outlined) {
      return OutlinedButton(
        onPressed: enabled ? _run : null,
        style: widget.destructive
            ? OutlinedButton.styleFrom(
                foregroundColor: AppColors.error,
                side: const BorderSide(color: AppColors.error),
              )
            : null,
        child: child,
      );
    }

    return FilledButton(
      onPressed: enabled ? _run : null,
      style: widget.destructive
          ? FilledButton.styleFrom(backgroundColor: AppColors.error)
          : null,
      child: child,
    );
  }
}
