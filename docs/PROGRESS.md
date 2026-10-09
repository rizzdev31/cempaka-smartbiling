# PROGRESS — Cempaka Smart Billing

Log harian. **Update setiap selesai kerja**, jangan ditumpuk.
Format entry: tanggal → apa yang dikerjakan → hasil → known issue → next step.

---

## Status sekarang

| | |
|---|---|
| **Tahap aktif** | **TAHAP 2 — Kotlin TV Agent** (kiosk kontrol-langsung, DEC-015) **+ TAHAP 0 — Laravel API Core** berjalan paralel sejak 7 Okt (DEC-022) |
| **Blocker** | **tidak ada** — kontrak sudah fix, billing rule sudah fix |
| **Blocker Tahap 2** | OD-004 (perilaku warning), OD-005 (fakta TV — dicek di **SESI 1, Sab 3 Okt 2026**) |
| **Milestone terdekat** | **SESI TV sedang berjalan.** APK sudah terpasang di TV (`192.168.0.100`); tertahan di jaringan — laptop/tablet harus pindah ke SSID yang sama |
| **Repo** | monorepo private, `github.com/rizzdev31/cempaka-smartbiling` (DEC-010) |
| **Kontrak** | `docs/contracts/` DRAFT 1 selesai (DEC-011) |
| **operator-app** | Semua screen PRD §18 kecuali Login & Booking; kontrol TV terpasang & status TV disatukan; **tema terang** (DEC-016); merek **Amor Gaming Space** (DEC-017); **202 test lulus** |
| **tv-agent** | kiosk + timer + kontrol HTTP lokal; **31 test lulus**; APK debug 4,2 MB **sudah terpasang di TV**; sambungan operator↔TV **belum terbukti** |
| **backend** | **TAHAP 0 SELESAI.** Laravel 13.35.0 + Sanctum; 18 entity; auth + RBAC 3 role; mesin state + billing engine; F&B, swap, checkout, shift, customer/membership; Reverb 8 event + debounce; scheduler; **endpoint `/devices/*` (10 Okt) — Tahap 2 terbuka**. **262 test LULUS** (64 unit + 198 feature, 918 assertion), **golden path 38/38 lewat HTTP tanpa menyentuh database**, dan realtime terbukti sampai ke client. 28 endpoint. Berikutnya: keputusan OD-002 sebelum dipakai untuk uang nyata |

### ⏳ Pertanyaan tertunda — ingatkan user

| ID | Pertanyaan | Ditunda sejak | Pemicu peninjauan |
|---|---|---|---|
| **OD-011** | Perlukah penemuan IP server otomatis (scan subnet) di Flutter? | 2 Okt 2026 | **Setelah DHCP reservation diuji di SESI 1.** Kalau IP laptop tetap stabil → tidak perlu. Kalau masih sering berubah → pasang scan subnet (± 100 baris) |
| **OD-019** | Owner ubah tarif lewat **login owner** di tablet, atau **PIN** di atas sesi operator? | 7 Okt 2026 | Saat layar pengaturan tarif dikerjakan. Backend sama (token owner) — ini soal cara login di Flutter |
| **OD-002** | Mitigasi Postpaid/Open Tab kabur tanpa bayar — deposit? batas maksimum? catat identitas? | 2 Okt 2026 | User menunda 8 Okt: "tanyakan nanti lagi, saya konfirmasi dulu" |

| **OD-021** | Toleransi pembulatan **overstay** — dipakai DEC-009 tanpa lantai 30 menit (lewat 4 menit = tidak ditagih). Benar? | 8 Okt 2026 | Saat checkout dikerjakan. Sudah jalan sebagai asumsi; mengubahnya cukup di `OverstayPolicy` |
| **OD-022** | Daftar member di kasir: biayanya berapa, siapa yang boleh mendaftarkan (bentrok OD-014), saldo berlaku berapa lama, bisa diuangkan? | 8 Okt 2026 | Saat checkout dikerjakan. Muncul dari DEC-024 |

> Sudah diputuskan 7 Okt 2026: OD-012 → **DEC-018** (per-instance) · OD-015 → **DEC-019** (tarif per tipe konsol, diatur dari aplikasi kasir) · OD-017 → **DEC-020** (hanya owner yang boleh ubah tarif) · OD-018 → **DEC-021** (swap hanya dalam tipe konsol yang sama).

Analisis lengkap ada di `DECISION-LOG.md` → OD-011 (termasuk deteksi TV lewat heartbeat) dan OD-012.

---

### Checklist Tahap 0

- [x] Monorepo + struktur folder + `.gitignore` (DEC-010)
- [x] Kontrak `docs/contracts/API.md` + `REALTIME.md` + `CHANGELOG.md` DRAFT 1 (DEC-011)
- [x] Repo Laravel dibuat + `.env.example` — Laravel 13.35.0 + Sanctum (7 Okt)
- [x] MySQL lokal + migration 16 entity Tahap 0 (PRD §22) — 7 Okt. `bookings`/`expenses`/`targets`/`notifications`/`backups` ditunda ke Tahap 3 (aturannya masih TBD)
- [x] Seeder: ST01–ST06, tipe konsol (DEC-019), packages, F&B dummy, 1 owner, 1 admin, 1 operator — 7 Okt
- [x] Auth + RBAC **owner/admin/operator** (3 role — DEC-020) — 7 Okt, login/me/logout + Gate per permission
- [x] `GET /api/v1/health` (tanpa auth) — 7 Okt
- [x] Header `server_time` di semua response (DEC-003) — 7 Okt, middleware global
- [x] Middleware `Idempotency-Key` — 7 Okt, 8 test
- [x] Session state machine + test per transisi — 8 Okt, `SessionStatus::allowedNext()` + 12 test
- [x] Billing engine: Prepaid / Postpaid / Open Tab / `session_items` — 8 Okt, `SessionTotals` + `SessionService`
- [x] Rounding durasi Postpaid per DEC-009 + unit test (35→30, 63→60, 70→90, 95→90) — 8 Okt
- [x] Extend blok 30 menit per DEC-007 — 8 Okt. ~~Grace 10 menit~~ **dicabut DEC-033**: extend hanya boleh sebelum waktu habis
- [x] Payment manual cash + QRIS statis + audit — 8 Okt (checkout belum)
- [x] ~~Overstay DEC-023~~ — **dicabut DEC-033**: waktu habis berarti berhenti, TV mati, tidak ada penagihan kelebihan sama sekali
- [x] F&B order endpoint → 8 Okt, 4 endpoint + antrian dapur
- [x] Extend + approval operator → 8 Okt (operator = approver, PRD §14)
- [x] Station Swap atomic → 8 Okt, jaminan R07 diuji terpisah
- [x] Checkout → satu final transaction → 8 Okt, termasuk saldo member (DEC-026)
- [x] Reverb lokal + semua event PRD §23 → 8 Okt, 8 event (`device.heartbeat` menunggu Tahap 2)
- [x] Scheduler reconciliation `session.expired` → 8 Okt, `sessions:reconcile` tiap menit
- [x] `audit_logs` terisi untuk aksi sensitif → 8 Okt, 14 aksi termasuk saldo member
- [x] Shift kasir: `open` / `close` / `current` → 8 Okt. Sebelumnya `shift_id` selalu NULL
- [x] Customer + membership: cari, daftar, jadikan member (DEC-027/029) → 8 Okt
- [x] Master data read-only: `GET /stations`, `GET /packages` → 9 Okt. `/packages` menerima filter `station_id` (DEC-019)
- [x] `POST /sessions/{id}/cancel` → 9 Okt. Batal dari `PENDING_PAYMENT`, station langsung kosong
- [x] Throttle broadcast (REALTIME.md §7): debounce `session.updated` 500 ms → 10 Okt, lewat job tertunda yang membaca ulang sesi saat berjalan
- [x] Golden path ST01 lulus → 8 Okt. 33/33 lewat HTTP (`php artisan serve` + curl), plus `GoldenPathTest` otomatis. Koleksi Postman di `docs/postman/`. **9 Okt: exit criteria ROADMAP terpenuhi penuh** → 38 pemeriksaan lewat HTTP, semua id dari `GET /stations` / `GET /packages` / `GET /fnb/products`, tanpa menyentuh database sama sekali

### Checklist Tahap 1 (Flutter)

- [x] Project Flutter + pemisahan dev/prod lewat Android source set
- [x] `ApiConfig` + settings screen ganti IP tanpa rebuild
- [x] Tema dark + tokens dari `UI-UX-SPEC.md`
- [x] Komponen: `StationCard`, `CountdownText`, `MoneyText`, `StatusChip`, `ConnectionBanner`, `ConfirmDialog`, `AsyncButton`
- [x] Ticker global timer + server-time offset
- [x] Model + error code ditranskrip dari kontrak
- [x] Fake repository yang mencerminkan aturan server
- [x] Dashboard 6 station (grid 3×2 landscape)
- [x] Start Session (Prepaid/Postpaid + pilih member)
- [x] Session Detail + Open Tab
- [x] Payment (cash + QRIS manual)
- [x] Extend + Station Swap + Tambah F&B
- [x] Checkout + struk (durasi aktual vs tertagih)
- [x] 128 test lulus (billing + layout + F&B + shift + device + customer + filter)
- [x] Design pass 1: rail status, bar proporsi waktu, brand mark, permukaan bertingkat
- [ ] Login / auth
- [ ] Reverb client + auto-reconnect + reconcile
- [x] F&B Queue (layar antrian + badge di dashboard)
- [x] Shift start/close/handover + pertanggungjawaban kas
- [x] Device status (layar Status TV, read-only)
- [ ] **Ganti fake repository → `ApiBillingRepository`** (DEC-012 syarat 2)
- [ ] Bundel font Fira Sans/Code

---

## Log

### 2026-10-02 — Setup dokumentasi

**Dikerjakan**
- PRD Word dibaca & dikonversi → `PRD-V2.md`
- `CLAUDE.md` dibuat sebagai kontrak kerja agent (dibaca setiap sesi)
- Urutan tahap diubah sesuai keputusan tim → `ROADMAP.md` + DEC-001
- `DECISION-LOG.md`: DEC-001 s/d DEC-006 + 10 Open Decision baru (OD-001…OD-010)
- `TEST-PLAN-SABTU.md`: persiapan server lokal, tes jaringan N1–N6, golden path T01–T14, pengumpulan fakta TV V1–V10
- `UI-UX-SPEC.md`: design system dark mode, status color, aturan timer, 10-foot UI untuk TV

**Temuan penting**
1. Keputusan "Laravel tahap 3" bentrok dengan PRD §8/§33 kalau diartikan API juga ditunda. Diselesaikan dengan memisahkan **API Core (Tahap 0)** dari **Superadmin Web (Tahap 3)**. Keputusan tim tetap jalan.
2. PRD tidak mengatur **timezone** dan **tipe data uang** → ditetapkan di DEC-005 sebelum ada kode.
3. PRD tidak mengatur **apa yang terjadi saat EXPIRED tapi customer masih bermain** (OD-001) — padahal ini kejadian harian.
4. PRD memperbolehkan Postpaid/Open Tab tanpa mitigasi kerugian kalau customer kabur (OD-002).
5. Tiga keputusan memblokir Tahap 0: pricing extend (OD-003), satu session banyak customer (OD-007), rounding durasi (OD-008).
6. Risiko terbesar hari Sabtu bukan Flutter, tapi **apakah TV bisa dipasang APK dan tidak memutus WiFi saat idle** (V4, V5, N6, V9).

**Known issues**
- Belum ada kode sama sekali
- OD-003 / OD-007 / OD-008 belum diputuskan → billing engine belum boleh ditulis

**Next step**
1. Inisialisasi repo Laravel + migration + seeder
2. Siapkan server lokal sesuai `TEST-PLAN-SABTU.md` Bagian 0

---

### 2026-10-02 — Keputusan billing (lanjutan)

**Dikerjakan**
- OD-003, OD-007, OD-008 dijawab user → jadi **DEC-007, DEC-008, DEC-009**
- Aturan billing final ditambahkan ke `CLAUDE.md` §4 supaya dibaca setiap sesi
- Checklist Tahap 0 ditambah dua item test billing

**Keputusan**
| | |
|---|---|
| Extend | kelipatan 30 menit, tarif proporsional dari tarif per jam paket |
| Extend setelah EXPIRED | boleh dalam grace **10 menit**; `end_at_baru` dihitung dari `end_at` lama |
| Customer per session | **satu**; `customer_id` nullable single FK, tanpa tabel pivot |
| Rounding durasi | per 30 menit, toleransi 5 menit; **Postpaid saja** |

**Known issues**
- Penagihan **overstay** (Prepaid lewat `end_at`) sengaja belum diatur → OD-001. Jangan diputuskan di kode.
- OD-004 & OD-005 masih menghalangi Tahap 2.

**Next step**
1. Inisialisasi repo Laravel + migration + seeder
2. Tulis billing engine + unit test rounding & extend sesuai DEC-007/009

---

### 2026-10-02 — Monorepo + kontrak API/realtime

**Dikerjakan**
- `git init` (branch `main`) + remote `github.com/rizzdev31/cempaka-smartbiling`
- `.gitignore` mencakup Laravel, Flutter, Android/Kotlin, IDE, OS — `.env` dan semua kredensial diblokir
- Struktur monorepo: `backend/`, `operator-app/`, `tv-agent/`, `docs/contracts/` → DEC-010
- README per folder app berisi aturan yang mengikat + blocker-nya
- `docs/contracts/API.md` DRAFT 1 — konvensi, envelope, 22 error code, aturan idempotency, semua endpoint Tahap 0–2, protokol reconnect
- `docs/contracts/REALTIME.md` DRAFT 1 — 3 channel, 8 event + payload, throttle, kewajiban per client
- `docs/contracts/CHANGELOG.md` + DEC-011

**Keputusan desain kontrak yang perlu diingat**
- `extend_deadline_at` & `extendable` dikirim **server** → aturan grace DEC-007 tidak diduplikasi di Flutter/Kotlin
- `receipt` membawa `actual_duration_minutes` **dan** `billable_duration_minutes` → operator bisa menjelaskan rounding DEC-009 ke customer
- **Tidak ada** `remaining_seconds` dan **tidak ada event warning** — keduanya dihitung client dari `end_at` (PRD §16). Kalau warning dikirim lewat WebSocket, warning justru hilang saat koneksi putus
- `session.expired` dari scheduler bisa terlambat beberapa detik → tampilan "habis" ditentukan timer lokal
- Kotlin kirim heartbeat lewat **HTTP**, Laravel yang broadcast → client tidak boleh jadi producer event

**Known issues**
- Belum di-push ke GitHub (menunggu konfirmasi user)
- Tiga event diusulkan tapi **belum disetujui**: `fnb.order.updated`, `device.offline`, `shift.closed` (`REALTIME.md` §9). Sampai disetujui → polling HTTP
- Klarifikasi transport `device.heartbeat` (`REALTIME.md` §6) perlu konfirmasi tim apakah dianggap perubahan kontrak

**Next step** *(digantikan entry di bawah)*

---

### 2026-10-02 — Urutan kerja disesuaikan: Flutter dulu, Sabtu jadi recon

**Temuan**
- **Hari ini Jumat; Sabtu = besok 3 Okt**, dan belum ada kode. Testing APK besok tidak mungkin.
- Tapi bagian paling berharga dari sesi lokasi **tidak butuh kode**: fakta TV (V1–V10) dan jalur jaringan. Keduanya justru risiko terbesar proyek (R01, R02, R04).

**Dikerjakan**
- `TEST-PLAN-SABTU.md` dipecah jadi **SESI 1** (Sabtu 3 Okt, 0 kode) dan **SESI 2** (golden path, setelah vertical slice)
  - Sesi 1 pakai `python -m http.server 8000 --bind 0.0.0.0` untuk membuktikan jalur jaringan tanpa Laravel
  - N4 (WebSocket) dipindah ke Sesi 2 karena butuh Reverb
  - Ditambah bagian "jangan dilakukan di Sesi 1" — terutama **jangan factory reset TV** sebelum ada keputusan Device Owner
- **DEC-012**: Flutter dibangun lebih dulu dengan fake repository dari kontrak, dikerjakan sendiri (sekuensial)
- `ROADMAP.md` ditambah tabel urutan kerja nyata
- Tabel hasil testing di file ini dipisah per sesi

**Alasan Flutter dulu (dicatat supaya tidak dibahas ulang)**
Yang paling mungkin salah di PRD bukan backend-nya, tapi **alur kasir**. Dashboard yang bisa diklik dan ditunjukkan ke operator asli mengungkap itu dalam satu jam; kalau backend dibangun dulu, alur yang salah sudah terkunci di schema. Risiko model drift — satu-satunya alasan menolak Flutter-first — sudah hilang sejak kontrak ditulis (DEC-011).

**Known issues**
- Belum di-push ke GitHub (user minta tunda; perlu konfirmasi repo private atau public dulu)
- Fake repository punya batas: **tidak bisa** membuktikan kontraknya lengkap. Wajib diganti di vertical slice pertama (DEC-012 syarat 2)

**Next step** *(selesai — lihat entry di bawah)*

---

### 2026-10-02 — Flutter operator: fondasi + Dashboard + Session Detail

**Files changed** (semua baru, 23 file di `operator-app/`)
- `lib/main.dart`, `lib/app.dart`
- `lib/core/`: `config/api_config.dart`, `theme/{tokens,app_theme,status_style}.dart`, `time/{server_time,ticker}.dart`, `util/format.dart`
- `lib/domain/`: `models/{enums,models}.dart`, `errors/api_error.dart`, `repositories/billing_repository.dart`
- `lib/data/fake/fake_billing_repository.dart`
- `lib/ui/widgets/`: `station_card`, `countdown_text`, `money_text`, `status_chip`, `connection_banner`, `confirm_dialog`
- `lib/ui/dashboard/`: `dashboard_controller`, `dashboard_screen`, `start_session_sheet`
- `lib/ui/session/`: `session_detail_controller`, `session_detail_screen`, `session_actions`
- `lib/ui/settings/settings_screen.dart`
- `android/app/src/debug/AndroidManifest.xml` + `res/xml/network_security_config.xml`
- `test/billing_rules_test.dart`

**DB changes** — tidak ada (client)

**API/Events** — tidak ada perubahan kontrak. Semua model ditranskrip dari `contracts/API.md` §6–§8, error code dari §11.

**Tests** — `flutter test`: **32 lulus**. `flutter analyze`: **bersih**.
Memetakan ke acceptance test: T05, T08, T13, T14, T15, T16, T17.

**Manual test**
```
flutter run --dart-define=API_BASE_URL=http://192.168.0.50:8000
```
Seed fake: ST01 ACTIVE prepaid + F&B · ST03 postpaid hampir habis · ST05 PENDING_PAYMENT · ST04 TV offline · ST06 maintenance · ST02 kosong.
Alur yang bisa dicoba: ketuk ST02 → Prepaid 1 jam → konfirmasi tunai → timer jalan → Tambah F&B → Tambah Durasi → Pindah Station → Checkout → struk.

