# tv-agent — Kotlin Android TV

Agen pada TV tiap station. Menampilkan timer dan menerima perintah operator.

**Status:** kiosk + timer + kontrol langsung lewat LAN berjalan. 31 unit test lulus, APK debug ter-build.

> ⚠️ **Mode kontrol langsung ini sementara.** Lihat `../docs/DECISION-LOG.md` → **DEC-015**. Di Tahap 2 sebenarnya, perintah datang dari Laravel lewat Reverb. Yang diganti hanya satu kelas — lihat [Batas yang menjaga ini tidak terbuang](#batas-yang-menjaga-ini-tidak-terbuang).

---

## Sebelum menulis kode, baca

1. [`../CLAUDE.md`](../CLAUDE.md) — aturan kerja
2. [`../docs/DECISION-LOG.md`](../docs/DECISION-LOG.md) — **DEC-015** menjelaskan kenapa mode ini ada
3. [`../docs/PRD-V2.md`](../docs/PRD-V2.md) §16 — timer & TV agent
4. [`../docs/contracts/API.md`](../docs/contracts/API.md) §9 — endpoint device yang jadi acuan
5. [`../docs/UI-UX-SPEC.md`](../docs/UI-UX-SPEC.md) §9 — 10-foot UI

---

## Build

Android Gradle Plugin butuh **JDK 17 atau 21**. Mesin ini punya `JAVA_HOME` ke JDK 23 yang belum didukung, jadi arahkan ke JBR milik Android Studio:

```bash
cd tv-agent && JAVA_HOME="/c/Program Files/Android/Android Studio/jbr" ./gradlew :app:assembleDebug
```

```bash
cd tv-agent && JAVA_HOME="/c/Program Files/Android/Android Studio/jbr" ./gradlew :app:testDebugUnitTest
```

APK: `app/build/outputs/apk/debug/app-debug.apk`

`local.properties` berisi `sdk.dir` dan **tidak di-commit**. Kalau belum ada:

```bash
printf 'sdk.dir=C:/Users/Rifqi/AppData/Local/Android/Sdk\n' > local.properties
```

> Pakai **garis miring**, bukan backslash. Di file `.properties` Java, `\` adalah karakter escape — `C:\Users\...` akan terbaca `C:Users...` dan build gagal dengan pesan menyesatkan ("filename, directory name, or volume label syntax is incorrect").

## Memasang ke TV

```bash
adb connect <IP-TV>:5555
```

```bash
adb install -r app/build/outputs/apk/debug/app-debug.apk
```

TV perlu **Developer options → Network debugging** aktif. Apakah itu tersedia adalah bagian dari **OD-005** (V5 di `TEST-PLAN-SABTU.md`).

---

## Cara pakai

1. Buka aplikasi di TV. Karena belum dipasangkan, layar menampilkan **kode 6 digit** dan **alamat IP:port**.
2. Operator memasukkan kode itu (nanti dari aplikasi operator; sekarang bisa lewat `curl`).
3. TV menyimpan device token dan pindah ke layar idle.
4. Operator mengirim perintah sesi; TV menampilkan timer.

### Coba manual dengan curl

```bash
curl -s http://<IP-TV>:8787/health
```

```bash
curl -s -X POST http://<IP-TV>:8787/pair -H 'Content-Type: application/json' -d '{"code":"123456","station_code":"ST01","sender_time":1760000000000}'
```

```bash
curl -s -X POST http://<IP-TV>:8787/session -H 'Content-Type: application/json' -H 'X-Agent-Token: <token>' -d '{"session_id":"ses-1","station_code":"ST01","customer_label":"Budi","started_at":1760000000000,"end_at":1760003600000,"sender_time":1760000000000}'
```

`sender_time` adalah epoch millis. Isi dengan waktu sekarang — itu yang dipakai TV untuk mengoreksi jamnya sendiri.

---

## Endpoint kontrol

| Metode | Path | Auth | Guna |
|---|---|---|---|
| GET | `/health` | — | identitas + kemampuan perangkat |
| POST | `/pair` | kode | tukar kode jadi device token |
| GET | `/state` | token | state yang sedang ditampilkan |
| POST | `/session` | token | mulai / perbarui sesi |
| DELETE | `/session` | token | akhiri sesi |
| POST | `/display/lock` | token | diterima, **belum berefek** (OD-001) |
| POST | `/unpair` | token | cabut pairing |

`/health` sengaja tanpa auth — operator harus bisa menemukan TV sebelum punya token. Isinya hanya identitas perangkat: **tidak ada** data sesi, customer, atau uang.

Bentuk error mengikuti kontrak §2 (`{ "error": { "code", "message" } }`) supaya penanganannya di operator app sama untuk agen maupun Laravel nanti.

---

## Autentikasi — dan kenapa tidak bisa ditunda

Kontrol langsung tanpa autentikasi berarti **siapa pun di WiFi yang sama bisa menyetel timer TV**. Customer di Guest Wi-Fi bisa memperpanjang sesinya sendiri secara gratis. Itu kelas risiko yang sama dengan *prank order* (PRD §13) dan **R05** (HIGH).

Jadi: TV menampilkan kode, operator memasukkannya sekali, TV memberi token. Kode **sekali pakai** dan hangus setelah berhasil; 10 percobaan salah mengunci pairing sampai aplikasi di-restart.

**Batasnya, jujur:** siapa pun yang bisa melihat layar TV bisa membaca kodenya. Ini memadai untuk jaringan operasional, **bukan** untuk TV yang terjangkau dari Guest Wi-Fi. Pemisahan guest di PRD §9 tetap wajib.

---

## Mode kiosk — dua tingkat, yang kedua belum pasti

| Tingkat | Cara | Bisa keluar? |
|---|---|---|
| **1 — selalu** | fullscreen immersive, layar tetap menyala, tombol kembali ditahan, foreground service, auto-start saat boot | **Ya**, lewat HOME |
| **2 — kiosk sebenarnya** | Lock Task mode | Tidak |

Tingkat 2 butuh **Device Owner**:

```bash
adb shell dpm set-device-owner id.cempaka.tvagent/.AgentDeviceAdminReceiver
```

Hanya berhasil pada perangkat **tanpa akun**. TV yang sudah dipakai dengan akun Google perlu factory reset lebih dulu — itu **OD-005 / V10**, dan keputusan bisnis, bukan teknis.

Aplikasi **melaporkan tingkat mana yang aktif** lewat `GET /health` (`device.kiosk_tier`) dan menampilkannya di baris diagnostik layar. PRD §5 melarang mengklaim dukungan kiosk sebelum terbukti, jadi aplikasi ini tidak pernah mengklaim — ia melaporkan.

---

## Batas yang menjaga ini tidak terbuang

```
AgentCommand  ──►  CommandApplier  ──►  StateStore  ──►  KioskActivity
     ▲
     │
CommandSource
  ├─ LocalHttpCommandSource   (sekarang — HTTP langsung dari operator)
  └─ ReverbCommandSource      (Tahap 2 — event dari Laravel)
```

Layar kiosk, timer, persistence, dan recovery **tidak tahu** dari mana perintah datang. Satu-satunya tempat sumbernya dipilih ada di `AgentService.ensureCommandSource()` — satu blok, satu kelas.

`AgentCommand` juga sengaja dibentuk mengikuti event di `contracts/REALTIME.md` §5, bukan mengikuti bentuk HTTP lokal.

## Aturan yang tidak boleh dilanggar

- **TV bukan source of truth** (PRD §8). Tidak ada harga, durasi paket, atau logika billing di sini — bahkan dalam mode langsung. TV hanya tahu kapan sesi berakhir.
- **Timer dihitung dari `end_at`**, bukan dari countdown yang dikirim per detik. Ini yang membuat timer tetap benar saat koneksi terputus.
- **Server-time offset** (DEC-003) dipakai untuk semua perhitungan waktu. Jam TV sering salah setelah boot sebelum NTP jalan.
- `end_at` **dipersist sinkron** (`commit()`), bukan `apply()`. Kalau TV mati listrik sedetik setelah sesi dimulai, waktunya harus sudah di disk.
- **Perintah untuk station lain ditolak.** Perintah yang salah kirim tidak boleh mengubah label TV diam-diam.
- **Jangan klaim dukungan kiosk/overlay/HDMI** sebelum diuji di TV aktual (PRD §5, R01).

---

## Yang belum ada

| Belum ada | Catatan |
|---|---|
| Klien Reverb | Tahap 2 sebenarnya; butuh Laravel |
| Heartbeat ke server | butuh Laravel (`POST /devices/heartbeat`) |
| Peringatan suara 10/5/1 menit | menunggu **OD-004** |
| Perilaku `LOCKED` | menunggu **OD-001**; perintahnya diterima tapi tidak berefek |
| Integrasi dari aplikasi operator | berikutnya — penemuan TV lewat subnet scan + panel kontrol |
| Bar progres waktu di layar TV | sengaja ditunda; timer besar sudah cukup dari jarak 3 meter |

## Test

31 unit test JVM, tanpa emulator:

| File | Yang dikunci |
|---|---|
| `TimeSyncTest` | koreksi jam TV yang salah, sisa waktu negatif tidak di-clamp, kompensasi latensi |
| `PairingTest` | kode sekali pakai, penguncian setelah 10 percobaan, validasi token, normalisasi station |
| `CommandApplierTest` | penolakan sebelum pairing, penolakan perintah salah sasaran, jalur extend, `LOCKED` tidak menebak perilaku |

`TimeSync` memakai `System.nanoTime()`, bukan `SystemClock.elapsedRealtime()` — keduanya monotonik, tapi yang pertama murni Java sehingga aturan waktu bisa diuji murah. Aturan waktu adalah bagian yang paling mahal kalau salah.

## Lisensi font

Font di `app/src/main/res/font/` sama dengan operator app (JetBrains Mono, Plus Jakarta Sans, Space Grotesk), SIL Open Font License 1.1. Teks lisensi ada di `licenses/` dan **wajib tetap disertakan**.
