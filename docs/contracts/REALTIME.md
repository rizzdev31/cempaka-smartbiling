# REALTIME CONTRACT — Cempaka Smart Billing

**Versi:** `v1` · **Status:** DRAFT 1 (2 Okt 2026) · **Owner:** Backend + Clients (PRD §34)
**Driver:** Laravel Reverb (protokol Pusher)

> Perubahan event/payload/channel wajib dicatat di `CHANGELOG.md` **dan** disetujui client terkait (PRD §34).

---

## 1. Prinsip yang mengikat

1. **Server tidak pernah mengirim countdown per detik.** Server mengirim `end_at`; client menghitung sendiri (PRD §8, §16).
2. **Realtime adalah optimasi, bukan sumber kebenaran.** Kalau WebSocket mati, client tetap berfungsi dari HTTP + timer lokal. Jangan ada fitur yang hanya bisa jalan lewat WebSocket.
3. **Event tidak diputar ulang.** Setelah reconnect, client **ambil ulang state** via HTTP (`API.md` §12), bukan meminta event yang terlewat.
4. **Setiap payload membawa `server_time`.** Dipakai untuk menyegarkan offset (DEC-003).
5. **Payload membawa snapshot, bukan delta.** `session.updated` mengirim objek `session` utuh supaya client tidak perlu merekonstruksi state.

---

## 2. Koneksi

| Parameter | Dev (lokal) | Prod (Tahap 3A) |
|---|---|---|
| Host | IP laptop, mis. `192.168.0.50` | domain |
| Port | `8080` | `443` |
| Scheme | `ws` | `wss` |
| `forceTLS` | `false` | `true` |
| Transport | `ws` saja | `wss` |

Semua nilai dari konfigurasi, **tidak hardcode** (DEC-002).

### Autorisasi channel

Endpoint: `POST /broadcasting/auth`

| Client | Kredensial |
|---|---|
| Flutter Operator | `Authorization: Bearer <token>` |
| Kotlin TV Agent | `X-Device-Token: <device_token>` |
| Customer Portal (Tahap 3C) | session token scoped |

Semua channel **private**. Tidak ada channel publik.

---

## 3. Channel

| Channel | Subscriber | Isi |
|---|---|---|
| `private-operator` | Flutter Operator, Admin Web | semua event operasional lokasi |
| `private-station.{station_code}` | Kotlin TV Agent station itu | hanya event station tersebut |
| `private-session.{session_id}` | Customer Portal *(Tahap 3C)* | hanya event session tersebut |

Aturan:
- TV Agent **hanya boleh** subscribe channel station yang dipetakan ke device-nya. Otorisasi dicek server (PRD §24).
- `private-session.{id}` tidak dipakai di v1 — dicatat agar nama tidak berubah nanti.
- Nama channel pakai `station_code` (`ST01`), bukan UUID, supaya mudah di-debug di lapangan.

---

## 4. Daftar event

Delapan event sesuai PRD §23. Nama memakai titik.

| Event | Producer | `private-operator` | `private-station.*` |
|---|---|---|---|
| `session.started` | Laravel | ✔ | ✔ |
| `session.updated` | Laravel | ✔ | ✔ |
| `session.extended` | Laravel | ✔ | ✔ |
| `session.swapped` | Laravel | ✔ | ✔ (station lama **dan** baru) |
| `session.expired` | Laravel (scheduler) | ✔ | ✔ |
| `payment.confirmed` | Laravel | ✔ | ✘ |
| `fnb.order.created` | Laravel | ✔ | ✘ |
| `device.heartbeat` | Laravel *(lihat §6)* | ✔ | ✘ |

**Tidak ada event warning.** Warning 10/5/1 menit dihitung client dari `end_at` (PRD §16). Mengirimnya lewat WebSocket akan membuat warning hilang saat koneksi putus — justru skenario yang harus selamat.

---

## 5. Payload

Semua payload memiliki `server_time`. `session` = objek `session` penuh dari `API.md` §7.

### `session.started`
```json
{ "session": { /* objek session */ },
  "server_time": "2026-10-02T07:00:00Z" }
```
Dipicu saat session jadi `ACTIVE` — Postpaid saat dibuat, Prepaid saat payment confirmed.

### `session.updated`
```json
{ "session": { /* objek session */ },
  "changed": ["status", "totals"],
  "server_time": "2026-10-02T07:30:00Z" }
```
`changed` adalah **petunjuk** untuk animasi/highlight UI. Client tidak boleh bergantung padanya — selalu pakai `session`.

Dipicu oleh: perubahan status, item baru (F&B/adjustment), perubahan `totals`, checkout masuk, cancel.

### `session.extended`
```json
{ "session": { /* end_at sudah baru */ },
  "extend": { "duration_minutes": 30, "price": 10000,
              "previous_end_at": "2026-10-02T08:00:00Z",
              "new_end_at": "2026-10-02T08:30:00Z" },
  "actor": { "id": "uuid", "name": "Budi" },
  "server_time": "2026-10-02T07:55:00Z" }
```
`previous_end_at` disertakan supaya TV bisa memastikan `end_at` yang dipegangnya memang yang diperpanjang — kalau tidak cocok, TV panggil `GET /devices/me/state`.

