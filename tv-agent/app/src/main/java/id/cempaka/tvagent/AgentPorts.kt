package id.cempaka.tvagent

/**
 * Batas antara logika agen dan penyimpanan Android.
 *
 * ## Kenapa interface ini ada
 *
 * [Pairing] dan [CommandApplier] memegang aturan yang paling mahal kalau
 * salah: siapa boleh mengirim perintah, dan perintah mana yang diterapkan.
 * Keduanya semula bergantung langsung pada [StateStore], yang butuh
 * `Context` — artinya hanya bisa diuji dengan emulator atau Robolectric.
 *
 * Dengan dua interface kecil ini, aturannya bisa diuji sebagai unit JVM biasa,
 * cepat dan tanpa perangkat. [StateStore] mengimplementasikan keduanya.
 */
interface TokenHolder {

    /** Token device yang sedang berlaku. `null` = belum dipasangkan. */
    val currentToken: String?

    /** Simpan token baru dan tandai agen sudah dipasangkan ke [stationCode]. */
    fun onPaired(token: String, stationCode: String)
}

/** Akses state yang dibutuhkan [CommandApplier]. */
interface AgentStateHolder {

    val current: AgentState

    fun replace(next: AgentState)

    fun dropSession()
}

/**
 * Alamat server Laravel yang terakhir diketahui benar — DEC-041.
 *
 * Dipisah dari [StateStore] dengan alasan yang sama seperti dua interface di
 * atas: aturan "coba yang diingat dulu, baru memindai" adalah bagian yang
 * paling mudah salah, dan harus bisa diuji tanpa perangkat.
 */
interface ServerAddressStore {

    /** `http://192.168.100.11:8000`, atau `null` kalau belum pernah tahu. */
    val apiBaseUrl: String?

    fun rememberApiBaseUrl(baseUrl: String)
}
