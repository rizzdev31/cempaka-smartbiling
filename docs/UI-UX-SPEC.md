# UI/UX SPEC — Flutter Operator & Kotlin TV

Acuan: perkakas operasional yang dipakai berjam-jam (Linear, Stripe Dashboard, aplikasi kasir yang benar-benar dipakai) — **bukan** halaman pemasaran.
Panduan struktural: skill `ui-ux-pro-max` → pattern **Real-Time / Operations**.
Keputusan: **DEC-016** (tema terang) yang meng-override DEC-014.

Baca file ini **sebelum** menyentuh UI apa pun.

---

## 1. Arah visual: terang, datar, padat

**Operator app: tema terang.** Dark mode tidak lagi dibuat.

> Versi sebelumnya memilih gelap dengan alasan ruang rental gelap dan layar terang mencolok dari kursi customer. Alasan itu tidak terbantahkan, hanya dikesampingkan — user sudah melihatnya di perangkat dan menilai ruangannya sendiri. Lengkapnya di DEC-016.

**Layar TV tetap hitam.** TV dilihat dari 2–3 meter di ruang gelap; itu masalah yang berbeda sama sekali dari tablet di meja kasir. Yang tetap sama: **makna warna status**.

### Tiga aturan yang menjaganya tidak terlihat seperti template

1. **Lapisan dari nada putih, bukan dari shadow.** Kanvas abu sangat muda, kartu putih, garis setipis mungkin. Shadow **hanya** untuk yang benar-benar melayang: modal, bottom sheet, popup.
2. **Satu warna aksen.** Teal untuk aksi. Warna lain hanya status, dan setiap status selalu disertai ikon dan teks.
3. **Radius kecil dan berbeda sesuai peran.** Semuanya membulat seragam besar adalah ciri yang paling cepat terbaca dari UI yang tidak dirancang.

### Yang dilarang

| Jangan | Kenapa |
|---|---|
| Gradasi apa pun | Tidak ada satu pun `LinearGradient` di aplikasi. Gradasi diagonal pada logo/tombol adalah penanda paling cepat terbaca |
| Glow | Nav aktif memakai penanda tepi, bukan cahaya |
| Shadow pada kartu biasa | Garis + nada sudah memisahkan |
| Blur / glassmorphism | Tidak ada `BackdropFilter` |
| Emoji sebagai ikon | Satu set ikon saja |
| Ikon di dalam lingkaran berwarna | Ikon polos |
| Lebih dari satu aksen | Teal saja |
| Kotak di dalam kotak di dalam kotak | Blok dalam kartu memakai bidang cekung |

---

## 2. Design tokens

Semua ada di `lib/core/theme/tokens.dart`. **Jangan tulis hex mentah di widget.**

### Lapisan putih

| Token | Hex | Dipakai untuk |
|---|---|---|
| `surfaceLowest` | `#FFFFFF` | sidebar, header, footer — chrome |
| `surface` | `#F6F7F9` | kanvas area kerja |
| `surfaceLow` | `#FFFFFF` | kartu, panel |
| `surfaceContainer` | `#F1F3F5` | bidang cekung: input, tombol sekunder, blok dalam kartu |
| `surfaceHigh` | `#E3E6EA` | **garis tepi kartu** |
| `surfaceHighest` | `#E8EBEF` | track bar progres |

> Nama token dipertahankan dari tema gelap supaya perubahan tidak menyentuh puluhan widget. Perannya yang berubah: `lowest` kini chrome paling putih, bukan paling gelap.

### Teks

| Token | Hex | Kontras di kartu | Pakai untuk |
|---|---|---|---|
| `onSurface` | `#15181D` | 17,8:1 | teks utama |
| `onSurfaceVariant` | `#5B6472` | 6,0:1 | teks sekunder |
| `outline` | `#8A939F` | 3,1:1 | **hanya** label pendukung |
| `outlineVariant` | `#D7DCE2` | — | pemisah dalam kartu |

`outline` sengaja di bawah 4,5:1. Target untuk peran itu 3:1, dan ia **tidak pernah** dipakai untuk informasi yang harus dibaca.

### Aksen & status

Kolom kontras = di atas kartu putih · di atas bidang cekung `#F1F3F5`. **Keduanya wajib ≥4,5:1**, karena kartu read-only (order dibatalkan, sesi selesai) berlatar bidang cekung — di situlah slate `#64748B` dulu gagal.

| Peran | Token | Hex | Kontras |
|---|---|---|---|
| Aksi, Tersedia | `primary` | `#0E7490` | 5,4 · 4,8 |
| Bermain | `secondary` | `#047857` | 5,5 · 4,9 |
| Hampir habis, uang | `tertiaryContainer` | `#B45309` | 5,0 · 4,5 |
| Habis, error | `error` | `#B91C1C` | 6,5 · 5,8 |
| Menunggu bayar | `statusPendingPayment` | `#4338CA` | 7,9 · 7,1 |
| Checkout | `statusCheckout` | `#7E22CE` | 7,0 · 6,3 |
| Offline, maintenance, dibatalkan | `statusOffline` | `#475569` | 7,6 · 6,8 |

Teal adalah garis keturunan cyan dari `contoh.html`, digelapkan agar terbaca di atas putih. **Cyan neon `#00E5FF` tidak bisa memenuhi kontras apa pun pada latar terang** — memaksakannya berarti teks yang tidak terbaca.

