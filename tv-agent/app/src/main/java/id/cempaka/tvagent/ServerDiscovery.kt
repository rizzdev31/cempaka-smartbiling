package id.cempaka.tvagent

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.async
import kotlinx.coroutines.awaitAll
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.withContext
import java.net.HttpURLConnection
import java.net.NetworkInterface
import java.net.URL

/** Server billing yang ditemukan di jaringan. */
data class DiscoveredServer(
    val baseUrl: String,
    val instance: String,
    val version: String,
) {
    /** `http://192.168.100.11:8000` -> `192.168.100.11`. */
    val host: String get() = baseUrl.substringAfter("://").substringBefore(":")
}

/**
 * Hasil satu ketukan HTTP. `null` = tidak ada yang menjawab, atau menjawab
 * dengan status selain 200.
 *
 * Dipisah jadi interface supaya seluruh aturan penemuan bisa diuji sebagai
 * unit JVM biasa — pola yang sama dengan [AgentPorts].
 */
fun interface HealthFetcher {
    fun get(url: String): String?
}

/**
 * Menemukan server billing di jaringan lokal — DEC-041.
 *
 * ## Masalah yang diselesaikan
 *
 * IP laptop server datang dari DHCP. Begitu laptop menyambung ulang ke WiFi,
 * IP-nya bisa berganti dan setiap TV kehilangan server — padahal tidak ada
 * yang rusak. TV dipasang di dinding, sering tanpa remote yang nyaman untuk
 * mengetik IP; memperbaikinya manual di enam TV adalah pekerjaan termahal
 * yang bisa dihindari.
 *
 * ## Cara kerjanya
 *
 * Ambil IP TV sendiri, lalu ketuk seluruh subnet-nya mencari alamat yang
 * menjawab `GET /api/v1/health` dengan `app` yang cocok.
 *
 * Pemeriksaan `app` itu intinya. Tanpanya, pemindaian akan menerima layanan
 * apa pun yang kebetulan hidup di port 8000 — printer, router, dashboard
 * lain — dan TV menyambung ke tempat yang salah tanpa gejala yang jelas.
 *
 * Aturannya sengaja identik dengan `ServerDiscovery` di Flutter. Dua aturan
 * berbeda untuk hal yang sama berarti tablet dan TV bisa memilih server yang
 * berbeda di jaringan yang sama.
 */
