# PRD — Cempaka Smart Billing (V2)

Sistem Billing & Operasional Rental PlayStation
**Tanggal:** 1 Oktober 2026 · **Baseline:** V2 · **Sumber:** `PRD Cempaka Smart Biling.docx`

> ⚠️ **Urutan tahap implementasi pada §32 sudah di-override.** Lihat `DECISION-LOG.md` (DEC-001) dan `ROADMAP.md`.
> Isi requirement (WHAT/WHY) di dokumen ini tetap berlaku.

---

## Keputusan arsitektur

V1 menggunakan **Public VPS** sebagai server utama sejak awal. Laravel + MySQL + Laravel Reverb/WebSocket berada di VPS. **Tidak ada Local Server** sebagai requirement V1. SQLite hanya boleh dipakai sebagai local persistence/cache/queue pada client bila diperlukan — bukan database utama.

| Item | Baseline V2 |
|---|---|
| Target awal | 6 station / TV; expandable |
| Server utama | Public VPS |
| Backend | Laravel |
| Primary database | MySQL di VPS |
| Realtime | Laravel Reverb / WebSocket |
| Operator | Flutter Tablet |
| TV Agent | Kotlin Android TV |
| Admin | Laravel Web |
| Customer | Customer Portal Web via HTTPS |
| Router | TP-Link Archer C64 |
| Local Server | Tidak digunakan pada V1 |
| SQLite | Opsional local persistence client; bukan primary DB |

**Prinsip utama:** MySQL di VPS adalah primary database / source of truth. Flutter dan Kotlin **tidak pernah** mengakses MySQL langsung. Semua perubahan bisnis sensitif harus melalui Laravel API.

### Perubahan dari baseline lama

| Area | Lama | V2 |
|---|---|---|
| Server | PC / Local Server | Public VPS |
| Laravel / MySQL / Reverb | Local | VPS |
| Operator | Flutter | Flutter |
| TV | Kotlin Agent | Kotlin Agent |
| Customer Portal | Local portal | Public HTTPS portal |
| Offline | Full local-first | Degraded resilience; backend tetap butuh koneksi VPS |
| SQLite | Bukan fokus | Opsional local persistence client |

---

## 2. Masalah yang diselesaikan

- Kontrol terpusat atas station, waktu bermain, customer, F&B, pembayaran, checkout.
- Dua pola pembayaran: **Prepaid** (bayar di awal) dan **Postpaid** (open tab).
- F&B masuk ke tagihan session yang sama, tanpa transaksi terpisah.
- Customer bisa lihat sisa waktu & order F&B tanpa ke kasir.
- Extend dengan kontrol operator.
- Station Swap mempertahankan histori dan sisa waktu.
- TV tetap aman saat WebSocket terputus sementara.
- Monitoring, laporan, target, expense, profit, audit untuk Owner/Admin.
- Jalur recovery saat TV/client restart dan saat koneksi VPS terganggu.

## 3. Tujuan produk

Otomatisasi start/monitor/extend/swap/checkout · Rental + F&B + Extend dalam satu Open Tab · Operator app cepat & jelas · Customer self-service terbatas via QR · Timestamp server sebagai sumber waktu · TV Agent dengan local timer failsafe + reconnect · Dashboard Admin/Owner · Audit trail · Fondasi scale dari 6 station.

## 4. Sasaran V1 / MVP

| ID | Requirement | Priority |
|---|---|---|
| MVP-01 | Laravel + MySQL + Reverb berjalan di VPS | MUST |
| MVP-02 | Auth + RBAC Admin/Operator | MUST |
| MVP-03 | Station ST01–ST06 | MUST |
| MVP-04 | Prepaid + Postpaid | MUST |
| MVP-05 | Open Tab Rental + F&B + Extend | MUST |
| MVP-06 | Flutter: dashboard, start, session, payment, checkout | MUST |
| MVP-07 | Kotlin: register, heartbeat, timer, warning, reconnect, lock PoC | MUST |
| MVP-08 | Customer QR + scoped portal | MUST |
| MVP-09 | F&B order + status | MUST |
| MVP-10 | Extend + Station Swap | MUST |
| MVP-11 | Cash + QRIS statis/manual | MUST |
| MVP-12 | Booking online | SHOULD |
| MVP-13 | Midtrans/gateway | AFTER CORE POC |
| MVP-14 | Audit + backup/restore | MUST |
| MVP-15 | Basic revenue/profit/target reporting | SHOULD |

