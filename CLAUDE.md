# CLAUDE.md — Cempaka Smart Billing

> **Baca file ini setiap awal sesi.** Ini kontrak kerja agent di repo ini.
> Baseline aktif: `docs/PRD-V2.md` (PRD V2, 1 Okt 2026) + `docs/DECISION-LOG.md` (override PRD).
> Status harian: `docs/PROGRESS.md`.

---

## 1. Urutan baca wajib (setiap sesi)

| # | File | Kenapa |
|---|------|--------|
| 1 | `CLAUDE.md` | aturan kerja (file ini) |
| 2 | `docs/DECISION-LOG.md` | keputusan terbaru — **menang atas PRD** kalau bentrok |
| 3 | `docs/ROADMAP.md` | tahap mana yang sedang dikerjakan |
| 4 | `docs/PROGRESS.md` | apa yang sudah/belum selesai, known issue |
| 5 | `docs/PRD-V2.md` | detail requirement (WHAT/WHY) |
| 6 | `docs/UI-UX-SPEC.md` | sebelum menyentuh UI apa pun |

Kalau ada bagian PRD yang ambigu atau bentrok → **stop di bagian itu, tanya keputusan.** Jangan berasumsi.

---

## 2. Peran agent

Claude = **coding assistant**, bukan pengambil keputusan produk.

- Ikuti PRD + Decision Log. Fitur baru tanpa entry di Decision Log = **tolak**.
- Laravel adalah source of truth business logic. Flutter/Kotlin tidak boleh membuat business rule sendiri.
- Jangan ganti stack tanpa persetujuan.
- Jangan refactor kode yang tidak berkaitan.

---

## 3. Urutan tahap (SUDAH DIUBAH dari PRD — lihat DEC-001)

```
TAHAP 0  Laravel API Core (lokal)      ← prasyarat, tidak bisa dilewati
TAHAP 1  Flutter Operator (tablet)     ← SEDANG DIKERJAKAN
TAHAP 2  Kotlin Android TV Agent
TAHAP 3  Laravel Superadmin Web + migrasi VPS + Customer Portal + gateway
```

**Penting:** "Laravel tahap 3" = **Admin/Superadmin Web UI + reporting + finance**, BUKAN API.
Laravel API Core wajib ada di Tahap 0/1, karena PRD melarang Flutter menyimpan business rule.
Detail: `docs/ROADMAP.md`.

---

## 4. Aturan teknis yang tidak boleh dilanggar

### Backend (Laravel)
- Setiap perubahan DB lewat **migration**. Tidak ada SQL manual.
- Setiap endpoint: validation + authorization + error handling.
- Setiap state transition punya test.
- Harga **selalu** dihitung server. Client tidak pernah mengirim harga.
- `station_id` dari URL/QR = **untrusted input**. Session ditentukan dari token, bukan dari parameter.
- Station Swap harus **atomic** (DB transaction), `session_id` tidak berubah.
- Payment/retry/reconnect yang bisa duplicate → **wajib idempotent**.
- Semua uang = **integer rupiah**, tanpa desimal.
- Timezone: app `Asia/Jakarta`, simpan **UTC** di DB.

### Aturan billing (sudah final — DEC-007/008/009)
- **Extend:** kelipatan **30 menit** saja. `harga = ceil(tarif_per_jam ÷ 2 × (menit ÷ 30))`, di mana `tarif_per_jam = harga_paket ÷ durasi_paket_jam`.
- **Grace extend:** boleh selama `now ≤ end_at + 10 menit`. `end_at_baru = end_at_lama + durasi` — **bukan** dari waktu approve. Lewat 10 menit → tolak.
- **Satu session = satu customer.** `sessions.customer_id` nullable single FK. Tidak ada pivot `session_customers`.
- **Rounding durasi (Postpaid saja):** `sisa = m mod 30`; `sisa ≤ 5` → bulatkan ke bawah; `sisa > 5` → ke atas; minimum 30 menit. Prepaid & Extend tidak di-rounding.
- **Overstay belum diatur** (OD-001). Jangan putuskan di kode.

### Realtime
- Server **tidak** mengirim countdown per detik. Server kirim `start_at` / `end_at`; client menghitung sendiri.
- Event wajib: `session.started`, `session.updated`, `session.extended`, `session.swapped`, `session.expired`, `payment.confirmed`, `fnb.order.created`, `device.heartbeat`.

### Client (Flutter & Kotlin)
- **Tidak ada akses MySQL langsung.** Selalu lewat Laravel API.
- Base URL **tidak boleh hardcode**. Lewat `--dart-define` / build config / settings screen.
- Setiap POST yang membuat data kirim header `Idempotency-Key` (UUID v4 dari client).
- Jangan hitung sisa waktu dari `DateTime.now()` mentah. Pakai **server-time offset** (lihat §5).
- SQLite hanya untuk cache / timer state / queue. **Bukan** source of truth.

### Security
- Jangan commit `.env`, token, kredensial, atau IP produksi. Pakai `.env.example`.
- Cleartext HTTP hanya di flavor **dev**. Flavor prod wajib HTTPS-only.

---

## 5. Pola wajib: server-time offset

Jam laptop, tablet, dan TV tidak pernah sama. Timer akan salah kalau pakai jam device mentah.

```
1. Setiap response API menyertakan header/field `server_time` (ISO-8601 UTC).
2. Client simpan: offset = server_time - device_time
3. Remaining = end_at - (device_now + offset)
4. Offset di-refresh setiap heartbeat / reconnect.
```

Berlaku untuk Flutter **dan** Kotlin TV Agent.

---

## 6. Format laporan setiap selesai satu potong kerja

Wajib, ringkas:

```
Files changed : ...
DB changes    : migration apa
API/Events    : endpoint/event baru atau berubah
Tests         : yang ditambah + hasil
Manual test   : langkah reproduksi
Known issues  : apa yang belum beres
Next step     : satu langkah berikutnya
```

Lalu **update `docs/PROGRESS.md`**. Kalau ada keputusan baru → tambah entry di `docs/DECISION-LOG.md`.

---

## 7. Kalau menemukan sesuatu yang belum diputuskan

Jangan tebak. Tambahkan ke bagian **Open Decisions** di `docs/DECISION-LOG.md`, beri tanda `NEEDS DECISION`, lanjutkan bagian lain yang tidak terblokir, lalu laporkan.

---

## 8. Definition of first success (Golden Path, 1 station)

Satu station (ST01) bisa menjalankan:

```
start → timer → F&B → extend → warning → checkout → completed
```

termasuk reconnect & recovery, **tanpa duplicate session/payment** dan **tanpa kehilangan `end_at`**.

Jangan kejar 6 TV sebelum ini lulus.
