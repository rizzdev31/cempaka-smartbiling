<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreStationTypeRequest;
use App\Http\Requests\Api\V1\UpdateStationTypeRequest;
use App\Models\StationType;
use App\Services\MasterDataService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tipe konsol — DEC-019 (pembawa tarif), DEC-048 (bisa ditambah & diubah).
 *
 * Namanya teks bebas, bukan enum: "PS5 Pro", "VIP Room", "Simulator Balap".
 * Enum akan memaksa rilis aplikasi baru setiap kali pemilik membeli jenis
 * mesin yang berbeda.
 */
class StationTypeController
{
    public function __construct(private readonly MasterDataService $masterData) {}

    public function index(Request $request): JsonResponse
    {
        $types = StationType::query()
            ->when($request->boolean('only_active', true), fn ($q) => $q->where('is_active', true))
            ->withCount('stations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::collection($types->map(fn (StationType $type) => [
            'id' => $type->id,
            'name' => $type->name,
            'sort_order' => (int) $type->sort_order,
            'is_active' => (bool) $type->is_active,
            // Dipakai layar pengaturan untuk memberi tahu akibat
            // menonaktifkan tipe sebelum tombolnya ditekan.
            'station_count' => (int) $type->stations_count,
        ])->all());
    }

    public function store(StoreStationTypeRequest $request): JsonResponse
    {
        $type = $this->masterData->createStationType($request->validated(), $request->user());

        return ApiResponse::data([
            'id' => $type->id,
            'name' => $type->name,
            'sort_order' => (int) $type->sort_order,
            'is_active' => (bool) $type->is_active,
        ], 201);
    }

    public function update(UpdateStationTypeRequest $request, StationType $type): JsonResponse
    {
        $type = $this->masterData->updateStationType($type, $request->validated(), $request->user());

        return ApiResponse::data([
            'id' => $type->id,
            'name' => $type->name,
            'sort_order' => (int) $type->sort_order,
            'is_active' => (bool) $type->is_active,
        ]);
    }
}