**Keputusan teknis yang diambil (dicatat agar tidak dibahas ulang)**
1. **Tanpa flavor gradle.** Perbedaan dev/prod ditegakkan Android source set: `src/debug/AndroidManifest.xml` berisi `usesCleartextTraffic="true"` dan hanya digabung pada build debug; `src/main` tidak punya atribut itu. Sudah diverifikasi pada manifest hasil build debug. Lebih sederhana daripada flavor, dan tidak mungkin bocor ke release.
2. **Paket `google_fonts` tidak dipakai** walau UI-UX-SPEC menyebut Fira Sans/Code. Paket itu mengunduh font saat runtime, sementara app ini dirancang untuk jaringan lokal tanpa internet. Yang wajib — tabular figures untuk timer dan uang — tetap benar karena Roboto mendukung `tnum`. Hook untuk membundel Fira ada di `app_theme.dart`.
3. **Paket `intl` tidak dipakai.** Kebutuhannya hanya rupiah dan durasi; ditulis sendiri di `core/util/format.dart`, menghindari friksi versi.
4. **Status station diturunkan dari `end_at` di client**, bukan dari snapshot server (`deriveStationViewStatus`). Server menandai WARNING/EXPIRED lewat scheduler sehingga bisa terlambat beberapa detik — tanpa ini kartu bisa menunjukkan timer `-00:00:14` sementara label masih "Bermain". Pola yang sama dipakai Kotlin TV nanti.
5. **`Idempotency-Key` dibuat saat dialog dibuka**, bukan saat tombol ditekan. Jadi double-tap dan retry setelah timeout memakai key yang sama. Diuji di test idempotency.
6. **Fake repository memajukan jamnya sendiri** (`advanceClock`) alih-alih memakai jam device. Tanpa itu, aturan yang bergantung waktu (grace extend, rounding) tidak bisa diuji sama sekali.

**Known issues**
- `ConnectionStatus` masih statis `connected` — belum ada WebSocket. Indikator di header sudah terpasang, tinggal disambungkan.
- Belum ada login; app langsung masuk Dashboard.
- Build **release** belum pernah dibuat (butuh signing config). Mekanisme HTTPS-only sudah benar secara struktur tapi belum diverifikasi lewat build nyata — dicek di Tahap 3A.
- Fake repository tidak bisa membuktikan kontraknya lengkap (DEC-012 syarat 2).

**Next step**
1. **Besok (Sabtu 3 Okt):** SESI 1 di lokasi — checklist cetak `TEST-PLAN-SABTU.md` §1.6. Bawa `app-debug.apk` untuk melihat UI di tablet, walau datanya masih palsu.
2. Tunjukkan Dashboard + alur checkout ke operator asli — ini tujuan utama Flutter-first (DEC-012). Catat koreksi alur kasir yang muncul.
3. F&B Queue + Shift + Device status (masih di fake)
4. Lalu Laravel thin slice, baru ganti fake → API

---

### Template entry berikutnya

```
### YYYY-MM-DD — <judul singkat>

**Dikerjakan**
-

**Files changed**
-

**DB changes**
-

**API / Events**
-

**Tests**
-

**Manual test**
-

**Known issues**
-

**Next step**
-
```

---

## SESI 1 — Sabtu 3 Okt, recon lokasi (diisi di lokasi)

### Jaringan
| ID | Hasil | Catatan |
|---|---|---|
| N1 IP laptop | ⬚ | IP: |
| N2 Laptop dari tablet | ⬚ | |
| N3 **Laptop dari browser TV** | ⬚ | |
| N5 Latency 60 detik | ⬚ | |
| N6 **TV idle 15 menit** | ⬚ | |

### Router
| Item | Hasil | Catatan |
|---|---|---|
| Ada akses admin C64? | ⬚ | |
| DHCP reservation laptop | ⬚ | |
| AP/client isolation mati | ⬚ | |

### Fakta TV
| ID | Yang dicatat | Hasil |
|---|---|---|
| V1 | Merek + model | |
| V2 | Versi Android/Google TV | |
| V3 | Android TV / Google TV | |
| V4 | **Bisa sideload APK?** | |
| V5 | **ADB over network?** | |
| V6 | Input USB / tombol remote | |
| V7 | Punya browser? | |
| V8 | Perilaku saat PS5 mati | |
| V9 | Auto-sleep berapa lama, bisa dimatikan? | |
| V10 | **Sudah ada akun Google?** | |

### Kesimpulan Sesi 1 — isi setelah selesai
- OD-005 terjawab? → 
- Tahap 2 layak dengan TV ini? → 
- Yang perlu dibeli/diurus sebelum Sesi 2? → 

---

## SESI 2 — golden path (setelah vertical slice jalan)

### Jaringan
| ID | Hasil | Catatan |
|---|---|---|
| N4 WebSocket connected | ⬚ | |

### Golden path
| ID | Hasil | Catatan |
|---|---|---|
| T01 Login | ⬚ | |
| T02 Dashboard | ⬚ | |
| T03 Start Prepaid | ⬚ | |
| T04 Konfirmasi cash | ⬚ | |
| T05 Akurasi timer | ⬚ | |
| T06 F&B Open Tab | ⬚ | |
| T07 Extend | ⬚ | |
| T08 Station Swap | ⬚ | |
| T09 Warning | ⬚ | |
| T10 WiFi putus & reconnect | ⬚ | |
| T11 Force-close & buka | ⬚ | |
| T12 Checkout | ⬚ | |
| T13 COMPLETED | ⬚ | |
| T14 Double-tap bayar | ⬚ | |
| T15 Extend dalam grace 10 menit | ⬚ | |
| T16 Extend lewat 10 menit → ditolak | ⬚ | |
| T17 Postpaid 63 menit → ditagih 60 | ⬚ | |

---

### 2026-10-02 — Design pass 1 + white-label dicatat

**Files changed**
- Baru: `lib/core/brand.dart`, `lib/ui/widgets/brand_mark.dart`, `test/station_card_layout_test.dart`
- Diubah: `lib/core/theme/{tokens,app_theme}.dart`, `lib/ui/widgets/{station_card,status_chip}.dart`, `lib/ui/dashboard/dashboard_screen.dart`, `lib/ui/session/session_detail_screen.dart`, `lib/domain/models/models.dart`, `lib/data/fake/fake_billing_repository.dart`, `lib/ui/dashboard/dashboard_controller.dart`
- Dokumen: `contracts/API.md`, `contracts/CHANGELOG.md` (DRAFT 2), `DECISION-LOG.md` (OD-012)

**API/Events** — satu penambahan kontrak, non-breaking:
`GET /stations` → `station.session.started_at`. Dibutuhkan untuk menggambar bar proporsi waktu di kartu tanpa memuat detail enam sesi. Tercatat di `contracts/CHANGELOG.md` DRAFT 2 berikut aksi untuk backend.

**Tests** — `flutter test`: **49 lulus** (naik dari 32). `flutter analyze`: bersih.
Test baru `station_card_layout_test.dart`: anti-overflow pada beberapa ukuran kartu, penurunan status dari `end_at`, dan perhitungan proporsi waktu.

**Arah visual**
Dark Mode (OLED) sebagai dasar, dilapisi **Soft UI Evolution** dari skill ui-ux-pro-max: kedalaman dari nada permukaan dan shadow berlapis halus, bukan garis tegas di mana-mana. Radius 16, animasi 220 ms, kontras AA+.

**Yang berubah di kartu station**
- **Rail status** 4 px di tepi kiri — isyarat paling cepat terbaca dari jarak beberapa meter
- **Bar proporsi waktu** di bawah timer; warnanya ikut status, jadi saat mendekati habis rail, chip, timer, dan bar berubah serentak
- **Empat tingkat permukaan** (`bg` / `surfaceSunken` / `surface` / `surfaceRaised`) menggantikan garis sebagai pembentuk kedalaman
- Chip status tanpa garis tebal supaya tidak bersaing dengan timer
- Station kosong **tidak lagi** menampilkan `--:--:--` — deretan tanda hubung terlihat seperti data gagal dimuat; diganti ikon + "Mulai sesi"
- `BrandMark` di header, semua nama dari `Brand` (persiapan OD-012)

**Dua bug layout yang ditemukan test, bukan saat dilihat**
1. Dua `Spacer()` di dalam `Column` membuat kartu overflow 28–48 px pada jendela pendek. Diganti `mainAxisAlignment: spaceBetween`.
2. Nominal panjang (mis. Rp 9.850.000) menabrak nama customer sebesar 1 px pada kartu sempit. Dibungkus `Flexible` + `FittedBox`.

Keduanya hanya muncul di ukuran kecil — kalau hanya dilihat di jendela besar, tidak akan ketahuan sampai dibuka di tablet.

**Keputusan baru**
- `minStationCardHeight = 200`. Grid menjamin tinggi kartu tidak di bawah itu; kalau jendela terlalu pendek untuk enam kartu, **grid-nya di-scroll** — bukan kartunya dipaksa mengecil sampai rusak. Target spec (enam kartu tanpa scroll) tetap tercapai di tablet.
- **OD-012** dicatat: aplikasi akan dijual ke beberapa pengguna. User minta fiturnya nanti, tapi satu bagiannya **tidak bisa ditunda** — keputusan multi-tenant atau tidak harus diambil sebelum migration pertama, karena menambahkan `tenant_id` setelah ada data produksi berarti membongkar setiap tabel, query, dan laporan. Rekomendasi: per-instance.

**Known issues**
- **Belum diverifikasi secara visual.** Analyze dan test bersih, dan test anti-overflow menutup risiko layout, tapi rasa visualnya tetap perlu dilihat user.
- Masih belum ada: login, WebSocket, F&B Queue, Shift, Device screen, pilih member.

**Next step**
1. User melihat hasilnya di Chrome dan memberi koreksi
2. Lanjut fitur: F&B Queue → Shift → Device → pilih member
3. Login (butuh backend) lalu Laravel thin slice

---

### 2026-10-02 — F&B Queue

**Files changed**
- Baru: `lib/ui/fnb/fnb_queue_controller.dart`, `lib/ui/fnb/fnb_queue_screen.dart`, `test/fnb_queue_test.dart`
- Diubah: `lib/ui/dashboard/{dashboard_controller,dashboard_screen}.dart`, `lib/domain/models/enums.dart`, `lib/data/fake/fake_billing_repository.dart`

**API/Events** — tidak ada perubahan kontrak. Memakai `GET /fnb/orders` dan `POST /fnb/orders/{id}/status` yang sudah ada di `API.md` §8.

**Tests** — `flutter test`: **62 lulus** (naik dari 49). `flutter analyze`: bersih.
13 test baru: pengelompokan antrian, transisi status, aturan pembatalan, order baru masuk antrian.

**Yang dibangun**
- Layar antrian terpisah, dikelompokkan per status: **Belum diproses → Sedang diproses → Siap diantar**. Bukan satu daftar panjang, karena "belum disentuh" dan "siap diantar" adalah dua pertanyaan berbeda untuk operator dapur.
- **Satu tombol per kartu** yang memajukan order satu langkah: Proses → Tandai Siap → Antar. Label pakai kata kerja, bukan nama status.
- Tab **Aktif / Riwayat**. Riwayat urut dari yang terbaru.
- **Umur order** dengan peringatan bertingkat: setelah 10 menit oranye, setelah 20 menit merah. Ini sinyal operasional, bukan hiasan — order yang menganggur perlu terlihat tanpa operator menghitung sendiri.
- Ketuk kartu → buka sesi terkait, karena operator sering perlu melihat Open Tab-nya.
- Badge di bar aksi dashboard. Hanya menghitung `PENDING` + `PROCESSING`; yang sudah `READY` tidak dihitung karena tinggal diantar, bukan dikerjakan.
- Bar aksi baru di bawah grid dashboard — akan menampung Shift dan Device.
- Seed fake menambah order di empat status supaya layarnya bisa langsung dinilai.

**Dua pelanggaran kontrak yang ditemukan dan diperbaiki**
1. `updateFnbOrderStatus` di fake mengizinkan cancel dari **status apa pun**, padahal kontrak §8 membatasi cancel hanya dari `PENDING` atau `PROCESSING`. Order yang sudah siap atau diantar tidak boleh dibatalkan begitu saja — barangnya sudah dibuat. Diperbaiki, dan pesan errornya dibuat spesifik.
2. Tombol batal muncul di semua order termasuk yang tidak boleh dibatalkan. Ditambah `FnbOrderStatus.canCancel`; tombolnya kini disembunyikan, supaya operator tidak pernah menemui error yang bisa dicegah.

Keduanya ditemukan saat menulis test, bukan saat memakai layarnya.

**Known issues**
- Belum ada `fnb.order.updated` di realtime (usulan `REALTIME.md` §9, belum disetujui). Sampai itu ada, antrian disegarkan manual atau saat layar dibuka — belum otomatis kalau ada tablet kedua.
- Belum diverifikasi secara visual.

**Next step**
1. Shift start/close/handover
2. Device status
3. Pilih member di Start Session

---

### 2026-10-02 — Satu kasir dikonfirmasi + layar Shift

**Keputusan baru: DEC-013 — satu tablet operator per lokasi**
User mengkonfirmasi hanya ada satu kasir. Yang jadi tidak perlu: event `fnb.order.updated`, event `shift.closed`, penanganan konflik antar tablet, polling agresif.
Yang **tetap** perlu: WebSocket Reverb (Kotlin TV Agent tetap mengkonsumsi `session.*` — ini soal tablet, bukan TV) dan fitur Shift itu sendiri.

**Catatan penting:** `fnb.order.updated` hanya **digeser**, bukan dihapus. PRD §13 mewajibkan customer melihat status order, dan Customer Portal (Tahap 3C) adalah layar kedua — jadi event itu akan kembali dibutuhkan. `REALTIME.md` §9 sudah diperbarui.

**Files changed**
- Baru: `lib/ui/shift/shift_controller.dart`, `lib/ui/shift/shift_screen.dart`, `test/shift_test.dart`
- Diubah: `lib/domain/models/models.dart` (model `Shift`, `ShiftSummary`), `lib/domain/repositories/billing_repository.dart` (4 method shift), `lib/data/fake/fake_billing_repository.dart` (log pembayaran + state shift), `lib/ui/dashboard/dashboard_screen.dart`
- Dokumen: `DECISION-LOG.md` (DEC-013, OD-013), `contracts/REALTIME.md` §9

**API/Events** — tidak ada perubahan kontrak. Memakai `/shifts/*` yang sudah ada di `API.md` §10. Empat method ditambahkan ke `BillingRepository`: `fetchCurrentShift`, `openShift`, `closeShift`, `fetchShiftHistory`.

**Tests** — `flutter test`: **78 lulus** (naik dari 62). `flutter analyze`: bersih.
16 test baru: kas seharusnya, selisih kurang/lebih/pas, penolakan tutup shift saat ada sesi berjalan, serah terima, idempotency, dan pemisahan tunai vs QRIS.

**Yang dibangun**
Layar shift berpusat pada satu pertanyaan: **berapa kas yang seharusnya ada di kotak?**

- `Kas seharusnya = kas awal + penerimaan tunai`. **QRIS sengaja tidak dihitung** — uangnya tidak masuk kotak kas. Ini kesalahan hitung yang paling mudah terjadi kalau tidak dipisahkan eksplisit.
- Selisih dihitung **saat operator mengetik** hasil hitungannya, bukan setelah shift ditutup. Operator melihat "Kurang Rp 50.000" sebelum menekan tombol, bukan sesudahnya.
- Selisih bukan nol → **konfirmasi kedua** dengan peringatan bahwa angkanya dicatat permanen di audit log (PRD §24).
- **Shift tidak bisa ditutup kalau masih ada sesi berjalan.** Tagihannya belum selesai, jadi pertanggungjawaban kas belum bisa ditutup. Pesan errornya menyebut jumlah sesinya.
- **Serah terima**: kas akhir shift ini diusulkan sebagai kas awal shift berikutnya, ditampilkan di laporan penutupan.
- Riwayat shift dengan penanda Pas / Lebih / Kurang.

**Keputusan teknis**
Fake repo kini punya **log pembayaran**, bukan hanya `paid` per sesi. Tanpa itu ringkasan shift harus diisi angka karangan, dan test tidak akan membuktikan apa pun.

**OD-013 baru**
Ringkasan shift memisahkan **uang masuk** (`cash`/`qris`/`total`, dari log pembayaran) dan **nilai transaksi** (`rental`/`fnb`, dari waktu item dibuat). Keduanya memang bisa berbeda — Open Tab yang dibuka pagi tapi dibayar malam. Mana yang jadi dasar laporan harian belum diputuskan; mempengaruhi formula profit (OD-009). Sudah dijelaskan di layar supaya tidak menyesatkan.

**Known issues**
- Shift masih memakai operator dummy (`Operator`) karena belum ada login. Nama sebenarnya ikut setelah auth.
- Belum diverifikasi secara visual.

**Next step**
1. Device status
2. Pilih member di Start Session
3. Login (butuh backend) lalu Laravel thin slice

---

### 2026-10-02 — Status TV (Device)

**Files changed**
- Baru: `lib/ui/device/device_screen.dart`, `test/device_test.dart`
- Diubah: `lib/domain/models/models.dart` (model `Device`, `DeviceList`), `lib/domain/repositories/billing_repository.dart` (`fetchDevices`), `lib/data/fake/fake_billing_repository.dart`, `lib/ui/dashboard/dashboard_screen.dart`
- Dokumen: `contracts/API.md` §9, `contracts/CHANGELOG.md` (DRAFT 3)

**API/Events** — satu penambahan kontrak:
Bentuk response `GET /devices` didefinisikan. Sebelumnya hanya disebut "daftar device + status, last_seen_at, app_version" tanpa skema. Ditambah `meta.offline_threshold_seconds` dan `station` yang nullable. Tercatat di `contracts/CHANGELOG.md` DRAFT 3 berikut aksi untuk backend.

**Tests** — `flutter test`: **89 lulus** (naik dari 78). `flutter analyze`: bersih.
11 test baru: urutan offline di atas, penentuan status dari `last_seen`, perubahan status saat waktu berjalan, dan rekonsiliasi angka badge vs layar.

**Yang dibangun**
Layar **read-only**. Operator di sini hanya menjawab satu pertanyaan: TV mana yang tidak mengirim kabar, dan sejak kapan. Pendaftaran, pemetaan ulang, dan pencabutan token device adalah wewenang Admin (PRD §19) → Tahap 3B.

- Ringkasan Online / Offline / Tanpa station, berikut keterangan ambang offline-nya
- Kartu per device: station, status, terakhir terlihat, model + versi OS, versi aplikasi
- Device offline **diurutkan di atas** — itu yang menuntut perhatian
- Badge jumlah offline di bar aksi dashboard
- Peringatan jujur di layar bahwa **belum ada TV yang benar-benar mengirim kabar** karena aplikasi TV belum dibuat. Tanpa catatan ini operator bisa menyimpulkan TV benar-benar mati

