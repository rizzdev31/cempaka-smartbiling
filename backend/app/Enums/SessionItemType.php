<?php

namespace App\Enums;

/** API.md §7 objek `session_item`. */
enum SessionItemType: string
{
    case RENTAL = 'RENTAL';
    case FNB = 'FNB';
    case EXTEND = 'EXTEND';
    case DISCOUNT = 'DISCOUNT';
    case ADJUSTMENT = 'ADJUSTMENT';

    /** DISCOUNT bernilai negatif — karena itu kolom uang item bertipe signed. */
    public function isNegative(): bool
    {
        return $this === self::DISCOUNT;
    }
}
