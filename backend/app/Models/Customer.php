<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'phone', 'note'])]
class Customer extends Model
{
    use HasUuidKey;

    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(BillingSession::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(CustomerCredit::class);
    }

    /**
     * Saldo berjalan dalam rupiah — DEC-026.
     *
     * Dijumlahkan dari ledger setiap kali dibutuhkan, bukan disimpan sebagai
     * kolom. Kolom saldo yang di-update terpisah bisa menyimpang dari riwayatnya
     * kalau ada satu jalur kode yang lupa memperbaruinya, dan selisih saldo
     * customer adalah hal yang paling sulit dijelaskan belakangan.
     */
    public function creditBalance(): int
    {
        return (int) $this->credits()->sum('amount');
    }

    /**
     * Hanya member yang boleh menyimpan sisa waktu (DEC-024).
     * Membership yang dinonaktifkan kehilangan hak itu, tapi saldo yang sudah
     * terkumpul tidak dihapus.
     */
    public function isActiveMember(): bool
    {
        return (bool) $this->membership?->is_active;
    }
}
