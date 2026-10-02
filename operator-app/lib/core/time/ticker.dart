import 'dart:async';

import 'package:flutter/widgets.dart';

/// Ticker global 1 detik — UI-UX-SPEC §4.
///
/// ATURAN YANG MENGIKAT: ini satu-satunya `Timer.periodic` di aplikasi.
///
/// Jangan membuat `Timer` per `StationCard`. Dengan 6 station itu berarti 6
/// timer paralel yang masing-masing memicu rebuild subtree — penyebab paling
/// umum operator app terasa berat.
///
/// Yang mendengarkan ticker ini hanya widget teks countdown, bukan card,
/// bukan grid, bukan layar.
///
/// Ticker berhenti otomatis saat app masuk background.
class AppTicker extends ChangeNotifier with WidgetsBindingObserver {
  AppTicker() {
    WidgetsBinding.instance.addObserver(this);
    _start();
  }

  Timer? _timer;
  bool _isForeground = true;

  /// Naik satu setiap detik. Dipakai sebagai pemicu rebuild.
  int get tick => _tick;
  int _tick = 0;

  bool get isRunning => _timer?.isActive ?? false;

  void _start() {
    if (_timer?.isActive ?? false) return;
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      _tick++;
      notifyListeners();
    });
  }

  void _stop() {
    _timer?.cancel();
    _timer = null;
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final foreground = state == AppLifecycleState.resumed;
    if (foreground == _isForeground) return;
    _isForeground = foreground;

    if (foreground) {
      _start();
      // Langsung notify supaya timer tidak terlihat "beku" sedetik
      // setelah app dibuka kembali.
      _tick++;
      notifyListeners();
    } else {
      _stop();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _stop();
    super.dispose();
  }
}
