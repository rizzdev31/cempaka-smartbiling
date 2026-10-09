<?php

namespace Tests\Feature\Realtime;

use App\Enums\SessionItemType;
use App\Events\SessionUpdated;
use App\Jobs\BroadcastSessionUpdate;
use App\Models\BillingSession;
use App\Support\Realtime\SessionUpdateBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * Debounce `session.updated` — REALTIME.md §7.
 *
 * Yang diuji bukan "broadcast terkirim", tapi dua sifat yang membedakan
 * debounce benar dari throttle naif:
 *
 * 1. Perubahan beruntun hanya menghasilkan SATU broadcast.
 * 2. Broadcast itu membawa keadaan TERBARU — tidak ada perubahan yang hilang.
 *
 * Sifat kedua yang paling penting. Throttle naif memenuhi sifat pertama tapi
 * bisa membuang perubahan terakhir, dan tablet lalu menampilkan tagihan basi.
 */
class SessionUpdateDebounceTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    private function makeSession(): BillingSession
    {
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);

        $this->actingAs($this->operator(), 'sanctum');
        $this->travelTo(Carbon::parse('2026-10-10T07:00:00Z'));

        $id = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $station->id,
                'package_id' => $package->id,
                'mode' => 'POSTPAID',
            ])->json('data.id');

        return BillingSession::findOrFail($id);
    }

    public function test_perubahan_beruntun_hanya_menjadwalkan_satu_broadcast(): void
    {
        $session = $this->makeSession();
        Queue::fake();

        SessionUpdateBroadcaster::schedule($session, ['items']);
        SessionUpdateBroadcaster::schedule($session, ['totals']);
        SessionUpdateBroadcaster::schedule($session, ['status']);

        Queue::assertPushed(BroadcastSessionUpdate::class, 1);
    }

    public function test_sesi_berbeda_tidak_saling_membungkam(): void
    {
        // Debounce-nya per sesi, bukan global. Kalau global, station kedua
        // tidak akan pernah dikabarkan selama station pertama ramai.
        $pertama = $this->makeSession();

        $kedua = BillingSession::query()->create($pertama->only([
            'station_id', 'package_id', 'package_name', 'package_duration_minutes',
            'package_price', 'hourly_rate', 'mode', 'status',
        ]) + ['code' => 'S-TEST-0002']);

        Queue::fake();

        SessionUpdateBroadcaster::schedule($pertama, ['totals']);
        SessionUpdateBroadcaster::schedule($kedua, ['totals']);

        Queue::assertPushed(BroadcastSessionUpdate::class, 2);
    }

    public function test_broadcast_membawa_keadaan_terbaru_bukan_potret_lama(): void
    {
        /*
         * Inti dari seluruh fitur ini. Perubahan dijadwalkan, LALU tagihannya
         * berubah, baru job berjalan. Yang terkirim harus angka yang baru.
         *
         * Job menerima id dan membaca ulang dari database — kalau ia membawa
         * objek sesi, yang terkirim adalah potret saat dijadwalkan dan
         * perubahan sesudahnya hilang.
         */
        $session = $this->makeSession();

        /*
         * Queue::fake() menahan job supaya tidak langsung jalan. Di test,
         * QUEUE_CONNECTION=sync menjalankan job seketika dan mengabaikan
         * delay — tanpa ditahan, jendela debounce-nya tidak pernah ada.
         */
        Queue::fake();
        Event::fake([SessionUpdated::class]);

        SessionUpdateBroadcaster::schedule($session, ['totals']);

        // Tagihan berubah SETELAH dijadwalkan, sebelum job jalan.
        $session->items()->create([
            'type' => SessionItemType::FNB,
            'name' => 'Teh Manis',
            'qty' => 1,
            'unit_price' => 7000,
            'subtotal' => 7000,
            'is_paid' => false,
        ]);

        (new BroadcastSessionUpdate($session->id))->handle();

        Event::assertDispatched(SessionUpdated::class, function (SessionUpdated $event) {
            return $event->session->totals()->fnb === 7000;
        });
    }

    public function test_petunjuk_changed_digabung_dari_semua_perubahan(): void
    {
        // Perubahan kedua tidak menjadwalkan job baru, tapi petunjuknya tetap
        // harus ikut — kalau tidak, animasi highlight di tablet melewatkan
        // bagian yang berubah.
        $session = $this->makeSession();
        Queue::fake();
        Event::fake([SessionUpdated::class]);

        SessionUpdateBroadcaster::schedule($session, ['items']);
        SessionUpdateBroadcaster::schedule($session, ['totals']);

        (new BroadcastSessionUpdate($session->id))->handle();

        Event::assertDispatched(SessionUpdated::class, function (SessionUpdated $event) {
            return in_array('items', $event->changed, true)
                && in_array('totals', $event->changed, true);
        });
    }

    public function test_setelah_job_jalan_perubahan_berikutnya_dijadwalkan_lagi(): void
    {
        // Penanda harus dilepas saat job berjalan. Kalau tidak, satu sesi
        // hanya pernah dikabarkan sekali seumur hidupnya.
        $session = $this->makeSession();

        Queue::fake();
        SessionUpdateBroadcaster::schedule($session, ['totals']);
        Queue::assertPushed(BroadcastSessionUpdate::class, 1);

        (new BroadcastSessionUpdate($session->id))->handle();

        Queue::fake();
        SessionUpdateBroadcaster::schedule($session, ['status']);
        Queue::assertPushed(BroadcastSessionUpdate::class, 1);
    }

    public function test_sesi_yang_hilang_tidak_membuat_job_gagal(): void
    {
        // Sesi bisa saja terhapus antara penjadwalan dan eksekusi. Tidak ada
        // yang perlu dikabarkan, dan itu bukan kegagalan.
        $session = $this->makeSession();
        Queue::fake();
        Event::fake([SessionUpdated::class]);

        SessionUpdateBroadcaster::schedule($session, ['status']);
        $id = $session->id;
        $session->delete();

        (new BroadcastSessionUpdate($id))->handle();

        Event::assertNotDispatched(SessionUpdated::class);
    }

    public function test_satu_order_fnb_hanya_memicu_satu_broadcast(): void
    {
        // Lewat endpoint sungguhan, bukan memanggil broadcaster langsung.
        $session = $this->makeSession();
        $teh = $this->fnbProduct();

        Queue::fake();

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$session->id}/fnb/orders", [
                'items' => [['product_id' => $teh->id, 'qty' => 2]],
            ])->assertCreated();

        Queue::assertPushed(BroadcastSessionUpdate::class, 1);
    }
}
