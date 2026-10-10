<?php

namespace App\Events;

use App\Events\Concerns\CarriesServerTime;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Master data berubah — tarif, paket, atau menu.
 *
 * Event kesembilan, di luar delapan event PRD §23. Ditambahkan karena tanpa
 * itu owner yang menaikkan harga di satu tablet tidak punya cara memberi tahu
 * tablet lain: layar Start Session akan terus menampilkan harga lama sampai
 * seseorang menutup dan membukanya lagi.
 *
 * ## Kenapa payload-nya ringan, bukan objek penuh
 *
 * Berbeda dengan `session.updated` yang membawa objek `session` utuh, event
 * ini hanya menyebut APA yang berubah. Client lalu memuat ulang daftarnya
 * sendiri lewat `GET /packages` atau `GET /fnb/products`.
 *
 * Alasannya: master data dibaca sebagai DAFTAR, bukan satu per satu. Mengirim
 * satu paket yang berubah memaksa client menyisipkannya ke daftar yang sudah
 * dipegang — dan urutan, penyaringan, serta paket yang baru dibuat atau
 * dinonaktifkan harus ditangani sendiri. Memuat ulang daftarnya jauh lebih
 * sulit salah, dan master data jarang berubah sehingga biayanya tidak terasa.
 */
class MasterDataUpdated implements ShouldBroadcast
{
    use CarriesServerTime;
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  string  $resource  `package` | `fnb_product`
     * @param  string  $action    `created` | `updated`
     */
    public function __construct(
        public readonly string $resource,
        public readonly string $action,
        public readonly string $id,
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        /*
         * Hanya ke operator. TV tidak pernah menampilkan harga — layarnya
         * cuma timer — jadi mengirimkannya ke channel station hanya menambah
         * lalu lintas tanpa ada yang memakainya.
         */
        return [new PrivateChannel('operator')];
    }

    public function broadcastAs(): string
    {
        return 'master.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'resource' => $this->resource,
            'action' => $this->action,
            'id' => $this->id,
            'server_time' => $this->serverTime(),
        ];
    }
}
