# API CONTRACT — Cempaka Smart Billing

**Versi:** `v1` · **Status:** DRAFT 1 (2 Okt 2026) · **Owner:** Backend (PRD §34)
**Base path:** `/api/v1`

> Kontrak ini **mengikat** Laravel, Flutter, dan Kotlin. Perubahan apa pun wajib dicatat di `CHANGELOG.md` dan dikomunikasikan ke semua client (PRD §34).
> Field yang belum ada di sini **tidak boleh** diasumsikan oleh client.

---

## 1. Konvensi global

| Hal | Aturan |
|---|---|
| Format | JSON. `Content-Type: application/json` |
| Penamaan field | `snake_case` |
| ID | UUID v4 (string), bukan integer auto-increment |
| Timestamp | ISO-8601 **UTC** dengan `Z` → `2026-10-02T07:15:00Z`. Konversi ke `Asia/Jakarta` hanya di UI (DEC-005) |
| Uang | **integer rupiah**, tanpa desimal (DEC-005) |
| Durasi | integer **menit** |
| Enum | `UPPER_SNAKE_CASE` |
| Bahasa pesan error | Bahasa Indonesia (ditampilkan ke operator) |

### Header request

| Header | Wajib | Keterangan |
|---|---|---|
| `Authorization: Bearer <token>` | ya | semua endpoint kecuali `/health` dan `/auth/login` |
| `X-Device-Token: <token>` | ya (TV) | menggantikan `Authorization` untuk endpoint device |
| `Idempotency-Key: <uuid4>` | ya (POST tertentu) | lihat §3 |
| `Accept: application/json` | ya | |

### Header response

| Header | Selalu ada | Keterangan |
|---|---|---|
| `X-Server-Time` | **ya** | ISO-8601 UTC. **Sumber resmi server-time offset** (DEC-003) |
| `X-Idempotent-Replay` | tidak | `true` kalau response diambil dari cache idempotency |

---

## 2. Bentuk response

### Sukses

```json
{
  "data": { },
  "meta": { "server_time": "2026-10-02T07:15:00Z" }
}
```

List pakai pagination:

```json
{
  "data": [ ],
  "meta": {
    "server_time": "2026-10-02T07:15:00Z",
    "page": 1,
    "per_page": 25,
    "total": 42
  }
}
```

### Error

```json
{
  "error": {
    "code": "EXTEND_GRACE_EXPIRED",
    "message": "Sesi sudah lewat 10 menit dari waktu habis. Lakukan checkout lalu buat sesi baru.",
    "details": { "end_at": "2026-10-02T07:00:00Z", "grace_until": "2026-10-02T07:00:00Z" }
  },
  "meta": { "server_time": "2026-10-02T07:15:00Z" }
}
```

