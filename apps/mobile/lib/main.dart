import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:lapsek_mobile/laos_api.dart';
import 'package:lapsek_mobile/session_store.dart';

void main() {
  runApp(const LaosApp());
}

class LaosApp extends StatelessWidget {
  const LaosApp({super.key, this.api, this.sessionStore});

  final LaosGateway? api;
  final SessionStore? sessionStore;

  @override
  Widget build(BuildContext context) {
    final colors = ColorScheme.fromSeed(
      seedColor: const Color(0xFF176B4A),
      brightness: Brightness.light,
    );
    return MaterialApp(
      title: 'LAOS - Laporan Online Sekolah',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: colors,
        scaffoldBackgroundColor: const Color(0xFFF5F8F6),
        useMaterial3: true,
        inputDecorationTheme: const InputDecorationTheme(
          border: OutlineInputBorder(),
          filled: true,
          fillColor: Colors.white,
        ),
      ),
      home: SessionGate(
        api: api ?? LaosApi(),
        store: sessionStore ?? const SecureSessionStore(),
      ),
    );
  }
}

class SessionGate extends StatefulWidget {
  const SessionGate({required this.api, required this.store, super.key});

  final LaosGateway api;
  final SessionStore store;

  @override
  State<SessionGate> createState() => _SessionGateState();
}

class _SessionGateState extends State<SessionGate> {
  String? _token;
  LaosUser? _user;
  int? _studentId;
  bool _checking = true;
  bool _busy = false;
  String? _message;
  bool _sessionUnavailable = false;

  @override
  void initState() {
    super.initState();
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    setState(() {
      _checking = true;
      _sessionUnavailable = false;
      _message = null;
    });
    try {
      final token = await widget.store.readToken();
      if (token == null || token.isEmpty) {
        if (mounted) setState(() => _checking = false);
        return;
      }
      final session = await widget.api.me(token);
      if (!mounted) return;
      setState(() {
        _token = token;
        _user = session.user;
        _studentId = session.studentId;
        _checking = false;
      });
    } on LaosApiException catch (error) {
      if (error.statusCode == 401) {
        await _clearLocalSession(
          message: 'Sesi berakhir. Silakan masuk kembali.',
        );
        return;
      }
      if (mounted) {
        setState(() {
          _checking = false;
          _sessionUnavailable = true;
          _message = error.message;
        });
      }
    } on PlatformException {
      if (mounted) {
        setState(() {
          _checking = false;
          _sessionUnavailable = true;
          _message =
              'Penyimpanan sesi aman tidak dapat dibuka di perangkat ini.';
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _checking = false;
          _sessionUnavailable = true;
          _message =
              'Sesi belum dapat diperiksa. Periksa jaringan lalu coba lagi.';
        });
      }
    }
  }

  Future<void> _signIn(String email, String password) async {
    setState(() {
      _busy = true;
      _message = null;
    });
    try {
      final token = await widget.api.login(email, password);
      final session = await widget.api.me(token);
      await widget.store.writeToken(token);
      if (!mounted) return;
      setState(() {
        _token = token;
        _user = session.user;
        _studentId = session.studentId;
        _sessionUnavailable = false;
        _busy = false;
      });
    } on LaosApiException catch (error) {
      if (mounted) {
        setState(() {
          _busy = false;
          _message = error.message;
        });
      }
    } on PlatformException {
      if (mounted) {
        setState(() {
          _busy = false;
          _message = 'Sesi aman tidak dapat disimpan di perangkat ini.';
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _busy = false;
          _message = 'Login tidak berhasil. Periksa jaringan lalu coba lagi.';
        });
      }
    }
  }

