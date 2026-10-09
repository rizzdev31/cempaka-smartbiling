<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Package;
use App\Models\Station;
use App\Support\Api\ApiResponse;
use App\Support\Presenters\PackagePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** `GET /packages` — API.md §6, DEC-019. */
class PackageController
{
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
}
