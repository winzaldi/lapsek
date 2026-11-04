import 'package:flutter_test/flutter_test.dart';

import 'package:lapsek_mobile/main.dart';

void main() {
  testWidgets('foundation screen does not expose student data', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(const LaosApp());

    expect(find.text('Laporan Online Sekolah'), findsOneWidget);
    expect(
      find.text('Lingkungan pengembangan - belum terhubung ke data siswa.'),
      findsOneWidget,
    );
  });
}