  Future<void> _logout() async {
    final token = _token;
    setState(() => _busy = true);
    String? message;
    try {
      if (token != null) await widget.api.logout(token);
    } on LaosApiException catch (error) {
      if (error.statusCode != 401) {
        message = 'Sesi lokal diakhiri; server belum dapat dihubungi.';
      }
    } catch (_) {
      message = 'Sesi lokal diakhiri; server belum dapat dihubungi.';
    }
    await _clearLocalSession(message: message);
  }

  Future<void> _clearLocalSession({String? message}) async {
    try {
      await widget.store.clear();
    } on PlatformException {
      message =
          'Sesi server berakhir, tetapi token lokal gagal dihapus. '
          'Tutup aplikasi dan hubungi bantuan jika masalah berlanjut.';
    } catch (_) {
      message =
          'Sesi server berakhir, tetapi token lokal gagal dihapus. '
          'Tutup aplikasi dan hubungi bantuan jika masalah berlanjut.';
    }
    if (!mounted) return;
    setState(() {
      _token = null;
      _user = null;
      _studentId = null;
      _checking = false;
      _busy = false;
      _sessionUnavailable = false;
      _message = message;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_checking) {
      return const Scaffold(
        body: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              CircularProgressIndicator(),
              SizedBox(height: 16),
              Text('Memeriksa sesi dengan aman...'),
            ],
          ),
        ),
      );
    }
    if (_sessionUnavailable) {
      return SessionRecoveryScreen(
        message: _message ?? 'Sesi belum dapat diperiksa.',
        onRetry: _restoreSession,
        onSignOut: () => _clearLocalSession(),
      );
    }
    if (_token != null && _user != null) {
      if (_studentId == null) {
        return AccountLinkPendingScreen(
          user: _user!,
          busy: _busy,
          onLogout: _logout,
          onRefresh: _restoreSession,
        );
      }
      return StudentHomeScreen(
        key: ValueKey(_studentId),
        api: widget.api,
        token: _token!,
        user: _user!,
        studentId: _studentId!,
        onLogout: _logout,
        onSessionExpired: () => _clearLocalSession(
          message: 'Sesi berakhir. Silakan masuk kembali.',
        ),
      );
    }
    return LoginScreen(
      api: widget.api,
      busy: _busy,
      message: _message,
      onLogin: _signIn,
    );
  }
}

class LoginScreen extends StatefulWidget {
  const LoginScreen({
    required this.api,
    required this.busy,
    required this.onLogin,
    this.message,
    super.key,
  });

