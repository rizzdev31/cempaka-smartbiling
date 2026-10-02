import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/status_style.dart';
import '../../core/theme/tokens.dart';

/// Chip status — warna **+ ikon + teks**.
///
/// Aturan `color-not-only` (UI-UX-SPEC §2): jangan pernah menampilkan status
/// hanya dengan warna. Operator bisa buta warna, dan di layar gelap
/// biru vs ungu sulit dibedakan sekilas.
///
/// Tanpa latar penuh dan tanpa garis tebal — titik warna + teks sudah cukup
/// dan tidak bersaing dengan timer, yang seharusnya jadi elemen dominan.
class StatusChip extends StatelessWidget {
  const StatusChip({super.key, required this.status, this.compact = false});

  final StatusStyle status;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final textStyle = (compact ? AppTypography.caption : AppTypography.cardLabel)
        .copyWith(color: status.color, fontWeight: FontWeight.w600);

    return Semantics(
      label: 'Status: ${status.label}',
      excludeSemantics: true,
      child: Container(
        padding: EdgeInsets.symmetric(
          horizontal: compact ? AppSpacing.sm : AppSpacing.sm + 2,
          vertical: compact ? AppSpacing.xs : AppSpacing.xs + 2,
        ),
        decoration: BoxDecoration(
          color: status.color.withValues(alpha: 0.13),
          borderRadius: BorderRadius.circular(AppRadius.chip),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              status.icon,
              size: compact ? 13 : 15,
              color: status.color,
            ),
            SizedBox(width: compact ? AppSpacing.xs + 1 : AppSpacing.xs + 2),
            Text(status.label, style: textStyle),
          ],
        ),
      ),
    );
  }
}

/// Titik warna + teks, tanpa latar. Untuk tempat yang sudah padat —
/// ringkasan di header, baris daftar.
class StatusDot extends StatelessWidget {
  const StatusDot({super.key, required this.status, this.showLabel = true});

  final StatusStyle status;
  final bool showLabel;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'Status: ${status.label}',
      excludeSemantics: true,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 7,
            height: 7,
            decoration: BoxDecoration(
              color: status.color,
              shape: BoxShape.circle,
            ),
          ),
          if (showLabel) ...[
            const SizedBox(width: AppSpacing.sm - 2),
            Text(
              status.label,
              style: AppTypography.caption.copyWith(color: AppColors.textMuted),
            ),
          ],
        ],
      ),
    );
  }
}
