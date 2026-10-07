<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seed Tahap 0.
 *
 * DEC-002 batasan 2: database dibangun hanya lewat migration + seeder, supaya
 * pindah ke VPS nanti = `migrate --seed`, bukan export dump.
 *
 * DEC-002 batasan 4: isinya DATA TEST. Jangan dipakai untuk transaksi uang
 * nyata sebelum backup/restore terbukti (PRD §26).
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            MasterDataSeeder::class,
            FnbSeeder::class,
        ]);
    }
}
