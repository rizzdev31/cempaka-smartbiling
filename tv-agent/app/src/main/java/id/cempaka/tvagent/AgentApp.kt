package id.cempaka.tvagent

import android.app.Application

/**
 * Pemilik objek yang harus hidup selama proses.
 *
 * [StateStore] sengaja satu instance: layar kiosk dan server HTTP harus
 * membaca state yang sama. Kalau masing-masing punya instance sendiri, TV bisa
 * menampilkan timer lama sementara server sudah menerima yang baru.
 */
class AgentApp : Application() {

    lateinit var store: StateStore
        private set

    lateinit var pairing: Pairing
        private set

    lateinit var applier: CommandApplier
        private set

    override fun onCreate() {
        super.onCreate()
        store = StateStore(this)
        pairing = Pairing(store)
        applier = CommandApplier(store)
    }

    companion object {
        fun of(context: android.content.Context): AgentApp =
            context.applicationContext as AgentApp
    }
}
