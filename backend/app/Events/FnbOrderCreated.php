<?php

namespace App\Events;

use App\Events\Concerns\CarriesServerTime;
use App\Models\FnbOrder;
use App\Support\Presenters\FnbPresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * REALTIME.md §5. Memicu badge di F&B Queue Flutter.
 *
 * Tidak dikirim ke channel station: pesanan dapur bukan urusan layar TV.
 */
class FnbOrderCreated implements ShouldBroadcast
{
    use CarriesServerTime;
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public readonly FnbOrder $order) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('operator')];
    }

    public function broadcastAs(): string
    {
        return 'fnb.order.created';
    }

    public function broadcastWith(): array
    {
        return [
            'order' => FnbPresenter::order($this->order),
            'server_time' => $this->serverTime(),
        ];
    }
}
