<?php

namespace App\Enums;

/**
 * Jenis baris saldo member — DEC-024, DEC-026.
 *
 * `EARNED` lahir dari sisa waktu Prepaid yang tidak terpakai saat checkout.
 * `USED` dipakai mengurangi tagihan Open Tab — rental, F&B, extend, apa pun
 * (DEC-026: saldo digabung dengan tagihan lainnya, bukan khusus rental).
 */
enum CustomerCreditType: string
{
    case EARNED = 'EARNED';
    case USED = 'USED';

    /** USED selalu disimpan negatif, supaya saldo = SUM(amount) tanpa cabang. */
    public function isNegative(): bool
    {
        return $this === self::USED;
    }
}
