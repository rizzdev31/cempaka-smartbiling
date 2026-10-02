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
| **Repo** | monorepo, `github.com/rizzdev31/cempaka-smartbiling` (DEC-010) — belum di-push |
| **Kontrak** | `docs/contracts/` DRAFT 1 selesai (DEC-011) |
| **Kode app** | `backend/`, `operator-app/`, `tv-agent/` masih kosong (baru README) |

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

- [ ] Project Flutter + flavor `dev` / `prod`
- [ ] `ApiConfig` + settings screen ganti IP
- [ ] Tema dark + tokens dari `UI-UX-SPEC.md`
- [ ] Komponen: `StationCard`, `CountdownText`, `MoneyText`, `StatusChip`, `ConnectionBanner`, `ConfirmDialog`
- [ ] Ticker global timer
- [ ] Login
- [ ] Dashboard 6 station
- [ ] Start Session
- [ ] Session Detail + Open Tab
- [ ] F&B Queue
- [ ] Payment
- [ ] Checkout
- [ ] Device status
- [ ] Reverb client + auto-reconnect + reconcile

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

**Next step**
1. **Besok:** SESI 1 di lokasi — checklist cetak ada di `TEST-PLAN-SABTU.md` §1.6
2. Scaffold Flutter: project + flavor `dev`/`prod` + tema dark + tokens
3. Komponen: `StationCard`, `CountdownText`, `MoneyText`, `StatusChip`, `ConnectionBanner`, `ConfirmDialog`
4. Ticker global + server-time offset
5. `ApiConfig` + settings screen IP
6. Fake repository ditranskrip dari `contracts/API.md`
7. Dashboard + Session Detail di atas fake repository

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
