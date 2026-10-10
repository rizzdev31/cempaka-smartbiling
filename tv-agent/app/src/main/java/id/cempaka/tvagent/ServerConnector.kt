package id.cempaka.tvagent

/** Bagaimana sambungan didapat. */
enum class ConnectOutcome {
    /** Alamat yang diingat masih benar. Tidak ada pemindaian. */
    REMEMBERED,

    /** Alamat lama diam; server ditemukan di alamat baru dan sudah disimpan. */
    REDISCOVERED,

    /** Tidak ada server billing di jaringan ini. */
    NOT_FOUND,
}

data class ConnectResult(
    val outcome: ConnectOutcome,
    val server: DiscoveredServer? = null,
) {
    val connected: Boolean get() = outcome != ConnectOutcome.NOT_FOUND

    /**
     * Alamat berganti. Pemanggil harus membangun ulang apa pun yang
     * memegang alamat lama — terutama koneksi WebSocket, yang tidak akan
     * gagal dengan sendirinya; ia hanya diam.
     */
    val addressChanged: Boolean get() = outcome == ConnectOutcome.REDISCOVERED
}

/**
 * Menyambung ke Laravel tanpa ada yang mengetik IP di TV — DEC-041.
 *
 * Urutannya: coba yang diingat dulu, baru memindai.
 *
 * Urutan itu bukan sekadar optimasi. Pemindaian memakan 2–5 detik dan
 * membuka ratusan soket; melakukannya setiap boot akan memperlambat setiap
 * kali TV dinyalakan, demi masalah yang muncul sebulan sekali.
 *
 * Aturannya sengaja sama dengan `ServerConnector` di Flutter.
 */
class ServerConnector(
    private val store: ServerAddressStore,
    private val discovery: ServerDiscovery = ServerDiscovery(),
) {

    suspend fun connect(): ConnectResult {
        val remembered = store.apiBaseUrl

        if (remembered != null) {
            val alive = discovery.probe(remembered)
            if (alive != null) {
                return ConnectResult(ConnectOutcome.REMEMBERED, alive)
            }
        }

        val found = discovery.discover()
            /*
             * Dua sebab yang tidak bisa dibedakan dari sini: server pindah
             * alamat, atau server memang sedang mati. Alamat lama sengaja
             * DIPERTAHANKAN — kalau dihapus, mematikan laptop sebentar akan
             * membuat setiap TV lupa alamatnya dan memindai ulang dari nol.
             */
            ?: return ConnectResult(ConnectOutcome.NOT_FOUND)

        store.rememberApiBaseUrl(found.baseUrl)
        return ConnectResult(ConnectOutcome.REDISCOVERED, found)
    }
}
