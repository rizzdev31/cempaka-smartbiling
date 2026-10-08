# DECISION LOG — Cempaka Smart Billing

Keputusan di file ini **menang atas PRD** kalau bentrok.
Owner keputusan bisnis: Product/GM. Agent tidak boleh menambah entry tanpa persetujuan — hanya boleh mengusulkan di §Open Decisions.

Format: `DEC-xxx` = sudah diputuskan · `OD-xxx` = belum diputuskan (`NEEDS DECISION`)

---

## DEC-001 — Urutan tahap diubah: Flutter → Kotlin TV → Laravel Superadmin
**Tanggal:** 2 Okt 2026 · **Status:** APPROVED · **Override:** PRD §32 (P0–P11)

Tahap 1 = Flutter Operator. Tahap 2 = Kotlin APK Android TV. Tahap 3 = Laravel Superadmin.

**Klarifikasi penting:** yang pindah ke Tahap 3 adalah **Superadmin Web UI + reporting + finance + migrasi VPS + Customer Portal + payment gateway**. **Laravel API Core tetap wajib di Tahap 0**, karena PRD §8/§33 melarang Flutter menyimpan business rule.

**Alasan:** operator app adalah yang paling cepat memberi nilai di lapangan dan paling cepat mengungkap salah asumsi alur kasir. Superadmin butuh data transaksi nyata dulu agar reporting tidak dibangun di atas tebakan.

**Konsekuensi:** `ROADMAP.md` menggantikan PRD §32. PRD §32 tetap disimpan sebagai referensi historis.

---

## DEC-002 — Tahap 0–2 berjalan lokal; VPS masuk di Tahap 3A
**Tanggal:** 2 Okt 2026 · **Status:** APPROVED · **Override:** PRD §25 (sebagian), Lampiran B

Laravel + MySQL + Reverb jalan di laptop/PC lokal selama Tahap 0, 1, 2. Migrasi ke VPS di Tahap 3A.

**Alasan:** testing Sabtu dilakukan di lokasi dengan WiFi lokal; menunggu VPS siap akan memblokir Tahap 1.

**Batasan yang mengikat:**
1. Semua konfigurasi lewat `.env` / build flavor. **Tidak ada IP/host hardcode** di kode.
2. DB dibangun hanya lewat **migration + seeder**, agar pindah VPS = `migrate --seed`, bukan export dump.
3. Cleartext HTTP hanya di flavor **dev**. Flavor **prod** HTTPS-only.
4. Data lokal = **data test**. Jangan dipakai untuk transaksi uang nyata sebelum backup/restore terbukti (PRD §26).
5. Keputusan arsitektur PRD (VPS = server utama V1) **tidak dibatalkan** — hanya dijadwalkan ulang.

---

## DEC-003 — Server-time offset wajib di semua client
**Tanggal:** 2 Okt 2026 · **Status:** APPROVED · **Melengkapi:** PRD §16, R08

Client tidak boleh menghitung sisa waktu dari jam device mentah. Setiap response API menyertakan `server_time`; client menyimpan `offset = server_time − device_time` dan menghitung `remaining = end_at − (device_now + offset)`. Offset di-refresh setiap heartbeat/reconnect.

**Alasan:** jam laptop, tablet, dan Android TV hampir pasti berbeda — terutama TV yang baru boot dan belum sync NTP. Tanpa ini timer bisa salah beberapa menit dan customer dirugikan atau rental kehilangan uang.

---

## DEC-004 — Flutter TIDAK pakai offline write queue di Tahap 1
**Tanggal:** 2 Okt 2026 · **Status:** APPROVED · **Menutup:** PRD §35 "Offline queue Flutter"

Tahap 1 hanya pakai **read cache / last-known state** + retry dengan `Idempotency-Key`. Tidak ada queue untuk write (create session, payment, order).

**Alasan:** offline write queue adalah sumber utama duplicate transaction dan double payment (R06). Risikonya lebih besar daripada manfaatnya saat operator berada 5 meter dari server lokal. Bisa dipertimbangkan ulang setelah data lapangan menunjukkan koneksi benar-benar sering putus.

---

## DEC-005 — Timezone & uang
**Tanggal:** 2 Okt 2026 · **Status:** APPROVED · **Melengkapi:** PRD §22 (tidak diatur di PRD)

- Timezone aplikasi: `Asia/Jakarta`. Semua timestamp disimpan **UTC** di DB, dikirim **ISO-8601 UTC** di API, dikonversi di UI.
- Semua nilai uang: **integer rupiah**, tanpa desimal. Tidak ada `float`/`double` untuk uang di mana pun.

**Alasan:** PRD tidak menyebut keduanya. Tanpa aturan ini `end_at` bisa geser 7 jam dan total tagihan bisa beda 1 rupiah antar layer — keduanya langsung terlihat customer.

---

## DEC-006 — Scope testing Sabtu: 1 station, bukan 6
**Tanggal:** 2 Okt 2026 · **Status:** APPROVED · **Sesuai:** PRD §29

Testing Sabtu dibatasi ke **ST01** sebagai vertical slice. TV lain hanya diuji konektivitas, bukan alur.

**Alasan:** PRD §29 eksplisit melarang PoC langsung 6 TV. Menguji 6 sekaligus membuat kegagalan tidak bisa dilacak penyebabnya.

**Detail eksekusi:** `TEST-PLAN-SABTU.md`

---

## DEC-007 — Extend: blok 30 menit + grace 10 menit setelah EXPIRED
**Tanggal:** 2 Okt 2026 · **Status:** APPROVED · **Menutup:** OD-003, PRD §35 "Extend pricing dan durasi"

**Durasi & harga**
- Extend hanya kelipatan **30 menit** (30, 60, 90, …). Tidak ada extend 15 menit atau per menit.
- `tarif_per_jam = harga_paket ÷ durasi_paket_dalam_jam` (dari paket yang dipakai session aktif).
- `harga_extend = ceil( tarif_per_jam ÷ 2 × (durasi_menit ÷ 30) )` → integer rupiah.
- Contoh: paket Rp 20.000/jam, extend 30 menit = **Rp 10.000**; extend 90 menit = **Rp 30.000**.
- Item EXTEND masuk `session_items` → Open Tab → default dibayar saat checkout (PRD §12).

**Grace period**
- Extend boleh diajukan selama `now ≤ end_at + 10 menit`.
- `end_at_baru = end_at_lama + durasi_extend` — **dihitung dari `end_at` lama, bukan dari waktu approve.** Waktu grace yang sudah lewat tetap terhitung, tidak ada waktu gratis.
- Lewat 10 menit → extend **ditolak**. Operator harus checkout lalu buat session baru.
- Tetap butuh approval operator (PRD §14 default control).

**Alasan:** blok 30 menit menghilangkan angka receh sehingga kasir tidak perlu menghitung manual. Grace 10 menit menutup celah paling mahal: customer minta lanjut tepat saat waktu habis, dan tanpa aturan ini operator cenderung memberi waktu lewat secara gratis.

**Catatan:** grace 10 menit ini **hanya mengatur kebolehan extend**. Perilaku TV saat EXPIRED (lock, overlay, auto-off) masih **OD-001**.

---

## DEC-008 — Satu session = satu customer
**Tanggal:** 2 Okt 2026 · **Status:** APPROVED · **Menutup:** OD-007

- `sessions.customer_id` = **nullable single FK**. Walk-in non-member → `null` + field nama opsional.
- **Tidak ada** tabel pivot `session_customers` di V1.
- Walau yang bermain 4 orang, hanya satu customer/member yang tercatat per session.

**Alasan:** schema dan laporan customer tetap sederhana. Tidak ada kebutuhan bisnis V1 yang menuntut pembagian poin/diskon antar beberapa member dalam satu session.

**Konsekuensi:** kalau nanti membership perlu dibagi antar pemain, itu **Change Request**, bukan penyesuaian kecil — butuh tabel pivot + perubahan laporan customer.

---

## DEC-009 — Rounding durasi: per 30 menit, toleransi 5 menit
**Tanggal:** 2 Okt 2026 · **Status:** APPROVED · **Menutup:** OD-008

**Formula** (`m` = menit aktual, dibulatkan ke atas ke menit penuh):

```
sisa = m mod 30

sisa == 0   → billable = m
sisa <= 5   → billable = m - sisa          (dibulatkan ke BAWAH)
sisa >  5   → billable = m + (30 - sisa)   (dibulatkan ke ATAS)

billable minimum = 30 menit
```

| Menit aktual | Ditagih |
|---|---|
| 35 | 30 menit |
| 60 | 60 menit |
| 63 | 60 menit (1 jam) |
| 70 | 90 menit (1,5 jam) |
| 95 | 90 menit |

**Berlaku untuk:** perhitungan durasi **aktual**, yaitu rental **Postpaid**.

**Tidak berlaku untuk:** Prepaid (durasi sudah ditetapkan paket di depan, `end_at` fix) dan Extend (sudah blok 30 menit per DEC-007).

**Alasan:** toleransi 5 menit menghilangkan keluhan paling sering — customer yang lewat 2–3 menit ditagih setengah jam tambahan. Blok 30 menit menjaga angka tetap bulat.

