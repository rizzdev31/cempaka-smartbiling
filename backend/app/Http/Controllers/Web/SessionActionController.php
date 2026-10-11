<?php

namespace App\Http\Controllers\Web;

use App\Enums\PaymentMethod;
use App\Enums\SessionMode;
use App\Exceptions\ApiException;
use App\Models\BillingSession;
use App\Models\Shift;
use App\Services\CancelService;
use App\Services\CheckoutService;
use App\Services\ExtendService;
use App\Services\FnbService;
use App\Services\PaymentService;
use App\Services\SessionService;
use App\Services\ShiftService;
use App\Services\SwapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Operasi sesi dari Admin Web — DEC-045.
 *
 * ## Kenapa ini ada, padahal DEC-044 bilang tidak perlu
 *
 * DEC-044 membagi peran: Laravel mengatur, Flutter melayani. User kemudian
 * menegaskan bahwa Laravel adalah **owner dari semua fitur** dan harus punya
 * setiap akses, walaupun bukan dia yang berhadapan dengan customer. DEC-045
 * mencatat pembalikan itu.
 *
 * Konsekuensi yang perlu disadari: sekarang ADA DUA PINTU ke operasi yang
 * memegang uang. Itu aman hanya karena keduanya memanggil Service yang sama
 * — aturan billing, idempotency state machine, broadcast, dan audit semuanya
 * hidup di sana, bukan di controller. Begitu salah satu pintu mulai menulis
 * aturannya sendiri, keduanya akan berbeda dan yang berbeda adalah uang.
 *
 * ## Yang TIDAK disalin dari API
 *
 * Header `Idempotency-Key`. Di tablet ia mencegah duplikat saat jaringan
 * putus di tengah kirim; di browser, form yang dikirim dua kali menghasilkan
 * dua POST yang berbeda niatnya. Pengaman di sini adalah state machine —
 * sesi yang sudah COMPLETED menolak checkout kedua — bukan kunci idempoten.
 */