## 5. Non-Goals

- Local Server bukan requirement V1.
- SQLite bukan database pusat/production.
- Flutter/Kotlin tidak direct access MySQL.
- Sistem tidak membaca game state PS5 via HDMI.
- **Tidak boleh mengklaim** semua Android TV mendukung HDMI switching, overlay, Device Owner, Lock Task, atau remote blocking sebelum PoC.
- Multi-cabang penuh bukan fokus V1.
- Fitur baru tidak masuk tanpa decision log.

## 6. Aktor & hak akses

| Aktor | Akses | Batasan |
|---|---|---|
| Admin / Owner | Dashboard, master data, transaksi, customer, F&B, shift, expense, profit, target, device | Server-side authorization wajib |
| Operator | Station, session, payment, F&B, extend, swap, checkout, shift | Dibatasi permission |
| Customer | Portal session sendiri, timer, F&B, extend request | Tidak boleh akses session lain/admin |
| TV Agent | Device API/WebSocket, timer, heartbeat, state recovery | Tidak ada akses admin/DB |
| System | Scheduler, backup, notification, reconciliation | Least privilege |

## 7. Arsitektur sistem V2

```
INTERNET
   ├──────────► PUBLIC VPS
   │              ├─ Nginx + HTTPS
   │              ├─ Laravel + API + Admin Web
   │              ├─ MySQL   ← PRIMARY SOURCE OF TRUTH
   │              └─ Laravel Reverb / WebSocket
   │
   └──────────► Payment / Booking / Notification Services
                        │
                        ▼
              TP-Link Archer C64
                        │
         ┌──────────────┼──────────────┐
         ▼              ▼              ▼
   Flutter         Kotlin TV      Customer
   Operator        Agent ×6       Portal Web
   Tablet          ST01–ST06      via HTTPS

Local client persistence: Flutter/Kotlin → cache/state/queue
                          (BUKAN primary database)
```

- **VPS:** business logic, database, realtime, auth, reporting, web.
- **C64:** connectivity di lokasi; bukan application server.
- **Flutter:** aplikasi operator.
- **Kotlin:** agent pada Android/Google TV.
- **Customer Portal:** web publik, akses data session hanya via secure token.

## 8. Tanggung jawab komponen

| Komponen | Teknologi | Tanggung jawab | Larangan |
|---|---|---|---|
| VPS | Cloud VM | Laravel, MySQL, Reverb, Nginx, backup, web | Jangan simpan secret di client |
| Backend | Laravel | Business logic, auth, RBAC, billing, F&B, booking, payment, audit | Client tidak menentukan harga/state |
| Database | MySQL | Semua data inti | Tidak public |
| Realtime | Reverb/WebSocket | Session/device/payment/F&B events | Jangan kirim countdown tiap detik |
| Operator | Flutter | UI & workflow operator | Tidak direct DB |
| TV Agent | Kotlin | Timer, heartbeat, reconnect, device behavior | Bukan source of truth |
| Customer | Web | Timer, F&B, extend request | Tidak akses admin |
| Router | C64 | Wi-Fi/LAN + guest network | Bukan pengganti auth backend |

## 9. Jaringan & TP-Link Archer C64

- C64 adalah router awal untuk lokasi rental.
- Pisahkan jaringan operasional dan Guest Wi-Fi secara logis.
- Guest isolation boleh dipakai karena Customer Portal V2 ada di VPS publik — customer tidak perlu akses IP lokal.
- Password jaringan operasional tidak diberikan ke customer.
- Switch belum wajib untuk 6 TV jika TV pakai Wi-Fi dan perangkat wired masih muat di port router.
- Kinerja jaringan harus diuji dengan kondisi customer realistis; label AC1200 bukan jaminan.
- Jika traffic customer mengganggu operasional → bandwidth policy / traffic control / tambah network equipment.

