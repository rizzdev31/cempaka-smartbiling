import 'dart:io';

/// IPv4 lokal perangkat.
///
/// Diambil dari [NetworkInterface], bukan dari paket WiFi: tablet bisa
/// terhubung lewat Ethernet/USB tethering, dan sumber khusus WiFi akan
/// mengembalikan kosong dalam kasus itu.
///
/// Alamat link-local (169.254.x.x) diabaikan — itu tanda DHCP gagal, dan
/// memindai subnet-nya tidak akan menemukan apa pun.
Future<String?> localIpv4() async {
  try {
    final interfaces = await NetworkInterface.list(
      type: InternetAddressType.IPv4,
      includeLoopback: false,
      includeLinkLocal: false,
    );

    for (final iface in interfaces) {
      for (final addr in iface.addresses) {
        final ip = addr.address;
        if (ip.startsWith('169.254.')) continue;
        if (addr.isLoopback) continue;
        return ip;
      }
    }
  } catch (_) {
    // Izin jaringan ditolak atau platform tidak mendukung — pemanggil
    // akan jatuh ke entri manual.
  }
  return null;
}

bool get supportsSubnetScan => true;

/// SEMUA alamat IPv4 perangkat, bukan hanya yang pertama.
///
/// Dipakai penemuan server untuk menurunkan prefix subnet. Satu alamat saja
/// tidak cukup: TV dan tablet bisa punya WiFi dan Ethernet sekaligus, dan
/// server bisa berada di subnet mana pun dari keduanya.
Future<List<String>> localIpv4All() async {
  try {
    final interfaces = await NetworkInterface.list(
      type: InternetAddressType.IPv4,
      includeLoopback: false,
      includeLinkLocal: false,
    );

    return [
      for (final i in interfaces)
        for (final a in i.addresses) a.address,
    ];
  } catch (_) {
    return const [];
  }
}
