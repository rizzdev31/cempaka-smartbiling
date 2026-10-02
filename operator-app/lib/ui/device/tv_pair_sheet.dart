import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';
import '../../data/tv/tv_agent_client.dart';
import '../../data/tv/tv_discovery.dart';
import '../../data/tv/tv_sync_service.dart';
import '../../domain/models/models.dart';
import '../../domain/models/tv_agent.dart';
import '../widgets/confirm_dialog.dart';

/// Alur memasangkan TV ke satu station.
///
/// Dua jalur, keduanya selalu tersedia:
/// 1. **Pindai jaringan** — menemukan TV otomatis. Tidak tersedia di web.
/// 2. **Alamat manual** — selalu bisa. Alamatnya tampil di layar TV.
///
/// Jalur manual bukan cadangan darurat: di web itu satu-satunya cara, dan
/// saat operator dan TV berbeda subnet, pemindaian memang tidak akan
/// menemukan apa pun.
Future<bool> showTvPairSheet(
  BuildContext context, {
  required Station station,
}) async {
  final result = await showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.surfaceLow,
    barrierColor: AppColors.scrim,
    shape: const RoundedRectangleBorder(
      borderRadius:
          BorderRadius.vertical(top: Radius.circular(AppRadius.modal)),
    ),
    builder: (_) => _TvPairSheet(station: station),
  );
  return result ?? false;
}

class _TvPairSheet extends StatefulWidget {
  const _TvPairSheet({required this.station});

  final Station station;

  @override
  State<_TvPairSheet> createState() => _TvPairSheetState();
}

class _TvPairSheetState extends State<_TvPairSheet> {
  final _address = TextEditingController();
  final _code = TextEditingController();

  TvScanResult? _scan;
  bool _scanning = false;
  int _scanDone = 0;
  int _scanTotal = 0;

  TvAgentInfo? _selected;
  String? _addressError;
  String? _codeError;