  final LaosGateway api;
  final bool busy;
  final String? message;
  final Future<void> Function(String email, String password) onLogin;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _obscurePassword = true;
  bool _resetBusy = false;
  String? _resetMessage;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _requestReset() async {
    final email = _email.text.trim();
    if (!email.contains('@') || !email.contains('.')) {
      setState(
        () => _resetMessage = 'Masukkan email akun yang valid terlebih dahulu.',
      );
      return;
    }
    setState(() {
      _resetBusy = true;
      _resetMessage = null;
    });
    try {
      await widget.api.requestPasswordReset(email);
      if (mounted) {
        setState(() {
          _resetMessage =
              'Jika akun dapat menerima reset, instruksi akan dikirim ke alamat terdaftar.';
        });
      }
    } on LaosApiException catch (error) {
      if (mounted) setState(() => _resetMessage = error.message);
    } catch (_) {
      if (mounted) {
        setState(() => _resetMessage = 'Permintaan reset belum berhasil.');
      }
    } finally {
      if (mounted) setState(() => _resetBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const _BrandMark(),
                    const SizedBox(height: 28),
                    Text(
                      'Rapor kesehatanmu,',
                      style: theme.textTheme.headlineMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF20352A),
                      ),
                    ),
                    Text(
                      'aman dan mudah dilihat.',
                      style: theme.textTheme.headlineSmall?.copyWith(
                        color: theme.colorScheme.primary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      'Masuk menggunakan akun siswa yang sudah dibuat '
                      'dan diverifikasi oleh sekolah.',
                      style: theme.textTheme.bodyLarge?.copyWith(height: 1.5),
                    ),
                    const SizedBox(height: 28),
                    TextFormField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      autofillHints: const [AutofillHints.username],
                      decoration: const InputDecoration(
                        labelText: 'Email akun siswa',
                        prefixIcon: Icon(Icons.alternate_email),
                      ),
                      validator: (value) {
                        final email = value?.trim() ?? '';
                        return email.contains('@')
                            ? null
                            : 'Masukkan alamat email yang valid.';
                      },
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _password,
                      obscureText: _obscurePassword,
                      textInputAction: TextInputAction.done,
                      autofillHints: const [AutofillHints.password],
                      onFieldSubmitted: (_) => _submit(),
                      decoration: InputDecoration(
                        labelText: 'Kata sandi',
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          tooltip: _obscurePassword
                              ? 'Tampilkan kata sandi'
                              : 'Sembunyikan kata sandi',
                          onPressed: () => setState(
                            () => _obscurePassword = !_obscurePassword,
                          ),
                          icon: Icon(
                            _obscurePassword
                                ? Icons.visibility_outlined
                                : Icons.visibility_off_outlined,
                          ),
                        ),
                      ),
                      validator: (value) => (value?.isNotEmpty ?? false)
                          ? null
                          : 'Kata sandi wajib diisi.',
                    ),
                    if (widget.message != null) ...[
                      const SizedBox(height: 16),
                      _MessageBanner(message: widget.message!, isError: true),
                    ],
                    if (_resetMessage != null) ...[
                      const SizedBox(height: 12),
                      _MessageBanner(message: _resetMessage!),
                    ],
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: widget.busy ? null : _submit,
                      child: Padding(
                        padding: const EdgeInsets.all(13),
                        child: widget.busy
                            ? const SizedBox.square(
                                dimension: 21,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : const Text('Masuk'),
                      ),
                    ),
                    Align(
                      alignment: Alignment.center,
                      child: TextButton(
                        onPressed: widget.busy || _resetBusy
                            ? null
                            : _requestReset,
                        child: Text(
                          _resetBusy
                              ? 'Mengirim permintaan...'
                              : 'Lupa kata sandi?',
                        ),
                      ),
                    ),
                    const SizedBox(height: 14),
                    const _PrivacyNote(
                      text:
                          'Rapor hanya dapat dibuka oleh siswa yang '
                          'akunnya sudah diverifikasi. Data kesehatan '
                          'tidak disimpan di perangkat.',
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  void _submit() {
    if (_formKey.currentState?.validate() ?? false) {
      TextInput.finishAutofillContext();
      unawaited(widget.onLogin(_email.text, _password.text));
    }
  }
}

class SessionRecoveryScreen extends StatelessWidget {
  const SessionRecoveryScreen({
    required this.message,
    required this.onRetry,
    required this.onSignOut,
    super.key,
  });

  final String message;
  final Future<void> Function() onRetry;
  final VoidCallback onSignOut;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Periksa sesi')),
    body: Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off_outlined, size: 48),
            const SizedBox(height: 16),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: const Text('Coba lagi'),
            ),
            TextButton(
              onPressed: onSignOut,
              child: const Text('Akhiri sesi pada perangkat ini'),
            ),
          ],
        ),
      ),
    ),
  );
}

class AccountLinkPendingScreen extends StatelessWidget {
  const AccountLinkPendingScreen({
    required this.user,
    required this.busy,
    required this.onLogout,
    required this.onRefresh,
    super.key,
  });