## 10. Station & Device Management

| Konsep | Aturan |
|---|---|
| Station | ST01–ST06 pada deployment awal; dapat ditambah |
| Device ID | Unik untuk setiap TV Agent |
| Mapping | Satu station ↔ satu device aktif; perubahan dicatat |
| Heartbeat | `last_seen`, status, version, connection health |
| Device Auth | Token/credential unik dan dapat dicabut |
| IP | Boleh reserved untuk troubleshooting, tapi bukan satu-satunya security control |
| PS Console | Tidak diinspeksi aplikasi; kontrol di TV/device layer |

## 11. Session & state machine

```
AVAILABLE
   ↓
PENDING_PAYMENT
   ↓ payment confirmed
ACTIVE
   ├── EXTENDED ─────► ACTIVE
   ├── STATION SWAP ─► ACTIVE
   ├── WARNING
   ↓
EXPIRED → CHECKOUT → COMPLETED → AVAILABLE
```

| State | Makna |
|---|---|
| AVAILABLE | Station siap dipakai |
| PENDING_PAYMENT | Prepaid dibuat, belum confirmed |
| ACTIVE | Session berjalan |
| WARNING | Mendekati `end_at` |
| EXTENDED | Perubahan `end_at` disetujui |
| EXPIRED | Waktu habis |
| CHECKOUT | Finalisasi balance/payment |
| COMPLETED | Session selesai; station kembali available |
| CANCELLED | Session dibatalkan sesuai policy |

## 12. Walk-in: Prepaid, Postpaid & Open Tab

| Aspek | Prepaid | Postpaid |
|---|---|---|
| Rental | Dibayar sebelum ACTIVE | Masuk balance Open Tab |
| Start | Setelah rental payment confirmed | Bisa ACTIVE tanpa bayar rental di awal |
| F&B | Masuk Open Tab; default dibayar checkout | Masuk Open Tab; dibayar checkout |
| Extend | Masuk Open Tab; default dibayar checkout | Masuk Open Tab; dibayar checkout |
| Checkout | Hanya unpaid F&B/extend/adjustment | Rental + F&B + extend unpaid |
| Receipt | Satu final receipt | Satu final receipt |

**Contoh lapangan:** Customer bayar 1 jam di depan. Di tengah bermain beli teh + mie. Rental sudah paid; F&B masuk Open Tab. Saat selesai, kasir hanya menagih balance F&B/extend yang belum dibayar. **Tidak ada pembayaran rental kedua.**

## 13. F&B Self Order

1. Customer scan QR station/session.
2. Portal memvalidasi secure session token.
3. Menu & harga diambil dari server.
4. Customer pilih item + quantity.
5. Backend menentukan session **dari token**; parameter station dari URL tidak dipercaya.
6. Order dibuat sebagai bagian dari Open Tab session.
7. Operator menerima & memproses order.
8. Customer lihat status order + total sementara.
9. Pembayaran sesuai mode transaksi dan policy.

Kontrol: prank order (ST01 tidak bisa dimanipulasi jadi ST03) · ghost order (tanpa active session ditolak) · harga dihitung server · rate limiting.

## 14. Extend

Request (customer/operator) → backend validasi token/session/status/durasi/policy → operator melihat request → approve/reject → jika approve backend hitung `end_at` baru + buat item EXTEND → broadcast ke client → Flutter/Kotlin/Portal update → audit log (actor, waktu, durasi, harga, hasil).

**Default control:** Extend pakai **approval operator**. Auto-approve hanya lewat keputusan bisnis yang dicatat di decision log.

## 15. Station Swap