Validasi gagal (422) → `details` berisi map field → array pesan:

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Data yang dikirim tidak valid.",
    "details": { "duration_minutes": ["Durasi harus kelipatan 30 menit."] }
  }
}
```

### HTTP status yang dipakai

`200` OK · `201` Created · `400` bad request (termasuk idempotency key hilang) · `401` belum login / token invalid · `403` tidak punya permission · `404` tidak ditemukan · `409` konflik state (station tidak available, session sudah berubah) · `422` validasi gagal · `429` rate limit · `500` error server

**Client tidak boleh menebak arti error dari HTTP status saja — pakai `error.code`.**

---

## 3. Idempotency

Wajib pada semua POST yang **membuat atau mengubah uang/state**:

```
POST /sessions
POST /sessions/{id}/extend
POST /sessions/{id}/swap
POST /sessions/{id}/checkout
POST /sessions/{id}/cancel
POST /sessions/{id}/payments
POST /sessions/{id}/fnb/orders
POST /shifts/open
POST /shifts/{id}/close
```

Aturan:

1. Client membuat `Idempotency-Key` = **UUID v4 baru per niat aksi**, bukan per retry. Retry pakai key yang sama.
2. Server menyimpan `key → response` minimal **24 jam**.
3. Request ulang dengan key sama → response **identik** + header `X-Idempotent-Replay: true`. Tidak ada data baru dibuat. (`meta.server_time` tetap diperbarui — lihat CHANGELOG DRAFT 5b.)
4. Key sama tapi **body berbeda** → `409 IDEMPOTENCY_KEY_REUSED`.
5. Key tidak dikirim pada endpoint di atas → `400 IDEMPOTENCY_KEY_REQUIRED`.

> Ini pertahanan utama terhadap R06 (duplicate payment) dan operator yang menekan tombol dua kali.

6. **Hanya response sukses (2xx) yang disimpan.** Response gagal tidak dikunci, supaya client bisa memperbaiki body lalu mengirim ulang dengan key yang sama (CHANGELOG DRAFT 5b).
7. Key di-scope per pemakai (user / device). Key operator A tidak menabrak key operator B.

---

## 4. Autentikasi

### `POST /auth/login`
Tanpa auth. Rate limited.

```json
{ "username": "operator1", "password": "..." }
```

→ `200`
```json
{
  "data": {
    "token": "1|abc...",
    "user": {
      "id": "uuid", "name": "Budi", "username": "operator1",
      "role": "OPERATOR",
      "permissions": ["session.create", "payment.confirm", "fnb.manage"]
    },
    "active_shift": { "id": "uuid", "opened_at": "...Z" }
  }
}
```

Error: `401 INVALID_CREDENTIALS`, `403 USER_INACTIVE`, `429 TOO_MANY_ATTEMPTS`

### `GET /auth/me` · `POST /auth/logout`

`GET /auth/me` → objek `user` + `active_shift`. Dipakai saat app dibuka ulang untuk cek token masih valid.

---

## 5. Health

### `GET /health`
**Tanpa auth.** Dipakai untuk tes jaringan dari tablet & TV (`TEST-PLAN-SABTU.md` N2/N3).

```json
{
  "data": {
    "status": "ok",
    "version": "0.1.0",
    "database": "ok",
    "broadcast": "ok"
  },
  "meta": { "server_time": "2026-10-02T07:15:00Z" }
}
```

---

## 6. Master data

### `GET /stations`

```json
{
  "data": [
    {
      "id": "uuid",
      "code": "ST01",
      "name": "Station 1",
      "console_type": "PS5 VIP",
      "status": "ACTIVE",
      "session": {
        "id": "uuid",
        "status": "ACTIVE",
        "started_at": "2026-10-02T07:00:00Z",
        "end_at": "2026-10-02T08:00:00Z",
        "customer_label": "Budi",
        "balance_due": 25000
      },
      "device": {
        "id": "uuid",
        "status": "ONLINE",
        "last_seen_at": "2026-10-02T07:14:50Z",
        "app_version": "0.1.0"
      }
    }
  ]
}
```

- `station.status` ∈ `ACTIVE` | `MAINTENANCE` | `DISABLED` — ini status **master data**, bukan status sesi.
- `station.console_type` = label konsol dari master data, mis. `PS5 VIP`, `PS4 PRO`. Nullable. Teks bebas, **bukan** enum: tiap rental punya penamaan sendiri.
  > **Diperbarui 7 Okt 2026 (DEC-019 — lihat CHANGELOG DRAFT 5):** tipe konsol **memengaruhi harga**. Kalimat lama "tidak memengaruhi harga (lihat OD-015)" sudah tidak berlaku. Bentuk field untuk tarif per tipe konsol ditambahkan saat endpoint-nya ditulis.
- `session` = `null` kalau station kosong. Inilah yang membuat station tampil `AVAILABLE` di dashboard.
- `session.started_at` = `null` saat `PENDING_PAYMENT`. Bersama `end_at`, dipakai client untuk menggambar proporsi waktu terpakai tanpa perlu memuat detail sesi.
- `device` = `null` kalau belum ada TV Agent terdaftar (normal sampai Tahap 2).
- `device.status` **dihitung** dari `last_seen_at`, tidak disimpan — kolom status yang disimpan pasti basi begitu heartbeat berhenti.
- `meta.offline_threshold_seconds` dikirim bersama daftar, supaya ambang ONLINE/OFFLINE tidak dituliskan ulang di client.
- `session` di sini adalah **ringkasan**, bukan objek `session` penuh: enam station dikali seluruh item dan payment akan membuat response dashboard jauh lebih besar daripada yang dipakai menggambar kartunya. Ambil detailnya lewat `GET /sessions/{id}`.

### `GET /packages`

```json
{
  "data": [
    { "id": "uuid", "name": "1 Jam", "duration_minutes": 60, "price": 20000,
      "hourly_rate": 20000, "station_type_id": "uuid",
      "console_type": "PS5 VIP", "is_active": true }
  ]
}
```

### `POST /packages` · `PATCH /packages/{id}` *(owner saja)*

DEC-020 — hanya owner. `POST` butuh `Idempotency-Key`.

```json
{ "station_type_id": "uuid", "name": "3 Jam", "duration_minutes": 180, "price": 30000 }
```

`PATCH` menerima sebagian field saja: `name`, `duration_minutes`, `price`,
`is_active`, `sort_order`. **`station_type_id` tidak bisa diubah** — memindahkan
paket ke tipe konsol lain membuat sesi lama seolah dijalankan di konsol berbeda.

> **Harga sesi yang SEDANG BERJALAN tidak ikut berubah.** Harga dan tarif per
> jam dibekukan ke baris sesi saat dibuat, termasuk untuk menghitung extend
> (DEC-007). Sesi berikutnya memakai harga baru. Setiap perubahan memicu
> `master.updated` (REALTIME.md) supaya tablet lain memuat ulang daftarnya.

### `PATCH /fnb/products/{id}`

Field: `name`, `price`, `stock`, `is_available`. Butuh `fnb.manage` — **kecuali
`price`, yang butuh owner** (DEC-020). Dipisah begitu supaya operator bisa
menandai menu habis sendiri tanpa menunggu owner.

---

`hourly_rate` = `price ÷ (duration_minutes ÷ 60)`, **dihitung server**. Client memakai nilai ini untuk menampilkan estimasi harga extend — tapi harga final tetap dari server (DEC-007).

Filter yang didukung:

| Parameter | Gunanya |
|---|---|
| `station_id` | **Dipakai layar Start Session.** Hanya paket yang sah untuk tipe konsol station itu yang dikembalikan (DEC-019). Station tanpa tipe konsol mengembalikan daftar kosong, bukan seluruh paket — daftar penuh akan membuat operator memilih paket yang pasti ditolak server |
| `station_type_id` | sama, tapi langsung dari tipe konsol |
| `only_active` | default `true`. `false` untuk menampilkan paket yang sudah tidak dijual |

`station_type_id` dan `console_type` ada di setiap paket supaya client bisa mengelompokkannya sendiri tanpa panggilan kedua.

### `GET /customers?q=<nama|telepon>` · `POST /customers`

```json
{ "data": { "id": "uuid", "name": "Budi", "phone": "08...",
            "membership": { "tier": "SILVER", "is_active": true } } }
