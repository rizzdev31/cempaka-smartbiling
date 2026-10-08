<?php

namespace App\Events;

use App\Events\Concerns\CarriesServerTime;
use App\Models\BillingSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * REALTIME.md §5. Dihasilkan **scheduler**, jadi bisa terlambat beberapa detik.
 *
 * Payload sengaja ringan — client sudah memegang sesinya. Client TIDAK boleh
 * menunggu event ini untuk menampilkan "habis": timer lokal yang menentukan
 * tampilan, event ini hanya menyinkronkan status di server.
 *
 * Sejak DEC-023 event ini TIDAK berarti sesi berhenti. Waktu paket habis,
 * tapi customer boleh terus bermain dan kelebihannya ditagih saat checkout.
 */
class SessionExpired implements ShouldBroadcast
{
    use CarriesServerTime;
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public readonly BillingSession $session) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('operator'),
            new PrivateChannel('station.'.$this->session->station->code),
        ];
    }

    public function broadcastAs(): string
    {
        return 'session.expired';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'station_code' => $this->session->station->code,
            'status' => $this->session->status->value,
            'end_at' => $this->session->end_at?->toIso8601ZuluString(),
            'server_time' => $this->serverTime(),
        ];
    }
}
