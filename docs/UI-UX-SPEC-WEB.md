# UI/UX SPEC — Admin Web (Laravel)

> Permukaan **ketiga**, dengan spec sendiri. Tablet dan TV tetap memakai
> `UI-UX-SPEC.md` dan tidak terpengaruh dokumen ini.
>
> Sumber bentuk: `backend/resources/uireference.html` (dashboard SaaS yang
> diberikan user, 11 Okt 2026). Sumber warna: merek yang sudah dipakai tablet
> dan TV. Keputusannya di **DEC-042**.

---

## 1. Kenapa web punya spec sendiri

`UI-UX-SPEC.md` melarang gradasi, shadow pada kartu, ikon di dalam lingkaran
berwarna, dan radius besar yang seragam. Referensi yang diberikan user
memakai **keempatnya**.

Larangan itu tidak salah — ia lahir dari layar tablet 10 inci yang penuh
kartu station, tempat setiap hiasan mengambil ruang dari informasi, dan dari
operator yang memakainya delapan jam sehari. Admin Web adalah hal lain: dibuka
di layar lebar, beberapa menit sekali, oleh satu orang yang mencari angka.
Di sana satu kartu sorot dan chip ikon justru memberi halaman titik masuk.

**Yang tetap berlaku di ketiga permukaan:**

| Aturan | Dari |
|---|---|
| Makna warna status sama persis | `UI-UX-SPEC.md` §1 |
| `color-not-only` — status selalu warna + ikon/titik + teks | §2 |
| Teks kecil ≥ 4,5:1 | §2 |
| Angka bertabulasi untuk uang | §3 |
| Font di-self-host, tidak diunduh runtime | §3, DEC-002 |

---

## 2. Token

Semua di `backend/resources/css/app.css` dalam blok `@theme` Tailwind v4.

> **Referensinya Tailwind v3 CDN** dengan `tailwind.config` berbentuk objek
> JS. Repo ini v4, yang konfigurasinya CSS-first. Konfigurasi referensi
> **tidak bisa disalin apa adanya** — sudah diterjemahkan.

### Lapisan

Tiga tingkat, dari halaman ke kartu. Inilah yang memisahkan bidang — bukan
shadow.

| Token | Hex | Peran |
|---|---|---|
| `canvas` | `#e5e9f0` | latar halaman |
| `shell` | `#f8fafc` | bungkus konten |
| `card` | `#ffffff` | kartu |
| `inset` | `#f1f3f7` | bidang cekung: input, tombol tersier, blok dalam kartu |
| `track` | `#e2e8f0` | track bar & gauge — tidak pernah berisi teks |

### Teks

| Token | Hex | Di atas kartu | Pakai untuk |
|---|---|---|---|
| `ink` | `#15181d` | 17,8:1 | teks utama |
| `ink-soft` | `#5b6472` | 6,0:1 | label, teks sekunder |
| `ink-faint` | `#8e9caa` | **2,8:1** | ⚠️ **dekorasi saja** |

> **`ink-faint` gagal AA, dan itu disengaja.** Referensi memakai `gray-400`
> untuk semua label kecilnya. Nada itu tidak terbaca pada 11px, jadi di sini
> ia hanya untuk yang tidak perlu dibaca: gridline, tick sumbu non-aktif,
> ikon dekoratif. Apa pun yang dibaca manusia memakai `ink-soft`.
>
> `WebDesignTokensTest` mengunci ini dari dua sisi: nilainya tidak boleh
> diam-diam digelapkan sampai "lulus", dan peringatannya harus tetap ada.

### Aksen — teal Amor

Nomornya mengikuti **peran di referensi**, bukan skala Tailwind: `600` =
aksi utama, `700` = hover. Dengan begitu kelas referensi diterjemahkan
satu-per-satu (`bg-blue-600` → `bg-brand-600`).

| Token | Hex | Sama dengan |
|---|---|---|
| `brand-50` | `#ecfeff` | — |
| `brand-100` | `#cffafe` | — |
| `brand-500` | `#0891b2` | — |
| `brand-600` | `#0e7490` | **`AppColors.primary` di Flutter** |
| `brand-700` | `#155e75` | **`AppColors.primaryFixedDim`** |
| `pitch` | `#181d27` | hitam-pekat referensi (chip, pill nav aktif) |

Teal Amor ternyata persis skala `cyan` Tailwind, jadi web dan tablet memakai
warna yang **sama benar**, bukan yang mirip. `test_aksen_web_sama_persis_dengan_tablet`
membaca `tokens.dart` dan gagal kalau keduanya menyimpang.

