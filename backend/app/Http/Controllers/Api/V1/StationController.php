<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreStationRequest;
use App\Http\Requests\Api\V1\UpdateStationRequest;
use App\Models\BillingSession;
use App\Models\Device;
use App\Models\Station;
use App\Services\MasterDataService;
use App\Support\Api\ApiResponse;
use App\Support\Presenters\StationPresenter;
use Illuminate\Http\JsonResponse;

/** `GET /stations` — API.md §6. Sumber data dashboard operator. */
class StationController
{
    public function __construct(private readonly MasterDataService $masterData) {}

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

    /**
     * `POST /stations` — DEC-048. Owner saja (ditegakkan Service).
     *
     * Station baru lahir dengan `enrollment_code` sendiri; kode itulah yang
     * dibacakan ke TV supaya ia bisa mendaftar. Tidak ada endpoint untuk
     * membuat kode terpisah — satu station, satu kode.
     */
    public function store(StoreStationRequest $request): JsonResponse
    {
        $station = $this->masterData->createStation($request->validated(), $request->user());

        return ApiResponse::data(StationPresenter::one($station, null), 201);
    }

    /**
     * `PATCH /stations/{station}` — DEC-048.
     *
     * `status` dan `name` boleh diubah admin; `code` dan `station_type_id`
     * hanya owner. Pembagiannya ada di `MasterDataService`, bukan di route —
     * "PS3 nomor 4 rusak" adalah kejadian harian yang tidak boleh menunggu
     * owner.
     */
    public function update(UpdateStationRequest $request, Station $station): JsonResponse
    {
        $station = $this->masterData->updateStation($station, $request->validated(), $request->user());

        return ApiResponse::data(StationPresenter::one($station, $station->currentSession()));
    }
}
