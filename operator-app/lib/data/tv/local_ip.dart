/// Alamat IPv4 lokal perangkat.
///
/// Dipakai untuk menurunkan subnet saat memindai TV. `dart:io` tidak tersedia
/// di web, jadi implementasinya dipilih saat compile: versi `dart:io` untuk
/// Android, dan stub untuk web.
///
/// Konsekuensinya jujur: **pemindaian tidak tersedia di web.** Di Chrome
/// operator memasukkan alamat TV secara manual. Itu bukan pembatasan yang
/// disengaja — browser memang tidak mengizinkan aplikasi membaca IP lokalnya.
library;

export 'local_ip_stub.dart' if (dart.library.io) 'local_ip_io.dart';