Operator pilih active session → pilih station tujuan → backend pastikan target AVAILABLE & kompatibel → swap **atomic** → `session_id` tetap sama → `end_at`, F&B, payment status, customer token, histori tetap terkait session yang sama → TV lama kembali AVAILABLE/LOCKED → TV baru menerima ACTIVE → audit log (asal → tujuan, actor, waktu, hasil).

## 16. Timer & Kotlin TV Agent

Server menyimpan `start_at` / `end_at`. Kotlin menghitung remaining time **secara lokal** dari `end_at` terakhir yang valid. Server **tidak** mengirim countdown detik demi detik.

```
Laravel / MySQL
start_at = ...
end_at   = ...
     │
     └── WebSocket state ──► Kotlin
                               ├─ local timer
                               ├─ 10 min warning
                               ├─ 5 min warning
                               ├─ 1 min warning
                               └─ 0 → expired / controlled lock
```

- Kotlin simpan `end_at` + state minimum untuk recovery.
- WebSocket putus sementara → timer tetap jalan.
- Reconnect → reconciliation dengan server.
- Auto-start dan auto-reconnect.
- Heartbeat untuk monitoring device.
- Device Owner / Lock Task / Accessibility / overlay / HDMI switching / remote blocking **wajib diuji pada TV aktual**. Tidak boleh klaim universal support.

## 17. Customer Portal

Akses HTTPS publik · QR berisi URL + opaque token · token scoped ke satu session + punya masa berlaku · tampilkan station/session yang sah · remaining time dari `end_at` · F&B menu, cart, order status, total sementara · request Extend · session expired/completed → order baru ditolak · tidak ada akses admin/MySQL/SSH/internal service.

## 18. Flutter Operator

| Screen | Minimum function |
|---|---|
| Login | Auth, logout, session timeout |
| Dashboard | ST01–ST06, status, timer, customer, quick actions |
| Start Session | Customer, package, duration, Prepaid/Postpaid, payment status |
| Session Detail | Timer, Open Tab, F&B, Extend, Swap, Checkout |
| F&B Queue | Incoming order, process, delivered/status |
| Payment | Cash, QRIS/manual, gateway status if enabled |
| Booking | Booking list, verify, check-in |
| Shift | Start/close shift, handover |
| Device | Online/offline/last seen |

Flutter adalah **client**. Semua business rule & sensitive write tetap divalidasi Laravel.

## 19. Admin Web

| Module | Minimum scope |
|---|---|
| Dashboard | Live stations, sessions, revenue, F&B, payment, device |
| Station & Device | Master station, device mapping, heartbeat, version |
| Package & Pricing | Duration, price, status, rules |
| Customer/Membership | Profile, membership, history |
| F&B/Inventory | Category, menu, HPP, stock, order |
| Booking | Calendar/list, status, payment |
| Payment | Transaction, method, status, reference, reconciliation |
| Shift/Attendance | Shift, attendance, handover |
| Expense/Profit | Expense, HPP, revenue, profit/margin |
| Target | Target omzet vs actual |
| Reports | Daily/weekly/monthly/custom range |
| Audit Log | Actor, action, time, before/after |
| Backup | Backup status & restore evidence |

## 20. Booking Online

Customer pilih tanggal/waktu/station/package → backend cek konflik booking/session → customer bayar sesuai metode aktif → payment status diverifikasi → booking confirmed → customer terima info → saat datang operator verifikasi & mulai session.

Grace period, late arrival, cancellation, refund **harus diputuskan sebagai business rule sebelum production**.

## 21. Payment

| Mode | V1 | Aturan |
|---|---|---|
| Cash | YES | Manual confirmation + audit |
| QRIS Statis | YES | Manual verification + audit |
| Midtrans/Gateway | After Core PoC | Internet + backend validation + webhook |
| Webhook | Jika gateway aktif | Verified + idempotent |
| Settlement | TBD | Mengikuti akun payment provider owner |
| Refund | TBD | Mengikuti policy bisnis |

**Payment rule:** client redirect **bukan** sumber kebenaran pembayaran. Backend harus memvalidasi status provider dan mencegah duplicate callback/transaction.

## 22. Database & data model

