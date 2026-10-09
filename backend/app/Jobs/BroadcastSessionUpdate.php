<?php

namespace App\Jobs;

use App\Events\SessionUpdated;
use App\Models\BillingSession;
use App\Support\Realtime\SessionUpdateBroadcaster;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Mengirim `session.updated` setelah jendela debounce lewat — REALTIME.md §7.
 *
 * Job ini menerima **id**, bukan objek sesi. Itu intinya: ia membaca ulang
 * sesi dari database saat berjalan, jadi yang terkirim selalu keadaan
 * terbaru — bukan potret saat job dijadwalkan. Semua perubahan yang terjadi
 * selama jendela debounce ikut terbawa tanpa satu pun event tambahan.
 *
 * Kalau sesinya sudah terhapus saat job berjalan, tidak ada yang perlu
 * dikabarkan dan job selesai diam-diam.
 */
class BroadcastSessionUpdate implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $sessionId) {}

    public function handle(): void
    {
        $changed = SessionUpdateBroadcaster::flush($this->sessionId);

        $session = BillingSession::query()
            ->with(['station', 'customer', 'items', 'payments'])
            ->find($this->sessionId);

        if ($session === null) {
            return;
        }

        /*
         * SessionUpdated bersifat ShouldBroadcastNow, jadi pengirimannya
         * terjadi di dalam job ini — bukan diantrekan lagi. Reverb yang mati
         * membuat JOB ini gagal dan masuk failed_jobs, bukan membuat
         * permintaan HTTP operator gagal (REALTIME.md §1: realtime adalah
         * optimasi, bukan sumber kebenaran).
         */
        SessionUpdated::dispatch($session, $changed);
    }
}
