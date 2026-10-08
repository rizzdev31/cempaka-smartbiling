<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\SwapSessionRequest;
use App\Models\BillingSession;
use App\Services\SwapService;
use App\Support\Api\ApiResponse;
use App\Support\Presenters\SessionPresenter;
use Illuminate\Http\JsonResponse;

/** `POST /sessions/{id}/swap` — API.md §7, PRD §15, R07. */
class SessionSwapController
{
    public function __construct(private readonly SwapService $swap) {}

    public function store(SwapSessionRequest $request, BillingSession $session): JsonResponse
    {
        $swapped = $this->swap->swap(
            $session,
            $request->validated('target_station_id'),
            $request->validated('reason'),
            $request->user(),
        );

        return ApiResponse::data(SessionPresenter::one($swapped));
    }
}
