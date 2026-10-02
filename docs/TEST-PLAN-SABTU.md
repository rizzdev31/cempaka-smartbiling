# TEST PLAN — Sabtu (Flutter + WiFi Lokal + TV)

**Konteks:** testing APK Flutter di lokasi, masih lokal, mau dihubungkan ke TV.
**Scope:** ST01 saja (DEC-006). **Bukan** 6 TV, bukan alur TV penuh.
**Tujuan sebenarnya hari itu:** membuktikan **jalur jaringan + jalur billing** benar, dan **mengumpulkan fakta tentang TV** yang akan menentukan apakah Tahap 2 layak.

---

## Bagian 0 — Yang perlu disiapkan sebelum Sabtu

### A. Laptop sebagai server lokal

| # | Langkah | Kenapa kritis |
|---|---|---|
| 1 | `php artisan serve --host=0.0.0.0 --port=8000` | Default `artisan serve` bind ke `127.0.0.1` → **tidak bisa diakses tablet/TV sama sekali**. Ini penyebab #1 "kok nggak konek". |
| 2 | Reverb: `php artisan reverb:start --host=0.0.0.0 --port=8080` | Sama. WebSocket juga harus bind ke semua interface. |
| 3 | `php artisan schedule:work` jalan di terminal terpisah | Tanpa scheduler, session **tidak akan pernah** jadi `EXPIRED`. Warning & reconciliation mati. |
| 4 | Windows Firewall: allow inbound TCP 8000 + 8080 untuk profil **Private** | Firewall Windows memblokir koneksi dari device lain secara default. Ini penyebab #2. |
| 5 | Matikan sleep/hibernate laptop + matikan "WiFi power saving" di adapter | Laptop tidur = server mati = timer di client jalan tanpa server. |

Perintah firewall (PowerShell, **Run as Administrator**):

```powershell
New-NetFirewallRule -DisplayName "SmartBilling API 8000" -Direction Inbound -Protocol TCP -LocalPort 8000 -Action Allow -Profile Private
```

```powershell
New-NetFirewallRule -DisplayName "SmartBilling Reverb 8080" -Direction Inbound -Protocol TCP -LocalPort 8080 -Action Allow -Profile Private
```

### B. Router Archer C64

| # | Langkah | Kenapa |
|---|---|---|
| 6 | **DHCP reservation** untuk MAC laptop → IP tetap (mis. `192.168.0.50`) | Tanpa ini IP laptop berubah setiap reboot/reconnect dan semua client mati. Jangan andalkan "kebetulan dapat IP yang sama". |
| 7 | DHCP reservation untuk tablet operator & TV | Memudahkan troubleshooting dan log device. |
| 8 | Pastikan tablet, TV, dan laptop di **SSID yang sama** (bukan guest) | Guest isolation memblokir komunikasi antar device — justru yang dibutuhkan hari ini. |
| 9 | **Jangan** aktifkan AP/client isolation di SSID operasional | Isolation = device tidak bisa saling lihat sama sekali. |

### C. Build Flutter

| # | Langkah |
|---|---|
| 10 | Base URL lewat `--dart-define=API_BASE_URL=http://192.168.0.50:8000` **dan** ada settings screen untuk ganti IP tanpa rebuild |
| 11 | Flavor `dev` dengan `android:usesCleartextTraffic="true"` — **hanya dev**. Flavor `prod` tetap HTTPS-only |
| 12 | Reverb client: `wsHost=192.168.0.50`, `wsPort=8080`, `forceTLS=false`, transport `ws` saja |
| 13 | Tambahkan banner kecil di UI dev build: IP server + status WS (connected/reconnecting) |
| 14 | Endpoint `GET /api/health` yang tidak butuh auth — untuk tes cepat dari browser TV |

> Nomor 10 dan 13 akan menghemat waktu paling banyak hari Sabtu. Tanpa settings screen, setiap salah IP = rebuild APK di lokasi.

---

## Bagian 1 — Tes jaringan (30 menit pertama, sebelum buka app)

