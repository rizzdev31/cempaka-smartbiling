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

                store.replace(
                    current.copy(
                        mode = if (command.pendingPayment) {
                            DisplayMode.PENDING_PAYMENT
                        } else {
                            DisplayMode.TIMER
                        },
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
