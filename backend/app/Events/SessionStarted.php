<?php

namespace App\Events;

use App\Events\Concerns\CarriesServerTime;
use App\Models\BillingSession;
use App\Support\Presenters\SessionPresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * REALTIME.md §5. Dipicu saat session jadi `ACTIVE` — Postpaid saat dibuat,
 * Prepaid saat payment confirmed.
 */
class SessionStarted implements ShouldBroadcast
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
        return 'session.started';
    }

    public function broadcastWith(): array
    {
        return [
            'session' => SessionPresenter::one($this->session),
            'server_time' => $this->serverTime(),
        ];
    }
}
