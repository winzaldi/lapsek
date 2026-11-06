import 'package:flutter_secure_storage/flutter_secure_storage.dart';

abstract interface class SessionStore {
  Future<String?> readToken();

  Future<void> writeToken(String token);

  Future<void> clear();
}

class SecureSessionStore implements SessionStore {
  const SecureSessionStore();

  static const _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(resetOnError: true),
    iOptions: IOSOptions(
      accessibility: KeychainAccessibility.first_unlock_this_device,
    ),
  );

  static const _tokenKey = 'laos_access_token';

  @override
  Future<void> clear() => _storage.delete(key: _tokenKey);

  @override
  Future<String?> readToken() => _storage.read(key: _tokenKey);

  @override
  Future<void> writeToken(String token) =>
      _storage.write(key: _tokenKey, value: token);
}
