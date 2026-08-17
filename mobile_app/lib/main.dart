import 'package:flutter/material.dart';
import 'services/api_client.dart';
import 'screens/login_screen.dart';
import 'screens/home_screen.dart';

void main() {
  runApp(const ManageMoScannerApp());
}

class ManageMoScannerApp extends StatelessWidget {
  const ManageMoScannerApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'ManageMo Delivery Scanner',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF8B0000)),
        useMaterial3: true,
        inputDecorationTheme: const InputDecorationTheme(
          filled: true,
          fillColor: Colors.white,
        ),
      ),
      home: const _StartupGate(),
    );
  }
}

/// Decides whether to show the login screen or jump straight to home,
/// based on whether a saved (and still valid) session token exists.
class _StartupGate extends StatefulWidget {
  const _StartupGate();

  @override
  State<_StartupGate> createState() => _StartupGateState();
}

class _StartupGateState extends State<_StartupGate> {
  @override
  Widget build(BuildContext context) {
    return FutureBuilder<AppUser?>(
      future: ApiClient.getSavedUser(),
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Scaffold(body: Center(child: CircularProgressIndicator()));
        }
        final user = snapshot.data;
        return user != null ? HomeScreen(user: user) : const LoginScreen();
      },
    );
  }
}
