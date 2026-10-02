/// Error code ditranskrip dari `docs/contracts/API.md` §11.
///
/// ATURAN: client menentukan perilaku dari [ApiError.code], BUKAN dari
/// HTTP status (kontrak §2).
class ApiErrorCode {
  ApiErrorCode._();

  static const validationFailed = 'VALIDATION_FAILED';
  static const invalidCredentials = 'INVALID_CREDENTIALS';
  static const unauthenticated = 'UNAUTHENTICATED';
  static const userInactive = 'USER_INACTIVE';
  static const forbidden = 'FORBIDDEN';
  static const tooManyAttempts = 'TOO_MANY_ATTEMPTS';
  static const notFound = 'NOT_FOUND';
  static const idempotencyKeyRequired = 'IDEMPOTENCY_KEY_REQUIRED';
  static const idempotencyKeyReused = 'IDEMPOTENCY_KEY_REUSED';
  static const stationNotAvailable = 'STATION_NOT_AVAILABLE';
  static const stationHasActiveSession = 'STATION_HAS_ACTIVE_SESSION';
  static const targetStationSame = 'TARGET_STATION_SAME';
  static const sessionStatusInvalid = 'SESSION_STATUS_INVALID';
  static const sessionNotOrderable = 'SESSION_NOT_ORDERABLE';
  static const extendDurationInvalid = 'EXTEND_DURATION_INVALID';
  static const extendGraceExpired = 'EXTEND_GRACE_EXPIRED';
  static const paymentAmountExceedsBalance = 'PAYMENT_AMOUNT_EXCEEDS_BALANCE';
  static const paymentReferenceRequired = 'PAYMENT_REFERENCE_REQUIRED';
  static const checkoutInsufficientPayment = 'CHECKOUT_INSUFFICIENT_PAYMENT';
  static const fnbStatusTransitionInvalid = 'FNB_STATUS_TRANSITION_INVALID';
  static const deviceTokenInvalid = 'DEVICE_TOKEN_INVALID';
  static const deviceNotAssigned = 'DEVICE_NOT_ASSIGNED';

  /// Bukan dari server — dipakai client saat request gagal sebelum sampai.
  static const networkUnreachable = 'NETWORK_UNREACHABLE';
  static const timeout = 'TIMEOUT';
  static const unexpected = 'UNEXPECTED';
}

/// Error terstruktur dari kontrak §2.
class ApiError implements Exception {
  const ApiError({
    required this.code,
    required this.message,
    this.details = const {},
    this.httpStatus,
  });

  final String code;

  /// Pesan Bahasa Indonesia dari server, siap ditampilkan ke operator.
  final String message;
  final Map<String, dynamic> details;
  final int? httpStatus;

  factory ApiError.fromJson(Map<String, dynamic> j, {int? httpStatus}) {
    final e = (j['error'] as Map<String, dynamic>?) ?? const {};
    return ApiError(
      code: e['code'] as String? ?? ApiErrorCode.unexpected,
      message: e['message'] as String? ?? 'Terjadi kesalahan tidak terduga.',
      details: (e['details'] as Map<String, dynamic>?) ?? const {},
      httpStatus: httpStatus,
    );
  }

  const ApiError.network()
      : code = ApiErrorCode.networkUnreachable,
        message = 'Tidak bisa menghubungi server. Periksa koneksi dan IP server.',
        details = const {},
        httpStatus = null;

  const ApiError.timeout()
      : code = ApiErrorCode.timeout,
        message = 'Server tidak merespons. Coba lagi.',
        details = const {},
        httpStatus = null;

  /// Pesan validasi per field — dipakai untuk menaruh error DI BAWAH field
  /// terkait, bukan menumpuk di atas (UI-UX-SPEC §7).
  Map<String, List<String>> get fieldErrors {
    if (code != ApiErrorCode.validationFailed) return const {};
    return details.map(
      (k, v) => MapEntry(
        k,
        v is List ? v.map((e) => e.toString()).toList() : [v.toString()],
      ),
    );
  }

  /// Token tidak berlaku -> app harus kembali ke login.
  bool get requiresReauth =>
      code == ApiErrorCode.unauthenticated || code == ApiErrorCode.userInactive;

  /// Retry dengan Idempotency-Key YANG SAMA masuk akal.
  bool get isRetryable =>
      code == ApiErrorCode.networkUnreachable || code == ApiErrorCode.timeout;

  @override
  String toString() => 'ApiError($code): $message';
}
