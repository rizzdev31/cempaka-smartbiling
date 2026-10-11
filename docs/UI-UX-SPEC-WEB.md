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
| `<x-sample-banner>` | penanda data contoh |

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
| **Login web** | Auth sekarang Sanctum — token API untuk tablet, bukan session web. Pekerjaan tersendiri, dan **wajib** sebelum ada data nyata di halaman |
| **Data nyata** | Dashboard memakai `DashboardSampleData`. Ganti kelas itu, jangan tambahkan query di sampingnya |
| Halaman Sesi, F&B, Harga & Paket, Laporan | nav-nya sudah ada, diredupkan dan tidak bisa diklik |
| Mode gelap | tidak dibuat — sama seperti tablet (DEC-016) |
| Responsif ponsel | tata letak sudah menyusut sampai satu kolom, tapi belum diuji di perangkat |
