# UI/UX SPEC — Flutter Operator & Kotlin TV

Acuan visual: **`operator-app/contoh.html`** yang disetujui user (DEC-014).
Panduan struktural: skill `ui-ux-pro-max` → pattern **Real-Time / Operations**, style **Dark Mode (OLED)**.

Baca file ini **sebelum** menyentuh UI apa pun.

---

## 1. Arah visual

**Material 3 dark** dengan aksen **cyan + mint**, permukaan biru-gelap bertingkat, dan **glow halus** pada elemen aktif.

Light mode **tidak** dibuat. Ruang rental gelap; UI terang mengganggu operator dan mencolok dari kursi customer. Jangan buang waktu membangun dua tema.

Kedalaman disampaikan lewat **nada permukaan**, bukan garis tegas di mana-mana. Garis dipakai hemat — hanya saat nada permukaan tidak cukup memisahkan.

---

## 2. Design tokens

Semua ada di `lib/core/theme/tokens.dart`. **Jangan tulis hex mentah di widget.**

### Permukaan — enam tingkat

| Token | Hex | Dipakai untuk |
|---|---|---|
| `surfaceLowest` | `#0A0E18` | sidebar, header, footer |
| `surface` | `#0F131D` | latar area kerja |
| `surfaceLow` | `#171B26` | kartu, panel, modal |
| `surfaceContainer` | `#1C1F2A` | tombol sekunder, bidang di dalam kartu |
| `surfaceHigh` | `#262A35` | hover, garis, badge |
| `surfaceHighest` | `#313540` | track bar progres |

### Teks

| Token | Hex | Pakai untuk |
|---|---|---|
| `onSurface` | `#DFE2F1` | teks utama |
| `onSurfaceVariant` | `#BAC9CC` | teks sekunder |
| `outline` | `#849396` | teks tersier, placeholder |
| `outlineVariant` | `#3B494C` | garis halus, placeholder timer |

### Aksen

| Peran | Token | Hex |
|---|---|---|
| Primary (teks/ikon) | `primary` | `#C3F5FF` |
| Primary (isian) | `primaryContainer` | `#00E5FF` |
| Secondary (sehat/jalan) | `secondary` | `#4EDEA3` |
| Secondary (isian) | `secondaryContainer` | `#00A572` |
| Tertiary (uang) | `tertiaryContainer` | `#FFC681` |
| Tertiary (peringatan) | `tertiaryFixedDim` | `#FFB95F` |
| Error | `error` | `#FFB4AB` |

### Status station — wajib konsisten di Flutter, TV, dan Superadmin

| Status | Token | Ikon | Label |
|---|---|---|---|
| `AVAILABLE` | `primary` cyan | circle-outline | Tersedia |
| `PENDING_PAYMENT` | `tertiary` | schedule | Menunggu Bayar |
| `ACTIVE` | `secondary` mint | play-circle | Bermain |
| `WARNING` | `tertiaryFixedDim` | alert-triangle | Hampir Habis |
| `EXPIRED` | `error` | x-circle | Habis |
| `CHECKOUT` | ungu | receipt | Checkout |
| `OFFLINE` / maintenance | `outline` | wifi-off / build | Offline / Maintenance |

> **Aturan `color-not-only`:** status **tidak boleh** dibedakan hanya dengan warna. Selalu **warna + ikon/titik + teks**. Operator bisa buta warna, dan di layar gelap cyan vs mint sulit dibedakan sekilas.

### Spacing, radius, ukuran

Spacing: `4 / 8 / 14 / 20 / 28 / 40`, gutter `16`, gutter tepi `24`.
Radius: `sm 4` (tombol kecil) · `md 8` (tombol, input, nav) · `lg 12` (kartu) · `modal 16` · `pill`.

| Ukuran | Nilai |
|---|---|
| Target sentuh minimum | **48 dp** |
| Tombol aksi di kartu | **44 dp** — pengecualian yang dicatat, lihat §7 |
| Header | 64 |
| Sidebar penuh / rail | 288 / 76 |
| Breakpoint sidebar penuh | ≥ 1040 |
| Kartu station minimum | 300 × 264 |