Primary DB = MySQL di VPS. Field type, index, FK, unique constraint, migration, seed ditetapkan di Technical Specification.

| Entity | Fungsi |
|---|---|
| `users` | Admin/operator auth & role |
| `customers` | Customer/member |
| `memberships` | Membership |
| `stations` | ST01–ST06+ |
| `devices` | TV Agent, heartbeat, version |
| `packages` | Duration, price, policy |
| `sessions` | Customer, station, status, `start_at`, `end_at`, mode |
| `session_items` | Rental, F&B, EXTEND, discount, adjustment |
| `fnb_products` | Menu, category, price, HPP, stock |
| `fnb_orders` | Order + session + status |
| `bookings` | Schedule + station + customer + package |
| `payments` | Amount, method, status, reference, actor |
| `shifts` | Operator shift |
| `expenses` | Expense |
| `targets` | Revenue target |
| `audit_logs` | Sensitive action history |
| `notifications` | Notification state if enabled |
| `backups` | Backup metadata if tracked |

**SQLite:** boleh dipakai sebagai local persistence di Flutter/Kotlin untuk cache, timer state, atau offline queue. **Bukan** pengganti MySQL dan bukan sumber kebenaran transaksi.

## 23. API & Realtime

| Area | Baseline | Rule |
|---|---|---|
| Auth | `POST /api/auth/login` | Validation + role |
| Stations | `GET /api/stations` | Authenticated |
| Session | `POST /api/sessions` | Server creates state |
| Session Detail | `GET /api/sessions/{id}` | Authorization |
| Extend | `POST /api/sessions/{id}/extend` | Policy + approval |
| Swap | `POST /api/sessions/{id}/swap` | Atomic |
| Checkout | `POST /api/sessions/{id}/checkout` | Final bill |
| F&B | `POST /api/sessions/{id}/fnb/orders` | Token/user scoped |
| Booking | `POST /api/bookings` | Conflict check |
| Device | `POST /api/devices/heartbeat` | Device auth |

| Event | Producer | Consumers |
|---|---|---|
| `session.started` | Laravel | Flutter, Kotlin, Customer |
| `session.updated` | Laravel | Flutter, Kotlin, Customer |
| `session.extended` | Laravel | Flutter, Kotlin, Customer |
| `session.swapped` | Laravel | Flutter, old/new TV, Customer |
| `session.expired` | Laravel / reconciliation | Flutter, Kotlin |
| `payment.confirmed` | Laravel | Flutter, Admin |
| `fnb.order.created` | Laravel | Flutter |
| `device.heartbeat` | Kotlin | Laravel/Admin |

Nama event boleh menyesuaikan framework, tapi **semantic meaning harus konsisten dan terdokumentasi**.

## 24. Security

HTTPS untuk public production · MySQL private, tidak diekspos internet · Laravel melakukan authentication, authorization, validation, rate limiting, audit · customer token random/scoped/expirable/revocable · `station_id` dari URL/QR = untrusted input · TV device credential unik & dapat dicabut · webhook payment diverifikasi & idempotent · permission ditegakkan di backend · backup tidak dapat diakses customer · audit untuk login, payment, extend, swap, discount, adjustment, price/master-data change.

## 25. Offline / Degraded Mode

> **Perubahan paling penting:** karena server utama = VPS, sistem V2 **tidak boleh diklaim full offline**. Backend operation bergantung pada koneksi VPS. Hanya fungsi dengan local persistence/failsafe yang boleh tetap jalan saat koneksi hilang.

| Skenario | Perilaku V2 |
|---|---|
| Internet normal | Semua client akses VPS; realtime normal |
| WebSocket putus sementara | Kotlin timer tetap jalan dari `end_at`; reconnect + reconcile |
| TV kehilangan internet | Timer lokal jalan sampai `end_at` terakhir yang valid |
| Internet mati | Portal/API/payment/operasi baru bergantung koneksi VPS |
| Flutter offline | Last-known state; offline queue hanya jika benar-benar diimplementasikan |
| VPS down | Core backend unavailable; operator ikut incident procedure |
| TV reboot | Kotlin auto-start + sync state server saat koneksi kembali |