  final LaosUser user;
  final bool busy;
  final Future<void> Function() onLogout;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('LAOS · Rapor siswa'),
      actions: [
        IconButton(
          tooltip: 'Keluar',
          onPressed: busy ? null : () => unawaited(onLogout()),
          icon: const Icon(Icons.logout),
        ),
      ],
    ),
    body: Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 440),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.verified_user_outlined, size: 52),
              const SizedBox(height: 18),
              Text(
                'Halo, ${user.name}',
                style: Theme.of(context).textTheme.titleLarge,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 10),
              const Text(
                'Akunmu belum terhubung dengan profil siswa yang '
                'terverifikasi. Hubungi Operator Sekolah. Rapor akan '
                'muncul setelah sekolah menyelesaikan verifikasi.',
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: busy ? null : onRefresh,
                icon: const Icon(Icons.refresh),
                label: const Text('Periksa lagi'),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class StudentHomeScreen extends StatefulWidget {
  const StudentHomeScreen({
    required this.api,
    required this.token,
    required this.user,
    required this.studentId,
    required this.onLogout,
    required this.onSessionExpired,
    super.key,
  });

  final LaosGateway api;
  final String token;
  final LaosUser user;
  final int studentId;
  final Future<void> Function() onLogout;
  final VoidCallback onSessionExpired;

  @override
  State<StudentHomeScreen> createState() => _StudentHomeScreenState();
}

class _StudentHomeScreenState extends State<StudentHomeScreen> {
  LaosHistoryPage? _history;
  bool _loading = true;
  bool _loadingMore = false;
  bool _busy = false;
  String? _error;
  String? _academicYear;
  int? _semester;
  List<Map<String, dynamic>> _availablePeriods = const [];

  @override
  void initState() {
    super.initState();
    _loadHistory();
  }

  Future<void> _loadHistory() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final page = await widget.api.history(
        widget.token,
        widget.studentId,
        academicYear: _academicYear,
        semester: _semester,
      );
      if (mounted) {
        setState(() {
          _history = page;
          _availablePeriods = page.availablePeriods;
          _loading = false;
        });
      }
    } on LaosApiException catch (error) {
      if (error.statusCode == 401) {
        widget.onSessionExpired();
        return;
      }
      if (mounted) {
        setState(() {
          _error = error.message;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error =
              'Riwayat belum dapat dimuat. Periksa jaringan lalu coba lagi.';
          _loading = false;
        });
      }
    }
  }

  Future<void> _loadMore() async {
    final current = _history;
    if (current == null ||
        _loadingMore ||
        current.currentPage >= current.lastPage) {
      return;
    }
    setState(() => _loadingMore = true);
    try {
      final next = await widget.api.history(
        widget.token,
        widget.studentId,
        academicYear: _academicYear,
        semester: _semester,
        page: current.currentPage + 1,
      );
      if (mounted) {
        setState(() {
          _history = LaosHistoryPage(
            records: [...current.records, ...next.records],
            currentPage: next.currentPage,
            lastPage: next.lastPage,
            availablePeriods: _availablePeriods,
          );
          _loadingMore = false;
        });
      }
    } on LaosApiException catch (error) {
      if (error.statusCode == 401) {
        widget.onSessionExpired();
        return;
      }
      if (mounted) {
        setState(() {
          _error = error.message;
          _loadingMore = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = 'Riwayat berikutnya belum dapat dimuat.';
          _loadingMore = false;
        });
      }
    }
  }

  Future<void> _logout() async {
    if (_busy) return;
    setState(() => _busy = true);
    await widget.onLogout();
  }

  @override
  Widget build(BuildContext context) {
    final years =
        _availablePeriods
            .map((period) => period['academic_year'])
            .whereType<String>()
            .toSet()
            .toList()
          ..sort((a, b) => b.compareTo(a));
    return Scaffold(
      appBar: AppBar(
        title: const Text('LAOS · Rapor kesehatan'),
        actions: [
          IconButton(
            tooltip: 'Keluar',
            onPressed: _busy ? null : () => unawaited(_logout()),
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _loadHistory,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(18, 12, 18, 30),
          children: [
            _WelcomeCard(user: widget.user),
            const SizedBox(height: 18),
            Text(
              'Riwayat pemeriksaan',
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 4),
            Text(
              'Hanya hasil yang telah dipublikasikan petugas kesehatan.',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<String?>(
                    key: ValueKey('year-$_academicYear-${years.length}'),
                    initialValue: _academicYear,
                    decoration: const InputDecoration(
                      labelText: 'Tahun ajaran',
                      isDense: true,
                    ),
                    items: [
                      const DropdownMenuItem<String?>(
                        child: Text('Semua tahun'),
                      ),
                      ...years.map(
                        (year) => DropdownMenuItem<String?>(
                          value: year,
                          child: Text(year),
                        ),
                      ),
                    ],
                    onChanged: _loading
                        ? null
                        : (value) {
                            setState(() {
                              _academicYear = value;
                              _history = null;
                            });
                            _loadHistory();
                          },
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: DropdownButtonFormField<int?>(
                    key: ValueKey('semester-$_semester'),
                    initialValue: _semester,
                    decoration: const InputDecoration(
                      labelText: 'Semester',
                      isDense: true,
                    ),
                    items: const [
                      DropdownMenuItem<int?>(child: Text('Semua')),
                      DropdownMenuItem<int?>(
                        value: 1,
                        child: Text('Semester 1'),
                      ),
                      DropdownMenuItem<int?>(
                        value: 2,
                        child: Text('Semester 2'),
                      ),
                    ],
                    onChanged: _loading
                        ? null
                        : (value) {
                            setState(() {
                              _semester = value;
                              _history = null;
                            });
                            _loadHistory();
                          },
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            if (_error != null) ...[
              _MessageBanner(message: _error!, isError: true),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: _loadHistory,
                icon: const Icon(Icons.refresh),
                label: const Text('Coba lagi'),
              ),
            ],
            if (_loading)
              const Padding(
                padding: EdgeInsets.all(36),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_history != null && _history!.records.isEmpty)
              const _EmptyHistory()
            else if (_history != null) ...[
              ..._history!.records.map(
                (record) => _ExaminationCard(
                  record: record,
                  onTap: () => _showDetails(context, record),
                ),
              ),
              if (_history!.currentPage < _history!.lastPage)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: OutlinedButton(
                    onPressed: _loadingMore ? null : _loadMore,
                    child: _loadingMore
                        ? const SizedBox.square(
                            dimension: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Muat histori sebelumnya'),
                  ),
                ),
            ],
            const SizedBox(height: 20),
            const _PrivacyNote(
              text:
                  'Rapor ini adalah catatan pemeriksaan, bukan diagnosis. '
                  'Jika ada hal yang ingin ditanyakan, diskusikan dengan '
                  'orang tua/wali dan petugas kesehatan.',
            ),
          ],
        ),
      ),
    );
  }

  void _showDetails(BuildContext context, Map<String, dynamic> record) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => _ExaminationDetails(record: record),
    );
  }
}

