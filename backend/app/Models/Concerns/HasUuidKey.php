<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

/**
 * Primary key UUID v4 (API.md §1: "UUID v4 (string), bukan integer
 * auto-increment").
 *
 * Alasan kontrak memilih UUID: ID muncul di URL dan QR customer. Integer
 * berurutan membuat session/customer orang lain bisa ditebak hanya dengan
 * menaikkan angka.
 *
 * Memakai `HasUuids` bawaan Laravel, bukan hook `creating` sendiri: hook
 * event mati saat seeder memakai `WithoutModelEvents`, dan insert gagal
 * dengan "Field 'id' doesn't have a default value". Jalur `uniqueIds()`
 * tidak bergantung pada event.
 */
trait HasUuidKey
{
    use HasUuids;

    /**
     * `Str::uuid()` (v4 acak penuh), bukan `Str::orderedUuid()` bawaan.
     *
     * Ordered UUID menaruh timestamp di depan supaya index lebih rapat —
     * tapi itu membuat sebagian isi ID bisa diperkirakan. Untuk ID yang
     * muncul di QR customer, acak penuh lebih tepat. Jumlah barisnya kecil
     * (satu rental), jadi keuntungan locality-nya tidak sebanding.
     */
    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }
}
