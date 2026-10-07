<?php

namespace App\Enums;

/**
 * PRD §12. Prepaid = rental dibayar sebelum ACTIVE.
 * Postpaid = rental masuk Open Tab, dibayar saat checkout.
 */
enum SessionMode: string
{
    case PREPAID = 'PREPAID';
    case POSTPAID = 'POSTPAID';

    /**
     * Rounding durasi hanya berlaku Postpaid (DEC-009).
     * Prepaid memakai durasi paket apa adanya.
     */
    public function usesDurationRounding(): bool
    {
        return $this === self::POSTPAID;
    }
}
