package id.cempaka.tvagent

/**
 * Serialisasi & parsing JSON minimal.
 *
 * Kebutuhannya kecil: lima endpoint dengan body datar berisi string, angka,
 * dan boolean. Menambah kotlinx.serialization atau Moshi untuk itu berarti
 * dependensi plus plugin plus aturan ProGuard — tidak sepadan.
 *
 * `org.json` sudah ada di Android, jadi parsing dipakai dari sana. Yang
 * ditulis di sini hanya pembungkus supaya pemanggilnya tidak berurusan dengan
 * exception dan null.
 */
object Json {

    /** Ubah map jadi JSON. Nilai null dihilangkan, bukan ditulis `null`. */
    fun encode(map: Map<String, Any?>): String {
        val obj = org.json.JSONObject()
        for ((k, v) in map) {
            when (v) {
                null -> {}
                is Map<*, *> -> obj.put(
                    k,
                    org.json.JSONObject(encode(v.toStringKeyedMap()))
                )
                is List<*> -> obj.put(k, org.json.JSONArray(v))
                else -> obj.put(k, v)
            }
        }
        return obj.toString()
    }

    fun decode(raw: String?): JsonBody {
        if (raw.isNullOrBlank()) return JsonBody(null)
        val obj = runCatching { org.json.JSONObject(raw) }.getOrNull()
        return JsonBody(obj)
    }

    private fun Map<*, *>.toStringKeyedMap(): Map<String, Any?> =
        entries.associate { (k, v) -> k.toString() to v }
}

/** Pembacaan body yang tidak melempar exception. */
class JsonBody(private val obj: org.json.JSONObject?) {

    val isPresent: Boolean get() = obj != null

    fun string(key: String): String? {
        val o = obj ?: return null
        if (!o.has(key) || o.isNull(key)) return null
        return o.optString(key).takeIf { it.isNotBlank() }
    }

    fun long(key: String): Long? {
        val o = obj ?: return null
        if (!o.has(key) || o.isNull(key)) return null
        // Angka besar (epoch millis) bisa datang sebagai Number atau String.
        return when (val v = o.opt(key)) {
            is Number -> v.toLong()
            is String -> v.toLongOrNull()
            else -> null
        }
    }

    fun bool(key: String, default: Boolean = false): Boolean {
        val o = obj ?: return default
        if (!o.has(key) || o.isNull(key)) return default
        return o.optBoolean(key, default)
    }
}
