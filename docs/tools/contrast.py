#!/usr/bin/env python3
"""Verifikasi kontras palet operator app terhadap WCAG 2.1.

Warna **dibaca langsung dari** `operator-app/lib/core/theme/tokens.dart`,
bukan ditulis ulang di sini. Kalau palet diubah, skrip ini ikut berubah
sendiri — tidak ada dua daftar warna yang bisa menyimpang satu dari yang lain.

    python docs/tools/contrast.py

Keluar dengan kode 1 kalau ada pasangan yang gagal, jadi bisa dipakai di CI.
Pada tema terang, kesalahan kontras paling sering terjadi pada warna status
yang terlihat "cukup gelap" tapi sebenarnya tidak — karena itu dihitung,
tidak dikira-kira.
"""

from __future__ import annotations

import io
import os
import re
import sys

TOKENS = os.path.join(
    os.path.dirname(os.path.abspath(__file__)),
    '..', '..', 'operator-app', 'lib', 'core', 'theme', 'tokens.dart',
)

# (nama baca-manusia, token foreground, token background, target)
#
# Target 4.5 untuk teks yang harus dibaca, 3.0 untuk label pendukung.
PAIRS: list[tuple[str, str, str, float]] = [
    ('Teks utama / kartu', 'onSurface', 'surfaceLow', 4.5),
    ('Teks utama / kanvas', 'onSurface', 'surface', 4.5),
    ('Teks utama / inset', 'onSurface', 'surfaceContainer', 4.5),

    ('Teks sekunder / kartu', 'onSurfaceVariant', 'surfaceLow', 4.5),
    ('Teks sekunder / kanvas', 'onSurfaceVariant', 'surface', 4.5),
    ('Teks sekunder / inset', 'onSurfaceVariant', 'surfaceContainer', 4.5),

    # Sengaja bertarget 3.0: `outline` dipakai hanya untuk label pendukung
    # (alamat, jam, keterangan kecil), tidak pernah untuk informasi yang
    # harus dibaca. Lihat DEC-016.
    ('Teks pendukung / kartu', 'outline', 'surfaceLow', 3.0),

    ('Aksen / kartu', 'primary', 'surfaceLow', 4.5),
    ('Aksen / kanvas', 'primary', 'surface', 4.5),
    ('Aksen / inset', 'primary', 'surfaceContainer', 4.5),
    ('Aksen gelap / kartu', 'primaryFixedDim', 'surfaceLow', 4.5),
    ('Aksen / tint aksen', 'primary', 'primarySurface', 4.5),

    ('Status Tersedia / kartu', 'statusAvailable', 'surfaceLow', 4.5),
    ('Status Bermain / kartu', 'statusActive', 'surfaceLow', 4.5),
    ('Status Bermain / inset', 'statusActive', 'surfaceContainer', 4.5),
    ('Status Hampir habis / kartu', 'statusWarning', 'surfaceLow', 4.5),
    ('Status Hampir habis / inset', 'statusWarning', 'surfaceContainer', 4.5),
    ('Status Habis / kartu', 'statusExpired', 'surfaceLow', 4.5),
    ('Status Menunggu bayar / kartu', 'statusPendingPayment', 'surfaceLow', 4.5),
    ('Status Checkout / kartu', 'statusCheckout', 'surfaceLow', 4.5),
    ('Status Offline / kartu', 'statusOffline', 'surfaceLow', 4.5),
    ('Status Offline / inset', 'statusOffline', 'surfaceContainer', 4.5),

    ('Teks error / tint error', 'onErrorContainer', 'errorContainer', 4.5),

    ('Teks di tombol aksen', 'onPrimary', 'primary', 4.5),
    ('Teks di tombol hijau', 'onSecondary', 'secondary', 4.5),
    ('Teks di tombol merah', 'onError', 'error', 4.5),
    ('Teks di tombol amber', 'onTertiary', 'tertiaryContainer', 4.5),
    ('Teks di chip terpilih', 'surfaceLow', 'onSurface', 4.5),
    # Monogram dipakai kalau pelanggan belum punya aset logo.
    ('Monogram di alas merek', 'surfaceLow', 'brandPlate', 4.5),

    # Garis hanya perlu terlihat, bukan terbaca. 1.1 adalah ambang praktis:
    # di bawah itu tepi kartu hilang dan seluruh layar terasa rata.
    ('Garis kartu vs kanvas', 'surfaceHigh', 'surface', 1.1),
    ('Garis kartu vs kartu', 'surfaceHigh', 'surfaceLow', 1.1),
    ('Garis halus vs kartu', 'outlineVariant', 'surfaceLow', 1.1),
]