```

`phone_wa` adalah `phone` yang sudah diubah ke bentuk yang diterima tautan `wa.me` — `0812-3456-7890` jadi `6281234567890` (DEC-035). `null` kalau nomornya kosong atau terlalu pendek. Dipakai saat mengirim struk ke member; `phone` sendiri tetap apa adanya supaya pencarian operator tidak gagal.

`membership` boleh `null`. `credit_balance` adalah saldo member dalam rupiah
(DEC-026) — dikirim di sini supaya operator melihatnya **sebelum** checkout dan
bisa memutuskan mencentang "pakai saldo"; kalau baru muncul setelah checkout,
keputusannya sudah lewat. Non-member selalu `0`.

```json
{ "data": { "id": "uuid", "name": "Budi", "phone": "08...",
            "membership": { "tier": "SILVER", "is_active": true,
                            "joined_at": "...Z" },
            "credit_balance": 6666,
            "phone_wa": "6281234567890" } }
```

`POST /customers` butuh `Idempotency-Key`. Field: `name` (wajib), `phone`
(opsional, unik), `note`. **Boleh dipanggil operator sejak DEC-027** — PRD §6
yang membatasinya ke Admin/Owner sudah di-override.

### `POST /customers/{id}/membership`
`Idempotency-Key` **wajib**. Permission `customer.create` (DEC-027).

```json
{ "session_id": "uuid-atau-null", "tier": "SILVER" }
```

- Biaya pendaftaran **Rp 10.000** (DEC-029, sementara; dari `config/billing.php`).
- `session_id` diisi → biaya masuk Open Tab sesi itu sebagai item `ADJUSTMENT`
  bernama "Biaya daftar member", dan sesi yang masih Walk-in **ditautkan** ke
  customer tersebut. Tanpa penautan itu, sisa waktunya tidak akan masuk ke akun
  siapa pun saat checkout (DEC-024).
- `session_id` kosong → membership dibuat tanpa tagihan apa pun.

→ `201` `{ "customer": { ... }, "fee": 10000 }`
Error: `409 CUSTOMER_ALREADY_MEMBER`, `409 SESSION_STATUS_INVALID`, `404 NOT_FOUND`

---

## 7. Session

### Objek `session` (bentuk tunggal — dipakai di semua response & event)

```json
{
  "id": "uuid",
  "code": "S-20261002-0001",
  "status": "ACTIVE",
  "mode": "PREPAID",

  "station": { "id": "uuid", "code": "ST01", "name": "Station 1" },
  "customer": { "id": "uuid", "name": "Budi" },
  "customer_name": null,

  "package": { "id": "uuid", "name": "1 Jam", "duration_minutes": 60, "price": 20000 },
  "hourly_rate": 20000,

  "started_at": "2026-10-02T07:00:00Z",
  "end_at":     "2026-10-02T08:00:00Z",
  "ended_at":   null,

  "extendable": true,
  "extend_deadline_at": "2026-10-02T08:00:00Z",

  "items": [ /* lihat session_item */ ],

  "totals": {
    "rental": 20000,
    "fnb": 15000,
    "extend": 10000,
    "discount": 0,
    "adjustment": 0,
    "grand_total": 45000,
    "paid": 20000,
    "balance_due": 25000
  },

  "created_at": "2026-10-02T06:59:00Z",
  "updated_at": "2026-10-02T07:30:00Z"
}
```

Catatan penting:

| Field | Aturan |
|---|---|
| `status` | `PENDING_PAYMENT` `ACTIVE` `WARNING` `EXPIRED` `CHECKOUT` `COMPLETED` `CANCELLED` (PRD §11). Tidak ada `AVAILABLE` — itu status station, bukan session |
| `mode` | `PREPAID` \| `POSTPAID` |
| `customer` / `customer_name` | DEC-008: **satu** customer per session. Walk-in non-member → `customer: null` + `customer_name: "Walk-in"` |
| `end_at` | `null` saat `PENDING_PAYMENT`, diisi saat payment confirmed. **Selalu `null` untuk Postpaid** (DEC-034) — artinya tidak ada batas waktu, tidak ada peringatan, dan TV menghitung **maju** dari `started_at` alih-alih mundur |
| `extendable` | **server** yang memutuskan, client tidak menghitung sendiri |
| `extend_deadline_at` | **Sama dengan `end_at`** sejak DEC-033. Grace 10 menit DEC-007 dicabut — "habis ya habis". Field-nya dipertahankan supaya client lama tidak patah |
| `totals.balance_due` | yang ditagih saat checkout |
| **Tidak ada** `remaining_seconds` | sengaja. Client hitung dari `end_at` + server-time offset (PRD §16, DEC-003) |

### Objek `session_item`

```json
{
  "id": "uuid",
  "type": "RENTAL",
  "name": "Paket 1 Jam",
  "qty": 1,
  "unit_price": 20000,
  "subtotal": 20000,
  "is_paid": true,
  "meta": { "duration_minutes": 60 },
  "created_by": { "id": "uuid", "name": "Budi" },
  "created_at": "2026-10-02T07:00:00Z"
}
```

`type` ∈ `RENTAL` | `FNB` | `EXTEND` | `DISCOUNT` | `ADJUSTMENT`
`meta` untuk `EXTEND` → `{ "duration_minutes": 30 }` · untuk `FNB` → `{ "fnb_order_id": "uuid" }`

### `POST /sessions`
`Idempotency-Key` **wajib**.

```json
{
  "station_id": "uuid",
  "package_id": "uuid",
  "mode": "PREPAID",
  "customer_id": "uuid",
  "customer_name": null
}
```

Perilaku:
- `mode: PREPAID` → session dibuat `PENDING_PAYMENT`. `started_at` dan `end_at` **belum** diisi. Timer belum jalan.
- `mode: POSTPAID` → session langsung `ACTIVE`, `started_at = now`, dan **`end_at = null`** (DEC-034 — Postpaid tidak punya batas waktu). Item `RENTAL` **belum dibuat**; barisnya lahir saat checkout dengan angka final, karena harganya baru pasti saat sesi ditutup. Selama sesi berjalan `totals.rental` adalah **tagihan berjalan** dari waktu yang sudah terpakai, minimum satu blok 30 menit.
- Paket pada Postpaid hanya menentukan **tarif per jam**, bukan durasi. Customer tetap memilihnya karena tarif berbeda per tipe konsol (DEC-019).
- `customer_id` dan `customer_name` → kirim **salah satu**. Keduanya kosong → `customer_name` di-default `"Walk-in"`.

→ `201` objek `session`
Error: `409 STATION_NOT_AVAILABLE`, `409 STATION_HAS_ACTIVE_SESSION`, `422 VALIDATION_FAILED`, `403 FORBIDDEN`

### `GET /sessions/{id}` · `GET /sessions?status=ACTIVE,WARNING`

`GET /sessions` mendukung `status` (comma-separated), `station_id`, `page`, `per_page`.
**Dipakai untuk reconcile setelah reconnect** (§12).

### `POST /sessions/{id}/payments`
`Idempotency-Key` **wajib**.

```json
{ "method": "CASH", "amount": 20000, "reference": null, "note": null }
```

- `method` ∈ `CASH` | `QRIS_STATIC`
- `QRIS_STATIC` → `reference` wajib (nomor referensi/bukti yang diverifikasi operator). Manual verification + audit (PRD §21).
- Pada `PENDING_PAYMENT`, pembayaran yang menutupi harga rental → session jadi **ACTIVE**, `started_at = now`, `end_at = now + duration`.
- `amount` > `balance_due` → `422 PAYMENT_AMOUNT_EXCEEDS_BALANCE`. Tidak ada kembalian tercatat di V1.

→ `201`
```json
{ "data": { "payment": { "id": "uuid", "method": "CASH", "amount": 20000,
            "status": "CONFIRMED", "reference": null,
            "confirmed_at": "...Z", "actor": { "id": "uuid", "name": "Budi" } },
            "session": { /* session terbaru */ } } }