### Glow — efek khas desain ini

`AppShadow.glowPrimary` hanya untuk **nav aktif** dan **chip filter terpilih**. Jangan dipakai di mana-mana; begitu semuanya menyala, tidak ada yang menonjol.

---

## 3. Tipografi

Tiga family, masing-masing punya tugas jelas. **Dibundel** di `assets/fonts/` — bukan diunduh runtime.

| Family | Weight | Tugas |
|---|---|---|
| **Space Grotesk** | 500, 600, 700 | judul, kode station |
| **Plus Jakarta Sans** | 400–700 | body, label |
| **JetBrains Mono** | 400–700 | timer, uang, label teknis |

Skala di `AppTypography`: `displayLg 44` · `timerCard 34` · `headlineLg 32` · `headlineMd 24` · `headlineSm 18` · `bodyLg 16` · `bodyMd 14` · `bodySm 12` · `labelLg 14` · `labelMd 12` · `labelSm 11` · `moneyLg 20` · `money 14` · `moneySm 12`.

> **Tabular figures wajib** untuk timer dan uang. Tanpa itu countdown bergoyang kiri-kanan setiap detik karena `1` lebih sempit dari `8` — perbedaan paling cepat terlihat antara app amatir dan profesional.

### Kenapa dibundel, bukan `google_fonts`

Paket `google_fonts` mengunduh font saat runtime. App ini dipakai di jaringan lokal yang bisa tanpa internet (DEC-002), jadi build pertama di lokasi akan menampilkan font sistem dan tampilannya berbeda dari yang dirancang.

Google Fonts hanya menyediakan **variable font** untuk ketiganya. Variable font di Flutter butuh `fontVariations` di setiap `TextStyle` — mudah terlewat di satu widget. Jadi static instance di-generate:

```bash
pip install fonttools
# lihat assets/fonts/README.md untuk skripnya
```

Lisensi OFL ada di `assets/fonts/OFL-*.txt` dan **wajib tetap disertakan**.

---

## 4. Shell: sidebar + header + area kerja

```
┌────────────┬──────────────────────────────────────────┐
│ ● Cempaka  │  Monitor Stasiun   [4/6] [Rp 45.000] ⟳   │ header 64
│ Rental     ├──────────────────────────────────────────┤
│            │ (Semua 6) (Bermain 2) (Tersedia 2) 🔍    │ bar filter
│ MENU UTAMA ├──────────────────────────────────────────┤
│ ▸ Monitor  │  ┌──────┐ ┌──────┐ ┌──────┐              │
│   F&B  (2) │  │ ST01 │ │ ST02 │ │ ST03 │              │ grid 3×2
│   Shift    │  └──────┘ └──────┘ └──────┘              │
│   TV   (1) │  ┌──────┐ ┌──────┐ ┌──────┐              │
│   Setting  │  └──────┘ └──────┘ └──────┘              │
│            ├──────────────────────────────────────────┤
│ [RS] Rian  │  Shift Rian · buka 15:00   2 antrian F&B │ strip shift
├────────────┴──────────────────────────────────────────┤
│ Cempaka Smart Billing                          v0.1.0 │ footer
└───────────────────────────────────────────────────────┘
```

- **Sidebar** = navigasi utama. Lima tujuan, badge untuk yang menuntut tindakan.
- **Adaptif:** di bawah 1040 px menyusut jadi rail ikon (aturan `adaptive-navigation`). Pada rail, badge jadi titik — angkanya tidak akan terbaca.
- **Tombol tutup shift dipisah** dari daftar navigasi (aturan `destructive-nav-separation`).
- **`IndexedStack`**, bukan rebuild per pindah: posisi scroll dan filter tidak hilang (aturan `state-preservation`).
- Enam station harus terlihat **tanpa scroll**. Kolom ditentukan **lebar yang tersedia**, bukan orientasi perangkat — shell sudah memakan sebagian lebar.

---

## 5. Kartu station