**Catatan:** penagihan **overstay** (customer masih bermain melewati `end_at` pada Prepaid) belum diatur di sini — menunggu **OD-001**.

---

## DEC-010 — Monorepo satu git, folder terpisah per app
**Tanggal:** 2 Okt 2026 · **Status:** APPROVED · **Melengkapi:** PRD §34

Satu repository git: `https://github.com/rizzdev31/cempaka-smartbiling.git`

```
/                     ← satu .git
├── CLAUDE.md, README.md
├── docs/             ← PRD, Decision Log, Roadmap, Progress, UI Spec
│   └── contracts/    ← API.md, REALTIME.md, CHANGELOG.md
├── backend/          ← Laravel: API + Admin Web + Customer Portal
├── operator-app/     ← Flutter
└── tv-agent/         ← Kotlin Android TV
```

**Alasan:**
1. Kontrak API/realtime adalah titik kopling terbesar proyek ini. Satu repo = ubah kontrak + backend + Flutter + Kotlin dalam satu commit, sehingga tidak mungkin ada client yang tertinggal (PRD §34, R10).
2. `CLAUDE.md` + `docs/` harus terbaca agent di setiap folder. Repo terpisah berarti sesi di repo Flutter tidak melihat Decision Log.
3. Tim masih kecil — tiga repo berarti tiga PR untuk satu perubahan field.

**Aturan yang mengikat:**
- Setiap folder app **berdiri sendiri** dan bisa di-build tanpa yang lain.
- Deploy ke VPS (Tahap 3A) hanya mengambil `backend/` lewat path filter — bukan seluruh repo.
- **Tidak ada git repo bersarang.** Satu `.git` di root.
- `backend/` menampung API, Admin Web, dan Customer Portal karena ketiganya satu aplikasi Laravel, bukan tiga proyek.
- `.env` dan semua kredensial **tidak pernah** di-commit.

**Konsekuensi:** CI perlu path filter per folder; IDE akan mengindeks folder yang tidak relevan. Keduanya kecil. Memecah repo nanti jauh lebih mudah daripada menyatukan.

---

## DEC-011 — Kontrak API & realtime ditulis sebelum kode
**Tanggal:** 2 Okt 2026 · **Status:** APPROVED · **Melengkapi:** PRD §34

`docs/contracts/API.md` + `REALTIME.md` ditulis dan disepakati **sebelum** ada kode Laravel maupun Flutter. Keduanya adalah artefak resmi (PRD §34: "API Contract", "Realtime Contract").

**Alasan:** kalau Flutter dibangun dengan model tebakan lalu Laravel dibuat belakangan, nama field akan berbeda (`end_at` vs `endAt`) dan seluruh model + parsing Flutter harus ditulis ulang. Kontrak lebih dulu membuat backend dan Flutter bisa dikerjakan **paralel**.

**Aturan yang mengikat:**
- Field yang tidak ada di kontrak **tidak boleh** diasumsikan client.
- Setiap perubahan kontrak dicatat di `docs/contracts/CHANGELOG.md` **sebelum** client menyesuaikan.
- Aturan bisnis yang bisa dihitung server **tidak boleh** diduplikasi di client. Contoh: `extend_deadline_at` dan `extendable` dikirim server supaya aturan grace DEC-007 tidak ditulis dua kali.
- Pengecualian yang disengaja: perhitungan sisa waktu memang di client (PRD §16) — server hanya mengirim `end_at`.

---

## DEC-012 — Flutter dibangun lebih dulu dengan fake repository dari kontrak
**Tanggal:** 2 Okt 2026 · **Status:** APPROVED · **Melengkapi:** DEC-001, DEC-011

Urutan kerja (dikerjakan **sendiri**, jadi sekuensial — bukan paralel):

```
Sabtu 3 Okt    recon lokasi (SESI 1) — 0 kode
Hari kerja 1–3 Flutter: scaffold, flavor, tema, komponen, ticker,
               settings IP, dashboard + session detail → fake repository
Hari kerja 4–6 Laravel thin slice: auth, stations, packages, sessions, payments
               + middleware X-Server-Time & Idempotency-Key
Hari kerja 7   Ganti fake → API asli. Vertical slice hidup.
Lanjut         sisa screen Flutter & sisa endpoint, bergantian per fitur
Lalu           SESI 2 di lokasi (golden path T01–T17)
```

**Alasan Flutter dulu:** hal yang paling mungkin salah di PRD bukan backend-nya, tapi **alur kasir** — prepaid vs postpaid, kapan F&B ditagih, bentuk checkout. Dashboard yang bisa diklik dan ditunjukkan ke operator asli mengungkap itu dalam satu jam. Kalau backend dibangun dulu, alur yang salah sudah terkunci di schema.

**Kenapa ini sekarang aman** (sebelumnya tidak): kontrak `API.md` sudah ada (DEC-011). Model Flutter **ditranskrip** dari `API.md` §7, bukan ditebak. Risiko model drift — satu-satunya alasan menolak Flutter-first — sudah hilang.

**Dua syarat yang mengikat:**
1. Model, enum, dan error code di Flutter **ditranskrip dari kontrak, tidak pernah dikarang.** Field yang tidak ada di `API.md` tidak boleh diasumsikan.
2. Fake repository **wajib diganti API asli pada vertical slice pertama** (login + dashboard + start session). Jangan menumpuk sampai semua screen jadi.

**Alasan syarat 2:** fake repository tidak bisa membuktikan kontraknya lengkap. Hanya Laravel asli yang mengungkap field yang kurang. Makin lama Flutter jalan di atas data palsu, makin banyak asumsi menumpuk dan makin mahal koreksinya.

**Bentuk fake repository:** satu implementasi in-memory di belakang interface yang sama dengan versi API-nya, berisi JSON yang ditranskrip langsung dari contoh di `API.md`. Harus **meniru kontrak apa adanya**, termasuk delay dan error code — bukan jalur yang selalu sukses. Nanti dipakai ulang sebagai fixture widget test, jadi tidak terbuang.

---

## DEC-013 — Satu tablet operator per lokasi (satu kasir)
**Tanggal:** 2 Okt 2026 · **Status:** APPROVED · **Melengkapi:** PRD §18

Hanya ada **satu** tablet operator di lokasi. Tidak ada tablet kedua untuk dapur, dan tidak ada dua kasir bekerja bersamaan.

**Yang menjadi tidak perlu di V1:**

| Usulan | Status |
|---|---|
| Event `fnb.order.updated` | **Tidak perlu di Tahap 1.** Dengan satu tablet, perubahan status order langsung terlihat di layar yang sama — tidak ada layar kedua yang perlu disinkronkan |
| Event `shift.closed` | **Tidak perlu.** Hanya relevan kalau ada beberapa tablet |
| Penanganan konflik antar tablet | **Tidak perlu.** Tidak ada dua operator menulis bersamaan |
| Polling agresif untuk antrian F&B | **Tidak perlu.** Refresh saat layar dibuka sudah cukup |

**Yang TETAP perlu:**
- WebSocket Reverb — Kotlin TV Agent (Tahap 2) tetap mengkonsumsi `session.started/updated/extended/swapped/expired`. Keputusan ini tentang tablet, bukan tentang TV.
- `device.offline` (usulan) — itu soal TV, bukan tablet. Masih terbuka.
- Fitur Shift tetap dibangun. Shift bukan soal beberapa kasir bersamaan, tapi soal **pertanggungjawaban kas per periode kerja** — operator berbeda di hari atau jam berbeda.

**Catatan penting — ini penundaan, bukan penghapusan:**
`fnb.order.updated` akan **kembali dibutuhkan di Tahap 3C**. PRD §13 mewajibkan "Customer melihat status order dan total sementara", dan Customer Portal adalah layar kedua. Jadi event itu tetap tercatat sebagai usulan di `REALTIME.md` §9, dengan tahap yang digeser.

---

## DEC-014 — Desain UI mengikuti `contoh.html`
**Tanggal:** 2 Okt 2026 · **Status:** APPROVED · **Override:** `UI-UX-SPEC.md` versi pertama

User menilai desain pertama belum pas dan memberi `operator-app/contoh.html`
sebagai acuan. Arah visual sekarang: **Material 3 dark** dengan aksen cyan +
mint, permukaan biru-gelap bertingkat, glow halus pada elemen aktif, dan
shell bersidebar.

**Yang berubah:**

| | Sebelum | Sekarang |
|---|---|---|
| Navigasi | bar aksi di bawah grid | **sidebar** dengan lima tujuan + badge |
| Palet | biru-slate, aksen amber | **cyan + mint**, Material 3 roles |
| Font | font sistem | **Space Grotesk / Plus Jakarta Sans / JetBrains Mono**, dibundel |
| Kartu station | rail status, chip, bar waktu | header (kode + tipe konsol \| status) → timer → bar → blok customer → **aksi cepat** |
| Filter | tidak ada | chip Semua / Bermain / Tersedia / Hampir Habis / Menunggu Bayar + pencarian |

