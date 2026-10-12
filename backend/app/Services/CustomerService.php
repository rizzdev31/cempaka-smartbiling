<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Data customer — DEC-027, DEC-049.
 *
 * ## Kenapa ini jadi Service, bukan `Customer::create()` di controller
 *
 * Sebelum DEC-049 memang begitu: API memanggil `Customer::create()` lalu
 * mencatat audit sendiri, dan **controller web memanggilnya tanpa mencatat
 * apa pun**. Akibatnya customer yang ditambahkan dari Admin Web tidak punya
 * jejak siapa yang menambahkannya — dan itu tidak menimbulkan error, hanya
 * pertanyaan yang tidak bisa dijawab berbulan-bulan kemudian.
 *
 * Satu pintu, satu jejak. Dua pintu yang menulis sendiri-sendiri selalu
 * berakhir dengan salah satunya lupa.
 */
class CustomerService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(array $input, User $actor): Customer
    {
        return DB::transaction(function () use ($input, $actor) {
            $customer = Customer::query()->create($input);

            // DEC-027: operator boleh mendaftarkan, tapi tidak boleh tidak
            // terlacak.
            $this->audit->forUser($actor, AuditAction::CUSTOMER_CREATED, [
                'subject_type' => 'customer',
                'subject_id' => $customer->id,
                'after' => $this->snapshot($customer),
            ]);

            return $customer;
        });
    }

    /**
     * Perbaiki data customer — DEC-049.
     *
     * Yang paling sering: nama salah ketik dan nomor HP yang kurang satu
     * angka. Keduanya baru ketahuan saat struk gagal terkirim atau saat
     * orangnya datang lagi dan tidak ditemukan di pencarian.
     *
     * Saldo, membership, dan riwayat sesi TIDAK disentuh di sini. Mengubah
     * nama tidak memindahkan uang siapa pun: saldo menempel ke
     * `customer_id`, bukan ke nama atau nomornya.
     */
    public function update(Customer $customer, array $input, User $actor): Customer
    {
        return DB::transaction(function () use ($customer, $input, $actor) {
            $sebelum = $this->snapshot($customer);

            $customer->fill($input)->save();

            /*
             * Nilai sebelum ikut disimpan. "Nomor HP member ini dulu berapa"
             * adalah pertanyaan yang muncul justru ketika ada yang salah —
             * dan tanpa nilai lamanya, perubahan yang keliru tidak bisa
             * dikembalikan.
             */
            $this->audit->forUser($actor, AuditAction::CUSTOMER_UPDATED, [
                'subject_type' => 'customer',
                'subject_id' => $customer->id,
                'before' => $sebelum,
                'after' => $this->snapshot($customer->fresh()),
            ]);

            return $customer;
        });
    }

    private function snapshot(Customer $customer): array
    {
        return [
            'name' => $customer->name,
            'phone' => $customer->phone,
            'note' => $customer->note,
        ];
    }
}
