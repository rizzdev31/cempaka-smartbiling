<?php

namespace App\Models;

use App\Enums\SessionItemType;
use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Exceptions\ApiException;
use App\Models\Concerns\HasUuidKey;
use App\Support\Api\ErrorCode;
use App\Support\Billing\CheckoutBilling;
use App\Support\Billing\ExtendPolicy;
use App\Support\Billing\SessionTotals;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Session billing. Tabelnya `sessions` (PRD §22); kelasnya diberi nama
 * `BillingSession` supaya tidak tertukar dengan Session milik Laravel.
 *
 * Model ini menyimpan perhitungan yang TIDAK boleh berbeda antar endpoint:
 * totals Open Tab, hak waktu, dan kebolehan extend. Aturannya sendiri ada di
 * `app/Support/Billing` supaya bisa diuji tanpa database; di sini hanya
 * penyambungan ke data.
 */
#[Fillable([
    'code', 'station_id', 'customer_id', 'customer_name', 'package_id',
    'package_name', 'package_duration_minutes', 'package_price', 'hourly_rate',
    'mode', 'status', 'started_at', 'end_at', 'ended_at',
    'actual_duration_minutes', 'billable_duration_minutes',
    'receipt_number', 'receipt_issued_at',
    'opened_by', 'closed_by', 'shift_id', 'cancel_reason',
])]
class BillingSession extends Model
{
    use HasUuidKey;

    protected $table = 'sessions';

    /**
     * Ambang status WARNING — PRD §16 memakai peringatan 10/5/1 menit, dan 10
     * menit adalah yang pertama. Perilaku tampilannya di TV masih OD-004;
     * yang diatur di sini hanya kapan statusnya berubah.
     */
    public const WARNING_THRESHOLD_MINUTES = 10;

    protected function casts(): array
    {
        return [
            'mode' => SessionMode::class,
            'status' => SessionStatus::class,
            'started_at' => 'datetime',
            'end_at' => 'datetime',
            'ended_at' => 'datetime',
            'receipt_issued_at' => 'datetime',
        ];
    }

    /**
     * Status yang membuat station terpakai.
     *
     * @return list<string>
     */
    public static function occupyingStatuses(): array
    {
        return array_values(array_map(
            fn (SessionStatus $s) => $s->value,
            array_filter(SessionStatus::cases(), fn (SessionStatus $s) => $s->occupiesStation()),
        ));
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SessionItem::class, 'session_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'session_id');
    }

    public function fnbOrders(): HasMany
    {
        return $this->hasMany(FnbOrder::class, 'session_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * Pindah status dengan penjagaan PRD §11.
     *
     * Semua perubahan status wajib lewat sini. Menulis `$session->status = ...`
     * langsung akan melewati penjagaan ini, dan sesi bisa selesai tanpa pernah
     * dibayar.
     */
    public function transitionTo(SessionStatus $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw ApiException::conflict(
                ErrorCode::SESSION_STATUS_INVALID,
                "Sesi berstatus {$this->status->value} tidak bisa menjadi {$next->value}.",
                ['from' => $this->status->value, 'to' => $next->value],
            );
        }

        $this->status = $next;
    }

    /** Jumlah payment yang sudah dikonfirmasi. Dasar `totals.paid`. */
    public function paidAmount(): int
    {
        return (int) $this->payments->where('status', 'CONFIRMED')->sum('amount');
    }

    public function totals(): SessionTotals
    {
        $totals = SessionTotals::of($this->items, $this->paidAmount());

        if ($this->mode !== SessionMode::POSTPAID || $this->started_at === null) {
            return $totals;
        }

        /*
         * Postpaid terbuka (DEC-034) belum punya baris rental tersimpan — harganya
         * baru pasti saat sesi ditutup. Tagihan berjalan dihitung di sini dari
         * waktu yang sudah terpakai, memakai rumus yang sama persis dengan
         * checkout: satu cara menghitung, bukan dua yang bisa berbeda.
         *
         * Setelah checkout barisnya ada dan nilainya sama — `withRental`
         * mengganti, bukan menambah, jadi tidak tertagih dua kali.
         */
        return $totals->withRental(CheckoutBilling::compute(
            $this->mode,
            (int) $this->package_duration_minutes,
            (int) $this->package_price,
            (int) $this->hourly_rate,
            $this->entitledMinutes() - (int) $this->package_duration_minutes,
            $this->actualMinutes(),
        )['rental_price']);
    }

    /**
     * Hak waktu customer: durasi paket + semua extend, dibayar maupun belum.
     * Dipakai checkout untuk memisahkan menit rental dari menit extend
     * (DEC-032) dan menghitung sisa waktu member (DEC-026).
     */
    public function entitledMinutes(): int
    {
        $extend = $this->items
            ->where('type', SessionItemType::EXTEND)
            ->sum(fn (SessionItem $item) => (int) ($item->meta['duration_minutes'] ?? 0));

        return (int) $this->package_duration_minutes + (int) $extend;
    }

    /**
     * Durasi aktual dalam menit. Dibulatkan ke atas: main 60 menit 1 detik
     * adalah 61 menit terpakai, dan station memang tidak bisa dijual selama
     * detik itu.
     */
    public function actualMinutes(?CarbonInterface $now = null): int
    {
        if ($this->started_at === null) {
            return 0;
        }

        $until = $this->ended_at ?? $now ?? Carbon::now();

        return max(0, (int) ceil($this->started_at->diffInSeconds($until, absolute: false) / 60));
    }

    /**
     * `extend_deadline_at` (API.md §7). Null selama `PENDING_PAYMENT`, karena
     * `end_at` belum ada. Sejak DEC-033 nilainya sama dengan `end_at`.
     */
    public function extendDeadlineAt(): ?CarbonInterface
    {
        return $this->end_at === null ? null : ExtendPolicy::deadlineFor($this->end_at);
    }

    /**
     * `extendable` (API.md §7) — **server** yang memutuskan, bukan client.
     * Kalau client menghitung sendiri, aturannya punya dua sumber kebenaran
     * yang bisa berbeda saat jam device meleset.
     */
    public function isExtendable(?CarbonInterface $now = null): bool
    {
        if (! $this->status->isExtendable() || $this->end_at === null) {
            return false;
        }

        return ExtendPolicy::isWithinWindow($this->end_at, $now ?? Carbon::now());
    }

    /**
     * Label customer untuk tampilan (API.md §6/§9 `customer_label`).
     * DEC-008: satu session satu customer; walk-in tanpa member pakai nama bebas.
     */
    public function customerLabel(): string
    {
        return $this->customer?->name ?? $this->customer_name ?? 'Walk-in';
    }
}