**Tiga penyesuaian yang sengaja menyimpang dari contoh**, karena contoh itu
mockup web sementara targetnya tablet sentuh:

1. **Tombol aksi 44 px, bukan 34 px.** Contoh memakai tombol kecil dengan
   asumsi presisi mouse. Aksi di kartu ini mengubah uang; mis-tap mahal.
   44 px adalah minimum sentuh iOS. Material meminta 48 px, tapi 48 membuat
   enam kartu tidak muat tanpa scroll di tablet 1280x800 — 44 px kompromi
   yang disengaja.
2. **Sidebar menyusut jadi rail ikon di bawah 1040 px.** 288 px dari 800 px
   layar portrait adalah 36% untuk navigasi saja (aturan
   `adaptive-navigation`).
3. **Tombol +30m / +1j tetap pakai konfirmasi.** Contoh tidak punya
   konfirmasi, tapi salah tap +1j menagih customer satu jam yang tidak
   diminta dan kontrak tidak punya jalur pembatalan.

**Yang TIDAK diambil dari contoh karena datanya tidak ada:**
bel notifikasi (tidak ada sistem notifikasi), badge terminal POS-01 (tidak
ada konsep terminal; DEC-013 satu kasir), indikator "Auto Refresh Aktif"
(tidak ada auto-refresh — menampilkannya akan menjadi klaim palsu), teknisi
dan nomor tiket pada kartu maintenance (OD-016). Slot bel diisi dengan
indikator koneksi yang memang nyata.

---

## DEC-015 — Tahap 0 ditahan; TV Agent dibangun lebih dulu sebagai kiosk kontrol-langsung
**Tanggal:** 2 Okt 2026 · **Status:** APPROVED · **Menahan:** DEC-012 urutan kerja

User menahan Laravel dan memilih membangun **APK TV lebih dulu**, sebagai kiosk
yang dikontrol operator lewat WiFi/jaringan lokal, **tanpa login dan tanpa
backend**.

### Konflik dengan PRD — dan kenapa tetap dijalankan

PRD §8 menyatakan TV Agent "bukan source of truth" dan client "tidak boleh
menentukan harga/state". PRD §16 menyatakan TV menerima `start_at`/`end_at`
dari Laravel lewat WebSocket. Kontrol langsung operator → TV **melanggar
keduanya**.

Tapi kebutuhan di belakangnya sah dan mendesak:
1. **R01 adalah risiko HIGH yang belum tersentuh.** Apakah TV bisa dijadikan
   kiosk sama sekali belum terbukti. Tidak ada gunanya menyelesaikan Laravel
   kalau ternyata TV-nya tidak bisa dipasangi APK.
2. **OD-005 dijawab lebih baik dengan APK nyata** daripada dengan browser TV.
3. PRD §29 sendiri meminta vertical slice lebih dulu, bukan sistem lengkap.

Jadi ini diperlakukan sebagai **technical spike untuk membuktikan kendali TV**,
bukan perubahan arsitektur permanen.

### Yang mengikat agar pekerjaannya tidak terbuang

1. **Sumber perintah di belakang interface.** Kotlin memakai `CommandSource`
   dengan dua implementasi: `LocalHttpCommandSource` (sekarang) dan
   `ReverbCommandSource` (Tahap 2 sebenarnya). Layar kiosk, timer, persistence,
   dan recovery **tidak tahu** dari mana perintahnya datang — jadi saat Laravel
   masuk, yang diganti hanya satu kelas.
2. **Timer tetap dihitung dari `end_at`**, bukan dari hitungan mundur yang
   dikirim per detik. Sama seperti PRD §16, jadi tidak perlu diubah nanti.
3. **Kontrol langsung WAJIB berautentikasi** — lihat di bawah.
4. **Tidak ada logika billing di TV.** TV hanya menampilkan `end_at` yang
   diberikan. Harga, kelayakan extend, dan rounding tetap tidak pernah ada di
   TV, bahkan dalam mode langsung ini.

### Kenapa autentikasi tidak bisa ditunda

Kontrol langsung tanpa autentikasi berarti **siapa pun di WiFi yang sama bisa
menyetel timer TV**. Customer yang terhubung ke Guest Wi-Fi bisa memperpanjang
sesinya sendiri secara gratis — ini kelas risiko yang sama dengan *prank order*
di PRD §13, dan R05 (unauthorized customer access, HIGH).

Jadi kontrol langsung memakai **pairing**: TV menampilkan kode, operator
memasukkannya sekali, lalu menerima token device. Setiap perintah berikutnya
membawa token itu. Token dapat dicabut — sejalan dengan PRD §10 dan §24, dan
polanya sama dengan `enrollment_code` di kontrak §9, jadi tidak terbuang.

### Mode kiosk punya dua tingkat — dan tingkat 2 belum pasti

| Tingkat | Cara | Bisa keluar? |
|---|---|---|
| **1** — selalu bisa | fullscreen immersive + foreground service + auto-start saat boot | Ya, lewat tombol HOME |
| **2** — kiosk sebenarnya | Lock Task mode + Device Owner | Tidak |

Tingkat 2 butuh **Device Owner**, yang umumnya hanya bisa di-set pada TV tanpa
akun Google — artinya factory reset (OD-005 / V10). Jadi aplikasi melaporkan
tingkat mana yang aktif lewat `GET /health`, dan **tidak mengklaim** kiosk
penuh sebelum terbukti. PRD §5 melarang klaim itu.

### Yang ditahan

Tahap 0 (Laravel API Core) ditahan, termasuk login. Konsekuensinya:
- Operator app tetap memakai fake repository (DEC-012 syarat 2 belum bisa
  dipenuhi)
- Tidak ada audit trail untuk apa pun yang dilakukan lewat kontrol langsung
- Tidak ada recovery dari server kalau TV kehilangan state-nya

Ketiganya **akan** dibutuhkan sebelum produksi. Dicatat di sini supaya tidak
dianggap sudah selesai.

---

## DEC-016 — Tema terang; dark mode dihentikan
**Tanggal:** 2 Okt 2026 · **Status:** APPROVED · **Override:** DEC-014, `UI-UX-SPEC.md` §1

User meminta UI putih dengan gradasi putih, dan secara eksplisit meminta
hasilnya **tidak terlihat seperti dibuat AI**.

### Alasan lama yang di-override — dicatat supaya tidak digali ulang

`UI-UX-SPEC.md` §1 versi sebelumnya menolak light mode dengan dua alasan:
ruang rental PlayStation gelap sehingga UI terang mengganggu operator, dan
layar terang mencolok dari kursi customer.

Alasan itu **tidak terbantahkan, hanya dikesampingkan**: user sudah melihat
versi gelapnya di perangkat dan menilai ruangannya sendiri. Kalau nanti
ternyata memang mengganggu di lokasi, alasannya ada di sini — tidak perlu
ditemukan ulang.

### Yang membuat UI terlihat "dibuat AI", dan apa yang dilakukan

| Ciri | Yang dilakukan |
|---|---|
| Gradasi diagonal pada logo & tombol | Dihapus. Logo mark jadi isian rata; tidak ada satu pun `LinearGradient` di aplikasi |
| Glow pada elemen aktif | Dihapus. Nav aktif memakai **penanda tepi kiri 3 px** + isian abu lembut — cara panel kontrol menunjukkan "kamu di sini" sejak lama |
| Shadow di setiap kartu | Kartu dipisahkan **garis setipis mungkin + nada permukaan**. Shadow hanya untuk yang benar-benar melayang: modal, bottom sheet, popup |
| Radius besar seragam di semua elemen | Dirapatkan dan dibedakan per peran: chrome data 4 px, tombol/input 6 px, kartu 10 px, modal 12 px. `pill` hanya untuk chip filter dan titik status |
| Banyak warna aksen | Satu aksen (teal). Warna lain hanya status, dan setiap status tetap disertai ikon + teks |
| Chip status berlatar penuh warna | Titik + teks, atau tint sangat muda |
| Kotak bertumpuk di dalam kotak | Statistik header dibuat rata tanpa kotak; blok di dalam kartu memakai bidang cekung, bukan kartu lagi |
| Ripple Material 3 | Diganti `InkRipple` klasik — pada permukaan putih, ripple M3 terbaca sebagai genangan |

### Palet

Lapisan dibangun dari **nada putih**, bukan dari shadow: kanvas `#F6F7F9`,
kartu `#FFFFFF`, bidang cekung `#F1F3F5`, garis `#E3E6EA`.

Aksen **teal `#0E7490`** — garis keturunan cyan dari `contoh.html` tapi gelap
agar terbaca di atas putih. Cyan neon `#00E5FF` pada latar terang tidak bisa
memenuhi kontras apa pun; memaksakannya berarti teks yang tidak terbaca.

Status memakai warna 700-an: hijau `#047857`, amber `#B45309`, merah
`#B91C1C`, indigo `#4338CA` (menunggu bayar), ungu `#7E22CE` (checkout, satu-
satunya pemakaian ungu di aplikasi), slate `#475569` (offline).

