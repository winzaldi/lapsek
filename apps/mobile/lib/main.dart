import 'package:flutter/material.dart';

void main() {
  runApp(const LaosApp());
}

class LaosApp extends StatelessWidget {
  const LaosApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'LAOS - Laporan Online Sekolah',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF157A55),
          brightness: Brightness.light,
        ),
        scaffoldBackgroundColor: const Color(0xFFF4F7F5),
      ),
      home: const FoundationScreen(),
    );
  }
}

class FoundationScreen extends StatelessWidget {
  const FoundationScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('LAOS'),
        backgroundColor: Theme.of(context).colorScheme.surface,
      ),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 480),
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Laporan Online Sekolah',
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: Theme.of(context).colorScheme.primary,
                      letterSpacing: 1.2,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Fondasi aplikasi siap dikembangkan.',
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Rapor kesehatan siswa akan tersedia setelah API, '
                    'autentikasi, dan aturan akses disiapkan.',
                    style: Theme.of(
                      context,
                    ).textTheme.bodyLarge?.copyWith(height: 1.55),
                  ),
                  const SizedBox(height: 24),
                  const _EnvironmentNotice(),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _EnvironmentNotice extends StatelessWidget {
  const _EnvironmentNotice();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
        borderRadius: BorderRadius.circular(12),
      ),
      child: const Text(
        'Lingkungan pengembangan - belum terhubung ke data siswa.',
      ),
    );
  }
}
