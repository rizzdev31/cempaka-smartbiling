import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/status_style.dart';
import '../../core/theme/tokens.dart';

/// Chip status — warna **+ ikon + teks**.
///
/// Aturan `color-not-only` (UI-UX-SPEC §2): jangan pernah menampilkan status
/// hanya dengan warna. Operator bisa buta warna, dan di layar gelap
/// biru vs ungu sulit dibedakan sekilas.
class StatusChip extends StatelessWidget {
  const StatusChip({super.key, required this.status, this.compact = false});

  final StatusStyle status;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final iconSize = compact ? 14.0 : 16.0;
    final textStyle = (compact ? AppTypography.caption : AppTypography.cardLabel)
        .copyWith(color: status.color);

    return Semantics(
      label: 'Status: ${status.label}',
      excludeSemantics: true,
      child: Container(
        padding: EdgeInsets.symmetric(
          horizontal: compact ? AppSpacing.sm : AppSpacing.md - 4,
          vertical: compact ? 4 : AppSpacing.xs + 2,
        ),
        decoration: BoxDecoration(
          // Latar transparan dari warna status: tetap terbaca di dark mode
          // tanpa menabrak kontras teks.
          color: status.color.withValues(alpha: 0.14),
          borderRadius: BorderRadius.circular(AppRadius.chip),
          border: Border.all(color: status.color.withValues(alpha: 0.4)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(status.icon, size: iconSize, color: status.color),
            const SizedBox(width: AppSpacing.xs + 2),
            Text(status.label, style: textStyle),
          ],
        ),
      ),
    );
  }
}