```

Error: `409 SESSION_STATUS_INVALID`, `422 PAYMENT_AMOUNT_EXCEEDS_BALANCE`, `422 PAYMENT_REFERENCE_REQUIRED`

### `POST /sessions/{id}/extend`
`Idempotency-Key` **wajib**. Operator-initiated = langsung diterapkan (operator adalah approver, PRD §14).

```json
{ "duration_minutes": 30 }
```

Validasi (DEC-007):

| Aturan | Error kalau gagal |
|---|---|
| kelipatan 30, minimum 30 | `422 EXTEND_DURATION_INVALID` |
| `status` ∈ `ACTIVE`, `WARNING` — **bukan `EXPIRED`** (DEC-033) | `409 SESSION_STATUS_INVALID` |
| `now ≤ end_at` (DEC-033 — tidak ada lagi grace 10 menit) | `409 EXTEND_GRACE_EXPIRED` |

Perhitungan:
```
end_at_baru = end_at_lama + duration_minutes      ← bukan dari waktu approve
harga       = ceil(hourly_rate ÷ 2 × (duration_minutes ÷ 30))
```

→ `200`
```json
{ "data": { "session": { /* end_at sudah baru, item EXTEND sudah ada */ },
            "extend": { "duration_minutes": 30, "price": 10000,
                        "previous_end_at": "...Z", "new_end_at": "...Z" } } }