**Keputusan desain**
- `status` diambil **apa adanya dari server**, tidak dihitung client dari `last_seen_at`. Ini berbeda dari status sesi — yang memang diturunkan client dari `end_at` — karena ambang offline adalah **kebijakan operasional**, bukan hitungan waktu yang pasti. Ambangnya tetap dikirim (`meta.offline_threshold_seconds`) supaya client bisa *menjelaskan* alasannya tanpa menduplikasi aturannya.
- `station` dibuat **nullable**: PRD §10 memperbolehkan perubahan pemetaan, dan device yang pemetaannya dicabut harus tetap terlihat — bukan hilang dari daftar.

**Satu ketidakkonsistenan yang ditemukan dan diperbaiki**
Awalnya `offlineCount` menghitung **semua** device offline termasuk cadangan yang tidak dipetakan ke station. Hasilnya badge di dashboard menunjukkan "1" sementara layar Device menunjukkan "Offline 2". Operator yang melihat dua angka berbeda untuk hal yang sama akan berhenti mempercayai keduanya.

Diperbaiki: `onlineCount`/`offlineCount` hanya menghitung device yang dipetakan ke station, dan cadangan dihitung terpisah sebagai "Tanpa station". Ada test yang mengunci kesamaan angka badge dan layar.

**Known issues**
- Semua data device masih dari seed. Angka nyata baru ada setelah Tahap 2.
- Belum diverifikasi secara visual.

**Next step**
1. Pilih member di Start Session (satu-satunya item PRD §18 yang masih kurang)
2. Login (butuh backend)
3. Laravel thin slice, lalu ganti fake → API

---

### 2026-10-02 — Pilih member di Start Session

**Files changed**
- Baru: `lib/ui/customer/customer_picker.dart`, `test/customer_selection_test.dart`
- Diubah: `lib/ui/dashboard/{start_session_sheet,dashboard_screen}.dart`, `lib/data/fake/fake_billing_repository.dart`
- Dokumen: `DECISION-LOG.md` (OD-014)

**API/Events** — tidak ada perubahan kontrak. Memakai `GET /customers?q=` yang sudah ada di `API.md` §6.

**Tests** — `flutter test`: **103 lulus** (naik dari 89). `flutter analyze`: bersih.
14 test baru: pencarian nama & telepon, pemetaan `CustomerChoice` ke field kontrak, dan pembuatan sesi dengan member / walk-in.

**Yang dibangun**
- Baris "Customer" yang bisa diketuk di sheet Mulai Sesi, menggantikan kolom nama bebas. Satu baris dan satu cara mengubahnya — sesuai DEC-008 yang hanya mengizinkan satu customer per sesi.
- Pemilih berupa bottom sheet: pencarian nama/telepon, opsi **Walk-in selalu di atas** (pilihan paling sering dipakai), lalu daftar member.
- Pencarian **di-debounce 300 ms** dan hasil yang datang terlambat diabaikan kalau kolom sudah berubah. Tanpa itu, mengetik "budi" mengirim empat permintaan dan daftar bisa berkedip ke hasil yang salah.
- Badge membership. **Membership kedaluwarsa sengaja ditampilkan berbeda, bukan disembunyikan** — operator perlu tahu orangnya pernah member tapi tidak berhak harga member sekarang.
- Seed customer ditambah jadi tujuh, termasuk satu dengan membership kedaluwarsa.

**Batas wewenang yang ditegakkan — OD-014 baru**
Operator **tidak bisa mendaftarkan member baru** dari layar ini. PRD §6 memberi akses `customer` hanya kepada Admin/Owner; operator tidak termasuk. Customer yang belum terdaftar dilayani sebagai Walk-in.

Catatan penjelasan dipasang di dalam pemilih, supaya operator tidak mencari tombol "Tambah member" dan menyimpulkan aplikasinya belum jadi.

Tapi ini perlu keputusan: customer yang ingin jadi member di meja kasir adalah kejadian harian. Dicatat sebagai **OD-014**, bukan diputuskan di kode.

**Satu bug ditemukan dan diperbaiki**
`createSession` di fake memakai `firstWhere` tanpa `orElse` untuk mencari customer. `customer_id` yang tidak ada melempar `StateError` mentah, bukan `ApiError` — sehingga UI menampilkan "kesalahan tidak terduga" alih-alih pesan yang berguna. Sekarang melempar `ApiError` dengan kode `NOT_FOUND`, dan ada test yang menguncinya.

**Status PRD §18 — operator app**

| Screen | Status |
|---|---|
| Login | ❌ butuh backend |
| Dashboard | ✅ |
| Start Session | ✅ |
| Session Detail | ✅ |
| F&B Queue | ✅ |
| Payment | ✅ |
| Shift | ✅ |
| Device | ✅ |
| Booking | ⏸ ditunda ke Tahap 3 (ROADMAP) |

**Known issues**
- Belum diverifikasi secara visual — seluruh Tahap 1 sejauh ini belum pernah dilihat user.
- Belum ada klien WebSocket; `ConnectionStatus` masih statis.
- Harga member belum berpengaruh apa pun. Paket masih satu harga untuk semua. Belum ada requirement PRD soal diskon member — kalau memang ada, perlu Change Request.

**Next step**
1. **User melihat hasilnya dan memberi koreksi** — ini yang paling berharga sekarang
2. Laravel thin slice (auth, stations, packages, sessions, payments)
3. Ganti fake repository → `ApiBillingRepository` (DEC-012 syarat 2)

---

### 2026-10-02 — Redesign UI mengikuti `contoh.html` (DEC-014)

User menilai desain pertama belum pas dan memberi `operator-app/contoh.html`
sebagai acuan. Arah visual baru: **Material 3 dark, aksen cyan + mint,
shell bersidebar**.

**Files changed**
- Baru: `lib/ui/shell/app_shell.dart`, `test/dashboard_filter_test.dart`,
  `assets/fonts/` (11 TTF + 3 lisensi OFL + README)
- Ditulis ulang: `lib/core/theme/{tokens,app_theme}.dart`,
  `lib/ui/widgets/station_card.dart`, `lib/ui/dashboard/dashboard_screen.dart`,
  `docs/UI-UX-SPEC.md`
- Diubah: `pubspec.yaml` (font), `lib/app.dart`, `lib/core/brand.dart`,
  `lib/ui/dashboard/dashboard_controller.dart` (filter, pencarian, shift),
  `lib/domain/models/models.dart`, `lib/data/fake/fake_billing_repository.dart`,
  + 14 file UI lain (migrasi nama token)
- Dokumen: `contracts/API.md`, `contracts/CHANGELOG.md` (DRAFT 4),
  `DECISION-LOG.md` (DEC-014, OD-015, OD-016)

**API/Events** — satu penambahan, non-breaking:
`GET /stations` → `station.console_type` (string, nullable). Desain menampilkan
label konsol per station; operator memakainya untuk memenuhi permintaan
"yang PS5". Teks bebas, **bukan enum** — tiap rental punya penamaan sendiri.
Tercatat di `contracts/CHANGELOG.md` DRAFT 4.

**Tests** — `flutter test`: **128 lulus** (naik dari 103). `flutter analyze`: bersih.
`flutter build bundle` lolos, font terverifikasi ikut ke bundle.
25 test baru: filter & pencarian station, tipe konsol, dan layout kartu pada
ukuran yang benar-benar bisa dihasilkan grid.

**Yang berubah**

| | Sebelum | Sekarang |
|---|---|---|
| Navigasi | bar aksi di bawah grid | sidebar 5 tujuan + badge, menyusut jadi rail di bawah 1040 px |
| Palet | biru-slate, aksen amber | cyan + mint, Material 3 roles, 6 tingkat permukaan |
| Font | font sistem | Space Grotesk / Plus Jakarta Sans / JetBrains Mono, **dibundel** |
| Kartu | rail status + chip | header (kode + tipe konsol \| status) → timer → bar → blok customer → **aksi cepat** |
| Filter | tidak ada | chip 5 status + pencarian (debounce 250 ms) |

**Font: dibundel, dan static — dua keputusan terpisah**
1. **Dibundel, bukan `google_fonts`.** Paket itu mengunduh saat runtime; app
   ini dipakai di jaringan lokal tanpa internet (DEC-002), jadi build pertama
   di lokasi akan memakai font sistem dan tampilannya beda dari rancangan.
2. **Static, bukan variable.** Google Fonts hanya menyediakan variable font
   untuk ketiganya. Variable font di Flutter butuh `fontVariations` di setiap
   `TextStyle`; `fontWeight` saja tidak mengubah ketebalan — mudah terlewat di
   satu widget. Static instance di-generate dengan `fontTools`, jadi
   `fontWeight` bekerja normal. Lisensi OFL disertakan.

**Tiga penyimpangan dari contoh — disengaja, karena contoh itu mockup web**
1. **Tombol aksi 44 px, bukan 34 px.** Contoh mengasumsikan presisi mouse.
   Aksi di kartu ini mengubah uang; mis-tap mahal. 48 px (Material) membuat
   enam kartu tidak muat tanpa scroll di tablet 1280×800, jadi 44 px (minimum
   iOS) dipilih sebagai kompromi — dicatat, bukan kelalaian.
2. **Sidebar menyusut jadi rail di bawah 1040 px.** 288 px dari 800 px layar
   portrait adalah 36% untuk navigasi saja.
3. **`+30m` / `+1j` tetap pakai konfirmasi** berisi perkiraan harga. Contoh
   tidak punya konfirmasi, tapi salah tap `+1j` menagih customer satu jam yang
   tidak diminta dan kontrak tidak punya jalur pembatalan.

**Empat elemen contoh yang TIDAK diambil**
Bel notifikasi (tidak ada sistem notifikasi — slotnya diisi indikator koneksi
yang nyata), badge terminal `POS-01` (DEC-013 satu kasir), "Auto Refresh
Aktif" (tidak ada auto-refresh; menampilkannya jadi klaim palsu), teknisi &
nomor tiket pada kartu maintenance (OD-016).

Prinsipnya: **UI yang menjanjikan data yang tidak ada lebih buruk daripada UI
yang kosong.** Operator akan mengandalkannya lalu kehilangan kepercayaan pada
seluruh layar.

**Masalah layout yang ditemukan test, bukan mata**
Kartu versi redesign tingginya **279 px**, sementara tablet 1280×800 landscape
hanya menyisakan ±265 px per baris setelah header, bar filter, strip shift,
dan footer. Kalau tidak ketahuan, enam kartu tidak muat tanpa scroll — target
utama UI-UX-SPEC §4 gagal justru oleh desain barunya.

Diperbaiki dengan merampingkan padding (12 px), padding body (8 px), dan jarak
aksi (8 px). Header dibuat anti-overflow: kode station tetap penuh, badge tipe
konsol di-ellipsis, label status mengecil lewat `FittedBox`. Baris aksi semua
`Expanded` sehingga tidak pernah overflow horizontal berapa pun lebarnya.
Tinggi minimum kartu ditetapkan **300 × 264** dan ada test untuk sembilan
kombinasi ukuran + status, termasuk penskalaan teks 1.3×.

**Known issues**
- **Belum diverifikasi secara visual.** Analyze, 128 test, dan build bundle
  bersih — tapi rasa visualnya hanya user yang bisa menilai.
- OD-015 (tarif per tipe konsol) kini **ikut memblokir migration pertama**,
  bersama OD-012.
- `contoh.html` masih ada di `operator-app/` sebagai acuan. Bisa dihapus
  setelah desain dianggap final.

**Next step**
1. User melihat hasilnya dan memberi koreksi
2. Putuskan OD-012 (multi-tenant) + OD-015 (tarif per konsol)
3. Laravel thin slice

---

### 2026-10-02 — TV Agent: kiosk + kontrol langsung lewat LAN (DEC-015)

User menahan Laravel dan memilih membangun **APK TV lebih dulu** sebagai kiosk
yang dikontrol operator lewat WiFi/jaringan lokal, tanpa login dan tanpa
backend.

**Files changed** — `tv-agent/` sebelumnya hanya README; kini project Android
penuh.
- Build: `settings.gradle.kts`, `build.gradle.kts`, `gradle/libs.versions.toml`,
  `app/build.gradle.kts`, `app/proguard-rules.pro`
- Manifest + resources: `AndroidManifest.xml`, `values/{colors,strings,themes,dimens}.xml`,
  `layout/activity_kiosk.xml`, `drawable/`, `xml/{network_security_config,device_admin}.xml`,
  `font/` (5 TTF)
- Kotlin: `AgentApp`, `AgentState`, `StateStore`, `TimeSync`, `AgentPorts`,
  `CommandSource` (+`CommandApplier`), `LocalHttpCommandSource`, `Pairing`,
  `Json`, `DeviceCapabilities`, `AgentService`, `BootReceiver`, `KioskActivity`,
  `AgentDeviceAdminReceiver`
- Test: `Fakes`, `TimeSyncTest`, `PairingTest`, `CommandApplierTest`
- Dokumen: `tv-agent/README.md`, `DECISION-LOG.md` (DEC-015), `ROADMAP.md`

**Tests** — 31 unit test JVM lulus, tanpa emulator. APK debug ter-build (4,0 MB),
terdeteksi sebagai aplikasi Android TV (leanback launcher), targetSdk 34.

**Konflik dengan PRD — diangkat, bukan disembunyikan**
PRD §8 menyatakan TV Agent bukan source of truth dan client tidak menentukan
state; PRD §16 menyatakan TV menerima `end_at` dari Laravel lewat WebSocket.
Kontrol langsung operator → TV melanggar keduanya.

Tetap dijalankan karena **R01 (HIGH) belum tersentuh**: apakah TV bisa
dijadikan kiosk sama sekali belum terbukti, dan tidak ada gunanya
menyelesaikan Laravel kalau ternyata TV-nya tidak bisa dipasangi APK. Jadi ini
diperlakukan sebagai **spike untuk membuktikan kendali TV**, bukan perubahan
arsitektur. Dicatat utuh di DEC-015.

**Empat hal yang membuat pekerjaan ini tidak terbuang**
1. **`CommandSource` sebagai batas.** `LocalHttpCommandSource` sekarang,
   `ReverbCommandSource` di Tahap 2. Layar kiosk, timer, persistence, dan
   recovery tidak tahu mana yang dipakai — yang diganti hanya satu blok di
   `AgentService.ensureCommandSource()`.
2. **`AgentCommand` dibentuk mengikuti event `REALTIME.md` §5**, bukan mengikuti
   bentuk HTTP lokal.
3. **Timer dihitung dari `end_at`** persis seperti PRD §16, jadi tidak perlu
   diubah nanti. Ini juga yang membuat timer tetap benar saat koneksi terputus.
4. **Bentuk error mengikuti kontrak §2**, sehingga penanganannya di operator app
   sama untuk agen maupun Laravel.

**Autentikasi tidak ditunda — ini yang paling penting**
Kontrol langsung tanpa autentikasi berarti siapa pun di WiFi yang sama bisa
menyetel timer TV; customer di Guest Wi-Fi bisa memperpanjang sesinya sendiri
secara gratis. Kelas risiko yang sama dengan *prank order* (PRD §13) dan R05.

Jadi dipakai **pairing**: TV menampilkan kode 6 digit, operator memasukkannya
sekali, TV memberi device token. Kode sekali pakai; 10 percobaan salah mengunci
pairing. Polanya sama dengan `enrollment_code` di kontrak §9, jadi tidak
terbuang.

Batasnya dicatat jujur: siapa pun yang melihat layar TV bisa membaca kodenya.
Memadai untuk jaringan operasional, **bukan** untuk TV yang terjangkau dari
Guest Wi-Fi — pemisahan guest di PRD §9 tetap wajib.

**Kiosk punya dua tingkat, dan yang kedua belum pasti**

| Tingkat | Cara | Bisa keluar? |
|---|---|---|
| 1 — selalu | fullscreen immersive, layar menyala, back ditahan, foreground service, auto-start saat boot | Ya, lewat HOME |
| 2 — kiosk sebenarnya | Lock Task mode + Device Owner | Tidak |

Aplikasi **melaporkan** tingkat mana yang aktif lewat `GET /health` dan baris
diagnostik di layar — tidak mengklaim. PRD §5 melarang klaim sebelum terbukti.
`AgentDeviceAdminReceiver` ditambahkan supaya perintah
`adb shell dpm set-device-owner` bisa dipakai; itu hanya berhasil pada perangkat
tanpa akun (OD-005 / V10).

**Satu kelemahan desain ditemukan test, bukan mata**
`CommandApplier` awalnya menerima `station_code` apa pun dari perintah.
Akibatnya perintah yang **salah kirim** — operator menekan ST01 padahal TV itu
ST02 — akan mengubah label TV diam-diam, sehingga dua TV mengaku station yang
sama dan tidak ada yang tahu mana yang benar. Sekarang perintah untuk station
lain **ditolak**; pemindahan harus eksplisit lewat unpair/pair agar tercatat
(PRD §10).

