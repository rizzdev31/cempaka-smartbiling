<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\TestCase;

/** Nomor untuk tautan `wa.me` — DEC-035. */
class PhoneTest extends TestCase
{
    public function test_nol_di_depan_diganti_kode_negara(): void
    {
        // Bentuk yang paling sering diketik operator.
        $this->assertSame('6281234567890', Phone::toWhatsApp('081234567890'));
    }

    public function test_tanda_baca_dibuang(): void
    {
        // Operator mengetik seperti yang diucapkan customer.
        $this->assertSame('6281234567890', Phone::toWhatsApp('0812-3456-7890'));
        $this->assertSame('6281234567890', Phone::toWhatsApp('0812 3456 7890'));
        $this->assertSame('6281234567890', Phone::toWhatsApp('(0812) 3456.7890'));
    }

    public function test_sudah_berkode_negara_dibiarkan(): void
    {
        $this->assertSame('6281234567890', Phone::toWhatsApp('+6281234567890'));
        $this->assertSame('6281234567890', Phone::toWhatsApp('6281234567890'));
    }

    public function test_nol_yang_terlewat_tetap_dikenali(): void
    {
        $this->assertSame('6281234567890', Phone::toWhatsApp('81234567890'));
    }

    public function test_kosong_atau_terlalu_pendek_jadi_null(): void
    {
        // Lebih baik tidak ada tautan daripada tautan yang membuka obrolan
        // ke nomor asing.
        $this->assertNull(Phone::toWhatsApp(null));
        $this->assertNull(Phone::toWhatsApp(''));
        $this->assertNull(Phone::toWhatsApp('0812'));
        $this->assertNull(Phone::toWhatsApp('-'));
    }

    public function test_nomor_negara_lain_tidak_dipaksa_jadi_indonesia(): void
    {
        // Customer asing: lebih baik tautan yang mungkin benar daripada yang
        // pasti salah karena dipaksa diberi kode 62.
        $this->assertSame('60123456789', Phone::toWhatsApp('+60 123456789'));
    }
}
