package id.cempaka.tvagent

/**
 * Apa yang sedang ditampilkan TV.
 *
 * Sengaja memakai nama yang sama dengan kontrak (`docs/contracts/API.md` §9
 * `display.mode`) supaya tidak perlu diterjemahkan saat Reverb masuk.
 */
enum class DisplayMode {
    /** Belum dipasangkan ke operator. Menampilkan kode pairing. */
    PAIRING,

    /** Sudah dipasangkan, tidak ada sesi. Layar idle. */
    IDLE,

    /** Sesi berjalan. Timer dihitung dari [AgentState.endAtMillis]. */
    TIMER,

    /** Menunggu pembayaran — sesi dibuat tapi belum mulai. */
    PENDING_PAYMENT,

    /**
     * Terkunci. **Belum dipakai di v1** — perilakunya menunggu OD-001 dan
     * OD-004. Ada di sini supaya nilainya tidak berubah nanti.
     */
    LOCKED,
}

/**
 * Tahap pembukaan layar saat sesi dimulai.
 *
 * Dipisah dari [DisplayMode] dengan sengaja. `DisplayMode` memakai nama
 * kontrak (`API.md` §9) dan datang dari server; tahap di bawah ini hanya
 * diketahui TV dan tidak pernah dikirim siapa pun. Menggabungkan keduanya
 * berarti menambah nilai ke enum kontrak untuk sesuatu yang server tidak
 * tahu — dan nilai itu akan bocor ke payload saat Reverb masuk.
 *
 * Urutannya searah: INTRO -> AWAITING_HDMI -> HANDED_OVER. Tidak ada jalan
 * mundur selain sesi berakhir.
 */
enum class StartupPhase {
    /** Tidak sedang membuka sesi. Nilai saat idle dan saat menunggu bayar. */
    NONE,

    /** Video pembuka sedang diputar. Diselesaikan oleh pemutarnya sendiri. */
    INTRO,

    /**
     * Video selesai, konsol belum mengirim sinyal. Layar memberi tahu
     * customer bahwa TV sedang menunggu — bukan rusak.
     */
    AWAITING_HDMI,

    /**
     * Layar sudah diserahkan ke input HDMI. Agen tidak lagi terlihat.
     *
     * Catatan penting: di tahap ini timer **tidak tampil**, karena yang
     * ada di layar adalah konsol. Itu konsekuensi yang diminta, bukan bug.
     */
    HANDED_OVER,
}

/**
 * State agen. Immutable; perubahan selalu menghasilkan instance baru supaya
 * tidak ada mutasi diam-diam dari thread server HTTP.
 *
 * **Tidak ada harga, tidak ada durasi paket, tidak ada logika billing di
 * sini.** TV hanya tahu kapan sesi berakhir. Harga, kelayakan extend, dan
 * rounding tetap milik server (PRD §8, DEC-015).
 */
data class AgentState(
    val mode: DisplayMode = DisplayMode.PAIRING,

    /** Kode station, mis. `ST01`. Diberikan operator saat pairing. */
    val stationCode: String? = null,

    val sessionId: String? = null,

    /** Nama customer untuk ditampilkan. Boleh null (walk-in). */
    val customerLabel: String? = null,

    /** Waktu mulai sesi, epoch millis UTC. Null kalau belum mulai. */
    val startedAtMillis: Long? = null,

    /**
     * Waktu sesi berakhir, epoch millis UTC.
     *
     * **Satu-satunya sumber perhitungan sisa waktu.** Tidak pernah ada
     * countdown yang dikirim per detik (PRD §16).
     */
    val endAtMillis: Long? = null,

    /**
     * Tahap pembukaan. Hanya berarti saat [mode] == [DisplayMode.TIMER];
     * di mode lain selalu [StartupPhase.NONE].
     */
    val phase: StartupPhase = StartupPhase.NONE,
) {
    val isPaired: Boolean get() = mode != DisplayMode.PAIRING

    val hasSession: Boolean get() = sessionId != null && endAtMillis != null

    /** Total durasi sesi dalam millis, untuk bar progres. */
    val totalMillis: Long?
        get() {
            val s = startedAtMillis ?: return null
            val e = endAtMillis ?: return null
            val total = e - s
            return if (total > 0) total else null
        }

    /** Sedang memutar video atau menunggu sinyal — layar masih milik agen. */
    val isOpening: Boolean
        get() = phase == StartupPhase.INTRO || phase == StartupPhase.AWAITING_HDMI

    companion object {
        val UNPAIRED = AgentState()
    }
}
