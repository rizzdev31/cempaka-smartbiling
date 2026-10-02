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

**Tidak ada lagi Open Decision yang memblokir Tahap 0.** Billing engine sudah boleh ditulis.

Yang masih menghalangi **Tahap 2**: OD-004 (perilaku warning) dan OD-005 (fakta TV — dicek Sabtu). OD-001 menghalangi penagihan overstay, tapi tidak menghalangi golden path.

---

## Open Decisions dari PRD §35 yang masih berlaku

Spesifikasi VPS · domain & subdomain · exact TV lock/unlock mechanism · Device Owner/Lock Task/Accessibility/HDMI behavior · booking grace period & late arrival · booking cancellation/refund · Midtrans setup/webhook/MDR/settlement · backup retention & secondary storage · guest bandwidth policy · notification provider & consent.
