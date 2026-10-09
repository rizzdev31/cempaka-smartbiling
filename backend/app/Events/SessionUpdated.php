<?php

namespace App\Events;

use App\Events\Concerns\CarriesServerTime;
use App\Models\BillingSession;
use App\Support\Presenters\SessionPresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * REALTIME.md §5. Dipicu oleh perubahan status, item baru, perubahan totals,
 * checkout masuk, dan cancel.
 *
 * Payload membawa objek `session` **utuh**, bukan delta: client tidak boleh
 * perlu merekonstruksi state dari urutan event, karena urutan itu tidak dijamin
 * setelah reconnect.
 *
 * **Jangan dipanggil langsung dari service.** Event ini di-debounce 500 ms per
 * sesi (REALTIME.md §7) lewat `SessionUpdateBroadcaster::schedule()`, dan hanya
 * `BroadcastSessionUpdate` yang boleh memicunya. `ShouldBroadcastNow` dipakai
 * justru karena pengirimannya sudah berada di dalam job itu — mengantrekannya
 * lagi hanya menambah satu lapisan tanpa guna.
 */
class SessionUpdated implements ShouldBroadcastNow
{
    use CarriesServerTime;
    use Dispatchable;
    use InteractsWithSockets;

    /** @param  list<string>  $changed  Petunjuk UI saja — client tetap pakai `session`. */
    public function __construct(
        public readonly BillingSession $session,
        public readonly array $changed = [],
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
        return 'session.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'session' => SessionPresenter::one($this->session),
            'changed' => $this->changed,
            'server_time' => $this->serverTime(),
        ];
    }
}
