# CONTRACT CHANGELOG

Riwayat perubahan `API.md` dan `REALTIME.md`.

**Wajib:** setiap perubahan kontrak dicatat di sini **sebelum** kode client menyesuaikan diri (PRD §34).
Perubahan yang merusak compatibility harus ditandai **BREAKING** dan dikomunikasikan ke Flutter + Kotlin.

Format: tanggal · versi · jenis (`ADDED` / `CHANGED` / `BREAKING` / `REMOVED` / `FIXED`) · apa · siapa yang terdampak.

---

## v1 · DRAFT 1 — 2026-10-02

Kontrak awal. Belum ada implementasi, jadi belum ada breaking change.

**ADDED — API** · semua client
- Konvensi global: `snake_case`, UUID v4, ISO-8601 UTC, integer rupiah, enum `UPPER_SNAKE_CASE`
- Header `X-Server-Time` di semua response (sumber resmi offset, DEC-003)
- Envelope `{ data, meta }` + bentuk error `{ error: { code, message, details } }`
- Daftar 22 error code (`API.md` §11)
- Aturan `Idempotency-Key` pada 9 endpoint POST (`API.md` §3)
- Endpoint: `/health`, `/auth/*`, `/stations`, `/packages`, `/customers`, `/sessions/*`, `/fnb/*`, `/devices/*`, `/shifts/*`
- Objek `session` dengan `extend_deadline_at` dan `extendable` — aturan grace DEC-007 dihitung **server**, tidak diduplikasi di client
- `receipt.actual_duration_minutes` + `receipt.billable_duration_minutes` — supaya rounding DEC-009 bisa dijelaskan ke customer
- `GET /devices/me/state` sebagai endpoint reconcile Kotlin
- Protokol reconnect & reconcile (`API.md` §12)

**ADDED — REALTIME** · semua client
- Channel `private-operator`, `private-station.{code}`, `private-session.{id}` (yang terakhir reserved untuk Tahap 3C)
- Delapan event sesuai PRD §23 + payload lengkap
- Aturan throttle broadcast (`REALTIME.md` §7)
- Kewajiban client per platform (`REALTIME.md` §8)

**KEPUTUSAN DESAIN yang perlu diketahui client**
- **Tidak ada** `remaining_seconds` di response mana pun. Client menghitung dari `end_at` + offset (PRD §16).
- **Tidak ada event warning.** Warning 10/5/1 menit dihitung client dari `end_at` — kalau dikirim lewat WebSocket, warning justru hilang saat koneksi putus.
- `session.expired` dihasilkan scheduler sehingga bisa terlambat beberapa detik. Tampilan "habis" ditentukan timer lokal, bukan event ini.
- `session.updated` membawa objek `session` **utuh**, bukan delta. `changed[]` hanya petunjuk UI.
- Kotlin mengirim heartbeat lewat **HTTP**, bukan broadcast. Laravel yang mem-broadcast `device.heartbeat` ke operator — client tidak boleh jadi producer event (`REALTIME.md` §6).

**BELUM ADA — jangan diimplementasikan**
- `extend-requests` dari customer → Tahap 3C
- `display.mode: LOCKED` aktif → menunggu OD-001 & OD-004
- Penagihan overstay → OD-001
- Booking, payment gateway, webhook → Tahap 3D
- Endpoint reporting/finance → Tahap 3B
- `fnb.order.updated`, `device.offline`, `shift.closed` → usulan, belum disetujui (`REALTIME.md` §9)

---

## v1 · DRAFT 2 — 2026-10-02

**ADDED — API** · terdampak: Flutter (sudah menyesuaikan), Backend (belum ada)

- `GET /stations` → `station.session.started_at` (ISO-8601 UTC, `null` saat `PENDING_PAYMENT`)

**Alasan:** dashboard operator menggambar proporsi waktu terpakai per station sebagai bar tipis di kartu. Tanpa `started_at`, client harus memuat detail setiap sesi hanya untuk menghitung proporsi — enam request tambahan untuk satu informasi visual.

**Non-breaking:** field tambahan. Client yang mengabaikannya tetap jalan.

