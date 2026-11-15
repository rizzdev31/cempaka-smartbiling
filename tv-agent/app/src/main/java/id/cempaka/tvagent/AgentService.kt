package id.cempaka.tvagent

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.IBinder
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.lifecycle.LifecycleService
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.launch

/**
 * Foreground service yang menjaga agen tetap hidup.
 *
 * ## Kenapa foreground, bukan background biasa
 *
 * R02 (HIGH): "Kotlin Agent killed / memory pressure". TV murah punya RAM
 * terbatas dan Android akan membunuh proses background saat idle. Kalau agen
 * mati, operator tidak bisa mengirim perintah dan TV diam tanpa penjelasan.
 *
 * Foreground service membuat proses bertahan dan **terlihat** — ada notifikasi
 * yang bisa dicek saat troubleshooting di lokasi.
 *
 * ## Yang belum terbukti
 *
 * Perilaku ini wajib diuji di TV aktual. Beberapa TV murah tetap membunuh
 * foreground service saat standby panjang, dan beberapa mematikan WiFi saat
 * idle — itu N6/V9 di `TEST-PLAN-SABTU.md`.
 */
class AgentService : LifecycleService() {

    private var commandSource: CommandSource? = null

    /** Tahap terakhir yang terlihat, untuk mengenali sesi yang baru berakhir. */
    private var lastPhase: StartupPhase = StartupPhase.NONE

    override fun onCreate() {
        super.onCreate()
        createChannel()
        watchForScreenHandback()
    }

    /**
     * Rebut layar kembali saat sesi berakhir.
     *
     * ## Kenapa ini butuh perlakuan khusus
     *
     * Setelah layar diserahkan ke HDMI, agen ada di **background**. Sejak
     * Android 10 aplikasi background tidak boleh memulai Activity begitu
     * saja — `startActivity()` diam-diam tidak melakukan apa pun. Padahal
     * DEC-033 mewajibkan TV berhenti saat waktu habis, dan itu mustahil
     * kalau agen tidak bisa kembali ke layar.
     *
     * Jalan yang DIDOKUMENTASIKAN untuk kasus ini adalah notifikasi dengan
     * `setFullScreenIntent()`: sistem sendiri yang membuka Activity-nya.
     * Dicoba bersama `startActivity()` biasa, karena di perangkat dengan
     * Device Owner (Lock Task) cara biasa justru berhasil dan lebih cepat.
     *
     * **Belum terbukti di TV aktual** (PRD §5, R01). Kalau keduanya gagal,
     * yang terjadi bukan crash: sesi tetap berakhir di server dan di state
     * agen, hanya layar TV yang masih menampilkan konsol sampai ada yang
     * memindahnya. Itu dicatat di `GET /health` lewat laporan HDMI.
     */
    private fun watchForScreenHandback() {
        lifecycleScope.launch {
            AgentApp.of(this@AgentService).store.state.collect { state ->
                val before = lastPhase
                lastPhase = state.phase

                val sesiBaruSelesai =
                    before == StartupPhase.HANDED_OVER && state.phase == StartupPhase.NONE

                if (sesiBaruSelesai) reclaimScreen()
            }
        }
    }

    private fun reclaimScreen() {
        Log.i(TAG, "Sesi berakhir saat layar dipegang HDMI — mencoba kembali")

        val intent = Intent(this, KioskActivity::class.java)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP)

        runCatching { startActivity(intent) }
            .onFailure { Log.w(TAG, "startActivity ditolak", it) }

        val full = PendingIntent.getActivity(
            this,
            1,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

        val notif = NotificationCompat.Builder(this, CHANNEL_RECLAIM_ID)
            .setContentTitle(getString(R.string.reclaim_title))
            .setContentText(getString(R.string.reclaim_body))
            .setSmallIcon(R.drawable.ic_agent)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_ALARM)
            .setAutoCancel(true)
            .setFullScreenIntent(full, true)
            .build()

        runCatching {
            getSystemService(NotificationManager::class.java)
                ?.notify(NOTIFICATION_RECLAIM_ID, notif)
        }.onFailure { Log.w(TAG, "Notifikasi layar penuh gagal", it) }
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        super.onStartCommand(intent, flags, startId)

        startForegroundCompat()
        ensureCommandSource()

