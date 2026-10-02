package id.cempaka.tvagent

/**
 * Server-time offset — DEC-003.
 *
 * Jam TV sering salah, terutama setelah boot sebelum NTP jalan. Kalau sisa
 * waktu dihitung dari jam TV mentah, timer bisa melenceng beberapa menit:
 * customer dirugikan, atau rental kehilangan uang.
 *
 * Polanya:
 * ```
 * offset    = waktu_pengirim - waktu_device
 * remaining = end_at - (device_now + offset)
 * ```
 *
 * **Siapa pengirimnya berubah antar tahap, polanya tidak.** Sekarang
 * pengirimnya aplikasi operator (DEC-015). Di Tahap 2 pengirimnya Laravel
 * lewat `X-Server-Time` / `server_time`. Karena itu kelas ini tidak menyebut
 * "operator" maupun "server" — hanya "pengirim".
 */
object TimeSync {

    @Volatile
    private var offsetMillis: Long = 0L

    /**
     * Penanda monotonik kapan terakhir sinkron.
     *
     * `System.nanoTime()` dipakai, bukan `SystemClock.elapsedRealtime()`:
     * keduanya monotonik, tapi yang pertama murni Java sehingga seluruh kelas
     * ini bisa diuji sebagai unit JVM tanpa emulator. Aturan waktu adalah
     * bagian paling mahal kalau salah (DEC-003), jadi harus bisa dites murah.
     */
    @Volatile
    private var lastSyncNanos: Long? = null

    val offset: Long get() = offsetMillis

    val isSynced: Boolean get() = lastSyncNanos != null

    /**
     * Sinkronkan dari waktu pengirim.
     *
     * @param senderEpochMillis waktu pengirim, epoch millis UTC
     * @param roundTripMillis bila diketahui, separuhnya dikompensasi untuk
     *   latensi jaringan. Di LAN nilainya kecil, tapi tetap benar dihitung.
     */
    fun sync(senderEpochMillis: Long, roundTripMillis: Long? = null) {
        val deviceNow = System.currentTimeMillis()
        var computed = senderEpochMillis - deviceNow
        if (roundTripMillis != null) computed += roundTripMillis / 2
        offsetMillis = computed
        lastSyncNanos = System.nanoTime()
    }

    /** "Sekarang" menurut pengirim. */
    fun now(): Long = System.currentTimeMillis() + offsetMillis

    /**
     * Sisa waktu sampai [endAtMillis]. Negatif kalau sudah lewat.
     *
     * Sengaja mengembalikan nilai negatif, tidak di-clamp ke nol: operator
     * perlu tahu sudah lewat berapa lama, dan layar menampilkannya.
     */
    fun remainingMillis(endAtMillis: Long): Long = endAtMillis - now()

    /** Berapa lama sejak sinkronisasi terakhir. Null kalau belum pernah. */
    fun secondsSinceSync(): Long? = lastSyncNanos?.let {
        (System.nanoTime() - it) / 1_000_000_000
    }

    internal fun resetForTest() {
        offsetMillis = 0L
        lastSyncNanos = null
    }
}