"Menunggu bayar" sengaja indigo, **jauh dari amber**, supaya tidak tertukar
dengan "hampir habis" — keduanya menuntut tindakan yang berbeda. Jarak hue-nya
dijaga test (>60°), bukan hanya oleh ingatan.

### Kontras diverifikasi, bukan dikira-kira

31 pasangan warna dihitung dengan rumus WCAG. Semuanya memenuhi target:
teks utama 17,8:1 · teks sekunder 6,0:1 · aksen 4,8:1 (di bidang cekung) ·
status terendah 4,5:1 · putih di atas tombol teal 5,4:1.

Dua tempat, satu sumber warna — keduanya membaca `AppColors`:

| | Peran |
|---|---|
| `test/theme_discipline_test.dart` | **yang mengikat.** Ikut jalan di `flutter test` |
| `docs/tools/contrast.py` | tabel untuk dibaca manusia saat menyetel warna |

Setiap warna status diuji di **dua** latar: kartu putih **dan** bidang cekung
`surfaceContainer`. Ini bukan kehati-hatian berlebihan — pemeriksaan pertama
hanya menguji latar putih dan lolos, lalu versi yang membaca token langsung
menemukan `statusOffline` gagal di 4,28:1 pada kartu read-only, yang justru
latar yang dipakai order dibatalkan. Slate dinaikkan dari `#64748B` ke
`#475569`. Yang membuat status itu terasa tenang adalah saturasinya yang
nyaris nol, bukan kontrasnya yang rendah.

Teks pendukung `#8A939F` berada di 3,1:1 — **di bawah 4,5:1 dan itu
disengaja**. Dipakai hanya untuk label pendukung (alamat, jam, kode order),
tidak pernah untuk informasi yang harus dibaca. Target untuk peran itu 3:1,
dan test menahannya tetap **di bawah** 4,5 supaya catatan ini tidak jadi basi
tanpa ada yang tahu.

### Aturannya ditegakkan mesin, bukan niat

Daftar "yang dilarang" di atas akan bocor kembali satu widget pada satu waktu
kalau hanya berupa tulisan. `test/theme_discipline_test.dart` membaca source
`lib/` dan menolak: gradasi apa pun, `Color(0x` di luar `tokens.dart`,
`BoxShadow(` di luar `tokens.dart`, dan sisa `Brightness.dark`.

Keempat lint itu sudah dibuktikan menyala terhadap berkas uji yang sengaja
melanggar — lint yang tidak pernah bisa gagal tidak menjaga apa pun.

### Layar TV tetap hitam

`tv-agent` **tidak** diubah. TV berada di ruang yang gelap, dilihat dari 2–3
meter, dan layar putih terang di depan customer yang sedang bermain adalah hal
yang berbeda sama sekali dari tablet di meja kasir. Hitam murni juga memberi
kontras maksimum dan hemat daya pada panel OLED.

Yang tetap sama di keduanya: **makna warna status**. Hijau tetap bermain,
amber tetap hampir habis, merah tetap habis — nilainya berbeda karena latarnya
berbeda, tapi artinya identik.

---

## DEC-017 — Merek pelanggan pertama: Amor Gaming Space

**Tanggal:** 3 Okt 2026 · **Status:** APPROVED · **Terkait:** OD-012

Operator app dipasangi merek pelanggan pertama. Logo dikirim user
(`logo-amor.png`), nama **Amor Gaming Space**, atribusi naungan di footer
diubah jadi **"Powered by Cempaka Smart Billing"**.

### Logo ditempel apa adanya — tanpa alas, tanpa bingkai

**Diputuskan user 3 Okt 2026**, setelah percobaan pertama memakai alas navy.
Bentuk logonya sendiri yang jadi bentuknya.

Kekhawatiran awal — logo ini 60% nyaris putih sehingga hilang di chrome putih
— **ternyata tidak berlaku untuk emblemnya.** Angka 60% itu dari lockup
penuh, yang sebagian besarnya wordmark. Diukur ulang pada emblem saja:

| | Emblem saja | Lockup penuh |
|---|---|---|
| Nyaris putih (>220) | 33% | — |
| Terang (>200) | 44% | 60% |
| Menengah & gelap | **56%** | 40% |
| Wordmark saja | — | 80% terang |

Stik kontrolernya gelap dan sapuan birunya pekat, dan keduanya **mengelilingi**
huruf "A" yang putih. Huruf itu terbentuk oleh tetangganya, bukan oleh
kontrasnya sendiri terhadap latar. Jadi di atas putih logonya tetap terbaca.

Pelajarannya: angka yang diukur pada keseluruhan berkas tidak otomatis
berlaku untuk bagian yang benar-benar dipakai.

Alas dilepas berikut token `brandPlate` dan flag `logoNeedsDarkPlate` —
keduanya tidak disisakan sebagai abstraksi yang tidak dipakai.

### Berkas dipangkas — 62% isinya ruang kosong

Konten aslinya 2532x1781 di dalam kanvas 3373x4770, 1,9 MB untuk slot ~44 px.
Dipasang apa adanya, logo tampil jauh lebih kecil dari ruang yang diberikan.

| Berkas | Isi | Ukuran |
|---|---|---|
| `logo-amor-mark.png` | emblem saja, dipangkas | 256x137, 37 KB |
| `logo-amor-full.png` | lockup penuh, untuk splash/Tentang nanti | 640x450, 155 KB |
| `logo-amor.png` | kiriman asli — **sumber, tidak dibundel** | 1,9 MB |

`pubspec.yaml` menyebut berkas satu per satu, bukan seluruh folder, supaya
sumber 1,9 MB tidak ikut ke dalam APK.

> **Menambah aset baru butuh `flutter run` ulang, bukan hot reload.** Bundel
> aset dibangun saat build; hot reload dan hot restart tidak membangunnya
> ulang. Logo yang "tidak muncul" pada percobaan pertama penyebabnya ini —
> bundel di `build/` masih berisi 11 aset font tanpa satu pun gambar.

### Wordmark dipotong dari penanda

Emblem saja yang dipakai di sidebar. Logo aslinya sudah memuat tulisan
"AMOR GAMING SPACE"; dipasang utuh di sebelah nama merek sebagai teks,
keduanya jadi berulang.

Nama ditulis **dua baris** — "Amor" besar, "GAMING SPACE" kecil dengan jarak
huruf — meniru kunci logo aslinya. Satu baris, nama sepanjang ini terpotong di
sidebar 268 px; dua baris justru memberi ruang untuk membuatnya lebih besar.

Baris kedua memakai **Space Grotesk, bukan `labelSm`**. `labelSm` memakai
JetBrains Mono, dan itu font untuk angka serta label teknis (UI-UX-SPEC §3) —
nama merek bukan keduanya, dan mono membuatnya terbaca seperti kode.

### Ukuran

Logo setinggi **44 px**, lebarnya mengikuti rasio aslinya (~82 px) — tidak
dipaksa ke kotak persegi, karena dipaksa persegi logo menyusut sampai
setengahnya. Sebelumnya kotak monogram 34x34.

Pada rail (72 px) tingginya 38 supaya lebarnya tetap muat.

Ikon navigasi 20 -> 22.

### Yang ini bukti untuk OD-012, bukan keputusannya

Pemasangan ini membuktikan jalur **white-label per-instance** bisa: satu
pelanggan, satu build, semua string merek di satu berkas. Tapi OD-012 tetap
**belum diputuskan** — yang menentukan bukan ini, melainkan apakah beberapa
pelanggan berbagi satu database.

Yang masih memakai nama naungan dan belum ikut rebrand: **APK TV**
(`Brand.tvAppName`), `applicationId` Android, dan nama repo.

> **DITUNDA — user, 3 Okt 2026:** "rebrand apk tv jadi Amor Gaming Space juga
> ini nanti saja." Dikerjakan **setelah SESI TV**, bukan sebelumnya.
>
> Alasannya bukan sekadar urutan: mengganti `applicationId` membuat Android
> memperlakukannya sebagai aplikasi berbeda, sehingga APK yang sudah terpasang
> di TV harus dicopot dulu dan pairing-nya hilang. Melakukan itu di tengah
> sesi pengujian hanya menambah variabel.
>
> Cakupannya nanti: `app_name` di `strings.xml`, `applicationId`, banner TV
> (@drawable/tv_banner, kini masih bentuk sederhana), dan `Brand.tvAppName`
> di operator app supaya layar pairing menyebut nama yang sama.

### Aksen aplikasi tetap teal

Biru dominan logonya `#0080F0`. Aksen aplikasi tetap teal `#0E7490` (DEC-016)
— menggantinya berarti mengulang seluruh verifikasi kontras, dan belum ada
yang meminta. Dicatat di sini supaya pilihannya ada kalau nanti diminta.

---

## DEC-018 — White-label per-instance: satu server untuk satu rental
**Tanggal:** 7 Okt 2026 · **Status:** APPROVED · **Menjawab:** OD-012

Diputuskan user (pemilik backend): **satu server = satu rental.** Tiap pelanggan
punya instance Laravel + MySQL sendiri. Bukan multi-tenant.

**Konsekuensi untuk schema:** tidak ada `tenant_id`. Migration ditulis untuk
satu rental. Sesuai rekomendasi di detail OD-012.

