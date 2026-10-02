# TEST PLAN LOKASI — Dua Sesi

Testing di lokasi dipecah jadi **dua sesi** karena belum ada kode saat sesi pertama.

| | Kapan | Butuh kode? | Tujuan |
|---|---|---|---|
| **SESI 1** | **Sabtu 3 Okt 2026** | **tidak** | Buktikan jalur jaringan + kumpulkan fakta TV. Menjawab OD-005 |
| **SESI 2** | setelah vertical slice jalan | ya | Golden path ST01 dari Flutter |

Scope tetap **ST01 saja** (DEC-006). Bukan 6 TV.

> Sesi 1 tidak menguji aplikasi — aplikasinya belum ada. Yang diuji adalah **jaringan dan TV**, dan itu justru risiko terbesar proyek ini (R01, R02, R04). Pulang dengan OD-005 terjawab berarti Tahap 2 tidak lagi menebak.

---
---

# SESI 1 — Sabtu 3 Oktober (tanpa kode)

## 1.1 Persiapan — ± 15 menit

Tidak perlu Laravel, tidak perlu Flutter. Cukup satu server statis untuk membuktikan jalur jaringan.

| # | Langkah | Kenapa |
|---|---|---|
| 1 | Jalankan server uji di laptop (perintah di bawah) | membuktikan port terbuka & device lain bisa menjangkau laptop |
| 2 | Allow inbound TCP **8000** di Windows Firewall, profil **Private** | firewall Windows memblokir koneksi dari device lain secara default — penyebab kegagalan #1 |
| 3 | Catat IP laptop (`ipconfig`) | dipakai semua tes berikutnya |
| 4 | Matikan sleep/hibernate laptop + WiFi power saving di adapter | laptop tidur = server mati di tengah tes |
| 5 | Pastikan laptop, tablet, dan TV di **SSID yang sama** (bukan guest) | guest isolation memblokir komunikasi antar device |

Server uji:

```bash
python -m http.server 8000 --bind 0.0.0.0
```

Firewall (PowerShell, **Run as Administrator**):

```powershell
New-NetFirewallRule -DisplayName "SmartBilling Test 8000" -Direction Inbound -Protocol TCP -LocalPort 8000 -Action Allow -Profile Private
```

> `--bind 0.0.0.0` wajib. Tanpa itu server hanya bisa diakses dari laptop sendiri — ini kesalahan yang sama persis dengan `php artisan serve` nanti.

## 1.2 Tes jaringan

| ID | Tes | Cara | Lulus jika |
|---|---|---|---|
| N1 | IP laptop diketahui | `ipconfig` | dapat IP di subnet yang sama dengan tablet/TV |
| N2 | Laptop terjangkau dari tablet | buka `http://<IP>:8000` di browser tablet | tampil daftar file, bukan timeout |
| N3 | **Laptop terjangkau dari TV** | buka URL sama di **browser TV** | tampil daftar file |
| N5 | Latency stabil | ping laptop dari tablet, 60 detik | tanpa packet loss, rata-rata < 30 ms |
| N6 | **TV tidak drop WiFi saat idle** | diamkan TV 15 menit tanpa disentuh, lalu buka URL lagi | masih terjangkau |

*(N4 — WebSocket — pindah ke Sesi 2, butuh Reverb.)*

**N3 dan N6 adalah inti sesi ini.**
- N3 gagal → Tahap 2 tidak akan jalan apa pun yang dikoding. Harus cari tahu penyebabnya hari itu.
- N6 gagal → heartbeat akan hilang terus-menerus, dan desain Kotlin wajib pakai foreground service + wake lock. Lebih baik tahu sekarang daripada setelah APK jadi.

## 1.3 Persiapan router (kalau ada akses admin C64)

| # | Langkah | Kenapa |
|---|---|---|
| 6 | **DHCP reservation** untuk MAC laptop → IP tetap (mis. `192.168.0.50`) | tanpa ini IP laptop berubah tiap reconnect dan semua client mati |
| 7 | DHCP reservation untuk tablet & TV | memudahkan troubleshooting dan log device |
| 8 | Pastikan AP/client isolation **mati** di SSID operasional | isolation = device tidak bisa saling lihat sama sekali |

Kalau belum ada akses admin router, catat itu sebagai temuan — nanti perlu diurus sebelum Sesi 2.

## 1.4 Fakta TV — bagian paling berharga hari ini

Catat semuanya di `PROGRESS.md` **sebelum pulang**.