## 26. Backup & Recovery

MySQL VPS wajib automated backup · backup tidak hanya di storage yang sama · retention period ditetapkan · **restore test wajib sebelum production sign-off** · backup failure harus terlihat Admin/Owner · backup sebelum migration besar · backup kedua boleh di cloud/object storage terpisah.

## 27. Reporting, Finance & Monitoring

| Area | Minimum output |
|---|---|
| Revenue | Rental + F&B + total |
| HPP | F&B cost dari product master |
| Profit | Gross profit/margin sesuai formula |
| Expense | Expense by category/period |
| Target | Target omzet vs actual |
| Payment | Cash, QRIS/manual, gateway |
| Session | Utilization + station performance |
| Customer | Customer/member history |
| Operator | Shift + transaction attribution |
| Device | Online/offline + last seen + version |

## 28. Non-Functional Requirements

| Kategori | Requirement |
|---|---|
| Reliability | State session persisted server; reconnect tidak membuat duplicate |
| Performance | UI/API responsif pada koneksi normal |
| Realtime | Perubahan penting diterima tanpa refresh ketika WebSocket tersedia |
| Security | DB private, HTTPS, RBAC, token scoping |
| Auditability | Sensitive action punya actor/time/history |
| Maintainability | Code, migration, API, event, README terdokumentasi |
| Scalability | Tambah station tanpa rewrite core |
| Recovery | TV/client reconnect dan backup/restore dapat diuji |

## 29. Golden Path PoC

PoC pertama **tidak** langsung menguji 6 TV dan semua integrasi eksternal. Gunakan satu station **ST01** sebagai vertical slice.

1. VPS sehat: Laravel + MySQL + Reverb + Nginx.
2. C64 terhubung internet.
3. Flutter login, lihat ST01 AVAILABLE.
4. Kotlin TV Agent ST01 registered/online.
5. Operator pilih customer/non-member → package 1 jam → Prepaid.
6. Payment rental dikonfirmasi → session ACTIVE.
7. Laravel simpan `start_at`/`end_at`.
8. Kotlin terima state, tampilkan timer.
9. Customer scan QR ST01 → portal hanya buka session ST01.
10. Customer order satu F&B → masuk Open Tab ST01.
11. Operator process order.
12. Request Extend → operator approve → `end_at` bertambah.
13. WebSocket diputus → timer Kotlin tetap jalan.
14. WebSocket disambung → state reconcile tanpa duplicate.
15. Checkout → rental prepaid sudah paid; F&B/extend unpaid dibayar.
16. Final transaction/receipt tersimpan.
17. Session COMPLETED → ST01 AVAILABLE.
18. Restart TV → Kotlin auto-start & ambil state server.

**Definition of first success:** satu station bisa start → timer → F&B → extend → warning → checkout → completed, termasuk reconnect/recovery, tanpa duplicate session/payment dan tanpa kehilangan `end_at`.

## 30. Acceptance Test Matrix

| ID | Scenario | Expected |
|---|---|---|
| T01 | Prepaid start | Paid → ACTIVE → timer |
| T02 | Postpaid start | ACTIVE → rental unpaid Open Tab |
| T03 | F&B valid | Order masuk session yang benar |
| T04 | Prank order | Token/session mismatch ditolak |
| T05 | Ghost order | No active session → ditolak |
| T06 | Extend | Approve → `end_at` bertambah |
| T07 | Unauthorized extend | Invalid token/policy → ditolak |
| T08 | Station Swap | Same `session_id`; timer/`end_at` tetap |
| T09 | Warning | 10/5/1 minute |
| T10 | WebSocket drop | Kotlin timer tetap |
| T11 | Reconnect | No duplicate state |
| T12 | TV reboot | Auto-start + recovery |
| T13 | Checkout | One final transaction |
| T14 | Booking conflict | Double booking ditolak |
| T15 | Cash | Manual payment audited |
| T16 | QRIS static | Manual verification audited |
| T17 | Gateway webhook | Idempotent |
| T18 | Customer access | No admin/internal resource |
| T19 | RBAC | Restricted action rejected |
| T20 | DB exposure | MySQL not public |
| T21 | Backup | Backup created |
| T22 | Restore | Restore to test environment |
| T23 | 6 TV load | Target load passes |
| T24 | Guest network | Customer cannot reach internal devices |
| T25 | TV control | Kiosk/lock/HDMI behavior validated on actual TV |