### Status — makna wajib sama dengan tablet

| Status sesi | Token | Hex |
|---|---|---|
| `ACTIVE` bermain | `playing` | `#047857` |
| `WARNING` hampir habis | `warning` | `#b45309` |
| `EXPIRED` waktu habis | `danger` | `#b91c1c` |
| `PENDING_PAYMENT` | `awaiting` | `#4338ca` |
| `CHECKOUT` | `checkout` | `#7e22ce` |
| `CANCELLED`, offline | `idle` | `#475569` |

Masing-masing punya pasangan `-surface` untuk latar badge. Semua pasangan
≥4,5:1, diverifikasi test.

> Status yang **tidak dikenal** tidak diterjemahkan jadi "netral" diam-diam.
> `x-status-pill` menampilkannya mentah dengan nada waspada — supaya status
> baru di backend terlihat, bukan lolos tanpa diperhatikan.

### Radius — besar, tapi tetap berbeda per peran

`shell 32` · `card 24` · `block 16` · `chip 6` · tombol & badge `pill`

Jauh lebih membulat daripada tablet (4/6/10/12), dan itu ciri paling menonjol
dari referensi. Yang **tidak** diambil: satu radius untuk segalanya — itu
yang membuat UI terbaca sebagai tidak dirancang.

### Shadow

| Token | Nilai | Pakai |
|---|---|---|
| `card` | `0 4px 20px -2px rgb(18 38 63 / .04)` | kartu — nyaris tak terlihat, hanya mengangkat dari shell |
| `tooltip` | `0 12px 30px -4px rgb(18 38 63 / .12)` | tooltip, popup |
| `brand` | `0 8px 20px -6px rgb(14 116 144 / .35)` | tombol & kartu aksi |

---

## 3. Tipografi