def load_tokens(path: str) -> dict[str, str]:
    """Ambil setiap `static const nama = Color(0xAARRGGBB)` dan aliasnya.

    Alias seperti `statusAvailable = primary` ikut diselesaikan, kalau tidak
    separuh warna status akan terlewat tanpa ada yang memberi tahu.
    """
    src = io.open(path, encoding='utf8').read()

    # Hanya blok AppColors — AppShadow juga memakai Color(0x...) untuk shadow.
    start = src.find('class AppColors')
    end = src.find('\nclass ', start + 1)
    if start == -1:
        raise SystemExit(f'Tidak menemukan class AppColors di {path}')
    src = src[start:end if end != -1 else len(src)]

    direct = dict(
        re.findall(r'static const (\w+) = Color\(0x([0-9A-Fa-f]{8})\)', src)
    )
    alias = dict(re.findall(r'static const (\w+) = (\w+);', src))

    out: dict[str, str] = {}
    for name, argb in direct.items():
        if argb[:2].upper() != 'FF':
            continue  # lapisan transparan — kontrasnya tergantung latar
        out[name] = argb[2:].upper()

    for _ in range(4):  # alias berantai
        for name, target in alias.items():
            if name not in out and target in out:
                out[name] = out[target]

    return out


def _linear(channel: int) -> float:
    c = channel / 255
    return c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4


def luminance(hex_color: str) -> float:
    r, g, b = (int(hex_color[i:i + 2], 16) for i in (0, 2, 4))
    return 0.2126 * _linear(r) + 0.7152 * _linear(g) + 0.0722 * _linear(b)


def contrast(fg: str, bg: str) -> float:
    a, b = luminance(fg), luminance(bg)
    hi, lo = max(a, b), min(a, b)
    return (hi + 0.05) / (lo + 0.05)


def main() -> int:
    tokens = load_tokens(TOKENS)
    print(f'{len(tokens)} token warna dibaca dari tokens.dart\n')
    print(f"{'Pasangan':32s} {'Rasio':>8s} {'Target':>7s}  Status")
    print('-' * 62)

    failed: list[str] = []
    for name, fg, bg, target in PAIRS:
        missing = [t for t in (fg, bg) if t not in tokens]
        if missing:
            # Token dihapus atau diganti nama — itu kegagalan, bukan hal
            # yang boleh dilewati diam-diam.
            failed.append(f'{name}: token hilang {", ".join(missing)}')
            print(f'{name:32s} {"—":>8s} {target:7.1f}  TOKEN HILANG')
            continue

        ratio = contrast(tokens[fg], tokens[bg])
        ok = ratio >= target
        if not ok:
            failed.append(f'{name}: {ratio:.2f}:1, butuh {target}:1')
        print(f'{name:32s} {ratio:7.2f}:1 {target:7.1f}  {"OK" if ok else "GAGAL"}')

    print('-' * 62)
    if failed:
        print(f'{len(failed)} GAGAL:')
        for line in failed:
            print(f'  {line}')
        return 1

    print(f'{len(PAIRS)} pasangan — semuanya memenuhi target')
    return 0


if __name__ == '__main__':
    sys.exit(main())