**Dua masalah build yang layak dicatat**
1. `local.properties` ditulis dengan backslash. Di file `.properties` Java,
   `\` adalah karakter escape, jadi `C:\Users\...` terbaca `C:Users...` dan
   Gradle gagal dengan pesan menyesatkan. Harus garis miring.
2. `TimeSync` semula memakai `SystemClock.elapsedRealtime()`, yang melempar
   `Stub!` di unit test JVM — 14 test gagal. Diganti `System.nanoTime()`:
   sama-sama monotonik tapi murni Java, sehingga aturan waktu (bagian paling
   mahal kalau salah) bisa diuji tanpa emulator.

**Konsekuensi penahanan Tahap 0 — dicatat supaya tidak dianggap selesai**
- Operator app masih memakai fake repository (DEC-012 syarat 2 belum terpenuhi)
- **Tidak ada audit trail** untuk apa pun yang dilakukan lewat kontrol langsung
- **Tidak ada recovery dari server** kalau TV kehilangan state-nya
- Belum ada login

Ketiganya akan dibutuhkan sebelum produksi.

**Known issues**
- Belum pernah dijalankan di TV atau emulator — baru terbukti **compile dan
  test lulus**, bukan berjalan.
- Integrasi dari aplikasi operator belum ada; sekarang hanya bisa dicoba lewat
  `curl` (lihat `tv-agent/README.md`).
- Peringatan 10/5/1 menit belum ada suara (OD-004); `LOCKED` belum berefek
  (OD-001).

**Next step**
1. Pasang APK ke TV atau emulator dan buktikan layarnya benar
2. Penemuan TV + panel kontrol di aplikasi operator
3. Kalau sudah terbukti: putuskan OD-012 + OD-015, lalu Laravel

---

### 2026-10-02 — Operator ↔ TV: penemuan, pairing, dan kontrol billing

Menyambungkan sisi billing ke TV. Operator sekarang bisa menemukan TV di
jaringan, memasangkannya ke station, dan sejak itu **setiap perubahan sesi
otomatis tampil di layar TV** — mulai sesi, tambah durasi, checkout.

**Files changed**
- Baru: `lib/data/tv/{tv_agent_client,tv_discovery,tv_link_store,tv_sync_service}.dart`,
  `lib/data/tv/{local_ip,local_ip_io,local_ip_stub}.dart`,
  `lib/domain/models/tv_agent.dart`, `lib/ui/device/tv_pair_sheet.dart`,
  `test/tv_sync_test.dart`, `web/` (platform web)
- Ditulis ulang: `lib/ui/device/device_screen.dart`
- Diubah: `pubspec.yaml` (`http`), `lib/app.dart`, `lib/main.dart`,
  `lib/ui/dashboard/dashboard_controller.dart`

**Tests** — `flutter test`: **154 lulus** (naik dari 128). `flutter analyze`: bersih.
`flutter build web`: lolos. 26 test baru untuk kontrol TV.

**Yang dibangun**
- **Penemuan** lewat pemindaian subnet /24, 32 probe paralel, dengan indikator
  kemajuan. Bukan mDNS: multicast sering di-drop access point murah dan butuh
  WiFi multicast lock di Android. Pemindaian hanya memakai HTTP biasa —
  kalau operator bisa membuka alamat TV di browser, pemindaian juga bisa.
- **Entri alamat manual selalu tersedia**, bukan cadangan darurat: di web itu
  satu-satunya cara, dan saat operator dan TV beda subnet pemindaian memang
  tidak akan menemukan apa pun.
- **Pairing** dari sheet: pilih TV → masukkan kode 6 digit dari layar TV →
  terpasang, lalu keadaan sesi langsung dikirim.
- **Layar Status TV** per station: status sambungan, perangkat, tingkat kiosk,
  kontak terakhir, dan **apa yang seharusnya tampil di TV sekarang**.
- Tombol Periksa / Kirim ulang / Ganti TV / Lepas.

**Keputusan desain yang menentukan perilaku**

*Dikirim saat berubah, bukan terus-menerus.* Dashboard menyegarkan data setiap
kali operator kembali ke monitor. Mengirim ulang ke TV setiap kali berarti
belasan permintaan per menit tanpa ada yang berubah. Jadi `TvSyncService`
menyimpan sidik keadaan per station dan hanya mengirim perubahan.

*Sidik berisi `session_id`, mode, dan `end_at` — bukan tagihan.* `end_at` masuk
karena **itu yang membuat extend terkirim**: menambah durasi tidak mengubah
`session_id`. Tagihan sengaja **tidak** masuk: TV tidak menampilkannya, dan
kalau dimasukkan setiap teh manis memicu satu permintaan ke TV tanpa ada yang
berubah di layarnya. Keduanya ada test-nya.

*Sidik tidak disimpan saat gagal.* Kalau disimpan, TV tertinggal sampai ada
perubahan berikutnya — bisa berjam-jam. Ada test yang mengunci ini.

*Token ditolak dibedakan dari tidak terjangkau.* Penanganannya berbeda: yang
pertama perlu pairing ulang, yang kedua perlu menunggu jaringan membaik.

*`unpair` tetap melepas walau TV tidak merespons.* TV yang mati atau sudah
dibawa pergi tidak boleh membuat station terjebak dengan perangkat yang tidak
ada.

*Satu TV tidak boleh terpasang di dua station* (PRD §10). Memasangkan perangkat
yang sama ke station lain melepas pasangan lamanya — kalau tidak, dua station
mengirim perintah ke TV yang sama dan timer-nya saling menimpa tanpa ada yang
tahu kenapa. Ada test-nya.

*`device_uid`, bukan IP, yang menentukan identitas TV.* DHCP bisa memberi
alamat berbeda setelah TV restart.

**Satu kesalahan saya yang perlu dikoreksi**
Saya beberapa kali memberi perintah `flutter run -d chrome` untuk melihat UI.
Project ini dibuat dengan `--platforms android`, jadi **perintah itu tidak akan
pernah jalan** — Flutter akan menolak dengan "not configured for the web".
Platform web sekarang ditambahkan dan `flutter build web` lolos, judul serta
warna tema web disesuaikan dengan merek.

**Satu hal yang test harness-nya sendiri menyesatkan**
Fake agen semula membalas `DEVICE_TOKEN_INVALID` untuk semua error. Akibatnya
test "TV tidak merespons" lulus/gagal karena alasan yang salah. Diganti error
generik `INTERNAL`, sesuai yang dibalas agen sebenarnya.

**Known issues**
- Belum pernah diuji terhadap TV atau emulator sungguhan — baru terbukti lewat
  fake HTTP. Yang membuktikan jalur nyata hanya pengujian di perangkat.
- Pemindaian tidak tersedia di web (browser tidak mengizinkan aplikasi membaca
  IP lokalnya). Entri manual dipakai di sana.
- Kartu station di dashboard masih menampilkan status device dari fake
  repository, belum dari `TvSyncService`. Berikutnya.
- Belum ada retry otomatis; operator menekan "Kirim ulang".

**Next step**
1. Uji operator ↔ TV di emulator atau TV sungguhan — ini yang membuktikan
   jalurnya
2. Satukan status TV di kartu dashboard dengan `TvSyncService`
3. Kalau sudah terbukti: putuskan OD-012 + OD-015, lalu Laravel

---

### 2026-10-02 — Status TV disatukan + data contoh ditandai jelas

Dua hal menjelang testing operator ↔ TV: **satu sumber kebenaran** untuk status
TV, dan **penanda jelas** mana yang nyata dan mana yang contoh.

**Files changed**
- Diubah: `lib/domain/repositories/billing_repository.dart` (`isSample`),
  `lib/data/fake/fake_billing_repository.dart`,
  `lib/ui/dashboard/{dashboard_controller,dashboard_screen}.dart`,
  `lib/ui/widgets/station_card.dart`, `lib/ui/shell/app_shell.dart`,
  `lib/ui/device/device_screen.dart`, `lib/ui/settings/settings_screen.dart`
- Ditulis ulang: `test/device_test.dart`
- Dokumen: `TEST-PLAN-SABTU.md` (SESI TV baru)

**Tests** — **155 lulus**, analyze bersih. APK debug per-ABI ter-build
(arm64 73 MB).

**Satu sumber kebenaran untuk status TV**

Sebelumnya ada **dua**: kartu station memakai `station.device` dari data
contoh (status online/offline karangan), sementara layar Status TV memakai
sambungan nyata dari `TvSyncService`. Keduanya bisa menampilkan angka berbeda
untuk hal yang sama, dan operator tidak punya cara tahu mana yang benar.

Diperbaiki dengan menghapus sumber palsunya:
- Fake repository mengembalikan `device: null` untuk semua station
- `fetchDevices()` mengembalikan daftar **kosong** — dan itu jawaban yang
  benar: tidak ada server yang menerima heartbeat sampai Laravel ada
- `DashboardController.offlineDeviceCount` dihapus
- Kartu station, badge sidebar, dan layar Status TV semuanya membaca
  `TvSyncService`

Kartu station kini punya **lencana TV** di header: tersambung (mint),
tidak merespons (merah), pairing ditolak (oranye), belum dipasang (abu).
Menempati slot yang sudah ada — tidak menambah keramaian, hanya menjadi benar.

**Data contoh ditandai, bukan disembunyikan**

Selama kontrol TV diuji, **sambungan ke TV nyata** sementara **sesi, customer,
dan nominal masih contoh**. Campuran itu paling berbahaya justru saat sedang
menguji, ketika perhatian ada di TV dan angka contoh mudah terbaca sebagai
angka asli.

Jadi:
- Chip **DATA CONTOH** di header shell, dengan tooltip penjelasan
- Layar Status TV memuat penjelasan eksplisit: status/alamat/perangkat dibaca
  langsung dari TV, tapi isi sesinya contoh
- Pengaturan memisahkan "Data billing: data contoh" dari "Sambungan TV: nyata"
- `BillingRepository.isSample` menjadi sumber penanda itu — hilang sendiri
  begitu `ApiBillingRepository` masuk

**`test/device_test.dart` ditulis ulang**

Versi lama menguji **daftar device karangan**: enam perangkat dengan status
online/offline buatan. Test itu menguji fiksi, dan menjadikannya acuan berarti
mempertahankan sumber kebingungan yang baru saja dihapus.

Sekarang menguji hal yang benar: fake repository memang **tidak lagi
mengarang**, penanda data contoh diteruskan ke UI, dan logika murni pada model
(`DeviceList` menghitung hanya device terpetakan, `TvAgentInfo` membaca
response agen termasuk nilai yang tidak dikenal).

**SESI TV ditambahkan ke rencana uji**

28 langkah, tiga bagian, tidak butuh Laravel:
- **TV-1 Pairing** termasuk kode salah dan penguncian setelah 10 percobaan
- **TV-2 Kontrol billing** — start, +30m, +1j, checkout, prepaid, swap. Termasuk
  **TV-14: tambah F&B tidak boleh mengubah TV** (kalau berubah, berarti tagihan
  masuk sidik keadaan)
- **TV-3 Ketahanan** — WiFi TV dimatikan, aplikasi di-force-stop, TV di-restart,
  idle 15 menit. **TV-24/TV-25 adalah inti PRD §16 dan T12**: timer harus lanjut
  dari waktu yang benar setelah restart, bukan dari nol

Bisa dijalankan dengan emulator di rumah, atau dengan TV sungguhan di lokasi.

**Known issues**
- Masih belum pernah diuji terhadap TV atau emulator sungguhan.
- Belum ada retry otomatis saat TV tidak merespons; operator menekan
  "Kirim ulang".

**Next step**
1. **Jalankan SESI TV** — ini yang membuktikan jalurnya
2. Catat hasilnya di bawah; TV-24/25 dan TV-26 melengkapi OD-005
3. Kalau lulus: lanjut melengkapi kebutuhan sistem sesuai PRD, mulai dari
   keputusan OD-012 + OD-015 lalu Laravel

---

### 2026-10-02 — Tema terang (DEC-016) + disiplin visual ditegakkan test

**Files changed**

Tema: `lib/core/theme/tokens.dart` (ditulis ulang terang),
`lib/core/theme/app_theme.dart` (`AppTheme.light()`, `InkRipple`),
plus 12 berkas UI menyesuaikan token.
Baru: `test/theme_discipline_test.dart`, `docs/tools/contrast.py`.
Dokumen: `DECISION-LOG.md` (DEC-016), `UI-UX-SPEC.md` §1–§2 ditulis ulang.

**DB changes** — tidak ada.
**API/Events** — tidak ada. Perubahan murni presentasi.

**Yang dikerjakan**

Permintaan user: UI putih, dan **tidak terlihat seperti dibuat AI**.

Yang kedua itu yang menentukan bentuknya. Yang membuat UI terasa dibuat AI
bukan warnanya, melainkan kebiasaan yang menumpuk: gradasi diagonal, glow di
elemen aktif, shadow di setiap kartu, radius besar seragam, kotak di dalam
kotak. Semuanya dihapus — kartu kini dipisahkan garis setipis mungkin plus
nada permukaan, nav aktif memakai penanda tepi kiri 3 px, radius dibedakan
per peran (4/6/10/12), shadow hanya untuk yang benar-benar melayang.

Aksen jadi teal `#0E7490` — keturunan cyan `contoh.html` tapi digelapkan.
Cyan neon tidak bisa memenuhi kontras apa pun di atas putih.

**Catatan:** `UI-UX-SPEC` §1 sebelumnya menolak light mode dengan alasan ruang
rental gelap dan layar terang mencolok dari kursi customer. Alasan itu tidak
terbantahkan, hanya dikesampingkan — user menilai ruangannya sendiri.
Dicatat di DEC-016 supaya tidak digali ulang kalau nanti terasa mengganggu
di lokasi.

**`tv-agent` tidak diubah** — tetap hitam. TV dilihat dari 2–3 meter di ruang
gelap; itu masalah yang berbeda dari tablet di meja kasir.

**Satu bug kontras nyata ditemukan**

Pemeriksaan pertama memakai palet yang saya salin manual dan hanya menguji
latar putih — lolos semua. Setelah skripnya diubah supaya **membaca
`tokens.dart` langsung** dan setiap status diuji juga di atas bidang cekung,
`statusOffline` `#64748B` gagal di **4,28:1**.

Bukan kasus teoretis: order F&B yang dibatalkan dirender `readOnly`, dan
kartu `readOnly` berlatar `surfaceContainer` — label statusnya teks. Slate
dinaikkan ke `#475569` (7,58 putih · 6,81 inset). Yang membuat status itu
terasa tenang adalah saturasinya yang nyaris nol, bukan kontras rendah.

**Tests** — +31 (`theme_discipline_test.dart`), total **186, semuanya lulus**.
`flutter analyze` bersih.

Test itu menegakkan dua hal yang sebelumnya cuma tulisan di dokumen:

1. **Lint source `lib/`** — menolak gradasi apa pun, `Color(0x` di luar
   `tokens.dart`, `BoxShadow(` di luar `tokens.dart`, sisa `Brightness.dark`.
2. **Kontras dari `AppColors`** — setiap status di **dua** latar (kartu putih
   dan bidang cekung), plus penjaga bahwa teks pendukung tetap **di bawah**
   4,5:1 supaya catatan "ini disengaja" tidak jadi basi diam-diam.

Keempat lint sudah dibuktikan menyala terhadap berkas yang sengaja melanggar,
lalu berkas itu dihapus. Lint yang tidak pernah bisa gagal tidak menjaga
apa pun.

**Manual test** — belum. Perubahan ini hanya terverifikasi lewat test dan
perhitungan; **belum pernah dilihat di layar tablet sungguhan.** Warna di
panel tablet murah bisa terasa berbeda dari perhitungan, terutama garis kartu
yang sengaja sangat tipis (1,17:1).

Langkah lihat sendiri:
1. `flutter run` di tablet
2. Dashboard — pastikan tepi kartu masih terlihat, tidak "rata" semua
3. F&B Queue — order dibatalkan: labelnya harus terbaca jelas di kartu abu
4. Lihat dari jarak duduk kasir, bukan dari jarak baca

**Known issues**
- Belum dilihat di layar sungguhan (di atas).
- Garis kartu 1,17:1 adalah angka paling berisiko; kalau hilang di tablet,
  naikkan `surfaceHigh` satu nada — bukan tambah shadow.
- `docs/tools/contrast.py` dan test punya daftar pasangan terpisah. Warnanya
  satu sumber jadi tidak bisa menyimpang, tapi cakupan pasangannya bisa beda.
  Test yang mengikat.

**Next step**
Tidak berubah: **jalankan SESI TV**. Tema tidak menyentuh jalur operator↔TV.

---

### 2026-10-03 — Lima overflow layout diperbaiki + penjaganya dipasang

**Files changed**

`lib/ui/widgets/brand_mark.dart`, `lib/ui/widgets/connection_banner.dart`,
`lib/ui/shell/app_shell.dart`, `lib/ui/dashboard/dashboard_screen.dart`,
`lib/core/theme/tokens.dart`, `lib/app.dart`.
Baru: `test/shell_overflow_test.dart`.

**DB changes** — tidak ada.
**API/Events** — tidak ada.

**Pemicu**

User menjalankan aplikasinya dan konsol mencetak **empat** `RenderFlex
overflowed` (5 px, 201 px, 22 px, 13 px). Ini terjadi sementara **186 test
lolos** — fakta yang paling penting dari sesi ini.

Penyebabnya: tidak ada satu pun test yang pernah memompa shell utuh.
`station_card_layout_test` menguji kartu secara **terpisah** pada ukuran yang
dijamin grid, jadi semua yang di luar kartu tidak pernah diuji — dan keempat
overflow itu semuanya di luar kartu.

**Yang diperbaiki**

| Lokasi | Sebab | Perbaikan |
|---|---|---|
| `brand_mark.dart:56` | Column nama+tagline minta lebar alaminya di dalam `Row` min | `Flexible` + ellipsis. Juga titik rawan OD-012: nama pelanggan tidak bisa ditebak panjangnya |
| `app_shell.dart` blok operator | teks shift di samping titik status berukuran tetap | `Expanded` + ellipsis |
| `app_shell.dart` header | chip DATA CONTOH + indikator koneksi berukuran tetap | Di bawah `headerCompactBreakpoint` (620) keduanya jadi **ikon saja**; tooltip & Semantics tetap membawa keterangan |
| `dashboard_screen.dart` strip shift | angka F&B + kas di kanan | Di bawah 520 px angkanya dilepas, **bukan** dipotong ellipsis |

Judul section juga diberi `maxLines: 1` — tanpa itu ia membungkus ke baris
kedua dan menabrak tinggi header yang tetap 60, bukan dipotong.

**Kenapa header memakai ikon-saja, bukan `Flexible`:** `Flexible` pada
cluster kanan akan ikut membagi ruang saat layar lebar, jadi judul section
terpotong padahal ruangnya masih ada. Melepas label jauh lebih jujur.

**Kenapa uang tidak di-ellipsis:** "Tunai Rp 1.2…" lebih berbahaya daripada
tidak ada angka, karena masih terbaca sebagai nominal. Keduanya ada di tempat
lain — antrian F&B punya badge di nav, kas ada di layar Shift.

**Temuan kelima, yang tidak terlihat di perangkat**

Test baru pada skala teks **1,3×** menemukan overflow **vertikal** 18 px di
`station_card.dart` `_ActiveBody`. Tidak muncul di perangkat user karena
skala teksnya normal, tapi laten.

Diperbaiki pada akarnya: tinggi minimum kartu sekarang **ikut skala teks**
(`AppSize.stationCardMinHeightFor`, dibatasi 1,4×). Kalau ruangnya kurang,
grid yang di-scroll — pilihan yang sama dengan yang sudah dipakai saat layar
pendek. "Enam station tanpa scroll" berlaku pada skala teks normal; operator
yang memperbesar teks memilih keterbacaan di atas kepadatan.

`UI-UX-SPEC` §10 sebelumnya mengklaim text scaling "ada test-nya" — itu
**tidak benar**. Sekarang benar, dan reduced motion ditandai jujur sebagai
belum ada test-nya.

**Tests** — +8 (`shell_overflow_test.dart`), total **194 lulus**.
`flutter analyze` bersih.

Diuji pada 7 lebar, termasuk **tepat di kedua sisi** kedua breakpoint
(1040 dan 620), plus satu skenario skala teks 1,3×. Tiap skenario mengunjungi
kelima section, karena masing-masing membawa layarnya sendiri.

**Dua catatan soal test ini**

1. Awalnya suite butuh **4 menit** untuk keluar. Penyebabnya `OperatorApp`
   membuat `http.Client` sungguhan; tidak ada permintaan yang keluar di test,
   tapi klien yang hidup menahan isolate. `OperatorApp` kini menerima
   `tvClient` opsional — hanya untuk test, `main()` tidak mengisinya.
   Sekarang 4 detik.
2. Widget test memakai font uji yang setiap glifnya kotak, jadi teksnya
   **lebih lebar** daripada di perangkat. Test ini lebih ketat dari
   kenyataan — bagus sebagai penjaga, tapi jumlah pikselnya tidak bisa
   dibandingkan dengan konsol perangkat.