**Satu family: Plus Jakarta Sans**, bobot 400/500/600/700/**800**. Ditetapkan
user. Berbeda dari tablet yang memakai tiga family — web tidak punya timer
berdetak maupun layar 10-foot, jadi tidak ada tugas yang menuntut Space
Grotesk atau JetBrains Mono.

| Bobot | Pakai |
|---|---|
| 800 `extrabold` | angka metrik besar, judul halaman |
| 700 `bold` | judul kartu, total di tabel |
| 600 `semibold` | tombol, header tabel |
| 500 `medium` | label |
| 400 | body |

### ⚠️ `@fonts` wajib ada di `<head>`

`@vite` **tidak** menautkan CSS font — berkas itu punya manifest sendiri
(`fonts-manifest.json`) dan hanya keluar lewat direktif `@fonts`.

Tanpanya halaman tetap tampil wajar, hanya memakai font sistem. Kegagalan
yang nyaris tidak terlihat kecuali dibandingkan berdampingan — dan justru
karena itu bisa bertahan berbulan-bulan. Dikunci
`test_layout_memanggil_direktif_fonts`.

### Uang

Selalu lewat `<x-rupiah :value="..." />`, jangan `number_format` di view.
Komponen itu menempelkan `tabular` dan format Indonesia (titik ribuan).
Tanpa angka bertabulasi, kolom Total bergoyang kiri-kanan antar baris karena
`1` lebih sempit dari `8`.

---

## 4. Komponen

Di `resources/views/components/`.

| Komponen | Guna |
|---|---|
| `<x-layouts.app>` | shell: nav pill, aksi header, bungkus `rounded-shell` |
| `<x-card>` | kartu putih |
| `<x-metric>` | kartu metrik; `highlight` membuat versi gradasi |
| `<x-icon-chip>` | lingkaran 40px berisi ikon |
| `<x-badge>` | pill delta & label pendek |
| `<x-status-pill>` | status sesi — titik + teks |
| `<x-pill>` | tombol pill: `primary` / `secondary` / `tertiary` |
| `<x-gauge>` | meter setengah lingkaran |
| `<x-rupiah>` | uang |
| `<x-icon>` | satu set ikon inline SVG |
| `<x-delta>` | badge perubahan terhadap kemarin |

Halaman login (`auth/login.blade.php`) **tidak** memakai `<x-layouts.app>`:
layout itu berisi nav, tombol notifikasi, dan avatar — semuanya menuntut user
yang sudah login. Memaksakannya berarti menaruh chrome yang tidak bisa dipakai
di depan orang yang belum boleh memakainya.

### ⛔ Responsif: tiap `<td>` wajib punya `data-label`

Tabel memakai `table-stacked`. Di bawah **48rem** setiap baris berubah jadi
blok, dan nama kolom pindah ke kiri tiap nilai lewat `data-label`:

```blade
<table class="w-full text-left text-xs table-stacked">
    ...
    <td data-label="Total" class="py-3 px-3 md:text-right">…</td>
```

Sel yang lupa labelnya tampil **tanpa keterangan** — dan hanya di layar
sempit, jadi lolos dari siapa pun yang mengujinya di laptop.
`test_setiap_sel_tabel_punya_data_label` menolaknya. Pengecualiannya hanya
`<td colspan>`, yaitu baris keadaan kosong yang memang dibiarkan utuh.

Alasan tidak memakai scroll mendatar: kolom paling kanan — Total, Status —
justru yang dicari, dan di tabel yang digeser ia selalu di luar pandangan.

### ⛔ Penuh layar — jangan pasang pagar lebar

Admin Web memakai **seluruh lebar layar**. Tidak ada `max-w-*` di layout,
dan `test_layout_penuh_tidak_dikunci_lebar_tetap` menolaknya kalau kembali.

Versi pertama memakai `max-w-[1240px] mx-auto` — pola "kartu melayang" dari
referensi. Wajar untuk satu halaman contoh, salah untuk alat kerja: di
monitor 1920px, **680px dibiarkan kosong** sementara tabel di tengah tetap
harus digeser.

Yang menjaga keterbacaan tanpa pagar:

| | |
|---|---|
| Padding bertingkat | `px-4` → `sm:px-6` → `lg:px-8` → `2xl:px-12` |
| Grid **menambah kolom** | `grid-cols-[repeat(auto-fit,minmax(15rem,1fr))]` — bukan merentangkan kartu |
| Header `sticky top-0` | tabel bisa 50 baris; tanpa ini, pindah halaman dari baris terbawah berarti menggulir balik ke puncak |

Halaman **login** adalah pengecualian: tetap di tengah dengan `max-w-sm`.
Form selebar layar tidak membuatnya lebih mudah diisi.

### Breakpoint yang dipakai

| Lebar | Perilaku |
|---|---|
| < 640 (`sm`) | satu kolom · nama rental disembunyikan · padding 16px · tabel bertumpuk |
| < 768 (`md`) | tabel masih bertumpuk · judul dan aksi menumpuk |
| < 1024 (`lg`) | nama & role user disembunyikan dari header |
| < 1280 (`xl`) | nav pindah ke **baris kedua**, menggeser mendatar |
| ≥ 1536 (`2xl`) | padding tepi naik ke 48px |

Navigasi **menggeser mendatar**, tidak pernah disembunyikan. Versi pertama
memakai `hidden lg:flex` — di tablet tidak ada navigasi sama sekali, dan
satu-satunya jalan antar halaman adalah tombol Back. Geser mendatar dipilih
daripada hamburger karena tidak butuh JavaScript dan tidak punya keadaan
terbuka/tertutup yang bisa macet.

Nav dirender **dua kali** (baris atas ≥1280, baris kedua di bawahnya), jadi
isinya ada di `<x-nav-link>`. Dua salinan akan melenceng pada perubahan
pertama, dan yang melenceng adalah yang jarang dilihat.

### Cara memeriksa responsif — diukur, bukan dilihat

```js
// 8 halaman × 5 lebar. Dua hal, keduanya perlu:
d.scrollWidth > d.clientWidth          // meluber mendatar?
main.getBoundingClientRect().width     // benar-benar memakai ≥90% layar?
```

Pemeriksaan kedua itu yang penting. Tanpanya, halaman yang masih terkunci
di tengah tetap "lulus" karena memang tidak meluber.

### ⛔ Kelas warna ditulis utuh, jangan dirangkai

```blade
{{-- SALAH: Tailwind tidak pernah membuat CSS-nya --}}
<span class="bg-{{ $nada }}-surface text-{{ $nada }}">

{{-- BENAR --}}
@php $nada = $online ? 'bg-playing-surface text-playing' : 'bg-danger-surface text-danger'; @endphp
<span class="{{ $nada }}">
```

Tailwind v4 memindai **kelas literal** di berkas source. Kelas yang baru
terbentuk saat render tidak pernah muncul sebagai teks, jadi CSS-nya tidak
dibuat — dan badge tampil tanpa warna sama sekali, hanya di produksi.

### ⛔ Angka perubahan harus dihitung, bukan dituliskan

`<x-metric>` menerima badge lewat **slot**, bukan prop string:

```blade
<x-slot:badge><x-delta :now="$hariIni" :before="$kemarin" /></x-slot:badge>
```

Versi pertama menerimanya sebagai string, dan string itu diisi `"↑ 12,5%"`
dari data contoh. Angka mati itu **bertahan** setelah datanya jadi nyata dan
mengklaim pertumbuhan di atas nol sesi. Slot memaksa angkanya dihitung.

`<x-delta>` **diam kalau pembandingnya nol**. Persentase perubahan dari nol
tidak punya arti, tapi badge-nya tetap terlihat sah — dan "↑ 100%" di hari
pertama akan dibaca sebagai pertumbuhan.

Dikunci dua test dari dua arah: halaman kosong tidak boleh memuat `↑`/`↓`
sama sekali, dan halaman dengan data kemarin **wajib** memuatnya.

### Dua hal yang diperbaiki dari referensi

**Gauge.** Referensi menggambar lingkaran penuh, memutarnya 180°, lalu
memotong separuhnya dengan container yang lebih pendek — dan mengisinya
dengan `clipPath` ber-path hardcode, sehingga jarumnya menunjuk 70,8% berapa
pun angkanya. Di sini `viewBox` dipaskan ke setengah lingkaran dan panjang
terisi dihitung dari panjang busur, jadi nilainya benar-benar menggerakkan
meter.

**Sumbu grafik.** Referensi menulis "40k/30k/20k/10k/0k" sebagai teks mati.
Di sini dihitung dari nilai tertinggi — sumbu yang tidak mengikuti data akan
berbohong begitu angkanya berubah.

### Yang sengaja tidak diambil

| Di referensi | Kenapa tidak |
|---|---|
| Tailwind CDN | Halaman tidak bisa dibuka tanpa internet (DEC-002) |
| Font dari CDN Google | Sama |
| Foto profil dari `googleusercontent` | Alamat luar, tidak terjangkau di LAN — menghasilkan ikon rusak. Diganti avatar inisial |
| `backdrop-blur` pada badge | Tidak menambah apa pun di atas latar solid |
| Empat tab nav bernama "Analytics" | Jelas placeholder |

---

## 5. Disiplin — yang ditegakkan test

`backend/tests/Unit/WebDesignTokensTest.php`, ikut jalan di `php artisan test`.

| Test | Menahan |
|---|---|
| `test_blade_tidak_memakai_kelas_warna_bawaan_tailwind` | `bg-blue-600`, `text-gray-400`, dst. — warna yang melewati palet merek dan tidak pernah diperiksa kontrasnya |
| `test_blade_tidak_menulis_warna_hex_mentah` | hex di `style`, atribut SVG, atau kelas arbitrary |
| `test_pasangan_teks_memenuhi_wcag_aa` | 17 pasangan teks, masing-masing ≥4,5:1 |
| `test_ink_faint_ditandai_sebagai_dekorasi` | `ink-faint` tidak digelapkan diam-diam, peringatannya tetap ada |
| `test_aksen_web_sama_persis_dengan_tablet` | web dan Flutter tidak menyimpang warna |
| `test_layout_memanggil_direktif_fonts` | `@fonts` tidak hilang |
| `test_font_hanya_plus_jakarta_sans` | family tidak diganti |

```bash
php artisan test --filter=WebDesignTokensTest
```

> **Menambah warna baru?** Tambahkan token di `app.css`, **lalu daftarkan
> pasangannya di `pasanganTeks()`.** Warna yang tidak terdaftar tidak
> terverifikasi.

---

## 6. Yang belum ada

| | |
|---|---|
| ~~Login web~~ | ✅ **Sudah ada** — DEC-043. Guard session, Owner & Admin saja |
| ~~Data nyata~~ | ✅ **Sudah** — `DashboardData` + `BusinessDay`. Batas "hari ini" = tengah malam **WIB**, bukan UTC |
| **Modul §19 lainnya** | Laporan, Booking, Target, Backup — backend-nya juga belum ada. **Expense kas sudah ada** (DEC-046, halaman Shift); Profit masih menunggu biaya operasional bulanan, bukan hanya pengeluaran laci |
| ~~Semua halaman hanya membaca~~ | ✅ **Sudah bisa mengubah** — DEC-044/045/046: harga, stok, status pesanan, operasi sesi, shift, pengeluaran kasir |
| Laporan | satu-satunya menu nav yang masih diredupkan dan tidak bisa diklik |
| Mode gelap | tidak dibuat — sama seperti tablet (DEC-016) |
| Responsif ponsel | tata letak sudah menyusut sampai satu kolom, tapi belum diuji di perangkat |
