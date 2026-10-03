#!/usr/bin/env bash
#
# Pembantu debugging TV lewat WiFi.
#
# Alasan skrip ini ada: `adb` tidak ada di PATH pada mesin Windows ini, dan
# selama pengujian langkah connect-install-log diulang puluhan kali. Mengetik
# path panjang berkali-kali adalah cara paling mudah membuat salah ketik yang
# terbaca seperti kegagalan jaringan.
#
# Pakai:
#   ./scripts/tv.sh connect 192.168.0.50   # sekali, IP-nya diingat
#   ./scripts/tv.sh install                # build + pasang
#   ./scripts/tv.sh log                    # log agen, mengalir
#   ./scripts/tv.sh health                 # cek server TV dari laptop
#
set -u

# ── Lokasi alat ──────────────────────────────────────────────────────

ADB="${ADB:-/c/Users/Rifqi/AppData/Local/Android/Sdk/platform-tools/adb.exe}"
JAVA_HOME="${JAVA_HOME:-/c/Program Files/Android/Android Studio/jbr}"
export JAVA_HOME

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APK="$ROOT/app/build/outputs/apk/debug/app-debug.apk"
PKG="id.cempaka.tvagent.debug"
ACTIVITY="$PKG/id.cempaka.tvagent.KioskActivity"
PORT=8787

# IP TV diingat di sini supaya tidak perlu diketik ulang tiap perintah.
IP_FILE="$ROOT/.tv-ip"

# Tag log agen. Sengaja disebutkan satu per satu: logcat Android TV sangat
# ramai, dan tanpa saringan log yang dicari tenggelam dalam hitungan detik.
TAGS="AgentService:V AgentHttp:V AgentBoot:V AgentDeviceAdmin:V AndroidRuntime:E"

red()  { printf '\033[31m%s\033[0m\n' "$*"; }
green(){ printf '\033[32m%s\033[0m\n' "$*"; }
dim()  { printf '\033[2m%s\033[0m\n' "$*"; }

# Apakah laptop berada di jaringan yang sama dengan TV?
#
# Ini kesalahan nomor satu, dan gejalanya menipu: semua perintah gagal dengan
# "tidak ada jawaban", persis seperti aplikasi yang tidak jalan. Padahal
# paketnya tidak pernah sampai.
#
# Caranya: minta OS memilih alamat lokal untuk menuju IP TV. Kalau yang
# dipilih bukan tetangga satu subnet, rute ke TV lewat gateway — dan di
# jaringan rumah/kantor itu berarti tidak akan sampai.
#
# Windows gemar berpindah sendiri ke WiFi yang punya internet, jadi ini bisa
# berubah di tengah sesi tanpa disentuh.
check_subnet() {
  local ip="$1"
  python - "$ip" <<'PY'
import socket, sys
tv = sys.argv[1]
s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
try:
    s.connect((tv, 9))
    local = s.getsockname()[0]
except Exception:
    sys.exit(0)        # tidak bisa dipastikan; jangan menghalangi
finally:
    s.close()

if local.rsplit('.', 1)[0] != tv.rsplit('.', 1)[0]:
    print('\033[31mPERINGATAN: laptop dan TV beda jaringan.\033[0m')
    print(f'  TV    : {tv}')
    print(f'  Laptop: {local}')
    print()
    print('  Selama ini berbeda, TIDAK ADA perintah di sini yang akan jalan.')
    print('  Sambungkan laptop ke WiFi yang sama dengan TV, lalu ulangi.')
    print()
PY
}

need_adb() {
  if [ ! -f "$ADB" ]; then
    red "adb tidak ditemukan di: $ADB"
    echo "Pasang Android SDK Platform-Tools, atau jalankan dengan:"
    echo "  ADB=/path/ke/adb.exe $0 $*"
    exit 1
  fi
}

# Pesan kesalahan WAJIB ke stderr di sini.
#
# Fungsi ini dipanggil lewat `$(tv_ip)`, dan `exit` di dalam substitusi hanya
# mematikan subshell-nya — bukan skripnya. Kalau pesannya ikut ke stdout, ia
# tertangkap sebagai nilai IP dan skrip lanjut dengan alamat berupa kalimat
# error. Pemanggil memeriksa status keluarnya.
tv_ip() {
  if [ ! -f "$IP_FILE" ]; then
    red "IP TV belum diset." >&2
    echo "Jalankan dulu:  $0 connect <IP-TV>" >&2
    return 1
  fi
  cat "$IP_FILE"
}

target() {
  local ip
  ip="$(tv_ip)" || return 1
  echo "$ip:5555"
}

# ── Perintah ─────────────────────────────────────────────────────────

