import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lapsek_mobile/laos_api.dart';
import 'package:lapsek_mobile/main.dart';
import 'package:lapsek_mobile/session_store.dart';

void main() {
  testWidgets('student can sign in and sees only the verified linked profile', (
    tester,
  ) async {
    final api = FakeLaosGateway();
    final store = FakeSessionStore();
    await tester.pumpWidget(LaosApp(api: api, sessionStore: store));
    await tester.pumpAndSettle();

    expect(find.text('Laporan Online Sekolah'), findsOneWidget);
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Email akun siswa'),
      'siswa@example.test',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Kata sandi'),
      'password',
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Masuk'));
    await tester.pumpAndSettle();

    expect(store.token, 'test-token');
    expect(api.requestedStudentIds, [42]);
    expect(find.text('Riwayat pemeriksaan'), findsOneWidget);
    expect(find.text('15-01-2026'), findsOneWidget);
    expect(find.text('Hasil siswa lain'), findsNothing);
  });

  testWidgets('unverified account link does not request or display a report', (
    tester,
  ) async {
    final api = FakeLaosGateway(studentId: null);
    final store = FakeSessionStore(token: 'test-token');
    await tester.pumpWidget(LaosApp(api: api, sessionStore: store));
    await tester.pumpAndSettle();

    expect(find.textContaining('belum terhubung'), findsOneWidget);
    expect(api.requestedStudentIds, isEmpty);
    expect(find.text('Hasil pemeriksaan saya'), findsNothing);
  });

  testWidgets('expired session is cleared and returns to login', (
    tester,
  ) async {
    final api = FakeLaosGateway(
      meError: const LaosApiException('Sesi berakhir.', statusCode: 401),
    );
    final store = FakeSessionStore(token: 'expired-token');
    await tester.pumpWidget(LaosApp(api: api, sessionStore: store));
    await tester.pumpAndSettle();

    expect(store.token, isNull);
    expect(find.text('Laporan Online Sekolah'), findsOneWidget);
    expect(find.text('Sesi berakhir. Silakan masuk kembali.'), findsOneWidget);
  });
}

class FakeSessionStore implements SessionStore {
  FakeSessionStore({this.token});

  String? token;

  @override
  Future<void> clear() async => token = null;

  @override
  Future<String?> readToken() async => token;

  @override
  Future<void> writeToken(String value) async => token = value;
}

class FakeLaosGateway implements LaosGateway {
  FakeLaosGateway({this.studentId = 42, this.meError});

  final int? studentId;
  final LaosApiException? meError;
  final List<int> requestedStudentIds = [];

  @override
  Future<LaosHistoryPage> history(
    String token,
    int studentId, {
    String? academicYear,
    int? semester,
    int page = 1,
  }) async {
    requestedStudentIds.add(studentId);
    return const LaosHistoryPage(
      records: [
        {
          'id': 1,
          'examined_at': '2026-01-15',
          'examiner_name': 'Petugas UKS',
          'status': 'published',
          'school': {'name': 'Sekolah Siswa'},
          'school_class': {'name': 'Kelas 6'},
          'semester': {
            'number': 1,
            'academic_year': {'name': '2025-2026'},
          },
          'observations': {},
        },
      ],
      currentPage: 1,
      lastPage: 1,
      availablePeriods: [
        {'academic_year': '2025-2026', 'semester': 1, 'semester_id': 1},
      ],
    );
  }

  @override
  Future<({LaosUser user, int? studentId})> me(String token) async {
    if (meError != null) throw meError!;
    return (
      user: const LaosUser(
        id: 1,
        name: 'Siswa Uji',
        email: 'siswa@example.test',
        roles: ['student'],
      ),
      studentId: studentId,
    );
  }

  @override
  Future<String> login(String email, String password) async => 'test-token';

  @override
  Future<void> logout(String token) async {}

  @override
  Future<void> requestPasswordReset(String email) async {}
}
