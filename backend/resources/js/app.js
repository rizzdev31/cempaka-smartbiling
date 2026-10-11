/**
 * Penyegaran otomatis halaman Admin Web — DEC-044.
 *
 * ## Kenapa polling, bukan WebSocket
 *
 * Reverb sudah ada dan menyiarkan sembilan event. Tapi dua kali dalam
 * seminggu terakhir realtime-nya mati diam-diam: sekali karena
 * `REVERB_HOST` salah, sekali karena `queue:work` tidak jalan. Keduanya
 * tidak menimbulkan error yang terlihat.
 *
 * Halaman admin yang bergantung pada Reverb akan ikut membeku dengan cara
 * yang sama — menampilkan angka lama sambil terlihat normal. Itu lebih
 * buruk daripada tidak realtime sama sekali, karena owner mengambil
 * keputusan dari angka yang dia kira baru.
 *
 * Polling tidak bisa gagal diam-diam: kalau servernya mati, permintaannya
 * gagal dan penanda waktunya berhenti maju — terlihat.
 *
 * Reverb tetap jalur yang benar untuk tablet dan TV, yang butuh reaksi
 * dalam hitungan detik. Dashboard admin tidak.
 *
 * ## Yang TIDAK boleh terjadi
 *
 * Menyegarkan halaman saat seseorang sedang mengetik akan menghapus
 * ketikannya. Karena itu penyegaran dilewati kalau fokus ada di dalam form,
 * atau kalau ada form yang sudah disentuh tapi belum dikirim.
 */

const TARGET = '#live';
const GAGAL_MAKSIMAL = 3;

let gagalBerturut = 0;
let adaPerubahanBelumDikirim = false;
let sedangMengambil = false;

/** Form yang sudah disentuh tidak boleh tertimpa penyegaran. */
document.addEventListener('input', (e) => {
    if (e.target.closest('form')) adaPerubahanBelumDikirim = true;
});
document.addEventListener('submit', () => {
    adaPerubahanBelumDikirim = false;
});

function bolehMenyegarkan() {
    // Tab di belakang: percuma, dan membebani server tanpa ada yang melihat.
    if (document.hidden) return false;

    if (adaPerubahanBelumDikirim) return false;

    const fokus = document.activeElement;
    if (fokus && fokus.closest(`${TARGET} form, ${TARGET} input, ${TARGET} select, ${TARGET} textarea`)) {
        return false;
    }

    /*
     * Panel aksi yang TERBUKA berarti ada yang sedang mengerjakan sesuatu —
     * walaupun belum satu huruf pun diketik.
     *
     * Tanpa ini: orang membuka "Mulai sesi baru", membaca pilihan paketnya
     * sepuluh detik, lalu panelnya tertutup sendiri dan isiannya hilang.
     * Ketahuan saat mencoba alurnya sungguhan di browser — bukan dari test,
     * karena test tidak menunggu sepuluh detik.
     */
    if (document.querySelector(`${TARGET} details[open]`)) return false;

    return true;
}

function tandaiWaktu(ok) {
    const tanda = document.querySelector('[data-live-status]');
    if (!tanda) return;

    const jam = new Intl.DateTimeFormat('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(new Date());

    tanda.textContent = ok ? `Diperbarui ${jam}` : 'Gagal menghubungi server';
    tanda.dataset.liveStatus = ok ? 'ok' : 'gagal';
}

async function segarkan() {
    if (sedangMengambil || !bolehMenyegarkan()) return;

    sedangMengambil = true;

    try {
        const res = await fetch(location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            // Tanpa ini, browser bisa menyajikan salinan cache dan penanda
            // waktunya maju padahal angkanya tidak.
            cache: 'no-store',
            credentials: 'same-origin',
        });

        // Session habis → server melempar ke /login. Memuat ulang penuh
        // supaya orangnya melihat form login, bukan halaman beku.
        if (res.redirected) {
            location.href = res.url;
            return;
        }

        if (!res.ok) throw new Error(res.status);

        const baru = new DOMParser()
            .parseFromString(await res.text(), 'text/html')
            .querySelector(TARGET);

        const lama = document.querySelector(TARGET);

        // Diperiksa LAGI: permintaannya butuh waktu, dan orangnya bisa mulai
        // mengetik di tengah jalan.
        if (baru && lama && bolehMenyegarkan()) {
            lama.replaceChildren(...baru.childNodes);
        }

        gagalBerturut = 0;
        tandaiWaktu(true);
    } catch {
        gagalBerturut++;
        tandaiWaktu(false);
    } finally {
        sedangMengambil = false;
    }
}

function mulai() {
    const wadah = document.querySelector(TARGET);
    const detik = Number(wadah?.dataset.poll ?? 0);

    if (!wadah || detik <= 0) return;

    setInterval(() => {
        /*
         * Berhenti setelah tiga kegagalan berturut-turut. Server yang mati
         * tidak perlu diketuk tiap sepuluh detik sampai besok pagi.
         * Dilanjutkan lagi saat tab dibuka kembali — biasanya memang itu
         * saat orangnya kembali bekerja.
         */
        if (gagalBerturut >= GAGAL_MAKSIMAL) return;

        segarkan();
    }, detik * 1000);

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) return;

        gagalBerturut = 0;
        segarkan();
    });
}

mulai();