**Yang masih terbuka** (tidak memblokir Tahap 0): poin-poin di
"OD-012 → Yang masih perlu diputuskan saat fiturnya dikerjakan" — logo per
build/per server, nama APK, lisensi per pelanggan.

---

## DEC-019 — Tarif berbeda per tipe konsol, diatur dari aplikasi kasir
**Tanggal:** 7 Okt 2026 · **Status:** APPROVED · **Menjawab:** OD-015 · **Melengkapi:** PRD §22

Diputuskan user: **tarif berbeda per tipe konsol** (contoh PS5 VIP vs PS4 Slim),
dan tarifnya **bisa diatur dari aplikasi kasir (Flutter)**.

**Konsekuensi untuk schema (usulan teknis backend):**
- Tabel baru tipe konsol (mis. `station_types`: PS5 VIP, PS4 Slim, …).
- `stations` punya FK ke tipe konsolnya.
- `packages` punya FK ke tipe konsol → harga paket berlaku per tipe.
- Harga tetap **dihitung server** dari tabel itu. Rumus extend DEC-007
  (`tarif_per_jam = harga_paket ÷ durasi_paket_jam`) otomatis ikut tarif tipe
  konsol station yang dipakai.

**Konsekuensi untuk API:** perlu endpoint ubah tarif/paket (belum ada di
`contracts/API.md` — ditambahkan saat implementasi, dicatat di
`contracts/CHANGELOG.md`). Setiap perubahan harga **wajib masuk `audit_logs`**
(PRD §24: price/master-data change).

**Konsekuensi untuk Flutter:** butuh layar pengaturan tarif — dikerjakan tim
Flutter.

**Siapa yang boleh mengubah tarif:** → **DEC-020** (hanya owner).

**Catatan swap:** swap antar tipe konsol **tidak diizinkan** → **DEC-021**.

---

## DEC-021 — Station Swap hanya boleh dalam tipe konsol yang sama
**Tanggal:** 7 Okt 2026 · **Status:** APPROVED · **Menjawab:** OD-018 · **Melengkapi:** PRD §15, DEC-019

Diputuskan user: **pindah ke tipe konsol berbeda tidak boleh.** Alasannya
paketnya berbeda — "harus bikin paket baru".

Jadi `POST /sessions/{id}/swap` **hanya menerima station tujuan dengan tipe
konsol yang sama** dengan station asal. Tipe berbeda → ditolak, bukan
dihitung ulang harganya.

Ini sekaligus memperjelas kata **"kompatibel"** di PRD §15, yang sebelumnya
tidak didefinisikan: kompatibel = **tipe konsol sama**.

**Yang mengikat di backend:**
- Validasi tipe konsol sebelum swap dijalankan, dengan error code tersendiri
  (ditambahkan ke `contracts/API.md` §11 saat implementasi).
- Swap tetap tidak menyentuh harga sama sekali — tidak ada perhitungan ulang
  tarif, karena tarifnya pasti sama.
- Sisanya tidak berubah: atomic, `session_id` tetap, `end_at` tetap, histori
  ikut (PRD §15).

**Kalau customer memang ingin pindah tipe konsol:** itu **session baru dengan
paket baru**, bukan swap. Session lama diselesaikan lebih dulu (checkout).

**Yang belum diputuskan → OD-020:** sisa waktu yang sudah dibayar di session
lama jadi apa — dipotong dari paket baru, hangus, atau ditagih penuh? Ini soal
uang, jadi **jangan diputuskan di kode**. Tidak memblokir golden path, karena
golden path tidak memuat perpindahan tipe konsol.

---

## DEC-020 — Hanya owner yang boleh mengubah tarif
**Tanggal:** 7 Okt 2026 · **Status:** APPROVED · **Menjawab:** OD-017 · **Melengkapi:** PRD §6, DEC-019

Diputuskan user: **yang bisa mengubah harga hanya owner.** Operator tidak,
dan admin biasa juga tidak — hanya owner.

**Konsekuensi untuk RBAC — role bertambah jadi tiga.** Sampai sekarang
`ROADMAP.md` Tahap 0 hanya menyebut dua role: `admin` dan `operator`.
Keputusan ini memaksa `owner` menjadi role tersendiri, bukan sinonim `admin`:

| Role | Ubah tarif/paket | Sisa akses |
|---|---|---|
| `owner` | **YA** | semua akses admin |
| `admin` | tidak | master data lain, laporan, device |
| `operator` | tidak | session, payment, F&B, extend, swap, checkout, shift |

PRD §6 menulis "Admin / Owner" sebagai satu aktor — **keputusan ini
memisahkannya**, khusus untuk harga. Seeder Tahap 0 jadi butuh 3 user contoh:
owner, admin, operator.

**Yang mengikat di backend:**
- Endpoint ubah tarif/paket ditolak (403) untuk siapa pun selain `owner`,
  **ditegakkan server** — menyembunyikan tombol di Flutter bukan kontrol
  keamanan (PRD §24).
- Perubahan harga tetap wajib masuk `audit_logs` dengan actor + nilai
  sebelum/sesudah.

**Konsekuensi untuk Flutter (tim rekan):** layar pengaturan tarif hanya muncul
untuk login owner. Tetap harus siap menerima 403 dari server.

**Yang belum diputuskan — tidak memblokir Tahap 0:** apakah owner mengubah
tarif lewat **login owner sendiri** di tablet, atau lewat **PIN** di atas sesi
operator yang sedang jalan. Backend-nya sama (token owner), bedanya hanya cara
login di Flutter. Dicatat di `PROGRESS.md` sebagai pertanyaan tertunda.

---

## DEC-022 — Penahanan Tahap 0 dicabut; Laravel API Core dimulai
**Tanggal:** 7 Okt 2026 · **Status:** APPROVED · **Mencabut:** DEC-015 (bagian "Yang ditahan")

Diputuskan user: **Laravel dimulai sekarang.** Penahanan Tahap 0 dari DEC-015
berakhir.

**Yang dicabut hanya penahanannya.** Sisa DEC-015 tetap berlaku — TV Agent
tetap memakai `CommandSource` dengan dua implementasi, kontrol langsung tetap
berautentikasi, dan kiosk tetap tidak diklaim penuh sebelum terbukti.

**Alasan boleh paralel:** pekerjaan backend tidak bersinggungan dengan masalah
jaringan yang sedang menahan SESI TV. Empat keputusan hari ini (DEC-018 s/d
DEC-021) sudah menutup semua Open Decision yang memblokir migration pertama.

**Pembagian kerja:** `backend/` dikerjakan pemilik backend; `operator-app/` dan
`tv-agent/` oleh rekan tim. Perubahan kontrak API/realtime wajib dicatat di
`contracts/CHANGELOG.md` dan dikomunikasikan (PRD §34).

**Konsekuensi untuk DEC-012:** syarat "fake repository diganti pada vertical
slice pertama" jadi bisa dipenuhi setelah langkah 7 (payment manual).

---

## DEC-023 — Overstay Prepaid: timer jalan terus, kelebihan ditagih saat checkout
**Tanggal:** 8 Okt 2026 · **Status:** APPROVED · **Menjawab:** OD-001 (bagian penagihan) · **Melengkapi:** PRD §11, DEC-007, DEC-009

Diputuskan user: customer **boleh terus bermain** melewati `end_at`. Sesi tidak
dihentikan. Kelebihan waktunya ditagih saat checkout.

**Konsekuensi terbesar — `EXPIRED` berubah arti.** Sampai sekarang `EXPIRED`
dibaca sebagai "sesi habis, berhenti". Sejak keputusan ini `EXPIRED` adalah
**penanda bahwa waktu paket sudah lewat**, bukan penghenti. Station tetap
terpakai, timer tetap berjalan maju, dan sesi baru pindah ke `CHECKOUT` ketika
operator menutupnya.

**Rumus penagihan overstay:**

```
overstay_menit = durasi_aktual - (durasi_paket + total_menit_extend)
blok           = pembulatan DEC-009 atas overstay_menit, TANPA lantai 30 menit
harga          = blok ÷ 30 × ceil(hourly_rate ÷ 2)
```

Item masuk Open Tab sebagai `ADJUSTMENT` bernama "Kelebihan waktu", `is_paid: false`,
dibayar saat checkout bersama F&B dan extend. Rental Prepaid yang sudah dibayar
**tidak dihitung ulang** — PRD §12 "tidak ada pembayaran rental kedua" tetap berlaku.

**Hubungannya dengan grace extend DEC-007.** Keduanya hidup berdampingan dan
tidak bertabrakan: extend dalam 10 menit pertama **lebih murah dan terencana**
(waktu ditambahkan ke `end_at`, customer tahu di depan). Overstay adalah
penagihan **setelah kejadian** untuk customer yang tidak minta extend. Operator
tetap didorong menawarkan extend lebih dulu.

**Yang mengikat di backend:**
- Scheduler reconciliation tetap menandai `ACTIVE/WARNING → EXPIRED` saat `end_at`
  lewat, tapi **tidak** menutup sesi dan **tidak** mengosongkan station.
