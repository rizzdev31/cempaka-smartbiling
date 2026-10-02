# ROADMAP — Urutan Tahap Implementasi

**Berlaku sejak:** 2 Oktober 2026 · **Override:** PRD V2 §32
**Keputusan dasar:** `DECISION-LOG.md` → DEC-001

---

## Keputusan tim

> Tahap 1 = **Flutter Operator**. Tahap 2 = **Kotlin APK Android TV**. Tahap 3 = **Laravel Superadmin**.

## Masalah yang harus diselesaikan dulu

PRD §8 dan §33 melarang Flutter menyimpan business rule — Laravel adalah source of truth. Kalau Flutter dibangun tanpa backend sama sekali, logika billing (pricing, state machine, open tab, prorata extend) akan mendarat di Flutter, lalu **harus dirombak total** saat Laravel masuk di Tahap 3. Itu rewrite, bukan refactor.

## Cara menyelesaikannya — pisahkan dua hal yang sering disebut "Laravel"

| | Isi | Kapan |
|---|---|---|
| **Laravel API Core** | auth, station, package, session state machine, billing/open tab, payment manual, F&B, extend, swap, checkout, audit, Reverb | **Tahap 0** (prasyarat Tahap 1) |
| **Laravel Superadmin Web** | dashboard owner, master data UI, reporting, finance/HPP/profit, target, expense, shift, audit viewer, backup UI | **Tahap 3** ✔ sesuai keputusan tim |

Jadi keputusan tim **tetap dijalankan** — yang pindah ke Tahap 3 adalah **UI Superadmin + reporting + finance**, bukan API-nya.

---

## TAHAP 0 — Laravel API Core (lokal) · prasyarat

**Tujuan:** ada source of truth sebelum Flutter dibangun. Jalan di laptop, di WiFi yang sama. Belum perlu VPS.

Scope:
- Laravel + MySQL lokal, migration + seeder (ST01–ST06, packages, F&B dummy, 1 admin, 1 operator)
- Sanctum auth + RBAC admin/operator
- Session state machine: `AVAILABLE → PENDING_PAYMENT → ACTIVE → WARNING → EXPIRED → CHECKOUT → COMPLETED`
- Billing engine: Prepaid, Postpaid, Open Tab, `session_items`
- Endpoint sesuai PRD §23
- Payment manual: Cash + QRIS statis + audit
- F&B order, Extend (approval operator), Station Swap (atomic)
- Checkout → satu final transaction
- Reverb lokal + event sesuai PRD §23
- Scheduler (`schedule:work`) untuk reconciliation `session.expired`
- Header `server_time` di setiap response (lihat `CLAUDE.md` §5)
- `Idempotency-Key` di semua POST yang membuat data
- `audit_logs` aktif sejak awal

**Exit criteria:** seluruh golden path ST01 bisa dijalankan dari Postman/curl, tanpa UI, tanpa sentuh DB manual.

**Belum di tahap ini:** Customer Portal, booking, payment gateway, reporting, admin web UI.

---

## TAHAP 1 — Flutter Operator (tablet) ← DIMULAI LEBIH DULU (DEC-012)

**Tujuan:** operator bisa menjalankan satu hari operasional penuh dari tablet.

Scope (PRD §18):

| Screen | Prioritas |
|---|---|
| Login | MUST |
| Dashboard 6 station (status, timer, customer, quick action) | MUST |
| Start Session (customer, package, durasi, Prepaid/Postpaid) | MUST |
| Session Detail (timer, Open Tab, F&B, Extend, Swap, Checkout) | MUST |
| F&B Queue | MUST |
| Payment (cash, QRIS manual) | MUST |
| Device status (online/offline/last seen) | MUST |
| Shift start/close/handover | SHOULD |
| Booking list/verify/check-in | LATER (Tahap 3) |

Teknis wajib:
- Base URL konfigurable (`--dart-define` + settings screen), **tidak hardcode**
- Server-time offset untuk semua timer
- `Idempotency-Key` per aksi create
- WebSocket Reverb + auto-reconnect + reconcile
- Satu ticker global untuk timer, bukan `setState` per card per detik
- Dark mode, landscape tablet — ikuti `UI-UX-SPEC.md`
- **Tanpa offline write queue** (lihat DEC-004)

**Exit criteria:** golden path PRD §29 lulus T01–T09, T13, T15, T16, T19 dari Flutter, 1 station, tanpa duplicate session/payment.

---

## TAHAP 2 — Kotlin Android TV Agent

**Tujuan:** TV menampilkan timer yang benar dan tidak bisa dicurangi, serta selamat dari putus koneksi.

