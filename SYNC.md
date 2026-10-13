# SYNC — apa yang berubah & siapa harus apa

> **Baca ini dulu setiap kali `git pull`.** Satu halaman, selalu diperbarui
> setiap ada perubahan backend. Kalau tidak ada nama Anda di bagian
> "Perlu dikerjakan", tidak ada yang perlu Anda lakukan.

**Terakhir diperbarui:** 13 Oktober 2026 · **Kontrak:** `v1 DRAFT 20`

> **Perubahan batas kerja (10 Okt):** user mencabut aturan "backend saja".
> Claude sekarang juga menyentuh `operator-app/` dan `tv-agent/`.
> **Koordinasikan sebelum mengedit file yang sama.**

---

## Keadaan sekarang

| Bagian | Status |
|---|---|
| **Backend (Laravel)** | Tahap 0 **SELESAI**. 40 endpoint, 9 event realtime, 520 test lulus |
| **Admin Web (Blade)** | Login + **11 halaman** di `/admin` (termasuk **Laporan**), data nyata, **penuh layar**, **menyegarkan otomatis**. Bisa ubah harga, stok, status F&B, shift, pengeluaran kasir, **dan menambah/mengubah station, tipe konsol, serta data customer** (DEC-044 s/d 050) |
| **Operator app (Flutter)** | **Memakai data server sungguhan (11 Okt).** Layar masuk + gate auth + token tersimpan; `ApiBillingRepository` menggantikan fake. 237 test lulus. Belum ada: klien Reverb, dan `ServerConnector().connect()` belum dipanggil otomatis |
| **TV Agent (Kotlin)** | Kiosk + timer jalan, dan sudah bisa **menemukan** server, tapi **belum bicara** dengan Laravel — masih menerima perintah langsung dari tablet |

---

## 🔴 Perlu dikerjakan — Flutter

| # | Pekerjaan | Kenapa mendesak |
|---|---|---|
| ✅ | **`ApiBillingRepository`** — sudah ditulis di `lib/data/api/`, 20 method, `flutter analyze` bersih | — |
| ✅ | **Penemuan server otomatis** — `server_discovery.dart` + tombol **Cari otomatis** di Pengaturan | — |
| ✅ | **Layar login** + token tersimpan — gate auth tunggal di `app.dart` | — |
| ✅ | **`ApiBillingRepository` dipakai** — fake tinggal untuk test | — |
| 1 | Panggil `ServerConnector().connect()` saat `main()` | Kodenya sudah ada tapi **belum dipanggil otomatis** — sekarang baru jalan kalau tombolnya ditekan |
| 2 | Klien Reverb + reconnect | Tablet tidak tahu apa pun yang terjadi di tablet/TV lain |
| 3 | Dengarkan `master.updated` → muat ulang daftarnya. `resource` sekarang bisa `package`, `fnb_product`, `station`, `station_type` (DEC-048) — **abaikan** nilai yang tidak dikenal, jangan error | Harga, menu, dan station yang diubah owner tidak akan terlihat di tablet sampai aplikasinya dimuat ulang |
| ✅ | **`expected_cash` dari server dipakai** (13 Okt) — `Shift.expectedCash` tidak lagi menghitung sendiri, dan fallback-nya ikut rumus DEC-046 di layar tutup shift — jangan menjumlahkan `opening_cash + cash` sendiri | **DEC-046.** Rumusnya sekarang `modal + tunai − pengeluaran`. Tablet yang menjumlahkan sendiri akan menampilkan angka yang berbeda dari yang dipakai server saat `close()`, dan kasir yang menyetor sesuai layar justru tercatat punya selisih |
| 4 | Layar **catat pengeluaran** (`POST /shifts/{id}/expenses`) | Yang memegang laci adalah kasir di tablet. Sekarang satu-satunya pintunya ada di Admin Web — artinya kasir harus memanggil owner hanya untuk mencatat Rp 20.000 beli galon |

> ⚠️ Kalau nomor 1 dikerjakan: `connect()` bisa mengembalikan
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

Detail lengkap: `docs/contracts/CHANGELOG.md` DRAFT 6 s/d 20.

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

## ⛔ Pembagian peran — DEC-044, diperbarui DEC-045

| | Laravel Admin Web | Flutter Tablet |
|---|---|---|
| **Peran** | owner — **punya semua akses** | kasir — melayani customer |
| Harga, paket, stok, HPP | ✅ **sumbernya** | melihat saja |
| Mulai sesi, bayar, extend, swap, checkout, batal | ✅ **bisa** (DEC-045) | ✅ jalur sehari-hari |
| Kas, laporan, audit, pembayaran | ✅ | — |
| Daftar member baru | ✅ | ✅ |

> **Untuk Flutter:** jangan bangun layar pengaturan harga atau stok. Begitu
> sebuah pengaturan ada di Laravel, Flutter **tidak** membuat salinannya.
>
> **DEC-045:** Laravel sekarang juga bisa menjalankan operasi sesi. Itu
> jalur owner, bukan pengganti kasir — tablet tetap yang dipakai melayani.

**Sekarang ada DUA pintu ke operasi yang memegang uang.** Itu aman hanya
karena keduanya memanggil Service yang sama. Kalau Anda menambah aksi web
baru, **salin juga `can:` dari `routes/api.php`** — dua pintu dengan gerbang
berbeda sama saja dengan satu pintu tanpa gerbang.

**Realtime = polling tiap 10 detik**, bukan Reverb. Reverb sudah dua kali
mati diam-diam. Penyegaran dilewati selama ada yang mengetik **atau** ada
panel aksi yang terbuka.

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
- Booking, payment gateway, laporan — semuanya Tahap 3

---

## Riwayat perubahan file ini

| Tanggal | Isi |
|---|---|
| 12 Okt 2026 | **DEC-050** — halaman **Laporan** jadi. HPP dibekukan ke baris order. Bug: `whereBetween` membuat transaksi tengah malam terhitung dua hari — Dashboard dan Laporan beda Rp 48.000. Sebelas halaman dirapikan ke satu skala padding & jarak |
| 12 Okt 2026 | **DEC-049** — data customer bisa diperbaiki (`PATCH /customers/{id}`). Jalur web yang selama ini menambah customer **tanpa audit** ikut diperbaiki |
| 12 Okt 2026 | **DEC-048** — station & tipe konsol bisa ditambah/diubah dari web. Permission baru `station.manage`. Kode station terkunci selama TV terdaftar; tipe terkunci selama ada sesi berjalan |
| 12 Okt 2026 | **DEC-047** — F&B walk-in (OD-030) **ditutup tanpa dibangun**. User: tidak bisa beli F&B saja, minimal sewa; teman yang ikut beli masuk ke sesi yang menyewa. `fnb_orders.session_id` tetap NOT NULL |
| 12 Okt 2026 | **DEC-046** — pengeluaran kasir dicatat (`POST /shifts/{id}/expenses`), `expected_cash` dihitung server. Rekonsiliasi laci berhenti menuduh kasir jujur. Bug: seluruh halaman bisa digeser menyamping di laptop 1366px — titik tukar nav dipindah ke 2xl |
| 11 Okt 2026 | **DEC-045** — Laravel punya SEMUA akses: mulai sesi, bayar, extend, swap, F&B, checkout, batal, buka shift |
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
