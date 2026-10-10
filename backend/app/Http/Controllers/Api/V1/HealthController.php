<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * `GET /api/v1/health` — API.md §5.
 *
 * Tanpa auth: dipakai tablet dan TV untuk tes jaringan sebelum ada token
 * apa pun (TEST-PLAN-SABTU.md N2/N3).
 *
 * Juga menjadi dasar **penemuan server otomatis**: client yang memindai
 * jaringan memanggil alamat ini dan memastikan `app` cocok sebelum
 * memakainya. Tanpa penanda itu, scanner bisa menemukan layanan lain yang
 * kebetulan hidup di port 8000 dan mengiranya server billing.
 */
class HealthController
{
    /**
     * Penanda tetap. JANGAN diubah — client memakainya untuk memastikan
     * alamat yang ditemukan benar-benar server billing.
     */
    public const APP_ID = 'cempaka-smart-billing';

    public function __invoke(): JsonResponse
    {
        return ApiResponse::data([
            'app' => self::APP_ID,
            /*
             * Nama rental, dari APP_NAME. DEC-018 menetapkan satu server
             * untuk satu rental, jadi ini yang ditampilkan saat teknisi
             * memilih server: "Amor Gaming Space", bukan deretan IP.
             */
            'instance' => config('app.name'),
            'status' => 'ok',
            'version' => config('app.api_version'),
            'database' => $this->databaseStatus(),
            /*
             * Driver yang DIKONFIGURASI, bukan klaim bahwa broadcast-nya
             * sampai. Mengatakan "ok" di sini akan menyembunyikan kegagalan
             * yang justru paling sulit disadari — event yang menumpuk diam
             * di tabel `jobs` karena `queue:work` tidak jalan.
             *
             * Cara memeriksa yang sebenarnya ada di backend/README.md.
             */
            'broadcast' => config('broadcasting.default'),
        ]);
    }

    private function databaseStatus(): string
    {
        try {
            DB::connection()->getPdo();

            return 'ok';
        } catch (Throwable) {
            return 'error';
        }
    }
}
