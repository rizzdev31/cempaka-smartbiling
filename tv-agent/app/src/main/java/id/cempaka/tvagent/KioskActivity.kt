package id.cempaka.tvagent

import android.content.Context
import android.graphics.drawable.GradientDrawable
import android.net.Uri
import android.net.wifi.WifiManager
import android.os.Build
import android.os.Bundle
import android.view.View
import android.view.WindowManager
import android.widget.FrameLayout
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.TextView
import android.widget.VideoView
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.WindowInsetsControllerCompat
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import kotlinx.coroutines.Dispatchers
import java.net.Inet4Address
import java.net.NetworkInterface
import kotlin.math.abs

/**
 * Layar kiosk.
 *
 * ## Timer dihitung di sini, bukan dikirim server
 *
 * PRD §16: server mengirim `end_at`, TV menghitung sendiri. Konsekuensinya
 * yang penting: **saat koneksi terputus, timer tetap benar.** Itu satu-satunya
 * alasan desain ini dipilih, dan alasan yang sama membuatnya tidak perlu
 * diubah saat Reverb masuk di Tahap 2.
 *
 * ## Kiosk: dua tingkat, dan yang kedua belum pasti
 *
 * Tingkat 1 (selalu): fullscreen immersive, layar tetap menyala, tombol
 * kembali dinonaktifkan. Customer masih bisa keluar lewat HOME.
 *
 * Tingkat 2 (butuh Device Owner): Lock Task mode — benar-benar tidak bisa
 * keluar. Dicoba otomatis; kalau tidak diizinkan sistem, aplikasi **tidak
 * mengklaim** berhasil (PRD §5). Lihat OD-005.
 */
class KioskActivity : AppCompatActivity() {

    private lateinit var app: AgentApp

    private lateinit var sessionPane: LinearLayout
    private lateinit var pairingPane: LinearLayout
    private lateinit var stationCode: TextView
    private lateinit var timer: TextView
    private lateinit var statusRow: LinearLayout
    private lateinit var statusDot: View
    private lateinit var statusText: TextView
    private lateinit var customerLabel: TextView
    private lateinit var pairingCode: TextView
    private lateinit var pairingAddress: TextView
    private lateinit var diagnostics: TextView
    private lateinit var linkDot: View

    private lateinit var idleLogo: ImageView
    private lateinit var introPane: FrameLayout
    private lateinit var introVideo: VideoView
    private lateinit var awaitingPane: LinearLayout
    private lateinit var awaitingStation: TextView
    private lateinit var awaitingHint: TextView

    private var capabilities: DeviceCapabilities? = null
    private var lockTaskActive = false

    private lateinit var hdmi: HdmiInput

    /** Video hanya boleh disiapkan sekali per sesi. */
    private var introPrepared = false

    /** Serah terima hanya dicoba sekali; kalau gagal, jangan diulang terus. */
    private var handoverTried = false

    /** Sejak kapan layar menunggu sinyal — untuk memunculkan petunjuk manual. */
    private var awaitingSince = 0L

    private var hdmiWatch: Job? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        app = AgentApp.of(this)
        setContentView(R.layout.activity_kiosk)
        bindViews()

        capabilities = DeviceCapabilities.read(this)
        hdmi = HdmiInput(this)

