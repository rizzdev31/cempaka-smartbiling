# UI/UX SPEC — Flutter Operator & Kotlin TV

Dasar: skill `ui-ux-pro-max` → pattern **Real-Time / Operations**, style **Dark Mode (OLED)**, typography **Fira Sans + Fira Code**.
Baca file ini **sebelum** menyentuh UI apa pun.

---

## 1. Kenapa dark mode, bukan pilihan selera

- Ruang rental PlayStation gelap. UI terang membuat operator terganggu dan terlihat mencolok dari kursi customer.
- Pattern "Real-Time / Operations" (monitoring 6 station, status, timer) memang mengandalkan status color di latar gelap.
- Style ini mencapai **WCAG AAA** dan hemat daya di panel OLED.

**Konsekuensi:** light mode **tidak** dibuat. Jangan buang waktu membangun dua tema di Tahap 1.

---

## 2. Design tokens

### Warna — operator (dark)

| Role | Hex | Pakai untuk |
|---|---|---|
| `bg` | `#0B1120` | background layar |
| `surface` | `#111C33` | card station, panel |
| `surface-raised` | `#1A2742` | modal, bottom sheet, header |
| `border` | `#24324D` | garis pemisah, outline card |
| `primary` | `#3B82F6` | aksi utama, link, selected |
| `on-primary` | `#FFFFFF` | teks di atas primary |
| `accent` | `#F59E0B` | highlight, badge perlu perhatian |
| `text` | `#F1F5F9` | teks utama |
| `text-muted` | `#94A3B8` | label sekunder (minimum 3:1) |
| `danger` | `#EF4444` | destructive, expired, error |

### Status station — wajib konsisten di Flutter, TV, dan Superadmin

| Status | Warna | Ikon | Label |
|---|---|---|---|
| `AVAILABLE` | `#22C55E` hijau | circle-outline | Tersedia |
| `PENDING_PAYMENT` | `#F59E0B` amber | clock-alert | Menunggu Bayar |
| `ACTIVE` | `#3B82F6` biru | play-circle | Bermain |
| `WARNING` | `#FB923C` oranye | alert-triangle | Hampir Habis |
| `EXPIRED` | `#EF4444` merah | x-circle | Habis |
| `CHECKOUT` | `#A855F7` ungu | receipt | Checkout |
| `OFFLINE` (device) | `#64748B` abu | wifi-off | Offline |

> **Aturan `color-not-only`:** status **tidak boleh** dibedakan hanya dengan warna. Setiap card station wajib punya **warna + ikon + teks label**. Operator bisa buta warna, dan di layar gelap biru/ungu sulit dibedakan sekilas.

### Tipografi

| Elemen | Font | Size | Weight |
|---|---|---|---|
| Timer besar (session detail) | Fira Code | 48 | 600 |
| Timer di card station | Fira Code | 28 | 600 |
| Angka uang / total | Fira Code | 18–24 | 500 |
| Judul layar | Fira Sans | 24 | 600 |
| Label card | Fira Sans | 16 | 500 |
| Body | Fira Sans | 16 | 400 |
| Caption | Fira Sans | 14 | 400 |

> **Wajib `tabular figures` (Fira Code / `FontFeature.tabularFigures()`) untuk timer dan uang.** Dengan font proporsional, angka `1` lebih sempit dari `8`, sehingga countdown bergoyang kiri-kanan setiap detik dan terlihat murah. Ini perbedaan paling cepat terlihat antara app amatir dan app profesional.

### Spacing & ukuran

- Grid **8dp**. Nilai yang dipakai: 4 / 8 / 16 / 24 / 32 / 48.
- Touch target minimum **48×48dp** (Material). Tablet dipakai berdiri, sambil terburu-buru.
- Jarak antar target minimum **8dp**.
- Radius: card 12, tombol 8, modal 16.
- Elevation hanya 3 tingkat: flat (`surface`) → card → modal (`surface-raised` + scrim 50%).

