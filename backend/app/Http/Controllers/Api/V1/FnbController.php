<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FnbOrderStatus;
use App\Http\Requests\Api\V1\StoreFnbOrderRequest;
use App\Http\Requests\Api\V1\UpdateFnbStatusRequest;
use App\Models\BillingSession;
use App\Models\FnbOrder;
use App\Models\FnbProduct;
use App\Services\FnbService;
use App\Support\Api\ApiResponse;
use App\Support\Presenters\FnbPresenter;
use App\Support\Presenters\SessionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** F&B — API.md §8. */
class FnbController
{
    public function __construct(private readonly FnbService $fnb) {}

    public function products(): JsonResponse
    {
        $products = FnbProduct::query()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return ApiResponse::collection(
            $products->map(fn (FnbProduct $p) => FnbPresenter::product($p))->all(),
        );
    }

    /** Antrian F&B operator — `GET /fnb/orders?status=PENDING,PROCESSING`. */
    public function orders(Request $request): JsonResponse
    {
        $query = FnbOrder::query()->with(['items', 'session.station']);

        if ($request->filled('status')) {
            $statuses = collect(explode(',', (string) $request->string('status')))
                ->map(fn (string $v) => trim($v))
                ->filter(fn (string $v) => FnbOrderStatus::tryFrom($v) !== null)
                ->values();

            $query->whereIn('status', $statuses->all());
        }

        // Antrian dapur: yang paling lama menunggu harus di atas.
        $orders = $query->oldest('created_at')->limit(200)->get();

        return ApiResponse::collection(
            $orders->map(fn (FnbOrder $o) => FnbPresenter::order($o))->all(),
        );
    }

    public function store(StoreFnbOrderRequest $request, BillingSession $session): JsonResponse
    {
        $order = $this->fnb->createOrder(
            $session,
            $request->validated('items'),
            $request->validated('note'),
            $request->user(),
        );

        return ApiResponse::data([
            'order' => FnbPresenter::order($order),
            // Totals ikut dikirim: order F&B mengubah balance_due, dan tablet
            // menampilkannya di kartu station tanpa perlu GET susulan.
            'session' => SessionPresenter::one($session->fresh(['station', 'customer', 'items', 'payments'])),
        ], 201);
    }

    public function updateStatus(UpdateFnbStatusRequest $request, FnbOrder $order): JsonResponse
    {
        $updated = $this->fnb->updateStatus(
            $order,
            FnbOrderStatus::from($request->validated('status')),
            $request->user(),
        );

        return ApiResponse::data(['order' => FnbPresenter::order($updated)]);
    }
}
