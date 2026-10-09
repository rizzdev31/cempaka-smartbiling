<?php

namespace App\Support\Realtime;

use App\Jobs\BroadcastSessionUpdate;
use App\Models\BillingSession;
use Illuminate\Support\Facades\Cache;

/**
 * Debounce `session.updated` — REALTIME.md §7.
 *
 * ## Kenapa bukan sekadar membuang event yang datang terlalu cepat
 *
 * Cara termudah adalah throttle: buang event yang datang dalam 500 ms setelah
 * event sebelumnya. Itu SALAH di sini, karena yang terbuang bisa jadi event
 * **terakhir** — dan tablet lalu menampilkan tagihan basi sampai ada perubahan
 * berikutnya. Operator menyebut angka yang salah ke customer, dan tidak ada
 * yang tahu sampai ada yang protes.
 *
 * Yang dipakai di sini debounce yang benar: perubahan pertama **menjadwalkan**
 * satu job tertunda, perubahan berikutnya dalam jendela yang sama tidak
 * menambah job apa pun. Saat job berjalan, ia **membaca ulang sesi dari
 * database**. Jadi apa pun yang terjadi selama jendela itu, yang terkirim
 * selalu keadaan terbaru — tidak ada yang bisa hilang.
 *
 * ## Batas ketelitian
 *
 * Queue database menyimpan `available_at` dalam detik, jadi jendela 500 ms
 * pada praktiknya membulat jadi 0–1 detik. Itu tidak mengubah sifatnya:
 * tujuan debounce ini mengurangi jumlah broadcast saat ramai, bukan menjamin
 * waktu yang presisi.
 *
 * ## Kalau worker mati
 *
 * Kunci penanda punya TTL. Tanpa itu, satu job yang tidak pernah jalan akan
 * memblokir seluruh broadcast sesi tersebut selamanya.
 */
final class SessionUpdateBroadcaster
{
    /** REALTIME.md §7. */
    public const WINDOW_MS = 500;

    /**
     * Pengaman kalau queue worker mati — setelah ini penanda kedaluwarsa dan
     * perubahan berikutnya boleh menjadwalkan lagi.
     */
    private const PENDING_TTL_SECONDS = 60;

    /**
     * @param  list<string>  $changed  Petunjuk UI saja (REALTIME.md §5) — client
     *                                 tetap memakai objek `session` utuh.
     */
    public static function schedule(BillingSession $session, array $changed = []): void
    {
        $id = (string) $session->id;

        /*
         * Petunjuk `changed` dikumpulkan lebih dulu, SEBELUM pemeriksaan
         * penanda. Perubahan kedua dalam jendela yang sama tidak menjadwalkan
         * job baru, tapi petunjuknya tetap harus ikut terkirim — kalau tidak,
         * animasi highlight di tablet melewatkan bagian yang berubah.
         */
        self::rememberChanged($id, $changed);

        // add() hanya berhasil kalau kuncinya belum ada — itu yang membuat
        // hanya satu job terjadwal per jendela.
        if (! Cache::add(self::pendingKey($id), true, self::PENDING_TTL_SECONDS)) {
            return;
        }

        BroadcastSessionUpdate::dispatch($id)
            ->delay(now()->addMilliseconds(self::WINDOW_MS));
    }

    /** Dipanggil job saat berjalan: ambil petunjuk yang terkumpul lalu bersihkan. */
    public static function flush(string $sessionId): array
    {
        $changed = Cache::pull(self::changedKey($sessionId), []);

        /*
         * Penanda dihapus SEBELUM broadcast dikirim. Kalau dihapus sesudahnya,
         * perubahan yang terjadi selama pengiriman akan dianggap masih dalam
         * jendela dan tidak dijadwalkan — itu persis kehilangan event terakhir
         * yang ingin dihindari.
         */
        Cache::forget(self::pendingKey($sessionId));

        return is_array($changed) ? $changed : [];
    }

    private static function rememberChanged(string $id, array $changed): void
    {
        if ($changed === []) {
            return;
        }

        $key = self::changedKey($id);
        $sebelumnya = Cache::get($key, []);

        Cache::put(
            $key,
            array_values(array_unique(array_merge(is_array($sebelumnya) ? $sebelumnya : [], $changed))),
            self::PENDING_TTL_SECONDS,
        );
    }

    private static function pendingKey(string $id): string
    {
        return "broadcast:session-updated:pending:{$id}";
    }

    private static function changedKey(string $id): string
    {
        return "broadcast:session-updated:changed:{$id}";
    }
}
