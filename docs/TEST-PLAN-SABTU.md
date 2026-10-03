# TEST PLAN LOKASI — Dua Sesi

Testing di lokasi dipecah jadi **dua sesi** karena belum ada kode saat sesi pertama.

| | Kapan | Butuh kode? | Tujuan |
|---|---|---|---|
| **SESI 1** | **Sabtu 3 Okt 2026** | **tidak** | Buktikan jalur jaringan + kumpulkan fakta TV. Menjawab OD-005 |
| **SESI TV** | kapan saja — bisa di rumah | ya, sudah ada | Buktikan operator bisa mengendalikan TV. Tidak butuh Laravel |
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

# SESI TV — operator mengendalikan TV

**Tidak butuh Laravel.** Ini menguji jalur DEC-015: operator → TV langsung
lewat jaringan lokal. Bisa dikerjakan di rumah dengan emulator, atau di lokasi
dengan TV sungguhan.

## TV-0 Siapkan

**Pilihan A — emulator (paling cepat, tanpa TV)**

Keduanya di satu emulator. Alamat yang dipakai adalah `127.0.0.1` dari sudut
pandang emulator itu sendiri.

```bash
flutter emulators --launch Pixel_4
```

```bash
cd tv-agent && JAVA_HOME="/c/Program Files/Android/Android Studio/jbr" ./gradlew :app:installDebug
```

