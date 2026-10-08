<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ExtendSessionRequest;
use App\Models\BillingSession;
use App\Services\ExtendService;
use App\Support\Api\ApiResponse;
use App\Support\Presenters\SessionPresenter;
use Illuminate\Http\JsonResponse;

/** `POST /sessions/{id}/extend` — API.md §7, DEC-007. */
class SessionExtendController
{
    public function __construct(private readonly ExtendService $extend) {}

    public function store(ExtendSessionRequest $request, BillingSession $session): JsonResponse
    {
        $result = $this->extend->extend(
            $session,
            (int) $request->integer('duration_minutes'),
            $request->user(),
        );

        return ApiResponse::data([
            'session' => SessionPresenter::one($result['session']),
            'extend' => [
                'duration_minutes' => $result['duration_minutes'],
                'price' => $result['price'],
                'previous_end_at' => $result['previous_end_at']->toIso8601ZuluString(),
                'new_end_at' => $result['new_end_at']->toIso8601ZuluString(),
            ],
        ]);
    }
}
