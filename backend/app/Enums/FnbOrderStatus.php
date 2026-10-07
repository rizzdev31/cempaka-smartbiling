<?php

namespace App\Enums;

/** API.md §8. Transisi sah: PENDING -> PROCESSING -> READY -> DELIVERED. */
enum FnbOrderStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case READY = 'READY';
    case DELIVERED = 'DELIVERED';
    case CANCELLED = 'CANCELLED';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::PENDING => [self::PROCESSING, self::CANCELLED],
            self::PROCESSING => [self::READY, self::CANCELLED],
            self::READY => [self::DELIVERED],
            self::DELIVERED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }
}
