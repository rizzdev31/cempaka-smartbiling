package id.cempaka.tvagent

import android.content.Context
import android.util.Log
import fi.iki.elonen.NanoHTTPD

/**
 * Server HTTP di TV — operator mengirim perintah langsung lewat LAN.
 *
 * **Ini implementasi sementara (DEC-015).** Di Tahap 2 digantikan
 * `ReverbCommandSource` yang menerima event dari Laravel. Karena keduanya
 * mengisi [CommandSource] yang sama, layar kiosk dan timer tidak perlu diubah.
 *
 * ## Endpoint
 *
 * | Metode | Path | Auth | Guna |
 * |---|---|---|---|
 * | GET | `/health` | tidak | identitas + kemampuan perangkat. Dipakai operator untuk menemukan TV lewat subnet scan |
 * | POST | `/pair` | kode | tukar kode pairing jadi device token |
 * | GET | `/state` | token | state yang sedang ditampilkan |
 * | POST | `/session` | token | mulai / perbarui sesi |
 * | DELETE | `/session` | token | akhiri sesi |
 * | POST | `/display/lock` | token | kunci / buka (diterima, belum berefek — OD-001) |
 * | POST | `/unpair` | token | cabut pairing |
 *
 * `/health` sengaja **tanpa auth**: operator harus bisa menemukan TV sebelum
 * punya token. Isinya hanya identitas perangkat — tidak ada data sesi,
 * customer, atau uang. Itu batas yang dijaga.
 */
