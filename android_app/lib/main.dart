import 'package:flutter/material.dart';
import 'services/api_client.dart';
import 'screens/login_screen.dart';
import 'screens/home_screen.dart';
import 'theme.dart';

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
      theme: buildAppTheme(),
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
          return Scaffold(
            backgroundColor: Colors.white,
            body: Center(
              child: Image.asset('assets/images/logo.png', width: 180),
            ),
          );
        }
        final user = snapshot.data;
        return user != null ? HomeScreen(user: user) : const LoginScreen();
      },
    );
  }
}