```

> `extend-requests` (customer mengajukan lewat portal) **belum ada di v1** — masuk Tahap 3C. Jangan diimplementasikan di Flutter sekarang.

### `POST /sessions/{id}/swap`
`Idempotency-Key` **wajib**. Atomic (PRD §15, R07).

```json
{ "target_station_id": "uuid", "reason": "TV bermasalah" }
```

Dijamin: `session_id` **tidak berubah** · `end_at`, items, payments, totals **tidak berubah** · station lama jadi kosong · audit log mencatat asal → tujuan.

→ `200` objek `session` dengan `station` baru.
Error: `409 STATION_NOT_AVAILABLE`, `409 TARGET_STATION_SAME`, `409 STATION_TYPE_MISMATCH`, `409 SESSION_STATUS_INVALID`, `409 STATION_HAS_ACTIVE_SESSION`

`STATION_TYPE_MISMATCH` menegakkan DEC-021: swap hanya dalam tipe konsol yang sama. Pindah tipe konsol adalah **sesi baru** (DEC-025), bukan swap.

### `POST /sessions/{id}/checkout`
`Idempotency-Key` **wajib**. Menghasilkan **satu** final transaction (PRD §12, T13).

```json
{
  "use_credit": false,
  "payments": [ { "method": "CASH", "amount": 25000, "reference": null } ]
}
```

Perilaku:
1. **Postpaid:** rental dihitung dari durasi aktual dengan rounding **DEC-009** (`sisa ≤ 5` → ke bawah, `> 5` → ke atas, per 30 menit, minimum 30). Item `RENTAL` diperbarui.
   **Prepaid:** rental sudah fix dari paket, **tidak** di-rounding — dan sisa waktu yang tidak terpakai **hangus** (DEC-024), kecuali customer member.
1b. ~~Overstay~~ — **dihapus**. DEC-033 mencabut DEC-023: waktu habis berarti TV mati dan sesi berhenti, jadi tidak ada menit di luar hak waktu yang bisa ditagih.
2. Semua item unpaid ditagih. Prepaid → hanya F&B/extend/adjustment yang belum dibayar.
2b. **Saldo member (DEC-026).** `use_credit: true` memakai saldo customer untuk mengurangi `balance_due`, berapa pun jenis tagihannya. Muncul sebagai item `DISCOUNT` bernama "Saldo member". Default `false` → memakai saldo harus keputusan sadar operator di depan customer.
3. `payments` harus **persis** sama dengan `balance_due`. Kurang → `422 CHECKOUT_INSUFFICIENT_PAYMENT`; lebih → `422 PAYMENT_AMOUNT_EXCEEDS_BALANCE` (tidak ada kembalian di V1). Boleh array kosong kalau tagihannya sudah nol.
4. Status → `COMPLETED`, `ended_at = now`, station kembali kosong.
5. **Sisa waktu Prepaid (DEC-024/026).** Member aktif: nilai sisa masuk saldo akunnya, `floor(sisa_menit x hourly_rate / 60)`. Non-member: hangus, tidak ada baris apa pun.

→ `200`
```json
{
  "data": {
    "session": { /* status COMPLETED */ },
    "receipt": {
      "number": "INV-20261002-0001",
      "issued_at": "...Z",
      "billable_duration_minutes": 60,
      "actual_duration_minutes": 63,
      "lines": [ { "name": "Paket 1 Jam", "qty": 1, "subtotal": 20000 } ],
      "totals": { "grand_total": 45000, "paid": 45000, "balance_due": 0 },
      "payments": [ { "method": "CASH", "amount": 25000 } ],
      "operator": { "id": "uuid", "name": "Budi" }
    }
  }
}
```

`actual_duration_minutes` dan `billable_duration_minutes` **wajib keduanya** — supaya operator bisa menjelaskan ke customer kenapa 63 menit ditagih 60.

### `POST /sessions/{id}/cancel`
`Idempotency-Key` **wajib**. Hanya dari `PENDING_PAYMENT` di v1.

```json
{ "reason": "Customer berubah pikiran" }
```

Station langsung kosong setelah dibatalkan dan bisa segera dipakai sesi baru —
operator yang salah memilih station harus bisa langsung mengulang.

Error: `409 SESSION_STATUS_INVALID` (sesi sudah berjalan; selesaikan lewat checkout), `409 SESSION_HAS_PAYMENT`

> Status diperiksa **lebih dulu** daripada pembayaran. Sesi yang sudah berjalan hampir selalu juga sudah dibayar; kalau uangnya yang dilaporkan, operator akan mencari uang itu alih-alih menekan tombol checkout.

> **Waktu habis berarti berhenti (DEC-033).** Begitu `now > end_at`, status jadi `EXPIRED`, **TV mati/standby**, dan customer tidak bisa melanjutkan. Tidak ada penagihan kelebihan waktu. Extend juga tidak lagi diizinkan setelah titik ini — customer yang ingin melanjutkan dibuatkan **sesi baru dengan paket baru**. Station tetap terpakai sampai operator checkout. Bentuk tampilan "TV mati" di layar adalah urusan Tahap 2 (OD-004).

---

## 8. F&B

### `GET /fnb/products`

```json
{ "data": [ { "id": "uuid", "category": "Minuman", "name": "Teh Manis",
              "price": 5000, "is_available": true, "stock": 24 } ] }
