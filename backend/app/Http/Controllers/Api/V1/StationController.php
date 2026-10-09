<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\BillingSession;
use App\Models\Device;
use App\Models\Station;
use App\Support\Api\ApiResponse;
use App\Support\Presenters\StationPresenter;
use Illuminate\Http\JsonResponse;

/** `GET /stations` — API.md §6. Sumber data dashboard operator. */
class StationController
{
    public function index(): JsonResponse
    {
        /*
         * Sesi aktif, item, payment, dan customer di-eager-load sekaligus.
         * Tanpa ini, menggambar enam kartu station berarti enam query sesi
         * ditambah dua query per sesi untuk menghitung `balance_due` — dan
         * dashboard ini yang paling sering dipanggil ulang di tablet.
         */
        $stations = Station::query()
            ->with([
                'stationType',
                'device',
                'sessions' => fn ($query) => $query
                    ->whereIn('status', BillingSession::occupyingStatuses())
                    ->with(['items', 'payments', 'customer'])
                    ->latest('created_at'),
            ])
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return ApiResponse::collection(
            $stations->map(
                fn (Station $station) => StationPresenter::one($station, $station->sessions->first()),
            )->all(),
            /*
             * Ambang offline dikirim supaya Flutter tidak menuliskan angkanya
             * sendiri. Kalau nanti diubah di server, client ikut tanpa rilis
             * baru.
             */
            ['offline_threshold_seconds' => Device::OFFLINE_THRESHOLD_SECONDS],
        );
    }
}
