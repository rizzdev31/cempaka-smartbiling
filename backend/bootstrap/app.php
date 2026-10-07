<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\EnforceIdempotency;
use App\Http\Middleware\ServerTime;
use App\Support\Api\ApiResponse;
use App\Support\Api\ErrorCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1', // API.md §1
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ServerTime dipasang GLOBAL dan paling luar, bukan di grup 'api'.
        //
        // Middleware grup hanya jalan kalau route-nya ketemu — jadi 404 dari
        // URL yang salah ketik tidak akan membawa `X-Server-Time`. Client yang
        // kehilangan offset ikut salah menampilkan timer, dan DEC-003 meminta
        // header ini ada di SEMUA response. Global juga membuat response
        // idempotent yang diputar ulang mendapat server_time baru, bukan yang
        // tersimpan saat request pertama.
        $middleware->prepend(ServerTime::class);

        $middleware->alias([
            'idempotency' => EnforceIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Satu tempat yang memetakan exception -> `error.code` kontrak (API.md §11).
        // Controller tidak boleh membentuk response error sendiri.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return match (true) {
                $e instanceof ApiException => ApiResponse::error(
                    $e->errorCode,
                    $e->getMessage(),
                    $e->status,
                    $e->details,
                ),

                $e instanceof ValidationException => ApiResponse::error(
                    ErrorCode::VALIDATION_FAILED,
                    'Data yang dikirim tidak valid.',
                    422,
                    $e->errors(),
                ),

                $e instanceof AuthenticationException => ApiResponse::error(
                    ErrorCode::UNAUTHENTICATED,
                    'Sesi login tidak valid atau sudah kedaluwarsa.',
                    401,
                ),

                $e instanceof AuthorizationException => ApiResponse::error(
                    ErrorCode::FORBIDDEN,
                    'Anda tidak punya akses untuk tindakan ini.',
                    403,
                ),

                $e instanceof TooManyRequestsHttpException => ApiResponse::error(
                    ErrorCode::TOO_MANY_ATTEMPTS,
                    'Terlalu banyak permintaan. Coba lagi sebentar.',
                    429,
                ),

                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => ApiResponse::error(
                    ErrorCode::NOT_FOUND,
                    'Data tidak ditemukan.',
                    404,
                ),

                // Sisa HttpException (405, 403 dari abort(), dll) tetap
                // memakai status aslinya supaya tidak dilaporkan 500.
                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    ErrorCode::SERVER_ERROR,
                    'Permintaan tidak dapat diproses.',
                    $e->getStatusCode(),
                ),

                default => ApiResponse::error(
                    ErrorCode::SERVER_ERROR,
                    'Terjadi kesalahan di server.',
                    500,
                    // Detail hanya saat debug — jangan membocorkan isi
                    // exception ke tablet operator di produksi.
                    config('app.debug') ? ['exception' => $e->getMessage()] : [],
                ),
            };
        });
    })->create();