### `session.swapped`
```json
{ "session": { /* station sudah yang baru */ },
  "from_station_code": "ST01",
  "to_station_code": "ST02",
  "actor": { "id": "uuid", "name": "Budi" },
  "server_time": "2026-10-02T07:40:00Z" }
```
Dikirim ke **dua** channel station. TV lama → idle. TV baru → timer, `end_at` **tidak berubah** (PRD §15, R07).

### `session.expired`
```json
{ "session_id": "uuid", "station_code": "ST01",
  "status": "EXPIRED", "end_at": "2026-10-02T08:00:00Z",
  "server_time": "2026-10-02T08:00:03Z" }
```
Payload ringan — client sudah tahu session-nya. Dihasilkan **scheduler**, jadi bisa terlambat beberapa detik; client **tidak boleh** menunggu event ini untuk menampilkan habis. Timer lokal yang menentukan tampilan; event ini hanya menyinkronkan status server.

### `payment.confirmed`
```json
{ "session_id": "uuid", "station_code": "ST01",
  "payment": { "id": "uuid", "method": "CASH", "amount": 20000, "confirmed_at": "...Z" },
  "totals": { "grand_total": 45000, "paid": 45000, "balance_due": 0 },
  "actor": { "id": "uuid", "name": "Budi" },
  "server_time": "2026-10-02T07:01:00Z" }
```
Tidak dikirim ke TV — customer tidak perlu melihat nominal di layar TV.

### `fnb.order.created`
```json
{ "order": { "id": "uuid", "code": "FB-0012", "status": "PENDING",
             "session_id": "uuid", "station_code": "ST01",
             "items": [ { "name": "Teh Manis", "qty": 2 } ],
             "total": 10000, "source": "OPERATOR" },
  "server_time": "2026-10-02T07:20:00Z" }
```
Memicu badge di F&B Queue Flutter.

### `device.heartbeat`
```json
{ "device": { "id": "uuid", "station_code": "ST01", "status": "ONLINE",
              "last_seen_at": "...Z", "app_version": "0.1.0" },
  "server_time": "2026-10-02T07:14:50Z" }
```
Untuk screen Device di Flutter dan dashboard Admin.

---

## 6. Catatan: `device.heartbeat` punya dua arti

PRD §23 mencantumkan producer `device.heartbeat` = **Kotlin**, consumer = Laravel/Admin. Dalam implementasi keduanya terpisah:

```
Kotlin  ──HTTP POST /devices/heartbeat──►  Laravel
Laravel ──broadcast "device.heartbeat"──►  private-operator
```

Kotlin **tidak** mem-broadcast ke WebSocket (client tidak boleh jadi producer event — PRD §8: TV Agent bukan source of truth). Makna semantiknya tetap sama seperti PRD, hanya transportnya dipisah.

**Status:** klarifikasi teknis, bukan perubahan requirement. Kalau tim menilai ini perubahan kontrak, perlu entry di Decision Log.

---

## 7. Throttle broadcast

| Event | Batas |
|---|---|
| `device.heartbeat` | maksimum 1 broadcast / device / 30 detik |
| `session.updated` | digabung (debounce) 500 ms per session |
| lain-lain | tanpa throttle |

Alasan: 6 device × heartbeat tiap 30 detik + `session.updated` tiap item F&B bisa membuat dashboard Flutter rebuild berlebihan. Debounce dilakukan **di server**, bukan di client.

---

## 8. Kewajiban client

### Flutter Operator
- Subscribe `private-operator` setelah login.
- Tampilkan status koneksi (`connected` / `reconnecting` / `offline`) di header — operator harus tahu kalau data basi.
- Reconnect dengan **exponential backoff** (1s, 2s, 4s, 8s, maks 30s) + jitter.
- Setelah reconnect: reconcile via HTTP (`API.md` §12) sebelum mempercayai event baru.
- Timer **tidak** bergantung pada WebSocket.

### Kotlin TV Agent
- Subscribe `private-station.{code}` setelah register.
- Simpan `end_at` + `session_id` secara persisten (PRD §16).
- WebSocket putus → timer **tetap jalan**, indikator kecil di pojok, jangan menutupi timer.
- Reconnect/boot → `GET /devices/me/state` dulu, baru subscribe.
- Abaikan event untuk `session_id` yang bukan miliknya.
- `session.extended` dengan `previous_end_at` yang tidak cocok → jangan tebak, panggil `/devices/me/state`.

---

## 9. Usulan tambahan — BELUM disetujui

Tidak ada di PRD §23. **Jangan diimplementasikan** sebelum masuk Decision Log.

| Event | Kegunaan | Kenapa diusulkan |
|---|---|---|
| `fnb.order.updated` | status order berubah (`PROCESSING`/`READY`/`DELIVERED`) | PRD §13 mewajibkan customer melihat status order, dan antrian F&B di tablet kedua tidak akan sinkron tanpa ini. Kemungkinan besar dibutuhkan di Tahap 3C |
| `device.offline` | device melewati batas heartbeat | tanpa ini, status offline hanya terlihat saat operator me-refresh |
| `shift.closed` | shift ditutup | relevan kalau ada lebih dari satu tablet operator |

Sampai disetujui, ketiganya ditangani dengan **polling HTTP** di Flutter.

---

## 10. Cakupan per tahap

| Bagian | Tahap |
|---|---|
| `private-operator` + 7 event (tanpa `device.heartbeat`) | 0 → dipakai di 1 |
| `private-station.*` + `device.heartbeat` | 0 (kontrak) → dipakai di 2 |
| `private-session.*` | 3C |
