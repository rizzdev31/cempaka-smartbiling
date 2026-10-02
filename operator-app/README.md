# operator-app — Flutter

Aplikasi operator untuk tablet Android. **Tahap 1.**

**Status:** belum diinisialisasi.

## Sebelum menulis kode, baca
1. [`../CLAUDE.md`](../CLAUDE.md) — aturan kerja
2. [`../docs/UI-UX-SPEC.md`](../docs/UI-UX-SPEC.md) — **wajib sebelum menyentuh UI**
3. [`../docs/contracts/API.md`](../docs/contracts/API.md) — bentuk model & error code
4. [`../docs/contracts/REALTIME.md`](../docs/contracts/REALTIME.md) §8 — kewajiban client Flutter
5. [`../docs/PRD-V2.md`](../docs/PRD-V2.md) §18 — daftar screen

## Aturan yang tidak boleh dilanggar
- **Tidak ada business rule di Flutter.** Harga, kelayakan extend, rounding — semua dari server.
- Base URL **tidak hardcode**: `--dart-define=API_BASE_URL=...` + settings screen untuk ganti IP tanpa rebuild.
- `Idempotency-Key` (UUID v4 baru per niat aksi, sama saat retry) pada semua aksi create.
- Sisa waktu dihitung dengan **server-time offset** dari header `X-Server-Time`, bukan `DateTime.now()` mentah.
- **Satu ticker global** untuk timer. Bukan satu `Timer` per `StationCard`.
- Tombol async **disable + spinner** selama request — pertahanan pertama terhadap double payment.
- **Tanpa offline write queue** (DEC-004). Hanya read cache + retry.
- Cleartext HTTP hanya di flavor `dev`. Flavor `prod` HTTPS-only.

## Flavor

| Flavor | Transport | Keterangan |
|---|---|---|
| `dev` | `http` diizinkan | `usesCleartextTraffic="true"`, banner IP server + status WS terlihat |
| `prod` | `https` saja | cleartext dimatikan, banner disembunyikan |

## Yang bisa dikerjakan sekarang (belum butuh API)
Scaffold + flavor · tema dark + tokens · `StationCard` · `CountdownText` · `MoneyText` · `StatusChip` · `ConnectionBanner` · `ConfirmDialog` · ticker global · `ApiConfig` + settings screen · layout dashboard & session detail dengan data dummy

## Yang menunggu backend
Login · start session · payment · F&B · extend · swap · checkout
