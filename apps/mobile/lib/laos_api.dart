import 'dart:convert';

import 'package:http/http.dart' as http;

class LaosApiException implements Exception {
  const LaosApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

class LaosUser {
  const LaosUser({
    required this.id,
    required this.name,
    required this.email,
    required this.roles,
  });

  final int id;
  final String name;
  final String email;
  final List<String> roles;

  factory LaosUser.fromJson(Map<String, dynamic> json) {
    final roles = json['roles'];
    return LaosUser(
      id: _asInt(json['id']) ?? 0,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      roles: roles is List
          ? roles
                .whereType<Map>()
                .map((role) => role['name'])
                .whereType<String>()
                .toList()
          : const [],
    );
  }
}

class LaosHistoryPage {
  const LaosHistoryPage({
    required this.records,
    required this.currentPage,
    required this.lastPage,
    required this.availablePeriods,
  });

  final List<Map<String, dynamic>> records;
  final int currentPage;
  final int lastPage;
  final List<Map<String, dynamic>> availablePeriods;

  factory LaosHistoryPage.fromJson(Map<String, dynamic> json) {
    final page = json['data'];
    final periods = json['available_periods'];
    if (page is! Map) {
      throw const LaosApiException('Format riwayat kesehatan tidak valid.');
    }

    return LaosHistoryPage(
      records: (page['data'] is List)
          ? (page['data'] as List)
                .whereType<Map>()
                .map((record) => Map<String, dynamic>.from(record))
                .toList()
          : const [],
      currentPage: _asInt(page['current_page']) ?? 1,
      lastPage: _asInt(page['last_page']) ?? 1,
      availablePeriods: periods is List
          ? periods
                .whereType<Map>()
                .map((period) => Map<String, dynamic>.from(period))
                .toList()
          : const [],
    );
  }
}

abstract interface class LaosGateway {
  Future<String> login(String email, String password);

  Future<({LaosUser user, int? studentId})> me(String token);

  Future<void> logout(String token);

  Future<LaosHistoryPage> history(
    String token,
    int studentId, {
    String? academicYear,
    int? semester,
    int page = 1,
  });

  Future<void> requestPasswordReset(String email);
}

class LaosApi implements LaosGateway {
  LaosApi({http.Client? client, Uri? baseUri})
    : _client = client ?? http.Client(),
      _baseUri = baseUri ?? _defaultBaseUri();

  final http.Client _client;
  final Uri _baseUri;

  static Uri _defaultBaseUri() {
    const configured = String.fromEnvironment('LAOS_API_BASE_URL');
    final value = configured.isNotEmpty
        ? configured
        : 'http://10.0.2.2:8000/api/v1';
    return Uri.parse(value.endsWith('/') ? value : '$value/');
  }

  @override
  Future<String> login(String email, String password) async {
    final payload = await _request(
      'auth/login',
      method: 'POST',
      body: {
        'email': email.trim(),
        'password': password,
        'device_name': 'LAOS Android/iOS',
      },
    );
    final token = payload['access_token'];
    if (token is! String || token.isEmpty) {
      throw const LaosApiException(
        'API tidak mengembalikan token sesi yang valid.',
      );
    }
    return token;
  }

  @override
  Future<({LaosUser user, int? studentId})> me(String token) async {
    final payload = await _request('auth/me', token: token);
    final userValue = payload['user'];
    if (userValue is! Map) {
      throw const LaosApiException('Data akun tidak valid.');
    }
    final user = LaosUser.fromJson(Map<String, dynamic>.from(userValue));
    if (!user.roles.contains('student')) {
      throw const LaosApiException('Aplikasi ini hanya untuk akun Siswa.');
    }
    return (user: user, studentId: _asInt(payload['student_id']));
  }

  @override
  Future<void> logout(String token) async {
    await _request('auth/logout', method: 'POST', token: token);
  }

  @override
  Future<LaosHistoryPage> history(
    String token,
    int studentId, {
    String? academicYear,
    int? semester,
    int page = 1,
  }) async {
    final query = <String, String>{'page': '$page'};
    if (academicYear != null) query['academic_year'] = academicYear;
    if (semester != null) query['semester'] = '$semester';
    final path = 'students/$studentId/health-history';
    final payload = await _request(path, token: token, query: query);
    return LaosHistoryPage.fromJson(payload);
  }

  @override
  Future<void> requestPasswordReset(String email) async {
    await _request(
      'auth/forgot-password',
      method: 'POST',
      body: {'email': email.trim()},
    );
  }

  Future<Map<String, dynamic>> _request(
    String path, {
    String method = 'GET',
    String? token,
    Map<String, String>? query,
    Map<String, Object?>? body,
  }) async {
    final uri = _baseUri.resolve(path).replace(queryParameters: query);
    final headers = <String, String>{'Accept': 'application/json'};
    if (token != null) headers['Authorization'] = 'Bearer $token';
    if (body != null) headers['Content-Type'] = 'application/json';

    try {
      final request = http.Request(method, uri)
        ..headers.addAll(headers)
        ..followRedirects = false;
      if (body != null) request.body = jsonEncode(body);
      final streamed = await _client
          .send(request)
          .timeout(const Duration(seconds: 20));
      final response = await http.Response.fromStream(
        streamed,
      ).timeout(const Duration(seconds: 20));
      final decoded = response.body.isEmpty
          ? <String, dynamic>{}
          : jsonDecode(response.body);
      if (decoded is! Map) {
        throw const LaosApiException('Respons API tidak dapat dibaca.');
      }
      final payload = Map<String, dynamic>.from(decoded);
      if (response.statusCode < 200 || response.statusCode >= 300) {
        final validation = payload['errors'];
        final details = validation is Map
            ? validation.values
                  .whereType<List>()
                  .expand((messages) => messages)
                  .whereType<String>()
                  .join(' ')
            : '';
        throw LaosApiException(
          details.isNotEmpty
              ? '${payload['message'] ?? 'Permintaan gagal.'} $details'
              : payload['message'] as String? ??
                    'Permintaan gagal (HTTP ${response.statusCode}).',
          statusCode: response.statusCode,
        );
      }
      return payload;
    } on LaosApiException {
      rethrow;
    } on FormatException {
      throw const LaosApiException('Respons API tidak valid.');
    } on http.ClientException {
      throw const LaosApiException('Tidak dapat terhubung ke layanan LAOS.');
    } on Exception catch (error) {
      if (error is LaosApiException) rethrow;
      throw const LaosApiException(
        'Permintaan melewati batas waktu atau jaringan tidak tersedia.',
      );
    }
  }
}

int? _asInt(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return value is String ? int.tryParse(value) : null;
}
