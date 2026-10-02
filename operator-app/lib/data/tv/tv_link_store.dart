import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../../domain/models/tv_agent.dart';

/// Penyimpanan pasangan station ↔ TV di perangkat operator.
///
/// ## Kenapa di perangkat, bukan di server
///
/// Karena belum ada server (DEC-015). Saat Laravel masuk, pemetaan
/// station↔device pindah ke tabel `devices` (PRD §22) dan kelas ini hilang.
/// Bentuk datanya sudah mengikuti itu, jadi migrasinya menyalin, bukan
/// merancang ulang.
///
/// ## Satu station, satu TV
///
/// PRD §10: "Satu station ↔ satu device aktif". Memasangkan TV baru ke station
/// yang sudah punya TV akan **menggantikan** yang lama, bukan menambah.
class TvLinkStore {
  TvLinkStore._(this._prefs, this._links);

  static const _key = 'tv.links.v1';

  final SharedPreferences _prefs;
  final Map<String, TvLink> _links;

  static Future<TvLinkStore> open() async {
    final prefs = await SharedPreferences.getInstance();
    return TvLinkStore._(prefs, _load(prefs));
  }

  /// Semua pasangan, dikunci `stationId`.
  Map<String, TvLink> get all => Map.unmodifiable(_links);

  TvLink? forStation(String stationId) => _links[stationId];

  /// Cari pasangan berdasarkan identitas perangkat.
  ///
  /// Dipakai saat pemindaian menemukan TV dengan IP berbeda: yang menentukan
  /// ini TV yang sama atau bukan adalah `device_uid`, bukan alamatnya.
  TvLink? forDeviceUid(String deviceUid) {
    for (final link in _links.values) {
      if (link.deviceUid == deviceUid) return link;
    }
    return null;
  }

  Future<void> save(TvLink link) async {
    // Satu TV tidak boleh terpasang di dua station. Kalau perangkat yang sama
    // dipasangkan ke station lain, pasangan lamanya dibuang — kalau tidak,
    // dua station akan mengirim perintah ke TV yang sama dan timer-nya saling
    // menimpa tanpa ada yang tahu kenapa.
    _links.removeWhere(
      (stationId, existing) =>
          existing.deviceUid == link.deviceUid && stationId != link.stationId,
    );

    _links[link.stationId] = link;
    await _persist();
  }

  Future<void> remove(String stationId) async {
    _links.remove(stationId);
    await _persist();
  }

  /// Perbarui alamat setelah TV mendapat IP baru dari DHCP.
  Future<void> updateAddress(String stationId, String baseUrl) async {
    final link = _links[stationId];
    if (link == null || link.baseUrl == baseUrl) return;
    _links[stationId] = link.copyWith(baseUrl: baseUrl);
    await _persist();
  }

  Future<void> clear() async {
    _links.clear();
    await _prefs.remove(_key);
  }

  Future<void> _persist() async {
    final payload = _links.values.map((l) => l.toJson()).toList();
    await _prefs.setString(_key, jsonEncode(payload));
  }

  static Map<String, TvLink> _load(SharedPreferences prefs) {
    final raw = prefs.getString(_key);
    if (raw == null || raw.isEmpty) return {};

    try {
      final list = jsonDecode(raw) as List<dynamic>;
      final out = <String, TvLink>{};
      for (final item in list) {
        final link = TvLink.fromJson(item as Map<String, dynamic>);
        out[link.stationId] = link;
      }
      return out;
    } catch (_) {
      // Data rusak atau format lama. Dibuang, bukan membuat app gagal start —
      // operator cukup memasangkan ulang, dan itu lebih baik daripada
      // aplikasi yang tidak bisa dibuka.
      return {};
    }
  }
}
