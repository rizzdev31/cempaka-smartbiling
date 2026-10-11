<?php

namespace App\Http\Controllers\Web;

use App\Enums\FnbOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SessionItemType;
use App\Enums\SessionStatus;
use App\Models\AuditLog;
use App\Models\BillingSession;
use App\Models\Customer;
use App\Models\Device;
use App\Models\FnbOrder;
use App\Models\FnbProduct;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\Station;
use App\Models\StationType;
use App\Support\Phone;
use App\Support\Time\BusinessDay;
use App\Support\Web\DashboardData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Halaman Admin Web — PRD §19.
 *
 * ## Kenapa query langsung, bukan memanggil API sendiri
 *
 * Admin Web berjalan di proses Laravel yang SAMA dengan API-nya. Memanggil
 * `GET /api/v1/sessions` dari sini berarti satu request HTTP ke diri sendiri,
 * lengkap dengan serialisasi JSON dan autentikasi ulang — untuk data yang
 * sudah ada satu lapisan di bawah.
 *
 * Aturan "client tidak boleh akses DB langsung" (CLAUDE.md §4) berlaku untuk
 * **Flutter dan Kotlin**, yang memang terpisah mesin. Blade bukan client; ia
 * bagian dari Laravel.
 *
 * Yang TIDAK boleh ditiru di sini: menulis aturan bisnis. Halaman ini hanya
 * membaca. Setiap perubahan harga, status, atau sesi tetap lewat Service
 * yang sama dengan API — lihat [PricingController].
 */
class AdminController
{
    public function dashboard(): View
    {
        return view('admin.dashboard', DashboardData::all());
    }

    /**
     * Daftar sesi — PRD §19 "Dashboard: live sessions".
     *
     * Dibatasi 50 terbaru. Tanpa batas, halaman ini akan memuat seluruh
     * riwayat rental begitu dipakai beberapa bulan, dan yang dicari orang
     * justru selalu yang paling baru.
     */
    public function sessions(Request $request): View
    {
        $status = $request->string('status')->toString();

        $query = BillingSession::query()
            ->with(['station.stationType', 'customer'])
            ->orderByDesc('created_at');

        if ($status !== '' && SessionStatus::tryFrom($status)) {
            $query->where('status', $status);
        }

        $sessions = $query->limit(50)->get();

        $totals = $sessions->isEmpty() ? collect() : DB::table('session_items')
            ->whereIn('session_id', $sessions->pluck('id'))
            ->whereNot('type', SessionItemType::DISCOUNT->value)
            ->groupBy('session_id')
            ->pluck(DB::raw('SUM(subtotal)'), 'session_id');

        return view('admin.sessions', [
            'sessions' => $sessions,
            'totals' => $totals,
            'status' => $status,
            // Hitungan per status untuk chip filter — dari SELURUH tabel,
            // bukan dari 50 yang tampil, supaya angkanya tidak berbohong.
            'counts' => BillingSession::query()
                ->selectRaw('status, COUNT(*) as jumlah')
                ->groupBy('status')
                ->pluck('jumlah', 'status'),
        ]);
    }

    /**
     * Satu sesi, lengkap — PRD §19.
     *
     * Halaman ini yang menjawab "kenapa tagihannya segitu". Karena itu
     * semuanya ditampilkan apa adanya: tiap baris item, tiap pembayaran, dan
     * jejak auditnya. Ringkasan saja tidak cukup — yang dicari orang selalu
     * satu baris yang tidak dia harapkan ada di situ.
     */
    public function session(BillingSession $session): View
    {
        $session->load(['station.stationType', 'customer.membership', 'items', 'payments.actor', 'openedBy']);

        return view('admin.session', [
            'session' => $session,
            'totals' => $session->totals(),
            /*
             * Jejak audit sesi ini. `subject_id` cocok untuk aksi yang
             * menyebut sesinya langsung — extend, swap, checkout, batal.
             */
            'logs' => AuditLog::query()
                ->where('subject_id', $session->id)
                ->orderBy('created_at')
                ->get(),
            'waPhone' => Phone::toWhatsApp($session->customer?->phone),
        ]);
    }

    /**
     * Pembayaran — PRD §19 "Payment: transaction, method, status,
     * reference, reconciliation".
     *
     * Dibatasi pada rentang hari supaya angka totalnya punya arti. Daftar
     * seluruh riwayat dengan total di bawahnya akan menjawab pertanyaan yang
     * tidak pernah ditanyakan siapa pun.
     */
    public function payments(Request $request): View
    {
        $hari = $request->date('tanggal') ?? BusinessDay::now();
        [$dari, $sampai] = BusinessDay::range($hari);

        $payments = Payment::query()
            ->with(['session.station', 'actor'])
            ->where('confirmed_at', '>=', $dari)
            ->where('confirmed_at', '<', $sampai)
            ->orderByDesc('confirmed_at')
            ->get();

        return view('admin.payments', [
            'payments' => $payments,
            'tanggal' => $hari->format('Y-m-d'),
            /*
             * Hanya yang CONFIRMED yang dijumlahkan — dasar yang sama dengan
             * dashboard dan `ShiftService::summary()`. Yang belum
             * dikonfirmasi tetap ditampilkan di daftar, supaya terlihat ada,
             * tapi tidak ikut dihitung sebagai uang masuk.
             */
            'cash' => (int) $payments->where('status', 'CONFIRMED')
                ->where('method', PaymentMethod::CASH)->sum('amount'),
            'qris' => (int) $payments->where('status', 'CONFIRMED')
                ->where('method', PaymentMethod::QRIS_STATIC)->sum('amount'),
        ]);
    }

