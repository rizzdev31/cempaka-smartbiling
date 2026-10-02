package id.cempaka.tvagent

import android.content.Context
import android.content.SharedPreferences
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

/**
 * Penyimpanan state agen.
 *
 * PRD §16 mewajibkan TV menyimpan `end_at` dan state minimum untuk recovery:
 * setelah TV restart, timer harus lanjut dari waktu yang benar **tanpa**
 * menunggu operator atau server. Itu inti dari T12.
 *
 * `SharedPreferences` dipilih, bukan DataStore: yang disimpan hanya segelintir
 * nilai, dan menambah dependensi untuk itu tidak sepadan. Tulisannya
 * `commit()` sinkron pada perubahan penting — kalau TV mati listrik sedetik
 * setelah sesi dimulai, `end_at` harus sudah ada di disk.
 */
class StateStore(context: Context) : TokenHolder, AgentStateHolder {

    private val prefs: SharedPreferences =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    private val _state = MutableStateFlow(load())

    /** State terkini. Satu sumber untuk UI dan server HTTP. */
    val state: StateFlow<AgentState> = _state.asStateFlow()

    // ── Pairing ───────────────────────────────────────────────────────

    /** Token yang harus dibawa setiap perintah operator. Null = belum pair. */
    override val currentToken: String?
        get() = prefs.getString(KEY_TOKEN, null)

    /** Alias yang lebih deskriptif untuk pemanggil di luar [Pairing]. */
    val deviceToken: String? get() = currentToken

    /**
     * ID device yang stabil antar restart, dipakai operator untuk mengenali
     * TV yang sama walau IP-nya berubah.
     */
    val deviceUid: String
        get() = prefs.getString(KEY_UID, null) ?: run {
            val generated = "tv-" + java.util.UUID.randomUUID()
                .toString().replace("-", "").take(12)
            prefs.edit().putString(KEY_UID, generated).commit()
            generated
        }

    override fun onPaired(token: String, stationCode: String) {
        prefs.edit().putString(KEY_TOKEN, token).commit()
        update(
            _state.value.copy(
                mode = DisplayMode.IDLE,
                stationCode = stationCode,
            )
        )
    }

    /**
     * Cabut pairing. Token dan sesi dihapus, state kembali ke PAIRING.
     *
     * Dipanggil operator saat TV dipindah ke station lain atau diganti —
     * PRD §10 mewajibkan token dapat dicabut.
     */
    fun unpair() {
        prefs.edit().remove(KEY_TOKEN).commit()
        update(AgentState.UNPAIRED)
    }

    // ── Sesi ──────────────────────────────────────────────────────────

    override val current: AgentState get() = _state.value

    override fun replace(next: AgentState) = update(next)

    override fun dropSession() = clearSession()

    fun update(next: AgentState) {
        _state.value = next
        persist(next)
    }

    fun clearSession() {
        update(
            _state.value.copy(
                mode = if (deviceToken == null) DisplayMode.PAIRING else DisplayMode.IDLE,
                sessionId = null,
                customerLabel = null,
                startedAtMillis = null,
                endAtMillis = null,
            )
        )
    }

    // ── Persistensi ───────────────────────────────────────────────────

    private fun persist(s: AgentState) {
        prefs.edit().apply {
            putString(KEY_MODE, s.mode.name)
            putString(KEY_STATION, s.stationCode)
            putString(KEY_SESSION, s.sessionId)
            putString(KEY_CUSTOMER, s.customerLabel)
            s.startedAtMillis?.let { putLong(KEY_STARTED, it) } ?: remove(KEY_STARTED)
            s.endAtMillis?.let { putLong(KEY_END, it) } ?: remove(KEY_END)
        }.commit()
    }

    private fun load(): AgentState {
        val token = prefs.getString(KEY_TOKEN, null)
        val savedMode = prefs.getString(KEY_MODE, null)
            ?.let { runCatching { DisplayMode.valueOf(it) }.getOrNull() }

        // Belum pernah pair -> apa pun yang tersimpan tidak relevan.
        if (token == null) return AgentState.UNPAIRED

        return AgentState(
            // LOCKED tidak pernah dipulihkan: perilakunya belum diputuskan
            // (OD-001), jadi TV yang restart dalam keadaan itu kembali ke
            // keadaan yang pasti aman.
            mode = when (savedMode) {
                null, DisplayMode.PAIRING, DisplayMode.LOCKED -> DisplayMode.IDLE
                else -> savedMode
            },
            stationCode = prefs.getString(KEY_STATION, null),
            sessionId = prefs.getString(KEY_SESSION, null),
            customerLabel = prefs.getString(KEY_CUSTOMER, null),
            startedAtMillis = prefs.takeIf { it.contains(KEY_STARTED) }
                ?.getLong(KEY_STARTED, 0L),
            endAtMillis = prefs.takeIf { it.contains(KEY_END) }
                ?.getLong(KEY_END, 0L),
        )
    }

    private companion object {
        const val PREFS = "cempaka_tv_agent"
        const val KEY_TOKEN = "device_token"
        const val KEY_UID = "device_uid"
        const val KEY_MODE = "mode"
        const val KEY_STATION = "station_code"
        const val KEY_SESSION = "session_id"
        const val KEY_CUSTOMER = "customer_label"
        const val KEY_STARTED = "started_at"
        const val KEY_END = "end_at"
    }
}
