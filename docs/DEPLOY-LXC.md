# DEPLOY — LXC Proxmox untuk Cempaka Smart Billing

> Spesifikasi container untuk memindahkan backend dari laptop ke Proxmox.
> Ditulis 15 Nov 2026, berdasarkan pembacaan node `pve` yang sebenarnya.
> Backend saja. Tablet dan TV tetap aplikasi terpisah.

---

## 0. Yang paling mudah salah — baca ini dulu

| Aturan | Kenapa |
|---|---|
| **HTTP wajib di port 8000**, bukan 80 | Tablet dan TV memindai subnet **hanya di port 8000** (DEC-041, `ServerDiscovery.ports = [8000]` di Flutter dan `PORT = 8000` di Kotlin). Pasang nginx di 80 saja, dan penemuan otomatis mati — setiap perangkat harus diisi alamat manual |
| **IP container harus tetap** | Alamat yang berubah memutus semua perangkat sekaligus. Static IP di container, bukan DHCP |
| `/api/v1/health` **tanpa auth**, `app` tetap `cempaka-smart-billing` | Itu yang dipakai perangkat untuk mengenali server. `HealthController::APP_ID` adalah kontrak |
| **Semua data di MySQL** — jangan lupa `DB_CONNECTION=mysql` | Bawaan Laravel adalah `sqlite`. Sudah diubah di `config/database.php`, tapi kalau sampai ada yang mengembalikannya: server tetap menyala dan `/health` tetap menjawab, sementara transaksi masuk ke berkas SQLite yang tidak di-backup |
| **Reverb di 8080 harus terjangkau LAN** | Tanpa itu realtime mati diam-diam; API tetap jalan jadi tidak ada error yang terlihat |
| **`queue:work` wajib hidup** | Semua event realtime lewat antrian. Tanpa worker, event menumpuk di tabel `jobs` **tanpa error apa pun** |

---

## 1. Keadaan node `pve` saat ini

Dibaca 15 Nov 2026 lewat `ssh pve`:

| | |
|---|---|
| Proxmox | `pve-manager/9.2.21`, kernel 7.0.14-24-pve |
| CPU | 8 core |
| RAM | 15 GiB total · **9,8 GiB available** (sudah terpakai 5,6 GiB) |
| Storage | `local-lvm` 340 GiB bebas · `data-ssd` 124 GiB bebas (0% terpakai) · `backup` 316 GiB bebas |
| Bridge | `vmbr0` → `192.168.0.101/24` |
| Guest terpakai | VMID 101–107 → **VMID bebas berikutnya: 108** |
| Template siap | `debian-13-standard`, `ubuntu-24.04`, `ubuntu-26.04`, `alpine-3.24` |

RAM yang sudah **dialokasikan** ke 7 container berjumlah ~17,9 GiB di mesin 15 GiB —
node ini sudah overcommit. Karena itu container baru sengaja diminta kecil.

---

## 2. Spesifikasi container

| Item | Nilai | Alasan |
|---|---|---|
| **VMID** | `108` | pertama yang kosong |
| **Hostname** | `smartbilling` | |
| **Template** | `debian-13-standard` | Debian 13 membawa **PHP 8.4** di repo resmi. `composer.json` meminta `php ^8.3`, jadi tidak perlu repo pihak ketiga. Ubuntu 24.04 hanya punya PHP 8.3 (cukup, tapi mepet) |
| **Tipe** | **Unprivileged**, nesting **off** | tidak ada Docker di dalamnya |
| **vCPU** | **2 core** | Beban nyata 6 station + 3 tablet = puluhan request per menit. 2 core memberi ruang untuk `php-fpm` + Reverb + worker berjalan bersamaan tanpa saling menunggu |
| **RAM** | **2048 MB** | MariaDB ±400 MB, php-fpm 4 worker ±400 MB, Reverb ±80 MB, queue + scheduler ±120 MB, sisanya page cache. Di bawah 1 GB, MariaDB dan php-fpm mulai berebut saat checkout bersamaan |
| **Swap** | **512 MB** | penyangga, bukan tempat kerja |
| **Disk** | **16 GB** di `data-ssd` | OS ±2,5 GB, aplikasi + vendor ±0,5 GB. Sisanya untuk database dan log: satu sesi ≈ 2 KB, jadi 10 GB cukup untuk bertahun-tahun. `data-ssd` dipilih karena masih kosong dan tidak berebut I/O dengan container lain di `local-lvm` |
| **Network** | `vmbr0`, **static** `192.168.0.108/24`, gateway `192.168.0.1` | lihat §0 |
| **DNS** | ikut host | |
| **Start at boot** | **ya**, start order 20 | listrik mati di rental = semua TV dan tablet kehilangan server |
| **Features** | `keyctl=1` | dibutuhkan systemd di container unprivileged |
| **Timezone** | `Asia/Jakarta` | hanya untuk log dan `cron`. **Database tetap UTC** (DEC-005) — jangan diubah |

