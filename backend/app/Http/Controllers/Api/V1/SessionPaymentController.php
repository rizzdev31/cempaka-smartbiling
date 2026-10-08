<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StorePaymentRequest;
use App\Models\BillingSession;
use App\Services\PaymentService;
use App\Support\Api\ApiResponse;
use App\Support\Presenters\SessionPresenter;
use Illuminate\Http\JsonResponse;

/** `POST /sessions/{id}/payments` — API.md §7, PRD §21. */
class SessionPaymentController
{
    public function __construct(private readonly PaymentService $payments) {}

    public function store(StorePaymentRequest $request, BillingSession $session): JsonResponse
    {
        $payment = $this->payments->record($session, $request->validated(), $request->user());

        /*
         * Session ikut dikirim balik, bukan hanya payment-nya. Pembayaran
         * pertama Prepaid mengubah status, `started_at`, dan `end_at`
         * sekaligus — tanpa session di response, tablet harus menembak
         * GET susulan dan timer-nya telat mulai.
         */
        return ApiResponse::data([
            'payment' => SessionPresenter::payment($payment),
            'session' => SessionPresenter::one($session->fresh(['station', 'customer', 'items', 'payments'])),
        ], 201);
    }
}
