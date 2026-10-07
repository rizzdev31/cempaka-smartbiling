<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Bentuk response & server-time — API.md §1, §2, DEC-003.
 */
class ResponseEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_mengembalikan_bentuk_data_dan_meta(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.database', 'ok')
            ->assertJsonStructure([
                'data' => ['status', 'version', 'database', 'broadcast'],
                'meta' => ['server_time'],
            ]);
    }

    public function test_server_time_ada_di_header_dan_body_dengan_nilai_sama(): void
    {
        $response = $this->getJson('/api/v1/health');

        $header = $response->headers->get('X-Server-Time');

        $this->assertNotNull($header, 'X-Server-Time wajib ada di setiap response.');
        $this->assertSame($header, $response->json('meta.server_time'));

        // ISO-8601 UTC dengan Z (API.md §1) — bukan offset +07:00.
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $header,
        );
    }

    public function test_server_time_tetap_ada_pada_response_error(): void
    {
        // Kalau client kehilangan offset saat error, timer di tablet/TV
        // ikut salah. Karena itu header wajib ada juga di jalur gagal.
        $response = $this->getJson('/api/v1/endpoint-yang-tidak-ada');

        $response->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonStructure(['error' => ['code', 'message'], 'meta' => ['server_time']]);

        $this->assertNotNull($response->headers->get('X-Server-Time'));
    }

    public function test_error_validasi_memakai_kode_kontrak_dan_details_per_field(): void
    {
        Route::post('/api/v1/_test/validate', fn () => request()->validate([
            'duration_minutes' => ['required', 'integer'],
        ]));

        $response = $this->postJson('/api/v1/_test/validate', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['duration_minutes']]]);
    }

    public function test_error_tak_terduga_tidak_membocorkan_isi_exception_saat_debug_mati(): void
    {
        config(['app.debug' => false]);

        Route::get('/api/v1/_test/boom', fn () => throw new \RuntimeException('kredensial rahasia'));

        $response = $this->getJson('/api/v1/_test/boom');

        $response->assertStatus(500)
            ->assertJsonPath('error.code', 'SERVER_ERROR')
            ->assertJsonMissing(['kredensial rahasia']);
    }
}
