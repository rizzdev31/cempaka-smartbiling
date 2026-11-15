<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Merek yang tampil ke manusia — DEC-017.
 *
 * Yang dijaga di sini bukan selera, tapi tiga hal yang pernah atau mudah
 * salah: nama sistem bocor ke tempat nama rental, berkas logo hilang tanpa
 * ada yang sadar, dan merek ditulis langsung di Blade sehingga memasang
 * aplikasi ini untuk rental lain berarti berburu string (OD-012).
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create([
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);
    }

    public function test_berkas_logo_dan_ikon_benar_benar_ada(): void
    {
        /*
         * Yang paling mudah lolos review: path logo salah satu huruf.
         * Halamannya tetap 200, test lain tetap hijau, dan yang muncul
         * hanya gambar rusak di pojok kiri atas.
         */
        foreach (config('brand.logo') as $nama => $path) {
            $this->assertFileExists(
                public_path($path),
                "brand.logo.{$nama} menunjuk ke `{$path}` yang tidak ada di public/",
            );
        }
    }

    public function test_layar_login_memakai_nama_rental_bukan_nama_sistem(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee(config('brand.mark_title'));
        $response->assertSee(config('brand.mark_subtitle'));
        $response->assertSee(config('brand.logo.mark'));
    }

    public function test_navbar_memakai_lencana_merek(): void
    {
        $response = $this->actingAs($this->owner())->get('/admin');

        $response->assertStatus(200);
        $response->assertSee(config('brand.mark_title'));
        $response->assertSee(config('brand.logo.mark'));
    }

    public function test_ikon_tab_terpasang(): void
    {
        /*
         * Tanpa `<link rel="icon">` browser jatuh ke `/favicon.ico` bawaan
         * Laravel — logo Laravel di tab, di aplikasi milik rental.
         */
        $this->get('/login')
            ->assertSee('rel="icon"', false)
            ->assertSee(config('brand.logo.icon_small'));
    }

    public function test_merek_tidak_ditulis_langsung_di_blade(): void
    {
        /*
         * OD-012 (white-label): memasang aplikasi ini untuk rental lain
         * harus berarti mengubah `config/brand.php` saja. Satu nama yang
         * terlanjur diketik di Blade cukup untuk membatalkan itu, dan
         * baru ketahuan setelah terpasang di tempat pelanggan.
         */
        $pelanggar = [];

        $berkas = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views')),
        );

        foreach ($berkas as $f) {
            if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.blade.php')) {
                continue;
            }

            $isi = file_get_contents($f->getPathname());
            $relatif = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $f->getPathname());

            // `welcome.blade.php` bawaan Laravel tidak dipakai — `/` sudah
            // dialihkan ke /admin. Tidak ikut dijaga.
            if ($relatif === 'welcome.blade.php') {
                continue;
            }

            foreach ([config('brand.name'), config('brand.mark_title').' Gaming'] as $nama) {
                // Komentar Blade boleh menyebut nama merek sebagai contoh.
                $tanpaKomentar = preg_replace('/\{\{--.*?--\}\}/s', '', $isi);
                $tanpaKomentar = preg_replace('#//.*$#m', '', (string) $tanpaKomentar);

                if (str_contains((string) $tanpaKomentar, $nama)) {
                    $pelanggar[] = "{$relatif} menulis \"{$nama}\"";
                }
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            "Nama merek harus datang dari config('brand.*'), bukan diketik di Blade:\n"
            .implode("\n", $pelanggar),
        );
    }
}
