package id.cempaka.tvagent

import android.app.admin.DevicePolicyManager
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import android.os.Process

/**
 * Tingkat kiosk yang benar-benar aktif.
 *
 * PRD §5 melarang mengklaim dukungan kiosk sebelum terbukti di TV aktual.
 * Kelas ini yang membuat klaim itu **tidak perlu ditebak** — nilainya dibaca
 * dari sistem dan dilaporkan apa adanya lewat `GET /health`.
 */
enum class KioskTier {
    /**
     * Fullscreen immersive + foreground service + auto-start saat boot.
     * Selalu tersedia. **Customer masih bisa keluar lewat tombol HOME.**
     */
    SOFT,

    /**
     * Lock Task mode aktif — benar-benar tidak bisa keluar.
     * Butuh Device Owner.
     */
    LOCK_TASK,
}

/**
 * Laporan kemampuan perangkat.
 *
 * Ini bahan jawaban **OD-005**: model, versi Android, apakah Device Owner
 * bisa dipakai, dan tingkat kiosk mana yang aktif. Dibaca operator lewat
 * `GET /health`, jadi tidak perlu dicatat manual di lokasi.
 */
data class DeviceCapabilities(
    val manufacturer: String,
    val model: String,
    val androidRelease: String,
    val sdkInt: Int,
    val isTelevision: Boolean,
    val hasLeanback: Boolean,
    val isDeviceOwner: Boolean,
    val isLockTaskPermitted: Boolean,
    val kioskTier: KioskTier,
    val appVersion: String,
) {
    companion object {

        fun read(context: Context): DeviceCapabilities {
            val pm = context.packageManager
            val dpm = context.getSystemService(Context.DEVICE_POLICY_SERVICE)
                    as? DevicePolicyManager

            val isOwner = runCatching {
                dpm?.isDeviceOwnerApp(context.packageName) == true
            }.getOrDefault(false)

            // Lock Task hanya sah kalau paket ini diizinkan — dan itu hanya
            // mungkin bila ada Device Owner (paket ini sendiri atau lain).
            val lockTaskOk = runCatching {
                dpm?.isLockTaskPermitted(context.packageName) == true
            }.getOrDefault(false)

            val uiMode = context.getSystemService(Context.UI_MODE_SERVICE)
                    as? android.app.UiModeManager
            val isTv = uiMode?.currentModeType ==
                    android.content.res.Configuration.UI_MODE_TYPE_TELEVISION

            val version = runCatching {
                @Suppress("DEPRECATION")
                pm.getPackageInfo(context.packageName, 0).versionName
            }.getOrNull() ?: BuildConfig.VERSION_NAME

            return DeviceCapabilities(
                manufacturer = Build.MANUFACTURER ?: "?",
                model = Build.MODEL ?: "?",
                androidRelease = Build.VERSION.RELEASE ?: "?",
                sdkInt = Build.VERSION.SDK_INT,
                isTelevision = isTv,
                hasLeanback = pm.hasSystemFeature("android.software.leanback"),
                isDeviceOwner = isOwner,
                isLockTaskPermitted = lockTaskOk,
                kioskTier = if (lockTaskOk) KioskTier.LOCK_TASK else KioskTier.SOFT,
                appVersion = version,
            )
        }

        /**
         * Perintah ADB untuk menjadikan paket ini Device Owner.
         *
         * Ditampilkan di layar pairing supaya tidak perlu dicari di catatan
         * saat berada di depan TV. Hanya berhasil pada perangkat **tanpa akun**
         * (OD-005 / V10) — kalau TV sudah dipakai dengan akun Google, perlu
         * factory reset lebih dulu.
         */
        fun deviceOwnerAdbCommand(packageName: String): String =
            "adb shell dpm set-device-owner " +
                    "$packageName/.AgentDeviceAdminReceiver"
    }

    /** Ringkas untuk notifikasi & log. */
    fun shortLabel(): String = "$manufacturer $model · Android $androidRelease"

    fun toJsonMap(): Map<String, Any?> = mapOf(
        "manufacturer" to manufacturer,
        "model" to model,
        "android_release" to androidRelease,
        "sdk_int" to sdkInt,
        "is_television" to isTelevision,
        "has_leanback" to hasLeanback,
        "is_device_owner" to isDeviceOwner,
        "is_lock_task_permitted" to isLockTaskPermitted,
        "kiosk_tier" to kioskTier.name,
        "app_version" to appVersion,
        "process_uid" to Process.myUid(),
    )
}

/** Dipakai untuk mendeteksi fitur yang belum tentu ada di tiap TV. */
fun PackageManager.hasFeatureSafe(name: String): Boolean =
    runCatching { hasSystemFeature(name) }.getOrDefault(false)