```
┌────────────────────────────────┐
│ ST01  PS5 VIP      ● Bermain   │  header, garis bawah
├────────────────────────────────┤
│ 00:37:50          Sisa Waktu   │  timer dominan
│ ▓▓▓▓▓▓▓▓▓▓▓░░░░                │  bar proporsi waktu
│ Mulai 20:15    Selesai 22:15   │
│ ┌────────────────────────────┐ │
│ │ Budi Santoso    Rp 20.000  │ │  blok customer (permukaan cekung)
│ │ Sisa 38 menit              │ │
│ └────────────────────────────┘ │
│ [+30m] [+1j] [F&B] [  Bayar  ] │  aksi cepat
└────────────────────────────────┘
```

Aturan:
- **Aksi cepat di kartu** supaya operator tidak membuka detail sesi untuk pekerjaan yang paling sering dilakukan.
- **Bar proporsi waktu** ikut warna status — saat mendekati habis, header, timer, dan bar berubah serentak. Operator menangkapnya tanpa membaca angka.
- Station kosong: `--:--` redup + tarif acuan + satu CTA **Mulai Sesi Baru** (aturan `primary-action`).
- Maintenance: ikon + "Sedang diperbaiki" saja. Teknisi/tiket/estimasi **tidak ditampilkan** — datanya tidak ada di kontrak, dan memalsukannya membuat operator mengandalkan informasi yang tidak pernah ada (OD-016).
- Kartu **tidak** berlangganan ticker. Hanya timer, bar, label status, dan sisa waktu yang rebuild per detik.

---

## 6. Performa timer — aturan yang mengikat

Ini penyebab paling umum operator app terasa berat.

**Jangan:** `setState` per kartu setiap detik, atau satu `Timer` per kartu.

**Lakukan:**
1. **Satu** ticker global (`AppTicker`) di level app.
2. Hanya widget kecil yang mendengarkannya — bukan kartu, bukan grid, bukan layar.
3. `end_at` disimpan di state; ticker hanya memicu render ulang teks.
4. Sisa waktu dihitung dengan **server-time offset** (DEC-003), bukan `DateTime.now()` mentah.
5. Ticker **berhenti** saat app di background.

---

## 7. Interaksi & animasi

| Aturan | Nilai |
|---|---|
| Durasi micro-interaction | 150–300 ms (`AppMotion`) |
| Feedback tap terlihat | < 100 ms |
| Easing | `easeOut` masuk, `easeIn` keluar |
| Animasi keluar | ~60–70% durasi masuk |
| Hanya animasikan | `transform`, `opacity`, warna |
| Loading > 300 ms | skeleton, bukan spinner penuh layar |
| Tombol async | disable + spinner inline |

**Tombol yang mengubah uang wajib disable selama request** — garis pertahanan pertama terhadap pembayaran ganda, sebelum `Idempotency-Key`.

### Target sentuh: pengecualian yang dicatat

Minimum 48 dp, **kecuali** tombol aksi di kartu station yang 44 dp.

`contoh.html` memakai ~34 px dengan asumsi presisi mouse. 34 px terlalu kecil untuk aksi yang mengubah uang di tablet. 48 px membuat enam kartu tidak muat tanpa scroll pada tablet 1280×800. **44 px** (minimum sentuh iOS) adalah kompromi yang disengaja — bukan kelalaian.

### Konfirmasi tetap ada walau "aksi cepat"

Tombol `+30m` / `+1j` memunculkan konfirmasi berisi perkiraan harga. Salah tap `+1j` menagih customer satu jam yang tidak diminta, dan kontrak **tidak punya** jalur pembatalan untuk itu. Satu tap tambahan lebih murah daripada salah tagih.

---

## 8. Jangan tampilkan apa yang tidak ada

Dari `contoh.html`, empat elemen **tidak** diambil karena datanya tidak ada:

| Tidak diambil | Alasan |
|---|---|
| Bel notifikasi | tidak ada sistem notifikasi. Slotnya diisi indikator koneksi yang nyata |
| Badge terminal `POS-01` | tidak ada konsep terminal; DEC-013 satu kasir |
| "Auto Refresh Aktif" | tidak ada auto-refresh — menampilkannya jadi klaim palsu |
| Teknisi & nomor tiket | tidak ada entity-nya (OD-016) |

