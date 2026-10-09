<?php

namespace Database\Seeders;

use App\Enums\StationStatus;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Package;
use App\Models\Station;
use App\Models\StationType;
use Illuminate\Database\Seeder;

/**
 * Tipe konsol, ST01–ST06, paket per tipe konsol, dan satu member contoh.
 *
 * ANGKA HARGA DI SINI DATA TEST, bukan tarif Amor Gaming Space. Tarif
 * sebenarnya diatur owner dari aplikasi kasir (DEC-019/020).
 */
/*
 * TARIF ASLI — dari catatan user, dikonfirmasi 10 Okt 2026 (DEC-036).
 *
 * Yang SUDAH pasti: nama tipe konsol (PS3, PS4), tarif per jam, dan paket
 * "3 jam gratis 1 jam".
 *
 * Yang BELUM masuk dan sengaja dikosongkan:
 *
 * - Paket "free 2 minuman" (PS3 40.000, PS4 50.000). Durasinya belum diketahui,
 *   dan sistem belum bisa membundel F&B ke dalam paket (DEC-037, belum dibuat).
 * - Paket 3 jam PS3 20.000 / PS4 25.000. Itu harga JAM SEPI, dan harga
 *   berdasarkan waktu belum ada (OD-025).
 * - Pembagian station per tipe (ST01-03 PS3, ST04-06 PS4) masih ASUMSI —
 *   catatan user tidak menyebutkan berapa unit masing-masing.
 *
 * Yang mengubah tarif nantinya adalah owner lewat aplikasi (DEC-019/020),
 * bukan seeder ini.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * Dua tipe konsol supaya perbedaan tarif per tipe (DEC-019) benar-benar
         * teruji — kalau cuma satu tipe, bug pemilihan tarif tidak akan terlihat.
         * Sekaligus menyiapkan uji DEC-021: swap antar tipe harus ditolak.
         */
        $types = [
            'PS4' => [
                'sort_order' => 1,
                'packages' => [
                    // Tarif per jam. Dipakai juga sebagai sumber tarif untuk
                    // Postpaid, yang tidak punya durasi (DEC-034).
                    ['name' => '1 Jam', 'duration_minutes' => 60, 'price' => 10000],
                    /*
                     * "3 jam gratis 1 jam" — durasinya 4 jam penuh, jadi
                     * disimpan 240 menit. Konsekuensinya tarif per jam paket
                     * ini 8.750, di bawah tarif normal 10.000, dan harga
                     * extend ikut memakai angka itu (DEC-036).
                     */
                    ['name' => '3 Jam + 1 Jam Gratis', 'duration_minutes' => 240, 'price' => 35000],
                ],
            ],
            'PS3' => [
                'sort_order' => 2,
                'packages' => [
                    ['name' => '1 Jam', 'duration_minutes' => 60, 'price' => 8000],
                    // Tarif per jam paket ini 7.500, di bawah normal 8.000.
                    ['name' => '3 Jam + 1 Jam Gratis', 'duration_minutes' => 240, 'price' => 30000],
                ],
            ],
        ];

        $typeModels = [];

        foreach ($types as $name => $config) {
            $type = StationType::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => $config['sort_order'], 'is_active' => true],
            );

            $typeModels[$name] = $type;

            foreach ($config['packages'] as $i => $package) {
                Package::query()->updateOrCreate(
                    ['station_type_id' => $type->id, 'name' => $package['name']],
                    $package + ['is_active' => true, 'sort_order' => $i + 1],
                );
            }
        }

        // PRD §10: ST01–ST06 pada deployment awal, dapat ditambah.
        // Pembagian ini masih ASUMSI — catatan user tidak menyebutkan berapa
        // unit PS3 dan berapa PS4 yang sebenarnya ada di lokasi.
        $stations = [
            'ST01' => 'PS4',
            'ST02' => 'PS4',
            'ST03' => 'PS4',
            'ST04' => 'PS3',
            'ST05' => 'PS3',
            'ST06' => 'PS3',
        ];

        $i = 0;
        foreach ($stations as $code => $typeName) {
            Station::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => 'Station '.ltrim(substr($code, 2), '0'),
                    'station_type_id' => $typeModels[$typeName]->id,
                    'status' => StationStatus::ACTIVE,
                    'sort_order' => ++$i,
                ],
            );
        }

        /*
         * Satu member contoh: layar Start Session punya jalur "pilih member"
         * dan jalur "walk-in". Tanpa satu member pun, jalur pertama tidak bisa
         * dicoba sama sekali.
         */
        $customer = Customer::query()->updateOrCreate(
            ['phone' => '081200000001'],
            ['name' => 'Budi Member'],
        );

        Membership::query()->updateOrCreate(
            ['customer_id' => $customer->id],
            ['tier' => 'SILVER', 'is_active' => true, 'joined_at' => now()],
        );
    }
}
