<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StorePackageRequest;
use App\Http\Requests\Api\V1\UpdatePackageRequest;
use App\Models\Package;
use App\Models\Station;
use App\Services\MasterDataService;
use App\Support\Api\ApiResponse;
use App\Support\Presenters\PackagePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** `GET /packages` — API.md §6, DEC-019. */
class PackageController
{
    public function __construct(private readonly MasterDataService $masterData) {}

    /**
     * Filter `station_id` adalah cara yang dipakai layar Start Session:
     * operator sudah memilih station, lalu hanya paket yang sah untuk tipe
     * konsol station itu yang boleh muncul (DEC-019). Tanpa filter ini
     * operator bisa memilih paket PS4 untuk station PS5 dan baru ditolak
     * server setelah menekan tombol.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Package::query()->with('stationType');

        if ($request->filled('station_id')) {
            $stationTypeId = Station::query()
                ->whereKey($request->string('station_id')->toString())
                ->value('station_type_id');

            // Station tidak ketemu atau belum punya tipe konsol -> daftar
            // kosong, bukan seluruh paket. Daftar penuh di layar yang sudah
            // memilih station akan menyesatkan.
            $query->where('station_type_id', $stationTypeId);
        } elseif ($request->filled('station_type_id')) {
            $query->where('station_type_id', $request->string('station_type_id')->toString());
        }

        if ($request->boolean('only_active', true)) {
            $query->where('is_active', true);
        }

        $packages = $query
            ->orderBy('station_type_id')
            ->orderBy('sort_order')
            ->orderBy('duration_minutes')
            ->get();

        return ApiResponse::collection(
            $packages->map(fn (Package $package) => PackagePresenter::one($package))->all(),
        );
    }

    /**
     * `POST /packages` — owner saja (DEC-020).
     *
     * Paket baru langsung terlihat di semua tablet lewat event
     * `master.updated`; tidak perlu menutup dan membuka layar.
     */
    public function store(StorePackageRequest $request): JsonResponse
    {
        return ApiResponse::data(
            PackagePresenter::one($this->masterData->createPackage($request->validated(), $request->user())),
            201,
        );
    }

    /**
     * `PATCH /packages/{id}` — owner saja (DEC-020).
     *
     * Harga sesi yang SEDANG BERJALAN tidak ikut berubah: harganya dibekukan
     * ke baris sesi saat dibuat. Itu disengaja — customer sudah disebutkan
     * harganya di depan.
     */
    public function update(UpdatePackageRequest $request, Package $package): JsonResponse
    {
        return ApiResponse::data(PackagePresenter::one(
            $this->masterData->updatePackage($package, $request->validated(), $request->user()),
        ));
    }
}
