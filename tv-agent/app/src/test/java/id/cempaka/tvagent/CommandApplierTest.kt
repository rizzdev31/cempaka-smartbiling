package id.cempaka.tvagent

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * Aturan penerapan perintah.
 *
 * Yang dikunci di sini adalah batas-batas yang kalau bocor jadi mahal:
 * agen belum dipasangkan tidak boleh menerima sesi, dan `LOCKED` tidak boleh
 * menebak perilaku yang belum diputuskan (OD-001, OD-004).
 */
class CommandApplierTest {

    private lateinit var store: FakeStore
    private lateinit var applier: CommandApplier

    @Before
    fun setUp() {
        TimeSync.resetForTest()
        store = FakeStore()
        applier = CommandApplier(store)
    }

    private fun session(
        endAt: Long? = System.currentTimeMillis() + 3_600_000,
        pending: Boolean = false,
        senderTime: Long? = System.currentTimeMillis(),
    ) = AgentCommand.ApplySession(
        sessionId = "ses-1",
        stationCode = "ST01",
        customerLabel = "Budi",
        startedAtMillis = System.currentTimeMillis(),
        endAtMillis = endAt,
        pendingPayment = pending,
        senderTimeMillis = senderTime,
    )

    // ── Batas pairing ─────────────────────────────────────────────────

    @Test
    fun `agen belum dipasangkan menolak sesi`() {
        assertFalse(applier.apply(session()))
        assertNull(store.current.sessionId)
        assertEquals(DisplayMode.PAIRING, store.current.mode)
    }

    @Test
    fun `agen belum dipasangkan menolak akhiri sesi`() {
        assertFalse(applier.apply(AgentCommand.EndSession(senderTimeMillis = null)))
        assertEquals(0, store.dropCount)
    }

    @Test
    fun `ping diterima walau belum dipasangkan`() {
        // Ping hanya menyinkronkan waktu dan tidak mengubah apa pun, jadi
        // tidak perlu dibatasi — operator memakainya untuk menguji jangkauan.
        assertTrue(applier.apply(AgentCommand.Ping(senderTimeMillis = System.currentTimeMillis())))
    }

    // ── Menerapkan sesi ───────────────────────────────────────────────

    @Test
    fun `sesi berjalan masuk mode TIMER dengan end_at tersimpan`() {
        store.paired()
        val endAt = System.currentTimeMillis() + 1_800_000

        assertTrue(applier.apply(session(endAt = endAt)))

        assertEquals(DisplayMode.TIMER, store.current.mode)
        assertEquals("ses-1", store.current.sessionId)
        assertEquals(endAt, store.current.endAtMillis)
        assertEquals("Budi", store.current.customerLabel)
    }

    @Test
    fun `sesi menunggu bayar masuk mode PENDING_PAYMENT`() {
        store.paired()

        assertTrue(applier.apply(session(endAt = null, pending = true)))

        assertEquals(DisplayMode.PENDING_PAYMENT, store.current.mode)
        assertNull(store.current.endAtMillis)
    }

    @Test
    fun `perintah tanpa station code tidak menghapus yang sudah ada`() {
        store.paired("ST05")

        applier.apply(
            AgentCommand.ApplySession(
                sessionId = "ses-2",
                stationCode = null,
                customerLabel = null,
                startedAtMillis = null,
                endAtMillis = System.currentTimeMillis() + 60_000,
                pendingPayment = false,
                senderTimeMillis = null,
            )
        )

        // Station code berasal dari pairing; perintah yang tidak menyebutnya
        // tidak boleh membuat TV lupa dia station mana.
        assertEquals("ST05", store.current.stationCode)
    }

    @Test
    fun `perintah berikutnya memperbarui end_at — ini jalur extend`() {
        store.paired()
        val first = System.currentTimeMillis() + 600_000
        applier.apply(session(endAt = first))

        val extended = first + 1_800_000
        applier.apply(session(endAt = extended))

        assertEquals(extended, store.current.endAtMillis)
        assertEquals(DisplayMode.TIMER, store.current.mode)
    }

    // ── Mengakhiri sesi ───────────────────────────────────────────────

    @Test
    fun `akhiri sesi membersihkan state tapi mempertahankan pairing`() {
        store.paired("ST01")
        applier.apply(session())

        assertTrue(applier.apply(AgentCommand.EndSession(senderTimeMillis = null)))

        assertNull(store.current.sessionId)
        assertNull(store.current.endAtMillis)
        assertEquals(DisplayMode.IDLE, store.current.mode)
        assertEquals("ST01", store.current.stationCode)
        assertTrue(store.current.isPaired)
    }

    // ── Perintah salah sasaran ────────────────────────────────────────

    @Test
    fun `perintah untuk station lain ditolak`() {
        store.paired("ST02")

        // session() memakai stationCode "ST01".
        assertFalse(applier.apply(session()))

        // Kalau diterima, TV ST02 akan mengaku ST01 dan dua TV mengklaim
        // station yang sama tanpa ada yang tahu mana yang benar.
        assertEquals("ST02", store.current.stationCode)
        assertNull(store.current.sessionId)
    }

    @Test
    fun `perintah untuk station yang sama diterima walau beda huruf besar-kecil`() {
        store.paired("ST02")

        val cmd = AgentCommand.ApplySession(
            sessionId = "ses-9",
            stationCode = "st02",
            customerLabel = null,
            startedAtMillis = null,
            endAtMillis = System.currentTimeMillis() + 60_000,
            pendingPayment = false,
            senderTimeMillis = null,
        )

        assertTrue(applier.apply(cmd))
        assertEquals("ses-9", store.current.sessionId)
    }

    // ── LOCKED: diterima, sengaja tidak diterapkan ────────────────────

    @Test
    fun `perintah kunci diterima tapi tidak mengubah tampilan`() {
        store.paired()
        applier.apply(session())

        assertTrue(applier.apply(AgentCommand.SetLocked(locked = true, senderTimeMillis = null)))

        // Perilaku LOCKED belum diputuskan (OD-001, OD-004). Menebaknya berarti
        // operator mengandalkan sesuatu yang bisa berubah total nanti.
        assertEquals(DisplayMode.TIMER, store.current.mode)
    }

    // ── Sinkronisasi waktu menempel di setiap perintah ────────────────

    @Test
    fun `setiap perintah yang membawa waktu pengirim menyinkronkan jam`() {
        store.paired()
        assertFalse(TimeSync.isSynced)

        applier.apply(session(senderTime = System.currentTimeMillis() + 45_000))

        assertTrue(TimeSync.isSynced)
        assertTrue("offset=${TimeSync.offset}", TimeSync.offset > 40_000)
    }

    @Test
    fun `perintah tanpa waktu pengirim tidak mengacak offset yang sudah ada`() {
        store.paired()
        TimeSync.sync(System.currentTimeMillis() + 20_000)
        val before = TimeSync.offset

        applier.apply(session(senderTime = null))

        assertEquals(before, TimeSync.offset)
    }
}
