<?php

namespace Tests\Concerns;

use App\Enums\StationStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\FnbProduct;
use App\Models\Membership;
use App\Models\Package;
use App\Models\Station;
use App\Models\StationType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Master data minimum untuk test billing.
 *
 * Dibuat langsung lewat `create()` dan bukan factory supaya empat model
 * master data tidak perlu ikut memakai `HasFactory` hanya demi test.
 *
 * Angka defaultnya sengaja memakai paket 1 jam seharga 20.000 — sama dengan
 * contoh di API.md §7, jadi nilai di test bisa dibandingkan langsung dengan
 * kontrak tanpa menghitung ulang.
 */
trait MakesBillingWorld
{
    protected function stationType(string $name = 'PS4 Slim'): StationType
    {
        return StationType::query()->create(['name' => $name, 'is_active' => true]);
    }

    protected function station(StationType $type, array $attributes = []): Station
    {
        return Station::query()->create($attributes + [
            'code' => 'ST01',
            'name' => 'Station 1',
            'station_type_id' => $type->id,
            'status' => StationStatus::ACTIVE,
            // Kode pendaftaran TV (API.md §9). Acak supaya dua station dalam
            // satu test tidak pernah bertabrakan kodenya.
            'enrollment_code' => Station::generateEnrollmentCode(),
        ]);
    }

    protected function package(StationType $type, array $attributes = []): Package
    {
        return Package::query()->create($attributes + [
            'station_type_id' => $type->id,
            'name' => '1 Jam',
            'duration_minutes' => 60,
            'price' => 20000,
            'is_active' => true,
        ]);
    }

    protected function operator(array $attributes = []): User
    {
        return User::query()->create($attributes + [
            'name' => 'Budi',
            'username' => 'operator-'.uniqid(),
            'password' => Hash::make('rahasia-test'),
            'role' => UserRole::OPERATOR,
            'is_active' => true,
        ]);
    }

    protected function member(string $name = 'Siti'): Customer
    {
        return Customer::query()->create(['name' => $name, 'phone' => '08'.random_int(100000000, 999999999)]);
    }

    protected function fnbProduct(array $attributes = []): FnbProduct
    {
        return FnbProduct::query()->create($attributes + [
            "category" => "Minuman",
            "name" => "Teh Manis",
            "price" => 5000,
            "stock" => null,          // null = tidak dilacak (API.md §8)
            "is_available" => true,
        ]);
    }

    /** Member aktif — syarat menyimpan sisa waktu (DEC-024). */
    protected function activeMember(string $name = "Siti"): Customer
    {
        $customer = $this->member($name);

        Membership::query()->create([
            "customer_id" => $customer->id,
            "tier" => "SILVER",
            "is_active" => true,
            "joined_at" => now(),
        ]);

        return $customer->fresh("membership");
    }
    /** Header wajib untuk POST yang membuat data (API.md §3). */
    protected function idempotent(): array
    {
        return ['Idempotency-Key' => (string) \Illuminate\Support\Str::uuid()];
    }
}
