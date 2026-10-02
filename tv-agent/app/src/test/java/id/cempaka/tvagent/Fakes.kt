package id.cempaka.tvagent

/**
 * Pengganti [StateStore] untuk test JVM.
 *
 * [StateStore] butuh `Context` Android. Dengan [TokenHolder] dan
 * [AgentStateHolder], aturan yang paling mahal kalau salah — siapa boleh
 * mengirim perintah, perintah mana yang diterapkan — bisa diuji tanpa
 * emulator.
 */
class FakeStore(
    initial: AgentState = AgentState.UNPAIRED,
) : TokenHolder, AgentStateHolder {

    var state: AgentState = initial
    var token: String? = null
    var dropCount: Int = 0

    override val currentToken: String? get() = token

    override fun onPaired(token: String, stationCode: String) {
        this.token = token
        state = state.copy(mode = DisplayMode.IDLE, stationCode = stationCode)
    }

    override val current: AgentState get() = state

    override fun replace(next: AgentState) {
        state = next
    }

    override fun dropSession() {
        dropCount++
        state = state.copy(
            mode = if (token == null) DisplayMode.PAIRING else DisplayMode.IDLE,
            sessionId = null,
            customerLabel = null,
            startedAtMillis = null,
            endAtMillis = null,
        )
    }

    /** Pintasan: anggap sudah dipasangkan ke [stationCode]. */
    fun paired(stationCode: String = "ST01"): FakeStore {
        onPaired("token-uji", stationCode)
        return this
    }
}