class ServerDiscovery(
    private val fetcher: HealthFetcher = HttpHealthFetcher(),
    private val localAddresses: () -> List<String> = ::systemAddresses,
) {

    suspend fun probe(baseUrl: String): DiscoveredServer? =
        withContext(Dispatchers.IO) { probeBlocking(baseUrl) }

    /** Memindai subnet TV ini. Berhenti pada temuan pertama. */
    suspend fun discover(): DiscoveredServer? =
        discoverAll(stopAtFirst = true).firstOrNull()

    /**
     * Memindai dan mengembalikan SEMUA temuan.
     *
     * Lebih dari satu berarti ada dua server hidup di jaringan yang sama,
     * biasanya laptop cadangan yang lupa dimatikan. TV tidak boleh menebak:
     * transaksi yang masuk ke database yang keliru baru ketahuan saat laporan
     * tidak cocok.
     */
    suspend fun discoverAll(stopAtFirst: Boolean = false): List<DiscoveredServer> {
        val found = mutableListOf<DiscoveredServer>()

        for (prefix in subnetPrefixes()) {
            var start = 1
            while (start <= LAST_HOST) {
                val end = minOf(start + BATCH_SIZE - 1, LAST_HOST)

                val batch = coroutineScope {
                    (start..end).map { i ->
                        async(Dispatchers.IO) {
                            probeBlocking("http://$prefix.$i:$PORT")
                        }
                    }.awaitAll()
                }

                for (server in batch.filterNotNull()) {
                    found += server
                    if (stopAtFirst) return found
                }

                start = end + 1
            }
        }

        return found
    }

    private fun probeBlocking(baseUrl: String): DiscoveredServer? {
        val body = fetcher.get("$baseUrl/api/v1/health") ?: return null
        val envelope = Json.decode(body)
        if (!envelope.isPresent) return null

        val data = envelope.child("data") ?: return null
        if (data.string("app") != APP_ID) return null

        return DiscoveredServer(
            baseUrl = baseUrl,
            instance = data.string("instance") ?: "Server billing",
            version = data.string("version") ?: "-",
        )
    }

    /** `192.168.100.37` -> `192.168.100`. */
    private fun subnetPrefixes(): List<String> =
        localAddresses()
            .mapNotNull { address ->
                val parts = address.split(".")
                if (parts.size == 4) parts.take(3).joinToString(".") else null
            }
            .distinct()

    companion object {
        /** Harus sama dengan `HealthController::APP_ID` di Laravel. */
        const val APP_ID = "cempaka-smart-billing"

        /**
         * Hanya 8000, default `php artisan serve`.
         *
         * Port 80 tidak dipindai: di jaringan lokal ia tidak akan dipakai
         * (Nginx baru muncul di Tahap 3A, di VPS, dengan nama domain), dan ia
         * justru port yang paling sering dijawab router dan printer —
         * menambahnya menggandakan waktu pindai sambil menambah kandidat
         * palsu.
         */
        const val PORT = 8000

        /** `.0` alamat jaringan dan `.255` broadcast, keduanya dilewati. */
        const val LAST_HOST = 254

        /**
         * Berapa alamat diketuk bersamaan. Android membatasi jumlah soket
         * terbuka; membuka 254 sekaligus membuat sebagian gagal, dan server
         * yang hidup bisa ikut terlewat.
         */
        const val BATCH_SIZE = 32

        /**
         * Pendek: di LAN, server yang hidup menjawab dalam puluhan
         * milidetik. Memperpanjangnya hanya menunda hasil tanpa menambah
         * temuan.
         */
        const val TIMEOUT_MILLIS = 600

        fun systemAddresses(): List<String> = runCatching {
            NetworkInterface.getNetworkInterfaces().toList()
                .filter { it.isUp && !it.isLoopback }
                .flatMap { it.inetAddresses.toList() }
                .filter { !it.isLoopbackAddress && !it.isLinkLocalAddress }
                .mapNotNull { it.hostAddress }
                // IPv6 tidak dipindai: subnet-nya terlalu besar untuk
                // ditebak satu per satu, dan Laravel di LAN selalu IPv4.
                .filter { it.count { c -> c == '.' } == 3 }
        }.getOrDefault(emptyList())
    }
}

/** Ketukan HTTP sungguhan. */
class HttpHealthFetcher(
    private val timeoutMillis: Int = ServerDiscovery.TIMEOUT_MILLIS,
) : HealthFetcher {

    /*
     * `HttpURLConnection` dipakai, bukan OkHttp. Kebutuhannya satu GET tanpa
     * header, tanpa retry, hanya di LAN — menambah dependensi HTTP untuk itu
     * tidak sepadan. Klien API penuh di Tahap 2 boleh memilih lain; kelas ini
     * tidak menghalangi karena pemanggilnya hanya melihat [HealthFetcher].
     */
    override fun get(url: String): String? = runCatching {
        val conn = URL(url).openConnection() as HttpURLConnection
        try {
            conn.requestMethod = "GET"
            conn.connectTimeout = timeoutMillis
            conn.readTimeout = timeoutMillis
            conn.setRequestProperty("Accept", "application/json")
            // Keep-alive tidak berguna di sini: 254 alamat berbeda, satu
            // ketukan masing-masing.
            conn.setRequestProperty("Connection", "close")

            if (conn.responseCode != 200) return null
            conn.inputStream.bufferedReader().use { it.readText() }
        } finally {
            conn.disconnect()
        }
    }.getOrNull()
}
