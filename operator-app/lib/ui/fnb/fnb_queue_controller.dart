import 'package:flutter/foundation.dart';

import '../../domain/errors/api_error.dart';
import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import '../../domain/repositories/billing_repository.dart';

/// State antrian F&B.
///
/// Status order dikelompokkan, bukan ditampilkan sebagai satu daftar panjang:
/// operator dapur perlu tahu "apa yang belum disentuh" dan "apa yang sudah
/// siap diantar" sebagai dua pertanyaan berbeda.
///
/// Transisi status tetap ditegakkan server (`FNB_STATUS_TRANSITION_INVALID`).
/// [FnbOrderStatus.next] di sini hanya untuk menentukan label tombol.
class FnbQueueController extends ChangeNotifier {
  FnbQueueController(this._repo);

  final BillingRepository _repo;

  static const activeStatuses = {
    FnbOrderStatus.pending,
    FnbOrderStatus.processing,
    FnbOrderStatus.ready,
  };

  static const doneStatuses = {
    FnbOrderStatus.delivered,
    FnbOrderStatus.cancelled,
  };

  List<FnbOrder> _orders = const [];
  bool _loading = false;
  Object? _error;

  /// Order yang sedang dalam proses update — tombolnya dinonaktifkan supaya
  /// tidak bisa ditekan dua kali.
  final Set<String> _updating = {};

  bool get loading => _loading;
  Object? get error => _error;
  bool get hasData => _orders.isNotEmpty;

  bool isUpdating(String orderId) => _updating.contains(orderId);

  List<FnbOrder> ofStatus(FnbOrderStatus s) =>
      _orders.where((o) => o.status == s).toList(growable: false);

  List<FnbOrder> get active => _orders
      .where((o) => activeStatuses.contains(o.status))
      .toList(growable: false);

  List<FnbOrder> get done {
    final list = _orders
        .where((o) => doneStatuses.contains(o.status))
        .toList(growable: true);
    // Riwayat dibaca dari yang terbaru.
    list.sort((a, b) => b.createdAt.compareTo(a.createdAt));
    return list;
  }

  /// Dipakai untuk badge di dashboard: yang masih menuntut tindakan.
  int get actionableCount => _orders
      .where((o) =>
          o.status == FnbOrderStatus.pending ||
          o.status == FnbOrderStatus.processing)
      .length;

  String? get errorMessage => switch (_error) {
        ApiError e => e.message,
        null => null,
        _ => 'Terjadi kesalahan tidak terduga.',
      };

  Future<void> load({bool silent = false}) async {
    if (!silent) {
      _loading = true;
      _error = null;
      notifyListeners();
    }
    try {
      // Ambil semua, lalu dikelompokkan di client. Antrian satu lokasi
      // jumlahnya kecil; memisahkannya jadi beberapa request hanya
      // menambah bolak-balik tanpa manfaat.
      final all = await _repo.fetchFnbOrders();
      _orders = all;
      _error = null;
    } catch (e) {
      _error = e;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> refresh() => load(silent: true);

  /// Majukan order ke status berikutnya.
  ///
  /// Mengembalikan status baru kalau berhasil. Melempar [ApiError] kalau
  /// server menolak — pemanggil yang menampilkan pesannya.
  Future<FnbOrderStatus> advance(FnbOrder order) async {
    final next = order.status.next;
    if (next == null) {
      throw const ApiError(
        code: ApiErrorCode.fnbStatusTransitionInvalid,
        message: 'Order ini sudah selesai.',
      );
    }
    return _apply(order, next);
  }

  Future<FnbOrderStatus> cancel(FnbOrder order) =>
      _apply(order, FnbOrderStatus.cancelled);

  Future<FnbOrderStatus> _apply(FnbOrder order, FnbOrderStatus status) async {
    _updating.add(order.id);
    notifyListeners();
    try {
      final updated = await _repo.updateFnbOrderStatus(
        orderId: order.id,
        status: status,
      );
      final idx = _orders.indexWhere((o) => o.id == order.id);
      if (idx >= 0) {
        _orders = List.of(_orders)..[idx] = updated;
      }
      return updated.status;
    } finally {
      _updating.remove(order.id);
      notifyListeners();
    }
  }
}