class _BrandMark extends StatelessWidget {
  const _BrandMark();

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Container(
        width: 52,
        height: 52,
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.primary,
          borderRadius: BorderRadius.circular(16),
        ),
        child: const Icon(Icons.health_and_safety, color: Colors.white),
      ),
      const SizedBox(width: 12),
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'LAOS',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              fontWeight: FontWeight.w900,
              letterSpacing: 1.5,
              color: const Color(0xFF20352A),
            ),
          ),
          const Text('Laporan Online Sekolah'),
        ],
      ),
    ],
  );
}

class _WelcomeCard extends StatelessWidget {
  const _WelcomeCard({required this.user});

  final LaosUser user;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: const Color(0xFFE9F4ED),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Row(
      children: [
        CircleAvatar(
          backgroundColor: Colors.white,
          foregroundColor: Theme.of(context).colorScheme.primary,
          child: const Icon(Icons.person_outline),
        ),
        const SizedBox(width: 13),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Selamat datang'),
              Text(
                user.name,
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _ExaminationCard extends StatelessWidget {
  const _ExaminationCard({required this.record, required this.onTap});

  final Map<String, dynamic> record;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final semester = record['semester'];
    final academicYearData = semester is Map ? semester['academic_year'] : null;
    final academicYear = academicYearData is Map
        ? academicYearData['name'] as String?
        : null;
    final number = semester is Map ? semester['number'] : null;
    final school = record['school'];
    final schoolName = school is Map ? school['name'] as String? : null;
    final schoolClass = record['school_class'];
    final className = schoolClass is Map
        ? schoolClass['name'] as String?
        : null;
    final examinedAt = record['examined_at'] as String? ?? '';
    return Card(
      margin: const EdgeInsets.only(bottom: 11),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.all(15),
          child: Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: const Color(0xFFEAF4ED),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(
                  Icons.health_and_safety_outlined,
                  color: Theme.of(context).colorScheme.primary,
                ),
              ),
              const SizedBox(width: 13),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _formatDate(examinedAt),
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      [
                        ?academicYear,
                        if (number != null) 'Semester $number',
                      ].join(' · '),
                    ),
                    Text(
                      [?schoolName, ?className].join(' · '),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}

class _ExaminationDetails extends StatelessWidget {
  const _ExaminationDetails({required this.record});

  final Map<String, dynamic> record;

  @override
  Widget build(BuildContext context) {
    final observations = record['observations'];
    final values = observations is Map
        ? Map<String, dynamic>.from(observations)
        : const <String, dynamic>{};
    final keys = values.keys.toList()..sort();
    return SafeArea(
      child: DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.78,
        maxChildSize: 0.94,
        builder: (context, controller) => ListView(
          controller: controller,
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 28),
          children: [
            Text(
              'Detail rapor kesehatan',
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            Text(
              '${record['school'] is Map ? (record['school'] as Map)['name'] ?? '' : ''}'
              ' · ${_formatDate(record['examined_at'] as String? ?? '')}',
            ),
            const SizedBox(height: 16),
            _DetailRow(label: 'Status', value: 'Terpublikasi'),
            _DetailRow(
              label: 'Pemeriksa',
              value: record['examiner_name'] as String? ?? 'Tidak dicantumkan',
            ),
            _DetailRow(
              label: 'IMT',
              value: record['bmi'] as String? ?? 'Belum dihitung',
            ),
            const Divider(height: 28),
            Text(
              'Hasil indikator',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            if (keys.isEmpty)
              const Text('Belum ada detail indikator yang tersedia.')
            else
              ...keys.map((key) {
                final value = values[key];
                final observation = value is Map
                    ? Map<String, dynamic>.from(value)
                    : const <String, dynamic>{};
                return _DetailRow(
                  label: _indicatorLabels[key] ?? _humanize(key),
                  value: _formatObservation(observation),
                );
              }),
            const SizedBox(height: 12),
            const _PrivacyNote(
              text:
                  'Informasi ini adalah catatan pemeriksaan, bukan diagnosis '
                  'atau pengganti konsultasi tenaga kesehatan.',
            ),
          ],
        ),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 9),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(child: Text(label)),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );
}

class _EmptyHistory extends StatelessWidget {
  const _EmptyHistory();

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 36, horizontal: 18),
    child: Column(
      children: [
        Icon(
          Icons.event_note_outlined,
          size: 46,
          color: Theme.of(context).colorScheme.primary,
        ),
        const SizedBox(height: 12),
        Text(
          'Belum ada rapor pada periode ini',
          style: Theme.of(context).textTheme.titleMedium,
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 5),
        const Text(
          'Hasil akan muncul di sini setelah pemeriksaan dipublikasikan.',
          textAlign: TextAlign.center,
        ),
      ],
    ),
  );
}

class _MessageBanner extends StatelessWidget {
  const _MessageBanner({required this.message, this.isError = false});

  final String message;
  final bool isError;

  @override
  Widget build(BuildContext context) => Semantics(
    liveRegion: true,
    child: Container(
      width: double.infinity,
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: isError ? const Color(0xFFFFEFED) : const Color(0xFFEAF4ED),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Text(
        message,
        style: TextStyle(
          color: isError ? const Color(0xFF8F3028) : const Color(0xFF285A3A),
        ),
      ),
    ),
  );
}

class _PrivacyNote extends StatelessWidget {
  const _PrivacyNote({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: const Color(0xFFEEF3F0),
      borderRadius: BorderRadius.circular(10),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.info_outline, size: 19),
        const SizedBox(width: 9),
        Expanded(child: Text(text, style: const TextStyle(height: 1.4))),
      ],
    ),
  );
}

const _indicatorLabels = <String, String>{
  'blood_pressure': 'Tekanan darah',
  'past_disease_history': 'Riwayat penyakit dahulu',
  'family_disease_history': 'Riwayat penyakit keluarga',
  'personal_hygiene': 'Kebersihan diri',
  'breakfast': 'Kebiasaan sarapan',
  'smoking': 'Paparan/merokok',
  'snacks': 'Kebiasaan jajan',
  'alcohol_narcotics': 'Alkohol/NAPZA',
  'height_cm': 'Tinggi badan (cm)',
  'weight_kg': 'Berat badan (kg)',
  'nutrition_status': 'Status gizi (catatan petugas)',
  'anemia': 'Pemeriksaan anemia',
  'eyes_external': 'Pemeriksaan mata luar',
  'color_blindness': 'Buta warna',
  'uses_glasses': 'Penggunaan kacamata',
  'ears_external': 'Pemeriksaan telinga luar',
  'hearing': 'Pendengaran',
  'cleft_lip_palate': 'Bibir/langit-langit sumbing',
  'mouth_corner_lesion': 'Lesi sudut mulut',
  'mouth_ulcer': 'Sariawan',
  'dirty_tongue': 'Kebersihan lidah',
  'other_lesion': 'Lesi mulut lainnya',
  'dental_caries': 'Karies gigi',
  'bleeding_gums': 'Gusi berdarah',
  'swollen_gums': 'Gusi bengkak',
  'dental_plaque': 'Plak gigi',
  'tartar': 'Karang gigi',
  'irregular_front_teeth': 'Susunan gigi depan',
};

String _formatObservation(Map<String, dynamic> observation) {
  if (observation['status'] == 'not_examined') return 'Belum diperiksa';
  final value = observation['value'];
  if (value == null) return 'Belum diperiksa';
  if (value is bool) return value ? 'Ya' : 'Tidak';
  if (value is List) return value.join(', ');
  if (value is Map) return value.values.join(', ');
  final raw = value.toString();
  return switch (raw) {
    'good' => 'Baik',
    'fair' => 'Cukup',
    'poor' => 'Kurang',
    'healthy' => 'Sehat',
    'unhealthy' => 'Tidak sehat',
    'infection' => 'Infeksi',
    'cerumen' => 'Serumen',
    'normal' => 'Normal',
    'impaired' => 'Terganggu',
    'very_thin' => 'Sangat kurus',
    'thin' => 'Kurus',
    'overweight' => 'Berat badan berlebih',
    'obesity' => 'Obesitas',
    _ => raw,
  };
}

String _humanize(String key) => key
    .split('_')
    .map(
      (part) =>
          part.isEmpty ? part : '${part[0].toUpperCase()}${part.substring(1)}',
    )
    .join(' ');

String _formatDate(String value) {
  final date = DateTime.tryParse(value);
  if (date == null) return value.isEmpty ? 'Tanggal tidak tersedia' : value;
  final day = date.day.toString().padLeft(2, '0');
  final month = date.month.toString().padLeft(2, '0');
  return '$day-$month-${date.year}';
}
