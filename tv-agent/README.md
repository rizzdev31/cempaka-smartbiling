# tv-agent — Kotlin Android TV

Agent pada TV tiap station. **Tahap 2.**

**Status:** belum diinisialisasi. **Jangan mulai sebelum OD-004 & OD-005 terjawab.**

## Blocker
| ID | Pertanyaan | Kapan terjawab |
|---|---|---|
| **OD-005** | Model/versi TV, bisa sideload APK?, ADB over network?, sudah ada akun Google? | **testing Sabtu** — V1–V10 di `../docs/TEST-PLAN-SABTU.md` |
| **OD-004** | Warning 10/5/1 menit: bunyi? teks? overlay penuh atau pojok? | keputusan bisnis |
| **OD-001** | Perilaku saat EXPIRED — lock? auto-off? operator manual? | keputusan bisnis |

OD-005 menentukan apakah tahap ini layak sama sekali (R01). Kalau TV tidak bisa dipasangi APK, desain berubah total.

## Sebelum menulis kode, baca
1. [`../CLAUDE.md`](../CLAUDE.md)
2. [`../docs/PRD-V2.md`](../docs/PRD-V2.md) §16 — timer & TV agent
3. [`../docs/contracts/API.md`](../docs/contracts/API.md) §9 — endpoint device
4. [`../docs/contracts/REALTIME.md`](../docs/contracts/REALTIME.md) §8 — kewajiban client Kotlin
5. [`../docs/UI-UX-SPEC.md`](../docs/UI-UX-SPEC.md) §6 — 10-foot UI

## Aturan yang tidak boleh dilanggar
- **Bukan source of truth.** Tidak pernah menentukan harga, status, atau `end_at` sendiri.
- Auth pakai `X-Device-Token`, bukan Bearer. Token unik per device dan dapat dicabut.
- Simpan `end_at` + `session_id` secara persisten. WebSocket putus → **timer tetap jalan**.
- Reconnect/boot → `GET /devices/me/state` dulu, baru subscribe channel.
- Server-time offset dari header `X-Server-Time` — jam TV sering belum sync NTP setelah boot.
- Warning dihitung **lokal** dari `end_at`. Tidak ada event warning dari server.
- Hanya subscribe `private-station.{code}` milik device sendiri.
- **Jangan klaim** dukungan Device Owner / Lock Task / overlay / HDMI switching sebelum diuji di TV aktual (PRD §5, R01).

## Yang perlu dipastikan di TV aktual
Foreground service agar tidak di-kill (R02) · perilaku WiFi saat standby (N6/V9) · auto-start saat boot · safe area overscan 5%
