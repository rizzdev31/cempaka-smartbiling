<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CashExpense;
use App\Support\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * Pengeluaran kasir dari laci — DEC-046 (dulu OD-029).
 *
 * ## Lubang yang ditutup berkas ini
 *
 * Rekonsiliasi laci seharusnya `Modal + Tunai − Pengeluaran`, dan suku
 * terakhirnya tidak pernah dibangun. Akibatnya kasir yang mengambil
 * Rp 20.000 dari laci untuk beli galon membuat sistem melaporkan kas
 * KURANG Rp 20.000 — fitur anti-bocor yang justru jadi sumber tuduhan
 * palsu.
 *
 * Yang diuji di sini bukan "pengeluaran bisa disimpan", tapi bahwa
 * angkanya benar-benar IKUT dalam hitungan kas. Baris yang tersimpan tapi
 * tidak dikurangkan tidak memperbaiki apa pun.
 */
class CashExpenseTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    private function open(int $openingCash = 200000): string
    {
        return $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/shifts/open', ['opening_cash' => $openingCash])
            ->json('data.id');
    }

    private function catat(string $shiftId, array $body = []): TestResponse
    {
        return $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/shifts/{$shiftId}/expenses", $body + [
                'amount' => 20000,
                'category' => 'Galon & air',
                'note' => 'beli galon 2 buah',
            ]);
    }

    public function test_pengeluaran_mengurangi_kas_yang_seharusnya_ada(): void
    {
        $this->actingAs($this->operator(), 'sanctum');
        $shiftId = $this->open(200000);

        /*
         * Response-nya menyertakan shift yang sudah diperbarui: pertanyaan
         * berikutnya setelah mencatat selalu "jadi berapa sekarang isi
         * laci", dan tablet tidak boleh menghitungnya sendiri.
         */
        $this->catat($shiftId)
            ->assertCreated()
            ->assertJsonPath('data.amount', 20000)
            ->assertJsonPath('data.shift.summary.expense', 20000)
            ->assertJsonPath('data.shift.expected_cash', 180000);
    }

    public function test_kas_seharusnya_memperhitungkan_tunai_masuk_dan_pengeluaran_sekaligus(): void
    {
        /*
         * Rumus lengkapnya diuji sekali dengan ketiga sukunya bernilai
         * berbeda. Dua suku saja bisa lulus walaupun tandanya tertukar.
         */
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);

        $this->actingAs($this->operator(), 'sanctum');
        $shiftId = $this->open(200000);

        $sessionId = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $station->id,
                'package_id' => $package->id,
                'mode' => 'PREPAID',
            ])->json('data.id');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$sessionId}/payments", ['method' => 'CASH', 'amount' => 12000])
            ->assertCreated();

        // QRIS tidak boleh ikut: uangnya tidak pernah masuk laci.
        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$sessionId}/payments", [
                'method' => 'QRIS_STATIC', 'amount' => 8000, 'reference' => 'TRX-1',
            ])->assertCreated();

        $this->catat($shiftId, ['amount' => 15000])->assertCreated();

        // 200.000 + 12.000 − 15.000 = 197.000. QRIS 8.000 di luar hitungan.
        $this->getJson('/api/v1/shifts/current')
            ->assertOk()
            ->assertJsonPath('data.expected_cash', 197000);
    }

    public function test_selisih_saat_tutup_shift_memakai_rumus_yang_sama(): void
    {
        /*
         * Ini inti DEC-046.
         *
         * Angka di layar dan angka yang dibandingkan saat tutup shift harus
         * dari satu rumus. Kalau halaman mengurangi pengeluaran tapi
         * `close()` tidak, kasir yang menyetor uang sesuai layar justru
         * tercatat punya selisih — dan selisih itu tidak akan pernah bisa
         * dijelaskan.
         */
        $this->actingAs($this->operator(), 'sanctum');
        $shiftId = $this->open(200000);

        $this->catat($shiftId, ['amount' => 20000])->assertCreated();

        // Laci berisi 180.000, sama dengan yang seharusnya. Selisih = 0.
        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/shifts/{$shiftId}/close", ['closing_cash' => 180000])
            ->assertOk();

        $audit = AuditLog::query()
            ->where('action', AuditAction::SHIFT_CLOSED)
            ->first();

        $this->assertSame(180000, $audit->after['expected_cash']);
        $this->assertSame(0, $audit->after['difference']);
    }

    public function test_tidak_bisa_dicatat_ke_shift_yang_sudah_ditutup(): void
    {
        /*
         * Pengeluaran yang ditempelkan ke shift yang sudah ditutup mengubah
         * selisih kas yang sudah ditandatangani orang lain — pintu belakang
         * untuk menutupi selisih setelah ketahuan.
         */
        $this->actingAs($this->operator(), 'sanctum');
        $shiftId = $this->open();

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/shifts/{$shiftId}/close", ['closing_cash' => 200000])
            ->assertOk();

        $this->catat($shiftId)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SHIFT_NOT_OPEN');

        $this->assertSame(0, CashExpense::query()->count());
    }

    public function test_operator_lain_tidak_boleh_mencatat_di_shift_orang(): void
    {
        // Orang yang tidak memegang laci itu tidak boleh mengubah isinya.
        $this->actingAs($this->operator(['name' => 'Budi']), 'sanctum');
        $shiftId = $this->open();

        $this->actingAs($this->operator(['name' => 'Siti']), 'sanctum');

        $this->catat($shiftId)->assertStatus(403);

        $this->assertSame(0, CashExpense::query()->count());
    }

    public function test_admin_boleh_mencatat_di_shift_orang(): void
    {
        // Aturan yang sama dengan menutup shift: admin bisa membereskan
        // tanpa menunggu operatornya kembali.
        $this->actingAs($this->operator(), 'sanctum');
        $shiftId = $this->open();

        $this->actingAs($this->operator(['name' => 'Bos', 'role' => UserRole::ADMIN]), 'sanctum');

        $this->catat($shiftId)->assertCreated();
    }

    public function test_keterangan_wajib_dan_tidak_boleh_satu_huruf(): void
    {
        /*
         * Pengeluaran tanpa keterangan persis yang tidak bisa dijelaskan
         * saat laporan diperiksa. Batas tiga huruf menutup jalan keluar
         * termudah — mengetik "x" demi melewati form.
         */
        $this->actingAs($this->operator(), 'sanctum');
        $shiftId = $this->open();

        $this->catat($shiftId, ['note' => ''])->assertStatus(422);
        $this->catat($shiftId, ['note' => 'x'])->assertStatus(422);

        $this->assertSame(0, CashExpense::query()->count());
    }

    public function test_nominal_nol_ditolak(): void
    {
        // Baris Rp 0 hanya menambah panjang laporan tanpa mengubah apa pun.
        $this->actingAs($this->operator(), 'sanctum');

        $this->catat($this->open(), ['amount' => 0])->assertStatus(422);
    }

    public function test_kategori_di_luar_daftar_ditolak(): void
    {
        /*
         * Tanpa daftar tertutup, "es batu", "Es Batu", dan "esbatu" jadi
         * tiga kelompok berbeda di laporan — dan tidak ada yang
         * menyadarinya sampai laporannya dibaca berbulan-bulan kemudian.
         */
        $this->actingAs($this->operator(), 'sanctum');

        $this->catat($this->open(), ['category' => 'Rokok'])->assertStatus(422);
    }

    public function test_pengeluaran_tercatat_di_audit(): void
    {
        $this->actingAs($this->operator(['name' => 'Budi']), 'sanctum');
        $shiftId = $this->open();

        $this->catat($shiftId, ['amount' => 35000, 'note' => 'iuran sampah bulan ini'])
            ->assertCreated();

        $audit = AuditLog::query()
            ->where('action', AuditAction::EXPENSE_RECORDED)
            ->first();

        $this->assertNotNull($audit, 'Pengeluaran kasir WAJIB masuk audit — ini uang yang keluar dari laci.');
        $this->assertSame($shiftId, $audit->subject_id);
        $this->assertSame(35000, $audit->after['amount']);
        $this->assertSame('iuran sampah bulan ini', $audit->after['note']);
    }
}
