<?php

namespace App\Services;

use App\Enums\SessionItemType;
use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Events\SessionStarted;
use App\Exceptions\ApiException;
use App\Models\BillingSession;
use App\Models\Package;
use App\Models\Station;
use App\Models\User;
use App\Support\Api\ErrorCode;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditLogger;
use App\Support\Billing\DocumentNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pembuatan session — API.md §7 `POST /sessions`.
 *
 * Harga dibekukan di baris session saat dibuat (`package_name`,
 * `package_price`, `hourly_rate`). Owner boleh mengubah tarif kapan saja
 * (DEC-019/020); tanpa snapshot ini, perubahan harga akan mengubah tagihan
 * sesi yang sedang berjalan — termasuk harga extend, karena rumus DEC-007
 * memakai `hourly_rate`.
 */
class SessionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(array $input, User $actor): BillingSession
    {
        return DB::transaction(function () use ($input, $actor) {
            $now = Carbon::now();

            /*
             * Station dikunci sampai transaksi selesai. Tanpa ini, dua request
             * yang datang bersamaan sama-sama melihat station kosong dan
             * membuat dua session di satu TV.
             */
            $station = Station::query()
                ->lockForUpdate()
                ->find($input['station_id']);

            if ($station === null) {
                throw ApiException::notFound('Station tidak ditemukan.');
            }

            if (! $station->status->acceptsNewSession()) {
                throw ApiException::conflict(
                    ErrorCode::STATION_NOT_AVAILABLE,
                    "Station {$station->code} sedang {$station->status->value}.",
                );
            }

            if ($station->currentSession() !== null) {
                throw ApiException::conflict(
                    ErrorCode::STATION_HAS_ACTIVE_SESSION,
                    "Station {$station->code} masih dipakai sesi lain.",
                );
            }

            $package = Package::query()->find($input['package_id']);

            if ($package === null || ! $package->is_active) {
                throw ApiException::unprocessable(
                    ErrorCode::VALIDATION_FAILED,
                    'Paket tidak ditemukan atau sudah tidak aktif.',
                    ['package_id' => 'Paket tidak tersedia.'],
                );
            }

            /*
             * DEC-019: paket milik satu tipe konsol. Memakai paket PS4 di
             * station PS5 akan membekukan tarif yang salah untuk seluruh sesi,
             * dan harga extend ikut salah sampai checkout.
             */
            if ($package->station_type_id !== $station->station_type_id) {
                throw ApiException::unprocessable(
                    ErrorCode::VALIDATION_FAILED,
                    'Paket ini bukan untuk tipe konsol station tersebut.',
                    ['package_id' => 'Tipe konsol paket dan station berbeda.'],
                );
            }

            $mode = SessionMode::from($input['mode']);

            // Postpaid langsung jalan; Prepaid menunggu pembayaran dulu
            // (API.md §7). Timer Prepaid tidak boleh jalan sebelum dibayar.
            $isPostpaid = $mode === SessionMode::POSTPAID;

            $session = new BillingSession([
                'code' => DocumentNumber::sessionCode($now),
                'station_id' => $station->id,
                'customer_id' => $input['customer_id'] ?? null,
                'customer_name' => $this->customerName($input),
                'package_id' => $package->id,
                'package_name' => $package->name,
                'package_duration_minutes' => $package->duration_minutes,
                'package_price' => $package->price,
                'hourly_rate' => $package->hourlyRate(),
                'mode' => $mode,
                'status' => $isPostpaid ? SessionStatus::ACTIVE : SessionStatus::PENDING_PAYMENT,
                'started_at' => $isPostpaid ? $now : null,
                'end_at' => $isPostpaid ? $now->copy()->addMinutes($package->duration_minutes) : null,
                'opened_by' => $actor->id,
                'shift_id' => $actor->activeShift()?->id,
            ]);

            $session->save();

            /*
             * Rental selalu masuk Open Tab sebagai item, termasuk Prepaid.
             * Kalau rental hanya hidup sebagai kolom di sesi, `balance_due`
             * tidak punya apa pun untuk ditagih saat pembayaran pertama.
             */
            $session->items()->create([
                'type' => SessionItemType::RENTAL,
                'name' => "Paket {$package->name}",
                'qty' => 1,
                'unit_price' => $package->price,
                'subtotal' => $package->price,
                'is_paid' => false,
                'meta' => ['duration_minutes' => $package->duration_minutes],
                'created_by' => $actor->id,
            ]);

            $this->audit->forUser($actor, AuditAction::SESSION_CREATED, [
                'subject_type' => 'session',
                'subject_id' => $session->id,
                'after' => [
                    'code' => $session->code,
                    'station' => $station->code,
                    'mode' => $mode->value,
                    'package' => $package->name,
                    'package_price' => $package->price,
                ],
            ]);

            $fresh = $session->fresh(['station', 'customer', 'items', 'payments']);

            /*
             * Postpaid langsung ACTIVE, jadi timernya mulai sekarang. Prepaid
             * belum — event-nya menyusul dari PaymentService saat dibayar.
             *
             * Dipanggil di dalam transaksi karena tidak ada lagi yang bisa
             * gagal setelah titik ini; broadcast sendiri berjalan asinkron.
             */
            if ($fresh->status === SessionStatus::ACTIVE) {
                SessionStarted::dispatch($fresh);
            }

            return $fresh;
        });
    }

    /**
     * DEC-008: satu session satu customer. Walk-in tanpa member disimpan
     * sebagai nama bebas, default "Walk-in" (API.md §7).
     */
    private function customerName(array $input): ?string
    {
        if (! empty($input['customer_id'])) {
            return null;
        }

        $name = trim((string) ($input['customer_name'] ?? ''));

        return $name === '' ? 'Walk-in' : $name;
    }
}
