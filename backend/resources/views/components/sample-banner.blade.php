{{--
    Spanduk "data contoh".

    Tidak bisa ditutup, dan tidak punya props. Itu disengaja: spanduk yang
    bisa dimatikan akan dimatikan, lalu halaman palsu terlihat seperti
    halaman sungguhan. Ia hilang bersama `DashboardSampleData` saat query
    nyatanya masuk — bukan karena seseorang ingat mematikannya.
--}}

<div class="flex items-start gap-3 p-4 mb-8 rounded-block bg-warning-surface border border-warning/30">
    <span class="text-warning shrink-0 mt-0.5">
        <x-icon name="warning" class="w-5 h-5" />
    </span>
    <p class="text-xs text-warning leading-relaxed">
        <span class="font-bold">Data contoh — belum dari database.</span>
        Semua angka di halaman ini berasal dari <code class="font-semibold">DashboardSampleData</code>,
        bukan dari tabel. Nominalnya memakai tarif asli supaya masuk akal, tapi
        tidak mencerminkan transaksi apa pun. Login web dan query nyatanya belum dibuat.
    </p>
</div>