class LocalHttpCommandSource(
    private val context: Context,
    private val store: StateStore,
    private val pairing: Pairing,
    private val applier: CommandApplier,
    port: Int = DEFAULT_PORT,
) : NanoHTTPD(port), CommandSource {

    override val label: String = "HTTP lokal :$listeningPort"

    override val isActive: Boolean get() = wasStarted()

    override fun start() {
        if (wasStarted()) return
        // SOCKET_READ_TIMEOUT default 5s; daemon=true supaya thread-nya tidak
        // menahan proses saat service dimatikan.
        start(SOCKET_READ_TIMEOUT, true)
        Log.i(TAG, "Server kontrol jalan di port $listeningPort")
    }

    override fun stop() {
        super.stop()
        Log.i(TAG, "Server kontrol berhenti")
    }

    override fun serve(session: IHTTPSession): Response {
        return try {
            route(session)
        } catch (e: Exception) {
            Log.e(TAG, "Gagal menangani ${session.method} ${session.uri}", e)
            error(Response.Status.INTERNAL_ERROR, "INTERNAL", "Kesalahan internal agen.")
        }
    }

    private fun route(session: IHTTPSession): Response {
        val path = session.uri.trimEnd('/').ifEmpty { "/" }
        val method = session.method

        return when {
            method == Method.GET && path == "/health" -> health()

            method == Method.POST && path == "/pair" -> pair(session)

            method == Method.GET && path == "/state" ->
                requireToken(session) { ok(stateJson()) }

            method == Method.POST && path == "/session" ->
                requireToken(session) { applySession(session) }

            method == Method.DELETE && path == "/session" ->
                requireToken(session) { endSession(session) }

            method == Method.POST && path == "/display/lock" ->
                requireToken(session) { setLocked(session) }

            method == Method.POST && path == "/unpair" ->
                requireToken(session) {
                    store.unpair()
                    pairing.regenerate()
                    ok(mapOf("unpaired" to true))
                }

            // Preflight: operator app berbasis Flutter web saat pengembangan
            // akan mengirim OPTIONS lebih dulu.
            method == Method.OPTIONS -> cors(newFixedLengthResponse(
                Response.Status.NO_CONTENT, MIME_JSON, ""
            ))

            else -> error(
                Response.Status.NOT_FOUND,
                "NOT_FOUND",
                "Endpoint tidak dikenal: $method $path",
            )
        }
    }

    // ── Endpoint ──────────────────────────────────────────────────────

    /**
     * Identitas + kemampuan. Tanpa auth, dan **tanpa data sesi**.
     *
     * Ini yang membuat subnet scan operator bisa menemukan TV, sekaligus
     * menjadi jawaban otomatis untuk OD-005: model, versi Android, dan
     * tingkat kiosk yang benar-benar aktif.
     */
    private fun health(): Response {
        val caps = DeviceCapabilities.read(context)
        val state = store.state.value

        return ok(
            mapOf(
                "agent" to "cempaka-tv-agent",
                "device_uid" to store.deviceUid,
                "paired" to state.isPaired,
                // Kode station aman ditampilkan: operator perlu tahu TV ini
                // sudah dipasangkan ke station mana sebelum memasangkannya
                // ke station lain.
                "station_code" to state.stationCode,
                "requires_pairing" to !state.isPaired,
                "pairing_locked" to pairing.isLocked,
                "device" to caps.toJsonMap(),
                "time" to mapOf(
                    "device_now" to System.currentTimeMillis(),
                    "synced" to TimeSync.isSynced,
                    "offset_millis" to TimeSync.offset,
                    "seconds_since_sync" to TimeSync.secondsSinceSync(),
                ),
            )
        )
    }

    private fun pair(session: IHTTPSession): Response {
        if (pairing.isLocked) {
            return error(
                Response.Status.FORBIDDEN,
                "PAIRING_LOCKED",
                "Terlalu banyak kode salah. Mulai ulang aplikasi di TV.",
            )
        }

        val body = body(session)
        val token = pairing.tryPair(
            submittedCode = body.string("code"),
            stationCode = body.string("station_code"),
        )
            ?: return error(
                Response.Status.UNAUTHORIZED,
                "PAIRING_CODE_INVALID",
                "Kode pairing salah.",
            )

        body.long("sender_time")?.let { TimeSync.sync(it) }

        return ok(
            mapOf(
                "device_token" to token,
                "device_uid" to store.deviceUid,
                "station_code" to store.state.value.stationCode,
            )
        )
    }

    private fun applySession(session: IHTTPSession): Response {
        val body = body(session)

        val sessionId = body.string("session_id")
            ?: return error(
                Response.Status.BAD_REQUEST,
                "VALIDATION_FAILED",
                "session_id wajib.",
            )

        val endAt = body.long("end_at")
        val pending = body.bool("pending_payment")

        // Sesi yang sudah berjalan wajib punya end_at — tanpa itu TV tidak
        // bisa menghitung apa pun dan akan menampilkan timer kosong.
        if (!pending && endAt == null) {
            return error(
                Response.Status.BAD_REQUEST,
                "VALIDATION_FAILED",
                "end_at wajib untuk sesi yang sudah berjalan.",
            )
        }

        val applied = applier.apply(
            AgentCommand.ApplySession(
                sessionId = sessionId,
                stationCode = body.string("station_code"),
                customerLabel = body.string("customer_label"),
                startedAtMillis = body.long("started_at"),
                endAtMillis = endAt,
                pendingPayment = pending,
                senderTimeMillis = body.long("sender_time"),
            )
        )

        return if (applied) ok(stateJson()) else notPaired()
    }

    private fun endSession(session: IHTTPSession): Response {
        val body = body(session)
        val applied = applier.apply(
            AgentCommand.EndSession(senderTimeMillis = body.long("sender_time"))
        )
        return if (applied) ok(stateJson()) else notPaired()
    }

    private fun setLocked(session: IHTTPSession): Response {
        val body = body(session)
        val locked = body.bool("locked", default = true)
        val applied = applier.apply(
            AgentCommand.SetLocked(
                locked = locked,
                senderTimeMillis = body.long("sender_time"),
            )
        )
        if (!applied) return notPaired()

        // Jujur: perintahnya diterima tapi belum mengubah apa pun.
        return ok(
            mapOf(
                "accepted" to true,
                "applied" to false,
                "reason" to "Perilaku kunci layar belum diputuskan (OD-001, OD-004).",
                "state" to stateJson(),
            )
        )
    }

    // ── Pembantu ──────────────────────────────────────────────────────

    private fun stateJson(): Map<String, Any?> {
        val s = store.state.value
        return mapOf(
            "mode" to s.mode.name,
            "station_code" to s.stationCode,
            "session_id" to s.sessionId,
            "customer_label" to s.customerLabel,
            "started_at" to s.startedAtMillis,
            "end_at" to s.endAtMillis,
            // Sisa waktu disertakan sebagai informasi diagnostik, BUKAN
            // sebagai sumber kebenaran — operator tetap menghitung dari
            // end_at. Dikirim supaya saat debugging di lokasi terlihat apakah
            // jam TV dan jam operator sepakat.
            "remaining_millis" to s.endAtMillis?.let { TimeSync.remainingMillis(it) },
            "time_synced" to TimeSync.isSynced,
        )
    }

    private fun body(session: IHTTPSession): JsonBody {
        val files = HashMap<String, String>()
        return runCatching {
            session.parseBody(files)
            Json.decode(files["postData"])
        }.getOrElse { Json.decode(null) }
    }

    private inline fun requireToken(
        session: IHTTPSession,
        block: () -> Response,
    ): Response {
        if (!store.state.value.isPaired) return notPaired()

        val token = session.headers["x-agent-token"]
            ?: session.parameters["token"]?.firstOrNull()

        if (!pairing.isTokenValid(token)) {
            return error(
                Response.Status.UNAUTHORIZED,
                "DEVICE_TOKEN_INVALID",
                "Token device tidak sah atau sudah dicabut.",
            )
        }
        return block()
    }

    private fun notPaired() = error(
        Response.Status.FORBIDDEN,
        "DEVICE_NOT_PAIRED",
        "TV belum dipasangkan. Masukkan kode yang tampil di layar TV.",
    )

    private fun ok(payload: Map<String, Any?>): Response = cors(
        newFixedLengthResponse(Response.Status.OK, MIME_JSON, Json.encode(payload))
    )

    private fun error(
        status: Response.Status,
        code: String,
        message: String,
    ): Response = cors(
        newFixedLengthResponse(
            status,
            MIME_JSON,
            // Bentuknya mengikuti kontrak §2 supaya penanganan error di
            // operator app sama untuk agen dan untuk Laravel nanti.
            Json.encode(
                mapOf("error" to mapOf("code" to code, "message" to message))
            ),
        )
    )

    private fun cors(response: Response): Response = response.apply {
        // Operator app dijalankan di Chrome saat pengembangan, jadi tanpa ini
        // request-nya diblokir browser. Dibatasi ke LAN oleh jaringan itu
        // sendiri, bukan oleh header ini.
        addHeader("Access-Control-Allow-Origin", "*")
        addHeader("Access-Control-Allow-Methods", "GET, POST, DELETE, OPTIONS")
        addHeader("Access-Control-Allow-Headers", "Content-Type, X-Agent-Token")
        addHeader("Cache-Control", "no-store")
    }

    companion object {
        const val DEFAULT_PORT = 8787
        private const val TAG = "AgentHttp"
        private const val MIME_JSON = "application/json"
    }
}
