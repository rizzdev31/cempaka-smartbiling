import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'app.dart';
import 'core/config/api_config.dart';

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

  runApp(const OperatorApp());
}
