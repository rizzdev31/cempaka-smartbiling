package id.cempaka.tvagent

import kotlinx.coroutines.runBlocking
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.util.concurrent.CopyOnWriteArrayList

/**
 * Penemuan server otomatis — DEC-041, menutup OD-011.
 *
 * Sebagian besar test di sini soal **penolakan**: apa saja yang menjawab di
 * port 8000 tapi bukan server billing. Kalau bagian itu salah, TV menyambung
 * ke printer atau router dan tidak ada gejala yang jelas — layarnya hanya
 * diam di IDLE selamanya.
 */
class ServerDiscoveryTest {

    private fun healthBody(
        app: String = ServerDiscovery.APP_ID,
        instance: String = "Amor Gaming Space",
    ) = """
        {"data":{"app":"$app","instance":"$instance","status":"ok",
        "version":"v1","database":"ok","broadcast":"reverb"},
        "meta":{"server_time":"2026-10-10T03:00:00Z"}}
    """.trimIndent()

    /**
     * Jaringan tiruan. Hanya [serverAt] menjawab seperti server billing;
     * [imposters] menjawab JSON yang sah tapi dengan `app` lain; sisanya
     * mati (`null`, seperti [HttpHealthFetcher] saat tidak ada yang
     * mendengarkan).
     */
    private class FakeNetwork(
        private val serverAt: String? = null,
        private val imposters: Set<String> = emptySet(),
        private val extraServers: Set<String> = emptySet(),
        private val body: (String, String) -> String,
    ) : HealthFetcher {

        /*
         * Thread-safe: pemindaian mengetuk 32 alamat sekaligus di
         * `Dispatchers.IO`, jadi `get` dipanggil dari banyak thread. Dengan
         * `mutableListOf` biasa, sebagian catatan hilang dan jumlahnya
         * terlihat kurang dari 254 — kegagalan yang muncul-hilang.
         */
        val probed: MutableList<String> = CopyOnWriteArrayList()

        override fun get(url: String): String? {
            val origin = url.removeSuffix("/api/v1/health")
            probed += origin

            return when (origin) {
                serverAt -> body(ServerDiscovery.APP_ID, "Amor Gaming Space")
                in extraServers -> body(ServerDiscovery.APP_ID, origin)
                in imposters -> body("octoprint", "Printer")
                else -> null
            }
        }
    }

    private fun discovery(
        fetcher: HealthFetcher,
        addresses: List<String> = listOf("192.168.100.42"),
    ) = ServerDiscovery(fetcher = fetcher, localAddresses = { addresses })

    // ── probe ─────────────────────────────────────────────────────────

    @Test
    fun `menerima server billing`() = runBlocking {
        val net = FakeNetwork(serverAt = "http://192.168.100.11:8000") { a, i ->
            healthBody(a, i)
        }

        val found = discovery(net).probe("http://192.168.100.11:8000")

        assertEquals("http://192.168.100.11:8000", found?.baseUrl)
        assertEquals("192.168.100.11", found?.host)
        // Nama rental, bukan IP — itu yang dibaca teknisi saat memilih.
        assertEquals("Amor Gaming Space", found?.instance)
    }

    @Test
    fun `menolak layanan lain yang hidup di port yang sama`() = runBlocking {
        /*
         * Inti pengamanannya. Printer, router, dan dashboard lain sering
         * memakai port 8000 dan menjawab 200 dengan JSON. Tanpa pemeriksaan
         * `app`, pemindaian akan menerimanya.
         */
        val net = FakeNetwork(imposters = setOf("http://192.168.100.11:8000")) { a, i ->
            healthBody(a, i)
        }

        assertNull(discovery(net).probe("http://192.168.100.11:8000"))
    }

    @Test
    fun `menolak jawaban yang bukan JSON`() = runBlocking {
        val net = HealthFetcher { "<html>Router Admin</html>" }

        assertNull(discovery(net).probe("http://192.168.100.11:8000"))
    }

    @Test
    fun `menolak JSON tanpa amplop data`() = runBlocking {
        val net = HealthFetcher { """{"app":"cempaka-smart-billing"}""" }

        assertNull(discovery(net).probe("http://192.168.100.11:8000"))
    }

    @Test
    fun `alamat mati menghasilkan null, bukan exception`() = runBlocking {
        // Pemindaian menyentuh 254 alamat mati; satu exception yang lolos
        // akan menghentikan seluruh pencarian.
        val net = HealthFetcher { null }

        assertNull(discovery(net).probe("http://192.168.100.11:8000"))
    }

    // ── pemindaian ────────────────────────────────────────────────────

    @Test
    fun `menemukan server di subnet TV sendiri`() = runBlocking {
        val net = FakeNetwork(serverAt = "http://192.168.100.11:8000") { a, i ->
            healthBody(a, i)
        }

        assertEquals(
            "http://192.168.100.11:8000",
            discovery(net, listOf("192.168.100.42")).discover()?.baseUrl,
        )
    }

    @Test
    fun `tidak memindai subnet lain`() = runBlocking {
        /*
         * TV di 10.0.0.x tidak akan menemukan server di 192.168.100.x, dan
         * memang tidak boleh mencoba — itu jaringan orang lain.
         */
        val net = FakeNetwork(serverAt = "http://192.168.100.11:8000") { a, i ->
            healthBody(a, i)
        }

        assertNull(discovery(net, listOf("10.0.0.5")).discover())
        assertTrue(net.probed.all { it.startsWith("http://10.0.0.") })
    }

