<?php

namespace App\Support\Auth;

use App\Models\User;

final readonly class CredentialResult
{
    public function __construct(
        public CredentialOutcome $outcome,
        /**
         * Terisi hanya kalau usernya memang ada — termasuk saat password
         * salah. JANGAN dipakai untuk menyimpulkan login berhasil; pakai
         * [$this->ok()].
         */
        public ?User $user = null,
    ) {}

    public function ok(): bool
    {
        return $this->outcome === CredentialOutcome::OK;
    }
}