**Aksi untuk backend:** sertakan `started_at` pada ringkasan sesi di `GET /stations`. Nilainya sama dengan `session.started_at` di §7.

---

## v1 · DRAFT 3 — 2026-10-02

**ADDED — API** · terdampak: Flutter (sudah menyesuaikan), Backend (belum ada)

- `GET /devices` → bentuk response didefinisikan. Sebelumnya hanya disebut "daftar device + status, last_seen_at, app_version" tanpa skema.
- Field per device: `id`, `device_uid`, `station` (nullable), `status`, `last_seen_at`, `app_version`, `model`, `os_version`, `registered_at`
- `meta.offline_threshold_seconds`

**Alasan:** layar Device di operator app perlu menampilkan *sejak kapan* sebuah TV offline dan *kenapa* dianggap offline. Tanpa ambang batasnya dikirim, client harus menebak atau menduplikasi aturan server.

**`station` nullable** karena PRD §10 memperbolehkan perubahan pemetaan station↔device. Device yang pemetaannya dicabut harus tetap terlihat, bukan hilang dari daftar.

**Keputusan:** `status` ditentukan **server**, bukan dihitung client dari `last_seen_at`. Berbeda dari status sesi (yang memang diturunkan client dari `end_at`) karena ambang offline adalah kebijakan operasional, bukan hitungan waktu yang pasti.

**Non-breaking:** endpoint baru dari sisi implementasi; belum ada client yang memakainya sebelum ini.

**Aksi untuk backend:** implementasikan `GET /devices` sesuai §9. `status` dihitung dari `last_seen_at` terhadap ambang yang sama yang dikirim di `meta`.

---

## v1 · DRAFT 4 — 2026-10-02

**ADDED — API** · terdampak: Flutter (sudah menyesuaikan), Backend (belum ada)

- `GET /stations` → `station.console_type` (string, nullable)

**Alasan:** desain dashboard yang disetujui menampilkan label konsol per station (`PS5 VIP`, `PS4 PRO`). Operator memakainya untuk memilih station yang sesuai permintaan customer — "yang PS5" adalah permintaan sehari-hari.

**Teks bebas, bukan enum:** tiap rental punya penamaan sendiri. Membuatnya enum berarti setiap pelanggan baru butuh perubahan kode.

**Tidak memengaruhi harga.** Harga tetap dari `packages`. Apakah tarif harus berbeda per tipe konsol masih **OD-015** — label ini hanya informasi.

**Non-breaking:** field tambahan, nullable.

**Aksi untuk backend:** tambahkan kolom `console_type` (nullable string) ke tabel `stations` dan sertakan di response `GET /stations`.

---

## v1 · DRAFT 5 — 2026-10-07

**CHANGED — API** · terdampak: Flutter, Backend · **membalik catatan DRAFT 4**

- `station.console_type` **sekarang memengaruhi harga** (DEC-019, menjawab OD-015).

DRAFT 4 menulis "Tidak memengaruhi harga. Harga tetap dari `packages`." Itu benar
saat OD-015 masih terbuka. Sekarang OD-015 sudah diputuskan: **tarif berbeda per
tipe konsol**, dan bisa diatur dari aplikasi kasir.

**Konsekuensi yang belum masuk kontrak** (akan ditambahkan saat endpoint-nya ditulis):
- `GET /packages` perlu menyatakan paket itu milik tipe konsol yang mana.
- Perlu endpoint ubah tarif/paket — **hanya role `owner`** (DEC-020), selain itu `403 FORBIDDEN`.
- `POST /sessions/{id}/swap` menolak station tujuan dengan tipe konsol berbeda (DEC-021).
  Butuh error code baru — belum ditambahkan ke §11.

**Aksi untuk Flutter:** jangan lagi menganggap `console_type` sebagai label informasi
saja. Layar pengaturan tarif per tipe konsol akan dibutuhkan, dan daftar station
tujuan pada Swap harus disaring ke tipe konsol yang sama.

---

## v1 · DRAFT 5b — 2026-10-07

**CLARIFIED — API §3 (idempotency)** · terdampak: Flutter, Kotlin

