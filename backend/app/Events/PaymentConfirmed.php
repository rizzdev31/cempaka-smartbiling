<?php

namespace App\Events;

use App\Events\Concerns\CarriesServerTime;
use App\Models\BillingSession;
use App\Models\Payment;
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
 * TIDAK dikirim ke channel station: customer tidak perlu melihat nominal
 * pembayaran orang lain di layar TV.
 */
class PaymentConfirmed implements ShouldBroadcast
{
    use CarriesServerTime;
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly BillingSession $session,
        public readonly Payment $payment,
        public readonly ?User $actor = null,
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('operator')];
    }

    public function broadcastAs(): string
    {
        return 'payment.confirmed';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'station_code' => $this->session->station?->code,
            'payment' => SessionPresenter::payment($this->payment),
            'totals' => $this->session->totals()->toArray(),
            'actor' => UserPresenter::actor($this->actor),
            'server_time' => $this->serverTime(),
        ];
    }
}
