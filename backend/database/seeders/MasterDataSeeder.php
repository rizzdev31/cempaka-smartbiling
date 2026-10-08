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
            'PS5 VIP' => [
                'sort_order' => 1,
                'packages' => [
                    ['name' => '1 Jam', 'duration_minutes' => 60, 'price' => 25000],
                    ['name' => '2 Jam', 'duration_minutes' => 120, 'price' => 45000],
                    ['name' => '3 Jam', 'duration_minutes' => 180, 'price' => 65000],
                ],
            ],
            'PS4 Slim' => [
                'sort_order' => 2,
                'packages' => [
                    ['name' => '1 Jam', 'duration_minutes' => 60, 'price' => 15000],
                    ['name' => '2 Jam', 'duration_minutes' => 120, 'price' => 28000],
                    ['name' => '3 Jam', 'duration_minutes' => 180, 'price' => 40000],
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
        $stations = [
            'ST01' => 'PS5 VIP',
            'ST02' => 'PS5 VIP',
            'ST03' => 'PS5 VIP',
            'ST04' => 'PS4 Slim',
            'ST05' => 'PS4 Slim',
            'ST06' => 'PS4 Slim',
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