Scope (PRD §16, MVP-07):
- Device register + device token (unik, revocable)
- Heartbeat → `last_seen`, status, version
- Terima `start_at`/`end_at` via WebSocket, hitung timer lokal
- Server-time offset (sama seperti Flutter)
- Warning 10 / 5 / 1 menit
- Persist `end_at` + state minimum (SQLite/DataStore) untuk recovery
- Auto-start saat boot, auto-reconnect, reconcile tanpa duplicate
- `0 → expired / controlled lock` — **PoC saja**, belum klaim universal
- Layout 10-foot UI, safe area overscan — ikuti `UI-UX-SPEC.md`

**Wajib PoC pada TV aktual sebelum dijanjikan:** Device Owner, Lock Task, Accessibility, overlay, HDMI switching, remote blocking. (PRD §5, R01)

**Exit criteria:** T10, T11, T12, T25 lulus pada TV fisik yang dipakai di lokasi.

---

## TAHAP 3 — Laravel Superadmin + VPS + Customer Portal + Gateway

**Tujuan:** owner punya kontrol & laporan; sistem pindah ke produksi.

### 3A — Migrasi ke VPS
- Provisioning VPS, Nginx, HTTPS, domain/subdomain
- MySQL private (tidak diekspos internet)
- Deploy via `migrate --seed` (bukan dump manual)
- Flutter & Kotlin ganti ke flavor **prod** (HTTPS-only, cleartext dimatikan)
- Automated backup + **restore test** (PRD §26) → T20, T21, T22

### 3B — Superadmin Web (PRD §19)
Dashboard live · Station & Device · Package & Pricing · Customer/Membership · F&B/Inventory + HPP + stock · Payment & reconciliation · Shift/Attendance · Expense/Profit · Target · Reports (daily/weekly/monthly/custom) · Audit Log viewer · Backup status

### 3C — Customer Portal + QR (PRD §13, §17)
Scoped opaque token · timer dari `end_at` · F&B order · extend request · guest-network safe

### 3D — Booking Online + Payment Gateway (PRD §20, §21)
Booking + conflict check · Midtrans/gateway + webhook idempotent · T14, T17

### 3E — Scale & UAT
6 TV load test (T23) · guest network isolation (T24) · SOP + handover

---

## Urutan kerja nyata (solo, sekuensial) — DEC-012

Tahap 0 dan Tahap 1 **tidak dikerjakan berurutan penuh**. Tahap 1 dimulai lebih dulu dengan fake repository, Tahap 0 menyusul sebagai thin slice.

| Kapan | Kerjakan | Catatan |
|---|---|---|
| **Sabtu 3 Okt** | Recon lokasi — SESI 1 | 0 kode. Jawab OD-005 |
| Hari kerja 1–3 | Flutter: scaffold, flavor, tema, komponen, ticker, settings IP, dashboard + session detail | fake repository dari `contracts/API.md` |
| Hari kerja 4–6 | Laravel thin slice: auth, stations, packages, sessions, payments + middleware | bukan Tahap 0 penuh |
| Hari kerja 7 | Ganti fake → API asli | **vertical slice pertama hidup** |
| Lanjut | Sisa screen Flutter & sisa endpoint, bergantian per fitur | F&B, extend, swap, checkout, shift |
| Lalu | SESI 2 di lokasi — golden path T01–T17 | |

Aturan yang mengikat ada di DEC-012. Yang paling penting: **fake repository diganti pada vertical slice pertama**, jangan menumpuk sampai semua screen jadi.

## Ringkasan dependensi

```
TAHAP 0 (API Core)
    │
    ├──► TAHAP 1 (Flutter Operator)
    │         │
    │         └──► TAHAP 2 (Kotlin TV Agent)
    │                   │
    └───────────────────┴──► TAHAP 3 (Superadmin + VPS + Portal + Gateway)
```

Kotlin (Tahap 2) butuh event `session.started/updated/extended/swapped` dari Tahap 0 — jadi kontrak realtime harus sudah fix sebelum Tahap 2 mulai.

## Yang sengaja ditunda

| Item | Ditunda ke | Alasan |
|---|---|---|
| Customer Portal | Tahap 3C | butuh HTTPS publik; di lokal QR tidak aman/tidak praktis |
| Booking online | Tahap 3D | bukan jalur kritis golden path |
| Payment gateway | Tahap 3D | PRD: "after core PoC" |
| Reporting & finance | Tahap 3B | butuh data transaksi nyata dulu |
| Offline write queue Flutter | belum dijadwalkan | DEC-004 |
| 6 TV serentak | Tahap 3E | PRD §29: mulai dari 1 station |
