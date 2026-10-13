<?php

namespace App\Http\Requests\Api\V1;

use App\Models\FnbProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /fnb/products` — menambah menu baru.
 *
 * Owner saja. Menambah produk berarti MENETAPKAN harganya, dan DEC-020
 * menyerahkan setiap angka harga ke owner — membolehkan admin menambah
 * produk akan membuka jalan memutar yang persis sama dengan mengubah harga
 * produk lama. Ditegakkan `MasterDataService::createFnbProduct()`.
 */
class StoreFnbProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /*
             * Kategori dibatasi pada yang SUDAH ADA di database, bukan teks
             * bebas dan bukan daftar yang ditulis di sini.
             *
             * "MINUMAN", "Minuman", dan "minuman " akan tampil sebagai tiga
             * kelompok berbeda di halaman menu, dan tidak ada yang menyadari
             * sampai daftarnya jadi panjang. Daftar yang ditulis di kode
             * punya masalah yang sama begitu ejaannya meleset satu huruf dari
             * isi database — itu nyaris terjadi di sini.
             *
             * Konsekuensinya kategori baru tidak bisa dibuat lewat form.
             * Itu disengaja: menambah kategori adalah keputusan tata menu,
             * bukan kejadian harian, dan untuk sekarang lewat seeder.
             */
            'category' => ['required', 'string', 'max:32', Rule::in(self::kategori())],
            'name' => ['required', 'string', 'max:255', 'unique:fnb_products,name'],
            'price' => ['required', 'integer', 'min:0'],
            // HPP opsional: harga modal sering baru diketahui setelah belanja
            // pertama, dan memaksanya membuat orang mengisi angka karangan.
            'hpp' => ['nullable', 'integer', 'min:0'],
            // null = stok tidak dilacak (API.md §8). Berbeda dari 0 = habis.
            'stock' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Kategori yang sudah dipakai, dibaca dari database.
     *
     * Dipakai juga oleh form di Admin Web supaya pilihannya persis sama
     * dengan yang diterima validasi — dropdown dan validator yang punya
     * daftar sendiri-sendiri akan berbeda pada perubahan pertama.
     *
     * @return list<string>
     */
    public static function kategori(): array
    {
        $dariDb = FnbProduct::query()
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();

        // Database kosong (instalasi baru, sebelum seeder): daftar awal ini
        // hanya dipakai sampai produk pertama ada.
        return $dariDb !== [] ? $dariDb : ['Makanan', 'Minuman', 'Snack'];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Sudah ada menu dengan nama ini.',
            'category.in' => 'Kategori harus salah satu dari: '.implode(', ', self::kategori()).'.',
            'price.integer' => 'Harga harus berupa angka rupiah bulat.',
        ];
    }
}
