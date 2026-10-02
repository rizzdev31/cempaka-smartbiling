/// Stub untuk web: tidak ada akses ke antarmuka jaringan.
Future<String?> localIpv4() async => null;

/// Pemindaian subnet tidak tersedia di web.
bool get supportsSubnetScan => false;
