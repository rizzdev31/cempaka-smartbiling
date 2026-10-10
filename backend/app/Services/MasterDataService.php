<?php

namespace App\Services;

use App\Events\MasterDataUpdated;
use App\Exceptions\ApiException;
use App\Models\FnbProduct;
use App\Models\Package;
use App\Models\StationType;
use App\Models\User;
use App\Support\Api\ErrorCode;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Perubahan master data — tarif, paket, menu. DEC-019, DEC-020, DEC-040.
 *
 * ## Harga sesi yang sedang berjalan TIDAK ikut berubah
 *
 * Ini yang paling mudah disalahpahami. Saat sesi dibuat, harga paket dan
 * tarif per jamnya **dibekukan** ke baris sesi itu (`package_price`,
 * `hourly_rate`). Menaikkan harga di tengah hari tidak mengubah tagihan
 * siapa pun yang sudah bermain — termasuk harga extend-nya, karena rumus
 * DEC-007 memakai `hourly_rate` yang dibekukan.
 *
 * Itu disengaja: customer sudah disebutkan harganya di depan, dan harga itu
 * tidak boleh bergerak setelah dia duduk.
 *
 * ## Setiap perubahan diberitahukan ke semua tablet
 *
 * Tanpa itu, owner yang menaikkan harga di satu tablet tidak punya cara
 * memberi tahu tablet lain — layar Start Session akan terus menampilkan harga
 * lama sampai seseorang menutup dan membukanya kembali.
 */
class MasterDataService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function createPackage(array $input, User $actor): Package
    {
        $tipe = StationType::query()->find($input['station_type_id']);

        if ($tipe === null) {
            throw ApiException::unprocessable(
                ErrorCode::VALIDATION_FAILED,
                'Tipe konsol tidak ditemukan.',
                ['station_type_id' => 'Tipe konsol tidak dikenal.'],
            );
        }

        $package = DB::transaction(function () use ($input, $actor) {
            $package = Package::query()->create($input + ['is_active' => true]);

            $this->audit->forUser($actor, AuditAction::PACKAGE_CREATED, [
                'subject_type' => 'package',
                'subject_id' => $package->id,
                'after' => $this->snapshotPackage($package),
            ]);

            return $package;
        });

        MasterDataUpdated::dispatch('package', 'created', $package->id);

        return $package->fresh('stationType');
    }

    public function updatePackage(Package $package, array $input, User $actor): Package
    {
        $package = DB::transaction(function () use ($package, $input, $actor) {
            $sebelum = $this->snapshotPackage($package);

            $package->fill($input)->save();

            /*
             * Nilai sebelum DAN sesudah disimpan. Pertanyaan "kenapa tagihan
             * hari Senin beda dengan hari ini" hanya bisa dijawab kalau harga
             * lamanya tercatat — PRD §24.
             */
            $this->audit->forUser($actor, AuditAction::PACKAGE_UPDATED, [
                'subject_type' => 'package',
                'subject_id' => $package->id,
                'before' => $sebelum,
                'after' => $this->snapshotPackage($package->fresh()),
            ]);

            return $package;
        });

        MasterDataUpdated::dispatch('package', 'updated', $package->id);

        return $package->fresh('stationType');
    }

    /**
     * Menu F&B: harga, ketersediaan, stok.
     *
     * Izinnya dibedakan per field. Mengubah **harga** butuh `pricing.manage`
     * (owner saja, DEC-020); menandai menu habis cukup `fnb.manage`.
     *
     * Dipisah begini karena "Mie Goreng habis" adalah kejadian harian yang
     * harus bisa ditangani operator sendiri — kalau seluruh endpoint
     * dikunci untuk owner, operator menunggu owner hanya untuk mematikan satu
     * menu, dan di lapangan itu berarti menu habis tetap muncul di tablet.
     */
    public function updateFnbProduct(FnbProduct $product, array $input, User $actor): FnbProduct
    {
        if (array_key_exists('price', $input) && ! $actor->role->canManagePricing()) {
            throw ApiException::forbidden('Hanya owner yang boleh mengubah harga.');
        }

        $product = DB::transaction(function () use ($product, $input, $actor) {
            $sebelum = $this->snapshotProduct($product);

            $product->fill($input)->save();

            $this->audit->forUser($actor, AuditAction::FNB_PRODUCT_UPDATED, [
                'subject_type' => 'fnb_product',
                'subject_id' => $product->id,
                'before' => $sebelum,
                'after' => $this->snapshotProduct($product->fresh()),
            ]);

            return $product;
        });

        MasterDataUpdated::dispatch('fnb_product', 'updated', $product->id);

        return $product->fresh();
    }

    private function snapshotPackage(Package $package): array
    {
        return [
            'name' => $package->name,
            'duration_minutes' => (int) $package->duration_minutes,
            'price' => (int) $package->price,
            'is_active' => (bool) $package->is_active,
        ];
    }

    private function snapshotProduct(FnbProduct $product): array
    {
        return [
            'name' => $product->name,
            'price' => (int) $product->price,
            'stock' => $product->stock === null ? null : (int) $product->stock,
            'is_available' => (bool) $product->is_available,
        ];
    }
}