### Perintah pembuatan (dijalankan di shell node, bukan di container)

```bash
pct create 108 backup:vztmpl/debian-13-standard_13.6-1_amd64.tar.zst \
  --hostname smartbilling \
  --cores 2 --memory 2048 --swap 512 \
  --rootfs data-ssd:16 \
  --net0 name=eth0,bridge=vmbr0,ip=192.168.0.108/24,gw=192.168.0.1 \
  --unprivileged 1 --features keyctl=1 \
  --onboot 1 --startup order=20 \
  --timezone Asia/Jakarta \
  --description "Cempaka Smart Billing - Laravel API + Reverb"
```

Kalau `192.168.0.108` sudah dipakai perangkat lain di jaringan rental, ganti ke
alamat lain **di luar rentang DHCP router** — bukan sekadar alamat yang kebetulan
kosong hari ini.

---

## 3. Isi container

### Paket

```bash
apt update && apt install -y \
  nginx mariadb-server redis-server git unzip \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml \
  php8.4-curl php8.4-zip php8.4-bcmath php8.4-intl php8.4-gd
```

`redis-server` opsional. Dengan 6 station, `CACHE_STORE=database` dan
`QUEUE_CONNECTION=database` sudah terbukti cukup — itu yang diuji 283 test.
Pasang Redis hanya kalau nanti antrian benar-benar terasa lambat; menambahnya
sekarang berarti satu layanan lagi yang bisa mati tanpa ketahuan.

**Ekstensi yang wajib ada** (diverifikasi dari yang dipakai di lokal):
`pdo_mysql`, `mbstring`, `openssl`, `curl`, `zip`, `fileinfo`, `bcmath`, `intl`.

### Empat layanan yang harus hidup

Di laptop semuanya dijalankan manual di empat terminal. Di container, masing-masing
jadi unit systemd — yang dijalankan manual pasti terlupakan setelah reboot.

| Unit | Perintah | Catatan |
|---|---|---|
| `php8.4-fpm` + `nginx` | — | **nginx `listen 8000;`** (§0) |
| `smartbilling-reverb` | `php artisan reverb:start --host=0.0.0.0 --port=8080` | `Restart=always` |
| `smartbilling-queue` | `php artisan queue:work --sleep=1 --tries=3 --max-time=3600` | `Restart=always`. **Paling sering terlupa** |
| `smartbilling-schedule` | `php artisan schedule:work` | menjalankan `sessions:reconcile` tiap menit (`session.expired`) |

Alternatif untuk scheduler: satu baris cron
`* * * * * cd /var/www/smartbilling && php artisan schedule:run >> /dev/null 2>&1`.

### `.env` yang berbeda dari lokal

```ini
APP_ENV=production
APP_DEBUG=false                 # wajib — debug=true membocorkan isi .env di halaman error
APP_URL=http://192.168.0.108:8000
APP_TIMEZONE=UTC                # jangan diubah (DEC-005)

DB_HOST=127.0.0.1
DB_DATABASE=cempaka_billing
DB_USERNAME=cempaka             # bukan root
DB_PASSWORD=<acak, 24+ karakter>

BROADCAST_CONNECTION=reverb
REVERB_SERVER_HOST=0.0.0.0      # kalau 127.0.0.1, tablet tidak bisa menyambung
REVERB_SERVER_PORT=8080
REVERB_HOST=192.168.0.108       # yang diberikan ke client
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_APP_ID / _KEY / _SECRET  # BUAT BARU, jangan salin dari laptop
```

Setelah itu: `php artisan key:generate`, `migrate --force`, `db:seed --force`,
lalu `config:cache route:cache view:cache`.

