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

## Template entry berikutnya

```
## v1 · DRAFT <n> — YYYY-MM-DD

**<JENIS> — API|REALTIME** · terdampak: Flutter | Kotlin | Admin | semua
- apa yang berubah
- alasan + referensi DEC/OD
- aksi yang harus dilakukan client
```