  @override
  void initState() {
    super.initState();
    final discovery = context.read<TvDiscovery>();
    if (discovery.isSupported) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _startScan());
    }
  }

  @override
  void dispose() {
    _address.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _startScan() async {
    final discovery = context.read<TvDiscovery>();
    setState(() {
      _scanning = true;
      _scanDone = 0;
      _scanTotal = 0;
    });

    final result = await discovery.scan(
      onProgress: (done, total) {
        if (!mounted) return;
        setState(() {
          _scanDone = done;
          _scanTotal = total;
        });
      },
    );

    if (!mounted) return;
    setState(() {
      _scan = result;
      _scanning = false;
      // Kalau hanya satu TV ditemukan, langsung dipilih — operator tidak
      // perlu menekan apa pun untuk hal yang sudah jelas.
      if (result.agents.length == 1) _selected = result.agents.first;
    });
  }

  Future<void> _probeManual() async {
    final raw = _address.text.trim();
    if (raw.isEmpty) {
      setState(() => _addressError = 'Masukkan alamat yang tampil di layar TV.');
      return;
    }
    setState(() => _addressError = null);

    final info = await context.read<TvDiscovery>().probeAddress(raw);
    if (!mounted) return;

    if (info == null) {
      setState(() => _addressError =
          'Tidak ada agen TV di alamat itu. Periksa TV dan jaringannya.');
      return;
    }
    setState(() => _selected = info);
  }

  Future<void> _pair() async {
    final target = _selected;
    if (target == null) return;

    final code = _code.text.trim();
    if (code.length < 6) {
      setState(() => _codeError = 'Kode 6 digit dari layar TV.');
      return;
    }
    setState(() => _codeError = null);

    try {
      await context.read<TvSyncService>().pair(
            station: widget.station,
            baseUrl: target.baseUrl,
            code: code,
          );
      if (!mounted) return;
      Navigator.of(context).pop(true);
      showSuccess(
        context,
        'TV terpasang di ${widget.station.code}.',
      );
    } catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final discovery = context.read<TvDiscovery>();
    final bottomInset = MediaQuery.viewInsetsOf(context).bottom;

    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: SafeArea(
        child: ConstrainedBox(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.sizeOf(context).height * 0.88,
          ),
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Pasang TV — ${widget.station.code}',
                        style: AppTypography.headlineSm,
                      ),
                    ),
                    IconButton(
                      onPressed: () => Navigator.of(context).pop(false),
                      icon: const Icon(Icons.close),
                      tooltip: 'Tutup',
                    ),
                  ],
                ),
                const SizedBox(height: AppSpacing.sm),
                Text(
                  'Buka aplikasi Cempaka TV di televisi. Layarnya menampilkan '
                  'kode 6 digit dan alamat jaringan.',
                  style: AppTypography.bodySm
                      .copyWith(color: AppColors.onSurfaceVariant),
                ),

                const SizedBox(height: AppSpacing.lg),
                _sectionLabel('1. PILIH TV'),
                const SizedBox(height: AppSpacing.sm),

                if (discovery.isSupported) ...[
                  _scanRow(),
                  const SizedBox(height: AppSpacing.sm),
                ] else
                  _notice(
                    icon: Icons.info_outline,
                    color: AppColors.onSurfaceVariant,
                    text: 'Pemindaian jaringan tidak tersedia di browser. '
                        'Masukkan alamat yang tampil di layar TV.',
                  ),

                ..._scanResults(),

                const SizedBox(height: AppSpacing.sm),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _address,
                        autocorrect: false,
                        keyboardType: TextInputType.url,
                        decoration: InputDecoration(
                          isDense: true,
                          labelText: 'Alamat TV',
                          hintText: '192.168.0.77',
                          errorText: _addressError,
                          helperText:
                              'Port ${TvAgentClient.defaultPort} otomatis',
                          prefixIcon: const Icon(Icons.tv_outlined, size: 18),
                        ),
                      ),
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: AsyncButton(
                        label: 'Cek',
                        icon: Icons.search,
                        outlined: true,
                        onPressed: _probeManual,
                      ),
                    ),
                  ],
                ),

                if (_selected != null) ...[
                  const SizedBox(height: AppSpacing.lg),
                  _sectionLabel('2. MASUKKAN KODE'),
                  const SizedBox(height: AppSpacing.sm),
                  _selectedCard(_selected!),
                  const SizedBox(height: AppSpacing.md),
                  TextField(
                    controller: _code,
                    keyboardType: TextInputType.number,
                    inputFormatters: [
                      FilteringTextInputFormatter.digitsOnly,
                      LengthLimitingTextInputFormatter(6),
                    ],
                    style: AppTypography.moneyLg
                        .copyWith(color: AppColors.onSurface, letterSpacing: 6),
                    decoration: InputDecoration(
                      labelText: 'Kode dari layar TV',
                      errorText: _codeError,
                      counterText: '',
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  AsyncButton(
                    label: 'Pasangkan',
                    icon: Icons.link,
                    expand: true,
                    onPressed: _pair,
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ── Potongan ──────────────────────────────────────────────────────

  Widget _sectionLabel(String text) => Text(
        text,
        style: AppTypography.labelSm.copyWith(color: AppColors.outline),
      );

  Widget _scanRow() {
    final progress = _scanTotal == 0 ? null : _scanDone / _scanTotal;

    return Row(
      children: [
        Expanded(
          child: _scanning
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Memindai ${_scan?.subnet ?? 'jaringan'}… '
                      '$_scanDone/$_scanTotal',
                      style: AppTypography.bodySm
                          .copyWith(color: AppColors.onSurfaceVariant),
                    ),
                    const SizedBox(height: AppSpacing.sm - 2),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(3),
                      child: LinearProgressIndicator(
                        value: progress,
                        minHeight: 4,
                      ),
                    ),
                  ],
                )
              : Text(
                  _scan == null
                      ? 'Belum dipindai'
                      : _scan!.isEmpty
                          ? 'Tidak ada TV ditemukan di ${_scan!.subnet}.x'
                          : '${_scan!.agents.length} TV ditemukan '
                              'di ${_scan!.subnet}.x',
                  style: AppTypography.bodySm
                      .copyWith(color: AppColors.onSurfaceVariant),
                ),
        ),
        const SizedBox(width: AppSpacing.sm),
        AsyncButton(
          label: 'Pindai',
          icon: Icons.wifi_find,
          outlined: true,
          onPressed: _scanning ? null : _startScan,
        ),
      ],
    );
  }

  List<Widget> _scanResults() {
    final agents = _scan?.agents ?? const <TvAgentInfo>[];
    if (agents.isEmpty) return const [];

    return [
      const SizedBox(height: AppSpacing.sm),
      for (final a in agents)
        Padding(
          padding: const EdgeInsets.only(bottom: AppSpacing.sm),
          child: _agentRow(a),
        ),
    ];
  }

  Widget _agentRow(TvAgentInfo info) {
    final selected = _selected?.deviceUid == info.deviceUid;

    // TV yang sudah dipasangkan ke station LAIN ditandai jelas: memasangkannya
    // ke sini akan melepasnya dari station itu, dan operator harus tahu
    // sebelum menekan.
    final takenByOther = info.paired &&
        info.stationCode != null &&
        info.stationCode != widget.station.code;

    return Material(
      color: selected
          ? AppColors.primaryContainer.withValues(alpha: 0.14)
          : AppColors.surfaceContainer,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: InkWell(
        onTap: () => setState(() => _selected = info),
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: Container(
          padding: const EdgeInsets.all(AppSpacing.md - 2),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.md),
            border: Border.all(
              color: selected
                  ? AppColors.primaryContainer
                  : AppColors.surfaceHigh,
              width: selected ? 2 : 1,
            ),
          ),
          child: Row(
            children: [
              Icon(
                info.isTelevision ? Icons.tv : Icons.devices_other,
                size: 20,
                color: selected ? AppColors.primary : AppColors.onSurfaceVariant,
              ),
              const SizedBox(width: AppSpacing.sm + 2),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(info.hardwareLabel, style: AppTypography.bodyMd),
                    const SizedBox(height: 1),
                    Text(
                      '${info.address} · ${info.osLabel}',
                      style: AppTypography.labelSm
                          .copyWith(color: AppColors.outline),
                    ),
                  ],
                ),
              ),
              if (takenByOther)
                _tag(info.stationCode!, AppColors.statusWarning)
              else if (info.paired)
                _tag('terpasang', AppColors.secondary)
              else
                _tag('bebas', AppColors.primary),
            ],
          ),
        ),
      ),
    );
  }

  Widget _selectedCard(TvAgentInfo info) {
    final takenByOther = info.paired &&
        info.stationCode != null &&
        info.stationCode != widget.station.code;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          padding: const EdgeInsets.all(AppSpacing.md - 2),
          decoration: BoxDecoration(
            color: AppColors.surfaceContainer,
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(info.hardwareLabel, style: AppTypography.bodyMd),
                    Text(
                      '${info.address} · ${info.osLabel} · '
                      '${info.kioskTier.label}',
                      style: AppTypography.labelSm
                          .copyWith(color: AppColors.outline),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),

        if (info.pairingLocked) ...[
          const SizedBox(height: AppSpacing.sm),
          _notice(
            icon: Icons.lock_outline,
            color: AppColors.error,
            text: 'TV mengunci pairing karena terlalu banyak kode salah. '
                'Mulai ulang aplikasi di TV.',
          ),
        ],

        if (takenByOther) ...[
          const SizedBox(height: AppSpacing.sm),
          _notice(
            icon: Icons.swap_horiz,
            color: AppColors.statusWarning,
            text: 'TV ini sedang terpasang di ${info.stationCode}. '
                'Memasangkannya ke ${widget.station.code} akan melepasnya '
                'dari sana.',
          ),
        ],

        if (info.kioskTier == KioskTier.soft) ...[
          const SizedBox(height: AppSpacing.sm),
          _notice(
            icon: Icons.info_outline,
            color: AppColors.onSurfaceVariant,
            text: 'Kiosk lunak: customer masih bisa keluar dari aplikasi '
                'lewat tombol HOME. Kiosk terkunci butuh Device Owner.',
          ),
        ],
      ],
    );
  }

  Widget _tag(String text, Color color) => Container(
        padding:
            const EdgeInsets.symmetric(horizontal: AppSpacing.sm, vertical: 2),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.16),
          borderRadius: BorderRadius.circular(AppRadius.pill),
        ),
        child: Text(
          text,
          style: AppTypography.labelSm.copyWith(color: color),
        ),
      );

  Widget _notice({
    required IconData icon,
    required Color color,
    required String text,
  }) =>
      Container(
        padding: const EdgeInsets.all(AppSpacing.sm + 2),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.10),
          borderRadius: BorderRadius.circular(AppRadius.md),
          border: Border.all(color: color.withValues(alpha: 0.28)),
        ),
        child: Row(
          children: [
            Icon(icon, size: 16, color: color),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: Text(
                text,
                style: AppTypography.bodySm.copyWith(color: color),
              ),
            ),
          ],
        ),
      );
}