| ID | Yang dicatat | Kenapa |
|---|---|---|
| V1 | Merek + model **persis** TV | perilaku lock/overlay beda per model (R01) |
| V2 | Versi Android / Google TV (`Settings → About`) | menentukan API yang tersedia |
| V3 | Android TV atau Google TV? | berbeda soal launcher & izin overlay |
| V4 | **Bisa install APK dari luar Play Store?** | kalau tidak bisa, desain Tahap 2 berubah total |
| V5 | **ADB over network bisa diaktifkan?** (`Developer options → Network debugging`) | jalur deploy APK ke TV tanpa USB |
| V6 | Ada input USB/keyboard? Remote punya tombol apa saja? | menentukan desain UI 10-foot |
| V7 | Punya browser? | dipakai untuk N3 dan sebagai fallback |
| V8 | Perilaku saat PS5 dimatikan — balik ke home, atau "no signal"? | menentukan apakah overlay/HDMI switching perlu sama sekali |
| V9 | Auto-sleep setelah berapa lama? Bisa dimatikan? | berkaitan langsung dengan N6 dan heartbeat |
| V10 | **Sudah ada akun Google di TV? Milik siapa?** | Device Owner/Lock Task umumnya hanya bisa di-set pada device **tanpa akun** → butuh factory reset |

> **V4, V5, V10 yang paling menentukan.** Kalau TV tidak bisa disideload APK, atau Device Owner butuh factory reset TV yang sudah dipakai, itu keputusan bisnis — bukan masalah teknis yang bisa dikoding. Jauh lebih murah diketahui hari ini.

## 1.5 Yang JANGAN dilakukan di Sesi 1

| Jangan | Alasan |
|---|---|
| Memaksa menguji alur billing | belum ada aplikasinya |
| Factory reset TV | jangan sebelum ada keputusan soal Device Owner. Cukup **catat** kondisinya |
| Install aplikasi billing pihak ketiga "buat coba" | bisa mengubah setting TV dan mengacaukan baseline |
| Pakai transaksi uang nyata | tidak ada sistem yang mencatatnya |
| Uji 6 TV | PRD §29 — mulai dari satu |

## 1.6 Checklist cetak — Sesi 1

```
PERSIAPAN (15 menit)
[ ] python -m http.server 8000 --bind 0.0.0.0
[ ] firewall inbound 8000 allowed (Private)
[ ] ipconfig -> catat IP laptop: ________________
[ ] laptop sleep OFF
[ ] laptop + tablet + TV di SSID yang sama

JARINGAN
[ ] N1 IP laptop
[ ] N2 http://IP:8000 dari tablet
[ ] N3 http://IP:8000 dari browser TV      <-- penting
[ ] N5 ping 60 detik, tanpa loss
[ ] N6 TV idle 15 menit, masih terjangkau  <-- penting

ROUTER (kalau ada akses admin)
[ ] DHCP reservation laptop
[ ] AP/client isolation mati di SSID operasional

FAKTA TV
[ ] V1 merek + model
[ ] V2 versi Android
[ ] V3 Android TV / Google TV
[ ] V4 bisa sideload APK?                  <-- penting
[ ] V5 ADB over network?                   <-- penting
[ ] V6 input USB / tombol remote
[ ] V7 punya browser?
[ ] V8 perilaku saat PS5 mati
[ ] V9 auto-sleep berapa lama
[ ] V10 sudah ada akun Google?             <-- penting

SEBELUM PULANG
[ ] semua hasil ditulis di docs/PROGRESS.md
```

---
---

# SESI 2 — Golden path (setelah vertical slice jalan)

Prasyarat: Laravel thin slice + Flutter sudah tersambung ke API asli (bukan fake data).

## 2.1 Persiapan laptop sebagai server

| # | Langkah | Kenapa kritis |
|---|---|---|
| 1 | `php artisan serve --host=0.0.0.0 --port=8000` | default bind `127.0.0.1` → **tidak bisa diakses tablet/TV sama sekali** |
| 2 | `php artisan reverb:start --host=0.0.0.0 --port=8080` | sama, WebSocket juga harus bind ke semua interface |
| 3 | `php artisan schedule:work` di terminal terpisah | tanpa scheduler, session **tidak akan pernah** jadi `EXPIRED`. Warning & reconciliation mati |
| 4 | Firewall: allow inbound TCP **8080** (8000 sudah dari Sesi 1) | |
| 5 | Sleep/hibernate laptop tetap OFF | |

```powershell
New-NetFirewallRule -DisplayName "SmartBilling Reverb 8080" -Direction Inbound -Protocol TCP -LocalPort 8080 -Action Allow -Profile Private
```

## 2.2 Persiapan build Flutter

| # | Langkah |
|---|---|
| 6 | Base URL lewat `--dart-define=API_BASE_URL=http://192.168.0.50:8000` **dan** settings screen untuk ganti IP tanpa rebuild |
| 7 | Flavor `dev` dengan `usesCleartextTraffic="true"` — **hanya dev**. Flavor `prod` tetap HTTPS-only |
| 8 | Reverb client: `wsHost=<IP>`, `wsPort=8080`, `forceTLS=false`, transport `ws` saja |
| 9 | Banner dev build: IP server + status WS (connected/reconnecting) |
| 10 | `GET /api/v1/health` sudah ada dan tanpa auth |
| 11 | Seeder: ST01–ST06, packages, F&B dummy, 1 admin, 1 operator |

> Nomor 6 dan 9 menghemat waktu paling banyak di lokasi. Tanpa settings screen, setiap salah IP = rebuild APK di tempat.

## 2.3 N4 — WebSocket