**Manual test** — jalankan ulang `flutter run` dan pastikan konsol
**bersih dari `RenderFlex overflowed`**. Itu verifikasi yang belum bisa saya
lakukan sendiri.

**Known issues**
- Dialog, bottom sheet, dan Session Detail belum masuk cakupan test overflow;
  yang diuji baru shell + kelima section.
- Reduced motion masih belum ada test-nya.

**Next step**
Tetap: **jalankan SESI TV**.

---

### 2026-10-03 — Merek Amor Gaming Space dipasang (DEC-017)

**Files changed**

`lib/core/brand.dart`, `lib/ui/widgets/brand_mark.dart` (ditulis ulang),
`lib/ui/shell/app_shell.dart`, `lib/core/theme/tokens.dart`, `lib/app.dart`,
`lib/ui/device/tv_pair_sheet.dart`, `pubspec.yaml`,
`android/.../AndroidManifest.xml`, `web/index.html`, `web/manifest.json`.
Aset baru: `logo-amor-mark.png`, `logo-amor-full.png`.
Test baru: `test/brand_test.dart`. Pratinjau: `docs/brand/preview-navbar.png`.

**DB changes** — tidak ada. **API/Events** — tidak ada.

**Yang diminta** — logo di navbar, nama Amor Gaming Space, logonya jangan
kecil, navbar lebih iconic, footer jadi "Powered by Cempaka Smart Billing".

**Logo tidak muncul — penyebabnya bukan kode**

Percobaan pertama: logo tidak tampil di perangkat. Bundel aset di `build/`
masih tertanggal 2 Okt 20:44, berisi **11 aset font tanpa satu pun gambar**.

**Menambah aset baru butuh `flutter run` ulang.** Bundel aset dibangun saat
build; hot reload dan hot restart tidak membangunnya ulang. Layak diingat,
karena gejalanya terlihat persis seperti path aset yang salah.

**Alas gelap dilepas — dan ukuran ulang menunjukkan memang tidak perlu**

Percobaan pertama memberi logo alas navy, dengan alasan 60% pikselnya nyaris
putih sehingga hilang di chrome putih. User minta alasnya dilepas.

Diukur ulang, **angka 60% itu dari lockup penuh**, yang sebagian besarnya
wordmark (80% terang). Emblem yang benar-benar dipakai: 33% nyaris putih,
**56% menengah dan gelap**. Stik kontrolernya gelap dan sapuan birunya pekat,
dan keduanya mengelilingi huruf "A" yang putih — huruf itu terbentuk oleh
tetangganya, bukan oleh kontrasnya sendiri. Di atas putih tetap terbaca.

Pelajarannya: angka yang diukur pada keseluruhan berkas tidak otomatis
berlaku untuk bagian yang benar-benar dipakai. Token `brandPlate` dan flag
`logoNeedsDarkPlate` ikut dihapus, tidak disisakan sebagai abstraksi mati.

**Berkas tetap dipangkas** — 62% kiriman aslinya ruang kosong (konten
2532x1781 di kanvas 3373x4770, 1,9 MB untuk slot 44 px). Jadi
`logo-amor-mark.png` (emblem, 256x137, **37 KB**) dan `logo-amor-full.png`
(lockup penuh untuk splash nanti). Kiriman asli disimpan sebagai sumber tapi
**tidak dibundel** — `pubspec.yaml` menyebut berkas satu per satu.

**Hasilnya** — logo setinggi **44 px**, lebar mengikuti rasio aslinya (~82 px),
ditempel tanpa alas maupun bingkai. Sebelumnya kotak monogram 34x34. Nama dua
baris ("Amor" besar + "GAMING SPACE" berjarak huruf) meniru kunci logo
aslinya. Ikon nav 20 -> 22.

Baris kedua memakai Space Grotesk, **bukan** `labelSm` — `labelSm` memakai
JetBrains Mono, font untuk angka dan label teknis; nama merek bukan keduanya.

Pratinjau 3x hasilnya: `docs/brand/preview-navbar.png`.

**String merek yang sebelumnya ditulis langsung di widget**

Ditemukan dan dipusatkan ke `Brand` saat mengerjakan ini: judul `MaterialApp`
(`'Cempaka Billing'` hardcoded), nama aplikasi TV di layar pairing,
`android:label` yang masih `"operator_app"`, serta judul web.

**Tests** — +8 (`brand_test.dart`), total **202 lulus**. `analyze` bersih.

Test mengunci: aset dipakai (bukan monogram), nama dua baris, logo setinggi
yang diminta dan melebar apa adanya, **tidak ada alas/bingkai/gradasi yang
membungkus logo**, penanda memakai emblem (bukan lockup penuh), dan atribusi
naungan tetap berbentuk "Powered by ...".

**Manual test** — **stop dulu**, lalu `flutter run` (bukan hot reload — lihat
di atas). Sidebar: logo tanpa alas, "Amor" + "GAMING SPACE", footer
"Powered by Cempaka Smart Billing".

**Known issues**
- **Versi tanpa alas belum dilihat di perangkat.** Pratinjau dirender lewat
  test (`docs/brand/preview-navbar.png`).
- Yang masih bernama Cempaka: **APK TV**, `applicationId` Android, nama repo.
- Biru logo `#0080F0` vs aksen aplikasi teal `#0E7490` — sedikit berbeda.
  Sengaja tidak diubah: mengganti aksen berarti mengulang seluruh verifikasi
  kontras DEC-016, dan belum diminta.

**Next step**
Tetap: **jalankan SESI TV**.

---

### 2026-10-03 — Audit kesiapan tv-agent untuk dipasang di Android TV

**Files changed** — `tv-agent/app/src/main/AndroidManifest.xml` (satu koreksi).
**DB changes / API** — tidak ada.

Diminta user sebelum testing: apakah APK aman dipasang di Android TV.
Diperiksa pada **APK hasil build**, bukan pada source.

**Yang terbukti aman**

| Periksa | Hasil |
|---|---|
| Build debug | sukses, **4,2 MB** |
| Tanda tangan | v1 **dan** v2 — v1 yang dipakai Android 6/7, v2 untuk yang lebih baru |
| Flag `testOnly` | **tidak ada** — ini yang paling sering menggagalkan sideload |
| `LEANBACK_LAUNCHER` | ada → muncul di laci aplikasi Android TV |
| `leanback` & `touchscreen` | `required=false` → tetap bisa dipasang di tablet/HP |
| minSdk / targetSdk | 23 / 34 |
| Cleartext HTTP | diizinkan lewat `network_security_config` — wajib untuk LAN |
| Bind server | `NanoHTTPD(port)` tanpa hostname → `0.0.0.0`, bukan localhost |
| Lock Task | dijaga `isLockTaskPermitted` + `runCatching` → tidak crash kalau bukan Device Owner |
| Pairing pertama | `KioskActivity.onCreate` memanggil `AgentService.start`, jadi server hidup walau belum ada token |
| Unit test | **31 lulus** |

**Kontrak HTTP Kotlin ↔ Flutter dicocokkan satu per satu**

Titik paling berisiko: Flutter mengirim header `X-Agent-Token`, Kotlin membaca
`"x-agent-token"` huruf kecil. Kalau tidak dinormalkan, **semua** request
terautentikasi gagal.

Diverifikasi di bytecode NanoHTTPD 2.3.1, bukan dari ingatan:
`HTTPSession.decodeHeader` memanggil `toLowerCase(Locale.US)` pada nama
header. Cocok.

Path cocok semua. Seluruh field yang dibaca Flutter (`device_token`,
`device_uid`, `paired`, `pairing_locked`, `requires_pairing`, `station_code`,
`synced`, `seconds_since_sync`, dan delapan field di dalam `device`) memang
dikirim Kotlin.

**Satu koreksi**

`ACCESS_WIFI_STATE` menyiratkan perangkat **wajib** punya WiFi. Itu salah
untuk perangkat ini: banyak TV box dipasang dengan kabel Ethernet, dan agen
ini justru membaca IP dari `NetworkInterface` supaya Ethernet ikut terbaca.
Ditambahkan `uses-feature wifi required=false`.

Bukan penghalang sideload — `uses-feature` hanya menyaring di Play Store,
bukan saat `adb install`. Diperbaiki karena manifest-nya bertentangan dengan
rancangan kodenya sendiri.

**Celah yang tersisa — ketahui sebelum testing**

**Lapisan HTTP-nya sendiri belum pernah diuji.** 31 test mencakup state
machine, pairing, dan sinkronisasi jam — semuanya lewat `CommandApplier`.
Routing, pembacaan token, dan parsing JSON di `LocalHttpCommandSource` tidak
tercakup, karena kelas itu menerima `StateStore` (butuh `Context` Android).

Membuatnya bisa diuji berarti mengubah plumbing `StateStore` jadi antarmuka.
**Sengaja tidak dikerjakan sekarang** — merombak penyimpanan state beberapa
jam sebelum uji perangkat adalah risiko di tempat yang salah. Dikerjakan
setelah SESI TV.

Mitigasinya: kontrak dicocokkan dengan membaca kedua sisi (di atas), dan sisi
klien sudah diuji terhadap fake HTTP di `tv_sync_test`.

**Known issues**
- Lapisan HTTP belum ada test (di atas).
- APK masih bernama **Cempaka TV**, belum ikut rebrand Amor (DEC-017).
- Build debug: `applicationId` berakhiran `.debug`, dan `debuggable=true` —
  wajar untuk uji coba, bukan untuk dipasang permanen di lokasi.

**Next step**
**Jalankan SESI TV.** Langkah install sudah ada di `TEST-PLAN-SABTU.md` §V4/V5.

---

### 2026-10-03 — SESI TV dimulai: APK terpasang, tertahan di jaringan

**Files changed** — `tv-agent/scripts/tv.sh` (baru), `docs/TEST-PLAN-SABTU.md`,
`.gitignore`.
**DB changes / API** — tidak ada.

**Install ke TV**

Percobaan lewat flashdisk gagal — APK tidak muncul. Penyebabnya dicatat di
`TEST-PLAN-SABTU`: Android TV tidak punya file manager bawaan yang bisa
memasang APK, izin "install unknown apps" diberikan **per aplikasi** bukan
sekali untuk sistem, dan flashdisk exFAT/NTFS sering tidak terbaca.

Jalur yang dipakai: **ADB lewat jaringan**. APK berhasil terpasang di TV.

**`scripts/tv.sh`**

Langkah connect–install–log diulang puluhan kali, dan `adb` tidak ada di PATH
mesin ini. Skrip menyimpan IP TV sekali lalu memakainya untuk semua perintah:
`connect`, `install`, `log`, `logclear`, `health`, `restart`, `ip`,
`uninstall`, `disconnect`.

`health` memakai `curl` **dari laptop**, bukan `adb shell` — yang perlu
dibuktikan adalah jalur yang sama dengan yang dipakai tablet operator.
Dipanggil dari dalam TV, jalur itu tidak pernah benar-benar diuji.

**Tertahan: laptop dan TV beda jaringan**

TV tidak ditemukan, baik lewat `ip` maupun `health`. Diperiksa dari laptop:

| | Jaringan |
|---|---|
| TV | `192.168.0.100` (router TP-Link) |
| Laptop | `192.168.110.112`, SSID **PPM ANNUR PUTRA LT 3** |

Ping ke TV **100% hilang**. Beda subnet — paketnya tidak pernah sampai.

**Laptop berpindah sendiri.** Beberapa jam sebelumnya ia berada di
`192.168.0.106`, jaringan yang benar. WiFi lokal untuk pengujian ini tanpa
internet, dan Windows menilai jaringan tanpa internet sebagai lebih buruk lalu
pindah ke yang punya internet — tanpa pemberitahuan, dan bisa di tengah sesi.

**Pencegahannya dipasang, bukan sekadar dicatat.** `tv.sh connect` dan
`tv.sh health` kini memeriksa sendiri: minta OS memilih alamat lokal untuk
menuju IP TV, lalu bandingkan subnetnya. Kalau berbeda, peringatan merah
muncul sebelum perintahnya jalan.

Ini layak dipasang karena **gejala beda subnet sama persis dengan gejala
aplikasi tidak jalan** — dua-duanya "tidak ada jawaban". Tanpa peringatan itu,
waktu habis mencari bug di kode yang tidak bermasalah.

**Langkah berikutnya untuk user**
1. Sambungkan laptop ke SSID yang memberi `192.168.0.x` (kemungkinan
   **TP-Link_DCC6** — TP-Link memakai `192.168.0.x` sebagai bawaan)
2. Matikan **"Connect automatically"** pada WiFi yang ada internet
3. **Tablet operator juga** di SSID yang sama — tiga perangkat, satu jaringan
4. **Buka aplikasi Cempaka TV di TV** — server baru hidup setelah layar kiosk
   muncul; terpasang saja tidak menyalakan apa pun
5. `./scripts/tv.sh health` → kalau menjawab, lanjut ke 28 langkah SESI TV

**Known issues**
- Sambungan operator↔TV **belum terbukti** — tertahan di jaringan, bukan kode.
- Lapisan HTTP `LocalHttpCommandSource` masih belum ada test (lihat entri
  audit sebelumnya).
- APK masih bernama **Cempaka TV**; rebrand ditunda user sampai SESI TV
  selesai (DEC-017).

**Next step**
Pindahkan laptop & tablet ke WiFi TV, lalu `./scripts/tv.sh health`.

---

## SESI TV — diisi saat menguji

### Pairing
| ID | Hasil | Catatan |
|---|---|---|
| TV-01 kode + alamat tampil di TV | ⬚ | |
| TV-03 pemindaian menemukan TV | ⬚ | berapa detik: |
| TV-04 entri manual berhasil | ⬚ | |
| TV-05 pairing berhasil | ⬚ | |
| TV-06 kode salah ditolak | ⬚ | |
| TV-07 terkunci setelah 10× salah | ⬚ | |

### Kontrol billing
| ID | Hasil | Catatan |
|---|---|---|
| TV-10 timer TV sama dengan kartu | ⬚ | |
| TV-11 selisih < 2 detik | ⬚ | selisih: |
| TV-12 +30m sampai ke TV | ⬚ | berapa detik: |
| TV-13 +1j sampai ke TV | ⬚ | |
| TV-14 F&B TIDAK mengubah TV | ⬚ | |
| TV-15 checkout → TV idle | ⬚ | |
| TV-16 prepaid → menunggu bayar | ⬚ | |
| TV-17 bayar → timer | ⬚ | |
| TV-18 swap → TV lama idle | ⬚ | |

### Ketahanan
| ID | Hasil | Catatan |
|---|---|---|
| TV-20 WiFi mati, timer tetap jalan | ⬚ | |
| TV-21 WiFi hidup, tersambung lagi | ⬚ | |
| TV-22 operator menandai tidak merespons | ⬚ | |
| TV-23 kirim ulang menyusul | ⬚ | |
| TV-24 restart aplikasi, timer benar | ⬚ | |
| TV-25 restart TV, timer benar | ⬚ | |
| TV-26 idle 15 menit masih merespons | ⬚ | |
| TV-27 HOME keluar dari aplikasi | ⬚ | wajar pada kiosk lunak |
| TV-28 buka lagi, timer benar | ⬚ | |

### Fakta perangkat
| Yang dicatat | Hasil |
|---|---|
| Model + versi Android | |
| Tingkat kiosk | |
| Pemindaian berhasil atau manual | |
| Device Owner mungkin? (ada akun Google?) | |

---

### 2026-10-07 — [Backend] Tiga keputusan schema & RBAC sebelum migration pertama

**Dikerjakan** (dokumen saja, belum ada kode backend)
- OD-012 diputuskan → **DEC-018**: white-label **per-instance**, satu server satu rental. Schema tanpa `tenant_id`.
- OD-015 diputuskan → **DEC-019**: tarif **berbeda per tipe konsol**, dan **bisa diatur dari aplikasi kasir**.
- OD-017 diputuskan → **DEC-020**: **hanya owner** yang boleh mengubah tarif.
- OD-018 diputuskan → **DEC-021**: swap **hanya dalam tipe konsol yang sama**. Pindah tipe konsol = session baru dengan paket baru, bukan swap. Ini sekaligus mendefinisikan kata "kompatibel" di PRD §15.
- Open Decision baru: **OD-019** (owner ubah tarif lewat login owner atau PIN), **OD-020** (sisa waktu saat pindah tipe konsol).
- PRD-V2 §5, §6, §22 diberi catatan keputusan.

**Dampak paling besar: role jadi tiga, bukan dua**

`ROADMAP.md` Tahap 0 menulis "RBAC admin/operator". DEC-020 memaksa `owner`
jadi role tersendiri — hanya owner yang boleh ubah harga, admin biasa tidak.
Seeder Tahap 0 jadi 3 user: owner, admin, operator.

**Dampak untuk tim Flutter**
- Butuh layar **pengaturan tarif per tipe konsol** di aplikasi kasir (DEC-019), **hanya untuk login owner** (DEC-020). Tetap harus siap menerima 403 dari server — menyembunyikan tombol bukan kontrol keamanan.
- Endpoint ubah tarif belum ada di `contracts/API.md` — akan ditambahkan backend dan dicatat di `contracts/CHANGELOG.md`.
- Cara owner masuk (login sendiri vs PIN) masih OD-019.
- **Layar Station Swap:** daftar station tujuan harus **menyaring tipe konsol yang sama** (DEC-021). Server tetap menolak kalau tipenya beda — penyaringan di UI hanya supaya operator tidak memilih yang pasti gagal.

**Known issues**
- Pembagian kerja: backend (Laravel) dikerjakan pemilik backend; `operator-app/` dan `tv-agent/` oleh rekan tim.
- Status DEC-015 (Tahap 0 ditahan) perlu dikonfirmasi tim sebelum kode Laravel dimulai.

**Next step**
- Inisialisasi repo Laravel + migration pertama (dengan tipe konsol + 3 role). Tidak ada lagi Open Decision yang memblokirnya.

---

### 2026-10-07 — [Backend] Tahap 0 dimulai: kerangka Laravel berdiri

**Keputusan**
- **DEC-022**: penahanan Tahap 0 (DEC-015) **dicabut**. Laravel dimulai, paralel dengan SESI TV yang masih tertahan di jaringan.

**Prasyarat laptop dibereskan**
| | Sebelum | Sesudah |
|---|---|---|
| PHP 8.4.14 | `php.ini` **tidak ada** → semua extension mati | `php.ini` dibuat, `extension_dir` diarahkan ke `C:/php/ext`; `pdo_mysql` `mbstring` `openssl` `curl` `fileinfo` `zip` `intl` aktif |
| Composer | belum ada | 2.10.3 di `C:\composer` (**belum masuk PATH** — dipanggil lewat path penuh) |
| MySQL | belum ada | 8.0.46, service `MySQL80` jalan |