    /** Station & Device — PRD §19. */
    public function stations(): View
    {
        $stations = Station::query()
            ->with(['stationType', 'device'])
            ->orderBy('code')
            ->get();

        // Sesi yang sedang berjalan, dipetakan per station supaya tidak ada
        // query di dalam perulangan view.
        $aktif = BillingSession::query()
            ->whereIn('status', [SessionStatus::ACTIVE, SessionStatus::WARNING])
            ->get()
            ->keyBy('station_id');

        return view('admin.stations', [
            'stations' => $stations,
            'aktif' => $aktif,
            /*
             * Konstanta yang SAMA dengan yang dikirim API lewat
             * `meta.offline_threshold_seconds`. Menulis 120 di sini akan
             * membuat web dan tablet bisa menyatakan TV yang sama
             * online dan offline sekaligus.
             */
            'offlineThreshold' => Device::OFFLINE_THRESHOLD_SECONDS,
        ]);
    }

    /** F&B: menu, stok, dan antrean pesanan — PRD §19 "F&B/Inventory". */
    public function fnb(): View
    {
        return view('admin.fnb', [
            'products' => FnbProduct::query()
                ->orderBy('category')
                ->orderBy('name')
                ->get()
                ->groupBy('category'),

            'orders' => FnbOrder::query()
                ->with(['items', 'session.station'])
                ->whereIn('status', [
                    FnbOrderStatus::PENDING,
                    FnbOrderStatus::PROCESSING,
                    FnbOrderStatus::READY,
                ])
                ->orderBy('created_at')
                ->get(),
        ]);
    }

    /** Harga & paket — PRD §19 "Package & Pricing". */
    public function pricing(): View
    {
        return view('admin.pricing', [
            'packages' => Package::query()
                ->with('stationType')
                ->orderBy('station_type_id')
                ->orderBy('sort_order')
                ->get()
                ->groupBy(fn (Package $p) => $p->stationType?->name ?? '—'),

            'products' => FnbProduct::query()->orderBy('category')->orderBy('name')->get(),

            /*
             * DEC-020: hanya owner yang boleh mengubah tarif. Dipakai view
             * untuk menonaktifkan form — TAPI itu bukan kontrolnya.
             * Penegaknya ada di route (`can:pricing.manage`) dan di
             * MasterDataService. Menyembunyikan tombol tidak pernah jadi
             * kontrol keamanan.
             */
            'bolehUbah' => auth()->user()?->role->canManagePricing() ?? false,

            // Untuk form tambah paket. `station_type_id` tidak bisa diubah
            // setelah paket dibuat (DEC-040), jadi pilihannya hanya muncul
            // di sini.
            'stationTypes' => StationType::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /** Customer & member — PRD §19 "Customer/Membership". */
    public function customers(Request $request): View
    {
        $q = $request->string('q')->toString();

        $customers = Customer::query()
            ->with('membership')
            ->when($q !== '', fn ($query) => $query->where(
                fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")
            ))
            ->withCount('sessions')
            ->orderBy('name')
            ->limit(100)
            ->get();

        return view('admin.customers', [
            'customers' => $customers,
            'q' => $q,
            'saldo' => $customers->isEmpty() ? collect() : DB::table('customer_credits')
                ->whereIn('customer_id', $customers->pluck('id'))
                ->groupBy('customer_id')
                ->pluck(
                    DB::raw("SUM(CASE WHEN type = 'EARNED' THEN amount ELSE -amount END)"),
                    'customer_id',
                ),
        ]);
    }

    /** Shift kasir — PRD §19 "Shift/Attendance". */
    public function shifts(): View
    {
        return view('admin.shifts', [
            'current' => Shift::query()->with('operator')->whereNull('closed_at')->first(),
            'history' => Shift::query()
                ->with('operator')
                ->whereNotNull('closed_at')
                ->orderByDesc('closed_at')
                ->limit(30)
                ->get(),
        ]);
    }

    /**
     * Audit log — PRD §19 & §24.
     *
     * Ini satu-satunya modul yang menjawab "siapa yang membatalkan sesi itu"
     * dan "siapa yang memberi diskon". Tanpa halaman ini, datanya ada tapi
     * hanya bisa dibaca lewat SQL.
     */
    public function audit(Request $request): View
    {
        $action = $request->string('action')->toString();

        return view('admin.audit', [
            'logs' => AuditLog::query()
                ->when($action !== '', fn ($q) => $q->where('action', $action))
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(),
            'action' => $action,
            'actions' => AuditLog::query()
                ->selectRaw('action, COUNT(*) as jumlah')
                ->groupBy('action')
                ->orderByDesc('jumlah')
                ->pluck('jumlah', 'action'),
            'hariIni' => BusinessDay::today(),
        ]);
    }
}
