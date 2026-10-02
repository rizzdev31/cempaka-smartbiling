import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'core/config/api_config.dart';
import 'core/theme/app_theme.dart';
import 'core/time/server_time.dart';
import 'core/time/ticker.dart';
import 'data/fake/fake_billing_repository.dart';
import 'domain/repositories/billing_repository.dart';
import 'ui/dashboard/dashboard_controller.dart';
import 'ui/dashboard/dashboard_screen.dart';
import 'ui/widgets/connection_banner.dart';

class OperatorApp extends StatelessWidget {
  const OperatorApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        // Konfigurasi & waktu
        ChangeNotifierProvider.value(value: ApiConfig.instance),
        ChangeNotifierProvider.value(value: ServerTime.instance),

        // Ticker global — SATU untuk seluruh app (UI-UX-SPEC §4).
        ChangeNotifierProvider(create: (_) => AppTicker()),

        // Status koneksi realtime. Tahap 1 belum punya WebSocket, jadi
        // nilainya tetap `connected`; diisi klien Reverb di Tahap 0.
        ChangeNotifierProvider(create: (_) => ConnectionStatus()),

        // Sumber data.
        //
        // DEC-012: ini satu-satunya baris yang berubah saat Laravel siap —
        // `FakeBillingRepository()` -> `ApiBillingRepository(ApiConfig.instance)`.
        // Tidak ada UI yang perlu disentuh.
        Provider<BillingRepository>(create: (_) => FakeBillingRepository()),

        ChangeNotifierProvider(
          create: (ctx) => DashboardController(ctx.read<BillingRepository>()),
        ),
      ],
      child: MaterialApp(
        title: 'Cempaka Billing',
        debugShowCheckedModeBanner: false,

        // Light mode TIDAK dibuat — UI-UX-SPEC §1.
        theme: AppTheme.dark(),
        darkTheme: AppTheme.dark(),
        themeMode: ThemeMode.dark,

        // Batasi penskalaan teks agar layout 6 kartu tanpa scroll tidak
        // pecah, tapi tetap menghormati pengaturan sistem (a11y).
        builder: (context, child) {
          final scale = MediaQuery.textScalerOf(context)
              .clamp(minScaleFactor: 1.0, maxScaleFactor: 1.3);
          return MediaQuery(
            data: MediaQuery.of(context).copyWith(textScaler: scale),
            child: child!,
          );
        },

        home: const DashboardScreen(),
      ),
    );
  }
}
