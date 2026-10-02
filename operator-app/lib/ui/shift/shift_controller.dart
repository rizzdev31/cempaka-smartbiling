import 'package:flutter/foundation.dart';
import 'package:uuid/uuid.dart';

import '../../domain/errors/api_error.dart';
import '../../domain/models/models.dart';
import '../../domain/repositories/billing_repository.dart';

/// State shift operator.
///
/// Shift bukan soal beberapa kasir bekerja bersamaan — dengan satu tablet
/// (DEC-013) itu tidak terjadi. Shift adalah soal **pertanggungjawaban kas
/// per periode kerja**: siapa yang pegang kas, berapa awalnya, berapa yang
/// masuk, dan berapa selisihnya saat ditutup.
///
/// Selisih kas adalah angka yang diaudit (PRD §24), jadi dihitung server
/// dan tidak pernah disembunyikan dari operator.
class ShiftController extends ChangeNotifier {
  ShiftController(this._repo);

  final BillingRepository _repo;
  static const _uuid = Uuid();

  Shift? _current;
  List<Shift> _history = const [];
  bool _loading = false;
  Object? _error;

  Shift? get current => _current;
  List<Shift> get history => _history;
  bool get loading => _loading;
  Object? get error => _error;

  bool get hasOpenShift => _current != null;

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
      final results = await Future.wait([
        _repo.fetchCurrentShift(),
        _repo.fetchShiftHistory(),
      ]);
      _current = results[0] as Shift?;
      _history = results[1] as List<Shift>;
      _error = null;
    } catch (e) {
      _error = e;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> refresh() => load(silent: true);

  Future<Shift> open({
    required int openingCash,
    String? idempotencyKey,
  }) async {
    final shift = await _repo.openShift(
      openingCash: openingCash,
      idempotencyKey: idempotencyKey ?? _uuid.v4(),
    );
    _current = shift;
    notifyListeners();
    return shift;
  }

  /// [closingCash] adalah hasil hitungan fisik operator, bukan angka sistem.
  /// Selisihnya ada di [Shift.variance] pada hasil yang dikembalikan.
  Future<Shift> close({
    required int closingCash,
    String? note,
    String? idempotencyKey,
  }) async {
    final shift = _current;
    if (shift == null) {
      throw const ApiError(
        code: ApiErrorCode.notFound,
        message: 'Tidak ada shift yang berjalan.',
      );
    }

    final closed = await _repo.closeShift(
      shiftId: shift.id,
      closingCash: closingCash,
      note: note,
      idempotencyKey: idempotencyKey ?? _uuid.v4(),
    );

    _current = null;
    _history = [closed, ..._history];
    notifyListeners();
    return closed;
  }

  static String newIdempotencyKey() => _uuid.v4();
}
