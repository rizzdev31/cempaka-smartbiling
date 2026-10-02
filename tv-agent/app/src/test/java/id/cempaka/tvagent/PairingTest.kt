package id.cempaka.tvagent

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Pairing — satu-satunya yang menghalangi orang asing di WiFi yang sama
 * menyetel timer TV.
 *
 * Tanpa ini, customer di Guest Wi-Fi bisa memperpanjang sesinya sendiri secara
 * gratis. Kelas risiko yang sama dengan *prank order* (PRD §13) dan R05.
 */
class PairingTest {

    @Test
    fun `kode enam digit angka saja`() {
        val pairing = Pairing(FakeStore())
        assertEquals(6, pairing.code.length)
        assertTrue("kode=${pairing.code}", pairing.code.all { it.isDigit() })
    }

    @Test
    fun `kode benar menghasilkan token dan menandai sudah dipasangkan`() {
        val store = FakeStore()
        val pairing = Pairing(store)

        val token = pairing.tryPair(pairing.code, "st01")

        assertNotNull(token)
        assertEquals(token, store.currentToken)
        assertEquals("ST01", store.current.stationCode)
        assertTrue(store.current.isPaired)
    }

    @Test
    fun `kode salah ditolak dan tidak menyimpan token`() {
        val store = FakeStore()
        val pairing = Pairing(store)

        assertNull(pairing.tryPair("000000".takeIf { it != pairing.code } ?: "111111", "ST01"))
        assertNull(store.currentToken)
        assertFalse(store.current.isPaired)
    }

    @Test
    fun `kode null atau kosong ditolak`() {
        val pairing = Pairing(FakeStore())
        assertNull(pairing.tryPair(null, "ST01"))
        assertNull(pairing.tryPair("", "ST01"))
        assertNull(pairing.tryPair("   ", "ST01"))
    }

    @Test
    fun `kode hangus setelah dipakai`() {
        val store = FakeStore()
        val pairing = Pairing(store)
        val used = pairing.code

        assertNotNull(pairing.tryPair(used, "ST01"))

        // Kode yang sama tidak boleh berlaku dua kali — kalau berlaku, siapa
        // pun yang pernah melihat layar TV bisa memasangkan diri kapan saja.
        assertNotEquals(used, pairing.code)
        assertNull(pairing.tryPair(used, "ST02"))
    }

    @Test
    fun `terlalu banyak percobaan salah mengunci pairing`() {
        val store = FakeStore()
        val pairing = Pairing(store)
        val wrong = if (pairing.code == "999999") "888888" else "999999"

        repeat(10) { pairing.tryPair(wrong, "ST01") }

        assertTrue(pairing.isLocked)
        // Setelah terkunci, kode yang BENAR pun ditolak — supaya brute force
        // tidak bisa dilanjutkan dengan menebak sampai kebetulan tepat.
        assertNull(pairing.tryPair(pairing.code, "ST01"))
    }

    @Test
    fun `regenerate mengatur ulang hitungan percobaan`() {
        val store = FakeStore()
        val pairing = Pairing(store)
        val wrong = if (pairing.code == "999999") "888888" else "999999"

        repeat(10) { pairing.tryPair(wrong, "ST01") }
        assertTrue(pairing.isLocked)

        pairing.regenerate()

        assertFalse(pairing.isLocked)
        assertNotNull(pairing.tryPair(pairing.code, "ST01"))
    }

    @Test
    fun `station code dinormalkan ke huruf besar`() {
        val store = FakeStore()
        val pairing = Pairing(store)

        pairing.tryPair(pairing.code, "  st03  ")

        assertEquals("ST03", store.current.stationCode)
    }

    @Test
    fun `station code kosong tetap menghasilkan penanda, bukan string kosong`() {
        val store = FakeStore()
        val pairing = Pairing(store)

        pairing.tryPair(pairing.code, null)

        // "?" lebih jujur daripada kosong: layar TV menunjukkan ada yang
        // belum diisi, bukan seolah station tanpa nama.
        assertEquals("?", store.current.stationCode)
    }

    @Test
    fun `token acak dan panjang`() {
        val a = Pairing(FakeStore()).let { it.tryPair(it.code, "ST01") }
        val b = Pairing(FakeStore()).let { it.tryPair(it.code, "ST01") }

        assertNotNull(a)
        assertNotNull(b)
        assertNotEquals(a, b)
        assertEquals(48, a!!.length) // 24 byte hex
    }

    @Test
    fun `validasi token menolak yang salah dan yang kosong`() {
        val store = FakeStore()
        val pairing = Pairing(store)
        val token = pairing.tryPair(pairing.code, "ST01")!!

        assertTrue(pairing.isTokenValid(token))
        assertFalse(pairing.isTokenValid(null))
        assertFalse(pairing.isTokenValid(""))
        assertFalse(pairing.isTokenValid("salah"))
        assertFalse(pairing.isTokenValid(token.dropLast(1) + "0"))
    }

    @Test
    fun `token tidak valid sebelum pernah dipasangkan`() {
        val pairing = Pairing(FakeStore())
        assertFalse(pairing.isTokenValid("apa pun"))
    }
}
