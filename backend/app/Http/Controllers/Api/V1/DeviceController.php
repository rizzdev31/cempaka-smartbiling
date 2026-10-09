<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\DeviceHeartbeatRequest;
use App\Http\Requests\Api\V1\RegisterDeviceRequest;
use App\Models\Device;
use App\Services\DeviceService;
use App\Support\Api\ApiResponse;
use App\Support\Presenters\DevicePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TV Agent — API.md §9.
 *
 * Tiga endpoint pertama dipakai TV dengan `X-Device-Token`. `GET /devices`
 * dipakai operator dengan Bearer token, dan read-only: pendaftaran ulang,
 * pemetaan, serta pencabutan token adalah wewenang Admin (PRD §19, Tahap 3B).
 */
class DeviceController
{
    public function __construct(private readonly DeviceService $devices) {}

    /**
     * `POST /devices/register` — tanpa auth, dijaga kode pendaftaran.
     *
     * Token mentahnya dikembalikan **sekali ini saja**; yang disimpan server
     * cuma hash-nya. TV yang kehilangan token mendaftar ulang, bukan
     * menanyakan yang lama.
     */
    public function register(RegisterDeviceRequest $request): JsonResponse
    {
        $hasil = $this->devices->register($request->validated());

        return ApiResponse::data([
            'device_token' => $hasil['token'],
            'station' => [
                'code' => $hasil['device']->station?->code,
                'name' => $hasil['device']->station?->name,
            ],
        ], 201);
    }

    public function heartbeat(DeviceHeartbeatRequest $request): JsonResponse
    {
        return ApiResponse::data(
            $this->devices->heartbeat($request->user(), $request->validated()),
        );
    }

    public function meState(Request $request): JsonResponse
    {
        return ApiResponse::data($this->devices->state($request->user()));
    }

    /** `GET /devices` — layar Status TV di Flutter dan dashboard Admin. */
    public function index(): JsonResponse
    {
        $devices = Device::query()
            ->with('station')
            ->orderBy('device_uid')
            ->get();

        return ApiResponse::collection(
            $devices->map(fn (Device $d) => DevicePresenter::one($d))->all(),
            /*
             * Dikirim supaya client bisa menjelaskan KENAPA sebuah device
             * dianggap offline, tanpa menduplikasi aturannya (API.md §9).
             */
            ['offline_threshold_seconds' => Device::OFFLINE_THRESHOLD_SECONDS],
        );
    }
}