Prinsipnya: **UI yang menjanjikan data yang tidak ada lebih buruk daripada UI yang kosong.** Operator akan mengandalkannya, lalu kehilangan kepercayaan pada seluruh layar.

Layar yang datanya masih palsu (Status TV, sebelum Tahap 2) memuat **peringatan jujur** di dalamnya.

---

## 9. Kotlin TV Agent — 10-foot UI

Layar TV dilihat dari 2–3 meter, dikontrol remote atau tidak sama sekali.

| Aturan | Nilai |
|---|---|
| Safe area overscan | padding **5%** tiap sisi — TV memotong tepi layar |
| Teks minimum | 24 sp; timer utama 96–144 sp |
| Kontras | minimum 7:1; latar `#000000` murni |
| Elemen di layar | maksimum 3 blok informasi |
| Warna | status color + putih; tanpa gradient atau dekorasi |
| Fokus remote | outline **4 dp** jelas |

```
┌────────────────────────────────┐
│          (5% safe area)        │
│            ST01                │  32sp
│          00:42:15              │  120sp JetBrains Mono
│        ● Sisa 42 menit         │  28sp + ikon
│          (5% safe area)        │
└────────────────────────────────┘
```

- **Warning 10/5/1 menit:** timer berubah oranye lalu merah + teks. Jangan berkedip cepat (risiko fotosensitif) — pulsasi ≥1 detik per siklus. Perilaku final menunggu **OD-004**.
- **Offline:** timer **tetap jalan** (PRD §16). Indikator kecil di pojok, jangan menutupi timer dan jangan tampilkan error besar — customer tidak perlu tahu.
- **EXPIRED:** menunggu **OD-001**. Jangan diputuskan di kode.
- **AVAILABLE:** logo + "Tersedia". Jangan layar hitam total — terlihat seperti TV rusak.

---

## 10. Checklist sebelum UI dianggap selesai

### Visual
- [ ] Tidak ada emoji sebagai ikon — satu set ikon saja (Material Symbols)
- [ ] Tidak ada hex mentah di widget — semua lewat `AppColors`
- [ ] Timer & uang pakai tabular figures
- [ ] Press state tidak menggeser layout
- [ ] Glow hanya di nav aktif & chip terpilih

### Interaksi
- [ ] Target sentuh ≥48 dp (kecuali aksi kartu 44 dp, dicatat)
- [ ] Jarak antar target ≥8 dp
- [ ] Setiap tap punya feedback < 100 ms
- [ ] Tombol async disable + spinner
- [ ] Aksi yang mengubah uang punya konfirmasi
- [ ] Aksi destruktif pakai warna `error`, terpisah, + confirm
- [ ] Status disampaikan warna **+ ikon + teks**

### Layout
- [ ] Enam station terlihat tanpa scroll di layar lebar
- [ ] Kartu tidak overflow pada 300 × 264 — **ada test-nya**
- [ ] Sidebar menyusut jadi rail di bawah 1040 px
- [ ] Safe area dihormati (tablet notch, TV overscan 5%)
- [ ] Ritme spacing konsisten
- [ ] Diuji di landscape **dan** portrait

### Kontras
- [ ] Teks utama ≥4.5:1
- [ ] Teks sekunder ≥3:1
- [ ] Garis terlihat, tidak hilang di latar gelap
- [ ] Scrim modal 40–60% hitam

### Aksesibilitas
- [ ] Ikon punya `semanticsLabel`
- [ ] Field punya label terlihat, bukan placeholder saja
- [ ] Error muncul **di bawah field** terkait, menyebut penyebab + cara perbaiki
- [ ] Reduced motion & text scaling (sampai 1.3×) tidak merusak layout — **ada test-nya**

### Performa
- [ ] Satu ticker global, bukan satu per kartu
- [ ] Ticker berhenti saat background
- [ ] Pencarian di-debounce
- [ ] List > 50 item di-virtualisasi