**Dikerjakan**
- `backend/` diisi **Laravel 13.35.0**.
- `laravel/sanctum` 4.3 terpasang + `php artisan install:api` → `routes/api.php` + migration `personal_access_tokens`.
- `.env` & `.env.example`: `DB_CONNECTION=mysql`, database `cempaka_billing`, `APP_NAME="Cempaka Smart Billing"`, locale `id`.
- `README.md` tim dikembalikan (sempat ditimpa README bawaan Laravel).
- Dihapus: `database/database.sqlite` (DEC-002 — MySQL yang jadi primary DB), serta `CLAUDE.md` + `AGENTS.md` bawaan scaffold Laravel — isinya instruksi generik "install PHP" yang bertabrakan dengan kontrak kerja di `CLAUDE.md` root.

**Catatan teknis — `APP_TIMEZONE=UTC`, bukan `Asia/Jakarta`**

DEC-005 menulis "timezone aplikasi `Asia/Jakarta`" tapi juga "disimpan **UTC** di DB, dikirim **UTC** di API, dikonversi di UI". Kalau `APP_TIMEZONE` diisi `Asia/Jakarta`, Eloquent justru menulis waktu Jakarta ke DB — melanggar kalimat kedua dan menggeser `end_at` 7 jam. Jadi `APP_TIMEZONE=UTC`; "Asia/Jakarta" diperlakukan sebagai timezone tampilan, bukan setelan Laravel. Alasannya ditulis sebagai komentar di `.env.example` supaya tidak diubah orang lain tanpa sengaja.

**Belum jalan**
- `php artisan migrate` **gagal**: database `cempaka_billing` belum dibuat dan `DB_PASSWORD` di `.env` masih kosong. Password root MySQL diisi user sendiri — tidak melewati agent.

**Next step**
- User membuat database + mengisi `DB_PASSWORD`, lalu **langkah 2**: lapisan dasar response (`{data, meta}`, header `X-Server-Time`, format error, middleware `Idempotency-Key`, ID UUID) — wajib sebelum endpoint fitur ditulis.

---

### 2026-10-07 — [Backend] Langkah 2: lapisan dasar response + idempotency

Fondasi yang dipakai semua endpoint. **Ditulis sebelum endpoint fitur apa pun** — kalau dikerjakan belakangan, setiap endpoint yang sudah jadi harus dibongkar ulang.

**Files changed**
| Berkas | Isi |
|---|---|
| `app/Support/Api/ApiResponse.php` | pembentuk `{data, meta}` dan `{error, meta}` (API.md §2) |
| `app/Support/Api/ErrorCode.php` | 23 error code dari API.md §11 sebagai konstanta |
| `app/Exceptions/ApiException.php` | exception pembawa `error.code` |
| `app/Http/Middleware/ServerTime.php` | header `X-Server-Time` + `meta.server_time` (DEC-003) |
| `app/Http/Middleware/EnforceIdempotency.php` | penegak `Idempotency-Key` (API.md §3) |
| `app/Models/Concerns/HasUuidKey.php` | primary key UUID v4 (API.md §1) |
| `app/Http/Controllers/Api/V1/HealthController.php` | `GET /health` (API.md §5) |
| `bootstrap/app.php` | prefix `api/v1`, middleware global, pemetaan exception → error code |
| `config/app.php` | `timezone` dari env + `api_version` |
| `phpunit.xml` | test memakai MySQL `cempaka_billing_test`, bukan SQLite |

**DB changes**
- `2026_10_07_140000_create_idempotency_keys_table` — `scope` + `key` unik, `request_hash`, response tersimpan, `expires_at` (retensi 24 jam).
- Database test terpisah `cempaka_billing_test` dibuat.

**API/Events**
- `GET /api/v1/health` — endpoint pertama yang hidup. Tanpa auth (dipakai tes jaringan N2/N3).
- `broadcast` dilaporkan `not_configured`, bukan `ok` — Reverb belum ada (langkah 12). Jangan melaporkan sehat untuk sesuatu yang belum dipasang.

**Tests — 15 lulus, 57 assertion**
- Bentuk `{data, meta}`, `server_time` sama di header dan body, format ISO-8601 `Z`.
- `server_time` tetap ada pada response error.
- Validasi gagal → `VALIDATION_FAILED` + `details` per field.
- Error tak terduga tidak membocorkan isi exception saat `APP_DEBUG=false`.
- Idempotency: header hilang → 400 · bukan UUID → 400 · key+body sama → replay, handler jalan **sekali** · key sama body beda → 409 · key beda → data baru · response gagal tidak dikunci · retensi 24 jam.

**Satu bug nyata ketemu dari test**

`X-Server-Time` awalnya dipasang di grup middleware `api`. Middleware grup **hanya jalan kalau route-nya ketemu** — jadi 404 dari URL salah ketik tidak membawa header, padahal DEC-003 meminta header ada di semua response. Client yang kehilangan offset ikut salah menampilkan timer. Dipindah jadi middleware **global** paling luar, dengan penjagaan supaya Admin Web (Tahap 3B) tidak ikut disentuh.

**Manual test**
```
php artisan serve --host=0.0.0.0 --port=8000
curl -i http://127.0.0.1:8000/api/v1/health      -> 200 + X-Server-Time + database:"ok"
curl -i http://127.0.0.1:8000/api/v1/salah       -> 404 {"error":{"code":"NOT_FOUND"}} + X-Server-Time
```

**Kontrak diperbarui — perlu dibaca tim Flutter**
- `CHANGELOG.md` **DRAFT 5**: `console_type` kini **memengaruhi harga** (DEC-019) — membalik catatan DRAFT 4.
- `CHANGELOG.md` **DRAFT 5b**: penegasan idempotency — hanya 2xx disimpan, key wajib UUID v4, key di-scope per pemakai, replay dapat `server_time` baru.

**Known issues**
- Composer belum masuk PATH; dipanggil lewat `php C:\composer\composer.phar`.
- Baris `idempotency_keys` kedaluwarsa belum dibersihkan — menyusul bersama scheduler (langkah 12).
- Error code untuk swap antar tipe konsol (DEC-021) belum ada di API.md §11.

**Next step**
- **Langkah 3**: migration semua entity (PRD §22) + tipe konsol (DEC-019) + 3 role (DEC-020), lalu seeder ST01–ST06.

---

### 2026-10-07 — [Backend] Langkah 3: schema + seeder Tahap 0

**Files changed**
- `app/Enums/` (7): `UserRole` `SessionStatus` `SessionMode` `SessionItemType` `PaymentMethod` `FnbOrderStatus` `StationStatus`
- `app/Models/` (14): `User` `StationType` `Station` `Package` `Customer` `Membership` `BillingSession` `SessionItem` `Payment` `FnbProduct` `FnbOrder` `FnbOrderItem` `Device` `Shift` `AuditLog`
- `app/Models/Concerns/HasUuidKey.php` ditulis ulang
- `database/seeders/`: `DatabaseSeeder` `UserSeeder` `MasterDataSeeder` `FnbSeeder`
- `database/factories/UserFactory.php` (email → username + role)
- `config/session.php`, `.env`/`.env.example` (`SEED_PASSWORD`)

**DB changes — 24 tabel, 16 entity Tahap 0**
| Migration | Isi |
|---|---|
| `0001_01_01_000000_create_users_table` (diubah) | `users` username/role/is_active + `web_sessions` |
| `2026_10_07_150000_create_master_data_tables` | `station_types` `stations` `packages` `customers` `memberships` |
| `2026_10_07_150100_create_session_tables` | `shifts` `sessions` `session_items` `payments` |
| `2026_10_07_150200_create_fnb_tables` | `fnb_products` `fnb_orders` `fnb_order_items` |
| `2026_10_07_150300_create_devices_table` | `devices` |
| `2026_10_07_150400_create_audit_logs_table` | `audit_logs` |

**Seed:** 3 user (owner/admin/operator1) · 2 tipe konsol · ST01–ST06 · 6 paket · 7 menu F&B · 1 member contoh. Aman dijalankan ulang (`updateOrCreate`) karena pindah VPS nanti memakai `migrate --seed` (DEC-002).

**Tests — 37 lulus, 129 assertion** (sebelumnya 15)
- `hourly_rate`: 1 jam, 2 jam, pembulatan ke atas 65000/3jam → 21667, paket 30 menit.
- DEC-020: hanya OWNER boleh ubah tarif; ADMIN tidak.
- `AVAILABLE` bukan status session · hanya ACTIVE/WARNING orderable (tutup ghost order T05) · EXPIRED masih extendable (grace DEC-007) · transisi status F&B.
- Schema: 16 entity ada · `sessions` milik billing · tidak ada pivot `session_customers` (DEC-008) · PK UUID v4 · tarif beda antar tipe konsol · paket nama sama boleh beda tipe · seeder idempoten.

---

### Tiga hal yang perlu diketahui tim

**1. Nama tabel `sessions` bentrok — Laravel vs PRD §22**

Laravel membuat tabel `sessions` untuk session login web. PRD §22 memakai nama `sessions` untuk session billing. Session web dipindah ke **`web_sessions`** (`config/session.php` ikut diubah); `sessions` tetap milik billing sesuai PRD. Model-nya diberi nama kelas **`BillingSession`** supaya tidak tertukar dengan `Session` milik Laravel — nama tabelnya tetap `sessions`.

**2. Harga dibekukan di `sessions`, tidak diambil ulang dari `packages`**

Kolom `package_name` `package_duration_minutes` `package_price` `hourly_rate` disalin ke baris session saat dibuat.

Alasannya DEC-019/020: owner boleh mengubah tarif kapan saja dari aplikasi kasir. Tanpa snapshot, mengubah harga paket akan mengubah tagihan session **yang sedang berjalan** — termasuk harga extend, karena rumus DEC-007 memakai `hourly_rate`. Customer sudah disebutkan harga di depan; harga itu tidak boleh bergerak di tengah sesi. Hal yang sama dilakukan pada `fnb_order_items` (nama + harga menu dibekukan).

**3. Entity yang sengaja BELUM dibuat**

PRD §22 menyebut `bookings` `expenses` `targets` `notifications` `backups`. Semuanya **tidak** dibuat sekarang, bukan karena terlupa:
- `bookings` butuh grace period, late arrival, cancellation/refund yang semuanya masih TBD di PRD §35 — kolomnya akan jadi tebakan.
- `expenses`/`targets` butuh formula profit/margin (OD-009).
- `notifications` butuh provider & consent (PRD §35), `backups` butuh retention (PRD §35).

Semuanya milik Tahap 3, dan menambah tabel lewat migration saat aturannya sudah jelas lebih murah daripada membongkar kolom yang salah tebak.

**Known issues**
- `enrollment_code` (pairing TV) belum ada tabelnya — alurnya wewenang Admin, Tahap 3B. `devices` sendiri sudah siap.
- Baris `idempotency_keys` kedaluwarsa belum dibersihkan (menyusul bersama scheduler).
- Error code swap antar tipe konsol (DEC-021) belum ada di API.md §11.
- Harga seed = **data test**, bukan tarif Amor Gaming Space.

**Manual test**
```
php artisan migrate:fresh --seed
php artisan test
```

**Next step**
- **Langkah 4**: auth + RBAC — `POST /auth/login`, `GET /auth/me`, `POST /auth/logout` dengan 3 role.

---

### 2026-10-07 — [Backend] Langkah 4: auth + RBAC tiga role

**Files changed**
- `app/Enums/Permission.php` (17 permission) + `UserRole::permissions()`
- `app/Http/Controllers/Api/V1/AuthController.php`
- `app/Http/Requests/Api/V1/LoginRequest.php`
- `app/Http/Middleware/EnsureUserIsActive.php` (alias `active.user`)
- `app/Providers/AuthServiceProvider.php` — Gate per permission + rate limiter
- `app/Support/Audit/AuditLogger.php` + `AuditAction.php`
- `app/Support/Presenters/UserPresenter.php`
- `app/Models/User.php` (HasFactory dikembalikan), `routes/api.php`, `bootstrap/app.php`, `bootstrap/providers.php`

**DB changes**
- `personal_access_tokens` diubah: `morphs` → **`uuidMorphs`** (lihat temuan di bawah).

**API/Events**
| Endpoint | Keterangan |
|---|---|
| `POST /api/v1/auth/login` | tanpa auth, throttle 5/menit/IP |
| `GET /api/v1/auth/me` | token + `active.user` + throttle 120/menit |
| `POST /api/v1/auth/logout` | mencabut token yang dipakai saja |

**Tests — 59 lulus, 239 assertion** (sebelumnya 37)
- Login: bentuk response lengkap · `active_shift` null dan terisi · password salah 401 · akun nonaktif 403 · validasi 422 · throttle 429.
- Token: `/auth/me` butuh token · bentuk `user` identik dengan login · logout mencabut token yang dipakai · logout **tidak** mematikan token perangkat lain · akun dinonaktifkan setelah login langsung kehilangan akses.
- Audit: login sukses & gagal tercatat · nama/role aktor dibekukan · percobaan login username tak terdaftar tetap tercatat.
- RBAC: setiap permission punya Gate · hanya OWNER lolos `pricing.manage` (ADMIN ditolak) · operator punya pekerjaan kasir · operator **belum** boleh daftar member (OD-014) · owner = admin + 1 permission · user nonaktif tidak lolos Gate apa pun.

---

### Empat bug nyata yang ketangkap test

**1. `personal_access_tokens` tidak kompatibel UUID**

`php artisan install:api` membuat kolom `tokenable_id` bertipe integer (`morphs`), sedangkan primary key `users` adalah UUID (API.md §1). Login 500 dengan *"Incorrect integer value"* tepat di baris pembuatan token. Diubah ke `uuidMorphs`.

**2. Login dengan username tak terdaftar → 500, bukan 401**

Untuk mencegah timing attack, `Hash::check` dijalankan juga saat user tidak ada, memakai hash pembanding. Hash pembanding yang saya tulis bukan bcrypt yang sah, dan `Hash::check` melempar *"This password does not use the Bcrypt algorithm"*. Akibatnya username yang tidak ada menjawab 500, sekaligus **tidak tercatat di audit** — percobaan login gagal justru yang paling perlu tercatat. Sekarang hash pembanding dibuat `Hash::make(Str::random(32))` dengan cost yang sama seperti hash asli.

**3. `can:` middleware menghasilkan `SERVER_ERROR`, bukan `FORBIDDEN`**

Middleware `can:` melempar `AccessDeniedHttpException`, bukan `AuthorizationException`, jadi pemetaan exception per-kelas melewatkannya. Client menerima `SERVER_ERROR` untuk penolakan permission yang wajar — padahal kontrak §11 mewajibkan client menangani `FORBIDDEN`. Pemetaan diubah berdasarkan **status HTTP**, bukan kelas, sehingga 401/403/404/429 dari sumber mana pun tetap memakai error code kontrak.

**4. Test lolos padahal token sudah dicabut (false negative)**

Guard menyimpan user yang sudah di-resolve, dan dalam test satu container dipakai beberapa request. Akibatnya token yang sudah dicabut tampak masih sah, dan dua test lolos padahal seharusnya gagal. Ditambah `forgetGuards()` antar request. Di produksi tidak ada masalah ini karena setiap request punya container sendiri.

---

### Keputusan implementasi

- **Permission diturunkan dari role, bukan ditempel ke token.** Kalau permission dibekukan di token Sanctum, operator yang dinaikkan jadi owner harus logout dulu, dan yang diturunkan tetap punya akses lama sampai token kedaluwarsa.
- **`active.user` dipasang bersama `auth:sanctum` di grup route**, bukan per endpoint — route yang lupa memasangnya akan jadi celah.
- **Pesan error login sama** untuk username salah dan password salah, supaya tidak bisa dipakai memetakan username yang terdaftar.
- **Logout hanya mencabut token yang dipakai.** Operator bisa pakai tablet dan laptop bersamaan saat pengujian.
- **`permissions` di response hanya untuk menyembunyikan tombol.** Penolakan tetap di server (PRD §24).

**Known issues**
- Rate limit 120/menit/user dipasang di `/auth/me` dan `/auth/logout`; endpoint lain menyusul saat ditulis.
- `Permission::AUDIT_READ` dan `PRICING_MANAGE` sudah ada Gate-nya tapi belum ada endpoint yang memakainya.

**Manual test**
```
POST /api/v1/auth/login {"username":"owner","password":"password"}      -> token + 17 permission
GET  /api/v1/auth/me (Bearer)                                           -> user + active_shift null
POST /api/v1/auth/login {"username":"operator1","password":"password"}  -> 14 permission, tanpa pricing.manage
```

**Next step**
- **Langkah 5**: master data read-only — `GET /stations`, `GET /packages`, `GET /customers`.

---

### 2026-10-08 — [Backend] Langkah 5: mesin state + billing engine + extend + payment

**Keputusan yang dibuka lebih dulu** (dua OD lama dijawab user, tiga DEC baru)

| | |
|---|---|
| **DEC-023** | Overstay Prepaid: timer jalan terus, kelebihan ditagih saat checkout. **`EXPIRED` jadi penanda, bukan penghenti** |
| **DEC-024** | Sisa waktu Prepaid hangus; hanya member yang bisa menyimpannya |
| **DEC-025** | Sisa waktu member saat pindah tipe konsol dikonversi senilai rupiah ke menit tarif baru |

OD-001 dan OD-020 ditutup. Dua OD baru dibuka: **OD-021** (toleransi pembulatan
overstay) dan **OD-022** (biaya & alur daftar member).

**Dikerjakan**
- `SessionStatus` dapat `allowedNext()` / `canTransitionTo()` / `isFinal()` / `isRunning()` —
  satu tempat yang tahu urutan status, pola sama dengan `FnbOrderStatus`
- `app/Support/Billing/`: `DurationRounding` (DEC-009), `HalfHourPricing`,
  `ExtendPolicy` (DEC-007), `OverstayPolicy` (DEC-023), `SessionTotals`, `DocumentNumber`
- `BillingSession` dapat `transitionTo()`, `totals()`, `entitledMinutes()`,
  `actualMinutes()`, `overstayPrice()`, `isExtendable()`, `extendDeadlineAt()`
- `app/Services/`: `SessionService`, `PaymentService`, `ExtendService` — semuanya
  dalam transaksi dengan `lockForUpdate` pada baris yang diperebutkan
- `SessionPresenter` — satu bentuk objek `session` untuk semua response & event
- Endpoint baru: `GET /sessions`, `GET /sessions/{id}`, `POST /sessions`,
  `POST /sessions/{id}/payments`, `POST /sessions/{id}/extend` (4 → 9 endpoint)

**Keputusan teknis yang perlu diingat**
- **Harga extend dan harga overstay memakai rumus yang sama** (`HalfHourPricing`).
  Kalau dipisah, 30 menit yang sama bisa beda tagihan lewat dua jalur, dan
  operator tidak akan bisa menjelaskannya ke customer.
- **Rental selalu jadi item Open Tab**, termasuk Prepaid. Kalau rental hanya hidup
  sebagai kolom di sesi, `balance_due` tidak punya apa pun untuk ditagih saat
  pembayaran pertama.
- **Paket divalidasi harus setipe konsol dengan station** (DEC-019). Paket PS4 di
  station PS5 akan membekukan tarif yang salah untuk seluruh sesi.
- **`transitionTo()` satu-satunya pintu ubah status.** Menulis `$session->status = ...`
  langsung melewati penjagaan PRD §11 dan sesi bisa selesai tanpa pernah dibayar.
