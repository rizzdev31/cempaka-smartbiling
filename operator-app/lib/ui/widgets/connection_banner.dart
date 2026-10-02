import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/config/api_config.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../core/time/server_time.dart';

/// Status koneksi realtime.
///
/// `connecting` dan `reconnecting` dibedakan: yang pertama normal saat app
/// baru dibuka, yang kedua berarti data di layar mungkin sudah basi.
enum ConnectionState { connected, connecting, reconnecting, offline }

/// Sumber status koneksi.
///
/// Tahap 1 (fake repository) belum punya WebSocket, jadi nilainya di-set
/// manual. Diganti oleh klien Reverb saat Tahap 0 tersambung —
/// widget yang memakainya tidak perlu diubah.
class ConnectionStatus extends ChangeNotifier {
  ConnectionState _state = ConnectionState.connected;
  ConnectionState get state => _state;

  void set(ConnectionState value) {
    if (_state == value) return;
    _state = value;
    notifyListeners();
  }
}

/// Indikator di header — REALTIME.md §8 mewajibkan ini.
///
/// Operator HARUS tahu kalau data yang dilihatnya mungkin sudah basi.
/// Tanpa indikator, operator akan menagih berdasarkan angka lama.
class ConnectionBanner extends StatelessWidget {
  const ConnectionBanner({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<ConnectionStatus>(
      builder: (context, status, _) {
        final (color, icon, label) = switch (status.state) {
          ConnectionState.connected => (
              AppColors.statusAvailable,
              Icons.cloud_done_outlined,
              'Tersambung',
            ),
          ConnectionState.connecting => (
              AppColors.onSurfaceVariant,
              Icons.cloud_sync_outlined,
              'Menyambung…',
            ),
          ConnectionState.reconnecting => (
              AppColors.statusWarning,
              Icons.cloud_sync_outlined,
              'Menyambung ulang…',
            ),
          ConnectionState.offline => (
              AppColors.statusExpired,
              Icons.cloud_off_outlined,
              'Terputus',
            ),
        };

        return Semantics(
          label: 'Koneksi server: $label',
          excludeSemantics: true,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 16, color: color),
              const SizedBox(width: AppSpacing.sm - 2),
              Text(
                label,
                style: AppTypography.bodySm.copyWith(color: color),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// Baris diagnostik: IP server + status sinkronisasi jam.
///
/// HANYA tampil di build dev. Di lokasi, ini yang menjawab pertanyaan
/// "kok nggak konek" tanpa perlu membuka laptop.
class DevDiagnosticBar extends StatelessWidget {
  const DevDiagnosticBar({super.key});

  @override
  Widget build(BuildContext context) {
    final cfg = context.watch<ApiConfig>();
    if (!cfg.isDev) return const SizedBox.shrink();

    final offset = ServerTime.instance.offset;
    final synced = ServerTime.instance.isSynced;
    final offsetText = !synced
        ? 'jam: belum sinkron'
        : offset.abs() < const Duration(seconds: 2)
            ? 'jam: sinkron'
            : 'jam: beda ${offset.inSeconds}s';

    return Container(
      width: double.infinity,
      color: AppColors.surfaceLow,
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.xs + 2,
      ),
      child: Row(
        children: [
          const Icon(Icons.developer_mode,
              size: 14, color: AppColors.onSurfaceVariant),
          const SizedBox(width: AppSpacing.sm - 2),
          Expanded(
            child: Text(
              '${cfg.baseUrl}  ·  ws ${cfg.wsHost}:${cfg.wsPort}  ·  $offsetText',
              style: AppTypography.bodySm.copyWith(
                color: synced ? AppColors.onSurfaceVariant : AppColors.statusWarning,
                fontSize: 12,
              ),
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}