---

## 3. Flutter Operator — layout

### Dashboard (landscape tablet, layar utama)

```
┌──────────────────────────────────────────────────────┐
│ Cempaka Billing   ● WS connected   Shift: Budi  ⚙    │  header 64dp
├──────────────────────────────────────────────────────┤
│  ┌────────┐  ┌────────┐  ┌────────┐                  │
│  │  ST01  │  │  ST02  │  │  ST03  │                  │
│  │ ▶ Main │  │ ○ Ready│  │ ⚠ 8:12 │                  │  grid 3×2
│  │ 00:42:15│  │        │  │        │                  │
│  │ Rp 45.000│  │        │  │ Rp 12.000│                │
│  └────────┘  └────────┘  └────────┘                  │
│  ┌────────┐  ┌────────┐  ┌────────┐                  │
│  │  ST04  │  │  ST05  │  │  ST06  │                  │
│  └────────┘  └────────┘  └────────┘                  │
├──────────────────────────────────────────────────────┤
│ F&B Queue (3)                              [+ Mulai] │  bar bawah
└──────────────────────────────────────────────────────┘
```

Aturan:
- **Grid 3×2 landscape**, 2×3 portrait. Enam station harus terlihat **tanpa scroll** — operator melirik, tidak membaca.
- Satu card = satu station. Isi: nama station, status (warna+ikon+label), timer, total open tab berjalan, nama customer kalau ada.
- **Satu primary CTA per layar.** Di dashboard itu tombol "Mulai Sesi". Aksi lain sekunder.
- Badge jumlah di F&B Queue, bersihkan setelah dibuka.
- Indikator WebSocket di header: `connected` / `reconnecting` / `offline`. Operator harus tahu kalau data basi.

### Session Detail

Dua kolom di landscape: kiri = timer + info session + aksi; kanan = Open Tab (rental, F&B, extend, adjustment) + total.

Aksi destruktif (Cancel Session, Void Item) **dipisah secara visual** dari aksi normal, pakai `danger`, dan wajib confirmation dialog.

### F&B Queue

List kartu order. Tiap kartu: station, item, waktu masuk, tombol state tunggal (`Proses` → `Siap` → `Diantar`). Jangan pakai dropdown status — satu tap, bukan tiga.

### Komponen yang harus ada sejak awal

| Komponen | Kenapa |
|---|---|
| `StationCard` | dipakai dashboard + swap picker |
| `CountdownText` | satu-satunya tempat timer dirender |
| `MoneyText` | format rupiah integer, tabular |
| `StatusChip` | warna + ikon + label, satu sumber |
| `ConnectionBanner` | status WS + IP server (dev build) |
| `ConfirmDialog` | semua aksi destruktif |

---

## 4. Performa timer — aturan teknis yang mengikat

Ini penyebab paling umum operator app terasa berat.

**Jangan:** `setState` per `StationCard` setiap detik, atau satu `Timer` per card (6 timer paralel).

**Lakukan:**
1. **Satu** ticker global (`Stream.periodic` 1 detik) di level app.
2. Hanya widget `CountdownText` yang mendengarkan ticker — bukan card, bukan grid, bukan layar.
3. Nilai `end_at` disimpan di state; ticker hanya memicu render ulang teks.
4. Sisa waktu dihitung dengan **server-time offset** (DEC-003), bukan `DateTime.now()` mentah.
5. Ticker **dihentikan** saat app di background.

Hasil: 6 station = 6 `Text` yang di-repaint, bukan 6 subtree.

---

## 5. Animasi & feedback

| Aturan | Nilai |
|---|---|
| Durasi micro-interaction | 150–300ms |
| Feedback tap terlihat | < 100ms |
| Easing | `ease-out` saat masuk, `ease-in` saat keluar |
| Animasi keluar | ~60–70% durasi masuk |
| Hanya animasikan | `transform` & `opacity` |
| Loading > 300ms | skeleton, bukan spinner penuh layar |
| Tombol async | disable + spinner inline saat request jalan |