```bash
cd operator-app && flutter run -d android --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

**Pilihan B — TV sungguhan + tablet** *(yang sebenarnya ingin dibuktikan)*

```bash
adb connect <IP-TV>:5555
```

```bash
cd tv-agent && JAVA_HOME="/c/Program Files/Android/Android Studio/jbr" ./gradlew :app:assembleDebug && adb install -r app/build/outputs/apk/debug/app-debug.apk
```

Operator app: pasang `app-arm64-v8a-debug.apk` dari
`operator-app/build/app/outputs/flutter-apk/`.

TV dan tablet **wajib di SSID yang sama**, dan AP/client isolation **mati**.

---

## Cara memasang APK ke Android TV

### Kenapa lewat flashdisk sering gagal

APK-nya ada di flashdisk, tapi tidak muncul. Penyebabnya hampir selalu salah
satu dari ini — dan tidak satu pun ditandai dengan pesan error:

| Sebab | Tandanya |
|---|---|
| Android TV **tidak punya file manager bawaan** yang bisa memasang APK | Berkasnya tidak muncul di mana pun |
| File manager bawaan menyaring tipe berkas | Folder terbuka, isinya "kosong" padahal ada APK |
| "Install unknown apps" belum diberikan **ke aplikasi yang membuka berkas itu** | APK terlihat, ditekan, tidak terjadi apa-apa |
| Flashdisk ber-format exFAT/NTFS | Flashdisk tidak terbaca sama sekali → pakai **FAT32** |

Izin "unknown sources" di Android TV diberikan **per aplikasi**, bukan sekali
untuk seluruh sistem. Memberikannya ke peramban tidak membuat file manager
ikut boleh memasang.

### Cara A — ADB lewat jaringan (dianjurkan)

Tidak perlu flashdisk, dan ini jalur yang sama dipakai untuk memasang ulang
tiap kali APK diperbarui. Ini juga yang menjawab **V5**.

**Di TV:**
1. `Settings → Device Preferences → About`
2. Tekan **Build** 7 kali sampai muncul "You are now a developer"
3. `Settings → Device Preferences → Developer options`
4. Nyalakan **USB debugging** dan **Network debugging** (namanya bisa
   "Wireless debugging" atau "ADB debugging" tergantung merek)
5. Catat IP TV: `Settings → Network & Internet → (jaringan aktif)`

**Di laptop:**

```bash
"/c/Users/Rifqi/AppData/Local/Android/Sdk/platform-tools/adb.exe" connect <IP-TV>:5555
```

> Di TV akan muncul **"Allow USB debugging?"** — centang "Always allow" lalu
> OK. Kalau dialog ini tidak disetujui, `adb install` akan gagal dengan
> `device unauthorized`.

```bash
"/c/Users/Rifqi/AppData/Local/Android/Sdk/platform-tools/adb.exe" install -r "/c/Users/Rifqi/Documents/Smart Biling/tv-agent/app/build/outputs/apk/debug/app-debug.apk"
```

Keluarannya harus `Success`. Aplikasinya muncul di laci aplikasi TV dengan
nama **Cempaka TV**.

**Kalau gagal:**

| Pesan | Artinya |
|---|---|
| `failed to connect` | Network debugging mati, IP salah, atau beda subnet |
| `device unauthorized` | Dialog izin di TV belum disetujui |
| `INSTALL_FAILED_UPDATE_INCOMPATIBLE` | Versi lama tertanda kunci berbeda → `adb uninstall id.cempaka.tvagent.debug` dulu |
| `INSTALL_FAILED_INSUFFICIENT_STORAGE` | Penyimpanan TV penuh |

### Cara B — unduh dari laptop lewat peramban TV

Kalau ADB tidak bisa dinyalakan di TV itu. Jalankan di root repo:

```bash
python -m http.server 8000 --directory "tv-agent/app/build/outputs/apk/debug"
```

Lalu di TV pasang **Downloader** (AFTVnews) dari Play Store, dan buka:

```
http://192.168.0.106:8000/app-debug.apk
```

Downloader akan meminta izin "install unknown apps" untuk dirinya sendiri —
berikan. IP di atas adalah IP laptop ini; cek ulang kalau jaringannya pindah.

### Cara C — flashdisk, tapi dengan file manager yang benar

Flashdisk **FAT32**, lalu di TV pasang **X-plore File Manager** atau **FX File
Explorer** dari Play Store. Buka APK-nya dari sana, dan berikan izin "install
unknown apps" **kepada file manager itu** saat diminta.

> Cara A yang dipakai berulang selama pengembangan: memasang ulang APK yang
> sudah diperbarui cukup satu perintah, tanpa menyentuh TV.

## TV-1 Pairing

| ID | Langkah | Lulus jika |
|---|---|---|
| TV-01 | Buka **Cempaka TV** di TV | Tampil kode 6 digit + alamat `IP:8787` |
| TV-02 | Operator: **Status TV** | Semua station "Belum dipasang" |
| TV-03 | ST01 → **Pasang TV** → tunggu pemindaian | TV ST01 muncul di daftar, ditandai "bebas" |
| TV-04 | Kalau pemindaian kosong, masukkan alamat dari layar TV → **Cek** | Perangkat terdeteksi, model & Android terbaca |
| TV-05 | Masukkan kode dari layar TV → **Pasangkan** | Layar TV langsung berganti dari kode ke sesi/idle |
| TV-06 | Masukkan kode **salah** lebih dulu | Ditolak dengan pesan jelas, tidak terpasang |
| TV-07 | Ulangi kode salah 10×| TV mengunci pairing; perlu restart aplikasi di TV |

> TV-06 dan TV-07 adalah pengaman yang menghalangi orang lain di WiFi yang
> sama menyetel timer TV. Layak diuji sekali.

## TV-2 Kontrol billing

Seluruh bagian ini memakai **data contoh** — header operator menandainya
`DATA CONTOH`. Yang diuji adalah **jalurnya**, bukan angkanya.

| ID | Langkah | Lulus jika |
|---|---|---|
| TV-10 | ST01 sudah ada sesi contoh berjalan | TV menampilkan timer yang sama dengan kartu ST01 |
| TV-11 | Bandingkan detik di TV dan di kartu operator | Selisih < 2 detik |
| TV-12 | Kartu ST01 → **+30m** → konfirmasi | Timer TV bertambah 30 menit dalam beberapa detik |
| TV-13 | Kartu ST01 → **+1j** | Timer TV bertambah 1 jam |
| TV-14 | Tambah F&B dari kartu | **TV tidak berubah** — F&B tidak ditampilkan di TV |
| TV-15 | Checkout ST01 sampai selesai | TV kembali ke layar idle "Tersedia" |
| TV-16 | Mulai sesi baru di ST01, pilih **Prepaid** | TV menampilkan "Menunggu pembayaran" |
| TV-17 | Konfirmasi pembayaran | TV berganti ke timer |
| TV-18 | **Pindah Station** ST01 → ST02 (ST02 belum ada TV) | TV ST01 kembali idle |

> **TV-14 penting.** Kalau TV ikut berkedip setiap teh manis ditambahkan,
> berarti tagihan masuk ke sidik keadaan dan setiap item memicu permintaan
> yang tidak perlu.

## TV-3 Ketahanan

Ini yang membedakan "jalan di meja" dari "jalan di lokasi".

| ID | Langkah | Lulus jika |
|---|---|---|
| TV-20 | Matikan WiFi TV 1 menit saat timer jalan | **Timer TV tetap berjalan** dan tetap benar |
| TV-21 | Nyalakan WiFi kembali | Status operator kembali "Tersambung" setelah **Periksa** |
| TV-22 | Saat TV mati, lakukan +30m dari operator | Operator menandai "Tidak merespons", tidak diam saja |
| TV-23 | TV hidup lagi → **Kirim ulang** | Timer TV menyusul ke nilai yang benar |
| TV-24 | **Restart aplikasi TV** (force stop lalu buka) | Timer lanjut dari waktu yang benar, bukan dari nol |
| TV-25 | **Restart TV** sepenuhnya, buka aplikasi | Timer masih benar; tidak perlu pairing ulang |
| TV-26 | Diamkan TV 15 menit tanpa disentuh | Masih merespons **Periksa** dari operator |
| TV-27 | Tekan **HOME** di remote TV | Keluar dari aplikasi — ini **wajar** pada kiosk lunak |
| TV-28 | Buka aplikasi TV lagi | Timer benar, tanpa pairing ulang |

> **TV-24 dan TV-25 adalah inti PRD §16 dan T12.** Kalau gagal, berarti
> `end_at` tidak tersimpan ke disk dan customer bisa kehilangan waktu
> bermainnya setiap kali TV tersendat.
>
> **TV-27 bukan kegagalan.** Kiosk yang benar-benar tidak bisa ditinggalkan
> butuh Device Owner — lihat OD-005/V10. Aplikasi melaporkan tingkat kiosk
> yang aktif di baris bawah layar TV dan di layar Status TV.

## TV-4 Yang perlu dicatat

| Yang dicatat | Dari mana |
|---|---|
| Model & versi Android TV | Status TV di operator, atau baris bawah layar TV |
| Tingkat kiosk (lunak / terkunci) | sama |
| Berapa lama pemindaian menemukan TV | layar Pasang TV |
| Apakah pemindaian menemukan TV, atau harus manual | sama |
| Apakah TV tetap merespons setelah 15 menit idle | TV-26 |
| Apakah timer benar setelah TV restart | TV-24, TV-25 |

Semuanya melengkapi **OD-005**. Tulis hasilnya di `PROGRESS.md` di bawah.

## TV-5 Yang JANGAN dilakukan

| Jangan | Alasan |
|---|---|
| Menyimpulkan angka di layar sebagai transaksi nyata | Semua data billing masih contoh — header menandainya `DATA CONTOH` |
| `dpm set-device-owner` pada TV yang sudah dipakai | Butuh TV tanpa akun; bisa berarti factory reset. Keputusan bisnis, bukan teknis (OD-005/V10) |
| Memasangkan TV ke station lewat Guest Wi-Fi | Kode pairing hanya melindungi dari penyalahgunaan tidak sengaja. Pemisahan guest PRD §9 tetap wajib |
| Menganggap kiosk sudah aman karena back tidak berfungsi | HOME masih bisa pada kiosk lunak |

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
