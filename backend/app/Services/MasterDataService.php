<?php

namespace App\Services;

use App\Enums\StationStatus;
use App\Events\MasterDataUpdated;
use App\Exceptions\ApiException;
use App\Models\FnbProduct;
use App\Models\Package;
use App\Models\Station;
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
    /**
     * Produk F&B baru.
     *
     * Butuh owner, bukan `fnb.manage`. Membuat produk berarti MENETAPKAN
     * harganya, dan DEC-020 menyerahkan setiap angka harga ke owner —
     * membolehkan admin membuat produk baru akan membuka jalan memutar yang
     * persis sama dengan mengubah harga produk lama.
     */
    public function createFnbProduct(array $input, User $actor): FnbProduct
    {
        if (! $actor->role->canManagePricing()) {
            throw ApiException::forbidden('Hanya owner yang boleh menambah produk — menambah berarti menetapkan harga.');
        }

        $product = DB::transaction(function () use ($input, $actor) {
            $product = FnbProduct::query()->create($input);

            $this->audit->forUser($actor, AuditAction::FNB_PRODUCT_CREATED, [
                'subject_type' => 'fnb_product',
                'subject_id' => $product->id,
                'after' => $this->snapshotProduct($product),
            ]);

            return $product;
        });

        MasterDataUpdated::dispatch('fnb_product', 'created', $product->id);

        return $product;
    }

    public function updateFnbProduct(FnbProduct $product, array $input, User $actor): FnbProduct
    {
        /*
         * `hpp` ikut dikunci untuk owner, sama seperti `price`.
         *
         * Ia bukan harga jual, tapi ia menentukan margin di setiap laporan
         * laba — dan angka yang mengubah laporan laba adalah angka yang
         * paling menggoda untuk dirapikan. Dikunci dulu sampai user
         * memutuskan sendiri (OD-033); melonggarkan nanti jauh lebih murah
         * daripada menarik kembali akses yang terlanjur diberikan.
         */
        foreach (['price', 'hpp'] as $field) {
            if (array_key_exists($field, $input) && ! $actor->role->canManagePricing()) {
                throw ApiException::forbidden(
                    $field === 'price'
                        ? 'Hanya owner yang boleh mengubah harga.'
                        : 'Hanya owner yang boleh mengubah HPP.',
                );
            }
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

    /*
     |--------------------------------------------------------------------
     | Station & tipe konsol — DEC-048 (menutup OD-028)
     |--------------------------------------------------------------------
     |
     | Yang membedakan bagian ini dari paket dan menu: station bukan angka,
     | ia barang fisik yang sedang dipakai orang. Hampir semua aturan di
     | bawah ada karena mengubah master data station saat ada yang sedang
     | bermain merusak sesuatu yang tidak menimbulkan error.
     */

    public function createStation(array $input, User $actor): Station
    {
        /*
         * Membuat station = OWNER.
         *
         * Bukan karena harganya — station tidak punya harga. Karena station
         * baru lahir dengan `enrollment_code`, dan kode itulah yang
         * membuat sebuah TV bisa bergabung ke sistem. Siapa pun yang bisa
         * membuat station bisa membuat kode pendaftaran sebanyak yang dia
         * mau.
         */
        if (! $actor->role->canManagePricing()) {
            throw ApiException::forbidden('Hanya owner yang boleh menambah station.');
        }

        $this->pastikanTipeAda($input['station_type_id'] ?? null);

        $station = DB::transaction(function () use ($input, $actor) {
            $station = Station::query()->create($input + [
                'status' => StationStatus::ACTIVE->value,
                'enrollment_code' => Station::generateEnrollmentCode(),
            ]);

            $this->audit->forUser($actor, AuditAction::STATION_CREATED, [
                'subject_type' => 'station',
                'subject_id' => $station->id,
                'after' => $this->snapshotStation($station),
            ]);

            return $station;
        });

        MasterDataUpdated::dispatch('station', 'created', $station->id);

        return $station->fresh('stationType');
    }

    public function updateStation(Station $station, array $input, User $actor): Station
    {
        /*
         * Dua kelompok field dengan aturan berbeda.
         *
         * `status`, `name`, `sort_order` → admin boleh. "PS3 nomor 4 rusak"
         * kejadian harian, dan menguncinya untuk owner berarti station rusak
         * tetap ditawarkan ke customer sampai owner sempat membukanya.
         *
         * `code`, `station_type_id` → owner saja. Keduanya identitas unit
         * yang membawa tarif, bukan keadaannya hari ini.
         */
        foreach (['code', 'station_type_id'] as $field) {
            if (array_key_exists($field, $input)
                && (string) $input[$field] !== (string) $station->getAttribute($field)
                && ! $actor->role->canManagePricing()) {
                throw ApiException::forbidden(
                    $field === 'code'
                        ? 'Hanya owner yang boleh mengubah kode station.'
                        : 'Hanya owner yang boleh mengubah tipe konsol station.',
                );
            }
        }

        $this->pastikanTipeAda($input['station_type_id'] ?? null);

        $sesiBerjalan = $station->currentSession();

        /*
         * Kode station adalah nama channel realtime (`private-station.{code}`)
         * dan satu-satunya hal yang menghubungkan TV terdaftar dengan
         * station-nya. TV yang sudah berlangganan channel lama tidak akan
         * error saat kodenya berubah — ia hanya diam selamanya, sampai ada
         * yang menyadari layarnya tidak pernah berganti.
         */
        if (array_key_exists('code', $input)
            && $input['code'] !== $station->code
            && $station->device()->exists()) {
            throw ApiException::conflict(
                ErrorCode::STATION_HAS_DEVICE,
                'Kode station tidak bisa diubah selama TV-nya masih terdaftar — TV akan berhenti menerima perintah tanpa pesan error apa pun.',
            );
        }

        /*
         * Tipe konsol menentukan paket mana yang sah untuk station ini
         * (DEC-019) dan ke mana sesi boleh dipindahkan (DEC-021). Mengubahnya
         * saat ada yang bermain membuat sesi berjalan menunjuk ke tipe yang
         * bukan dasar harganya.
         */
        if (array_key_exists('station_type_id', $input)
            && (string) $input['station_type_id'] !== (string) $station->station_type_id
            && $sesiBerjalan !== null) {
            throw ApiException::conflict(
                ErrorCode::STATION_HAS_ACTIVE_SESSION,
                'Tipe konsol tidak bisa diubah selama masih ada sesi berjalan di station ini.',
            );
        }

        $station = DB::transaction(function () use ($station, $input, $actor) {
            $sebelum = $this->snapshotStation($station);

            $station->fill($input)->save();

            $this->audit->forUser($actor, AuditAction::STATION_UPDATED, [
                'subject_type' => 'station',
                'subject_id' => $station->id,
                'before' => $sebelum,
                'after' => $this->snapshotStation($station->fresh()),
            ]);

            return $station;
        });

        MasterDataUpdated::dispatch('station', 'updated', $station->id);

        return $station->fresh('stationType');
    }

    public function createStationType(array $input, User $actor): StationType
    {
        // Tipe konsol adalah PEMBAWA TARIF (DEC-019): paket menempel ke sini.
        // Membuatnya berarti membuka tempat untuk daftar harga baru.
        if (! $actor->role->canManagePricing()) {
            throw ApiException::forbidden('Hanya owner yang boleh menambah tipe konsol — tipe adalah pembawa tarif.');
        }

        $type = DB::transaction(function () use ($input, $actor) {
            $type = StationType::query()->create($input + ['is_active' => true]);

            $this->audit->forUser($actor, AuditAction::STATION_TYPE_CREATED, [
                'subject_type' => 'station_type',
                'subject_id' => $type->id,
                'after' => $this->snapshotStationType($type),
            ]);

            return $type;
        });

        MasterDataUpdated::dispatch('station_type', 'created', $type->id);

        return $type;
    }

    public function updateStationType(StationType $type, array $input, User $actor): StationType
    {
        if (! $actor->role->canManagePricing()) {
            throw ApiException::forbidden('Hanya owner yang boleh mengubah tipe konsol.');
        }

        /*
         * Menonaktifkan tipe yang masih dipakai station aktif akan
         * menyembunyikan seluruh paketnya dari layar Start Session —
         * station-nya tetap terlihat kosong dan bisa diklik, lalu tidak ada
         * satu pun paket yang bisa dipilih. Kasir akan mengira sistemnya
         * rusak.
         */
        $menonaktifkan = array_key_exists('is_active', $input) && ! $input['is_active'] && $type->is_active;

        if ($menonaktifkan) {
            $terpakai = $type->stations()->where('status', StationStatus::ACTIVE->value)->count();

            if ($terpakai > 0) {
                throw ApiException::conflict(
                    ErrorCode::STATION_NOT_AVAILABLE,
                    "Tipe ini masih dipakai {$terpakai} station aktif. Pindahkan atau nonaktifkan station-nya dulu.",
                );
            }
        }

        $type = DB::transaction(function () use ($type, $input, $actor) {
            $sebelum = $this->snapshotStationType($type);

            $type->fill($input)->save();

            $this->audit->forUser($actor, AuditAction::STATION_TYPE_UPDATED, [
                'subject_type' => 'station_type',
                'subject_id' => $type->id,
                'before' => $sebelum,
                'after' => $this->snapshotStationType($type->fresh()),
            ]);

            return $type;
        });

        MasterDataUpdated::dispatch('station_type', 'updated', $type->id);

        return $type->fresh();
    }

    /**
     * Tipe konsol boleh kosong (station baru dipasang, konsolnya belum
     * datang), tapi kalau diisi harus ada. ID yang tidak dikenal akan
     * tersimpan sebagai relasi menggantung tanpa error apa pun.
     */
    private function pastikanTipeAda(?string $id): void
    {
        if ($id === null || $id === '') {
            return;
        }

        if (! StationType::query()->whereKey($id)->exists()) {
            throw ApiException::unprocessable(
                ErrorCode::VALIDATION_FAILED,
                'Tipe konsol tidak ditemukan.',
                ['station_type_id' => 'Tipe konsol tidak dikenal.'],
            );
        }
    }

    private function snapshotStation(Station $station): array
    {
        return [
            'code' => $station->code,
            'name' => $station->name,
            'station_type_id' => $station->station_type_id,
            'status' => $station->status instanceof StationStatus
                ? $station->status->value
                : (string) $station->status,
            'sort_order' => (int) $station->sort_order,
        ];
    }

    private function snapshotStationType(StationType $type): array
    {
        return [
            'name' => $type->name,
            'sort_order' => (int) $type->sort_order,
            'is_active' => (bool) $type->is_active,
        ];
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
            'hpp' => $product->hpp === null ? null : (int) $product->hpp,
            'stock' => $product->stock === null ? null : (int) $product->stock,
            'is_available' => (bool) $product->is_available,
        ];
    }
}
