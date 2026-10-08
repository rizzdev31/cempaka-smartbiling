<?php

namespace App\Events;

use App\Events\Concerns\CarriesServerTime;
use App\Models\Device;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * REALTIME.md §5 dan §6.
 *
 * Kotlin mengirim heartbeat lewat **HTTP**; Laravel yang mem-broadcast. Client
 * tidak boleh jadi producer event (PRD §8) — kalau TV mem-broadcast sendiri,
 * status device jadi klaim sepihak yang tidak bisa diverifikasi server.
 *
 * Endpoint `POST /devices/heartbeat` yang memicu event ini baru ada di Tahap 2.
 * Kelasnya ditulis sekarang supaya bentuk payload-nya sudah terkunci bersama
 * tujuh event lainnya.
 */
class DeviceHeartbeat implements ShouldBroadcast
{
    use CarriesServerTime;
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public readonly Device $device) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('operator')];
    }

    public function broadcastAs(): string
    {
        return 'device.heartbeat';
    }

    public function broadcastWith(): array
    {
        return [
            'device' => [
                'id' => $this->device->id,
                'station_code' => $this->device->station?->code,
                'status' => $this->device->last_seen_at === null ? 'OFFLINE' : 'ONLINE',
                'last_seen_at' => $this->device->last_seen_at?->toIso8601ZuluString(),
                'app_version' => $this->device->app_version,
            ],
            'server_time' => $this->serverTime(),
        ];
    }
}
