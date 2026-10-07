<?php

namespace App\Support\Presenters;

use App\Models\Shift;
use App\Models\User;

/**
 * Bentuk objek `user` dan `active_shift` (API.md §4).
 *
 * Dikumpulkan di satu kelas supaya bentuknya identik di `/auth/login` dan
 * `/auth/me` — client membandingkan keduanya saat reconnect (API.md §12).
 */
class UserPresenter
{
    public static function one(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role->value,
            'permissions' => $user->role->permissionValues(),
        ];
    }

    /** Dipakai sebagai referensi aktor di item, payment, dan audit. */
    public static function actor(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name];
    }

    public static function activeShift(?Shift $shift): ?array
    {
        return $shift === null ? null : [
            'id' => $shift->id,
            'opened_at' => $shift->opened_at?->toIso8601ZuluString(),
        ];
    }
}
