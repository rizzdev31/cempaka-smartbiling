<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uang yang keluar dari laci saat shift berjalan — DEC-046.
 *
 * Es batu, galon, iuran sampah. Nilainya positif; arahnya sudah ditentukan
 * oleh tabelnya, bukan oleh tanda.
 */
#[Fillable(['shift_id', 'amount', 'category', 'note', 'actor_id'])]
class CashExpense extends Model
{
    use HasUuidKey;

    /**
     * Pilihan kategori untuk form.
     *
     * Disimpan sebagai teks bebas di database, tapi ditawarkan sebagai
     * daftar: tanpa daftar, "es batu", "Es Batu", dan "esbatu" jadi tiga
     * kelompok berbeda di laporan, dan tidak ada yang menyadarinya sampai
     * laporannya dibaca berbulan-bulan kemudian.
     *
     * "Lainnya" ada karena daftar yang tidak punya jalan keluar membuat
     * orang memilih kategori yang salah demi melewati form.
     *
     * @return list<string>
     */
    public static function kategori(): array
    {
        return ['Bahan F&B', 'Galon & air', 'Kebersihan', 'Perbaikan', 'Listrik & utilitas', 'Lainnya'];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