"Menunggu bayar" sengaja indigo, jauh dari amber, supaya tidak tertukar dengan "hampir habis": keduanya menuntut tindakan berbeda.

> **Aturan `color-not-only`:** status **tidak boleh** dibedakan hanya dengan warna. Selalu **warna + titik/ikon + teks**.

### Radius — berbeda sesuai peran

`sm 4` badge & chrome data · `md 6` tombol, input, nav · `lg 10` kartu · `modal 12` · `pill` **hanya** chip filter dan titik status.

### Ukuran

| | Nilai |
|---|---|
| Target sentuh minimum | **48 dp** |
| Tombol aksi di kartu | **44 dp** — pengecualian, lihat §7 |
| Header | 60 |
| Sidebar penuh / rail | 268 / 72 |
| Breakpoint sidebar penuh | ≥ 1040 |
| Penanda nav aktif | 3 |
| Kartu station minimum | 300 × 264 — tingginya **ikut skala teks** (`AppSize.stationCardMinHeightFor`, dibatasi 1,4×) |

### Shadow — dipakai sangat hemat

`card` nyaris tidak terlihat (pemisah utamanya garis) · `panel` chrome di tepi layar · `modal` di sini shadow memang bertugas.

### Dekorasi bersama

`AppDecoration.card()` · `.inset()` · `.selected()` — dikumpulkan supaya tepi kartu konsisten. Pada tema terang, perbedaan satu nada garis langsung terlihat.

### Verifikasi kontras & disiplin visual

31 pasangan dihitung dengan rumus WCAG. Semuanya memenuhi target.

Dua tempat, satu sumber warna — keduanya membaca `AppColors`, jadi palet tidak bisa menyimpang:

| | Peran |
|---|---|
| `test/theme_discipline_test.dart` | **yang mengikat.** Ikut jalan di `flutter test` |
| `docs/tools/contrast.py` | tabel untuk dibaca saat menyetel warna; keluar kode 1 kalau gagal |

```bash
flutter test test/theme_discipline_test.dart   # mengikat
python docs/tools/contrast.py                  # laporan
```

Test yang sama juga menegakkan daftar larangan di §1 dengan membaca source `lib/`: menolak gradasi apa pun, `Color(0x` di luar `tokens.dart`, `BoxShadow(` di luar `tokens.dart`, dan sisa `Brightness.dark`.

> Menambah warna baru? Tambahkan token di `tokens.dart` **lalu daftarkan pasangannya di test itu.** Warna yang tidak terdaftar tidak terverifikasi — dan `statusOffline` membuktikan warna yang "kelihatan cukup gelap" bisa gagal.

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
- Enam station harus terlihat **tanpa scroll** pada skala teks normal. Kolom ditentukan **lebar yang tersedia**, bukan orientasi perangkat — shell sudah memakan sebagian lebar.
- Kalau ruangnya kurang — layar pendek **atau** teks diperbesar — yang di-scroll adalah grid-nya, bukan kartunya yang dipaksa mengecil sampai isinya overflow.

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
- [ ] Tidak ada `LinearGradient`, glow, atau `BoxShadow` di luar modal/sheet/popup

### Interaksi
- [ ] Target sentuh ≥48 dp (kecuali aksi kartu 44 dp, dicatat)
- [ ] Jarak antar target ≥8 dp
- [ ] Setiap tap punya feedback < 100 ms
- [ ] Tombol async disable + spinner
- [ ] Aksi yang mengubah uang punya konfirmasi
- [ ] Aksi destruktif pakai warna `error`, terpisah, + confirm
- [ ] Status disampaikan warna **+ ikon + teks**

### Layout
- [ ] Enam station terlihat tanpa scroll di layar lebar — **pada skala teks normal.** Teks diperbesar → kartu tumbuh dan grid di-scroll; keterbacaan menang atas kepadatan
- [ ] Kartu tidak overflow pada 300 × 264 — **ada test-nya**
- [ ] Shell tidak overflow di rentang lebar 411–1280, termasuk tepat di kedua sisi breakpoint 1040 dan 620 — **ada test-nya** (`shell_overflow_test.dart`)
- [ ] Sidebar menyusut jadi rail di bawah 1040 px
- [ ] Safe area dihormati (tablet notch, TV overscan 5%)
- [ ] Ritme spacing konsisten
- [ ] Diuji di landscape **dan** portrait

### Kontras
- [ ] `python docs/tools/contrast.py` lolos semua
- [ ] Teks utama ≥4.5:1, teks pendukung ≥3:1
- [ ] Garis kartu masih terlihat di atas kanvas putih
- [ ] Scrim modal 40–60% hitam

### Aksesibilitas
- [ ] Ikon punya `semanticsLabel`
- [ ] Field punya label terlihat, bukan placeholder saja
- [ ] Error muncul **di bawah field** terkait, menyebut penyebab + cara perbaiki
- [ ] Text scaling sampai 1,3× tidak merusak layout — **ada test-nya** (`shell_overflow_test.dart`)
- [ ] Reduced motion tidak merusak layout — **belum ada test-nya**

### Performa
- [ ] Satu ticker global, bukan satu per kartu
- [ ] Ticker berhenti saat background
- [ ] Pencarian di-debounce
- [ ] List > 50 item di-virtualisasi
