<?php

namespace App\Support\Auth;

/**
 * Hasil pemeriksaan kredensial, tanpa memutuskan bentuk response-nya.
 *
 * Sengaja TIDAK melempar exception: API harus menjawab JSON `error.code`,
 * Admin Web harus kembali ke form dengan pesan. Kalau pemeriksanya sudah
 * melempar, salah satu dari keduanya terpaksa menangkap exception milik
 * permukaan lain.
 */
enum CredentialOutcome
{
    case OK;

    /** Username tidak ada ATAU password salah — sengaja tidak dibedakan. */
    case INVALID;

    /** Kredensial benar, tapi akunnya dinonaktifkan (PRD §6). */
    case INACTIVE;
}
