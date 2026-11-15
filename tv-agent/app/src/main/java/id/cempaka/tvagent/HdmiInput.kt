package id.cempaka.tvagent

import android.content.Context
import android.content.Intent
import android.media.tv.TvContract
import android.media.tv.TvInputInfo
import android.media.tv.TvInputManager
import android.os.Build
import android.util.Log

/**
 * Input HDMI: mendeteksi sinyal konsol, lalu menyerahkan layar kepadanya.
 *
 * ## Yang kelas ini TIDAK lakukan: menjanjikan
 *
 * PRD §5 dan R01 melarang mengklaim dukungan HDMI switching sebelum diuji
 * di TV aktual. Jadi kelas ini **tidak pernah berpura-pura berhasil**. Ia
 * melaporkan apa yang benar-benar ditemukan lewat [report], dan hasilnya
 * ikut keluar di `GET /health` — sama seperti [DeviceCapabilities] menjawab
 * OD-005 dengan fakta, bukan dengan asumsi.
 *
 * Kalau TV ini ternyata tidak mengizinkan, yang terjadi bukan crash dan
 * bukan layar hitam: agen tetap di layar dengan pesan "menunggu input",
 * dan customer memindah input dengan remote seperti biasa. Itu perilaku
 * yang sama dengan sebelum fitur ini ada.
 *
 * ## Kenapa TvInputManager, bukan menebak merek
 *
 * Setiap vendor punya intent rahasianya sendiri untuk pindah input, dan
 * daftar itu basi secepat model TV berganti. `TvInputManager` adalah satu-
 * satunya jalan yang didokumentasikan Android, dan ia juga yang memberi
 * tahu **apakah ada sinyal** — bagian yang sebenarnya paling dibutuhkan.
 */
class HdmiInput(private val context: Context) {

    data class Report(
        /** Layanan TV Input Framework tersedia di perangkat ini? */
        val frameworkAvailable: Boolean,
        /** Jumlah input HDMI yang terlihat. */
        val hdmiInputCount: Int,
        /** Id input yang dipakai, kalau ada. */
        val selectedInputId: String?,
        /** Terakhir diketahui: ada sinyal di input itu? */
        val signalPresent: Boolean,
        /** Hasil percobaan pindah input terakhir. Null = belum pernah dicoba. */
        val lastSwitchOk: Boolean?,
        val note: String,
    ) {
        fun toJsonMap(): Map<String, Any?> = mapOf(
            "framework_available" to frameworkAvailable,
            "hdmi_input_count" to hdmiInputCount,
            "selected_input_id" to selectedInputId,
            "signal_present" to signalPresent,
            "last_switch_ok" to lastSwitchOk,
            "note" to note,
        )
    }

    private val manager: TvInputManager? by lazy {
        runCatching {
            context.getSystemService(Context.TV_INPUT_SERVICE) as? TvInputManager
        }.getOrNull()
    }

    private var lastSwitchOk: Boolean? = null
    private var lastNote: String = "belum diperiksa"

    /** Semua input bertipe HDMI yang dikenali sistem. */
    private fun hdmiInputs(): List<TvInputInfo> {
        val m = manager ?: return emptyList()
        return runCatching {
            m.tvInputList.filter { it.type == TvInputInfo.TYPE_HDMI }
        }.getOrDefault(emptyList())
    }

    /**
     * Input HDMI yang akan dipakai.
     *
     * Kalau ada lebih dari satu, dipilih yang **sedang ada sinyalnya**.
     * Memilih HDMI1 secara buta akan salah di rental yang menancapkan
     * konsol di port mana pun yang kebetulan kosong.
     */
    private fun pickInput(): TvInputInfo? {
        val inputs = hdmiInputs()
        if (inputs.isEmpty()) return null
        return inputs.firstOrNull { isConnected(it.id) } ?: inputs.first()
    }

    private fun isConnected(inputId: String): Boolean {
        val m = manager ?: return false
        return runCatching {
            m.getInputState(inputId) == TvInputManager.INPUT_STATE_CONNECTED
        }.getOrDefault(false)
    }

    /**
     * Apakah konsol sudah mengirim sinyal?
     *
     * `INPUT_STATE_CONNECTED` berarti ada perangkat hidup di ujung kabel.
     * `CONNECTED_STANDBY` sengaja dianggap BELUM: konsol yang tercolok tapi
     * mati tetap membuat TV melaporkan standby, dan menyerahkan layar ke
     * sana hanya menampilkan "no signal" kepada customer.
     */
    fun hasSignal(): Boolean {
        val input = pickInput() ?: return false
        return isConnected(input.id)
    }

    /**
     * Serahkan layar ke input HDMI.
     *
     * @return `true` hanya kalau sistem benar-benar menerima perintahnya.
     */
    fun handOver(): Boolean {
        val input = pickInput()
        if (input == null) {
            lastSwitchOk = false
            lastNote = "tidak ada input HDMI yang terlihat"
            return false
        }

        /*
         * Satu-satunya cara yang didokumentasikan: buka URI passthrough
         * milik input itu. Di TV yang mengizinkan, sistem berpindah ke
         * HDMI. Di TV yang tidak, tidak ada Activity yang menanganinya dan
         * kita tahu itu dari ActivityNotFoundException — bukan dari diam.
         */
        val uri = runCatching {
            TvContract.buildChannelUriForPassthroughInput(input.id)
        }.getOrNull()

        if (uri == null) {
            lastSwitchOk = false
            lastNote = "URI passthrough tidak bisa dibentuk"
            return false
        }

        val intent = Intent(Intent.ACTION_VIEW, uri)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)

        return runCatching {
            context.startActivity(intent)
            lastSwitchOk = true
            lastNote = "berpindah ke ${input.id}"
            true
        }.getOrElse { e ->
            lastSwitchOk = false
            lastNote = "ditolak sistem: ${e.javaClass.simpleName}"
            Log.w(TAG, "Gagal pindah ke input HDMI", e)
            false
        }
    }

    fun report(): Report {
        val inputs = hdmiInputs()
        val picked = pickInput()

        val note = when {
            manager == null -> "perangkat tidak punya TV Input Framework"
            inputs.isEmpty() -> "framework ada, tapi tidak ada input HDMI terdaftar"
            else -> lastNote
        }

        return Report(
            frameworkAvailable = manager != null,
            hdmiInputCount = inputs.size,
            selectedInputId = picked?.id,
            signalPresent = picked?.let { isConnected(it.id) } ?: false,
            lastSwitchOk = lastSwitchOk,
            note = note,
        )
    }

    companion object {
        private const val TAG = "HdmiInput"

        /** Framework TV Input baru ada sejak API 21; minSdk sudah di atasnya. */
        val isSupportedApi: Boolean get() = Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP
    }
}