Implementasi backend mempersempit satu aturan yang tertulis luas:

- **Hanya response sukses (2xx) yang disimpan.** §3 aturan 2 menulis "server menyimpan
  `key → response`" tanpa membedakan sukses/gagal. Kalau 422 ikut disimpan, aksi yang
  salah ketik akan terkunci selamanya pada key itu — client tidak bisa memperbaiki body
  lalu mengirim ulang dengan key yang sama, padahal §12 justru meminta retry memakai key
  yang sama.
- **`Idempotency-Key` wajib UUID v4.** Format lain → `400 IDEMPOTENCY_KEY_REQUIRED`.
  §3 aturan 1 sudah menyebut UUID v4; ini hanya menegaskan bahwa server menolaknya,
  bukan menerima apa adanya.
- **Key di-scope per pemakai** (user id / device token / anon). Key milik satu operator
  tidak bisa menabrak milik operator lain atau milik device TV.
- **Response replay mendapat `server_time` baru**, bukan nilai saat request pertama —
  kalau tidak, offset timer client akan mundur sejauh jarak antar retry.

**Aksi untuk client:** tidak ada perubahan kode selama `Idempotency-Key` sudah UUID v4
dan retry memakai key yang sama.

---

## Template entry berikutnya

```
## v1 · DRAFT 6 — 2026-10-08

Overstay akhirnya punya aturan. Ini perubahan **perilaku**, bukan bentuk data —
tidak ada field yang berubah, tapi arti `EXPIRED` berubah untuk semua client.

**CHANGED — API** · semua client · **DEC-023**
- `EXPIRED` sekarang berarti "waktu paket sudah lewat", **bukan** "sesi berhenti".
  Timer terus berjalan, station tetap terpakai, dan kelebihan waktunya ditagih
  saat checkout. Catatan lama di `API.md` §7 ("Overstay belum diatur — jangan
  diimplementasikan") sudah dicabut.
- `POST /sessions/{id}/checkout` menambah langkah 1b: menit di luar
  `durasi_paket + total_extend` ditagih sebagai item `ADJUSTMENT` bernama
  "Kelebihan waktu". Pembulatan per 30 menit dengan toleransi 5 menit (DEC-009)
  **tanpa** lantai minimum 30. Harga per blok sama dengan extend.

**CHANGED — API** · operator app · **DEC-024**
- Prepaid yang berhenti lebih awal: sisa waktunya **hangus**, tidak ada
  pengembalian. Member bisa menyimpannya — tabel saldonya menyusul bersama
  endpoint checkout, jadi belum ada field baru di kontrak.

**FIXED — API** · semua client
- `409 EXTEND_GRACE_EXPIRED` mengirim `error.details.grace_until`, sesuai contoh
  di `API.md` §2. Sebelumnya belum ada implementasinya, jadi ini bukan breaking.

**Yang WAJIB diketahui Kotlin TV Agent:** TV **tidak boleh** mengunci, memblank,
atau mematikan tampilan saat `end_at` lewat. Yang ditampilkan adalah waktu
berjalan maju (overtime). Perilaku lock/overlay saat EXPIRED masih OD-004 —
sampai itu diputuskan, TV cukup menampilkan timer yang terus berjalan.

**Yang perlu diketahui Flutter:** kartu station berstatus `EXPIRED` tetap
menampilkan timer berjalan, bukan "selesai". `extendable` tetap dari server —
setelah grace 10 menit lewat, tombol extend hilang tapi sesi tetap hidup, dan
operator menutupnya lewat checkout.

---

## v1 · DRAFT 7 — 2026-10-08

F&B, Swap, Checkout, dan delapan event realtime terpasang. Satu error code baru,
satu field baru di checkout. Tidak ada yang breaking — semua endpoint yang
berubah belum pernah punya implementasi.

**ADDED — API** · semua client
- `STATION_TYPE_MISMATCH` (409) pada `POST /sessions/{id}/swap`. Menegakkan
  DEC-021: swap hanya dalam tipe konsol yang sama. Daftar error code jadi 24.
- `POST /sessions/{id}/checkout` menerima `use_credit` (boolean, default `false`)
  — saldo member, DEC-026. Field opsional, jadi client lama tetap jalan.

**CHANGED — API** · operator app
- `POST /sessions/{id}/checkout` langkah 3 dipertegas: `payments` harus **persis**
  sama dengan `balance_due`. Kurang → `CHECKOUT_INSUFFICIENT_PAYMENT`, lebih →
  `PAYMENT_AMOUNT_EXCEEDS_BALANCE`. Kalimat lama "harus menutupi" bisa dibaca
  sebagai boleh lebih, padahal V1 tidak mencatat kembalian.
- `payments` boleh array kosong — Prepaid tanpa F&B sudah lunas sebelum checkout,
  dan sejak DEC-026 saldo member juga bisa menutup seluruh tagihan.

**ADDED — REALTIME** · semua client
- Delapan event di `REALTIME.md` §4 sekarang benar-benar dikirim, kecuali
  `device.heartbeat` yang menunggu `POST /devices/heartbeat` di Tahap 2.
  Bentuk payload-nya sudah dikunci sekarang supaya tidak berubah nanti.
- Channel `private-operator`, `private-station.{code}`, `private-session.{id}`
  terdaftar. Otorisasi lewat `POST /broadcasting/auth` dengan **Bearer token**.

**Yang WAJIB diketahui Kotlin TV Agent:** channel `private-station.{code}` belum
bisa di-subscribe pakai `X-Device-Token` — guard device-nya baru ada di Tahap 2
bersama `POST /devices/register`. Sampai itu ada, hanya token Sanctum yang
diterima. Ini disengaja, bukan terlewat.

**Yang perlu diketahui Flutter:**
- `session.expired` tidak berarti sesi berhenti (DEC-023). Kartu station tetap
  menampilkan timer berjalan.
- Layar checkout butuh satu kontrol baru: centang "pakai saldo member", hanya
  muncul kalau customer punya saldo. Jangan dicentang otomatis.
- Nominal pembayaran di checkout harus persis `balance_due` — tidak ada kembalian.

---

## v1 · DRAFT 8 — 2026-10-08

Shift, customer, dan membership. Satu perubahan permission yang perlu
diperhatikan Flutter, tiga error code baru, satu field baru.

**BREAKING (ringan) — API** · operator app
- `customer.create` sekarang dipegang **operator**, bukan hanya admin (DEC-027).
  Disebut breaking karena Flutter mungkin menyembunyikan tombol "daftar member"
  berdasarkan permission ini — sekarang tombol itu **harus muncul** untuk
  operator. Tidak ada endpoint yang berubah bentuk; yang berubah siapa yang boleh.

**ADDED — API** · operator app
- `POST /shifts/open`, `POST /shifts/{id}/close`, `GET /shifts/current`.
  Sebelum ini `shift_id` pada setiap payment selalu NULL dan uang masuk tidak
  bisa dihubungkan ke siapa yang jaga.
- `GET /customers?q=`, `POST /customers`, `POST /customers/{id}/membership`.
- `customer.credit_balance` — saldo member dalam rupiah (DEC-026). Dikirim di
  objek customer supaya operator melihatnya **sebelum** checkout.
- Permission baru `discount.manage`, **hanya owner** (DEC-028).
- Error code: `SHIFT_ALREADY_OPEN`, `SHIFT_NOT_OPEN`, `CUSTOMER_ALREADY_MEMBER`.
  Daftar error code jadi 27.

**Yang perlu diketahui Flutter:**
- Layar shift start/close sudah punya backend. `GET /shifts/current` membalas
  `data: null` kalau belum buka — itu normal di awal hari, bukan error.
- Selisih kas **tidak** menghalangi penutupan shift. Jangan memblokir tombolnya.
- Tombol "daftar member" sekarang boleh muncul untuk operator.
- Biaya daftar member Rp 10.000 masuk ke Open Tab sesi berjalan, jadi tagihan
  di layar checkout akan bertambah setelah pendaftaran. Operator perlu melihat
  itu sebelum menagih.
- `summary.rental` di shift sudah termasuk extend.

**Yang perlu diketahui Kotlin TV Agent:**
- **DEC-030**: peringatan 10/5/1 menit ditampilkan sebagai overlay kecil di
  **pojok kanan atas** — bukan layar penuh, bukan dialog. Dihitung client dari
  `end_at`; tidak ada event warning dari server. Setelah `end_at` lewat, overlay
  berganti menampilkan waktu berjalan maju (DEC-023), bukan layar mati.
  Ini mencabut salah satu dari dua penghalang Tahap 2.

---

## v1 · DRAFT 9 — 2026-10-08

**BREAKING — perilaku.** Aturan "waktu habis" berbalik dari DRAFT 6. Tidak ada
field yang hilang, tapi arti `EXPIRED` dan syarat extend berubah untuk semua client.

**BREAKING — API & REALTIME** · semua client · **DEC-033** (mencabut DEC-023)
- `EXPIRED` sekarang berarti **berhenti**. Waktu habis → TV mati/standby,
  customer tidak bisa melanjutkan. Kalimat di DRAFT 6 yang menyebut `EXPIRED`
  sebagai "penanda, bukan penghenti" **dicabut**.
- **Tidak ada penagihan kelebihan waktu.** Langkah 1b di checkout (item
  `ADJUSTMENT` "Kelebihan waktu") dihapus dari kontrak dan dari kode.
- **Extend hanya boleh sebelum waktu habis** (`now ≤ end_at`). Grace 10 menit
  DEC-007 dicabut — "habis ya habis". Customer yang ingin melanjutkan dibuatkan
  **sesi baru dengan paket baru**.
- `extend_deadline_at` **sekarang sama persis dengan `end_at`**. Field-nya
  sengaja tidak dihapus supaya client yang sudah membacanya tidak patah.
- `409 EXTEND_GRACE_EXPIRED` **tetap dipakai dengan nama yang sama**, tapi
  artinya berubah jadi "waktunya sudah lewat". Namanya tidak diubah supaya
  daftar error code yang sudah disalin client tidak perlu dibongkar.
- `status` yang diterima `POST /sessions/{id}/extend` sekarang hanya `ACTIVE`
  dan `WARNING`. `EXPIRED` → `409 SESSION_STATUS_INVALID`.

**Yang WAJIB diketahui Kotlin TV Agent — instruksinya berbalik**

| | Isi instruksi |
|---|---|
| DRAFT 6 (pagi ini) | TV **tidak boleh** mengunci atau memblank saat `end_at` lewat |
| DRAFT 9 (sekarang) | TV **harus** mati / standby saat `end_at` lewat |

Kalau yang pertama sudah dikerjakan, pekerjaan itu terbuang — dan itu akibat
perubahan keputusan bisnis, bukan kesalahan implementasi. Peringatan warning
10/5/1 menit tetap seperti DEC-030: overlay kecil di pojok kanan atas, dihitung
client dari `end_at`. Setelah `end_at` lewat, TV **tidak** menampilkan hitungan
maju — itu bagian DRAFT 6 yang juga dicabut.

**Yang perlu diketahui Flutter**
- Tombol extend **hilang** begitu waktu habis, bukan 10 menit sesudahnya.
  `extendable` dari server sudah mencerminkan itu — jangan hitung sendiri.
- Kartu station berstatus `EXPIRED` menampilkan "waktu habis", bukan timer
  berjalan maju. Bagian DRAFT 6 yang menyuruh sebaliknya dicabut.
- Checkout sesi `EXPIRED` tidak lagi memunculkan baris "Kelebihan waktu".
- Customer yang mau lanjut: buat **sesi baru**, bukan extend.

---

## v1 · DRAFT 10 — 2026-10-08

**BREAKING — arti Postpaid berubah.** Ditemukan saat user bertanya apakah
perilakunya sudah terpasang: ternyata kontrak memberi Postpaid batas waktu,
padahal maksudnya "main dulu berapapun, bayar belakangan".

**BREAKING — API** · semua client · **DEC-034**
- `POST /sessions` dengan `mode: POSTPAID` sekarang membuat sesi **`end_at: null`**.
  Kalimat lama (`end_at = now + package.duration_minutes`) dicabut.
- Item `RENTAL` **tidak lagi dibuat saat start** untuk Postpaid. Barisnya lahir
  saat checkout dengan angka final. Client yang membaca `items[0]` sebagai rental
  akan menemukan daftar kosong di awal sesi.
- Selama sesi Postpaid berjalan, `totals.rental` adalah **tagihan berjalan** dari
  waktu yang sudah terpakai, minimum satu blok 30 menit. Angkanya **bertambah
  seiring waktu** — bukan diam di harga paket.
- Paket pada Postpaid hanya menentukan tarif per jam, bukan durasi.

**Yang WAJIB diketahui Kotlin TV Agent**

| `end_at` | Artinya |
|---|---|
| ada isinya | Prepaid. Timer **mundur**, peringatan 10/5/1 menit, TV mati saat habis (DEC-030, DEC-033) |
| `null` | Postpaid. Timer **maju** dari `started_at`. Tidak ada peringatan, tidak ada mati sendiri |

TV pada sesi Postpaid baru berhenti saat menerima `session.updated` berstatus
`COMPLETED`. Jangan ada cabang yang mengasumsikan `end_at` selalu ada —
sekarang `null` adalah keadaan normal, bukan data rusak.

**Yang perlu diketahui Flutter**
- Kartu station Postpaid tidak punya sisa waktu. Yang ditampilkan waktu berjalan
  dan **tagihan berjalan** dari `totals.rental` — itu angka yang disebut ke
  customer kalau dia bertanya "sudah berapa?".
- Tombol extend tidak berlaku untuk Postpaid. `extendable` dari server sudah
  `false` — jangan hitung sendiri.
- Daftar item sesi Postpaid kosong di awal. Itu benar, bukan gagal memuat.

**Risiko yang perlu disampaikan ke pemilik**
Tanpa batas waktu, tidak ada rem otomatis untuk customer yang pergi tanpa bayar.
Dulu kerugian terbatas pada durasi paket; sekarang tidak terbatas. **OD-002**
(deposit / batas Open Tab / catat identitas) naik jadi mendesak.

---

## v1 · DRAFT 11 — 2026-10-09

Satu field baru. Tidak ada yang berubah bentuk — aman untuk client lama.

**ADDED — API** · operator app · **DEC-035**
- `customer.phone_wa` — nomor yang sudah siap dipakai tautan `wa.me`.
  `0812-3456-7890` → `6281234567890`. `null` kalau nomornya kosong atau
  terlalu pendek untuk masuk akal.
- `customer.phone` **tidak berubah** — tetap seperti yang diketik operator,
  supaya pencarian tidak gagal saat dia mengetik ulang bentuk yang sama.

**Yang perlu diketahui Flutter**

Pengiriman struk ke WhatsApp memakai tautan `wa.me`, **bukan** WhatsApp
Business API (DEC-035). Backend **tidak mengirim apa pun** — Flutter yang
membuka tautannya, operator yang menekan kirim.

```
https://wa.me/{phone_wa}?text={pesan struk yang sudah di-encode}
```

Isi pesannya disusun Flutter dari objek `receipt` di response checkout; semua
datanya sudah ada di sana — nomor struk, dua durasi, baris item, totals,
payments, operator.

Tombolnya **hanya muncul kalau `phone_wa` tidak null**. Walk-in tidak punya
nomor, jadi tidak bisa dikirimi struk — itu memang konsekuensi DEC-031, bukan
kekurangan.

Konsekuensi yang perlu diketahui operator: pengirimannya manual, dan sistem
tidak menyimpan bukti bahwa struknya terkirim.

---

## v1 · DRAFT 12 — 2026-10-09

Tiga endpoint terakhir Tahap 0. Tidak ada yang breaking — semuanya tambahan.

**ADDED — API** · operator app
- `GET /stations` — sumber data dashboard. Station, sesi aktifnya, dan status
  TV dalam satu panggilan. `meta.offline_threshold_seconds` ikut dikirim supaya
  ambang ONLINE/OFFLINE tidak dituliskan ulang di client.
- `GET /packages` — menerima filter `station_id`, `station_type_id`, dan
  `only_active`. Setiap paket membawa `station_type_id` + `console_type`.
- `POST /sessions/{id}/cancel` — batal dari `PENDING_PAYMENT`. Station langsung
  kosong dan bisa segera dipakai sesi baru.
- Error code baru: `SESSION_HAS_PAYMENT` (409). Daftar error code jadi 28.

**Yang perlu diketahui Flutter**
- **Layar Start Session wajib memakai `GET /packages?station_id=...`**, bukan
  daftar paket penuh. Tanpa filter itu, operator bisa memilih paket PS4 untuk
  station PS5 dan baru ditolak server setelah menekan tombol (DEC-019).
- `station.session` adalah **ringkasan**, bukan objek `session` penuh. Detailnya
  dari `GET /sessions/{id}`. Yang ada di ringkasan cukup untuk menggambar kartu:
  status, mode, `started_at`, `end_at`, `customer_label`, `balance_due`.
- `station.session.end_at` bisa `null` untuk dua alasan berbeda:
  `PENDING_PAYMENT` (timer belum mulai) dan Postpaid (tidak ada batas waktu,
  DEC-034). Bedakan lewat `status`.
- Tombol batal hanya untuk sesi `PENDING_PAYMENT`. Sesi berjalan diselesaikan
  lewat checkout — server menolaknya dengan `SESSION_STATUS_INVALID`, bukan
  dengan pesan tentang uang.

**Tahap 0 selesai.** Seluruh golden path sekarang bisa dijalankan dari luar
tanpa menyentuh database: 38 pemeriksaan lewat HTTP, semua id diambil dari
`GET /stations`, `GET /packages`, dan `GET /fnb/products`.

---

## v1 · DRAFT 13 — 2026-10-10

Tidak ada perubahan bentuk data. Yang berubah: dua hal di `REALTIME.md` yang
sebelumnya hanya tertulis, sekarang benar-benar berjalan.

**IMPLEMENTED — REALTIME** · semua client
- **Debounce `session.updated` 500 ms per sesi** (§7) sekarang aktif. Perubahan
  beruntun pada satu sesi menghasilkan **satu** event, bukan satu per perubahan.
- Event itu membawa **keadaan terbaru**, bukan potret saat perubahan pertama.
  Tidak ada perubahan yang hilang karena di-debounce.

**Yang perlu diketahui Flutter**
- Jumlah `session.updated` yang diterima akan **berkurang** saat ramai. Itu
  disengaja. Karena payload-nya selalu objek `session` utuh (§5), satu event
  sudah cukup — jangan menghitung event untuk melacak berapa kali sesuatu
  berubah.
- `changed[]` sekarang bisa berisi **gabungan** beberapa perubahan sekaligus,
  mis. `["items","totals","status"]` dalam satu event. Tetap petunjuk UI saja.
- Jedanya 0–1 detik pada praktiknya: queue database menyimpan waktu dalam
  detik. Timer tidak terpengaruh — timer dihitung client dari `end_at`.

**Yang perlu diketahui keduanya**
- Sisi subscribe sudah **dibuktikan bekerja**, bukan hanya diasumsikan:
  `php artisan realtime:listen` menyambung ke Reverb sebagai client sungguhan
  lewat jalur yang sama dengan Flutter dan Kotlin — login, handshake WebSocket,
  `POST /broadcasting/auth` dengan Bearer token, lalu subscribe — dan menerima
  `session.started`, `payment.confirmed`, serta `session.updated`.
- Pakai perintah itu untuk memastikan masalah ada di client atau di server
  sebelum menebak.

**Catatan operasional yang mengikat siapa pun yang menjalankan server:**
tanpa `php artisan queue:work`, **tidak ada satu pun event yang terkirim** —
semuanya menumpuk diam-diam di tabel `jobs`. Tidak ada error yang terlihat.

---

## v1 · DRAFT <n> — YYYY-MM-DD

**<JENIS> — API|REALTIME** · terdampak: Flutter | Kotlin | Admin | semua
- apa yang berubah
- alasan + referensi DEC/OD
- aksi yang harus dilakukan client
```