    @Test
    fun `memindai seluruh rentang satu sampai 254`() = runBlocking {
        // `.0` adalah alamat jaringan dan `.255` broadcast; keduanya tidak
        // pernah menjadi alamat server.
        val net = FakeNetwork { a, i -> healthBody(a, i) }

        discovery(net).discover()

        assertEquals(254, net.probed.size)
        assertTrue(net.probed.contains("http://192.168.100.1:8000"))
        assertTrue(net.probed.contains("http://192.168.100.254:8000"))
        assertFalse(net.probed.contains("http://192.168.100.0:8000"))
        assertFalse(net.probed.contains("http://192.168.100.255:8000"))
    }

    @Test
    fun `hanya port 8000 yang diketuk`() = runBlocking {
        val net = FakeNetwork { a, i -> healthBody(a, i) }

        discovery(net).discover()

        assertTrue(net.probed.all { it.endsWith(":8000") })
    }

    @Test
    fun `jaringan kosong menghasilkan null`() = runBlocking {
        val net = FakeNetwork { a, i -> healthBody(a, i) }

        assertNull(discovery(net).discover())
    }

    @Test
    fun `tanpa IP lokal tidak memindai apa pun`() = runBlocking {
        // WiFi mati: tidak ada subnet untuk ditebak.
        val net = FakeNetwork(serverAt = "http://192.168.100.11:8000") { a, i ->
            healthBody(a, i)
        }

        assertNull(discovery(net, emptyList()).discover())
        assertTrue(net.probed.isEmpty())
    }

    @Test
    fun `melaporkan semua server kalau ada lebih dari satu`() = runBlocking {
        /*
         * Dua server hidup biasanya berarti laptop cadangan lupa dimatikan.
         * Menebak salah satunya berarti transaksi masuk ke database yang
         * keliru, dan itu baru ketahuan saat laporan tidak cocok.
         */
        val net = FakeNetwork(
            serverAt = "http://192.168.100.11:8000",
            extraServers = setOf("http://192.168.100.12:8000"),
        ) { a, i -> healthBody(a, i) }

        val all = discovery(net).discoverAll()

        assertEquals(
            listOf("192.168.100.11", "192.168.100.12"),
            all.map { it.host }.sorted(),
        )
    }

    // ── ServerConnector ───────────────────────────────────────────────

    @Test
    fun `alamat yang diingat masih benar - tidak memindai`() = runBlocking {
        val store = FakeStore().apply { savedBaseUrl = "http://192.168.100.11:8000" }
        val net = FakeNetwork(serverAt = "http://192.168.100.11:8000") { a, i ->
            healthBody(a, i)
        }

        val result = ServerConnector(store, discovery(net)).connect()

        assertEquals(ConnectOutcome.REMEMBERED, result.outcome)
        assertFalse(result.addressChanged)
        // Satu ketukan saja. Memindai 254 alamat setiap boot memperlambat
        // setiap kali TV dinyalakan, demi masalah yang jarang terjadi.
        assertEquals(listOf("http://192.168.100.11:8000"), net.probed)
    }

    @Test
    fun `IP server berganti - ditemukan lagi dan disimpan`() = runBlocking {
        val store = FakeStore().apply { savedBaseUrl = "http://192.168.100.11:8000" }
        // Router memberi laptop alamat lain setelah menyambung ulang WiFi.
        val net = FakeNetwork(serverAt = "http://192.168.100.37:8000") { a, i ->
            healthBody(a, i)
        }

        val result = ServerConnector(store, discovery(net)).connect()

        assertEquals(ConnectOutcome.REDISCOVERED, result.outcome)
        assertTrue(result.addressChanged)
        assertEquals("http://192.168.100.37:8000", store.savedBaseUrl)
    }

    @Test
    fun `server mati - alamat lama TIDAK dihapus`() = runBlocking {
        /*
         * Mematikan laptop sebentar tidak boleh membuat setiap TV lupa
         * alamatnya dan memindai ulang dari nol saat laptop hidup lagi.
         */
        val store = FakeStore().apply { savedBaseUrl = "http://192.168.100.11:8000" }
        val net = FakeNetwork { a, i -> healthBody(a, i) }

        val result = ServerConnector(store, discovery(net)).connect()

        assertEquals(ConnectOutcome.NOT_FOUND, result.outcome)
        assertFalse(result.connected)
        assertEquals("http://192.168.100.11:8000", store.savedBaseUrl)
    }

    @Test
    fun `TV baru tanpa alamat tersimpan langsung memindai`() = runBlocking {
        // Keadaan saat TV pertama kali dipasang: tidak ada yang mengetik IP.
        val store = FakeStore()
        val net = FakeNetwork(serverAt = "http://192.168.100.11:8000") { a, i ->
            healthBody(a, i)
        }

        val result = ServerConnector(store, discovery(net)).connect()

        assertEquals(ConnectOutcome.REDISCOVERED, result.outcome)
        assertEquals("http://192.168.100.11:8000", store.savedBaseUrl)
    }
}
