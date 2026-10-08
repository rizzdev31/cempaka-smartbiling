<?php

namespace App\Services;

use App\Events\SessionSwapped;
use App\Exceptions\ApiException;
use App\Models\BillingSession;
use App\Models\Station;
use App\Models\User;
use App\Support\Api\ErrorCode;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Station Swap — API.md §7, PRD §15, R07.
 *
 * Yang dijamin dan diuji: `session_id` tidak berubah, `end_at` tidak berubah,
 * items dan payments tidak tersentuh. Swap memindahkan TEMPAT, bukan membuat
 * sesi baru — kalau session dibuat ulang, customer kehilangan sisa waktu dan
 * Open Tab-nya, dan itu persis kegagalan yang ditandai R07.
 *
 * Seluruhnya dalam satu transaksi dengan kedua station dikunci. Tanpa kunci,
 * dua swap yang menargetkan station kosong yang sama akan sama-sama lolos.
 */
class SwapService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function swap(BillingSession $session, string $targetStationId, ?string $reason, User $actor): BillingSession
    {
        [$session, $from, $to] = DB::transaction(function () use ($session, $targetStationId, $reason, $actor) {
            $session = BillingSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id)
                ->load(['station', 'items', 'payments']);

            // Sesi yang belum dibayar atau sudah selesai tidak sedang memakai
            // TV mana pun, jadi tidak ada yang perlu dipindahkan.
            if (! $session->status->isRunning()) {
                throw ApiException::conflict(
                    ErrorCode::SESSION_STATUS_INVALID,
                    "Sesi berstatus {$session->status->value} tidak bisa dipindah station.",
                );
            }

            $from = $session->station;

            if ($from !== null && $from->id === $targetStationId) {
                throw ApiException::conflict(
                    ErrorCode::TARGET_STATION_SAME,
                    'Station tujuan sama dengan station asal.',
                );
            }

            $to = Station::query()->lockForUpdate()->find($targetStationId);

            if ($to === null) {
                throw ApiException::notFound('Station tujuan tidak ditemukan.');
            }

            if (! $to->status->acceptsNewSession()) {
                throw ApiException::conflict(
                    ErrorCode::STATION_NOT_AVAILABLE,
                    "Station {$to->code} sedang {$to->status->value}.",
                );
            }

            if ($to->currentSession() !== null) {
                throw ApiException::conflict(
                    ErrorCode::STATION_HAS_ACTIVE_SESSION,
                    "Station {$to->code} masih dipakai sesi lain.",
                );
            }

            /*
             * DEC-021 — swap hanya dalam tipe konsol yang sama. Tipe berbeda
             * berarti tarif berbeda (DEC-019), dan harga sesi ini sudah dibekukan
             * saat dibuat. Memindahkannya akan membuat customer bermain di PS5
             * dengan tarif PS4. Pindah tipe konsol adalah sesi baru (DEC-025),
             * bukan swap.
             */
            if ($from !== null && $from->station_type_id !== $to->station_type_id) {
                throw ApiException::conflict(
                    ErrorCode::STATION_TYPE_MISMATCH,
                    'Station tujuan beda tipe konsol. Pindah tipe konsol harus lewat sesi baru.',
                    [
                        'from_station_type_id' => $from->station_type_id,
                        'to_station_type_id' => $to->station_type_id,
                    ],
                );
            }

            // Satu kolom. end_at, items, payments, dan status sengaja tidak
            // disentuh sama sekali.
            $session->station_id = $to->id;
            $session->save();

            $this->audit->forUser($actor, AuditAction::SESSION_SWAPPED, [
                'subject_type' => 'session',
                'subject_id' => $session->id,
                'before' => ['station' => $from?->code],
                'after' => ['station' => $to->code, 'reason' => $reason],
            ]);

            return [$session->fresh(['station', 'customer', 'items', 'payments']), $from, $to];
        });

        SessionSwapped::dispatch($session, $from?->code ?? '', $to->code, $actor);

        return $session;
    }
}
