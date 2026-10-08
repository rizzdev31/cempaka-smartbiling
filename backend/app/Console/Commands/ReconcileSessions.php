<?php

namespace App\Console\Commands;

use App\Enums\SessionStatus;
use App\Events\SessionExpired;
use App\Events\SessionUpdated;
use App\Models\BillingSession;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menyelaraskan status sesi dengan jam server — ROADMAP Tahap 0,
 * REALTIME.md §5 (`session.expired`).
 *
 * ## Kenapa ini ada, padahal client menghitung timer sendiri
 *
 * Status di database tidak bergerak sendiri. Tanpa perintah ini, sesi yang
 * waktunya habis tetap tertulis `ACTIVE` sampai ada operator menyentuhnya —
 * dan laporan, dashboard, serta `GET /sessions` ikut salah. Tampilan "habis"
 * di tablet dan TV TETAP ditentukan timer lokal dari `end_at` (PRD §16); yang
 * dikerjakan di sini hanya menyelaraskan status server dan mengirim event.
 *
 * ## Yang TIDAK dilakukan perintah ini
 *
 * Menutup sesi. Sejak DEC-023, `EXPIRED` berarti "waktu paket sudah lewat",
 * bukan "sesi selesai" — customer boleh terus bermain dan kelebihannya ditagih
 * saat checkout. Station tetap terpakai, dan hanya operator yang menutup sesi
 * lewat checkout.
 */
class ReconcileSessions extends Command
{
    protected $signature = 'sessions:reconcile';

    protected $description = 'Selaraskan status sesi (ACTIVE/WARNING/EXPIRED) dengan end_at';

    public function handle(): int
    {
        $now = Carbon::now();

        $sessions = BillingSession::query()
            ->with('station')
            ->whereIn('status', [
                SessionStatus::ACTIVE->value,
                SessionStatus::WARNING->value,
                SessionStatus::EXPIRED->value,
            ])
            ->whereNotNull('end_at')
            ->get();

        $changed = 0;

        foreach ($sessions as $session) {
            $target = $this->targetStatus($session, $now);

            if ($target === null || $target === $session->status) {
                continue;
            }

            $from = $session->status;

            DB::transaction(function () use ($session, $target) {
                $session->transitionTo($target);
                $session->save();
            });

            $changed++;

            /*
             * Event dikirim SETELAH commit. Kalau dikirim di dalam transaksi
             * dan transaksinya gagal, TV sudah terlanjur menerima kabar yang
             * tidak pernah terjadi.
             */
            if ($target === SessionStatus::EXPIRED) {
                SessionExpired::dispatch($session);
            } else {
                SessionUpdated::dispatch(
                    $session->fresh(['station', 'customer', 'items', 'payments']),
                    ['status'],
                );
            }

            $this->line("{$session->code}: {$from->value} -> {$target->value}");
        }

        $this->info("Selesai. {$sessions->count()} sesi diperiksa, {$changed} diubah.");

        return self::SUCCESS;
    }

    /**
     * Status yang seharusnya, menurut jam server.
     *
     * Hanya mengembalikan status yang transisinya sah dari status sekarang —
     * penjagaan PRD §11 tetap dihormati, perintah ini tidak boleh jadi pintu
     * belakang yang memindahkan sesi ke mana saja.
     */
    private function targetStatus(BillingSession $session, Carbon $now): ?SessionStatus
    {
        $endAt = $session->end_at;

        if ($now->greaterThan($endAt)) {
            return SessionStatus::EXPIRED;
        }

        $warningFrom = $endAt->copy()->subMinutes(BillingSession::WARNING_THRESHOLD_MINUTES);

        // Extend bisa mendorong end_at jauh ke depan lagi; sesi yang tadinya
        // WARNING harus bisa kembali ACTIVE.
        $target = $now->greaterThanOrEqualTo($warningFrom)
            ? SessionStatus::WARNING
            : SessionStatus::ACTIVE;

        return $session->status->canTransitionTo($target) ? $target : null;
    }
}
