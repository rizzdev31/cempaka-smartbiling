package id.cempaka.tvagent

import java.security.SecureRandom

/**
 * Pairing operator ↔ TV.
 *
 * ## Kenapa ini tidak bisa ditunda
 *
 * Kontrol langsung tanpa autentikasi berarti **siapa pun di WiFi yang sama
 * bisa menyetel timer TV**. Customer yang terhubung ke Guest Wi-Fi bisa
 * memperpanjang sesinya sendiri secara gratis. Itu kelas risiko yang sama
 * dengan *prank order* di PRD §13, dan R05 (HIGH).
 *
 * ## Cara kerja
 *
 * 1. TV yang belum dipasangkan menampilkan **kode 6 digit** di layar.
 * 2. Operator memasukkan kode itu di aplikasi operator.
 * 3. TV memberi **device token** acak; operator menyimpannya.
 * 4. Setiap perintah berikutnya membawa token di header `X-Agent-Token`.
 *
 * Polanya sama dengan `enrollment_code` di kontrak §9, jadi tidak terbuang
 * saat Tahap 2 — yang berubah hanya siapa yang menerbitkan token.
 *
 * ## Batasnya, dicatat jujur
 *
 * Kode pairing hanya melindungi dari penyalahgunaan **tidak sengaja** dan dari
 * orang yang tidak melihat layar TV. Siapa pun yang bisa melihat layar bisa
 * membaca kodenya. Itu memadai untuk spike di jaringan operasional, **bukan**
 * untuk TV yang terjangkau dari Guest Wi-Fi — PRD §9 mewajibkan guest
 * dipisahkan, dan itu tetap wajib.
 */
class Pairing(private val store: TokenHolder) {

    private val random = SecureRandom()

    /**
     * Kode yang sedang ditampilkan. Dibuat ulang setiap agen start supaya
     * kode lama tidak berlaku selamanya.
     */
    @Volatile
    var code: String = generateCode()
        private set

    /** Berapa kali kode salah dicoba sejak kode dibuat. */
    @Volatile
    private var failedAttempts: Int = 0

    val isLocked: Boolean get() = failedAttempts >= MAX_ATTEMPTS

    fun regenerate() {
        code = generateCode()
        failedAttempts = 0
    }

    /**
     * Coba pasangkan.
     *
     * @return token device kalau berhasil, `null` kalau kode salah atau
     *   percobaan sudah melebihi batas.
     */
    fun tryPair(submittedCode: String?, stationCode: String?): String? {
        if (isLocked) return null

        val expected = code
        val given = submittedCode?.trim()?.uppercase().orEmpty()

        // Perbandingan waktu-konstan: panjangnya pendek, tapi kebiasaan yang
        // benar lebih murah daripada mengingat pengecualiannya nanti.
        if (given.length != expected.length || !constantTimeEquals(given, expected)) {
            failedAttempts++
            return null
        }

        val token = generateToken()
        store.onPaired(
            token,
            stationCode?.trim()?.uppercase().orEmpty().ifEmpty { "?" },
        )

        // Kode sekali pakai: setelah berhasil, yang lama tidak berlaku lagi.
        regenerate()
        return token
    }

    /** Apakah token yang dibawa perintah sah. */
    fun isTokenValid(token: String?): Boolean {
        val expected = store.currentToken ?: return false
        val given = token.orEmpty()
        return given.length == expected.length && constantTimeEquals(given, expected)
    }

    private fun generateCode(): String {
        // Angka saja: diketik dengan remote TV atau keypad tablet, dan huruf
        // yang mirip (O/0, I/1) sering salah baca dari jarak beberapa meter.
        val sb = StringBuilder(CODE_LENGTH)
        repeat(CODE_LENGTH) { sb.append(random.nextInt(10)) }
        return sb.toString()
    }

    private fun generateToken(): String {
        val bytes = ByteArray(24)
        random.nextBytes(bytes)
        return bytes.joinToString("") { "%02x".format(it) }
    }

    private fun constantTimeEquals(a: String, b: String): Boolean {
        if (a.length != b.length) return false
        var diff = 0
        for (i in a.indices) diff = diff or (a[i].code xor b[i].code)
        return diff == 0
    }

    private companion object {
        const val CODE_LENGTH = 6
        const val MAX_ATTEMPTS = 10
    }
}
