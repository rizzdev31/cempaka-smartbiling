<?php

namespace App\Exceptions;

use App\Support\Api\ErrorCode;
use RuntimeException;

/**
 * Exception yang membawa `error.code` kontrak (API.md §11).
 *
 * Dipakai untuk semua penolakan yang disengaja: konflik state, policy
 * billing, idempotency. Handler di `bootstrap/app.php` yang mengubahnya
 * jadi JSON — jangan membentuk response error langsung di controller.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 409,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function conflict(string $code, string $message, array $details = []): self
    {
        return new self($code, $message, 409, $details);
    }

    public static function unprocessable(string $code, string $message, array $details = []): self
    {
        return new self($code, $message, 422, $details);
    }

    public static function forbidden(string $message = 'Anda tidak punya akses untuk tindakan ini.'): self
    {
        return new self(ErrorCode::FORBIDDEN, $message, 403);
    }

    public static function notFound(string $message = 'Data tidak ditemukan.'): self
    {
        return new self(ErrorCode::NOT_FOUND, $message, 404);
    }
}