```

`stock` boleh `null` kalau produk tidak dilacak stoknya.

### `POST /sessions/{id}/fnb/orders`
`Idempotency-Key` **wajib**.

```json
{ "items": [ { "product_id": "uuid", "qty": 2 } ], "note": "tanpa gula" }
```

- Harga **selalu** dari server. Client tidak boleh mengirim harga (PRD §8).
- Session harus `ACTIVE` atau `WARNING` → kalau tidak: `409 SESSION_NOT_ORDERABLE` (ini yang menutup *ghost order*, T05).
- Order masuk Open Tab session → item `FNB` dengan `is_paid: false`.

→ `201`
```json
{ "data": { "order": { "id": "uuid", "code": "FB-0012", "status": "PENDING",
            "session_id": "uuid", "station_code": "ST01",
            "items": [ { "product_id": "uuid", "name": "Teh Manis", "qty": 2,
                         "unit_price": 5000, "subtotal": 10000 } ],
            "total": 10000, "source": "OPERATOR", "created_at": "...Z" },
            "session": { /* totals terbaru */ } } }
```

`status` ∈ `PENDING` | `PROCESSING` | `READY` | `DELIVERED` | `CANCELLED`
`source` ∈ `OPERATOR` | `CUSTOMER` (customer baru ada di Tahap 3C)

### `GET /fnb/orders?status=PENDING,PROCESSING`
Antrian F&B operator.

### `POST /fnb/orders/{id}/status`

```json
{ "status": "PROCESSING" }
```

Transisi sah: `PENDING → PROCESSING → READY → DELIVERED`. Cancel hanya dari `PENDING` atau `PROCESSING`.
Error: `409 FNB_STATUS_TRANSITION_INVALID`

---

## 9. Device (TV Agent) — kontrak untuk Tahap 2

Memakai `X-Device-Token`, **bukan** `Authorization`. Tidak punya akses apa pun selain tiga endpoint ini (PRD §6).

### `POST /devices/register`
Dipanggil sekali saat provisioning. **Tanpa auth** — TV belum punya token. Yang menjaganya kode pendaftaran milik station, plus rate limit 5/menit/IP: kode enam huruf bisa ditebak kalau boleh dicoba terus-menerus.

Kodenya tinggal di kolom `stations.enrollment_code` dan menentukan TV ini milik station yang mana. `Idempotency-Key` wajib.

```json
{ "enrollment_code": "ABC123", "device_uid": "<android_id>",
  "model": "Xiaomi TV A2", "os_version": "Android 11", "app_version": "0.1.0" }
```
→ `201` `{ "data": { "device_token": "...", "station": { "code": "ST01" } } }`

Token unik per device dan **dapat dicabut** (PRD §10, §24). Yang disimpan server hanya **hash**-nya; token mentah dikembalikan sekali ini saja. TV yang kehilangan token mendaftar ulang, bukan menanyakan yang lama.

Aturan pendaftaran ulang:

| Keadaan | Hasil |
|---|---|
| `device_uid` sama | Token baru diterbitkan, token lama **langsung mati**. Ini kejadian normal saat APK dipasang ulang |
| Station sudah dipegang `device_uid` lain | `409 STATION_HAS_DEVICE`. Tidak diambil alih diam-diam — teknisi yang salah membacakan kode akan mematikan TV yang sedang jalan tanpa ada yang sadar |
| Kode tidak dikenal | `422 ENROLLMENT_CODE_INVALID` |

### `POST /devices/heartbeat`

```json
{ "app_version": "0.1.0", "uptime_seconds": 3600,
  "known_session_id": "uuid", "known_end_at": "...Z" }
```

→ `200`
```json
{ "data": { "acknowledged": true, "state_match": false,
            "state": { /* lihat /devices/me/state */ } } }
