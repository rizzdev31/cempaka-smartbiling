import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'core/brand.dart';
import 'core/config/api_config.dart';
import 'core/theme/app_theme.dart';
import 'core/theme/tokens.dart';
import 'core/time/server_time.dart';
import 'core/time/ticker.dart';
import 'data/api/api_billing_repository.dart';
import 'data/api/api_client.dart';
import 'data/api/auth_repository.dart';
import 'data/tv/tv_agent_client.dart';
import 'data/tv/tv_discovery.dart';
import 'data/tv/tv_link_store.dart';
import 'data/tv/tv_sync_service.dart';
import 'domain/repositories/billing_repository.dart';
import 'ui/auth/auth_controller.dart';
import 'ui/auth/login_screen.dart';
import 'ui/dashboard/dashboard_controller.dart';
import 'ui/shell/app_shell.dart';
import 'ui/widgets/connection_banner.dart';

class OperatorApp extends StatelessWidget {
  const OperatorApp({
    super.key,
    required this.tvLinkStore,
    required this.apiClient,
    this.tvClient,
    this.billingRepository,
  });

  /// Dibuka di `main()` sebelum UI dibangun: pasangan station-TV harus sudah
  /// terbaca sebelum dashboard mencoba menyinkronkan apa pun.
  final TvLinkStore tvLinkStore;

  /// Klien TV pengganti — **hanya** untuk test.
  ///
  /// Tanpa seam ini, widget test membuat `http.Client` sungguhan. Tidak ada
  /// permintaan yang benar-benar keluar di test, tapi klien yang hidup menahan
  /// isolate sehingga suite butuh menit untuk keluar. `main()` tidak
  /// mengisinya, jadi perilaku produksi tidak berubah.
  final TvAgentClient? tvClient;

  /// Klien HTTP ke Laravel.
  ///
  /// Dibuat di `main()`, **bukan di sini**, karena dua alasan yang sama-sama
  /// mengikat: tokennya harus sudah dibaca dari disk sebelum UI dibangun, dan
  /// seluruh aplikasi wajib memakai **satu** instance. Dua instance berarti
  /// token yang disimpan saat login tidak terlihat oleh repository billing,
  /// dan setiap permintaan ditolak 401 walau login tampak berhasil.
  final ApiClient apiClient;

  /// Pengganti sumber data — **hanya** untuk test.
  ///
  /// Produksi memakai [ApiBillingRepository]. Test layout dan alur UI tidak
  /// perlu server hidup, dan memaksanya ke sana membuat test lambat sekaligus
  /// menguji hal yang bukan sasarannya.
  final BillingRepository? billingRepository;

  ApiClient get _api => apiClient;

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

        // ── Sumber data: Laravel sungguhan ───────────────────────────
        //
        // DEC-012 terpenuhi: satu baris, dan tidak ada UI yang disentuh.
        // `FakeBillingRepository` masih ada di pohon kode tapi **tidak lagi
        // dipakai aplikasi** — ia hanya dipakai test, dan aturan billing yang
        // ditirunya sudah dicabut DEC-033/034.
        Provider<ApiClient>.value(value: _api),
        Provider<AuthRepository>(create: (_) => AuthRepository(_api)),
        Provider<BillingRepository>(
          create: (_) => billingRepository ?? ApiBillingRepository(_api),
        ),

        // Siapa yang masuk. Dibuat lebih dulu daripada controller lain karena
        // merekalah yang akan memanggil endpoint yang butuh tokennya.
        ChangeNotifierProvider(
          create: (ctx) =>
              AuthController(ctx.read<AuthRepository>())..bootstrap(),
        ),

        // ── Kontrol TV (sementara — DEC-015) ─────────────────────────
        //
        // Saat Reverb masuk di Tahap 2, tiga provider ini hilang dan
        // perintah ke TV datang dari Laravel.
        Provider<TvAgentClient>(
          create: (_) => tvClient ?? TvAgentClient(),
          // Yang disuntikkan dari luar ditutup oleh pemiliknya, bukan di sini.
          dispose: (_, c) => tvClient == null ? c.close() : null,
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
        title: Brand.appName,
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

        home: const _AuthGate(),
      ),
    );
  }
}

/// Memilih antara layar masuk dan isi aplikasi.
///
/// Satu-satunya tempat keputusan itu diambil. Kalau tiap layar memutuskannya
/// sendiri, akan ada keadaan setengah masuk — sebagian panel menampilkan data,
/// sebagian menampilkan 401.
class _AuthGate extends StatelessWidget {
  const _AuthGate();

  @override
  Widget build(BuildContext context) {
    final status = context.watch<AuthController>().status;

    return switch (status) {
      // Token tersimpan sedang diperiksa. Sengaja polos: ini hanya terlihat
      // sepersekian detik, dan logo yang berkedip lebih mengganggu daripada
      // layar kosong.
      AuthStatus.checking => const Scaffold(
          backgroundColor: AppColors.surface,
          body: Center(child: CircularProgressIndicator()),
        ),
      AuthStatus.signedOut => const LoginScreen(),
      AuthStatus.signedIn => const AppShell(),
    };
  }
}
