@props(['padding' => 'p-4 sm:p-6'])

{{--
    Kartu putih — unit dasar tata letak referensi.

    Garis tepi DAN shadow, berbeda dari tablet yang hanya memakai garis.
    Di referensi shadow-nya nyaris tidak terlihat (alpha 0,04) dan tugasnya
    hanya mengangkat kartu sedikit dari shell, bukan membuatnya melayang.

    ## Padding bawaannya responsif, dan itu sengaja

    Bawaannya dulu `p-6` tetap, jadi hampir setiap halaman menuliskan
    `padding="p-4 sm:p-6"` sendiri — dan yang lupa menuliskannya jadi
    berbeda tanpa ada yang menyadarinya. Hasilnya lima varian padding di
    sebelas halaman: `p-6`, `p-5`, `p-4 sm:p-5`, `p-4 sm:p-6`, dan bawaan.

    Sekarang bawaannya sudah benar untuk hampir semua tempat: 16px di
    ponsel (24px memakan hampir seperlima lebar layar 375px), 24px mulai
    `sm`. `padding` hanya diisi kalau memang ada alasan khusus.
--}}

<div {{ $attributes->merge(['class' => "bg-card rounded-card border border-line shadow-card {$padding}"]) }}>
    {{ $slot }}
</div>
