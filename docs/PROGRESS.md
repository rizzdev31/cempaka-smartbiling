# PROGRESS — Cempaka Smart Billing

Log harian. **Update setiap selesai kerja**, jangan ditumpuk.
Format entry: tanggal → apa yang dikerjakan → hasil → known issue → next step.

---

## Status sekarang

| | |
|---|---|
| **Tahap aktif** | TAHAP 1 — Flutter Operator, pakai fake repository (DEC-012) |
| **Blocker** | **tidak ada** — kontrak sudah fix, billing rule sudah fix |
| **Blocker Tahap 2** | OD-004 (perilaku warning), OD-005 (fakta TV — **dicek besok**) |
| **Milestone terdekat** | **Sabtu 3 Okt: SESI 1 recon lokasi (0 kode)** — `TEST-PLAN-SABTU.md` |
| **Repo** | monorepo private, `github.com/rizzdev31/cempaka-smartbiling` (DEC-010) |
| **Kontrak** | `docs/contracts/` DRAFT 1 selesai (DEC-011) |
| **operator-app** | Dashboard + Session Detail + F&B Queue + **Shift** jalan di fake repository; **78 test lulus**; design pass 1 selesai |
| **backend / tv-agent** | masih kosong (baru README) |

### ⏳ Pertanyaan tertunda — ingatkan user

| ID | Pertanyaan | Ditunda sejak | Pemicu peninjauan |
|---|---|---|---|
| **OD-011** | Perlukah penemuan IP server otomatis (scan subnet) di Flutter? | 2 Okt 2026 | **Setelah DHCP reservation diuji di SESI 1.** Kalau IP laptop tetap stabil → tidak perlu. Kalau masih sering berubah → pasang scan subnet (± 100 baris) |
| **OD-012** | Aplikasi dijual ke beberapa pengguna: **white-label per-instance atau multi-tenant?** | 2 Okt 2026 | **Sebelum migration pertama Laravel ditulis.** Kalau multi-tenant, `tenant_id` harus ada sejak awal — retrofit setelah ada data produksi sangat mahal. Rekomendasi: per-instance |

Analisis lengkap ada di `DECISION-LOG.md` → OD-011 (termasuk deteksi TV lewat heartbeat) dan OD-012.

---

### Checklist Tahap 0

- [x] Monorepo + struktur folder + `.gitignore` (DEC-010)
- [x] Kontrak `docs/contracts/API.md` + `REALTIME.md` + `CHANGELOG.md` DRAFT 1 (DEC-011)
- [ ] Repo Laravel dibuat + `.env.example`
- [ ] MySQL lokal + migration semua entity (PRD §22)
- [ ] Seeder: ST01–ST06, packages, F&B dummy, 1 admin, 1 operator
- [ ] Auth + RBAC admin/operator
- [ ] `GET /api/health` (tanpa auth)
- [ ] Header `server_time` di semua response (DEC-003)
- [ ] Middleware `Idempotency-Key`
- [ ] Session state machine + test per transisi
- [ ] Billing engine: Prepaid / Postpaid / Open Tab / `session_items`
- [ ] Rounding durasi Postpaid per DEC-009 + unit test (35→30, 63→60, 70→90, 95→90)
- [ ] Extend blok 30 menit + grace 10 menit per DEC-007 + test penolakan di menit ke-11
- [ ] Payment manual cash + QRIS statis + audit
- [ ] F&B order endpoint
- [ ] Extend + approval operator
- [ ] Station Swap atomic
- [ ] Checkout → satu final transaction
- [ ] Reverb lokal + semua event PRD §23
- [ ] Scheduler reconciliation `session.expired`
- [ ] `audit_logs` terisi untuk aksi sensitif
- [ ] Golden path ST01 lulus dari Postman

### Checklist Tahap 1 (Flutter)

- [x] Project Flutter + pemisahan dev/prod lewat Android source set
- [x] `ApiConfig` + settings screen ganti IP tanpa rebuild
- [x] Tema dark + tokens dari `UI-UX-SPEC.md`
- [x] Komponen: `StationCard`, `CountdownText`, `MoneyText`, `StatusChip`, `ConnectionBanner`, `ConfirmDialog`, `AsyncButton`
- [x] Ticker global timer + server-time offset
- [x] Model + error code ditranskrip dari kontrak
- [x] Fake repository yang mencerminkan aturan server
- [x] Dashboard 6 station (grid 3×2 landscape)
- [x] Start Session (Prepaid/Postpaid)
- [x] Session Detail + Open Tab
- [x] Payment (cash + QRIS manual)
- [x] Extend + Station Swap + Tambah F&B
- [x] Checkout + struk (durasi aktual vs tertagih)
- [x] 78 test lulus (billing + layout + antrian F&B + shift)
- [x] Design pass 1: rail status, bar proporsi waktu, brand mark, permukaan bertingkat
- [ ] Login / auth
- [ ] Reverb client + auto-reconnect + reconcile
- [x] F&B Queue (layar antrian + badge di dashboard)
- [x] Shift start/close/handover + pertanggungjawaban kas
- [ ] Device status (layar terpisah)
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
