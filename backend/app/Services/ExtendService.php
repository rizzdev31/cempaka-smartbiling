<?php

namespace App\Services;

use App\Enums\SessionItemType;
use App\Enums\SessionStatus;
use App\Exceptions\ApiException;
use App\Models\BillingSession;
use App\Models\User;
use App\Support\Api\ErrorCode;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditLogger;
use App\Support\Billing\ExtendPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Extend — API.md §7 `POST /sessions/{id}/extend`, aturan DEC-007.
 *
 * Operator adalah approver (PRD §14), jadi extend dari tablet langsung
 * diterapkan. Pengajuan extend dari customer lewat portal (`extend-requests`)
 * baru ada di Tahap 3C.
 *
 * @return array{session: BillingSession, duration_minutes: int, price: int,
 *               previous_end_at: Carbon, new_end_at: Carbon}
 */
class ExtendService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function extend(BillingSession $session, int $minutes, User $actor): array
    {
        return DB::transaction(function () use ($session, $minutes, $actor) {
            $now = Carbon::now();

            $session = BillingSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id)
                ->load(['items', 'payments']);

            // Kelipatan 30 menit, minimum 30 (DEC-007).
            if (! ExtendPolicy::isDurationValid($minutes)) {
                throw ApiException::unprocessable(
                    ErrorCode::EXTEND_DURATION_INVALID,
                    'Extend hanya kelipatan 30 menit, minimum 30 menit.',
                    ['duration_minutes' => $minutes],
                );
            }

            if (! $session->status->isExtendable() || $session->end_at === null) {
                throw ApiException::conflict(
                    ErrorCode::SESSION_STATUS_INVALID,
                    "Sesi berstatus {$session->status->value} tidak bisa di-extend.",
                );
            }

            /*
             * Grace 10 menit dihitung dari `end_at`, bukan dari sekarang.
             * Lewat batas ini sesi tetap berjalan (DEC-023) — yang ditolak
             * hanya extend-nya, dan kelebihan waktunya ditagih saat checkout.
             */
            if (! ExtendPolicy::isWithinGrace($session->end_at, $now)) {
                throw ApiException::conflict(
                    ErrorCode::EXTEND_GRACE_EXPIRED,
                    'Batas extend sudah lewat lebih dari 10 menit setelah waktu habis.',
                    [
                        'end_at' => $session->end_at->toIso8601ZuluString(),
                        'grace_until' => $session->extendDeadlineAt()->toIso8601ZuluString(),
                    ],
                );
            }

            $previousEndAt = $session->end_at->copy();
            $newEndAt = ExtendPolicy::newEndAt($session->end_at, $minutes);
            $price = ExtendPolicy::priceFor((int) $session->hourly_rate, $minutes);

            $session->items()->create([
                'type' => SessionItemType::EXTEND,
                'name' => "Extend {$minutes} menit",
                'qty' => 1,
                'unit_price' => $price,
                'subtotal' => $price,
                // Masuk Open Tab, dibayar saat checkout (PRD §12) — termasuk
                // pada Prepaid, yang rental-nya sudah lunas.
                'is_paid' => false,
                'meta' => ['duration_minutes' => $minutes],
                'created_by' => $actor->id,
            ]);

            $session->end_at = $newEndAt;

            // Waktu bertambah, jadi sesi hidup lagi. WARNING dan EXPIRED
            // kembali ke ACTIVE; yang sudah ACTIVE tidak perlu transisi.
            if ($session->status !== SessionStatus::ACTIVE) {
                $session->transitionTo(SessionStatus::ACTIVE);
            }

            $session->save();

            $this->audit->forUser($actor, AuditAction::SESSION_EXTENDED, [
                'subject_type' => 'session',
                'subject_id' => $session->id,
                'before' => ['end_at' => $previousEndAt->toIso8601ZuluString()],
                'after' => [
                    'end_at' => $newEndAt->toIso8601ZuluString(),
                    'duration_minutes' => $minutes,
                    'price' => $price,
                ],
            ]);

            return [
                'session' => $session->fresh(['station', 'customer', 'items', 'payments']),
                'duration_minutes' => $minutes,
                'price' => $price,
                'previous_end_at' => $previousEndAt,
                'new_end_at' => $newEndAt,
            ];
        });
    }
}