- **Overstay tidak memakai lantai 30 menit**, beda dengan rounding Postpaid —
  kelebihan 2 menit tidak boleh ditagih setengah jam (OD-021).

**Tests**
- **54 unit test lulus, 125 assertion** (sebelumnya 36) — rounding DEC-009 termasuk
  empat contoh wajib 35→30, 63→60, 70→90, 95→90; grace DEC-007 termasuk penolakan
  di menit ke-11; overstay DEC-023; transisi status; totals Open Tab
- **36 feature test baru BELUM dijalankan** — lihat Known issues

**Known issues**
- **Feature test belum terbukti.** Worktree ini tidak punya `.env` dengan password
  MySQL, jadi `cempaka_billing_test` tidak bisa diakses. `AuthTest` lama (15 test)
  ikut gagal dengan error yang sama, jadi ini soal lingkungan, bukan kode. Perbaikannya
  satu baris: `cp ../../../backend/.env backend/.env` dari root worktree.
- Scheduler yang memindahkan ACTIVE → WARNING → EXPIRED belum ada, jadi status
  tidak bergerak sendiri. `extendable` tetap benar karena dihitung dari `end_at`.
- Overstay sudah dihitung dan diuji, tapi belum dipasang ke checkout (checkout
  belum ditulis).
- Belum ada broadcast Reverb — `session.started` / `updated` / `extended` menyusul.

**Manual test**
```
POST /api/v1/sessions           {station_id, package_id, mode:"PREPAID"}   -> 201 PENDING_PAYMENT, end_at null
POST /api/v1/sessions/{id}/payments {method:"CASH", amount:20000}          -> 201 ACTIVE, end_at terisi
POST /api/v1/sessions/{id}/extend   {duration_minutes:30}                  -> 200 price 10000, end_at +30
POST /api/v1/sessions/{id}/extend   {duration_minutes:45}                  -> 422 EXTEND_DURATION_INVALID
(tunggu 11 menit lewat end_at) POST .../extend                             -> 409 EXTEND_GRACE_EXPIRED
```
Semua POST wajib header `Idempotency-Key: <uuid v4>`.

**Next step**
- Jalankan 36 feature test setelah `.env` tersedia, perbaiki yang gagal.
- **Langkah 6**: master data read-only — `GET /stations`, `GET /packages`, `GET /customers`.

---

### 2026-10-08 — [Backend] Langkah 6: F&B, Swap, Checkout, Reverb, Scheduler, Golden Path

**Keputusan baru**

**DEC-026** — saldo member. User melengkapi OD-022: *"sisa waktu berapapun
disimpan di akunnya, nanti bisa dipakai dan digabungkan dengan tambahan biling
lainnya."* Tiga hal yang dikunci: tidak ada minimum, saldo melekat ke customer
(bukan sesi), dan saldo mengurangi **seluruh** Open Tab — bukan hanya rental.
Disimpan dalam **rupiah** (DEC-019 membuat menit tidak punya nilai tetap) sebagai
**buku besar** `customer_credits`, bukan satu kolom saldo (PRD §24 butuh jejak).

**Dikerjakan**
- Migration `customer_credits` + `CustomerCredit` + `CustomerCreditType` (entity ke-17)
- `FnbService` + 4 endpoint: `GET /fnb/products`, `GET /fnb/orders`,
  `POST /sessions/{id}/fnb/orders`, `POST /fnb/orders/{id}/status`
- `SwapService` + `POST /sessions/{id}/swap` — atomic, dua station dikunci
- `CheckoutService` + `POST /sessions/{id}/checkout` — overstay, saldo member, struk
- `CheckoutBilling` (murni, bisa diuji tanpa DB)
- Laravel Reverb terpasang; 8 event + 3 channel + `POST /broadcasting/auth`
- `sessions:reconcile` + jadwal tiap menit
- Koleksi Postman golden path: `docs/postman/cempaka-tahap0.postman_collection.json`
- Endpoint 9 → 15

**Keputusan teknis yang perlu diingat**
- **Rental Postpaid dikurangi menit extend.** Postpaid menagih dari durasi aktual
  (DEC-009) sementara extend tetap item sendiri (PRD §12) — dua aturan itu tumpang
  tindih dan akan menagih 30 menit yang sama dua kali. Totalnya sekarang tetap
  `rounded(aktual)`, hanya dibagi antara rental dan extend. **Ini turunan, bukan
  keputusan user** — kalau salah baca, yang berubah angka tagihan.
- **Overstay hanya untuk Prepaid.** Postpaid sudah menagih durasi aktual, jadi
  tidak ada kelebihan yang belum tertagih.
- **`use_credit` default `false`.** Memakai saldo harus keputusan sadar operator
  di depan customer, bukan perilaku diam-diam.
- **Pembayaran checkout harus persis `balance_due`.** Kurang dan lebih sama-sama
  ditolak — V1 tidak mencatat kembalian.
- **Order F&B yang dibatalkan menghapus `session_item`-nya** kalau belum dibayar.
  Kalau sudah dibayar, barisnya dibiarkan: V1 tidak punya refund.
- **`broadcasting/auth` pakai `auth:sanctum`**, bukan parameter `channels:` di
  `withRouting` — parameter itu memakai guard `web`, dan Flutter/Kotlin datang
  dengan Bearer token.

**Tests**
- **66 unit test lulus, 156 assertion** (sebelumnya 54). Tambahan: `CheckoutBillingTest`
  — termasuk kasus yang membuktikan menit extend tidak tertagih dua kali.
- **36 feature test baru ditulis** (F&B 11, Swap 10, Checkout 13, Golden Path 2),
  total 118 di repo. **Semuanya belum dijalankan.**

**Known issues**
- **Semua feature test masih belum terbukti.** Worktree ini tidak punya `.env`
  berisi password MySQL. Perbaikannya: `cp ../../../backend/.env backend/.env`
  dari root worktree, lalu `php artisan migrate:fresh --seed`.
- **Golden path Postman belum dijalankan** — sama, menunggu DB.
- `device.heartbeat` punya kelas event dan payload, tapi belum ada yang memicunya:
  `POST /devices/heartbeat` milik Tahap 2.
- Channel `private-station.{code}` belum bisa di-subscribe TV — guard
  `X-Device-Token` menyusul di Tahap 2.
- Throttle broadcast REALTIME.md §7 (debounce `session.updated` 500 ms) belum dipasang.
- **`shift_id` selalu NULL di semua payment dan session.** Endpoint `POST /shifts/open`
  belum ada, jadi `activeShift()` tidak pernah mengembalikan apa pun. Akibatnya
  rekonsiliasi kas per shift (PRD §20) belum bisa dijalankan sama sekali, padahal
  kolomnya sudah terisi di schema. Harus beres sebelum dipakai untuk uang nyata.

**Manual test**
```
php artisan migrate:fresh --seed
php artisan serve --host 0.0.0.0
php artisan schedule:work          # terminal lain
php artisan reverb:start           # terminal lain, kalau ingin melihat event
```
Lalu import `docs/postman/cempaka-tahap0.postman_collection.json`, isi `base_url`,
`station_id`, `package_id`, `product_id`, jalankan Collection Runner berurutan.

**Next step**
1. Isi `.env`, jalankan 118 feature test, perbaiki yang gagal.
2. Jalankan golden path dari Postman — ini exit criteria Tahap 0.
3. Master data read-only: `GET /stations`, `GET /packages`, `GET /customers`.

---

### 2026-10-08 — [Backend] Verifikasi: seluruh test dijalankan, golden path lulus

Sesi sebelumnya menulis kode tanpa bisa membuktikannya — worktree tidak punya
`.env`. Ternyata file-nya ada di folder kerja utama; tinggal disalin.

**Hasil**

| | |
|---|---|
| Unit | **66 lulus** |
| Feature | **118 lulus** |
| Total | **184 lulus, 640 assertion, 12 detik** |
| Golden path HTTP | **33/33 lulus** |

**Tiga bug ditemukan — semuanya di test, bukan di aplikasi**

1. `FnbOrderTest::session()` dan `SessionPaymentTest::session()` menabrak
   `session()` milik `Illuminate\Foundation\Testing\TestCase` yang bersifat
   public. PHP menolak menurunkan visibility-nya → fatal error sebelum satu
   test pun jalan. Diganti `makeSession()`.
2. `SessionSwapTest::swap()` menabrak `swap()` milik TestCase (protected,
   dari `InteractsWithContainer`). Diganti `doSwap()`.
3. `SessionPaymentTest::test_tanpa_idempotency_key_ditolak` dapat 409 bukan
   400. Penyebabnya `withHeaders()` Laravel **bertahan antar request dalam
   satu test**, jadi `Idempotency-Key` dari pembuatan sesi masih menempel dan
   yang teruji justru "key dipakai ulang". Ditambah `flushHeaders()`.

Tidak ada satu pun kode aplikasi yang berubah untuk membuat test hijau.

**Golden path dijalankan dari luar**

`php artisan serve` pada database terpisah (`cempaka_billing_golden`, dibuat
dan dihapus lagi supaya data dev tidak tersentuh), lalu 33 pemeriksaan lewat
HTTP sungguhan:

```
start Prepaid -> tolak tanpa Idempotency-Key -> tolak station terpakai
-> bayar -> tolak bayar lebih -> F&B -> antrian PENDING..DELIVERED
-> tolak lompat status -> tolak extend 45 menit -> extend 30 menit
-> swap ST01->ST02 (end_at & session_id tidak berubah) -> tolak kurang bayar
-> checkout -> tolak checkout ulang
```

Struk `INV-20261008-0001`, grand total 47.500 (rental 25.000 + F&B 10.000 +
extend 12.500). Angkanya cocok dengan hitungan manual.

**Yang masih BELUM terbukti lewat HTTP:** perpindahan `WARNING` dan `EXPIRED`,
karena curl tidak bisa memajukan jam satu jam. Keduanya terbukti di
`GoldenPathTest` yang memakai `travelTo()`, dan `sessions:reconcile` ikut
dipanggil di sana.

**Known issues**
- `shift_id` masih selalu NULL — `POST /shifts/open` belum ada.
- `GET /stations` dan `GET /packages` belum ada, jadi golden path HTTP masih
  mengambil `station_id`/`package_id` langsung dari database. Exit criteria
  ROADMAP menuntut "tanpa sentuh DB manual" — baru terpenuhi penuh setelah
  master data read-only selesai.
- Throttle broadcast (REALTIME.md §7) belum dipasang.

**Next step**
1. `POST /shifts/open` + `close` + `GET /shifts/current` — supaya uang bisa
   dihubungkan ke yang jaga.
2. Master data read-only: `GET /stations`, `GET /packages`, `GET /customers`.

---

### 2026-10-08 — [Backend] Langkah 7: shift kasir + customer + membership

**Lima keputusan user sekaligus**

| | |
|---|---|
| **DEC-027** | Operator **boleh** mendaftarkan member di kasir (menjawab OD-014) |
| **DEC-028** | Diskon **hanya owner** (menjawab OD-006 bagian diskon) |
| **DEC-029** | Biaya daftar member **Rp 10.000**, sementara |
| **DEC-030** | Warning di TV = **overlay kanan atas** (menjawab OD-004 → membuka Tahap 2) |
| **DEC-031** | Struk: layar + cetak opsional + kirim WA untuk member (menjawab OD-010) |

DEC-027 membuka jalan buntu yang ditemukan kemarin: DEC-024 meminta operator
menawarkan membership saat checkout, tapi operator tidak punya permission-nya.

**Dikerjakan**
- `ShiftService` + `POST /shifts/open`, `POST /shifts/{id}/close`, `GET /shifts/current`
- `MembershipService` + `GET /customers`, `POST /customers`,
  `POST /customers/{id}/membership`
- `config/billing.php` — `membership_fee`, supaya angkanya tidak dipatri di kode
- Permission: `CUSTOMER_CREATE` pindah ke operator; `DISCOUNT_MANAGE` baru, owner saja
- Error code baru: `SHIFT_ALREADY_OPEN`, `SHIFT_NOT_OPEN`, `CUSTOMER_ALREADY_MEMBER`
- Endpoint 15 → 21

**Keputusan teknis yang perlu diingat**
- **Selisih kas tidak menghalangi penutupan shift.** Shift yang tidak bisa
  ditutup karena selisih akan membuat operator mengarang angka supaya bisa
  pulang. Selisihnya dicatat di audit, bukan dipaksa nol.
- **Selisih kas tidak disimpan sebagai kolom** — nilainya turunan
  (`closing − opening − cash masuk`), dan menyimpannya berarti ada dua angka
  yang bisa berbeda. Yang perlu bertahan adalah jejaknya di `audit_logs`.
- **Shift orang lain hanya boleh ditutup admin ke atas.** Operator yang lupa
  menutup shift harus bisa dibereskan tanpa menunggu dia kembali, tapi bukan
  oleh sesama operator.
- **Mendaftar member sekalian menautkan sesi Walk-in ke customer itu.** Tanpa
  penautan, sesi tetap tanpa `customer_id` dan sisa waktunya tidak masuk ke akun
  siapa pun saat checkout — persis masalah yang DEC-024 ingin selesaikan.
- **Biaya member masuk Open Tab sesi berjalan**, bukan transaksi terpisah.
  Customer sudah berdiri di meja kasir dengan satu tagihan di depan mata.
- **`summary.rental` termasuk extend** — keduanya penjualan waktu bermain.
  Dasar `rental`/`fnb` memakai nilai transaksi; itu masih OD-013.

**Tests**
- **208 lulus, 721 assertion** (sebelumnya 184). Tambahan: `ShiftTest` 12,
  `CustomerMembershipTest` 11, plus `RbacTest` disesuaikan DEC-027/028.
- `test_alur_lengkap_jadi_member_lalu_sisa_waktu_tersimpan` membuktikan alur
  yang kemarin buntu: Walk-in bayar 1 jam, berhenti menit ke-40, daftar member
  Rp 10.000, checkout — sisa 20 menit senilai 6.666 masuk saldo, tidak hangus.

