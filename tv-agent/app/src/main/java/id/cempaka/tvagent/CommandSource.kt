package id.cempaka.tvagent

/**
 * Perintah yang bisa diterima agen.
 *
 * Bentuknya mencerminkan event realtime di `docs/contracts/REALTIME.md` §5,
 * **bukan** bentuk HTTP lokal — supaya saat sumbernya diganti Reverb, hanya
 * lapisan transport yang berubah.
 */
sealed interface AgentCommand {

    /** Waktu pengirim, untuk sinkronisasi offset (DEC-003). */
    val senderTimeMillis: Long?

    /** Sesi dimulai atau diperbarui. Setara `session.started`/`session.updated`. */
    data class ApplySession(
        val sessionId: String,
        val stationCode: String?,
        val customerLabel: String?,
        val startedAtMillis: Long?,
        val endAtMillis: Long?,
        val pendingPayment: Boolean,
        override val senderTimeMillis: Long?,
    ) : AgentCommand

    /** Sesi selesai. Setara `session.updated` ke COMPLETED. */
    data class EndSession(
        override val senderTimeMillis: Long?,
    ) : AgentCommand

    /**
     * Kunci layar. **Belum dipakai di v1** — perilakunya menunggu OD-001 dan
     * OD-004. Diterima tapi hanya dicatat, tidak mengubah tampilan.
     */
    data class SetLocked(
        val locked: Boolean,
        override val senderTimeMillis: Long?,
    ) : AgentCommand

    /**
     * Video pembuka selesai diputar.
     *
     * Datang dari pemutar di layar, bukan dari jaringan — tapi lewat jalur
     * yang sama supaya perpindahan tahap hanya terjadi di satu tempat
     * ([CommandApplier]) dan bisa diuji tanpa perangkat.
     */
    data class IntroFinished(
        override val senderTimeMillis: Long? = null,
    ) : AgentCommand

    /**
     * Konsol mengirim sinyal di input HDMI. Layar diserahkan.
     */
    data class HdmiSignalDetected(
        override val senderTimeMillis: Long? = null,
    ) : AgentCommand

    /** Sinkronisasi waktu saja, tanpa perubahan sesi. */
    data class Ping(
        override val senderTimeMillis: Long?,
    ) : AgentCommand
}

/**
 * Sumber perintah.
 *
 * **Ini batas yang menjaga pekerjaan Tahap 2 tidak terbuang (DEC-015.)**
 *
 * Sekarang: [LocalHttpCommandSource] — operator mengirim HTTP langsung ke TV.
 * Tahap 2: `ReverbCommandSource` — Laravel mem-broadcast lewat WebSocket.
 *
 * Layar kiosk, timer, persistence, dan recovery tidak tahu mana yang dipakai.
 */
interface CommandSource {

    /** Nama untuk ditampilkan di diagnostik. */
    val label: String

    /** Sedang menerima perintah? */
    val isActive: Boolean

    fun start()

    fun stop()
}

/**
 * Penerap perintah.
 *
 * Dipisah dari [CommandSource] supaya satu penerap bisa dipakai oleh sumber
 * mana pun, dan supaya aturan "perintah apa yang sah" ada di satu tempat.
 */
class CommandApplier(private val store: AgentStateHolder) {

    /** @return `true` kalau perintah diterapkan. */
    fun apply(command: AgentCommand): Boolean {
        command.senderTimeMillis?.let { TimeSync.sync(it) }

        return when (command) {
            is AgentCommand.Ping -> true

            is AgentCommand.ApplySession -> {
                val current = store.current

                // Agen tidak boleh menerima sesi sebelum dipasangkan.
                if (!current.isPaired) return false

                // Perintah untuk station lain DITOLAK, tidak diterapkan.
                //
                // Kalau diterima, perintah yang salah kirim — operator menekan
                // ST01 padahal TV ini ST02 — akan mengubah label TV diam-diam.
                // Akibatnya dua TV mengaku station yang sama dan tidak ada yang
                // tahu mana yang benar.
                //
                // Pemindahan station harus eksplisit: unpair lalu pair ulang,
                // supaya perubahannya tercatat (PRD §10).
                val target = command.stationCode
                val paired = current.stationCode
                if (target != null && paired != null && !paired.equals(target, ignoreCase = true)) {
                    return false
                }

                /*
                 * Tahap pembukaan hanya dimulai SEKALI per sesi.
                 *
                 * `session.updated` datang berkali-kali selama sesi berjalan
                 * — extend, F&B, swap. Kalau tahapnya di-reset setiap kali,
                 * customer yang sedang bermain tiba-tiba dilempar kembali ke
                 * video pembuka karena operator menambahkan sebotol minum.
                 */
                val sesiSama = current.sessionId == command.sessionId
                val phase = when {
                    command.pendingPayment -> StartupPhase.NONE
                    sesiSama && current.phase != StartupPhase.NONE -> current.phase
                    else -> StartupPhase.INTRO
                }

                store.replace(
                    current.copy(
                        mode = if (command.pendingPayment) {
                            DisplayMode.PENDING_PAYMENT
                        } else {
                            DisplayMode.TIMER
                        },
                        phase = phase,
                        sessionId = command.sessionId,
                        stationCode = command.stationCode ?: current.stationCode,
                        customerLabel = command.customerLabel,
                        startedAtMillis = command.startedAtMillis,
                        endAtMillis = command.endAtMillis,
                    )
                )
                true
            }

            is AgentCommand.EndSession -> {
                if (!store.current.isPaired) return false
                store.dropSession()
                true
            }

            is AgentCommand.IntroFinished -> {
                val current = store.current
                if (current.phase != StartupPhase.INTRO) return false
                store.replace(current.copy(phase = StartupPhase.AWAITING_HDMI))
                true
            }

            is AgentCommand.HdmiSignalDetected -> {
                val current = store.current
                /*
                 * Hanya sah saat benar-benar sedang menunggu.
                 *
                 * Sinyal HDMI bisa muncul kapan saja — konsol menyala sendiri,
                 * atau customer menekan tombolnya di tengah sesi. Kalau itu
                 * memindahkan tahap, video pembuka bisa terpotong dan layar
                 * diserahkan sebelum waktunya.
                 */
                if (current.phase != StartupPhase.AWAITING_HDMI) return false
                store.replace(current.copy(phase = StartupPhase.HANDED_OVER))
                true
            }

            is AgentCommand.SetLocked -> {
                // Sengaja TIDAK mengubah tampilan: perilaku LOCKED belum
                // diputuskan (OD-001/OD-004), dan menebaknya berarti operator
                // mengandalkan sesuatu yang bisa berubah total nanti.
                // Perintahnya diterima supaya kontraknya stabil.
                store.current.isPaired
            }
        }
    }
}
