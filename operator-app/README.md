# operator-app — Flutter

Aplikasi operator untuk tablet Android. **Tahap 1.**

**Status:** fondasi + Dashboard + Session Detail berjalan di atas **fake repository** (DEC-012). Belum menyentuh API sama sekali.

## Sebelum menulis kode, baca
1. [`../CLAUDE.md`](../CLAUDE.md) — aturan kerja
2. [`../docs/UI-UX-SPEC.md`](../docs/UI-UX-SPEC.md) — **wajib sebelum menyentuh UI**
3. [`../docs/contracts/API.md`](../docs/contracts/API.md) — bentuk model & error code
4. [`../docs/contracts/REALTIME.md`](../docs/contracts/REALTIME.md) §8 — kewajiban client Flutter
5. [`../docs/PRD-V2.md`](../docs/PRD-V2.md) §18 — daftar screen

## Menjalankan

Di tablet/emulator Android:

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.0.50:8000 --dart-define=WS_HOST=192.168.0.50 --dart-define=WS_PORT=8080
```

Di Chrome untuk melihat UI dengan cepat:

```bash
flutter run -d chrome --dart-define=API_BASE_URL=http://192.168.0.50:8000
```

> Di web, **pemindaian TV tidak tersedia** — browser tidak mengizinkan aplikasi
> membaca IP lokalnya. Alamat TV dimasukkan manual; alamatnya tampil di layar TV.

Ganti IP sesuai laptop server. Nilainya masih bisa diubah dari dalam app lewat **Pengaturan**, tanpa rebuild.

Build APK debug untuk dibawa ke lokasi:

```bash
flutter build apk --debug --dart-define=API_BASE_URL=http://192.168.0.50:8000 --dart-define=WS_HOST=192.168.0.50 --dart-define=WS_PORT=8080
```

Test & analisis:

```bash
flutter test && flutter analyze
```

## Struktur

```
lib/
├── main.dart                  entry — orientasi + load config
├── app.dart                   provider + tema + route
├── core/
│   ├── config/api_config.dart  base URL (dart-define + override settings)
│   ├── theme/                  tokens, tema gelap, gaya status
│   ├── time/                   server-time offset, ticker global
│   └── util/format.dart        rupiah, countdown, durasi
├── domain/
│   ├── models/                 transkrip dari contracts/API.md
│   ├── errors/api_error.dart   22 error code dari kontrak
│   └── repositories/           interface BillingRepository
├── data/fake/                  implementasi in-memory (DEC-012)
└── ui/
    ├── widgets/                StationCard, CountdownText, MoneyText,
    │                           StatusChip, ConnectionBanner, ConfirmDialog
    ├── dashboard/              grid 6 station + sheet mulai sesi
    ├── session/                detail sesi + semua dialog aksi
    └── settings/               alamat server + diagnostik
