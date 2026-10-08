<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SessionStatus;
use App\Http\Requests\Api\V1\StoreSessionRequest;
use App\Models\BillingSession;
use App\Services\SessionService;
use App\Support\Api\ApiResponse;
use App\Support\Presenters\SessionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Session — API.md §7.
 *
 * Controller sengaja tipis: seluruh aturan billing ada di `SessionService`
 * dan `app/Support/Billing`. PRD §8/§33 menetapkan Laravel sebagai source of
 * truth, jadi aturan yang tersebar di controller akan sulit diuji dan mudah
 * berbeda antar endpoint.
 */
class SessionController
{
    public function __construct(private readonly SessionService $sessions) {}

    /**
     * `GET /sessions?status=ACTIVE,WARNING&station_id=...`
     *
     * Dipakai Flutter untuk reconcile setelah reconnect (API.md §12) — karena
     * itu filternya menerima beberapa status sekaligus.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 25), 1), 100);

        $query = BillingSession::query()->with(['station', 'customer', 'items', 'payments']);

        if ($request->filled('status')) {
            $statuses = collect(explode(',', (string) $request->string('status')))
                ->map(fn (string $value) => trim($value))
                ->filter()
                // Status yang tidak dikenal dibuang, bukan membuat 422:
                // reconnect tidak boleh gagal total karena satu salah ketik.
                ->filter(fn (string $value) => SessionStatus::tryFrom($value) !== null)
                ->values();

            $query->whereIn('status', $statuses->all());
        }

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->string('station_id'));
        }

        $page = $query->latest('created_at')->paginate(
            perPage: $perPage,
            page: max((int) $request->integer('page', 1), 1),
        );

        return ApiResponse::collection(
            collect($page->items())->map(fn (BillingSession $s) => SessionPresenter::one($s))->all(),
            [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        );
    }

    public function show(BillingSession $session): JsonResponse
    {
        return ApiResponse::data(SessionPresenter::one($session));
    }

    public function store(StoreSessionRequest $request): JsonResponse
    {
        $session = $this->sessions->create($request->validated(), $request->user());

        return ApiResponse::data(SessionPresenter::one($session), 201);
    }
}
