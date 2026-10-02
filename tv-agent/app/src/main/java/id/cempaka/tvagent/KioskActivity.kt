package id.cempaka.tvagent

import android.content.Context
import android.graphics.drawable.GradientDrawable
import android.net.wifi.WifiManager
import android.os.Build
import android.os.Bundle
import android.view.View
import android.view.WindowManager
import android.widget.LinearLayout
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.WindowInsetsControllerCompat
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
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

    private var capabilities: DeviceCapabilities? = null
    private var lockTaskActive = false

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        app = AgentApp.of(this)
        setContentView(R.layout.activity_kiosk)
        bindViews()

        capabilities = DeviceCapabilities.read(this)

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
                // Idle: tidak menampilkan deretan tanda hubung yang terlihat
                // seperti kerusakan. Station kosong adalah keadaan normal.
                timer.text = getString(R.string.idle_title)
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
}