| ID | Tes | Cara | Lulus jika |
|---|---|---|---|
| N1 | Laptop punya IP yang benar | `ipconfig` | IP = yang direservasi di C64 |
| N2 | API reachable dari tablet | buka `http://<IP>:8000/api/health` di browser tablet | tampil JSON, bukan timeout |
| N3 | API reachable dari **TV** | buka URL sama di browser TV | tampil JSON |
| N4 | WebSocket reachable | dari tablet, cek koneksi WS di banner app | status `connected` |
| N5 | Latency stabil | ping laptop dari tablet, 60 detik | tidak ada packet loss, rata-rata < 30ms |
| N6 | **TV tidak drop WiFi saat idle** | diamkan TV 15 menit tanpa disentuh, lalu ping | masih reachable |

**N3 dan N6 adalah yang paling penting hari itu.** Kalau N3 gagal, Tahap 2 tidak bisa jalan apa pun yang dikoding. Kalau N6 gagal, heartbeat akan hilang terus-menerus dan desain Tahap 2 harus berubah (perlu wake lock / foreground service).

---

## Bagian 2 — Golden path ST01 di Flutter

Urut, jangan dilompati. Catat hasil di `PROGRESS.md`.

| ID | Tes | Expected |
|---|---|---|
| T01 | Login operator | masuk, token tersimpan |
| T02 | Dashboard | ST01–ST06 tampil, ST01 `AVAILABLE` |
| T03 | Start Prepaid ST01, 1 jam | `PENDING_PAYMENT` |
| T04 | Konfirmasi cash | `ACTIVE`, timer mulai, `end_at` tersimpan server |
| T05 | Timer akurat | bandingkan countdown app vs jam dinding, 5 menit → selisih < 2 detik |
| T06 | Tambah F&B dari operator | masuk Open Tab, total naik, rental **tidak** tertagih dua kali |
| T07 | Extend 30 menit + approve | `end_at` bertambah tepat 30 menit, item EXTEND muncul |
| T08 | Station Swap ST01 → ST02 | `session_id` sama, sisa waktu sama, ST01 jadi `AVAILABLE` |
| T09 | Warning | pada 10/5/1 menit muncul penanda di dashboard |
| T10 | Matikan WiFi tablet 2 menit, nyalakan | app reconnect, state benar, **tidak** ada session/payment ganda |
| T11 | Force-close app, buka lagi | state diambil dari server, timer benar |
| T12 | Checkout | hanya F&B + extend unpaid yang ditagih; satu final transaction |
| T13 | Session COMPLETED | station kembali `AVAILABLE` |
| T14 | Double-tap tombol bayar cepat | hanya **satu** payment tercatat (uji `Idempotency-Key`) |
| T15 | Extend saat sudah EXPIRED, masih dalam 10 menit | diterima; `end_at_baru` = `end_at` lama + 30 menit (bukan dari waktu approve) — DEC-007 |
| T16 | Extend saat sudah lewat 10 menit dari `end_at` | **ditolak** dengan pesan jelas; operator diarahkan checkout — DEC-007 |
| T17 | Postpaid 63 menit lalu checkout | ditagih **60 menit**, bukan 90 — DEC-009 |

**T14 sering dilewatkan dan paling mahal kalau bocor.** Operator yang ragu akan menekan tombol dua kali.

---

## Bagian 3 — Pengumpulan fakta TV (tidak butuh coding)

Hari ini TV **belum** perlu menjalankan APK Kotlin. Yang dibutuhkan adalah data untuk memutuskan Tahap 2. Catat semuanya di `PROGRESS.md`.

