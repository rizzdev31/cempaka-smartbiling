<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Penulis audit trail — PRD §24.
 *
 * Wajib untuk login, payment, extend, swap, discount, adjustment, dan
 * perubahan master data (termasuk tarif, DEC-019/020).
 *
 * Nama dan role aktor dibekukan di baris audit: kalau hanya menyimpan
 * `actor_id`, mengganti nama user akan mengubah isi audit lama.
 */
class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    public function forUser(?User $user, string $action, array $attributes = []): AuditLog
    {
        return $this->write([
            'actor_type' => 'USER',
            'actor_id' => $user?->id,
            'actor_name' => $user?->name,
            'actor_role' => $user?->role->value,
            'action' => $action,
        ] + $attributes);
    }

    /**
     * Aktor yang tidak dikenali — mis. percobaan login dengan username yang
     * tidak ada. Tetap dicatat: percobaan login gagal berulang adalah
     * informasi keamanan, dan kehilangannya berarti tidak bisa menjelaskan
     * apa pun setelah kejadian.
     */
    public function forAnonymous(string $action, array $attributes = []): AuditLog
    {
        return $this->write([
            'actor_type' => 'USER',
            'actor_id' => null,
            'action' => $action,
        ] + $attributes);
    }

    public function forSystem(string $action, array $attributes = []): AuditLog
    {
        return $this->write([
            'actor_type' => 'SYSTEM',
            'actor_name' => 'system',
            'action' => $action,
        ] + $attributes);
    }

    private function write(array $attributes): AuditLog
    {
        return AuditLog::query()->create($attributes + [
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 255),
            'created_at' => Carbon::now(),
        ]);
    }
}
