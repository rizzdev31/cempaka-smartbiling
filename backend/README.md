# backend — Laravel

API + Admin Web + Customer Portal. **Satu aplikasi Laravel**, bukan tiga proyek.

**Status:** belum diinisialisasi (Tahap 0).

## Sebelum menulis kode, baca
1. [`../CLAUDE.md`](../CLAUDE.md) — aturan kerja
2. [`../docs/contracts/API.md`](../docs/contracts/API.md) — kontrak yang wajib diikuti
3. [`../docs/contracts/REALTIME.md`](../docs/contracts/REALTIME.md)
4. [`../docs/DECISION-LOG.md`](../docs/DECISION-LOG.md) — DEC-005/007/008/009 mengatur billing

## Stack
Laravel · MySQL · Laravel Reverb · Sanctum

## Aturan yang tidak boleh dilanggar
- Setiap perubahan DB lewat **migration**. Tidak ada SQL manual.
- Harga **selalu** dihitung server. Client tidak pernah mengirim harga.
- Uang = integer rupiah. Timestamp = UTC di DB, ISO-8601 UTC di API.
- Header `X-Server-Time` di **semua** response, termasuk error.
- `Idempotency-Key` ditegakkan middleware pada 9 endpoint di `API.md` §3.
- Station Swap atomic dalam DB transaction.
- `audit_logs` terisi untuk login, payment, extend, swap, discount, adjustment, perubahan master data.

## Menjalankan lokal (dev)

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

```bash
php artisan schedule:work
```

`--host=0.0.0.0` **wajib** — default `127.0.0.1` tidak bisa diakses tablet/TV.
`schedule:work` **wajib** — tanpa itu session tidak akan pernah jadi `EXPIRED`.

Detail persiapan lengkap: [`../docs/TEST-PLAN-SABTU.md`](../docs/TEST-PLAN-SABTU.md) Bagian 0.
