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

## Open Decisions — tambahan hasil analisis

Belum diputuskan. **Jangan diperlakukan sebagai requirement.**

| ID | Pertanyaan | Kenapa penting | Blokir tahap |
|---|---|---|---|
| **OD-001** | Apa yang terjadi saat `EXPIRED` tapi customer masih bermain? Auto-lock TV? Operator manual? Overstay ditagih bagaimana? | Kejadian paling sering di lapangan tapi tidak ada di PRD. Menentukan perilaku TV **dan** penagihan overstay (DEC-009 sengaja tidak mengaturnya). | Tahap 1 (UI), Tahap 2 (lock) |
| **OD-002** | Mitigasi Postpaid/Open Tab kabur tanpa bayar — deposit? batas maksimum open tab? catat identitas? | PRD §12 memperbolehkan Postpaid tapi tidak punya mitigasi kerugian. | Tahap 1 |
| ~~OD-003~~ | ~~Extend pricing & extend setelah EXPIRED~~ | **DIPUTUSKAN → DEC-007** | — |
| **OD-004** | Warning 10/5/1 menit: bunyi? teks? overlay penuh atau pojok? Bisa ditutup customer? | Tahap 2 tidak bisa selesai tanpa ini. PRD §35 TBD. | Tahap 2 |
| **OD-005** | Model & versi Android TV final; bisa sideload APK? ADB over network aktif? | Menentukan apakah Tahap 2 layak sama sekali (R01). **Cek Sabtu.** | Tahap 2 |
| **OD-006** | Apakah discount/adjustment operator perlu approval admin, atau bebas dengan audit saja? | PRD mewajibkan audit tapi tidak menyebut approval. Celah kebocoran kas. | Tahap 1 |
| ~~OD-007~~ | ~~Satu session bisa beberapa customer?~~ | **DIPUTUSKAN → DEC-008** | — |
| ~~OD-008~~ | ~~Rounding durasi~~ | **DIPUTUSKAN → DEC-009** | — |
| **OD-009** | Formula profit/margin & target achievement | PRD §35 TBD. Belum blokir karena reporting di Tahap 3B. | Tahap 3B |
| **OD-010** | Receipt: dicetak (printer model/interface) atau cukup di layar? | Mempengaruhi UI checkout dan hardware yang perlu dibeli. | Tahap 1 (UI), Tahap 3 (hardware) |
| **OD-011** | Apakah Flutter perlu **penemuan IP server otomatis** (scan subnet), atau cukup DHCP reservation? | **Ditunda oleh user 2 Okt 2026 — tunggu hasil DHCP reservation di SESI 1.** Analisis ada di bawah tabel. | Tahap 1 (opsional) |
| **OD-014** | Bolehkah **operator mendaftarkan member baru** di meja kasir, atau hanya Admin? | PRD §6 memberi akses `customer` hanya kepada Admin/Owner — operator tidak termasuk. Tapi customer yang ingin jadi member di tempat adalah kejadian harian. Sekarang operator hanya bisa mencari & memilih member yang sudah ada; yang belum terdaftar dilayani sebagai Walk-in | Tahap 1 (UI sudah siap), Tahap 3B (Admin) |
| **OD-013** | Ringkasan shift: `rental`/`fnb` dihitung saat item **dibuat** (nilai transaksi) atau saat **dibayar** (uang masuk)? | Keduanya sudah dibedakan di UI, tapi mana yang jadi dasar laporan belum diputuskan. Mempengaruhi laporan harian dan formula profit (OD-009). `cash`/`qris`/`total` tidak terpengaruh — itu selalu uang masuk | Tahap 3B (reporting) |
| **OD-012** | Aplikasi akan **dijual ke beberapa pengguna** dengan nama & logo menyesuaikan, tetap di bawah naungan Cempaka Smart Billing. White-label per-instance, atau multi-tenant satu server? | **Keputusan arsitektur terbesar yang belum ada di PRD.** Menentukan schema DB. Retrofit `tenant_id` setelah ada data produksi sangat mahal. Detail di bawah tabel. | **Tahap 0 (schema)** — walau fiturnya nanti |

**Tidak ada lagi Open Decision yang memblokir Tahap 0.** Billing engine sudah boleh ditulis.

Yang masih menghalangi **Tahap 2**: OD-004 (perilaku warning) dan OD-005 (fakta TV — dicek Sabtu). OD-001 menghalangi penagihan overstay, tapi tidak menghalangi golden path.

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
