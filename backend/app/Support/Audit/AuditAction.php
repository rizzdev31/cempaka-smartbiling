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
}