- `SessionStatus::EXPIRED->occupiesStation()` tetap `true` — sudah benar sejak awal.
- Checkout menghitung overstay sebelum menjumlahkan balance.

**Konsekuensi untuk Kotlin TV Agent (tim rekan):** TV **tidak boleh** mematikan
tampilan atau mengunci saat `end_at` lewat. Yang ditampilkan: waktu berjalan maju
(overtime), bukan layar mati. Perilaku lock/overlay saat EXPIRED tetap **OD-004**.

**Asumsi yang perlu dikonfirmasi → OD-021.** Toleransi pembulatan overstay memakai
DEC-009 (sisa ≤ 5 menit dibulatkan ke bawah) **tanpa** lantai minimum 30 menit.
Artinya overstay 4 menit tidak ditagih, overstay 20 menit ditagih 30 menit. Lantai
30 menit sengaja tidak dipakai karena akan menagih 30 menit untuk kelebihan 2 menit.

---

## DEC-024 — Sisa waktu Prepaid hangus; hanya member yang bisa menyimpannya
**Tanggal:** 8 Okt 2026 · **Status:** APPROVED · **Menjawab:** OD-001 (bagian sisa waktu) · **Melengkapi:** PRD §12, DEC-009

Diputuskan user: customer Prepaid yang berhenti lebih awal **tidak mendapat
pengembalian**. Bayar paket 1 jam, main 40 menit → tetap bayar 1 jam penuh.

Pengecualian: **member**. Sisa waktunya boleh disimpan untuk dipakai lain hari.
Customer non-member yang ingin menyimpan sisa waktunya **harus mendaftar member
lebih dulu** — operator menawarkan saat checkout. Kalau menolak, sisanya hangus.

**Alasannya konsisten dengan DEC-009:** Prepaid memang tidak di-rounding dan
memakai durasi paket apa adanya. Kalau sisa waktu dikembalikan tunai, Prepaid
berubah jadi deposit dan kasir harus memegang uang kembalian — beban operasional
yang tidak diminta.

**Yang mengikat di backend:**
- Rental Prepaid di checkout = `package_price`, tidak pernah dihitung ulang dari
  durasi aktual. Hanya Postpaid yang memakai rounding DEC-009.
- Penyimpanan sisa waktu member butuh tabel saldo tersendiri — **belum dibuat**,
  menyusul bersama endpoint checkout.

**Belum diputuskan → OD-022** (tidak memblokir butir 1–5): biaya mendaftar member,
siapa yang boleh mendaftarkan di kasir (bentrok dengan OD-014 yang menahan
`customer.create` dari operator), masa berlaku saldo, dan apakah saldo bisa
diuangkan. Semuanya soal uang — jangan diputuskan di kode.

---

## DEC-025 — Sisa waktu member saat pindah tipe konsol dikonversi senilai rupiah
**Tanggal:** 8 Okt 2026 · **Status:** APPROVED · **Menjawab:** OD-020 · **Melengkapi:** DEC-021, DEC-024

Diputuskan user: sisa waktu **langsung jadi waktu di sesi baru**, dikonversi
berdasarkan nilai rupiahnya, bukan jumlah menitnya.

```
nilai_sisa  = sisa_menit ÷ 60 × hourly_rate_konsol_lama
menit_baru  = floor(nilai_sisa ÷ hourly_rate_konsol_baru × 60)
```

Contoh: sisa 30 menit di PS4 (10.000/jam) = 5.000 → di PS5 (15.000/jam) jadi
**20 menit**. Tidak ada saldo mengendap; semuanya habis dipakai di sesi baru.

**Alasan dikonversi senilai rupiah, bukan menit apa adanya:** DEC-019 membuat
tarif berbeda per tipe konsol. Memindahkan 30 menit PS4 jadi 30 menit PS5 berarti
rental memberi 2.500 gratis setiap kali customer pindah ke konsol lebih mahal.

**Tetap berlaku DEC-021:** pindah tipe konsol **bukan swap**. Sesi lama di-checkout,
sesi baru dibuat dengan paket baru. Konversi ini terjadi di pembuatan sesi baru,
bukan di endpoint swap.

**Hanya untuk member** (DEC-024). Non-member yang pindah tipe konsol → sisa hangus.

---

## DEC-026 — Saldo member: buku besar rupiah, dipakai untuk tagihan apa pun
**Tanggal:** 8 Okt 2026 · **Status:** APPROVED · **Melengkapi:** DEC-024, DEC-025 · **Menjawab sebagian:** OD-022

Diputuskan user: *"orang yang ingin menjadi member dengan sisa waktu berapapun
akan disimpan di akunnya. Lalu nanti bisa dipakai dan digabungkan dengan
tambahan biling lainnya."*

Tiga hal yang dikunci oleh kalimat itu:

| | |
|---|---|
| **Berapapun** | Tidak ada minimum. Sisa 3 menit tetap disimpan — tidak dibulatkan ke blok 30 menit |
| **Di akunnya** | Saldo melekat pada customer, bukan pada sesi. Bertahan lintas kunjungan |
| **Digabungkan dengan tagihan lain** | Saldo mengurangi **seluruh** Open Tab — rental, F&B, extend, adjustment — bukan hanya rental |

**Disimpan dalam rupiah, bukan menit.** DEC-019 membuat tarif berbeda per tipe
konsol, jadi "30 menit" tidak punya nilai tetap. Menyimpan menit berarti saldo
yang diperoleh di PS4 tiba-tiba lebih berharga saat dipakai di PS5. Konversi ke
menit terjadi saat dipakai, memakai tarif konsol yang sedang dipakai (DEC-025).

```
nilai_disimpan = floor(sisa_menit × tarif_per_jam ÷ 60)
```

Dibulatkan ke **bawah** supaya saldo tidak pernah melebihi nilai yang benar-benar
tersisa.

**Disimpan sebagai buku besar (`customer_credits`), bukan satu kolom saldo.**
PRD §24 mewajibkan jejak audit untuk semua yang menyangkut uang. Satu kolom
saldo hanya menyimpan hasil akhir; kalau angkanya dipertanyakan customer, tidak
ada yang bisa dijelaskan. Dengan ledger, setiap penambahan dan pemakaian punya
barisnya sendiri beserta sesi asalnya. Saldo berjalan = `SUM(amount)`.

**Yang mengikat di backend:**
- `POST /sessions/{id}/checkout` menerima `use_credit` (boolean, default `false`).
  Default-nya sengaja `false`: memakai saldo harus keputusan sadar operator di
  depan customer, bukan perilaku diam-diam yang baru ketahuan saat saldonya habis.
- Saldo yang dipakai muncul sebagai item `DISCOUNT` bernama "Saldo member"
  supaya terbaca di struk.
- Hanya membership **aktif** yang berhak memperoleh saldo (DEC-024). Membership
  yang dinonaktifkan kehilangan hak menambah, tapi saldo yang sudah terkumpul
  tidak dihapus.

**Masih belum diputuskan (OD-022 menyempit):** biaya mendaftar member, siapa yang
boleh mendaftarkan di kasir (bentrok OD-014), masa berlaku saldo, dan apakah
saldo bisa diuangkan. Tiga yang terakhir belum memblokir — tanpa keputusan, saldo
**tidak kedaluwarsa** dan **tidak bisa dicairkan**. Keduanya pilihan paling aman
untuk dibatalkan belakangan.

---

## DEC-027 — Operator boleh mendaftarkan member di kasir
**Tanggal:** 8 Okt 2026 · **Status:** APPROVED · **Menjawab:** OD-014 · **Membuka:** DEC-024

Diputuskan user: **"operator atau kasir boleh mendaftarkan member baru."**

Ini membuka jalan buntu yang ditemukan saat implementasi: DEC-024 mengharuskan
operator menawarkan membership saat checkout supaya sisa waktu customer bisa
disimpan, tapi `Permission::CUSTOMER_CREATE` hanya dipegang admin dan owner.
Operator menawarkan, lalu tidak punya tombolnya.

**Yang mengikat di backend:**
- `Permission::CUSTOMER_CREATE` masuk ke daftar permission **operator**.
- `POST /customers` dan pendaftaran membership boleh dipanggil operator.
- Pendaftaran tetap masuk `audit_logs` dengan aktor — PRD §24. Operator boleh,
  tapi tidak boleh tidak terlacak.

PRD §6 memberi akses `customer` hanya kepada Admin/Owner. Keputusan ini
**override** bagian itu, dengan alasan yang sama seperti DEC-020 memisahkan
owner dari admin: aturan di PRD ditulis sebelum alur kasir nyata diketahui.

---

## DEC-028 — Diskon hanya boleh diberikan owner
**Tanggal:** 8 Okt 2026 · **Status:** APPROVED · **Menjawab:** OD-006 (bagian diskon) · **Melengkapi:** DEC-020

Diputuskan user: **"untuk diskon sejauh ini hanya owner."**

Konsisten dengan DEC-020 — yang menyentuh harga hanya owner. Diskon adalah
pengurangan harga setelah transaksi berjalan, jadi perlakuannya sama.

