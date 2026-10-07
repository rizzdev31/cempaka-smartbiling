<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Baris audit — PRD §24. Hanya ditulis, tidak pernah diubah:
 * audit yang bisa diedit tidak berguna sebagai bukti.
 */
#[Fillable([
    'actor_type', 'actor_id', 'actor_name', 'actor_role',
    'action', 'subject_type', 'subject_id',
    'before', 'after', 'ip_address', 'user_agent', 'created_at',
])]
class AuditLog extends Model
{
    use HasUuidKey;

    /** Tabelnya tidak punya `updated_at`. */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
