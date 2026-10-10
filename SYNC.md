# SYNC — apa yang berubah & siapa harus apa

> **Baca ini dulu setiap kali `git pull`.** Satu halaman, selalu diperbarui
> setiap ada perubahan backend. Kalau tidak ada nama Anda di bagian
> "Perlu dikerjakan", tidak ada yang perlu Anda lakukan.

**Terakhir diperbarui:** 10 Oktober 2026 · **Kontrak:** `v1 DRAFT 15`

---

## Keadaan sekarang

| Bagian | Status |
|---|---|
| **Backend (Laravel)** | Tahap 0 **SELESAI**. 31 endpoint, 9 event realtime, 280 test lulus |
| **Operator app (Flutter)** | Layar sudah jadi, tapi **masih memakai data palsu** — belum menyambung ke API |
| **TV Agent (Kotlin)** | Kiosk + timer jalan, tapi **belum menyambung ke Laravel** — masih menerima perintah langsung dari tablet |

---

## 🔴 Perlu dikerjakan — Flutter

| # | Pekerjaan | Kenapa mendesak |
|---|---|---|
| 1 | **`ApiBillingRepository`** menggantikan `FakeBillingRepository` | Tanpa ini aplikasi tidak pernah menyentuh server. Semua di bawah ini sia-sia |
| 2 | Login / auth (simpan token Sanctum) | Semua endpoint selain `/health` butuh token |
| 3 | Klien Reverb + reconnect | Tablet tidak tahu apa pun yang terjadi di tablet/TV lain |
| 4 | Dengarkan `master.updated` → muat ulang `GET /packages` | Harga yang diubah owner tidak akan terlihat |

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

Detail lengkap: `docs/contracts/CHANGELOG.md` DRAFT 6 s/d 15.

---

## 🔴 Perlu dikerjakan — Kotlin TV Agent

| # | Pekerjaan |
|---|---|
| 1 | `POST /devices/register` pakai kode station, simpan `device_token` permanen |
| 2 | `POST /devices/heartbeat` tiap 30 detik, kirim `known_session_id` + `known_end_at` |
| 3 | `GET /devices/me/state` saat boot & reconnect |
| 4 | Subscribe `private-station.{code}` lewat `X-Device-Token` |

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
| 10 Okt 2026 | Dibuat. Tahap 0 selesai, endpoint harga + `master.updated` ditambahkan |