## 31. Risiko & Validasi

| ID | Risiko | Mitigasi / Validation | Level |
|---|---|---|---|
| R01 | TV overlay/lock/HDMI beda antar model | PoC pada TV aktual; abstraction + fallback | HIGH |
| R02 | Kotlin Agent killed / memory pressure | Agent ringan + persistence + reconnect + stress test | HIGH |
| R03 | Internet/VPS outage | Degraded mode + TV local timer + incident SOP | HIGH |
| R04 | Customer traffic mengganggu koneksi | Guest separation + load test C64 | MEDIUM |
| R05 | Unauthorized customer access | HTTPS + token scoping + RBAC + guest isolation | HIGH |
| R06 | Duplicate payment webhook | Idempotency + unique reference | HIGH |
| R07 | Station swap memecah histori | Atomic swap + same `session_id` | HIGH |
| R08 | Timer drift | Server `end_at` + reconciliation | HIGH |
| R09 | Database loss | Automated backup + restore test | HIGH |
| R10 | AI scope creep | PRD + Tech Spec + change control | MEDIUM |

## 32. Rencana implementasi (ORIGINAL — sudah di-override, lihat ROADMAP.md)

| Phase | Build | Exit criteria |
|---|---|---|
| P0 | Repo audit + environment + architecture | Stack/repo/dependency/unknowns understood |
| P1 | Laravel + MySQL + Auth + Station | Migrations, auth, station health |
| P2 | Session/Billing Engine | State machine, pricing, prepaid/postpaid, Open Tab |
| P3 | Flutter Operator | Dashboard, start, session, payment, checkout |
| P4 | Kotlin TV Agent | Register, heartbeat, timer, warning, reconnect, lock PoC |
| P5 | Customer Portal + QR | Scoped token, timer, F&B, extend request |
| P6 | F&B + Extend + Station Swap | End-to-end consistency |
| P7 | Booking + Payment Gateway | After core is stable |
| P8 | Admin + Reports + Finance | Dashboard/report/target/expense/profit |
| P9 | Security + Backup + Recovery | Security checks + restore evidence |
| P10 | Scale 6 TV | Load/stress + device monitoring |
| P11 | Production/UAT | SOP + handover + release |

## 33. Aturan development dengan Claude

> Claude = **coding assistant**. Mengikuti PRD + Technical Specification + approved decision log. Bukan pengambil keputusan produk. Jika requirement ambigu/kontradiktif → **berhenti dan minta keputusan**.

- Inspect repository, framework, package versions, folders, env, migrations, README sebelum coding.
- Jangan ganti stack tanpa persetujuan.
- Laravel = source of truth business logic.
- Flutter/Kotlin tidak boleh membuat business rule yang bertentangan dengan backend.
- Setiap DB change lewat migration.
- Setiap endpoint punya validation, authorization, error handling.
- Setiap state transition punya test.
- Payment/retry/reconnect yang berpotensi duplicate wajib idempotent.
- Station Swap harus atomic.
- Customer token harus scoped.
- Server tidak mengirim countdown per detik.
- Jangan refactor unrelated code tanpa alasan.
- Setiap phase melaporkan: files changed, DB changes, API/events, tests, manual test steps, known issues, next step.

**First Claude task:** audit repo vs PRD → tampilkan stack terdeteksi, arsitektur existing, module existing, gap/conflict, dependency/migration issue → buat implementation plan → **jangan coding sebelum audit disetujui team**.

## 34. Change Control & Team Handoff

