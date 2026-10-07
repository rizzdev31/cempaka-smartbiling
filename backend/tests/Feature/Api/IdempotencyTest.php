<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Idempotency — API.md §3. Pertahanan utama terhadap R06 (duplicate payment)
 * dan operator yang menekan tombol dua kali.
 */
class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    /** Berapa kali handler benar-benar dijalankan. */
    private int $calls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calls = 0;

        Route::post('/api/v1/_test/create', function () {
            $this->calls++;

            return response()->json(['data' => ['id' => 'sesi-'.$this->calls]], 201);
        })->middleware('idempotency');
    }

    private function send(string $key, array $body = ['station_id' => 'ST01']): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/_test/create', $body);
    }

    public function test_tanpa_header_ditolak_400(): void
    {
        $this->postJson('/api/v1/_test/create', [])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');

        $this->assertSame(0, $this->calls);
    }

    public function test_header_bukan_uuid_ditolak_400(): void
    {
        $this->send('bukan-uuid')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');
    }

    public function test_key_sama_body_sama_mengembalikan_response_identik_tanpa_membuat_data_baru(): void
    {
        $key = (string) Str::uuid();

        $first = $this->send($key)->assertStatus(201);
        $second = $this->send($key)->assertStatus(201);

        // Inti aturannya: handler hanya jalan sekali.
        $this->assertSame(1, $this->calls);
        $this->assertSame($first->json('data'), $second->json('data'));

        $this->assertNull($first->headers->get('X-Idempotent-Replay'));
        $this->assertSame('true', $second->headers->get('X-Idempotent-Replay'));
    }

    public function test_replay_tetap_mendapat_server_time_baru_bukan_yang_tersimpan(): void
    {
        $key = (string) Str::uuid();

        $first = $this->send($key);
        $this->travel(5)->seconds();
        $second = $this->send($key);

        $this->assertNotSame(
            $first->json('meta.server_time'),
            $second->json('meta.server_time'),
            'server_time pada replay harus waktu sekarang — client memakainya untuk offset timer.',
        );
    }

    public function test_key_sama_body_beda_ditolak_409(): void
    {
        $key = (string) Str::uuid();

        $this->send($key, ['station_id' => 'ST01'])->assertStatus(201);

        $this->send($key, ['station_id' => 'ST03'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');

        $this->assertSame(1, $this->calls);
    }

    public function test_key_berbeda_membuat_data_baru(): void
    {
        $this->send((string) Str::uuid())->assertStatus(201);
        $this->send((string) Str::uuid())->assertStatus(201);

        $this->assertSame(2, $this->calls);
    }

    public function test_response_gagal_tidak_disimpan_sehingga_boleh_dicoba_ulang(): void
    {
        Route::post('/api/v1/_test/gagal', function () {
            $this->calls++;

            return response()->json(['error' => ['code' => 'VALIDATION_FAILED', 'message' => 'x']], 422);
        })->middleware('idempotency');

        $key = (string) Str::uuid();

        $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/_test/gagal', [])->assertStatus(422);
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/_test/gagal', [])->assertStatus(422);

        $this->assertSame(2, $this->calls, 'Response gagal tidak boleh dikunci — client harus bisa memperbaiki lalu kirim ulang.');
        $this->assertSame(0, DB::table('idempotency_keys')->count());
    }

    public function test_retensi_disimpan_24_jam(): void
    {
        $this->send((string) Str::uuid());

        $row = DB::table('idempotency_keys')->first();

        $this->assertSame(
            24 * 60,
            (int) round((strtotime($row->expires_at) - strtotime($row->created_at)) / 60),
        );
    }
}
