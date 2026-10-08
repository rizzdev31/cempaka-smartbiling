<?php

namespace App\Support\Api;

/**
 * Daftar error code dari `docs/contracts/API.md` §11.
 *
 * Kontrak ini mengikat Flutter dan Kotlin — client memakai `error.code`,
 * bukan HTTP status (API.md §2). Menambah code di sini wajib diikuti
 * entry di `docs/contracts/CHANGELOG.md`.
 */
final class ErrorCode
{
    public const VALIDATION_FAILED = 'VALIDATION_FAILED';
    public const INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    public const UNAUTHENTICATED = 'UNAUTHENTICATED';
    public const USER_INACTIVE = 'USER_INACTIVE';
    public const FORBIDDEN = 'FORBIDDEN';
    public const TOO_MANY_ATTEMPTS = 'TOO_MANY_ATTEMPTS';
    public const NOT_FOUND = 'NOT_FOUND';
    public const IDEMPOTENCY_KEY_REQUIRED = 'IDEMPOTENCY_KEY_REQUIRED';
    public const IDEMPOTENCY_KEY_REUSED = 'IDEMPOTENCY_KEY_REUSED';
    public const STATION_NOT_AVAILABLE = 'STATION_NOT_AVAILABLE';
    public const STATION_HAS_ACTIVE_SESSION = 'STATION_HAS_ACTIVE_SESSION';
    public const TARGET_STATION_SAME = 'TARGET_STATION_SAME';

    /** DEC-021 — swap hanya dalam tipe konsol yang sama. */
    public const STATION_TYPE_MISMATCH = 'STATION_TYPE_MISMATCH';
    public const SESSION_STATUS_INVALID = 'SESSION_STATUS_INVALID';
    public const SESSION_NOT_ORDERABLE = 'SESSION_NOT_ORDERABLE';
    public const EXTEND_DURATION_INVALID = 'EXTEND_DURATION_INVALID';
    public const EXTEND_GRACE_EXPIRED = 'EXTEND_GRACE_EXPIRED';
    public const PAYMENT_AMOUNT_EXCEEDS_BALANCE = 'PAYMENT_AMOUNT_EXCEEDS_BALANCE';
    public const PAYMENT_REFERENCE_REQUIRED = 'PAYMENT_REFERENCE_REQUIRED';
    public const CHECKOUT_INSUFFICIENT_PAYMENT = 'CHECKOUT_INSUFFICIENT_PAYMENT';
    public const FNB_STATUS_TRANSITION_INVALID = 'FNB_STATUS_TRANSITION_INVALID';
    public const DEVICE_TOKEN_INVALID = 'DEVICE_TOKEN_INVALID';
    public const DEVICE_NOT_ASSIGNED = 'DEVICE_NOT_ASSIGNED';
    public const SERVER_ERROR = 'SERVER_ERROR';
}
