<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Biaya mendaftar member
    |--------------------------------------------------------------------------
    |
    | DEC-029 — Rp 10.000, ditandai SEMENTARA oleh user.
    |
    | Ditaruh di config dan bukan dipatri di kode karena angkanya akan berubah:
    | DEC-019/020 menetapkan owner mengubah tarif dari aplikasi, dan sampai
    | layar itu ada, satu tempat di sini lebih mudah diubah daripada tersebar
    | di service dan test.
    |
    | Integer rupiah tanpa desimal (DEC-005).
    |
    */
    'membership_fee' => (int) env('MEMBERSHIP_FEE', 10000),

];