```

`state_match: false` → state lokal TV basi, pakai `state` dari response. Ini mekanisme reconciliation murah tanpa WebSocket.

### `GET /devices/me/state`
Endpoint **reconcile** untuk Kotlin setelah reboot atau reconnect (PRD §16, T11/T12).

```json
{
  "data": {
    "station": { "code": "ST01", "name": "Station 1" },
    "session": { "id": "uuid", "status": "ACTIVE",
                 "end_at": "2026-10-02T08:00:00Z",
                 "customer_label": "Budi" },
    "display": { "mode": "TIMER" }
  },
  "meta": { "server_time": "2026-10-02T07:15:00Z" }
}
```

- `session: null` → station kosong, TV tampilkan layar idle.
- `display.mode` ∈ `IDLE` | `TIMER` | `LOCKED`. **`LOCKED` sekarang dipakai** — OD-001 dan OD-004 sudah diputuskan (DEC-033 dan DEC-030):

| Keadaan sesi | mode |
|---|---|
| tidak ada sesi, atau `PENDING_PAYMENT` | `IDLE` |
| `ACTIVE` / `WARNING` | `TIMER` |
| `EXPIRED` / `CHECKOUT` | `LOCKED` |

  `LOCKED` berarti station ini **tidak boleh dimainkan**. Bentuk visualnya — benar-benar padam, standby, atau layar "waktu habis, silakan ke kasir" — masih sisa OD-004 dan urusan Tahap 2. Server hanya menyatakan keadaannya.
- `session.end_at` bisa `null` untuk Postpaid (DEC-034): tidak ada batas waktu, TV menghitung **maju** dari `started_at`.
- Tidak ada `remaining_seconds`. TV hitung dari `end_at` + offset.

### `GET /devices` *(operator/admin, pakai Bearer, permission `device.read`)*
Untuk screen Device di Flutter dan dashboard Admin.

```json
{
  "data": [
    {
      "id": "uuid",
      "device_uid": "a1b2c3d4e5f6",
      "station": { "id": "uuid", "code": "ST01", "name": "Station 1" },
      "status": "ONLINE",
      "last_seen_at": "2026-10-02T07:14:50Z",
      "app_version": "0.1.0",
      "model": "Xiaomi TV A2",
      "os_version": "Android 11",
      "registered_at": "2026-09-28T03:00:00Z"
    }
  ],
  "meta": {
    "server_time": "2026-10-02T07:15:00Z",
    "offline_threshold_seconds": 120
  }
}
```

- `station` = `null` kalau device belum dipetakan ke station, atau pemetaannya dicabut (PRD §10 memperbolehkan perubahan mapping).
- `status` ∈ `ONLINE` | `OFFLINE`. **Server** yang memutuskan, berdasarkan `last_seen_at` dan `offline_threshold_seconds`.
- `meta.offline_threshold_seconds` dikirim supaya client bisa menjelaskan *kenapa* sebuah device dianggap offline, tanpa menduplikasi aturannya.
- `device_uid`, `model`, `os_version` berasal dari `POST /devices/register`.

Endpoint ini **read-only** untuk operator. Pendaftaran, pemetaan ulang, dan pencabutan token device adalah wewenang Admin (PRD §19) dan masuk Tahap 3B.

---

## 10. Shift

### `POST /shifts/open` · `POST /shifts/{id}/close` · `GET /shifts/current`
`Idempotency-Key` wajib untuk open & close.

```json
{ "data": { "id": "uuid", "operator": { "id": "uuid", "name": "Budi" },
            "opened_at": "...Z", "closed_at": null,
            "opening_cash": 200000,
            "summary": { "rental": 0, "fnb": 0, "cash": 0, "qris": 0, "total": 0 } } }