        // START_STICKY: kalau sistem tetap membunuh proses, Android
        // menjalankannya kembali. State sesi sudah ada di disk, jadi timer
        // lanjut dari end_at yang benar (PRD §16).
        return START_STICKY
    }

    override fun onBind(intent: Intent): IBinder? {
        super.onBind(intent)
        return null
    }

    override fun onDestroy() {
        commandSource?.stop()
        commandSource = null
        super.onDestroy()
    }

    private fun ensureCommandSource() {
        if (commandSource?.isActive == true) return

        val app = AgentApp.of(this)

        // Satu-satunya tempat sumber perintah dipilih.
        //
        // Tahap 2 (DEC-015): ganti baris ini dengan ReverbCommandSource.
        // Tidak ada file lain yang perlu diubah.
        val source = LocalHttpCommandSource(
            context = this,
            store = app.store,
            pairing = app.pairing,
            applier = app.applier,
        )

        try {
            source.start()
            commandSource = source
            Log.i(TAG, "Sumber perintah aktif: ${source.label}")
            refreshNotification()
        } catch (e: Exception) {
            // Port sudah dipakai, atau izin jaringan ditolak. Jangan crash —
            // layar kiosk tetap berguna menampilkan timer yang tersimpan.
            Log.e(TAG, "Gagal menjalankan sumber perintah", e)
        }
    }

    // ── Notifikasi ────────────────────────────────────────────────────

    private fun startForegroundCompat() {
        val notification = buildNotification()
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            startForeground(
                NOTIFICATION_ID,
                notification,
                ServiceInfo.FOREGROUND_SERVICE_TYPE_SPECIAL_USE.takeIf {
                    Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE
                } ?: 0,
            )
        } else {
            startForeground(NOTIFICATION_ID, notification)
        }
    }

    private fun refreshNotification() {
        val manager = getSystemService(NotificationManager::class.java)
        manager?.notify(NOTIFICATION_ID, buildNotification())
    }

    private fun buildNotification(): Notification {
        val state = AgentApp.of(this).store.state.value
        val source = commandSource

        val station = state.stationCode ?: "belum dipasangkan"
        val detail = buildString {
            append(station)
            source?.let { append(" · ").append(it.label) }
        }

        val open = PendingIntent.getActivity(
            this,
            0,
            Intent(this, KioskActivity::class.java),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle(getString(R.string.service_notification_title))
            .setContentText(detail)
            .setSmallIcon(R.drawable.ic_agent)
            .setOngoing(true)
            .setShowWhen(false)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .setContentIntent(open)
            .build()
    }

    private fun createChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val manager = getSystemService(NotificationManager::class.java) ?: return
        if (manager.getNotificationChannel(CHANNEL_ID) != null) return

        val channel = NotificationChannel(
            CHANNEL_ID,
            getString(R.string.service_channel_name),
            NotificationManager.IMPORTANCE_LOW,
        ).apply {
            description = getString(R.string.service_channel_desc)
            setShowBadge(false)
        }
        manager.createNotificationChannel(channel)

        /*
         * Channel terpisah dengan IMPORTANCE_HIGH.
         *
         * `setFullScreenIntent` hanya dihormati pada channel yang penting.
         * Dipisah dari channel status supaya notifikasi "agen aktif" yang
         * ada sepanjang hari tetap diam dan tidak pernah merebut layar.
         */
        if (manager.getNotificationChannel(CHANNEL_RECLAIM_ID) == null) {
            manager.createNotificationChannel(
                NotificationChannel(
                    CHANNEL_RECLAIM_ID,
                    getString(R.string.reclaim_channel_name),
                    NotificationManager.IMPORTANCE_HIGH,
                ).apply {
                    description = getString(R.string.reclaim_channel_desc)
                    setShowBadge(false)
                }
            )
        }
    }

    companion object {
        private const val TAG = "AgentService"
        private const val CHANNEL_ID = "agent_status"
        private const val NOTIFICATION_ID = 1001
        private const val CHANNEL_RECLAIM_ID = "agent_reclaim"
        private const val NOTIFICATION_RECLAIM_ID = 1002

        fun start(context: Context) {
            val intent = Intent(context, AgentService::class.java)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(intent)
            } else {
                context.startService(intent)
            }
        }

        fun stop(context: Context) {
            context.stopService(Intent(context, AgentService::class.java))
        }
    }
}
