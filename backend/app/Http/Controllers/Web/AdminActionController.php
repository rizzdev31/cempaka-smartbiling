<?php

namespace App\Http\Controllers\Web;

use App\Enums\FnbOrderStatus;
use App\Exceptions\ApiException;
use App\Http\Requests\Api\V1\StoreExpenseRequest;
use App\Http\Requests\Api\V1\StoreFnbProductRequest;
use App\Http\Requests\Api\V1\StoreStationRequest;
use App\Http\Requests\Api\V1\StoreStationTypeRequest;
use App\Http\Requests\Api\V1\UpdateCustomerRequest;
use App\Http\Requests\Api\V1\UpdateStationRequest;
use App\Http\Requests\Api\V1\UpdateStationTypeRequest;
use App\Models\Customer;
use App\Models\FnbOrder;
use App\Models\FnbProduct;
use App\Models\Package;
use App\Models\Shift;
use App\Models\Station;
use App\Models\StationType;
use App\Services\CustomerService;
use App\Services\FnbService;
use App\Services\MasterDataService;
use App\Services\MembershipService;
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
        private readonly MembershipService $memberships,
        private readonly CustomerService $customers,
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

    /*
     |--------------------------------------------------------------------
     | Station & tipe konsol — DEC-048
     |--------------------------------------------------------------------
     |
     | Validasinya memakai FormRequest yang SAMA dengan API, bukan aturan
     | yang diketik ulang. Aturan `code` tanpa titik ada karena kode station
     | adalah nama channel realtime; aturan yang disalin akan melunak di
     | salah satu pintu tanpa ada yang menyadarinya.
     */

    public function storeStation(StoreStationRequest $request): RedirectResponse
    {
        $station = $this->jalankan(
            fn () => $this->masterData->createStation($request->validated(), $request->user()),
            'code',
        );

        return back()->with(
            'sukses',
            "Station {$station->code} ditambahkan. Kode pendaftaran TV-nya: {$station->enrollment_code}",
        );
    }

    public function updateStation(UpdateStationRequest $request, Station $station): RedirectResponse
    {
        $this->jalankan(
            fn () => $this->masterData->updateStation($station, $request->validated(), $request->user()),
            'code',
        );

        return back()->with('sukses', "Station {$station->fresh()->code} diperbarui.");
    }

    public function storeStationType(StoreStationTypeRequest $request): RedirectResponse
    {
        $type = $this->jalankan(
            fn () => $this->masterData->createStationType($request->validated(), $request->user()),
            'name',
        );

        return back()->with('sukses', "Tipe konsol “{$type->name}” ditambahkan. Buat paketnya di Harga & Paket.");
    }

    public function updateStationType(UpdateStationTypeRequest $request, StationType $type): RedirectResponse
    {
        /*
         * Checkbox yang tidak dicentang tidak dikirim browser, jadi
         * `is_active` harus dibaca eksplisit — kalau tidak, menonaktifkan
         * tipe mustahil dan tombolnya terlihat rusak.
         */
        $data = $request->validated() + ['is_active' => $request->boolean('is_active')];

        $this->jalankan(
            fn () => $this->masterData->updateStationType($type, $data, $request->user()),
            'name',
        );

        return back()->with('sukses', "Tipe konsol “{$type->fresh()->name}” diperbarui.");
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
            'hpp' => ['nullable', 'integer', 'min:0'],
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

        // HPP dikunci untuk owner juga (OD-033), jadi perlakuannya sama:
        // dikirim hanya kalau benar-benar berubah.
        $hppBaru = $request->input('hpp') === '' ? null : $data['hpp'] ?? null;
        $hppLama = $product->hpp === null ? null : (int) $product->hpp;

        if ($hppBaru !== $hppLama) {
            $input['hpp'] = $hppBaru;
        }

        $this->jalankan(fn () => $this->masterData->updateFnbProduct($product, $input, $request->user()), 'price');

        return back()->with('sukses', "“{$product->name}” diperbarui.");
    }

    // ── Customer & member ─────────────────────────────────────────────

    /**
     * Daftarkan customer baru dari web — DEC-044.
     *
     * Operator juga boleh melakukannya dari tablet (DEC-027). Yang di sini
     * untuk kasus yang berbeda: owner memasukkan member yang mendaftar lewat
     * telepon atau yang datanya telanjur ditulis di kertas.
     */
    public function storeCustomer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            /*
             * Nomor unik dan opsional. Memaksanya akan membuat orang
             * mengarang nomor demi melewati form, dan nomor karangan lebih
             * buruk daripada kosong — ia tidak bisa dicari DAN memblokir
             * nomor asli yang kebetulan sama.
             */
            'phone' => ['nullable', 'string', 'max:32', 'unique:customers,phone'],
            'note' => ['nullable', 'string', 'max:500'],
            'jadikan_member' => ['nullable', 'boolean'],
        ], [
            'phone.unique' => 'Nomor telepon ini sudah terdaftar atas nama lain.',
        ], ['name' => 'nama']);

        /*
         * Lewat Service, bukan `Customer::create()` langsung — DEC-049.
         *
         * Versi sebelumnya menulis sendiri, dan ikut melewatkan audit log
         * yang dicatat jalur API. Akibatnya customer yang ditambahkan dari
         * Admin Web tidak punya jejak siapa yang menambahkannya, dan itu
         * tidak menimbulkan error apa pun — hanya pertanyaan yang tidak bisa
         * dijawab berbulan-bulan kemudian.
         */
        $customer = $this->customers->create([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'note' => $data['note'] ?? null,
        ], $request->user());

        if ($request->boolean('jadikan_member')) {
            $this->jalankan(
                fn () => $this->memberships->register($customer, null, null, $request->user()),
                'name',
            );

            return back()->with('sukses', "{$customer->name} terdaftar sebagai member.");
        }

        return back()->with('sukses', "{$customer->name} ditambahkan.");
    }

    /** Jadikan member customer yang sudah ada. */
    /**
     * Perbaiki data customer — DEC-049.
     *
     * Setelah berhasil, kembali ke daftar TANPA `?edit=` supaya formnya
     * tertutup sendiri. Form yang tetap terbuka setelah disimpan mengundang
     * penyimpanan kedua yang tidak disengaja, dan di layar sempit
     * membuatnya terlihat seolah belum tersimpan.
     */
    public function updateCustomer(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->jalankan(
            fn () => $this->customers->update($customer, $request->validated(), $request->user()),
            'name',
        );

        return redirect()
            ->route('admin.customers', ['q' => $request->query('q', '')])
            ->with('sukses', "Data {$customer->fresh()->name} diperbarui.");
    }

    public function storeMembership(Request $request, Customer $customer): RedirectResponse
    {
        $this->jalankan(
            fn () => $this->memberships->register($customer, null, null, $request->user()),
            'membership',
        );

        return back()->with('sukses', "{$customer->name} sekarang member.");
    }

    // ── F&B ───────────────────────────────────────────────────────────

    public function storeFnbProduct(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', Rule::in(StoreFnbProductRequest::kategori())],
            'name' => ['required', 'string', 'max:255', 'unique:fnb_products,name'],
            'price' => ['required', 'integer', 'min:0'],
            'hpp' => ['nullable', 'integer', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
        ], [
            'name.unique' => 'Sudah ada menu dengan nama ini.',
        ], ['name' => 'nama menu']);

        // Kosong = tidak dilacak, BERBEDA dari nol yang berarti habis.
        $data['stock'] = $request->input('stock') === '' ? null : ($data['stock'] ?? null);
        $data['hpp'] = $request->input('hpp') === '' ? null : ($data['hpp'] ?? null);
        $data['is_available'] = true;

        $produk = $this->jalankan(
            fn () => $this->masterData->createFnbProduct($data, $request->user()),
            'name',
        );

        return back()->with('sukses', "Menu “{$produk->name}” ditambahkan.");
    }

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

    /**
     * Catat uang yang keluar dari laci — DEC-046.
     *
     * Validasinya memakai `StoreExpenseRequest` yang SAMA dengan API, bukan
     * aturan yang ditulis ulang di sini. Batas "keterangan minimal tiga
     * huruf" adalah satu-satunya hal yang membuat kolom itu berguna saat
     * laporan diperiksa; aturan yang disalin akan melunak di salah satu
     * pintu tanpa ada yang menyadarinya.
     */
    public function recordExpense(StoreExpenseRequest $request, Shift $shift): RedirectResponse
    {
        $this->jalankan(
            fn () => $this->shifts->recordExpense($shift, $request->validated(), $request->user()),
            'amount',
        );

        return back()->with('sukses', 'Pengeluaran dicatat. Kas seharusnya di laci sudah dikurangi.');
    }
}
