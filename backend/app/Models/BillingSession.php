<?php

namespace App\Models;

use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Session billing. Tabelnya `sessions` (PRD §22); kelasnya diberi nama
 * `BillingSession` supaya tidak tertukar dengan Session milik Laravel.
 *
 * Mesin state dan perhitungan harga BELUM ada di sini — itu langkah 6.
 * Model ini sengaja tipis: hanya relasi, cast, dan hal yang tidak boleh
 * diduplikasi di tempat lain.
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

    /**
     * Label customer untuk tampilan (API.md §6/§9 `customer_label`).
     * DEC-008: satu session satu customer; walk-in tanpa member pakai nama bebas.
     */
    public function customerLabel(): string
    {
        return $this->customer?->name ?? $this->customer_name ?? 'Walk-in';
    }
}