```

## Aturan yang tidak boleh dilanggar

- **Tidak ada business rule di Flutter.** Harga, kelayakan extend, rounding — semua dari server.
  - `session.extendable` dan `session.extend_deadline_at` **dipakai apa adanya**, jangan dihitung ulang.
  - Angka estimasi harga extend di UI ditandai "perkiraan"; harga final selalu dari response.
- Base URL **tidak hardcode**: `--dart-define` + settings screen.
- `Idempotency-Key` = UUID v4 **per niat aksi**, dibuat sekali saat dialog dibuka dan dipakai ulang saat retry. Bukan key baru setiap percobaan.
- Sisa waktu dari **server-time offset** (`X-Server-Time`), bukan `DateTime.now()` mentah.
- **Satu ticker global** (`AppTicker`). Jangan pernah membuat `Timer` di widget lain.
- Tombol async **disable + spinner** (`AsyncButton`) — pertahanan pertama terhadap double payment.
- **Tanpa offline write queue** (DEC-004). Hanya read cache + retry.
- Status station = warna **+ ikon + teks** (`StatusChip`). Jangan warna saja.
- Tidak ada hex mentah di widget — lewat `AppColors`.

## Flavor & cleartext HTTP

Tidak memakai flavor gradle. Perbedaan dev/prod ditegakkan Android lewat source set:

| Build | Manifest | Cleartext HTTP |
|---|---|---|
| debug | `src/main` + `src/debug` | **diizinkan** |
| release | `src/main` saja | **dilarang** |

`src/debug/AndroidManifest.xml` berisi `usesCleartextTraffic="true"` dan hanya digabung pada build debug. `src/main` tidak punya atribut itu, jadi release otomatis HTTPS-only — DEC-002 nomor 3.

Sudah diverifikasi pada manifest hasil build debug. Build release belum pernah dibuat (butuh signing config, Tahap 3A).

## Fake repository — batas yang perlu diingat

`FakeBillingRepository` mencerminkan aturan server **apa adanya**: delay jaringan, error code, cache idempotency, DEC-007, DEC-009. Tujuannya supaya UI tidak perlu diubah saat API asli masuk.

**Yang tidak bisa dibuktikan fake:** apakah kontraknya lengkap. Hanya Laravel asli yang mengungkap field yang kurang. Karena itu DEC-012 syarat 2 mewajibkan penggantian pada vertical slice pertama.

Saat Laravel siap, yang berubah **satu baris** di `app.dart`:

```dart
Provider<BillingRepository>(create: (_) => FakeBillingRepository()),
// menjadi
Provider<BillingRepository>(create: (_) => ApiBillingRepository(ApiConfig.instance)),
```

Lalu `test/billing_rules_test.dart` dijalankan terhadap API asli untuk membuktikan server dan client sepakat.

## Yang belum ada

| Belum ada | Tahap |
|---|---|
| Login / auth | 0 → 1 |
| Klien WebSocket Reverb (`ConnectionStatus` masih statis) | 0 → 1 |
| Retry otomatis saat TV tidak merespons (sekarang manual "Kirim ulang") | 1 |
| F&B Queue (layar antrian terpisah) | 1 |
| Shift start/close/handover | 1 |
| Device status (layar terpisah) | 1 |
| Booking list/verify/check-in | 3 |
| Font Fira Sans/Code dibundel (lihat `app_theme.dart`) | 1 |

## Kontrol TV (sementara — DEC-015)

Operator mengirim perintah **langsung ke TV** lewat jaringan lokal, tanpa
backend. Di Tahap 2 ini diganti event dari Laravel lewat Reverb.

```
Station berubah ──► TvSyncService ──► TvAgentClient ──► HTTP ──► agen TV
                         │
                    sidik keadaan
              (session_id | mode | end_at)
```

- **Hanya perubahan yang dikirim.** `end_at` masuk sidik karena itu yang membuat
  extend terkirim; tagihan sengaja tidak masuk karena TV tidak menampilkannya.
- **Penemuan** lewat pemindaian subnet, **entri manual selalu tersedia**.
- **Satu station satu TV** (PRD §10); memasangkan perangkat yang sama ke station
  lain melepas pasangan lamanya.
- Identitas TV adalah `device_uid`, bukan IP — DHCP bisa memberi alamat lain.

Layar **Status TV** menampilkan apa yang *seharusnya* tampil di TV saat ini,
supaya operator bisa membandingkan tanpa berdiri dan melihat layarnya.

Lihat `../tv-agent/README.md` untuk sisi TV-nya.

## Test

`test/billing_rules_test.dart` — 32 test, memetakan ke acceptance test PRD:

| Test | Acceptance |
|---|---|
| Rounding durasi 14 kasus | T17 (DEC-009) |
| Harga extend + grace 10 menit | T15, T16 (DEC-007) |
| Idempotency sesi & pembayaran | T14 |
| Station swap mempertahankan `session_id` & `end_at` | T08 |
| Checkout prepaid hanya menagih item unpaid | T13 |
| Ghost order ditolak | T05 |
| QRIS wajib nomor referensi | audit PRD §21 |
