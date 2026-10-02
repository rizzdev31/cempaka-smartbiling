# PROGRESS — Cempaka Smart Billing

Log harian. **Update setiap selesai kerja**, jangan ditumpuk.
Format entry: tanggal → apa yang dikerjakan → hasil → known issue → next step.

---

## Status sekarang

| | |
|---|---|
| **Tahap aktif** | **TAHAP 2 — Kotlin TV Agent** (kiosk kontrol-langsung, DEC-015). Tahap 0 ditahan |
| **Blocker** | **tidak ada** — kontrak sudah fix, billing rule sudah fix |
| **Blocker Tahap 2** | OD-004 (perilaku warning), OD-005 (fakta TV — **dicek besok**) |
| **Milestone terdekat** | **SESI TV** — uji operator mengendalikan TV (bisa kapan saja) · lalu SESI 1 recon lokasi |
| **Repo** | monorepo private, `github.com/rizzdev31/cempaka-smartbiling` (DEC-010) |
| **Kontrak** | `docs/contracts/` DRAFT 1 selesai (DEC-011) |
| **operator-app** | Semua screen PRD §18 kecuali Login & Booking; kontrol TV terpasang & status TV disatukan; **155 test lulus** |
| **tv-agent** | kiosk + timer + kontrol HTTP lokal; **31 test lulus**; APK 4,0 MB |
| **backend** | masih kosong — **ditahan** (DEC-015) |

### ⏳ Pertanyaan tertunda — ingatkan user

| ID | Pertanyaan | Ditunda sejak | Pemicu peninjauan |
|---|---|---|---|
| **OD-011** | Perlukah penemuan IP server otomatis (scan subnet) di Flutter? | 2 Okt 2026 | **Setelah DHCP reservation diuji di SESI 1.** Kalau IP laptop tetap stabil → tidak perlu. Kalau masih sering berubah → pasang scan subnet (± 100 baris) |
| **OD-015** | Tarif berbeda per tipe konsol (PS5 VIP vs PS4 Slim)? | 2 Okt 2026 | **Sebelum migration pertama.** Kalau ya, `packages` butuh relasi ke tipe station — perubahan schema |
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