**Known issues**
- `GET /stations` dan `GET /packages` masih belum ada, jadi golden path HTTP
  masih mengambil id dari database. Exit criteria ROADMAP ("tanpa sentuh DB
  manual") baru terpenuhi penuh setelah itu.
- `DISCOUNT_MANAGE` sudah ada Gate-nya tapi belum ada endpoint diskon.
- Throttle broadcast (REALTIME.md §7) belum dipasang.
- Pengiriman struk ke WA (DEC-031) belum dikerjakan — caranya masih OD-023.

**Next step**
- `GET /stations` + `GET /packages` — menutup butir terakhir master data dan
  membuat golden path benar-benar bebas dari database.

---

### 2026-10-08 — [Backend] DEC-033: waktu habis berarti berhenti (pembatalan DEC-023)

**Keputusan user berbalik dalam satu hari.** Pagi: *"timer jalan terus,
kelebihan ditagih di checkout"* (DEC-023). Sore: *"kalau jamnya sudah selesai ya
TV-nya mati... tidak ada toleransi, kalau dia bayar 1 jam bisa lebih mainnya,
tidak bisa."* Ditanyakan ulang karena bertentangan; user menegaskan yang baru,
dan menambahkan bahwa extend setelah habis juga tidak boleh.

**Yang dicabut**
- **DEC-023 seluruhnya.** `OverstayPolicy` dan `OverstayPolicyTest` **dihapus**,
  bukan dimatikan. Cabang overstay di `CheckoutBilling` dan item `ADJUSTMENT`
  "Kelebihan waktu" di `CheckoutService` ikut hilang.
- **Grace 10 menit DEC-007.** Extend sekarang hanya boleh `now ≤ end_at`.
  Rumus harga, kelipatan 30 menit, dan `end_at_baru = end_at_lama + durasi`
  **tetap berlaku**.
- **OD-021** (toleransi pembulatan overstay) kehilangan objeknya dan ditutup.

**Perubahan kode**
- `SessionStatus`: `EXPIRED` tidak lagi `isExtendable()` maupun `isRunning()`,
  dan satu-satunya transisi keluarnya sekarang `CHECKOUT`.
- `ExtendPolicy`: `isWithinGrace()` → `isWithinWindow()`; `deadlineFor()`
  mengembalikan `end_at` apa adanya.
- `DurationRounding`: parameter `applyMinimum` dihapus — hanya overstay yang
  memakainya, dan parameter yang tidak dipakai siapa pun mengundang
  pemakaian yang salah.
- `CheckoutBilling`: `billable_minutes` Prepaid = hak waktu (paket + extend),
  tidak lagi ditambah menit kelebihan.
- Error code `EXTEND_GRACE_EXPIRED` **dipertahankan namanya** walau artinya
  berubah jadi "waktunya sudah lewat" — mengganti nama akan memaksa client
  membongkar daftar error yang sudah disalin.

**Tests**
- **200 lulus, 707 assertion** (sebelumnya 208/721). Turun karena 8 test
  overstay dihapus, bukan karena ada yang dilewati.
- 20 test gagal saat pertama dijalankan — semuanya memang mengunci aturan lama.
  Diperbaiki satu per satu, bukan dilonggarkan.

**Biaya yang perlu dicatat**
Instruksi ke Kotlin TV Agent berbalik dua kali dalam satu hari: CHANGELOG
DRAFT 6 bilang TV **tidak boleh** mati saat waktu habis; DRAFT 9 bilang TV
**harus** mati. Kalau rekan yang memegang Kotlin sudah mengerjakan yang pertama,
pekerjaan itu terbuang — akibat perubahan keputusan, bukan kesalahan
implementasi. Lebih murah berubah sekarang daripada setelah dipakai di lokasi.

**Yang jadi lebih sederhana**
Mesin billing kehilangan satu cabang penuh. `EXPIRED` kembali punya satu arti
tunggal dan tidak perlu lagi dijelaskan sebagai "penanda, bukan penghenti" di
setiap tempat.

**Known issues**
- Sisa waktu member dihitung dari kapan sesi **ditutup**, bukan kapan customer
  benar-benar berhenti. Operator yang menunda checkout mengurangi saldo member.
  Sudah ada testnya (`test_checkout_terlambat_tidak_menghapus_sisa_waktu`)
  sebagai catatan batas, belum diputuskan apakah perlu diperbaiki.

**Next step**
- `GET /stations` + `GET /packages`.

---

### 2026-10-08 — [Backend] DEC-034: Postpaid tidak punya batas waktu

**Ditemukan dari pertanyaan user**, bukan dari test: *"apakah sudah diterapkan
kalau orang main dulu berapapun, lalu kalau mau bayar baru TV-nya mati?"*

Jawabannya **belum**. Kontrak menulis Postpaid mendapat
`end_at = now + durasi paket`, jadi Postpaid ikut dipotong persis seperti
Prepaid dan bedanya cuma kapan membayar. Kalimat itu ditulis sebelum maksud
Postpaid dijelaskan — salah tangkap dari awal, bukan keputusan yang pernah
diambil. User memilih **tanpa batas**.

**Yang berubah**
- `end_at` Postpaid sekarang `null`. Scheduler melewatinya, jadi tidak pernah
  `WARNING` maupun `EXPIRED`. TV baru berhenti saat operator menutup sesi.
- Baris `RENTAL` Postpaid **tidak dibuat saat start**. Lahir saat checkout
  dengan angka final.
- `totals.rental` Postpaid dihitung **berjalan** dari waktu terpakai, memakai
  `CheckoutBilling` yang sama dengan checkout — satu rumus, bukan dua.
- Extend tidak berlaku untuk Postpaid: tidak ada `end_at` yang bisa digeser.

**Kenapa baris rental tidak dibuat di awal**

Kalau dibuat dengan harga paket, operator akan melihat tagihan 25.000 untuk
customer yang baru main 5 menit — dan menagihkannya. Angka yang ditampilkan
harus angka yang benar saat itu juga, bukan angka yang kebetulan tersimpan.

**Tests**
- **208 lulus, 761 assertion.** `PostpaidOpenEndedTest` (8 test) menguji hal
  yang paling mudah salah: sesi tidak pernah berhenti sendiri walau scheduler
  dijalankan 6 jam kemudian, dan tagihan berjalan benar-benar bertambah
  (10.000 di menit 0 → 20.000 di menit 45 → 30.000 di menit 90).
- 14 test lama disesuaikan. `SessionExtendTest` dan `SessionSwapTest` sekarang
  memakai sesi **Prepaid yang sudah dibayar** sebagai sesi bertimer, karena
  Postpaid tidak punya `end_at` lagi.

**Known issues**
- **OD-002 naik jadi mendesak.** Tanpa batas waktu, kerugian kalau customer
  kabur tidak terbatas lagi. Dulu paling banyak sebesar durasi paket.
- Sisa waktu member masih dihitung dari kapan sesi ditutup, bukan kapan customer
  berhenti (catatan dari entry sebelumnya, belum diperbaiki).

**Next step**
- `GET /stations` + `GET /packages`.

---

### 2026-10-09 — [Backend] DEC-035: struk ke WhatsApp lewat tautan `wa.me`

User memilih yang gratis. Operator menekan tombol di tablet, WhatsApp terbuka
dengan pesan sudah terisi, operator menekan kirim.

**Pekerjaan backend-nya hampir nol — kecuali satu hal.** Tautan dibentuk Flutter
dari objek `receipt` yang sudah lengkap. Yang tetap perlu server adalah bentuk
nomornya: operator mengetik `0812-3456-7890`, tautan `wa.me` butuh
`6281234567890`.

**Dikerjakan**
- `App\Support\Phone::toWhatsApp()` + 6 unit test
- `customer.phone_wa` di response — `null` kalau nomor kosong atau terlalu pendek
- `phone` yang tersimpan tidak diubah: nomor yang sudah "dirapikan" membuat
  pencarian gagal saat operator mengetik ulang bentuk yang sama

**Kenapa di server, bukan di Flutter:** Admin Web (Tahap 3B) akan butuh aturan
yang sama. Satu tempat yang tahu caranya, bukan dua yang bisa berbeda.

**Konsekuensi yang perlu diketahui pemilik:** pengirimannya manual, dan sistem
tidak menyimpan bukti bahwa struknya terkirim. Kalau nanti butuh otomatis atau
butuh jejak terkirim, pindah ke WhatsApp Business API adalah keputusan baru —
bukan perbaikan.

**Tests** — 214 lulus, 774 assertion.

**Next step** — tiga endpoint terakhir Tahap 0: `GET /stations`, `GET /packages`,
`POST /sessions/{id}/cancel`.

---

### 2026-10-09 — [Backend] Tiga endpoint terakhir — TAHAP 0 SELESAI

**Dikerjakan**
- `GET /stations` — dashboard dalam satu panggilan: station, sesi aktif, status
  TV. `meta.offline_threshold_seconds` ikut dikirim.
- `GET /packages` — filter `station_id` / `station_type_id` / `only_active`,
  plus `station_type_id` + `console_type` di tiap paket.
- `POST /sessions/{id}/cancel` — batal dari `PENDING_PAYMENT`.
- Endpoint 21 → 24. Error code baru `SESSION_HAS_PAYMENT`.

**Exit criteria ROADMAP akhirnya terpenuhi penuh**

Golden path dijalankan ulang dari luar lewat HTTP — **38 pemeriksaan, nol
gagal**, dan kali ini **tanpa satu pun id diambil dari database**. Semuanya dari
`GET /stations`, `GET /packages?station_id=`, dan `GET /fnb/products`, persis
seperti yang akan dilakukan tablet.

Alurnya: login → buka shift → baca station & paket → start Prepaid → **batal** →
start lagi di station yang sama → bayar → daftar member → F&B → antrian dapur →
extend → swap → checkout → cek saldo member → tutup shift → station kosong.

**Satu bug ditemukan — oleh golden path, bukan oleh test**

`CancelService` memeriksa pembayaran **sebelum** status. Akibatnya sesi yang
sudah berjalan ditolak dengan "sesi ini sudah menerima pembayaran" padahal
alasan sebenarnya "sesi sudah berjalan, selesaikan lewat checkout". Operator
akan mencari uangnya alih-alih menekan tombol yang benar.

Lolos dari test karena test memakai sesi Postpaid yang belum dibayar — di sana
kedua pemeriksaan memberi jawaban yang sama. Urutannya dibalik dan test
regresinya ditambahkan.

**Throttle broadcast sengaja ditunda — ini keputusan, bukan kelalaian**

`REALTIME.md` §7 meminta `session.updated` di-*debounce* 500 ms per sesi.
Implementasi naif (buang event yang datang dalam 500 ms terakhir) adalah
**throttle**, bukan debounce, dan bisa membuang event **terakhir** — dashboard
operator lalu menampilkan tagihan basi sampai ada event berikutnya. Itu lebih
berbahaya daripada rebuild berlebihan.

Debounce yang benar butuh job tertunda yang membaca state terbaru saat berjalan,
dan itu butuh queue worker yang belum ada di Tahap 0 (DEC-002 — semuanya lokal).
Ditunda sampai Reverb benar-benar jalan di bawah beban nyata dan queue worker
sudah ada.

**Tests** — 233 lulus, 838 assertion. Tambahan: `MasterDataTest` (10),
`SessionCancelTest` (10).

**Yang tersisa dari Tahap 0** — hanya throttle di atas, dan itu ditunda sadar.

**Next step**
- Keputusan **OD-002** (Postpaid kabur) sebelum sistem dipakai untuk uang nyata.
- Tarif dan menu asli menggantikan data uji di seeder.
- Setelah itu: Tahap 2 (`/devices/*`) atau Tahap 1 menyambung Flutter ke API.

---

### 2026-10-10 — [Backend] Tarif asli masuk; dua hal yang belum bisa ditangani

User mengirim catatan harga dari lapangan. Empat hal diklarifikasi lewat
tanya-jawab sebelum apa pun dimasukkan ke sistem — semuanya menyangkut uang.

**Masuk ke seeder (DEC-036)**

| Tipe | Per jam | 3 jam + 1 jam gratis |
|---|---|---|
| PS4 | 10.000 | 35.000 (240 menit) |
| PS3 | 8.000 | 30.000 (240 menit) |

Nama tipe konsol berubah dari karangan (`PS5 VIP` / `PS4 Slim`) jadi `PS4` dan
`PS3`. Biaya member 10.000 di catatan **cocok** dengan DEC-029 yang sudah ada.

**Konsekuensi yang dikunci test, bukan ditemukan belakangan**

Paket "3 jam gratis 1 jam" = 240 menit, jadi tarif per jamnya **di bawah**
tarif normal (PS3 7.500 vs 8.000). Harga extend memakai angka itu, jadi extend
di paket promo lebih murah daripada extend biasa. Ada unit test khusus yang
mengunci angkanya supaya kalau terasa janggal di laporan, jelas ini akibat yang
sudah diketahui.

**Dua hal dari catatan yang BELUM bisa dimasukkan**

1. **Paket 3 jam PS3 20.000 / PS4 25.000** — user menegaskan ini harga **jam
   sepi**. Harga berdasarkan waktu belum ada sama sekali → **OD-025**.
2. **Paket "free 2 minuman"** — sistem tidak punya konsep paket yang berisi
   F&B, jadi minumannya tetap akan tertagih. User memilih penanganan
   **otomatis** (sistem tahu jatahnya) → **DEC-037**, belum dibuat. Durasinya
   juga belum disebut → **OD-026**.

**Masih asumsi:** pembagian station per tipe (ST01–03 PS4, ST04–06 PS3).
Catatan tidak menyebut berapa unit masing-masing. Ditandai di berkas seeder.

**Tests** — 234 lulus. Tambahan: tarif asli dikunci di `SchemaAndSeedTest`,
plus test tarif paket bonus waktu.

**Next step** — tidak berubah: keputusan **OD-002** sebelum dipakai untuk uang
nyata. Setelah itu DEC-037 (paket berisi F&B) kalau paket minuman mau dijual.

---

### 2026-10-10 — [Backend] Audit Tahap 0: satu bug realtime yang tidak tertangkap test

User minta diperiksa ulang apakah Tahap 0 benar-benar selesai. Diaudit butir
per butir terhadap scope ROADMAP, bukan dari ingatan. **Hasilnya: satu bug
nyata ditemukan.**

**Reverb tidak pernah benar-benar dijalankan sampai hari ini**

Seluruh test memakai `BROADCAST_CONNECTION=null`, jadi 234 test bisa hijau
tanpa satu event pun benar-benar terkirim. Saat dicoba sungguhan dengan
`reverb:start` + `queue:work`, hasilnya:

```
App\Events\SessionStarted .. FAIL
cURL error 7: Failed to connect to 0.0.0.0 port 8080
```

**Penyebabnya saya sendiri.** Laravel punya DUA variabel host yang berbeda:

| Variabel | Artinya | Nilai benar |
|---|---|---|
| `REVERB_SERVER_HOST` | alamat yang **didengarkan** Reverb | `0.0.0.0` |
| `REVERB_HOST` | alamat yang **dihubungi Laravel** saat mengirim event | `127.0.0.1` |

Saat memulihkan konfigurasi Reverb 8 Okt, saya menulis `REVERB_HOST=0.0.0.0`.
Itu bukan alamat tujuan yang sah. Setiap broadcast gagal dan masuk
`failed_jobs` **tanpa tanda apa pun di sisi operator** — API tetap membalas
201, sesi tetap dibuat, hanya tabletnya yang tidak pernah dapat kabar.

Setelah diperbaiki, broadcast berhasil (`DONE`) dan Reverb menerimanya.

**Temuan kedua: `queue:work` tidak pernah didokumentasikan**

`queue.default = database`, jadi event broadcast masuk antrean. Tanpa worker,
event menumpuk diam-diam di tabel `jobs` dan tidak pernah terkirim. README dan
koleksi Postman hanya menyebut `serve`, `reverb:start`, dan `schedule:work` —
`queue:work` tidak ada di mana pun.

Desain antreannya sendiri **benar** dan tidak diubah: REALTIME.md §1 menyatakan
realtime adalah optimasi, bukan sumber kebenaran, jadi Reverb yang mati tidak
boleh membuat permintaan HTTP gagal. Yang salah dokumentasinya.

README sekarang memuat keempat proses beserta akibat kalau tidak dijalankan,
penjelasan dua host Reverb, dan satu perintah untuk memeriksa apakah realtime
benar-benar jalan.

**Sisa audit: bersih**

Seluruh scope Tahap 0 di ROADMAP tercentang. Endpoint PRD §23 lengkap kecuali
`/api/bookings` (Tahap 3) dan `/api/devices/heartbeat` (Tahap 2), keduanya
memang di luar Tahap 0. Delapan event terdaftar, 19 aksi audit, 24 endpoint.

**Yang masih belum terbukti:** sisi *subscribe* — apakah tablet dan TV benar-
benar menerima event. Tidak bisa dibuktikan sekarang karena belum ada client
yang menyambung; itu Tahap 1 dan 2.

**Tests** — 234 lulus, 843 assertion.

---

### 2026-10-10 — [Backend] Throttle broadcast + sisi subscribe akhirnya terbukti

Dua butir terakhir yang kemarin masih menggantung. Checklist Tahap 0 sekarang
**tercentang seluruhnya**.

## 1. Debounce `session.updated` — dikerjakan dengan benar, bukan yang mudah

Kemarin ini ditunda dengan alasan: cara mudahnya (buang event yang datang
dalam 500 ms terakhir) adalah **throttle**, dan bisa membuang event
**terakhir** — tablet lalu menampilkan tagihan basi.

Yang dipasang sekarang debounce sungguhan:

- Perubahan pertama **menjadwalkan** satu job tertunda; perubahan berikutnya
  dalam jendela yang sama tidak menambah job apa pun.
- Job membawa **id**, bukan objek sesi. Saat berjalan ia **membaca ulang sesi
  dari database** — jadi yang terkirim selalu keadaan terbaru. Tidak ada
  perubahan yang bisa hilang.
- Penanda dilepas **sebelum** broadcast dikirim. Kalau dilepas sesudahnya,
  perubahan yang terjadi selama pengiriman dianggap masih dalam jendela dan
  tidak dijadwalkan — persis kehilangan event terakhir yang ingin dihindari.
- Penanda punya TTL 60 detik, supaya satu job yang tidak pernah jalan tidak
  memblokir broadcast sesi itu selamanya.

`SessionUpdated` jadi `ShouldBroadcastNow` dan **hanya boleh dipicu dari job
itu**. Lima pemanggil langsung di service dan scheduler dialihkan ke
`SessionUpdateBroadcaster::schedule()`.

**Batas ketelitian:** queue database menyimpan waktu dalam detik, jadi jendela
500 ms praktisnya 0–1 detik. Tidak mengubah sifatnya — tujuannya mengurangi
jumlah broadcast saat ramai, bukan presisi waktu.

7 test baru. Yang terpenting `test_broadcast_membawa_keadaan_terbaru_bukan_
potret_lama`: menjadwalkan, lalu mengubah tagihan, baru menjalankan job — dan
yang terkirim harus angka yang baru.

## 2. Sisi subscribe akhirnya dibuktikan

Kemarin tertulis "belum bisa diuji karena belum ada client". Sekarang ada:
`php artisan realtime:listen`, client WebSocket sungguhan yang menempuh jalur
persis sama dengan Flutter dan Kotlin nanti:

```
login -> handshake WebSocket -> POST /broadcasting/auth -> pusher:subscribe
```

Dijalankan dengan Reverb + serve + queue:work hidup, lalu sesi dibuat dan
dibayar lewat curl. Hasilnya:

```
Tersambung. socket_id = 199399351.639203837
Otorisasi channel private-operator berhasil.
Berhasil subscribe ke private-operator.

EVENT #1: session.started
EVENT #2: payment.confirmed
EVENT #3: session.updated
```

Tiga event asli diterima client. Ini sekaligus membuktikan debounce-nya jalan
di jalur sungguhan, bukan hanya di test.

Perintahnya ditulis dengan soket mentah, tanpa menambah dependensi, dan tetap
berguna di lapangan: kalau operator bilang tabletnya tidak update, jalankan ini
untuk tahu masalahnya di client atau di server.

**Tests** — 241 lulus, 852 assertion.

**Checklist Tahap 0: selesai seluruhnya.**

**Next step** — keputusan **OD-002** sebelum dipakai untuk uang nyata, lalu
DEC-037 (paket berisi F&B) kalau paket minuman mau dijual.

---

### 2026-10-10 — [Backend] Endpoint `/devices/*` — Tahap 2 terbuka

User memilih tetap di backend. Dipilih `/devices/*` karena itu yang paling
membuka jalan: tanpanya Tahap 2 tidak bisa dimulai sama sekali (TV tidak bisa
mendaftar), event `device.heartbeat` tidak punya pemicu, dan **TV belum bisa
subscribe ke channel-nya** — celah yang dicatat 9 Okt.

**Empat endpoint**
- `POST /devices/register` — tanpa auth, dijaga kode pendaftaran + rate limit
- `POST /devices/heartbeat` — `X-Device-Token`, 2/menit/device
- `GET /devices/me/state` — endpoint reconcile
- `GET /devices` — Bearer, untuk layar Status TV

Endpoint 24 → 28. Semua endpoint di kontrak kini ada kecuali `/bookings`
(Tahap 3).

**Dua keputusan teknis yang perlu diingat**

**DEC-038** — kode pendaftaran tinggal di `stations.enrollment_code`, bukan
tabel tersendiri. Tabel tersendiri memungkinkan kode sekali pakai, tapi selama
Tahap 2 teknisi mendaftarkan ulang TV berkali-kali, dan kode sekali pakai tanpa
UI Admin akan buntu setelah percobaan pertama. Konsekuensinya kode berumur
panjang → **OD-027**.

**DEC-039** — `display.mode: LOCKED` akhirnya dipakai. Penahannya (OD-001 &
OD-004) sudah diputuskan. `IDLE` dan `LOCKED` sengaja dipisah: keduanya
"tidak bisa main", tapi `IDLE` berarti belum disewa, `LOCKED` berarti waktunya
habis dan customer perlu ke kasir.

**Keamanan yang ditegakkan**
- Token disimpan sebagai **hash**; mentahnya dikembalikan sekali saja.
- Mendaftar ulang TV yang sama **mematikan token lama**.
- Station yang sudah dipegang TV lain **ditolak**, bukan diambil alih —
  teknisi yang salah membacakan kode akan mematikan TV yang sedang jalan.
- TV tidak bisa menyentuh satu pun endpoint operator (diuji eksplisit).
- TV hanya boleh channel station-nya sendiri; `private-operator` tertutup
  untuk device.

**Guard `device`**

Dibuat lewat `Auth::viaRequest`, jadi guard Laravel sungguhan — bukan jalur
autentikasi buatan sendiri. Akibatnya `auth:device` dan otorisasi channel
memakai mesin yang sama dengan user, dan tidak ada jalur kedua yang harus
diingat saat menambah endpoint.

`POST /broadcasting/auth` sekarang menerima **dua guard**: `auth:sanctum,device`.

**Dibuktikan jalan, bukan diasumsikan**

TV didaftarkan lewat API sungguhan, menyambung ke Reverb lewat WebSocket,
diotorisasi dengan `X-Device-Token`, lalu menerima event:

```
Otorisasi channel private-station.ST01 berhasil.
Berhasil subscribe ke private-station.ST01.

EVENT #1: session.extended
EVENT #2: session.updated   (status COMPLETED)
```

`realtime:listen` ditambahi opsi `--device-token` supaya bisa meniru TV Agent.
Teman yang memegang Kotlin bisa memakainya untuk membandingkan: kalau event
muncul di sana tapi tidak di APK, masalahnya di APK.

**Tests** — 262 lulus, 918 assertion. `DeviceTest` 21 test, lulus di percobaan
pertama.

**Next step** — keputusan **OD-002** sebelum uang nyata. Sisa backend:
DEC-037 (paket berisi F&B) kalau paket minuman mau dijual.
