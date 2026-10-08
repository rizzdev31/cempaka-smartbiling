<?php

namespace App\Support\Presenters;

use App\Models\Shift;

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
        ];
    }
}
