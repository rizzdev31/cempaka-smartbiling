<?php

namespace App\Support\Presenters;

use App\Models\Shift;
use App\Services\ShiftService;

/** Objek `shift` — API.md §10. */
class ShiftPresenter
{
    public static function one(Shift $shift, array $summary): array
    {
        $shift->loadMissing('operator');

        return [
            'id' => $shift->id,
            'operator' => UserPresenter::actor($shift->operator),
            'opened_at' => $shift->opened_at?->toIso8601ZuluString(),
            'closed_at' => $shift->closed_at?->toIso8601ZuluString(),
            'opening_cash' => (int) $shift->opening_cash,
            'closing_cash' => $shift->closing_cash === null ? null : (int) $shift->closing_cash,
            'note' => $shift->note,
            'summary' => $summary,

            /*
             * Kas yang seharusnya ada di laci — DEC-046.
             *
             * Dikirim SERVER, tidak dihitung ulang client. Rumusnya
             * (modal + tunai − pengeluaran) sudah salah sekali karena
             * sukunya kurang; menaruhnya di tiga tempat berarti tiga
             * kesempatan untuk salah lagi.
             */
            'expected_cash' => ShiftService::expectedCash($shift, $summary),
        ];
    }
}