| ID | Tes | Lulus jika |
|---|---|---|
| N4 | WebSocket terjangkau | banner di app menunjukkan `connected` |

## 2.4 Golden path ST01

Urut, jangan dilompati. Catat hasil di `PROGRESS.md`.

| ID | Tes | Expected |
|---|---|---|
| T01 | Login operator | masuk, token tersimpan |
| T02 | Dashboard | ST01–ST06 tampil, ST01 `AVAILABLE` |
| T03 | Start Prepaid ST01, 1 jam | `PENDING_PAYMENT` |
| T04 | Konfirmasi cash | `ACTIVE`, timer mulai, `end_at` tersimpan server |
| T05 | Timer akurat | bandingkan countdown app vs jam dinding, 5 menit → selisih < 2 detik |
| T06 | Tambah F&B dari operator | masuk Open Tab, total naik, rental **tidak** tertagih dua kali |
| T07 | Extend 30 menit | `end_at` bertambah tepat 30 menit, item EXTEND muncul |
| T08 | Station Swap ST01 → ST02 | `session_id` sama, sisa waktu sama, ST01 jadi `AVAILABLE` |
| T09 | Warning | pada 10/5/1 menit muncul penanda di dashboard |
| T10 | Matikan WiFi tablet 2 menit, nyalakan | app reconnect, state benar, **tidak** ada session/payment ganda |
| T11 | Force-close app, buka lagi | state diambil dari server, timer benar |
| T12 | Checkout | hanya F&B + extend unpaid yang ditagih; satu final transaction |
| T13 | Session COMPLETED | station kembali `AVAILABLE` |
| T14 | Double-tap tombol bayar cepat | hanya **satu** payment tercatat (uji `Idempotency-Key`) |
| T15 | Extend saat EXPIRED, masih dalam 10 menit | diterima; `end_at_baru` = `end_at` lama + 30 menit (bukan dari waktu approve) — DEC-007 |
| T16 | Extend saat lewat 10 menit | **ditolak** dengan pesan jelas; operator diarahkan checkout — DEC-007 |
| T17 | Postpaid 63 menit lalu checkout | ditagih **60 menit**, bukan 90 — DEC-009 |

**T14 sering dilewatkan dan paling mahal kalau bocor.** Operator yang ragu akan menekan tombol dua kali.

## 2.5 Yang JANGAN dilakukan di Sesi 2

| Jangan | Alasan |
|---|---|
| Uji 6 TV sekaligus | PRD §29. Kegagalan jadi tidak bisa dilacak |
| Pakai transaksi uang nyata | backup/restore belum terbukti (PRD §26). Data ini = data test |
| Pasang QR Customer Portal untuk customer | portal belum ada (Tahap 3C). QR ke IP lokal tidak aman dan akan mati saat pindah VPS |
| Hardcode IP di kode "biar cepat" | akan terbawa ke produksi (DEC-002) |
| Aktifkan cleartext HTTP di flavor prod | lubang keamanan permanen |
| Tambah fitur baru di lokasi karena "sekalian" | PRD §5 + R10. Masuk Decision Log dulu |

## 2.6 Checklist cetak — Sesi 2

```
PRA-SESI
[ ] artisan serve --host=0.0.0.0 --port=8000
[ ] reverb:start --host=0.0.0.0 --port=8080
[ ] schedule:work jalan
[ ] firewall 8000 + 8080 allowed (Private)
[ ] DHCP reservation laptop aktif
[ ] laptop sleep OFF
[ ] APK dev build + settings screen IP
[ ] /api/v1/health merespons
[ ] seeder ST01-ST06 + packages + F&B + user

DI LOKASI
[ ] N4 WebSocket connected
[ ] T01-T17 golden path ST01
[ ] docs/PROGRESS.md diisi sebelum pulang
```

---
---

# Masalah yang paling mungkin muncul — berlaku kedua sesi

| Gejala | Penyebab paling sering | Perbaikan |
|---|---|---|
| "Connection refused" | server bind ke `127.0.0.1` | `--bind 0.0.0.0` / `--host=0.0.0.0` |
| Timeout, bukan refused | Windows Firewall | tambah inbound rule |
| Jalan tadi, sekarang mati | IP laptop berubah | DHCP reservation di C64 |
| Semua device tidak saling lihat | AP/client isolation aktif di SSID | matikan isolation di SSID operasional |
| TV terjangkau lalu hilang sendiri | WiFi power saving TV | catat sebagai N6/V9; Kotlin perlu foreground service |
| API jalan, realtime tidak | port 8080 belum dibuka / `forceTLS` masih `true` | buka port + `forceTLS=false` |
| `ERR_CLEARTEXT_NOT_PERMITTED` | Android memblokir HTTP | `usesCleartextTraffic` di flavor **dev** |
| Timer beda antara tablet dan server | jam device tidak sinkron | server-time offset (DEC-003) |
| Session tidak pernah EXPIRED | scheduler tidak jalan | `php artisan schedule:work` |
| Payment dobel | tombol ditekan 2× | `Idempotency-Key` + disable tombol saat request |
