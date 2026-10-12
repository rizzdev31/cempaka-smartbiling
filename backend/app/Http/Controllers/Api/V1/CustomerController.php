<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreCustomerRequest;
use App\Http\Requests\Api\V1\StoreMembershipRequest;
use App\Http\Requests\Api\V1\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Services\MembershipService;
use App\Support\Api\ApiResponse;
use App\Support\Presenters\CustomerPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Customer & membership — API.md §6, DEC-027, DEC-049. */
class CustomerController
{
    public function __construct(
        private readonly MembershipService $memberships,
        private readonly CustomerService $customers,
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
        $customer = $this->customers->create($request->validated(), $request->user());

        return ApiResponse::data(CustomerPresenter::one($customer), 201);
    }

    /**
     * `PATCH /customers/{customer}` — DEC-049.
     *
     * Nama salah ketik dan nomor HP yang kurang satu angka baru ketahuan
     * saat struk gagal terkirim atau saat orangnya datang lagi dan tidak
     * ditemukan di pencarian. Sebelum ini keduanya permanen.
     *
     * Izinnya `customer.create`, bukan permission baru: orang yang boleh
     * membuat catatannya adalah orang yang paling mungkin menyadari
     * salahnya — operator di kasir, sedetik setelah mengetik. Permission
     * terpisah berarti dia harus mencari admin, dan dalam praktiknya
     * salah ketik itu akan dibiarkan.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer = $this->customers->update($customer, $request->validated(), $request->user());

        return ApiResponse::data(CustomerPresenter::one($customer->fresh('membership')));
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
