import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../core/util/format.dart';

/// Satu-satunya tempat nominal uang dirender.
///
/// Uang SELALU integer rupiah (DEC-005) dan SELALU tabular figures —
/// tanpa itu kolom angka tidak sejajar dan total terlihat bergeser.
class MoneyText extends StatelessWidget {
  const MoneyText(
    this.amount, {
    super.key,
    this.style,
    this.color,
    this.withPrefix = true,
  });

  /// Nominal besar — total akhir, tagihan.
  const MoneyText.large(this.amount, {super.key, this.color, this.withPrefix = true})
      : style = AppTypography.moneyLg;

  /// Nominal kecil — baris item.
  const MoneyText.small(this.amount, {super.key, this.color, this.withPrefix = true})
      : style = AppTypography.moneySm;

  final int amount;
  final TextStyle? style;
  final Color? color;
  final bool withPrefix;

  @override
  Widget build(BuildContext context) {
    final base = style ?? AppTypography.money;
    return Text(
      formatRupiah(amount, withPrefix: withPrefix),
      style: base.copyWith(color: color ?? base.color ?? AppColors.onSurface),
    );
  }
}

/// Baris "label ........ nominal" untuk Open Tab dan ringkasan tagihan.
class MoneyRow extends StatelessWidget {
  const MoneyRow({
    super.key,
    required this.label,
    required this.amount,
    this.emphasize = false,
    this.amountColor,
    this.sublabel,
  });

  final String label;
  final int amount;
  final bool emphasize;
  final Color? amountColor;
  final String? sublabel;

  @override
  Widget build(BuildContext context) {
    final labelStyle = emphasize
        ? AppTypography.bodyLg.copyWith(color: AppColors.onSurface)
        : AppTypography.bodyMd.copyWith(color: AppColors.onSurfaceVariant);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: labelStyle),
                if (sublabel != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text(
                      sublabel!,
                      style: AppTypography.bodySm
                          .copyWith(color: AppColors.onSurfaceVariant),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          emphasize
              ? MoneyText.large(amount, color: amountColor)
              : MoneyText(amount, color: amountColor),
        ],
      ),
    );
  }
}
