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
 * REALTIME.md §5. Dikirim ke DUA channel station: yang lama supaya TV-nya
 * kembali idle, dan yang baru supaya TV-nya mulai menghitung.
 *
 * `end_at` tidak berubah sama sekali (PRD §15, R07) — swap memindahkan tempat,
 * bukan memperpanjang waktu.
 */
class SessionSwapped implements ShouldBroadcast
{
    use CarriesServerTime;
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly BillingSession $session,
        public readonly string $fromStationCode,
        public readonly string $toStationCode,
        public readonly ?User $actor = null,
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('operator'),
            new PrivateChannel('station.'.$this->fromStationCode),
            new PrivateChannel('station.'.$this->toStationCode),
        ];
    }

    public function broadcastAs(): string
    {
        return 'session.swapped';
    }

    public function broadcastWith(): array
    {
        return [
            'session' => SessionPresenter::one($this->session),
            'from_station_code' => $this->fromStationCode,
            'to_station_code' => $this->toStationCode,
            'actor' => UserPresenter::actor($this->actor),
            'server_time' => $this->serverTime(),
        ];
    }
}