cmd_connect() {
  local ip="${1:-}"
  if [ -z "$ip" ]; then
    red "IP TV wajib diisi."
    echo "Contoh:  $0 connect 192.168.0.50"
    echo "IP TV ada di: Settings > Network & Internet > (jaringan aktif)"
    exit 1
  fi

  need_adb
  echo "$ip" > "$IP_FILE"

  check_subnet "$ip"

  dim "Menyambung ke $ip:5555 ..."
  "$ADB" connect "$ip:5555"

  echo
  dim "Perangkat yang terlihat:"
  "$ADB" devices -l

  echo
  cat <<'EOF'
Kalau statusnya `unauthorized`:
  Lihat layar TV — ada dialog "Allow USB debugging?".
  Centang "Always allow from this computer", lalu OK.
  Setelah itu jalankan perintah connect ini sekali lagi.

Kalau `failed to connect`:
  - Network debugging di TV belum menyala
  - IP-nya salah, atau TV dan laptop beda subnet
  - AP/client isolation di router menyala (ini memblokir semuanya)
EOF
}

cmd_install() {
  need_adb
  local t; t="$(target)" || exit 1

  dim "Build APK debug ..."
  ( cd "$ROOT" && ./gradlew assembleDebug -q ) || { red "Build gagal."; exit 1; }

  dim "Memasang ke $t ..."
  if "$ADB" -s "$t" install -r "$APK"; then
    green "Terpasang. Buka 'Cempaka TV' di laci aplikasi TV."
  else
    echo
    cat <<EOF
Gagal memasang. Arti pesan yang sering muncul:

  device unauthorized
      Dialog izin di TV belum disetujui.

  INSTALL_FAILED_UPDATE_INCOMPATIBLE
      Versi lama tertanda kunci berbeda. Copot dulu:
        $0 uninstall

  INSTALL_FAILED_INSUFFICIENT_STORAGE
      Penyimpanan TV penuh.
EOF
    exit 1
  fi
}

cmd_log() {
  need_adb
  local t; t="$(target)" || exit 1
  dim "Log agen dari $t. Ctrl+C untuk berhenti."
  dim "Tag: AgentService, AgentHttp, AgentBoot, AgentDeviceAdmin, + crash"
  echo
  # -s menyaring: hanya tag di atas yang lewat, sisanya dibuang.
  "$ADB" -s "$t" logcat -v time -s $TAGS
}

cmd_logclear() {
  need_adb
  t="$(target)" || exit 1
  "$ADB" -s "$t" logcat -c && green "Buffer log dikosongkan."
}

cmd_health() {
  local ip; ip="$(tv_ip)" || exit 1
  check_subnet "$ip"
  dim "GET http://$ip:$PORT/health"
  echo
  # Dipanggil dari laptop, bukan lewat adb: ini menguji jalur yang sama
  # dengan yang dipakai tablet operator — termasuk kalau router memblokirnya.
  if curl -s -m 5 "http://$ip:$PORT/health"; then
    echo
    green "Server TV menjawab. Jalur operator -> TV terbuka."
  else
    echo
    red "Tidak ada jawaban dari $ip:$PORT"
    cat <<'EOF'

Yang perlu dicek, berurutan:
  1. Laptop dan TV di WiFi yang sama? (lihat peringatan di atas kalau ada)
  2. Aplikasi sudah DIBUKA di TV? Server baru hidup setelah layar kiosk
     muncul -- terpasang saja tidak cukup.
  3. AP/client isolation di router mati?
EOF
    exit 1
  fi
}

cmd_restart() {
  need_adb
  local t; t="$(target)" || exit 1
  "$ADB" -s "$t" shell am force-stop "$PKG"
  "$ADB" -s "$t" shell am start -n "$ACTIVITY" >/dev/null
  green "Aplikasi dijalankan ulang di TV."
}

cmd_uninstall() {
  need_adb
  t="$(target)" || exit 1
  "$ADB" -s "$t" uninstall "$PKG" &&
    green "Dicopot. Pairing ikut hilang — TV perlu dipasangkan ulang."
}

cmd_ip() {
  need_adb
  local t; t="$(target)" || exit 1
  dim "Alamat jaringan TV menurut TV sendiri:"
  "$ADB" -s "$t" shell ip -f inet addr show 2>/dev/null |
    grep -oE 'inet [0-9.]+' | grep -v '127.0.0.1'
}

cmd_disconnect() {
  need_adb
  t="$(target)" || exit 1
  "$ADB" disconnect "$t" && green "Terputus."
}

usage() {
  cat <<EOF
Pembantu debugging TV lewat WiFi.

  $0 connect <IP-TV>   sambungkan, IP diingat untuk perintah berikutnya
  $0 install           build APK debug lalu pasang ke TV
  $0 log               log agen, mengalir (Ctrl+C berhenti)
  $0 logclear          kosongkan buffer log sebelum mengulang percobaan
  $0 health            panggil /health dari laptop — menguji jalur operator
  $0 restart           jalankan ulang aplikasi di TV
  $0 ip                tampilkan alamat jaringan TV menurut TV sendiri
  $0 uninstall         copot APK (pairing ikut hilang)
  $0 disconnect        putuskan adb

IP TV tersimpan di: $IP_FILE
EOF
}

case "${1:-}" in
  connect)    shift; cmd_connect "${1:-}" ;;
  install)    cmd_install ;;
  log)        cmd_log ;;
  logclear)   cmd_logclear ;;
  health)     cmd_health ;;
  restart)    cmd_restart ;;
  ip)         cmd_ip ;;
  uninstall)  cmd_uninstall ;;
  disconnect) cmd_disconnect ;;
  *)          usage ;;
esac
