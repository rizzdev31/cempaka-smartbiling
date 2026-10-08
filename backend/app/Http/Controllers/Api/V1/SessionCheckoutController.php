<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CheckoutSessionRequest;
use App\Models\BillingSession;
use App\Services\CheckoutService;
use App\Support\Api\ApiResponse;
use App\Support\Presenters\SessionPresenter;
use Illuminate\Http\JsonResponse;

/** `POST /sessions/{id}/checkout` — API.md §7, PRD §12, T13. */
class SessionCheckoutController
{
    public function __construct(private readonly CheckoutService $checkout) {}

    public function store(CheckoutSessionRequest $request, BillingSession $session): JsonResponse
    {
        $result = $this->checkout->checkout(
            $session,
            $request->validated('payments'),
            (bool) $request->validated('use_credit', false),
            $request->user(),
        );

        return ApiResponse::data([
            'session' => SessionPresenter::one($result['session']),
            'receipt' => $result['receipt'],
        ]);
    }
}
