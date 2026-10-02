import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../core/time/server_time.dart';
import '../../core/time/ticker.dart';
import '../../core/util/format.dart';
import '../../data/tv/tv_sync_service.dart';
import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import '../../domain/models/tv_agent.dart';
import '../dashboard/dashboard_controller.dart';
import '../widgets/confirm_dialog.dart';
import 'tv_pair_sheet.dart';

/// Status TV — PRD §18: "Online/offline/last seen".
///
/// Sejak DEC-015 layar ini bukan hanya pemantauan: di sini operator
/// **memasangkan** TV ke station dan memastikan perintah billing sampai.
///
/// Satu baris per station, bukan per perangkat. Alasannya: pertanyaan operator
/// selalu "TV di ST03 kenapa?", bukan "perangkat dengan uid abc123 kenapa?".
class DeviceScreen extends StatefulWidget {
  const DeviceScreen({super.key, this.embedded = false});

  /// `true` saat dipasang di dalam `AppShell` — shell sudah punya header.
  final bool embedded;

  @override
  State<DeviceScreen> createState() => _DeviceScreenState();
}

class _DeviceScreenState extends State<DeviceScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _refreshAll());
  }

  Future<void> _refreshAll() async {
    final dash = context.read<DashboardController>();
    final sync = context.read<TvSyncService>();

    if (!dash.hasData) await dash.load();
    if (!mounted) return;

    for (final station in dash.stations) {
      if (!sync.isLinked(station.id)) continue;
      await sync.refreshStatus(station.id);
      if (!mounted) return;
    }
  }

  Future<void> _pair(Station station) async {
    final ok = await showTvPairSheet(context, station: station);
    if (ok && mounted) await context.read<DashboardController>().refresh();
  }

  Future<void> _unpair(Station station) async {
    final ok = await showConfirmDialog(
      context,
      title: 'Lepas TV dari ${station.code}?',
      message: 'TV akan kembali menampilkan kode pairing dan berhenti '
          'menerima perintah dari station ini.',
      confirmLabel: 'Lepas TV',
      destructive: true,
    );
    if (!ok || !mounted) return;

    await context.read<TvSyncService>().unpair(station.id);
    if (mounted) showSuccess(context, 'TV dilepas dari ${station.code}.');
  }

  Future<void> _resend(Station station) async {
    await context.read<TvSyncService>().forcePush(station);
    if (!mounted) return;

    final health = context.read<TvSyncService>().statusFor(station.id).health;
    if (health == TvLinkHealth.online) {
      showSuccess(context, '${station.code}: keadaan dikirim ulang ke TV.');
    } else {
      showWarning(context, '${station.code}: TV tidak merespons.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final content = _buildBody();
    if (widget.embedded) return content;

    return Scaffold(
      appBar: AppBar(title: const Text('Status TV')),
      body: SafeArea(child: content),
    );
  }

  Widget _buildBody() {
    final dash = context.watch<DashboardController>();
    final sync = context.watch<TvSyncService>();

    if (dash.loading && !dash.hasData) {
      return const Center(child: CircularProgressIndicator());
    }

    return RefreshIndicator(
      onRefresh: _refreshAll,
      backgroundColor: AppColors.surfaceLow,
      color: AppColors.primaryContainer,
      child: ListView(
        padding: const EdgeInsets.all(AppSpacing.gutterLg),
        children: [
          Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 820),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _Summary(dash: dash, sync: sync),
                  const SizedBox(height: AppSpacing.md),
                  for (final station in dash.stations)
                    Padding(
                      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                      child: _StationRow(
                        station: station,
                        status: sync.statusFor(station.id),
                        busy: sync.isBusy(station.id),
                        onPair: () => _pair(station),
                        onUnpair: () => _unpair(station),
                        onResend: () => _resend(station),
                        onRefresh: () =>
                            context.read<TvSyncService>().refreshStatus(station.id),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Ringkasan ────────────────────────────────────────────────────────

class _Summary extends StatelessWidget {
  const _Summary({required this.dash, required this.sync});

  final DashboardController dash;
  final TvSyncService sync;

  @override
  Widget build(BuildContext context) {
    final total = dash.stations.length;
    final linked = sync.linkedCount;

    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.surfaceLow,
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: Row(
        children: [
          _Count(
            value: sync.onlineCount,
            label: 'Tersambung',
            color: AppColors.secondary,
          ),
          const SizedBox(width: AppSpacing.lg),
          _Count(
            value: sync.problemCount,
            label: 'Bermasalah',
            color: sync.problemCount > 0
                ? AppColors.statusExpired
                : AppColors.onSurfaceVariant,
          ),
          const SizedBox(width: AppSpacing.lg),
          _Count(
            value: total - linked,
            label: 'Belum dipasang',
            color: AppColors.outline,
          ),
          const Spacer(),
          Flexible(
            child: Text(
              'Perintah dikirim langsung ke TV lewat jaringan lokal',
              textAlign: TextAlign.right,
              style:
                  AppTypography.labelSm.copyWith(color: AppColors.outline),
            ),
          ),
        ],
      ),
    );
  }
}

class _Count extends StatelessWidget {
  const _Count({
    required this.value,
    required this.label,
    required this.color,
  });

  final int value;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: '$label: $value',
      excludeSemantics: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('$value', style: AppTypography.moneyLg.copyWith(color: color)),
          Text(
            label,
            style: AppTypography.labelSm
                .copyWith(color: AppColors.onSurfaceVariant),
          ),
        ],
      ),
    );
  }
}

// ─── Baris per station ────────────────────────────────────────────────

class _StationRow extends StatelessWidget {
  const _StationRow({
    required this.station,
    required this.status,
    required this.busy,
    required this.onPair,
    required this.onUnpair,
    required this.onResend,
    required this.onRefresh,
  });

  final Station station;
  final TvLinkStatus status;
  final bool busy;
  final Future<void> Function() onPair;
  final Future<void> Function() onUnpair;
  final Future<void> Function() onResend;
  final Future<void> Function() onRefresh;

  Color get _color => switch (status.health) {
        TvLinkHealth.online => AppColors.secondary,
        TvLinkHealth.unreachable => AppColors.statusExpired,
        TvLinkHealth.rejected => AppColors.statusWarning,
        TvLinkHealth.unlinked => AppColors.outline,
      };

  String get _label => switch (status.health) {
        TvLinkHealth.online => 'Tersambung',
        TvLinkHealth.unreachable => 'Tidak merespons',
        TvLinkHealth.rejected => 'Pairing ditolak',
        TvLinkHealth.unlinked => 'Belum dipasang',
      };

  IconData get _icon => switch (status.health) {
        TvLinkHealth.online => Icons.tv,
        TvLinkHealth.unreachable => Icons.tv_off,
        TvLinkHealth.rejected => Icons.link_off,
        TvLinkHealth.unlinked => Icons.add_to_queue,
      };

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surfaceLow,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      clipBehavior: Clip.antiAlias,
      child: Row(
        children: [
          Container(width: AppSize.statusRail, height: 96, color: _color),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md - 2),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(
                        station.code,
                        style: AppTypography.headlineSm
                            .copyWith(color: AppColors.onSurface),
                      ),
                      if (station.consoleType != null) ...[
                        const SizedBox(width: AppSpacing.sm),
                        Text(
                          station.consoleType!,
                          style: AppTypography.labelSm
                              .copyWith(color: AppColors.outline),
                        ),
                      ],
                      const SizedBox(width: AppSpacing.sm),
                      Icon(_icon, size: 15, color: _color),
                      const SizedBox(width: AppSpacing.xs),
                      Text(
                        _label,
                        style: AppTypography.labelMd.copyWith(color: _color),
                      ),
                      const Spacer(),
                      if (busy)
                        const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      else
                        _LastContact(status: status),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.sm - 2),
                  _detail(),
                  const SizedBox(height: AppSpacing.sm),
                  _actions(context),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _detail() {
    final link = status.link;
    final info = status.info;

    if (link == null) {
      return Text(
        'Pasangkan TV supaya timer dan sesi tampil di layarnya.',
        style: AppTypography.bodySm.copyWith(color: AppColors.outline),
      );
    }

    final bits = <String>[
      link.baseUrl.replaceFirst(RegExp(r'^https?://'), ''),
      if (info != null) info.hardwareLabel,
      if (info != null) info.osLabel,
      if (info != null) info.kioskTier.label,
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          bits.join(' · '),
          style: AppTypography.labelSm.copyWith(color: AppColors.outline),
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
        if (status.lastError != null) ...[
          const SizedBox(height: AppSpacing.xs),
          Text(
            status.lastError!,
            style: AppTypography.bodySm.copyWith(color: _color),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
        ],
        // Apa yang SEHARUSNYA tampil di TV. Ini yang dipakai operator untuk
        // membandingkan dengan layar TV tanpa harus berdiri dan melihat.
        if (status.health == TvLinkHealth.online) ...[
          const SizedBox(height: AppSpacing.xs),
          _ExpectedOnTv(station: station),
        ],
      ],
    );
  }

  Widget _actions(BuildContext context) {
    if (status.link == null) {
      return Align(
        alignment: Alignment.centerLeft,
        child: AsyncButton(
          label: 'Pasang TV',
          icon: Icons.add_link,
          onPressed: onPair,
        ),
      );
    }

    return Wrap(
      spacing: AppSpacing.sm,
      runSpacing: AppSpacing.sm,
      children: [
        AsyncButton(
          label: 'Periksa',
          icon: Icons.refresh,
          outlined: true,
          onPressed: busy ? null : onRefresh,
        ),
        AsyncButton(
          label: 'Kirim ulang',
          icon: Icons.sync,
          outlined: true,
          onPressed: busy ? null : onResend,
        ),
        AsyncButton(
          label: 'Ganti TV',
          icon: Icons.swap_horiz,
          outlined: true,
          onPressed: onPair,
        ),
        AsyncButton(
          label: 'Lepas',
          icon: Icons.link_off,
          outlined: true,
          destructive: true,
          onPressed: onUnpair,
        ),
      ],
    );
  }
}

/// Apa yang seharusnya tampil di layar TV saat ini.
///
/// Dihitung dari data station yang sama yang dikirim ke TV, memakai
/// server-time offset. Kalau angka di sini dan di layar TV berbeda, berarti
/// perintah terakhir tidak sampai — itu diagnosis yang tidak bisa didapat
/// hanya dari status "tersambung".
class _ExpectedOnTv extends StatelessWidget {
  const _ExpectedOnTv({required this.station});

  final Station station;

  @override
  Widget build(BuildContext context) {
    final session = station.session;

    if (session == null) {
      return Row(
        children: [
          const Icon(Icons.desktop_access_disabled_outlined,
              size: 13, color: AppColors.outline),
          const SizedBox(width: AppSpacing.xs + 2),
          Text(
            'Di TV: layar idle',
            style: AppTypography.labelSm.copyWith(color: AppColors.outline),
          ),
        ],
      );
    }

    if (session.status == SessionStatus.pendingPayment) {
      return Row(
        children: [
          const Icon(Icons.schedule,
              size: 13, color: AppColors.statusPendingPayment),
          const SizedBox(width: AppSpacing.xs + 2),
          Text(
            'Di TV: menunggu pembayaran',
            style: AppTypography.labelSm
                .copyWith(color: AppColors.statusPendingPayment),
          ),
        ],
      );
    }

    return Consumer<AppTicker>(
      builder: (context, _, __) {
        final endAt = session.endAt;
        if (endAt == null) return const SizedBox.shrink();

        final remaining = ServerTime.instance.remainingUntil(endAt);
        final text = remaining.isNegative
            ? 'Di TV: habis ${formatDurationLabel(remaining.abs().inMinutes)} lalu'
            : 'Di TV: ${formatCountdown(remaining)}';

        return Row(
          children: [
            const Icon(Icons.timer_outlined,
                size: 13, color: AppColors.secondary),
            const SizedBox(width: AppSpacing.xs + 2),
            Text(
              text,
              style: AppTypography.labelSm.copyWith(
                color: remaining.isNegative
                    ? AppColors.statusExpired
                    : AppColors.secondary,
              ),
            ),
          ],
        );
      },
    );
  }
}

class _LastContact extends StatelessWidget {
  const _LastContact({required this.status});

  final TvLinkStatus status;

  @override
  Widget build(BuildContext context) {
    final at = status.lastOkAt;
    if (at == null) {
      return Text(
        status.link == null ? '' : 'belum ada kontak',
        style: AppTypography.labelSm.copyWith(color: AppColors.outline),
      );
    }

    return Consumer<AppTicker>(
      builder: (context, _, __) => Text(
        formatRelative(at, ServerTime.instance.now),
        style: AppTypography.labelSm.copyWith(color: AppColors.outline),
      ),
    );
  }
}
