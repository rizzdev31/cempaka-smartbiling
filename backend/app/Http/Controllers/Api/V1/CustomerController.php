<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreCustomerRequest;
use App\Http\Requests\Api\V1\StoreMembershipRequest;
use App\Models\Customer;
use App\Services\MembershipService;
use App\Support\Api\ApiResponse;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditLogger;
use App\Support\Presenters\CustomerPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Customer & membership — API.md §6, DEC-027. */
class CustomerController
{
    public function __construct(
        private readonly MembershipService $memberships,
        private readonly AuditLogger $audit,
    ) {}

    /** `GET /customers?q=<nama|telepon>` — kotak cari di layar Start Session. */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query()->with('membership');

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));

            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        // Dibatasi: kotak cari kasir tidak pernah butuh lebih dari sehalaman,
        // dan daftar panjang justru memperlambat operator.
        $customers = $query->orderBy('name')->limit(50)->get();

        return ApiResponse::collection(
            $customers->map(fn (Customer $c) => CustomerPresenter::one($c))->all(),
        );
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::query()->create($request->validated());

        // DEC-027: operator boleh mendaftarkan, tapi tidak boleh tidak terlacak.
        $this->audit->forUser($request->user(), AuditAction::CUSTOMER_CREATED, [
            'subject_type' => 'customer',
            'subject_id' => $customer->id,
            'after' => ['name' => $customer->name, 'phone' => $customer->phone],
        ]);

        return ApiResponse::data(CustomerPresenter::one($customer), 201);
    }

    /** `POST /customers/{id}/membership` — DEC-027, DEC-029. */
    public function storeMembership(StoreMembershipRequest $request, Customer $customer): JsonResponse
    {
        $this->memberships->register(
            $customer,
            $request->validated('session_id'),
            $request->validated('tier'),
            $request->user(),
        );

        return ApiResponse::data([
            'customer' => CustomerPresenter::one($customer->fresh('membership')),
            'fee' => (int) config('billing.membership_fee'),
        ], 201);
    }
}
