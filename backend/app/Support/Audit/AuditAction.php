<?php

namespace App\Support\Audit;

/**
 * Nama aksi audit. Dikumpulkan di satu tempat supaya laporan audit
 * (Tahap 3B) tidak perlu menebak ejaan yang dipakai di tiap controller.
 */
final class AuditAction
{
    public const LOGIN_SUCCESS = 'auth.login.success';
    public const LOGIN_FAILED = 'auth.login.failed';
    public const LOGIN_BLOCKED_INACTIVE = 'auth.login.blocked_inactive';
    public const LOGOUT = 'auth.logout';

    public const SESSION_CREATED = 'session.created';

    /** Timer mulai jalan. Terpisah dari SESSION_CREATED karena Prepaid
     *  dibuat lebih dulu dan baru aktif setelah dibayar. */
    public const SESSION_ACTIVATED = 'session.activated';

    public const SESSION_EXTENDED = 'session.extended';

    /** PRD §24 mewajibkan audit untuk setiap payment. */
    public const PAYMENT_CONFIRMED = 'payment.confirmed';

    public const SESSION_SWAPPED = 'session.swapped';
    public const SESSION_CANCELLED = 'session.cancelled';
    public const SESSION_CHECKOUT = 'session.checkout';

    public const FNB_ORDER_CREATED = 'fnb.order.created';
    public const FNB_STATUS_CHANGED = 'fnb.order.status_changed';

    public const SHIFT_OPENED = 'shift.opened';
    public const SHIFT_CLOSED = 'shift.closed';

    /** DEC-027 — operator boleh mendaftarkan, tapi tidak boleh tidak terlacak. */
    public const CUSTOMER_CREATED = 'customer.created';
    public const MEMBERSHIP_CREATED = 'customer.membership.created';

    /** DEC-026 — saldo member. Wajib diaudit: ini uang customer. */
    public const CREDIT_EARNED = 'customer.credit.earned';
    public const CREDIT_USED = 'customer.credit.used';
}
