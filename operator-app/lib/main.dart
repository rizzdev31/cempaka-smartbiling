import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'app.dart';
import 'core/config/api_config.dart';
import 'data/tv/tv_link_store.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Tablet operator dipakai mendatar di meja kasir.
  await SystemChrome.setPreferredOrientations([
    DeviceOrientation.landscapeLeft,
    DeviceOrientation.landscapeRight,
    DeviceOrientation.portraitUp,
  ]);

  // Baca override alamat server dari penyimpanan lokal sebelum UI dibangun,
  // supaya tidak sempat memanggil alamat yang salah.
  await ApiConfig.instance.load();

  // Pasangan station-TV dibaca lebih dulu supaya dashboard bisa langsung
  // menyinkronkan keadaan ke TV pada pemuatan pertama.
  final tvLinkStore = await TvLinkStore.open();

  runApp(OperatorApp(tvLinkStore: tvLinkStore));
}