class SessionActionController
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly PaymentService $payments,
        private readonly ExtendService $extends,
        private readonly SwapService $swaps,
        private readonly CheckoutService $checkouts,
        private readonly CancelService $cancels,
        private readonly FnbService $fnb,
        private readonly ShiftService $shifts,
    ) {}

    /**
     * Terjemahkan penolakan Service jadi pesan di form.
     *
     * `ApiException` hanya dirender JSON untuk `api/*`. Dibiarkan lewat di
     * web, ia jadi halaman 500 kosong — padahal isinya justru kalimat yang
     * perlu dibaca ("Sesi sudah berjalan", "Waktu sudah lewat").
     *
     * Hanya `ApiException` yang ditangkap: menangkap `Throwable` akan
     * menelan bug sungguhan dan menampilkannya sebagai kesalahan pengisian.
     *
     * @template T
     *
     * @param  callable(): T  $aksi
     * @return T
     */
    private function jalankan(callable $aksi, string $field)
    {
        try {
            return $aksi();
        } catch (ApiException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }

    // ── Sesi ──────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'station_id' => ['required', 'uuid', 'exists:stations,id'],
            'package_id' => ['required', 'uuid', 'exists:packages,id'],
            'mode' => ['required', Rule::enum(SessionMode::class)],
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
        ], [], ['station_id' => 'station', 'package_id' => 'paket']);

        /*
         * Satu sesi satu customer (DEC-008). Kalau member dipilih, nama
         * ketikan diabaikan — bukan digabung. Dua sumber nama untuk satu
         * sesi akan membuat struk dan riwayat member menyebut orang berbeda.
         */
        if (($data['customer_id'] ?? null) !== null) {
            $data['customer_name'] = null;
        }

        $session = $this->jalankan(
            fn () => $this->sessions->create($data, $request->user()),
            'station_id',
        );

        return redirect()
            ->route('admin.session', $session)
            ->with('sukses', "Sesi {$session->code} dibuka di {$session->station?->code}.");
    }

    public function pay(Request $request, BillingSession $session): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['amount' => 'jumlah']);

        $this->jalankan(fn () => $this->payments->record($session, $data, $request->user()), 'amount');

        return back()->with('sukses', 'Pembayaran dicatat.');
    }

    public function extend(Request $request, BillingSession $session): RedirectResponse
    {
        $data = $request->validate([
            /*
             * Kelipatan 30 menit saja (DEC-007). Divalidasi di sini JUGA,
             * bukan hanya di Service: tombol cepat di layar hanya menawarkan
             * 30/60/120, tapi form bisa dikirim dengan angka lain, dan pesan
             * "harus kelipatan 30" lebih berguna di sebelah kolomnya
             * daripada sebagai penolakan setelah dikirim.
             */
            'duration_minutes' => ['required', 'integer', 'min:30', 'max:1440'],
        ], [
            'duration_minutes.min' => 'Extend minimal 30 menit.',
        ], ['duration_minutes' => 'durasi']);

        $hasil = $this->jalankan(
            fn () => $this->extends->extend($session, (int) $data['duration_minutes'], $request->user()),
            'duration_minutes',
        );

        $harga = $hasil['price'] ?? 0;

        return back()->with(
            'sukses',
            "Waktu ditambah {$data['duration_minutes']} menit · Rp ".number_format($harga, 0, ',', '.'),
        );
    }

    public function swap(Request $request, BillingSession $session): RedirectResponse
    {
        $data = $request->validate([
            'target_station_id' => ['required', 'uuid', 'exists:stations,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [], ['target_station_id' => 'station tujuan']);

        $this->jalankan(
            fn () => $this->swaps->swap($session, $data['target_station_id'], $data['reason'] ?? null, $request->user()),
            'target_station_id',
        );

        return back()->with('sukses', 'Sesi dipindahkan. Sisa waktunya ikut.');
    }

    public function checkout(Request $request, BillingSession $session): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'amount' => ['nullable', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:64'],
            'use_credit' => ['nullable', 'boolean'],
        ], [], ['amount' => 'jumlah bayar']);

        /*
         * Satu pembayaran dari form ini, bukan split.
         *
         * Split payment ada di API dan dipakai tablet, tempat customer
         * berdiri di depan kasir membagi tunai dan QRIS. Di web, checkout
         * biasanya dilakukan setelah kejadian — menambah UI split di sini
         * berarti merawat dua tempat untuk satu kasus yang jarang.
         *
         * Kalau tagihannya sudah lunas dari pembayaran sebelumnya,
         * `payments` dikirim kosong dan checkout tetap jalan.
         */
        $payments = [];

        if (($data['amount'] ?? null) !== null) {
            $payments[] = [
                'method' => $data['method'] ?? PaymentMethod::CASH->value,
                'amount' => (int) $data['amount'],
                'reference' => $data['reference'] ?? null,
            ];
        }

        $this->jalankan(
            fn () => $this->checkouts->checkout(
                $session,
                $payments,
                $request->boolean('use_credit'),
                $request->user(),
            ),
            'amount',
        );

        return back()->with('sukses', 'Checkout selesai. Struknya ada di bawah.');
    }

    public function cancel(Request $request, BillingSession $session): RedirectResponse
    {
        $data = $request->validate([
            // Alasan WAJIB di sini, walau API membolehkannya kosong.
            // Membatalkan sesi dari belakang meja tanpa alasan adalah
            // persis yang nanti perlu dijelaskan saat laporan tidak cocok.
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Tulis alasan pembatalan — ini yang akan dibaca saat audit.',
        ], ['reason' => 'alasan']);

        $this->jalankan(
            fn () => $this->cancels->cancel($session, $data['reason'], $request->user()),
            'reason',
        );

        return back()->with('sukses', 'Sesi dibatalkan.');
    }

    // ── F&B ───────────────────────────────────────────────────────────

    public function storeFnbOrder(Request $request, BillingSession $session): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:fnb_products,id'],
            /*
             * `min:0`, BUKAN `min:1`.
             *
             * Form menampilkan seluruh menu dengan kolom jumlah, jadi baris
             * yang tidak dipesan ikut terkirim dengan nilai 0. Validasi
             * berjalan SEBELUM penyaringan di bawah — dengan `min:1`, setiap
             * pesanan ditolak karena menu yang justru tidak dipesan.
             */
            'items.*.qty' => ['required', 'integer', 'min:0', 'max:99'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'items.required' => 'Pilih minimal satu menu.',
        ]);

        /*
         * Baris dengan qty kosong dibuang sebelum dikirim. Form menampilkan
         * SELURUH menu dengan kolom qty; tanpa penyaringan ini, setiap
         * pesanan akan berisi puluhan baris nol.
         *
         * Harga TIDAK ikut dikirim — selalu dari server (CLAUDE.md §4).
         */
        $items = collect($data['items'])
            ->filter(fn ($i) => (int) ($i['qty'] ?? 0) > 0)
            ->map(fn ($i) => ['product_id' => $i['product_id'], 'qty' => (int) $i['qty']])
            ->values()
            ->all();

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Isi jumlah untuk minimal satu menu.']);
        }

        $this->jalankan(
            fn () => $this->fnb->createOrder($session, $items, $data['note'] ?? null, $request->user()),
            'items',
        );

        return back()->with('sukses', 'Pesanan F&B dibuat.');
    }

    // ── Shift ─────────────────────────────────────────────────────────

    public function openShift(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'opening_cash' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['opening_cash' => 'modal awal']);

        $this->jalankan(
            fn () => $this->shifts->open($request->user(), (int) $data['opening_cash'], $data['note'] ?? null),
            'opening_cash',
        );

        return back()->with('sukses', 'Shift dibuka.');
    }

    public function closeShift(Request $request, Shift $shift): RedirectResponse
    {
        $data = $request->validate([
            'closing_cash' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['closing_cash' => 'kas akhir']);

        $this->jalankan(fn () => $this->shifts->close(
            $shift,
            (int) $data['closing_cash'],
            $data['note'] ?? null,
            $request->user(),
        ), 'closing_cash');

        return back()->with('sukses', 'Shift ditutup. Selisih kasnya tercatat di Audit.');
    }
}
