# SYNC — apa yang berubah & siapa harus apa

> **Baca ini dulu setiap kali `git pull`.** Satu halaman, selalu diperbarui
> setiap ada perubahan backend. Kalau tidak ada nama Anda di bagian
> "Perlu dikerjakan", tidak ada yang perlu Anda lakukan.

**Terakhir diperbarui:** 11 Oktober 2026 · **Kontrak:** `v1 DRAFT 17`

> **Perubahan batas kerja (10 Okt):** user mencabut aturan "backend saja".
> Claude sekarang juga menyentuh `operator-app/` dan `tv-agent/`.
> **Koordinasikan sebelum mengedit file yang sama.**

---

## Keadaan sekarang

| Bagian | Status |
|---|---|
| **Backend (Laravel)** | Tahap 0 **SELESAI**. 33 endpoint, 9 event realtime, 423 test lulus |
| **Admin Web (Blade)** | Login + **10 halaman** di `/admin`, data nyata, **penuh layar**, **menyegarkan otomatis**. Bisa ubah harga, stok, status F&B, tutup shift (DEC-044) |
| **Operator app (Flutter)** | Layar sudah jadi. Klien API **sudah ada** (`lib/data/api/`) dan alamat server sudah bisa ditemukan otomatis, tapi aplikasi **masih memakai data palsu** — belum dialihkan dan belum ada login |
| **TV Agent (Kotlin)** | Kiosk + timer jalan, dan sudah bisa **menemukan** server, tapi **belum bicara** dengan Laravel — masih menerima perintah langsung dari tablet |

---

## 🔴 Perlu dikerjakan — Flutter

| # | Pekerjaan | Kenapa mendesak |
|---|---|---|
| ✅ | **`ApiBillingRepository`** — sudah ditulis di `lib/data/api/`, 20 method, `flutter analyze` bersih | — |
| ✅ | **Penemuan server otomatis** — `server_discovery.dart` + tombol **Cari otomatis** di Pengaturan | — |
| 1 | **Layar login** + simpan token | Tanpa token, semua endpoint selain `/health` ditolak |
| 2 | **Alihkan `main.dart`** dari `FakeBillingRepository` ke `ApiBillingRepository` | Satu baris, tapi sampai itu dilakukan aplikasi tetap memakai data palsu |
| 3 | Panggil `ServerConnector().connect()` saat `main()` | Kodenya sudah ada tapi **belum dipanggil otomatis** — sekarang baru jalan kalau tombolnya ditekan |
| 4 | Klien Reverb + reconnect | Tablet tidak tahu apa pun yang terjadi di tablet/TV lain |
| 5 | Dengarkan `master.updated` → muat ulang `GET /packages` | Harga yang diubah owner tidak akan terlihat |

> ⚠️ Kalau nomor 3 dikerjakan: `connect()` bisa mengembalikan
> `addressChanged == true`. Saat itu koneksi WebSocket yang sudah terbuka
> **harus dibangun ulang** — ia tidak akan error sendiri, hanya diam.

### Yang berubah dan akan mematahkan asumsi lama

Fake repository sekarang **meniru aturan yang sudah tidak berlaku**. Yang
paling penting:

| Dulu | Sekarang |
|---|---|
| Postpaid punya batas waktu | **Tidak ada batas.** `end_at: null`, timer menghitung **maju** |
| Lewat waktu boleh lanjut, ditagih | **Tidak bisa.** Waktu habis = berhenti |
| Extend boleh sampai 10 menit setelah habis | **Tidak.** Hanya sebelum habis |
| `items[0]` selalu rental | Postpaid **tidak punya** baris rental sampai checkout |
| Operator tidak boleh daftarkan member | **Boleh** — tombolnya harus muncul |

Detail lengkap: `docs/contracts/CHANGELOG.md` DRAFT 6 s/d 16.

---

## 🔴 Perlu dikerjakan — Kotlin TV Agent

| # | Pekerjaan |
|---|---|
| ✅ | **Penemuan server otomatis** — `ServerDiscovery.kt` + `ServerConnector.kt` |
| 1 | Panggil `ServerConnector(store).connect()` saat boot, sebelum request pertama |
| 2 | `POST /devices/register` pakai kode station, simpan `device_token` permanen |
| 3 | `POST /devices/heartbeat` tiap 30 detik, kirim `known_session_id` + `known_end_at` |
| 4 | `GET /devices/me/state` saat boot & reconnect |
| 5 | Subscribe `private-station.{code}` lewat `X-Device-Token` |

`ServerConnector` sudah ada dan sudah diuji, tapi **belum dipanggil dari
`AgentService`** — alamatnya tersimpan di `StateStore.apiBaseUrl`, siap
dipakai klien API yang akan ditulis.