**Yang mengikat di backend:**
- Permission baru `discount.manage`, **hanya owner**. Operator dan admin tidak.
- Item `DISCOUNT` hanya boleh lahir dari dua jalur: endpoint diskon milik owner,
  dan pemakaian saldo member (DEC-026) yang bukan diskon sesungguhnya melainkan
  uang customer sendiri.
- Setiap diskon wajib masuk `audit_logs` dengan nilai sebelum/sesudah.

**Belum diputuskan:** `ADJUSTMENT` (koreksi tagihan selain diskon, mis. kompensasi
TV rusak) belum diatur siapa yang boleh. Sekarang hanya lahir dari sistem
(overstay DEC-023), belum ada endpoint manual. Tetap di OD-006.

---

## DEC-029 — Biaya mendaftar member Rp 10.000 (sementara)
**Tanggal:** 8 Okt 2026 · **Status:** APPROVED (sementara) · **Melengkapi:** OD-022, DEC-024

Diputuskan user: **"untuk sementara biaya jadi member 10 ribu."**

Ditandai **sementara** karena user menyebutnya begitu, dan karena angkanya
berinteraksi dengan DEC-026: customer dengan sisa waktu 20 menit (senilai 6.666)
membayar 10.000 untuk menyimpannya. Untuk satu kali kunjungan itu merugikan;
masuk akal hanya kalau membership punya manfaat lain. Itu urusan bisnis, bukan
kode — tapi operator perlu tahu supaya tidak menawarkannya sebagai "biar sisa
waktunya tidak hangus" kepada customer yang tidak akan kembali.

**Yang mengikat di backend:**
- Nilainya di `config/billing.php` (`membership_fee`), bukan dipatri di kode.
  Owner mengubah tarif dari aplikasi (DEC-019/020); sampai layarnya ada, satu
  tempat di config lebih mudah diubah daripada tersebar.
- Kalau pendaftaran dilakukan saat sesi berjalan, biayanya masuk Open Tab sesi
  itu sebagai item `ADJUSTMENT` bernama "Biaya daftar member", dibayar di
  checkout bersama tagihan lain.

---

## DEC-030 — Warning di TV: overlay kecil di kanan atas
**Tanggal:** 8 Okt 2026 · **Status:** APPROVED · **Menjawab:** OD-004 · **Membuka:** TAHAP 2

Diputuskan user: **"overlay di kanan atas."**

Ini mencabut salah satu dari dua penghalang Tahap 2 (sisanya OD-005, fakta TV).

**Yang mengikat di Kotlin TV Agent (tim rekan):**
- Peringatan 10/5/1 menit tampil sebagai **overlay di pojok kanan atas**, bukan
  layar penuh dan bukan dialog yang menutupi permainan.
- Dihitung client dari `end_at` + server-time offset. **Tidak ada event warning**
  dari server (REALTIME.md §4) — kalau dikirim lewat WebSocket, warning justru
  hilang saat koneksi putus, yaitu skenario yang paling harus selamat.
- Sejak DEC-023 `EXPIRED` bukan penghenti: setelah lewat `end_at`, overlay
  berganti menampilkan waktu berjalan maju (overtime), bukan layar mati.

**Belum diputuskan:** apakah overlay berbunyi, dan apakah customer bisa
menutupnya. Tidak memblokir — default: tanpa suara, tidak bisa ditutup.

---

## DEC-031 — Struk: di layar, bisa dicetak, dan bisa dikirim ke WA member
**Tanggal:** 8 Okt 2026 · **Status:** APPROVED · **Menjawab:** OD-010

Diputuskan user: **"bisa dicetak atau di layar, lalu bisa dikirimkan ke customer
lewat WA kalau jadi member."**

**Yang mengikat:**
- Struk **selalu** tampil di layar tablet setelah checkout. Mencetak jadi
  opsional, jadi printer bukan prasyarat untuk mulai beroperasi.
- Pengiriman WA hanya untuk **member**, karena hanya member yang nomor
  teleponnya tersimpan (`customers.phone`). Walk-in tidak punya nomor.
- Data struk sudah lengkap di response checkout (`receipt`) — nomor, dua durasi,
  baris, totals, payments, operator. Tidak ada field baru yang dibutuhkan.

**Belum diputuskan, dan ini bukan hal kecil:** cara mengirim WA-nya. Membuka
`wa.me` dari tablet (gratis, operator menekan kirim sendiri) berbeda jauh dari
WhatsApp Business API (berbayar, otomatis, butuh verifikasi bisnis). Yang pertama
bisa dikerjakan Flutter tanpa backend sama sekali. Ditambahkan sebagai **OD-023**.

---

## Open Decisions — tambahan hasil analisis

Belum diputuskan. **Jangan diperlakukan sebagai requirement.**

| ID | Pertanyaan | Kenapa penting | Blokir tahap |
|---|---|---|---|
| ~~OD-001~~ | ~~Overstay: EXPIRED tapi customer masih bermain~~ | **DIPUTUSKAN → DEC-023** (timer jalan terus, kelebihan ditagih di checkout) + **DEC-024** (sisa waktu hangus kecuali member). Perilaku lock/overlay TV saat EXPIRED tetap di OD-004 | — |
| **OD-002** | Mitigasi Postpaid/Open Tab kabur tanpa bayar — deposit? batas maksimum open tab? catat identitas? | PRD §12 memperbolehkan Postpaid tapi tidak punya mitigasi kerugian. | Tahap 1 |
| ~~OD-003~~ | ~~Extend pricing & extend setelah EXPIRED~~ | **DIPUTUSKAN → DEC-007** | — |
| ~~OD-004~~ | ~~Perilaku warning di TV~~ | **DIPUTUSKAN → DEC-030** (overlay kecil di kanan atas). Bunyi & bisa-ditutup belum, default: tanpa suara, tidak bisa ditutup | — |
| **OD-005** | Model & versi Android TV final; bisa sideload APK? ADB over network aktif? | Menentukan apakah Tahap 2 layak sama sekali (R01). **Cek Sabtu.** | Tahap 2 |
| **OD-006** | Siapa yang boleh membuat item **ADJUSTMENT** manual (koreksi tagihan selain diskon, mis. kompensasi TV rusak)? | Bagian diskon sudah dijawab DEC-028 (hanya owner). ADJUSTMENT manual belum ada endpointnya — sekarang hanya lahir dari sistem (overstay DEC-023) | Tahap 3B |
| ~~OD-007~~ | ~~Satu session bisa beberapa customer?~~ | **DIPUTUSKAN → DEC-008** | — |
| ~~OD-008~~ | ~~Rounding durasi~~ | **DIPUTUSKAN → DEC-009** | — |
| **OD-009** | Formula profit/margin & target achievement | PRD §35 TBD. Belum blokir karena reporting di Tahap 3B. | Tahap 3B |
| ~~OD-010~~ | ~~Struk dicetak atau di layar~~ | **DIPUTUSKAN → DEC-031** (layar + cetak opsional + kirim WA untuk member). Cara kirim WA-nya jadi OD-023 | — |
| **OD-011** | Apakah Flutter perlu **penemuan IP server otomatis** (scan subnet), atau cukup DHCP reservation? | **Ditunda oleh user 2 Okt 2026 — tunggu hasil DHCP reservation di SESI 1.** Analisis ada di bawah tabel. | Tahap 1 (opsional) |
| ~~OD-015~~ | ~~Tarif berbeda per tipe konsol?~~ | **DIPUTUSKAN → DEC-019** (ya, diatur dari aplikasi kasir) | — |
| ~~OD-017~~ | ~~Siapa yang boleh mengubah tarif dari aplikasi kasir?~~ | **DIPUTUSKAN → DEC-020** (hanya owner) | — |
| ~~OD-018~~ | ~~Swap ke tipe konsol berbeda?~~ | **DIPUTUSKAN → DEC-021** (tidak boleh) | — |
| ~~OD-020~~ | ~~Sisa waktu saat pindah tipe konsol~~ | **DIPUTUSKAN → DEC-025** (dikonversi senilai rupiah ke menit di tarif konsol baru, member saja) | — |
| **OD-021** | Toleransi pembulatan **overstay**: pakai DEC-009 (sisa ≤ 5 menit ke bawah) tanpa lantai 30 menit — sudah diterapkan sebagai asumsi di DEC-023. Benar? | Menentukan apakah overstay 4 menit ditagih 0 atau 30 menit. Soal uang, tapi tidak memblokir karena mudah diubah di satu tempat (`OverstayPolicy`) | Tahap 0 (checkout) |
| ~~OD-022~~ | ~~Biaya & wewenang daftar member~~ | **DIPUTUSKAN → DEC-027** (operator boleh) + **DEC-029** (Rp 10.000, sementara) | — |
| **OD-023** | Cara mengirim struk ke WA: buka `wa.me` dari tablet (gratis, operator menekan kirim) atau WhatsApp Business API (berbayar, otomatis, butuh verifikasi bisnis)? | Muncul dari DEC-031. Yang pertama bisa dikerjakan Flutter tanpa backend sama sekali; yang kedua butuh kerja backend + biaya bulanan | Tahap 3 |
| **OD-016** | Apakah **maintenance perlu data pendukung** — teknisi, nomor tiket, estimasi selesai? | Desain contoh menampilkannya, tapi tidak ada entity-nya di PRD §22. Sekarang kartu maintenance hanya menampilkan "Sedang diperbaiki" — tidak memalsukan data yang tidak ada | Tahap 3B (Admin) |
| ~~OD-014~~ | ~~Bolehkah operator mendaftarkan member baru?~~ | **DIPUTUSKAN → DEC-027** (boleh) | — |
| **OD-013** | Ringkasan shift: `rental`/`fnb` dihitung saat item **dibuat** (nilai transaksi) atau saat **dibayar** (uang masuk)? | Keduanya sudah dibedakan di UI, tapi mana yang jadi dasar laporan belum diputuskan. Mempengaruhi laporan harian dan formula profit (OD-009). `cash`/`qris`/`total` tidak terpengaruh — itu selalu uang masuk | Tahap 3B (reporting) |
| ~~OD-012~~ | ~~White-label per-instance atau multi-tenant?~~ | **DIPUTUSKAN → DEC-018** (per-instance, satu server satu rental) | — |

