<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Disiplin visual Admin Web — DEC-042.
 *
 * Padanan `operator-app/test/theme_discipline_test.dart`. Alasannya sama:
 * dokumen design system tidak menahan apa pun. Yang menahan adalah test yang
 * ikut gagal di CI saat seseorang menulis `bg-blue-600` karena itu yang ada
 * di contoh Tailwind yang dia salin.
 *
 * Di tema terang, selisih satu nada pada garis kartu langsung terlihat, dan
 * warna yang tersebar di puluhan berkas Blade tidak bisa diverifikasi
 * kontrasnya.
 */
class WebDesignTokensTest extends TestCase
{
    private const CSS = __DIR__.'/../../resources/css/app.css';

    private const VIEWS = __DIR__.'/../../resources/views';

    /** @return list<string> */
    private function bladeFiles(): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::VIEWS));

        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                // Bawaan Laravel, bukan milik kami.
                if ($file->getFilename() === 'welcome.blade.php') {
                    continue;
                }
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function css(): string
    {
        return file_get_contents(self::CSS);
    }

    private function token(string $name): string
    {
        preg_match('/--color-'.preg_quote($name, '/').':\s*(#[0-9a-fA-F]{6})/', $this->css(), $m);
        $this->assertNotEmpty($m, "Token --color-{$name} tidak ada di app.css");

        return strtolower($m[1]);
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = [];

        foreach ([0, 2, 4] as $offset) {
            $v = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    public function test_blade_tidak_memakai_kelas_warna_bawaan_tailwind(): void
    {
        /*
         * Inti dari test ini. `bg-blue-600` akan TERLIHAT baik-baik saja —
         * itu biru yang wajar — tapi ia melewati palet merek dan tidak pernah
         * diperiksa kontrasnya. Satu baris begitu sudah cukup membuat web
         * berbeda warna dari tablet.
         */
        $palettes = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald'
            .'|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';
        $props = 'bg|text|border|from|via|to|ring|divide|placeholder|outline|accent|fill|stroke';

        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            if (preg_match_all('/\b(?:'.$props.')-(?:'.$palettes.')-\d{2,3}\b/', file_get_contents($file), $m)) {
                $offenders[basename($file)] = array_values(array_unique($m[0]));
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Kelas warna bawaan Tailwind di Blade. Pakai token merek di app.css.\n"
            .json_encode($offenders, JSON_PRETTY_PRINT),
        );
    }

    public function test_blade_tidak_menulis_warna_hex_mentah(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $body = file_get_contents($file);

            // Hanya hex yang dipakai SEBAGAI warna: di style, di atribut SVG,
            // atau di kelas arbitrary Tailwind. `href="#"` tidak ikut.
            if (preg_match_all('/(?:style="[^"]*|stroke="|fill="|\[)#[0-9a-fA-F]{3,8}/', $body, $m)) {
                $offenders[basename($file)] = array_values(array_unique($m[0]));
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Hex mentah di Blade. Tambahkan token di app.css lalu daftarkan pasangannya di test ini.\n"
            .json_encode($offenders, JSON_PRETTY_PRINT),
        );
    }

    public function test_setiap_sel_tabel_punya_data_label(): void
    {
        /*
         * `table-stacked` mengubah tiap baris jadi blok di bawah 48rem, dan
         * nama kolomnya datang dari `data-label`. Sel yang lupa akan tampil
         * tanpa keterangan — dan HANYA di layar sempit, jadi lolos dari siapa
         * pun yang mengujinya di laptop.
         */
        $pelanggar = [];

        foreach ($this->bladeFiles() as $file) {
            $isi = file_get_contents($file);

            if (! str_contains($isi, 'table-stacked')) {
                continue;
            }

            // `<td` tanpa `data-label`, kecuali yang memakai colspan —
            // itu baris keadaan kosong yang memang dibiarkan utuh.
            preg_match_all('/<td(?![^>]*\bdata-label=)(?![^>]*\bcolspan=)[^>]*>/', $isi, $m);

            if ($m[0] !== []) {
                $pelanggar[basename($file)] = $m[0];
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            "Sel tabel tanpa `data-label` — akan tampil tanpa keterangan di ponsel.\n"
            .json_encode($pelanggar, JSON_PRETTY_PRINT),
        );
    }

    public function test_prop_komponen_tidak_memakai_entity_html(): void
    {
        /*
         * Blade meng-escape isi `{{ }}`. Prop yang sudah ditulis sebagai
         * entity jadi ter-escape DUA KALI: `&amp;` keluar sebagai `&amp;amp;`
         * dan halaman menampilkan "Harga &amp; Paket" apa adanya.
         *
         * Di dalam HTML mentah, `&amp;` justru yang benar. Jadi yang
         * diperiksa hanya yang ada di dalam atribut `<x-...>`.
         */
        $pelanggar = [];

        foreach ($this->bladeFiles() as $file) {
            // Tag komponen `<x-nama ...>` yang atributnya memuat entity.
            preg_match_all('/<x-[a-z0-9.:-]+[^>]*?(&(?:amp|lt|gt|quot|#\d+);)[^>]*>/i', file_get_contents($file), $m);

            if ($m[0] !== []) {
                $pelanggar[basename($file)] = array_values(array_unique($m[1]));
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            "Entity HTML di dalam prop komponen — akan ter-escape dua kali.\n"
            ."Tulis karakternya apa adanya (`&`, bukan `&amp;`).\n"
            .json_encode($pelanggar, JSON_PRETTY_PRINT),
        );
    }

    public function test_layout_penuh_tidak_dikunci_lebar_tetap(): void
    {
        /*
         * Admin Web memakai SELURUH lebar layar.
         *
         * Versi pertama memakai `max-w-[1240px] mx-auto` — pola "kartu
         * melayang" dari referensi. Wajar untuk satu halaman contoh, salah
         * untuk alat kerja: di monitor 1920px, 680px dibiarkan kosong di
         * kiri-kanan sementara tabel di tengah tetap harus digeser.
         *
         * Yang mengatur kenyamanan baca adalah padding yang ikut membesar
         * (`2xl:px-12`) dan grid yang menambah kolom, bukan pagar lebar.
         */
        $layout = file_get_contents(self::VIEWS.'/components/layouts/app.blade.php');

        /*
         * Komentar dibuang dulu. Tanpa ini, kalimat yang MENJELASKAN kenapa
         * `max-w-[1240px]` dibuang justru membuat test ini gagal — dan
         * perbaikan yang terlihat paling mudah adalah menghapus
         * penjelasannya.
         */
        $kelas = preg_replace('/\{\{--.*?--\}\}/s', '', $layout);

        $this->assertDoesNotMatchRegularExpression(
            '/\bmax-w-(?:\[|screen-|7xl|6xl|5xl)/',
            $kelas,
            'Layout dikunci lebar tetap lagi — halaman akan tertahan di tengah.',
        );

        $this->assertStringContainsString('px-4 sm:px-6 lg:px-8', $layout, 'Padding tepi harus ikut membesar.');
    }

    public function test_layout_memanggil_direktif_fonts(): void
    {
        /*
         * `@vite` TIDAK menautkan CSS font — berkas itu punya manifest
         * sendiri dan hanya keluar lewat `@fonts`. Tanpanya halaman tetap
         * tampil wajar, hanya memakai font sistem: kegagalan yang nyaris
         * tidak terlihat kecuali dibandingkan berdampingan, dan justru
         * karena itu bisa bertahan berbulan-bulan.
         */
        $layout = file_get_contents(self::VIEWS.'/components/layouts/app.blade.php');

        $this->assertStringContainsString('@fonts', $layout, 'Layout kehilangan @fonts — Plus Jakarta Sans tidak akan termuat.');
        $this->assertStringContainsString('@vite', $layout);
    }

    public function test_font_hanya_plus_jakarta_sans(): void
    {
        $this->assertMatchesRegularExpression(
            "/--font-sans:\s*'Plus Jakarta Sans'/",
            $this->css(),
            'Font web ditetapkan Plus Jakarta Sans oleh user.',
        );
    }

    /**
     * Pasangan warna yang BENAR-BENAR dipakai untuk teks, dan rasio
     * minimumnya. Menambah warna baru? Daftarkan di sini — yang tidak
     * terdaftar tidak terverifikasi.
     *
     * @return array<string, array{string, string}>
     */
    public static function pasanganTeks(): array
    {
        return [
            'teks utama di kartu' => ['ink', 'card'],
            'teks sekunder di kartu' => ['ink-soft', 'card'],
            'teks utama di bidang cekung' => ['ink', 'inset'],
            'teks sekunder di bidang cekung' => ['ink-soft', 'inset'],
            'teks sekunder di shell' => ['ink-soft', 'shell'],
            'aksen di kartu' => ['brand-600', 'card'],
            'aksen di bidang cekung' => ['brand-600', 'inset'],
            'badge aksen' => ['brand-600', 'brand-50'],
            'badge bermain' => ['playing', 'playing-surface'],
            'badge waspada' => ['warning', 'warning-surface'],
            'badge bahaya' => ['danger', 'danger-surface'],
            'badge menunggu bayar' => ['awaiting', 'awaiting-surface'],
            'badge checkout' => ['checkout', 'checkout-surface'],
            'badge dibatalkan' => ['idle', 'idle-surface'],
            'putih di tombol aksi' => ['card', 'brand-600'],
            'putih di tombol hover' => ['card', 'brand-700'],
            'putih di pill nav aktif' => ['card', 'pitch'],
        ];
    }

    /**
     * @see pasanganTeks
     */
    #[DataProvider('pasanganTeks')]
    public function test_pasangan_teks_memenuhi_wcag_aa(string $fg, string $bg): void
    {
        $ratio = self::contrast($this->token($fg), $this->token($bg));

        $this->assertGreaterThanOrEqual(
            4.5,
            round($ratio, 2),
            sprintf('%s di atas %s hanya %.2f:1 — minimum AA untuk teks kecil 4,5:1.', $fg, $bg, $ratio),
        );
    }

    public function test_ink_faint_ditandai_sebagai_dekorasi(): void
    {
        /*
         * `ink-faint` adalah nada label redup dari referensi, dan ia MEMANG
         * gagal AA (2,8:1). Ia dipertahankan untuk gridline dan tick sumbu.
         *
         * Test ini mengunci dua hal sekaligus: bahwa nilainya belum diam-diam
         * digelapkan sampai "lulus" (yang akan mengubah tampilan tanpa
         * siapa pun memutuskan), dan bahwa peringatannya masih tertulis di
         * sebelahnya.
         */
        $ratio = self::contrast($this->token('ink-faint'), $this->token('card'));

        $this->assertLessThan(4.5, $ratio, 'ink-faint sekarang lulus AA — kalau disengaja, pindahkan ke pasanganTeks().');
        $this->assertStringContainsString(
            'DEKORASI SAJA',
            $this->css(),
            'Peringatan pada --color-ink-faint hilang.',
        );
    }

    public function test_aksen_web_sama_persis_dengan_tablet(): void
    {
        /*
         * Invarian lintas aplikasi. Kalau palet Flutter berubah dan web
         * tidak, owner akan membuka keduanya berdampingan dan melihat dua
         * teal yang berbeda — perbedaan kecil yang terlihat justru karena
         * kecil.
         */
        $flutter = __DIR__.'/../../../operator-app/lib/core/theme/tokens.dart';

        if (! file_exists($flutter)) {
            $this->markTestSkipped('operator-app tidak ada di checkout ini.');
        }

        preg_match('/static const primary = Color\(0xFF([0-9A-Fa-f]{6})\)/', file_get_contents($flutter), $m);
        $this->assertNotEmpty($m, 'AppColors.primary tidak ditemukan di tokens.dart.');

        $this->assertSame(
            '#'.strtolower($m[1]),
            $this->token('brand-600'),
            'Aksen web dan tablet berbeda. Keduanya harus teal Amor yang sama (DEC-042).',
        );
    }
}
