/// Identitas operator yang sedang masuk.
///
/// Bentuknya mengikuti `UserPresenter::one()` di backend (API.md §4).
///
/// Permission dikirim server dan **tidak** ditempel ke token: backend sengaja
/// menyimpan role di user, supaya menaikkan seseorang jadi owner langsung
/// berlaku tanpa dia harus login ulang. Jadi client membaca permission dari
/// respons, bukan menyimpulkannya sendiri dari role.
class AuthUser {
  const AuthUser({
    required this.id,
    required this.name,
    required this.username,
    required this.role,
    required this.permissions,
  });

  factory AuthUser.fromJson(Map<String, dynamic> json) => AuthUser(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        username: json['username'] as String? ?? '',
        role: json['role'] as String? ?? '',
        permissions: (json['permissions'] as List<dynamic>? ?? const [])
            .map((e) => e as String)
            .toSet(),
      );

  final int id;
  final String name;
  final String username;
  final String role;
  final Set<String> permissions;

  bool can(String permission) => permissions.contains(permission);

  /// Inisial untuk avatar — diambil dari nama, bukan username.
  String get initials {
    final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty);
    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first.characters1();
    return '${parts.first.characters1()}${parts.last.characters1()}';
  }
}

extension on String {
  String characters1() => isEmpty ? '' : substring(0, 1).toUpperCase();
}

/// Hasil login: token + siapa yang masuk.
///
/// `active_shift` ikut dikirim backend pada login, tapi tidak dipakai di sini —
/// `ShiftController` memuatnya sendiri lewat endpoint shift, dan menyimpan
/// salinan kedua di sini hanya akan menciptakan dua sumber kebenaran yang
/// bisa berbeda setelah shift dibuka atau ditutup.
class AuthSession {
  const AuthSession({required this.token, required this.user});

  final String token;
  final AuthUser user;
}
