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

```bash
php artisan queue:work
```

Keempatnya jalan **bersamaan**, masing-masing di terminal sendiri.

| Proses | Kalau tidak dijalankan |
|---|---|
| `serve --host=0.0.0.0` | Tablet dan TV tidak bisa menghubungi API sama sekali. Default `127.0.0.1` hanya bisa diakses laptop itu sendiri |
| `reverb:start` | Tidak ada realtime. Tablet tetap jalan dari HTTP (REALTIME.md §1), tapi harus menunggu refresh |
| `schedule:work` | Session **tidak akan pernah** jadi `WARNING` maupun `EXPIRED` |
| `queue:work` | **Semua event realtime menumpuk diam-diam di tabel `jobs` dan tidak pernah terkirim.** Tidak ada error di layar operator — kelihatannya normal, padahal tablet tidak pernah dapat kabar |

Yang terakhir paling mudah terlewat, karena gagalnya tidak terlihat.

### Dua host Reverb yang berbeda

Ini pernah membuat seluruh broadcast gagal diam-diam, jadi perlu ditulis:

| Variabel | Artinya | Isi |
|---|---|---|
| `REVERB_SERVER_HOST` | alamat yang **didengarkan** Reverb | `0.0.0.0` supaya tablet & TV bisa menyambung |
| `REVERB_HOST` | alamat yang **dihubungi Laravel** saat mengirim event | `127.0.0.1` |

`REVERB_HOST=0.0.0.0` **salah** — itu bukan alamat tujuan yang sah, dan setiap
broadcast masuk `failed_jobs` dengan `cURL error 7` tanpa tanda apa pun di sisi
operator.

### Memeriksa realtime benar-benar jalan

```bash
php artisan tinker --execute="echo DB::table('jobs')->count().' antre, '.DB::table('failed_jobs')->count().' gagal';"
```

Dua-duanya harus **0** setelah beberapa detik. `jobs` menumpuk berarti
`queue:work` tidak jalan; `failed_jobs` bertambah berarti Reverb tidak bisa
dihubungi.

Detail persiapan lengkap: [`../docs/TEST-PLAN-SABTU.md`](../docs/TEST-PLAN-SABTU.md) Bagian 0.