**Tombol pembayaran wajib disable selama request berjalan.** Ini garis pertahanan pertama terhadap double payment, sebelum `Idempotency-Key`.

Hormati `prefers-reduced-motion` / pengaturan animasi sistem.

---

## 6. Kotlin TV Agent — 10-foot UI

Layar TV dilihat dari 2–3 meter, dikontrol remote (atau tidak sama sekali). Aturan berbeda dari tablet.

| Aturan | Nilai |
|---|---|
| Safe area overscan | padding **5%** dari tiap sisi — TV memotong tepi layar |
| Ukuran teks minimum | 24sp; timer utama 96–144sp |
| Kontras | minimum 7:1; latar `#000000` murni (hemat OLED, kontras maksimum) |
| Jumlah elemen di layar | maksimum 3 blok informasi |
| Warna | hanya status color + putih; tidak ada gradient atau dekorasi |
| Fokus remote | kalau ada elemen fokusable, outline **4dp** jelas |

Layout:

```
┌────────────────────────────────┐
│          (5% safe area)        │
│                                │
│            ST01                │  32sp, text-muted
│                                │
│          00:42:15              │  120sp Fira Code, putih
│                                │
│        ● Sisa 42 menit         │  28sp + ikon status
│                                │
│          (5% safe area)        │
└────────────────────────────────┘
```

Keadaan khusus:
- **Warning 10/5/1 menit:** warna timer berubah ke oranye lalu merah + teks. Jangan berkedip cepat (risiko fotosensitif) — pakai pulsasi halus ≥1 detik per siklus. Perilaku final menunggu **OD-004**.
- **Offline / WS putus:** timer **tetap jalan** (PRD §16). Tampilkan indikator kecil di pojok, jangan menutupi timer. Jangan tampilkan error besar — customer tidak perlu tahu.
- **EXPIRED:** perilaku menunggu **OD-001**. Jangan diputuskan di kode.
- **AVAILABLE:** layar idle, logo + "Tersedia". Jangan layar hitam total — terlihat seperti TV rusak.

---

## 7. Checklist sebelum UI dianggap selesai

### Visual
- [ ] Tidak ada emoji sebagai ikon — pakai SVG (Lucide / Material Symbols), satu set saja
- [ ] Stroke width ikon konsisten
- [ ] Tidak ada hex mentah di widget — semua lewat token tema
- [ ] Timer & uang pakai tabular figures
- [ ] Press state tidak menggeser layout

### Interaksi
- [ ] Semua target ≥48×48dp, jarak ≥8dp
- [ ] Setiap tap punya feedback visual < 100ms
- [ ] Tombol async disable + spinner
- [ ] Aksi destruktif: warna `danger` + terpisah + confirm dialog
- [ ] Status disampaikan dengan warna **+ ikon + teks**

### Layout
- [ ] 6 station terlihat tanpa scroll di landscape
- [ ] Safe area dihormati (tablet notch, TV overscan 5%)
- [ ] Konten tidak tertutup header/bar bawah
- [ ] Ritme spacing 4/8dp konsisten
- [ ] Diuji di landscape **dan** portrait

### Kontras (dark mode)
- [ ] Teks utama ≥4.5:1
- [ ] Teks sekunder ≥3:1
- [ ] Border terlihat, tidak hilang di latar gelap
- [ ] Scrim modal 40–60% hitam

### Aksesibilitas
- [ ] Semua ikon punya `semanticsLabel`
- [ ] Field form punya label terlihat, bukan placeholder saja
- [ ] Error muncul **di bawah field** terkait, menyebut penyebab + cara perbaiki
- [ ] Reduced motion & text scaling tidak merusak layout

### Performa
- [ ] Satu ticker global, bukan satu per card
- [ ] Ticker berhenti saat background
- [ ] List > 50 item di-virtualisasi (`ListView.builder`)
