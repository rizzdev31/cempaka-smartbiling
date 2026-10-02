# Font

Static instance yang di-generate dari variable font Google Fonts.

| Family | Weight | Dipakai untuk |
|---|---|---|
| Space Grotesk | 500, 600, 700 | judul, kode station, angka ringkasan |
| Plus Jakarta Sans | 400, 500, 600, 700 | body, label |
| JetBrains Mono | 400, 500, 600, 700 | timer, uang, label teknis |

## Kenapa dibundel, bukan paket `google_fonts`

Paket `google_fonts` mengunduh font saat runtime. Aplikasi ini dipakai di
jaringan lokal yang bisa tanpa internet (DEC-002), jadi font harus ada di
dalam APK. Kalau diunduh saat runtime, build pertama di lokasi akan
menampilkan font sistem dan tampilannya berbeda dari yang dirancang.

## Kenapa static, bukan variable

Google Fonts hanya menyediakan variable font untuk ketiga family ini.
Variable font di Flutter butuh `fontVariations` di setiap `TextStyle`;
`fontWeight` saja tidak mengubah ketebalan. Itu mudah terlewat di satu
widget dan hasilnya tidak konsisten.

Static instance dibuat dengan `fontTools.varLib.instancer` sehingga
`fontWeight` bekerja normal di seluruh app:

```bash
pip install fonttools
# lalu lihat perintah di docs/UI-UX-SPEC.md
```

`updateFontNames=False` dipakai karena Space Grotesk tidak punya named
instance di tabel STAT untuk setiap weight. Nama internal font tidak
dipakai — `pubspec.yaml` yang menentukan family dan weight.

## Lisensi

Ketiganya SIL Open Font License 1.1. Teks lisensi ada di `OFL-*.txt`
dan **wajib tetap disertakan** dalam distribusi.