**Tidak ada lagi Open Decision yang memblokir Tahap 0.** Billing engine sudah boleh ditulis.

Yang masih menghalangi **Tahap 2**: OD-004 (perilaku warning) dan OD-005 (fakta TV — dicek Sabtu). Penagihan overstay sudah dibuka oleh DEC-023; yang tersisa dari OD-001 hanya perilaku TV, yang memang milik OD-004.

---

---

## OD-011 — detail: penemuan IP otomatis & deteksi TV

**Ditunda oleh user pada 2 Okt 2026.** Analisis sudah selesai, tinggal keputusan.
**Pemicu peninjauan:** setelah DHCP reservation diuji di SESI 1 (Sabtu 3 Okt).

### Bagian A — penemuan IP server otomatis

Keadaan sekarang: manual lewat `--dart-define` + layar Pengaturan.

| Cara | Keandalan di C64 | Kerja server |
|---|---|---|
| **Scan subnet** — baca IP sendiri, probe `GET /api/v1/health` ke `.1`–`.254` | **Tinggi** — HTTP biasa, tidak bergantung fitur router | **Tidak ada** (endpoint sudah ada) |
| mDNS / Bonjour | Sedang — multicast sering di-drop AP murah, perlu WiFi multicast lock | Perlu mDNS responder di Windows (tidak ada bawaan) |
| UDP broadcast | Sedang-tinggi | Perlu listener UDP custom di Laravel |

Kalau dikerjakan, urutannya: alamat terakhir yang berhasil → scan subnet → lebih dari satu hasil tampilkan pilihan → tidak ketemu buka Pengaturan. Biaya ± 100 baris, tanpa dependensi baru. 254 host dengan 32 request paralel dan timeout 300 ms ≈ 2–3 detik.

**Dua pertimbangan yang membuat ini ditunda:**
1. **Jadi tidak terpakai di Tahap 3A.** Setelah pindah VPS, alamatnya domain tetap dengan HTTPS — tidak ada yang perlu ditemukan.
2. **DHCP reservation menyelesaikan masalah yang sama tanpa kode.** Sudah masuk checklist SESI 1 §1.3.

### Bagian B — deteksi TV yang terhubung

**Sudah dirancang, sengaja TIDAK memakai scan jaringan.**

```
Kotlin TV Agent ──POST /api/v1/devices/heartbeat──► Laravel
Flutter ──GET /api/v1/stations──► { device: { status, last_seen_at, app_version } }
```

Alasan tidak memakai scan: TV yang terjangkau di WiFi **tidak berarti** agent-nya jalan atau menampilkan sesi yang benar. TV bisa hidup dan WiFi nyambung sementara aplikasinya crash — ping akan berkata "online" padahal customer melihat layar kosong. Heartbeat tidak bisa bohong soal itu. PRD §10 juga menegaskan IP bukan kontrol keamanan; identitas device adalah token unik yang dapat dicabut.

Sudah siap: kontrak `API.md` §9, model `DeviceSummary`, indikator offline di kartu station. Di build sekarang datanya masih dari seed fake.
Belum ada: heartbeat-nya sendiri → **Tahap 2**, masih diblokir OD-005.

**Yang tidak bisa otomatis:** memasangkan TV mana = station mana. Tidak ada protokol jaringan yang bisa tahu suatu TV secara fisik berada di ST01. Dibinding sekali oleh manusia lewat `enrollment_code` (Admin buat kode untuk ST01 → dimasukkan di TV ST01), setelah itu permanen. Ini memang yang diminta PRD §10.

---

---

## OD-012 — detail: aplikasi dijual ke beberapa pengguna (white-label)

**Disampaikan user 2 Okt 2026.** User minta fiturnya dikerjakan nanti, fokus ke fitur dulu.
**Tapi satu bagiannya tidak bisa ditunda** — lihat "Yang harus diputuskan sebelum Tahap 0 selesai".

Kebutuhan: nama dan logo menyesuaikan tiap pengguna, tetap di bawah naungan Cempaka Smart Billing.

> **Diperbarui 3 Okt 2026 (DEC-017).** Pelanggan pertama sudah dipasang:
> **Amor Gaming Space**. Sisi klien terbukti siap — satu berkas `Brand`
> memegang semua string dan aset, dan mengganti merek tidak menyentuh satu
> widget pun.
>
> **Itu tidak menjawab OD-012.** Yang menentukan bukan tampilan merek,
> melainkan apakah beberapa pelanggan berbagi satu database. Keputusannya
> tetap ditunggu sebelum migration pertama.
>
> **DIPUTUSKAN 7 Okt 2026 → DEC-018: per-instance.**

### Dua model yang sangat berbeda konsekuensinya

| | **White-label per-instance** | **Multi-tenant satu server** |
|---|---|---|
| Deployment | Tiap rental punya VPS/DB sendiri | Satu VPS, satu DB, banyak rental |
| Schema | Tidak berubah | `tenant_id` di **hampir semua** tabel + row-level scoping |
| Isolasi data | Mutlak | Bergantung disiplin query — satu query lupa filter = data rental lain bocor |
| Biaya per pelanggan | Satu VPS per pelanggan | Dibagi |
| Update | Deploy ke tiap instance | Sekali |
| Risiko | Operasional (banyak instance) | Keamanan (kebocoran antar tenant) |

PRD §5 menyatakan "multi-cabang penuh bukan fokus V1" — jadi kebutuhan ini **belum tercakup PRD** dan perlu masuk sebagai Change Request (PRD §34).

### Yang harus diputuskan sebelum Tahap 0 selesai

Hanya satu pertanyaan: **multi-tenant atau tidak.**

Kalau nanti dipilih multi-tenant, `tenant_id` harus ada di schema **sejak migration pertama**. Menambahkannya setelah ada data transaksi nyata berarti membongkar setiap tabel, setiap query, setiap laporan, dan setiap endpoint — sekaligus memverifikasi tidak ada kebocoran. Itu pekerjaan berminggu-minggu, bukan berhari-hari.

Kalau dipilih per-instance, schema sekarang sudah benar dan tidak ada yang perlu diubah.

**Rekomendasi:** mulai **per-instance**. Alasannya: isolasi data mutlak tanpa bergantung disiplin query, cocok untuk pelanggan awal yang jumlahnya sedikit, dan tidak menambah kerumitan pada tahap yang belum terbukti jalan. Pindah ke multi-tenant nanti tetap mahal — tapi memilih multi-tenant sekarang berarti menanggung risiko kebocoran data sejak hari pertama untuk kebutuhan yang belum ada pelanggannya.

### Yang sudah dikerjakan sekarang sebagai persiapan murah

`operator-app/lib/core/brand.dart` — semua nama, tagline, dan atribusi dikumpulkan di satu file. Tidak ada string merek yang tertulis di widget. Mengubah merek nanti = mengubah satu file, bukan berburu string di seluruh app.

Ini **bukan** implementasi white-label. Ini hanya memastikan white-label nanti tidak perlu menyentuh puluhan file. Biayanya nol.

### Yang masih perlu diputuskan saat fiturnya dikerjakan

Logo: dibundel per build, atau diunduh dari server per tenant? · Warna: ikut tenant atau tetap? (mengubah warna berarti menguji ulang kontras — UI-UX-SPEC §1) · Nama aplikasi di launcher Android: beda APK per pelanggan, atau satu APK dengan nama netral? · Bagaimana "di bawah naungan Cempaka" ditampilkan — footer, splash, atau halaman Tentang? · Lisensi & masa aktif per pelanggan.

---

## Open Decisions dari PRD §35 yang masih berlaku

Spesifikasi VPS · domain & subdomain · exact TV lock/unlock mechanism · Device Owner/Lock Task/Accessibility/HDMI behavior · booking grace period & late arrival · booking cancellation/refund · Midtrans setup/webhook/MDR/settlement · backup retention & secondary storage · guest bandwidth policy · notification provider & consent.
