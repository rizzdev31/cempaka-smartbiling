package id.cempaka.tvagent

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log

/**
 * Auto-start setelah TV dinyalakan — PRD §16 dan T12.
 *
 * Yang dijalankan adalah [AgentService], bukan [KioskActivity]: membuka
 * Activity dari boot diblokir pada Android 10+ (batasan background activity
 * start). Service cukup — begitu operator mengirim perintah, state tersimpan,
 * dan layar menyusul saat Activity dibuka.
 *
 * Kalau TV perlu langsung menampilkan kiosk setelah boot, itu butuh app
 * dijadikan launcher atau Device Owner — lihat OD-005.
 */
class BootReceiver : BroadcastReceiver() {

    override fun onReceive(context: Context, intent: Intent?) {
        val action = intent?.action ?: return
        if (action != Intent.ACTION_BOOT_COMPLETED &&
            action != "android.intent.action.QUICKBOOT_POWERON"
        ) {
            return
        }

        // Tidak ada gunanya menjalankan agen kalau belum pernah dipasangkan.
        val store = AgentApp.of(context).store
        if (store.deviceToken == null) {
            Log.i(TAG, "Boot: belum dipasangkan, agen tidak dijalankan")
            return
        }

        Log.i(TAG, "Boot: menjalankan agen")
        AgentService.start(context)
    }

    private companion object {
        const val TAG = "AgentBoot"
    }
}