### Aturan yang mengikat tampilan TV

| `display.mode` | Artinya |
|---|---|
| `IDLE` | belum ada yang menyewa |
| `TIMER` | sedang main |
| `LOCKED` | **waktu habis — TV mati/standby, tidak boleh dimainkan** |

- `end_at` **ada** → timer **mundur**, peringatan overlay kecil di pojok kanan atas pada 10/5/1 menit.
- `end_at` **null** → Postpaid, timer **maju**, tanpa peringatan, tidak mati sendiri.
- Server **tidak pernah** mengirim sisa detik. Hitung dari `end_at` + offset.

> ⚠️ **Instruksi ini pernah berbalik.** DRAFT 6 menyuruh TV **tidak** mati saat
> waktu habis; DRAFT 9 membalikkannya. Yang berlaku sekarang: **TV mati**.

---

## Alamat server tidak perlu diketik lagi — DEC-041

Tablet dan TV sekarang bisa menemukan Laravel sendiri:

```
1. ketuk alamat yang tersimpan        (±600 ms)
2. kalau diam → pindai x.y.z.1–254    (254 alamat, 32 paralel, ±2–5 detik)
3. terima hanya kalau /api/v1/health menjawab app = "cempaka-smart-billing"
4. simpan; kalau ketemu lebih dari satu, manusia yang memilih
```

Gunanya: IP laptop datang dari DHCP, jadi menyambung ulang WiFi bisa
mengganti alamatnya dan memutus **semua** perangkat sekaligus.

| Yang sering ditanyakan | Jawaban |
|---|---|
| Kalau server mati, alamatnya dihapus? | **Tidak.** Server pindah dan server mati tidak bisa dibedakan dari client; menghapusnya berarti setiap perangkat lupa alamat saat laptop dimatikan sebentar |
| Port berapa yang dipindai? | **Hanya 8000.** Port 80 justru paling sering dijawab router dan printer |
| Masih perlu DHCP reservation? | Tidak wajib, tapi tetap dianjurkan — keduanya boleh dipakai bersamaan |

> ⛔ **Untuk backend:** `HealthController::APP_ID` (`cempaka-smart-billing`)
> sekarang **kontrak**. Mengubahnya membuat setiap tablet dan TV berhenti
> mengenali servernya. `/health` juga harus tetap tanpa auth — pemindaian
> terjadi sebelum ada token apa pun.

---

## ⛔ Pembagian peran — DEC-044

| | Laravel Admin Web | Flutter Tablet |
|---|---|---|
| **Peran** | mengatur & mengawasi | melayani customer |
| Harga, paket, stok F&B | ✅ **sumbernya** | melihat saja |
| Kas, laporan, audit | ✅ | — |
| Buka/tutup sesi, bayar, extend, swap | — | ✅ |
| Daftar member baru | — | ✅ |

> **Untuk Flutter:** jangan bangun layar pengaturan harga atau stok. Begitu
> sebuah pengaturan ada di Laravel, Flutter **tidak** membuat salinannya —
> dua tempat mengubah harga berarti dua tempat yang bisa berbeda.

**Setiap aksi ubah di web memanggil Service yang sama dengan API.** Yang
berbeda hanya bentuk jawabannya. Kalau Anda menambah aksi web baru, salin
juga `can:` dari `routes/api.php` — letak penegakannya berbeda per sumber
daya, dan versi pertama grup ini melewatkannya sampai admin bisa mengubah
seluruh tarif rental.

**Realtime = polling tiap 10 detik**, bukan Reverb. Reverb sudah dua kali
mati diam-diam minggu ini (`REVERB_HOST` salah, lalu `queue:work` tidak
jalan). Halaman yang bergantung padanya akan membeku sambil terlihat
normal — polling berhenti memperbarui penanda waktunya, dan itu terlihat.

---

## Admin Web dimulai — DEC-042

Ada permukaan **ketiga** sekarang: Laravel Blade di `/admin`. Bentuknya dari
referensi yang diberikan user (`backend/resources/uireference.html`),
warnanya **teal Amor yang sama dengan tablet dan TV**.

```bash
npm install && npm run build     # sekali, di backend/
```

Lalu buka `http://localhost:8000/admin`.

