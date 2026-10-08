<?php

namespace App\Events;

use App\Events\Concerns\CarriesServerTime;
use App\Models\BillingSession;
use App\Models\User;
use App\Support\Presenters\SessionPresenter;
use App\Support\Presenters\UserPresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * REALTIME.md §5.
 *
 * `previous_end_at` ikut dikirim supaya TV bisa memastikan `end_at` yang
 * dipegangnya memang yang diperpanjang. Kalau tidak cocok, TV memanggil
 * `GET /devices/me/state` — tanpa field ini, TV yang ketinggalan satu event
 * akan menambahkan durasi ke `end_at` yang salah.
 */
class SessionExtended implements ShouldBroadcast
{
    use CarriesServerTime;
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly BillingSession $session,
        public readonly array $extend,
        public readonly ?User $actor = null,
    ) {}

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
        return 'session.extended';
    }

    public function broadcastWith(): array
    {
        return [
            'session' => SessionPresenter::one($this->session),
            'extend' => $this->extend,
            'actor' => UserPresenter::actor($this->actor),
            'server_time' => $this->serverTime(),
        ];
    }
}