Product/GM = decision owner business rules · perubahan requirement masuk decision log / Change Request · perubahan DB/API/realtime wajib dikomunikasikan ke client terkait · PRD = WHAT/WHY, Technical Spec = HOW · README menunjuk baseline document aktif · tidak boleh ada silent business-rule change di code.

| Artefact | Owner | Trigger |
|---|---|---|
| PRD | Product/GM | Business flow/requirement berubah |
| Technical Spec | Technical Lead + Team | Architecture/API/state/security berubah |
| DB Schema | Backend | Entity/field/index/constraint berubah |
| API Contract | Backend | Endpoint/request/response berubah |
| Realtime Contract | Backend + Clients | Event/payload/consumer berubah |
| UI Spec | Frontend | Screen/interaction berubah |
| Decision Log | Product + Team | Keputusan baru / conflict resolved |

## 35. Open Decisions / TBD

Poin yang belum diputuskan **tidak boleh** diperlakukan sebagai requirement final.

Spesifikasi final VPS (CPU/RAM/SSD/region/OS) · domain production & struktur subdomain · model Android/Google TV final & OS version · exact TV lock/unlock mechanism · Device Owner/Lock Task/Accessibility/HDMI behavior · warning sound/text final · booking grace period & late arrival · booking cancellation/refund policy · extend pricing & durasi · Midtrans setup/webhook/MDR/settlement/credentials · receipt printer model & interface · backup retention & secondary storage · guest bandwidth policy setelah load test · offline queue Flutter (V1 atau phase berikutnya) · formula profit/margin & target achievement · notification provider & consent.

> Tambahan Open Decisions hasil analisis tim ada di `DECISION-LOG.md` §Open Decisions.

## 36. Definition of Done

| Area | Done jika |
|---|---|
| Architecture | VPS/MySQL/Flutter/Kotlin boundary dipahami; Local Server bukan V1 |
| Backend | Auth, migration, station, session, billing, audit, API |
| Flutter | Golden path operator tanpa DB manual |
| Kotlin | Register, heartbeat, timer, reconnect, recovery, TV PoC |
| Customer | Scoped QR/token; F&B/extend sesuai policy |
| Payment | Manual auditable; gateway idempotent bila aktif |
| Security | HTTPS, private DB, RBAC, token scope, rate limit, audit |
| Recovery | TV restart/reconnect + DB backup/restore |
| Scale | 6 TV load test |
| Documentation | PRD + Technical Spec + README + decision log + test evidence |

---

## Lampiran A — Alur end-to-end

**Walk-in Prepaid:** Customer → Operator → pilih station → pilih package → bayar rental → Laravel ACTIVE → Kotlin timer → F&B/Extend masuk Open Tab → checkout unpaid balance → final transaction/receipt → COMPLETED

**Walk-in Postpaid:** Customer → Operator → station/package → ACTIVE → Rental + F&B + Extend → Open Tab → checkout → payment → COMPLETED

**Customer F&B:** QR → secure token → Customer Portal → menu → order → Laravel → Open Tab → operator process

**Extend:** Request → validation → operator approval → new `end_at` → realtime event → Flutter/Kotlin/Customer update

**Station Swap:** Active session → target station check → atomic swap → same `session_id` → old TV free → new TV active

**Booking:** Web → station/time/package → payment/status → confirmed → arrival verification → session

## Lampiran B — Checklist sebelum coding

- [ ] Team menyetujui VPS sebagai server utama V1
- [ ] MySQL ditetapkan sebagai primary database
- [ ] Local Server dihapus dari V1 scope
- [ ] SQLite hanya local persistence bila dibutuhkan
- [ ] Laravel/Flutter/Kotlin boundary dipahami
- [ ] Golden Path disetujui
- [ ] TV aktual tersedia untuk PoC
- [ ] VPS/domain/HTTPS siap
- [ ] C64 siap + internet stabil
- [ ] Repository diaudit sebelum coding
- [ ] Technical Specification diselaraskan dengan PRD V2
- [ ] Open Decisions yang menghalangi P0/P1 diputuskan
