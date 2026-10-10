# Cempaka Smart Billing

> **Baru `git pull`?** Baca [`SYNC.md`](SYNC.md) — satu halaman berisi apa yang
> berubah dan apa yang perlu Anda kerjakan.

Sistem billing & operasional rental PlayStation — 6 station, operator tablet, Android TV agent, admin web.

**Baseline dokumen aktif:** `docs/PRD-V2.md` + `docs/DECISION-LOG.md`

---

## Struktur repo

Monorepo — satu git, folder terpisah per app (DEC-010). Tiap folder berdiri sendiri.

```
├── docs/                 dokumentasi & kontrak
├── backend/              Laravel: API + Admin Web + Customer Portal
├── operator-app/         Flutter (tablet operator)
└── tv-agent/             Kotlin (Android TV)
```

## Dokumentasi

| File | Isi |
|---|---|
| [CLAUDE.md](CLAUDE.md) | Kontrak kerja agent — **dibaca setiap sesi** |
| [docs/PRD-V2.md](docs/PRD-V2.md) | PRD V2 lengkap (WHAT/WHY) |
| [docs/DECISION-LOG.md](docs/DECISION-LOG.md) | Keputusan tim — **menang atas PRD** kalau bentrok |
| [docs/ROADMAP.md](docs/ROADMAP.md) | Urutan tahap implementasi |
| [docs/PROGRESS.md](docs/PROGRESS.md) | Log harian + status + hasil testing |
| [docs/TEST-PLAN-SABTU.md](docs/TEST-PLAN-SABTU.md) | Persiapan & skenario testing lokal |
| [docs/UI-UX-SPEC.md](docs/UI-UX-SPEC.md) | Design system Flutter & TV |
| [docs/contracts/API.md](docs/contracts/API.md) | **Kontrak API** — mengikat semua client |
| [docs/contracts/REALTIME.md](docs/contracts/REALTIME.md) | **Kontrak realtime** — channel & event |
| [docs/contracts/CHANGELOG.md](docs/contracts/CHANGELOG.md) | Riwayat perubahan kontrak |

Sumber asli: `PRD Cempaka Smart Biling.docx`

## Stack

| Layer | Teknologi |
|---|---|
| Backend | Laravel + MySQL |
| Realtime | Laravel Reverb / WebSocket |
| Operator | Flutter (tablet Android) |
| TV Agent | Kotlin (Android TV) |
| Admin / Superadmin | Laravel Web |
| Customer Portal | Web via HTTPS |
| Router lokasi | TP-Link Archer C64 |

## Urutan tahap

```
TAHAP 0  Laravel API Core (lokal)      ← prasyarat
TAHAP 1  Flutter Operator              ← sedang dikerjakan
TAHAP 2  Kotlin Android TV Agent
TAHAP 3  Laravel Superadmin + VPS + Customer Portal + Payment Gateway
```

Detail & alasan: [docs/ROADMAP.md](docs/ROADMAP.md) dan DEC-001.

## Aturan yang tidak boleh dilanggar

1. **Laravel adalah source of truth.** Flutter/Kotlin tidak pernah akses MySQL langsung, tidak pernah membuat business rule.
2. **Harga selalu dihitung server.** Client tidak mengirim harga.
3. **Server tidak mengirim countdown per detik.** Server kirim `start_at`/`end_at`; client menghitung sendiri dengan server-time offset.
4. **Setiap POST yang membuat data pakai `Idempotency-Key`.**
5. **Setiap perubahan DB lewat migration.**
6. **Uang = integer rupiah.** Timestamp = UTC di DB, `Asia/Jakarta` di UI.
7. **Station Swap atomic**, `session_id` tidak berubah.
8. **Jangan commit `.env`** atau kredensial apa pun.