| ID | Yang dicatat | Kenapa |
|---|---|---|
| V1 | Merek + model persis TV | OD-005. Perilaku lock/overlay beda per model (R01) |
| V2 | Versi Android / Google TV (`Settings → About`) | Menentukan API yang tersedia |
| V3 | Android TV atau Google TV? | Berbeda soal launcher & izin overlay |
| V4 | Bisa install APK dari luar Play Store? | Kalau tidak bisa, Tahap 2 **berubah total** |
| V5 | ADB over network bisa diaktifkan? (`Developer options → Network debugging`) | Jalur deploy APK ke TV tanpa USB |
| V6 | Ada input USB/keyboard? Remote punya tombol apa saja? | Menentukan desain UI 10-foot |
| V7 | Apakah TV punya browser? | Dipakai untuk tes N3 dan fallback |
| V8 | Perilaku TV saat PS5 dimatikan — balik ke home, atau layar "no signal"? | Menentukan apakah overlay/HDMI switching perlu sama sekali |
| V9 | TV sleep otomatis setelah berapa lama? Bisa dimatikan? | Berkaitan langsung dengan N6 dan heartbeat |
| V10 | Apakah ada akun Google di TV? Siapa pemiliknya? | Device Owner mode butuh TV yang di-factory-reset tanpa akun |

> **V10 penting dan sering ditemukan terlambat:** Device Owner / Lock Task mode umumnya hanya bisa di-set pada device yang **belum punya akun**. Kalau TV sudah dipakai dengan akun Google, provisioning Device Owner butuh factory reset. Lebih baik tahu Sabtu ini daripada di Tahap 2.

---

## Bagian 4 — Yang JANGAN dilakukan Sabtu

| Jangan | Alasan |
|---|---|
| Uji 6 TV sekaligus | PRD §29. Kegagalan jadi tidak bisa dilacak |
| Pakai transaksi uang nyata | Backup/restore belum terbukti (PRD §26). Data Sabtu = data test |
| Pasang QR Customer Portal untuk customer | Portal belum ada (Tahap 3C). QR ke IP lokal tidak aman dan akan mati saat pindah VPS |
| Hardcode IP di kode "biar cepat" | Akan terbawa ke produksi. DEC-002 batasan 1 |
| Aktifkan cleartext HTTP di flavor prod | Lubang keamanan permanen |
| Tambah fitur baru di lokasi karena "sekalian" | PRD §5 + R10. Masuk Decision Log dulu |
| Simpan hasil tes hanya di kepala | Besok lupa. Tulis di `PROGRESS.md` hari itu juga |

---

## Bagian 5 — Masalah yang paling mungkin muncul & solusinya

| Gejala | Penyebab paling sering | Perbaikan |
|---|---|---|
| App "connection refused" | `artisan serve` bind `127.0.0.1` | jalankan dengan `--host=0.0.0.0` |
| App timeout, bukan refused | Windows Firewall | tambah inbound rule (Bagian 0 A.4) |
| Jalan tadi, sekarang mati | IP laptop berubah | DHCP reservation di C64 |
| API jalan, realtime tidak | port 8080 belum dibuka / `forceTLS` masih true | buka port + set `forceTLS=false` |
| `ERR_CLEARTEXT_NOT_PERMITTED` | Android blokir HTTP | `usesCleartextTraffic` di flavor dev |
| Timer beda antara tablet dan server | jam device tidak sinkron | server-time offset (DEC-003) |
| Session tidak pernah EXPIRED | scheduler tidak jalan | `php artisan schedule:work` |
| Payment dobel | tombol ditekan 2× | `Idempotency-Key` per aksi |
| TV reachable lalu hilang sendiri | WiFi power saving TV | catat sebagai V9/N6; Tahap 2 perlu foreground service |
| Semua device tidak saling lihat | AP/client isolation aktif di SSID | matikan isolation di SSID operasional |

---

## Checklist cetak untuk dibawa

```
PRA-SABTU
[ ] artisan serve --host=0.0.0.0 --port=8000
[ ] reverb:start --host=0.0.0.0 --port=8080
[ ] schedule:work jalan
[ ] firewall 8000 + 8080 allowed (Private)
[ ] DHCP reservation laptop di C64
[ ] laptop sleep OFF
[ ] APK dev build + settings screen IP
[ ] /api/health ada
[ ] seeder ST01-ST06 + packages + F&B + user

DI LOKASI
[ ] N1-N6 jaringan
[ ] T01-T14 golden path ST01
[ ] V1-V10 fakta TV dicatat
[ ] PROGRESS.md diisi sebelum pulang
```
