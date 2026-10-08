<?php

namespace Database\Seeders;

use App\Models\FnbProduct;
use Illuminate\Database\Seeder;

/**
 * Menu F&B contoh. Harga dan HPP DATA TEST.
 *
 * Sengaja ada satu produk dengan `stock = null` (tidak dilacak) dan satu
 * yang `is_available = false`, supaya kedua cabang di API.md §8 ada datanya
 * untuk diuji.
 */
/*
 * PERINGATAN — ANGKA DI BAWAH ADALAH DATA UJI, BUKAN TARIF ASLI.
 *
 * Dikonfirmasi user 8 Okt 2026: "belum final, masih uji coba ini."
 * Nama tipe konsol, harga paket, dan pembagian station per tipe semuanya
 * karangan untuk keperluan pengujian.
 *
 * JANGAN dipakai untuk transaksi uang nyata sebelum diganti tarif sebenarnya
 * (DEC-002 butir 4). Yang mengubahnya nanti adalah owner lewat aplikasi
 * (DEC-019/020), bukan seeder ini.
 */
class FnbSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['category' => 'Minuman', 'name' => 'Teh Manis', 'price' => 5000, 'hpp' => 2000, 'stock' => 50],
            ['category' => 'Minuman', 'name' => 'Kopi Hitam', 'price' => 7000, 'hpp' => 3000, 'stock' => 40],
            ['category' => 'Minuman', 'name' => 'Air Mineral', 'price' => 4000, 'hpp' => 1500, 'stock' => null],
            ['category' => 'Makanan', 'name' => 'Mie Goreng', 'price' => 12000, 'hpp' => 6000, 'stock' => 25],
            ['category' => 'Makanan', 'name' => 'Nasi Goreng', 'price' => 15000, 'hpp' => 7500, 'stock' => 20],
            ['category' => 'Snack', 'name' => 'Kentang Goreng', 'price' => 10000, 'hpp' => 4000, 'stock' => 30],
            ['category' => 'Snack', 'name' => 'Roti Bakar', 'price' => 8000, 'hpp' => 3500, 'stock' => 0, 'is_available' => false],
        ];

        foreach ($products as $product) {
            FnbProduct::query()->updateOrCreate(
                ['name' => $product['name']],
                $product + ['is_available' => true],
            );
        }
    }
}
