/// Stub untuk web: tidak ada akses ke antarmuka jaringan.
Future<String?> localIpv4() async => null;

/// Pemindaian subnet tidak tersedia di web.
bool get supportsSubnetScan => false;

/// Browser tidak mengizinkan aplikasi membaca alamat IP lokalnya, jadi
/// subnet tidak bisa diturunkan dan pemindaian tidak tersedia. Operator
/// mengisi alamat server secara manual di layar Pengaturan.
Future<List<String>> localIpv4All() async => const [];
