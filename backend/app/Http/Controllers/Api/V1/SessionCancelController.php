<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CancelSessionRequest;
use App\Models\BillingSession;
use App\Services\CancelService;
use App\Support\Api\ApiResponse;
use App\Support\Presenters\SessionPresenter;
use Illuminate\Http\JsonResponse;

/** `POST /sessions/{id}/cancel` — API.md §7. */
class SessionCancelController
{
    public function __construct(private readonly CancelService $cancel) {}

    public function store(CancelSessionRequest $request, BillingSession $session): JsonResponse
    {
        return ApiResponse::data(SessionPresenter::one(
            $this->cancel->cancel($session, $request->validated('reason'), $request->user()),
        ));
    }
}
