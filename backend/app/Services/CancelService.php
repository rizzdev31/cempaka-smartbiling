<?php

namespace App\Services;

use App\Enums\SessionStatus;
use App\Events\SessionUpdated;
use App\Exceptions\ApiException;
use App\Models\BillingSession;
use App\Models\User;
use App\Support\Api\ErrorCode;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pembatalan sesi — API.md §7 `POST /sessions/{id}/cancel`.
 *
 * Hanya dari `PENDING_PAYMENT`: customer berubah pikiran sebelum membayar,
 * atau operator salah memilih station. Sesi yang sudah berjalan diselesaikan
 * lewat checkout, bukan dibatalkan — kalau boleh dibatalkan, uang yang sudah
 * masuk dan F&B yang sudah keluar kehilangan jejaknya.
 *
 * Penjagaan statusnya ada di `BillingSession::transitionTo()`, bukan diulang
 * di sini.
 */
class CancelService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function cancel(BillingSession $session, ?string $reason, User $actor): BillingSession
    {
        $session = DB::transaction(function () use ($session, $reason, $actor) {
            $session = BillingSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id)
                ->load(['items', 'payments', 'station']);

            /*
             * Status diperiksa LEBIH DULU. Sesi yang sudah berjalan hampir
             * selalu juga sudah dibayar, dan kalau pemeriksaan uang didahulukan
             * operator menerima "sesi ini sudah menerima pembayaran" padahal
             * alasan sebenarnya "sesi sudah berjalan, selesaikan lewat
             * checkout". Pesan yang menyesatkan membuat dia mencari uangnya,
             * bukan menekan tombol yang benar.
             */
            $session->transitionTo(SessionStatus::CANCELLED);

            /*
             * Baru setelah itu: pembayaran sebagian memang mungkin terjadi pada
             * sesi yang masih PENDING_PAYMENT — customer membayar 5.000 dari
             * 20.000 lalu berubah pikiran. V1 tidak punya refund (API.md §7),
             * jadi membatalkannya akan meninggalkan uang yang tidak terhubung
             * ke transaksi mana pun. Ditolak supaya selisihnya diselesaikan
             * manual oleh kasir, bukan diam-diam.
             */
            if ($session->paidAmount() > 0) {
                throw ApiException::conflict(
                    ErrorCode::SESSION_HAS_PAYMENT,
                    'Sesi ini sudah menerima pembayaran dan tidak bisa dibatalkan.',
                    ['paid' => $session->paidAmount()],
                );
            }
            $session->cancel_reason = $reason;
            // Station langsung kosong: CANCELLED tidak lagi memakainya.
            $session->ended_at = Carbon::now();
            $session->closed_by = $actor->id;
            $session->save();

            $this->audit->forUser($actor, AuditAction::SESSION_CANCELLED, [
                'subject_type' => 'session',
                'subject_id' => $session->id,
                'after' => [
                    'code' => $session->code,
                    'station' => $session->station?->code,
                    'reason' => $reason,
                ],
            ]);

            return $session->fresh(['station', 'customer', 'items', 'payments']);
        });

        SessionUpdated::dispatch($session, ['status']);

        return $session;
    }
}
