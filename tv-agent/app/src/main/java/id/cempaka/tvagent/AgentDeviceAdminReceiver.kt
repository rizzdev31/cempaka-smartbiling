package id.cempaka.tvagent

import android.app.admin.DeviceAdminReceiver
import android.content.Context
import android.content.Intent
import android.util.Log

/**
 * Receiver yang dibutuhkan agar paket ini bisa dijadikan **Device Owner**.
 *
 * Tanpa kelas ini, perintah berikut gagal:
 * ```
 * adb shell dpm set-device-owner id.cempaka.tvagent/.AgentDeviceAdminReceiver
 * ```
 *
 * Device Owner adalah syarat Lock Task mode — satu-satunya cara kiosk
 * benar-benar tidak bisa ditinggalkan (tingkat 2 di DEC-015).
 *
 * **Hanya berhasil pada perangkat tanpa akun.** TV yang sudah dipakai dengan
 * akun Google perlu factory reset lebih dulu. Itu OD-005 / V10, dan alasan
 * aplikasi ini tidak pernah mengklaim kiosk penuh sebelum terbukti.
 *
 * Kelas ini sengaja tidak menerapkan policy apa pun. Keberadaannya hanya
 * membuka kemungkinan Lock Task; kebijakan lain (blokir pengaturan, batasi
 * aplikasi) butuh keputusan bisnis yang belum ada.
 */
class AgentDeviceAdminReceiver : DeviceAdminReceiver() {

    override fun onEnabled(context: Context, intent: Intent) {
        super.onEnabled(context, intent)
        Log.i(TAG, "Device admin diaktifkan")
    }

    override fun onDisabled(context: Context, intent: Intent) {
        super.onDisabled(context, intent)
        Log.w(TAG, "Device admin dinonaktifkan — Lock Task tidak lagi tersedia")
    }

    private companion object {
        const val TAG = "AgentDeviceAdmin"
    }
}
