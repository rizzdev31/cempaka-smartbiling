<?php

namespace App\Http\Controllers\Web;

use App\Enums\FnbOrderStatus;
use App\Exceptions\ApiException;
use App\Models\FnbOrder;
use App\Models\FnbProduct;
use App\Models\Package;
use App\Models\Shift;
use App\Services\FnbService;
use App\Services\MasterDataService;
use App\Services\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Aksi ubah dari Admin Web — DEC-044.
 *
 * ## Satu aturan yang mengikat seluruh berkas ini
 *
 * Setiap method di sini **memanggil Service yang sama dengan API**. Tidak
 * ada satu pun aturan bisnis yang ditulis ulang.
 *
 * Alasannya bukan kerapian. `MasterDataService::updateFnbProduct()`
 * memeriksa bahwa hanya owner yang boleh mengubah `price` (DEC-020) dan
 * mencatat nilai sebelum/sesudah ke audit log (PRD §24). Controller web yang
 * menulis `$product->update()` sendiri akan melewati keduanya — dan
 * kehilangannya tidak menimbulkan error apa pun, hanya lubang yang baru
 * ketahuan saat ada yang perlu diaudit.
 *
 * Yang berbeda dari API hanyalah bentuk jawabannya: JSON di sana, kembali ke
 * halaman dengan pesan di sini.
 */
class AdminActionController
{
    public function __construct(
        private readonly MasterDataService $masterData,
        private readonly FnbService $fnb,
        private readonly ShiftService $shifts,
    ) {}

    /**
     * Jalankan aksi Service, ubah penolakannya jadi pesan di form.
     *
     * `ApiException` hanya dirender sebagai JSON untuk `api/*`
     * (`bootstrap/app.php`). Dibiarkan lewat di web, ia jadi halaman error
     * 500 tanpa penjelasan — padahal isinya justru kalimat yang perlu
     * dibaca: "Hanya pemilik shift atau admin yang boleh menutup shift ini."
     *
     * Hanya `ApiException` yang ditangkap. Menangkap `Throwable` akan
     * menelan bug sungguhan dan menampilkannya sebagai kesalahan pengisian
     * form — kesalahan dialihkan ke orang yang tidak melakukannya.
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

    // ── Harga & paket ─────────────────────────────────────────────────

    public function updatePackage(Request $request, Package $package): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'price' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Checkbox yang tidak dicentang TIDAK dikirim browser. Tanpa baris
        // ini, menonaktifkan paket mustahil — nilainya selalu terbaca "tetap".
        $data['is_active'] = $request->boolean('is_active');

        $this->jalankan(fn () => $this->masterData->updatePackage($package, $data, $request->user()), 'price');

        return back()->with('sukses', "Paket “{$package->name}” diperbarui. Berlaku untuk sesi berikutnya.");
    }

    public function storePackage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'station_type_id' => ['required', 'uuid', 'exists:station_types,id'],
            'name' => ['required', 'string', 'max:64'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'price' => ['required', 'integer', 'min:0'],
        ]);

        $package = $this->jalankan(fn () => $this->masterData->createPackage($data, $request->user()), 'price');

        return back()->with('sukses', "Paket “{$package->name}” ditambahkan.");
    }

    public function updateFnbProduct(Request $request, FnbProduct $product): RedirectResponse
    {
        $data = $request->validate([
            'price' => ['required', 'integer', 'min:0'],
            /*
             * `stock` boleh kosong, dan itu BERBEDA dari nol: kosong berarti
             * tidak dilacak (es batu, sambal), nol berarti habis. Form
             * mengirim string kosong untuk "tidak dilacak".
             */
            'stock' => ['nullable', 'integer', 'min:0'],
            'is_available' => ['nullable', 'boolean'],
        ]);

        $input = [
            'stock' => $request->input('stock') === '' ? null : $data['stock'],
            'is_available' => $request->boolean('is_available'),
        ];

        /*
         * `price` hanya dikirim kalau BERUBAH.
         *
         * Service menolak perubahan harga dari non-owner. Kalau admin
         * mengubah stok saja, mengirim `price` yang nilainya sama persis
         * tetap akan ditolak — admin jadi tidak bisa menandai "Mie Goreng
         * habis", yang justru pekerjaan hariannya.
         */
        if ((int) $data['price'] !== (int) $product->price) {
            $input['price'] = (int) $data['price'];
        }

        $this->jalankan(fn () => $this->masterData->updateFnbProduct($product, $input, $request->user()), 'price');

        return back()->with('sukses', "“{$product->name}” diperbarui.");
    }

    // ── F&B ───────────────────────────────────────────────────────────

    public function updateFnbStatus(Request $request, FnbOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(FnbOrderStatus::class)],
        ]);

        $this->jalankan(
            fn () => $this->fnb->updateStatus($order, FnbOrderStatus::from($data['status']), $request->user()),
            'status',
        );

        return back()->with('sukses', 'Status pesanan diperbarui.');
    }

    // ── Shift ─────────────────────────────────────────────────────────

    public function closeShift(Request $request, Shift $shift): RedirectResponse
    {
        $data = $request->validate([
            'closing_cash' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['closing_cash' => 'kas akhir']);

        /*
         * Shift ditutup dari web hanya kalau operatornya lupa. Jalur
         * normalnya tetap dari tablet — orang yang memegang uangnya yang
         * harus menghitungnya.
         *
         * Service yang menentukan siapa boleh: pemilik shift, atau
         * admin-level. Dicegat di sini supaya pesannya menjelaskan, bukan
         * halaman 403 kosong.
         */
        $this->jalankan(fn () => $this->shifts->close(
            $shift,
            (int) $data['closing_cash'],
            $data['note'] ?? null,
            $request->user(),
        ), 'closing_cash');

        return back()->with('sukses', 'Shift ditutup. Selisih kasnya tercatat di Audit.');
    }
}
