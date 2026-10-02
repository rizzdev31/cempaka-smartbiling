package id.cempaka.tvagent

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import kotlin.math.abs

/**
 * DEC-003 — server-time offset.
 *
 * Jam TV sering salah setelah boot sebelum NTP jalan. Kalau sisa waktu
 * dihitung dari jam TV mentah, timer melenceng dan ada pihak yang dirugikan.
 */
class TimeSyncTest {

    @Before
    fun reset() = TimeSync.resetForTest()

    @Test
    fun `belum sinkron ditandai jelas`() {
        assertFalse(TimeSync.isSynced)
        assertEquals(0L, TimeSync.offset)
        assertEquals(null, TimeSync.secondsSinceSync())
    }

    @Test
    fun `jam TV terlambat satu menit dikoreksi`() {
        val senderNow = System.currentTimeMillis() + 60_000
        TimeSync.sync(senderNow)

        assertTrue(TimeSync.isSynced)
        // Offset harus mendekati +60s; toleransi untuk waktu eksekusi test.
        assertTrue(
            "offset=${TimeSync.offset}",
            abs(TimeSync.offset - 60_000) < 1_000,
        )
    }

    @Test
    fun `jam TV terlalu cepat dikoreksi ke arah sebaliknya`() {
        TimeSync.sync(System.currentTimeMillis() - 30_000)
        assertTrue(TimeSync.offset < -29_000)
    }

    @Test
    fun `sisa waktu dihitung dari jam pengirim, bukan jam device`() {
        // TV mengira sekarang 5 menit lebih awal daripada kenyataan.
        val realNow = System.currentTimeMillis()
        TimeSync.sync(realNow + 300_000)

        // Sesi berakhir 10 menit dari "sekarang" menurut pengirim.
        val endAt = realNow + 300_000 + 600_000

        val remaining = TimeSync.remainingMillis(endAt)
        assertTrue(
            "remaining=${remaining}",
            abs(remaining - 600_000) < 2_000,
        )
    }

    @Test
    fun `sisa waktu negatif tidak di-clamp ke nol`() {
        TimeSync.sync(System.currentTimeMillis())
        val remaining = TimeSync.remainingMillis(System.currentTimeMillis() - 120_000)

        // Operator perlu tahu sudah lewat berapa lama; nol menyesatkan.
        assertTrue("remaining=$remaining", remaining < -119_000)
    }

    @Test
    fun `latensi jaringan dikompensasi separuh perjalanan`() {
        val senderNow = System.currentTimeMillis()
        TimeSync.sync(senderNow, roundTripMillis = 400)

        // Tanpa kompensasi offset mendekati 0; dengan 400ms RTT jadi +200.
        assertTrue("offset=${TimeSync.offset}", TimeSync.offset >= 150)
    }
}
