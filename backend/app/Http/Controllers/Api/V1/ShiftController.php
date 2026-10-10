<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CloseShiftRequest;
use App\Http\Requests\Api\V1\OpenShiftRequest;
use App\Models\Shift;
use App\Services\ShiftService;
use App\Support\Api\ApiResponse;
use App\Support\Presenters\ShiftPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Shift kasir — API.md §10, PRD §20. */
class ShiftController
{
    public function __construct(private readonly ShiftService $shifts) {}

    public function open(OpenShiftRequest $request): JsonResponse
    {
        $shift = $this->shifts->open(
            $request->user(),
            (int) $request->validated('opening_cash'),
            $request->validated('note'),
        );

        return ApiResponse::data(
            ShiftPresenter::one($shift, $this->shifts->summary($shift)),
            201,
        );
    }

    public function close(CloseShiftRequest $request, Shift $shift): JsonResponse
    {
        // Ringkasan diambil SEBELUM ditutup supaya angkanya sama persis dengan
        // yang dipakai menghitung selisih kas di audit.
        $summary = $this->shifts->summary($shift);

        $closed = $this->shifts->close(
            $shift,
            (int) $request->validated('closing_cash'),
            $request->validated('note'),
            $request->user(),
        );

        return ApiResponse::data(ShiftPresenter::one($closed, $summary));
    }

    /**
     * `GET /shifts` — riwayat shift yang sudah ditutup, terbaru di atas.
     *
     * Dibutuhkan layar serah-terima: operator berikutnya perlu melihat kas
     * akhir shift sebelumnya sebelum menghitung modal awalnya sendiri.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 20), 1), 100);

        $shifts = Shift::query()
            ->with('operator')
            ->whereNotNull('closed_at')
            ->latest('closed_at')
            ->limit($limit)
            ->get();

        return ApiResponse::collection(
            $shifts->map(fn (Shift $shift) => ShiftPresenter::one($shift, $this->shifts->summary($shift)))->all(),
        );
    }

    /**
     * `GET /shifts/current` — dipakai saat app dibuka ulang, bersama
     * `GET /auth/me`, untuk tahu apakah kasir masih di tengah shift.
     */
    public function current(Request $request): JsonResponse
    {
        $shift = $request->user()->activeShift();

        if ($shift === null) {
            // null, bukan 404: "belum buka shift" adalah keadaan normal di awal
            // hari, bukan kesalahan yang perlu ditangani client sebagai error.
            return ApiResponse::data(null);
        }

        return ApiResponse::data(ShiftPresenter::one($shift, $this->shifts->summary($shift)));
    }
}