> Sesudah `config:cache`, mengubah `.env` **tidak berpengaruh** sampai
> `php artisan config:clear` dijalankan. Ini sumber bingung yang paling mahal
> saat memperbaiki alamat di lokasi.

### Firewall

| Port | Dari | Untuk |
|---|---|---|
| 8000/tcp | LAN rental | API + penemuan otomatis |
| 8080/tcp | LAN rental | Reverb |
| 22/tcp | admin saja | SSH |

Port 3306 **tidak** dibuka. Klien tidak pernah menyentuh MySQL langsung
(CLAUDE.md §4).

---

## 4. Setelah container jalan — periksa ini

```bash
# 1. Identitas server benar (ini yang dicari tablet & TV)
curl -s http://192.168.0.108:8000/api/v1/health
#    harus memuat "app":"cempaka-smart-billing" dan "database":"ok"

# 2. Login
curl -s -X POST http://192.168.0.108:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"username":"operator1","password":"<password seeder>"}'

# 3. Realtime benar-benar sampai (di dalam container)
php artisan realtime:listen --seconds=30
#    lalu buat sesi dari tablet — harus muncul session.started

# 4. Antrian tidak menumpuk
php artisan queue:monitor database
```

Dari tablet: **Pengaturan → Cari otomatis**. Kalau container tidak ketemu padahal
`curl` dari laptop berhasil, hampir selalu salah satu dari dua ini: nginx tidak di
port 8000, atau tablet berada di SSID/VLAN yang berbeda.

Dan dari sisi klien, satu test sudah menyiapkan pembuktiannya:

```bash
cd operator-app
flutter test test/live_api_test.dart \
  --dart-define=LIVE_API_BASE_URL=http://192.168.0.108:8000
```

Golden path penuh (login → mulai → bayar → F&B → extend → checkout) dijalankan
lewat klien Flutter yang asli terhadap container. Lulus di situ berarti setiap
field yang dibutuhkan aplikasi benar-benar ada di server baru.

---

## 5. Backup

Satu baris di Datacenter → Backup sudah cukup untuk tahap ini:
container 108, harian, mode **snapshot**, tujuan `backup`, simpan 7.

Tambahan yang berguna: `mysqldump` harian ke `/var/backups/` sebelum jam buka.
Snapshot container memulihkan **semuanya** sekaligus — kalau yang rusak hanya satu
tabel, dump jauh lebih cepat dipakai.

---

## 6. Yang BELUM ada dan akan menuntut perubahan nanti

| Hal | Dampak ke container |
|---|---|
| **Peran server ini — DEC-051** | Container ini adalah **server operasional, selamanya** — bukan tempat singgah sebelum pindah VPS. Semua tulisan terjadi di sini, di LAN lokasi, tanpa internet. ROADMAP 3A sudah ditulis ulang mengikuti ini |
| Pengiriman ke pusat (DEC-051, Tahap 3A-2) | Dikerjakan **Laravel di container ini**: satu job terjadwal yang mengirim saat internet ada, idempotent, tertunda diam-diam saat tidak ada. Butuh akses **keluar**, tidak butuh port masuk baru, dan tidak mengubah RAM 2 GB. Jangan dipindah ke tablet — tablet tidak pernah butuh internet |
| HTTPS | Flavor prod Flutter wajib HTTPS-only (CLAUDE.md §4). Selama di LAN dan pakai flavor dev, HTTP cukup. Begitu keluar LAN: butuh reverse proxy + sertifikat — container `proxy` (VMID 103) sudah ada dan bisa dipakai |
| Admin Web (DEC-042…DEC-050) | **Sudah ada** dan dilayani Laravel yang sama — tidak ada layanan tambahan, tidak ada port tambahan. Ia memakai guard session (`SESSION_DRIVER=database`), jadi tabel `web_sessions` ikut tumbuh. RAM 2 GB masih cukup; naikkan ke 3 GB kalau halaman Laporan mulai berat |
| Payment gateway (Tahap 3) | Butuh akses keluar + webhook masuk → aturan firewall baru |
| TV Agent bicara ke Laravel (Tahap 2) | `/devices/heartbeat` tiap 30 detik × 6 TV = 12 request/menit. Tidak mengubah spesifikasi |
