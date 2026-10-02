import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'core/config/api_config.dart';
import 'core/theme/app_theme.dart';
import 'core/time/server_time.dart';
import 'core/time/ticker.dart';
import 'data/fake/fake_billing_repository.dart';
import 'data/tv/tv_agent_client.dart';
import 'data/tv/tv_discovery.dart';
import 'data/tv/tv_link_store.dart';
import 'data/tv/tv_sync_service.dart';
import 'domain/repositories/billing_repository.dart';
import 'ui/dashboard/dashboard_controller.dart';
import 'ui/shell/app_shell.dart';
import 'ui/widgets/connection_banner.dart';

class OperatorApp extends StatelessWidget {
  const OperatorApp({super.key, required this.tvLinkStore});

  /// Dibuka di `main()` sebelum UI dibangun: pasangan station-TV harus sudah
  /// terbaca sebelum dashboard mencoba menyinkronkan apa pun.
  final TvLinkStore tvLinkStore;

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

        // ── Kontrol TV (sementara — DEC-015) ─────────────────────────
        //
        // Saat Reverb masuk di Tahap 2, tiga provider ini hilang dan
        // perintah ke TV datang dari Laravel.
        Provider<TvAgentClient>(
          create: (_) => TvAgentClient(),
          dispose: (_, c) => c.close(),
        ),
        Provider<TvLinkStore>.value(value: tvLinkStore),
        Provider<TvDiscovery>(
          create: (ctx) => TvDiscovery(ctx.read<TvAgentClient>()),
        ),
        ChangeNotifierProvider(
          create: (ctx) => TvSyncService(
            client: ctx.read<TvAgentClient>(),
            store: ctx.read<TvLinkStore>(),
          ),
        ),

        ChangeNotifierProvider(
          create: (ctx) => DashboardController(
            ctx.read<BillingRepository>(),
            tvSync: ctx.read<TvSyncService>(),
          ),
        ),
      ],
      child: MaterialApp(
        title: 'Cempaka Billing',
        debugShowCheckedModeBanner: false,

        // Tema terang. Alasan versi sebelumnya memilih gelap tercatat di
        // UI-UX-SPEC §1 — tidak perlu digali ulang kalau ternyata layar
        // terang mengganggu di ruang rental.
        theme: AppTheme.light(),
        darkTheme: AppTheme.light(),
        themeMode: ThemeMode.light,

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

        home: const AppShell(),
      ),
    );
  }
}