        // Layar tidak boleh mati saat sesi berjalan — customer sedang bermain
        // dan timer harus terlihat.
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)

        AgentService.start(this)
        observeState()
        startTicker()
    }

    override fun onResume() {
        super.onResume()
        applyImmersive()
        tryEnterLockTask()
        render()
    }

    override fun onPause() {
        super.onPause()
        // Lock Task tidak dilepas di sini: melepasnya saat pause berarti
        // customer bisa keluar hanya dengan memicu dialog sistem. Dilepas
        // hanya lewat perintah operator (unpair).
    }

    @Deprecated("Kiosk sengaja menahan tombol kembali")
    override fun onBackPressed() {
        // Tidak memanggil super: tombol kembali tidak boleh keluar dari kiosk.
        // Ini tingkat 1 — HOME masih bisa, dan itu dilaporkan apa adanya.
    }

    // ── Pengikatan & render ───────────────────────────────────────────

    private fun bindViews() {
        sessionPane = findViewById(R.id.sessionPane)
        pairingPane = findViewById(R.id.pairingPane)
        stationCode = findViewById(R.id.stationCode)
        timer = findViewById(R.id.timer)
        statusRow = findViewById(R.id.statusRow)
        statusDot = findViewById(R.id.statusDot)
        statusText = findViewById(R.id.statusText)
        customerLabel = findViewById(R.id.customerLabel)
        pairingCode = findViewById(R.id.pairingCode)
        pairingAddress = findViewById(R.id.pairingAddress)
        diagnostics = findViewById(R.id.diagnostics)
        linkDot = findViewById(R.id.linkDot)

        idleLogo = findViewById(R.id.idleLogo)
        introPane = findViewById(R.id.introPane)
        introVideo = findViewById(R.id.introVideo)
        awaitingPane = findViewById(R.id.awaitingPane)
        awaitingStation = findViewById(R.id.awaitingStation)
        awaitingHint = findViewById(R.id.awaitingHint)
    }

    private fun observeState() {
        lifecycleScope.launch {
            app.store.state.collect { render() }
        }
    }

    /**
     * Satu ticker untuk seluruh layar.
     *
     * Interval 500 ms, bukan 1000: dengan 1000 ms pergantian detik bisa
     * terlihat melompat dua angka saat ticker dan jam tidak sefase. 500 ms
     * cukup murah untuk satu TextView.
     */
    private fun startTicker() {
        lifecycleScope.launch {
            while (isActive) {
                render()
                delay(500)
            }
        }
    }

    private fun render() {
        val state = app.store.state.value

        /*
         * Tiga pane saling meniadakan. Dikerjakan di satu tempat supaya
         * tidak ada kombinasi yang tertinggal terlihat — dua pane yang
         * sama-sama VISIBLE adalah bug yang hanya muncul di TV, bukan di
         * emulator.
         */
        val fase = if (state.mode == DisplayMode.TIMER) state.phase else StartupPhase.NONE

        introPane.visibility = if (fase == StartupPhase.INTRO) View.VISIBLE else View.GONE

        /*
         * Selama video, layar HANYA berisi video.
         *
         * Baris diagnostik dan titik koneksi berguna sepanjang hari, tapi di
         * atas video pembuka keduanya terbaca sebagai cacat tampilan —
         * customer tidak tahu itu alat bantu teknisi.
         */
        val bersih = fase == StartupPhase.INTRO
        diagnostics.visibility = if (bersih) View.GONE else View.VISIBLE
        linkDot.visibility = if (bersih) View.GONE else linkDot.visibility
        awaitingPane.visibility =
            if (fase == StartupPhase.AWAITING_HDMI) View.VISIBLE else View.GONE

        when (fase) {
            StartupPhase.INTRO -> {
                sessionPane.visibility = View.GONE
                pairingPane.visibility = View.GONE
                startIntroIfNeeded()
                return
            }

            StartupPhase.AWAITING_HDMI -> {
                sessionPane.visibility = View.GONE
                pairingPane.visibility = View.GONE
                renderAwaiting(state)
                return
            }

            StartupPhase.HANDED_OVER -> {
                // Layar seharusnya sudah milik konsol. Kalau kita masih
                // terlihat, serah terimanya gagal — dan itu ditampilkan apa
                // adanya lewat pane menunggu, bukan disembunyikan.
                tryHandOverOnce()
            }

            StartupPhase.NONE -> {
                stopIntro()
                stopHdmiWatch()
                handoverTried = false
            }
        }

        if (!state.isPaired) {
            sessionPane.visibility = View.GONE
            pairingPane.visibility = View.VISIBLE
            pairingCode.text = app.pairing.code
            pairingAddress.text = buildString {
                append(localIpv4() ?: "IP belum didapat")
                append(':')
                append(LocalHttpCommandSource.DEFAULT_PORT)
            }
            renderDiagnostics(state)
            return
        }

        sessionPane.visibility = View.VISIBLE
        pairingPane.visibility = View.GONE

        // Default tiap render; cabang idle di bawah yang membalikkannya.
        idleLogo.visibility = View.GONE
        timer.visibility = View.VISIBLE

        stationCode.text = state.stationCode ?: "—"

        customerLabel.text = state.customerLabel.orEmpty()
        customerLabel.visibility =
            if (state.customerLabel.isNullOrBlank()) View.GONE else View.VISIBLE

        when {
            state.mode == DisplayMode.PENDING_PAYMENT -> {
                timer.text = "--:--:--"
                setStatus(R.string.status_pending, R.color.status_pending)
            }

            state.hasSession -> renderRunning(state.endAtMillis!!)

            else -> {
                /*
                 * Idle: logo rental + kode station, bukan angka.
                 *
                 * Ini yang dilihat orang sepanjang hari di station kosong.
                 * Deretan tanda hubung terbaca seperti kerusakan, dan kata
                 * "Tersedia" setinggi 132sp lebih besar daripada mereknya
                 * sendiri. Timer disembunyikan sepenuhnya — tidak ada waktu
                 * yang sedang berjalan untuk ditampilkan.
                 */
                idleLogo.visibility = View.VISIBLE
                timer.visibility = View.GONE
                setStatus(R.string.idle_hint, R.color.status_available)
            }
        }

        renderDiagnostics(state)
    }

    private fun renderRunning(endAtMillis: Long) {
        val remaining = TimeSync.remainingMillis(endAtMillis)
        timer.text = formatCountdown(remaining)

        val minutesLeft = remaining / 60_000.0

        val color = when {
            remaining < 0 -> R.color.status_expired
            minutesLeft <= 1 -> R.color.status_expired
            minutesLeft <= 10 -> R.color.status_warning
            else -> R.color.tv_on_surface
        }
        timer.setTextColor(getColor(color))

        when {
            remaining < 0 -> setStatus(R.string.status_expired, R.color.status_expired)
            minutesLeft <= 10 -> setStatus(R.string.status_warning, R.color.status_warning)
            else -> setStatus(R.string.status_playing, R.color.status_active)
        }
    }

    private fun setStatus(textRes: Int, colorRes: Int) {
        val color = getColor(colorRes)
        statusText.setText(textRes)
        statusText.setTextColor(color)
        (statusDot.background as? GradientDrawable)?.mutate()?.let {
            (it as GradientDrawable).setColor(color)
            statusDot.background = it
        }
        statusRow.visibility = View.VISIBLE
    }

    private fun renderDiagnostics(state: AgentState) {
        val caps = capabilities
        val ip = localIpv4() ?: "tanpa IP"

        val clock = when {
            !TimeSync.isSynced -> "jam belum sinkron"
            abs(TimeSync.offset) < 2_000 -> "jam sinkron"
            else -> "jam beda ${TimeSync.offset / 1000}s"
        }

        val kiosk = when {
            lockTaskActive -> "kiosk terkunci"
            caps?.kioskTier == KioskTier.LOCK_TASK -> "kiosk siap"
            else -> "kiosk lunak"
        }

        diagnostics.text = buildString {
            append(ip).append(':').append(LocalHttpCommandSource.DEFAULT_PORT)
            append(" · ").append(clock)
            append(" · ").append(kiosk)
            caps?.let { append(" · v").append(it.appVersion) }
        }

        // Titik koneksi: hijau kalau baru menerima perintah, abu kalau lama
        // tidak ada kabar. Kecil dan di pojok — PRD §16, customer tidak perlu
        // tahu kalau koneksi terputus, tapi operator perlu.
        val staleSeconds = TimeSync.secondsSinceSync()
        val linkColor = when {
            staleSeconds == null -> R.color.tv_outline
            staleSeconds < 120 -> R.color.status_active
            else -> R.color.status_warning
        }
        (linkDot.background as? GradientDrawable)?.mutate()?.let {
            (it as GradientDrawable).setColor(getColor(linkColor))
            linkDot.background = it
        }
        linkDot.visibility = if (state.isPaired) View.VISIBLE else View.GONE
    }

    // ── Pembukaan layar: video -> menunggu HDMI -> serah terima ──────

    /**
     * Mulai video pembuka. Idempoten — `render()` dipanggil tiap 500 ms.
     */
    private fun startIntroIfNeeded() {
        if (introPrepared) return
        introPrepared = true

        val uri = Uri.parse("android.resource://$packageName/${R.raw.intro_tvagent}")
        introVideo.setVideoURI(uri)

        introVideo.setOnPreparedListener { mp ->
            // Tanpa suara: TV rental berdiri di ruangan berisi orang lain
            // yang sedang bermain. Video pembuka tidak boleh menimpa suara
            // konsol di sebelahnya.
            mp.setVolume(0f, 0f)
            mp.isLooping = false
        }

        introVideo.setOnCompletionListener { selesaikanIntro("video selesai") }

        /*
         * Error TIDAK boleh menahan layar.
         *
         * Berkas rusak, codec tidak didukung, atau pemutar gagal — apa pun
         * sebabnya, customer sudah membayar dan harus bisa bermain. Jadi
         * kegagalan video memajukan tahap, bukan menghentikannya.
         */
        introVideo.setOnErrorListener { _, what, extra ->
            selesaikanIntro("video gagal ($what/$extra)")
            true
        }

        introVideo.start()

        /*
         * Jaring pengaman. `OnCompletionListener` tidak selalu terpanggil di
         * semua implementasi VideoView — dan kalau ia diam, layar tertahan
         * di video selamanya. Batasnya murah hati: durasi video 10 detik.
         */
        lifecycleScope.launch {
            delay(INTRO_TIMEOUT_MS)
            if (app.store.state.value.phase == StartupPhase.INTRO) {
                selesaikanIntro("batas waktu video terlewat")
            }
        }
    }

    private fun selesaikanIntro(alasan: String) {
        android.util.Log.i(TAG, "Intro selesai: $alasan")
        stopIntro()
        app.applier.apply(AgentCommand.IntroFinished())
    }

    private fun stopIntro() {
        if (!introPrepared) return
        introPrepared = false
        runCatching {
            introVideo.stopPlayback()
            introVideo.setOnCompletionListener(null)
            introVideo.setOnErrorListener(null)
        }
    }

    private fun renderAwaiting(state: AgentState) {
        awaitingStation.text = state.stationCode ?: "—"

        if (awaitingSince == 0L) awaitingSince = System.currentTimeMillis()

        /*
         * Petunjuk manual muncul BELAKANGAN, bukan sejak awal.
         *
         * Di TV yang serah terimanya berhasil, layar berpindah dalam
         * hitungan detik dan menyuruh customer menekan SOURCE hanya
         * membingungkan. Di TV yang tidak bisa, diam saja jauh lebih buruk.
         */
        val menunggu = System.currentTimeMillis() - awaitingSince
        awaitingHint.setText(
            if (menunggu > MANUAL_HINT_AFTER_MS) R.string.awaiting_hdmi_manual
            else R.string.awaiting_hdmi_hint
        )

        startHdmiWatch()
    }

    /**
     * Pantau sinyal HDMI selama menunggu.
     *
     * Polling, bukan callback: `TvInputManager.registerCallback` tidak
     * tersedia merata di TV murah, dan satu pemeriksaan per detik pada satu
     * nilai status jauh lebih murah daripada kode yang hanya jalan di
     * sebagian perangkat.
     */
    private fun startHdmiWatch() {
        if (hdmiWatch?.isActive == true) return

        hdmiWatch = lifecycleScope.launch {
            while (isActive && app.store.state.value.phase == StartupPhase.AWAITING_HDMI) {
                val ada = withContext(Dispatchers.IO) { hdmi.hasSignal() }
                if (ada) {
                    app.applier.apply(AgentCommand.HdmiSignalDetected())
                    break
                }
                delay(HDMI_POLL_MS)
            }
        }
    }

    private fun stopHdmiWatch() {
        hdmiWatch?.cancel()
        hdmiWatch = null
        awaitingSince = 0L
    }

    /**
     * Serahkan layar ke konsol.
     *
     * Dicoba SEKALI. Kalau sistem menolak — dan PRD §5/R01 memang melarang
     * menganggapnya pasti berhasil — tahapnya dikembalikan ke menunggu,
     * sehingga customer melihat petunjuk menekan SOURCE alih-alih layar
     * yang diam.
     */
    private fun tryHandOverOnce() {
        if (handoverTried) return
        handoverTried = true

        lifecycleScope.launch {
            val ok = withContext(Dispatchers.IO) { hdmi.handOver() }
            if (!ok) {
                android.util.Log.w(TAG, "Serah terima HDMI ditolak: ${hdmi.report().note}")
                app.store.update(
                    app.store.state.value.copy(phase = StartupPhase.AWAITING_HDMI)
                )
                // Petunjuk manual langsung, tanpa menunggu: kita sudah tahu
                // otomatisnya tidak akan datang.
                awaitingSince = 1L
            }
        }
    }

    // ── Kiosk ─────────────────────────────────────────────────────────

    private fun applyImmersive() {
        WindowCompat.setDecorFitsSystemWindows(window, false)
        WindowInsetsControllerCompat(window, window.decorView).apply {
            hide(WindowInsetsCompat.Type.systemBars())
            systemBarsBehavior =
                WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
        }
    }

    /**
     * Coba masuk Lock Task mode.
     *
     * Berhasil hanya kalau paket ini diizinkan Device Owner. Kalau tidak,
     * tidak ada efek dan tidak ada klaim keberhasilan — `lockTaskActive`
     * tetap `false` dan diagnostik menampilkan "kiosk lunak".
     */
    private fun tryEnterLockTask() {
        if (lockTaskActive) return
        val caps = capabilities ?: return
        if (!caps.isLockTaskPermitted) return

        runCatching {
            startLockTask()
            lockTaskActive = true
        }.onFailure {
            lockTaskActive = false
        }
    }

    // ── Pembantu ──────────────────────────────────────────────────────

    /**
     * IP lokal perangkat.
     *
     * Dibaca dari NetworkInterface, bukan dari WifiManager: TV sering memakai
     * Ethernet, dan `WifiManager.connectionInfo.ipAddress` mengembalikan 0
     * dalam kasus itu — sehingga operator melihat alamat kosong padahal TV
     * terhubung.
     */
    private fun localIpv4(): String? {
        return runCatching {
            NetworkInterface.getNetworkInterfaces().asSequence()
                .filter { it.isUp && !it.isLoopback }
                .flatMap { it.inetAddresses.asSequence() }
                .filterIsInstance<Inet4Address>()
                .firstOrNull { !it.isLoopbackAddress }
                ?.hostAddress
        }.getOrNull()
    }

    private fun formatCountdown(millis: Long): String {
        val negative = millis < 0
        val total = abs(millis) / 1000
        val h = total / 3600
        val m = (total % 3600) / 60
        val s = total % 60
        return buildString {
            if (negative) append('-')
            append("%02d:%02d:%02d".format(h, m, s))
        }
    }

    @Suppress("unused")
    private fun wifiManager(): WifiManager? =
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            getSystemService(WifiManager::class.java)
        } else {
            @Suppress("DEPRECATION")
            applicationContext.getSystemService(Context.WIFI_SERVICE) as? WifiManager
        }

    companion object {
        private const val TAG = "KioskActivity"

        /** Durasi video 10 detik; batasnya diberi ruang untuk TV lambat. */
        private const val INTRO_TIMEOUT_MS = 20_000L

        private const val HDMI_POLL_MS = 1_000L

        /** Setelah ini, layar menyarankan menekan SOURCE di remote. */
        private const val MANUAL_HINT_AFTER_MS = 12_000L
    }
}