| Yang perlu diketahui | |
|---|---|
| **Tablet dan TV tidak berubah** | Web punya spec sendiri: `docs/UI-UX-SPEC-WEB.md`. `UI-UX-SPEC.md` tetap berlaku penuh di Flutter dan Kotlin |
| **Warnanya sama persis** | Teal Amor ternyata skala `cyan` Tailwind: `primary #0E7490` = `cyan-700`. Sebuah test membaca `tokens.dart` dan gagal kalau keduanya menyimpang |
| **Font: Plus Jakarta Sans saja** | Berbeda dari tablet yang memakai tiga family |
| **Datanya nyata** | `DashboardSampleData` sudah dihapus. Database dev masih kosong transaksi, jadi angkanya nol — itu benar, bukan rusak |
| ⚠️ **Batas "hari ini" = tengah malam WIB** | DB menyimpan UTC; `BusinessDay` yang menggeser batasnya. **Jangan pakai `whereDate(..., today())`** di query laporan — itu memotong pada 07:00 WIB dan membuang omzet dini hari ke hari sebelumnya |
| **Login sudah ada** (DEC-043) | Guard **session**, bukan Sanctum — tablet tidak terpengaruh. Masuk dengan `owner` atau `admin`; **operator ditolak**, dia memakai tablet |

> **Kalau Anda menyentuh Blade**, tiga aturan ini ditegakkan test
> (`php artisan test --filter=WebDesignTokensTest`):
>
> 1. Semua warna lewat token di `app.css` — bukan `bg-blue-600`, bukan hex.
> 2. Kelas warna **ditulis utuh**, jangan dirangkai (`bg-{$nada}-surface`
>    tidak pernah dibuat Tailwind, dan hasilnya badge tanpa warna).
> 3. Setiap sel tabel wajib punya `data-label` — tabel berubah jadi kartu
>    bertumpuk di bawah 768px, dan sel tanpa label tampil tanpa keterangan.

---

## Cara menjalankan server

Empat proses, masing-masing terminal sendiri:

```bash
php artisan serve --host=0.0.0.0 --port=8000
php artisan reverb:start
php artisan schedule:work
php artisan queue:work
```

**`queue:work` paling mudah terlupakan** — tanpa itu semua event realtime
menumpuk diam-diam di tabel `jobs` dan tidak pernah terkirim. Tidak ada error
yang terlihat.

Cek realtime benar-benar jalan:

```bash
php artisan realtime:listen --seconds=60
php artisan realtime:listen --device-token=<token> --channel=station.ST01
```

Kalau event muncul di situ tapi tidak di aplikasi Anda, masalahnya di aplikasi.

---

## Keputusan yang paling sering ditanyakan

| Pertanyaan | Jawaban |
|---|---|
| Harga diubah, kenapa tagihan sesi berjalan tidak ikut? | **Disengaja.** Harga dibekukan saat sesi dibuat (DEC-040) |
| Customer mau lanjut setelah waktu habis? | **Sesi baru**, bukan extend (DEC-033) |
| Prepaid berhenti lebih awal, sisanya? | Hangus — kecuali member, disimpan jadi saldo (DEC-024, DEC-026) |
| Postpaid kabur tanpa bayar? | **Belum ada pengamannya** — OD-002, menunggu keputusan |

---

## Belum ada di backend

- Paket yang sudah termasuk F&B (DEC-037) — menunggu durasi paket minuman
- Harga per jam sepi / happy hour (OD-025)
- Ubah station & tipe konsol (OD-028)
- Booking, payment gateway, laporan — semuanya Tahap 3

---

## Riwayat perubahan file ini

| Tanggal | Isi |
|---|---|
| 11 Okt 2026 | 7 celah "backend ada, UI belum" ditutup: detail sesi, struk WA, pembayaran, daftar member, tambah menu, HPP. `POST /fnb/products` baru (DRAFT 17) |
| 11 Okt 2026 | **DEC-044** — Laravel mengatur semuanya, Flutter melayani. Web bisa ubah harga/stok/shift, menyegarkan otomatis tiap 10 detik |
| 11 Okt 2026 | Admin Web jadi **penuh layar** — pagar 1240px dibuang, grid menambah kolom, header sticky |
| 11 Okt 2026 | **8 halaman Admin Web** — sesi, station, F&B, harga, customer, shift, audit. Semuanya responsif sampai 375px |
| 11 Okt 2026 | Dashboard Admin Web memakai **data nyata**; data contoh dihapus. Audit fitur kasir → OD-029/030/031 |
| 11 Okt 2026 | **Login Admin Web** (DEC-043) — guard session, Owner & Admin saja, pemeriksa kredensial dibagi dengan API |
| 11 Okt 2026 | **Admin Web dimulai** (DEC-042) — dashboard Blade di `/admin`, design system teal Amor + bentuk referensi |
| 10 Okt 2026 | Penemuan server otomatis (DEC-041) di Flutter **dan** Kotlin. `/health` menyebut identitasnya; `APP_ID` jadi kontrak |
| 10 Okt 2026 | Klien API Flutter ditulis. `GET /shifts` ditambahkan; struk sekarang membawa objek payment utuh |
| 10 Okt 2026 | Dibuat. Tahap 0 selesai, endpoint harga + `master.updated` ditambahkan |
