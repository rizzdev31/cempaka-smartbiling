# backend — Laravel

API + Admin Web + Customer Portal. **Satu aplikasi Laravel**, bukan tiga proyek.

**Status:** **Tahap 0 SELESAI.** Laravel 13.35.0 + Sanctum, 32 endpoint,
9 event realtime, 283 test lulus, golden path 38/38 lewat HTTP.

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

## Pertama kali di mesin baru

Urutan ini **sekali saja**. Lewat satu langkah dan yang gagal biasanya bukan
langkah itu, tapi langkah berikutnya — dengan pesan yang tidak menunjuk ke
penyebabnya.

**0. PHP 8.4+ — periksa dulu**

```bash
php -v
```

`composer.json` menulis `^8.3`, tapi lock-nya sebenarnya menuntut
**≥ 8.4.1** (`symfony/http-foundation` v8.1.8). Di bawah itu, setiap perintah
berhenti dengan:

```
Composer detected issues in your platform: Your Composer dependencies
require a PHP version ">= 8.4.1". You are running 8.2.12.
```

**Jangan diselesaikan dengan `composer update`.** Itu menulis ulang
`composer.lock` dan menurunkan paket agar muat di PHP lama — sementara mesin
lain tetap di 8.4. Hasilnya dua lingkungan yang berbeda diam-diam, dan bug
yang hanya muncul di satu sisi.

Kalau mesin Anda memakai XAMPP, PHP bawaannya biasanya masih lama. Pasang
PHP 8.4 **berdampingan** (mis. `C:\php84`) — jangan menimpa `C:\xampp\php`,
karena Apache memuat PHP dari sana lewat jalur absolut dan semua proyek
`htdocs` ikut berubah.

Lalu panggil yang baru secara eksplisit di proyek ini:

```bash
/c/php84/php.exe artisan migrate
/c/php84/php.exe /c/ProgramData/ComposerSetup/bin/composer.phar install
```

> `composer` di Windows hanyalah shim yang memanggil `php` dari PATH — jadi
> ia **ikut** PHP yang salah kalau dipanggil begitu saja. Itu sebabnya
> `composer.phar` dijalankan sebagai argumen PHP di atas.

Supaya tidak mengetik path panjang terus, tambahkan ke `~/.bashrc`:

```bash
alias php84='/c/php84/php.exe'
```

**1. Dependensi**

```bash
composer install
```

**2. `.env`**

`.env` **tidak di-commit** (berisi kredensial). Salin dari contohnya:

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

**3. Kredensial Reverb**

`REVERB_APP_ID`, `REVERB_APP_KEY`, dan `REVERB_APP_SECRET` di `.env.example`
sengaja **kosong** — itu rahasia, tidak boleh di-commit. Isi sekali:

```bash
php artisan install:broadcasting
```

Kalau dilewati, `reverb:start` jalan tapi setiap broadcast ditolak, dan
jatuh ke `failed_jobs` tanpa tanda apa pun di sisi operator.

Jangan sentuh `REVERB_SERVER_HOST` dan `REVERB_HOST` — nilainya sudah benar,
dan menukarnya adalah kesalahan yang paling sulit disadari di proyek ini
(lihat **Dua host Reverb yang berbeda** di bawah).

**4. Database**

Buat database kosong bernama `cempaka_billing`, lalu sesuaikan `DB_USERNAME`
dan `DB_PASSWORD` di `.env` dengan MySQL di mesin Anda.

```bash
php artisan migrate --seed
```

Kalau muncul `Access denied for user 'root'@'localhost' (using password: NO)`,
itu berarti `DB_PASSWORD` di `.env` masih kosong padahal MySQL Anda pakai
password — bukan masalah migration.

**5. Aset Admin Web**

```bash
npm install && npm run build
```

**Jangan dilewat.** Admin Web memakai Tailwind lewat Vite, dan tanpa
`public/build/manifest.json` halaman `/admin` menjawab **500**
(`ViteManifestNotFoundException`).

Yang membuatnya mahal: kegagalannya tidak berhenti di situ. Tiga test web
ikut gagal, **dan satu test login API ikut merah dengan 401** — padahal test
itu lulus kalau dijalankan sendiri. Gejalanya terlihat seperti bug
autentikasi, bukan aset yang belum dibangun.

**6. Pastikan hidup**

```bash
curl http://localhost:8000/api/v1/health
```

Harus menjawab `"app": "cempaka-smart-billing"`. Nilai itulah yang dipakai
tablet dan TV untuk menemukan server ini sendiri (DEC-041) — kalau tidak
muncul, penemuan otomatis tidak akan bekerja.

### Akun contoh dari seeder

Username `owner`, `admin`, `operator1`. Password diambil dari `SEED_PASSWORD`
di `.env`; kalau tidak diisi, nilainya `password`.

**Hanya untuk development.** Seeder tidak boleh dijalankan di produksi — akun
produksi dibuat lewat Admin Web (Tahap 3B).

---

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

Untuk memastikan event benar-benar **sampai ke client**, bukan sekadar
terkirim dari server:

```bash
php artisan realtime:listen --api=http://127.0.0.1:8000 --seconds=60
```

Perintah itu menyambung ke Reverb sebagai client sungguhan — menempuh jalur
yang sama dengan Flutter dan Kotlin: login, handshake WebSocket, minta tanda
tangan ke `POST /broadcasting/auth`, lalu subscribe. Setiap event yang masuk
dicetak ke layar.

Kalau operator bilang "tabletnya tidak update", jalankan ini di laptop: kalau
event muncul di sini, masalahnya di client; kalau tidak, masalahnya di server.

Detail persiapan lengkap: [`../docs/TEST-PLAN-SABTU.md`](../docs/TEST-PLAN-SABTU.md) Bagian 0.
