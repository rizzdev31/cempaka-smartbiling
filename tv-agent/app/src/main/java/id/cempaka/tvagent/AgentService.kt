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

    override fun onCreate() {
        super.onCreate()
        createChannel()
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
    }

    companion object {
        private const val TAG = "AgentService"
        private const val CHANNEL_ID = "agent_status"
        private const val NOTIFICATION_ID = 1001

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