```

Close butuh `closing_cash` + `note` opsional; selisih dicatat untuk audit.

Dasar angka di `summary`:

| Field | Dihitung dari |
|---|---|
| `cash`, `qris`, `total` | **uang yang benar-benar masuk** — payment ber-`shift_id` sama |
| `rental`, `fnb` | **nilai transaksi** — item pada sesi milik shift ini, dibayar maupun belum |

`rental` sudah termasuk `EXTEND`: keduanya penjualan waktu bermain.

> Dasar untuk `rental`/`fnb` masih **OD-013** dan belum final. Pilihan sekarang
> menjawab "berapa yang terjual saat saya jaga"; uang masuknya sudah terwakili
> `cash`/`qris`.

Aturan lain:
- Satu operator tidak boleh punya dua shift terbuka → `409 SHIFT_ALREADY_OPEN`.
- Shift yang sudah ditutup → `409 SHIFT_NOT_OPEN`.
- Shift orang lain hanya boleh ditutup **admin ke atas** → `403 FORBIDDEN`.
  Operator yang lupa menutup shift harus bisa dibereskan tanpa menunggu dia kembali.
- **Selisih kas tidak menghalangi penutupan.** Shift yang tidak bisa ditutup
  karena selisih akan membuat operator mengarang angka supaya bisa pulang;
  yang dibutuhkan adalah selisihnya tercatat, bukan dipaksa nol.
- `GET /shifts/current` mengembalikan `data: null` kalau belum ada shift —
  itu keadaan normal di awal hari, bukan `404`.

---

## 11. Daftar error code

| Code | HTTP | Arti |
|---|---|---|
| `VALIDATION_FAILED` | 422 | body tidak valid; cek `details` |
| `INVALID_CREDENTIALS` | 401 | username/password salah |
| `UNAUTHENTICATED` | 401 | token hilang/kedaluwarsa |
| `USER_INACTIVE` | 403 | akun dinonaktifkan |
| `FORBIDDEN` | 403 | role/permission tidak cukup |
| `TOO_MANY_ATTEMPTS` | 429 | rate limit |
| `NOT_FOUND` | 404 | resource tidak ada |
| `IDEMPOTENCY_KEY_REQUIRED` | 400 | header tidak dikirim |
| `IDEMPOTENCY_KEY_REUSED` | 409 | key sama, body beda |
| `STATION_NOT_AVAILABLE` | 409 | station maintenance/disabled |
| `STATION_HAS_ACTIVE_SESSION` | 409 | sudah ada session jalan |
| `TARGET_STATION_SAME` | 409 | swap ke station yang sama |
| `STATION_TYPE_MISMATCH` | 409 | swap ke tipe konsol berbeda (DEC-021) |
| `SHIFT_ALREADY_OPEN` | 409 | operator masih punya shift terbuka |
| `SHIFT_NOT_OPEN` | 409 | shift sudah ditutup |
| `CUSTOMER_ALREADY_MEMBER` | 409 | customer sudah punya membership |
| `SESSION_HAS_PAYMENT` | 409 | sesi sudah menerima uang, tidak bisa dibatalkan (V1 tanpa refund) |
| `ENROLLMENT_CODE_INVALID` | 422 | kode pendaftaran TV tidak dikenal |
| `STATION_HAS_DEVICE` | 409 | station sudah dipegang TV lain |
| `SESSION_STATUS_INVALID` | 409 | aksi tidak sah pada status ini |
| `SESSION_NOT_ORDERABLE` | 409 | order F&B ke session tidak aktif (ghost order) |
| `EXTEND_DURATION_INVALID` | 422 | bukan kelipatan 30 menit |
| `EXTEND_GRACE_EXPIRED` | 409 | lewat 10 menit dari `end_at` |
| `PAYMENT_AMOUNT_EXCEEDS_BALANCE` | 422 | nominal melebihi tagihan |
| `PAYMENT_REFERENCE_REQUIRED` | 422 | QRIS tanpa nomor referensi |
| `CHECKOUT_INSUFFICIENT_PAYMENT` | 422 | pembayaran belum menutupi `balance_due` |
| `FNB_STATUS_TRANSITION_INVALID` | 409 | transisi status order tidak sah |
| `DEVICE_TOKEN_INVALID` | 401 | token device dicabut/salah |
| `DEVICE_NOT_ASSIGNED` | 409 | device belum dipetakan ke station |

Client **wajib** menangani minimal: `VALIDATION_FAILED`, `UNAUTHENTICATED`, `FORBIDDEN`, semua `409`, dan kegagalan jaringan.

---

## 12. Protokol reconnect & reconcile

Berlaku untuk Flutter dan Kotlin. **Jangan** memutar ulang event yang terlewat — selalu ambil ulang state.

```
1. Koneksi hilang           → UI tampilkan status "reconnecting"; timer lokal TETAP jalan
2. Koneksi kembali          → GET /auth/me (Flutter) atau GET /devices/me/state (Kotlin)
3. Ambil state terbaru      → Flutter: GET /sessions?status=ACTIVE,WARNING,EXPIRED,PENDING_PAYMENT
                               Kotlin : GET /devices/me/state
4. Perbarui server-time offset dari header X-Server-Time
5. Subscribe ulang channel  → lihat REALTIME.md
6. Ganti state lokal dengan state server — server selalu menang
```

Aksi yang gagal karena jaringan di-retry **dengan `Idempotency-Key` yang sama**, bukan key baru.

---

## 13. Rate limit (indikatif, difinalkan saat implementasi)

| Endpoint | Limit |
|---|---|
| `POST /auth/login` | 5 / menit / IP |
| `POST /devices/heartbeat` | 2 / menit / device |
| `POST /sessions/{id}/fnb/orders` | 10 / menit / session |
| Endpoint lain (auth) | 120 / menit / user |

---

## 14. Cakupan per tahap

| Endpoint | Tahap |
|---|---|
| `/health`, `/auth/*`, `/stations`, `/packages`, `/customers` | 0 |
| `/sessions` (create, get, list, payments, extend, swap, checkout, cancel) | 0 |
| `/fnb/products`, `/fnb/orders`, `/sessions/{id}/fnb/orders` | 0 |
| `/shifts/*` | 0 (SHOULD) |
| `/devices/*` | 0 (kontrak) → dipakai di 2 |
| `extend-requests`, customer portal token, `/bookings`, webhook gateway, reporting | 3 |

---

## 15. Yang sengaja TIDAK ada di v1

| Tidak ada | Alasan |
|---|---|
| `remaining_seconds` / countdown per detik | PRD §16 — client menghitung dari `end_at` |
| `display.mode: LOCKED` aktif | Bentuk tampilannya masih OD-004. Aturannya sudah jelas: TV mati/standby saat waktu habis (DEC-033) |
| Penagihan overstay | **Tidak akan ada.** DEC-033 menutup kemungkinannya — waktu habis berarti berhenti |
| `extend-requests` dari customer | Tahap 3C |
| Booking, payment gateway, webhook | Tahap 3D |
| Endpoint reporting/finance | Tahap 3B |
| Kembalian / uang muka | belum ada keputusan bisnis (OD-002 untuk uang muka Postpaid) |
| Beberapa customer per session | DEC-008 — ditolak untuk V1 |
