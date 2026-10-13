<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `POST /station-types` — DEC-048. Owner saja (ditegakkan Service). */
class StoreStationTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /*
             * Nama bebas — "PS5 Pro", "VIP Room", "Simulator Balap". DEC-019
             * sengaja tidak memakai enum: tiap rental punya penamaan sendiri.
             *
             * Uniknya **tidak** memperhatikan besar-kecil huruf, karena
             * kolomnya `utf8mb4_unicode_ci` — "PS5" dan "ps5" ditolak sebagai
             * duplikat. Itu yang diinginkan: dua tipe yang namanya hanya beda
             * huruf besar akan memecah daftar harga jadi dua, dan tidak ada
             * yang menyadarinya sampai ada paket yang "hilang".
             *
             * Kalau kolomnya suatu saat dipindah ke collation `_bin` atau
             * `_cs`, perlindungan ini hilang tanpa test mana pun gagal.
             * `test_tipe_konsol_beda_huruf_besar_ditolak` ada untuk itu.
             */
            'name' => ['required', 'string', 'max:64', Rule::unique('station_types', 'name')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Tipe konsol dengan nama ini sudah ada.'];
    }
}
